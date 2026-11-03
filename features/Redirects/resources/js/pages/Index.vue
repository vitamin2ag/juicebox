<script setup>
import { Head, Link, router } from '@statamic/cms/inertia';
import {
    Button,
    ConfirmationModal,
    Dropdown,
    DropdownItem,
    DropdownMenu,
    EmptyStateItem,
    EmptyStateMenu,
    Header,
    Listing,
    StatusIndicator,
} from '@statamic/cms/ui';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    redirects: { type: Array, required: true },
    createUrl: { type: String, required: true },
    importUrl: { type: String, required: true },
    pruneUrl: { type: String, required: true },
    actionUrl: { type: String, required: true },
    reorderUrl: { type: String, required: true },
});

const columns = [
    { field: 'source', label: __('Source'), visible: true, sortable: true },
    { field: 'destination', label: __('Destination'), visible: true, sortable: true },
    { field: 'type', label: __('Type'), visible: true, sortable: true },
    { field: 'match_type', label: __('Match type'), visible: true, sortable: true },
];

const pruning = ref(false);
const items = ref(props.redirects);
const reordering = ref(false);
const saving = ref(false);

// An exact source is unique per site and found by that source, so only regex redirects depend on their order.
const regexRedirects = computed(() =>
    props.redirects
        .filter((redirect) => redirect.match_type === 'regex')
        // In the order they are tried in, where a redirect without an order comes first.
        .sort((a, b) => (a.order ?? 0) - (b.order ?? 0)),
);

watch(() => props.redirects, (redirects) => (items.value = reordering.value ? regexRedirects.value : redirects));

function startReordering() {
    items.value = regexRedirects.value;
    reordering.value = true;
}

function cancelReordering() {
    items.value = props.redirects;
    reordering.value = false;
}

function saveOrder() {
    saving.value = true;

    router.post(
        props.reorderUrl,
        { ids: items.value.map((redirect) => redirect.id) },
        {
            preserveScroll: true,
            onSuccess: () => (reordering.value = false),
            onFinish: () => (saving.value = false),
        },
    );
}

function prune() {
    router.post(props.pruneUrl, {}, { onFinish: () => (pruning.value = false) });
}
</script>

<template>
    <Head :title="__('Redirects')" />

    <div class="max-w-page mx-auto">
        <Header :title="__('Redirects')" icon="arrow-up-right">
            <template v-if="reordering">
                <Button :text="__('Cancel')" @click="cancelReordering" />
                <Button variant="primary" :text="__('Save Order')" :disabled="saving" @click="saveOrder" />
            </template>
            <template v-else>
                <Dropdown v-if="can('create redirects') || can('delete redirects')">
                    <DropdownMenu>
                        <DropdownItem v-if="can('create redirects')" :text="__('Import CSV')" icon="upload" :href="importUrl" />
                        <DropdownItem
                            v-if="can('delete redirects')"
                            :text="__('Delete disabled redirects')"
                            icon="trash"
                            variant="destructive"
                            @click="pruning = true"
                        />
                    </DropdownMenu>
                </Dropdown>
                <Button v-if="can('edit redirects') && regexRedirects.length > 1" :text="__('Reorder')" @click="startReordering" />
                <Button v-if="can('create redirects')" :text="__('Create Redirect')" :href="createUrl" variant="primary" />
            </template>
        </Header>

        <p v-if="reordering" class="mb-4 text-sm text-gray-600 dark:text-gray-400">
            {{ __('Only regex redirects are reordered, since an exact redirect is found by its source.') }}
        </p>

        <EmptyStateMenu v-if="!items.length" :heading="__('Redirects send visitors of a missing page to a new URL.')">
            <EmptyStateItem
                v-if="can('create redirects')"
                :href="createUrl"
                icon="arrow-up-right"
                :heading="__('Create Redirect')"
                :description="__('Get started by creating your first redirect.')"
            />
            <EmptyStateItem
                v-if="can('create redirects')"
                :href="importUrl"
                icon="upload"
                :heading="__('Import CSV')"
                :description="__('Import the redirects of a CSV file.')"
            />
        </EmptyStateMenu>

        <Listing
            v-else
            :items="items"
            :columns="columns"
            :allow-customizing-columns="false"
            :allow-bulk-actions="can('edit redirects') || can('delete redirects')"
            :action-url="actionUrl"
            :reorderable="reordering"
            :sortable="!reordering"
            @reordered="items = $event"
            @refreshing="router.reload()"
        >
            <template #cell-source="{ row: redirect }">
                <Link v-if="can('edit redirects')" :href="redirect.edit_url" class="title-index-field">
                    <StatusIndicator :status="redirect.enabled ? 'published' : 'hidden'" />
                    <span v-text="redirect.source" />
                </Link>
                <span v-else class="title-index-field">
                    <StatusIndicator :status="redirect.enabled ? 'published' : 'hidden'" />
                    <span v-text="redirect.source" />
                </span>
            </template>
            <template #cell-destination="{ value }">
                <span v-if="value" v-text="value" />
                <span v-else class="text-gray-500">—</span>
            </template>
            <template #prepended-row-actions="{ row: redirect }">
                <DropdownItem v-if="can('edit redirects')" :text="__('Edit')" icon="edit" :href="redirect.edit_url" />
            </template>
        </Listing>

        <ConfirmationModal
            v-model:open="pruning"
            :title="__('Delete disabled redirects')"
            :body-text="__('This deletes the disabled redirects of every site.')"
            :button-text="__('Delete')"
            danger
            @confirm="prune"
        />
    </div>
</template>
