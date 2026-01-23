<script setup lang="ts">
import { show as showDeployment } from '@/actions/Nip/Deployment/Http/Controllers/SiteDeploymentController';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useDeploymentStatus } from '@/composables/useDeploymentStatus';
import SiteLayout from '@/layouts/SiteLayout.vue';
import type { Deployment, Site } from '@/types';
import { SiteStatus, SiteType } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import {
    Check,
    Copy,
    Folder,
    FolderRoot,
    Globe,
    Rocket,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import LaravelPackages from './partials/LaravelPackages.vue';
import SiteProvisioning from './partials/SiteProvisioning.vue';

interface Props {
    site: Site;
    recentDeployments: { data: Deployment[] };
}

const props = defineProps<Props>();

const { getStatusIcon, getStatusClass, isDeploying } = useDeploymentStatus();

const isInstalling = computed(
    () => props.site.status === SiteStatus.Installing,
);

const copiedPath = ref<string | null>(null);

async function copyToClipboard(text: string, key: string) {
    await navigator.clipboard.writeText(text);
    copiedPath.value = key;
    setTimeout(() => {
        copiedPath.value = null;
    }, 2000);
}
</script>

<template>
    <Head :title="site.domain" />

    <SiteLayout :site="site" :show-sidebar="!isInstalling">
        <SiteProvisioning v-if="isInstalling" :site="site" />

        <div v-else class="space-y-6">
            <!-- Paths -->
            <Card class="bg-white">
                <CardHeader class="pb-3">
                    <CardTitle class="flex items-center gap-2 text-base">
                        <Folder class="size-4" />
                        Paths
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-2">
                    <TooltipProvider>
                        <div
                            v-for="(path, key) in {
                                siteRoot: {
                                    label: 'Site Root',
                                    value: site.siteRoot,
                                    icon: FolderRoot,
                                },
                                applicationPath: {
                                    label: 'Application',
                                    value: site.applicationPath,
                                    icon: Folder,
                                },
                                documentRoot: {
                                    label: 'Document Root',
                                    value: site.documentRoot,
                                    icon: Globe,
                                },
                            }"
                            :key="key"
                            class="group flex items-center justify-between rounded-lg border bg-muted/30 px-3 py-2"
                        >
                            <div class="flex items-center gap-3 overflow-hidden">
                                <component
                                    :is="path.icon"
                                    class="size-4 shrink-0 text-muted-foreground"
                                />
                                <div class="min-w-0">
                                    <p
                                        class="text-xs font-medium text-muted-foreground"
                                    >
                                        {{ path.label }}
                                    </p>
                                    <p
                                        class="truncate font-mono text-sm"
                                        :title="path.value"
                                    >
                                        {{ path.value }}
                                    </p>
                                </div>
                            </div>
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="size-8 shrink-0 opacity-0 transition-opacity group-hover:opacity-100"
                                        :class="{
                                            'opacity-100': copiedPath === key,
                                        }"
                                        @click="
                                            copyToClipboard(
                                                path.value,
                                                key as string,
                                            )
                                        "
                                    >
                                        <Check
                                            v-if="copiedPath === key"
                                            class="size-4 text-green-500"
                                        />
                                        <Copy v-else class="size-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>
                                        {{
                                            copiedPath === key
                                                ? 'Copied!'
                                                : 'Copy path'
                                        }}
                                    </p>
                                </TooltipContent>
                            </Tooltip>
                        </div>
                    </TooltipProvider>
                </CardContent>
            </Card>

            <!-- Recent Deployments -->
            <Card
                v-if="recentDeployments?.data?.length"
                class="bg-white"
            >
                <CardHeader class="pb-3">
                    <CardTitle class="flex items-center gap-2 text-base">
                        <Rocket class="size-4" />
                        Recent Deployments
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="divide-y">
                        <Link
                            v-for="deployment in recentDeployments.data"
                            :key="deployment.id"
                            :href="
                                showDeployment.url({
                                    site,
                                    deployment: deployment.id,
                                })
                            "
                            class="flex items-center gap-3 py-2 transition-colors hover:bg-muted/50"
                        >
                            <!-- Status Icon -->
                            <component
                                :is="getStatusIcon(deployment.status)"
                                class="size-4 shrink-0"
                                :class="[
                                    getStatusClass(deployment.status),
                                    {
                                        'animate-spin': isDeploying(
                                            deployment.status,
                                        ),
                                    },
                                ]"
                            />

                            <!-- Commit Hash -->
                            <code
                                v-if="deployment.shortCommitHash"
                                class="shrink-0 rounded bg-muted px-1.5 py-0.5 font-mono text-xs"
                            >
                                {{ deployment.shortCommitHash }}
                            </code>

                            <!-- Commit Message -->
                            <span
                                v-if="deployment.commitMessage"
                                class="min-w-0 flex-1 truncate text-sm"
                            >
                                {{ deployment.commitMessage }}
                            </span>

                            <!-- Time -->
                            <span
                                class="shrink-0 text-xs text-muted-foreground"
                            >
                                {{ deployment.createdAtForHumans }}
                            </span>
                        </Link>
                    </div>
                </CardContent>
            </Card>

            <!-- Laravel Packages -->
            <LaravelPackages v-if="site.type === SiteType.Laravel" :site="site" />
        </div>
    </SiteLayout>
</template>
