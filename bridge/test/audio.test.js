const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const {
  ensureOggOpus,
  isOgg,
  isWebm,
  getOpusSamplesPerFrame,
  getOpusPacketSamples,
  createOggPage,
  oggCrc,
  oggOpusVoiceInfo
} = require('../audio');

test('oggOpusVoiceInfo gives WhatsApp wave bars and length from Opus packet sizes', () => {
  const head = Buffer.alloc(19); head.write('OpusHead', 0, 'ascii'); head[8] = 1; head[9] = 1; head.writeUInt16LE(312, 10);
  const tags = Buffer.from('OpusTags\0\0\0\0\0\0\0\0');
  // 3 seconds of 20 ms packets: loud first second, silence, loud last second.
  const audio = [];
  for (let i = 0; i < 150; i++) audio.push(Buffer.alloc(i >= 50 && i < 100 ? 3 : 80, 0xf8));
  const ogg = Buffer.concat([
    createOggPage(0x02, 0, 7, 0, [head]),
    createOggPage(0x00, 0, 7, 1, [tags]),
    createOggPage(0x04, 312 + 150 * 960, 7, 2, audio)
  ]);
  const info = oggOpusVoiceInfo(ogg);
  assert.equal(info.seconds, 3);
  assert.equal(info.waveform.length, 64);
  assert.ok(info.waveform[5] > 80, 'loud start has tall bars');
  assert.ok(info.waveform[32] < 10, 'silence has short bars');
  assert.ok(info.waveform[60] > 80, 'loud end has tall bars');
  assert.equal(oggOpusVoiceInfo(Buffer.from('ID3 not ogg')), null);
});

test('getOpusSamplesPerFrame returns correct frame size for all configurations', () => {
  // CELT 20ms
  assert.equal(getOpusSamplesPerFrame(0b11111000), 960);
  // CELT 10ms
  assert.equal(getOpusSamplesPerFrame(0b11110000), 480);
  // CELT 5ms
  assert.equal(getOpusSamplesPerFrame(0b11101000), 240);
  // CELT 2.5ms
  assert.equal(getOpusSamplesPerFrame(0b11100000), 120);
  // Hybrid 20ms
  assert.equal(getOpusSamplesPerFrame(0b01101000), 960);
  // Hybrid 10ms
  assert.equal(getOpusSamplesPerFrame(0b01100000), 480);
  // SILK 20ms
  assert.equal(getOpusSamplesPerFrame(0b00001000), 960);
  // SILK 60ms
  assert.equal(getOpusSamplesPerFrame(0b00011000), 2880);
});

test('getOpusPacketSamples correctly calculates total packet samples', () => {
  // Code 0: 1 frame of 20ms = 960 samples
  const pkt1 = Buffer.from([0xf8, 0x01, 0x02]);
  assert.equal(getOpusPacketSamples(pkt1), 960);

  // Code 3: 3 frames of 20ms = 2880 samples
  const pkt3 = Buffer.from([0xfb, 0x03, 0x01, 0x02]);
  assert.equal(getOpusPacketSamples(pkt3), 2880);
});

test('createOggPage creates a valid page with correct CRC', () => {
  const payload = Buffer.from('OpusHeadTestPayload123456');
  const page = createOggPage(0x02, 0, 12345, 0, [payload]);
  assert.equal(page.subarray(0, 4).toString('ascii'), 'OggS');
  assert.equal(page[5], 0x02);
  assert.equal(isOgg(page), true);

  // Validate CRC
  const copy = Buffer.from(page);
  copy.writeUInt32LE(0, 22);
  const calculated = oggCrc(copy);
  assert.equal(page.readUInt32LE(22), calculated);
});

test('ensureOggOpus passes through valid Ogg buffers unchanged', async () => {
  const oggPage = createOggPage(0x02, 0, 1, 0, [Buffer.from('OpusHead')]);
  const res = await ensureOggOpus(oggPage);
  assert.equal(res, oggPage);
  assert.equal(isOgg(res), true);
});
