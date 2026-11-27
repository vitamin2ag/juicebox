<script setup lang="ts">
import { dateFormatter } from '@statamic/cms/api';
import { Head, Link } from '@statamic/cms/inertia';
import { Badge, CardPanel, Description, Header, Table, TableCell, TableColumn, TableColumns, TableRow, TableRows } from '@statamic/cms/ui';

interface Source {
    type: 'entry' | 'term' | 'global' | 'nav';
    title: string;
    editUrl: string | null;
    site: string | null;
    field: string;
    missing: boolean;
}

interface DeadLink {
    url: string;
    reason: string;
    reasonLabel: string;
    status: number | null;
    sources: Source[];
}

defineProps<{
    checkedAt: string | null;
    checked: number;
    links: DeadLink[];
}>();
</script>

<template>
    <Head :title="[__('Dead Links'), __('Utilities')]" />

    <div class="max-w-page mx-auto">
        <Header :title="__('Dead Links')" icon="link">
            <template v-if="checkedAt !== null">
                <Badge :prepend="__('Last check')" :text="dateFormatter.format(checkedAt, 'datetime')" />
                <Badge :prepend="__('Links checked')" :text="checked" />
                <Badge :prepend="__('Broken links')" :text="links.length" :color="links.length > 0 ? 'red' : 'green'" />
            </template>
        </Header>

        <CardPanel v-if="checkedAt === null" :heading="__('No check has run yet')">
            <Description>
                {{ __('Run the check with this command on the server:') }}
                <code>php please juicebox:dead-links:check</code>
            </Description>
        </CardPanel>

        <CardPanel v-else :heading="__('Broken links')">
            <Description v-if="links.length === 0" :text="__('The last check found no broken links.')" />

            <Table v-else>
                <TableColumns>
                    <TableColumn v-text="__('URL')" />
                    <TableColumn v-text="__('Reason')" />
                    <TableColumn v-text="__('Found in')" />
                </TableColumns>
                <TableRows>
                    <TableRow v-for="link in links" :key="link.url">
                        <TableCell class="break-all" v-text="link.url" />
                        <TableCell class="whitespace-nowrap" v-text="link.reasonLabel" />
                        <TableCell>
                            <div class="space-y-1">
                                <div
                                    v-for="(source, index) in link.sources"
                                    :key="index"
                                    class="flex flex-wrap items-center gap-x-2 gap-y-1"
                                >
                                    <Link
                                        v-if="source.editUrl"
                                        :href="source.editUrl"
                                        class="text-blue-600 hover:underline dark:text-blue-400"
                                        v-text="source.title"
                                    />
                                    <span v-else :class="{ 'text-red-600 dark:text-red-400': source.missing }" v-text="source.title" />
                                    <Badge v-if="source.site" size="sm" :prepend="__('Site')" :text="source.site" />
                                    <Badge size="sm" :prepend="__('Field')" :text="source.field" />
                                </div>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableRows>
            </Table>
        </CardPanel>
    </div>
</template>
