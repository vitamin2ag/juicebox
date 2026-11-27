import Index from './pages/Index.vue';

Statamic.booting(() => {
    Statamic.$inertia.register('dead-links::Index', Index);
});
