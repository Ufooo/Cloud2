<script setup lang="ts">
import { settings } from '@/actions/Nip/Deployment/Http/Controllers/SiteDeploymentController';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import SiteLayout from '@/layouts/SiteLayout.vue';
import type { Paginated, Site } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import {
    CheckCircle,
    Clock,
    GitCommit,
    Loader2,
    Rocket,
    Settings,
    XCircle,
} from 'lucide-vue-next';

interface Deployment {
    id: string;
    status: string;
    statusLabel: string;
    statusColor: string;
    commitHash: string | null;
    shortCommitHash: string | null;
    commitMessage: string | null;
    commitAuthor: string | null;
    branch: string | null;
    deployedBy: string | null;
    startedAt: string | null;
    endedAt: string | null;
    duration: number | null;
    createdAt: string;
    createdAtForHumans: string;
}

interface Props {
    site: Site;
    deployments: Paginated<Deployment>;
}

defineProps<Props>();

function getStatusIcon(status: string) {
    switch (status) {
        case 'finished':
            return CheckCircle;
        case 'failed':
            return XCircle;
        case 'deploying':
            return Loader2;
        default:
            return Clock;
    }
}

function getStatusClass(status: string) {
    switch (status) {
        case 'finished':
            return 'text-green-500';
        case 'failed':
            return 'text-red-500';
        case 'deploying':
            return 'text-blue-500 animate-spin';
        default:
            return 'text-yellow-500';
    }
}
</script>

<template>
    <Head :title="`Deployments - ${site.domain}`" />

    <SiteLayout :site="site">
        <Card>
            <CardHeader class="flex flex-row items-center justify-between">
                <div>
                    <CardTitle class="flex items-center gap-2">
                        <Rocket class="size-5" />
                        Deployments
                    </CardTitle>
                    <CardDescription>
                        View deployment history for this site.
                    </CardDescription>
                </div>
                <Button as-child variant="outline" size="sm">
                    <Link :href="settings.url(site)">
                        <Settings class="mr-2 size-4" />
                        Settings
                    </Link>
                </Button>
            </CardHeader>

            <CardContent>
                <div
                    v-if="deployments.data.length === 0"
                    class="py-12 text-center"
                >
                    <Rocket class="mx-auto size-12 text-muted-foreground/50" />
                    <h3 class="mt-4 text-lg font-medium">No deployments yet</h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Deploy your site to see the history here.
                    </p>
                </div>

                <div v-else class="divide-y">
                    <div
                        v-for="deployment in deployments.data"
                        :key="deployment.id"
                        class="flex items-center gap-4 py-3"
                    >
                        <!-- Status Icon -->
                        <component
                            :is="getStatusIcon(deployment.status)"
                            class="size-5 shrink-0"
                            :class="getStatusClass(deployment.status)"
                        />

                        <!-- Commit Hash -->
                        <code
                            v-if="deployment.shortCommitHash"
                            class="shrink-0 rounded bg-muted px-2 py-1 font-mono text-sm"
                        >
                            {{ deployment.shortCommitHash }}
                        </code>

                        <!-- Commit Message -->
                        <div class="flex min-w-0 flex-1 items-center gap-2">
                            <GitCommit
                                v-if="deployment.commitMessage"
                                class="size-4 shrink-0 text-muted-foreground"
                            />
                            <span class="truncate text-sm">
                                {{
                                    deployment.commitMessage || 'Manual deployment'
                                }}
                            </span>
                        </div>

                        <!-- Branch & Time -->
                        <div
                            class="flex shrink-0 items-center gap-2 text-sm text-muted-foreground"
                        >
                            <span v-if="deployment.branch">
                                Deployed from
                                <code
                                    class="rounded bg-muted px-1.5 py-0.5 text-xs"
                                >
                                    {{ deployment.branch }}
                                </code>
                            </span>
                            <span>{{ deployment.createdAtForHumans }}</span>
                            <span v-if="deployment.deployedBy">
                                by
                                <span class="font-medium text-foreground">
                                    {{ deployment.deployedBy }}
                                </span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <div
                    v-if="deployments.meta.last_page > 1"
                    class="mt-4 flex justify-center gap-2"
                >
                    <Button
                        v-for="link in deployments.meta.links"
                        :key="link.label"
                        :disabled="!link.url || link.active"
                        :variant="link.active ? 'default' : 'outline'"
                        size="sm"
                        as-child
                    >
                        <Link
                            v-if="link.url"
                            :href="link.url"
                        >
                            <span v-html="link.label" />
                        </Link>
                        <span v-else v-html="link.label" />
                    </Button>
                </div>
            </CardContent>
        </Card>
    </SiteLayout>
</template>
