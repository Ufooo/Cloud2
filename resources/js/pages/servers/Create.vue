<script setup lang="ts">
import {
    create,
    index,
    store,
} from '@/actions/Nip/Server/Http/Controllers/ServerController';
import InputError from '@/components/InputError.vue';
import CustomVpsLogo from '@/components/icons/CustomVpsLogo.vue';
import DigitalOceanLogo from '@/components/icons/DigitalOceanLogo.vue';
import VultrLogo from '@/components/icons/VultrLogo.vue';
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
import AppLayout from '@/layouts/AppLayout.vue';
import {
    ServerProvider,
    ServerType,
    type BreadcrumbItem,
    type ServerProviderOptionData,
    type ServerTypeOptionData,
} from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, type Component } from 'vue';

interface SelectOption {
    value: string | null;
    label: string;
}

interface Props {
    providers: ServerProviderOptionData[];
    serverTypes: ServerTypeOptionData[];
    phpVersions: SelectOption[];
    databaseTypes: SelectOption[];
    ubuntuVersions: SelectOption[];
    timezones: SelectOption[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Servers',
        href: index.url(),
    },
    {
        title: 'Create Server',
        href: create.url(),
    },
];

const form = useForm({
    provider: '' as ServerProvider | '',
    name: '',
    type: ServerType.App as ServerType,
    ip_address: '',
    private_ip_address: '',
    ssh_port: '22',
    ubuntu_version: '24.04',
    php_version: 'php84',
    database_type: null as string | null,
    timezone: 'UTC',
});

// Logo mapping for providers
const providerLogos: Record<ServerProvider, Component> = {
    [ServerProvider.DigitalOcean]: DigitalOceanLogo,
    [ServerProvider.Vultr]: VultrLogo,
    [ServerProvider.Custom]: CustomVpsLogo,
};

function getProviderLogo(provider: ServerProvider): Component {
    return providerLogos[provider] ?? CustomVpsLogo;
}

// Computed
const selectedProvider = computed(() => {
    return props.providers.find((p) => p.value === form.provider);
});

const selectedServerType = computed(() => {
    return props.serverTypes.find((t) => t.value === form.type);
});

const canSubmit = computed(
    () => form.provider !== '' && form.name.trim().length > 0,
);

function submitForm() {
    form.transform((data) => ({
        ...data,
        ip_address: data.ip_address || null,
        private_ip_address: data.private_ip_address || null,
        ssh_port: data.ssh_port || '22',
        database_type: data.database_type || null,
    })).post(store.url());
}
</script>

