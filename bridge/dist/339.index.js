"use strict";
exports.id = 339;
exports.ids = [339];
exports.modules = {

/***/ 9339:
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   FlacParser: () => (/* binding */ FlacParser)
/* harmony export */ });
/* harmony import */ var debug__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(9159);
/* harmony import */ var token_types__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(43);
/* harmony import */ var _common_FourCC_js__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(6777);
/* harmony import */ var _id3v2_AbstractID3Parser_js__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(4763);
/* harmony import */ var _ogg_vorbis_Vorbis_js__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(214);
/* harmony import */ var _ogg_vorbis_VorbisDecoder_js__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(4340);
/* harmony import */ var _ogg_vorbis_VorbisStream_js__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(4350);
/* harmony import */ var _ParseError_js__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(6994);
/* harmony import */ var _FlacToken_js__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(3271);









const debug = debug__WEBPACK_IMPORTED_MODULE_0__('music-metadata:parser:FLAC');
class FlacContentError extends (0,_ParseError_js__WEBPACK_IMPORTED_MODULE_2__/* .makeUnexpectedFileContentError */ .fO)('FLAC') {
}
class FlacParser extends _id3v2_AbstractID3Parser_js__WEBPACK_IMPORTED_MODULE_3__/* .AbstractID3Parser */ .k {
    constructor() {
        super(...arguments);
        this.vorbisParser = new _ogg_vorbis_VorbisStream_js__WEBPACK_IMPORTED_MODULE_4__/* .VorbisStream */ ._(this.metadata, this.options);
    }
    async postId3v2Parse() {
        const fourCC = await this.tokenizer.readToken(_common_FourCC_js__WEBPACK_IMPORTED_MODULE_5__/* .FourCcToken */ .e);
        if (fourCC.toString() !== 'fLaC') {
            throw new FlacContentError('Invalid FLAC preamble');
        }
        let blockHeader;
        do {
            // Read block header
            blockHeader = await this.tokenizer.readToken(_FlacToken_js__WEBPACK_IMPORTED_MODULE_6__/* .BlockHeader */ .Tw);
            // Parse block data
            await this.parseDataBlock(blockHeader);
        } while (!blockHeader.lastBlock);
        if (this.tokenizer.fileInfo.size && this.metadata.format.duration) {
            const dataSize = this.tokenizer.fileInfo.size - this.tokenizer.position;
            this.metadata.setFormat('bitrate', (8 * dataSize) / this.metadata.format.duration);
        }
    }
    async parseDataBlock(blockHeader) {
        debug(`blockHeader type=${blockHeader.type}, length=${blockHeader.length}`);
        switch (blockHeader.type) {
            case _FlacToken_js__WEBPACK_IMPORTED_MODULE_6__/* .BlockType */ ._B.STREAMINFO:
                return this.readBlockStreamInfo(blockHeader.length);
            case _FlacToken_js__WEBPACK_IMPORTED_MODULE_6__/* .BlockType */ ._B.PADDING:
                break;
            case _FlacToken_js__WEBPACK_IMPORTED_MODULE_6__/* .BlockType */ ._B.APPLICATION:
                break;
            case _FlacToken_js__WEBPACK_IMPORTED_MODULE_6__/* .BlockType */ ._B.SEEKTABLE:
                break;
            case _FlacToken_js__WEBPACK_IMPORTED_MODULE_6__/* .BlockType */ ._B.VORBIS_COMMENT:
                return this.readComment(blockHeader.length);
            case _FlacToken_js__WEBPACK_IMPORTED_MODULE_6__/* .BlockType */ ._B.CUESHEET:
                break;
            case _FlacToken_js__WEBPACK_IMPORTED_MODULE_6__/* .BlockType */ ._B.PICTURE:
                await this.parsePicture(blockHeader.length);
                return;
            default:
                this.metadata.addWarning(`Unknown block type: ${blockHeader.type}`);
        }
        // Ignore data block
        return this.tokenizer.ignore(blockHeader.length).then();
    }
    /**
     * Parse STREAMINFO
     */
    async readBlockStreamInfo(dataLen) {
        if (dataLen !== _FlacToken_js__WEBPACK_IMPORTED_MODULE_6__/* .BlockStreamInfo */ .om.len) {
            throw new FlacContentError('Unexpected block-stream-info length');
        }
        const streamInfo = await this.tokenizer.readToken(_FlacToken_js__WEBPACK_IMPORTED_MODULE_6__/* .BlockStreamInfo */ .om);
        this.metadata.setFormat('container', 'FLAC');
        this.processsStreamInfo(streamInfo);
    }
    /**
     * Parse STREAMINFO
     */
    processsStreamInfo(streamInfo) {
        this.metadata.setFormat('codec', 'FLAC');
        this.metadata.setFormat('hasAudio', true);
        this.metadata.setFormat('lossless', true);
        this.metadata.setFormat('numberOfChannels', streamInfo.channels);
        this.metadata.setFormat('bitsPerSample', streamInfo.bitsPerSample);
        this.metadata.setFormat('sampleRate', streamInfo.sampleRate);
        if (streamInfo.totalSamples > 0) {
            this.metadata.setFormat('duration', streamInfo.totalSamples / streamInfo.sampleRate);
        }
    }
    /**
     * Read VORBIS_COMMENT from tokenizer
     * Ref: https://www.xiph.org/vorbis/doc/Vorbis_I_spec.html#x1-640004.2.3
     */
    async readComment(dataLen) {
        const data = await this.tokenizer.readToken(new token_types__WEBPACK_IMPORTED_MODULE_1__.Uint8ArrayType(dataLen));
        return this.parseComment(data);
    }
    /**
     * Parse VORBIS_COMMENT
     * Ref: https://www.xiph.org/vorbis/doc/Vorbis_I_spec.html#x1-640004.2.3
     */
    async parseComment(data) {
        const decoder = new _ogg_vorbis_VorbisDecoder_js__WEBPACK_IMPORTED_MODULE_7__/* .VorbisDecoder */ .Y(data, 0);
        const vendor = decoder.readStringUtf8();
        if (vendor.length > 0) {
            this.metadata.setFormat('tool', vendor);
        }
        const commentListLength = decoder.readInt32();
        const tags = new Array(commentListLength);
        for (let i = 0; i < commentListLength; i++) {
            tags[i] = decoder.parseUserComment();
        }
        await Promise.all(tags.map(tag => {
            if (tag.key === 'ENCODER') {
                this.metadata.setFormat('tool', tag.value);
            }
            return this.addTag(tag.key, tag.value);
        }));
    }
    async parsePicture(dataLen) {
        if (this.options.skipCovers) {
            return this.tokenizer.ignore(dataLen);
        }
        return this.addPictureTag(await this.tokenizer.readToken(new _ogg_vorbis_Vorbis_js__WEBPACK_IMPORTED_MODULE_8__/* .VorbisPictureToken */ .xu(dataLen)));
    }
    addPictureTag(picture) {
        return this.addTag('METADATA_BLOCK_PICTURE', picture);
    }
    addTag(id, value) {
        return this.vorbisParser.addTag(id, value);
    }
}


