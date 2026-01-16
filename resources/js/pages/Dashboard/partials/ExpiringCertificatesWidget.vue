<script setup lang="ts">
import { renew } from '@/actions/Nip/Domain/Http/Controllers/CertificateController';
import { show } from '@/actions/Nip/Site/Http/Controllers/SiteController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { router } from '@inertiajs/vue3';
import { Globe, RefreshCw } from 'lucide-vue-next';
import { computed, ref } from 'vue';

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
    certificates: ExpiringCertificate[];
}

const props = defineProps<Props>();

const renewingIds = ref<Set<number>>(new Set());

const hasCertificates = computed(() => props.certificates.length > 0);

function getUrgencyClass(days: number): string {
    if (days <= 3) return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400';
    if (days <= 7) return 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400';
    return 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400';
}

function handleRenew(cert: ExpiringCertificate) {
    if (!cert.canRenew || renewingIds.value.has(cert.id)) return;
    renewingIds.value.add(cert.id);
    router.post(renew.url({ site: cert.siteSlug, certificate: cert.id }), {}, {
        preserveScroll: true,
        onFinish: () => renewingIds.value.delete(cert.id),
    });
}

function isRenewing(id: number): boolean {
    return renewingIds.value.has(id);
}
</script>

<template>
    <Card class="bg-white dark:bg-card">
        <div class="border-b px-3 py-2.5">
            <div class="flex items-center justify-between">
                <span class="text-sm font-semibold">Expiring Certificates</span>
                <Badge v-if="hasCertificates" variant="secondary" class="text-xs">
                    {{ certificates.length }}
                </Badge>
            </div>
        </div>
        <div v-if="hasCertificates">
            <div class="divide-y">
                <div
                    v-for="cert in certificates"
                    :key="cert.id"
                    class="flex items-center gap-2 px-3 py-2 hover:bg-muted/30"
                >
                    <Globe class="size-3.5 shrink-0 text-muted-foreground" />
                    <div class="min-w-0 flex-1">
                        <a
                            :href="show.url({ site: cert.siteSlug })"
                            class="block truncate text-sm font-medium hover:text-primary"
                        >
                            {{ cert.siteDomain }}
                        </a>
                        <span class="block truncate text-xs text-muted-foreground">
                            {{ cert.domainsFormatted }}
                        </span>
                    </div>
                    <Badge variant="secondary" :class="getUrgencyClass(cert.daysUntilExpiry)" class="shrink-0 text-xs">
                        {{ cert.daysUntilExpiry }}d
                    </Badge>
                    <Button
                        v-if="cert.canRenew"
                        size="icon"
                        variant="ghost"
                        class="size-6 shrink-0"
                        :disabled="isRenewing(cert.id)"
                        @click="handleRenew(cert)"
                    >
                        <RefreshCw class="size-3" :class="{ 'animate-spin': isRenewing(cert.id) }" />
                    </Button>
                </div>
            </div>
        </div>
        <div v-else class="flex items-center justify-center p-4">
            <span class="text-xs text-muted-foreground">No expiring certificates</span>
        </div>
    </Card>
</template>
