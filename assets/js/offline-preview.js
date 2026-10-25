/* File-only campaign sandbox. Never enabled on the hosted application. */
(() => {
  if (location.protocol !== 'file:') return;
  window.NUNNO_OFFLINE_PREVIEW = true;
  const originalFetch = window.fetch.bind(window);
  const user = { id: 'offline-admin', name: 'Offline Tester', username: 'offline', role: 'admin', designation: 'Local test' };
  const leads = new Map();
  const messages = new Map();
  const requests = new Map();
  const reply = (data, status = 200) => Promise.resolve(new Response(JSON.stringify(data), { status, headers: { 'Content-Type': 'application/json' } }));
  window.fetch = (resource, options = {}) => {
    const url = new URL(typeof resource === 'string' ? resource : resource.url, location.href);
    if (!url.pathname.includes('/api/')) return originalFetch(resource, options);
    let body = {};
    try { body = JSON.parse(options.body || '{}'); } catch (_) { return reply({ success: false, message: 'Invalid test request.' }, 400); }
    const action = body.action || url.searchParams.get('action');
    if (url.pathname.endsWith('/auth.php')) {
      if (action === 'check' || action === 'login') return reply({ success: true, authenticated: true, user });
      if (action === 'logout') return reply({ success: true });
      if (action === 'list_employees') return reply({ success: true, employees: [] });
      return reply({ success: false, message: 'Employee administration is unavailable in the local campaign sandbox.' }, 400);
    }
    if (url.pathname.endsWith('/leads_sync.php')) {
      if (action === 'get_leads') return reply({ success: true, leads: [...leads.values()] });
      if (action === 'update_lead') {
        const lead = { ...(leads.get(body.id) || { messages: [] }), ...body };
        if (body.newMessage && !lead.messages.some(m => m.id === body.newMessage.id)) lead.messages = [...lead.messages, body.newMessage];
        if (body.updateMessage) lead.messages = lead.messages.map(m => m.waMessageId === body.updateMessage.waMessageId ? { ...m, ...body.updateMessage } : m);
        leads.set(body.id, lead);
        return reply({ success: true });
      }
    }
    if (url.pathname.endsWith('/direct_sender.php')) {
      if (action === 'get_sender_info' || action === 'connect_whatsapp') return reply({ success: true,
        is_configured: true, is_connected: false, state: 'ready', mode: 'offline_preview', connected_number: 'Offline simulation',
        message: 'Offline test mode. No WhatsApp account is connected and no real messages will be sent.' });
      if (action === 'send_direct') {
        if (!body.message?.trim() || !body.phone) return reply({ success: false, message: 'Recipient and message are required.' }, 400);
        let result = requests.get(body.request_id);
        if (!result) {
          const id = `simulation-${messages.size + 1}`;
          messages.set(id, { ...body, simulated: true, message_id: id });
          result = { success: true, simulated: true, dispatch_mode: 'offline_preview', message_id: id };
          requests.set(body.request_id, result);
        }
        return reply(result);
      }
      if (action === 'get_send_status') return reply({ success: true, simulated: true, status: messages.has(body.message_id) ? 'simulated' : 'accepted' });
      if (action === 'get_logs') return reply({ success: true, logs: [...messages.values()] });
    }
    return reply({ success: false, message: 'This action is not available in the offline campaign sandbox.' }, 400);
  };
})();
