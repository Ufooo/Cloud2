<script setup lang="ts">
import {
    destroy,
    restart,
    start,
    stop,
    store,
    update,
} from '@/actions/Nip/BackgroundProcess/Http/Controllers/BackgroundProcessController';
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
import { useConfirmation } from '@/composables/useConfirmation';
import { useStatusPolling } from '@/composables/useStatusPolling';
import ServerLayout from '@/layouts/ServerLayout.vue';
import type { Server } from '@/types';
import type { PaginatedResponse } from '@/types/pagination';
import { Form, Head, router } from '@inertiajs/vue3';
import {
    Activity,
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
    siteId: string | null;
    siteDomain: string | null;
    siteSlug: string | null;
    can: {
        update: boolean;
        delete: boolean;
        restart: boolean;
        start: boolean;
        stop: boolean;
    };
}

interface Props {
    server: Server;
    processes: PaginatedResponse<BackgroundProcess>;
    users: string[];
    stopSignals: StopSignalOption[];
}

const props = defineProps<Props>();

const processes = computed(() => props.processes.data);

useStatusPolling({
    items: processes,
    getStatus: (process) => process.status,
    propName: 'processes',
    pendingStatuses: ['pending', 'installing', 'deleting'],
});

const { confirmButton } = useConfirmation();

const showAddDialog = ref(false);
const showEditDialog = ref(false);
const editingProcess = ref<BackgroundProcess | null>(null);
const showAdvanced = ref(false);

function openAddDialog() {
    showAdvanced.value = false;
    showAddDialog.value = true;
}

function openEditDialog(process: BackgroundProcess) {
    editingProcess.value = process;
    showAdvanced.value = false;
    showEditDialog.value = true;
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

    router.delete(
        destroy.url({ server: props.server, process: process.id }),
    );
}

function restartProcess(process: BackgroundProcess) {
    router.post(
        restart.url({ server: props.server, process: process.id }),
    );
}

function startProcess(process: BackgroundProcess) {
    router.post(
        start.url({ server: props.server, process: process.id }),
    );
}

function stopProcess(process: BackgroundProcess) {
    router.post(stop.url({ server: props.server, process: process.id }));
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
</script>

<template>
    <Head :title="`Background Processes - ${server.name}`" />

    <ServerLayout :server="server">
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
                                Background processes are managed using
                                Supervisor, which monitors your processes and
                                automatically restarts them if they crash or
                                stop unexpectedly.
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
                            Get started and create your first background
                            process.
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
                                    <Badge
                                        v-if="process.siteDomain"
                                        variant="secondary"
                                        class="shrink-0 text-xs"
                                    >
                                        {{ process.siteDomain }}
                                    </Badge>
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

        <!-- Add Process Dialog -->
        <Dialog v-model:open="showAddDialog">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>New background process</DialogTitle>
                    <DialogDescription>
                        Create a new background process that will be restarted
                        if it crashes or the server restarts.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    v-bind="store.form(server)"
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
                            The command that should run for this background
                            process.
                        </p>
                        <InputError :message="errors.command" />
                    </div>

                    <div class="space-y-2">
                        <Label for="directory">Working Directory</Label>
                        <Input
                            id="directory"
                            name="directory"
                            :default-value="`/home/${users[0] || 'netipar'}`"
                            placeholder="/home/netipar/app"
                        />
                        <p class="text-xs text-muted-foreground">
                            The directory where the background process should be
                            started.
                        </p>
                        <InputError :message="errors.directory" />
                    </div>

                    <div class="space-y-2">
                        <Label for="user">Unix user</Label>
                        <Select name="user" :default-value="users[0] || 'netipar'">
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
                            <input type="hidden" name="processes" value="1" />
                            <input type="hidden" name="startsecs" value="1" />
                            <input type="hidden" name="stopwaitsecs" value="15" />
                            <input type="hidden" name="stopsignal" value="TERM" />
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
                                <InputError :message="errors.processes" />
                            </div>

                            <div class="space-y-2">
                                <Label for="startsecs">Start (seconds)</Label>
                                <Input
                                    id="startsecs"
                                    name="startsecs"
                                    type="number"
                                    min="0"
                                    default-value="1"
                                />
                                <InputError :message="errors.startsecs" />
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
                                <InputError :message="errors.stopwaitsecs" />
                            </div>

                            <div class="space-y-2">
                                <Label for="stopsignal">Stop signal</Label>
                                <Select name="stopsignal" default-value="TERM">
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
                                <InputError :message="errors.stopsignal" />
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
                            server: server,
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
                            :default-value="
                                editingProcess.processes.toString()
                            "
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
                            :default-value="
                                editingProcess.startsecs.toString()
                            "
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
    </ServerLayout>
</template>