<template>
    <Head title="Create Server" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <Card class="mx-auto w-full max-w-2xl">
                <CardHeader>
                    <CardTitle>Create Server</CardTitle>
                    <CardDescription>
                        Configure your server settings and choose the software
                        stack.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <form @submit.prevent="submitForm" class="space-y-6">
                        <!-- Server Name -->
                        <div class="space-y-2">
                            <Label for="name"
                                >Server Name
                                <span class="text-destructive">*</span></Label
                            >
                            <Input
                                id="name"
                                v-model="form.name"
                                type="text"
                                placeholder="my-production-server"
                                autofocus
                            />
                            <p class="text-sm text-muted-foreground">
                                A unique name to identify your server.
                            </p>
                            <InputError :message="form.errors.name" />
                        </div>

                        <!-- Provider -->
                        <div class="space-y-2">
                            <Label for="provider"
                                >Provider
                                <span class="text-destructive">*</span></Label
                            >
                            <Select v-model="form.provider">
                                <SelectTrigger id="provider">
                                    <div
                                        v-if="form.provider"
                                        class="flex items-center gap-3"
                                    >
                                        <component
                                            :is="
                                                getProviderLogo(
                                                    form.provider as ServerProvider,
                                                )
                                            "
                                            class="size-5 rounded"
                                        />
                                        <span>{{
                                            selectedProvider?.label
                                        }}</span>
                                    </div>
                                    <SelectValue
                                        v-else
                                        placeholder="Select a provider"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="provider in props.providers"
                                        :key="provider.value"
                                        :value="provider.value"
                                    >
                                        <div class="flex items-center gap-3">
                                            <component
                                                :is="
                                                    getProviderLogo(
                                                        provider.value,
                                                    )
                                                "
                                                class="size-5 rounded"
                                            />
                                            <span>{{ provider.label }}</span>
                                        </div>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.provider" />
                        </div>

                        <!-- Server Type -->
                        <div class="space-y-2">
                            <Label for="type"
                                >Server Type
                                <span class="text-destructive">*</span></Label
                            >
                            <Select v-model="form.type">
                                <SelectTrigger id="type">
                                    <SelectValue v-if="selectedServerType">
                                        {{ selectedServerType.label }}
                                    </SelectValue>
                                    <SelectValue
                                        v-else
                                        placeholder="Select a server type"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="serverType in props.serverTypes"
                                        :key="serverType.value"
                                        :value="serverType.value"
                                    >
                                        <div class="flex flex-col gap-0.5">
                                            <span class="font-medium">{{
                                                serverType.label
                                            }}</span>
                                            <span
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ serverType.description }}
                                            </span>
                                        </div>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.type" />
                        </div>

                        <!-- Ubuntu Version -->
                        <div class="space-y-2">
                            <Label for="ubuntu_version"
                                >Server OS
                                <span class="text-destructive">*</span></Label
                            >
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="ubuntu in props.ubuntuVersions"
                                    :key="ubuntu.value"
                                    type="button"
                                    class="rounded-lg border-2 px-4 py-2 text-sm font-medium transition-all hover:border-primary/50"
                                    :class="{
                                        'border-primary bg-primary/5':
                                            form.ubuntu_version ===
                                            ubuntu.value,
                                        'border-border':
                                            form.ubuntu_version !==
                                            ubuntu.value,
                                    }"
                                    @click="form.ubuntu_version = ubuntu.value"
                                >
                                    {{ ubuntu.label }}
                                </button>
                            </div>
                            <InputError :message="form.errors.ubuntu_version" />
                        </div>

                        <!-- PHP Version (only for app/web/worker types) -->
                        <div
                            v-if="['app', 'web', 'worker'].includes(form.type)"
                            class="space-y-2"
                        >
                            <Label for="php_version"
                                >PHP Version
                                <span class="text-destructive">*</span></Label
                            >
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="php in props.phpVersions"
                                    :key="php.value"
                                    type="button"
                                    class="rounded-lg border-2 px-4 py-2 text-sm font-medium transition-all hover:border-primary/50"
                                    :class="{
                                        'border-primary bg-primary/5':
                                            form.php_version === php.value,
                                        'border-border':
                                            form.php_version !== php.value,
                                    }"
                                    @click="form.php_version = php.value"
                                >
                                    {{ php.label }}
                                </button>
                            </div>
                            <InputError :message="form.errors.php_version" />
                        </div>

                        <!-- Database Type (only for app/database types) -->
                        <div
                            v-if="['app', 'database'].includes(form.type)"
                            class="space-y-2"
                        >
                            <Label for="database_type"
                                >Database
                                <span class="text-destructive">*</span></Label
                            >
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="db in props.databaseTypes"
                                    :key="db.value"
                                    type="button"
                                    class="rounded-lg border-2 px-4 py-2 text-sm font-medium transition-all hover:border-primary/50"
                                    :class="{
                                        'border-primary bg-primary/5':
                                            form.database_type === db.value,
                                        'border-border':
                                            form.database_type !== db.value,
                                    }"
                                    @click="form.database_type = db.value"
                                >
                                    {{ db.label }}
                                </button>
                            </div>
                            <InputError :message="form.errors.database_type" />
                        </div>

                        <!-- Timezone -->
                        <div class="space-y-2">
                            <Label for="timezone"
                                >Timezone
                                <span class="text-destructive">*</span></Label
                            >
                            <Select v-model="form.timezone">
                                <SelectTrigger id="timezone">
                                    <SelectValue
                                        placeholder="Select timezone"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="tz in props.timezones"
                                        :key="tz.value"
                                        :value="tz.value"
                                    >
                                        {{ tz.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.timezone" />
                        </div>

                        <!-- Network Configuration -->
                        <div class="grid gap-4 md:grid-cols-3">
                            <div class="space-y-2">
                                <Label for="ip_address"
                                    >IP Address
                                    <span class="text-destructive"
                                        >*</span
                                    ></Label
                                >
                                <Input
                                    id="ip_address"
                                    v-model="form.ip_address"
                                    type="text"
                                    placeholder="192.168.1.1"
                                />
                                <InputError :message="form.errors.ip_address" />
                            </div>

                            <div class="space-y-2">
                                <Label for="private_ip_address"
                                    >Private IP Address</Label
                                >
                                <Input
                                    id="private_ip_address"
                                    v-model="form.private_ip_address"
                                    type="text"
                                    placeholder="10.0.0.1"
                                />
                                <InputError
                                    :message="form.errors.private_ip_address"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label for="ssh_port"
                                    >SSH Port
                                    <span class="text-destructive"
                                        >*</span
                                    ></Label
                                >
                                <Input
                                    id="ssh_port"
                                    v-model="form.ssh_port"
                                    type="text"
                                    placeholder="22"
                                />
                                <InputError :message="form.errors.ssh_port" />
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div
                            class="flex items-center justify-end gap-3 border-t pt-6"
                        >
                            <Button
                                type="button"
                                variant="outline"
                                as="a"
                                :href="index.url()"
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                :disabled="!canSubmit || form.processing"
                            >
                                <template v-if="form.processing">
                                    Creating...
                                </template>
                                <template v-else> Create Server </template>
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
