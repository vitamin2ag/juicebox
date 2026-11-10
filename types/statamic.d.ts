// What the control panel puts on the page and in every template, which @statamic/cms does not declare. Written by hand
// from how the pages use it, so `npm run typecheck` is only as right as this file: keep it in step with Statamic.
import type { Component } from 'vue';

declare global {
    const Statamic: {
        booting(callback: () => void): void;
        $inertia: {
            register(name: string, component: Component): void;
        };
    };
}

declare module 'vue' {
    interface ComponentCustomProperties {
        __: (key: string, replacements?: Record<string, unknown>) => string;
        can: (permission: string) => boolean;
    }
}

// The package exports its core components at the root, as `src/index.js` does, but types them under `core` only.
declare module '@statamic/cms' {
    export const InlineEditForm: typeof core.InlineEditForm;
}

export {};
