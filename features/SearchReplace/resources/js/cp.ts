import Index from './pages/Index.vue';

Statamic.booting(() => {
    Statamic.$inertia.register('search-replace::Index', Index);
});
