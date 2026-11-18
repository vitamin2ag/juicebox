import GeoCoordinates from './fieldtypes/GeoCoordinates.vue';

Statamic.booting(() => {
    Statamic.$components.register('geo_coordinates-fieldtype', GeoCoordinates);
});