/***/ }),

/***/ 3271:
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   Tw: () => (/* binding */ BlockHeader),
/* harmony export */   _B: () => (/* binding */ BlockType),
/* harmony export */   om: () => (/* binding */ BlockStreamInfo)
/* harmony export */ });
/* harmony import */ var token_types__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(43);
/* harmony import */ var _common_Util_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(3791);


/**
 * FLAC supports up to 128 kinds of metadata blocks; currently the following are defined:
 * ref: https://xiph.org/flac/format.html#metadata_block
 */
const BlockType = {
    STREAMINFO: 0, // STREAMINFO
    PADDING: 1, // PADDING
    APPLICATION: 2, // APPLICATION
    SEEKTABLE: 3, // SEEKTABLE
    VORBIS_COMMENT: 4, // VORBIS_COMMENT
    CUESHEET: 5, // CUESHEET
    PICTURE: 6 // PICTURE
};
const BlockHeader = {
    len: 4,
    get: (buf, off) => {
        return {
            lastBlock: _common_Util_js__WEBPACK_IMPORTED_MODULE_1__/* .getBit */ .mh(buf, off, 7),
            type: _common_Util_js__WEBPACK_IMPORTED_MODULE_1__/* .getBitAllignedNumber */ .f5(buf, off, 1, 7),
            length: token_types__WEBPACK_IMPORTED_MODULE_0__.UINT24_BE.get(buf, off + 1)
        };
    }
};
/**
 * METADATA_BLOCK_DATA
 * Ref: https://xiph.org/flac/format.html#metadata_block_streaminfo
 */
