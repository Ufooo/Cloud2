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
    <Badge variant="outline" class="gap-2 bg-muted px-3 py-1.5">
        <component
            v-if="providerConfig"
            :is="providerConfig.icon"
            :class="['size-5!', providerConfig.iconClass]"
        />
        <div class="text-sm">
            <span class="text-muted-foreground">{{ repository }}</span>
            <template v-if="branch">
                <span class="mx-1 text-muted-foreground/60">:</span>
                <span class="font-medium text-emerald-500">{{ branch }}</span>
            </template>
        </div>
    </Badge>
</template>
