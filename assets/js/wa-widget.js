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
  .cw-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(270px, 1fr)); gap:16px; }
  .cw-card { position:relative; overflow:hidden; border-radius:16px; padding:18px 20px; background:linear-gradient(145deg, rgba(17, 24, 39, 0.85), rgba(15, 23, 42, 0.95)); border:1px solid rgba(255,255,255,0.08); backdrop-filter:blur(16px); box-shadow:0 10px 25px -5px rgba(0,0,0,0.5), inset 0 1px 0 rgba(255,255,255,0.06); transition:border-color .3s ease, box-shadow .3s ease, transform .2s ease; }
  .cw-card:hover { transform:translateY(-2px); border-color:rgba(255,255,255,0.18); box-shadow:0 14px 30px -4px rgba(0,0,0,0.6); }
  .cw-card::before { content:''; position:absolute; inset:0 0 auto 0; height:3px; background:var(--cw-c, #ef4444); opacity:.95; }
  .cw-card.cw-red    { --cw-c:#ef4444; }
  .cw-card.cw-orange { --cw-c:#f59e0b; border-color:rgba(245,158,11,0.5); box-shadow:0 0 0 1px rgba(245,158,11,0.2), 0 12px 32px rgba(245,158,11,0.18); }
  .cw-card.cw-green  { --cw-c:#10b981; border-color:rgba(16,185,129,0.45); box-shadow:0 0 0 1px rgba(16,185,129,0.15), 0 12px 32px rgba(16,185,129,0.12); }
  .cw-top { display:flex; align-items:center; gap:12px; }
  .cw-avatar { width:46px; height:46px; border-radius:14px; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:18px; color:#fff; background:linear-gradient(135deg, #10b981, #059669); box-shadow:0 6px 16px rgba(16,185,129,0.3); border:1px solid rgba(255,255,255,0.12); }
  .cw-avatar.tl { background:linear-gradient(135deg, #8b5cf6, #6366f1); box-shadow:0 6px 16px rgba(99,102,241,0.35); }
  .cw-id { min-width:0; flex:1; }
  .cw-name { font-weight:800; font-size:14.5px; color:#ffffff; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; letter-spacing:-0.01em; }
  .cw-role { font-size:11px; font-weight:700; color:#94a3b8; margin-top:2px; display:flex; align-items:center; gap:6px; }
  .cw-role .role-pill { font-size:9.5px; font-weight:800; padding:1px 6px; border-radius:4px; text-transform:uppercase; letter-spacing:0.04em; }
  .cw-role .role-pill.tm { background:rgba(56,189,248,0.15); color:#38bdf8; }
  .cw-role .role-pill.tl { background:rgba(168,85,247,0.18); color:#c084fc; }
  .cw-num { margin-top:12px; font-family:'JetBrains Mono', ui-monospace, monospace; font-size:15px; font-weight:800; letter-spacing:.03em; color:#38bdf8; display:flex; align-items:center; gap:6px; }
  .cw-num svg { flex-shrink:0; opacity:0.8; }
  .cw-light { display:flex; flex-direction:column; gap:5px; padding:6px; border-radius:10px; background:#04070a; border:1px solid rgba(255,255,255,0.1); box-shadow:inset 0 2px 4px rgba(0,0,0,0.6); }
  .cw-bulb { width:11px; height:11px; border-radius:50%; opacity:.18; transition:opacity .3s ease, box-shadow .3s ease; }
  .cw-bulb.r { background:#ef4444; } .cw-bulb.o { background:#f59e0b; } .cw-bulb.g { background:#10b981; }
  .cw-red .cw-bulb.r    { opacity:1; box-shadow:0 0 12px #ef4444, inset 0 1px 1px #fff; }
  .cw-orange .cw-bulb.o { opacity:1; box-shadow:0 0 14px #f59e0b, inset 0 1px 1px #fff; animation:cwPulse .85s ease-in-out infinite alternate; }
  .cw-green .cw-bulb.g  { opacity:1; box-shadow:0 0 14px #10b981, inset 0 1px 1px #fff; }
  @keyframes cwPulse { from { opacity:.45; transform:scale(0.92); } to { opacity:1; transform:scale(1.08); } }
  .cw-state { margin-top:12px; display:flex; align-items:center; justify-content:space-between; gap:8px; font-size:12px; }
  .cw-tag { font-weight:800; font-size:11px; padding:3px 10px; border-radius:999px; background:color-mix(in srgb, var(--cw-c) 16%, transparent); color:var(--cw-c); border:1px solid color-mix(in srgb, var(--cw-c) 30%, transparent); display:inline-flex; align-items:center; gap:5px; }
  .cw-tag::before { content:''; width:6px; height:6px; border-radius:50%; background:currentColor; }
  .cw-count { color:#cbd5e1; font-weight:700; font-family:'JetBrains Mono', ui-monospace, monospace; font-size:12px; }
  .cw-bar { margin-top:10px; height:6px; border-radius:99px; background:rgba(255,255,255,0.08); overflow:hidden; }
  .cw-bar i { display:block; height:100%; width:0; background:var(--cw-c); border-radius:99px; transition:width .4s ease; box-shadow:0 0 8px var(--cw-c); }
  .cw-legend { display:flex; gap:16px; flex-wrap:wrap; margin:0 0 14px; font-size:12px; color:#94a3b8; font-weight:600; padding:8px 12px; background:rgba(255,255,255,0.02); border-radius:8px; border:1px solid rgba(255,255,255,0.05); }
  .cw-legend span { display:inline-flex; align-items:center; gap:7px; }
  .cw-legend i { width:10px; height:10px; border-radius:50%; display:inline-block; box-shadow:0 0 6px currentColor; }
  .cw-empty { grid-column:1/-1; padding:28px 20px; text-align:center; border-radius:16px; border:1px dashed rgba(255,255,255,0.15); background:rgba(255,255,255,0.02); color:#94a3b8; font-size:13px; line-height:1.6; }
  html.theme-light .cw-card, body.light-theme .cw-card { background:#ffffff; border-color:#e2e8f0; box-shadow:0 4px 15px rgba(0,0,0,0.05); }
  html.theme-light .cw-name, body.light-theme .cw-name { color:#0f172a; }
  `;
  const style = document.createElement('style');
  style.textContent = css;
  document.head.appendChild(style);

  const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  function stateFor(sender, status) {
    const lanes = (status && Array.isArray(status.lanes)) ? status.lanes : [];
    const lane = lanes.find(l => l.id === sender.id);
    const campaignStatus = status ? status.status : 'idle';
    const isRunning = campaignStatus === 'running';
    const isStopped = campaignStatus === 'stopped';
    const isPaused = campaignStatus === 'paused';

    if (!lane) {
      const todaySent = Number(sender.messages_sent || 0);
      if (todaySent > 0) return { cls: 'cw-green', label: `${todaySent} sent today`, sent: todaySent, total: 0 };
      return { cls: 'cw-red', label: 'Idle / Ready', sent: 0, total: 0 };
    }

    const sent = Number(lane.sent || 0);
    const total = Number(lane.assigned || 0);
    const failed = Number(lane.failed || 0);
    const left = Number(lane.remaining ?? Math.max(0, total - sent - failed));

    // If device is actively sending right now
    if ((isRunning || isStopped || isPaused) && lane.status === 'sending') {
      return { cls: 'cw-orange', label: 'Sending right now…', sent, total };
    }

    // If campaign is actively running and this lane still has assigned numbers to process
    if (isRunning && left > 0 && lane.connected !== false && lane.status !== 'offline') {
      return { cls: 'cw-orange', label: 'Dispatching queue…', sent, total };
    }

    // If messages have been sent successfully
    if (sent > 0) {
      if (isStopped) {
        return { cls: 'cw-green', label: `${sent} Sent (Stopped)`, sent, total };
      }
      if (isPaused) {
        return { cls: 'cw-green', label: `${sent} Sent (Paused)`, sent, total };
      }
      return { cls: 'cw-green', label: left > 0 ? `${sent} Sent · Active` : 'All Sent ✓', sent, total };
    }

    if (lane.status === 'offline' || lane.connected === false) {
      return { cls: 'cw-red', label: 'Offline / Unlinked', sent, total };
    }

    return { cls: 'cw-red', label: isStopped ? 'Stopped (0 sent)' : 'Idle (0 sent)', sent, total };
  }

  function render(container, senders, status) {
    if (!container) return;
    container.style.display = 'block';
    senders = Array.isArray(senders) ? senders : [];
    if (!senders.length) {
      container.innerHTML = `<div class="cw-grid"><div class="cw-empty">📱 No Team Member WhatsApp is linked yet.<br>Each closer scans the login QR (or "Connect my WhatsApp" in their workspace) once, and appears here ready for campaigns.</div></div>`;
      return;
    }
    const legend = `<div class="cw-legend">
        <span><i style="background:#ef4444;color:#ef4444"></i> 🔴 No message / Idle</span>
        <span><i style="background:#f59e0b;color:#f59e0b"></i> 🟡 Sending right now</span>
        <span><i style="background:#10b981;color:#10b981"></i> 🟢 Messages sent</span>
      </div>`;
    const cards = senders.map(s => {
      const st = stateFor(s, status);
      const pct = st.total ? Math.round((st.sent / st.total) * 100) : (st.sent > 0 ? 100 : 0);
      const isTL = s.roleType === 'lead';
      const num = s.device_number ? '+' + String(s.device_number).replace(/^\+/, '') : (s.display || s.phone || '');
      return `<div class="cw-card ${st.cls}">
        <div class="cw-top">
          <div class="cw-avatar ${isTL ? 'tl' : ''}">${esc((s.name || 'W').trim().charAt(0).toUpperCase())}</div>
          <div class="cw-id">
            <div class="cw-name">${esc(s.name || 'Team Member')}</div>
            <div class="cw-role">
              <span class="role-pill ${isTL ? 'tl' : 'tm'}">${isTL ? 'Team Leader' : 'Sales Closer'}</span>
              <span>• WhatsApp Linked</span>
            </div>
          </div>
          <div class="cw-light" title="${esc(st.label)}"><span class="cw-bulb r"></span><span class="cw-bulb o"></span><span class="cw-bulb g"></span></div>
        </div>
        <div class="cw-num">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
          <span>${esc(num)}</span>
        </div>
        <div class="cw-state"><span class="cw-tag">${esc(st.label)}</span><span class="cw-count">${st.total ? `${st.sent} / ${st.total} sent` : `${Number(s.messages_sent || 0)} sent today`}</span></div>
        <div class="cw-bar"><i style="width:${pct}%"></i></div>
      </div>`;
    }).join('');
    container.innerHTML = legend + `<div class="cw-grid">${cards}</div>`;
  }

  window.ChatlyWaWidget = { render, stateFor };
})();
