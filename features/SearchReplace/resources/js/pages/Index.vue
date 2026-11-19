<script setup lang="ts">
import { dateFormatter } from '@statamic/cms/api';
import { Head, Link, router, usePoll } from '@statamic/cms/inertia';
import {
    Alert,
    Badge,
    Button,
    CardPanel,
    Checkbox,
    ConfirmationModal,
    Description,
    ErrorMessage,
    Field,
    Header,
    Input,
    Table,
    TableCell,
    TableColumn,
    TableColumns,
    TableRow,
    TableRows,
} from '@statamic/cms/ui';
import { computed, reactive, ref, watch } from 'vue';

interface Occurrence {
    id: string;
    field: string;
    before: string;
    match: string;
    after: string;
}

interface Item {
    type: 'entry' | 'term' | 'global';
    title: string;
    editUrl: string | null;
    site: string | null;
    occurrences: Occurrence[];
}

interface SkippedItem {
    type: 'entry' | 'term' | 'global';
    title: string;
    editUrl: string | null;
    site: string | null;
    reason: string;
}

interface Batch {
    id: string;
    createdAt: string;
    user: string | null;
    search: string;
    replacement: string;
    pending: boolean;
    appliedAt: string | null;
    replaced: number | null;
    items: number | null;
    skipped: SkippedItem[];
}

const props = defineProps<{
    search: string;
    matchCase: boolean;
    searchUrl: string;
    replaceUrl: string;
    results: { truncated: boolean; items: Item[] } | null;
    batches: Batch[];
    errors: Record<string, string>;
}>();

const form = reactive({ search: props.search, matchCase: props.matchCase, replacement: '' });
const selected = ref<string[]>([]);
const confirming = ref(false);
const replacing = ref(false);

const occurrenceIds = computed(() => props.results?.items.flatMap((item) => item.occurrences.map((occurrence) => occurrence.id)) ?? []);
// The ids belong to the search the results came from, so an edited search has to run before its matches are replaced.
const searchChanged = computed(() => form.search !== props.search || form.matchCase !== props.matchCase);

watch(occurrenceIds, (ids) => {
    selected.value = selected.value.filter((id) => ids.includes(id));
});

function find(): void {
    router.get(props.searchUrl, form.matchCase ? { search: form.search, match_case: 1 } : { search: form.search }, { preserveState: true });
}

function toggle(id: string, checked: boolean): void {
    selected.value = checked ? [...selected.value, id] : selected.value.filter((selectedId) => selectedId !== id);
}

function replace(): void {
    replacing.value = true;

    router.post(
        props.replaceUrl,
        { search: props.search, replacement: form.replacement, match_case: props.matchCase, occurrences: selected.value },
        {
            preserveState: 'errors',
            preserveScroll: 'errors',
            onFinish: () => {
                replacing.value = false;
                confirming.value = false;
            },
        },
    );
}

const poll = usePoll(5000, { only: ['batches'] }, { autoStart: false });

watch(
    () => props.batches.some((batch) => batch.pending),
    (pending) => (pending ? poll.start() : poll.stop()),
    { immediate: true },
);
</script>

