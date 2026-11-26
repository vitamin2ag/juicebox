<script setup lang="ts">
import { InlineEditForm } from '@statamic/cms';
import { Head, Link } from '@statamic/cms/inertia';
import { Badge, Button, DropdownItem, EmptyStateItem, EmptyStateMenu, Header, Listing, StatusIndicator } from '@statamic/cms/ui';
import { ref, useTemplateRef } from 'vue';

interface Row {
    id: string;
    slug: string;
    destination: string | null;
    enabled: boolean;
    url: string;
    edit_url: string;
    qr_url: string;
    destination_entry: { title: string; edit_url: string; status: string } | null;
}

defineProps<{
    hasShortlinks: boolean;
    listingUrl: string;
    createUrl: string;
    actionUrl: string;
    entryForm: { formComponent: string; formComponentProps: Record<string, string>; formStackSize: string | null };
}>();

// Not named like the ref in the template, or Vue binds the ref to this variable by name.
const listingRef = useTemplateRef<{ refresh(): void }>('listing');
// The entry of a destination open in a stack, as the entries fieldtype opens its entries.
const editingEntry = ref<Row['destination_entry']>(null);
</script>

<template>
    <Head :title="__('Shortlinks')" />

    <div class="max-w-page mx-auto">
        <Header :title="__('Shortlinks')" icon="link">
            <Button v-if="can('create shortlinks')" :text="__('Create Shortlink')" :href="createUrl" variant="primary" />
        </Header>

        <EmptyStateMenu v-if="!hasShortlinks" :heading="__('Shortlinks lead from a short URL and its QR code to a page.')">
            <EmptyStateItem
                v-if="can('create shortlinks')"
                :href="createUrl"
                icon="link"
                :heading="__('Create Shortlink')"
                :description="__('Get started by creating your first shortlink.')"
            />
        </EmptyStateMenu>

        <Listing
            v-else
            ref="listing"
            :url="listingUrl"
            :allow-customizing-columns="false"
            :allow-presets="false"
            :allow-select-all-matching="false"
            push-query
            :allow-bulk-actions="can('delete shortlinks')"
            :action-url="actionUrl"
        >
            <template #cell-slug="{ row: shortlink }">
                <div class="flex flex-col gap-0.5">
                    <Link v-if="can('edit shortlinks')" :href="shortlink.edit_url" class="title-index-field">
                        <StatusIndicator :status="shortlink.enabled ? 'published' : 'hidden'" />
                        <span v-text="shortlink.slug" />
                    </Link>
                    <span v-else class="title-index-field">
                        <StatusIndicator :status="shortlink.enabled ? 'published' : 'hidden'" />
                        <span v-text="shortlink.slug" />
                    </span>
                    <a :href="shortlink.url" target="_blank" class="text-xs text-gray-500 hover:underline" v-text="shortlink.url" />
                </div>
            </template>
            <template #cell-destination="{ row: shortlink }">
                <!-- With an href, Badge renders an Inertia link, which leaves the list whatever the click handler does. -->
                <Badge v-if="shortlink.destination_entry" as="button" size="sm" @click="editingEntry = shortlink.destination_entry">
                    <StatusIndicator :status="shortlink.destination_entry.status" class="h-1" />
                    <span v-text="shortlink.destination_entry.title" />
                </Badge>
                <span
                    v-else-if="shortlink.destination?.startsWith('entry::')"
                    class="text-red-600 dark:text-red-400"
                    :title="shortlink.destination"
                    v-text="__('Missing entry')"
                />
                <span v-else-if="shortlink.destination" v-text="shortlink.destination" />
                <span v-else class="text-gray-500">—</span>
            </template>
            <template #cell-description="{ value }">
                <span v-if="value" class="block max-w-xs truncate" :title="value" v-text="value" />
            </template>
            <template #prepended-row-actions="{ row: shortlink }">
                <DropdownItem v-if="can('edit shortlinks')" :text="__('Edit')" icon="edit" :href="shortlink.edit_url" />
                <DropdownItem :text="__('Download QR code')" icon="download" as="a" :href="shortlink.qr_url" />
            </template>
        </Listing>

        <InlineEditForm
            v-if="editingEntry"
            :item="editingEntry"
            :component="entryForm.formComponent"
            :component-props="entryForm.formComponentProps"
            :stack-size="entryForm.formStackSize ?? undefined"
            @updated="listingRef?.refresh()"
            @closed="editingEntry = null"
        />
    </div>
</template>
