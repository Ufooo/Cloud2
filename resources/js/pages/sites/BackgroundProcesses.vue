<script setup lang="ts">
import {
    clearLogs,
    destroy,
    restart,
    start,
    stop,
    store,
    update,
    viewLogs,
    viewStatus,
} from '@/actions/Nip/BackgroundProcess/Http/Controllers/SiteBackgroundProcessController';
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useConfirmation } from '@/composables/useConfirmation';
import { useResourceStatusUpdates } from '@/composables/useResourceStatusUpdates';
import SiteLayout from '@/layouts/SiteLayout.vue';
import type { Site } from '@/types';
import type { PaginatedResponse } from '@/types/pagination';
import { Form, Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Activity,
    FileText,
    MoreHorizontal,
    Pause,
    Pencil,
    Play,
    Plus,
    RefreshCw,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

type BadgeVariant =
    | 'default'
    | 'secondary'
    | 'destructive'
    | 'outline'
    | null
    | undefined;

interface StopSignalOption {
    value: string;
    label: string;
}

interface BackgroundProcess {
    id: string;
    name: string;
    command: string;
    directory: string | null;
    user: string;
    processes: number;
    startsecs: number;
    stopwaitsecs: number;
    stopsignal: string;
    status: string;
    displayableStatus: string;
    statusBadgeVariant: BadgeVariant;
    supervisorProcessStatus: string | null;
    displayableSupervisorProcessStatus: string | null;
    supervisorBadgeVariant: BadgeVariant;
    createdAt: string | null;
    can: {
        update: boolean;
        delete: boolean;
        restart: boolean;
        start: boolean;
        stop: boolean;
    };
}

interface PhpVersion {
    version: string;
    binary: string;
}

interface Props {
    site: Site;
    processes: PaginatedResponse<BackgroundProcess>;
    users: string[];
    stopSignals: StopSignalOption[];
    phpVersions: PhpVersion[];
}

const props = defineProps<Props>();

const processes = computed(() => props.processes.data);

useResourceStatusUpdates({
    channelType: 'site',
    channelId: props.site.id,
    propNames: ['processes'],
});

const { confirmButton } = useConfirmation();

const showAddDialog = ref(false);
const showEditDialog = ref(false);
const editingProcess = ref<BackgroundProcess | null>(null);
const showAdvanced = ref(false);
const activeTab = ref<'queue' | 'custom'>('queue');

// Logs modal
const showLogsDialog = ref(false);
const logsProcess = ref<BackgroundProcess | null>(null);
const logsContent = ref<string>('');
const logsLoading = ref(false);

// Status modal
const showStatusDialog = ref(false);
const statusProcess = ref<BackgroundProcess | null>(null);
const statusContent = ref<string>('');
const statusLoading = ref(false);

// Get default PHP binary from site's configured PHP version
// site.phpVersion is already in format like "php84", phpVersions[].binary is like "php8.4"
const getDefaultPhpBinary = () => {
    if (props.site.phpVersion) {
        // Convert "php84" to "php8.4" format to match phpVersions[].binary
        const version = props.site.phpVersion.replace('php', '');
        const formattedVersion =
            version.length === 2 ? `${version[0]}.${version[1]}` : version;
        return `php${formattedVersion}`;
    }
    return props.phpVersions[0]?.binary || 'php';
};

// Queue Worker form
const queueForm = ref({
    phpVersion: getDefaultPhpBinary(),
    connection: 'redis',
    processes: 1,
    queue: '',
    backoff: 0,
    sleep: 3,
    rest: 0,
    timeout: 60,
    tries: 1,
    memory: 128,
    env: '',
    force: false,
});

function openAddDialog() {
    showAdvanced.value = false;
    activeTab.value = 'queue';
    // Reset queue form
    queueForm.value = {
        phpVersion: getDefaultPhpBinary(),
        connection: 'redis',
        processes: 1,
        queue: '',
        backoff: 0,
        sleep: 3,
        rest: 0,
        timeout: 60,
        tries: 1,
        memory: 128,
        env: '',
        force: false,
    };
    showAddDialog.value = true;
}

function openEditDialog(process: BackgroundProcess) {
    editingProcess.value = process;
    showAdvanced.value = false;
    showEditDialog.value = true;
}

async function openLogsDialog(process: BackgroundProcess) {
    logsProcess.value = process;
    logsContent.value = '';
    logsLoading.value = true;
    showLogsDialog.value = true;

    try {
        const response = await axios.get(
            viewLogs.url({ site: props.site, process: process.id }),
        );
        logsContent.value = response.data.content || '';
    } catch {
        logsContent.value = 'Failed to fetch logs.';
    } finally {
        logsLoading.value = false;
    }
}

async function openStatusDialog(process: BackgroundProcess) {
    statusProcess.value = process;
    statusContent.value = '';
    statusLoading.value = true;
    showStatusDialog.value = true;

    try {
        const response = await axios.get(
            viewStatus.url({ site: props.site, process: process.id }),
        );
        statusContent.value = response.data.status || '';
    } catch {
        statusContent.value = 'Failed to fetch status.';
    } finally {
        statusLoading.value = false;
    }
}

async function handleClearLogs() {
    const confirmed = await confirmButton({
        title: 'Clear Logs',
        description: `Are you sure you want to clear logs for "${logsProcess.value?.name}"?`,
        confirmText: 'Clear',
    });

    if (!confirmed || !logsProcess.value) {
        return;
    }

    router.delete(
        clearLogs.url({ site: props.site, process: logsProcess.value.id }),
        {
            preserveScroll: true,
            onSuccess: () => {
                logsContent.value = '';
            },
        },
    );
}

function onSuccess() {
    showAddDialog.value = false;
    showEditDialog.value = false;
    editingProcess.value = null;
}

async function deleteProcess(process: BackgroundProcess) {
    const confirmed = await confirmButton({
        title: 'Delete Background Process',
        description: `Are you sure you want to delete "${process.name}"? This will stop the process and remove it from Supervisor.`,
        confirmText: 'Delete',
    });

    if (!confirmed) {
        return;
    }

    router.delete(destroy.url({ site: props.site, process: process.id }));
}

function restartProcess(process: BackgroundProcess) {
    router.post(restart.url({ site: props.site, process: process.id }));
}

function startProcess(process: BackgroundProcess) {
    router.post(start.url({ site: props.site, process: process.id }));
}

function stopProcess(process: BackgroundProcess) {
    router.post(stop.url({ site: props.site, process: process.id }));
}

function getStatusBadge(process: BackgroundProcess): {
    label: string;
    variant: BadgeVariant;
} {
    if (process.status !== 'installed') {
        return {
            label: process.displayableStatus,
            variant: process.statusBadgeVariant,
        };
    }

    if (process.supervisorProcessStatus) {
        return {
            label: process.displayableSupervisorProcessStatus || 'Unknown',
            variant: process.supervisorBadgeVariant,
        };
    }

    return {
        label: process.displayableStatus,
        variant: process.statusBadgeVariant,
    };
}

function pluralize(count: number, singular: string, plural: string): string {
    return count === 1 ? `${count} ${singular}` : `${count} ${plural}`;
}

const queueCommandPreview = computed(() => {
    const parts = [queueForm.value.phpVersion, 'artisan queue:work'];
    if (queueForm.value.connection) {
        parts.push(queueForm.value.connection);
    }
    if (queueForm.value.queue) {
        parts.push(`--queue="${queueForm.value.queue}"`);
    }
    if (queueForm.value.backoff > 0) {
        parts.push(`--backoff=${queueForm.value.backoff}`);
    }
    parts.push(`--sleep=${queueForm.value.sleep}`);
    if (queueForm.value.rest > 0) {
        parts.push(`--rest=${queueForm.value.rest}`);
    }
    parts.push(`--timeout=${queueForm.value.timeout}`);
    parts.push(`--tries=${queueForm.value.tries}`);
    parts.push(`--memory=${queueForm.value.memory}`);
    if (queueForm.value.env) {
        parts.push(`--env=${queueForm.value.env}`);
    }
    if (queueForm.value.force) {
        parts.push('--force');
    }
    return parts.join(' ');
});
</script>

<template>
    <Head :title="`Background Processes - ${site.domain}`" />

    <SiteLayout :site="site">
        <div class="space-y-6">
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <Activity class="size-5" />
                                Background processes
                            </CardTitle>
                            <CardDescription>
                                Manage background processes for this site.
                            </CardDescription>
                        </div>
                        <div class="flex items-center gap-2">
                            <Button variant="outline" @click="openAddDialog">
                                <Plus class="mr-2 size-4" />
                                Add process
                            </Button>
                        </div>
                    </div>
                </CardHeader>

                <CardContent>
                    <div
                        v-if="processes.length === 0"
                        class="rounded-lg border border-dashed p-8 text-center"
                    >
                        <Activity
                            class="mx-auto mb-4 size-12 text-muted-foreground opacity-50"
                        />
                        <h3 class="text-lg font-medium">
                            No background processes yet
                        </h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Get started and create your first background process
                            for this site.
                        </p>
                        <Button
                            variant="outline"
                            class="mt-4"
                            @click="openAddDialog"
                        >
                            <Plus class="mr-2 size-4" />
                            Add background process
                        </Button>
                    </div>

                    <div v-else class="divide-y">
                        <div
                            v-for="process in processes"
                            :key="process.id"
                            class="flex items-center justify-between py-4 first:pt-0 last:pb-0"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <p
                                        class="truncate font-medium"
                                        :title="process.name"
                                    >
                                        {{ process.name }}
                                    </p>
                                </div>
                                <p
                                    class="mt-1 truncate font-mono text-xs text-muted-foreground"
                                    :title="process.command"
                                >
                                    {{ process.command }}
                                </p>
                                <p
                                    v-if="process.directory"
                                    class="mt-0.5 truncate font-mono text-xs text-muted-foreground"
                                    :title="process.directory"
                                >
                                    {{ process.directory }}
                                </p>
                            </div>
                            <div class="ml-4 flex items-center gap-4">
                                <span class="text-sm text-muted-foreground">
                                    {{
                                        pluralize(
                                            process.processes,
                                            'Process',
                                            'Processes',
                                        )
                                    }}
                                </span>
                                <Badge
                                    :variant="getStatusBadge(process).variant"
                                >
                                    {{ getStatusBadge(process).label }}
                                </Badge>
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button variant="ghost" size="icon">
                                            <MoreHorizontal class="size-4" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem
                                            @click="openLogsDialog(process)"
                                        >
                                            <FileText class="mr-2 size-4" />
                                            View logs
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            @click="openStatusDialog(process)"
                                        >
                                            <Activity class="mr-2 size-4" />
                                            View status
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem
                                            v-if="process.can.restart"
                                            @click="restartProcess(process)"
                                        >
                                            <RefreshCw class="mr-2 size-4" />
                                            Restart
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            v-if="process.can.start"
                                            @click="startProcess(process)"
                                        >
                                            <Play class="mr-2 size-4" />
                                            Start
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            v-if="process.can.stop"
                                            @click="stopProcess(process)"
                                        >
                                            <Pause class="mr-2 size-4" />
                                            Stop
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator
                                            v-if="
                                                process.can.restart ||
                                                process.can.start ||
                                                process.can.stop
                                            "
                                        />
                                        <DropdownMenuItem
                                            v-if="process.can.update"
                                            @click="openEditDialog(process)"
                                        >
                                            <Pencil class="mr-2 size-4" />
                                            Edit
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator
                                            v-if="process.can.delete"
                                        />
                                        <DropdownMenuItem
                                            v-if="process.can.delete"
                                            class="text-destructive focus:text-destructive"
                                            @click="deleteProcess(process)"
                                        >
                                            <Trash2 class="mr-2 size-4" />
                                            Delete
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Add Process Dialog with Tabs -->
        <Dialog v-model:open="showAddDialog">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>New background process</DialogTitle>
                    <DialogDescription>
                        Create a new background process for {{ site.domain }}.
                    </DialogDescription>
                </DialogHeader>

                <Tabs v-model="activeTab" class="w-full">
                    <TabsList class="grid w-full grid-cols-2">
                        <TabsTrigger value="queue">Queue Worker</TabsTrigger>
                        <TabsTrigger value="custom">Custom</TabsTrigger>
                    </TabsList>

                    <!-- Queue Worker Tab -->
                    <TabsContent value="queue" class="mt-4">
                        <Form
                            v-bind="store.form(site)"
                            class="space-y-3"
                            :on-success="onSuccess"
                            reset-on-success
                            v-slot="{ processing }"
                        >
                            <!-- Hidden command field with generated queue command -->
                            <input
                                type="hidden"
                                name="command"
                                :value="queueCommandPreview"
                            />

                            <!-- Forge-style horizontal rows -->
                            <div class="flex items-center gap-4">
                                <Label
                                    for="php-version"
                                    class="w-28 shrink-0 text-muted-foreground"
                                    >php</Label
                                >
                                <Select
                                    v-model="queueForm.phpVersion"
                                    name="php_version"
                                >
                                    <SelectTrigger class="flex-1">
                                        <SelectValue
                                            placeholder="Select PHP version"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="phpVer in phpVersions"
                                            :key="phpVer.version"
                                            :value="phpVer.binary"
                                        >
                                            PHP {{ phpVer.version }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div class="flex items-center gap-4">
                                <Label
                                    for="connection"
                                    class="w-28 shrink-0 text-muted-foreground"
                                    >connection</Label
                                >
                                <Input
                                    id="connection"
                                    v-model="queueForm.connection"
                                    placeholder=""
                                    class="flex-1"
                                />
                            </div>

                            <div class="flex items-center gap-4">
                                <Label
                                    for="processes-count"
                                    class="w-28 shrink-0 text-muted-foreground"
                                    >processes</Label
                                >
                                <Input
                                    id="processes-count"
                                    v-model.number="queueForm.processes"
                                    type="number"
                                    min="1"
                                    max="100"
                                    class="flex-1"
                                />
                            </div>

                            <div class="flex items-center gap-4">
                                <Label
                                    for="queue-name-field"
                                    class="w-28 shrink-0 text-muted-foreground"
                                    >--queue</Label
                                >
                                <Input
                                    id="queue-name-field"
                                    v-model="queueForm.queue"
                                    placeholder=""
                                    class="flex-1"
                                />
                            </div>

                            <div class="flex items-center gap-4">
                                <Label
                                    for="backoff"
                                    class="w-28 shrink-0 text-muted-foreground"
                                    >--backoff</Label
                                >
                                <div class="flex flex-1 items-center gap-2">
                                    <Input
                                        id="backoff"
                                        v-model.number="queueForm.backoff"
                                        type="number"
                                        min="0"
                                        class="flex-1"
                                    />
                                    <span class="text-sm text-muted-foreground"
                                        >seconds</span
                                    >
                                </div>
                            </div>

                            <div class="flex items-center gap-4">
                                <Label
                                    for="sleep"
                                    class="w-28 shrink-0 text-muted-foreground"
                                    >--sleep</Label
                                >
                                <div class="flex flex-1 items-center gap-2">
                                    <Input
                                        id="sleep"
                                        v-model.number="queueForm.sleep"
                                        type="number"
                                        min="0"
                                        class="flex-1"
                                    />
                                    <span class="text-sm text-muted-foreground"
                                        >seconds</span
                                    >
                                </div>
                            </div>

                            <div class="flex items-center gap-4">
                                <Label
                                    for="rest"
                                    class="w-28 shrink-0 text-muted-foreground"
                                    >--rest</Label
                                >
                                <div class="flex flex-1 items-center gap-2">
                                    <Input
                                        id="rest"
                                        v-model.number="queueForm.rest"
                                        type="number"
                                        min="0"
                                        class="flex-1"
                                    />
                                    <span class="text-sm text-muted-foreground"
                                        >seconds</span
                                    >
                                </div>
                            </div>

                            <div class="flex items-center gap-4">
                                <Label
                                    for="timeout"
                                    class="w-28 shrink-0 text-muted-foreground"
                                    >--timeout</Label
                                >
                                <div class="flex flex-1 items-center gap-2">
                                    <Input
                                        id="timeout"
                                        v-model.number="queueForm.timeout"
                                        type="number"
                                        min="0"
                                        class="flex-1"
                                    />
                                    <span class="text-sm text-muted-foreground"
                                        >seconds</span
                                    >
                                </div>
                            </div>

                            <div class="flex items-center gap-4">
                                <Label
                                    for="tries"
                                    class="w-28 shrink-0 text-muted-foreground"
                                    >--tries</Label
                                >
                                <div class="flex flex-1 items-center gap-2">
                                    <Input
                                        id="tries"
                                        v-model.number="queueForm.tries"
                                        type="number"
                                        min="1"
                                        class="flex-1"
                                    />
                                    <span class="text-sm text-muted-foreground"
                                        >tries</span
                                    >
                                </div>
                            </div>

                            <div class="flex items-center gap-4">
                                <Label
                                    for="memory"
                                    class="w-28 shrink-0 text-muted-foreground"
                                    >--memory</Label
                                >
                                <div class="flex flex-1 items-center gap-2">
                                    <Input
                                        id="memory"
                                        v-model.number="queueForm.memory"
                                        type="number"
                                        min="0"
                                        class="flex-1"
                                    />
                                    <span class="text-sm text-muted-foreground"
                                        >MB</span
                                    >
                                </div>
                            </div>

                            <div class="flex items-center gap-4">
                                <Label
                                    for="env"
                                    class="w-28 shrink-0 text-muted-foreground"
                                    >--env</Label
                                >
                                <Input
                                    id="env"
                                    v-model="queueForm.env"
                                    placeholder=""
                                    class="flex-1"
                                />
                            </div>

                            <div class="flex items-center gap-4">
                                <Label
                                    for="force"
                                    class="w-28 shrink-0 text-muted-foreground"
                                    >--force</Label
                                >
                                <Switch id="force" v-model="queueForm.force" />
                            </div>

                            <!-- Command Preview -->
                            <div class="mt-4 rounded-lg border bg-muted/30 p-3">
                                <code class="font-mono text-xs text-primary">{{
                                    queueCommandPreview
                                }}</code>
                            </div>

                            <!-- Working Directory -->
                            <div class="flex items-center gap-4">
                                <Label
                                    for="queue-directory"
                                    class="w-28 shrink-0 text-muted-foreground"
                                    >directory</Label
                                >
                                <Input
                                    id="queue-directory"
                                    name="directory"
                                    :default-value="site.applicationPath"
                                    class="flex-1 font-mono text-xs"
                                />
                            </div>

                            <!-- Hidden fields -->
                            <input
                                type="hidden"
                                name="name"
                                value="Queue Worker"
                            />
                            <input
                                type="hidden"
                                name="user"
                                :value="site.user"
                            />
                            <input
                                type="hidden"
                                name="processes"
                                :value="queueForm.processes"
                            />
                            <input type="hidden" name="startsecs" value="1" />
                            <input
                                type="hidden"
                                name="stopwaitsecs"
                                value="15"
                            />
                            <input
                                type="hidden"
                                name="stopsignal"
                                value="TERM"
                            />

                            <DialogFooter class="mt-4">
                                <Button
                                    type="button"
                                    variant="outline"
                                    @click="showAddDialog = false"
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" :disabled="processing">
                                    {{
                                        processing
                                            ? 'Creating...'
                                            : 'Create queue worker'
                                    }}
                                </Button>
                            </DialogFooter>
                        </Form>
                    </TabsContent>

                    <!-- Custom Tab -->
                    <TabsContent value="custom" class="mt-4 space-y-4">
                        <Form
                            v-bind="store.form(site)"
                            class="space-y-4"
                            :on-success="onSuccess"
                            reset-on-success
                            v-slot="{ errors, processing }"
                        >
                            <div class="space-y-2">
                                <Label for="name">Name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    placeholder="e.g., Queue Worker"
                                />
                                <p class="text-xs text-muted-foreground">
                                    Add a custom display name for the background
                                    process.
                                </p>
                                <InputError :message="errors.name" />
                            </div>

                            <div class="space-y-2">
                                <Label for="command">Command</Label>
                                <Input
                                    id="command"
                                    name="command"
                                    class="font-mono text-sm"
                                    placeholder="e.g., php artisan queue:work"
                                />
                                <p class="text-xs text-muted-foreground">
                                    The command that should run for this
                                    background process.
                                </p>
                                <InputError :message="errors.command" />
                            </div>

                            <div class="space-y-2">
                                <Label for="directory">Working Directory</Label>
                                <Input
                                    id="directory"
                                    name="directory"
                                    :default-value="site.applicationPath"
                                    class="font-mono text-sm"
                                />
                                <p class="text-xs text-muted-foreground">
                                    The directory where the background process
                                    should be started. Defaults to the site's
                                    application path.
                                </p>
                                <InputError :message="errors.directory" />
                            </div>

                            <div class="space-y-2">
                                <Label for="user">Unix user</Label>
                                <Select name="user" :default-value="site.user">
                                    <SelectTrigger>
                                        <SelectValue
                                            placeholder="Select a unix user"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="user in users"
                                            :key="user"
                                            :value="user"
                                        >
                                            {{ user }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="errors.user" />
                            </div>

                            <!-- Advanced Settings -->
                            <div class="rounded-lg border bg-muted/30 p-4">
                                <div
                                    class="flex w-full items-center justify-between text-sm font-medium"
                                >
                                    <span>Advanced settings</span>
                                    <button
                                        type="button"
                                        class="text-primary hover:underline"
                                        @click="showAdvanced = !showAdvanced"
                                    >
                                        {{ showAdvanced ? 'Hide' : 'Edit' }}
                                    </button>
                                </div>

                                <!-- Hidden defaults when advanced is collapsed -->
                                <template v-if="!showAdvanced">
                                    <input
                                        type="hidden"
                                        name="processes"
                                        value="1"
                                    />
                                    <input
                                        type="hidden"
                                        name="startsecs"
                                        value="1"
                                    />
                                    <input
                                        type="hidden"
                                        name="stopwaitsecs"
                                        value="15"
                                    />
                                    <input
                                        type="hidden"
                                        name="stopsignal"
                                        value="TERM"
                                    />
                                </template>

                                <div v-if="showAdvanced" class="mt-4 space-y-4">
                                    <div class="space-y-2">
                                        <Label for="processes">Processes</Label>
                                        <Input
                                            id="processes"
                                            name="processes"
                                            type="number"
                                            min="1"
                                            max="100"
                                            default-value="1"
                                        />
                                        <InputError
                                            :message="errors.processes"
                                        />
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="startsecs"
                                            >Start (seconds)</Label
                                        >
                                        <Input
                                            id="startsecs"
                                            name="startsecs"
                                            type="number"
                                            min="0"
                                            default-value="1"
                                        />
                                        <InputError
                                            :message="errors.startsecs"
                                        />
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="stopwaitsecs"
                                            >Stop wait (seconds)</Label
                                        >
                                        <Input
                                            id="stopwaitsecs"
                                            name="stopwaitsecs"
                                            type="number"
                                            min="0"
                                            default-value="15"
                                        />
                                        <InputError
                                            :message="errors.stopwaitsecs"
                                        />
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="stopsignal"
                                            >Stop signal</Label
                                        >
                                        <Select
                                            name="stopsignal"
                                            default-value="TERM"
                                        >
                                            <SelectTrigger>
                                                <SelectValue
                                                    placeholder="Select a signal"
                                                />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="signal in stopSignals"
                                                    :key="signal.value"
                                                    :value="signal.value"
                                                >
                                                    {{ signal.label }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            :message="errors.stopsignal"
                                        />
                                    </div>
                                </div>

                                <div
                                    v-if="!showAdvanced"
                                    class="mt-3 space-y-1 text-xs text-muted-foreground"
                                >
                                    <div class="flex justify-between">
                                        <span>Processes:</span>
                                        <span>1</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Start:</span>
                                        <span>1 second</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Stop:</span>
                                        <span>15 seconds</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Stop signal:</span>
                                        <span>SIGTERM</span>
                                    </div>
                                </div>
                            </div>

                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    @click="showAddDialog = false"
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" :disabled="processing">
                                    {{
                                        processing
                                            ? 'Creating...'
                                            : 'Create background process'
                                    }}
                                </Button>
                            </DialogFooter>
                        </Form>
                    </TabsContent>
                </Tabs>
            </DialogContent>
        </Dialog>

        <!-- Edit Process Dialog -->
        <Dialog v-model:open="showEditDialog">
            <DialogContent v-if="editingProcess" class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Edit background process</DialogTitle>
                    <DialogDescription>
                        Update the background process configuration.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    v-bind="
                        update.form({
                            site: site,
                            process: editingProcess.id,
                        })
                    "
                    class="space-y-4"
                    :on-success="onSuccess"
                    v-slot="{ errors, processing }"
                >
                    <div class="space-y-2">
                        <Label for="edit-name">Name</Label>
                        <Input
                            id="edit-name"
                            name="name"
                            :default-value="editingProcess.name"
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="space-y-2">
                        <Label for="edit-command">Command</Label>
                        <Input
                            id="edit-command"
                            name="command"
                            class="font-mono text-sm"
                            :default-value="editingProcess.command"
                        />
                        <InputError :message="errors.command" />
                    </div>

                    <div class="space-y-2">
                        <Label for="edit-directory">Working Directory</Label>
                        <Input
                            id="edit-directory"
                            name="directory"
                            :default-value="editingProcess.directory ?? ''"
                        />
                        <InputError :message="errors.directory" />
                    </div>

                    <div class="space-y-2">
                        <Label for="edit-user">Unix user</Label>
                        <Select
                            name="user"
                            :default-value="editingProcess.user"
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select a unix user" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="user in users"
                                    :key="user"
                                    :value="user"
                                >
                                    {{ user }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.user" />
                    </div>

                    <div class="space-y-2">
                        <Label for="edit-processes">Processes</Label>
                        <Input
                            id="edit-processes"
                            name="processes"
                            type="number"
                            min="1"
                            max="100"
                            :default-value="editingProcess.processes.toString()"
                        />
                        <InputError :message="errors.processes" />
                    </div>

                    <div class="space-y-2">
                        <Label for="edit-startsecs">Start (seconds)</Label>
                        <Input
                            id="edit-startsecs"
                            name="startsecs"
                            type="number"
                            min="0"
                            :default-value="editingProcess.startsecs.toString()"
                        />
                        <InputError :message="errors.startsecs" />
                    </div>

                    <div class="space-y-2">
                        <Label for="edit-stopwaitsecs"
                            >Stop wait (seconds)</Label
                        >
                        <Input
                            id="edit-stopwaitsecs"
                            name="stopwaitsecs"
                            type="number"
                            min="0"
                            :default-value="
                                editingProcess.stopwaitsecs.toString()
                            "
                        />
                        <InputError :message="errors.stopwaitsecs" />
                    </div>

                    <div class="space-y-2">
                        <Label for="edit-stopsignal">Stop signal</Label>
                        <Select
                            name="stopsignal"
                            :default-value="editingProcess.stopsignal"
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select a signal" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="signal in stopSignals"
                                    :key="signal.value"
                                    :value="signal.value"
                                >
                                    {{ signal.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.stopsignal" />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="showEditDialog = false"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="processing">
                            {{ processing ? 'Saving...' : 'Save changes' }}
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>

        <!-- View Logs Dialog -->
        <Dialog v-model:open="showLogsDialog">
            <DialogContent v-if="logsProcess" class="max-h-[80vh] max-w-4xl">
                <DialogHeader>
                    <DialogTitle>View {{ logsProcess.name }} logs</DialogTitle>
                    <DialogDescription>
                        Background process log output
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <div
                        class="max-h-96 overflow-y-auto rounded-lg border bg-muted/30 p-4"
                    >
                        <div
                            v-if="logsLoading"
                            class="text-center text-muted-foreground"
                        >
                            Loading logs...
                        </div>
                        <pre
                            v-else-if="logsContent"
                            class="font-mono text-xs break-words whitespace-pre-wrap"
                            >{{ logsContent }}</pre
                        >
                        <div v-else class="text-center text-muted-foreground">
                            No logs found.
                        </div>
                    </div>
                </div>

                <DialogFooter class="flex justify-between sm:justify-between">
                    <Button
                        variant="destructive"
                        @click="handleClearLogs"
                        :disabled="logsLoading || !logsContent"
                    >
                        Delete contents
                    </Button>
                    <div class="flex gap-2">
                        <Button
                            variant="outline"
                            @click="showLogsDialog = false"
                        >
                            Close
                        </Button>
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- View Status Dialog -->
        <Dialog v-model:open="showStatusDialog">
            <DialogContent v-if="statusProcess" class="max-h-[80vh] max-w-4xl">
                <DialogHeader>
                    <DialogTitle
                        >View {{ statusProcess.name }} status</DialogTitle
                    >
                    <DialogDescription>
                        Background process status information
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <div
                        class="max-h-96 overflow-y-auto rounded-lg border bg-muted/30 p-4"
                    >
                        <div
                            v-if="statusLoading"
                            class="text-center text-muted-foreground"
                        >
                            Loading status...
                        </div>
                        <pre
                            v-else-if="statusContent"
                            class="font-mono text-xs break-words whitespace-pre-wrap"
                            >{{ statusContent }}</pre
                        >
                        <div v-else class="text-center text-muted-foreground">
                            No status available.
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="showStatusDialog = false">
                        Close
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </SiteLayout>
</template>
