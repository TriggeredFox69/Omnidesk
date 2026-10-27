"use strict";
exports.id = 544;
exports.ids = [544];
exports.modules = {

/***/ 87:
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   k: () => (/* binding */ AbstractID3Parser)
/* harmony export */ });
/* harmony import */ var debug__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(5721);
/* harmony import */ var strtok3__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(6481);
/* harmony import */ var _common_BasicParser_js__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(5258);
/* harmony import */ var _id3v1_ID3v1Parser_js__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(781);
/* harmony import */ var _ID3v2Parser_js__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(8503);
/* harmony import */ var _ID3v2Token_js__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(8597);






const debug = debug__WEBPACK_IMPORTED_MODULE_0__('music-metadata:parser:ID3');
/**
 * Abstract parser which tries take ID3v2 and ID3v1 headers.
 */
class AbstractID3Parser extends _common_BasicParser_js__WEBPACK_IMPORTED_MODULE_2__/* .BasicParser */ .s {
    constructor() {
        super(...arguments);
        this.id3parser = new _ID3v2Parser_js__WEBPACK_IMPORTED_MODULE_3__/* .ID3v2Parser */ .S();
    }
    static async startsWithID3v2Header(tokenizer) {
        return (await tokenizer.peekToken(_ID3v2Token_js__WEBPACK_IMPORTED_MODULE_4__/* .ID3v2Header */ .yW)).fileIdentifier === 'ID3';
    }
    async parse() {
        try {
            await this.parseID3v2();
        }
        catch (err) {
            if (err instanceof strtok3__WEBPACK_IMPORTED_MODULE_1__/* .EndOfStreamError */ .d1) {
                debug('End-of-stream');
            }
            else {
                throw err;
            }
        }
    }
    finalize() {
        return;
    }
    async parseID3v2() {
        await this.tryReadId3v2Headers();
        debug('End of ID3v2 header, go to MPEG-parser: pos=%s', this.tokenizer.position);
        await this.postId3v2Parse();
        if (this.options.skipPostHeaders && this.metadata.hasAny()) {
            this.finalize();
        }
        else {
            const id3v1parser = new _id3v1_ID3v1Parser_js__WEBPACK_IMPORTED_MODULE_5__/* .ID3v1Parser */ .Bn(this.metadata, this.tokenizer, this.options);
            await id3v1parser.parse();
            this.finalize();
        }
    }
    async tryReadId3v2Headers() {
        const id3Header = await this.tokenizer.peekToken(_ID3v2Token_js__WEBPACK_IMPORTED_MODULE_4__/* .ID3v2Header */ .yW);
        if (id3Header.fileIdentifier === 'ID3') {
            debug('Found ID3v2 header, pos=%s', this.tokenizer.position);
            await this.id3parser.parse(this.metadata, this.tokenizer, this.options);
            return this.tryReadId3v2Headers();
        }
    }
}


/***/ }),

/***/ 7544:
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

// ESM COMPAT FLAG
__webpack_require__.r(__webpack_exports__);

// EXPORTS
__webpack_require__.d(__webpack_exports__, {
  MpegContentError: () => (/* binding */ MpegContentError),
  MpegParser: () => (/* binding */ MpegParser)
});

// EXTERNAL MODULE: ./bridge/node_modules/debug/src/index.js
var src = __webpack_require__(5721);
// EXTERNAL MODULE: ./bridge/node_modules/strtok3/lib/index.js + 2 modules
var lib = __webpack_require__(6481);
// EXTERNAL MODULE: ./bridge/node_modules/token-types/lib/index.js
var token_types_lib = __webpack_require__(9431);
// EXTERNAL MODULE: ./bridge/node_modules/music-metadata/lib/common/Util.js
var Util = __webpack_require__(7659);
// EXTERNAL MODULE: ./bridge/node_modules/music-metadata/lib/id3v2/AbstractID3Parser.js
var AbstractID3Parser = __webpack_require__(87);
// EXTERNAL MODULE: ./bridge/node_modules/music-metadata/lib/ParseError.js
var ParseError = __webpack_require__(4326);
;// CONCATENATED MODULE: ./bridge/node_modules/music-metadata/lib/mpeg/ReplayGainDataFormat.js

/**
 * https://github.com/Borewit/music-metadata/wiki/Replay-Gain-Data-Format#name-code
 */
