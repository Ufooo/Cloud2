<script setup lang="ts">
import { useAppearance } from '@/composables/useAppearance';
import { Toaster as Sonner, type ToasterProps } from 'vue-sonner';

const props = defineProps<ToasterProps>();

const { appearance } = useAppearance();
</script>

<template>
    <Sonner
        :theme="appearance"
        class="toaster group"
        :toast-options="{
            classes: {
                toast: 'group toast group-[.toaster]:bg-background group-[.toaster]:text-foreground group-[.toaster]:border-border group-[.toaster]:shadow-lg',
                description: 'group-[.toast]:text-muted-foreground',
                actionButton:
                    'group-[.toast]:bg-primary group-[.toast]:text-primary-foreground',
                cancelButton:
                    'group-[.toast]:bg-muted group-[.toast]:text-muted-foreground',
            },
        }"
        v-bind="props"
    />
</template>
