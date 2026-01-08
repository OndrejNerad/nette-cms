import { defineConfig } from 'vite';

export default defineConfig({
    build: {
        outDir: 'www/assets',
        rollupOptions: {
            input: {
                main: 'assets/js/script.js',
                styles: 'assets/scss/index.scss'
            },
            output: {
                entryFileNames: 'js/script.js',
                assetFileNames: 'css/style.css'
            }
        }
    },
    cssCodeSplit: false
});