const BlockStreamInfo = {
    len: 34,
    get: (buf, off) => {
        return {
            // The minimum block size (in samples) used in the stream.
            minimumBlockSize: token_types__WEBPACK_IMPORTED_MODULE_0__.UINT16_BE.get(buf, off),
            // The maximum block size (in samples) used in the stream.
            // (Minimum blocksize == maximum blocksize) implies a fixed-blocksize stream.
            maximumBlockSize: token_types__WEBPACK_IMPORTED_MODULE_0__.UINT16_BE.get(buf, off + 2) / 1000,
            // The minimum frame size (in bytes) used in the stream.
            // May be 0 to imply the value is not known.
            minimumFrameSize: token_types__WEBPACK_IMPORTED_MODULE_0__.UINT24_BE.get(buf, off + 4),
            // The maximum frame size (in bytes) used in the stream.
            // May be 0 to imply the value is not known.
            maximumFrameSize: token_types__WEBPACK_IMPORTED_MODULE_0__.UINT24_BE.get(buf, off + 7),
            // Sample rate in Hz. Though 20 bits are available,
            // the maximum sample rate is limited by the structure of frame headers to 655350Hz.
            // Also, a value of 0 is invalid.
            sampleRate: token_types__WEBPACK_IMPORTED_MODULE_0__.UINT24_BE.get(buf, off + 10) >> 4,
            // probably slower: sampleRate: common.getBitAllignedNumber(buf, off + 10, 0, 20),
            // (number of channels)-1. FLAC supports from 1 to 8 channels
            channels: _common_Util_js__WEBPACK_IMPORTED_MODULE_1__/* .getBitAllignedNumber */ .f5(buf, off + 12, 4, 3) + 1,
            // bits per sample)-1.
            // FLAC supports from 4 to 32 bits per sample. Currently the reference encoder and decoders only support up to 24 bits per sample.
            bitsPerSample: _common_Util_js__WEBPACK_IMPORTED_MODULE_1__/* .getBitAllignedNumber */ .f5(buf, off + 12, 7, 5) + 1,
            // Total samples in stream.
            // 'Samples' means inter-channel sample, i.e. one second of 44.1Khz audio will have 44100 samples regardless of the number of channels.
            // A value of zero here means the number of total samples is unknown.
            totalSamples: _common_Util_js__WEBPACK_IMPORTED_MODULE_1__/* .getBitAllignedNumber */ .f5(buf, off + 13, 4, 36),
            // the MD5 hash of the file (see notes for usage... it's a littly tricky)
            fileMD5: new token_types__WEBPACK_IMPORTED_MODULE_0__.Uint8ArrayType(16).get(buf, off + 18)
        };
    }
};


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


/***/ }),

/***/ 214:
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   Sl: () => (/* binding */ IdentificationHeader),
/* harmony export */   Z: () => (/* binding */ CommonHeader),
/* harmony export */   xu: () => (/* binding */ VorbisPictureToken)
/* harmony export */ });
/* harmony import */ var token_types__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(43);
/* harmony import */ var _id3v2_ID3v2Token_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(5057);


