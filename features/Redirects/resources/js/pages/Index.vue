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
import { ref } from 'vue';

const props = defineProps({
    redirects: { type: Array, required: true },
    createUrl: { type: String, required: true },
    importUrl: { type: String, required: true },
    pruneUrl: { type: String, required: true },
});

const columns = [
    { field: 'source', label: __('Source'), visible: true, sortable: true },
    { field: 'destination', label: __('Destination'), visible: true, sortable: true },
    { field: 'type', label: __('Type'), visible: true, sortable: true },
    { field: 'match_type', label: __('Match type'), visible: true, sortable: true },
];

const pruning = ref(false);

function prune() {
    router.post(props.pruneUrl, {}, { onFinish: () => (pruning.value = false) });
}
</script>

<template>
    <Head :title="__('Redirects')" />

    <div class="max-w-page mx-auto">
        <Header :title="__('Redirects')" icon="arrow-up-right">
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
            <Button v-if="can('create redirects')" :text="__('Create Redirect')" :href="createUrl" variant="primary" />
        </Header>

        <EmptyStateMenu v-if="!redirects.length" :heading="__('Redirects send visitors of a missing page to a new URL.')">
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

        <Listing v-else :items="redirects" :columns="columns" :allow-customizing-columns="false" @refreshing="router.reload()">
            <template #cell-source="{ row: redirect }">
                <Link v-if="can('edit redirects')" :href="redirect.edit_url" class="title-index-field">
                    <StatusIndicator :status="redirect.enabled ? 'published' : 'hidden'" />
                    <span v-text="redirect.source" />
                </Link>
                <span v-else class="title-index-field">
                    <StatusIndicator :status="redirect.enabled ? 'published' : 'hidden'" />
                    <span v-text="redirect.source" />
                </span>

                <resource-deleter :ref="`deleter_${redirect.id}`" :resource="redirect" :resource-title="redirect.source" reload />
            </template>
            <template #cell-destination="{ value }">
                <span v-if="value" v-text="value" />
                <span v-else class="text-gray-500">—</span>
            </template>
            <template #prepended-row-actions="{ row: redirect }">
                <DropdownItem v-if="can('edit redirects')" :text="__('Edit')" icon="edit" :href="redirect.edit_url" />
                <DropdownItem
                    v-if="can('delete redirects')"
                    :text="__('Delete')"
                    icon="trash"
                    variant="destructive"
                    @click="$refs[`deleter_${redirect.id}`].confirm()"
                />
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
