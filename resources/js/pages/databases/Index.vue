<script setup lang="ts">
import {
    destroy,
    destroyUser,
    index,
    store,
    storeUser,
    updateUser,
} from '@/actions/Nip/Database/Http/Controllers/DatabaseController';
import EmptyState from '@/components/shared/EmptyState.vue';
import Pagination from '@/components/shared/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/AppLayout.vue';
import ServerLayout from '@/layouts/ServerLayout.vue';
import SiteLayout from '@/layouts/SiteLayout.vue';
import type { BreadcrumbItem, Server, Site } from '@/types';
import type { PaginatedResponse } from '@/types/pagination';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Database, Eye, EyeOff, Plus, User } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import DatabaseCardListItem from './partials/DatabaseCardListItem.vue';
import DatabaseUserCardListItem from './partials/DatabaseUserCardListItem.vue';

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
    createdAt?: string;
    createdAtHuman?: string;
}

interface DatabaseUserItem {
    id: string;
    serverId: number;
    serverName?: string;
    username: string;
    readonly?: boolean;
    databaseCount?: number;
    databaseIds?: string[];
    createdAt?: string;
    createdAtHuman?: string;
}

interface Props {
    databases: PaginatedResponse<DatabaseItem>;
    databaseUsers: PaginatedResponse<DatabaseUserItem> | DatabaseUserItem[];
    server: Server | null;
    site: Site | null;
}

const props = defineProps<Props>();

const hasDatabases = computed(() => props.databases.data.length > 0);
const hasDatabaseUsers = computed(() => {
    if (Array.isArray(props.databaseUsers)) {
        return props.databaseUsers.length > 0;
    }
    return props.databaseUsers.data.length > 0;
});

const databaseUsersList = computed(() => {
    if (Array.isArray(props.databaseUsers)) {
        return props.databaseUsers;
    }
    return props.databaseUsers.data;
});

const pageTitle = computed(() => {
    if (props.site) {
        return `Databases - ${props.site.domain}`;
    }
    if (props.server) {
        return `Databases - ${props.server.name}`;
    }
    return 'Databases';
});

const breadcrumbs = computed<BreadcrumbItem[]>(() => {
    return [
        {
            title: 'Databases',
            href: index.url(),
        },
    ];
});

// Database form
const showDatabaseDialog = ref(false);
const showDbPassword = ref(false);
const databaseForm = useForm({
    name: '',
    user: '',
    password: '',
});

function generatePassword(): string {
    const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    return Array.from({ length: 24 }, () => chars.charAt(Math.floor(Math.random() * chars.length))).join('');
}

function openDatabaseDialog() {
    databaseForm.reset();
    showDbPassword.value = false;
    showDatabaseDialog.value = true;
}

function submitDatabase() {
    if (!props.server) return;

    databaseForm.post(store.url(props.server), {
        preserveScroll: true,
        onSuccess: () => {
            showDatabaseDialog.value = false;
            databaseForm.reset();
        },
    });
}

function deleteDatabase(database: DatabaseItem) {
    if (!props.server) return;

    router.delete(destroy.url({ server: props.server.slug, database: database.id }), {
        preserveScroll: true,
    });
}

// Database user form
const showUserDialog = ref(false);
const showUserPassword = ref(false);
const databaseSearch = ref('');
const userForm = useForm({
    username: '',
    password: '',
    databases: [] as string[],
    readonly: false,
});

const filteredDatabases = computed(() => {
    if (!databaseSearch.value) {
        return props.databases.data;
    }
    return props.databases.data.filter((db) =>
        db.name.toLowerCase().includes(databaseSearch.value.toLowerCase()),
    );
});

function openUserDialog() {
    userForm.reset();
    userForm.databases = [];
    showUserPassword.value = false;
    databaseSearch.value = '';
    showUserDialog.value = true;
}

function generateUserPassword() {
    userForm.password = generatePassword();
    showUserPassword.value = true;
}

function toggleDatabase(databaseId: string) {
    const index = userForm.databases.indexOf(databaseId);
    if (index === -1) {
        userForm.databases.push(databaseId);
    } else {
        userForm.databases.splice(index, 1);
    }
}

function selectAllDatabases() {
    userForm.databases = props.databases.data.map((db) => db.id);
}

function submitUser() {
    if (!props.server) return;

    userForm
        .transform((data) => ({
            ...data,
            readonly: Boolean(data.readonly),
        }))
        .post(storeUser.url(props.server), {
            preserveScroll: true,
            onSuccess: () => {
                showUserDialog.value = false;
                userForm.reset();
            },
        });
}

