'use strict';

// Recent WhatsApp Web MsgKey models expose toString(), but no _serialized.
// whatsapp-web.js 1.34.7 still uses _serialized for cache lookups and events.
async function installMessageIdCompatibility(client) {
  if (!client.pupPage) return; // Test clients have no browser.
  await client.pupPage.evaluate(() => {
    const MsgKey = window.require('WAWebMsgKey');
    if (!Object.getOwnPropertyDescriptor(MsgKey.prototype, '_serialized')) {
      Object.defineProperty(MsgKey.prototype, '_serialized', {
        configurable: true,
        get() { return this.toString(); },
        set(value) { Object.defineProperty(this, '_serialized', { value, writable: true, configurable: true, enumerable: true }); }
      });
    }
    if (window.WWebJS.nunnoMessageIdCompatibility) return;
    const original = window.WWebJS.getMessageModel;
    window.WWebJS.getMessageModel = function (message) {
      const model = original(message);
      const id = message.id?._serialized || message.id?.toString();
      if (model.id && typeof id === 'string' && id !== '[object Object]') {
        model.id = { ...model.id, _serialized: id };
      }
      return model;
    };
    window.WWebJS.nunnoMessageIdCompatibility = true;
  });
}
module.exports = { installMessageIdCompatibility };
