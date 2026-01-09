<script setup lang="ts">
import { show } from '@/actions/Nip/Site/Http/Controllers/SiteController';
import Avatar from '@/components/shared/Avatar.vue';
import PhpVersionBadge from '@/components/PhpVersionBadge.vue';
import RepositoryBadge from '@/components/RepositoryBadge.vue';
import { Button } from '@/components/ui/button';
import SiteStatusBadge from '@/pages/sites/partials/SiteStatusBadge.vue';
import type { Site } from '@/types';
import { Link } from '@inertiajs/vue3';
import { Clock, ExternalLink, MoreHorizontal, Server, User } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    site: Site;
    showServer?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    showServer: true,
});

const isInstalled = computed(() => props.site.status === 'installed');
</script>

<template>
    <Link
        :href="show.url({ site: site.slug })"
        class="flex items-center gap-4 px-4 py-4 transition-colors hover:bg-muted/50"
    >
        <!-- Avatar -->
        <Avatar :name="site.domain" :color="site.avatarColor" size="md" />

        <!-- Main Info -->
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 mb-1">
                <h4 class="font-semibold text-foreground truncate">{{ site.domain }}</h4>
                <ExternalLink class="w-3.5 h-3.5 text-muted-foreground cursor-pointer hover:text-primary flex-shrink-0" />
            </div>
            <div class="flex items-center gap-4 text-sm">
                <span v-if="site.serverName && showServer" class="text-muted-foreground">
                    <Server class="w-3.5 h-3.5 inline mr-1" />
                    {{ site.serverName }}
                </span>
                <span class="text-muted-foreground">
                    <User class="w-3.5 h-3.5 inline mr-1" />
                    {{ site.user }}
                </span>
                <PhpVersionBadge v-if="site.phpVersionValue" :version="site.phpVersionValue" />
            </div>
        </div>

        <!-- Repository Info -->
        <RepositoryBadge
            v-if="site.displayableRepository && isInstalled"
            :provider="site.sourceControlProvider"
            :repository="site.repository!"
            :branch="site.branch"
        />

        <!-- Right side -->
        <div class="flex-shrink-0 text-right w-28">
            <template v-if="isInstalled">
                <div class="text-xs text-muted-foreground mb-1">Last deploy</div>
                <div v-if="site.lastDeployedAtHuman" class="flex items-center justify-end gap-1 text-sm text-muted-foreground">
                    <Clock class="w-3.5 h-3.5" />
                    {{ site.lastDeployedAtHuman }}
                </div>
                <div v-else class="text-sm text-muted-foreground">Never</div>
            </template>
            <SiteStatusBadge v-else :status="site.status" />
        </div>

        <!-- Actions -->
        <Button variant="ghost" size="icon" class="h-9 w-9 text-muted-foreground hover:text-foreground hover:bg-muted flex-shrink-0" @click.prevent>
            <MoreHorizontal class="w-4 h-4" />
        </Button>
    </Link>
</template>
