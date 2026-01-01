<script setup lang="ts">
import {
    index,
    store,
} from '@/actions/Nip/Site/Http/Controllers/SiteController';
import {
    branches as fetchBranches,
    repositories as fetchRepositories,
} from '@/actions/Nip/SourceControl/Http/Controllers/SourceControlController';
import SiteTypeIcon from '@/components/icons/SiteTypeIcon.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Combobox,
    ComboboxAnchor,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxList,
    ComboboxTrigger,
} from '@/components/ui/combobox';
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
import { Form, Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { Check, ChevronDown, GitBranch, Loader2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import ConfigureDomainModal from './partials/ConfigureDomainModal.vue';

interface SiteTypeData {
    value: string;
    label: string;
    webDirectory: string;
    buildCommand: string | null;
    isPhpBased: boolean;
}

interface SelectOption {
    value: string;
    label: string;
}

interface SourceControlOption {
    id: number;
    provider: string;
    providerLabel: string;
    name: string;
}

interface Repository {
    id: number;
    full_name: string;
    name: string;
    private: boolean;
    default_branch: string;
}

interface NumericSelectOption {
    value: number;
    label: string;
}

interface DatabaseOption {
    value: number;
    label: string;
    userIds: number[];
}

interface WwwRedirectTypeOption extends SelectOption {
    description: string;
    isDefault: boolean;
}

interface PhpVersionOption extends SelectOption {
    isDefault: boolean;
}

interface ServerOption {
    id: number;
    slug: string;
    name: string;
    phpVersions: PhpVersionOption[];
    unixUsers: SelectOption[];
    databases: DatabaseOption[];
    databaseUsers: NumericSelectOption[];
}

interface Props {
    siteType: SiteTypeData;
    servers: ServerOption[];
    sourceControls: SourceControlOption[];
    packageManagers: SelectOption[];
    wwwRedirectTypes: WwwRedirectTypeOption[];
}

const props = defineProps<Props>();

const selectedServer = ref<string>(props.servers[0]?.id.toString() || '');
const selectedPhpVersion = ref<string>('');
const selectedUser = ref<string>('');
const selectedWwwRedirect = ref<string>(
    props.wwwRedirectTypes.find((t) => t.isDefault)?.value || 'from_www',
);
const allowWildcard = ref(false);
const domainValue = ref('');
const showDomainModal = ref(false);
const installComposer = ref(true);
const createDatabase = ref(false);
const selectedDatabase = ref<string | undefined>(undefined);
const selectedDatabaseUser = ref<string | undefined>(undefined);

const selectedSourceControl = ref<string>('');
const selectedRepository = ref<string>('');
const selectedBranch = ref<string>('');
const repositories = ref<Repository[]>([]);
const branches = ref<string[]>([]);
const loadingRepositories = ref(false);
const loadingBranches = ref(false);
const repositorySearchQuery = ref<string>('');
const repositoryComboboxOpen = ref(false);

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
    if (!selectedDatabaseObject.value) return [];
    const allowedUserIds = selectedDatabaseObject.value.userIds;
    return (currentServer.value?.databaseUsers || []).filter((dbu) =>
        allowedUserIds.includes(dbu.value),
    );
});

const defaultPhpVersion = computed(() => {
    const defaultVersion = availablePhpVersions.value.find((v) => v.isDefault);
    return defaultVersion?.value || availablePhpVersions.value[0]?.value || '';
});

const defaultUnixUser = computed(() => {
    return availableUnixUsers.value[0]?.value || '';
});

const filteredRepositories = computed(() => {
    if (!repositorySearchQuery.value) {
        return repositories.value;
    }
    const query = repositorySearchQuery.value.toLowerCase();
    return repositories.value.filter(
        (repo) =>
            repo.full_name.toLowerCase().includes(query) ||
            repo.name.toLowerCase().includes(query),
    );
});

const selectedRepositoryDisplay = computed(() => {
    const repo = repositories.value.find(
        (r) => r.full_name === selectedRepository.value,
    );
    return repo?.full_name || '';
});

// Update selected values when server changes
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

// Reset database user when database changes
watch(selectedDatabase, () => {
    selectedDatabaseUser.value = undefined;
});

