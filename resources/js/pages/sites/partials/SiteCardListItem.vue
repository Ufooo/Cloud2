<script setup lang="ts">
import { show } from '@/actions/Nip/Site/Http/Controllers/SiteController';
import Avatar from '@/components/shared/Avatar.vue';
import PhpVersionBadge from '@/components/PhpVersionBadge.vue';
import RepositoryBadge from '@/components/RepositoryBadge.vue';
import SiteTypeBadge from '@/components/SiteTypeBadge.vue';
import { Button } from '@/components/ui/button';
import SiteStatusBadge from '@/pages/sites/partials/SiteStatusBadge.vue';
import { SiteStatus, SslStatus, type Site } from '@/types';
import { Link } from '@inertiajs/vue3';
import { Clock, Lock, MoreHorizontal, Server, User } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    site: Site;
    showServer?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    showServer: true,
});

const isInstalled = computed(() => props.site.status === SiteStatus.Installed);

const sslIndicatorClass = computed(() => {
    switch (props.site.sslStatus) {
        case SslStatus.Active:
            return 'bg-emerald-500';
        case SslStatus.Expiring:
            return 'bg-orange-500';
        case SslStatus.Expired:
            return 'bg-red-500';
        default:
            return null;
    }
});

const showSslIndicator = computed(
    () => props.site.sslStatus && props.site.sslStatus !== SslStatus.None,
);
</script>

<template>
    <Link
        :href="show.url({ site: site.slug })"
        class="flex items-center gap-4 px-4 py-4 transition-colors hover:bg-muted/50"
    >
        <!-- Avatar with SSL indicator -->
        <div class="relative">
            <Avatar :name="site.domain" :color="site.avatarColor" size="md" />
            <div
                v-if="showSslIndicator"
                class="absolute -bottom-0.5 -right-0.5 flex h-4 w-4 items-center justify-center rounded-full ring-2 ring-background"
                :class="sslIndicatorClass"
            >
                <Lock class="h-2 w-2 text-white" />
            </div>
        </div>

        <!-- Main Info -->
        <div class="flex-1 min-w-0">
            <h4 class="font-semibold text-foreground truncate mb-1">{{ site.domain }}</h4>
            <div class="flex items-center gap-4 text-sm">
                <span v-if="site.serverName && showServer" class="text-muted-foreground">
                    <Server class="w-3.5 h-3.5 inline mr-1" />
                    {{ site.serverName }}
                </span>
                <span class="text-muted-foreground">
                    <User class="w-3.5 h-3.5 inline mr-1" />
                    {{ site.user }}
                </span>
                <PhpVersionBadge v-if="site.phpVersionLabel" :version="site.phpVersionLabel" />
            </div>
        </div>

        <!-- Site Type Badge (only if no repository) -->
        <SiteTypeBadge
            v-if="isInstalled && !site.repository"
            :type="site.type"
            :displayable-type="site.displayableType"
            :version="site.detectedVersion"
        />

        <!-- Repository Info (if has repository) -->
        <RepositoryBadge
            v-if="site.repository && isInstalled"
            :provider="site.sourceControlProvider"
            :repository="site.repository"
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

        <!-- Actions - TODO: Implement actions menu -->
        <Button variant="ghost" size="icon" class="h-9 w-9 text-muted-foreground hover:text-foreground hover:bg-muted flex-shrink-0" @click.prevent>
            <MoreHorizontal class="w-4 h-4" />
        </Button>
    </Link>
</template>
