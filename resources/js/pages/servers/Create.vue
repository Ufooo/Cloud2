<script setup lang="ts">
import { ref, computed, type Component } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import InputError from '@/components/InputError.vue'
import DigitalOceanLogo from '@/components/icons/DigitalOceanLogo.vue'
import VultrLogo from '@/components/icons/VultrLogo.vue'
import CustomVpsLogo from '@/components/icons/CustomVpsLogo.vue'
import { index, create, store } from '@/actions/Nip/Server/Http/Controllers/ServerController'
import { ServerProvider, ServerType, type ServerProviderOptionData, type ServerTypeOptionData } from '@/types/server'
import type { BreadcrumbItem } from '@/types'

interface SelectOption {
    value: string | null
    label: string
}

interface Props {
    providers: ServerProviderOptionData[]
    serverTypes: ServerTypeOptionData[]
    phpVersions: SelectOption[]
    databaseTypes: SelectOption[]
    ubuntuVersions: SelectOption[]
    timezones: SelectOption[]
}

const props = defineProps<Props>()

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Servers',
        href: index.url(),
    },
    {
        title: 'Create Server',
        href: create.url(),
    },
]

// Form data
const formData = ref({
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
})

const errors = ref<Record<string, string>>({})
const processing = ref(false)

// Logo mapping for providers
const providerLogos: Record<ServerProvider, Component> = {
    [ServerProvider.DigitalOcean]: DigitalOceanLogo,
    [ServerProvider.Vultr]: VultrLogo,
    [ServerProvider.Custom]: CustomVpsLogo,
}

function getProviderLogo(provider: ServerProvider): Component {
    return providerLogos[provider] ?? CustomVpsLogo
}

// Computed
const selectedProvider = computed(() => {
    return props.providers.find(p => p.value === formData.value.provider)
})

const selectedServerType = computed(() => {
    return props.serverTypes.find(t => t.value === formData.value.type)
})

const canSubmit = computed(() =>
    formData.value.provider !== '' && formData.value.name.trim().length > 0
)

