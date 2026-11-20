/* Connected WhatsApps widget (campaign screens).
 * One card per Team Member whose WhatsApp is linked, with a 3-light status:
 *   RED    = idle: no message sent / no progress
 *   ORANGE = working: this WhatsApp is sending its share right now
 *   GREEN  = done: this WhatsApp has sent its messages
 * Usage: ChatlyWaWidget.render(containerEl, senders, campaignStatus)
 *   senders        = list from get_campaign_senders (connected TMs)
 *   campaignStatus = latest get_campaign_status payload (or null)
 */
(function () {
  if (window.ChatlyWaWidget) return;

  const css = `
  .cw-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(250px, 1fr)); gap:14px; }
  .cw-card { position:relative; overflow:hidden; border-radius:18px; padding:16px; background:linear-gradient(160deg, rgba(255,255,255,0.05), rgba(255,255,255,0.015)); border:1px solid rgba(255,255,255,0.09); transition:border-color .3s ease, box-shadow .3s ease, transform .2s ease; }
  .cw-card:hover { transform:translateY(-2px); }
  .cw-card::before { content:''; position:absolute; inset:0 0 auto 0; height:3px; background:var(--cw-c, #ef4444); opacity:.9; }
  .cw-card.cw-red    { --cw-c:#ef4444; }
  .cw-card.cw-orange { --cw-c:#f59e0b; border-color:rgba(245,158,11,0.45); box-shadow:0 0 0 1px rgba(245,158,11,0.15), 0 10px 30px rgba(245,158,11,0.12); }
  .cw-card.cw-green  { --cw-c:#22c55e; border-color:rgba(34,197,94,0.4); box-shadow:0 10px 30px rgba(34,197,94,0.1); }
  .cw-top { display:flex; align-items:center; gap:12px; }
  .cw-avatar { width:44px; height:44px; border-radius:14px; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-weight:900; font-size:17px; color:#fff; background:linear-gradient(135deg, #25d366, #0f9d58); box-shadow:0 6px 16px rgba(37,211,102,0.25); }
  .cw-avatar.tl { background:linear-gradient(135deg, #a855f7, #7c3aed); box-shadow:0 6px 16px rgba(168,85,247,0.25); }
  .cw-id { min-width:0; flex:1; }
  .cw-name { font-weight:800; font-size:14px; color:#fff; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .cw-role { font-size:11px; font-weight:700; color:#94a3b8; margin-top:1px; }
  .cw-num { margin-top:12px; font-family:'JetBrains Mono', ui-monospace, monospace; font-size:15px; font-weight:800; letter-spacing:.02em; color:#38bdf8; }
  .cw-light { display:flex; flex-direction:column; gap:4px; padding:5px; border-radius:10px; background:#05090a; border:1px solid rgba(255,255,255,0.08); }
  .cw-bulb { width:10px; height:10px; border-radius:50%; opacity:.18; transition:opacity .3s ease, box-shadow .3s ease; }
  .cw-bulb.r { background:#ef4444; } .cw-bulb.o { background:#f59e0b; } .cw-bulb.g { background:#22c55e; }
  .cw-red .cw-bulb.r    { opacity:1; box-shadow:0 0 10px #ef4444; }
  .cw-orange .cw-bulb.o { opacity:1; box-shadow:0 0 12px #f59e0b; animation:cwPulse .9s ease-in-out infinite alternate; }
  .cw-green .cw-bulb.g  { opacity:1; box-shadow:0 0 12px #22c55e; }
  @keyframes cwPulse { from { opacity:.45; } to { opacity:1; } }
  .cw-state { margin-top:10px; display:flex; align-items:center; justify-content:space-between; gap:8px; font-size:11.5px; }
  .cw-tag { font-weight:800; padding:3px 9px; border-radius:999px; background:color-mix(in srgb, var(--cw-c) 16%, transparent); color:var(--cw-c); }
  .cw-count { color:#cbd5e1; font-weight:700; font-family:'JetBrains Mono', ui-monospace, monospace; }
  .cw-bar { margin-top:8px; height:6px; border-radius:99px; background:rgba(255,255,255,0.07); overflow:hidden; }
  .cw-bar i { display:block; height:100%; width:0; background:var(--cw-c); border-radius:99px; transition:width .5s ease; }
  .cw-legend { display:flex; gap:14px; flex-wrap:wrap; margin:0 0 12px; font-size:11.5px; color:#94a3b8; }
  .cw-legend span { display:inline-flex; align-items:center; gap:6px; }
  .cw-legend i { width:9px; height:9px; border-radius:50%; display:inline-block; }
  .cw-empty { grid-column:1/-1; padding:22px; text-align:center; border-radius:16px; border:1px dashed rgba(255,255,255,0.15); color:#94a3b8; font-size:13px; line-height:1.6; }
  html.theme-light .cw-card, body.light-theme .cw-card { background:#fff; border-color:#e2e8f0; }
  html.theme-light .cw-name, body.light-theme .cw-name { color:#0f172a; }
  `;
  const style = document.createElement('style');
  style.textContent = css;
  document.head.appendChild(style);

  const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  function stateFor(sender, status) {
    const lanes = (status && Array.isArray(status.lanes)) ? status.lanes : [];
    const lane = lanes.find(l => l.id === sender.id);
    const running = status && status.status === 'running';
    if (!lane) return { cls: 'cw-red', label: 'Idle', sent: 0, total: 0 };
    const sent = Number(lane.sent || 0);
    const total = Number(lane.assigned || 0);
    const left = Number(lane.remaining ?? Math.max(0, total - sent - Number(lane.failed || 0)));
    if (running && left > 0 && lane.connected !== false && lane.status !== 'offline') {
      return { cls: 'cw-orange', label: lane.status === 'sending' ? 'Sending now…' : 'Working', sent, total };
    }
    if (sent > 0) return { cls: 'cw-green', label: left > 0 ? 'Sent (paused)' : 'Messages sent', sent, total };
    if (lane.status === 'offline' || lane.connected === false) return { cls: 'cw-red', label: 'Offline', sent, total };
    return { cls: 'cw-red', label: 'No progress', sent, total };
  }

  function render(container, senders, status) {
    if (!container) return;
    container.style.display = 'block';
    senders = Array.isArray(senders) ? senders : [];
    if (!senders.length) {
      container.innerHTML = `<div class="cw-grid"><div class="cw-empty">No Team Member WhatsApp is linked yet.<br>Each closer scans the login QR (or "Connect my WhatsApp" in their workspace) once, and appears here.</div></div>`;
      return;
    }
    const legend = `<div class="cw-legend">
        <span><i style="background:#ef4444"></i> No message / no progress</span>
        <span><i style="background:#f59e0b"></i> Sending right now</span>
        <span><i style="background:#22c55e"></i> Messages sent</span>
      </div>`;
    const cards = senders.map(s => {
      const st = stateFor(s, status);
      const pct = st.total ? Math.round((st.sent / st.total) * 100) : 0;
      const isTL = s.roleType === 'lead';
      const num = s.device_number ? '+' + String(s.device_number).replace(/^\+/, '') : (s.display || s.phone || '');
      return `<div class="cw-card ${st.cls}">
        <div class="cw-top">
          <div class="cw-avatar ${isTL ? 'tl' : ''}">${esc((s.name || 'W').trim().charAt(0).toUpperCase())}</div>
          <div class="cw-id">
            <div class="cw-name">${esc(s.name || 'Team Member')}</div>
            <div class="cw-role">${isTL ? 'Team Leader' : 'Sales Closer'} · WhatsApp linked</div>
          </div>
          <div class="cw-light" title="${esc(st.label)}"><span class="cw-bulb r"></span><span class="cw-bulb o"></span><span class="cw-bulb g"></span></div>
        </div>
        <div class="cw-num">${esc(num)}</div>
        <div class="cw-state"><span class="cw-tag">${esc(st.label)}</span><span class="cw-count">${st.total ? `${st.sent} / ${st.total} sent` : `${Number(s.messages_sent || 0)} sent today`}</span></div>
        <div class="cw-bar"><i style="width:${pct}%"></i></div>
      </div>`;
    }).join('');
    container.innerHTML = legend + `<div class="cw-grid">${cards}</div>`;
  }

  window.ChatlyWaWidget = { render, stateFor };
})();
