"use strict";
exports.id = 523;
exports.ids = [523];
exports.modules = {

/***/ 4763:
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   k: () => (/* binding */ AbstractID3Parser)
/* harmony export */ });
/* harmony import */ var debug__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(9159);
/* harmony import */ var strtok3__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(397);
/* harmony import */ var _common_BasicParser_js__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(6);
/* harmony import */ var _id3v1_ID3v1Parser_js__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(4233);
/* harmony import */ var _ID3v2Parser_js__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(1763);
/* harmony import */ var _ID3v2Token_js__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(5057);






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

/***/ 3523:
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

// ESM COMPAT FLAG
__webpack_require__.r(__webpack_exports__);

// EXPORTS
__webpack_require__.d(__webpack_exports__, {
  MusepackParser: () => (/* binding */ MusepackParser)
});

// EXTERNAL MODULE: ./node_modules/debug/src/index.js
var src = __webpack_require__(9159);
// EXTERNAL MODULE: ./node_modules/token-types/lib/index.js
var lib = __webpack_require__(43);
// EXTERNAL MODULE: ./node_modules/music-metadata/lib/id3v2/AbstractID3Parser.js
var AbstractID3Parser = __webpack_require__(4763);
// EXTERNAL MODULE: ./node_modules/music-metadata/lib/ParseError.js
var ParseError = __webpack_require__(6994);
;// CONCATENATED MODULE: ./node_modules/music-metadata/lib/musepack/MusepackConentError.js

class MusepackContentError extends (0,ParseError/* makeUnexpectedFileContentError */.fO)('Musepack') {
}

// EXTERNAL MODULE: ./node_modules/music-metadata/lib/apev2/APEv2Parser.js + 1 modules
var APEv2Parser = __webpack_require__(6311);
// EXTERNAL MODULE: ./node_modules/music-metadata/lib/common/BasicParser.js
var BasicParser = __webpack_require__(6);
;// CONCATENATED MODULE: ./node_modules/music-metadata/lib/musepack/sv7/BitReader.js

class BitReader {
    constructor(tokenizer) {
        this.pos = 0;
        this.dword = null;
        this.tokenizer = tokenizer;
    }
    /**
     *
     * @param bits 1..30 bits
     */
    async read(bits) {
        while (this.dword === null) {
            this.dword = await this.tokenizer.readToken(lib.UINT32_LE);
        }
        let out = this.dword;
        this.pos += bits;
        if (this.pos < 32) {
            out >>>= 32 - this.pos;
            return out & ((1 << bits) - 1);
        }
        this.pos -= 32;
        if (this.pos === 0) {
            this.dword = null;
            return out & ((1 << bits) - 1);
        }
        this.dword = await this.tokenizer.readToken(lib.UINT32_LE);
        if (this.pos) {
            out <<= this.pos;
            out |= this.dword >>> (32 - this.pos);
        }
        return out & ((1 << bits) - 1);
    }
    async ignore(bits) {
        if (this.pos > 0) {
            const remaining = 32 - this.pos;
            this.dword = null;
            bits -= remaining;
            this.pos = 0;
        }
        const remainder = bits % 32;
        const numOfWords = (bits - remainder) / 32;
        await this.tokenizer.ignore(numOfWords * 4);
        return this.read(remainder);
    }
}

// EXTERNAL MODULE: ./node_modules/@borewit/text-codec/lib/index.js
var text_codec_lib = __webpack_require__(7245);
// EXTERNAL MODULE: ./node_modules/music-metadata/lib/common/Util.js
var Util = __webpack_require__(3791);
;// CONCATENATED MODULE: ./node_modules/music-metadata/lib/musepack/sv7/StreamVersion7.js



/**
 * BASIC STRUCTURE
 */
const Header = {
    len: 6 * 4,
    get: (buf, off) => {
        const header = {
            // word 0
            signature: (0,text_codec_lib/* textDecode */.c)(buf.subarray(off, off + 3), 'latin1'),
            // versionIndex number * 1000 (3.81 = 3810) (remember that 4-byte alignment causes this to take 4-bytes)
            streamMinorVersion: Util/* getBitAllignedNumber */.f5(buf, off + 3, 0, 4),
            streamMajorVersion: Util/* getBitAllignedNumber */.f5(buf, off + 3, 4, 4),
            // word 1
            frameCount: lib.UINT32_LE.get(buf, off + 4),
            // word 2
            maxLevel: lib.UINT16_LE.get(buf, off + 8),
            sampleFrequency: [44100, 48000, 37800, 32000][Util/* getBitAllignedNumber */.f5(buf, off + 10, 0, 2)],
            link: Util/* getBitAllignedNumber */.f5(buf, off + 10, 2, 2),
            profile: Util/* getBitAllignedNumber */.f5(buf, off + 10, 4, 4),
            maxBand: Util/* getBitAllignedNumber */.f5(buf, off + 11, 0, 6),
            intensityStereo: Util/* isBitSet */.cD(buf, off + 11, 6),
            midSideStereo: Util/* isBitSet */.cD(buf, off + 11, 7),
            // word 3
            titlePeak: lib.UINT16_LE.get(buf, off + 12),
            titleGain: lib.UINT16_LE.get(buf, off + 14),
            // word 4
            albumPeak: lib.UINT16_LE.get(buf, off + 16),
            albumGain: lib.UINT16_LE.get(buf, off + 18),
            // word
            lastFrameLength: (lib.UINT32_LE.get(buf, off + 20) >>> 20) & 0x7ff,
            trueGapless: Util/* isBitSet */.cD(buf, off + 23, 0)
        };
        header.lastFrameLength = header.trueGapless ? (lib.UINT32_LE.get(buf, 20) >>> 20) & 0x7ff : 0;
        return header;
    }
};

