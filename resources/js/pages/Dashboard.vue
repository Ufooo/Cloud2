<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import ExpiringCertificatesWidget from './Dashboard/partials/ExpiringCertificatesWidget.vue';
import ServersWidget from './Dashboard/partials/ServersWidget.vue';

interface ServerWidget {
    id: number;
    name: string;
    slug: string;
    ipAddress: string;
    status: string;
    statusLabel: string;
    isConnected: boolean;
    sitesCount: number;
    uptimeFormatted: string | null;
    loadAvgFormatted: string | null;
    cpuPercent: number;
    ramTotalBytes: number | null;
    ramUsedBytes: number | null;
    ramPercent: number;
    diskTotalBytes: number | null;
    diskUsedBytes: number | null;
    diskPercent: number;
    lastMetricsAt: string | null;
}

interface ExpiringCertificate {
    id: number;
    type: string;
    displayableType: string;
    siteId: number;
    siteSlug: string;
    siteDomain: string;
    domains: string[];
    domainsFormatted: string;
    expiresAt: string;
    expiresAtHuman: string;
    daysUntilExpiry: number;
    canRenew: boolean;
}

interface Props {
    servers: {
        data: ServerWidget[];
    };
    expiringCertificates: {
        data: ExpiringCertificate[];
    };
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
            <!-- Servers Widget -->
            <ServersWidget :servers="servers.data" />

            <!-- Certificates Widget -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <ExpiringCertificatesWidget
                    :certificates="expiringCertificates.data"
                />
            </div>
        </div>
    </AppLayout>
</template>
