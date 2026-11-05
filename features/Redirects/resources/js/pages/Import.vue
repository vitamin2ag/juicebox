<script setup lang="ts">
import { Form, Head } from '@statamic/cms/inertia';
import { Button, CardPanel, Field, Header, Icon, Input } from '@statamic/cms/ui';
import { ref, useTemplateRef } from 'vue';

defineProps<{
    submitUrl: string;
    listingUrl: string;
}>();

const delimiter = ref(',');
const file = useTemplateRef<HTMLInputElement>('file');
const filename = ref<string | null>(null);
const dragging = ref(false);

function chosen(): void {
    filename.value = file.value?.files?.[0]?.name ?? null;
}

function dropped(event: DragEvent): void {
    dragging.value = false;

    if (file.value && event.dataTransfer) {
        file.value.files = event.dataTransfer.files;
    }

    chosen();
}
</script>

<template>
    <Head :title="__('Import Redirects')" />

    <Form :action="submitUrl" method="post" class="max-w-page mx-auto" v-slot="{ errors, processing }">
        <Header :title="__('Import Redirects')" icon="upload">
            <Button :text="__('Cancel')" :href="listingUrl" />
            <Button type="submit" variant="primary" :text="__('Import')" :disabled="processing" />
        </Header>

        <CardPanel :heading="__('CSV file')">
            <div class="space-y-6">
                <Field
                    :label="__('File')"
                    :instructions="
                        __(
                            'The first row names the columns `source`, `destination`, `type` and `match_type`, and optionally `site`, `enabled` and `description`.',
                        )
                    "
                    :error="errors.file"
                    required
                >
                    <input ref="file" type="file" name="file" accept=".csv,text/csv" class="sr-only" @change="chosen" />
                    <div
                        class="flex flex-col items-center gap-2 rounded-lg border border-dashed px-6 py-8 text-center"
                        :class="dragging ? 'border-blue-500 bg-blue-50' : 'border-gray-400'"
                        @dragover.prevent="dragging = true"
                        @dragleave="dragging = false"
                        @drop.prevent="dropped"
                    >
                        <Icon name="upload" class="size-6 text-gray-500" />
                        <p v-if="filename" class="font-medium" v-text="filename" />
                        <div class="flex items-center gap-2 text-sm text-gray-700">
                            <span v-text="__('Drag & drop here or')" />
                            <Button type="button" size="sm" :text="__('choose a file')" @click="file?.click()" />
                        </div>
                    </div>
                </Field>

                <Field :label="__('Delimiter')" :instructions="__('One character, usually `,` or `;`.')" :error="errors.delimiter" required>
                    <div class="w-24">
                        <Input name="delimiter" v-model="delimiter" maxlength="1" />
                    </div>
                </Field>
            </div>
        </CardPanel>
    </Form>
</template>
