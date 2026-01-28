<script setup lang="ts">
import {
    index,
    store,
} from '@/actions/Nip/Site/Http/Controllers/SiteController';
import WordpressIcon from '@/components/icons/WordpressIcon.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/AppLayout.vue';
import type {
    DatabaseOptionData,
    PhpVersionOptionData,
    SelectOptionData,
} from '@/types/generated';
import { Form, Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

interface ServerOption {
    id: number;
    slug: string;
    name: string;
    phpVersions: PhpVersionOptionData[];
    unixUsers: SelectOptionData[];
    databases: DatabaseOptionData[];
    databaseUsers: SelectOptionData[];
}

interface Props {
    servers: ServerOption[];
}

const props = defineProps<Props>();

// Form state
const domainValue = ref('');
const selectedServer = ref<string>(props.servers[0]?.id.toString() || '');
const selectedPhpVersion = ref<string>('');
const selectedUser = ref<string>('');

// Database
const createDatabase = ref(true);
const selectedDatabase = ref<string | undefined>(undefined);
const selectedDatabaseUser = ref<string | undefined>(undefined);
const databaseName = ref('');
const databaseUser = ref('');
const databasePassword = ref('');

// Computed
const currentServer = computed(() => {
    return props.servers.find((s) => s.id.toString() === selectedServer.value);
});

const availablePhpVersions = computed(() => {
    return currentServer.value?.phpVersions || [];
});

const availableUnixUsers = computed(() => {
    return currentServer.value?.unixUsers || [];
});

const availableDatabases = computed(() => {
    return currentServer.value?.databases || [];
});

const selectedDatabaseObject = computed(() => {
    if (!selectedDatabase.value) return null;
    return (
        availableDatabases.value.find(
            (db) => db.value.toString() === selectedDatabase.value,
        ) || null
    );
});

const availableDatabaseUsers = computed(() => {
    if (!selectedDatabaseObject.value) return null;
    const allowedUserIds = Object.values(selectedDatabaseObject.value.userIds);
    return (currentServer.value?.databaseUsers || []).filter((dbu) =>
        allowedUserIds.includes(dbu.value as number),
    );
});

const defaultPhpVersion = computed(() => {
    const defaultVersion = availablePhpVersions.value.find((v) => v.isDefault);
    return defaultVersion?.value || availablePhpVersions.value[0]?.value || '';
});

const defaultUnixUser = computed(() => {
    return availableUnixUsers.value[0]?.value || '';
});

// Auto-generate database credentials from domain
const sanitizedDomain = computed(() => {
    return domainValue.value
        .replace(/[^a-zA-Z0-9]/g, '_')
        .replace(/_+/g, '_')
        .replace(/^_|_$/g, '')
        .substring(0, 16);
});

// Watchers
watch(
    selectedServer,
    () => {
        selectedPhpVersion.value = defaultPhpVersion.value;
        selectedUser.value = defaultUnixUser.value;
        selectedDatabase.value = undefined;
        selectedDatabaseUser.value = undefined;
    },
    { immediate: true },
);

watch(selectedDatabase, () => {
    selectedDatabaseUser.value = undefined;
});

// Auto-fill database name when domain changes
watch(domainValue, (newDomain) => {
    if (createDatabase.value && newDomain) {
        databaseName.value = sanitizedDomain.value;
        databaseUser.value = sanitizedDomain.value;
    }
});

// Methods
function generatePassword(): void {
    const chars =
        'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    let password = '';
    for (let i = 0; i < 20; i++) {
        password += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    databasePassword.value = password;
}

function navigateToSites() {
    router.visit(index.url());
}
</script>

<template>
    <Head title="Install WordPress" />

    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <Card class="mx-auto w-full max-w-xl bg-white">
                <CardHeader>
                    <div class="flex items-center gap-3">
                        <WordpressIcon class="size-8" />
                        <div>
                            <CardTitle>Install a WordPress application</CardTitle>
                            <CardDescription>
                                WordPress will be downloaded and configured automatically.
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>

                <CardContent>
                    <Form
                        v-bind="store.form()"
                        class="space-y-6"
                        v-slot="{ errors, processing }"
                    >
                        <!-- Hidden fields -->
                        <input type="hidden" name="type" value="wordpress" />
                        <input type="hidden" name="web_directory" value="/" />
                        <input type="hidden" name="root_directory" value="/" />
                        <input
                            v-if="createDatabase"
                            type="hidden"
                            name="database_name"
                            :value="databaseName"
                        />
                        <input
                            v-if="createDatabase"
                            type="hidden"
                            name="database_user"
                            :value="databaseUser"
                        />
                        <input
                            v-if="createDatabase"
                            type="hidden"
                            name="database_password"
                            :value="databasePassword"
                        />

                        <!-- Domain -->
                        <div class="space-y-2">
                            <Label for="domain">Domain</Label>
                            <Input
                                id="domain"
                                name="domain"
                                v-model="domainValue"
                                placeholder="example.com"
                            />
                            <InputError :message="errors.domain" />
                        </div>

                        <!-- Server, User, PHP in one row -->
                        <div
                            class="grid gap-4"
                            :class="
                                availablePhpVersions.length > 0
                                    ? 'grid-cols-3'
                                    : 'grid-cols-2'
                            "
                        >
                            <div class="space-y-2">
                                <Label for="server_id">Server</Label>
                                <Select name="server_id" v-model="selectedServer">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select a server" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="server in servers"
                                            :key="server.id"
                                            :value="server.id.toString()"
                                        >
                                            {{ server.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="errors.server_id" />
                            </div>

                            <div class="space-y-2">
                                <Label for="user">Site user</Label>
                                <Select name="user" v-model="selectedUser">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select a user" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="user in availableUnixUsers"
                                            :key="user.value"
                                            :value="user.value"
                                        >
                                            {{ user.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="errors.user" />
                            </div>

                            <div
                                v-if="availablePhpVersions.length > 0"
                                class="space-y-2"
                            >
                                <Label for="php_version">PHP version</Label>
                                <Select
                                    name="php_version"
                                    v-model="selectedPhpVersion"
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select PHP version" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="version in availablePhpVersions"
                                            :key="version.value"
                                            :value="version.value"
                                        >
                                            {{ version.label }}
                                            <span
                                                v-if="version.isDefault"
                                                class="ml-1 text-muted-foreground"
                                                >(default)</span
                                            >
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="errors.php_version" />
                            </div>
                        </div>

                        <!-- Database section -->
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <input
                                    type="hidden"
                                    name="create_database"
                                    :value="createDatabase ? '1' : '0'"
                                />
                                <div class="space-y-0.5">
                                    <Label for="create_database">Create database</Label>
                                    <p class="text-xs text-muted-foreground">
                                        Create a new database for this site
                                    </p>
                                </div>
                                <Switch
                                    id="create_database"
                                    v-model="createDatabase"
                                />
                            </div>

                            <!-- Database creation inputs (when creating a new database) -->
                            <div
                                v-if="createDatabase"
                                class="grid gap-4 rounded-lg border border-dashed p-4"
                            >
                                <div class="space-y-2">
                                    <Label for="database_name">Database name</Label>
                                    <Input
                                        id="database_name"
                                        v-model="databaseName"
                                        placeholder="my_database"
                                        required
                                    />
                                    <InputError :message="errors.database_name" />
                                </div>

                                <div class="space-y-2">
                                    <Label for="database_user">Database user</Label>
                                    <Input
                                        id="database_user"
                                        v-model="databaseUser"
                                        placeholder="db_user"
                                        required
                                    />
                                    <InputError :message="errors.database_user" />
                                </div>

                                <div class="space-y-2">
                                    <Label for="database_password">Database password</Label>
                                    <div class="flex gap-2">
                                        <Input
                                            id="database_password"
                                            v-model="databasePassword"
                                            type="text"
                                            placeholder="Enter or generate password"
                                            required
                                            class="flex-1"
                                        />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            @click="generatePassword"
                                        >
                                            Generate
                                        </Button>
                                    </div>
                                    <InputError :message="errors.database_password" />
                                </div>
                            </div>

                            <!-- Database selection (when not creating a new one) -->
                            <div
                                v-if="!createDatabase && availableDatabases.length > 0"
                                class="grid grid-cols-2 gap-4 rounded-lg border border-dashed p-4"
                            >
                                <input
                                    type="hidden"
                                    name="database_id"
                                    :value="selectedDatabase || ''"
                                />
                                <input
                                    type="hidden"
                                    name="database_user_id"
                                    :value="selectedDatabaseUser || ''"
                                />
                                <div class="space-y-2">
                                    <Label for="database_id">Database</Label>
                                    <Select v-model="selectedDatabase">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select a database" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="db in availableDatabases"
                                                :key="db.value"
                                                :value="db.value.toString()"
                                            >
                                                {{ db.label }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError :message="errors.database_id" />
                                </div>

                                <div class="space-y-2">
                                    <Label for="database_user_id">Database user</Label>
                                    <Select
                                        v-model="selectedDatabaseUser"
                                        :disabled="
                                            !selectedDatabase ||
                                            !availableDatabaseUsers ||
                                            availableDatabaseUsers.length === 0
                                        "
                                    >
                                        <SelectTrigger>
                                            <SelectValue
                                                :placeholder="
                                                    !selectedDatabase
                                                        ? 'Select database first'
                                                        : !availableDatabaseUsers ||
                                                            availableDatabaseUsers.length === 0
                                                          ? 'No users available'
                                                          : 'Select a user'
                                                "
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="dbu in availableDatabaseUsers"
                                                :key="dbu.value"
                                                :value="dbu.value.toString()"
                                            >
                                                {{ dbu.label }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError :message="errors.database_user_id" />
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex justify-end gap-3 pt-4">
                            <Button
                                type="button"
                                variant="outline"
                                @click="navigateToSites"
                            >
                                Cancel
                            </Button>
                            <Button type="submit" :disabled="processing">
                                {{ processing ? 'Installing...' : 'Install WordPress' }}
                            </Button>
                        </div>
                    </Form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