// Methods
function submitForm() {
    processing.value = true
    errors.value = {}

    router.post(store.url(), {
        name: formData.value.name,
        provider: formData.value.provider,
        type: formData.value.type,
        ip_address: formData.value.ip_address || null,
        private_ip_address: formData.value.private_ip_address || null,
        ssh_port: formData.value.ssh_port || '22',
        ubuntu_version: formData.value.ubuntu_version,
        php_version: formData.value.php_version,
        database_type: formData.value.database_type || null,
        timezone: formData.value.timezone,
    }, {
        onError: (err) => {
            errors.value = err
            processing.value = false
        },
        onFinish: () => {
            processing.value = false
        },
    })
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
                        Configure your server settings and choose the software stack.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <form @submit.prevent="submitForm" class="space-y-6">
                        <!-- Server Name -->
                        <div class="space-y-2">
                            <Label for="name">Server Name <span class="text-destructive">*</span></Label>
                            <Input
                                id="name"
                                v-model="formData.name"
                                type="text"
                                placeholder="my-production-server"
                                autofocus
                            />
                            <p class="text-sm text-muted-foreground">
                                A unique name to identify your server.
                            </p>
                            <InputError :message="errors.name" />
                        </div>

                        <!-- Provider -->
                        <div class="space-y-2">
                            <Label for="provider">Provider <span class="text-destructive">*</span></Label>
                            <Select v-model="formData.provider">
                                <SelectTrigger id="provider">
                                    <div v-if="formData.provider" class="flex items-center gap-3">
                                        <component
                                            :is="getProviderLogo(formData.provider as ServerProvider)"
                                            class="size-5 rounded"
                                        />
                                        <span>{{ selectedProvider?.label }}</span>
                                    </div>
                                    <SelectValue v-else placeholder="Select a provider" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="provider in props.providers"
                                        :key="provider.value"
                                        :value="provider.value"
                                    >
                                        <div class="flex items-center gap-3">
                                            <component
                                                :is="getProviderLogo(provider.value)"
                                                class="size-5 rounded"
                                            />
                                            <span>{{ provider.label }}</span>
                                        </div>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="errors.provider" />
                        </div>

                        <!-- Server Type -->
                        <div class="space-y-2">
                            <Label for="type">Server Type <span class="text-destructive">*</span></Label>
                            <Select v-model="formData.type">
                                <SelectTrigger id="type">
                                    <SelectValue v-if="selectedServerType">
                                        {{ selectedServerType.label }}
                                    </SelectValue>
                                    <SelectValue v-else placeholder="Select a server type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="serverType in props.serverTypes"
                                        :key="serverType.value"
                                        :value="serverType.value"
                                    >
                                        <div class="flex flex-col gap-0.5">
                                            <span class="font-medium">{{ serverType.label }}</span>
                                            <span class="text-xs text-muted-foreground">
                                                {{ serverType.description }}
                                            </span>
                                        </div>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="errors.type" />
                        </div>

                        <!-- Ubuntu Version -->
                        <div class="space-y-2">
                            <Label for="ubuntu_version">Server OS <span class="text-destructive">*</span></Label>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="ubuntu in props.ubuntuVersions"
                                    :key="ubuntu.value"
                                    type="button"
                                    class="rounded-lg border-2 px-4 py-2 text-sm font-medium transition-all hover:border-primary/50"
                                    :class="{
                                        'border-primary bg-primary/5': formData.ubuntu_version === ubuntu.value,
                                        'border-border': formData.ubuntu_version !== ubuntu.value,
                                    }"
                                    @click="formData.ubuntu_version = ubuntu.value"
                                >
                                    {{ ubuntu.label }}
                                </button>
                            </div>
                            <InputError :message="errors.ubuntu_version" />
                        </div>

                        <!-- PHP Version (only for app/web/worker types) -->
                        <div
                            v-if="['app', 'web', 'worker'].includes(formData.type)"
                            class="space-y-2"
                        >
                            <Label for="php_version">PHP Version <span class="text-destructive">*</span></Label>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="php in props.phpVersions"
                                    :key="php.value"
                                    type="button"
                                    class="rounded-lg border-2 px-4 py-2 text-sm font-medium transition-all hover:border-primary/50"
                                    :class="{
                                        'border-primary bg-primary/5': formData.php_version === php.value,
                                        'border-border': formData.php_version !== php.value,
                                    }"
                                    @click="formData.php_version = php.value"
                                >
                                    {{ php.label }}
                                </button>
                            </div>
                            <InputError :message="errors.php_version" />
                        </div>

                        <!-- Database Type (only for app/database types) -->
                        <div
                            v-if="['app', 'database'].includes(formData.type)"
                            class="space-y-2"
                        >
                            <Label for="database_type">Database <span class="text-destructive">*</span></Label>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="db in props.databaseTypes"
                                    :key="db.value"
                                    type="button"
                                    class="rounded-lg border-2 px-4 py-2 text-sm font-medium transition-all hover:border-primary/50"
                                    :class="{
                                        'border-primary bg-primary/5': formData.database_type === db.value,
                                        'border-border': formData.database_type !== db.value,
                                    }"
                                    @click="formData.database_type = db.value"
                                >
                                    {{ db.label }}
                                </button>
                            </div>
                            <InputError :message="errors.database_type" />
                        </div>

                        <!-- Timezone -->
                        <div class="space-y-2">
                            <Label for="timezone">Timezone <span class="text-destructive">*</span></Label>
                            <Select v-model="formData.timezone">
                                <SelectTrigger id="timezone">
                                    <SelectValue placeholder="Select timezone" />
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
                            <InputError :message="errors.timezone" />
                        </div>

                        <!-- Network Configuration -->
                        <div class="grid gap-4 md:grid-cols-3">
                            <div class="space-y-2">
                                <Label for="ip_address">IP Address <span class="text-destructive">*</span></Label>
                                <Input
                                    id="ip_address"
                                    v-model="formData.ip_address"
                                    type="text"
                                    placeholder="192.168.1.1"
                                />
                                <InputError :message="errors.ip_address" />
                            </div>

                            <div class="space-y-2">
                                <Label for="private_ip_address">Private IP Address</Label>
                                <Input
                                    id="private_ip_address"
                                    v-model="formData.private_ip_address"
                                    type="text"
                                    placeholder="10.0.0.1"
                                />
                                <InputError :message="errors.private_ip_address" />
                            </div>

                            <div class="space-y-2">
                                <Label for="ssh_port">SSH Port <span class="text-destructive">*</span></Label>
                                <Input
                                    id="ssh_port"
                                    v-model="formData.ssh_port"
                                    type="text"
                                    placeholder="22"
                                />
                                <InputError :message="errors.ssh_port" />
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="flex items-center justify-end gap-3 border-t pt-6">
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
                                :disabled="!canSubmit || processing"
                            >
                                <template v-if="processing">
                                    Creating...
                                </template>
                                <template v-else>
                                    Create Server
                                </template>
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