/**
 * Parse the METADATA_BLOCK_PICTURE
 * Ref: https://wiki.xiph.org/VorbisComment#METADATA_BLOCK_PICTURE
 * Ref: https://xiph.org/flac/format.html#metadata_block_picture
 * // ToDo: move to ID3 / APIC?
 */
class VorbisPictureToken {
    static fromBase64(base64str) {
        return VorbisPictureToken.fromBuffer(Uint8Array.from(atob(base64str), c => c.charCodeAt(0)));
    }
    static fromBuffer(buffer) {
        const pic = new VorbisPictureToken(buffer.length);
        return pic.get(buffer, 0);
    }
    constructor(len) {
        this.len = len;
    }
    get(buffer, offset) {
        const type = _id3v2_ID3v2Token_js__WEBPACK_IMPORTED_MODULE_1__/* .AttachedPictureType */ .n5[token_types__WEBPACK_IMPORTED_MODULE_0__.UINT32_BE.get(buffer, offset)];
        offset += 4;
        const mimeLen = token_types__WEBPACK_IMPORTED_MODULE_0__.UINT32_BE.get(buffer, offset);
        offset += 4;
        const format = new token_types__WEBPACK_IMPORTED_MODULE_0__.StringType(mimeLen, 'utf-8').get(buffer, offset);
        offset += mimeLen;
        const descLen = token_types__WEBPACK_IMPORTED_MODULE_0__.UINT32_BE.get(buffer, offset);
        offset += 4;
        const description = new token_types__WEBPACK_IMPORTED_MODULE_0__.StringType(descLen, 'utf-8').get(buffer, offset);
        offset += descLen;
        const width = token_types__WEBPACK_IMPORTED_MODULE_0__.UINT32_BE.get(buffer, offset);
        offset += 4;
        const height = token_types__WEBPACK_IMPORTED_MODULE_0__.UINT32_BE.get(buffer, offset);
        offset += 4;
        const colour_depth = token_types__WEBPACK_IMPORTED_MODULE_0__.UINT32_BE.get(buffer, offset);
        offset += 4;
        const indexed_color = token_types__WEBPACK_IMPORTED_MODULE_0__.UINT32_BE.get(buffer, offset);
        offset += 4;
        const picDataLen = token_types__WEBPACK_IMPORTED_MODULE_0__.UINT32_BE.get(buffer, offset);
        offset += 4;
        const data = buffer.slice(offset, offset + picDataLen);
        return {
            type,
            format,
            description,
            width,
            height,
            colour_depth,
            indexed_color,
            data
        };
    }
}
/**
 * Comment header decoder
 * Ref: https://xiph.org/vorbis/doc/Vorbis_I_spec.html#x1-620004.2.1
 */
const CommonHeader = {
    len: 7,
    get: (buf, off) => {
        return {
            packetType: token_types__WEBPACK_IMPORTED_MODULE_0__.UINT8.get(buf, off),
            vorbis: new token_types__WEBPACK_IMPORTED_MODULE_0__.StringType(6, 'ascii').get(buf, off + 1)
        };
    }
};
/**
 * Identification header decoder
 * Ref: https://xiph.org/vorbis/doc/Vorbis_I_spec.html#x1-630004.2.2
 */
const IdentificationHeader = {
    len: 23,
    get: (uint8Array, off) => {
        return {
            version: token_types__WEBPACK_IMPORTED_MODULE_0__.UINT32_LE.get(uint8Array, off + 0),
            channelMode: token_types__WEBPACK_IMPORTED_MODULE_0__.UINT8.get(uint8Array, off + 4),
            sampleRate: token_types__WEBPACK_IMPORTED_MODULE_0__.UINT32_LE.get(uint8Array, off + 5),
            bitrateMax: token_types__WEBPACK_IMPORTED_MODULE_0__.UINT32_LE.get(uint8Array, off + 9),
            bitrateNominal: token_types__WEBPACK_IMPORTED_MODULE_0__.UINT32_LE.get(uint8Array, off + 13),
            bitrateMin: token_types__WEBPACK_IMPORTED_MODULE_0__.UINT32_LE.get(uint8Array, off + 17)
        };
    }
};


