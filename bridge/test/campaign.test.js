const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.resolve(__dirname, '../../assets/js/whatsapp-app.js'), 'utf8');
const connectionCode = source.slice(source.indexOf('  let waPollTimer'), source.indexOf('  // ==========================================', source.indexOf('  let waPollTimer')));
const startCode = source.slice(source.indexOf('  async function startCampaign()'), source.indexOf('  function pauseCampaign()'));

function setup() {
  let ready = false, dispatches = 0, connects = 0, open = false;
  const timers = [];
  const els = {
    campaignCustomMsg: { value: 'Hello' }, startCampaignBtn: { style: {} },
    campaignStatusBadge: { style: {} }, waPendingCampaign: {}, waConnectionStatus: {},
    saveConnectedWaBtn: { style: {} }, waQrImage: { removeAttribute() {} },
    connectWaModal: { classList: { add() { open = true; }, remove() { open = false; }, contains() { return open; } } }
  };
  const campaignState = { audience: [{ phone: '923001234567' }], isRunning: false };
  const context = vm.createContext({ els, campaignState, state: {}, window: { addEventListener() {} },
    document: { getElementById() { return null; }, addEventListener() {} },
    renderConnectedNumber() {}, showToast() {}, logCampaignTerminal() {}, escapeHtml: String,
    describeSendError: error => error.message,
    dispatchNextCampaignMessage() { dispatches++; },
    setTimeout(fn) { timers.push(fn); return fn; }, clearTimeout(fn) { const i = timers.indexOf(fn); if (i >= 0) timers.splice(i, 1); },
    fetch: async (url, options) => {
      if (options?.method === 'POST') connects++;
      return { ok: true, json: async () => ({ success: true, state: ready ? 'ready' : 'qr', is_configured: ready }) };
    }
  });
  vm.runInContext(connectionCode + startCode, context);
  return { context, els, campaignState, timers, ready() { ready = true; }, dispatches: () => dispatches, connects: () => connects };
}
const flush = () => new Promise(resolve => setImmediate(resolve));

test('Start immediately dispatches when linked and ignores a second Start', async () => {
  const run = setup(); run.ready();
  await vm.runInContext('startCampaign()', run.context);
  await vm.runInContext('startCampaign()', run.context);
  assert.equal(run.dispatches(), 1);
  assert.equal(run.campaignState.isRunning, true);
});

test('Start opens QR linking and automatically dispatches after scan', async () => {
  const run = setup();
  await vm.runInContext('startCampaign()', run.context); await flush();
  assert.equal(run.dispatches(), 0);
  assert.equal(run.connects(), 1);
  assert.equal(run.els.waPendingCampaign.hidden, false);
  run.ready(); await run.timers.shift()(); await flush();
  assert.equal(run.dispatches(), 1);
});

test('Cancel during QR setup prevents automatic campaign launch', async () => {
  const run = setup();
  await vm.runInContext('startCampaign()', run.context); await flush();
  const stalePoll = run.timers[0];
  vm.runInContext('closeConnectWaModal()', run.context);
  run.ready(); await stalePoll(); await flush();
  assert.equal(run.dispatches(), 0);
});

test('manual linking closes the QR dialog when ready without starting a campaign', async () => {
  const run = setup();
  vm.runInContext('openConnectWaModal()', run.context); await flush();
  run.ready(); await run.timers.shift()(); await flush();
  assert.equal(run.els.connectWaModal.classList.contains('open'), false);
  assert.equal(run.dispatches(), 0);
});

test('Start cannot re-enter while a send is already in flight', async () => {
  const run = setup(); run.ready(); run.campaignState.inFlight = true;
  await vm.runInContext('startCampaign()', run.context);
  assert.equal(run.dispatches(), 0);
});
