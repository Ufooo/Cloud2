<script setup lang="ts">
import FlashMessages from '@/components/FlashMessages.vue';
import ConfirmationDialog from '@/components/shared/ConfirmationDialog.vue';
import { Sonner } from '@/components/ui/sonner';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <slot />
    </AppLayout>
    <ConfirmationDialog />
    <FlashMessages />
    <Sonner position="top-right" :duration="5000" />
</template>
