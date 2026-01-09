<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { useRepositoryProvider } from '@/composables/useRepositoryProvider';

interface Props {
    provider?: string | null;
    repository: string;
    branch?: string | null;
}

const props = defineProps<Props>();

const providerConfig = useRepositoryProvider(() => props.provider);
</script>

<template>
    <Badge variant="outline" class="gap-2 px-3 py-1.5 bg-muted">
        <component
            v-if="providerConfig"
            :is="providerConfig.icon"
            :class="['w-5 h-5', providerConfig.iconClass]"
        />
        <div class="text-sm">
            <span class="text-muted-foreground">{{ repository }}</span>
            <template v-if="branch">
                <span class="text-muted-foreground/60 mx-1">:</span>
                <span class="text-primary font-medium">{{ branch }}</span>
            </template>
        </div>
    </Badge>
</template>
