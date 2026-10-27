'use strict';
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const { spawnSync } = require('node:child_process');

// Standard Ogg CRC Table (Polynomial: 0x04c11db7)
const CRC_TABLE = new Uint32Array(256);
for (let i = 0; i < 256; i++) {
  let r = i << 24;
  for (let j = 0; j < 8; j++) {
    r = (r & 0x80000000) ? ((r << 1) ^ 0x04c11db7) : (r << 1);
  }
  CRC_TABLE[i] = r >>> 0;
}

function oggCrc(buf) {
  let crc = 0;
  for (let i = 0; i < buf.length; i++) {
    crc = ((crc << 8) ^ CRC_TABLE[((crc >>> 24) & 0xff) ^ buf[i]]) >>> 0;
  }
  return crc >>> 0;
}

function createOggPage(headerType, granulePos, serialNo, pageSeq, packets) {
  const segmentTable = [];
  const payloadBuffers = [];

  for (const pkt of packets) {
    let len = pkt.length;
    while (len >= 255) {
      segmentTable.push(255);
      len -= 255;
    }
    segmentTable.push(len);
    payloadBuffers.push(pkt);
  }

  const headerLen = 27 + segmentTable.length;
  const payloadLen = payloadBuffers.reduce((acc, b) => acc + b.length, 0);
  const page = Buffer.alloc(headerLen + payloadLen);

  page.write('OggS', 0, 4, 'ascii');
  page[4] = 0; // Stream structure version
  page[5] = headerType; // 0x02: BOS, 0x04: EOS, 0x00: normal
  page.writeBigInt64LE(BigInt(granulePos), 6);
  page.writeUInt32LE(serialNo >>> 0, 14);
  page.writeUInt32LE(pageSeq >>> 0, 18);
  page[26] = segmentTable.length;
  for (let i = 0; i < segmentTable.length; i++) {
    page[27 + i] = segmentTable[i];
  }

  let offset = headerLen;
  for (const pkt of payloadBuffers) {
    pkt.copy(page, offset);
    offset += pkt.length;
  }

  const crc = oggCrc(page);
  page.writeUInt32LE(crc, 22);

  return page;
}

function readVint(buf, offset) {
  if (offset >= buf.length) return null;
  const first = buf[offset];
  let length = 1;
  let mask = 0x80;
  while (length <= 8 && !(first & mask)) {
    length++;
    mask >>= 1;
  }
  if (length > 8 || offset + length > buf.length) return null;

  let value = first & (mask - 1);
  for (let i = 1; i < length; i++) {
    value = (value * 256) + buf[offset + i];
  }
  return { value, length };
}

function readElementId(buf, offset) {
  if (offset >= buf.length) return null;
  const first = buf[offset];
  let length = 1;
  let mask = 0x80;
  while (length <= 4 && !(first & mask)) {
    length++;
    mask >>= 1;
  }
  if (length > 4 || offset + length > buf.length) return null;

  let value = 0;
  for (let i = 0; i < length; i++) {
    value = (value * 256) + buf[offset + i];
  }
  return { value, length };
}

const CONTAINER_IDS = new Set([
  0x1A45DFA3, // EBML
  0x18538067, // Segment
  0x114D9B74, // SeekHead
  0x1549A966, // Info
  0x1654AE6B, // Tracks
  0xAE,       // TrackEntry
  0xE1,       // Audio
  0x1F43B675  // Cluster
]);

function extractOpusPacketsFromWebm(webmBuf) {
  const packets = [];
  let opusHead = null;
  let offset = 0;

  const id0 = readElementId(webmBuf, offset);
  if (!id0 || id0.value !== 0x1A45DFA3) return null;

  while (offset < webmBuf.length - 4) {
    const el = readElementId(webmBuf, offset);
    if (!el) break;
    offset += el.length;

    const size = readVint(webmBuf, offset);
    if (!size) break;
    offset += size.length;

    const dataStart = offset;
    const isUnknownSize = (size.length === 8 && size.value === 0x00FFFFFFFFFFFF);
    const dataEnd = isUnknownSize ? webmBuf.length : Math.min(webmBuf.length, offset + size.value);

    if (CONTAINER_IDS.has(el.value)) {
      continue;
    }

    if (el.value === 0x63A2) {
      // CodecPrivate: OpusHead
      opusHead = webmBuf.subarray(dataStart, dataEnd);
    } else if (el.value === 0xA3) {
      // SimpleBlock
      const tr = readVint(webmBuf, dataStart);
      if (tr) {
        // Skip track number + 2 bytes timecode + 1 byte flags
        const payloadStart = dataStart + tr.length + 3;
        if (payloadStart < dataEnd) {
          packets.push(webmBuf.subarray(payloadStart, dataEnd));
        }
      }
    }

    offset = dataEnd;
  }

  return { opusHead, packets };
}