function deleteUser(user: DatabaseUserItem) {
    if (!props.server) return;

    router.delete(destroyUser.url({ server: props.server.slug, databaseUser: user.id }), {
        preserveScroll: true,
    });
}

// Edit user form
const showEditUserDialog = ref(false);
const showEditUserPassword = ref(false);
const editDatabaseSearch = ref('');
const editingUser = ref<DatabaseUserItem | null>(null);
const editUserForm = useForm({
    password: '',
    databases: [] as string[],
    readonly: false,
});

const filteredEditDatabases = computed(() => {
    if (!editDatabaseSearch.value) {
        return props.databases.data;
    }
    return props.databases.data.filter((db) =>
        db.name.toLowerCase().includes(editDatabaseSearch.value.toLowerCase()),
    );
});

function openEditUserDialog(user: DatabaseUserItem) {
    editingUser.value = user;
    editUserForm.reset();
    editUserForm.databases = user.databaseIds || [];
    editUserForm.readonly = user.readonly || false;
    showEditUserPassword.value = false;
    editDatabaseSearch.value = '';
    showEditUserDialog.value = true;
}

function generateEditUserPassword() {
    editUserForm.password = generatePassword();
    showEditUserPassword.value = true;
}

function toggleEditDatabase(databaseId: string) {
    const index = editUserForm.databases.indexOf(databaseId);
    if (index === -1) {
        editUserForm.databases.push(databaseId);
    } else {
        editUserForm.databases.splice(index, 1);
    }
}

function selectAllEditDatabases() {
    editUserForm.databases = props.databases.data.map((db) => db.id);
}

