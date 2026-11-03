// Builds the control panel assets of every feature that has resources/js/cp.ts into that feature's
// resources/dist, where statamic-features publishes them from. `node scripts/build-control-panel.ts dev <Feature>`
// runs the dev server of one feature instead.
import { existsSync, readdirSync } from 'node:fs';
import { resolve } from 'node:path';
import statamic from '@statamic/cms/vite-plugin';
import laravel from 'laravel-vite-plugin';
import { build, createServer, type InlineConfig } from 'vite';

const root = resolve(import.meta.dirname, '..');

const features = readdirSync(resolve(root, 'features'), { withFileTypes: true })
    .filter((entry) => entry.isDirectory() && existsSync(resolve(root, 'features', entry.name, 'resources/js/cp.ts')))
    .map((entry) => entry.name);

// Each feature is built from its own folder, so the manifest keys stay `resources/js/cp.ts`. The plugin writes
// the hot file relative to the working directory, not to that folder, so it gets an absolute path.
const config = (feature: string): InlineConfig => ({
    root: resolve(root, 'features', feature),
    configFile: false,
    plugins: [
        statamic(),
        laravel({
            input: ['resources/js/cp.ts'],
            publicDirectory: 'resources',
            buildDirectory: 'dist',
            hotFile: resolve(root, 'features', feature, 'resources/hot'),
        }),
    ],
});

const [command = 'build', only] = process.argv.slice(2);

if (command === 'dev') {
    if (only === undefined || !features.includes(only)) {
        console.error(`Usage: npm run dev -- <Feature>, one of: ${features.join(', ')}`);
        process.exit(1);
    }

    const server = await createServer(config(only));
    await server.listen();
    server.printUrls();
} else {
    for (const feature of features) {
        await build(config(feature));
    }
}
