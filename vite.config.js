import { defineConfig } from 'vite';
import { resolve } from 'node:path';
import { copyFileSync, existsSync, readFileSync, writeFileSync } from 'node:fs';

// Bundled packages name their license file differently, and the name can change between releases.
function licenseText(name) {
    const file = ['LICENSE', 'LICENSE.md', 'LICENSE.txt']
        .map(candidate => resolve(import.meta.dirname, `node_modules/${name}/${candidate}`))
        .find(path => existsSync(path));
    if (!file) {
        throw new Error(`No license file found for bundled package ${name}`);
    }

    return readFileSync(file, 'utf8');
}

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
            const licenses = ['chart.js', '@kurkle/color', 'vanilla-cookieconsent'].map(name => `${name}\n${licenseText(name)}`).join('\n\n---\n\n');
            writeFileSync(resolve(import.meta.dirname, 'dist/THIRD_PARTY_LICENSES.txt'), licenses);
            for (const file of ['metribay-lockup-light.svg', 'metribay-lockup-dark.svg', 'metribay-favicon.svg', 'apple-touch-icon.png']) {
                copyFileSync(resolve(import.meta.dirname, `resources/images/${file}`), resolve(import.meta.dirname, `dist/${file}`));
            }
        },
    }],
});
