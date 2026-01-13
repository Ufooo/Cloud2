<script setup lang="ts">
import {
    destroy,
    destroyUser,
    index,
    refreshSizes,
} from '@/actions/Nip/Database/Http/Controllers/DatabaseController';
import FailedScriptsAlert from '@/components/FailedScriptsAlert.vue';
import ScriptOutputModal from '@/components/ScriptOutputModal.vue';
import ConfirmationDialog from '@/components/shared/ConfirmationDialog.vue';
import EmptyState from '@/components/shared/EmptyState.vue';
import Pagination from '@/components/shared/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useConfirmation } from '@/composables/useConfirmation';
import { useResourceStatusUpdates } from '@/composables/useResourceStatusUpdates';
import AppLayout from '@/layouts/AppLayout.vue';
import ServerLayout from '@/layouts/ServerLayout.vue';
import SiteLayout from '@/layouts/SiteLayout.vue';
import type {
    BreadcrumbItem,
    ProvisionScriptData,
    Server,
    Site,
} from '@/types';
import type { PaginatedResponse } from '@/types/pagination';
import { Deferred, Head, router } from '@inertiajs/vue3';
import { Database, Loader2, Plus, RefreshCw, User } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import CreateDatabaseDialog from './partials/CreateDatabaseDialog.vue';
import CreateDatabaseUserDialog from './partials/CreateDatabaseUserDialog.vue';
import DatabaseCardListItem from './partials/DatabaseCardListItem.vue';
import DatabaseUserCardListItem from './partials/DatabaseUserCardListItem.vue';
import EditDatabaseUserDialog from './partials/EditDatabaseUserDialog.vue';

type BadgeVariant =
    | 'default'
    | 'secondary'
    | 'destructive'
    | 'outline'
    | null
    | undefined;

interface DatabaseItem {
    id: string;
    serverId: number;
    serverName?: string;
    serverSlug?: string;
    siteId?: string;
    siteDomain?: string;
    siteSlug?: string;
    name: string;
    size?: number;
    displayableSize?: string;
    status?: string;
    displayableStatus?: string;
    statusBadgeVariant?: BadgeVariant;
    createdAt?: string;
    createdAtHuman?: string;
    can?: {
        delete: boolean;
    };
}

interface DatabaseUserItem {
    id: string;
    serverId: number;
    serverName?: string;
    serverSlug?: string;
    username: string;
    readonly?: boolean;
    status?: string;
    displayableStatus?: string;
    statusBadgeVariant?: BadgeVariant;
    databaseCount?: number;
    databaseIds?: string[];
    createdAt?: string;
    createdAtHuman?: string;
    can?: {
        update: boolean;
        delete: boolean;
    };
}

interface Props {
    databases: PaginatedResponse<DatabaseItem>;
    databaseUsers: PaginatedResponse<DatabaseUserItem> | DatabaseUserItem[];
    server: Server | null;
    site: Site | null;
}

const props = defineProps<Props>();

const { confirmButton } = useConfirmation();

const databases = computed(() => props.databases.data);
const databaseUsers = computed(() => {
    if (Array.isArray(props.databaseUsers)) {
        return props.databaseUsers;
    }
    return props.databaseUsers.data;
});

if (props.site) {
    useResourceStatusUpdates({
        channelType: 'site',
        channelId: props.site.id,
        propNames: ['databases'],
    });
} else if (props.server) {
    useResourceStatusUpdates({
        channelType: 'server',
        channelId: props.server.id,
        propNames: ['databases', 'databaseUsers'],
    });
}

const hasDatabases = computed(() => databases.value.length > 0);
const hasDatabaseUsers = computed(() => databaseUsers.value.length > 0);

const pageTitle = computed(() => {
    if (props.site) {
        return `Databases - ${props.site.domain}`;
    }
    if (props.server) {
        return `Databases - ${props.server.name}`;
    }
    return 'Databases';
});

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Databases', href: index.url() },
]);

// Dialog states
const showDatabaseDialog = ref(false);
const showUserDialog = ref(false);
const showEditUserDialog = ref(false);
const editingUser = ref<DatabaseUserItem | null>(null);

