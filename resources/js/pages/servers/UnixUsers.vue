<script setup lang="ts">
import {
    destroy,
    store,
} from '@/actions/Nip/UnixUser/Http/Controllers/UnixUserController';
import EmptyState from '@/components/shared/EmptyState.vue';
import FormField from '@/components/shared/FormField.vue';
import ResourceFormDialog from '@/components/shared/ResourceFormDialog.vue';
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
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { useResourceDelete } from '@/composables/useResourceDelete';
import ServerLayout from '@/layouts/ServerLayout.vue';
import type { Server } from '@/types';
import type { PaginatedResponse } from '@/types/pagination';
import { Head, usePoll } from '@inertiajs/vue3';
import { MoreHorizontal, Plus, Trash2, Users } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface UnixUserStatus {
    value: 'pending' | 'installing' | 'installed' | 'deleting' | 'failed';
    label: string;
    variant: 'default' | 'secondary' | 'destructive';
}

interface UnixUser {
    id: number;
    username: string;
    status: UnixUserStatus;
    createdAt: string | null;
    can: {
        delete: boolean;
    };
}

interface Props {
    server: Server;
    users: PaginatedResponse<UnixUser>;
}

const props = defineProps<Props>();

const addUserDialog = ref<InstanceType<typeof ResourceFormDialog>>();

// Poll while there are pending, installing, or deleting users
const hasPendingUsers = computed(() =>
    props.users.data.some(
        (user) =>
            user.status.value === 'pending' ||
            user.status.value === 'installing' ||
            user.status.value === 'deleting',
    ),
);

const { start: startPolling, stop: stopPolling } = usePoll(
    3000,
    { only: ['users'] },
    { autoStart: false },
);

watch(
    hasPendingUsers,
    (pending) => {
        if (pending) {
            startPolling();
        } else {
            stopPolling();
        }
    },
    { immediate: true },
);

const { deleteResource: deleteUser } = useResourceDelete<UnixUser>({
    resourceName: 'Unix User',
    getDisplayName: (user) => user.username,
    getDeleteUrl: (user) => destroy.url({ server: props.server, user: user.id }),
});
</script>

<template>
    <Head :title="`Unix Users - ${server.name}`" />

    <ServerLayout :server="server">
        <div class="space-y-6">
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <Users class="size-5" />
                                Unix users
                            </CardTitle>
                            <CardDescription>
                                Manage system users on your server. Each user
                                can have SSH keys associated with them.
                            </CardDescription>
                        </div>
                        <div class="flex items-center gap-2">
                            <Button
                                variant="outline"
                                @click="addUserDialog?.open()"
                            >
                                <Plus class="mr-2 size-4" />
                                Add user
                            </Button>
                        </div>
                    </div>
                </CardHeader>

                <CardContent>
                    <EmptyState
                        v-if="users.data.length === 0"
                        :icon="Users"
                        title="No users yet"
                        description="Get started and create your first user."
                        compact
                    >
                        <template #action>
                            <Button
                                variant="outline"
                                @click="addUserDialog?.open()"
                            >
                                <Plus class="mr-2 size-4" />
                                Add user
                            </Button>
                        </template>
                    </EmptyState>

                    <div v-else class="divide-y">
                        <div
                            v-for="user in users.data"
                            :key="user.id"
                            class="flex items-center justify-between py-4 first:pt-0 last:pb-0"
                        >
                            <div>
                                <p class="font-medium">{{ user.username }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <Badge
                                    v-if="user.status.value !== 'installed'"
                                    :variant="user.status.variant"
                                >
                                    {{ user.status.label }}
                                </Badge>
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button variant="ghost" size="icon">
                                            <MoreHorizontal class="size-4" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem
                                            v-if="user.can.delete"
                                            class="text-destructive focus:text-destructive"
                                            @click="deleteUser(user)"
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

        <ResourceFormDialog
            ref="addUserDialog"
            title="Add Unix user"
            description="Create a new system user on your server."
            submit-text="Create user"
            processing-text="Creating..."
            :form-action="store.form(server)"
        >
            <template #default="{ errors }">
                <FormField
                    label="Username"
                    name="username"
                    :error="errors.username"
                    description="Lowercase letters, numbers, underscores, and hyphens only."
                >
                    <Input
                        id="username"
                        name="username"
                        placeholder="e.g., deployer"
                    />
                </FormField>
            </template>
        </ResourceFormDialog>
    </ServerLayout>
</template>
