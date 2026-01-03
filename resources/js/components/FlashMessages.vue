<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { watch } from 'vue';
import { toast } from 'vue-sonner';

interface Flash {
    success?: string | null;
    error?: string | null;
}

const page = usePage<{ flash: Flash }>();
const lastShown = { success: '', error: '' };

watch(
    () => page.props.flash,
    (flash) => {
        if (!flash) return;

        if (flash.success && flash.success !== lastShown.success) {
            lastShown.success = flash.success;
            toast.success(flash.success);
        }
        if (flash.error && flash.error !== lastShown.error) {
            lastShown.error = flash.error;
            toast.error(flash.error);
        }
    },
    { immediate: true },
);
</script>

<template>
    <div />
</template>