const NameCode = {
    /**
     * not set
     */
    not_set: 0,
    /**
     * Radio Gain Adjustment
     */
    radio: 1,
    /**
     * Audiophile Gain Adjustment
     */
    audiophile: 2
};
/**
 * https://github.com/Borewit/music-metadata/wiki/Replay-Gain-Data-Format#originator-code
 */
const ReplayGainOriginator = {
    /**
     * Replay Gain unspecified
     */
    unspecified: 0,
    /**
     * Replay Gain pre-set by artist/producer/mastering engineer
     */
    engineer: 1,
    /**
     * Replay Gain set by user
     */
    user: 2,
    /**
     * Replay Gain determined automatically, as described on this site
     */
    automatic: 3,
    /**
     * Set by simple RMS average
     */
    rms_average: 4
};
/**
 * Replay Gain Data Format
 *
 * https://github.com/Borewit/music-metadata/wiki/Replay-Gain-Data-Format
 */
const ReplayGain = {
    len: 2,
    get: (buf, off) => {
        const gain_type = Util/* getBitAllignedNumber */.f5(buf, off, 0, 3);
        const sign = Util/* getBitAllignedNumber */.f5(buf, off, 6, 1);
        const gain_adj = Util/* getBitAllignedNumber */.f5(buf, off, 7, 9) / 10.0;
        if (gain_type > 0) {
            return {
                type: Util/* getBitAllignedNumber */.f5(buf, off, 0, 3),
                origin: Util/* getBitAllignedNumber */.f5(buf, off, 3, 3),
                adjustment: sign ? -gain_adj : gain_adj
            };
        }
        return undefined;
    }
};

;// CONCATENATED MODULE: ./bridge/node_modules/music-metadata/lib/mpeg/ExtendedLameHeader.js
/**
 * Extended Lame Header
 */



/**
 * Info Tag
 * @link http://gabriel.mp3-tech.org/mp3infotag.html
 * @link https://github.com/quodlibet/mutagen/blob/abd58ee58772224334a18817c3fb31103572f70e/mutagen/mp3/_util.py#L112
 */
const ExtendedLameHeader = {
    len: 27,
    get: (buf, off) => {
        const track_peak = token_types_lib.UINT32_BE.get(buf, off + 2);
        return {
            revision: Util/* getBitAllignedNumber */.f5(buf, off, 0, 4),
            vbr_method: Util/* getBitAllignedNumber */.f5(buf, off, 4, 4),
            lowpass_filter: 100 * token_types_lib.UINT8.get(buf, off + 1),
            track_peak: track_peak === 0 ? null : track_peak / 2 ** 23,
            track_gain: ReplayGain.get(buf, 6),
            album_gain: ReplayGain.get(buf, 8),
            music_length: token_types_lib.UINT32_BE.get(buf, off + 20),
            music_crc: token_types_lib.UINT8.get(buf, off + 24),
            header_crc: token_types_lib.UINT16_BE.get(buf, off + 24)
        };
    }
};

;// CONCATENATED MODULE: ./bridge/node_modules/music-metadata/lib/mpeg/XingTag.js



/**
 * Info Tag: Xing, LAME
 */
const InfoTagHeaderTag = new token_types_lib.StringType(4, 'ascii');
/**
 * LAME TAG value
 * Did not find any official documentation for this
 * Value e.g.: "3.98.4"
 */
const LameEncoderVersion = new token_types_lib.StringType(6, 'ascii');
/**
 * Info Tag
 * Ref: http://gabriel.mp3-tech.org/mp3infotag.html
 */
