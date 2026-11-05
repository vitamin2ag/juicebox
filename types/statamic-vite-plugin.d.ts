// The Vite plugin of @statamic/cms ships as plain JavaScript without declarations.
declare module '@statamic/cms/vite-plugin' {
    import type { PluginOption } from 'vite';

    export default function statamic(): PluginOption;
}
