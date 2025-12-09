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
import { Input } from '@/components/ui/input';
import { useConfirmation } from '@/composables/useConfirmation';
import { ref, watch } from 'vue';

const { isOpen, currentOptions, currentInputOptions, confirm, cancel } =
    useConfirmation();

const inputValue = ref('');

watch(isOpen, (open) => {
    if (open) {
        inputValue.value = '';
    }
});

const isInputMode = () => currentInputOptions.value !== null;
const options = () => currentInputOptions.value ?? currentOptions.value;

const canConfirm = () => {
    if (!isInputMode()) return true;
    return inputValue.value === currentInputOptions.value?.value;
};
</script>

<template>
    <Dialog :open="isOpen" @update:open="(open) => !open && cancel()">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ options()?.title }}</DialogTitle>
                <DialogDescription>{{
                    options()?.description
                }}</DialogDescription>
            </DialogHeader>

            <div v-if="isInputMode()" class="py-4">
                <Input
                    v-model="inputValue"
                    :placeholder="currentInputOptions?.value"
                    @keyup.enter="canConfirm() && confirm()"
                />
            </div>

            <DialogFooter>
                <Button variant="outline" @click="cancel">
                    {{ options()?.cancelText ?? 'Cancel' }}
                </Button>
                <Button
                    variant="destructive"
                    :disabled="!canConfirm()"
                    @click="confirm"
                >
                    {{ options()?.confirmText ?? 'Confirm' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
