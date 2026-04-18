/* eslint-disable */
/**
 * PM2 process definition for the AlumGlass WebSocket relay.
 * Deployment:
 *   cd websocket && npm install --production
 *   pm2 start ecosystem.config.js
 *   pm2 save
 */
module.exports = {
    apps: [{
        name: 'alumglass-ws',
        script: 'server.js',
        cwd: __dirname,
        instances: 1,
        exec_mode: 'fork',
        watch: false,
        max_memory_restart: '150M',
        env: {
            NODE_ENV: 'production',
            WS_PORT: 8080,
            PHP_AUTH_URL: process.env.PHP_AUTH_URL || 'http://localhost/chat/api/verify_session.php',
        },
        error_file: '../logs/websocket-error.log',
        out_file: '../logs/websocket.log',
        merge_logs: true,
        time: true,
    }],
};
