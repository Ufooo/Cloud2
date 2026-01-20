<script setup lang="ts">
import { index } from '@/actions/Nip/SecurityMonitor/Http/Controllers/SecurityMonitorController';
import EmptyState from '@/components/shared/EmptyState.vue';
import { Card } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { SecuritySummary, ServerGroup } from '@/types/security-monitor';
import { Head } from '@inertiajs/vue3';
import { Server, Shield, ShieldAlert } from 'lucide-vue-next';
import { computed } from 'vue';
import SecuritySiteListItem from './partials/SecuritySiteListItem.vue';
import SummaryStats from './partials/SummaryStats.vue';

interface Props {
    summary: SecuritySummary;
    serverGroups: { data: ServerGroup[] };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Security Monitor',
        href: index.url(),
    },
];

const hasSites = computed(() => props.serverGroups.data.length > 0);
</script>

<template>
    <Head title="Security Monitor" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">Security Monitor</h1>
                    <p class="text-gray-500">
                        Sites with security issues that need attention
                    </p>
                </div>
            </div>

            <!-- Empty State - No issues -->
            <EmptyState
                v-if="!hasSites && summary.totalSites > 0"
                :icon="Shield"
                title="All clear"
                description="No security issues detected. All monitored sites are clean."
            />

            <!-- Empty State - No monitored sites -->
            <EmptyState
                v-else-if="!hasSites"
                :icon="ShieldAlert"
                title="No monitored sites"
                description="Enable security monitoring on your sites to track git file changes."
            />

            <!-- Content -->
            <template v-else>
                <!-- Summary Stats -->
                <SummaryStats :summary="summary" />

                <!-- Sites List grouped by Server -->
                <div class="flex flex-col gap-4">
                    <Card
                        v-for="group in serverGroups.data"
                        :key="group.server.id"
                        class="overflow-hidden bg-white dark:bg-card py-0"
                    >
                        <!-- Server Header -->
                        <div class="flex items-center gap-2 px-4 py-3 bg-muted/50 text-sm font-medium">
                            <Server class="size-4 text-muted-foreground" />
                            <span>{{ group.server.name }}</span>
                            <span class="text-muted-foreground">({{ group.server.ipAddress }})</span>
                            <span class="ml-auto text-muted-foreground">{{ group.sites.length }} site{{ group.sites.length !== 1 ? 's' : '' }}</span>
                        </div>

                        <!-- Sites in this server -->
                        <div class="divide-y">
                            <SecuritySiteListItem
                                v-for="site in group.sites"
                                :key="site.id"
                                :site="site"
                                :show-server="false"
                            />
                        </div>
                    </Card>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
