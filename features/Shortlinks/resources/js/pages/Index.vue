<script setup>
import { Head, Link, router } from '@statamic/cms/inertia';
import { Button, DropdownItem, EmptyStateItem, EmptyStateMenu, Header, Listing, StatusIndicator } from '@statamic/cms/ui';

defineProps({
    shortlinks: { type: Array, required: true },
    createUrl: { type: String, required: true },
});

const columns = [
    { field: 'slug', label: __('Slug'), visible: true, sortable: true },
    { field: 'destination', label: __('Destination'), visible: true, sortable: true },
    { field: 'description', label: __('Description'), visible: true, sortable: true },
    { field: 'enabled', label: __('Status'), visible: true, sortable: true },
];
</script>

<template>
    <Head :title="__('Shortlinks')" />

    <div class="max-w-page mx-auto">
        <Header :title="__('Shortlinks')" icon="link">
            <Button v-if="can('create shortlinks')" :text="__('Create Shortlink')" :href="createUrl" variant="primary" />
        </Header>

        <EmptyStateMenu v-if="!shortlinks.length" :heading="__('Shortlinks lead from a short URL and its QR code to a page.')">
            <EmptyStateItem
                v-if="can('create shortlinks')"
                :href="createUrl"
                icon="link"
                :heading="__('Create Shortlink')"
                :description="__('Get started by creating your first shortlink.')"
            />
        </EmptyStateMenu>

        <Listing v-else :items="shortlinks" :columns="columns" :allow-customizing-columns="false" @refreshing="router.reload()">
            <template #cell-slug="{ row: shortlink }">
                <div class="flex flex-col gap-0.5">
                    <Link v-if="can('edit shortlinks')" :href="shortlink.edit_url" class="title-index-field">
                        <span v-text="shortlink.slug" />
                    </Link>
                    <span v-else class="title-index-field" v-text="shortlink.slug" />
                    <a :href="shortlink.url" target="_blank" class="text-xs text-gray-500 hover:underline" v-text="shortlink.url" />
                </div>

                <resource-deleter :ref="`deleter_${shortlink.id}`" :resource="shortlink" :resource-title="shortlink.slug" reload />
            </template>
            <template #cell-destination="{ value }">
                <span v-if="value" v-text="value" />
                <span v-else class="text-gray-500">—</span>
            </template>
            <template #cell-enabled="{ value }">
                <span class="flex items-center gap-2">
                    <StatusIndicator :status="value ? 'published' : 'hidden'" />
                    <span v-text="value ? __('Enabled') : __('Disabled')" />
                </span>
            </template>
            <template #prepended-row-actions="{ row: shortlink }">
                <DropdownItem v-if="can('edit shortlinks')" :text="__('Edit')" icon="edit" :href="shortlink.edit_url" />
                <DropdownItem :text="__('Download QR code')" icon="download" as="a" :href="shortlink.qr_url" />
                <DropdownItem
                    v-if="can('delete shortlinks')"
                    :text="__('Delete')"
                    icon="trash"
                    variant="destructive"
                    @click="$refs[`deleter_${shortlink.id}`].confirm()"
                />
            </template>
        </Listing>
    </div>
</template>
