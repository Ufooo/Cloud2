<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { computed } from 'vue';

interface Props {
    status: string;
    iconOnly?: boolean;
}

const props = defineProps<Props>();

const statusConfig = computed(() => {
    const configs: Record<
        string,
        {
            variant: 'default' | 'secondary' | 'destructive' | 'outline';
            class: string;
            label: string;
            pulse: boolean;
        }
    > = {
        installed: {
            variant: 'default',
            class: 'bg-green-500/10 text-green-600 border-green-500/20 dark:bg-green-500/20 dark:text-green-400',
            label: 'Installed',
            pulse: false,
        },
        pending: {
            variant: 'default',
            class: 'bg-blue-500/10 text-blue-600 border-blue-500/20 dark:bg-blue-500/20 dark:text-blue-400',
            label: 'Pending',
            pulse: true,
        },
        installing: {
            variant: 'default',
            class: 'bg-yellow-500/10 text-yellow-600 border-yellow-500/20 dark:bg-yellow-500/20 dark:text-yellow-400',
            label: 'Installing',
            pulse: true,
        },
        failed: {
            variant: 'destructive',
            class: 'bg-red-500/10 text-red-600 border-red-500/20 dark:bg-red-500/20 dark:text-red-400',
            label: 'Failed',
            pulse: false,
        },
        deleting: {
            variant: 'destructive',
            class: '',
            label: 'Deleting',
            pulse: true,
        },
    };

    return (
        configs[props.status] ?? {
            variant: 'secondary' as const,
            class: '',
            label: props.status.charAt(0).toUpperCase() + props.status.slice(1),
            pulse: false,
        }
    );
});
</script>

<template>
    <Badge :variant="statusConfig.variant" :class="statusConfig.class">
        <span v-if="statusConfig.pulse" class="relative flex size-2">
            <span
                class="absolute inline-flex size-full animate-ping rounded-full bg-current opacity-75"
            />
            <span class="relative inline-flex size-2 rounded-full bg-current" />
        </span>
        <span v-if="!props.iconOnly">{{ statusConfig.label }}</span>
    </Badge>
</template>