// Load repositories when source control changes
watch(selectedSourceControl, async (newValue) => {
    selectedRepository.value = '';
    selectedBranch.value = '';
    repositories.value = [];
    branches.value = [];
    repositorySearchQuery.value = '';

    if (!newValue) return;

    loadingRepositories.value = true;
    try {
        const response = await axios.get(
            fetchRepositories.url({ sourceControl: parseInt(newValue) }),
        );
        repositories.value = response.data;
    } catch (error) {
        console.error('Failed to load repositories:', error);
    } finally {
        loadingRepositories.value = false;
    }
});

// Load branches when repository changes
watch(selectedRepository, async (newValue) => {
    selectedBranch.value = '';
    branches.value = [];

    if (!newValue || !selectedSourceControl.value) return;

    loadingBranches.value = true;
    try {
        const response = await axios.get(
            fetchBranches.url({
                sourceControl: parseInt(selectedSourceControl.value),
                repository: newValue,
            }),
        );
        branches.value = response.data;

        // Auto-select default branch if available
        const selectedRepo = repositories.value.find(
            (r) => r.full_name === newValue,
        );
        if (
            selectedRepo &&
            branches.value.includes(selectedRepo.default_branch)
        ) {
            selectedBranch.value = selectedRepo.default_branch;
        } else if (branches.value.length > 0) {
            selectedBranch.value = branches.value[0];
        }
    } catch (error) {
        console.error('Failed to load branches:', error);
    } finally {
        loadingBranches.value = false;
    }
});

// Use server-provided defaults from SiteType enum
const defaultWebDirectory = computed(() => props.siteType.webDirectory);
const defaultBuildCommand = computed(() => props.siteType.buildCommand || '');
const isPhpType = computed(() => props.siteType.isPhpBased);

const selectedWwwRedirectLabel = computed(() => {
    const type = props.wwwRedirectTypes.find(
        (t) => t.value === selectedWwwRedirect.value,
    );
    if (!type) return '';

    if (type.value === 'from_www') return 'Will redirect from www.';
    if (type.value === 'to_www') return 'Will redirect to www.';
    return 'No redirects.';
});

function navigateToSites() {
    router.visit(index.url());
}
</script>

