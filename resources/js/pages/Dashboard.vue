<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import ExpiringCertificatesWidget from './Dashboard/partials/ExpiringCertificatesWidget.vue';

interface ExpiringCertificate {
    id: number;
    type: string;
    displayableType: string;
    siteId: number;
    siteSlug: string;
    siteDomain: string;
    domains: string[];
    expiresAt: string;
    expiresAtHuman: string;
    daysUntilExpiry: number;
    canRenew: boolean;
}

interface Props {
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
            <div class="grid gap-4 md:grid-cols-3">
                <div class="aspect-video">
                    <ExpiringCertificatesWidget
                        :certificates="expiringCertificates.data"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
