import { defineConfig, loadEnv } from 'vite';
import react from '@vitejs/plugin-react';

/*
 * Vite runs the React app in development and builds it for production.
 *
 * The "proxy" forwards every request for /api/... to the PHP backend, so the
 * browser sees ONE website: the session cookie just works, and no CORS
 * settings are needed. Where the backend lives depends on how you run it:
 *
 *   Docker (docker-compose.yml)   API_PROXY_TARGET=http://api   (set for you)
 *   php -S localhost:8000 ...     http://localhost:8000          (the default)
 *   AMPPS                         http://localhost/campus_bridge/backend/public
 *
 * Set it in the shell, or once in frontend/.env.local (git-ignored):
 *   API_PROXY_TARGET=http://localhost:8010
 */
export default defineConfig(({ mode }) => {
    const fileEnv = loadEnv(mode, process.cwd(), ''); // reads .env, .env.local, ...
    const apiTarget = process.env.API_PROXY_TARGET || fileEnv.API_PROXY_TARGET || 'http://localhost:8000';
    const proxy = {
        '/api': { target: apiTarget, changeOrigin: true },
    };

    // In Docker on Windows/macOS, file-change events do not reach the container,
    // so Vite must check for changes itself (VITE_POLL=1 is set in docker-compose.yml).
    const watch = process.env.VITE_POLL === '1' ? { usePolling: true, interval: 300 } : undefined;

    return {
        plugins: [react()],
        server: { port: 5173, proxy, watch },
        preview: { port: 4173, proxy },
    };
});
