<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { useDatabaseVersionBadge } from '@/composables/useDatabaseVersionBadge';

interface Props {
    type: string | null;
}

const props = defineProps<Props>();

const badgeConfig = useDatabaseVersionBadge(() => props.type);
</script>

<template>
    <Badge v-if="badgeConfig" variant="custom" :class="badgeConfig.class">
        {{ badgeConfig.label }}
    </Badge>
</template>
