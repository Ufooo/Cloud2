<script setup lang="ts">
import { Badge } from '@/components/ui/badge';

interface Props {
    variant?: 'default' | 'secondary' | 'destructive' | 'outline';
    label: string;
    badgeClass?: string;
    pulse?: boolean;
    iconOnly?: boolean;
}

withDefaults(defineProps<Props>(), {
    variant: 'secondary',
    pulse: false,
    iconOnly: false,
});
</script>

<template>
    <Badge :variant="variant" :class="badgeClass">
        <span v-if="pulse" class="relative flex size-2">
            <span
                class="absolute inline-flex size-full animate-ping rounded-full bg-current opacity-75"
            />
            <span class="relative inline-flex size-2 rounded-full bg-current" />
        </span>
        <span v-if="!iconOnly">{{ label }}</span>
    </Badge>
</template>
