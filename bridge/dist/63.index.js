"use strict";
exports.id = 63;
exports.ids = [63];
exports.modules = {

/***/ 7063:
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

// ESM COMPAT FLAG
__webpack_require__.r(__webpack_exports__);

// EXPORTS
__webpack_require__.d(__webpack_exports__, {
  DsdiffContentParseError: () => (/* binding */ DsdiffContentParseError),
  DsdiffParser: () => (/* binding */ DsdiffParser)
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
// EXTERNAL MODULE: ./node_modules/music-metadata/lib/ParseError.js
var ParseError = __webpack_require__(6994);
;// CONCATENATED MODULE: ./node_modules/music-metadata/lib/dsdiff/DsdiffToken.js


/**
 * DSDIFF chunk header
 * The data-size encoding is deviating from EA-IFF 85
 * Ref: http://www.sonicstudio.com/pdf/dsd/DSDIFF_1.5_Spec.pdf
 */
const ChunkHeader64 = {
    len: 12,
    get: (buf, off) => {
        return {
            // Group-ID
            chunkID: FourCC/* FourCcToken */.e.get(buf, off),
            // Size
            chunkSize: token_types_lib.INT64_BE.get(buf, off + 4)
        };
    }
};

;// CONCATENATED MODULE: ./node_modules/music-metadata/lib/dsdiff/DsdiffParser.js








const debug = src('music-metadata:parser:aiff');
class DsdiffContentParseError extends (0,ParseError/* makeUnexpectedFileContentError */.fO)('DSDIFF') {
}
/**
 * DSDIFF - Direct Stream Digital Interchange File Format (Phillips)
 *
 * Ref:
 * - http://www.sonicstudio.com/pdf/dsd/DSDIFF_1.5_Spec.pdf
 */
class DsdiffParser extends BasicParser/* BasicParser */.s {
    async parse() {
        const header = await this.tokenizer.readToken(ChunkHeader64);
        if (header.chunkID !== 'FRM8') {
            throw new DsdiffContentParseError('Unexpected chunk-ID');
        }
        this.metadata.setAudioOnly();
        const type = (await this.tokenizer.readToken(FourCC/* FourCcToken */.e)).trim();
        switch (type) {
            case 'DSD':
                this.metadata.setFormat('container', `DSDIFF/${type}`);
                this.metadata.setFormat('lossless', true);
                return this.readFmt8Chunks(header.chunkSize - BigInt(FourCC/* FourCcToken */.e.len));
            default:
                throw new DsdiffContentParseError(`Unsupported DSDIFF type: ${type}`);
        }
    }
    async readFmt8Chunks(remainingSize) {
        while (remainingSize >= ChunkHeader64.len) {
            const chunkHeader = await this.tokenizer.readToken(ChunkHeader64);
            //  If the data is an odd number of bytes in length, a pad byte must be added at the end
            debug(`Chunk id=${chunkHeader.chunkID}`);
            await this.readData(chunkHeader);
            remainingSize -= BigInt(ChunkHeader64.len) + chunkHeader.chunkSize;
        }
    }
    async readData(header) {
        debug(`Reading data of chunk[ID=${header.chunkID}, size=${header.chunkSize}]`);
        const p0 = this.tokenizer.position;
        switch (header.chunkID.trim()) {
            case 'FVER': {
                // 3.1 FORMAT VERSION CHUNK
                const version = await this.tokenizer.readToken(token_types_lib.UINT32_LE);
                debug(`DSDIFF version=${version}`);
                break;
            }
            case 'PROP': {
                // 3.2 PROPERTY CHUNK
                const propType = await this.tokenizer.readToken(FourCC/* FourCcToken */.e);
                if (propType !== 'SND ') {
                    throw new DsdiffContentParseError('Unexpected PROP-chunk ID');
                }
                await this.handleSoundPropertyChunks(header.chunkSize - BigInt(FourCC/* FourCcToken */.e.len));
                break;
            }
            case 'ID3': {
                // Unofficial ID3 tag support
                const id3_data = await this.tokenizer.readToken(new token_types_lib.Uint8ArrayType(Number(header.chunkSize)));
                const rst = lib/* fromBuffer */.vY(id3_data);
                await new ID3v2Parser/* ID3v2Parser */.S().parse(this.metadata, rst, this.options);
                break;
            }
            case 'DSD':
                if (this.metadata.format.numberOfChannels) {
                    this.metadata.setFormat('numberOfSamples', Number((header.chunkSize * BigInt(8)) / BigInt(this.metadata.format.numberOfChannels)));
                }
                if (this.metadata.format.numberOfSamples && this.metadata.format.sampleRate) {
                    this.metadata.setFormat('duration', this.metadata.format.numberOfSamples / this.metadata.format.sampleRate);
                }
                break;
            default:
                debug(`Ignore chunk[ID=${header.chunkID}, size=${header.chunkSize}]`);
                break;
        }
        const remaining = header.chunkSize - BigInt(this.tokenizer.position - p0);
        if (remaining > 0) {
            debug(`After Parsing chunk, remaining ${remaining} bytes`);
            await this.tokenizer.ignore(Number(remaining));
        }
    }
    async handleSoundPropertyChunks(remainingSize) {
        debug(`Parsing sound-property-chunks, remainingSize=${remainingSize}`);
        while (remainingSize > 0) {
            const sndPropHeader = await this.tokenizer.readToken(ChunkHeader64);
            debug(`Sound-property-chunk[ID=${sndPropHeader.chunkID}, size=${sndPropHeader.chunkSize}]`);
            const p0 = this.tokenizer.position;
            switch (sndPropHeader.chunkID.trim()) {
                case 'FS': {
                    // 3.2.1 Sample Rate Chunk
                    const sampleRate = await this.tokenizer.readToken(token_types_lib.UINT32_BE);
                    this.metadata.setFormat('sampleRate', sampleRate);
                    break;
                }
                case 'CHNL': {
                    // 3.2.2 Channels Chunk
                    const numChannels = await this.tokenizer.readToken(token_types_lib.UINT16_BE);
                    this.metadata.setFormat('numberOfChannels', numChannels);
                    await this.handleChannelChunks(sndPropHeader.chunkSize - BigInt(token_types_lib.UINT16_BE.len));
                    break;
                }
                case 'CMPR': {
                    // 3.2.3 Compression Type Chunk
                    const compressionIdCode = (await this.tokenizer.readToken(FourCC/* FourCcToken */.e)).trim();
                    const count = await this.tokenizer.readToken(token_types_lib.UINT8);
                    const compressionName = await this.tokenizer.readToken(new token_types_lib.StringType(count, 'ascii'));
                    if (compressionIdCode === 'DSD') {
                        this.metadata.setFormat('lossless', true);
                        this.metadata.setFormat('bitsPerSample', 1);
                    }
                    this.metadata.setFormat('codec', `${compressionIdCode} (${compressionName})`);
                    break;
                }
                case 'ABSS': {
                    // 3.2.4 Absolute Start Time Chunk
                    const hours = await this.tokenizer.readToken(token_types_lib.UINT16_BE);
                    const minutes = await this.tokenizer.readToken(token_types_lib.UINT8);
                    const seconds = await this.tokenizer.readToken(token_types_lib.UINT8);
                    const samples = await this.tokenizer.readToken(token_types_lib.UINT32_BE);
                    debug(`ABSS ${hours}:${minutes}:${seconds}.${samples}`);
                    break;
                }
                case 'LSCO': {
                    // 3.2.5 Loudspeaker Configuration Chunk
                    const lsConfig = await this.tokenizer.readToken(token_types_lib.UINT16_BE);
                    debug(`LSCO lsConfig=${lsConfig}`);
                    break;
                }
                default:
                    debug(`Unknown sound-property-chunk[ID=${sndPropHeader.chunkID}, size=${sndPropHeader.chunkSize}]`);
                    await this.tokenizer.ignore(Number(sndPropHeader.chunkSize));
            }
            const remaining = sndPropHeader.chunkSize - BigInt(this.tokenizer.position - p0);
            if (remaining > 0) {
                debug(`After Parsing sound-property-chunk ${sndPropHeader.chunkSize}, remaining ${remaining} bytes`);
                await this.tokenizer.ignore(Number(remaining));
            }
            remainingSize -= BigInt(ChunkHeader64.len) + sndPropHeader.chunkSize;
            debug(`Parsing sound-property-chunks, remainingSize=${remainingSize}`);
        }
        if (this.metadata.format.lossless &&
            this.metadata.format.sampleRate &&
            this.metadata.format.numberOfChannels &&
            this.metadata.format.bitsPerSample) {
            const bitrate = this.metadata.format.sampleRate * this.metadata.format.numberOfChannels * this.metadata.format.bitsPerSample;
            this.metadata.setFormat('bitrate', bitrate);
        }
    }
    async handleChannelChunks(remainingSize) {
        debug(`Parsing channel-chunks, remainingSize=${remainingSize}`);
        const channels = [];
        while (remainingSize >= FourCC/* FourCcToken */.e.len) {
            const channelId = await this.tokenizer.readToken(FourCC/* FourCcToken */.e);
            debug(`Channel[ID=${channelId}]`);
            channels.push(channelId);
            remainingSize -= BigInt(FourCC/* FourCcToken */.e.len);
        }
        debug(`Channels: ${channels.join(', ')}`);
        return channels;
    }
}


/***/ })

};
;