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

  /* ── List layout (layout: 'list') — compact rows for hundreds of WhatsApps ── */
  .cwl { --cwl-line: var(--glass-border-subtle, rgba(15,169,104,.14)); --cwl-text: var(--text-primary, #0f172a); --cwl-sub: var(--text-secondary, #475569); --cwl-muted: var(--text-muted, #94a3b8); }
  .cwl-bar { display:flex; align-items:center; justify-content:space-between; gap:10px 16px; flex-wrap:wrap; margin-bottom:10px; }
  .cwl-filters { display:flex; flex-wrap:wrap; gap:6px; }
  .cwl-chip { display:inline-flex; align-items:center; gap:7px; padding:5px 11px; border-radius:999px; font-size:12px; font-weight:650; cursor:pointer;
    color:var(--cwl-sub); background:transparent; border:1px solid var(--cwl-line); }
  .cwl-chip b { min-width:18px; padding:0 6px; border-radius:999px; font-size:11px; line-height:18px; text-align:center; background:var(--glass-surface-hover, rgba(15,169,104,.1)); color:var(--cwl-text); }
  .cwl-chip::before { content:''; width:7px; height:7px; border-radius:50%; background:var(--cwl-c, #94a3b8); }
  .cwl-chip.cwl-all::before { display:none; }
  .cwl-chip.on { color:var(--cwl-text); border-color:var(--accent-wa, #0fa968); background:var(--accent-wa-glass, rgba(15,169,104,.1)); }
  .cwl-sending { --cwl-c:#f59e0b; } .cwl-done { --cwl-c:#10b981; } .cwl-idle { --cwl-c:#94a3b8; } .cwl-offline { --cwl-c:#ef4444; }
  .cwl-search { flex:0 1 240px; min-width:160px; padding:7px 11px; border-radius:10px; font-size:12.5px; color:var(--cwl-text);
    background:var(--glass-surface-elevated, #fff); border:1px solid var(--glass-border, rgba(15,169,104,.2)); outline:none; }
  .cwl-search:focus { border-color:var(--accent-wa, #0fa968); }
  .cwl-head, .cwl-row { display:grid; grid-template-columns:minmax(170px,1.5fr) minmax(130px,1fr) minmax(120px,.9fr) minmax(150px,1.1fr); gap:12px; align-items:center; }
  .cwl-head { padding:0 12px 6px; font-size:10.5px; font-weight:750; letter-spacing:.05em; text-transform:uppercase; color:var(--cwl-muted); }
  .cwl-list { max-height:380px; overflow-y:auto; border:1px solid var(--cwl-line); border-radius:12px; background:var(--glass-surface-subtle, rgba(15,169,104,.04)); }
  .cwl-row { padding:9px 12px; border-bottom:1px solid var(--cwl-line); }
  .cwl-row:last-child { border-bottom:0; }
  .cwl-who { display:flex; align-items:center; gap:10px; min-width:0; }
  .cwl-av { width:30px; height:30px; flex-shrink:0; border-radius:50%; display:flex; align-items:center; justify-content:center; font-style:normal; font-size:12px; font-weight:800;
    color:var(--accent-wa, #0fa968); background:var(--accent-wa-glass, rgba(15,169,104,.12)); }
  .cwl-av.tl { color:#8b5cf6; background:rgba(139,92,246,.14); }
  .cwl-who span { min-width:0; display:flex; flex-direction:column; }
  .cwl-who b { font-size:13px; font-weight:700; color:var(--cwl-text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .cwl-who small { font-size:10.5px; font-weight:650; color:var(--cwl-muted); text-transform:uppercase; letter-spacing:.03em; }
  .cwl-num { font-family:'JetBrains Mono', ui-monospace, monospace; font-size:12px; color:var(--cwl-sub); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .cwl-state { display:inline-flex; align-items:center; gap:7px; font-size:12px; font-weight:650; color:var(--cwl-sub); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .cwl-state i { width:8px; height:8px; flex-shrink:0; border-radius:50%; background:var(--cwl-c); box-shadow:0 0 0 3px color-mix(in srgb, var(--cwl-c) 18%, transparent); }
  .cwl-row.cwl-sending .cwl-state i { animation:cwPulse .85s ease-in-out infinite alternate; }
  .cwl-prog { display:flex; align-items:center; gap:10px; min-width:0; }
  .cwl-track { flex:1; height:5px; border-radius:99px; overflow:hidden; background:var(--glass-surface-hover, rgba(15,169,104,.12)); }
  .cwl-track i { display:block; height:100%; border-radius:99px; background:var(--cwl-c); transition:width .4s ease; }
  .cwl-prog em { flex-shrink:0; min-width:62px; text-align:right; font-style:normal; font-size:11.5px; font-weight:650; color:var(--cwl-muted); font-variant-numeric:tabular-nums; }
  .cwl-foot { margin-top:8px; font-size:11.5px; color:var(--cwl-muted); }
  .cwl-empty { padding:22px; text-align:center; font-size:12.5px; color:var(--cwl-muted); }
  @media (max-width: 680px) {
    .cwl-head { display:none; }
    .cwl-row { grid-template-columns:1fr auto; grid-template-areas:"who state" "num prog"; row-gap:6px; }
    .cwl-who { grid-area:who; } .cwl-state { grid-area:state; justify-self:end; } .cwl-num { grid-area:num; } .cwl-prog { grid-area:prog; min-width:130px; }
    .cwl-search { flex:1 1 100%; }
  }
  @media (prefers-reduced-motion: reduce) { .cwl-row.cwl-sending .cwl-state i { animation:none; } }
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

  // ── List layout: filter chips with counts, search, one compact row per WhatsApp. ──
  // The toolbar is built once and kept, so typing in the search box survives the
  // status refresh every few seconds; only the rows are redrawn.
  const keyOf = st => st.cls === 'cw-orange' ? 'sending' : st.cls === 'cw-green' ? 'done'
    : (/offline|unlinked/i.test(st.label) ? 'offline' : 'idle');
  const FILTERS = [['all', 'All'], ['sending', 'Sending'], ['offline', 'Offline'], ['idle', 'Idle'], ['done', 'Sent']];
  const ORDER = { sending: 0, offline: 1, idle: 2, done: 3 };

  function renderList(container, senders, status) {
    container.style.display = 'block';
    const prefs = container._cwl || (container._cwl = { filter: 'all', q: '' });
    if (!senders.length) {
      container.innerHTML = `<div class="cwl-empty">No Team Member WhatsApp is linked yet. Each closer scans the login QR (or "Connect my WhatsApp" in their workspace) once, and appears here.</div>`;
      return;
    }
    let root = container.querySelector('.cwl');
    if (!root || root.parentNode !== container) {
      container.innerHTML = `<div class="cwl">
          <div class="cwl-bar"><div class="cwl-filters"></div>
            <input type="search" class="cwl-search" placeholder="Search name or number…" aria-label="Search team WhatsApps"></div>
          <div class="cwl-head" aria-hidden="true"><span>Team member</span><span>WhatsApp number</span><span>Status</span><span>Progress</span></div>
          <div class="cwl-list" role="list"></div>
          <div class="cwl-foot"></div>
        </div>`;
      root = container.querySelector('.cwl');
      const search = root.querySelector('.cwl-search');
      search.value = prefs.q;
      search.addEventListener('input', () => { prefs.q = search.value.trim().toLowerCase(); paintList(container); });
      root.querySelector('.cwl-filters').addEventListener('click', e => {
        const b = e.target.closest('[data-f]');
        if (b) { prefs.filter = b.dataset.f; paintList(container); }
      });
    }
    container._cwlData = { senders, status };
    paintList(container);
  }

  function paintList(container) {
    const root = container.querySelector('.cwl');
    const { senders, status } = container._cwlData || { senders: [], status: null };
    const prefs = container._cwl;
    if (!root) return;
    const rows = senders.map(s => { const st = stateFor(s, status); return { s, st, key: keyOf(st) }; });
    const counts = { all: rows.length, sending: 0, offline: 0, idle: 0, done: 0 };
    rows.forEach(r => { counts[r.key]++; });
    if (prefs.filter !== 'all' && !counts[prefs.filter]) prefs.filter = 'all';
    root.querySelector('.cwl-filters').innerHTML = FILTERS
      .filter(([k]) => k === 'all' || counts[k] > 0)
      .map(([k, label]) => `<button type="button" class="cwl-chip cwl-${k}${prefs.filter === k ? ' on' : ''}" data-f="${k}">${label}<b>${counts[k]}</b></button>`)
      .join('');
    const q = prefs.q;
    const shown = rows
      .filter(r => (prefs.filter === 'all' || r.key === prefs.filter) &&
        (!q || String(r.s.name || '').toLowerCase().includes(q) || String(r.s.device_number || r.s.phone || r.s.display || '').replace(/\D/g, '').includes(q.replace(/\D/g, '') || '\u0000')))
      .sort((a, b) => (ORDER[a.key] - ORDER[b.key]) || String(a.s.name || '').localeCompare(String(b.s.name || '')));
    root.querySelector('.cwl-list').innerHTML = shown.length ? shown.map(({ s, st, key }) => {
      const isTL = s.roleType === 'lead';
      const num = s.device_number ? '+' + String(s.device_number).replace(/^\+/, '') : (s.display || s.phone || '');
      const pct = st.total ? Math.round((st.sent / st.total) * 100) : (st.sent > 0 ? 100 : 0);
      const count = st.total ? `${st.sent} / ${st.total}` : `${Number(s.messages_sent || 0)} today`;
      return `<div class="cwl-row cwl-${key}" role="listitem">
          <span class="cwl-who"><i class="cwl-av${isTL ? ' tl' : ''}">${esc((s.name || 'W').trim().charAt(0).toUpperCase())}</i>
            <span><b title="${esc(s.name || 'Team Member')}">${esc(s.name || 'Team Member')}</b><small>${isTL ? 'Team Leader' : 'Sales Closer'}</small></span></span>
          <span class="cwl-num">${esc(num)}</span>
          <span class="cwl-state" title="${esc(st.label)}"><i></i>${esc(st.label)}</span>
          <span class="cwl-prog"><span class="cwl-track"><i style="width:${pct}%"></i></span><em>${esc(count)}</em></span>
        </div>`;
    }).join('') : '<div class="cwl-empty">No WhatsApp matches this filter.</div>';
    root.querySelector('.cwl-foot').textContent = shown.length === rows.length
      ? `${rows.length} WhatsApp${rows.length === 1 ? '' : 's'}`
      : `Showing ${shown.length} of ${rows.length}`;
  }

  function render(container, senders, status, opts) {
    if (!container) return;
    senders = Array.isArray(senders) ? senders : [];
    if (opts && opts.layout === 'list') return renderList(container, senders, status);
    container.style.display = 'block';
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
