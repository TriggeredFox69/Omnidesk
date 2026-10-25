'use strict';
async function readPresence(client, phone) {
  if (!/^\d{10,15}$/.test(phone) || !client?.pupPage) return { success: true, state: 'unknown', label: 'Online / last seen unavailable' };
  const id = await client.getNumberId(phone);
  if (!id) return { success: true, state: 'unknown', label: 'Online / last seen unavailable' };
  let timer;
  try { return await Promise.race([client.pupPage.evaluate(async chatId => {
    const unknown = { success: true, state: 'unknown', label: 'Online / last seen unavailable' };
    try {
      const chat = await window.WWebJS.getChat(chatId, { getAsModel: false });
      const p = chat?.presence;
      if (!p) return unknown;
      if (window.nunnoObservedPresence !== p) {
        window.nunnoObservedPresence?.decObservers?.();
        window.nunnoObservedPresence = p;
        p.incObservers?.();
        await p.subscribe();
      }
      if (!p.hasData || p.stale) return unknown;
      const type = p.chatstate?.type;
      const label = p.getUserSubtitleText?.();
      if (!label) return unknown;
      return { success: true, state: type === 'available' ? 'online' : type === 'typing' ? 'typing' : type === 'recording_audio' ? 'recording' : 'offline', label: String(label) };
    } catch (_) { return unknown; }
  }, id._serialized), new Promise(resolve => { timer = setTimeout(() => resolve({ success: true, state: 'unknown', label: 'Online / last seen unavailable' }), 5000); })]); }
  finally { clearTimeout(timer); }
}
module.exports = { readPresence };
