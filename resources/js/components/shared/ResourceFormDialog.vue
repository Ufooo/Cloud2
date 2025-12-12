<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Props {
    title: string;
    description: string;
    submitText?: string;
    processingText?: string;
    formAction: {
        action: string;
        method: string;
    };
}

withDefaults(defineProps<Props>(), {
    submitText: 'Create',
    processingText: 'Creating...',
});

const emit = defineEmits<{
    (e: 'success'): void;
}>();

const isOpen = ref(false);

function open() {
    isOpen.value = true;
}

function close() {
    isOpen.value = false;
}

function onSuccess() {
    isOpen.value = false;
    emit('success');
}

defineExpose({ open, close });
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
            </DialogHeader>

            <Form
                v-bind="formAction"
                class="space-y-4"
                :on-success="onSuccess"
                reset-on-success
                v-slot="{ errors, processing }"
            >
                <slot :errors="errors" :processing="processing" />

                <DialogFooter>
                    <Button type="button" variant="outline" @click="close">
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="processing">
                        {{ processing ? processingText : submitText }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
