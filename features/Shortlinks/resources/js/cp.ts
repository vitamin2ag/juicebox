import Index from './pages/Index.vue';

Statamic.booting(() => {
    Statamic.$inertia.register('shortlinks::Index', Index);
});
