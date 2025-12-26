<script setup lang="ts">
import { store } from '@/actions/Nip/Site/Http/Controllers/SiteController';
import SiteTypeIcon from '@/components/icons/SiteTypeIcon.vue';
import InputError from '@/components/InputError.vue';
import ConfigureDomainModal from './partials/ConfigureDomainModal.vue';
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
import { Form, Head, router } from '@inertiajs/vue3';
import { GitBranch } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface SiteTypeData {
    value: string;
    label: string;
}

interface SelectOption {
    value: string;
    label: string;
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
}

interface Props {
    siteType: SiteTypeData;
    servers: ServerOption[];
    packageManagers: SelectOption[];
    wwwRedirectTypes: WwwRedirectTypeOption[];
}

const props = defineProps<Props>();

const selectedServer = ref<string>(props.servers[0]?.id.toString() || '');
const selectedPhpVersion = ref<string>('');
const selectedUser = ref<string>('');
const selectedWwwRedirect = ref<string>(props.wwwRedirectTypes.find(t => t.isDefault)?.value || 'from_www');
const allowWildcard = ref(false);
const domainValue = ref('');
const showDomainModal = ref(false);
const installComposer = ref(true);
const createDatabase = ref(false);

const currentServer = computed(() => {
    return props.servers.find(s => s.id.toString() === selectedServer.value);
});

const availablePhpVersions = computed(() => {
    return currentServer.value?.phpVersions || [];
});

const availableUnixUsers = computed(() => {
    return currentServer.value?.unixUsers || [];
});

const defaultPhpVersion = computed(() => {
    const defaultVersion = availablePhpVersions.value.find(v => v.isDefault);
    return defaultVersion?.value || availablePhpVersions.value[0]?.value || '';
});

const defaultUnixUser = computed(() => {
    return availableUnixUsers.value[0]?.value || '';
});

// Update selected values when server changes
watch(selectedServer, () => {
    selectedPhpVersion.value = defaultPhpVersion.value;
    selectedUser.value = defaultUnixUser.value;
}, { immediate: true });

const typeDefaults: Record<string, { webDirectory: string; buildCommand: string | null }> = {
    laravel: { webDirectory: '/public', buildCommand: 'npm run build' },
    symfony: { webDirectory: '/public', buildCommand: null },
    statamic: { webDirectory: '/public', buildCommand: 'npm run build' },
    wordpress: { webDirectory: '/', buildCommand: null },
    phpmyadmin: { webDirectory: '/', buildCommand: null },
    php: { webDirectory: '/', buildCommand: null },
    nextjs: { webDirectory: '/', buildCommand: 'npm run build' },
    nuxtjs: { webDirectory: '/', buildCommand: 'npm run build' },
    html: { webDirectory: '/', buildCommand: null },
    other: { webDirectory: '/', buildCommand: null },
};

const defaultWebDirectory = computed(() => typeDefaults[props.siteType.value]?.webDirectory || '/');
const defaultBuildCommand = computed(() => typeDefaults[props.siteType.value]?.buildCommand || '');

const isPhpType = computed(() => {
    return ['laravel', 'symfony', 'statamic', 'wordpress', 'phpmyadmin', 'php'].includes(props.siteType.value);
});

const selectedWwwRedirectLabel = computed(() => {
    const type = props.wwwRedirectTypes.find(t => t.value === selectedWwwRedirect.value);
    if (!type) return '';

    if (type.value === 'from_www') return 'Will redirect from www.';
    if (type.value === 'to_www') return 'Will redirect to www.';
    return 'No redirects.';
});

function navigateToSites() {
    router.visit('/sites');
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
                            <CardTitle>Install a {{ siteType.label }} application</CardTitle>
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
                        <input type="hidden" name="type" :value="siteType.value" />
                        <input type="hidden" name="www_redirect_type" :value="selectedWwwRedirect" />
                        <input type="hidden" name="allow_wildcard" :value="allowWildcard ? '1' : '0'" />

                        <!-- Domain first - most important -->
                        <div class="space-y-2">
                            <Label for="domain">Domain</Label>
                            <Input
                                id="domain"
                                name="domain"
                                v-model="domainValue"
                                placeholder="example.com"
                            />
                            <p class="flex items-center gap-1 text-xs text-muted-foreground">
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
                        <div class="grid gap-4" :class="isPhpType && availablePhpVersions.length > 0 ? 'grid-cols-3' : 'grid-cols-2'">
                            <div class="space-y-2">
                                <Label for="server_id">Server</Label>
                                <Select
                                    name="server_id"
                                    v-model="selectedServer"
                                >
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
                                <Select
                                    name="user"
                                    v-model="selectedUser"
                                >
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

                            <div v-if="isPhpType && availablePhpVersions.length > 0" class="space-y-2">
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
                                            <span v-if="version.isDefault" class="ml-1 text-muted-foreground">(default)</span>
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="errors.php_version" />
                            </div>
                        </div>

                        <!-- Repository and Branch in one row -->
                        <div class="grid grid-cols-3 gap-4">
                            <div class="col-span-2 space-y-2">
                                <Label for="repository">Repository (optional)</Label>
                                <div class="relative">
                                    <GitBranch class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
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
                                <Label for="root_directory">Root directory</Label>
                                <Input
                                    id="root_directory"
                                    name="root_directory"
                                    default-value="/"
                                />
                                <p class="text-xs text-muted-foreground">
                                    The directory where your application code lives, relative to the site path.
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
                                    The publicly accessible directory, relative to the root directory.
                                </p>
                                <InputError :message="errors.web_directory" />
                            </div>
                        </div>

                        <!-- Toggles -->
                        <div v-if="isPhpType" class="flex items-center justify-between">
                            <input type="hidden" name="install_composer" :value="installComposer ? '1' : '0'" />
                            <div class="space-y-0.5">
                                <Label for="install_composer">Install Composer dependencies</Label>
                                <p class="text-xs text-muted-foreground">
                                    Run <code class="rounded bg-muted px-1">composer install</code> after cloning
                                </p>
                            </div>
                            <Switch
                                id="install_composer"
                                v-model="installComposer"
                            />
                        </div>

                        <div class="flex items-center justify-between">
                            <input type="hidden" name="create_database" :value="createDatabase ? '1' : '0'" />
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

                        <!-- Package manager and Build command -->
                        <div class="grid grid-cols-3 gap-4">
                            <div class="space-y-2">
                                <Label for="package_manager">Package manager</Label>
                                <Select name="package_manager" default-value="npm">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select package manager" />
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
