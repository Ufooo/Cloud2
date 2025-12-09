<script setup lang="ts">
import {
    create,
    index,
} from '@/actions/Nip/Server/Http/Controllers/ServerController';
import EmptyState from '@/components/shared/EmptyState.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, ServerListResponse } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { Plus, Server } from 'lucide-vue-next';
import { computed } from 'vue';
import ServerCardListItem from './partials/ServerCardListItem.vue';

interface Props {
    servers: ServerListResponse;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Servers',
        href: index.url(),
    },
];

const hasServers = computed(() => props.servers.data.length > 0);
</script>

<template>
    <Head title="Servers" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold">Servers</h1>
                <Button as-child>
                    <Link :href="create.url()">
                        <Plus class="mr-2 size-4" />
                        New server
                    </Link>
                </Button>
            </div>

            <!-- Empty State -->
            <EmptyState
                v-if="!hasServers"
                :icon="Server"
                title="No servers yet"
                description="Create your first server to get started with deploying your applications."
            >
                <template #action>
                    <Button as-child>
                        <Link :href="create.url()">
                            <Plus class="mr-2 size-4" />
                            New server
                        </Link>
                    </Button>
                </template>
            </EmptyState>

            <!-- Server List -->
            <Card v-else class="overflow-hidden py-0">
                <div class="divide-y">
                    <ServerCardListItem
                        v-for="server in servers.data"
                        :key="server.id"
                        :server="server"
                    />
                </div>
            </Card>

            <!-- Pagination will be added when backend pagination is ready -->
        </div>
    </AppLayout>
</template>
