const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const { downloadAttachment } = require('../media');

test('attachment decryption receives the MIME type required by WhatsApp', async () => {
  let options;
  const raw = { directPath: '/media', mediaKey: 'key', mimetype: 'audio/ogg; codecs=opus', type: 'ptt', size: 3 };
  const window = {
    require: name => name === 'WAWebCollections' ? { Msg: { get: () => raw } } : {
      downloadManager: { downloadAndMaybeDecrypt: async value => { options = value; return new Uint8Array([1,2,3]).buffer; } }
    },
    WWebJS: { arrayBufferToBase64Async: async bytes => Buffer.from(bytes).toString('base64') }
  };
  const client = { pupPage: { evaluate: (fn, id) => vm.runInNewContext(`(${fn.toString()})(id)`, { window, id, AbortSignal }) } };
  const result = await downloadAttachment(client, { id: { _serialized: 'voice' } });
  assert.equal(options.mimetype, raw.mimetype);
  assert.equal(options.type, 'ptt');
  assert.equal(result.data, 'AQID');
});
