"use strict";
exports.id = 444;
exports.ids = [444];
exports.modules = {

/***/ 6444:
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

// ESM COMPAT FLAG
__webpack_require__.r(__webpack_exports__);

// EXPORTS
__webpack_require__.d(__webpack_exports__, {
  DsdContentParseError: () => (/* binding */ DsdContentParseError),
  DsfParser: () => (/* binding */ DsfParser)
});

// EXTERNAL MODULE: ./node_modules/debug/src/index.js
var src = __webpack_require__(9159);
// EXTERNAL MODULE: ./node_modules/music-metadata/lib/id3v2/AbstractID3Parser.js
var AbstractID3Parser = __webpack_require__(4763);
// EXTERNAL MODULE: ./node_modules/music-metadata/lib/id3v2/ID3v2Parser.js + 3 modules
var ID3v2Parser = __webpack_require__(1763);
// EXTERNAL MODULE: ./node_modules/music-metadata/lib/id3v2/ID3v2Token.js
var ID3v2Token = __webpack_require__(5057);
// EXTERNAL MODULE: ./node_modules/music-metadata/lib/ParseError.js
var ParseError = __webpack_require__(6994);
// EXTERNAL MODULE: ./node_modules/token-types/lib/index.js
var lib = __webpack_require__(43);
// EXTERNAL MODULE: ./node_modules/music-metadata/lib/common/FourCC.js
var FourCC = __webpack_require__(6777);
;// CONCATENATED MODULE: ./node_modules/music-metadata/lib/dsf/DsfChunk.js


/**
 * Common chunk DSD header: the 'chunk name (Four-CC)' & chunk size
 */
const ChunkHeader = {
    len: 12,
    get: (buf, off) => {
        return { id: FourCC/* FourCcToken */.e.get(buf, off), size: lib.UINT64_LE.get(buf, off + 4) };
    }
};
/**
 * Common chunk DSD header: the 'chunk name (Four-CC)' & chunk size
 */
const DsdChunk = {
    len: 16,
    get: (buf, off) => {
        return {
            fileSize: lib.UINT64_LE.get(buf, off),
            metadataPointer: lib.UINT64_LE.get(buf, off + 8)
        };
    }
};
const ChannelType = {
    mono: 1,
    stereo: 2,
    channels: 3,
    quad: 4,
    '4 channels': 5,
    '5 channels': 6,
    '5.1 channels': 7
};
/**
 * Common chunk DSD header: the 'chunk name (Four-CC)' & chunk size
 */
const FormatChunk = {
    len: 40,
    get: (buf, off) => {
        return {
            formatVersion: lib.INT32_LE.get(buf, off),
            formatID: lib.INT32_LE.get(buf, off + 4),
            channelType: lib.INT32_LE.get(buf, off + 8),
            channelNum: lib.INT32_LE.get(buf, off + 12),
            samplingFrequency: lib.INT32_LE.get(buf, off + 16),
            bitsPerSample: lib.INT32_LE.get(buf, off + 20),
            sampleCount: lib.INT64_LE.get(buf, off + 24),
            blockSizePerChannel: lib.INT32_LE.get(buf, off + 32)
        };
    }
};

;// CONCATENATED MODULE: ./node_modules/music-metadata/lib/dsf/DsfParser.js






const debug = src('music-metadata:parser:DSF');
class DsdContentParseError extends (0,ParseError/* makeUnexpectedFileContentError */.fO)('DSD') {
}
/**
 * DSF (dsd stream file) File Parser
 * Ref: https://dsd-guide.com/sites/default/files/white-papers/DSFFileFormatSpec_E.pdf
 */
class DsfParser extends AbstractID3Parser/* AbstractID3Parser */.k {
    async postId3v2Parse() {
        const p0 = this.tokenizer.position; // mark start position, normally 0
        const chunkHeader = await this.tokenizer.readToken(ChunkHeader);
        if (chunkHeader.id !== 'DSD ') {
            throw new DsdContentParseError('Invalid chunk signature');
        }
        if (chunkHeader.size !== BigInt(ChunkHeader.len + DsdChunk.len)) {
            throw new DsdContentParseError(`Invalid DSD chunk size: ${chunkHeader.size}`);
        }
        this.metadata.setFormat('container', 'DSF');
        this.metadata.setFormat('lossless', true);
        this.metadata.setAudioOnly();
        const dsdChunk = await this.tokenizer.readToken(DsdChunk);
        if (dsdChunk.fileSize < chunkHeader.size) {
            throw new DsdContentParseError(`Invalid DSF file size: ${dsdChunk.fileSize}`);
        }
        await this.parseChunks(dsdChunk.fileSize - chunkHeader.size);
        if (dsdChunk.metadataPointer === 0n) {
            debug('No ID3v2 tag present');
            return;
        }
        debug(`expect ID3v2 at offset=${dsdChunk.metadataPointer}`);
        const metadataOffset = dsdChunk.metadataPointer - BigInt(this.tokenizer.position - p0);
        if (metadataOffset < 0n ||
            metadataOffset > BigInt(Number.MAX_SAFE_INTEGER) ||
            dsdChunk.metadataPointer + BigInt(ID3v2Token/* ID3v2Header */.yW.len) > dsdChunk.fileSize) {
            throw new DsdContentParseError(`Invalid metadata pointer: ${dsdChunk.metadataPointer}`);
        }
        await this.tokenizer.ignore(Number(metadataOffset));
        return new ID3v2Parser/* ID3v2Parser */.S().parse(this.metadata, this.tokenizer, this.options);
    }
    async parseChunks(bytesRemaining) {
        const chunkHeaderSize = BigInt(ChunkHeader.len);
        while (bytesRemaining >= chunkHeaderSize) {
            const chunkHeader = await this.tokenizer.readToken(ChunkHeader);
            debug(`Parsing chunk name=${chunkHeader.id} size=${chunkHeader.size}`);
            if (chunkHeader.size < chunkHeaderSize) {
                throw new DsdContentParseError(`Invalid ${chunkHeader.id} chunk size: ${chunkHeader.size}`);
            }
            if (chunkHeader.size > bytesRemaining) {
                throw new DsdContentParseError(`${chunkHeader.id} chunk exceeds remaining file size`);
            }
            const payloadSize = chunkHeader.size - chunkHeaderSize;
            switch (chunkHeader.id) {
                case 'fmt ': {
                    if (payloadSize < BigInt(FormatChunk.len)) {
                        throw new DsdContentParseError(`Invalid fmt chunk size: ${chunkHeader.size}`);
                    }
                    const formatChunk = await this.tokenizer.readToken(FormatChunk);
                    this.metadata.setFormat('numberOfChannels', formatChunk.channelNum);
                    this.metadata.setFormat('sampleRate', formatChunk.samplingFrequency);
                    this.metadata.setFormat('bitsPerSample', formatChunk.bitsPerSample);
                    this.metadata.setFormat('numberOfSamples', formatChunk.sampleCount);
                    this.metadata.setFormat('duration', Number(formatChunk.sampleCount) / formatChunk.samplingFrequency);
                    const bitrate = formatChunk.bitsPerSample * formatChunk.samplingFrequency * formatChunk.channelNum;
                    this.metadata.setFormat('bitrate', bitrate);
                    return; // We got what we want, stop further processing of chunks
                }
                default:
                    await this.tokenizer.ignore(Number(payloadSize));
                    break;
            }
            bytesRemaining -= chunkHeader.size;
        }
    }
}


/***/ }),

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


/***/ })

};
;