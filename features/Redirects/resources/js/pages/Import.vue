<script setup>
import { Form, Head } from '@statamic/cms/inertia';
import { Button, CardPanel, Field, Header, Input } from '@statamic/cms/ui';
import { ref } from 'vue';

defineProps({
    submitUrl: { type: String, required: true },
    listingUrl: { type: String, required: true },
});

const delimiter = ref(',');
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
                    :instructions="__('The first row names the columns `source`, `destination`, `type` and `match_type`, and optionally `site`, `enabled` and `description`.')"
                    :error="errors.file"
                    required
                >
                    <Input name="file" type="file" accept=".csv,text/csv" />
                </Field>

                <Field
                    :label="__('Delimiter')"
                    :instructions="__('One character, usually `,` or `;`.')"
                    :error="errors.delimiter"
                    required
                >
                    <Input name="delimiter" v-model="delimiter" />
                </Field>
            </div>
        </CardPanel>
    </Form>
</template>
