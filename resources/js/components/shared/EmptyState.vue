<script setup lang="ts">
import type { LucideIcon } from 'lucide-vue-next';

interface Props {
    icon?: LucideIcon;
    title: string;
    description?: string;
}

defineProps<Props>();
</script>

<template>
    <div class="flex flex-col items-center justify-center py-12 text-center">
        <component
            v-if="icon"
            :is="icon"
            class="mb-4 size-12 text-muted-foreground"
        />
        <h3 class="mb-2 text-lg font-semibold">{{ title }}</h3>
        <p v-if="description" class="mb-6 text-sm text-muted-foreground">
            {{ description }}
        </p>
        <slot name="action" />
    </div>
</template>
