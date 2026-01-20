<script setup lang="ts">
import { scan } from '@/actions/Nip/SecurityMonitor/Http/Controllers/SecurityMonitorController';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import SiteLayout from '@/layouts/SiteLayout.vue';
import type { SecuritySettingsData, Site } from '@/types';
import type { GitChange, SecurityScan } from '@/types/security-monitor';
import { Head, router, useForm } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import { RefreshCw } from 'lucide-vue-next';
import { computed } from 'vue';
import GitChangesTable from './partials/GitChangesTable.vue';
import ScanHeader from './partials/ScanHeader.vue';
import SecuritySettings from './partials/SecuritySettings.vue';

interface Props {
    site: Site;
    lastScan: { data: SecurityScan } | null;
    gitChanges: { data: GitChange[] } | [];
    securitySettings: SecuritySettingsData;
}

const props = defineProps<Props>();

// Real-time updates when scan completes
useEcho(`sites.${props.site.id}`, '.SecurityScanCompleted', () => {
    router.reload({ only: ['lastScan', 'gitChanges'], preserveScroll: true });
});

const scanForm = useForm({});

function handleScanNow() {
    scanForm.post(scan.url(props.site), {
        preserveScroll: true,
        onSuccess: () => {
            // Scan started successfully
        },
    });
}

const gitChangesData = computed(() =>
    Array.isArray(props.gitChanges) ? props.gitChanges : props.gitChanges.data
);

const newGitChanges = computed(() =>
    gitChangesData.value.filter(change => !change.isWhitelisted)
);

const whitelistedGitChanges = computed(() =>
    gitChangesData.value.filter(change => change.isWhitelisted)
);
</script>

<template>
    <Head :title="`Security - ${site.domain}`" />

    <SiteLayout :site="site">
        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">Security</h1>
                    <p class="text-muted-foreground">Git changes monitoring</p>
                </div>

                <Button
                    :disabled="scanForm.processing"
                    @click="handleScanNow"
                >
                    <RefreshCw class="mr-2 size-4" :class="{ 'animate-spin': scanForm.processing }" />
                    {{ scanForm.processing ? 'Scanning...' : 'Scan Now' }}
                </Button>
            </div>

            <!-- Last Scan Info -->
            <ScanHeader
                v-if="lastScan"
                :scan="lastScan.data"
                :site="site"
            />

            <!-- Git Changes Section -->
            <Card v-if="securitySettings.gitMonitorEnabled" class="bg-white dark:bg-card">
                <CardHeader>
                    <CardTitle>Git Changes</CardTitle>
                    <CardDescription>
                        Uncommitted file changes detected by git status
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-6">
                    <GitChangesTable
                        :changes="newGitChanges"
                        :site="site"
                        title="New Changes"
                        :show-whitelist-all="newGitChanges.length > 0"
                    />

                    <Separator v-if="whitelistedGitChanges.length > 0" />

                    <GitChangesTable
                        v-if="whitelistedGitChanges.length > 0"
                        :changes="whitelistedGitChanges"
                        :site="site"
                        title="Whitelisted Changes"
                        :is-whitelisted="true"
                    />
                </CardContent>
            </Card>

            <!-- Security Settings -->
            <SecuritySettings
                :site="site"
                :settings="securitySettings"
            />
        </div>
    </SiteLayout>
</template>
