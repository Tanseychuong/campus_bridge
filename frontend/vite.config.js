import { defineConfig } from 'vite';
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
 */
const apiTarget = process.env.API_PROXY_TARGET || 'http://localhost:8000';

const proxy = {
    '/api': { target: apiTarget, changeOrigin: true },
};

export default defineConfig({
    plugins: [react()],
    server: { port: 5173, proxy },
    preview: { port: 4173, proxy },
});
