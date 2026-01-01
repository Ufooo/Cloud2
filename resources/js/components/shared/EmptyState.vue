<script setup lang="ts">
import type { LucideIcon } from 'lucide-vue-next';

interface Props {
    icon?: LucideIcon;
    title: string;
    description?: string;
    compact?: boolean;
}

withDefaults(defineProps<Props>(), {
    compact: false,
});
</script>

<template>
    <div
        :class="[
            'flex flex-col items-center justify-center text-center',
            compact ? 'rounded-lg border border-dashed p-8' : 'py-12',
        ]"
    >
        <component
            v-if="icon"
            :is="icon"
            class="mb-4 size-12 text-muted-foreground"
            :class="{ 'opacity-50': compact }"
        />
        <h3 class="mb-2 text-lg font-medium">{{ title }}</h3>
        <p v-if="description" class="mb-6 text-sm text-muted-foreground">
            {{ description }}
        </p>
        <slot name="action" />
    </div>
</template>
