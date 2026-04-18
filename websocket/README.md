# AlumGlass WebSocket Relay

Lightweight Node.js WebSocket server that relays chat traffic in real time.

- Authentication is handled by PHP via `/chat/api/verify_session.php`
- Persistence is handled by `/chat/api/messages.php` (this server does not touch the DB)
- Clients connect with `ws://host:8080/?token=<PHPSESSID>`

## Local dev

```bash
cd websocket
npm install
# Point at a real PHP endpoint or bypass:
AUTH_BYPASS_USER_ID=1 WS_PORT=8080 npm run dev
```

## Production (PM2)

```bash
cd websocket
npm install --production
pm2 start ecosystem.config.js
pm2 save
pm2 startup systemd  # once per host
```

## Health check

`GET /health` returns `{status:"ok", users_online, sockets, uptime_seconds}`.

## Environment

| Variable | Default | Purpose |
|----------|---------|---------|
| `WS_PORT` | `8080` | TCP port the relay listens on |
| `PHP_AUTH_URL` | `http://localhost/chat/api/verify_session.php` | Session verifier |
| `AUTH_BYPASS_USER_ID` | _unset_ | If set, skip PHP auth and treat every connection as that user id. **Dev only.** |

## Behind nginx

```
location /ws/ {
  proxy_pass http://127.0.0.1:8080/;
  proxy_http_version 1.1;
  proxy_set_header Upgrade $http_upgrade;
  proxy_set_header Connection "upgrade";
  proxy_set_header Host $host;
  proxy_read_timeout 3600s;
}
```
