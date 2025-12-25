<script setup lang="ts">
import {
    installVersion,
    setDefault,
    uninstallVersion,
    updateSettings,
} from '@/actions/Nip/Php/Http/Controllers/PhpController';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
import { useConfirmation } from '@/composables/useConfirmation';
import ServerLayout from '@/layouts/ServerLayout.vue';
import type { Server } from '@/types';
import type {
    PhpSettingData,
    PhpVersionData,
} from '@/types/generated';
import { Form, Head, router, usePoll } from '@inertiajs/vue3';
import { Code, MoreHorizontal, Plus, Trash2 } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

// Extend PhpVersionData with resource-specific fields
interface PhpVersionResource extends PhpVersionData {
    statusLabel: string;
    statusBadgeVariant: 'default' | 'secondary' | 'destructive' | 'outline';
    can: {
        delete: boolean;
        setCliDefault: boolean;
        setSiteDefault: boolean;
    };
}

interface AvailableVersion {
    value: string;
    label: string;
}

// Props from Inertia - Resource::make wraps data in { data: {} }
interface Props {
    server: Server;
    phpSetting: { data: PhpSettingData } | null;
    phpVersions: { data: PhpVersionResource[] };
    availableVersions: AvailableVersion[];
}

const props = defineProps<Props>();

const { confirmButton } = useConfirmation();

// Unwrap phpVersions from { data: [] } format
const phpVersions = computed(() => props.phpVersions?.data ?? []);

// Poll while there are installing or uninstalling PHP versions
const hasPendingVersions = computed(() =>
    phpVersions.value.some(
        (version) =>
            version.status === 'installing' ||
            version.status === 'uninstalling' ||
            version.status === 'pending',
    ),
);

const { start: startPolling, stop: stopPolling } = usePoll(
    3000,
    { only: ['phpVersions'] },
    { autoStart: false },
);

watch(
    hasPendingVersions,
    (pending) => {
        if (pending) {
            startPolling();
        } else {
            stopPolling();
        }
    },
    { immediate: true },
);

// Get initial setting values - access props directly
const initialSetting = props.phpSetting?.data;

// Settings form - reactive state for form values
const settingsFormData = reactive({
    max_upload_size: initialSetting?.maxUploadSize ?? 100,
    max_execution_time: initialSetting?.maxExecutionTime ?? 60,
    opcache_enabled: initialSetting?.opcacheEnabled ?? true,
});

// Computed for hidden input value - ensures reactivity
const opcacheHiddenValue = computed(() => settingsFormData.opcache_enabled ? '1' : '0');

// Install version dialog
const showInstallDialog = ref(false);
const selectedVersion = ref<string>('');

const notInstalledVersions = computed(() => {
    const installedVersionValues = phpVersions.value.map((v) => v.version);
    return props.availableVersions.filter(
        (v) => !installedVersionValues.includes(v.value)
    );
});

function openInstallDialog() {
    selectedVersion.value = '';
    showInstallDialog.value = true;
}

function installPhpVersion() {
    if (!selectedVersion.value) return;

    router.post(
        installVersion.url(props.server),
        { version: selectedVersion.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                showInstallDialog.value = false;
                selectedVersion.value = '';
            },
        }
    );
}

// Version actions
async function handleUninstall(version: PhpVersionResource) {
    const confirmed = await confirmButton({
        title: 'Uninstall PHP Version',
        description: `Are you sure you want to uninstall ${getVersionLabel(version.version)}? This action cannot be undone.`,
        confirmText: 'Uninstall',
    });

    if (!confirmed) return;

    router.delete(
        uninstallVersion.url({ server: props.server, phpVersion: version.id }),
        {
            preserveScroll: true,
        }
    );
}

function handleSetDefault(version: PhpVersionResource, type: 'cli' | 'site') {
    router.post(
        setDefault.url({ server: props.server, phpVersion: version.id }),
        { type },
        {
            preserveScroll: true,
        }
    );
}

function getVersionLabel(versionValue: string): string {
    const found = props.availableVersions.find((v) => v.value === versionValue);
    return found?.label ?? versionValue;
}
</script>

