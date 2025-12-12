<script setup lang="ts">
import { show } from '@/actions/Nip/Site/Http/Controllers/SiteController';
import Avatar from '@/components/shared/Avatar.vue';
import { Badge } from '@/components/ui/badge';
import type { Site } from '@/types';
import { Link } from '@inertiajs/vue3';

interface Props {
    site: Site;
    showServer?: boolean;
}

withDefaults(defineProps<Props>(), {
    showServer: true,
});
</script>

<template>
    <Link
        :href="show.url({ site: site.slug })"
        class="flex items-center gap-4 px-4 py-3 transition-colors hover:bg-accent/50"
    >
        <Avatar :name="site.domain" :color="site.avatarColor" size="sm" />

        <!-- Content -->
        <div class="flex min-w-0 flex-1 cursor-pointer flex-col gap-y-0.5">
            <div class="flex items-center gap-2">
                <span class="truncate font-medium text-foreground">
                    {{ site.domain }}
                </span>
            </div>
            <span class="flex items-center gap-x-1 text-xs text-muted-foreground">
                <template v-if="site.serverName && showServer">
                    <span>{{ site.serverName }}</span>
                    <span>·</span>
                </template>
                <template v-if="site.displayableRepository">
                    <span>{{ site.displayableRepository }}</span>
                    <span>·</span>
                </template>
                <span>{{ site.user }}</span>
                <template v-if="site.phpVersion">
                    <span>·</span>
                    <span>{{ site.phpVersion }}</span>
                </template>
            </span>
        </div>

        <!-- Right side -->
        <div class="shrink-0 text-right">
            <Badge :variant="site.deployStatusBadgeVariant">
                {{ site.displayableDeployStatus }}
            </Badge>
            <p
                v-if="site.lastDeployedAtHuman"
                class="mt-1 text-xs text-muted-foreground"
            >
                {{ site.lastDeployedAtHuman }}
            </p>
        </div>
    </Link>
</template>
