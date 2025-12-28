<script setup lang="ts">
import { index } from '@/actions/Nip/Site/Http/Controllers/SiteController';
import SiteTypeIcon from '@/components/icons/SiteTypeIcon.vue';
import EmptyState from '@/components/shared/EmptyState.vue';
import Pagination from '@/components/shared/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useResourceStatusUpdates } from '@/composables/useResourceStatusUpdates';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Server, Site } from '@/types';
import type { PaginatedResponse } from '@/types/pagination';
import { Head, router } from '@inertiajs/vue3';
import { Globe, Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import SiteCardListItem from './partials/SiteCardListItem.vue';

interface SiteTypeOption {
    value: string;
    label: string;
}

interface Props {
    sites: PaginatedResponse<Site>;
    servers: { id: number; slug: string; name: string }[];
    currentServer: Server | null;
    siteTypes: SiteTypeOption[];
}

const props = defineProps<Props>();

const sites = computed(() => props.sites.data);

// Subscribe to WebSocket updates when filtered by server
// For global view (no currentServer), data only updates on navigation
if (props.currentServer) {
    useResourceStatusUpdates({
        channelType: 'server',
        channelId: props.currentServer.id,
        propNames: ['sites'],
    });
}

const showTypeSelectDialog = ref(false);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Sites',
        href: index.url(),
    },
];

// Group site types for the type selection modal
const siteTypeGroups = computed(() => {
    const phpTypes = ['laravel', 'symfony', 'statamic', 'wordpress', 'phpmyadmin', 'php'];
    const nodeTypes = ['nextjs', 'nuxtjs'];
    const staticTypes = ['html', 'other'];

    return {
        'PHP Applications': props.siteTypes.filter(t => phpTypes.includes(t.value)),
        'Node.js Applications': props.siteTypes.filter(t => nodeTypes.includes(t.value)),
        'Static Sites': props.siteTypes.filter(t => staticTypes.includes(t.value)),
    };
});

const hasSites = computed(() => sites.value.length > 0);

function openTypeSelectDialog() {
    showTypeSelectDialog.value = true;
}

function selectTypeAndNavigate(type: string) {
    showTypeSelectDialog.value = false;
    router.visit(`/sites/create/${type}`);
}
</script>

<template>
    <Head title="Sites" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold">Sites</h1>
                <Button @click="openTypeSelectDialog">
                    <Plus class="mr-2 size-4" />
                    New site
                </Button>
            </div>

            <!-- Empty State -->
            <EmptyState
                v-if="!hasSites"
                :icon="Globe"
                title="No sites yet"
                description="Create your first site to get started with deploying your applications."
            >
                <template #action>
                    <Button @click="openTypeSelectDialog">
                        <Plus class="mr-2 size-4" />
                        New site
                    </Button>
                </template>
            </EmptyState>

            <!-- Site List -->
            <Card v-else class="overflow-hidden py-0">
                <div class="divide-y">
                    <SiteCardListItem
                        v-for="site in sites"
                        :key="site.id"
                        :site="site"
                        :show-server="!currentServer"
                    />
                </div>

                <!-- Pagination -->
                <Pagination :meta="props.sites.meta" />
            </Card>
        </div>

        <!-- Site Type Selection Dialog -->
        <Dialog v-model:open="showTypeSelectDialog">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>Create a new site</DialogTitle>
                    <DialogDescription>
                        Select the type of site you want to create.
                    </DialogDescription>
                </DialogHeader>

                <div class="flex flex-col gap-y-1 px-1">
                    <template v-for="(types, groupName, idx) in siteTypeGroups" :key="groupName">
                        <p v-if="types.length > 0" class="mt-2 h-7 px-2 text-xs font-medium leading-7 text-muted-foreground first:mt-0">
                            {{ groupName }}
                        </p>
                        <div class="flex flex-col gap-y-1">
                            <button
                                v-for="siteType in types"
                                :key="siteType.value"
                                type="button"
                                class="flex h-9 items-center gap-x-2 rounded px-3 text-left text-sm transition-colors hover:bg-muted"
                                @click="selectTypeAndNavigate(siteType.value)"
                            >
                                <SiteTypeIcon :type="siteType.value" class="size-7" />
                                <span>{{ siteType.label }}</span>
                            </button>
                        </div>
                        <hr v-if="idx < Object.keys(siteTypeGroups).length - 1 && types.length > 0" class="mt-2 border-border" />
                    </template>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