function webmToOggOpus(webmBuf) {
  try {
    const extracted = extractOpusPacketsFromWebm(webmBuf);
    if (!extracted || !extracted.packets || extracted.packets.length === 0) {
      return null;
    }

    const serial = (Math.random() * 0x7FFFFFFF) >>> 0;
    const pages = [];

    // Page 0: OpusHead
    let headPayload = extracted.opusHead;
    if (!headPayload || headPayload.length < 19 || headPayload.subarray(0, 8).toString('ascii') !== 'OpusHead') {
      headPayload = Buffer.from([
        0x4F, 0x70, 0x75, 0x73, 0x48, 0x65, 0x61, 0x64, // 'OpusHead'
        0x01,                                           // version 1
        0x01,                                           // 1 channel (mono)
        0x38, 0x01,                                     // pre-skip 312 LE
        0x80, 0xBB, 0x00, 0x00,                         // 48000 Hz LE
        0x00, 0x00,                                     // output gain 0
        0x00                                            // channel mapping 0
      ]);
    }
    pages.push(createOggPage(0x02, 0, serial, 0, [headPayload]));

    // Page 1: OpusTags
    const vendor = Buffer.from('Omnidesk');
    const tagsPayload = Buffer.alloc(8 + 4 + vendor.length + 4);
    tagsPayload.write('OpusTags', 0, 8, 'ascii');
    tagsPayload.writeUInt32LE(vendor.length, 8);
    vendor.copy(tagsPayload, 12);
    tagsPayload.writeUInt32LE(0, 12 + vendor.length);
    pages.push(createOggPage(0x00, 0, serial, 1, [tagsPayload]));

    // Audio pages: 20ms @ 48kHz = 960 samples per frame
    let granulePos = 0;
    let pageSeq = 2;
    const frameSamples = 960;

    for (let i = 0; i < extracted.packets.length; i++) {
      const isLast = (i === extracted.packets.length - 1);
      granulePos += frameSamples;
      const headerType = isLast ? 0x04 : 0x00;
      pages.push(createOggPage(headerType, granulePos, serial, pageSeq++, [extracted.packets[i]]));
    }

    return Buffer.concat(pages);
  } catch (err) {
    console.warn('[Audio] Pure JS WebM to Ogg conversion failed:', err.message);
    return null;
  }
}

let cachedFfmpegPath = undefined;
function findFfmpeg() {
  if (cachedFfmpegPath !== undefined) return cachedFfmpegPath;
  const candidates = [
    process.env.FFMPEG_PATH,
    'ffmpeg',
    '/usr/bin/ffmpeg',
    '/usr/local/bin/ffmpeg',
    '/bin/ffmpeg',
    '/usr/local/cpanel/3rdparty/bin/ffmpeg'
  ].filter(Boolean);

  for (const bin of candidates) {
    try {
      const res = spawnSync(bin, ['-version'], { timeout: 2000, stdio: 'ignore' });
      if (res.status === 0) {
        cachedFfmpegPath = bin;
        return bin;
      }
    } catch (_) {}
  }

  cachedFfmpegPath = null;
  return null;
}

function convertWithFfmpeg(buffer) {
  const ffmpeg = findFfmpeg();
  if (!ffmpeg) return null;

  const tmpId = `wa_audio_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`;
  const inputPath = path.join(os.tmpdir(), `${tmpId}.input`);
  const outputPath = path.join(os.tmpdir(), `${tmpId}.ogg`);

  try {
    fs.writeFileSync(inputPath, buffer);
    const res = spawnSync(ffmpeg, [
      '-y',
      '-i', inputPath,
      '-vn',
      '-c:a', 'libopus',
      '-b:a', '32k',
      '-ac', '1',
      '-ar', '48000',
      outputPath
    ], { timeout: 15000, stdio: 'ignore' });

    if (res.status === 0 && fs.existsSync(outputPath)) {
      const oggBuf = fs.readFileSync(outputPath);
      if (oggBuf && oggBuf.length > 50) return oggBuf;
    }
  } catch (err) {
    console.warn('[Audio] FFmpeg conversion failed:', err.message);
  } finally {
    try { if (fs.existsSync(inputPath)) fs.unlinkSync(inputPath); } catch (_) {}
    try { if (fs.existsSync(outputPath)) fs.unlinkSync(outputPath); } catch (_) {}
  }
  return null;
}

function isOgg(buf) {
  return Buffer.isBuffer(buf) && buf.length >= 4 && buf.subarray(0, 4).toString('ascii') === 'OggS';
}

function isWebm(buf) {
  return Buffer.isBuffer(buf) && buf.length >= 4 &&
    buf[0] === 0x1A && buf[1] === 0x45 && buf[2] === 0xDF && buf[3] === 0xA3;
}

/**
 * Ensures audio buffer is valid OGG OPUS suitable for WhatsApp PTT voice notes.
 * 1. Returns immediately if already OggS.
 * 2. If WebM (standard from Chrome MediaRecorder), remuxes via pure JS (zero-dependency, ultra-fast).
 * 3. Falls back to FFmpeg if available for MP3/WAV/AAC or any other format.
 * 4. Returns original buffer if all conversions fail.
 */
async function ensureOggOpus(buffer) {
  if (!Buffer.isBuffer(buffer) || buffer.length === 0) return buffer;

  if (isOgg(buffer)) {
    return buffer;
  }

  if (isWebm(buffer)) {
    const converted = webmToOggOpus(buffer);
    if (converted && isOgg(converted)) {
      console.log(`[Audio] Remuxed WebM (${buffer.length} bytes) to Ogg Opus (${converted.length} bytes) via pure JS.`);
      return converted;
    }
  }

  // Fallback to FFmpeg if present
  const ffmpegResult = convertWithFfmpeg(buffer);
  if (ffmpegResult && isOgg(ffmpegResult)) {
    console.log(`[Audio] Converted audio (${buffer.length} bytes) to Ogg Opus (${ffmpegResult.length} bytes) via FFmpeg.`);
    return ffmpegResult;
  }

  console.warn('[Audio] Could not convert audio to Ogg Opus; sending raw buffer.');
  return buffer;
}

module.exports = {
  ensureOggOpus,
  isOgg,
  isWebm,
  webmToOggOpus,
  convertWithFfmpeg,
  findFfmpeg,
  createOggPage,
  oggCrc
};