/***/ }),

/***/ 4340:
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   Y: () => (/* binding */ VorbisDecoder)
/* harmony export */ });
/* harmony import */ var _borewit_text_codec__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(7245);
/* harmony import */ var token_types__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(43);


class VorbisDecoder {
    constructor(data, offset) {
        this.data = data;
        this.offset = offset;
    }
    readInt32() {
        const value = token_types__WEBPACK_IMPORTED_MODULE_1__.UINT32_LE.get(this.data, this.offset);
        this.offset += 4;
        return value;
    }
    readStringUtf8() {
        const len = this.readInt32();
        const value = (0,_borewit_text_codec__WEBPACK_IMPORTED_MODULE_0__/* .textDecode */ .c)(this.data.subarray(this.offset, this.offset + len), 'utf-8');
        this.offset += len;
        return value;
    }
    parseUserComment() {
        const offset0 = this.offset;
        const v = this.readStringUtf8();
        const idx = v.indexOf('=');
        return {
            key: v.substring(0, idx).toUpperCase(),
            value: v.substring(idx + 1),
            len: this.offset - offset0
        };
    }
}


/***/ }),

/***/ 4350:
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   _: () => (/* binding */ VorbisStream)
/* harmony export */ });
/* unused harmony export VorbisContentError */
/* harmony import */ var debug__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(9159);
/* harmony import */ var token_types__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(43);
/* harmony import */ var _ParseError_js__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(6994);
/* harmony import */ var _Vorbis_js__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(214);
/* harmony import */ var _VorbisDecoder_js__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(4340);





const debug = debug__WEBPACK_IMPORTED_MODULE_0__('music-metadata:parser:ogg:vorbis1');
class VorbisContentError extends (0,_ParseError_js__WEBPACK_IMPORTED_MODULE_2__/* .makeUnexpectedFileContentError */ .fO)('Vorbis') {
}
/**
 * Vorbis 1 Parser.
 * Used by OggStream
 */
