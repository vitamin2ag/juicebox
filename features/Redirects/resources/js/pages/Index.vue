<script setup lang="ts">
import { Head, Link, router } from '@statamic/cms/inertia';
import {
    Badge,
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
import { toast } from '@statamic/cms/api';
import { getCurrentInstance, ref, useTemplateRef } from 'vue';

interface Row {
    id: string;
    source: string;
    destination: string | null;
    enabled: boolean;
    edit_url: string;
    destination_entry: { title: string; edit_url: string; status: string } | null;
}

interface Completed {
    items: Row[];
    parameters: { page: number; perPage: number | null };
}

const props = defineProps<{
    hasRedirects: boolean;
    filters: unknown[];
    listingUrl: string;
    createUrl: string;
    importUrl: string;
    exportUrl: string;
    pruneUrl: string;
    actionUrl: string;
    reorderUrl: string;
}>();

// Not named like the ref in the template, or Vue binds the ref to this variable by name.
const listingRef = useTemplateRef<{ refresh(): void }>('listing');
const pruning = ref(false);
const reordering = ref(false);
const saving = ref(false);
const items = ref<Row[]>([]);
const page = ref(1);
const perPage = ref<number | null>(null);

// An exact source is unique per site and found by that source, so only regex redirects depend on their order.
const filtersForReordering = () => ({ redirect_match_type: { match_type: 'regex' } });

function requestCompleted(request: Completed): void {
    items.value = request.items;
    page.value = request.parameters.page;
    perPage.value = request.parameters.perPage;
}

function saveOrder(): void {
    saving.value = true;

    router.post(
        props.reorderUrl,
        { ids: items.value.map((redirect) => redirect.id), page: page.value, perPage: perPage.value },
        {
            preserveScroll: true,
            onSuccess: () => (reordering.value = false),
            onFinish: () => (saving.value = false),
        },
    );
}

const { __ } = getCurrentInstance()!.appContext.config.globalProperties;

// Downloads the file the way Statamic downloads the file of an action, since a visit of the Inertia router would swallow
// it. It fetches without Statamic's axios, whose toast interceptor parses every blob it gets as JSON and fails on a CSV.
// Asking for JSON turns a missing permission or session into an error instead of the login page.
function exportCsv(): void {
    fetch(props.exportUrl, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        .then((response) => (response.ok ? response : Promise.reject(response)))
        .then(async (response) => {
            const url = URL.createObjectURL(await response.blob());
            const link = document.createElement('a');
            link.href = url;
            link.download = response.headers.get('content-disposition')?.match(/filename="?([^";]+)"?/)?.[1] ?? 'redirects.csv';
            link.click();
            setTimeout(() => URL.revokeObjectURL(url));
        })
        .catch(() => toast.error(__('Something went wrong'), {}));
}

function prune(): void {
    router.post(
        props.pruneUrl,
        {},
        {
            // The listing loads its rows from its own URL, so the page props do not reach it. It is gone when no redirect is left.
            onSuccess: () => listingRef.value?.refresh(),
            onFinish: () => (pruning.value = false),
        },
    );
}
</script>

<template>
    <Head :title="__('Redirects')" />

    <div class="max-w-page mx-auto">
        <Header :title="__('Redirects')" icon="arrow-up-right">
            <template v-if="reordering">
                <Button :text="__('Cancel')" @click="reordering = false" />
                <Button variant="primary" :text="__('Save Order')" :disabled="saving" @click="saveOrder" />
            </template>
            <template v-else>
                <Dropdown v-if="hasRedirects || can('create redirects') || can('delete redirects')">
                    <DropdownMenu>
                        <DropdownItem v-if="can('create redirects')" :text="__('Import CSV')" icon="upload" :href="importUrl" />
                        <DropdownItem v-if="hasRedirects" :text="__('Export CSV')" icon="download" @click="exportCsv" />
                        <DropdownItem
                            v-if="can('delete redirects')"
                            :text="__('Delete disabled redirects')"
                            icon="trash"
                            variant="destructive"
                            @click="pruning = true"
                        />
                    </DropdownMenu>
                </Dropdown>
                <Button v-if="can('edit redirects') && hasRedirects" :text="__('Reorder')" @click="reordering = true" />
                <Button v-if="can('create redirects')" :text="__('Create Redirect')" :href="createUrl" variant="primary" />
            </template>
        </Header>

        <p v-if="reordering" class="mb-4 text-sm text-gray-600 dark:text-gray-400">
            {{ __('Only regex redirects are listed, in the order they are tried. The first match wins.') }}
        </p>

        <EmptyStateMenu v-if="!hasRedirects" :heading="__('Redirects send visitors of a missing page to a new URL.')">
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
            ref="listing"
            :url="listingUrl"
            :filters="filters"
            :filters-for-reordering="filtersForReordering"
            :allow-customizing-columns="false"
            :allow-presets="false"
            :allow-select-all-matching="false"
            push-query
            :allow-bulk-actions="can('edit redirects') || can('delete redirects')"
            :action-url="actionUrl"
            :reorderable="reordering"
            :sortable="!reordering"
            @reordered="items = $event"
            @request-completed="requestCompleted"
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
            <template #cell-destination="{ row: redirect }">
                <!-- Linked like the entries of a relationship field in a listing, with the status of the entry. -->
                <Badge v-if="redirect.destination_entry" size="sm" :href="redirect.destination_entry.edit_url">
                    <StatusIndicator :status="redirect.destination_entry.status" class="h-1" />
                    <span v-text="redirect.destination_entry.title" />
                </Badge>
                <span
                    v-else-if="redirect.destination?.startsWith('entry::')"
                    class="text-red-600 dark:text-red-400"
                    :title="redirect.destination"
                    v-text="__('Missing entry')"
                />
                <span v-else-if="redirect.destination" v-text="redirect.destination" />
                <span v-else class="text-gray-500">—</span>
            </template>
            <template #cell-description="{ value }">
                <span v-if="value" class="block max-w-xs truncate" :title="value" v-text="value" />
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
