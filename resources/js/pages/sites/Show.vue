<script setup lang="ts">
import { deploy } from '@/actions/Nip/Site/Http/Controllers/SiteController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import SiteLayout from '@/layouts/SiteLayout.vue';
import type { Site } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import {
    ExternalLink,
    Folder,
    GitBranch,
    Globe,
    Rocket,
    Server,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import DetectedPackages from './partials/DetectedPackages.vue';
import SiteProvisioning from './partials/SiteProvisioning.vue';

interface Props {
    site: Site;
}

const props = defineProps<Props>();

const isInstalling = computed(() => props.site.status === 'installing');
const isDeployingInProgress = ref(false);

function triggerDeploy() {
    if (isDeployingInProgress.value) return;

    isDeployingInProgress.value = true;
    router.post(deploy.url(props.site), {}, {
        onFinish: () => {
            isDeployingInProgress.value = false;
        },
    });
}

function getDeployStatusVariant(
    status: string | null,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (status) {
        case 'deployed':
            return 'default';
        case 'deploying':
            return 'secondary';
        case 'failed':
            return 'destructive';
        default:
            return 'outline';
    }
}
</script>

<template>
    <Head :title="site.domain" />

    <SiteLayout :site="site" :show-sidebar="!isInstalling">
        <SiteProvisioning v-if="isInstalling" :site="site" />

        <div v-else class="space-y-6">
            <!-- Quick Actions -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <Rocket class="size-5" />
                                Deployment
                            </CardTitle>
                            <CardDescription>
                                Deploy your application to production
                            </CardDescription>
                        </div>
                        <div class="flex items-center gap-2">
                            <Badge
                                :variant="
                                    getDeployStatusVariant(site.deployStatus)
                                "
                            >
                                {{ site.displayableDeployStatus }}
                            </Badge>
                            <Button
                                v-if="site.can?.deploy"
                                @click="triggerDeploy"
                                :disabled="site.deployStatus === 'deploying' || isDeployingInProgress"
                            >
                                <Rocket class="mr-2 size-4" />
                                {{ isDeployingInProgress ? 'Starting...' : 'Deploy now' }}
                            </Button>
                        </div>
                    </div>
                </CardHeader>
                <CardContent v-if="site.lastDeployedAtHuman">
                    <p class="text-sm text-muted-foreground">
                        Last deployed {{ site.lastDeployedAtHuman }}
                    </p>
                </CardContent>
            </Card>

            <!-- Site Information -->
            <div class="grid gap-6 md:grid-cols-2">
                <!-- General Info -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Globe class="size-5" />
                            Site details
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-muted-foreground"
                                >Domain</span
                            >
                            <a
                                :href="site.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="flex items-center gap-1 text-sm font-medium hover:underline"
                            >
                                {{ site.domain }}
                                <ExternalLink class="size-3" />
                            </a>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-muted-foreground"
                                >Type</span
                            >
                            <span class="text-sm font-medium">{{
                                site.displayableType
                            }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-muted-foreground"
                                >PHP Version</span
                            >
                            <span class="text-sm font-medium">{{
                                site.phpVersion ?? 'N/A'
                            }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-muted-foreground"
                                >Unix User</span
                            >
                            <span class="font-mono text-sm font-medium">{{
                                site.user
                            }}</span>
                        </div>
                    </CardContent>
                </Card>

                <!-- Server Info -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Server class="size-5" />
                            Server
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-muted-foreground"
                                >Server</span
                            >
                            <span class="text-sm font-medium">{{
                                site.serverName
                            }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-muted-foreground"
                                >Root Directory</span
                            >
                            <span class="font-mono text-sm font-medium">{{
                                site.rootDirectory
                            }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-muted-foreground"
                                >Web Directory</span
                            >
                            <span class="font-mono text-sm font-medium">{{
                                site.webDirectory
                            }}</span>
                        </div>
                    </CardContent>
                </Card>

                <!-- Repository Info -->
                <Card v-if="site.repository">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <GitBranch class="size-5" />
                            Repository
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-muted-foreground"
                                >Repository</span
                            >
                            <span class="text-sm font-medium">{{
                                site.displayableRepository
                            }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-muted-foreground"
                                >Branch</span
                            >
                            <Badge variant="secondary" class="font-mono">
                                {{ site.branch }}
                            </Badge>
                        </div>
                    </CardContent>
                </Card>

                <!-- Paths Info -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Folder class="size-5" />
                            Paths
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div>
                            <span class="text-sm text-muted-foreground"
                                >Full Path</span
                            >
                            <p
                                class="mt-1 font-mono text-sm font-medium break-all"
                            >
                                {{ site.fullPath }}
                            </p>
                        </div>
                        <div>
                            <span class="text-sm text-muted-foreground"
                                >Web Path</span
                            >
                            <p
                                class="mt-1 font-mono text-sm font-medium break-all"
                            >
                                {{ site.webPath }}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Detected Packages -->
            <DetectedPackages :site="site" />
        </div>
    </SiteLayout>
</template>
