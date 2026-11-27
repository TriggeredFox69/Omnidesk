'use strict';
const fs = require('node:fs');
const path = require('node:path');
const { createHash } = require('node:crypto');
const { HttpsProxyAgent } = require('https-proxy-agent');
const { SocksProxyAgent } = require('socks-proxy-agent');

const VALID_PROTOCOLS = new Set(['http:', 'https:', 'socks:', 'socks4:', 'socks4a:', 'socks5:', 'socks5h:']);

function normalizeProxyUrl(raw) {
  const s = String(raw || '').trim();
  if (!s) return '';
  try {
    const u = new URL(s);
    if (!VALID_PROTOCOLS.has(u.protocol)) return '';
    return u.toString();
  } catch (_) {
    return '';
  }
}

function maskProxyUrl(raw) {
  const s = normalizeProxyUrl(raw);
  if (!s) return '';
  try {
    const u = new URL(s);
    return `${u.protocol}//${u.hostname}${u.port ? ':' + u.port : ''}`;
  } catch (_) {
    return '';
  }
}

function buildProxyAgent(raw) {
  const url = normalizeProxyUrl(raw);
  if (!url) return null;
  try {
    const u = new URL(url);
    const agent = u.protocol.startsWith('socks')
      ? new SocksProxyAgent(url)
      : new HttpsProxyAgent(url);
    return {
      url,
      masked: maskProxyUrl(url),
      agent,
      fetchAgent: agent
    };
  } catch (err) {
    console.warn('[Proxy] Could not create proxy agent:', err.message);
    return null;
  }
}

function readJsonSafe(filePath) {
  try {
    if (filePath && fs.existsSync(filePath)) {
      return JSON.parse(fs.readFileSync(filePath, 'utf8') || 'null');
    }
  } catch (_) {}
  return null;
}

function pickStickyFromPool(pool, key) {
  if (!Array.isArray(pool) || !pool.length) return '';
  const valid = pool.map(normalizeProxyUrl).filter(Boolean);
  if (!valid.length) return '';
  const digest = createHash('sha256').update(String(key || 'default')).digest();
  const idx = digest.readUInt32BE(0) % valid.length;
  return valid[idx];
}

/**
 * Resolves a dedicated sticky proxy URL for a WhatsApp device.
 * Priority:
 *  1. Device meta.json proxy_url
 *  2. proxies.json (devices[id] -> users[id] -> companies[companyId] -> deterministic pool[id] -> default)
 *  3. users.json user.proxy_url / user.proxy
 *  4. WHATSAPP_PROXY_URL env var
 */
function resolveProxy({ deviceId, companyId, metaProxy, dataDir, usersFile } = {}) {
  const explicit = normalizeProxyUrl(metaProxy);
  if (explicit) return explicit;

  const id = String(deviceId || '');
  let resolvedCompany = String(companyId || '');
  let userProxy = '';

  const users = readJsonSafe(usersFile || (dataDir ? path.resolve(dataDir, '../users.json') : ''));
  if (Array.isArray(users) && id) {
    const u = users.find(entry => entry && String(entry.id) === id);
    if (u) {
      if (!resolvedCompany && u.company_id) resolvedCompany = String(u.company_id);
      userProxy = normalizeProxyUrl(u.proxy_url || u.proxy);
    }
  }

  const candidates = [];
  if (dataDir) {
    candidates.push(path.join(dataDir, 'proxies.json'), path.resolve(dataDir, '../proxies.json'));
  }
  for (const cfgFile of candidates) {
    const cfg = readJsonSafe(cfgFile);
    if (!cfg || typeof cfg !== 'object') continue;
    const fromDevices = id && cfg.devices ? normalizeProxyUrl(cfg.devices[id]) : '';
    if (fromDevices) return fromDevices;
    const fromUsers = id && cfg.users ? normalizeProxyUrl(cfg.users[id]) : '';
    if (fromUsers) return fromUsers;
    if (userProxy) return userProxy;
    const fromCompany = resolvedCompany && cfg.companies ? normalizeProxyUrl(cfg.companies[resolvedCompany]) : '';
    if (fromCompany) return fromCompany;
    const fromPool = pickStickyFromPool(cfg.pool, id || resolvedCompany || 'main');
    if (fromPool) return fromPool;
    const fromDefault = normalizeProxyUrl(cfg.default);
    if (fromDefault) return fromDefault;
  }

  if (userProxy) return userProxy;
  return normalizeProxyUrl(process.env.WHATSAPP_PROXY_URL);
}

module.exports = {
  normalizeProxyUrl,
  maskProxyUrl,
  buildProxyAgent,
  resolveProxy
};