;// CONCATENATED MODULE: ./node_modules/music-metadata/lib/musepack/sv7/MpcSv7Parser.js






const debug = src('music-metadata:parser:musepack');
class MpcSv7Parser extends BasicParser/* BasicParser */.s {
    constructor() {
        super(...arguments);
        this.bitreader = null;
        this.audioLength = 0;
        this.duration = null;
    }
    async parse() {
        const header = await this.tokenizer.readToken(Header);
        if (header.signature !== 'MP+') {
            throw new MusepackContentError('Unexpected magic number');
        }
        debug(`stream-version=${header.streamMajorVersion}.${header.streamMinorVersion}`);
        this.metadata.setFormat('container', 'Musepack, SV7');
        this.metadata.setFormat('sampleRate', header.sampleFrequency);
        const numberOfSamples = 1152 * (header.frameCount - 1) + header.lastFrameLength;
        this.metadata.setFormat('numberOfSamples', numberOfSamples);
        this.duration = numberOfSamples / header.sampleFrequency;
        this.metadata.setFormat('duration', this.duration);
        this.bitreader = new BitReader(this.tokenizer);
        this.metadata.setFormat('numberOfChannels', header.midSideStereo || header.intensityStereo ? 2 : 1);
        const version = await this.bitreader.read(8);
        this.metadata.setFormat('codec', (version / 100).toFixed(2));
        await this.skipAudioData(header.frameCount);
        debug(`End of audio stream, switching to APEv2, offset=${this.tokenizer.position}`);
        return (0,APEv2Parser.tryParseApeHeader)(this.metadata, this.tokenizer, this.options);
    }
    async skipAudioData(frameCount) {
        while (frameCount-- > 0) {
            const frameLength = await this.bitreader.read(20);
            this.audioLength += 20 + frameLength;
            await this.bitreader.ignore(frameLength);
        }
        // last frame
        const lastFrameLength = await this.bitreader.read(11);
        this.audioLength += lastFrameLength;
        if (this.duration !== null) {
            this.metadata.setFormat('bitrate', this.audioLength / this.duration);
        }
    }
}

// EXTERNAL MODULE: ./node_modules/music-metadata/lib/common/FourCC.js
var FourCC = __webpack_require__(6777);
;// CONCATENATED MODULE: ./node_modules/music-metadata/lib/musepack/sv8/StreamVersion8.js



const StreamVersion8_debug = src('music-metadata:parser:musepack:sv8');
const PacketKey = new lib.StringType(2, 'latin1');
/**
 * Stream Header Packet part 1
 * Ref: http://trac.musepack.net/musepack/wiki/SV8Specification#StreamHeaderPacket
 */
const SH_part1 = {
    len: 5,
    get: (buf, off) => {
        return {
            crc: lib.UINT32_LE.get(buf, off),
            streamVersion: lib.UINT8.get(buf, off + 4)
        };
    }
};
/**
 * Stream Header Packet part 3
 * Ref: http://trac.musepack.net/musepack/wiki/SV8Specification#StreamHeaderPacket
 */
