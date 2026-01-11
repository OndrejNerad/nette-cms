import { defineConfig } from 'vite';
import path from 'path';

export default defineConfig({
    publicDir: false,

    build: {
        outDir: 'www/assets',
        assetsDir: '',
        emptyOutDir: false,
        cssCodeSplit: false,
        manifest: false,
        rollupOptions: {
            input: path.resolve(__dirname, 'assets/assets.js'),
            output: {
                entryFileNames: 'js/scripts.js',
                assetFileNames: (asset) => {
                    return asset.name.endsWith('.css') ? 'css/style.css' : '[name][extname]';
                }
            }
        }
    },

    server: {
        port: 8010,
        host: '0.0.0.0',
        watch: { usePolling: true, interval: 1000 }
    }
});