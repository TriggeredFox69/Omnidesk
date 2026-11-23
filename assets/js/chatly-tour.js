/*
 * Chatly guided tour (Sales Desk, index.html).
 *
 *   window.ChatlyTour.start({ role: 'lead' | 'member', onDone: function (completed) {} })
 *
 * Shows a dimmed overlay with a cut-out around each target element and a tooltip card
 * (title, short text, "3 of 8", Back / Next / Skip). Under 600px wide the card becomes a
 * bottom (or top) sheet. Steps whose target is missing or hidden are skipped silently.
 * Esc = skip, Left / Right arrows = back / next. Nothing is stored here: the caller decides
 * what to remember through onDone(completed), where completed is true only when the person
 * pressed the last button ("Start selling").
 *
 * Also exposed: ChatlyTour.stop() (ends as skipped) and ChatlyTour.isActive().
 * Self-contained, no libraries. Styles live in assets/css/chatly-tour.css (classes ctour-*).
 */
(function (global) {
  'use strict';

  if (global.ChatlyTour) return;

  var doc = global.document;

  // Tabs the tour may open so a step's target becomes visible (restored when the tour ends).
  var TAB_BUTTONS = { inbox: 'tabInbox', employee: 'tabEmployee' };
  var VIEW_IDS = ['viewInbox', 'viewEmployee', 'viewCampaign'];

  var MOBILE_MAX = 600;   // below this width the card is a sheet
  var PAD = 6;            // space between the target and the cut-out edge
  var GAP = 14;           // space between the cut-out and the card
  var EDGE = 12;          // minimum distance from the screen edge

  var STEPS = {
    member: [
      { id: 'welcome', title: 'Welcome to your Sales Desk',
        body: 'This quick tour shows where your chats arrive, how to log a sale and how you get paid. It takes under a minute.' },
      { id: 'inbox', target: ['#tabInbox'], title: 'Team Inbox',
        body: 'Chats from campaigns arrive here. When a customer replies, their chat shows up in this tab.' },
      { id: 'chats', target: ['#conversationsScroll'], tab: 'inbox', title: 'Your chats',
        body: 'Each row is one customer. A number badge means unread messages, so open those first.' },
      { id: 'thread', target: ['#chatComposerBar'], tab: 'inbox', title: 'Reply fast',
        body: 'Type your reply here and send it. Quick replies close more sales, and voice notes and images work too.' },
      { id: 'paid', target: ['#markPaidHeaderBtn', '#dossierPaidBtn'], tab: 'inbox', title: 'Mark Paid',
        body: 'When a customer pays, tap Mark Paid and add the payment details. The sale goes to review, and your earnings update once it is verified.' },
      { id: 'stats', target: ['#tabEmployee'], title: 'My Stats',
        body: 'Your verified sales and earnings live here. The first Rs 10,000 of sales is all yours, then you earn your plan\'s commission.' },
      { id: 'withdraw', target: ['#openWithdrawModalBtn', '#heroQuickWithdrawBtn', '#employeeWithdrawalSection'], tab: 'employee', title: 'Get paid',
        body: 'Request a payout from your balance here. It arrives in 24 to 48 hours. Payouts are not sent on Saturday or Sunday.' },
      { id: 'quota', target: ['#headerQuotaPill'], title: 'Daily message limit',
        body: 'This shows how many messages you have sent today out of your daily limit. It resets every night at 12 AM (Pakistan time).' },
      { id: 'done', title: 'You\'re ready',
        body: 'Open a chat, reply fast and mark your first sale paid. Good luck!' }
    ],
    lead: [
      { id: 'welcome', title: 'Welcome, Team Leader',
        body: 'This quick tour shows where your chats live, how to log a new sign-up and how you get paid.' },
      { id: 'inbox', target: ['#tabInbox'], title: 'Team Inbox',
        body: 'All your chats are here: campaign replies and also the people you message yourself on WhatsApp, like recruits from Telegram groups.' },
      { id: 'thread', target: ['#chatComposerBar', '#conversationsScroll'], tab: 'inbox', title: 'Chat and reply',
        body: 'Open a chat and reply here. Quick answers win sign-ups, and voice notes and images work too.' },
      { id: 'paid', target: ['#markPaidHeaderBtn', '#dossierPaidBtn'], tab: 'inbox', title: 'Mark Paid',
        body: 'When someone you recruited buys the Nunno x Chatly plan (Rs 1,000), tap Mark Paid. It goes to review, and you earn Rs 500 once it is verified.' },
      { id: 'stats', target: ['#tabEmployee'], title: 'My Stats',
        body: 'Track your sign-ups and earnings here.' },
      { id: 'withdraw', target: ['#openWithdrawModalBtn', '#heroQuickWithdrawBtn', '#employeeWithdrawalSection'], tab: 'employee', title: 'Withdraw your earnings',
        body: 'Request a payout from your balance. It arrives in 24 to 48 hours, with no payouts on Saturday or Sunday.' },
      { id: 'done', title: 'You\'re ready',
        body: 'Start reaching out, keep replies quick and mark each paid sign-up. Good luck!' }
    ]
  };

  var state = null;
  var uid = 0;

  // ── Helpers ────────────────────────────────────────────────────────────────

  function make(tag, cls, attrs) {
    var node = doc.createElement(tag);
    if (cls) node.className = cls;
    if (attrs) {
      for (var k in attrs) {
        if (Object.prototype.hasOwnProperty.call(attrs, k)) node.setAttribute(k, attrs[k]);
      }
    }
    return node;
  }

  function setText(node, text) {
    // textContent never parses HTML, so inserted text is always escaped.
    node.textContent = text == null ? '' : String(text);
  }

  function reducedMotion() {
    return !!(global.matchMedia && global.matchMedia('(prefers-reduced-motion: reduce)').matches);
  }

  function isDarkTheme() {
    return !!((doc.body && doc.body.classList.contains('theme-dark')) ||
      doc.documentElement.classList.contains('theme-dark'));
  }

  function viewport() {
    return {
      w: doc.documentElement.clientWidth || global.innerWidth,
      h: global.innerHeight || doc.documentElement.clientHeight
    };
  }

  function query(sel) {
    try { return doc.querySelector(sel); } catch (_) { return null; }
  }

  // Really on screen now: rendered, not hidden, non-zero size.
  function isShown(node) {
    if (!node || !node.isConnected) return false;
    var r = node.getBoundingClientRect();
    if (r.width < 1 || r.height < 1) return false;
    var cs = global.getComputedStyle(node);
    return cs.display !== 'none' && cs.visibility !== 'hidden';
  }

  // Could be shown if the step's tab were opened (only the tab's view itself may be hidden).
  function couldShow(node, step) {
    if (!node || !node.isConnected) return false;
    for (var n = node; n && n.nodeType === 1 && n !== doc.body; n = n.parentElement) {
      if (step.tab && VIEW_IDS.indexOf(n.id) !== -1) return true;
      var cs = global.getComputedStyle(n);
      if (cs.display === 'none' || cs.visibility === 'hidden') return false;
    }
    return isShown(node);
  }

  function findShown(step) {
    var list = step.target || [];
    for (var i = 0; i < list.length; i++) {
      var node = query(list[i]);
      if (isShown(node)) return node;
    }
    return null;
  }

  function findPossible(step) {
    var list = step.target || [];
    for (var i = 0; i < list.length; i++) {
      var node = query(list[i]);
      if (node && couldShow(node, step)) return node;
    }
    return null;
  }

  function activeTab() {
    for (var key in TAB_BUTTONS) {
      if (!Object.prototype.hasOwnProperty.call(TAB_BUTTONS, key)) continue;
      var btn = doc.getElementById(TAB_BUTTONS[key]);
      if (btn && btn.classList.contains('active')) return key;
    }
    return null;
  }

  // Returns true when a tab button was clicked.
  function openTab(key) {
    var btn = doc.getElementById(TAB_BUTTONS[key] || '');
    if (!btn || btn.classList.contains('active') || !isShown(btn)) return false;
    try { btn.click(); } catch (_) { return false; }
    return true;
  }

  // ── DOM ────────────────────────────────────────────────────────────────────

  function buildDom() {
    var n = ++uid;
    var root = make('div', 'ctour-root');
    if (reducedMotion()) root.classList.add('ctour-reduced');
    if (isDarkTheme()) root.classList.add('ctour-dark');

    var blocker = make('div', 'ctour-blocker', { 'aria-hidden': 'true' });
    var spot = make('div', 'ctour-spot', { 'aria-hidden': 'true' });

    var tip = make('div', 'ctour-tip', {
      role: 'dialog',
      'aria-modal': 'true',
      'aria-labelledby': 'ctour-title-' + n,
      'aria-describedby': 'ctour-body-' + n
    });

    var live = make('div', 'ctour-live', { 'aria-live': 'polite', 'aria-atomic': 'true' });
    var head = make('div', 'ctour-head');
    var count = make('span', 'ctour-count');
    var skip = make('button', 'ctour-btn ctour-skip', { type: 'button' });
    setText(skip, 'Skip');
    head.appendChild(count);
    head.appendChild(skip);

    var title = make('h2', 'ctour-title', { id: 'ctour-title-' + n });
    var body = make('p', 'ctour-body', { id: 'ctour-body-' + n });
    live.appendChild(title);
    live.appendChild(body);

    var dots = make('div', 'ctour-dots', { 'aria-hidden': 'true' });

    var foot = make('div', 'ctour-foot');
    var back = make('button', 'ctour-btn ctour-back', { type: 'button' });
    setText(back, 'Back');
    var next = make('button', 'ctour-btn ctour-next', { type: 'button' });
    setText(next, 'Next');
    foot.appendChild(back);
    foot.appendChild(next);

    tip.appendChild(head);
    tip.appendChild(live);
    tip.appendChild(dots);
    tip.appendChild(foot);

    root.appendChild(blocker);
    root.appendChild(spot);
    root.appendChild(tip);

    return { root: root, blocker: blocker, spot: spot, tip: tip, count: count, title: title,
      body: body, dots: dots, back: back, next: next, skip: skip };
  }

  // ── Flow ───────────────────────────────────────────────────────────────────

  function go(index, dir) {
    var s = state;
    if (!s) return;
    if (index < 0) index = 0;
    if (index >= s.plan.length) { finish(true); return; }

    var step = s.plan[index];
    var token = ++s.token;

    if (!step.target) { render(index, null); return; }

    var proceed = function () {
      if (!state || state !== s || s.token !== token) return;
      var node = findShown(step);
      if (!node) {
        // Missing or hidden: drop the step silently and keep moving the same way.
        s.plan.splice(index, 1);
        go(dir < 0 ? index - 1 : index, dir);
        return;
      }
      render(index, node);
    };

    if (!findShown(step) && step.tab && openTab(step.tab)) {
      s.switchedTab = true;
      global.setTimeout(proceed, 160);   // let the app show the view first
    } else {
      proceed();
    }
  }

  function render(index, node) {
    var s = state;
    if (!s) return;
    var step = s.plan[index];
    var last = index === s.plan.length - 1;
    s.index = index;
    s.node = node;

    setText(s.dom.count, (index + 1) + ' of ' + s.plan.length);
    setText(s.dom.title, step.title);
    setText(s.dom.body, step.body);

    var dots = s.dom.dots;
    while (dots.firstChild) dots.removeChild(dots.firstChild);
    for (var i = 0; i < s.plan.length; i++) {
      dots.appendChild(make('span', 'ctour-dot' + (i === index ? ' is-on' : (i < index ? ' is-done' : ''))));
    }

    s.dom.back.hidden = index === 0;
    s.dom.skip.hidden = last;
    setText(s.dom.next, last ? 'Start selling' : 'Next');
    s.dom.tip.classList.toggle('ctour-last', last);

    if (node) {
      var r = node.getBoundingClientRect();
      var vp = viewport();
      if (r.top < 0 || r.bottom > vp.h || r.left < 0 || r.right > vp.w) {
        try {
          node.scrollIntoView({ block: 'center', inline: 'nearest', behavior: s.reduced ? 'auto' : 'smooth' });
        } catch (_) {
          node.scrollIntoView();
        }
      }
    }

    layout();
    s.dom.root.classList.add('ctour-ready');
    try { s.dom.next.focus({ preventScroll: true }); } catch (_) { s.dom.next.focus(); }
  }

  function scheduleLayout() {
    var s = state;
    if (!s || s.raf) return;
    var run = function () {
      if (state !== s) return;
      s.raf = 0;
      layout();
    };
    s.raf = global.requestAnimationFrame ? global.requestAnimationFrame(run) : global.setTimeout(run, 16);
  }

  function layout() {
    var s = state;
    if (!s) return;
    var dom = s.dom;
    var tip = dom.tip;
    var spot = dom.spot;
    var vp = viewport();
    var mobile = vp.w < MOBILE_MAX;
    var step = s.plan[s.index];

    dom.root.classList.toggle('ctour-dark', isDarkTheme());

    // The app re-renders parts of the page; follow the element if it was replaced.
    if (s.node && !isShown(s.node) && step) {
      var again = findShown(step);
      if (again) s.node = again;
    }

    tip.classList.remove('ctour-sheet', 'ctour-sheet-top', 'ctour-sheet-bottom', 'ctour-center');
    tip.style.left = '';
    tip.style.top = '';

    if (!s.node || !isShown(s.node)) {
      // Welcome / done (or a target that vanished mid-step): centred card, full dim.
      dom.root.classList.add('ctour-no-target');
      spot.style.display = 'none';
      tip.classList.add('ctour-center');
      return;
    }

    dom.root.classList.remove('ctour-no-target');
    spot.style.display = '';

    var r = s.node.getBoundingClientRect();
    var pad = mobile ? 4 : PAD;
    var sl = Math.max(r.left - pad, 4);
    var st = Math.max(r.top - pad, 4);
    var sr = Math.min(r.right + pad, vp.w - 4);
    var sb = Math.min(r.bottom + pad, vp.h - 4);
    if (sr < sl + 8) sr = sl + 8;
    if (sb < st + 8) sb = st + 8;
    spot.style.left = sl + 'px';
    spot.style.top = st + 'px';
    spot.style.width = (sr - sl) + 'px';
    spot.style.height = (sb - st) + 'px';

    if (mobile) {
      tip.classList.add('ctour-sheet', 'ctour-sheet-bottom');
      var th = tip.offsetHeight;
      var fitsBelowSheet = sb <= vp.h - th - 8;
      var fitsAboveSheet = st >= th + 8;
      if (!fitsBelowSheet && (fitsAboveSheet || st > vp.h - sb)) {
        tip.classList.remove('ctour-sheet-bottom');
        tip.classList.add('ctour-sheet-top');
      }
      return;
    }

    var tw = tip.offsetWidth;
    var tH = tip.offsetHeight;
    var cx = (sl + sr) / 2;
    var cy = (st + sb) / 2;
    var options = [
      { left: cx - tw / 2, top: sb + GAP, fits: sb + GAP + tH <= vp.h - EDGE, room: vp.h - sb },
      { left: cx - tw / 2, top: st - GAP - tH, fits: st - GAP - tH >= EDGE, room: st },
      { left: sr + GAP, top: cy - tH / 2, fits: sr + GAP + tw <= vp.w - EDGE, room: vp.w - sr },
      { left: sl - GAP - tw, top: cy - tH / 2, fits: sl - GAP - tw >= EDGE, room: sl }
    ];
    var pick = null;
    for (var i = 0; i < options.length; i++) {
      if (options[i].fits) { pick = options[i]; break; }
    }
    if (!pick) {
      pick = options[0];
      for (var j = 1; j < options.length; j++) {
        if (options[j].room > pick.room) pick = options[j];
      }
    }
    var left = Math.min(Math.max(pick.left, EDGE), Math.max(EDGE, vp.w - tw - EDGE));
    var top = Math.min(Math.max(pick.top, EDGE), Math.max(EDGE, vp.h - tH - EDGE));
    tip.style.left = Math.round(left) + 'px';
    tip.style.top = Math.round(top) + 'px';
  }

  function onKey(e) {
    var s = state;
    if (!s) return;
    var key = e.key;
    if (key === 'Escape' || key === 'Esc') {
      e.preventDefault(); e.stopPropagation();
      finish(false);
    } else if (key === 'ArrowRight' || key === 'Right') {
      e.preventDefault(); e.stopPropagation();
      go(s.index + 1, 1);
    } else if (key === 'ArrowLeft' || key === 'Left') {
      e.preventDefault(); e.stopPropagation();
      if (s.index > 0) go(s.index - 1, -1);
    } else if (key === 'Tab') {
      // Keep keyboard focus inside the card.
      var items = [s.dom.skip, s.dom.back, s.dom.next].filter(function (b) { return !b.hidden; });
      if (!items.length) return;
      var at = items.indexOf(doc.activeElement);
      e.preventDefault(); e.stopPropagation();
      var nextAt = at === -1 ? 0 : (at + (e.shiftKey ? -1 : 1) + items.length) % items.length;
      items[nextAt].focus();
    }
  }

  function finish(completed) {
    var s = state;
    if (!s) return;
    state = null;
    s.token++;

    doc.removeEventListener('keydown', onKey, true);
    global.removeEventListener('resize', scheduleLayout);
    global.removeEventListener('orientationchange', scheduleLayout);
    doc.removeEventListener('scroll', scheduleLayout, true);
    if (global.visualViewport) global.visualViewport.removeEventListener('resize', scheduleLayout);
    if (s.timer) global.clearInterval(s.timer);
    if (s.dom.root.parentNode) s.dom.root.parentNode.removeChild(s.dom.root);

    if (s.switchedTab && s.originalTab && activeTab() !== s.originalTab) openTab(s.originalTab);

    if (s.prevFocus && s.prevFocus.isConnected && typeof s.prevFocus.focus === 'function') {
      try { s.prevFocus.focus({ preventScroll: true }); } catch (_) {}
    }

    if (typeof s.onDone === 'function') {
      try { s.onDone(!!completed); } catch (err) {
        global.setTimeout(function () { throw err; }, 0);
      }
    }
  }

  // ── Public API ─────────────────────────────────────────────────────────────

  function start(opts) {
    opts = opts || {};
    if (state || !doc.body) return false;
    var role = opts.role === 'lead' ? 'lead' : 'member';

    var plan = [];
    STEPS[role].forEach(function (step) {
      if (!step.target || findShown(step) || findPossible(step)) plan.push(step);
    });

    var dom = buildDom();
    state = {
      role: role,
      plan: plan,
      index: 0,
      node: null,
      token: 0,
      raf: 0,
      timer: 0,
      dom: dom,
      reduced: reducedMotion(),
      originalTab: activeTab(),
      switchedTab: false,
      prevFocus: doc.activeElement,
      onDone: opts.onDone
    };

    dom.next.addEventListener('click', function () { if (state) go(state.index + 1, 1); });
    dom.back.addEventListener('click', function () { if (state && state.index > 0) go(state.index - 1, -1); });
    dom.skip.addEventListener('click', function () { finish(false); });

    doc.addEventListener('keydown', onKey, true);
    global.addEventListener('resize', scheduleLayout);
    global.addEventListener('orientationchange', scheduleLayout);
    doc.addEventListener('scroll', scheduleLayout, true);
    if (global.visualViewport) global.visualViewport.addEventListener('resize', scheduleLayout);
    // Catch layout shifts the app makes without scrolling or resizing (new chats, badges).
    state.timer = global.setInterval(scheduleLayout, 500);

    doc.body.appendChild(dom.root);
    go(0, 1);
    return true;
  }

  function stop() {
    finish(false);
  }

  global.ChatlyTour = {
    start: start,
    stop: stop,
    isActive: function () { return !!state; }
  };
})(window);
