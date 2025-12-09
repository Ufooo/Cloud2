<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { ServerStatus } from '@/types';
import { computed } from 'vue';

interface Props {
    status: ServerStatus;
    iconOnly?: boolean;
}

const props = defineProps<Props>();

const statusConfig = computed(() => {
    const configs = {
        connected: {
            variant: 'default' as const,
            class: 'bg-green-500/10 text-green-600 border-green-500/20 dark:bg-green-500/20 dark:text-green-400',
            label: 'Connected',
            pulse: false,
        },
        connecting: {
            variant: 'default' as const,
            class: 'bg-blue-500/10 text-blue-600 border-blue-500/20 dark:bg-blue-500/20 dark:text-blue-400',
            label: 'Connecting',
            pulse: true,
        },
        provisioning: {
            variant: 'default' as const,
            class: 'bg-yellow-500/10 text-yellow-600 border-yellow-500/20 dark:bg-yellow-500/20 dark:text-yellow-400',
            label: 'Provisioning',
            pulse: true,
        },
        disconnected: {
            variant: 'destructive' as const,
            class: 'bg-red-500/10 text-red-600 border-red-500/20 dark:bg-red-500/20 dark:text-red-400',
            label: 'Disconnected',
            pulse: false,
        },
        deleting: {
            variant: 'destructive' as const,
            class: '',
            label: 'Deleting',
            pulse: true,
        },
        locked: {
            variant: 'secondary' as const,
            class: '',
            label: 'Locked',
            pulse: false,
        },
        resizing: {
            variant: 'default' as const,
            class: 'bg-purple-500/10 text-purple-600 border-purple-500/20 dark:bg-purple-500/20 dark:text-purple-400',
            label: 'Resizing',
            pulse: true,
        },
        stopping: {
            variant: 'default' as const,
            class: 'bg-orange-500/10 text-orange-600 border-orange-500/20 dark:bg-orange-500/20 dark:text-orange-400',
            label: 'Stopping',
            pulse: true,
        },
        off: {
            variant: 'secondary' as const,
            class: '',
            label: 'Off',
            pulse: false,
        },
        unknown: {
            variant: 'secondary' as const,
            class: '',
            label: 'Unknown',
            pulse: false,
        },
    };

    return (
        configs[props.status] || {
            variant: 'secondary' as const,
            class: '',
            label: props.status,
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