// Actions
async function deleteDatabase(database: DatabaseItem) {
    const serverSlug = props.server?.slug ?? database.serverSlug;
    if (!serverSlug) return;

    const confirmed = await confirmButton({
        title: 'Delete Database',
        description: `Are you sure you want to delete the database "${database.name}"? This action cannot be undone.`,
        confirmText: 'Delete',
    });

    if (!confirmed) return;

    router.delete(destroy.url({ server: serverSlug, database: database.id }), {
        preserveScroll: true,
    });
}

async function deleteUser(user: DatabaseUserItem) {
    const serverSlug = props.server?.slug ?? user.serverSlug;
    if (!serverSlug) return;

    const confirmed = await confirmButton({
        title: 'Delete Database User',
        description: `Are you sure you want to delete the user "${user.username}"? This action cannot be undone.`,
        confirmText: 'Delete',
    });

    if (!confirmed) return;

    router.delete(
        destroyUser.url({ server: serverSlug, databaseUser: user.id }),
        { preserveScroll: true },
    );
}

function openEditUserDialog(user: DatabaseUserItem) {
    editingUser.value = user;
    showEditUserDialog.value = true;
}

// Script output modal
const scriptOutputModal = ref<InstanceType<typeof ScriptOutputModal> | null>(
    null,
);
const databaseResourceTypes = ['database', 'database_user'];

function handleScriptClick(script: ProvisionScriptData) {
    scriptOutputModal.value?.open(script);
}

// Refresh database sizes
const isRefreshingSizes = ref(false);

async function handleRefreshSizes() {
    if (!props.server) return;

    isRefreshingSizes.value = true;

    try {
        const response = await fetch(refreshSizes.url(props.server), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN':
                    document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute('content') ?? '',
            },
        });

        if (response.ok) {
            router.reload({ only: ['databases'] });
        }
    } finally {
        isRefreshingSizes.value = false;
    }
}
</script>

