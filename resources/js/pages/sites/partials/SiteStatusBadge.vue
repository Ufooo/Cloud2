<script setup lang="ts">
import StatusBadge from '@/components/StatusBadge.vue';
import { useStatusBadge, type StatusDefinition } from '@/composables/useStatusBadge';

interface Props {
    status: string;
    iconOnly?: boolean;
}

const props = defineProps<Props>();

const siteStatusDefinitions: Record<string, StatusDefinition> = {
    installed: { type: 'success', label: 'Installed' },
    pending: { type: 'progress', label: 'Pending' },
    installing: { type: 'warning', label: 'Installing' },
    failed: { type: 'error', label: 'Failed' },
    deleting: { type: 'error', label: 'Deleting', pulse: true },
};

const statusConfig = useStatusBadge(
    () => props.status,
    siteStatusDefinitions,
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