<template>
    <Head :title="[__('Search & Replace'), __('Utilities')]" />

    <div class="max-w-page mx-auto space-y-6">
        <Header :title="__('Search & Replace')" icon="replace" />

        <CardPanel :heading="__('Search')">
            <form class="space-y-6" @submit.prevent="find">
                <div class="grid gap-4 md:grid-cols-2">
                    <Field :label="__('Search')" :error="errors.search">
                        <Input v-model="form.search" name="search" />
                    </Field>
                    <Field :label="__('Replace with')" :error="errors.replacement">
                        <Input v-model="form.replacement" name="replacement" />
                    </Field>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <Checkbox v-model="form.matchCase" :label="__('Match case')" />
                    <Button type="submit" variant="primary" :text="__('Search')" :disabled="form.search === ''" />
                </div>
            </form>
        </CardPanel>

        <CardPanel v-if="results !== null" :heading="__('Matches')">
            <Description v-if="results.items.length === 0" :text="__('No text field contains the search term.')" />

            <div v-else class="space-y-6">
                <Alert
                    v-if="results.truncated"
                    variant="warning"
                    :text="__('The search found more than 500 matches and lists the first 500. Narrow the search to see the others.')"
                />

                <div class="space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <Button size="sm" :text="__('Select All')" @click="selected = [...occurrenceIds]" />
                        <Button size="sm" :text="__('Deselect All')" :disabled="selected.length === 0" @click="selected = []" />
                        <Badge :prepend="__('Selected')" :text="selected.length" />
                        <Button
                            class="ms-auto"
                            variant="primary"
                            :text="__('Replace selected')"
                            :disabled="selected.length === 0 || searchChanged"
                            @click="confirming = true"
                        />
                    </div>
                    <Description v-if="searchChanged" :text="__('The search has changed. Search again to replace its matches.')" />
                    <ErrorMessage v-if="errors.occurrences" :text="errors.occurrences" />
                </div>

                <div v-for="(item, index) in results.items" :key="index" class="space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <Link
                            v-if="item.editUrl"
                            :href="item.editUrl"
                            class="font-medium text-blue-600 hover:underline dark:text-blue-400"
                            v-text="item.title"
                        />
                        <span v-else class="font-medium" v-text="item.title" />
                        <Badge v-if="item.site" size="sm" :prepend="__('Site')" :text="item.site" />
                    </div>
                    <Checkbox
                        v-for="occurrence in item.occurrences"
                        :key="occurrence.id"
                        align="start"
                        :model-value="selected.includes(occurrence.id)"
                        @update:model-value="toggle(occurrence.id, $event)"
                    >
                        <span class="flex items-start gap-2">
                            <Badge size="sm" :text="occurrence.field" />
                            <!-- The control panel ships no whitespace-pre-wrap class; the spaces of a match are part of it. -->
                            <span style="white-space: pre-wrap"
                                ><span v-text="occurrence.before" /><del
                                    class="bg-red-100 text-red-800 dark:bg-red-300/6 dark:text-red-300"
                                    v-text="occurrence.match" /><ins
                                    class="bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-300"
                                    v-text="form.replacement" /><span v-text="occurrence.after"
                            /></span>
                        </span>
                    </Checkbox>
                </div>
            </div>
        </CardPanel>

        <CardPanel v-if="batches.length > 0" :heading="__('Batches')">
            <Table>
                <TableColumns>
                    <TableColumn v-text="__('Date')" />
                    <TableColumn v-text="__('User')" />
                    <TableColumn v-text="__('Replacement')" />
                    <TableColumn v-text="__('Status')" />
                    <TableColumn v-text="__('Replaced')" />
                    <TableColumn v-text="__('Skipped')" />
                </TableColumns>
                <TableRows>
                    <TableRow v-for="batch in batches" :key="batch.id">
                        <TableCell class="whitespace-nowrap" v-text="dateFormatter.format(batch.createdAt, 'datetime')" />
                        <TableCell v-text="batch.user ?? '—'" />
                        <TableCell>
                            <span style="white-space: pre-wrap"
                                ><del class="bg-red-100 text-red-800 dark:bg-red-300/6 dark:text-red-300" v-text="batch.search" /> →
                                <ins
                                    class="bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-300"
                                    v-text="batch.replacement"
                            /></span>
                        </TableCell>
                        <TableCell>
                            <Badge v-if="batch.pending" color="amber" :text="__('Pending')" />
                            <div v-else class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <Badge color="green" :text="__('Applied')" />
                                <span
                                    v-if="batch.appliedAt"
                                    class="text-sm whitespace-nowrap text-gray-600 dark:text-gray-400"
                                    v-text="dateFormatter.format(batch.appliedAt, 'datetime')"
                                />
                            </div>
                        </TableCell>
                        <TableCell v-text="batch.replaced ?? '—'" />
                        <TableCell>
                            <div class="space-y-1">
                                <div
                                    v-for="(skipped, index) in batch.skipped"
                                    :key="index"
                                    class="flex flex-wrap items-center gap-x-2 gap-y-1"
                                >
                                    <Link
                                        v-if="skipped.editUrl"
                                        :href="skipped.editUrl"
                                        class="text-blue-600 hover:underline dark:text-blue-400"
                                        v-text="skipped.title"
                                    />
                                    <span v-else v-text="skipped.title" />
                                    <Badge v-if="skipped.site" size="sm" :prepend="__('Site')" :text="skipped.site" />
                                    <span class="text-sm text-gray-600 dark:text-gray-400" v-text="skipped.reason" />
                                </div>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableRows>
            </Table>
        </CardPanel>

        <ConfirmationModal
            v-model:open="confirming"
            :title="__('Replace selected')"
            :body-text="__('The selected matches are replaced at the next scheduled run.')"
            :button-text="__('Replace')"
            :busy="replacing"
            @confirm="replace"
        />
    </div>
</template>
