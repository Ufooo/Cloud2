<script setup lang="ts">
import { index as deploymentsIndex } from '@/actions/Nip/Deployment/Http/Controllers/SiteDeploymentController';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { useAnsiToHtml } from '@/composables/useAnsiToHtml';
import { useDeploymentStatus } from '@/composables/useDeploymentStatus';
import SiteLayout from '@/layouts/SiteLayout.vue';
import type { DeploymentDetail, Site } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import {
    ArrowLeft,
    ChevronDown,
    Clock,
    ExternalLink,
    GitBranch,
    GitCommit,
    Loader2,
    User,
} from 'lucide-vue-next';
import { computed, nextTick, ref, toRef, watch } from 'vue';

interface Props {
    site: Site;
    deployment: DeploymentDetail | { data: DeploymentDetail };
}

const props = defineProps<Props>();

const {
    getStatusIcon,
    getStatusLabel,
    getStatusClass,
    getStatusBgClass,
    isDeploying: checkIsDeploying,
} = useDeploymentStatus();

// Handle Laravel Resource wrapper (deployment comes as {data: {...}})
function getDeploymentData() {
    return 'data' in props.deployment
        ? props.deployment.data
        : props.deployment;
}

const currentDeployment = ref(getDeploymentData() as DeploymentDetail);
const outputRef = toRef(() => currentDeployment.value.output);
const { html: outputHtml } = useAnsiToHtml(outputRef);

// Update local state when props change (from Inertia reload)
watch(
    () => props.deployment,
    () => {
        const newData = getDeploymentData() as DeploymentDetail;
        currentDeployment.value = {
            ...currentDeployment.value,
            ...newData,
        };
    },
    { deep: true },
);

const buildLogsOpen = ref(true);
const deploymentLogsOpen = ref(true);
const logContainerRef = ref<HTMLElement | null>(null);

const isDeploying = computed(() =>
    checkIsDeploying(currentDeployment.value.status),
);

// Auto-scroll to bottom when output changes
watch(
    () => currentDeployment.value.output,
    () => {
        nextTick(() => {
            if (logContainerRef.value) {
                logContainerRef.value.scrollTop =
                    logContainerRef.value.scrollHeight;
            }
        });
    },
);

// Real-time updates via WebSocket - triggers reload to fetch full output
const { stopListening } = useEcho(
    `deployments.${currentDeployment.value.id}`,
    '.DeploymentUpdated',
    (event: { status: string; hasOutput: boolean; endedAt: string | null }) => {
        // Update status immediately for responsiveness
        currentDeployment.value = {
            ...currentDeployment.value,
            status: event.status,
            statusLabel: getStatusLabel(event.status),
            endedAt: event.endedAt,
        };

        // Fetch full output from server (output is too large for WebSocket)
        if (event.hasOutput) {
            router.reload({ only: ['deployment'], preserveScroll: true });
        }
    },
);

// Stop listening when deployment is finished
watch(isDeploying, (deploying) => {
    if (!deploying) {
        stopListening();
    }
});
</script>

