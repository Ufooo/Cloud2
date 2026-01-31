<script setup lang="ts">
import { show } from '@/actions/Nip/SecurityMonitor/Http/Controllers/SecurityMonitorController';
import Avatar from '@/components/shared/Avatar.vue';
import { Badge } from '@/components/ui/badge';
import type { SecuritySite } from '@/types/security-monitor';
import { Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CheckCircle2,
    Clock,
    Server,
    XCircle,
} from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    site: SecuritySite;
    showServer?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    showServer: true,
});

const statusIcon = computed(() => {
    if (!props.site.lastScan) return Clock;

    switch (props.site.lastScan.status) {
        case 'clean':
            return CheckCircle2;
        case 'issues_detected':
            return AlertTriangle;
        case 'error':
            return XCircle;
        default:
            return Clock;
    }
});

const statusColor = computed(() => {
    if (!props.site.lastScan) return 'text-gray-400';

    switch (props.site.lastScan.status) {
        case 'clean':
            return 'text-green-500';
        case 'issues_detected':
            return 'text-yellow-500';
        case 'error':
            return 'text-red-500';
        default:
            return 'text-gray-400';
    }
});

const statusBadge = computed(() => {
    if (!props.site.lastScan)
        return { variant: 'secondary' as const, label: 'No scan' };

    switch (props.site.lastScan.status) {
        case 'clean':
            return { variant: 'default' as const, label: 'Clean' };
        case 'issues_detected':
            return { variant: 'warning' as const, label: 'Issues' };
        case 'error':
            return { variant: 'destructive' as const, label: 'Error' };
        default:
            return { variant: 'secondary' as const, label: 'Pending' };
    }
});

const issuesSummary = computed(() => {
    if (!props.site.lastScan) return null;

    if (props.site.lastScan.gitNewCount > 0) {
        return `${props.site.lastScan.gitNewCount} git changes`;
    }

    return null;
});

const lastScanTime = computed(() => {
    if (!props.site.lastScan?.completedAt) return null;

    const date = new Date(props.site.lastScan.completedAt);
    const now = new Date();
    const diffMinutes = Math.floor((now.getTime() - date.getTime()) / 60000);

    if (diffMinutes < 1) return 'Just now';
    if (diffMinutes < 60) return `${diffMinutes}m ago`;
    if (diffMinutes < 1440) return `${Math.floor(diffMinutes / 60)}h ago`;
    return `${Math.floor(diffMinutes / 1440)}d ago`;
});
</script>

<template>
    <Link
        :href="show.url(site)"
        class="flex items-center gap-4 px-4 py-4 transition-colors hover:bg-muted/50"
    >
        <!-- Status Icon -->
        <div class="flex-shrink-0">
            <component :is="statusIcon" class="size-5" :class="statusColor" />
        </div>

        <!-- Avatar -->
        <Avatar :name="site.domain" size="md" />

        <!-- Main Info -->
        <div class="min-w-0 flex-1">
            <h4 class="mb-1 truncate font-semibold text-foreground">
                {{ site.domain }}
            </h4>
            <div class="flex items-center gap-4 text-sm">
                <span
                    v-if="site.server?.name && showServer"
                    class="text-muted-foreground"
                >
                    <Server class="mr-1 inline h-3.5 w-3.5" />
                    {{ site.server.name }}
                </span>
                <span v-if="issuesSummary" class="text-muted-foreground">
                    {{ issuesSummary }}
                </span>
            </div>
        </div>

        <!-- Monitoring badge -->
        <div class="flex flex-shrink-0 items-center gap-2">
            <Badge
                v-if="site.gitMonitorEnabled"
                variant="outline"
                class="text-xs"
            >
                Git
            </Badge>
        </div>

        <!-- Status Badge -->
        <Badge :variant="statusBadge.variant" class="flex-shrink-0">
            {{ statusBadge.label }}
        </Badge>

        <!-- Last Scan Time -->
        <div class="w-24 flex-shrink-0 text-right">
            <div class="mb-1 text-xs text-muted-foreground">Last scan</div>
            <div
                v-if="lastScanTime"
                class="flex items-center justify-end gap-1 text-sm text-muted-foreground"
            >
                <Clock class="h-3.5 w-3.5" />
                {{ lastScanTime }}
            </div>
            <div v-else class="text-sm text-muted-foreground">Never</div>
        </div>
    </Link>
</template>
