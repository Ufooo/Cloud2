<script setup lang="ts">
import StatusBadge from '@/components/StatusBadge.vue';
import {
    useStatusBadge,
    type StatusDefinition,
} from '@/composables/useStatusBadge';
import type { ServerStatus } from '@/types';

interface Props {
    status: ServerStatus;
    iconOnly?: boolean;
}

const props = defineProps<Props>();

const serverStatusDefinitions: Record<ServerStatus, StatusDefinition> = {
    connected: { type: 'success', label: 'Connected' },
    connecting: { type: 'progress', label: 'Connecting' },
    provisioning: { type: 'warning', label: 'Provisioning' },
    disconnected: { type: 'error', label: 'Disconnected' },
    deleting: { type: 'error', label: 'Deleting', pulse: true },
    locked: { type: 'neutral', label: 'Locked' },
    resizing: { type: 'progress', label: 'Resizing', color: 'purple' },
    stopping: { type: 'warning', label: 'Stopping', color: 'orange' },
    off: { type: 'neutral', label: 'Off' },
    unknown: { type: 'neutral', label: 'Unknown' },
};

const statusConfig = useStatusBadge(
    () => props.status,
    serverStatusDefinitions,
);
</script>

<template>
    <StatusBadge
        :variant="statusConfig.variant"
        :badge-class="statusConfig.class"
        :label="statusConfig.label"
        :pulse="statusConfig.pulse"
        :icon-only="iconOnly"
    />
</template>
