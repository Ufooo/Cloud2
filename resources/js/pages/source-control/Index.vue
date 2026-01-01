<script setup lang="ts">
import {
    destroy,
    index,
    redirect,
} from '@/actions/Nip/SourceControl/Http/Controllers/SourceControlController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import EmptyState from '@/components/shared/EmptyState.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useResourceDelete } from '@/composables/useResourceDelete';
import AppLayout from '@/layouts/AppLayout.vue';
import GlobalSettingsLayout from '@/layouts/global-settings/Layout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { GitBranch, MoreHorizontal, Plus, Trash2 } from 'lucide-vue-next';

interface SourceControl {
    id: number;
    provider: string;
    providerLabel: string;
    name: string;
    connectedAt: string;
}

interface Provider {
    value: string;
    label: string;
}

interface Props {
    sourceControls: SourceControl[];
    providers: Provider[];
}

defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Source Control',
        href: index.url(),
    },
];

const { deleteResource: deleteSourceControl } =
    useResourceDelete<SourceControl>({
        resourceName: 'Source Control',
        getDisplayName: (sc) => `${sc.providerLabel} (${sc.name})`,
        getDeleteUrl: (sc) => destroy.url({ sourceControl: sc.id }),
    });

function connectProvider(provider: string) {
    window.location.href = redirect.url({ provider });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Source Control" />

        <GlobalSettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall
                    title="Source Control"
                    description="Connect your source control providers to deploy code from your repositories."
                />

                <div class="space-y-4">
                    <div class="flex justify-end">
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button variant="outline">
                                    <Plus class="mr-2 size-4" />
                                    Connect provider
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem
                                    v-for="provider in providers"
                                    :key="provider.value"
                                    @click="connectProvider(provider.value)"
                                >
                                    <GitBranch class="mr-2 size-4" />
                                    {{ provider.label }}
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>

                    <EmptyState
                        v-if="sourceControls.length === 0"
                        :icon="GitBranch"
                        title="No providers connected"
                        description="Connect a source control provider to deploy code from your repositories."
                        compact
                    >
                        <template #action>
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button variant="outline">
                                        <Plus class="mr-2 size-4" />
                                        Connect provider
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem
                                        v-for="provider in providers"
                                        :key="provider.value"
                                        @click="connectProvider(provider.value)"
                                    >
                                        <GitBranch class="mr-2 size-4" />
                                        {{ provider.label }}
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </template>
                    </EmptyState>

                    <div v-else class="divide-y rounded-lg border">
                        <div
                            v-for="sc in sourceControls"
                            :key="sc.id"
                            class="flex items-center justify-between p-4"
                        >
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex size-10 items-center justify-center rounded-lg bg-muted"
                                >
                                    <GitBranch class="size-5" />
                                </div>
                                <div>
                                    <p class="font-medium">
                                        {{ sc.providerLabel }}
                                    </p>
                                    <p class="text-sm text-muted-foreground">
                                        Connected as {{ sc.name }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button variant="ghost" size="icon">
                                            <MoreHorizontal class="size-4" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem
                                            class="text-destructive focus:text-destructive"
                                            @click="deleteSourceControl(sc)"
                                        >
                                            <Trash2 class="mr-2 size-4" />
                                            Disconnect
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </GlobalSettingsLayout>
    </AppLayout>
</template>
