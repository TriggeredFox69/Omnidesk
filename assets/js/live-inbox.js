(() => {
  window.NunnoLiveInbox = function ({ state, getLead, renderList, renderChat, toast, stopAudio }) {
    let cursor = 0, busy = false, epoch = '', activePhone = '', sendingMedia = false;
    const mediaRequests = new Map();
    async function api(action, data = {}) {
      const response = await fetch('api/direct_sender.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action, ...data }), signal: AbortSignal.timeout(action === 'send_direct' ? 90000 : action === 'get_media' ? 35000 : 15000) });
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.message || 'WhatsApp request failed.');
      return result;
    }
    function merge(item) {
      let lead = state.leads.find(l => l.phone === item.phone);
      if (!lead) {
        lead = { id: 'wa_lead_' + item.phone, phone: item.phone, display: '+' + item.phone,
          name: item.name || item.phone, city: '', network: '', ownerId: null, leadState: 'fresh',
          status: 'unread', unreadCount: 0, interest: '', notes: '', messages: [] };
        state.leads.unshift(lead);
      }
      const previous = lead.messages.find(m => m.waMessageId === item.waMessageId);
      if (lead.name === lead.phone && item.sender === 'lead' && item.name) lead.name = item.name;
      const fields = { ...item, time: new Date(item.timestamp * 1000).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
        duration: item.durationSeconds ? `${Math.floor(item.durationSeconds / 60)}:${String(item.durationSeconds % 60).padStart(2,'0')}` : undefined };
      if (previous) Object.assign(previous, fields, { id: previous.id, agentName: previous.agentName || item.agentName });
      else {
        lead.messages.push(fields);
        if (item.sender === 'lead' && (state.activeLeadId !== lead.id || state.currentTab !== 'inbox' || document.hidden)) {
          lead.unreadCount = (lead.unreadCount || 0) + 1; lead.status = 'unread';
        }
      }
      lead.messages.sort((a,b) => (a.timestamp || 0)-(b.timestamp || 0));
      lead.lastMessageAt = Math.max(lead.lastMessageAt || 0, item.timestamp);
      // Message time is not last-seen presence.
      if (!state.activeLeadId) state.activeLeadId = lead.id;
      return lead;
    }
    async function loadMedia(message) {
      if (message.audioUrl || message.imageUrl || !message.waMessageId) return;
      if (!mediaRequests.has(message.waMessageId)) {
        mediaRequests.set(message.waMessageId, api('get_media', { message_id: message.waMessageId }).then(result => {
          if (!/^data:(image\/(png|jpeg|webp|gif)|audio\/[a-z0-9.+-]+)(;[^,]*)?;base64,/i.test(result.data_url)) throw new Error('Unsupported attachment.');
          message[message.type === 'audio' ? 'audioUrl' : 'imageUrl'] = result.data_url;
          message.mediaError = '';
        }).catch(error => { message.mediaError = error.message; throw error; }).finally(() => mediaRequests.delete(message.waMessageId)));
      }
      return mediaRequests.get(message.waMessageId);
    }
    async function poll() {
      if (busy || !state.currentUser || !state.senderConfigured || window.NUNNO_OFFLINE_PREVIEW) return;
      busy = true;
      try {
        const result = await api('get_events', { after: cursor });
        if (epoch && epoch !== result.epoch) { cursor = 0; epoch = result.epoch; return; }
        epoch = result.epoch;
        let activeChanged = false;
        for (const item of result.messages || []) {
          const lead = merge(item); if (lead.id === state.activeLeadId) activeChanged = true;
        }
        cursor = result.cursor;
        if (result.messages?.length) { renderList(); if (activeChanged) renderChat(); }
        const lead = getLead(state.activeLeadId);
        if (lead && state.currentTab === 'inbox') {
          if (activePhone !== lead.phone) {
            activePhone = lead.phone;
            await api('sync_chat', { phone: lead.phone }).catch(() => {});
          }
          const presence = await api('get_presence', { phone: lead.phone }).catch(() => ({ state: 'unknown', label: 'Online / last seen unavailable' }));
          const presenceEl = document.getElementById('chatPresence');
          if (getLead(state.activeLeadId)?.phone === lead.phone && presenceEl) {
            presenceEl.textContent = presence.label;
            presenceEl.className = 'chat-presence presence-' + presence.state;
          }
          const image = lead.messages.find(m => m.type === 'image' && m.hasMedia && !m.imageUrl && !m.mediaError);
          if (image && !mediaRequests.has(image.waMessageId)) loadMedia(image).catch(() => {}).finally(() => { if (state.activeLeadId === lead.id) renderChat(); });
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
      if (!match || match[2].length > 14*1024*1024) return toast('Choose an audio or image file up to 10 MB.');
      const type = match[1].startsWith('audio/') ? 'audio' : 'image';
      const message = { id: 'media_' + crypto.randomUUID(), sender: 'agent', agentName: user?.name || 'Agent',
        type, text: caption || (type === 'audio' ? 'Voice note' : 'Image'), deliveryStatus: 'sending',
        timestamp: Math.floor(Date.now()/1000), time: new Date().toLocaleTimeString([], { hour:'2-digit', minute:'2-digit' }),
        durationSeconds: duration, [type === 'audio' ? 'audioUrl' : 'imageUrl']: dataUrl };
      lead.messages.push(message); sendingMedia = true; renderList(); renderChat();
      try {
        const result = await api('send_direct', { phone: lead.phone, message: caption, request_id: message.id,
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
    setInterval(poll, 2000);
    return { poll, loadMedia, sendMedia };
  };
})();