<template>
    <Head
        :title="`Deployment ${currentDeployment.shortCommitHash || currentDeployment.id} - ${site.domain}`"
    />

    <SiteLayout :site="site">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Button as-child variant="ghost" size="icon">
                        <Link :href="deploymentsIndex.url(site)">
                            <ArrowLeft class="size-4" />
                        </Link>
                    </Button>

                    <div>
                        <h1
                            class="flex items-center gap-3 text-2xl font-semibold"
                        >
                            Deployment details
                            <code
                                v-if="currentDeployment.shortCommitHash"
                                class="rounded bg-muted px-2 py-1 font-mono text-lg"
                            >
                                {{ currentDeployment.shortCommitHash }}
                            </code>
                        </h1>
                    </div>
                </div>

                <Button v-if="site.url" as-child variant="outline" size="sm">
                    <a
                        :href="site.url"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <ExternalLink class="mr-2 size-4" />
                        Visit
                    </a>
                </Button>
            </div>

            <!-- Deployment Info -->
            <div class="flex flex-wrap items-center gap-4 text-sm">
                <!-- Status Badge -->
                <span
                    class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 font-medium"
                    :class="getStatusBgClass(currentDeployment.status)"
                >
                    <component
                        :is="getStatusIcon(currentDeployment.status)"
                        class="size-4"
                        :class="{
                            'animate-spin':
                                currentDeployment.status === 'deploying',
                        }"
                    />
                    {{ currentDeployment.statusLabel }}
                </span>

                <!-- Branch -->
                <span
                    v-if="currentDeployment.branch"
                    class="inline-flex items-center gap-1.5 text-muted-foreground"
                >
                    <GitBranch class="size-4" />
                    <code class="rounded bg-muted px-1.5 py-0.5 text-xs">
                        {{ currentDeployment.branch }}
                    </code>
                </span>

                <!-- Commit Message -->
                <span
                    v-if="currentDeployment.commitMessage"
                    class="inline-flex items-center gap-1.5 text-muted-foreground"
                >
                    <GitCommit class="size-4" />
                    <span class="max-w-xs truncate">{{
                        currentDeployment.commitMessage
                    }}</span>
                </span>

                <!-- Deployed By -->
                <span
                    v-if="currentDeployment.deployedBy"
                    class="inline-flex items-center gap-1.5 text-muted-foreground"
                >
                    <User class="size-4" />
                    {{ currentDeployment.deployedBy }}
                </span>

                <!-- Time -->
                <span
                    class="inline-flex items-center gap-1.5 text-muted-foreground"
                >
                    <Clock class="size-4" />
                    {{ currentDeployment.createdAtForHumans }}
                </span>

                <!-- Duration -->
                <span
                    v-if="currentDeployment.durationForHumans"
                    class="text-muted-foreground"
                >
                    {{ currentDeployment.durationForHumans }}
                </span>
            </div>

            <!-- Build Logs -->
            <Card>
                <Collapsible v-model:open="buildLogsOpen">
                    <CardHeader class="py-3">
                        <CollapsibleTrigger
                            class="flex w-full items-center justify-between"
                        >
                            <div class="flex items-center gap-3">
                                <component
                                    :is="
                                        getStatusIcon(currentDeployment.status)
                                    "
                                    class="size-5"
                                    :class="[
                                        getStatusClass(
                                            currentDeployment.status,
                                        ),
                                        {
                                            'animate-spin':
                                                currentDeployment.status ===
                                                'deploying',
                                        },
                                    ]"
                                />
                                <span class="font-semibold">Build logs</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span
                                    v-if="
                                        currentDeployment.status === 'deploying'
                                    "
                                    class="text-sm text-muted-foreground"
                                >
                                    In progress...
                                </span>
                                <ChevronDown
                                    class="size-5 text-muted-foreground transition-transform"
                                    :class="{ 'rotate-180': buildLogsOpen }"
                                />
                            </div>
                        </CollapsibleTrigger>
                    </CardHeader>

                    <CollapsibleContent>
                        <CardContent class="pt-0">
                            <div
                                class="rounded-lg bg-muted/50 p-4 font-mono text-sm"
                            >
                                <p class="text-muted-foreground">
                                    <span class="text-green-500">=&gt;</span>
                                    Preparing to build site
                                    <span class="font-bold">{{
                                        site.domain
                                    }}</span>
                                    <span
                                        v-if="currentDeployment.shortCommitHash"
                                    >
                                        for commit
                                        <span class="font-bold">{{
                                            currentDeployment.commitHash
                                        }}</span>
                                    </span>
                                </p>
                                <p
                                    v-if="site.zeroDowntime"
                                    class="text-green-500"
                                >
                                    <span>=&gt;</span> Zero downtime deployments
                                    enabled
                                </p>
                                <p class="text-green-500">
                                    <span>=&gt;</span> Build ready to be
                                    deployed
                                </p>
                            </div>
                        </CardContent>
                    </CollapsibleContent>
                </Collapsible>
            </Card>

            <!-- Deployment Logs -->
            <Card>
                <Collapsible v-model:open="deploymentLogsOpen">
                    <CardHeader class="py-3">
                        <CollapsibleTrigger
                            class="flex w-full items-center justify-between"
                        >
                            <div class="flex items-center gap-3">
                                <component
                                    :is="
                                        getStatusIcon(currentDeployment.status)
                                    "
                                    class="size-5"
                                    :class="[
                                        getStatusClass(
                                            currentDeployment.status,
                                        ),
                                        {
                                            'animate-spin':
                                                currentDeployment.status ===
                                                'deploying',
                                        },
                                    ]"
                                />
                                <span class="font-semibold"
                                    >Deployment logs</span
                                >
                            </div>
                            <div class="flex items-center gap-2">
                                <span
                                    v-if="currentDeployment.durationForHumans"
                                    class="text-sm text-muted-foreground"
                                >
                                    {{ currentDeployment.durationForHumans }}
                                </span>
                                <ChevronDown
                                    class="size-5 text-muted-foreground transition-transform"
                                    :class="{
                                        'rotate-180': deploymentLogsOpen,
                                    }"
                                />
                            </div>
                        </CollapsibleTrigger>
                    </CardHeader>

                    <CollapsibleContent>
                        <CardContent class="pt-0">
                            <div
                                v-if="currentDeployment.output"
                                ref="logContainerRef"
                                class="max-h-[600px] overflow-auto rounded-lg bg-zinc-900 p-4 font-mono text-sm text-zinc-100"
                            >
                                <pre
                                    class="break-words whitespace-pre-wrap"
                                    v-html="outputHtml"
                                />
                            </div>
                            <div
                                v-else-if="
                                    currentDeployment.status === 'deploying'
                                "
                                class="flex items-center gap-2 py-8 text-center text-muted-foreground"
                            >
                                <Loader2 class="mx-auto size-6 animate-spin" />
                            </div>
                            <div
                                v-else
                                class="py-8 text-center text-muted-foreground"
                            >
                                No deployment logs available.
                            </div>
                        </CardContent>
                    </CollapsibleContent>
                </Collapsible>
            </Card>
        </div>
    </SiteLayout>
</template>