const XingHeaderFlags = {
    len: 4,
    get: (buf, off) => {
        return {
            frames: Util/* isBitSet */.cD(buf, off, 31),
            bytes: Util/* isBitSet */.cD(buf, off, 30),
            toc: Util/* isBitSet */.cD(buf, off, 29),
            vbrScale: Util/* isBitSet */.cD(buf, off, 28)
        };
    }
};
// /**
//  * XING Header Tag
//  * Ref: http://gabriel.mp3-tech.org/mp3infotag.html
//  */
async function readXingHeader(tokenizer) {
    const flags = await tokenizer.readToken(XingHeaderFlags);
    const xingInfoTag = { numFrames: null, streamSize: null, vbrScale: null };
    if (flags.frames) {
        xingInfoTag.numFrames = await tokenizer.readToken(token_types_lib.UINT32_BE);
    }
    if (flags.bytes) {
        xingInfoTag.streamSize = await tokenizer.readToken(token_types_lib.UINT32_BE);
    }
    if (flags.toc) {
        xingInfoTag.toc = new Uint8Array(100);
        await tokenizer.readBuffer(xingInfoTag.toc);
    }
    if (flags.vbrScale) {
        xingInfoTag.vbrScale = await tokenizer.readToken(token_types_lib.UINT32_BE);
    }
    const lameTag = await tokenizer.peekToken(new token_types_lib.StringType(4, 'ascii'));
    if (lameTag === 'LAME') {
        await tokenizer.ignore(4);
        xingInfoTag.lame = {
            version: await tokenizer.readToken(new token_types_lib.StringType(5, 'ascii'))
        };
        const match = xingInfoTag.lame.version.match(/\d+.\d+/g);
        if (match !== null) {
            const majorMinorVersion = match[0]; // e.g. 3.97
            const version = majorMinorVersion.split('.').map(n => Number.parseInt(n, 10));
            if (version[0] >= 3 && version[1] >= 90) {
                xingInfoTag.lame.extended = await tokenizer.readToken(ExtendedLameHeader);
            }
        }
    }
    return xingInfoTag;
}

;// CONCATENATED MODULE: ./bridge/node_modules/music-metadata/lib/mpeg/MpegParser.js







const debug = src('music-metadata:parser:mpeg');
class MpegContentError extends (0,ParseError/* makeUnexpectedFileContentError */.fO)('MPEG') {
}
/**
 * Cache buffer size used for searching synchronization preabmle
 */
const maxPeekLen = 1024;
/**
 * MPEG-4 Audio definitions
 * Ref:  https://wiki.multimedia.cx/index.php/MPEG-4_Audio
 */
const MPEG4 = {
    /**
     * Audio Object Types
     */
    AudioObjectTypes: [
        'AAC Main',
        'AAC LC', // Low Complexity
        'AAC SSR', // Scalable Sample Rate
        'AAC LTP' // Long Term Prediction
    ],
    /**
     * Sampling Frequencies
     * https://wiki.multimedia.cx/index.php/MPEG-4_Audio#Sampling_Frequencies
     */
    SamplingFrequencies: [
        96000,
        88200,
        64000,
        48000,
        44100,
        32000,
        24000,
        22050,
        16000,
        12000,
        11025,
        8000,
        7350,
        null,
        null,
        null
    ]
    /**
     * Channel Configurations
     */
};
const MPEG4_ChannelConfigurations = [
    undefined,
    ['front-center'],
    ['front-left', 'front-right'],
    ['front-center', 'front-left', 'front-right'],
    ['front-center', 'front-left', 'front-right', 'back-center'],
    ['front-center', 'front-left', 'front-right', 'back-left', 'back-right'],
    ['front-center', 'front-left', 'front-right', 'back-left', 'back-right', 'LFE-channel'],
    ['front-center', 'front-left', 'front-right', 'side-left', 'side-right', 'back-left', 'back-right', 'LFE-channel']
];
/**
 * MPEG Audio Layer I/II/III frame header
 * Ref: https://www.mp3-tech.org/programmer/frame_header.html
 * Bit layout: AAAAAAAA AAABBCCD EEEEFFGH IIJJKLMM
 * Ref: https://wiki.multimedia.cx/index.php/ADTS
 */