class VorbisStream {
    constructor(metadata, options) {
        this.pageSegments = [];
        this.durationOnLastPage = true;
        this.isMetadataComplete = false;
        this.metadata = metadata;
        this.options = options;
    }
    /**
     * Vorbis 1 parser
     * @param header Ogg Page Header
     * @param pageData Page data
     */
    async parsePage(header, pageData) {
        this.lastPageHeader = header;
        if (header.headerType.firstPage) {
            this.parseFirstPage(header, pageData);
        }
        else {
            if (header.headerType.continued) {
                if (this.pageSegments.length === 0) {
                    throw new VorbisContentError('Cannot continue on previous page');
                }
                this.pageSegments.push(pageData);
            }
            if (header.headerType.lastPage || !header.headerType.continued) {
                // Flush page segments
                if (this.pageSegments.length > 0) {
                    const fullPage = VorbisStream.mergeUint8Arrays(this.pageSegments);
                    await this.parseFullPage(fullPage);
                }
                // Reset page segments
                this.pageSegments = header.headerType.lastPage ? [] : [pageData];
            }
        }
    }
    static mergeUint8Arrays(arrays) {
        const totalSize = arrays.reduce((acc, e) => acc + e.length, 0);
        const merged = new Uint8Array(totalSize);
        arrays.forEach((array, i, _arrays) => {
            const offset = _arrays.slice(0, i).reduce((acc, e) => acc + e.length, 0);
            merged.set(array, offset);
        });
        return merged;
    }
    async flush() {
        await this.parseFullPage(VorbisStream.mergeUint8Arrays(this.pageSegments));
    }
    async parseUserComment(pageData, offset) {
        const decoder = new _VorbisDecoder_js__WEBPACK_IMPORTED_MODULE_3__/* .VorbisDecoder */ .Y(pageData, offset);
        const tag = decoder.parseUserComment();
        await this.addTag(tag.key, tag.value);
        return tag.len;
    }
    async addTag(id, value) {
        if (id === 'METADATA_BLOCK_PICTURE' && typeof value === 'string') {
            if (this.options.skipCovers) {
                debug('Ignore picture');
                return;
            }
            value = _Vorbis_js__WEBPACK_IMPORTED_MODULE_4__/* .VorbisPictureToken */ .xu.fromBase64(value);
            debug(`Push picture: id=${id}, format=${value.format}`);
        }
        else {
            debug(`Push tag: id=${id}, value=${value}`);
        }
        await this.metadata.addTag('vorbis', id, value);
    }
    calculateDuration(enfOfStream) {
        if (this.lastPageHeader &&
            (enfOfStream || this.lastPageHeader.headerType.lastPage) &&
            this.metadata.format.sampleRate &&
            this.lastPageHeader.absoluteGranulePosition >= 0) {
            // Calculate duration
            this.metadata.setFormat('numberOfSamples', this.lastPageHeader.absoluteGranulePosition);
            this.metadata.setFormat('duration', this.lastPageHeader.absoluteGranulePosition / this.metadata.format.sampleRate);
        }
    }
    /**
     * Parse first Ogg/Vorbis page
     * @param _header
     * @param pageData
     */
    parseFirstPage(_header, pageData) {
        this.metadata.setFormat('codec', 'Vorbis I');
        this.metadata.setFormat('hasAudio', true);
        debug('Parse first page');
        // Parse  Vorbis common header
        const commonHeader = _Vorbis_js__WEBPACK_IMPORTED_MODULE_4__/* .CommonHeader */ .Z.get(pageData, 0);
        if (commonHeader.vorbis !== 'vorbis') {
            throw new VorbisContentError('Metadata does not look like Vorbis');
        }
        if (commonHeader.packetType === 1) {
            const idHeader = _Vorbis_js__WEBPACK_IMPORTED_MODULE_4__/* .IdentificationHeader */ .Sl.get(pageData, _Vorbis_js__WEBPACK_IMPORTED_MODULE_4__/* .CommonHeader */ .Z.len);
            this.metadata.setFormat('sampleRate', idHeader.sampleRate);
            this.metadata.setFormat('bitrate', idHeader.bitrateNominal);
            this.metadata.setFormat('numberOfChannels', idHeader.channelMode);
            debug('sample-rate=%s[hz], bitrate=%s[b/s], channel-mode=%s', idHeader.sampleRate, idHeader.bitrateNominal, idHeader.channelMode);
        }
        else {
            throw new VorbisContentError('First Ogg page should be type 1: the identification header');
        }
    }
    async parseFullPage(pageData) {
        // New page
        const commonHeader = _Vorbis_js__WEBPACK_IMPORTED_MODULE_4__/* .CommonHeader */ .Z.get(pageData, 0);
        debug('Parse full page: type=%s, byteLength=%s', commonHeader.packetType, pageData.byteLength);
        switch (commonHeader.packetType) {
            case 3: //  type 3: comment header
                return this.parseUserCommentList(pageData, _Vorbis_js__WEBPACK_IMPORTED_MODULE_4__/* .CommonHeader */ .Z.len);
            case 1: // type 1: the identification header
            case 5: // type 5: setup header type
                break; // ignore
        }
    }
    /**
     * Ref: https://xiph.org/vorbis/doc/Vorbis_I_spec.html#x1-840005.2
     */
    async parseUserCommentList(pageData, offset) {
        const strLen = token_types__WEBPACK_IMPORTED_MODULE_1__.UINT32_LE.get(pageData, offset);
        offset += 4;
        // const vendorString = new Token.StringType(strLen, 'utf-8').get(pageData, offset);
        offset += strLen;
        let userCommentListLength = token_types__WEBPACK_IMPORTED_MODULE_1__.UINT32_LE.get(pageData, offset);
        offset += 4;
        while (userCommentListLength-- > 0) {
            offset += await this.parseUserComment(pageData, offset);
        }
        this.isMetadataComplete = true;
    }
}


/***/ })

};
;