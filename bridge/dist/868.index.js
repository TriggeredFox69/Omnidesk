"use strict";
exports.id = 868;
exports.ids = [868];
exports.modules = {

/***/ 3868:
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

// ESM COMPAT FLAG
__webpack_require__.r(__webpack_exports__);

// EXPORTS
__webpack_require__.d(__webpack_exports__, {
  AsfParser: () => (/* binding */ AsfParser)
});

// EXTERNAL MODULE: ./node_modules/debug/src/index.js
var src = __webpack_require__(9159);
// EXTERNAL MODULE: ./node_modules/strtok3/lib/index.js + 2 modules
var lib = __webpack_require__(397);
// EXTERNAL MODULE: ./node_modules/music-metadata/lib/common/BasicParser.js
var BasicParser = __webpack_require__(6);
// EXTERNAL MODULE: ./node_modules/music-metadata/lib/matroska/types.js
var types = __webpack_require__(8941);
;// CONCATENATED MODULE: ./node_modules/win-guid/lib/guid.js
/**
 * Parse canonical GUID string (xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx)
 * into Windows / CFBF byte order.
 */
function parseWindowsGuid(guid) {
    let s = guid.trim();
    // Keep validation readable and strict, avoid lowercasing allocations
    if (s.length !== 36 ||
        s[8] !== "-" ||
        s[13] !== "-" ||
        s[18] !== "-" ||
        s[23] !== "-") {
        throw new Error(`Invalid GUID format: ${guid}`);
    }
    let v;
    const out = new Uint8Array(16);
    // Data1: 8 hex, uint32 little-endian
    v = parseInt(s.slice(0, 8), 16);
    out[0] = v & 0xff;
    out[1] = (v >>> 8) & 0xff;
    out[2] = (v >>> 16) & 0xff;
    out[3] = (v >>> 24) & 0xff;
    // Data2: 4 hex, uint16 little-endian
    v = parseInt(s.slice(9, 13), 16);
    out[4] = v & 0xff;
    out[5] = (v >>> 8) & 0xff;
    // Data3: 4 hex, uint16 little-endian
    v = parseInt(s.slice(14, 18), 16);
    out[6] = v & 0xff;
    out[7] = (v >>> 8) & 0xff;
    // Data4: 4 hex, as-is (string order)
    v = parseInt(s.slice(19, 23), 16);
    out[8] = (v >>> 8) & 0xff;
    out[9] = v & 0xff;
    // Data5: 12 hex, 6 bytes, as-is (string order)
    // Parse as two chunks to avoid any precision worries, keep it simple.
    v = parseInt(s.slice(24, 32), 16); // 8 hex -> 4 bytes
    out[10] = (v >>> 24) & 0xff;
    out[11] = (v >>> 16) & 0xff;
    out[12] = (v >>> 8) & 0xff;
    out[13] = v & 0xff;
    v = parseInt(s.slice(32, 36), 16); // 4 hex -> 2 bytes
    out[14] = (v >>> 8) & 0xff;
    out[15] = v & 0xff;
    // Ensure all parsed parts were valid hex (parseInt can yield NaN)
    for (let i = 0; i < 16; i++) {
        if (!Number.isFinite(out[i])) {
            throw new Error(`Invalid GUID format: ${guid}`);
        }
    }
    // Also catch NaN early (more useful error locality)
    // If any parseInt produced NaN, assignments above would have become 0,
    // so instead validate hex characters directly with a lightweight check.
    // (Keeps code small while staying strict.)
    if (!/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/.test(s)) {
        throw new Error(`Invalid GUID format: ${guid}`);
    }
    return out;
}
class Guid {
    constructor(bytes) {
        if (bytes.length !== 16)
            throw new Error("GUID must be exactly 16 bytes");
        this.bytes = bytes;
    }
    static fromString(guid) {
        return new Guid(parseWindowsGuid(guid));
    }
    /**
     * Convert Windows / CFBF byte order into canonical GUID string:
     * xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
     */
    toString() {
        const b = this.bytes;
        const hx = (n) => n.toString(16).padStart(2, "0");
        // Data1 (uint32 LE) -> big-endian text
        const g1 = hx(b[3]) + hx(b[2]) + hx(b[1]) + hx(b[0]);
        // Data2 (uint16 LE)
        const g2 = hx(b[5]) + hx(b[4]);
        // Data3 (uint16 LE)
        const g3 = hx(b[7]) + hx(b[6]);
        // Data4 (as-is)
        const g4 = hx(b[8]) + hx(b[9]);
        // Data5 (as-is)
        const g5 = hx(b[10]) +
            hx(b[11]) +
            hx(b[12]) +
            hx(b[13]) +
            hx(b[14]) +
            hx(b[15]);
        return `${g1}-${g2}-${g3}-${g4}-${g5}`.toUpperCase();
    }
    /**
     * Compare against a Uint8Array containing GUID bytes
     * in Windows / CFBF layout.
     */
    equals(buf, offset = 0) {
        if (offset < 0 || buf.length - offset < 16)
            return false;
        const a = this.bytes;
        for (let i = 0; i < 16; i++) {
            if (buf[offset + i] !== a[i])
                return false;
        }
        return true;
    }
}

;// CONCATENATED MODULE: ./node_modules/music-metadata/lib/asf/AsfGuid.js

/**
 * Ref:
 * - https://tools.ietf.org/html/draft-fleischman-asf-01, Appendix A: ASF GUIDs
 * - http://drang.s4.xrea.com/program/tips/id3tag/wmp/10_asf_guids.html
 * - http://drang.s4.xrea.com/program/tips/id3tag/wmp/index.html
 * - http://drang.s4.xrea.com/program/tips/id3tag/wmp/10_asf_guids.html
 *
 * ASF File Structure:
 * - https://msdn.microsoft.com/en-us/library/windows/desktop/ee663575(v=vs.85).aspx
 *
 * ASF GUIDs:
 * - http://drang.s4.xrea.com/program/tips/id3tag/wmp/10_asf_guids.html
 * - https://github.com/dji-sdk/FFmpeg/blob/master/libavformat/asf.c
 */
class AsfGuid {
    static fromBin(bin, offset = 0) {
        return new AsfGuid(AsfGuid.decode(bin, offset));
    }
    /**
     * Decode GUID in format like "B503BF5F-2EA9-CF11-8EE3-00C00C205365"
     * @param objectId Binary GUID
     * @param offset Read offset in bytes, default 0
     * @returns GUID as dashed hexadecimal representation
     */
    static decode(objectId, offset = 0) {
        return new Guid(objectId.subarray(offset, offset + 16)).toString();
    }
    /**
     * Decode stream type
     * @param mediaType Media type GUID
     * @returns Media type
     */
    static decodeMediaType(mediaType) {
        switch (mediaType.str) {
            case AsfGuid.AudioMedia.str:
                return 'audio';
            case AsfGuid.VideoMedia.str:
                return 'video';
            case AsfGuid.CommandMedia.str:
                return 'command';
            case AsfGuid.Degradable_JPEG_Media.str:
                return 'degradable-jpeg';
            case AsfGuid.FileTransferMedia.str:
                return 'file-transfer';
            case AsfGuid.BinaryMedia.str:
                return 'binary';
        }
    }
    /**
     * Encode GUID
     * @param guid GUID like: "B503BF5F-2EA9-CF11-8EE3-00C00C205365"
     * @returns Encoded Binary GUID
     */
    static encode(guid) {
        return parseWindowsGuid(guid);
    }
    constructor(str) {
        this.str = str;
    }
    equals(guid) {
        return this.str === guid.str;
    }
    toBin() {
        return AsfGuid.encode(this.str);
    }
}
// 10.1 Top-level ASF object GUIDs
AsfGuid.HeaderObject = new AsfGuid('75B22630-668E-11CF-A6D9-00AA0062CE6C');
AsfGuid.DataObject = new AsfGuid('75B22636-668E-11CF-A6D9-00AA0062CE6C');
AsfGuid.SimpleIndexObject = new AsfGuid('33000890-E5B1-11CF-89F4-00A0C90349CB');
AsfGuid.IndexObject = new AsfGuid('D6E229D3-35DA-11D1-9034-00A0C90349BE');
AsfGuid.MediaObjectIndexObject = new AsfGuid('FEB103F8-12AD-4C64-840F-2A1D2F7AD48C');
AsfGuid.TimecodeIndexObject = new AsfGuid('3CB73FD0-0C4A-4803-953D-EDF7B6228F0C');
// 10.2 Header Object GUIDs
AsfGuid.FilePropertiesObject = new AsfGuid('8CABDCA1-A947-11CF-8EE4-00C00C205365');
AsfGuid.StreamPropertiesObject = new AsfGuid('B7DC0791-A9B7-11CF-8EE6-00C00C205365');
AsfGuid.HeaderExtensionObject = new AsfGuid('5FBF03B5-A92E-11CF-8EE3-00C00C205365');
AsfGuid.CodecListObject = new AsfGuid('86D15240-311D-11D0-A3A4-00A0C90348F6');
AsfGuid.ScriptCommandObject = new AsfGuid('1EFB1A30-0B62-11D0-A39B-00A0C90348F6');
AsfGuid.MarkerObject = new AsfGuid('F487CD01-A951-11CF-8EE6-00C00C205365');
AsfGuid.BitrateMutualExclusionObject = new AsfGuid('D6E229DC-35DA-11D1-9034-00A0C90349BE');
AsfGuid.ErrorCorrectionObject = new AsfGuid('75B22635-668E-11CF-A6D9-00AA0062CE6C');
AsfGuid.ContentDescriptionObject = new AsfGuid('75B22633-668E-11CF-A6D9-00AA0062CE6C');
AsfGuid.ExtendedContentDescriptionObject = new AsfGuid('D2D0A440-E307-11D2-97F0-00A0C95EA850');
AsfGuid.ContentBrandingObject = new AsfGuid('2211B3FA-BD23-11D2-B4B7-00A0C955FC6E');
AsfGuid.StreamBitratePropertiesObject = new AsfGuid('7BF875CE-468D-11D1-8D82-006097C9A2B2');
AsfGuid.ContentEncryptionObject = new AsfGuid('2211B3FB-BD23-11D2-B4B7-00A0C955FC6E');
AsfGuid.ExtendedContentEncryptionObject = new AsfGuid('298AE614-2622-4C17-B935-DAE07EE9289C');
AsfGuid.DigitalSignatureObject = new AsfGuid('2211B3FC-BD23-11D2-B4B7-00A0C955FC6E');
AsfGuid.PaddingObject = new AsfGuid('1806D474-CADF-4509-A4BA-9AABCB96AAE8');
// 10.3 Header Extension Object GUIDs
AsfGuid.ExtendedStreamPropertiesObject = new AsfGuid('14E6A5CB-C672-4332-8399-A96952065B5A');
AsfGuid.AdvancedMutualExclusionObject = new AsfGuid('A08649CF-4775-4670-8A16-6E35357566CD');
AsfGuid.GroupMutualExclusionObject = new AsfGuid('D1465A40-5A79-4338-B71B-E36B8FD6C249');
AsfGuid.StreamPrioritizationObject = new AsfGuid('D4FED15B-88D3-454F-81F0-ED5C45999E24');
AsfGuid.BandwidthSharingObject = new AsfGuid('A69609E6-517B-11D2-B6AF-00C04FD908E9');
AsfGuid.LanguageListObject = new AsfGuid('7C4346A9-EFE0-4BFC-B229-393EDE415C85');
AsfGuid.MetadataObject = new AsfGuid('C5F8CBEA-5BAF-4877-8467-AA8C44FA4CCA');
AsfGuid.MetadataLibraryObject = new AsfGuid('44231C94-9498-49D1-A141-1D134E457054');
AsfGuid.IndexParametersObject = new AsfGuid('D6E229DF-35DA-11D1-9034-00A0C90349BE');
AsfGuid.MediaObjectIndexParametersObject = new AsfGuid('6B203BAD-3F11-48E4-ACA8-D7613DE2CFA7');
AsfGuid.TimecodeIndexParametersObject = new AsfGuid('F55E496D-9797-4B5D-8C8B-604DFE9BFB24');
AsfGuid.CompatibilityObject = new AsfGuid('26F18B5D-4584-47EC-9F5F-0E651F0452C9');
AsfGuid.AdvancedContentEncryptionObject = new AsfGuid('43058533-6981-49E6-9B74-AD12CB86D58C');
// 10.4 Stream Properties Object Stream Type GUIDs
AsfGuid.AudioMedia = new AsfGuid('F8699E40-5B4D-11CF-A8FD-00805F5C442B');
AsfGuid.VideoMedia = new AsfGuid('BC19EFC0-5B4D-11CF-A8FD-00805F5C442B');
AsfGuid.CommandMedia = new AsfGuid('59DACFC0-59E6-11D0-A3AC-00A0C90348F6');
AsfGuid.JFIF_Media = new AsfGuid('B61BE100-5B4E-11CF-A8FD-00805F5C442B');
AsfGuid.Degradable_JPEG_Media = new AsfGuid('35907DE0-E415-11CF-A917-00805F5C442B');
AsfGuid.FileTransferMedia = new AsfGuid('91BD222C-F21C-497A-8B6D-5AA86BFC0185');
AsfGuid.BinaryMedia = new AsfGuid('3AFB65E2-47EF-40F2-AC2C-70A90D71D343');
AsfGuid.ASF_Index_Placeholder_Object = new AsfGuid('D9AADE20-7C17-4F9C-BC28-8555DD98E2A2');
/* harmony default export */ const asf_AsfGuid = (AsfGuid);

// EXTERNAL MODULE: ./node_modules/token-types/lib/index.js
var token_types_lib = __webpack_require__(43);
// EXTERNAL MODULE: ./node_modules/music-metadata/lib/common/Util.js
var Util = __webpack_require__(3791);
// EXTERNAL MODULE: ./node_modules/music-metadata/lib/id3v2/ID3v2Token.js
var ID3v2Token = __webpack_require__(5057);
// EXTERNAL MODULE: ./node_modules/music-metadata/lib/ParseError.js
var ParseError = __webpack_require__(6994);
;// CONCATENATED MODULE: ./node_modules/music-metadata/lib/asf/AsfUtil.js


function getParserForAttr(i) {
    return attributeParsers[i];
}
function parseUnicodeAttr(uint8Array) {
    return Util/* stripNulls */.F6(Util/* decodeString */.sc(uint8Array, 'utf-16le'));
}
const attributeParsers = [
    parseUnicodeAttr,
    parseByteArrayAttr,
    parseBoolAttr,
    parseDWordAttr,
    parseQWordAttr,
    parseWordAttr,
    parseByteArrayAttr
];
function parseByteArrayAttr(buf) {
    return new Uint8Array(buf);
}
function parseBoolAttr(buf, offset = 0) {
    return parseWordAttr(buf, offset) === 1;
}
function parseDWordAttr(buf, offset = 0) {
    return token_types_lib.UINT32_LE.get(buf, offset);
}
function parseQWordAttr(buf, offset = 0) {
    return token_types_lib.UINT64_LE.get(buf, offset);
}
function parseWordAttr(buf, offset = 0) {
    return token_types_lib.UINT16_LE.get(buf, offset);
}

;// CONCATENATED MODULE: ./node_modules/music-metadata/lib/asf/AsfObject.js
// ASF Objects






class AsfContentParseError extends (0,ParseError/* makeUnexpectedFileContentError */.fO)('ASF') {
}
/**
 * Data Type: Specifies the type of information being stored. The following values are recognized.
 */
const DataType = {
    /**
     * Unicode string. The data consists of a sequence of Unicode characters.
     */
    UnicodeString: 0,
    /**
     * BYTE array. The type of data is implementation-specific.
     */
    ByteArray: 1,
    /**
     * BOOL. The data is 2 bytes long and should be interpreted as a 16-bit unsigned integer. Only 0x0000 or 0x0001 are permitted values.
     */
    Bool: 2,
    /**
     * DWORD. The data is 4 bytes long and should be interpreted as a 32-bit unsigned integer.
     */
    DWord: 3,
    /**
     * QWORD. The data is 8 bytes long and should be interpreted as a 64-bit unsigned integer.
     */
    QWord: 4,
    /**
     * WORD. The data is 2 bytes long and should be interpreted as a 16-bit unsigned integer.
     */
    Word: 5
};
/**
 * Token for: 3. ASF top-level Header Object
 * Ref: http://drang.s4.xrea.com/program/tips/id3tag/wmp/03_asf_top_level_header_object.html#3
 */
const TopLevelHeaderObjectToken = {
    len: 30,
    get: (buf, off) => {
        const base = HeaderObjectToken.get(buf, off);
        if (base.objectSize < TopLevelHeaderObjectToken.len) {
            throw new AsfContentParseError(`Invalid ASF top-level header object size: ${base.objectSize}`);
        }
        return {
            ...base,
            numberOfHeaderObjects: token_types_lib.UINT32_LE.get(buf, off + 24)
            // reserved: 2 bytes
        };
    }
};
/**
 * Token for: 3.1 Header Object (mandatory, one only)
 * Ref: http://drang.s4.xrea.com/program/tips/id3tag/wmp/03_asf_top_level_header_object.html#3_1
 */
const HeaderObjectToken = {
    len: 24,
    get: (buf, off) => {
        const objectSize = token_types_lib.UINT64_LE.get(buf, off + 16);
        if (objectSize < BigInt(HeaderObjectToken.len) || objectSize > BigInt(Number.MAX_SAFE_INTEGER)) {
            throw new AsfContentParseError(`Invalid ASF header object size: ${objectSize}`);
        }
        const header = {
            objectId: asf_AsfGuid.fromBin(buf, off),
            objectSize: Number(objectSize)
        };
        return header;
    }
};
class State {
    constructor(header) {
        this.len = header.objectSize - HeaderObjectToken.len;
    }
    postProcessTag(tags, name, valueType, data) {
        if (name === 'WM/Picture') {
            tags.push({ id: name, value: WmPictureToken.fromBuffer(data) });
        }
        else {
            const parseAttr = getParserForAttr(valueType);
            if (!parseAttr) {
                throw new AsfContentParseError(`unexpected value headerType: ${valueType}`);
            }
            tags.push({ id: name, value: parseAttr(data) });
        }
    }
}
/**
 * Token for: 3.2: File Properties Object (mandatory, one only)
 * Ref: http://drang.s4.xrea.com/program/tips/id3tag/wmp/03_asf_top_level_header_object.html#3_2
 */
class FilePropertiesObject extends State {
    get(buf, off) {
        return {
            fileId: asf_AsfGuid.fromBin(buf, off),
            fileSize: token_types_lib.UINT64_LE.get(buf, off + 16),
            creationDate: token_types_lib.UINT64_LE.get(buf, off + 24),
            dataPacketsCount: token_types_lib.UINT64_LE.get(buf, off + 32),
            playDuration: token_types_lib.UINT64_LE.get(buf, off + 40),
            sendDuration: token_types_lib.UINT64_LE.get(buf, off + 48),
            preroll: token_types_lib.UINT64_LE.get(buf, off + 56),
            flags: {
                broadcast: Util/* getBit */.mh(buf, off + 64, 24),
                seekable: Util/* getBit */.mh(buf, off + 64, 25)
            },
            // flagsNumeric: Token.UINT32_LE.get(buf, off + 64),
            minimumDataPacketSize: token_types_lib.UINT32_LE.get(buf, off + 68),
            maximumDataPacketSize: token_types_lib.UINT32_LE.get(buf, off + 72),
            maximumBitrate: token_types_lib.UINT32_LE.get(buf, off + 76)
        };
    }
}
FilePropertiesObject.guid = asf_AsfGuid.FilePropertiesObject;
/**
 * Token for: 3.3 Stream Properties Object (mandatory, one per stream)
 * Ref: http://drang.s4.xrea.com/program/tips/id3tag/wmp/03_asf_top_level_header_object.html#3_3
 */
class StreamPropertiesObject extends State {
    get(buf, off) {
        return {
            streamType: asf_AsfGuid.decodeMediaType(asf_AsfGuid.fromBin(buf, off)),
            errorCorrectionType: asf_AsfGuid.fromBin(buf, off + 8)
            // ToDo
        };
    }
}
StreamPropertiesObject.guid = asf_AsfGuid.StreamPropertiesObject;
/**
 * 3.4: Header Extension Object (mandatory, one only)
 * Ref: http://drang.s4.xrea.com/program/tips/id3tag/wmp/03_asf_top_level_header_object.html#3_4
 */
class HeaderExtensionObject {
    constructor() {
        this.len = 22;
    }
    get(buf, off) {
        const view = new DataView(buf.buffer, off);
        return {
            reserved1: asf_AsfGuid.fromBin(buf, off),
            reserved2: view.getUint16(16, true),
            extensionDataSize: view.getUint32(18, true)
        };
    }
}
HeaderExtensionObject.guid = asf_AsfGuid.HeaderExtensionObject;
/**
 * 3.5: The Codec List Object provides user-friendly information about the codecs and formats used to encode the content found in the ASF file.
 * Ref: http://drang.s4.xrea.com/program/tips/id3tag/wmp/03_asf_top_level_header_object.html#3_5
 */
const CodecListObjectHeader = {
    len: 20,
    get: (buf, off) => {
        const view = new DataView(buf.buffer, off);
        return {
            entryCount: view.getUint16(16, true)
        };
    }
};
class BoundedReader {
    constructor(tokenizer, size) {
        this.tokenizer = tokenizer;
        this.remaining = size;
    }
    async readToken(token) {
        this.ensureAvailable(token.len);
        const value = await this.tokenizer.readToken(token);
        this.remaining -= token.len;
        return value;
    }
    async readNumber(token) {
        return this.readToken(token);
    }
    async readBuffer(length) {
        this.ensureAvailable(length);
        const buffer = new Uint8Array(length);
        await this.tokenizer.readBuffer(buffer);
        this.remaining -= length;
        return buffer;
    }
    async ignoreRemaining() {
        const ignored = await this.tokenizer.ignore(this.remaining);
        if (ignored !== this.remaining) {
            throw new AsfContentParseError(`Unexpected end of ASF Codec List Object; missing ${this.remaining - ignored} bytes`);
        }
        this.remaining = 0;
    }
    ensureAvailable(length) {
        if (length > this.remaining) {
            throw new AsfContentParseError(`ASF Codec List field size ${length} exceeds remaining object payload size ${this.remaining}`);
        }
    }
}
async function readString(reader) {
    const length = await reader.readNumber(token_types_lib.UINT16_LE);
    return (await reader.readToken(new token_types_lib.StringType(length * 2, 'utf-16le'))).replace('\0', '');
}
/**
 * 3.5: Read the Codec-List-Object, which provides user-friendly information about the codecs and formats used to encode the content found in the ASF file.
 * Ref: http://drang.s4.xrea.com/program/tips/id3tag/wmp/03_asf_top_level_header_object.html#3_5
 */
async function readCodecEntries(tokenizer, payloadSize) {
    const reader = new BoundedReader(tokenizer, payloadSize);
    const codecHeader = await reader.readToken(CodecListObjectHeader);
    const entries = [];
    for (let i = 0; i < codecHeader.entryCount; ++i) {
        entries.push(await readCodecEntry(reader));
    }
    await reader.ignoreRemaining();
    return entries;
}
async function readInformation(reader) {
    const length = await reader.readNumber(token_types_lib.UINT16_LE);
    return reader.readBuffer(length);
}
/**
 * Read Codec-Entries
 * @param reader
 */
async function readCodecEntry(reader) {
    const type = await reader.readNumber(token_types_lib.UINT16_LE);
    return {
        type: {
            videoCodec: (type & 0x0001) === 0x0001,
            audioCodec: (type & 0x0002) === 0x0002
        },
        codecName: await readString(reader),
        description: await readString(reader),
        information: await readInformation(reader)
    };
}
/**
 * 3.10 Content Description Object (optional, one only)
 * Ref: http://drang.s4.xrea.com/program/tips/id3tag/wmp/03_asf_top_level_header_object.html#3_10
 */
class ContentDescriptionObjectState extends State {
    get(buf, off) {
        const tags = [];
        const view = new DataView(buf.buffer, off);
        let pos = 10;
        for (let i = 0; i < ContentDescriptionObjectState.contentDescTags.length; ++i) {
            const length = view.getUint16(i * 2, true);
            if (length > 0) {
                const tagName = ContentDescriptionObjectState.contentDescTags[i];
                const end = pos + length;
                tags.push({ id: tagName, value: parseUnicodeAttr(buf.subarray(off + pos, off + end)) });
                pos = end;
            }
        }
        return tags;
    }
}
ContentDescriptionObjectState.guid = asf_AsfGuid.ContentDescriptionObject;
ContentDescriptionObjectState.contentDescTags = ['Title', 'Author', 'Copyright', 'Description', 'Rating'];
/**
 * 3.11 Extended Content Description Object (optional, one only)
 * Ref: http://drang.s4.xrea.com/program/tips/id3tag/wmp/03_asf_top_level_header_object.html#3_11
 */
class ExtendedContentDescriptionObjectState extends State {
    get(buf, off) {
        const tags = [];
        const view = new DataView(buf.buffer, off);
        const attrCount = view.getUint16(0, true);
        let pos = 2;
        for (let i = 0; i < attrCount; i += 1) {
            const nameLen = view.getUint16(pos, true);
            pos += 2;
            const name = parseUnicodeAttr(buf.subarray(off + pos, off + pos + nameLen));
            pos += nameLen;
            const valueType = view.getUint16(pos, true);
            pos += 2;
            const valueLen = view.getUint16(pos, true);
            pos += 2;
            const value = buf.subarray(off + pos, off + pos + valueLen);
            pos += valueLen;
            this.postProcessTag(tags, name, valueType, value);
        }
        return tags;
    }
}
ExtendedContentDescriptionObjectState.guid = asf_AsfGuid.ExtendedContentDescriptionObject;
/**
 * 4.1 Extended Stream Properties Object (optional, 1 per media stream)
 * Ref: http://drang.s4.xrea.com/program/tips/id3tag/wmp/04_objects_in_the_asf_header_extension_object.html#4_1
 */
class ExtendedStreamPropertiesObjectState extends State {
    get(buf, off) {
        const view = new DataView(buf.buffer, off);
        return {
            startTime: token_types_lib.UINT64_LE.get(buf, off),
            endTime: token_types_lib.UINT64_LE.get(buf, off + 8),
            dataBitrate: view.getInt32(12, true),
            bufferSize: view.getInt32(16, true),
            initialBufferFullness: view.getInt32(20, true),
            alternateDataBitrate: view.getInt32(24, true),
            alternateBufferSize: view.getInt32(28, true),
            alternateInitialBufferFullness: view.getInt32(32, true),
            maximumObjectSize: view.getInt32(36, true),
            flags: {
                // ToDo, check flag positions
                reliableFlag: Util/* getBit */.mh(buf, off + 40, 0),
                seekableFlag: Util/* getBit */.mh(buf, off + 40, 1),
                resendLiveCleanpointsFlag: Util/* getBit */.mh(buf, off + 40, 2)
            },
            // flagsNumeric: Token.UINT32_LE.get(buf, off + 64),
            streamNumber: view.getInt16(42, true),
            streamLanguageId: view.getInt16(44, true),
            averageTimePerFrame: view.getInt32(52, true),
            streamNameCount: view.getInt32(54, true),
            payloadExtensionSystems: view.getInt32(56, true),
            streamNames: [], // ToDo
            streamPropertiesObject: null
        };
    }
}
ExtendedStreamPropertiesObjectState.guid = asf_AsfGuid.ExtendedStreamPropertiesObject;
/**
 * 4.7  Metadata Object (optional, 0 or 1)
 * Ref: http://drang.s4.xrea.com/program/tips/id3tag/wmp/04_objects_in_the_asf_header_extension_object.html#4_7
 */
class MetadataObjectState extends State {
    get(uint8Array, off) {
        const tags = [];
        const view = new DataView(uint8Array.buffer, off);
        const descriptionRecordsCount = view.getUint16(0, true);
        let pos = 2;
        for (let i = 0; i < descriptionRecordsCount; i += 1) {
            pos += 4;
            const nameLen = view.getUint16(pos, true);
            pos += 2;
            const dataType = view.getUint16(pos, true);
            pos += 2;
            const dataLen = view.getUint32(pos, true);
            pos += 4;
            const name = parseUnicodeAttr(uint8Array.subarray(off + pos, off + pos + nameLen));
            pos += nameLen;
            const data = uint8Array.subarray(off + pos, off + pos + dataLen);
            pos += dataLen;
            this.postProcessTag(tags, name, dataType, data);
        }
        return tags;
    }
}
MetadataObjectState.guid = asf_AsfGuid.MetadataObject;
// 4.8	Metadata Library Object (optional, 0 or 1)
class MetadataLibraryObjectState extends MetadataObjectState {
}
MetadataLibraryObjectState.guid = asf_AsfGuid.MetadataLibraryObject;
/**
 * Ref: https://msdn.microsoft.com/en-us/library/windows/desktop/dd757977(v=vs.85).aspx
 */
class WmPictureToken {
    static fromBuffer(buffer) {
        const pic = new WmPictureToken(buffer.length);
        return pic.get(buffer, 0);
    }
    constructor(len) {
        this.len = len;
    }
    get(buffer, offset) {
        const view = new DataView(buffer.buffer, offset);
        const typeId = view.getUint8(0);
        const size = view.getInt32(1, true);
        let index = 5;
        while (view.getUint16(index) !== 0) {
            index += 2;
        }
        const format = new token_types_lib.StringType(index - 5, 'utf-16le').get(buffer, 5);
        while (view.getUint16(index) !== 0) {
            index += 2;
        }
        const description = new token_types_lib.StringType(index - 5, 'utf-16le').get(buffer, 5);
        return {
            type: ID3v2Token/* AttachedPictureType */.n5[typeId],
            format,
            description,
            size,
            data: buffer.slice(index + 4)
        };
    }
}

;// CONCATENATED MODULE: ./node_modules/music-metadata/lib/asf/AsfParser.js







const debug = src('music-metadata:parser:ASF');
const headerType = 'asf';
const maxAsfMetadataObjectSize = 16 * 1024 * 1024;
/**
 * Windows Media Metadata Usage Guidelines
 * - Ref: https://msdn.microsoft.com/en-us/library/ms867702.aspx
 *
 * Ref:
 * - https://tools.ietf.org/html/draft-fleischman-asf-01
 * - https://hwiegman.home.xs4all.nl/fileformats/asf/ASF_Specification.pdf
 * - http://drang.s4.xrea.com/program/tips/id3tag/wmp/index.html
 * - https://msdn.microsoft.com/en-us/library/windows/desktop/ee663575(v=vs.85).aspx
 */
class AsfParser extends BasicParser/* BasicParser */.s {
    async parse() {
        const header = await this.tokenizer.readToken(TopLevelHeaderObjectToken);
        if (!header.objectId.equals(asf_AsfGuid.HeaderObject)) {
            throw new AsfContentParseError(`expected asf header; but was not found; got: ${header.objectId.str}`);
        }
        if (header.numberOfHeaderObjects < 1 || header.numberOfHeaderObjects > 10000) {
            throw new AsfContentParseError(`Unrealistic number of ASF header objects: ${header.numberOfHeaderObjects}`);
        }
        await this.parseObjectHeaders(header.numberOfHeaderObjects, header.objectSize - TopLevelHeaderObjectToken.len);
    }
    async parseObjectHeaders(numberOfObjectHeaders, remainingHeaderSize) {
        let tags;
        for (let index = 0; index < numberOfObjectHeaders; ++index) {
            const { header, payloadSize } = await this.readObjectHeader(remainingHeaderSize, 'header');
            // Parse data part of the ASF Object
            debug('header GUID=%s', header.objectId.str);
            switch (header.objectId.str) {
                case FilePropertiesObject.guid.str: {
                    // 3.2
                    this.validateAllocationSize(payloadSize, 'File Properties Object');
                    const fpo = await this.tokenizer.readToken(new FilePropertiesObject(header));
                    this.metadata.setFormat('duration', Number(fpo.playDuration / BigInt(1000)) / 10000 - Number(fpo.preroll) / 1000);
                    this.metadata.setFormat('bitrate', fpo.maximumBitrate);
                    break;
                }
                case StreamPropertiesObject.guid.str: {
                    // 3.3
                    this.validateAllocationSize(payloadSize, 'Stream Properties Object');
                    const spo = await this.tokenizer.readToken(new StreamPropertiesObject(header));
                    this.metadata.setFormat('container', `ASF/${spo.streamType}`);
                    break;
                }
                case HeaderExtensionObject.guid.str: {
                    // 3.4
                    const extensionHeaderToken = new HeaderExtensionObject();
                    if (payloadSize < extensionHeaderToken.len) {
                        throw new AsfContentParseError(`ASF Header Extension Object payload is too small: ${payloadSize} bytes`);
                    }
                    const extHeader = await this.tokenizer.readToken(extensionHeaderToken);
                    const expectedExtensionSize = payloadSize - extensionHeaderToken.len;
                    if (extHeader.extensionDataSize !== expectedExtensionSize) {
                        throw new AsfContentParseError(`ASF extension data size ${extHeader.extensionDataSize} does not match enclosing payload size ${expectedExtensionSize}`);
                    }
                    await this.parseExtensionObject(extHeader.extensionDataSize);
                    break;
                }
                case ContentDescriptionObjectState.guid.str: // 3.10
                    this.validateAllocationSize(payloadSize, 'Content Description Object');
                    tags = await this.tokenizer.readToken(new ContentDescriptionObjectState(header));
                    await this.addTags(tags);
                    break;
                case ExtendedContentDescriptionObjectState.guid.str: // 3.11
                    this.validateAllocationSize(payloadSize, 'Extended Content Description Object');
                    tags = await this.tokenizer.readToken(new ExtendedContentDescriptionObjectState(header));
                    await this.addTags(tags);
                    break;
                case asf_AsfGuid.CodecListObject.str: {
                    this.validateAllocationSize(payloadSize, 'Codec List Object');
                    let codecs;
                    try {
                        codecs = await readCodecEntries(this.tokenizer, payloadSize);
                    }
                    catch (error) {
                        const message = error instanceof Error ? error.message : String(error);
                        throw new AsfContentParseError(`Invalid ASF Codec List Object: ${message}`);
                    }
                    codecs.forEach(codec => {
                        this.metadata.addStreamInfo({
                            type: codec.type.videoCodec ? types/* TrackType */.S.video : types/* TrackType */.S.audio,
                            codecName: codec.codecName
                        });
                    });
                    const audioCodecs = codecs
                        .filter(codec => codec.type.audioCodec)
                        .map(codec => codec.codecName)
                        .join('/');
                    this.metadata.setFormat('codec', audioCodecs);
                    break;
                }
                case asf_AsfGuid.StreamBitratePropertiesObject.str:
                    // ToDo?
                    await this.ignorePayload(payloadSize, 'Stream Bitrate Properties Object');
                    break;
                case asf_AsfGuid.PaddingObject.str:
                    // ToDo: register bytes pad
                    debug('Padding: %s bytes', payloadSize);
                    await this.ignorePayload(payloadSize, 'Padding Object');
                    break;
                default:
                    this.metadata.addWarning(`Ignore ASF-Object-GUID: ${header.objectId.str}`);
                    debug('Ignore ASF-Object-GUID: %s', header.objectId.str);
                    await this.ignorePayload(payloadSize, `object ${header.objectId.str}`);
            }
            remainingHeaderSize -= header.objectSize;
        }
        if (remainingHeaderSize !== 0) {
            throw new AsfContentParseError(`ASF header child objects leave ${remainingHeaderSize} payload byte(s) unaccounted`);
        }
    }
    async addTags(tags) {
        await Promise.all(tags.map(({ id, value }) => this.metadata.addTag(headerType, id, value)));
    }
    async readObjectHeader(remainingSize, containerType) {
        if (remainingSize < HeaderObjectToken.len) {
            throw new AsfContentParseError(`Insufficient ASF ${containerType} data for an object header: ${remainingSize} bytes`);
        }
        const header = await this.tokenizer.readToken(HeaderObjectToken);
        if (header.objectSize > remainingSize) {
            throw new AsfContentParseError(`ASF ${containerType} object size ${header.objectSize} exceeds remaining ${containerType} payload size ${remainingSize}`);
        }
        return {
            header,
            payloadSize: this.validatePayloadAvailability(header, `${containerType} object`)
        };
    }
    validatePayloadAvailability(header, objectType) {
        const payloadSize = header.objectSize - HeaderObjectToken.len;
        const fileSize = this.tokenizer.fileInfo.size;
        if (fileSize !== undefined) {
            const available = fileSize - this.tokenizer.position;
            if (payloadSize > available) {
                throw new AsfContentParseError(`ASF ${objectType} payload size ${payloadSize} exceeds available input size ${available}`);
            }
        }
        return payloadSize;
    }
    validateAllocationSize(payloadSize, objectType) {
        if (payloadSize > maxAsfMetadataObjectSize) {
            throw new AsfContentParseError(`ASF ${objectType} payload size ${payloadSize} exceeds allocation limit ${maxAsfMetadataObjectSize}`);
        }
    }
    async ignorePayload(payloadSize, objectType) {
        let ignored;
        try {
            ignored = await this.tokenizer.ignore(payloadSize);
        }
        catch (error) {
            if (error instanceof lib/* EndOfStreamError */.d1) {
                throw new AsfContentParseError(`Unexpected end of ASF ${objectType}`);
            }
            throw error;
        }
        if (ignored !== payloadSize) {
            throw new AsfContentParseError(`Unexpected end of ASF ${objectType}; missing ${payloadSize - ignored} bytes`);
        }
    }
    async parseExtensionObject(extensionSize) {
        while (extensionSize > 0) {
            const { header, payloadSize } = await this.readObjectHeader(extensionSize, 'extension');
            // Parse data part of the ASF Object
            switch (header.objectId.str) {
                case ExtendedStreamPropertiesObjectState.guid.str: // 4.1
                    // ToDo: extended stream header properties are ignored
                    this.validateAllocationSize(payloadSize, 'Extended Stream Properties Object');
                    await this.tokenizer.readToken(new ExtendedStreamPropertiesObjectState(header));
                    break;
                case MetadataObjectState.guid.str: {
                    // 4.7
                    this.validateAllocationSize(payloadSize, 'Metadata Object');
                    const moTags = await this.tokenizer.readToken(new MetadataObjectState(header));
                    await this.addTags(moTags);
                    break;
                }
                case MetadataLibraryObjectState.guid.str: {
                    // 4.8
                    this.validateAllocationSize(payloadSize, 'Metadata Library Object');
                    const mlTags = await this.tokenizer.readToken(new MetadataLibraryObjectState(header));
                    await this.addTags(mlTags);
                    break;
                }
                case asf_AsfGuid.PaddingObject.str:
                    // ToDo: register bytes pad
                    await this.ignorePayload(payloadSize, 'extension Padding Object');
                    break;
                case asf_AsfGuid.CompatibilityObject.str:
                    await this.ignorePayload(payloadSize, 'Compatibility Object');
                    break;
                case asf_AsfGuid.ASF_Index_Placeholder_Object.str:
                    await this.ignorePayload(payloadSize, 'Index Placeholder Object');
                    break;
                default:
                    this.metadata.addWarning(`Ignore ASF-Object-GUID: ${header.objectId.str}`);
                    // console.log("Ignore ASF-Object-GUID: %s", header.objectId.str);
                    await this.ignorePayload(payloadSize, `extension object ${header.objectId.str}`);
                    break;
            }
            extensionSize -= header.objectSize;
        }
    }
}


/***/ })

};
;