<template>
    <Head :title="`Install ${siteType.label}`" />

    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <Card class="mx-auto w-full max-w-2xl">
                <CardHeader>
                    <div class="flex items-center gap-3">
                        <SiteTypeIcon :type="siteType.value" class="size-8" />
                        <div>
                            <CardTitle
                                >Install a
                                {{ siteType.label }} application</CardTitle
                            >
                            <CardDescription>
                                Configure your new {{ siteType.label }} site.
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
                        <input
                            type="hidden"
                            name="type"
                            :value="siteType.value"
                        />
                        <input
                            type="hidden"
                            name="www_redirect_type"
                            :value="selectedWwwRedirect"
                        />
                        <input
                            type="hidden"
                            name="allow_wildcard"
                            :value="allowWildcard ? '1' : '0'"
                        />

                        <!-- Domain first - most important -->
                        <div class="space-y-2">
                            <Label for="domain">Domain</Label>
                            <Input
                                id="domain"
                                name="domain"
                                v-model="domainValue"
                                placeholder="example.com"
                            />
                            <p
                                class="flex items-center gap-1 text-xs text-muted-foreground"
                            >
                                <span>{{ selectedWwwRedirectLabel }}</span>
                                <button
                                    type="button"
                                    class="text-primary hover:underline"
                                    @click="showDomainModal = true"
                                >
                                    Change
                                </button>
                            </p>
                            <InputError :message="errors.domain" />
                        </div>

                        <!-- Server, User, PHP in one row -->
                        <div
                            class="grid gap-4"
                            :class="
                                isPhpType && availablePhpVersions.length > 0
                                    ? 'grid-cols-3'
                                    : 'grid-cols-2'
                            "
                        >
                            <div class="space-y-2">
                                <Label for="server_id">Server</Label>
                                <Select
                                    name="server_id"
                                    v-model="selectedServer"
                                >
                                    <SelectTrigger>
                                        <SelectValue
                                            placeholder="Select a server"
                                        />
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
                                        <SelectValue
                                            placeholder="Select a user"
                                        />
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
                                v-if="
                                    isPhpType && availablePhpVersions.length > 0
                                "
                                class="space-y-2"
                            >
                                <Label for="php_version">PHP version</Label>
                                <Select
                                    name="php_version"
                                    v-model="selectedPhpVersion"
                                >
                                    <SelectTrigger>
                                        <SelectValue
                                            placeholder="Select PHP version"
                                        />
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

                        <!-- Source Control Provider -->
                        <div v-if="sourceControls.length > 0" class="space-y-2">
                            <Label for="source_control_id"
                                >Source Control Provider (optional)</Label
                            >
                            <Select
                                name="source_control_id"
                                v-model="selectedSourceControl"
                            >
                                <SelectTrigger>
                                    <SelectValue
                                        placeholder="Select a provider"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="sc in sourceControls"
                                        :key="sc.id"
                                        :value="sc.id.toString()"
                                    >
                                        {{ sc.providerLabel }} ({{ sc.name }})
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="errors.source_control_id" />
                        </div>

                        <!-- Repository and Branch in one row -->
                        <div
                            v-if="selectedSourceControl"
                            class="grid grid-cols-3 gap-4"
                        >
                            <div class="col-span-2 space-y-2">
                                <Label for="repository">Repository</Label>
                                <input
                                    type="hidden"
                                    name="repository"
                                    :value="selectedRepository"
                                />
                                <Combobox
                                    v-model="selectedRepository"
                                    v-model:open="repositoryComboboxOpen"
                                    v-model:search-term="repositorySearchQuery"
                                    :disabled="loadingRepositories"
                                    :display-value="
                                        () => selectedRepositoryDisplay
                                    "
                                >
                                    <ComboboxAnchor class="w-full">
                                        <div class="relative w-full">
                                            <ComboboxInput
                                                class="w-full pr-8"
                                                :placeholder="
                                                    loadingRepositories
                                                        ? 'Loading repositories...'
                                                        : 'Search repository...'
                                                "
                                            />
                                            <ComboboxTrigger
                                                class="absolute top-0 right-0 flex h-full items-center px-2"
                                            >
                                                <Loader2
                                                    v-if="loadingRepositories"
                                                    class="size-4 animate-spin text-muted-foreground"
                                                />
                                                <ChevronDown
                                                    v-else
                                                    class="size-4 text-muted-foreground"
                                                />
                                            </ComboboxTrigger>
                                        </div>
                                    </ComboboxAnchor>
                                    <ComboboxList
                                        class="max-h-[300px] w-[var(--reka-combobox-trigger-width)] overflow-y-auto"
                                    >
                                        <ComboboxEmpty>
                                            <span
                                                class="block px-2 py-1.5 text-sm text-muted-foreground"
                                            >
                                                No repositories found.
                                            </span>
                                        </ComboboxEmpty>
                                        <ComboboxItem
                                            v-for="repo in filteredRepositories"
                                            :key="repo.id"
                                            :value="repo.full_name"
                                            class="cursor-pointer"
                                        >
                                            <span class="flex-1">{{
                                                repo.full_name
                                            }}</span>
                                            <span
                                                v-if="repo.private"
                                                class="ml-1 text-xs text-muted-foreground"
                                                >(private)</span
                                            >
                                            <Check
                                                v-if="
                                                    selectedRepository ===
                                                    repo.full_name
                                                "
                                                class="ml-2 size-4 shrink-0"
                                            />
                                        </ComboboxItem>
                                    </ComboboxList>
                                </Combobox>
                                <InputError :message="errors.repository" />
                            </div>

                            <div class="space-y-2">
                                <Label for="branch">Branch</Label>
                                <Select
                                    name="branch"
                                    v-model="selectedBranch"
                                    :disabled="
                                        loadingBranches || !selectedRepository
                                    "
                                >
                                    <SelectTrigger>
                                        <template v-if="loadingBranches">
                                            <Loader2
                                                class="mr-2 size-4 animate-spin"
                                            />
                                            Loading...
                                        </template>
                                        <SelectValue
                                            v-else
                                            :placeholder="
                                                !selectedRepository
                                                    ? 'Select repository first'
                                                    : 'Select branch'
                                            "
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="branch in branches"
                                            :key="branch"
                                            :value="branch"
                                        >
                                            {{ branch }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="errors.branch" />
                            </div>
                        </div>

                        <!-- Manual Repository input (when no source control selected) -->
                        <div v-else class="grid grid-cols-3 gap-4">
                            <div class="col-span-2 space-y-2">
                                <Label for="repository"
                                    >Repository (optional)</Label
                                >
                                <div class="relative">
                                    <GitBranch
                                        class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                    />
                                    <Input
                                        id="repository"
                                        name="repository"
                                        class="pl-9"
                                        placeholder="git@github.com:NETipar/netipar.git"
                                    />
                                </div>
                                <InputError :message="errors.repository" />
                            </div>

                            <div class="space-y-2">
                                <Label for="branch">Branch</Label>
                                <Input
                                    id="branch"
                                    name="branch"
                                    placeholder="main"
                                    default-value="main"
                                />
                                <InputError :message="errors.branch" />
                            </div>
                        </div>

                        <!-- Root and Web directory -->
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="root_directory"
                                    >Root directory</Label
                                >
                                <Input
                                    id="root_directory"
                                    name="root_directory"
                                    default-value="/"
                                />
                                <p class="text-xs text-muted-foreground">
                                    The directory where your application code
                                    lives, relative to the site path.
                                </p>
                                <InputError :message="errors.root_directory" />
                            </div>

                            <div class="space-y-2">
                                <Label for="web_directory">Web directory</Label>
                                <Input
                                    id="web_directory"
                                    name="web_directory"
                                    :default-value="defaultWebDirectory"
                                />
                                <p class="text-xs text-muted-foreground">
                                    The publicly accessible directory, relative
                                    to the root directory.
                                </p>
                                <InputError :message="errors.web_directory" />
                            </div>
                        </div>

                        <!-- Toggles -->
                        <div
                            v-if="isPhpType"
                            class="flex items-center justify-between"
                        >
                            <input
                                type="hidden"
                                name="install_composer"
                                :value="installComposer ? '1' : '0'"
                            />
                            <div class="space-y-0.5">
                                <Label for="install_composer"
                                    >Install Composer dependencies</Label
                                >
                                <p class="text-xs text-muted-foreground">
                                    Run
                                    <code class="rounded bg-muted px-1"
                                        >composer install</code
                                    >
                                    after cloning
                                </p>
                            </div>
                            <Switch
                                id="install_composer"
                                v-model="installComposer"
                            />
                        </div>

                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <input
                                    type="hidden"
                                    name="create_database"
                                    :value="createDatabase ? '1' : '0'"
                                />
                                <div class="space-y-0.5">
                                    <Label for="create_database"
                                        >Create database</Label
                                    >
                                    <p class="text-xs text-muted-foreground">
                                        Create a new database for this site
                                    </p>
                                </div>
                                <Switch
                                    id="create_database"
                                    v-model="createDatabase"
                                />
                            </div>

                            <!-- Database selection (when not creating a new one) -->
                            <div
                                v-if="
                                    !createDatabase &&
                                    availableDatabases.length > 0
                                "
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
                                            <SelectValue
                                                placeholder="Select a database"
                                            />
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
                                    <Label for="database_user_id"
                                        >Database user</Label
                                    >
                                    <Select
                                        v-model="selectedDatabaseUser"
                                        :disabled="
                                            !selectedDatabase ||
                                            availableDatabaseUsers.length === 0
                                        "
                                    >
                                        <SelectTrigger>
                                            <SelectValue
                                                :placeholder="
                                                    !selectedDatabase
                                                        ? 'Select database first'
                                                        : availableDatabaseUsers.length ===
                                                            0
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
                                    <InputError
                                        :message="errors.database_user_id"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Package manager and Build command -->
                        <div class="grid grid-cols-3 gap-4">
                            <div class="space-y-2">
                                <Label for="package_manager"
                                    >Package manager</Label
                                >
                                <Select
                                    name="package_manager"
                                    default-value="npm"
                                >
                                    <SelectTrigger>
                                        <SelectValue
                                            placeholder="Select package manager"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="pm in packageManagers"
                                            :key="pm.value"
                                            :value="pm.value"
                                        >
                                            {{ pm.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="errors.package_manager" />
                            </div>

                            <div class="col-span-2 space-y-2">
                                <Label for="build_command">Build command</Label>
                                <Input
                                    id="build_command"
                                    name="build_command"
                                    :default-value="defaultBuildCommand"
                                    placeholder="npm run build"
                                />
                                <InputError :message="errors.build_command" />
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 pt-4">
                            <Button
                                type="button"
                                variant="outline"
                                @click="navigateToSites"
                            >
                                Cancel
                            </Button>
                            <Button type="submit" :disabled="processing">
                                {{ processing ? 'Creating...' : 'Create site' }}
                            </Button>
                        </div>
                    </Form>
                </CardContent>
            </Card>
        </div>

        <ConfigureDomainModal
            v-model:open="showDomainModal"
            :domain="domainValue"
            v-model:www-redirect-type="selectedWwwRedirect"
            v-model:allow-wildcard="allowWildcard"
            :www-redirect-types="wwwRedirectTypes"
        />
    </AppLayout>
</template>
