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
  oggCrc
} = require('../audio');

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