class MpegFrameHeader {
    constructor(buf, off) {
        // E(15,12): Bitrate index
        this.bitrateIndex = null;
        // F(11,10): Sampling rate frequency index
        this.sampRateFreqIndex = null;
        // G(9): Padding bit
        this.padding = null;
        // H(8): Private bit
        this.privateBit = null;
        // I(7,6): Channel Mode
        this.channelModeIndex = null;
        // J(5,4): Mode extension (Only used in Joint stereo)
        this.modeExtension = null;
        // L(2): Original
        this.isOriginalMedia = null;
        this.version = null;
        this.bitrate = null;
        this.samplingRate = null;
        this.frameLength = 0;
        // B(20,19): MPEG Audio versionIndex ID
        this.versionIndex = Util/* getBitAllignedNumber */.f5(buf, off + 1, 3, 2);
        // C(18,17): Layer description
        this.layer = MpegFrameHeader.LayerDescription[Util/* getBitAllignedNumber */.f5(buf, off + 1, 5, 2)];
        if (this.versionIndex > 1 && this.layer === 0) {
            this.parseAdtsHeader(buf, off); // Audio Data Transport Stream (ADTS)
        }
        else {
            this.parseMpegHeader(buf, off); // Conventional MPEG header
        }
        // D(16): Protection bit (if true 16-bit CRC follows header)
        this.isProtectedByCRC = !Util/* isBitSet */.cD(buf, off + 1, 7);
    }
    calcDuration(numFrames) {
        return this.samplingRate == null ? null : (numFrames * this.calcSamplesPerFrame()) / this.samplingRate;
    }
    calcSamplesPerFrame() {
        return MpegFrameHeader.samplesInFrameTable[this.version === 1 ? 0 : 1][this.layer];
    }
    calculateSideInfoLength() {
        if (this.layer !== 3) {
            return 2;
        }
        if (this.channelModeIndex === 3) {
            // mono
            if (this.version === 1) {
                return 17;
            }
            if (this.version === 2 || this.version === 2.5) {
                return 9;
            }
        }
        else {
            if (this.version === 1) {
                return 32;
            }
            if (this.version === 2 || this.version === 2.5) {
                return 17;
            }
        }
        return null;
    }
    calcSlotSize() {
        return [null, 4, 1, 1][this.layer];
    }
    parseMpegHeader(buf, off) {
        this.container = 'MPEG';
        // E(15,12): Bitrate index
        this.bitrateIndex = Util/* getBitAllignedNumber */.f5(buf, off + 2, 0, 4);
        // F(11,10): Sampling rate frequency index
        this.sampRateFreqIndex = Util/* getBitAllignedNumber */.f5(buf, off + 2, 4, 2);
        // G(9): Padding bit
        this.padding = Util/* isBitSet */.cD(buf, off + 2, 6);
        // H(8): Private bit
        this.privateBit = Util/* isBitSet */.cD(buf, off + 2, 7);
        // I(7,6): Channel Mode
        this.channelModeIndex = Util/* getBitAllignedNumber */.f5(buf, off + 3, 0, 2);
        // J(5,4): Mode extension (Only used in Joint stereo)
        this.modeExtension = Util/* getBitAllignedNumber */.f5(buf, off + 3, 2, 2);
        // K(3): Copyright
        this.isCopyrighted = Util/* isBitSet */.cD(buf, off + 3, 4);
        // L(2): Original
        this.isOriginalMedia = Util/* isBitSet */.cD(buf, off + 3, 5);
        // M(3): The original bit indicates, if it is set, that the frame is located on its original media.
        this.emphasis = Util/* getBitAllignedNumber */.f5(buf, off + 3, 7, 2);
        this.version = MpegFrameHeader.VersionID[this.versionIndex];
        this.channelMode = MpegFrameHeader.ChannelMode[this.channelModeIndex];
        this.codec = `MPEG ${this.version} Layer ${this.layer}`;
        // Calculate bitrate
        const bitrateInKbps = this.calcBitrate();
        if (!bitrateInKbps) {
            throw new MpegContentError('Cannot determine bit-rate');
        }
        this.bitrate = bitrateInKbps * 1000;
        // Calculate sampling rate
        this.samplingRate = this.calcSamplingRate();
        if (this.samplingRate == null) {
            throw new MpegContentError('Cannot determine sampling-rate');
        }
    }
    parseAdtsHeader(buf, off) {
        debug('layer=0 => ADTS');
        this.version = this.versionIndex === 2 ? 4 : 2;
        this.container = `ADTS/MPEG-${this.version}`;
        const profileIndex = Util/* getBitAllignedNumber */.f5(buf, off + 2, 0, 2);
        this.codec = 'AAC';
        this.codecProfile = MPEG4.AudioObjectTypes[profileIndex];
        debug(`MPEG-4 audio-codec=${this.codec}`);
        const samplingFrequencyIndex = Util/* getBitAllignedNumber */.f5(buf, off + 2, 2, 4);
        this.samplingRate = MPEG4.SamplingFrequencies[samplingFrequencyIndex];
        debug(`sampling-rate=${this.samplingRate}`);
        const channelIndex = Util/* getBitAllignedNumber */.f5(buf, off + 2, 7, 3);
        this.mp4ChannelConfig = MPEG4_ChannelConfigurations[channelIndex];
        debug(`channel-config=${this.mp4ChannelConfig ? this.mp4ChannelConfig.join('+') : '?'}`);
        this.frameLength = Util/* getBitAllignedNumber */.f5(buf, off + 3, 6, 2) << 11;
    }
    calcBitrate() {
        if (this.bitrateIndex === 0x00 || // free
            this.bitrateIndex === 0x0f) {
            // reserved
            return null;
        }
        if (this.version && this.bitrateIndex) {
            const codecIndex = 10 * Math.floor(this.version) + this.layer;
            return MpegFrameHeader.bitrate_index[this.bitrateIndex][codecIndex];
        }
        return null;
    }
    calcSamplingRate() {
        if (this.sampRateFreqIndex === 0x03 || this.version === null || this.sampRateFreqIndex == null) {
            return null; // 'reserved'
        }
        return MpegFrameHeader.sampling_rate_freq_index[this.version][this.sampRateFreqIndex];
    }
}
MpegFrameHeader.SyncByte1 = 0xff;
MpegFrameHeader.SyncByte2 = 0xe0;
MpegFrameHeader.VersionID = [2.5, null, 2, 1];
MpegFrameHeader.LayerDescription = [0, 3, 2, 1];
MpegFrameHeader.ChannelMode = ['stereo', 'joint_stereo', 'dual_channel', 'mono'];
MpegFrameHeader.bitrate_index = {
    1: { 11: 32, 12: 32, 13: 32, 21: 32, 22: 8, 23: 8 },
    2: { 11: 64, 12: 48, 13: 40, 21: 48, 22: 16, 23: 16 },
    3: { 11: 96, 12: 56, 13: 48, 21: 56, 22: 24, 23: 24 },
    4: { 11: 128, 12: 64, 13: 56, 21: 64, 22: 32, 23: 32 },
    5: { 11: 160, 12: 80, 13: 64, 21: 80, 22: 40, 23: 40 },
    6: { 11: 192, 12: 96, 13: 80, 21: 96, 22: 48, 23: 48 },
    7: { 11: 224, 12: 112, 13: 96, 21: 112, 22: 56, 23: 56 },
    8: { 11: 256, 12: 128, 13: 112, 21: 128, 22: 64, 23: 64 },
    9: { 11: 288, 12: 160, 13: 128, 21: 144, 22: 80, 23: 80 },
    10: { 11: 320, 12: 192, 13: 160, 21: 160, 22: 96, 23: 96 },
    11: { 11: 352, 12: 224, 13: 192, 21: 176, 22: 112, 23: 112 },
    12: { 11: 384, 12: 256, 13: 224, 21: 192, 22: 128, 23: 128 },
    13: { 11: 416, 12: 320, 13: 256, 21: 224, 22: 144, 23: 144 },
    14: { 11: 448, 12: 384, 13: 320, 21: 256, 22: 160, 23: 160 }
};
MpegFrameHeader.sampling_rate_freq_index = {
    1: { 0: 44100, 1: 48000, 2: 32000 },
    2: { 0: 22050, 1: 24000, 2: 16000 },
    2.5: { 0: 11025, 1: 12000, 2: 8000 }
};
MpegFrameHeader.samplesInFrameTable = [
    /* Layer   I    II   III */
    [0, 384, 1152, 1152], // MPEG-1
    [0, 384, 1152, 576] // MPEG-2(.5
];
/**
 * MPEG Audio Layer I/II/III
 */
