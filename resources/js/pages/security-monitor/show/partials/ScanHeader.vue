<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import type { Site } from '@/types';
import { ScanStatus } from '@/types/generated';
import type { SecurityScan } from '@/types/security-monitor';
import { AlertTriangle, CheckCircle2, Clock, XCircle } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    scan: SecurityScan;
    site: Site;
}

const props = defineProps<Props>();

const statusIcon = computed(() => {
    switch (props.scan.status) {
        case ScanStatus.Clean:
            return CheckCircle2;
        case ScanStatus.IssuesDetected:
            return AlertTriangle;
        case ScanStatus.Error:
            return XCircle;
        default:
            return Clock;
    }
});

const statusColor = computed(() => {
    switch (props.scan.status) {
        case ScanStatus.Clean:
            return 'text-green-600';
        case ScanStatus.IssuesDetected:
            return 'text-yellow-600';
        case ScanStatus.Error:
            return 'text-red-600';
        default:
            return 'text-gray-500';
    }
});

const scanTime = computed(() => {
    if (!props.scan.completedAt) return null;
    return new Date(props.scan.completedAt).toLocaleString();
});
</script>

<template>
    <Card class="bg-white dark:bg-card">
        <CardContent class="pt-6">
            <div class="flex items-start gap-4">
                <component
                    :is="statusIcon"
                    class="size-6"
                    :class="statusColor"
                />

                <div class="flex-1 space-y-2">
                    <div class="flex items-center gap-2">
                        <h3 class="font-medium">Last Scan</h3>
                        <Badge :variant="scan.statusBadgeVariant">
                            {{ scan.statusLabel }}
                        </Badge>
                    </div>

                    <div class="space-y-1 text-sm text-muted-foreground">
                        <p v-if="scanTime">
                            Completed: {{ scanTime }}
                            <span v-if="scan.completedAtHuman"
                                >({{ scan.completedAtHuman }})</span
                            >
                        </p>

                        <div
                            v-if="scan.status === ScanStatus.IssuesDetected"
                            class="space-y-1"
                        >
                            <p v-if="scan.gitNewCount > 0">
                                <span class="font-medium text-yellow-600">
                                    {{ scan.gitNewCount }}
                                </span>
                                new git change{{
                                    scan.gitNewCount !== 1 ? 's' : ''
                                }}
                                <span
                                    v-if="scan.gitWhitelistedCount > 0"
                                    class="text-muted-foreground"
                                >
                                    ({{ scan.gitWhitelistedCount }} whitelisted)
                                </span>
                            </p>
                        </div>

                        <p
                            v-if="
                                scan.status === ScanStatus.Error &&
                                scan.errorMessage
                            "
                            class="text-red-600"
                        >
                            Error: {{ scan.errorMessage }}
                        </p>

                        <p v-if="scan.status === ScanStatus.Clean">
                            No issues detected
                        </p>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
