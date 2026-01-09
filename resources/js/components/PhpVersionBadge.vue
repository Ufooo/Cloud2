<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { usePhpVersionBadge } from '@/composables/usePhpVersionBadge';

interface Props {
    version: string | null;
}

const props = defineProps<Props>();

const badgeConfig = usePhpVersionBadge(() => props.version);
</script>

<template>
    <Badge v-if="badgeConfig" variant="custom" :class="badgeConfig.class">
        {{ badgeConfig.label }}
    </Badge>
</template>
