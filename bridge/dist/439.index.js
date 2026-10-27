"use strict";
exports.id = 439;
exports.ids = [439];
exports.modules = {

/***/ 7439:
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

// ESM COMPAT FLAG
__webpack_require__.r(__webpack_exports__);

// EXPORTS
__webpack_require__.d(__webpack_exports__, {
  WaveParser: () => (/* binding */ WaveParser)
});

// EXTERNAL MODULE: ./bridge/node_modules/debug/src/index.js
var src = __webpack_require__(5721);
// EXTERNAL MODULE: ./bridge/node_modules/strtok3/lib/index.js + 2 modules
var lib = __webpack_require__(6481);
// EXTERNAL MODULE: ./bridge/node_modules/token-types/lib/index.js
var token_types_lib = __webpack_require__(9431);
// EXTERNAL MODULE: ./bridge/node_modules/music-metadata/lib/common/BasicParser.js
var BasicParser = __webpack_require__(5258);
// EXTERNAL MODULE: ./bridge/node_modules/music-metadata/lib/common/FourCC.js
var FourCC = __webpack_require__(677);
// EXTERNAL MODULE: ./bridge/node_modules/music-metadata/lib/id3v2/ID3v2Parser.js + 3 modules
var ID3v2Parser = __webpack_require__(8503);
;// CONCATENATED MODULE: ./bridge/node_modules/music-metadata/lib/riff/RiffChunk.js

/**
 * Common RIFF chunk header
 */
const Header = {
    len: 8,
    get: (buf, off) => {
        return {
            // Group-ID
            chunkID: new token_types_lib.StringType(4, 'latin1').get(buf, off),
            // Size
            chunkSize: token_types_lib.UINT32_LE.get(buf, off + 4)
        };
    }
};
/**
 * Token to parse RIFF-INFO tag value
 */
class ListInfoTagValue {
    constructor(tagHeader, encoding = 'latin1') {
        this.encoding = encoding;
        this.tagHeader = tagHeader;
        this.len = tagHeader.chunkSize;
        this.len += this.len & 1; // if it is an odd length, round up to even
    }
    get(buf, off) {
        return new token_types_lib.StringType(this.tagHeader.chunkSize, this.encoding).get(buf, off).split('\0', 1)[0];
    }
}

// EXTERNAL MODULE: ./bridge/node_modules/music-metadata/lib/common/Util.js
var Util = __webpack_require__(7659);
;// CONCATENATED MODULE: ./bridge/node_modules/music-metadata/lib/wav/BwfChunk.js


/**
 * Broadcast Audio Extension Chunk
 * Ref: https://tech.ebu.ch/docs/tech/tech3285.pdf
 */
const BroadcastAudioExtensionChunk = {
    len: 420,
    get: (uint8array, off) => {
        return {
            description: (0,Util/* stripNulls */.F6)(new token_types_lib.StringType(256, 'ascii').get(uint8array, off)).trim(),
            originator: (0,Util/* stripNulls */.F6)(new token_types_lib.StringType(32, 'ascii').get(uint8array, off + 256)).trim(),
            originatorReference: (0,Util/* stripNulls */.F6)(new token_types_lib.StringType(32, 'ascii').get(uint8array, off + 288)).trim(),
            originationDate: (0,Util/* stripNulls */.F6)(new token_types_lib.StringType(10, 'ascii').get(uint8array, off + 320)).trim(),
            originationTime: (0,Util/* stripNulls */.F6)(new token_types_lib.StringType(8, 'ascii').get(uint8array, off + 330)).trim(),
            timeReferenceLow: token_types_lib.UINT32_LE.get(uint8array, off + 338),
            timeReferenceHigh: token_types_lib.UINT32_LE.get(uint8array, off + 342),
            version: token_types_lib.UINT16_LE.get(uint8array, off + 346),
            umid: new token_types_lib.Uint8ArrayType(64).get(uint8array, off + 348),
            loudnessValue: token_types_lib.UINT16_LE.get(uint8array, off + 412),
            maxTruePeakLevel: token_types_lib.UINT16_LE.get(uint8array, off + 414),
            maxMomentaryLoudness: token_types_lib.UINT16_LE.get(uint8array, off + 416),
            maxShortTermLoudness: token_types_lib.UINT16_LE.get(uint8array, off + 418)
        };
    }
};

// EXTERNAL MODULE: ./bridge/node_modules/music-metadata/lib/ParseError.js
var ParseError = __webpack_require__(4326);
;// CONCATENATED MODULE: ./bridge/node_modules/music-metadata/lib/wav/WaveChunk.js


class WaveContentError extends (0,ParseError/* makeUnexpectedFileContentError */.fO)('Wave') {
}
/**
 * Ref: https://msdn.microsoft.com/en-us/library/windows/desktop/dd317599(v=vs.85).aspx
 */
const WaveFormat = {
    PCM: 0x0001,
    ADPCM: 0x0002,
    IEEE_FLOAT: 0x0003,
    ALAW: 0x0006,
    MULAW: 0x0007,
    DVI_ADPCM: 0x0011,
    GSM610: 0x0031,
    // MPEG-4 and AAC Audio Types
    MPEG_ADTS_AAC: 0x1600,
    MPEG_LOAS: 0x1602,
    RAW_AAC1: 0x00ff,
    // Dolby Audio Types
    DOLBY_AC3_SPDIF: 0x0092,
    DVM: 0x2000,
    RAW_SPORT: 0x0240,
    ESST_AC3: 0x0241,
    DRM: 0x0009,
    DTS2: 0x2001,
    MPEG: 0x0050,
    MPEGLAYER3: 0x0055
};
const WaveFormatNameMap = {
    [WaveFormat.PCM]: 'PCM',
    [WaveFormat.ADPCM]: 'ADPCM',
    [WaveFormat.IEEE_FLOAT]: 'IEEE_FLOAT',
    [WaveFormat.ALAW]: 'ALAW',
    [WaveFormat.MULAW]: 'MULAW',
    [WaveFormat.DVI_ADPCM]: 'DVI_ADPCM',
    [WaveFormat.GSM610]: 'GSM610',
    [WaveFormat.MPEG_ADTS_AAC]: 'MPEG_ADTS_AAC',
    [WaveFormat.MPEG_LOAS]: 'MPEG_LOAS',
    [WaveFormat.RAW_AAC1]: 'RAW_AAC1',
    [WaveFormat.DOLBY_AC3_SPDIF]: 'DOLBY_AC3_SPDIF',
    [WaveFormat.DVM]: 'DVM',
    [WaveFormat.RAW_SPORT]: 'RAW_SPORT',
    [WaveFormat.ESST_AC3]: 'ESST_AC3',
    [WaveFormat.DRM]: 'DRM',
    [WaveFormat.DTS2]: 'DTS2',
    [WaveFormat.MPEG]: 'MPEG',
    [WaveFormat.MPEGLAYER3]: 'MPEGLAYER3'
};
/**
 * format chunk; chunk-id is "fmt "
 * http://soundfile.sapp.org/doc/WaveFormat/
 */
class Format {
    constructor(header) {
        if (header.chunkSize < 16) {
            throw new WaveContentError('Invalid chunk size');
        }
        this.len = header.chunkSize;
    }
    get(buf, off) {
        return {
            wFormatTag: token_types_lib.UINT16_LE.get(buf, off),
            nChannels: token_types_lib.UINT16_LE.get(buf, off + 2),
            nSamplesPerSec: token_types_lib.UINT32_LE.get(buf, off + 4),
            nAvgBytesPerSec: token_types_lib.UINT32_LE.get(buf, off + 8),
            nBlockAlign: token_types_lib.UINT16_LE.get(buf, off + 12),
            wBitsPerSample: token_types_lib.UINT16_LE.get(buf, off + 14)
        };
    }
}
/**
 * Fact chunk; chunk-id is "fact"
 * http://www-mmsp.ece.mcgill.ca/Documents/AudioFormats/WAVE/WAVE.html
 * http://www.recordingblogs.com/wiki/fact-chunk-of-a-wave-file
 */
class FactChunk {
    constructor(header) {
        if (header.chunkSize < 4) {
            throw new WaveContentError('Invalid fact chunk size.');
        }
        this.len = header.chunkSize;
    }
    get(buf, off) {
        return {
            dwSampleLength: token_types_lib.UINT32_LE.get(buf, off)
        };
    }
}

;// CONCATENATED MODULE: ./bridge/node_modules/music-metadata/lib/wav/WaveParser.js










const debug = src('music-metadata:parser:RIFF');
/**
 * Resource Interchange File Format (RIFF) Parser
 *
 * WAVE PCM soundfile format
 *
 * Ref:
 * - http://www.johnloomis.org/cpe102/asgn/asgn1/riff.html
 * - http://soundfile.sapp.org/doc/WaveFormat
 *
 * ToDo: Split WAVE part from RIFF parser
 */
class WaveParser extends BasicParser/* BasicParser */.s {
    constructor() {
        super(...arguments);
        this.pendingInfoTags = [];
        this.blockAlign = 0;
        this.avgBytesPerSec = 0;
    }
    async parse() {
        const riffHeader = await this.tokenizer.readToken(Header);
        debug(`pos=${this.tokenizer.position}, parse: chunkID=${riffHeader.chunkID}`);
        if (riffHeader.chunkID !== 'RIFF') {
            return; // Not RIFF format
        }
        this.metadata.setAudioOnly();
        await this.parseRiffChunk(riffHeader.chunkSize).catch(err => {
            if (!(err instanceof lib/* EndOfStreamError */.d1)) {
                throw err;
            }
        });
        await this.flushInfoTags();
    }
    async parseRiffChunk(chunkSize) {
        const type = await this.tokenizer.readToken(FourCC/* FourCcToken */.e);
        this.metadata.setFormat('container', type);
        switch (type) {
            case 'WAVE':
                return this.readWaveChunk(chunkSize - FourCC/* FourCcToken */.e.len);
            default:
                throw new WaveContentError(`Unsupported RIFF format: RIFF/${type}`);
        }
    }
    async readWaveChunk(remaining) {
        while (remaining >= Header.len) {
            const header = await this.tokenizer.readToken(Header);
            remaining -= Header.len + header.chunkSize;
            if (header.chunkSize > remaining) {
                this.metadata.addWarning('Data chunk size exceeds file size');
            }
            this.header = header;
            debug(`pos=${this.tokenizer.position}, readChunk: chunkID=RIFF/WAVE/${header.chunkID}`);
            switch (header.chunkID) {
                case 'CSET': {
                    if (header.chunkSize < 8) {
                        throw new WaveContentError('CSET chunk must contain at least 8 bytes');
                    }
                    const cset = await this.tokenizer.readToken(new token_types_lib.Uint8ArrayType(header.chunkSize));
                    this.codePage = token_types_lib.UINT16_LE.get(cset, 0);
                    if (!this.getInfoEncoding()) {
                        this.metadata.addWarning(`Unsupported RIFF CSET code page: ${this.codePage}; LIST/INFO tags will be omitted`);
                    }
                    await this.flushInfoTags();
                    break;
                }
                case 'LIST':
                    await this.parseListTag(header);
                    break;
                case 'fact': // extended Format chunk,
                    this.metadata.setFormat('lossless', false);
                    this.fact = await this.tokenizer.readToken(new FactChunk(header));
                    break;
                case 'fmt ': {
                    // The Util Chunk, non-PCM Formats
                    const fmt = await this.tokenizer.readToken(new Format(header));
                    let subFormat = WaveFormatNameMap[fmt.wFormatTag];
                    if (!subFormat) {
                        debug(`WAVE/non-PCM format=${fmt.wFormatTag}`);
                        subFormat = `non-PCM (${fmt.wFormatTag})`;
                    }
                    this.metadata.setFormat('codec', subFormat);
                    this.metadata.setFormat('bitsPerSample', fmt.wBitsPerSample);
                    this.metadata.setFormat('sampleRate', fmt.nSamplesPerSec);
                    this.metadata.setFormat('numberOfChannels', fmt.nChannels);
                    this.blockAlign = fmt.nBlockAlign;
                    this.avgBytesPerSec = fmt.nAvgBytesPerSec;
                    break;
                }
                case 'id3 ': // The way Picard, FooBar currently stores, ID3 meta-data
                case 'ID3 ': {
                    // The way Mp3Tags stores ID3 meta-data
                    const id3_data = await this.tokenizer.readToken(new token_types_lib.Uint8ArrayType(header.chunkSize));
                    const rst = lib/* fromBuffer */.vY(id3_data);
                    await new ID3v2Parser/* ID3v2Parser */.S().parse(this.metadata, rst, this.options);
                    break;
                }
                case 'data': {
                    // PCM-data
                    if (this.metadata.format.lossless !== false) {
                        this.metadata.setFormat('lossless', true);
                    }
                    let chunkSize = header.chunkSize;
                    if (this.tokenizer.fileInfo.size) {
                        const calcRemaining = this.tokenizer.fileInfo.size - this.tokenizer.position;
                        if (calcRemaining < chunkSize) {
                            this.metadata.addWarning('data chunk length exceeding file length');
                            chunkSize = calcRemaining;
                        }
                    }
                    const numberOfSamples = this.fact
                        ? this.fact.dwSampleLength
                        : chunkSize === 0xffffffff
                            ? undefined
                            : chunkSize / this.blockAlign;
                    if (numberOfSamples) {
                        this.metadata.setFormat('numberOfSamples', numberOfSamples);
                        if (this.metadata.format.sampleRate) {
                            this.metadata.setFormat('duration', numberOfSamples / this.metadata.format.sampleRate);
                        }
                    }
                    if (this.avgBytesPerSec > 0) {
                        this.metadata.setFormat('bitrate', this.avgBytesPerSec * 8);
                    }
                    else if (this.metadata.format.duration) {
                        this.metadata.setFormat('bitrate', (chunkSize * 8) / this.metadata.format.duration);
                    }
                    await this.tokenizer.ignore(header.chunkSize);
                    break;
                }
                case 'bext': {
                    // Broadcast Audio Extension chunk	https://tech.ebu.ch/docs/tech/tech3285.pdf
                    const bext = await this.tokenizer.readToken(BroadcastAudioExtensionChunk);
                    for (const key of Object.keys(bext)) {
                        await this.metadata.addTag('exif', `bext.${key}`, bext[key]);
                    }
                    const bextRemaining = header.chunkSize - BroadcastAudioExtensionChunk.len;
                    await this.tokenizer.ignore(bextRemaining);
                    break;
                }
                case '\x00\x00\x00\x00': // padding ??
                    debug(`Ignore padding chunk: RIFF/${header.chunkID} of ${header.chunkSize} bytes`);
                    this.metadata.addWarning(`Ignore chunk: RIFF/${header.chunkID}`);
                    await this.tokenizer.ignore(header.chunkSize);
                    break;
                default:
                    debug(`Ignore chunk: RIFF/${header.chunkID} of ${header.chunkSize} bytes`);
                    this.metadata.addWarning(`Ignore chunk: RIFF/${header.chunkID}`);
                    await this.tokenizer.ignore(header.chunkSize);
            }
            if (this.header.chunkSize % 2 === 1) {
                debug('Read odd padding byte'); // https://wiki.multimedia.cx/index.php/RIFF
                await this.tokenizer.ignore(1);
            }
        }
    }
    async parseListTag(listHeader) {
        const listType = await this.tokenizer.readToken(new token_types_lib.StringType(4, 'latin1'));
        debug('pos=%s, parseListTag: chunkID=RIFF/WAVE/LIST/%s', this.tokenizer.position, listType);
        switch (listType) {
            case 'INFO':
                return this.parseRiffInfoTags(listHeader.chunkSize - 4);
            default:
                this.metadata.addWarning(`Ignore chunk: RIFF/WAVE/LIST/${listType}`);
                debug(`Ignoring chunkID=RIFF/WAVE/LIST/${listType}`);
                return this.tokenizer.ignore(listHeader.chunkSize - 4).then();
        }
    }
    async parseRiffInfoTags(chunkSize) {
        while (chunkSize >= 8) {
            const header = await this.tokenizer.readToken(Header);
            const valueToken = new ListInfoTagValue(header);
            const bytes = await this.tokenizer.readToken(new token_types_lib.Uint8ArrayType(valueToken.len));
            if (this.codePage === undefined) {
                this.metadata.registerTagType('exif');
                this.pendingInfoTags.push({ header, bytes });
            }
            else {
                await this.addInfoTag(header, bytes);
            }
            chunkSize -= 8 + valueToken.len;
        }
        if (chunkSize !== 0) {
            throw new WaveContentError(`Illegal remaining size: ${chunkSize}`);
        }
    }
    async addInfoTag(header, bytes) {
        const encoding = this.getInfoEncoding();
        if (encoding) {
            const value = new ListInfoTagValue(header, encoding).get(bytes, 0);
            await this.metadata.addTag('exif', header.chunkID, value);
        }
    }
    async flushInfoTags() {
        for (const { header, bytes } of this.pendingInfoTags.splice(0)) {
            await this.addInfoTag(header, bytes);
        }
    }
    getInfoEncoding() {
        // RIFF CSET: absent/zero means ISO 8859-1, not Windows-1252.
        // https://www.robotplanet.dk/audio/wav_meta_data/riff_mci.pdf#page=25
        switch (this.codePage) {
            case undefined:
            case 0:
            case 28591:
                return 'latin1';
            case 1252:
                return 'windows-1252';
            case 65001:
                return 'utf-8';
            default:
                return undefined;
        }
    }
}


/***/ })

};
;