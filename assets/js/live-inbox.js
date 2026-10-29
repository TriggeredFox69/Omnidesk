(() => {
  window.NunnoLiveInbox = function ({ state, getLead, renderList, renderChat, toast, stopAudio }) {
    let cursor = 0, busy = false, epoch = '', activePhone = '', sendingMedia = false, lastPresenceAt = 0, hydrated = false;
    const mediaRequests = new Map();
    async function api(action, data = {}) {
      const response = await fetch('api/direct_sender.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action, ...data }), signal: AbortSignal.timeout(action === 'send_direct' ? 90000 : action === 'get_media' ? 35000 : 15000) });
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.message || 'WhatsApp request failed.');
      return result;
    }
    const cleanDigits = p => {
      let c = String(p || '').replace(/\D/g, '');
      if (c.startsWith('0092')) c = c.slice(2);
      else if (c.startsWith('03') && c.length === 11) c = '92' + c.slice(1);
      else if (c.startsWith('3') && c.length === 10) c = '92' + c;
      return c;
    };
    function merge(item) {
      const itemPhone = cleanDigits(item.phone);
      let lead = state.leads.find(l => cleanDigits(l.phone) === itemPhone);
      if (!lead) {
        const contactName = (item.name && item.name !== item.phone) ? item.name : (itemPhone ? '+' + itemPhone : item.phone);
        lead = { id: 'wa_lead_' + (itemPhone || item.phone), phone: itemPhone || item.phone, display: '+' + (itemPhone || item.phone),
          name: contactName, city: '', network: '', ownerId: null, leadState: 'fresh',
          status: 'unread', unreadCount: 0, interest: '', notes: '', messages: [] };
        state.leads.unshift(lead);
        // Persist newly discovered WhatsApp conversation to database
        fetch('api/leads_sync.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'add_lead', id: lead.id, phone: lead.phone, name: lead.name })
        }).catch(() => {});
      }
      const previous = lead.messages.find(m => m.waMessageId === item.waMessageId);
      // Contact names are user-managed only; do not auto-override with WhatsApp push names
      const fields = { ...item, time: new Date(item.timestamp * 1000).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
        duration: item.durationSeconds ? `${Math.floor(item.durationSeconds / 60)}:${String(item.durationSeconds % 60).padStart(2,'0')}` : undefined };
      if (previous) Object.assign(previous, fields, { id: previous.id, agentName: previous.agentName || item.agentName });
      else {
        lead.messages.push(fields);
        if (hydrated && item.sender === 'lead' && (state.activeLeadId !== lead.id || state.currentTab !== 'inbox' || document.hidden)) {
          lead.unreadCount = (lead.unreadCount || 0) + 1; lead.status = 'unread';
        }
        // Save new message to server database so leads.json stays in full duplex sync
        fetch('api/leads_sync.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'update_lead', id: lead.id, phone: lead.phone, newMessage: fields, lastActive: 'Just now' })
        }).catch(() => {});
      }
      lead.messages.sort((a,b) => (a.timestamp || 0)-(b.timestamp || 0));
      lead.lastMessageAt = Math.max(lead.lastMessageAt || 0, item.timestamp);
      // Message time is not last-seen presence.
      if (!state.activeLeadId) state.activeLeadId = lead.id;
      return lead;
    }
    async function loadMedia(message) {
      if (message.audioUrl || message.imageUrl || message.videoUrl || message.docUrl || !message.waMessageId) return;
      if (!mediaRequests.has(message.waMessageId)) {
        mediaRequests.set(message.waMessageId, api('get_media', { message_id: message.waMessageId }).then(result => {
          if (!/^data:(image|audio|video|application|text)\/[a-z0-9.+-]+(;[^,]*)?;base64,/i.test(result.data_url)) throw new Error('Unsupported attachment.');
          if (message.type === 'audio') message.audioUrl = result.data_url;
          else if (message.type === 'image') message.imageUrl = result.data_url;
          else if (message.type === 'video') message.videoUrl = result.data_url;
          else message.docUrl = result.data_url;
          if (result.filename) message.filename = result.filename;
          message.mediaError = '';
        }).catch(error => { message.mediaError = error.message; throw error; }).finally(() => mediaRequests.delete(message.waMessageId)));
      }
      return mediaRequests.get(message.waMessageId);
    }
    async function poll() {
      if (busy || !state.currentUser || window.NUNNO_OFFLINE_PREVIEW) return;
      busy = true;
      try {
        const result = await api('get_events', { after: cursor });
        if (epoch && epoch !== result.epoch) { cursor = 0; epoch = result.epoch; hydrated = false; return; }
        epoch = result.epoch;
        let activeChanged = false;
        for (const item of result.messages || []) {
          const lead = merge(item); if (lead.id === state.activeLeadId) activeChanged = true;
        }
        cursor = result.cursor;
        hydrated = true;
        if (result.messages?.length) { renderList(); if (activeChanged) renderChat(); }
        // Saved history is available while WhatsApp reconnects. Only live operations
        // need a linked sender; hiding the history made a restart look like data loss.
        if (!state.senderConfigured) {
          activePhone = '';
          const el = document.getElementById('chatPresence');
          if (el) { el.textContent = 'Saved history · reconnect WhatsApp for live updates'; el.className = 'chat-presence'; }
          return;
        }
        const lead = getLead(state.activeLeadId);
        if (lead && state.currentTab === 'inbox') {
          if (activePhone !== lead.phone) {
            activePhone = lead.phone;
            // Request a focused backfill without holding up the live event cursor.
            api('sync_chat', { phone: lead.phone }).then(() => poll()).catch(() => {});
          }
          // Presence is useful, but polling it on every inbox tick needlessly delays
          // message sync. Refresh it independently at a relaxed cadence.
          if (Date.now() - lastPresenceAt > 15000) {
            lastPresenceAt = Date.now();
            api('get_presence', { phone: lead.phone }).then(presence => {
              const presenceEl = document.getElementById('chatPresence');
              if (getLead(state.activeLeadId)?.phone === lead.phone && presenceEl) {
                presenceEl.textContent = presence.label;
                presenceEl.className = 'chat-presence presence-' + presence.state;
              }
            }).catch(() => {});
          }
          const unhandled = lead.messages.find(m => (m.type === 'image' || m.type === 'video' || m.type === 'document' || m.type === 'audio') && m.hasMedia && !m.imageUrl && !m.videoUrl && !m.docUrl && !m.audioUrl && !m.mediaError);
          if (unhandled && !mediaRequests.has(unhandled.waMessageId)) loadMedia(unhandled).catch(() => {}).finally(() => { if (state.activeLeadId === lead.id) renderChat(); });
        }
      } catch (_) {
        const el = document.getElementById('chatPresence');
        if (el) { el.textContent = 'Connection unavailable'; el.className = 'chat-presence'; }
      } finally { busy = false; }
    }
    async function sendMedia(dataUrl, { voice = false, duration = 0, filename = 'attachment', caption = '', leadId } = {}) {
      const lead = getLead(leadId || state.activeLeadId);
      if (!lead) return toast('Select a conversation first.');
      const user = state.currentUser;
      if (lead.ownerId && lead.ownerId !== user?.id && user?.role !== 'admin') return toast('This conversation is assigned to another agent.');
      if (sendingMedia) return toast('An attachment is already being sent.');
      const match = /^data:([^,]+);base64,(.+)$/s.exec(dataUrl || '');
      if (!match || match[2].length > 14*1024*1024) return toast('Choose an attachment up to 10 MB.');
      const mime = match[1].toLowerCase();
      const type = mime.startsWith('audio/') ? 'audio' : (mime.startsWith('image/') ? 'image' : (mime.startsWith('video/') ? 'video' : 'document'));
      const message = { id: 'media_' + crypto.randomUUID(), sender: 'agent', agentName: user?.name || 'Agent',
        type, text: caption || (type === 'audio' ? 'Voice note' : (type === 'image' ? 'Image' : (type === 'video' ? 'Video' : filename || 'Document'))), deliveryStatus: 'sending',
        timestamp: Math.floor(Date.now()/1000), time: new Date().toLocaleTimeString([], { hour:'2-digit', minute:'2-digit' }),
        durationSeconds: duration, [type === 'audio' ? 'audioUrl' : (type === 'image' ? 'imageUrl' : (type === 'video' ? 'videoUrl' : 'docUrl'))]: dataUrl, filename };
      lead.messages.push(message); sendingMedia = true; renderList(); renderChat();
      try {
        const result = await api('send_direct', { phone: lead.phone, message: caption || '', request_id: message.id,
          media: { mimetype: match[1], data: match[2], filename, voice } });
        const observed = lead.messages.find(m => m !== message && m.waMessageId === result.message_id);
        message.waMessageId = result.message_id; message.deliveryStatus = observed?.deliveryStatus || 'accepted';
        // Incoming event polling may have already seen the same outgoing message.
        lead.messages = lead.messages.filter(m => m === message || m.waMessageId !== result.message_id);
        toast('Attachment submitted to WhatsApp; waiting for delivery receipt.');
      } catch (error) {
        message.deliveryStatus = /unconfirmed|unknown|did not respond/i.test(error.message) ? 'unknown' : 'failed';
        message.deliveryError = error.message; toast(error.message);
      } finally { sendingMedia = false; renderList(); if (state.activeLeadId === lead.id) renderChat(); }
    }
    // The lightweight cursor endpoint is polled frequently for near-live two-way
    // updates. Media, chat backfills and presence stay off the hot path.
    setInterval(poll, 800);
    return { poll, loadMedia, sendMedia };
  };
})();
