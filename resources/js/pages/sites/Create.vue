<script setup lang="ts">
import {
    index,
    store,
} from '@/actions/Nip/Site/Http/Controllers/SiteController';
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
import { useSiteCreationForm } from '@/composables/useSiteCreationForm';
import AppLayout from '@/layouts/AppLayout.vue';
import type {
    ServerOption,
    SiteTypeData,
    SourceControlOption,
    WwwRedirectTypeOption,
} from '@/types/site-creation';
import type { SelectOptionData } from '@/types/generated';
import { Form, Head, router } from '@inertiajs/vue3';
import { Check, ChevronDown, GitBranch, Loader2 } from 'lucide-vue-next';
import { computed, toRef } from 'vue';
import ConfigureDomainModal from './partials/ConfigureDomainModal.vue';

interface Props {
    siteType: SiteTypeData;
    servers: ServerOption[];
    sourceControls: SourceControlOption[];
    packageManagers: SelectOptionData[];
    wwwRedirectTypes: WwwRedirectTypeOption[];
}

const props = defineProps<Props>();

// Use the composable for form state management
const {
    // Server selection
    selectedServer,
    selectedPhpVersion,
    selectedUser,
    availablePhpVersions,
    availableUnixUsers,

    // Domain configuration
    selectedWwwRedirect,
    allowWildcard,
    domainValue,
    showDomainModal,
    selectedWwwRedirectLabel,

    // Site options
    installComposer,
    zeroDowntime,

    // Database
    createDatabase,
    selectedDatabase,
    selectedDatabaseUser,
    databaseName,
    databaseUser,
    databasePassword,
    availableDatabases,
    availableDatabaseUsers,
    generatePassword,

    // Source control
    selectedSourceControl,
    selectedRepository,
    selectedBranch,
    branches,
    loadingRepositories,
    loadingBranches,
    repositorySearchQuery,
    repositoryComboboxOpen,
    filteredRepositories,
    selectedRepositoryDisplay,
} = useSiteCreationForm({
    servers: toRef(props, 'servers'),
    wwwRedirectTypes: toRef(props, 'wwwRedirectTypes'),
});

// Use server-provided defaults from SiteType enum
const defaultWebDirectory = computed(() => props.siteType.webDirectory);
const defaultBuildCommand = computed(() => props.siteType.buildCommand || '');
const isPhpType = computed(() => props.siteType.isPhpBased);

function navigateToSites() {
    router.visit(index.url());
}
</script>

<template>
    <Head :title="`Install ${siteType.label}`" />

    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <Card class="mx-auto w-full max-w-2xl bg-white">
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

                        <div
                            v-if="siteType.supportsZeroDowntime"
                            class="flex items-center justify-between"
                        >
                            <input
                                type="hidden"
                                name="zero_downtime"
                                :value="zeroDowntime ? '1' : '0'"
                            />
                            <div class="space-y-0.5">
                                <Label for="zero_downtime">Zero downtime deployment</Label>
                                <p class="text-xs text-muted-foreground">
                                    Uses releases directory with symlink for
                                    instant switchover during deployments
                                </p>
                            </div>
                            <Switch id="zero_downtime" v-model="zeroDowntime" />
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
                                        <Button type="button" variant="outline" @click="generatePassword">
                                            Generate
                                        </Button>
                                    </div>
                                    <InputError :message="errors.database_password" />
                                </div>
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
                                                            availableDatabaseUsers.length ===
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
