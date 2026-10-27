"use strict";
exports.id = 503;
exports.ids = [503];
exports.modules = {

/***/ 8503:
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {


// EXPORTS
__webpack_require__.d(__webpack_exports__, {
  S: () => (/* binding */ ID3v2Parser)
});

// EXTERNAL MODULE: ./bridge/node_modules/token-types/lib/index.js
var lib = __webpack_require__(9431);
// EXTERNAL MODULE: ./bridge/node_modules/@borewit/text-codec/lib/index.js
var text_codec_lib = __webpack_require__(1777);
// EXTERNAL MODULE: ./bridge/node_modules/music-metadata/lib/common/Util.js
var Util = __webpack_require__(7659);
// EXTERNAL MODULE: ./bridge/node_modules/debug/src/index.js
var src = __webpack_require__(5721);
// EXTERNAL MODULE: ./bridge/node_modules/music-metadata/lib/id3v1/ID3v1Parser.js
var ID3v1Parser = __webpack_require__(781);
// EXTERNAL MODULE: ./bridge/node_modules/music-metadata/lib/ParseError.js
var ParseError = __webpack_require__(4326);
;// CONCATENATED MODULE: ./bridge/node_modules/music-metadata/lib/id3v2/ID3v2ChapterToken.js

/**
 * Data portion of `CHAP` sub frame
 */
const ChapterInfo = {
    len: 16,
    get: (buf, off) => {
        const startOffset = lib.UINT32_BE.get(buf, off + 8);
        const endOffset = lib.UINT32_BE.get(buf, off + 12);
        return {
            startTime: lib.UINT32_BE.get(buf, off),
            endTime: lib.UINT32_BE.get(buf, off + 4),
            startOffset: startOffset === 0xffffffff ? undefined : startOffset,
            endOffset: endOffset === 0xffffffff ? undefined : endOffset
        };
    }
};

// EXTERNAL MODULE: ./bridge/node_modules/music-metadata/lib/id3v2/ID3v2Token.js
var ID3v2Token = __webpack_require__(8597);
;// CONCATENATED MODULE: ./bridge/node_modules/music-metadata/lib/id3v2/FrameParser.js









const debug = src('music-metadata:id3v2:frame-parser');
const defaultEnc = 'latin1'; // latin1 == iso-8859-1;
const urlEnc = { encoding: defaultEnc, bom: false };
function parseGenre(origVal) {
    // match everything inside parentheses
    const genres = [];
    let code;
    let word = '';
    for (const c of origVal) {
        if (typeof code === 'string') {
            if (c === '(' && code === '') {
                word += '(';
                code = undefined;
            }
            else if (c === ')') {
                if (word !== '') {
                    genres.push(word);
                    word = '';
                }
                const genre = parseGenreCode(code);
                if (genre) {
                    genres.push(genre);
                }
                code = undefined;
            }
            else {
                code += c;
            }
        }
        else if (c === '(') {
            code = '';
        }
        else {
            word += c;
        }
    }
    if (word) {
        if (genres.length === 0 && word.match(/^\d*$/)) {
            word = parseGenreCode(word);
        }
        if (word) {
            genres.push(word);
        }
    }
    return genres;
}
function parseGenreCode(code) {
    if (code === 'RX') {
        return 'Remix';
    }
    if (code === 'CR') {
        return 'Cover';
    }
    if (code.match(/^\d*$/)) {
        return ID3v1Parser/* Genres */.jR[Number.parseInt(code, 10)];
    }
}
class FrameParser {
    /**
     * Create id3v2 frame parser
     * @param major - Major version, e.g. (4) for  id3v2.4
     * @param warningCollector - Used to collect decode issue
     */
    constructor(major, warningCollector) {
        this.major = major;
        this.warningCollector = warningCollector;
    }
    readData(uint8Array, type, includeCovers) {
        if (uint8Array.length === 0) {
            this.warningCollector.addWarning(`id3v2.${this.major} header has empty tag type=${type}`);
            return;
        }
        const { encoding, bom } = ID3v2Token/* TextEncodingToken */.YF.get(uint8Array, 0);
        const length = uint8Array.length;
        let offset = 0;
        let output = []; // ToDo
        const nullTerminatorLength = FrameParser.getNullTerminatorLength(encoding);
        let fzero;
        debug(`Parsing tag type=${type}, encoding=${encoding}, bom=${bom}`);
        switch (type !== 'TXXX' && type[0] === 'T' ? 'T*' : type) {
            case 'T*': // 4.2.1. Text information frames - details
            case 'GRP1': // iTunes-specific ID3v2 grouping field
            case 'GP1': // iTunes-specific ID3v2.2 grouping field
            case 'IPLS': // v2.3: Involved people list
            case 'MVIN':
            case 'MVNM':
            case 'PCS':
            case 'PCST': {
                let text;
                try {
                    text = FrameParser.trimNullPadding(Util/* decodeString */.sc(uint8Array.subarray(1), encoding));
                }
                catch (error) {
                    if (error instanceof Error) {
                        this.warningCollector.addWarning(`id3v2.${this.major} type=${type} header has invalid string value: ${error.message}`);
                        break;
                    }
                    throw error;
                }
                switch (type) {
                    case 'TMCL': // Musician credits list
                    case 'TIPL': // Involved people list
                    case 'IPLS': // Involved people list
                        output = FrameParser.functionList(this.splitValue(type, text));
                        break;
                    case 'TRK':
                    case 'TRCK':
                    case 'TPOS':
                    case 'TIT1':
                    case 'TIT2':
                    case 'TIT3':
                        output = text;
                        break;
                    case 'TCOM':
                    case 'TEXT':
                    case 'TOLY':
                    case 'TOPE':
                    case 'TPE1':
                    case 'TSRC':
                        // id3v2.3 defines that TCOM, TEXT, TOLY, TOPE & TPE1 values are separated by /
                        output = this.splitValue(type, text);
                        break;
                    case 'TCO':
                    case 'TCON':
                        output = this.splitValue(type, text)
                            .map(v => parseGenre(v))
                            .reduce((acc, val) => acc.concat(val), []);
                        break;
                    case 'PCS':
                    case 'PCST':
                        // TODO: Why `default` not results `1` but `''`?
                        output = this.major >= 4 ? this.splitValue(type, text) : [text];
                        output = Array.isArray(output) && output[0] === '' ? 1 : 0;
                        break;
                    default:
                        output = this.major >= 4 ? this.splitValue(type, text) : [text];
                }
                break;
            }
            case 'TXXX': {
                const idAndData = FrameParser.readIdentifierAndData(uint8Array.subarray(1), encoding);
                output = {
                    description: idAndData.id,
                    text: this.splitValue(type, FrameParser.trimNullPadding(Util/* decodeString */.sc(idAndData.data, encoding)))
                };
                break;
            }
            case 'PIC':
            case 'APIC':
                if (includeCovers) {
                    const pic = {};
                    uint8Array = uint8Array.subarray(1);
                    switch (this.major) {
                        case 2:
                            pic.format = Util/* decodeString */.sc(uint8Array.subarray(0, 3), 'latin1'); // 'latin1'; // latin1 == iso-8859-1;
                            uint8Array = uint8Array.subarray(3);
                            break;
                        case 3:
                        case 4:
                            fzero = Util/* findZero */.sX(uint8Array, defaultEnc);
                            pic.format = Util/* decodeString */.sc(uint8Array.subarray(0, fzero), defaultEnc);
                            uint8Array = uint8Array.subarray(fzero + 1);
                            break;
                        default:
                            throw makeUnexpectedMajorVersionError(this.major);
                    }
                    pic.format = FrameParser.fixPictureMimeType(pic.format);
                    pic.type = ID3v2Token/* AttachedPictureType */.n5[uint8Array[0]];
                    uint8Array = uint8Array.subarray(1);
                    fzero = Util/* findZero */.sX(uint8Array, encoding);
                    pic.description = Util/* decodeString */.sc(uint8Array.subarray(0, fzero), encoding);
                    uint8Array = uint8Array.subarray(fzero + nullTerminatorLength);
                    pic.data = uint8Array;
                    output = pic;
                }
                break;
            case 'CNT':
            case 'PCNT':
                output = (0,Util/* decodeUintBE */.ku)(uint8Array);
                break;
            case 'SYLT': {
                const syltHeader = ID3v2Token/* SyncTextHeader */.aY.get(uint8Array, 0);
                uint8Array = uint8Array.subarray(ID3v2Token/* SyncTextHeader */.aY.len);
                const result = {
                    descriptor: '',
                    language: syltHeader.language,
                    contentType: syltHeader.contentType,
                    timeStampFormat: syltHeader.timeStampFormat,
                    syncText: []
                };
                let readSyllables = false;
                while (uint8Array.length > 0) {
                    const nullStr = FrameParser.readNullTerminatedString(uint8Array, syltHeader.encoding);
                    uint8Array = uint8Array.subarray(nullStr.len);
                    if (readSyllables) {
                        const timestamp = lib.UINT32_BE.get(uint8Array, 0);
                        uint8Array = uint8Array.subarray(lib.UINT32_BE.len);
                        result.syncText.push({
                            text: nullStr.text,
                            timestamp
                        });
                    }
                    else {
                        result.descriptor = nullStr.text;
                        readSyllables = true;
                    }
                }
                output = result;
                break;
            }
            case 'ULT':
            case 'USLT':
            case 'COM':
            case 'COMM': {
                const textHeader = ID3v2Token/* TextHeader */.Jp.get(uint8Array, offset);
                offset += ID3v2Token/* TextHeader */.Jp.len;
                const descriptorStr = FrameParser.readNullTerminatedString(uint8Array.subarray(offset), textHeader.encoding);
                offset += descriptorStr.len;
                const textStr = FrameParser.readNullTerminatedString(uint8Array.subarray(offset), textHeader.encoding);
                const comment = {
                    language: textHeader.language,
                    descriptor: descriptorStr.text,
                    text: textStr.text
                };
                output = comment;
                break;
            }
            case 'UFID': {
                const ufid = FrameParser.readIdentifierAndData(uint8Array, defaultEnc);
                output = { owner_identifier: ufid.id, identifier: ufid.data };
                break;
            }
            case 'PRIV': {
                // private frame
                const priv = FrameParser.readIdentifierAndData(uint8Array, defaultEnc);
                output = { owner_identifier: priv.id, data: priv.data };
                break;
            }
            case 'POPM': {
                // Popularimeter
                uint8Array = uint8Array.subarray(offset);
                const emailStr = FrameParser.readNullTerminatedString(uint8Array, urlEnc);
                const email = emailStr.text;
                uint8Array = uint8Array.subarray(emailStr.len);
                if (uint8Array.length === 0) {
                    this.warningCollector.addWarning(`id3v2.${this.major} type=${type} POPM frame missing rating byte`);
                    output = { email, rating: 0, counter: undefined };
                    break;
                }
                const rating = lib.UINT8.get(uint8Array, 0);
                const counterBytes = uint8Array.subarray(lib.UINT8.len);
                output = {
                    email,
                    rating,
                    counter: counterBytes.length > 0 ? (0,Util/* decodeUintBE */.ku)(counterBytes) : undefined
                };
                break;
            }
            case 'GEOB': {
                // General encapsulated object
                // [encoding] <MIME> 0x00 <filename> 0x00/0x00 0x00 <description> 0x00/0x00 0x00 <data>
                const encoding = ID3v2Token/* TextEncodingToken */.YF.get(uint8Array, 0);
                uint8Array = uint8Array.subarray(1);
                const mimeTypeStr = FrameParser.readNullTerminatedString(uint8Array, urlEnc);
                const mimeType = mimeTypeStr.text;
                uint8Array = uint8Array.subarray(mimeTypeStr.len);
                const filenameStr = FrameParser.readNullTerminatedString(uint8Array, encoding);
                const filename = filenameStr.text;
                uint8Array = uint8Array.subarray(filenameStr.len);
                const descriptionStr = FrameParser.readNullTerminatedString(uint8Array, encoding);
                const description = descriptionStr.text;
                uint8Array = uint8Array.subarray(descriptionStr.len);
                const geob = {
                    type: mimeType,
                    filename,
                    description,
                    data: uint8Array
                };
                output = geob;
                break;
            }
            // W-Frames:
            case 'WCOM':
            case 'WCOP':
            case 'WOAF':
            case 'WOAR':
            case 'WOAS':
            case 'WORS':
            case 'WPAY':
            case 'WPUB':
                // Decode URL
                output = FrameParser.readNullTerminatedString(uint8Array, urlEnc).text;
                break;
            case 'WXXX': {
                // [encoding] <description> 0x00/0x00 0x00 <url>
                const encoding = ID3v2Token/* TextEncodingToken */.YF.get(uint8Array, 0);
                uint8Array = uint8Array.subarray(1);
                const descriptionStr = FrameParser.readNullTerminatedString(uint8Array, encoding);
                const description = descriptionStr.text;
                uint8Array = uint8Array.subarray(descriptionStr.len);
                // URL is always ISO-8859-1
                output = { description, url: FrameParser.trimNullPadding(Util/* decodeString */.sc(uint8Array, defaultEnc)) };
                break;
            }
            case 'WFD':
            case 'WFED': {
                const encoding = ID3v2Token/* TextEncodingToken */.YF.get(uint8Array, 0);
                uint8Array = uint8Array.subarray(1);
                output = FrameParser.readNullTerminatedString(uint8Array, encoding).text;
                break;
            }
            case 'MCDI': {
                // Music CD identifier
                output = uint8Array.subarray(0, length);
                break;
            }
            // ID3v2 Chapters 1.0
            // https://mutagen-specs.readthedocs.io/en/latest/id3/id3v2-chapters-1.0.html#chapter-frame
            case 'CHAP': {
                //  // Chapter frame
                debug('Reading CHAP');
                fzero = Util/* findZero */.sX(uint8Array, defaultEnc);
                const chapter = {
                    label: Util/* decodeString */.sc(uint8Array.subarray(0, fzero), defaultEnc),
                    info: ChapterInfo.get(uint8Array, fzero + 1),
                    frames: new Map()
                };
                offset += fzero + 1 + ChapterInfo.len;
                while (offset < length) {
                    const subFrame = readFrameHeader(uint8Array.subarray(offset), this.major, this.warningCollector);
                    const headerSize = getFrameHeaderLength(this.major);
                    offset += headerSize;
                    const subOutput = this.readData(uint8Array.subarray(offset, offset + subFrame.length), subFrame.id, includeCovers);
                    offset += subFrame.length;
                    chapter.frames.set(subFrame.id, subOutput);
                }
                output = chapter;
                break;
            }
            // ID3v2 Chapters 1.0
            // https://mutagen-specs.readthedocs.io/en/latest/id3/id3v2-chapters-1.0.html#table-of-contents-frame
            case 'CTOC': {
                // Table of contents frame
                debug('Reading CTOC');
                // Element ID (null-terminated latin1)
                const idEnd = Util/* findZero */.sX(uint8Array, defaultEnc);
                const label = Util/* decodeString */.sc(uint8Array.subarray(0, idEnd), defaultEnc);
                offset = idEnd + 1;
                // Flags
                const flags = uint8Array[offset++];
                const topLevel = (flags & 0x02) !== 0;
                const ordered = (flags & 0x01) !== 0;
                // Child element IDs
                const entryCount = uint8Array[offset++];
                const childElementIds = [];
                for (let i = 0; i < entryCount && offset < length; i++) {
                    const end = Util/* findZero */.sX(uint8Array.subarray(offset), defaultEnc);
                    const childId = Util/* decodeString */.sc(uint8Array.subarray(offset, offset + end), defaultEnc);
                    childElementIds.push(childId);
                    offset += end + 1;
                }
                const toc = {
                    label,
                    flags: { topLevel, ordered },
                    childElementIds,
                    frames: new Map()
                };
                // Optional embedded sub-frames (e.g. TIT2) follow after the child list
                while (offset < length) {
                    const subFrame = readFrameHeader(uint8Array.subarray(offset), this.major, this.warningCollector);
                    const headerSize = getFrameHeaderLength(this.major);
                    offset += headerSize;
                    const subOutput = this.readData(uint8Array.subarray(offset, offset + subFrame.length), subFrame.id, includeCovers);
                    offset += subFrame.length;
                    toc.frames.set(subFrame.id, subOutput);
                }
                output = toc;
                break;
            }
            default:
                debug(`Warning: unsupported id3v2-tag-type: ${type}`);
                break;
        }
        return output;
    }
    static readNullTerminatedString(uint8Array, encoding) {
        const originalLen = uint8Array.length;
        const zeroIndex = Util/* findZero */.sX(uint8Array, encoding.encoding);
        if (zeroIndex >= originalLen) {
            // No terminator found, decode the whole buffer
            return {
                text: Util/* decodeString */.sc(uint8Array, encoding.encoding),
                len: originalLen
            };
        }
        const txt = uint8Array.subarray(0, zeroIndex);
        return {
            text: Util/* decodeString */.sc(txt, encoding.encoding),
            len: zeroIndex + FrameParser.getNullTerminatorLength(encoding.encoding)
        };
    }
    static fixPictureMimeType(pictureType) {
        pictureType = pictureType.toLocaleLowerCase();
        switch (pictureType) {
            case 'jpg':
                return 'image/jpeg';
            case 'png':
                return 'image/png';
        }
        return pictureType;
    }
    /**
     * Converts TMCL (Musician credits list) or TIPL (Involved people list)
     * @param entries
     */
    static functionList(entries) {
        const res = {};
        for (let i = 0; i + 1 < entries.length; i += 2) {
            const names = entries[i + 1].split(',');
            res[entries[i]] = res[entries[i]] ? res[entries[i]].concat(names) : names;
        }
        return res;
    }
    /**
     * id3v2.4 defines that multiple T* values are separated by 0x00
     * id3v2.3 defines that TCOM, TEXT, TOLY, TOPE & TPE1 values are separated by /
     * (TCON/TCO genre values are NOT slash-separated in ID3v2.3)
     * @param tag - Tag name
     * @param text - Concatenated tag value
     * @returns Split tag value
     */
    splitValue(tag, text) {
        let values;
        if (this.major < 4) {
            values = text.split(/\x00/g);
            if (values.length > 1) {
                this.warningCollector.addWarning(`ID3v2.${this.major} ${tag} uses non standard null-separator.`);
            }
            else if ([
                /* v2.3: */ 'TPE1',
                'TPE2',
                'TPE3',
                'TPE4',
                'TCOM',
                'TEXT',
                'TOLY',
                'TOPE',
                /* v2.2: */ 'TP1',
                'TCM',
                'TXT',
                'TOA',
                'TOL',
                // Picard can flatten multiple values with '/' in these frames.
                // https://picard-docs.musicbrainz.org/en/v2.13/config/options_tags_compatibility_id3.html
                'TXXX',
                'TSRC',
                // Retain legacy support for nonstandard slash-separated credits.
                'IPLS'
            ].includes(tag)) {
                // note that field Genre (TCON/TCO) is NOT in this list:
                // a genre like "Duo Cello/Piano" must stay intact
                values = text.split(/\//g);
            }
        }
        else {
            values = text.split(/\x00/g);
        }
        return FrameParser.trimArray(values);
    }
    static trimArray(values) {
        return values.map(value => FrameParser.trimNullPadding(value).trim());
    }
    static trimNullPadding(value) {
        let end = value.length;
        while (end > 0 && value.charCodeAt(end - 1) === 0) {
            end--;
        }
        return end === value.length ? value : value.slice(0, end);
    }
    static readIdentifierAndData(uint8Array, encoding) {
        const idStr = FrameParser.readNullTerminatedString(uint8Array, { encoding, bom: false });
        return { id: idStr.text, data: uint8Array.subarray(idStr.len) };
    }
    static getNullTerminatorLength(enc) {
        return enc.startsWith('utf-16') ? 2 : 1;
    }
}
class Id3v2ContentError extends (0,ParseError/* makeUnexpectedFileContentError */.fO)('id3v2') {
}
function makeUnexpectedMajorVersionError(majorVer) {
    throw new Id3v2ContentError(`Unexpected majorVer: ${majorVer}`);
}

;// CONCATENATED MODULE: ./bridge/node_modules/music-metadata/lib/id3v2/FrameHeader.js
// lib/id3v2/FrameHeader.ts





/**
 * Frame header length (bytes) depending on ID3v2 major version.
 */
function getFrameHeaderLength(majorVer) {
    switch (majorVer) {
        case 2:
            return 6;
        case 3:
        case 4:
            return 10;
        default:
            throw FrameHeader_makeUnexpectedMajorVersionError(majorVer);
    }
}
function readFrameFlags(b, majorVer) {
    // ID3v2.3 and ID3v2.4 use different bit layouts for the frame status and format flags.
    if (majorVer === 3) {
        // ID3v2.3: status `%abc00000`, format `%ijk00000` (https://id3.org/id3v2.3.0)
        return {
            status: {
                tag_alter_preservation: Util/* getBit */.mh(b, 0, 7),
                file_alter_preservation: Util/* getBit */.mh(b, 0, 6),
                read_only: Util/* getBit */.mh(b, 0, 5)
            },
            format: {
                compression: Util/* getBit */.mh(b, 1, 7),
                encryption: Util/* getBit */.mh(b, 1, 6),
                grouping_identity: Util/* getBit */.mh(b, 1, 5),
                // Unsynchronisation and data-length-indicator do not exist as per-frame flags in ID3v2.3.
                unsynchronisation: false,
                data_length_indicator: false
            }
        };
    }
    // ID3v2.4: status `%0abc0000`, format `%0h00kmnp` (https://id3.org/id3v2.4.0-structure)
    return {
        status: {
            tag_alter_preservation: Util/* getBit */.mh(b, 0, 6),
            file_alter_preservation: Util/* getBit */.mh(b, 0, 5),
            read_only: Util/* getBit */.mh(b, 0, 4)
        },
        format: {
            grouping_identity: Util/* getBit */.mh(b, 1, 6),
            compression: Util/* getBit */.mh(b, 1, 3),
            encryption: Util/* getBit */.mh(b, 1, 2),
            unsynchronisation: Util/* getBit */.mh(b, 1, 1),
            data_length_indicator: Util/* getBit */.mh(b, 1, 0)
        }
    };
}
/**
 * Factory: parse a frame header from its header bytes (6 for v2.2, 10 for v2.3/v2.4).
 *
 * Note: It only *parses* and does light validation. It does not read payload bytes.
 */
function readFrameHeader(uint8Array, majorVer, warningCollector) {
    switch (majorVer) {
        case 2:
            return parseFrameHeaderV22(uint8Array, majorVer, warningCollector);
        case 3:
        case 4:
            return parseFrameHeaderV23V24(uint8Array, majorVer, warningCollector);
        default:
            throw FrameHeader_makeUnexpectedMajorVersionError(majorVer);
    }
}
function parseFrameHeaderV22(uint8Array, majorVer, warningCollector) {
    const header = {
        id: (0,text_codec_lib/* textDecode */.c)(uint8Array.subarray(0, 3), 'ascii'),
        length: lib.UINT24_BE.get(uint8Array, 3)
    };
    if (!header.id.match(/^[A-Z0-9]{3}$/)) {
        warningCollector.addWarning(`Invalid ID3v2.${majorVer} frame-header-ID: ${header.id}`);
    }
    return header;
}
function parseFrameHeaderV23V24(uint8Array, majorVer, warningCollector) {
    const header = {
        id: (0,text_codec_lib/* textDecode */.c)(uint8Array.subarray(0, 4), 'ascii'),
        length: (majorVer === 4 ? ID3v2Token/* UINT32SYNCSAFE */.St : lib.UINT32_BE).get(uint8Array, 4),
        flags: readFrameFlags(uint8Array.subarray(8, 10), majorVer)
    };
    if (!header.id.match(/^[A-Z0-9]{4}$/)) {
        warningCollector.addWarning(`Invalid ID3v2.${majorVer} frame-header-ID: ${header.id}`);
    }
    return header;
}
function FrameHeader_makeUnexpectedMajorVersionError(majorVer) {
    throw new Id3v2ContentError(`Unexpected majorVer: ${majorVer}`);
}

;// CONCATENATED MODULE: ./bridge/node_modules/music-metadata/lib/id3v2/ID3v2Parser.js




class ID3v2Parser {
    constructor() {
        this.tokenizer = undefined;
        this.id3Header = undefined;
        this.metadata = undefined;
        this.headerType = undefined;
        this.options = undefined;
    }
    static removeUnsyncBytes(buffer) {
        let readI = 0;
        let writeI = 0;
        while (readI < buffer.length - 1) {
            if (readI !== writeI) {
                buffer[writeI] = buffer[readI];
            }
            readI += buffer[readI] === 0xff && buffer[readI + 1] === 0 ? 2 : 1;
            writeI++;
        }
        if (readI < buffer.length) {
            buffer[writeI++] = buffer[readI];
        }
        return buffer.subarray(0, writeI);
    }
    static readFrameData(uint8Array, frameHeader, majorVer, includeCovers, warningCollector) {
        const frameParser = new FrameParser(majorVer, warningCollector);
        switch (majorVer) {
            case 2:
                return frameParser.readData(uint8Array, frameHeader.id, includeCovers);
            case 3:
            case 4:
                if (frameHeader.flags?.format.unsynchronisation) {
                    uint8Array = ID3v2Parser.removeUnsyncBytes(uint8Array);
                }
                if (frameHeader.flags?.format.data_length_indicator) {
                    uint8Array = uint8Array.subarray(4, uint8Array.length);
                }
                return frameParser.readData(uint8Array, frameHeader.id, includeCovers);
            default:
                throw ID3v2Parser_makeUnexpectedMajorVersionError(majorVer);
        }
    }
    /**
     * Create a combined tag key, of tag & description
     * @param tag e.g.: COM
     * @param description e.g. iTunPGAP
     * @returns string e.g. COM:iTunPGAP
     */
    static makeDescriptionTagName(tag, description) {
        return tag + (description ? `:${description}` : '');
    }
    async parse(metadata, tokenizer, options) {
        this.tokenizer = tokenizer;
        this.metadata = metadata;
        this.options = options;
        const id3Header = await this.tokenizer.readToken(ID3v2Token/* ID3v2Header */.yW);
        if (id3Header.fileIdentifier !== 'ID3') {
            throw new Id3v2ContentError("expected ID3-header file-identifier 'ID3' was not found");
        }
        const fileSize = this.tokenizer.fileInfo.size;
        if (fileSize !== undefined) {
            const remainingBytes = fileSize - this.tokenizer.position;
            if (id3Header.size > remainingBytes) {
                throw new Id3v2ContentError(`ID3v2 tag size ${id3Header.size} exceeds remaining file size ${remainingBytes}`);
            }
        }
        this.id3Header = id3Header;
        this.headerType = `ID3v2.${id3Header.version.major}`;
        await (id3Header.flags.isExtendedHeader ? this.parseExtendedHeader() : this.parseId3Data(id3Header.size));
        // Post process
        const chapters = ID3v2Parser.mapId3v2Chapters(this.metadata.native[this.headerType]);
        this.metadata.setFormat('chapters', chapters);
    }
    async parseExtendedHeader() {
        const extendedHeader = await this.tokenizer.readToken(ID3v2Token/* ExtendedHeader */.nl);
        const dataRemaining = extendedHeader.size - ID3v2Token/* ExtendedHeader */.nl.len;
        return dataRemaining > 0
            ? this.parseExtendedHeaderData(dataRemaining, extendedHeader.size)
            : this.parseId3Data(this.id3Header.size - extendedHeader.size);
    }
    async parseExtendedHeaderData(dataRemaining, extendedHeaderSize) {
        await this.tokenizer.ignore(dataRemaining);
        return this.parseId3Data(this.id3Header.size - extendedHeaderSize);
    }
    async parseId3Data(dataLen) {
        const uint8Array = await this.tokenizer.readToken(new lib.Uint8ArrayType(dataLen));
        for (const tag of this.parseMetadata(uint8Array)) {
            switch (tag.id) {
                case 'TXXX':
                    if (tag.value) {
                        await this.handleTag(tag, tag.value.text, () => tag.value.description);
                    }
                    break;
                default:
                    await (Array.isArray(tag.value)
                        ? Promise.all(tag.value.map(value => this.addTag(tag.id, value)))
                        : this.addTag(tag.id, tag.value));
            }
        }
    }
    async handleTag(tag, values, descriptor, resolveValue = value => value) {
        await Promise.all(values.map(value => this.addTag(ID3v2Parser.makeDescriptionTagName(tag.id, descriptor(value)), resolveValue(value))));
    }
    async addTag(id, value) {
        await this.metadata.addTag(this.headerType, id, value);
    }
    parseMetadata(data) {
        let offset = 0;
        const tags = [];
        while (true) {
            if (offset === data.length) {
                break;
            }
            const frameHeaderLength = getFrameHeaderLength(this.id3Header.version.major);
            if (offset + frameHeaderLength > data.length) {
                this.metadata.addWarning('Illegal ID3v2 tag length');
                break;
            }
            const frameHeaderBytes = data.subarray(offset, offset + frameHeaderLength);
            offset += frameHeaderLength;
            const frameHeader = readFrameHeader(frameHeaderBytes, this.id3Header.version.major, this.metadata);
            const frameDataBytes = data.subarray(offset, offset + frameHeader.length);
            offset += frameHeader.length;
            const values = ID3v2Parser.readFrameData(frameDataBytes, frameHeader, this.id3Header.version.major, !this.options.skipCovers, this.metadata);
            if (values) {
                tags.push({ id: frameHeader.id, value: values });
            }
        }
        return tags;
    }
    /**
     * Convert parsed ID3v2 chapter frames (CHAP / CTOC) to generic `format.chapters`.
     *
     * This function expects the `native` tags already to contain parsed `CHAP` and `CTOC` frame values,
     * as produced by `FrameParser.readData`.
     */
    static mapId3v2Chapters(id3Tags) {
        if (!id3Tags) {
            return;
        }
        const chapFrames = id3Tags.filter(t => t.id === 'CHAP').map(t => t.value);
        if (!chapFrames.length) {
            return;
        }
        const tocFrames = id3Tags.filter(t => t.id === 'CTOC').map(t => t.value);
        const topLevelToc = tocFrames.find(t => t.flags.topLevel);
        const chapterById = new Map();
        for (const chap of chapFrames) {
            chapterById.set(chap.label, chap);
        }
        const orderedIds = topLevelToc?.childElementIds;
        const chapters = [];
        const source = orderedIds ?? [...chapterById.keys()];
        for (const id of source) {
            const chap = chapterById.get(id);
            if (!chap) {
                continue;
            }
            const frames = chap.frames;
            const title = frames.get('TIT2');
            if (!title) {
                continue; // title is required
            }
            chapters.push({
                id,
                title,
                url: frames.get('WXXX'),
                start: chap.info.startTime / 1000,
                end: chap.info.endTime / 1000,
                image: frames.get('APIC')
            });
        }
        // If no ordered CTOC, sort by time
        if (!orderedIds) {
            chapters.sort((a, b) => a.start - b.start);
        }
        return chapters.length ? chapters : undefined;
    }
}
function ID3v2Parser_makeUnexpectedMajorVersionError(majorVer) {
    throw new Id3v2ContentError(`Unexpected majorVer: ${majorVer}`);
}


/***/ })

};
;