const FrameHeader = {
    len: 4,
    get: (buf, off) => {
        return new MpegFrameHeader(buf, off);
    }
};
function getVbrCodecProfile(vbrScale) {
    return `V${Math.floor((100 - vbrScale) / 10)}`;
}
class MpegParser extends AbstractID3Parser/* AbstractID3Parser */.k {
    constructor() {
        super(...arguments);
        this.frameCount = 0;
        this.syncFrameCount = -1;
        this.totalDataLength = 0;
        this.bitrates = [];
        this.offset = 0;
        this.frame_size = 0;
        this.calculateEofDuration = false;
        this.samplesPerFrame = null;
        this.buf_frame_header = new Uint8Array(4);
        /**
         * Number of bytes already parsed since beginning of stream / file
         */
        this.mpegOffset = null;
        this.syncPeek = {
            buf: new Uint8Array(maxPeekLen),
            len: 0
        };
    }
    /**
     * Called after ID3 headers have been parsed
     */
    async postId3v2Parse() {
        this.metadata.setFormat('lossless', false);
        this.metadata.setAudioOnly();
        try {
            let quit = false;
            while (!quit) {
                await this.sync();
                quit = await this.parseCommonMpegHeader();
            }
        }
        catch (err) {
            if (err instanceof lib/* EndOfStreamError */.d1) {
                debug('End-of-stream');
                if (this.calculateEofDuration) {
                    if (this.samplesPerFrame !== null) {
                        const numberOfSamples = this.frameCount * this.samplesPerFrame;
                        this.metadata.setFormat('numberOfSamples', numberOfSamples);
                        if (this.metadata.format.sampleRate) {
                            const duration = numberOfSamples / this.metadata.format.sampleRate;
                            debug(`Calculate duration at EOF: ${duration} sec.`, duration);
                            this.metadata.setFormat('duration', duration);
                        }
                    }
                }
            }
            else {
                throw err;
            }
        }
    }
    /**
     * Called after file has been fully parsed, this allows, if present, to exclude the ID3v1.1 header length
     */
    finalize() {
        const format = this.metadata.format;
        const hasID3v1 = !!this.metadata.native.ID3v1;
        if (this.mpegOffset !== null) {
            if (format.duration && this.tokenizer.fileInfo.size) {
                const mpegSize = this.tokenizer.fileInfo.size - this.mpegOffset - (hasID3v1 ? 128 : 0);
                if (format.codecProfile && format.codecProfile[0] === 'V') {
                    this.metadata.setFormat('bitrate', (mpegSize * 8) / format.duration);
                }
            }
            if (this.tokenizer.fileInfo.size && format.codecProfile === 'CBR') {
                const mpegSize = this.tokenizer.fileInfo.size - this.mpegOffset - (hasID3v1 ? 128 : 0);
                if (this.frame_size !== null && this.samplesPerFrame !== null) {
                    const numberOfSamples = Math.round(mpegSize / this.frame_size) * this.samplesPerFrame;
                    this.metadata.setFormat('numberOfSamples', numberOfSamples);
                    if (format.sampleRate && !format.duration) {
                        const duration = numberOfSamples / format.sampleRate;
                        debug('Calculate CBR duration based on file size: %s', duration);
                        this.metadata.setFormat('duration', duration);
                    }
                }
            }
        }
    }
    async sync() {
        let gotFirstSync = false;
        while (true) {
            let bo = 0;
            this.syncPeek.len = await this.tokenizer.peekBuffer(this.syncPeek.buf, { length: maxPeekLen, mayBeLess: true });
            if (this.syncPeek.len <= 163) {
                throw new lib/* EndOfStreamError */.d1();
            }
            while (true) {
                if (gotFirstSync && (this.syncPeek.buf[bo] & 0xe0) === 0xe0) {
                    this.buf_frame_header[0] = MpegFrameHeader.SyncByte1;
                    this.buf_frame_header[1] = this.syncPeek.buf[bo];
                    await this.tokenizer.ignore(bo);
                    debug(`Sync at offset=${this.tokenizer.position - 1}, frameCount=${this.frameCount}`);
                    if (this.syncFrameCount === this.frameCount) {
                        debug(`Re-synced MPEG stream, frameCount=${this.frameCount}`);
                        this.frameCount = 0;
                        this.frame_size = 0;
                    }
                    this.syncFrameCount = this.frameCount;
                    return; // sync
                }
                gotFirstSync = false;
                bo = this.syncPeek.buf.indexOf(MpegFrameHeader.SyncByte1, bo);
                if (bo === -1) {
                    if (this.syncPeek.len < this.syncPeek.buf.length) {
                        throw new lib/* EndOfStreamError */.d1();
                    }
                    await this.tokenizer.ignore(this.syncPeek.len);
                    break; // continue with next buffer
                }
                ++bo;
                gotFirstSync = true;
            }
        }
    }
    /**
     * Combined ADTS & MPEG (MP2 & MP3) header handling
     * @return {Promise<boolean>} true if parser should quit
     */
    async parseCommonMpegHeader() {
        if (this.frameCount === 0) {
            this.mpegOffset = this.tokenizer.position - 1;
        }
        await this.tokenizer.peekBuffer(this.buf_frame_header.subarray(1), { length: 3 });
        let header;
        try {
            header = FrameHeader.get(this.buf_frame_header, 0);
        }
        catch (err) {
            await this.tokenizer.ignore(1);
            if (err instanceof Error) {
                this.metadata.addWarning(`Parse error: ${err.message}`);
                return false; // sync
            }
            throw err;
        }
        await this.tokenizer.ignore(3);
        this.metadata.setFormat('container', header.container);
        this.metadata.setFormat('codec', header.codec);
        this.metadata.setFormat('lossless', false);
        this.metadata.setFormat('sampleRate', header.samplingRate);
        this.frameCount++;
        return header.version !== null && header.version >= 2 && header.layer === 0
            ? this.parseAdts(header)
            : this.parseAudioFrameHeader(header);
    }
    /**
     * @return {Promise<boolean>} true if parser should quit
     */
    async parseAudioFrameHeader(header) {
        this.metadata.setFormat('numberOfChannels', header.channelMode === 'mono' ? 1 : 2);
        this.metadata.setFormat('bitrate', header.bitrate);
        if (this.frameCount < 20 * 10000) {
            debug('offset=%s MP%s bitrate=%s sample-rate=%s', this.tokenizer.position - 4, header.layer, header.bitrate, header.samplingRate);
        }
        const slot_size = header.calcSlotSize();
        if (slot_size === null) {
            throw new MpegContentError('invalid slot_size');
        }
        const samples_per_frame = header.calcSamplesPerFrame();
        debug(`samples_per_frame=${samples_per_frame}`);
        const bps = samples_per_frame / 8.0;
        if (header.bitrate !== null && header.samplingRate != null) {
            const fsize = (bps * header.bitrate) / header.samplingRate + (header.padding ? slot_size : 0);
            this.frame_size = Math.floor(fsize);
        }
        this.audioFrameHeader = header;
        if (header.bitrate !== null) {
            this.bitrates.push(header.bitrate);
        }
        // xtra header only exists in first frame
        if (this.frameCount === 1) {
            this.offset = FrameHeader.len;
            await this.skipSideInformation();
            return false;
        }
        if (this.frameCount === 4) {
            // the stream is CBR if the first 3 frame bitrates are the same
            if (this.areAllSame(this.bitrates)) {
                // Actual calculation will be done in finalize
                this.samplesPerFrame = samples_per_frame;
                this.metadata.setFormat('codecProfile', 'CBR');
                if (this.tokenizer.fileInfo.size) {
                    return true; // Will calculate duration based on the file size
                }
            }
            else if (this.metadata.format.duration) {
                return true; // We already got the duration, stop processing MPEG stream any further
            }
            if (!this.options.duration) {
                return true; // Enforce duration not enabled, stop processing entire stream
            }
        }
        // once we know the file is VBR attach listener to end of
        // stream so we can do the duration calculation when we
        // have counted all the frames
        if (this.options.duration && this.frameCount === 4) {
            this.samplesPerFrame = samples_per_frame;
            this.calculateEofDuration = true;
        }
        this.offset = 4;
        if (header.isProtectedByCRC) {
            await this.parseCrc();
            return false;
        }
        await this.skipSideInformation();
        return false;
    }
    async parseAdts(header) {
        const buf = new Uint8Array(3);
        await this.tokenizer.readBuffer(buf);
        header.frameLength += Util/* getBitAllignedNumber */.f5(buf, 0, 0, 11);
        this.totalDataLength += header.frameLength;
        this.samplesPerFrame = 1024;
        if (header.samplingRate !== null) {
            const framesPerSec = header.samplingRate / this.samplesPerFrame;
            const bytesPerFrame = this.frameCount === 0 ? 0 : this.totalDataLength / this.frameCount;
            const bitrate = 8 * bytesPerFrame * framesPerSec + 0.5;
            this.metadata.setFormat('bitrate', bitrate);
            debug(`frame-count=${this.frameCount}, size=${header.frameLength} bytes, bit-rate=${bitrate}`);
        }
        await this.tokenizer.ignore(header.frameLength > 7 ? header.frameLength - 7 : 1);
        // Consume remaining header and frame data
        if (this.frameCount === 3) {
            this.metadata.setFormat('codecProfile', header.codecProfile);
            if (header.mp4ChannelConfig) {
                this.metadata.setFormat('numberOfChannels', header.mp4ChannelConfig.length);
            }
            if (this.options.duration) {
                this.calculateEofDuration = true;
            }
            else {
                return true; // Stop parsing after the third frame
            }
        }
        return false;
    }
    async parseCrc() {
        await this.tokenizer.ignore(token_types_lib.INT16_BE.len); // Ignore CRC
        this.offset += token_types_lib.INT16_BE.len;
        return this.skipSideInformation();
    }
    async skipSideInformation() {
        if (this.audioFrameHeader) {
            const sideinfo_length = this.audioFrameHeader.calculateSideInfoLength();
            if (sideinfo_length !== null) {
                await this.tokenizer.readToken(new token_types_lib.Uint8ArrayType(sideinfo_length));
                // side information
                this.offset += sideinfo_length;
                await this.readXtraInfoHeader();
                return;
            }
        }
    }
    async readXtraInfoHeader() {
        const headerTag = await this.tokenizer.readToken(InfoTagHeaderTag);
        this.offset += InfoTagHeaderTag.len; // 12
        switch (headerTag) {
            case 'Info':
                this.metadata.setFormat('codecProfile', 'CBR');
                return this.readXingInfoHeader();
            case 'Xing': {
                const infoTag = await this.readXingInfoHeader();
                if (infoTag.vbrScale !== null) {
                    const codecProfile = getVbrCodecProfile(infoTag.vbrScale);
                    this.metadata.setFormat('codecProfile', codecProfile);
                }
                return null;
            }
            case 'Xtra':
                // ToDo: ???
                break;
            case 'LAME': {
                const version = await this.tokenizer.readToken(LameEncoderVersion);
                if (this.frame_size !== null && this.frame_size >= this.offset + LameEncoderVersion.len) {
                    this.offset += LameEncoderVersion.len;
                    this.metadata.setFormat('tool', `LAME ${version}`);
                    await this.skipFrameData(this.frame_size - this.offset);
                    return null;
                }
                this.metadata.addWarning('Corrupt LAME header');
                break;
            }
            // ToDo: ???
        }
        // ToDo: promise duration???
        const frameDataLeft = this.frame_size - this.offset;
        if (frameDataLeft < 0) {
            this.metadata.addWarning(`Frame ${this.frameCount}corrupt: negative frameDataLeft`);
        }
        else {
            await this.skipFrameData(frameDataLeft);
        }
        return null;
    }
    /**
     * Ref: http://gabriel.mp3-tech.org/mp3infotag.html
     * @returns {Promise<string>}
     */
    async readXingInfoHeader() {
        const offset = this.tokenizer.position;
        const infoTag = await readXingHeader(this.tokenizer);
        this.offset += this.tokenizer.position - offset;
        if (infoTag.lame) {
            this.metadata.setFormat('tool', `LAME ${Util/* stripNulls */.F6(infoTag.lame.version)}`);
            if (infoTag.lame.extended) {
                // this.metadata.setFormat('trackGain', infoTag.lame.extended.track_gain);
                this.metadata.setFormat('trackPeakLevel', infoTag.lame.extended.track_peak);
                if (infoTag.lame.extended.track_gain) {
                    this.metadata.setFormat('trackGain', infoTag.lame.extended.track_gain.adjustment);
                }
                if (infoTag.lame.extended.album_gain) {
                    this.metadata.setFormat('albumGain', infoTag.lame.extended.album_gain.adjustment);
                }
                this.metadata.setFormat('duration', infoTag.lame.extended.music_length / 1000);
            }
        }
        if (infoTag.streamSize && this.audioFrameHeader && infoTag.numFrames !== null) {
            const duration = this.audioFrameHeader.calcDuration(infoTag.numFrames);
            this.metadata.setFormat('duration', duration);
            debug('Get duration from Xing header: %s', this.metadata.format.duration);
            return infoTag;
        }
        // frames field is not present
        const frameDataLeft = this.frame_size - this.offset;
        await this.skipFrameData(frameDataLeft);
        return infoTag;
    }
    async skipFrameData(frameDataLeft) {
        if (frameDataLeft < 0) {
            throw new MpegContentError('frame-data-left cannot be negative');
        }
        await this.tokenizer.ignore(frameDataLeft);
    }
    areAllSame(array) {
        const first = array[0];
        return array.every(element => {
            return element === first;
        });
    }
}


/***/ })

};
;