'use strict';

/**
 * WebSocket authentication helper.
 *
 * The real session source of truth is PHP — this module makes a single
 * HTTP call to /chat/api/verify_session.php which echoes the caller's
 * PHPSESSID cookie. If the session is active and associated with a
 * user, verify_session.php returns {valid:true, userId, username, role}.
 *
 * In dev we additionally accept AUTH_BYPASS_USER_ID to make local
 * testing possible without a PHP server running.
 */

const http = require('http');
const https = require('https');
const { URL } = require('url');

async function authenticateUser(token, phpAuthUrl) {
    if (!token) return null;

    if (process.env.AUTH_BYPASS_USER_ID) {
        return parseInt(process.env.AUTH_BYPASS_USER_ID, 10);
    }

    return new Promise((resolve) => {
        let url;
        try {
            url = new URL(phpAuthUrl);
        } catch (err) {
            console.error('[auth] invalid PHP_AUTH_URL:', err.message);
            return resolve(null);
        }

        const body = JSON.stringify({ token });
        const opts = {
            hostname: url.hostname,
            port: url.port || (url.protocol === 'https:' ? 443 : 80),
            path: url.pathname + url.search,
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Content-Length': Buffer.byteLength(body),
                'X-WebSocket-Auth': '1',
            },
            timeout: 3000,
        };

        const lib = url.protocol === 'https:' ? https : http;
        const req = lib.request(opts, (res) => {
            let chunks = '';
            res.on('data', (c) => { chunks += c; });
            res.on('end', () => {
                try {
                    const data = JSON.parse(chunks);
                    resolve(data.valid ? parseInt(data.userId, 10) : null);
                } catch (err) {
                    console.error('[auth] parse error:', err.message);
                    resolve(null);
                }
            });
        });
        req.on('error', (err) => {
            console.error('[auth] request error:', err.message);
            resolve(null);
        });
        req.on('timeout', () => { req.destroy(); resolve(null); });
        req.write(body);
        req.end();
    });
}

module.exports = { authenticateUser };
