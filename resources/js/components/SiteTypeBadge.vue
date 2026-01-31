<script setup lang="ts">
import SiteTypeIcon from '@/components/icons/SiteTypeIcon.vue';
import { Badge } from '@/components/ui/badge';

interface Props {
    type?: string | null;
    displayableType?: string | null;
    version?: string | null;
}

defineProps<Props>();
</script>

<template>
    <Badge
        v-if="type && displayableType"
        variant="outline"
        class="gap-1.5 bg-muted px-3 py-1.5"
    >
        <SiteTypeIcon :type="type" class="size-5!" />
        <span class="text-sm text-muted-foreground">{{ displayableType }}</span>
        <template v-if="version">
            <span class="text-muted-foreground/60">·</span>
            <span class="text-sm font-medium text-foreground">{{
                version
            }}</span>
        </template>
    </Badge>
</template>
