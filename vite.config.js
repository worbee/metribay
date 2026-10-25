import { defineConfig } from 'vite';
import { resolve } from 'node:path';
import { copyFileSync, readFileSync, writeFileSync } from 'node:fs';

export default defineConfig({
    build: {
        outDir: 'dist',
        emptyOutDir: true,
        cssCodeSplit: false,
        minify: true,
        lib: {
            entry: resolve(import.meta.dirname, 'resources/js/app.js'),
            name: 'Metribay',
            formats: ['es'],
            fileName: () => 'app.js',
            cssFileName: 'app',
        },
    },
    plugins: [{
        name: 'metribay-brand-assets',
        closeBundle() {
            const licenses = ['chart.js', '@kurkle/color'].map(name => `${name}\n${readFileSync(resolve(import.meta.dirname, `node_modules/${name}/LICENSE.md`), 'utf8')}`).join('\n\n---\n\n');
            writeFileSync(resolve(import.meta.dirname, 'dist/THIRD_PARTY_LICENSES.txt'), licenses);
            for (const theme of ['light', 'dark']) {
                copyFileSync(resolve(import.meta.dirname, `resources/images/metribay-${theme}.svg`), resolve(import.meta.dirname, `dist/metribay-${theme}.svg`));
            }
        },
    }],
});
