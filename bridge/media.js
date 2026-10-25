'use strict';
async function downloadAttachment(client, message) {
  if (!client.pupPage) return message.downloadMedia();
  // Current WhatsApp validates the MIME type during decryption. The pinned
  // library's downloadMedia omits it and raises InvalidMediaFileType.
  return client.pupPage.evaluate(async id => {
    const message = window.require('WAWebCollections').Msg.get(id);
    if (!message?.directPath || !message.mediaKey) throw new Error('Attachment is no longer available.');
    if (Number(message.size) > 10*1024*1024) throw new Error('Attachment exceeds 10 MB.');
    const qpl = { addAnnotations() { return this; }, addPoint() { return this; } };
    const bytes = await window.require('WAWebDownloadManager').downloadManager.downloadAndMaybeDecrypt({
      directPath: message.directPath, encFilehash: message.encFilehash, filehash: message.filehash,
      mediaKey: message.mediaKey, mediaKeyTimestamp: message.mediaKeyTimestamp,
      mimetype: message.mimetype, type: message.type, signal: AbortSignal.timeout(20000), downloadQpl: qpl
    });
    if (!bytes || bytes.byteLength > 10*1024*1024) throw new Error('Attachment is unavailable or exceeds 10 MB.');
    return { data: await window.WWebJS.arrayBufferToBase64Async(bytes), mimetype: message.mimetype, filename: message.filename || '' };
  }, message.id._serialized);
}
module.exports = { downloadAttachment };