<template>
    <Head :title="pageTitle" />

    <!-- Site-scoped layout -->
    <SiteLayout v-if="site" :site="site">
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold">Databases</h2>
            </div>

            <EmptyState
                v-if="!hasDatabases"
                :icon="Database"
                title="No databases linked"
                description="No databases have been linked to this site."
            />

            <Card v-else class="overflow-hidden py-0">
                <div class="divide-y">
                    <DatabaseCardListItem
                        v-for="database in databases"
                        :key="database.id"
                        :database="database"
                        :show-server="false"
                        :show-site="false"
                    />
                </div>
                <Pagination :meta="props.databases.meta" />
            </Card>
        </div>
    </SiteLayout>

    <!-- Server-scoped layout -->
    <ServerLayout v-else-if="server" :server="server">
        <div class="space-y-6">
            <!-- Databases Section -->
            <Card>
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle class="flex items-center gap-2 text-lg">
                        <Database class="size-5" />
                        Databases
                    </CardTitle>
                    <div class="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="isRefreshingSizes"
                            @click="handleRefreshSizes"
                        >
                            <Loader2
                                v-if="isRefreshingSizes"
                                class="mr-2 size-4 animate-spin"
                            />
                            <RefreshCw v-else class="mr-2 size-4" />
                            Refresh sizes
                        </Button>
                        <Button size="sm" @click="showDatabaseDialog = true">
                            <Plus class="mr-2 size-4" />
                            New database
                        </Button>
                    </div>
                </CardHeader>
                <CardContent class="p-0">
                    <EmptyState
                        v-if="!hasDatabases"
                        :icon="Database"
                        title="No databases yet"
                        description="Create your first database on this server."
                        class="py-8"
                    />
                    <div v-else class="divide-y">
                        <DatabaseCardListItem
                            v-for="database in databases"
                            :key="database.id"
                            :database="database"
                            :show-server="false"
                            @delete="deleteDatabase"
                        />
                    </div>
                </CardContent>
            </Card>

            <!-- Database Users Section -->
            <Card>
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle class="flex items-center gap-2 text-lg">
                        <User class="size-5" />
                        Database Users
                    </CardTitle>
                    <Deferred data="databaseUsers">
                        <template #fallback>
                            <div />
                        </template>
                        <Button size="sm" @click="showUserDialog = true">
                            <Plus class="mr-2 size-4" />
                            New user
                        </Button>
                    </Deferred>
                </CardHeader>
                <CardContent class="p-0">
                    <Deferred data="databaseUsers">
                        <template #fallback>
                            <div class="space-y-3 p-4">
                                <div
                                    v-for="i in 3"
                                    :key="i"
                                    class="flex items-center gap-4"
                                >
                                    <div
                                        class="h-10 w-10 animate-pulse rounded-full bg-muted"
                                    />
                                    <div class="flex-1 space-y-2">
                                        <div
                                            class="h-4 w-32 animate-pulse rounded bg-muted"
                                        />
                                        <div
                                            class="h-3 w-24 animate-pulse rounded bg-muted"
                                        />
                                    </div>
                                </div>
                            </div>
                        </template>

                        <EmptyState
                            v-if="!hasDatabaseUsers"
                            :icon="User"
                            title="No database users yet"
                            description="Create your first database user on this server."
                            class="py-8"
                        />
                        <div v-else class="divide-y">
                            <DatabaseUserCardListItem
                                v-for="user in databaseUsers"
                                :key="user.id"
                                :user="user"
                                :show-server="false"
                                @edit="openEditUserDialog"
                                @delete="deleteUser"
                            />
                        </div>
                    </Deferred>
                </CardContent>
            </Card>
        </div>

        <!-- Dialogs -->
        <CreateDatabaseDialog v-model:open="showDatabaseDialog" :server="server" />

        <CreateDatabaseUserDialog
            v-model:open="showUserDialog"
            :server="server"
            :databases="databases"
        />

        <EditDatabaseUserDialog
            v-model:open="showEditUserDialog"
            :server="server"
            :databases="databases"
            :user="editingUser"
        />

        <ScriptOutputModal ref="scriptOutputModal" />
        <ConfirmationDialog />
    </ServerLayout>

    <!-- Global layout -->
    <AppLayout v-else :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold">Databases</h1>
            </div>

            <!-- Database Warnings -->
            <FailedScriptsAlert
                :resource-types="databaseResourceTypes"
                title="Database Warnings"
                show-server-name
                @script-click="handleScriptClick"
            />

            <!-- Databases Section -->
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="flex items-center gap-2 text-lg">
                        <Database class="size-5" />
                        Databases
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-0">
                    <EmptyState
                        v-if="!hasDatabases"
                        :icon="Database"
                        title="No databases yet"
                        description="Create your first database on a server."
                        class="py-8"
                    />
                    <div v-else class="divide-y">
                        <DatabaseCardListItem
                            v-for="database in databases"
                            :key="database.id"
                            :database="database"
                            @delete="deleteDatabase"
                        />
                    </div>
                    <Pagination
                        v-if="hasDatabases"
                        :meta="props.databases.meta"
                    />
                </CardContent>
            </Card>

            <!-- Database Users Section -->
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="flex items-center gap-2 text-lg">
                        <User class="size-5" />
                        Database Users
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-0">
                    <Deferred data="databaseUsers">
                        <template #fallback>
                            <div class="space-y-3 p-4">
                                <div
                                    v-for="i in 3"
                                    :key="i"
                                    class="flex items-center gap-4"
                                >
                                    <div
                                        class="h-10 w-10 animate-pulse rounded-full bg-muted"
                                    />
                                    <div class="flex-1 space-y-2">
                                        <div
                                            class="h-4 w-32 animate-pulse rounded bg-muted"
                                        />
                                        <div
                                            class="h-3 w-24 animate-pulse rounded bg-muted"
                                        />
                                    </div>
                                </div>
                            </div>
                        </template>

                        <EmptyState
                            v-if="!hasDatabaseUsers"
                            :icon="User"
                            title="No database users yet"
                            description="Create your first database user on a server."
                            class="py-8"
                        />
                        <div v-else class="divide-y">
                            <DatabaseUserCardListItem
                                v-for="user in databaseUsers"
                                :key="user.id"
                                :user="user"
                                @delete="deleteUser"
                            />
                        </div>
                    </Deferred>
                </CardContent>
            </Card>
        </div>

        <ScriptOutputModal ref="scriptOutputModal" />
        <ConfirmationDialog />
    </AppLayout>
</template>
