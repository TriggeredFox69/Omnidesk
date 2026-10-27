"use strict";
exports.id = 127;
exports.ids = [127];
exports.modules = {

/***/ 7127:
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

// ESM COMPAT FLAG
__webpack_require__.r(__webpack_exports__);

// EXPORTS
__webpack_require__.d(__webpack_exports__, {
  AIFFParser: () => (/* binding */ AIFFParser)
});

// EXTERNAL MODULE: ./node_modules/debug/src/index.js
var src = __webpack_require__(9159);
// EXTERNAL MODULE: ./node_modules/strtok3/lib/index.js + 2 modules
var lib = __webpack_require__(397);
// EXTERNAL MODULE: ./node_modules/token-types/lib/index.js
var token_types_lib = __webpack_require__(43);
// EXTERNAL MODULE: ./node_modules/music-metadata/lib/common/BasicParser.js
var BasicParser = __webpack_require__(6);
// EXTERNAL MODULE: ./node_modules/music-metadata/lib/common/FourCC.js
var FourCC = __webpack_require__(6777);
// EXTERNAL MODULE: ./node_modules/music-metadata/lib/id3v2/ID3v2Parser.js + 3 modules
var ID3v2Parser = __webpack_require__(1763);
;// CONCATENATED MODULE: ./node_modules/music-metadata/lib/iff/index.js


/**
 * Common AIFF chunk header
 */
const Header = {
    len: 8,
    get: (buf, off) => {
        return {
            // Chunk type ID
            chunkID: FourCC/* FourCcToken */.e.get(buf, off),
            // Chunk size
            chunkSize: Number(BigInt(token_types_lib.UINT32_BE.get(buf, off + 4)))
        };
    }
};

// EXTERNAL MODULE: ./node_modules/music-metadata/lib/ParseError.js
var ParseError = __webpack_require__(6994);
;// CONCATENATED MODULE: ./node_modules/music-metadata/lib/aiff/AiffToken.js



const compressionTypes = {
    NONE: 'not compressed	PCM	Apple Computer',
    sowt: 'PCM (byte swapped)',
    fl32: '32-bit floating point IEEE 32-bit float',
    fl64: '64-bit floating point IEEE 64-bit float	Apple Computer',
    alaw: 'ALaw 2:1	8-bit ITU-T G.711 A-law',
    ulaw: 'µLaw 2:1	8-bit ITU-T G.711 µ-law	Apple Computer',
    ULAW: 'CCITT G.711 u-law 8-bit ITU-T G.711 µ-law',
    ALAW: 'CCITT G.711 A-law 8-bit ITU-T G.711 A-law',
    FL32: 'Float 32	IEEE 32-bit float '
};
class AiffContentError extends (0,ParseError/* makeUnexpectedFileContentError */.fO)('AIFF') {
}
class Common {
    constructor(header, isAifc) {
        this.isAifc = isAifc;
        const minimumChunkSize = isAifc ? 22 : 18;
        if (header.chunkSize < minimumChunkSize) {
            throw new AiffContentError(`COMMON CHUNK size should always be at least ${minimumChunkSize}`);
        }
        this.len = header.chunkSize;
    }
    get(buf, off) {
        // see: https://cycling74.com/forums/aiffs-80-bit-sample-rate-value
        const shift = token_types_lib.UINT16_BE.get(buf, off + 8) - 16398;
        const baseSampleRate = token_types_lib.UINT16_BE.get(buf, off + 8 + 2);
        const res = {
            numChannels: token_types_lib.UINT16_BE.get(buf, off),
            numSampleFrames: token_types_lib.UINT32_BE.get(buf, off + 2),
            sampleSize: token_types_lib.UINT16_BE.get(buf, off + 6),
            sampleRate: shift < 0 ? baseSampleRate >> Math.abs(shift) : baseSampleRate << shift
        };
        if (this.isAifc) {
            res.compressionType = FourCC/* FourCcToken */.e.get(buf, off + 18);
            if (this.len > 22) {
                const strLen = token_types_lib.UINT8.get(buf, off + 22);
                if (strLen > 0) {
                    const padding = (strLen + 1) % 2;
                    if (23 + strLen + padding === this.len) {
                        res.compressionName = new token_types_lib.StringType(strLen, 'latin1').get(buf, off + 23);
                    }
                    else {
                        throw new AiffContentError('Illegal pstring length');
                    }
                }
                else {
                    res.compressionName = undefined;
                }
            }
        }
        else {
            res.compressionName = 'PCM';
        }
        return res;
    }
}

;// CONCATENATED MODULE: ./node_modules/music-metadata/lib/aiff/AiffParser.js









const debug = src('music-metadata:parser:aiff');
/**
 * AIFF - Audio Interchange File Format
 *
 * Ref:
 * - http://www-mmsp.ece.mcgill.ca/Documents/AudioFormats/AIFF/AIFF.html
 * - http://www-mmsp.ece.mcgill.ca/Documents/AudioFormats/AIFF/Docs/AIFF-1.3.pdf
 */
class AIFFParser extends BasicParser/* BasicParser */.s {
    constructor() {
        super(...arguments);
        this.isCompressed = null;
    }
    async parse() {
        const header = await this.tokenizer.readToken(Header);
        if (header.chunkID !== 'FORM') {
            throw new AiffContentError("Invalid Chunk-ID, expected 'FORM'"); // Not AIFF format
        }
        const type = await this.tokenizer.readToken(FourCC/* FourCcToken */.e);
        switch (type) {
            case 'AIFF':
                this.metadata.setFormat('container', type);
                this.isCompressed = false;
                break;
            case 'AIFC':
                this.metadata.setFormat('container', 'AIFF-C');
                this.isCompressed = true;
                break;
            default:
                throw new AiffContentError(`Unsupported AIFF type: ${type}`);
        }
        this.metadata.setFormat('lossless', !this.isCompressed);
        this.metadata.setAudioOnly();
        try {
            while (!this.tokenizer.fileInfo.size ||
                this.tokenizer.fileInfo.size - this.tokenizer.position >= Header.len) {
                debug(`Reading AIFF chunk at offset=${this.tokenizer.position}`);
                const chunkHeader = await this.tokenizer.readToken(Header);
                const nextChunk = 2 * Math.round(chunkHeader.chunkSize / 2);
                const bytesRead = await this.readData(chunkHeader);
                await this.tokenizer.ignore(nextChunk - bytesRead);
            }
        }
        catch (err) {
            if (err instanceof lib/* EndOfStreamError */.d1) {
                debug('End-of-stream');
            }
            else {
                throw err;
            }
        }
    }
    async readData(header) {
        switch (header.chunkID) {
            case 'COMM': {
                // The Common Chunk
                if (this.isCompressed === null) {
                    throw new AiffContentError('Failed to parse AIFF.COMM chunk when compression type is unknown');
                }
                const common = await this.tokenizer.readToken(new Common(header, this.isCompressed));
                this.metadata.setFormat('bitsPerSample', common.sampleSize);
                this.metadata.setFormat('sampleRate', common.sampleRate);
                this.metadata.setFormat('numberOfChannels', common.numChannels);
                this.metadata.setFormat('numberOfSamples', common.numSampleFrames);
                this.metadata.setFormat('duration', common.numSampleFrames / common.sampleRate);
                if (common.compressionName || common.compressionType) {
                    this.metadata.setFormat('codec', common.compressionName ?? compressionTypes[common.compressionType]);
                }
                return header.chunkSize;
            }
            case 'ID3 ': {
                // ID3-meta-data
                const id3_data = await this.tokenizer.readToken(new token_types_lib.Uint8ArrayType(header.chunkSize));
                const rst = lib/* fromBuffer */.vY(id3_data);
                await new ID3v2Parser/* ID3v2Parser */.S().parse(this.metadata, rst, this.options);
                return header.chunkSize;
            }
            case 'SSND': // Sound Data Chunk
                if (this.metadata.format.duration) {
                    this.metadata.setFormat('bitrate', (8 * header.chunkSize) / this.metadata.format.duration);
                }
                return 0;
            case 'NAME': // Sample name chunk
            case 'AUTH': // Author chunk
            case '(c) ': // Copyright chunk
            case 'ANNO': // Annotation chunk
                return this.readTextChunk(header);
            default:
                debug(`Ignore chunk id=${header.chunkID}, size=${header.chunkSize}`);
                return 0;
        }
    }
    async readTextChunk(header) {
        const value = await this.tokenizer.readToken(new token_types_lib.StringType(header.chunkSize, 'ascii'));
        const values = value
            .split('\0')
            .map(v => v.trim())
            .filter(v => v?.length);
        await Promise.all(values.map(v => this.metadata.addTag('AIFF', header.chunkID, v)));
        return header.chunkSize;
    }
}


/***/ })

};
;