function submitEditUser() {
    if (!props.server || !editingUser.value) return;

    editUserForm
        .transform((data) => ({
            ...data,
            readonly: Boolean(data.readonly),
        }))
        .put(updateUser.url({ server: props.server.slug, databaseUser: editingUser.value.id }), {
            preserveScroll: true,
            onSuccess: () => {
                showEditUserDialog.value = false;
                editUserForm.reset();
                editingUser.value = null;
            },
        });
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
                        v-for="database in databases.data"
                        :key="database.id"
                        :database="database"
                        :show-server="false"
                        :show-site="false"
                    />
                </div>
                <Pagination :meta="databases.meta" />
            </Card>
        </div>
    </SiteLayout>

    <!-- Server-scoped layout -->
    <ServerLayout v-else-if="server" :server="server">
        <div class="space-y-6">
            <!-- Databases Section -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="flex items-center gap-2 text-lg">
                        <Database class="size-5" />
                        Databases
                    </CardTitle>
                    <Button size="sm" @click="openDatabaseDialog">
                        <Plus class="mr-2 size-4" />
                        New database
                    </Button>
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
                            v-for="database in databases.data"
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
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="flex items-center gap-2 text-lg">
                        <User class="size-5" />
                        Database Users
                    </CardTitle>
                    <Button size="sm" @click="openUserDialog">
                        <Plus class="mr-2 size-4" />
                        New user
                    </Button>
                </CardHeader>
                <CardContent class="p-0">
                    <EmptyState
                        v-if="!hasDatabaseUsers"
                        :icon="User"
                        title="No database users yet"
                        description="Create your first database user on this server."
                        class="py-8"
                    />
                    <div v-else class="divide-y">
                        <DatabaseUserCardListItem
                            v-for="user in databaseUsersList"
                            :key="user.id"
                            :user="user"
                            :show-server="false"
                            @edit="openEditUserDialog"
                            @delete="deleteUser"
                        />
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Create Database Dialog -->
        <Dialog v-model:open="showDatabaseDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add database</DialogTitle>
                    <DialogDescription>
                        Create a new database for your <strong>{{ server.name }}</strong> server.
                        You can optionally create a new database user if needed.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitDatabase">
                    <div class="space-y-4">
                        <div class="space-y-2">
                            <Label for="db-name">Name</Label>
                            <Input
                                id="db-name"
                                v-model="databaseForm.name"
                                placeholder="my_database"
                                :class="{ 'border-destructive': databaseForm.errors.name }"
                            />
                            <p v-if="databaseForm.errors.name" class="text-sm text-destructive">
                                {{ databaseForm.errors.name }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-center gap-2">
                                <Label for="db-user">User</Label>
                                <Badge variant="outline" class="text-xs font-normal">Optional</Badge>
                            </div>
                            <Input
                                id="db-user"
                                v-model="databaseForm.user"
                                placeholder="db_user"
                                :class="{ 'border-destructive': databaseForm.errors.user }"
                            />
                            <p v-if="databaseForm.errors.user" class="text-sm text-destructive">
                                {{ databaseForm.errors.user }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <Label for="db-password">Password</Label>
                                    <Badge variant="outline" class="text-xs font-normal">Optional</Badge>
                                </div>
                                <Button
                                    type="button"
                                    variant="link"
                                    size="sm"
                                    class="h-auto p-0 text-primary"
                                    @click="databaseForm.password = generatePassword(); showDbPassword = true"
                                >
                                    Generate password
                                </Button>
                            </div>
                            <div class="relative">
                                <Input
                                    id="db-password"
                                    v-model="databaseForm.password"
                                    :type="showDbPassword ? 'text' : 'password'"
                                    placeholder="••••••••"
                                    class="pr-10"
                                    :class="{ 'border-destructive': databaseForm.errors.password }"
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="absolute top-1/2 right-1 size-8 -translate-y-1/2"
                                    @click="showDbPassword = !showDbPassword"
                                >
                                    <Eye v-if="!showDbPassword" class="size-4" />
                                    <EyeOff v-else class="size-4" />
                                </Button>
                            </div>
                            <p v-if="databaseForm.errors.password" class="text-sm text-destructive">
                                {{ databaseForm.errors.password }}
                            </p>
                        </div>
                    </div>

                    <DialogFooter class="mt-6">
                        <Button type="submit" class="w-full" :disabled="databaseForm.processing">
                            {{ databaseForm.processing ? 'Creating...' : 'Create database' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Create User Dialog -->
        <Dialog v-model:open="showUserDialog">
            <DialogContent class="max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>New database user</DialogTitle>
                    <DialogDescription>
                        Create a new database user for the server <strong>{{ server.name }}</strong>.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitUser">
                    <div class="space-y-4">
                        <div class="space-y-2">
                            <Label for="username">Name</Label>
                            <Input
                                id="username"
                                v-model="userForm.username"
                                placeholder="db_user"
                                :class="{ 'border-destructive': userForm.errors.username }"
                            />
                            <p v-if="userForm.errors.username" class="text-sm text-destructive">
                                {{ userForm.errors.username }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <Label for="user-password">Password</Label>
                                <Button
                                    type="button"
                                    variant="link"
                                    size="sm"
                                    class="h-auto p-0 text-primary"
                                    @click="generateUserPassword"
                                >
                                    Generate password
                                </Button>
                            </div>
                            <div class="relative">
                                <Input
                                    id="user-password"
                                    v-model="userForm.password"
                                    :type="showUserPassword ? 'text' : 'password'"
                                    placeholder="••••••••"
                                    class="pr-10"
                                    :class="{ 'border-destructive': userForm.errors.password }"
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="absolute top-1/2 right-1 size-8 -translate-y-1/2"
                                    @click="showUserPassword = !showUserPassword"
                                >
                                    <Eye v-if="!showUserPassword" class="size-4" />
                                    <EyeOff v-else class="size-4" />
                                </Button>
                            </div>
                            <p v-if="userForm.errors.password" class="text-sm text-destructive">
                                {{ userForm.errors.password }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label>Grant access to</Label>
                            <Input
                                v-model="databaseSearch"
                                placeholder="Search"
                                class="mb-2"
                            />
                            <div class="max-h-48 space-y-1 overflow-y-auto rounded-md border p-2">
                                <div
                                    v-for="db in filteredDatabases"
                                    :key="db.id"
                                    class="flex items-center gap-2 rounded px-2 py-1.5 hover:bg-accent"
                                >
                                    <Checkbox
                                        :id="`db-${db.id}`"
                                        :model-value="userForm.databases.includes(db.id)"
                                        @update:model-value="toggleDatabase(db.id)"
                                    />
                                    <label
                                        :for="`db-${db.id}`"
                                        class="flex-1 cursor-pointer text-sm"
                                    >
                                        {{ db.name }}
                                    </label>
                                </div>
                                <p
                                    v-if="filteredDatabases.length === 0"
                                    class="py-2 text-center text-sm text-muted-foreground"
                                >
                                    No databases found
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="link"
                                size="sm"
                                class="h-auto p-0 text-primary"
                                @click="selectAllDatabases"
                            >
                                Select all databases
                            </Button>
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <div>
                                    <Label for="readonly">Read-only access?</Label>
                                    <p class="text-sm text-muted-foreground">
                                        If enabled, this user will only be able to read data from the selected databases.
                                    </p>
                                </div>
                                <Switch
                                    id="readonly"
                                    v-model:checked="userForm.readonly"
                                />
                            </div>
                        </div>
                    </div>

                    <DialogFooter class="mt-6">
                        <Button type="submit" class="w-full" :disabled="userForm.processing">
                            {{ userForm.processing ? 'Creating...' : 'Add database user' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Edit User Dialog -->
        <Dialog v-model:open="showEditUserDialog">
            <DialogContent class="max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Edit database user</DialogTitle>
                    <DialogDescription>
                        Update the database user <strong>{{ editingUser?.username }}</strong> on server <strong>{{ server.name }}</strong>.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitEditUser">
                    <div class="space-y-4">
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <Label for="edit-user-password">Password</Label>
                                    <Badge variant="outline" class="text-xs font-normal">Optional</Badge>
                                </div>
                                <Button
                                    type="button"
                                    variant="link"
                                    size="sm"
                                    class="h-auto p-0 text-primary"
                                    @click="generateEditUserPassword"
                                >
                                    Generate password
                                </Button>
                            </div>
                            <div class="relative">
                                <Input
                                    id="edit-user-password"
                                    v-model="editUserForm.password"
                                    :type="showEditUserPassword ? 'text' : 'password'"
                                    placeholder="Leave empty to keep current"
                                    class="pr-10"
                                    :class="{ 'border-destructive': editUserForm.errors.password }"
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="absolute top-1/2 right-1 size-8 -translate-y-1/2"
                                    @click="showEditUserPassword = !showEditUserPassword"
                                >
                                    <Eye v-if="!showEditUserPassword" class="size-4" />
                                    <EyeOff v-else class="size-4" />
                                </Button>
                            </div>
                            <p v-if="editUserForm.errors.password" class="text-sm text-destructive">
                                {{ editUserForm.errors.password }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label>Grant access to</Label>
                            <Input
                                v-model="editDatabaseSearch"
                                placeholder="Search"
                                class="mb-2"
                            />
                            <div class="max-h-48 space-y-1 overflow-y-auto rounded-md border p-2">
                                <div
                                    v-for="db in filteredEditDatabases"
                                    :key="db.id"
                                    class="flex items-center gap-2 rounded px-2 py-1.5 hover:bg-accent"
                                >
                                    <Checkbox
                                        :id="`edit-db-${db.id}`"
                                        :model-value="editUserForm.databases.includes(db.id)"
                                        @update:model-value="toggleEditDatabase(db.id)"
                                    />
                                    <label
                                        :for="`edit-db-${db.id}`"
                                        class="flex-1 cursor-pointer text-sm"
                                    >
                                        {{ db.name }}
                                    </label>
                                </div>
                                <p
                                    v-if="filteredEditDatabases.length === 0"
                                    class="py-2 text-center text-sm text-muted-foreground"
                                >
                                    No databases found
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="link"
                                size="sm"
                                class="h-auto p-0 text-primary"
                                @click="selectAllEditDatabases"
                            >
                                Select all databases
                            </Button>
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <div>
                                    <Label for="edit-readonly">Read-only access?</Label>
                                    <p class="text-sm text-muted-foreground">
                                        If enabled, this user will only be able to read data from the selected databases.
                                    </p>
                                </div>
                                <Switch
                                    id="edit-readonly"
                                    v-model:checked="editUserForm.readonly"
                                />
                            </div>
                        </div>
                    </div>

                    <DialogFooter class="mt-6">
                        <Button type="submit" class="w-full" :disabled="editUserForm.processing">
                            {{ editUserForm.processing ? 'Saving...' : 'Save changes' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </ServerLayout>

    <!-- Global layout -->
    <AppLayout v-else :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold">Databases</h1>
            </div>

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
                            v-for="database in databases.data"
                            :key="database.id"
                            :database="database"
                        />
                    </div>
                    <Pagination v-if="hasDatabases" :meta="databases.meta" />
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
                    <EmptyState
                        v-if="!hasDatabaseUsers"
                        :icon="User"
                        title="No database users yet"
                        description="Create your first database user on a server."
                        class="py-8"
                    />
                    <div v-else class="divide-y">
                        <DatabaseUserCardListItem
                            v-for="user in databaseUsersList"
                            :key="user.id"
                            :user="user"
                        />
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
