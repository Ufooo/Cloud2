<script setup lang="ts">
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import FlashMessages from '@/components/FlashMessages.vue';
import ConfirmationDialog from '@/components/shared/ConfirmationDialog.vue';
import { SidebarInset, SidebarProvider } from '@/components/ui/sidebar';
import { Sonner } from '@/components/ui/sonner';
import type { BreadcrumbItemType } from '@/types';
import { usePage } from '@inertiajs/vue3';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const isOpen = usePage().props.sidebarOpen as boolean;
</script>

<template>
    <SidebarProvider :default-open="isOpen" class="min-h-screen bg-background">
        <AppSidebar />
        <SidebarInset
            class="flex-1 overflow-x-hidden rounded-2xl border border-border bg-card p-6"
        >
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <div class="flex-1">
                <slot />
            </div>
        </SidebarInset>
    </SidebarProvider>
    <ConfirmationDialog />
    <FlashMessages />
    <Sonner position="top-right" :duration="5000" />
</template>
