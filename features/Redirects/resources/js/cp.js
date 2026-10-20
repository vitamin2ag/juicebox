import Index from './pages/Index.vue';
import Import from './pages/Import.vue';

Statamic.booting(() => {
    Statamic.$inertia.register('redirects::Index', Index);
    Statamic.$inertia.register('redirects::Import', Import);
});