const SH_part3 = {
    len: 2,
    get: (buf, off) => {
        return {
            sampleFrequency: [44100, 48000, 37800, 32000][Util/* getBitAllignedNumber */.f5(buf, off, 0, 3)],
            maxUsedBands: Util/* getBitAllignedNumber */.f5(buf, off, 3, 5),
            channelCount: Util/* getBitAllignedNumber */.f5(buf, off + 1, 0, 4) + 1,
            msUsed: Util/* isBitSet */.cD(buf, off + 1, 4),
            audioBlockFrames: Util/* getBitAllignedNumber */.f5(buf, off + 1, 5, 3)
        };
    }
};
class StreamReader {
    get tokenizer() {
        return this._tokenizer;
    }
    set tokenizer(value) {
        this._tokenizer = value;
    }
    constructor(_tokenizer) {
        this._tokenizer = _tokenizer;
    }
    async readPacketHeader() {
        const key = await this.tokenizer.readToken(PacketKey);
        const size = await this.readVariableSizeField();
        return {
            key,
            payloadLength: size.value - 2 - size.len
        };
    }
    async readStreamHeader(size) {
        const streamHeader = {};
        StreamVersion8_debug(`Reading SH at offset=${this.tokenizer.position}`);
        const part1 = await this.tokenizer.readToken(SH_part1);
        size -= SH_part1.len;
        Object.assign(streamHeader, part1);
        StreamVersion8_debug(`SH.streamVersion = ${part1.streamVersion}`);
        const sampleCount = await this.readVariableSizeField();
        size -= sampleCount.len;
        streamHeader.sampleCount = sampleCount.value;
        const bs = await this.readVariableSizeField();
        size -= bs.len;
        streamHeader.beginningOfSilence = bs.value;
        const part3 = await this.tokenizer.readToken(SH_part3);
        size -= SH_part3.len;
        Object.assign(streamHeader, part3);
        // assert.equal(size, 0);
        await this.tokenizer.ignore(size);
        return streamHeader;
    }
    async readVariableSizeField(len = 1, hb = 0) {
        let n = await this.tokenizer.readNumber(lib.UINT8);
        if ((n & 0x80) === 0) {
            return { len, value: hb + n };
        }
        n &= 0x7f;
        n += hb;
        return this.readVariableSizeField(len + 1, n << 7);
    }
}

;// CONCATENATED MODULE: ./node_modules/music-metadata/lib/musepack/sv8/MpcSv8Parser.js






const MpcSv8Parser_debug = src('music-metadata:parser:musepack');
class MpcSv8Parser extends BasicParser/* BasicParser */.s {
    constructor() {
        super(...arguments);
        this.audioLength = 0;
    }
    async parse() {
        const signature = await this.tokenizer.readToken(FourCC/* FourCcToken */.e);
        if (signature !== 'MPCK') {
            throw new MusepackContentError('Invalid Magic number');
        }
        this.metadata.setFormat('container', 'Musepack, SV8');
        return this.parsePacket();
    }
    async parsePacket() {
        const sv8reader = new StreamReader(this.tokenizer);
        do {
            const header = await sv8reader.readPacketHeader();
            MpcSv8Parser_debug(`packet-header key=${header.key}, payloadLength=${header.payloadLength}`);
            switch (header.key) {
                case 'SH': {
                    // Stream Header
                    const sh = await sv8reader.readStreamHeader(header.payloadLength);
                    this.metadata.setFormat('numberOfSamples', sh.sampleCount);
                    this.metadata.setFormat('sampleRate', sh.sampleFrequency);
                    this.metadata.setFormat('duration', sh.sampleCount / sh.sampleFrequency);
                    this.metadata.setFormat('numberOfChannels', sh.channelCount);
                    break;
                }
                case 'AP': // Audio Packet
                    this.audioLength += header.payloadLength;
                    await this.tokenizer.ignore(header.payloadLength);
                    break;
                case 'RG': // Replaygain
                case 'EI': // Encoder Info
                case 'SO': // Seek Table Offset
                case 'ST': // Seek Table
                case 'CT': // Chapter-Tag
                    await this.tokenizer.ignore(header.payloadLength);
                    break;
                case 'SE': // Stream End
                    if (this.metadata.format.duration) {
                        this.metadata.setFormat('bitrate', (this.audioLength * 8) / this.metadata.format.duration);
                    }
                    return (0,APEv2Parser.tryParseApeHeader)(this.metadata, this.tokenizer, this.options);
                default:
                    throw new MusepackContentError(`Unexpected header: ${header.key}`);
            }
            // biome-ignore lint/correctness/noConstantCondition: break is handled in the switch statement
        } while (true);
    }
}

;// CONCATENATED MODULE: ./node_modules/music-metadata/lib/musepack/MusepackParser.js






const MusepackParser_debug = src('music-metadata:parser:musepack');
class MusepackParser extends AbstractID3Parser/* AbstractID3Parser */.k {
    async postId3v2Parse() {
        const signature = await this.tokenizer.peekToken(new lib.StringType(3, 'latin1'));
        let mpcParser;
        switch (signature) {
            case 'MP+': {
                MusepackParser_debug('Stream-version 7');
                mpcParser = new MpcSv7Parser(this.metadata, this.tokenizer, this.options);
                break;
            }
            case 'MPC': {
                MusepackParser_debug('Stream-version 8');
                mpcParser = new MpcSv8Parser(this.metadata, this.tokenizer, this.options);
                break;
            }
            default: {
                throw new MusepackContentError('Invalid signature prefix');
            }
        }
        this.metadata.setAudioOnly();
        return mpcParser.parse();
    }
}


/***/ })

};
;