<template>
    <Head :title="`PHP - ${server.name}`" />

    <ServerLayout :server="server">
        <div class="space-y-6">
            <!-- Settings Section -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Code class="size-5" />
                        PHP Settings
                    </CardTitle>
                    <CardDescription>
                        Configure PHP settings for your server.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Form
                        v-bind="updateSettings.form(server)"
                        class="space-y-6"
                        #default="{ errors, processing }"
                    >
                        <div class="grid gap-6 sm:grid-cols-2">
                            <!-- Max Upload Size -->
                            <div class="space-y-2">
                                <Label for="max-upload-size">
                                    Max file upload size
                                </Label>
                                <div class="relative">
                                    <Input
                                        id="max-upload-size"
                                        v-model.number="settingsFormData.max_upload_size"
                                        name="max_upload_size"
                                        type="number"
                                        min="1"
                                        class="pr-16"
                                    />
                                    <span
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-muted-foreground"
                                    >
                                        MBs
                                    </span>
                                </div>
                                <InputError :message="errors.max_upload_size" />
                            </div>

                            <!-- Max Execution Time -->
                            <div class="space-y-2">
                                <Label for="max-execution-time">
                                    Max execution time
                                </Label>
                                <div class="relative">
                                    <Input
                                        id="max-execution-time"
                                        v-model.number="settingsFormData.max_execution_time"
                                        name="max_execution_time"
                                        type="number"
                                        min="1"
                                        class="pr-20"
                                    />
                                    <span
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-muted-foreground"
                                    >
                                        seconds
                                    </span>
                                </div>
                                <InputError :message="errors.max_execution_time" />
                            </div>
                        </div>

                        <!-- OPcache -->
                        <div class="flex items-center justify-between">
                            <div class="space-y-0.5">
                                <Label for="opcache-enabled">OPcache</Label>
                                <p class="text-sm text-muted-foreground">
                                    Enable OPcache for improved performance
                                </p>
                            </div>
                            <input
                                type="hidden"
                                name="opcache_enabled"
                                :value="opcacheHiddenValue"
                            />
                            <Switch
                                id="opcache-enabled"
                                v-model="settingsFormData.opcache_enabled"
                            />
                        </div>

                        <!-- Action Buttons -->
                        <Button
                            type="submit"
                            :disabled="processing"
                        >
                            {{ processing ? 'Saving...' : 'Save' }}
                        </Button>
                    </Form>
                </CardContent>
            </Card>

            <!-- Versions Section -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <Code class="size-5" />
                                PHP Versions
                            </CardTitle>
                            <CardDescription>
                                Manage PHP versions installed on your server.
                            </CardDescription>
                        </div>
                        <Button
                            variant="outline"
                            @click="openInstallDialog"
                            :disabled="notInstalledVersions.length === 0"
                        >
                            <Plus class="mr-2 size-4" />
                            Install version
                        </Button>
                    </div>
                </CardHeader>

                <CardContent>
                    <div
                        v-if="phpVersions.length === 0"
                        class="rounded-lg border border-dashed p-8 text-center"
                    >
                        <Code
                            class="mx-auto mb-4 size-12 text-muted-foreground opacity-50"
                        />
                        <h3 class="text-lg font-medium">
                            No PHP versions installed
                        </h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Install a PHP version to get started.
                        </p>
                        <Button
                            variant="outline"
                            class="mt-4"
                            @click="openInstallDialog"
                        >
                            <Plus class="mr-2 size-4" />
                            Install version
                        </Button>
                    </div>

                    <div v-else class="divide-y">
                        <div
                            v-for="version in phpVersions"
                            :key="version.id"
                            class="flex items-center justify-between py-4 first:pt-0 last:pb-0"
                        >
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <p class="font-medium">
                                        {{ getVersionLabel(version.version) }}
                                    </p>
                                    <Badge
                                        v-if="version.isSiteDefault"
                                        variant="default"
                                    >
                                        Site default
                                    </Badge>
                                    <Badge
                                        v-if="version.isCliDefault"
                                        variant="default"
                                    >
                                        CLI default
                                    </Badge>
                                    <Badge :variant="version.statusBadgeVariant">
                                        {{ version.statusLabel }}
                                    </Badge>
                                </div>
                                <p
                                    v-if="version.createdAt"
                                    class="text-sm text-muted-foreground"
                                >
                                    Installed on {{ version.createdAt }}
                                </p>
                            </div>

                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button variant="ghost" size="icon">
                                        <MoreHorizontal class="size-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem
                                        v-if="version.can.setSiteDefault"
                                        @click="
                                            handleSetDefault(version, 'site')
                                        "
                                    >
                                        Set as site default
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        v-if="version.can.setCliDefault"
                                        @click="handleSetDefault(version, 'cli')"
                                    >
                                        Set as CLI default
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator
                                        v-if="
                                            (version.can.setSiteDefault ||
                                                version.can.setCliDefault) &&
                                            version.can.delete
                                        "
                                    />
                                    <DropdownMenuItem
                                        v-if="version.can.delete"
                                        class="text-destructive focus:text-destructive"
                                        @click="handleUninstall(version)"
                                    >
                                        <Trash2 class="mr-2 size-4" />
                                        Uninstall
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Install Version Dialog -->
        <Dialog v-model:open="showInstallDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Install PHP version</DialogTitle>
                    <DialogDescription>
                        Select a PHP version to install on your server.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-4">
                    <div class="space-y-2">
                        <Label for="version">PHP Version</Label>
                        <Select v-model="selectedVersion" required>
                            <SelectTrigger id="version">
                                <SelectValue placeholder="Select a version" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="version in notInstalledVersions"
                                    :key="version.value"
                                    :value="version.value"
                                >
                                    {{ version.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="showInstallDialog = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        @click="installPhpVersion"
                        :disabled="!selectedVersion"
                    >
                        Install
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </ServerLayout>
</template>
