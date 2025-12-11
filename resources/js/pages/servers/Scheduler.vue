<script setup lang="ts">
import {
    destroy,
    pause,
    resume,
    store,
    update,
} from '@/actions/Nip/Scheduler/Http/Controllers/ScheduledJobController';
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
import type { PaginatedResponse } from '@/types/pagination';
import { Form, Head, router } from '@inertiajs/vue3';
import {
    Clock,
    ExternalLink,
    MoreHorizontal,
    Pause,
    Pencil,
    Play,
    Plus,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

type BadgeVariant =
    | 'default'
    | 'secondary'
    | 'destructive'
    | 'outline'
    | null
    | undefined;

interface FrequencyOption {
    value: string;
    label: string;
}

interface GracePeriodOption {
    value: string;
    label: string;
}

interface ScheduledJob {
    id: string;
    name: string;
    command: string;
    user: string;
    frequency: string;
    displayableFrequency: string;
    cron: string | null;
    effectiveCron: string;
    heartbeatEnabled: boolean;
    heartbeatUrl: string | null;
    gracePeriod: number | null;
    displayableGracePeriod: string | null;
    status: string;
    displayableStatus: string;
    statusBadgeVariant: BadgeVariant;
    isCustomFrequency: boolean;
    createdAt: string | null;
    can: {
        update: boolean;
        delete: boolean;
        pause: boolean;
        resume: boolean;
    };
}

interface Props {
    server: Server;
    jobs: PaginatedResponse<ScheduledJob>;
    users: string[];
    frequencies: FrequencyOption[];
    gracePeriods: GracePeriodOption[];
}

const props = defineProps<Props>();

const { confirmButton } = useConfirmation();

const showAddDialog = ref(false);
const showEditDialog = ref(false);
const editingJob = ref<ScheduledJob | null>(null);

const addFormFrequency = ref('weekly');
const addFormHeartbeat = ref(false);
const editFormFrequency = ref('weekly');
const editFormHeartbeat = ref(false);

const isCustomFrequency = computed(() => addFormFrequency.value === 'custom');
const isEditCustomFrequency = computed(
    () => editFormFrequency.value === 'custom',
);

function openAddDialog() {
    addFormFrequency.value = 'weekly';
    addFormHeartbeat.value = false;
    showAddDialog.value = true;
}

function openEditDialog(job: ScheduledJob) {
    editingJob.value = job;
    editFormFrequency.value = job.frequency;
    editFormHeartbeat.value = job.heartbeatEnabled;
    showEditDialog.value = true;
}

function onSuccess() {
    showAddDialog.value = false;
    showEditDialog.value = false;
    editingJob.value = null;
}

async function deleteJob(job: ScheduledJob) {
    const confirmed = await confirmButton({
        title: 'Delete Scheduled Job',
        description: `Are you sure you want to delete "${job.name}"? This will remove the cron entry from the server.`,
        confirmText: 'Delete',
    });

    if (!confirmed) {
        return;
    }

    router.delete(destroy.url({ server: props.server, job: job.id }));
}

function pauseJob(job: ScheduledJob) {
    router.post(pause.url({ server: props.server, job: job.id }));
}

function resumeJob(job: ScheduledJob) {
    router.post(resume.url({ server: props.server, job: job.id }));
}

watch(
    () => editingJob.value,
    (job) => {
        if (job) {
            editFormFrequency.value = job.frequency;
            editFormHeartbeat.value = job.heartbeatEnabled;
        }
    },
);
</script>

<template>
    <Head :title="`Scheduler - ${server.name}`" />

    <ServerLayout :server="server">
        <div class="space-y-6">
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <Clock class="size-5" />
                                Scheduled jobs
                            </CardTitle>
                            <CardDescription>
                                Forge allows you to schedule any recurring tasks
                                that need to run on your server.
                            </CardDescription>
                        </div>
                        <div class="flex items-center gap-2">
                            <Button variant="outline" @click="openAddDialog">
                                <Plus class="mr-2 size-4" />
                                Add scheduled job
                            </Button>
                        </div>
                    </div>
                </CardHeader>

                <CardContent>
                    <div
                        v-if="jobs.data.length === 0"
                        class="rounded-lg border border-dashed p-8 text-center"
                    >
                        <Clock
                            class="mx-auto mb-4 size-12 text-muted-foreground opacity-50"
                        />
                        <h3 class="text-lg font-medium">
                            No scheduled jobs yet
                        </h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Get started and create your first scheduled job.
                        </p>
                        <Button
                            variant="outline"
                            class="mt-4"
                            @click="openAddDialog"
                        >
                            <Plus class="mr-2 size-4" />
                            Add scheduled job
                        </Button>
                    </div>

                    <div v-else class="divide-y">
                        <div
                            v-for="job in jobs.data"
                            :key="job.id"
                            class="flex items-center justify-between py-4 first:pt-0 last:pb-0"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <p
                                        class="truncate font-medium"
                                        :title="job.name"
                                    >
                                        {{ job.name }}
                                    </p>
                                </div>
                                <p
                                    class="mt-1 truncate font-mono text-xs text-muted-foreground"
                                    :title="`${job.user} · ${job.command}`"
                                >
                                    <span class="text-foreground/70">{{
                                        job.user
                                    }}</span>
                                    · {{ job.command }}
                                </p>
                            </div>
                            <div class="ml-4 flex items-center gap-4">
                                <Badge variant="outline">
                                    {{ job.displayableFrequency }}
                                </Badge>
                                <Badge :variant="job.statusBadgeVariant">
                                    {{ job.displayableStatus }}
                                </Badge>
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button variant="ghost" size="icon">
                                            <MoreHorizontal class="size-4" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem
                                            v-if="job.can.pause"
                                            @click="pauseJob(job)"
                                        >
                                            <Pause class="mr-2 size-4" />
                                            Pause
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            v-if="job.can.resume"
                                            @click="resumeJob(job)"
                                        >
                                            <Play class="mr-2 size-4" />
                                            Resume
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator
                                            v-if="job.can.pause || job.can.resume"
                                        />
                                        <DropdownMenuItem
                                            v-if="job.can.update"
                                            @click="openEditDialog(job)"
                                        >
                                            <Pencil class="mr-2 size-4" />
                                            Edit
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator
                                            v-if="job.can.delete"
                                        />
                                        <DropdownMenuItem
                                            v-if="job.can.delete"
                                            class="text-destructive focus:text-destructive"
                                            @click="deleteJob(job)"
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

        <!-- Add Job Dialog -->
        <Dialog v-model:open="showAddDialog">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>New scheduled job</DialogTitle>
                    <DialogDescription>
                        Create a new scheduled job for the
                        {{ server.name }} server.
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
                            placeholder="My scheduled job"
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="space-y-2">
                        <Label for="command">Command</Label>
                        <p class="text-xs text-muted-foreground">
                            Commands should use fully qualified paths.
                        </p>
                        <Input
                            id="command"
                            name="command"
                            class="font-mono text-sm"
                            placeholder="/usr/local/bin/composer self-update"
                        />
                        <InputError :message="errors.command" />
                    </div>

                    <div class="space-y-2">
                        <Label for="user">User</Label>
                        <Select
                            name="user"
                            :default-value="users[0] || 'netipar'"
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select a user" />
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
                        <Label for="frequency">Frequency</Label>
                        <Select
                            name="frequency"
                            default-value="weekly"
                            v-model="addFormFrequency"
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select frequency" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="freq in frequencies"
                                    :key="freq.value"
                                    :value="freq.value"
                                >
                                    {{ freq.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.frequency" />
                    </div>

                    <div v-if="isCustomFrequency" class="space-y-2">
                        <Label for="cron">Custom schedule</Label>
                        <p class="text-xs text-muted-foreground">
                            Need help formatting your own cron expression?
                            Checkout
                            <a
                                href="https://crontab.guru"
                                target="_blank"
                                class="text-primary hover:underline"
                            >
                                crontab.guru
                                <ExternalLink
                                    class="ml-0.5 inline size-3"
                                />
                            </a>
                        </p>
                        <Input
                            id="cron"
                            name="cron"
                            class="font-mono text-sm"
                            placeholder="0 0 * * *"
                            default-value="0 0 * * *"
                        />
                        <InputError :message="errors.cron" />
                    </div>

                    <div class="space-y-4 rounded-lg border bg-muted/30 p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <Label for="heartbeat_enabled"
                                    >Monitor with heartbeats</Label
                                >
                                <p class="text-xs text-muted-foreground">
                                    Generate a URL to ping after the job has
                                    run.
                                </p>
                            </div>
                            <Switch
                                id="heartbeat_enabled"
                                name="heartbeat_enabled"
                                v-model:checked="addFormHeartbeat"
                            />
                        </div>

                        <div v-if="addFormHeartbeat" class="space-y-2">
                            <Label for="grace_period">Notify me after</Label>
                            <p class="text-xs text-muted-foreground">
                                The amount of time to wait after a scheduled
                                job's expected run time before sending a
                                notification.
                            </p>
                            <Select name="grace_period" default-value="5">
                                <SelectTrigger>
                                    <SelectValue
                                        placeholder="Select grace period"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="period in gracePeriods"
                                        :key="period.value"
                                        :value="period.value"
                                    >
                                        {{ period.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="errors.grace_period" />
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
                                    : 'Create scheduled job'
                            }}
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>

        <!-- Edit Job Dialog -->
        <Dialog v-model:open="showEditDialog">
            <DialogContent v-if="editingJob" class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Edit scheduled job</DialogTitle>
                    <DialogDescription>
                        Update the scheduled job configuration.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    v-bind="
                        update.form({
                            server: server,
                            job: editingJob.id,
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
                            :default-value="editingJob.name"
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="space-y-2">
                        <Label for="edit-command">Command</Label>
                        <p class="text-xs text-muted-foreground">
                            Commands should use fully qualified paths.
                        </p>
                        <Input
                            id="edit-command"
                            name="command"
                            class="font-mono text-sm"
                            :default-value="editingJob.command"
                        />
                        <InputError :message="errors.command" />
                    </div>

                    <div class="space-y-2">
                        <Label for="edit-user">User</Label>
                        <Select name="user" :default-value="editingJob.user">
                            <SelectTrigger>
                                <SelectValue placeholder="Select a user" />
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
                        <Label for="edit-frequency">Frequency</Label>
                        <Select
                            name="frequency"
                            :default-value="editingJob.frequency"
                            v-model="editFormFrequency"
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select frequency" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="freq in frequencies"
                                    :key="freq.value"
                                    :value="freq.value"
                                >
                                    {{ freq.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.frequency" />
                    </div>

                    <div v-if="isEditCustomFrequency" class="space-y-2">
                        <Label for="edit-cron">Custom schedule</Label>
                        <p class="text-xs text-muted-foreground">
                            Need help formatting your own cron expression?
                            Checkout
                            <a
                                href="https://crontab.guru"
                                target="_blank"
                                class="text-primary hover:underline"
                            >
                                crontab.guru
                                <ExternalLink
                                    class="ml-0.5 inline size-3"
                                />
                            </a>
                        </p>
                        <Input
                            id="edit-cron"
                            name="cron"
                            class="font-mono text-sm"
                            :default-value="editingJob.cron || '0 0 * * *'"
                        />
                        <InputError :message="errors.cron" />
                    </div>

                    <div class="space-y-4 rounded-lg border bg-muted/30 p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <Label for="edit-heartbeat_enabled"
                                    >Monitor with heartbeats</Label
                                >
                                <p class="text-xs text-muted-foreground">
                                    Generate a URL to ping after the job has
                                    run.
                                </p>
                            </div>
                            <Switch
                                id="edit-heartbeat_enabled"
                                name="heartbeat_enabled"
                                v-model:checked="editFormHeartbeat"
                            />
                        </div>

                        <div v-if="editFormHeartbeat" class="space-y-2">
                            <Label for="edit-grace_period"
                                >Notify me after</Label
                            >
                            <p class="text-xs text-muted-foreground">
                                The amount of time to wait after a scheduled
                                job's expected run time before sending a
                                notification.
                            </p>
                            <Select
                                name="grace_period"
                                :default-value="
                                    editingJob.gracePeriod?.toString() || '5'
                                "
                            >
                                <SelectTrigger>
                                    <SelectValue
                                        placeholder="Select grace period"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="period in gracePeriods"
                                        :key="period.value"
                                        :value="period.value"
                                    >
                                        {{ period.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="errors.grace_period" />
                        </div>

                        <div
                            v-if="editingJob.heartbeatUrl"
                            class="rounded border bg-background p-2"
                        >
                            <Label class="text-xs">Heartbeat URL</Label>
                            <p
                                class="mt-1 break-all font-mono text-xs text-muted-foreground"
                            >
                                {{ editingJob.heartbeatUrl }}
                            </p>
                        </div>
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
