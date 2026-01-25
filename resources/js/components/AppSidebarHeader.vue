<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <header v-if="breadcrumbs && breadcrumbs.length > 0" class="mx-6 mt-6">
        <Breadcrumbs :breadcrumbs="breadcrumbs" />
    </header>
</template>
