"use strict";
exports.id = 457;
exports.ids = [457];
exports.modules = {

/***/ 3457:
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

// ESM COMPAT FLAG
__webpack_require__.r(__webpack_exports__);

// EXPORTS
__webpack_require__.d(__webpack_exports__, {
  MP4Parser: () => (/* binding */ MP4Parser)
});

// EXTERNAL MODULE: ./bridge/node_modules/@borewit/text-codec/lib/index.js
var lib = __webpack_require__(1777);
// EXTERNAL MODULE: ./bridge/node_modules/debug/src/index.js
var src = __webpack_require__(5721);
// EXTERNAL MODULE: ./bridge/node_modules/token-types/lib/index.js
var token_types_lib = __webpack_require__(9431);
// EXTERNAL MODULE: ./bridge/node_modules/uint8array-extras/index.js
var uint8array_extras = __webpack_require__(6117);
// EXTERNAL MODULE: ./bridge/node_modules/music-metadata/lib/common/BasicParser.js
var BasicParser = __webpack_require__(5258);
// EXTERNAL MODULE: ./bridge/node_modules/music-metadata/lib/id3v1/ID3v1Parser.js
var ID3v1Parser = __webpack_require__(781);
// EXTERNAL MODULE: ./bridge/node_modules/music-metadata/lib/matroska/types.js
var types = __webpack_require__(4233);
// EXTERNAL MODULE: ./bridge/node_modules/music-metadata/lib/common/FourCC.js
var FourCC = __webpack_require__(677);
// EXTERNAL MODULE: ./bridge/node_modules/music-metadata/lib/common/Util.js
var Util = __webpack_require__(7659);
// EXTERNAL MODULE: ./bridge/node_modules/music-metadata/lib/ParseError.js
var ParseError = __webpack_require__(4326);
;// CONCATENATED MODULE: ./bridge/node_modules/music-metadata/lib/mp4/AtomToken.js





const debug = src('music-metadata:parser:MP4:atom');
class Mp4ContentError extends (0,ParseError/* makeUnexpectedFileContentError */.fO)('MP4') {
}
const Header = {
    len: 8,
    get: (buf, off) => {
        const length = token_types_lib.UINT32_BE.get(buf, off);
        if (length < 0) {
            throw new Mp4ContentError('Invalid atom header length');
        }
        return {
            length: BigInt(length),
            name: new token_types_lib.StringType(4, 'latin1').get(buf, off + 4)
        };
    },
    put: (buf, off, hdr) => {
        token_types_lib.UINT32_BE.put(buf, off, Number(hdr.length));
        return FourCC/* FourCcToken */.e.put(buf, off + 4, hdr.name);
    }
};
/**
 * Ref: https://developer.apple.com/library/archive/documentation/QuickTime/QTFF/QTFFChap1/qtff1.html#//apple_ref/doc/uid/TP40000939-CH203-38190
 */
const ExtendedSize = token_types_lib.UINT64_BE;
const ftyp = {
    len: 4,
    get: (buf, off) => {
        return {
            type: new token_types_lib.StringType(4, 'ascii').get(buf, off)
        };
    }
};
/**
 * Token: Movie Header Atom
 */
const mhdr = {
    len: 8,
    get: (buf, off) => {
        return {
            version: token_types_lib.UINT8.get(buf, off),
            flags: token_types_lib.UINT24_BE.get(buf, off + 1),
            nextItemID: token_types_lib.UINT32_BE.get(buf, off + 4)
        };
    }
};
/**
 * Base class for 'fixed' length atoms.
 * In some cases these atoms are longer then the sum of the described fields.
 * Issue: https://github.com/Borewit/music-metadata/issues/120
 */
class FixedLengthAtom {
    /**
     *
     * @param {number} len Length as specified in the size field
     * @param {number} expLen Total length of sum of specified fields in the standard
     * @param atomId Atom ID
     */
    constructor(len, expLen, atomId) {
        if (len < expLen) {
            throw new Mp4ContentError(`Atom ${atomId} expected to be ${expLen}, but specifies ${len} bytes long.`);
        }
        if (len > expLen) {
            debug(`Warning: atom ${atomId} expected to be ${expLen}, but was actually ${len} bytes long.`);
        }
        this.len = len;
    }
}
/**
 * Timestamp stored in seconds since Mac Epoch (1 January 1904)
 */
const SecondsSinceMacEpoch = {
    len: 4,
    get: (buf, off) => {
        const secondsSinceUnixEpoch = token_types_lib.UINT32_BE.get(buf, off) - 2082844800;
        return new Date(secondsSinceUnixEpoch * 1000);
    }
};
const SecondsSinceMacEpoch64 = {
    len: 8,
    get: (buf, off) => {
        const secondsSinceUnixEpoch = Number(token_types_lib.UINT64_BE.get(buf, off)) - 2082844800;
        return new Date(secondsSinceUnixEpoch * 1000);
    }
};
/**
 * Token: Media Header Atom
 * Ref:
 * - https://developer.apple.com/library/archive/documentation/QuickTime/QTFF/QTFFChap2/qtff2.html#//apple_ref/doc/uid/TP40000939-CH204-SW34
 * - https://wiki.multimedia.cx/index.php/QuickTime_container#mdhd
 */
class MdhdAtom extends FixedLengthAtom {
    constructor(len) {
        super(len, 24, 'mdhd');
    }
    get(buf, off) {
        const version = token_types_lib.UINT8.get(buf, off + 0);
        const flags = token_types_lib.UINT24_BE.get(buf, off + 1);
        switch (version) {
            case 0:
                // Version 0: 32-bit fields
                return {
                    version,
                    flags,
                    creationTime: SecondsSinceMacEpoch.get(buf, off + 4),
                    modificationTime: SecondsSinceMacEpoch.get(buf, off + 8),
                    timeScale: token_types_lib.UINT32_BE.get(buf, off + 12),
                    duration: token_types_lib.UINT32_BE.get(buf, off + 16),
                    language: token_types_lib.UINT16_BE.get(buf, off + 20),
                    quality: token_types_lib.UINT16_BE.get(buf, off + 22)
                };
            case 1:
                return {
                    version,
                    flags,
                    creationTime: SecondsSinceMacEpoch64.get(buf, off + 4),
                    modificationTime: SecondsSinceMacEpoch64.get(buf, off + 12),
                    timeScale: token_types_lib.UINT32_BE.get(buf, off + 20),
                    duration: Number(token_types_lib.UINT64_BE.get(buf, off + 24)),
                    language: token_types_lib.UINT16_BE.get(buf, off + 32),
                    quality: token_types_lib.UINT16_BE.get(buf, off + 34)
                };
            default:
                throw new ParseError/* FieldDecodingError */.gP('Invalid mdhd version header');
        }
    }
}
/**
 * Token: Movie Header Atom
 */
class MvhdAtom extends FixedLengthAtom {
    constructor(len) {
        super(len, 100, 'mvhd');
    }
    get(buf, off) {
        const version = token_types_lib.UINT8.get(buf, off);
        const flags = token_types_lib.UINT24_BE.get(buf, off + 1);
        if (version === 1) {
            // Version 1: 64-bit creation/modification times and duration
            return {
                version,
                flags,
                creationTime: SecondsSinceMacEpoch64.get(buf, off + 4),
                modificationTime: SecondsSinceMacEpoch64.get(buf, off + 12),
                timeScale: token_types_lib.UINT32_BE.get(buf, off + 20),
                duration: Number(token_types_lib.UINT64_BE.get(buf, off + 24)),
                preferredRate: token_types_lib.UINT32_BE.get(buf, off + 32),
                preferredVolume: token_types_lib.UINT16_BE.get(buf, off + 36),
                // ignore reserved: 10 bytes
                // ignore matrix structure: 36 bytes
                previewTime: token_types_lib.UINT32_BE.get(buf, off + 84),
                previewDuration: token_types_lib.UINT32_BE.get(buf, off + 88),
                posterTime: token_types_lib.UINT32_BE.get(buf, off + 92),
                selectionTime: token_types_lib.UINT32_BE.get(buf, off + 96),
                selectionDuration: token_types_lib.UINT32_BE.get(buf, off + 100),
                currentTime: token_types_lib.UINT32_BE.get(buf, off + 104),
                nextTrackID: token_types_lib.UINT32_BE.get(buf, off + 108)
            };
        }
        // Version 0: 32-bit fields
        return {
            version,
            flags,
            creationTime: SecondsSinceMacEpoch.get(buf, off + 4),
            modificationTime: SecondsSinceMacEpoch.get(buf, off + 8),
            timeScale: token_types_lib.UINT32_BE.get(buf, off + 12),
            duration: token_types_lib.UINT32_BE.get(buf, off + 16),
            preferredRate: token_types_lib.UINT32_BE.get(buf, off + 20),
            preferredVolume: token_types_lib.UINT16_BE.get(buf, off + 24),
            // ignore reserved: 10 bytes
            // ignore matrix structure: 36 bytes
            previewTime: token_types_lib.UINT32_BE.get(buf, off + 72),
            previewDuration: token_types_lib.UINT32_BE.get(buf, off + 76),
            posterTime: token_types_lib.UINT32_BE.get(buf, off + 80),
            selectionTime: token_types_lib.UINT32_BE.get(buf, off + 84),
            selectionDuration: token_types_lib.UINT32_BE.get(buf, off + 88),
            currentTime: token_types_lib.UINT32_BE.get(buf, off + 92),
            nextTrackID: token_types_lib.UINT32_BE.get(buf, off + 96)
        };
    }
}
/**
 * Data Atom Structure
 */
class DataAtom {
    constructor(len) {
        this.len = len;
    }
    get(buf, off) {
        return {
            type: {
                set: token_types_lib.UINT8.get(buf, off + 0),
                type: token_types_lib.UINT24_BE.get(buf, off + 1)
            },
            locale: token_types_lib.UINT24_BE.get(buf, off + 4),
            value: new token_types_lib.Uint8ArrayType(this.len - 8).get(buf, off + 8)
        };
    }
}
/**
 * Data Atom Structure
 * Ref: https://developer.apple.com/library/content/documentation/QuickTime/QTFF/Metadata/Metadata.html#//apple_ref/doc/uid/TP40000939-CH1-SW31
 */
class NameAtom {
    constructor(len) {
        this.len = len;
    }
    get(buf, off) {
        return {
            version: token_types_lib.UINT8.get(buf, off),
            flags: token_types_lib.UINT24_BE.get(buf, off + 1),
            name: new token_types_lib.StringType(this.len - 4, 'utf-8').get(buf, off + 4)
        };
    }
}
/**
 * Track Header Atoms structure (`tkhd`)
 * Ref: https://developer.apple.com/library/content/documentation/QuickTime/QTFF/QTFFChap2/qtff2.html#//apple_ref/doc/uid/TP40000939-CH204-25550
 */
class TrackHeaderAtom {
    constructor(len) {
        this.len = len;
    }
    get(buf, off) {
        const available = Math.min(this.len, buf.length - off);
        if (available < 4) {
            throw new Mp4ContentError('Truncated tkhd header');
        }
        const version = token_types_lib.UINT8.get(buf, off);
        // Validate through the volume field, the last field currently decoded.
        if ((version === 0 && available < 38) || (version === 1 && available < 50)) {
            throw new Mp4ContentError('Truncated tkhd header');
        }
        const flags = token_types_lib.UINT24_BE.get(buf, off + 1);
        switch (version) {
            case 0:
                // Version 0: 32-bit fields
                return {
                    version,
                    flags,
                    creationTime: SecondsSinceMacEpoch.get(buf, off + 4),
                    modificationTime: SecondsSinceMacEpoch.get(buf, off + 8),
                    trackId: token_types_lib.UINT32_BE.get(buf, off + 12),
                    // reserved 4 bytes
                    duration: token_types_lib.UINT32_BE.get(buf, off + 20),
                    // reserved 8 bytes
                    layer: token_types_lib.UINT16_BE.get(buf, off + 32),
                    alternateGroup: token_types_lib.UINT16_BE.get(buf, off + 34),
                    volume: token_types_lib.UINT16_BE.get(buf, off + 36) // ToDo: fixed point
                    // ToDo: add remaining fields
                };
            case 1:
                // Version 1: 64-bit creation/modification times and duration
                return {
                    version,
                    flags,
                    creationTime: SecondsSinceMacEpoch64.get(buf, off + 4),
                    modificationTime: SecondsSinceMacEpoch64.get(buf, off + 12),
                    trackId: token_types_lib.UINT32_BE.get(buf, off + 20),
                    // reserved 4 bytes
                    duration: Number(token_types_lib.UINT64_BE.get(buf, off + 28)),
                    // reserved 8 bytes
                    layer: token_types_lib.UINT16_BE.get(buf, off + 44),
                    alternateGroup: token_types_lib.UINT16_BE.get(buf, off + 46),
                    volume: token_types_lib.UINT16_BE.get(buf, off + 48) // ToDo: fixed point
                    // ToDo: add remaining fields
                };
            default:
                throw new ParseError/* FieldDecodingError */.gP('Invalid tkhd version header');
        }
    }
}
/**
 * Atom: Sample Description Atom ('stsd')
 * Ref: https://developer.apple.com/library/archive/documentation/QuickTime/QTFF/QTFFChap2/qtff2.html#//apple_ref/doc/uid/TP40000939-CH204-25691
 */
const stsdHeader = {
    len: 8,
    get: (buf, off) => {
        return {
            version: token_types_lib.UINT8.get(buf, off),
            flags: token_types_lib.UINT24_BE.get(buf, off + 1),
            numberOfEntries: token_types_lib.UINT32_BE.get(buf, off + 4)
        };
    }
};
/**
 * Atom: Sample Description Atom ('stsd')
 * Ref: https://developer.apple.com/documentation/quicktime-file-format/sample_description_atom
 */
class SampleDescriptionTable {
    constructor(len) {
        this.len = len;
    }
    get(buf, off) {
        const descrLen = this.len - 12;
        return {
            dataFormat: FourCC/* FourCcToken */.e.get(buf, off),
            dataReferenceIndex: token_types_lib.UINT16_BE.get(buf, off + 10),
            description: descrLen > 0 ? new token_types_lib.Uint8ArrayType(descrLen).get(buf, off + 12) : undefined
        };
    }
}
/**
 * Atom: Sample-description Atom ('stsd')
 * Ref: https://developer.apple.com/library/archive/documentation/QuickTime/QTFF/QTFFChap2/qtff2.html#//apple_ref/doc/uid/TP40000939-CH204-25691
 */
class StsdAtom {
    constructor(len) {
        this.len = len;
    }
    get(buf, off) {
        const end = Math.min(off + this.len, buf.length);
        if (end - off < stsdHeader.len) {
            throw new Mp4ContentError('Truncated stsd header');
        }
        const header = stsdHeader.get(buf, off);
        off += stsdHeader.len;
        const table = [];
        for (let n = 0; n < header.numberOfEntries; ++n) {
            if (end - off < token_types_lib.UINT32_BE.len) {
                throw new Mp4ContentError('Truncated stsd sample entry');
            }
            const size = token_types_lib.UINT32_BE.get(buf, off); // Sample description size
            // A SampleEntry needs its size, format, reserved bytes and data reference index.
            // Requiring it to fit also bounds the loop by the available payload, not entry_count.
            if (size < 16 || size > end - off) {
                throw new Mp4ContentError(`Invalid stsd sample entry size: ${size}`);
            }
            off += token_types_lib.UINT32_BE.len;
            table.push(new SampleDescriptionTable(size - token_types_lib.UINT32_BE.len).get(buf, off));
            // A SampleEntry extends Box, so its size covers the size field already stepped over
            off += size - token_types_lib.UINT32_BE.len;
        }
        return {
            header,
            table
        };
    }
}
/**
 * Common Sound Sample Description (version & revision)
 * Ref: https://developer.apple.com/library/archive/documentation/QuickTime/QTFF/QTFFChap3/qtff3.html#//apple_ref/doc/uid/TP40000939-CH205-57317
 */
const SoundSampleDescriptionVersion = {
    len: 8,
    get(buf, off) {
        return {
            version: token_types_lib.INT16_BE.get(buf, off),
            revision: token_types_lib.INT16_BE.get(buf, off + 2),
            vendor: token_types_lib.INT32_BE.get(buf, off + 4)
        };
    }
};
/**
 * Sound Sample Description (Version 0)
 * Ref: https://developer.apple.com/library/archive/documentation/QuickTime/QTFF/QTFFChap3/qtff3.html#//apple_ref/doc/uid/TP40000939-CH205-130736
 */
const SoundSampleDescriptionV0 = {
    len: 12,
    get(buf, off) {
        return {
            numAudioChannels: token_types_lib.INT16_BE.get(buf, off + 0),
            sampleSize: token_types_lib.INT16_BE.get(buf, off + 2),
            compressionId: token_types_lib.INT16_BE.get(buf, off + 4),
            packetSize: token_types_lib.INT16_BE.get(buf, off + 6),
            sampleRate: token_types_lib.UINT16_BE.get(buf, off + 8) + token_types_lib.UINT16_BE.get(buf, off + 10) / 10000
        };
    }
};
class SimpleTableAtom {
    constructor(len, token) {
        this.len = len;
        this.token = token;
    }
    get(buf, off) {
        const nrOfEntries = token_types_lib.INT32_BE.get(buf, off + 4);
        return {
            version: token_types_lib.INT8.get(buf, off + 0),
            flags: token_types_lib.INT24_BE.get(buf, off + 1),
            numberOfEntries: nrOfEntries,
            entries: readTokenTable(buf, this.token, off + 8, this.len - 8, nrOfEntries)
        };
    }
}
const TimeToSampleToken = {
    len: 8,
    get(buf, off) {
        return {
            count: token_types_lib.INT32_BE.get(buf, off + 0),
            duration: token_types_lib.INT32_BE.get(buf, off + 4)
        };
    }
};
/**
 * Time-to-sample('stts') atom.
 * Store duration information for a media’s samples.
 * Ref: https://developer.apple.com/library/archive/documentation/QuickTime/QTFF/QTFFChap2/qtff2.html#//apple_ref/doc/uid/TP40000939-CH204-25696
 */
class SttsAtom extends SimpleTableAtom {
    constructor(len) {
        super(len, TimeToSampleToken);
    }
}
const SampleToChunkToken = {
    len: 12,
    get(buf, off) {
        return {
            firstChunk: token_types_lib.INT32_BE.get(buf, off),
            samplesPerChunk: token_types_lib.INT32_BE.get(buf, off + 4),
            sampleDescriptionId: token_types_lib.INT32_BE.get(buf, off + 8)
        };
    }
};
/**
 * Sample-to-Chunk ('stsc') atom interface
 * Ref: https://developer.apple.com/library/archive/documentation/QuickTime/QTFF/QTFFChap2/qtff2.html#//apple_ref/doc/uid/TP40000939-CH204-25706
 */
class StscAtom extends SimpleTableAtom {
    constructor(len) {
        super(len, SampleToChunkToken);
    }
}
/**
 * Sample-size ('stsz') atom
 * Ref: https://developer.apple.com/library/archive/documentation/QuickTime/QTFF/QTFFChap2/qtff2.html#//apple_ref/doc/uid/TP40000939-CH204-25710
 */
class StszAtom {
    constructor(len) {
        this.len = len;
    }
    get(buf, off) {
        const nrOfEntries = token_types_lib.INT32_BE.get(buf, off + 8);
        return {
            version: token_types_lib.INT8.get(buf, off),
            flags: token_types_lib.INT24_BE.get(buf, off + 1),
            sampleSize: token_types_lib.INT32_BE.get(buf, off + 4),
            numberOfEntries: nrOfEntries,
            entries: readTokenTable(buf, token_types_lib.INT32_BE, off + 12, this.len - 12, nrOfEntries)
        };
    }
}
/**
 * Chunk offset atom, 'stco'
 * Ref: https://developer.apple.com/library/archive/documentation/QuickTime/QTFF/QTFFChap2/qtff2.html#//apple_ref/doc/uid/TP40000939-CH204-25715
 */
class StcoAtom extends SimpleTableAtom {
    constructor(len) {
        super(len, token_types_lib.INT32_BE);
        this.len = len;
    }
}
/**
 * Token used to decode text-track from 'mdat' atom (raw data stream)
 */
class ChapterText {
    constructor(len) {
        this.len = len;
    }
    get(buf, off) {
        const titleLen = token_types_lib.INT16_BE.get(buf, off + 0);
        const str = new token_types_lib.StringType(titleLen, 'utf-8');
        return str.get(buf, off + 2);
    }
}
function readTokenTable(buf, token, off, remainingLen, numberOfEntries) {
    debug(`remainingLen=${remainingLen}, numberOfEntries=${numberOfEntries} * token-len=${token.len}`);
    if (remainingLen === 0) {
        return [];
    }
    if (remainingLen !== numberOfEntries * token.len) {
        throw new Mp4ContentError('mismatch number-of-entries with remaining atom-length');
    }
    const entries = [];
    // parse offset-table
    for (let n = 0; n < numberOfEntries; ++n) {
        entries.push(token.get(buf, off));
        off += token.len;
    }
    return entries;
}
/**
 * Sample-size ('tfhd') TrackFragmentHeaderBox
 */
class TrackFragmentHeaderBox {
    constructor(len) {
        this.len = len;
    }
    get(buf, off) {
        const flagOffset = off + 1;
        const header = {
            version: token_types_lib.INT8.get(buf, off),
            flags: {
                baseDataOffsetPresent: Util/* getBit */.mh(buf, flagOffset + 2, 0),
                sampleDescriptionIndexPresent: Util/* getBit */.mh(buf, flagOffset + 2, 1),
                defaultSampleDurationPresent: Util/* getBit */.mh(buf, flagOffset + 2, 3),
                defaultSampleSizePresent: Util/* getBit */.mh(buf, flagOffset + 2, 4),
                defaultSampleFlagsPresent: Util/* getBit */.mh(buf, flagOffset + 2, 5),
                defaultDurationIsEmpty: Util/* getBit */.mh(buf, flagOffset, 0),
                defaultBaseIsMoof: Util/* getBit */.mh(buf, flagOffset, 1)
            },
            trackId: token_types_lib.UINT32_BE.get(buf, 4)
        };
        let dynOffset = 8;
        if (header.flags.baseDataOffsetPresent) {
            header.baseDataOffset = token_types_lib.UINT64_BE.get(buf, dynOffset);
            dynOffset += 8;
        }
        if (header.flags.sampleDescriptionIndexPresent) {
            header.sampleDescriptionIndex = token_types_lib.UINT32_BE.get(buf, dynOffset);
            dynOffset += 4;
        }
        if (header.flags.defaultSampleDurationPresent) {
            header.defaultSampleDuration = token_types_lib.UINT32_BE.get(buf, dynOffset);
            dynOffset += 4;
        }
        if (header.flags.defaultSampleSizePresent) {
            header.defaultSampleSize = token_types_lib.UINT32_BE.get(buf, dynOffset);
            dynOffset += 4;
        }
        if (header.flags.defaultSampleFlagsPresent) {
            header.defaultSampleFlags = token_types_lib.UINT32_BE.get(buf, dynOffset);
        }
        return header;
    }
}
/**
 * Sample-size ('trun') TrackRunBox
 */
class TrackRunBox {
    constructor(len) {
        this.len = len;
    }
    get(buf, off) {
        const flagOffset = off + 1;
        const trun = {
            version: token_types_lib.INT8.get(buf, off),
            flags: {
                dataOffsetPresent: Util/* getBit */.mh(buf, flagOffset + 2, 0),
                firstSampleFlagsPresent: Util/* getBit */.mh(buf, flagOffset + 2, 2),
                sampleDurationPresent: Util/* getBit */.mh(buf, flagOffset + 1, 0),
                sampleSizePresent: Util/* getBit */.mh(buf, flagOffset + 1, 1),
                sampleFlagsPresent: Util/* getBit */.mh(buf, flagOffset + 1, 2),
                sampleCompositionTimeOffsetsPresent: Util/* getBit */.mh(buf, flagOffset + 1, 3)
            },
            sampleCount: token_types_lib.UINT32_BE.get(buf, off + 4),
            samples: []
        };
        let dynOffset = off + 8;
        if (trun.flags.dataOffsetPresent) {
            trun.dataOffset = token_types_lib.UINT32_BE.get(buf, dynOffset);
            dynOffset += 4;
        }
        if (trun.flags.firstSampleFlagsPresent) {
            trun.firstSampleFlags = token_types_lib.UINT32_BE.get(buf, dynOffset);
            dynOffset += 4;
        }
        for (let n = 0; n < trun.sampleCount; ++n) {
            if (dynOffset >= this.len) {
                debug('TrackRunBox size mismatch');
                break;
            }
            const sample = {};
            if (trun.flags.sampleDurationPresent) {
                sample.sampleDuration = token_types_lib.UINT32_BE.get(buf, dynOffset);
                dynOffset += 4;
            }
            if (trun.flags.sampleSizePresent) {
                sample.sampleSize = token_types_lib.UINT32_BE.get(buf, dynOffset);
                dynOffset += 4;
            }
            if (trun.flags.sampleFlagsPresent) {
                sample.sampleFlags = token_types_lib.UINT32_BE.get(buf, dynOffset);
                dynOffset += 4;
            }
            if (trun.flags.sampleCompositionTimeOffsetsPresent) {
                sample.sampleCompositionTimeOffset = token_types_lib.UINT32_BE.get(buf, dynOffset);
                dynOffset += 4;
            }
            trun.samples.push(sample);
        }
        return trun;
    }
}
/**
 * HandlerBox (`hdlr`)
 */
class HandlerBox {
    constructor(len) {
        this.len = len;
    }
    get(buf, off) {
        const _flagOffset = off + 1;
        const charTypeToken = new token_types_lib.StringType(4, 'utf-8');
        return {
            version: token_types_lib.INT8.get(buf, off),
            flags: token_types_lib.UINT24_BE.get(buf, off + 1),
            componentType: charTypeToken.get(buf, off + 4),
            handlerType: charTypeToken.get(buf, off + 8),
            componentName: new token_types_lib.StringType(this.len - 28, 'utf-8').get(buf, off + 28)
        };
    }
}
/**
 * Chapter Track Reference Box (`chap`)
 */
class ChapterTrackReferenceBox {
    constructor(len) {
        this.len = len;
    }
    get(buf, off) {
        let dynOffset = 0;
        const trackIds = [];
        while (dynOffset < this.len) {
            trackIds.push(token_types_lib.UINT32_BE.get(buf, off + dynOffset));
            dynOffset += 4;
        }
        return trackIds;
    }
}

;// CONCATENATED MODULE: ./bridge/node_modules/music-metadata/lib/mp4/Atom.js



const Atom_debug = src('music-metadata:parser:MP4:Atom');
class Atom {
    static async readAtom(tokenizer, dataHandler, parent, remaining) {
        // Validate boundaries before reading either header or dispatching a payload parser.
        const offset = tokenizer.position;
        remaining = Math.min(remaining, (tokenizer.fileInfo.size ?? Number.POSITIVE_INFINITY) - offset);
        if (remaining < Header.len) {
            throw new Mp4ContentError('Truncated atom header');
        }
        Atom_debug(`Reading next token on offset=${offset}...`); //  buf.toString('ascii')
        const header = await tokenizer.readToken(Header);
        const extended = header.length === 1n;
        if (extended) {
            if (remaining < 16) {
                throw new Mp4ContentError('Truncated extended atom header');
            }
            header.length = await tokenizer.readToken(ExtendedSize);
        }
        const headerLength = extended ? 16 : 8;
        if ((extended || header.length !== 0n) && header.length < BigInt(headerLength)) {
            throw new Mp4ContentError(`Invalid atom size: ${header.length}`);
        }
        if (header.length > BigInt(Number.MAX_SAFE_INTEGER)) {
            throw new Mp4ContentError(`Atom size exceeds Number.MAX_SAFE_INTEGER: ${header.length}`);
        }
        const length = header.length === 0n ? remaining : Number(header.length);
        if (length > remaining) {
            throw new Mp4ContentError(`Atom size exceeds remaining bytes: ${header.length} > ${remaining}`);
        }
        const payloadLength = length - headerLength;
        const atomBean = new Atom(header, extended, parent, payloadLength);
        Atom_debug(`parse atom name=${atomBean.atomPath}, extended=${atomBean.extended}, offset=${offset}, len=${atomBean.header.length}`); //  buf.toString('ascii')
        await atomBean.readData(tokenizer, dataHandler, payloadLength);
        return atomBean;
    }
    constructor(header, extended, parent, payloadLength) {
        this.payloadLength = payloadLength;
        this.header = header;
        this.extended = extended;
        this.parent = parent;
        this.children = [];
        this.atomPath = (this.parent ? `${this.parent.atomPath}.` : '') + this.header.name;
    }
    getHeaderLength() {
        return this.extended ? 16 : 8;
    }
    getPayloadLength() {
        return this.payloadLength;
    }
    async readAtoms(tokenizer, dataHandler, size) {
        while (size > 0) {
            if (size === Number.POSITIVE_INFINITY) {
                // An open-ended container ends only at clean EOF, before the next header.
                const bytesRead = await tokenizer.peekBuffer(new Uint8Array(Header.len), { mayBeLess: true });
                if (bytesRead === 0) {
                    return;
                }
                if (bytesRead < Header.len) {
                    throw new Mp4ContentError('Truncated atom header');
                }
            }
            const atomBean = await Atom.readAtom(tokenizer, dataHandler, this, size);
            this.children.push(atomBean);
            size = atomBean.header.length === 0n ? 0 : size - Number(atomBean.header.length);
        }
    }
    async readData(tokenizer, dataHandler, remaining) {
        switch (this.header.name) {
            // "Container" atoms, contains nested atoms
            case 'moov': // The Movie Atom: contains other atoms
            case 'udta': // User defined atom
            case 'mdia': // Media atom
            case 'minf': // Media Information Atom
            case 'stbl': // The Sample Table Atom
            case '<id>':
            case 'ilst':
            case 'tref':
            case 'moof':
                return this.readAtoms(tokenizer, dataHandler, remaining);
            case 'meta': {
                // Metadata Atom, ref: https://developer.apple.com/library/content/documentation/QuickTime/QTFF/Metadata/Metadata.html#//apple_ref/doc/uid/TP40000939-CH1-SW8
                if (remaining < 8) {
                    throw new Mp4ContentError('Truncated meta atom');
                }
                // meta has 4 bytes of padding, ignore
                const peekHeader = await tokenizer.peekToken(Header);
                const paddingLength = peekHeader.name === 'hdlr' ? 0 : 4;
                await tokenizer.ignore(paddingLength);
                return this.readAtoms(tokenizer, dataHandler, remaining - paddingLength);
            }
            default:
                return dataHandler(this, remaining);
        }
    }
}

;// CONCATENATED MODULE: ./bridge/node_modules/music-metadata/lib/mp4/MP4Parser.js










const MP4Parser_debug = src('music-metadata:parser:MP4');
const tagFormat = 'iTunes';
const encoderDict = {
    raw: {
        lossy: false,
        format: 'raw'
    },
    MAC3: {
        lossy: true,
        format: 'MACE 3:1'
    },
    MAC6: {
        lossy: true,
        format: 'MACE 6:1'
    },
    ima4: {
        lossy: true,
        format: 'IMA 4:1'
    },
    ulaw: {
        lossy: true,
        format: 'uLaw 2:1'
    },
    alaw: {
        lossy: true,
        format: 'uLaw 2:1'
    },
    Qclp: {
        lossy: true,
        format: 'QUALCOMM PureVoice'
    },
    '.mp3': {
        lossy: true,
        format: 'MPEG-1 layer 3'
    },
    alac: {
        lossy: false,
        format: 'ALAC'
    },
    'ac-3': {
        lossy: true,
        format: 'AC-3'
    },
    mp4a: {
        lossy: true,
        format: 'MPEG-4/AAC'
    },
    mp4s: {
        lossy: true,
        format: 'MP4S'
    },
    // Closed Captioning Media, https://developer.apple.com/library/archive/documentation/QuickTime/QTFF/QTFFChap3/qtff3.html#//apple_ref/doc/uid/TP40000939-CH205-SW87
    c608: {
        lossy: true,
        format: 'CEA-608'
    },
    c708: {
        lossy: true,
        format: 'CEA-708'
    }
};
function distinct(value, index, self) {
    return self.indexOf(value) === index;
}
/**
 * Determine if a track carries audio.
 *
 * Ref: ISO/IEC 14496-12, 8.5.2: the sample entries in a sample description box are track-type specific,
 * selected by the handler type of the enclosing track. Only an audio track holds an AudioSampleEntry, so
 * only such a track may be described by a sound sample description; `isAudio()` treats both the 'soun'
 * and 'audi' handler types as audio.
 *
 * Falls back to the sample description when no handler box was found, preserving the previous behavior.
 */
function isAudioTrack(track) {
    const [ssd] = track.soundSampleDescription ?? [];
    if (!ssd) {
        return false;
    }
    if (track.isAudio) {
        return track.isAudio();
    }
    return !!ssd.description && ssd.description.numAudioChannels > 0;
}
/*
 * Parser for the MP4 (MPEG-4 Part 14) container format
 * Standard: ISO/IEC 14496-14
 * supporting:
 * - QuickTime container
 * - MP4 File Format
 * - 3GPP file format
 * - 3GPP2 file format
 *
 * MPEG-4 Audio / Part 3 (.m4a)& MPEG 4 Video (m4v, mp4) extension.
 * Support for Apple iTunes tags as found in a M4A/M4V files.
 * Ref:
 *   https://en.wikipedia.org/wiki/ISO_base_media_file_format
 *   https://developer.apple.com/library/archive/documentation/QuickTime/QTFF/Metadata/Metadata.html
 *   http://atomicparsley.sourceforge.net/mpeg-4files.html
 *   https://github.com/sergiomb2/libmp4v2/wiki/iTunesMetadata
 *   https://wiki.multimedia.cx/index.php/QuickTime_container
 */
class MP4Parser extends BasicParser/* BasicParser */.s {
    constructor() {
        super(...arguments);
        this.tracks = new Map();
        this.hasVideoTrack = false;
        this.hasAudioTrack = true;
        this.atomParsers = {
            /**
             * Parse movie header (mvhd) atom
             * Ref: https://developer.apple.com/library/archive/documentation/QuickTime/QTFF/QTFFChap2/qtff2.html#//apple_ref/doc/uid/TP40000939-CH204-56313
             */
            mvhd: async (len) => {
                const mvhd = await this.readToken(new MvhdAtom(len));
                this.metadata.setFormat('creationTime', mvhd.creationTime);
                this.metadata.setFormat('modificationTime', mvhd.modificationTime);
            },
            chap: async (len) => {
                const td = this.getTrackDescription();
                const trackIds = [];
                while (len >= token_types_lib.UINT32_BE.len) {
                    trackIds.push(await this.tokenizer.readNumber(token_types_lib.UINT32_BE));
                    len -= token_types_lib.UINT32_BE.len;
                }
                td.chapterList = trackIds;
            },
            /**
             * Parse mdat atom.
             * Will scan for chapters
             */
            mdat: async (len) => {
                if (this.options.includeChapters) {
                    const trackWithChapters = [...this.tracks.values()].filter(track => track.chapterList);
                    if (trackWithChapters.length === 1) {
                        const chapterTrackIds = trackWithChapters[0].chapterList;
                        const chapterTracks = [...this.tracks.values()].filter(track => chapterTrackIds.indexOf(track.header.trackId) !== -1);
                        if (chapterTracks.length === 1) {
                            return this.parseChapterTrack(chapterTracks[0], trackWithChapters[0], len);
                        }
                    }
                }
                await this.tokenizer.ignore(len);
            },
            ftyp: async (len) => {
                if (len < 8 || len % ftyp.len !== 0) {
                    throw new Mp4ContentError(`Invalid ftyp payload length: ${len}`);
                }
                const types = [];
                while (len > 0) {
                    const ftype = await this.readToken(ftyp);
                    len -= ftyp.len;
                    const value = ftype.type.replace(/\W/g, '');
                    if (value.length > 0) {
                        types.push(value); // unshift for backward compatibility
                    }
                }
                MP4Parser_debug(`ftyp: ${types.join('/')}`);
                const x = types.filter(distinct).join('/');
                this.metadata.setFormat('container', x);
            },
            /**
             * Parse sample description atom
             */
            stsd: async (len) => {
                const stsd = await this.readToken(new StsdAtom(len));
                const trackDescription = this.getTrackDescription();
                trackDescription.soundSampleDescription = stsd.table.map(dfEntry => this.parseSoundSampleDescription(dfEntry));
            },
            /**
             * Parse sample-sizes atom ('stsz')
             */
            stsz: async (len) => {
                const stsz = await this.readToken(new StszAtom(len));
                const td = this.getTrackDescription();
                td.sampleSize = stsz.sampleSize;
                td.sampleSizeTable = stsz.entries;
            },
            date: async (len) => {
                const date = await this.readToken(new token_types_lib.StringType(len, 'utf-8'));
                await this.addTag('date', date);
            }
        };
    }
    static read_BE_Integer(array, signed) {
        const integerType = (signed ? 'INT' : 'UINT') + array.length * 8 + (array.length > 1 ? '_BE' : '');
        const token = token_types_lib[integerType];
        if (!token) {
            throw new Mp4ContentError(`Token for integer type not found: "${integerType}"`);
        }
        return Number(token.get(array, 0));
    }
    // Metadata and sample tables are buffered; media data is skipped separately.
    // A bound is also necessary for streams whose total size is unknown.
    validatePayloadLength(length) {
        if (!Number.isSafeInteger(length) || length < 0 || length > 64 * 1024 * 1024) {
            throw new Mp4ContentError(`Atom payload exceeds the 64 MiB buffering limit: ${length}`);
        }
    }
    async readToken(token) {
        this.validatePayloadLength(token.len);
        return this.tokenizer.readToken(token);
    }
    async parse() {
        this.hasVideoTrack = false;
        this.hasAudioTrack = true;
        this.tracks.clear();
        let remainingFileSize = this.tokenizer.fileInfo.size ?? Number.POSITIVE_INFINITY;
        while (remainingFileSize > 0) {
            try {
                const token = await this.tokenizer.peekToken(Header);
                if (token.name === '\0\0\0\0') {
                    const errMsg = `Error at offset=${this.tokenizer.position}: box.id=0`;
                    MP4Parser_debug(errMsg);
                    this.addWarning(errMsg);
                    break;
                }
            }
            catch (error) {
                if (error instanceof Error) {
                    const errMsg = `Error at offset=${this.tokenizer.position}: ${error.message}`;
                    MP4Parser_debug(errMsg);
                    this.addWarning(errMsg);
                }
                else {
                    throw error;
                }
                break;
            }
            const rootAtom = await Atom.readAtom(this.tokenizer, (atom, remaining) => this.handleAtom(atom, remaining), null, remainingFileSize);
            remainingFileSize = rootAtom.header.length === 0n ? 0 : remainingFileSize - Number(rootAtom.header.length);
        }
        // Post process metadata
        const formatList = [];
        this.tracks.forEach(track => {
            const trackFormats = [];
            const trackIsAudio = isAudioTrack(track);
            track.soundSampleDescription.forEach(ssd => {
                const streamInfo = {};
                const encoderInfo = encoderDict[ssd.dataFormat];
                if (encoderInfo) {
                    trackFormats.push(encoderInfo.format);
                    streamInfo.codecName = encoderInfo.format;
                }
                else {
                    streamInfo.codecName = `<${ssd.dataFormat}>`;
                }
                if (trackIsAudio && ssd.description) {
                    const { description } = ssd;
                    if (description.sampleRate > 0) {
                        streamInfo.type = types/* TrackType */.S.audio;
                        streamInfo.audio = {
                            samplingFrequency: description.sampleRate,
                            bitDepth: description.sampleSize,
                            channels: description.numAudioChannels
                        };
                    }
                }
                this.metadata.addStreamInfo(streamInfo);
            });
            if (trackFormats.length >= 1) {
                formatList.push(trackFormats.join('/'));
            }
        });
        if (formatList.length > 0) {
            this.metadata.setFormat('codec', formatList.filter(distinct).join('+'));
        }
        const audioTracks = [...this.tracks.values()].filter(isAudioTrack);
        // Calculate duration and bitrate of audio tracks
        for (const audioTrack of audioTracks) {
            if (audioTrack.media.header && audioTrack.media.header.timeScale > 0) {
                audioTrack.sampleRate = audioTrack.media.header.timeScale;
                if (audioTrack.media.header.duration > 0) {
                    MP4Parser_debug('Using duration defined on audio track');
                    audioTrack.samples = audioTrack.media.header.duration;
                    audioTrack.duration = audioTrack.samples / audioTrack.sampleRate;
                }
                if (audioTrack.fragments.length > 0) {
                    MP4Parser_debug('Calculate duration defined in track fragments');
                    let totalTimeUnits = 0;
                    audioTrack.sizeInBytes = 0;
                    for (const fragment of audioTrack.fragments) {
                        for (const sample of fragment.trackRun.samples) {
                            const dur = sample.sampleDuration ?? fragment.header.defaultSampleDuration ?? 0;
                            const size = sample.sampleSize ?? fragment.header.defaultSampleSize ?? 0;
                            if (dur === 0) {
                                throw new Error('Missing sampleDuration and no defaultSampleDuration in track fragment header');
                            }
                            if (size === 0) {
                                throw new Error('Missing sampleSize and no defaultSampleSize in track fragment header');
                            }
                            totalTimeUnits += dur;
                            audioTrack.sizeInBytes += size;
                        }
                    }
                    if (!audioTrack.samples) {
                        audioTrack.samples = totalTimeUnits;
                    }
                    if (!audioTrack.duration) {
                        audioTrack.duration = totalTimeUnits / audioTrack.sampleRate;
                    }
                }
                else if (audioTrack.sampleSizeTable.length > 0) {
                    audioTrack.sizeInBytes = audioTrack.sampleSizeTable.reduce((sum, n) => sum + n, 0);
                }
            }
            const ssd = audioTrack.soundSampleDescription[0];
            if (ssd.description && audioTrack.media.header) {
                this.metadata.setFormat('sampleRate', ssd.description.sampleRate);
                this.metadata.setFormat('bitsPerSample', ssd.description.sampleSize);
                this.metadata.setFormat('numberOfChannels', ssd.description.numAudioChannels);
                if (audioTrack.media.header.timeScale === 0 && audioTrack.timeToSampleTable.length > 0) {
                    const totalSampleSize = audioTrack.timeToSampleTable
                        .map(ttstEntry => ttstEntry.count * ttstEntry.duration)
                        .reduce((total, sampleSize) => total + sampleSize);
                    audioTrack.duration = totalSampleSize / ssd.description.sampleRate;
                }
            }
            const encoderInfo = encoderDict[ssd.dataFormat];
            if (encoderInfo) {
                this.metadata.setFormat('lossless', !encoderInfo.lossy);
            }
        }
        if (audioTracks.length >= 1) {
            const firstAudioTrack = audioTracks[0];
            if (firstAudioTrack.duration) {
                this.metadata.setFormat('duration', firstAudioTrack.duration);
                if (firstAudioTrack.sizeInBytes) {
                    this.metadata.setFormat('bitrate', (8 * firstAudioTrack.sizeInBytes) / firstAudioTrack.duration);
                }
            }
        }
        this.metadata.setFormat('hasAudio', this.hasAudioTrack);
        this.metadata.setFormat('hasVideo', this.hasVideoTrack);
    }
    async handleAtom(atom, remaining) {
        if (atom.parent) {
            switch (atom.parent.header.name) {
                case 'ilst':
                case '<id>':
                    return this.parseMetadataItemData(atom);
                case 'moov':
                    switch (atom.header.name) {
                        case 'trak':
                            return this.parseTrackBox(atom);
                        case 'udta':
                            return this.parseTrackBox(atom);
                    }
                    break;
                case 'moof':
                    switch (atom.header.name) {
                        case 'traf':
                            return this.parseTrackFragmentBox(atom);
                    }
            }
        }
        if (this.atomParsers[atom.header.name]) {
            if (atom.header.name !== 'mdat') {
                this.validatePayloadLength(remaining);
            }
            return this.atomParsers[atom.header.name](remaining);
        }
        MP4Parser_debug(`No parser for atom path=${atom.atomPath}, payload-len=${remaining}, ignoring atom`);
        await this.tokenizer.ignore(remaining);
    }
    getTrackDescription() {
        // ToDo: pick the right track, not the last track!!!!
        const tracks = [...this.tracks.values()];
        return tracks[tracks.length - 1];
    }
    async addTag(id, value) {
        await this.metadata.addTag(tagFormat, id, value);
    }
    addWarning(message) {
        MP4Parser_debug(`Warning: ${message}`);
        this.metadata.addWarning(message);
    }
    /**
     * Parse data of Meta-item-list-atom (item of 'ilst' atom)
     * @param metaAtom
     * Ref: https://developer.apple.com/library/content/documentation/QuickTime/QTFF/Metadata/Metadata.html#//apple_ref/doc/uid/TP40000939-CH1-SW8
     */
    parseMetadataItemData(metaAtom) {
        let tagKey = metaAtom.header.name;
        return metaAtom.readAtoms(this.tokenizer, async (child) => {
            const payLoadLength = child.getPayloadLength();
            switch (child.header.name) {
                case 'data': // value atom
                    return this.parseValueAtom(tagKey, child);
                case 'name': // name atom (optional)
                case 'mean':
                case 'rate': {
                    const name = await this.readToken(new NameAtom(payLoadLength));
                    tagKey += `:${name.name}`;
                    break;
                }
                default: {
                    const uint8Array = await this.readToken(new token_types_lib.Uint8ArrayType(payLoadLength));
                    this.addWarning(`Unsupported meta-item: ${tagKey}[${child.header.name}] => value=${(0,uint8array_extras/* uint8ArrayToHex */.EY)(uint8Array)} ascii=${(0,lib/* textDecode */.c)(uint8Array, 'ascii')}`);
                }
            }
        }, metaAtom.getPayloadLength());
    }
    async parseValueAtom(tagKey, metaAtom) {
        const dataAtom = await this.readToken(new DataAtom(metaAtom.getPayloadLength()));
        if (dataAtom.type.set !== 0) {
            throw new Mp4ContentError(`Unsupported type-set != 0: ${dataAtom.type.set}`);
        }
        // Use well-known-type table
        // Ref: https://developer.apple.com/library/content/documentation/QuickTime/QTFF/Metadata/Metadata.html#//apple_ref/doc/uid/TP40000939-CH1-SW35
        switch (dataAtom.type.type) {
            case 0: // reserved: Reserved for use where no type needs to be indicated
                switch (tagKey) {
                    case 'trkn':
                    case 'disk': {
                        const num = token_types_lib.UINT8.get(dataAtom.value, 3);
                        const of = token_types_lib.UINT8.get(dataAtom.value, 5);
                        // console.log("  %s[data] = %s/%s", tagKey, num, of);
                        await this.addTag(tagKey, `${num}/${of}`);
                        break;
                    }
                    case 'gnre': {
                        const genreInt = token_types_lib.UINT8.get(dataAtom.value, 1);
                        const genreStr = ID3v1Parser/* Genres */.jR[genreInt - 1];
                        // console.log("  %s[data] = %s", tagKey, genreStr);
                        await this.addTag(tagKey, genreStr);
                        break;
                    }
                    case 'rate': {
                        const rate = (0,lib/* textDecode */.c)(dataAtom.value, 'ascii');
                        await this.addTag(tagKey, rate);
                        break;
                    }
                    default:
                        MP4Parser_debug(`unknown proprietary value type for: ${metaAtom.atomPath}`);
                }
                break;
            case 1: // UTF-8: Without any count or NULL terminator
            case 18: // Unknown: Found in m4b in combination with a '©gen' tag
                await this.addTag(tagKey, (0,lib/* textDecode */.c)(dataAtom.value));
                break;
            case 13: // JPEG
                if (this.options.skipCovers) {
                    break;
                }
                await this.addTag(tagKey, {
                    format: 'image/jpeg',
                    data: Uint8Array.from(dataAtom.value)
                });
                break;
            case 14: // PNG
                if (this.options.skipCovers) {
                    break;
                }
                await this.addTag(tagKey, {
                    format: 'image/png',
                    data: Uint8Array.from(dataAtom.value)
                });
                break;
            case 21: // BE Signed Integer
                await this.addTag(tagKey, MP4Parser.read_BE_Integer(dataAtom.value, true));
                break;
            case 22: // BE Unsigned Integer
                await this.addTag(tagKey, MP4Parser.read_BE_Integer(dataAtom.value, false));
                break;
            case 65: // An 8-bit signed integer
                await this.addTag(tagKey, token_types_lib.UINT8.get(dataAtom.value, 0));
                break;
            case 66: // A big-endian 16-bit signed integer
                await this.addTag(tagKey, token_types_lib.UINT16_BE.get(dataAtom.value, 0));
                break;
            case 67: // A big-endian 32-bit signed integer
                await this.addTag(tagKey, token_types_lib.UINT32_BE.get(dataAtom.value, 0));
                break;
            default:
                this.addWarning(`atom key=${tagKey}, has unknown well-known-type (data-type): ${dataAtom.type.type}`);
        }
    }
    async parseTrackBox(trakBox) {
        // @ts-expect-error
        const track = {
            media: {},
            fragments: []
        };
        await trakBox.readAtoms(this.tokenizer, async (child) => {
            const payLoadLength = child.getPayloadLength();
            switch (child.header.name) {
                case 'chap': {
                    const chap = await this.readToken(new ChapterTrackReferenceBox(payLoadLength));
                    track.chapterList = chap;
                    break;
                }
                case 'tkhd': // TrackHeaderBox
                    track.header = await this.readToken(new TrackHeaderAtom(payLoadLength));
                    break;
                case 'hdlr': // TrackHeaderBox
                    track.handler = await this.readToken(new HandlerBox(payLoadLength));
                    track.isAudio = () => track.handler.handlerType === 'audi' || track.handler.handlerType === 'soun';
                    track.isVideo = () => track.handler.handlerType === 'vide';
                    if (track.isAudio()) {
                        this.hasAudioTrack = true;
                    }
                    else if (track.isVideo()) {
                        this.hasVideoTrack = true;
                    }
                    break;
                case 'mdhd': {
                    // Parse media header (mdhd) box
                    const mdhd_data = await this.readToken(new MdhdAtom(payLoadLength));
                    track.media.header = mdhd_data;
                    break;
                }
                case 'stco': {
                    const stco = await this.readToken(new StcoAtom(payLoadLength));
                    track.chunkOffsetTable = stco.entries; // remember chunk offsets
                    break;
                }
                case 'stsc': {
                    // sample-to-Chunk box
                    const stsc = await this.readToken(new StscAtom(payLoadLength));
                    track.sampleToChunkTable = stsc.entries;
                    break;
                }
                case 'stsd': {
                    // sample description box
                    const stsd = await this.readToken(new StsdAtom(payLoadLength));
                    track.soundSampleDescription = stsd.table.map(dfEntry => this.parseSoundSampleDescription(dfEntry));
                    break;
                }
                case 'stts': {
                    // time-to-sample table
                    const stts = await this.readToken(new SttsAtom(payLoadLength));
                    track.timeToSampleTable = stts.entries;
                    break;
                }
                case 'stsz': {
                    const stsz = await this.readToken(new StszAtom(payLoadLength));
                    track.sampleSize = stsz.sampleSize;
                    track.sampleSizeTable = stsz.entries;
                    break;
                }
                case 'dinf':
                case 'vmhd':
                case 'smhd':
                    MP4Parser_debug(`Ignoring: ${child.header.name}`);
                    await this.tokenizer.ignore(payLoadLength);
                    break;
                default: {
                    MP4Parser_debug(`Unexpected track box: ${child.header.name}`);
                    await this.tokenizer.ignore(payLoadLength);
                }
            }
        }, trakBox.getPayloadLength());
        // Register track
        this.tracks.set(track.header.trackId, track);
    }
    parseTrackFragmentBox(trafBox) {
        let tfhd;
        return trafBox.readAtoms(this.tokenizer, async (child) => {
            const payLoadLength = child.getPayloadLength();
            switch (child.header.name) {
                case 'tfhd': {
                    // TrackFragmentHeaderBox
                    const fragmentHeaderBox = new TrackFragmentHeaderBox(child.getPayloadLength());
                    tfhd = await this.readToken(fragmentHeaderBox);
                    break;
                }
                case 'tfdt': // TrackFragmentBaseMediaDecodeTimeBo
                    await this.tokenizer.ignore(payLoadLength);
                    break;
                case 'trun': {
                    // TrackRunBox
                    const trackRunBox = new TrackRunBox(payLoadLength);
                    const trun = await this.readToken(trackRunBox);
                    if (tfhd) {
                        const track = this.tracks.get(tfhd.trackId);
                        track?.fragments.push({ header: tfhd, trackRun: trun });
                    }
                    break;
                }
                default: {
                    MP4Parser_debug(`Unexpected box: ${child.header.name}`);
                    await this.tokenizer.ignore(payLoadLength);
                }
            }
        }, trafBox.getPayloadLength());
    }
    /**
     * @param sampleDescription
     * Ref: https://developer.apple.com/library/archive/documentation/QuickTime/QTFF/QTFFChap3/qtff3.html#//apple_ref/doc/uid/TP40000939-CH205-128916
     */
    parseSoundSampleDescription(sampleDescription) {
        const ssd = {
            dataFormat: sampleDescription.dataFormat,
            dataReferenceIndex: sampleDescription.dataReferenceIndex
        };
        let offset = 0;
        const { description } = sampleDescription;
        // Only an AudioSampleEntry carries a sound sample description; it is at least 20 bytes beyond the
        // 16-byte SampleEntry base. A shorter description belongs to another sample entry class, or is truncated.
        if (description && description.length >= SoundSampleDescriptionVersion.len) {
            const version = SoundSampleDescriptionVersion.get(description, offset);
            offset += SoundSampleDescriptionVersion.len;
            if (version.version === 0 || version.version === 1) {
                // Sound Sample Description (Version 0)
                if (description.length >= offset + SoundSampleDescriptionV0.len) {
                    ssd.description = SoundSampleDescriptionV0.get(description, offset);
                }
                else {
                    MP4Parser_debug(`Warning: sound-sample-description too short: ${description.length} bytes`);
                }
            }
            else {
                MP4Parser_debug(`Warning: sound-sample-description ${version} not implemented`);
            }
        }
        return ssd;
    }
    async parseChapterTrack(chapterTrack, track, len) {
        if (!chapterTrack.sampleSize) {
            if (chapterTrack.chunkOffsetTable.length !== chapterTrack.sampleSizeTable.length) {
                throw new Error('Expected equal chunk-offset-table & sample-size-table length.');
            }
        }
        const chapters = [];
        for (let i = 0; i < chapterTrack.chunkOffsetTable.length && len > 0; ++i) {
            const start = chapterTrack.timeToSampleTable.slice(0, i).reduce((acc, cur) => acc + cur.duration, 0);
            const chunkOffset = chapterTrack.chunkOffsetTable[i];
            const nextChunkLen = chunkOffset - this.tokenizer.position;
            const sampleSize = chapterTrack.sampleSize > 0 ? chapterTrack.sampleSize : chapterTrack.sampleSizeTable[i];
            len -= nextChunkLen + sampleSize;
            if (len < 0) {
                throw new Mp4ContentError('Chapter chunk exceeding token length');
            }
            await this.tokenizer.ignore(nextChunkLen);
            const title = await this.readToken(new ChapterText(sampleSize));
            MP4Parser_debug(`Chapter ${i + 1}: ${title}`);
            const chapter = {
                title,
                timeScale: chapterTrack.media.header ? chapterTrack.media.header.timeScale : 0,
                start,
                sampleOffset: this.findSampleOffset(track, this.tokenizer.position)
            };
            MP4Parser_debug(`Chapter title=${chapter.title}, offset=${chapter.sampleOffset}/${track.header.duration}`); // ToDo, use media duration if required!!!
            chapters.push(chapter);
        }
        this.metadata.setFormat('chapters', chapters);
        await this.tokenizer.ignore(len);
    }
    findSampleOffset(track, chapterOffset) {
        let chunkIndex = 0;
        while (chunkIndex < track.chunkOffsetTable.length && track.chunkOffsetTable[chunkIndex] < chapterOffset) {
            ++chunkIndex;
        }
        return this.getChunkDuration(chunkIndex + 1, track);
    }
    getChunkDuration(chunkId, track) {
        let ttsi = 0;
        let ttsc = track.timeToSampleTable[ttsi].count;
        let ttsd = track.timeToSampleTable[ttsi].duration;
        let curChunkId = 1;
        let samplesPerChunk = this.getSamplesPerChunk(curChunkId, track.sampleToChunkTable);
        let totalDuration = 0;
        while (curChunkId < chunkId) {
            const nrOfSamples = Math.min(ttsc, samplesPerChunk);
            totalDuration += nrOfSamples * ttsd;
            ttsc -= nrOfSamples;
            samplesPerChunk -= nrOfSamples;
            if (samplesPerChunk === 0) {
                ++curChunkId;
                samplesPerChunk = this.getSamplesPerChunk(curChunkId, track.sampleToChunkTable);
            }
            else {
                ++ttsi;
                ttsc = track.timeToSampleTable[ttsi].count;
                ttsd = track.timeToSampleTable[ttsi].duration;
            }
        }
        return totalDuration;
    }
    getSamplesPerChunk(chunkId, stcTable) {
        for (let i = 0; i < stcTable.length - 1; ++i) {
            if (chunkId >= stcTable[i].firstChunk && chunkId < stcTable[i + 1].firstChunk) {
                return stcTable[i].samplesPerChunk;
            }
        }
        return stcTable[stcTable.length - 1].samplesPerChunk;
    }
}


/***/ })

};
;