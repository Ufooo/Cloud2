<script setup lang="ts">
import {
    destroy,
    store,
} from '@/actions/Nip/UnixUser/Http/Controllers/UnixUserController';
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
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useConfirmation } from '@/composables/useConfirmation';
import ServerLayout from '@/layouts/ServerLayout.vue';
import type { Server } from '@/types';
import type { PaginatedResponse } from '@/types/pagination';
import { UserStatus } from '@/types/generated';
import { Form, Head, router } from '@inertiajs/vue3';
import { MoreHorizontal, Plus, Trash2, Users } from 'lucide-vue-next';
import { ref } from 'vue';

interface UnixUser {
    id: number;
    username: string;
    status: UserStatus;
    displayableStatus: string;
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

const { confirmButton } = useConfirmation();

const showAddUserDialog = ref(false);

function openAddUserDialog() {
    showAddUserDialog.value = true;
}

function onSuccess() {
    showAddUserDialog.value = false;
}

async function deleteUser(user: UnixUser) {
    const confirmed = await confirmButton({
        title: 'Delete Unix User',
        description: `Are you sure you want to delete the "${user.username}" user?`,
        confirmText: 'Delete',
    });

    if (!confirmed) {
        return;
    }

    router.delete(destroy.url({ server: props.server, user: user.id }));
}

function getBadgeVariant(user: UnixUser) {
    if (user.status !== UserStatus.Installed) {
        return 'secondary';
    }
    return 'default';
}
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
                                @click="openAddUserDialog"
                            >
                                <Plus class="mr-2 size-4" />
                                Add user
                            </Button>
                        </div>
                    </div>
                </CardHeader>

                <CardContent>
                    <div
                        v-if="users.data.length === 0"
                        class="rounded-lg border border-dashed p-8 text-center"
                    >
                        <Users
                            class="mx-auto mb-4 size-12 text-muted-foreground opacity-50"
                        />
                        <h3 class="text-lg font-medium">No users yet</h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Get started and create your first user.
                        </p>
                        <Button
                            variant="outline"
                            class="mt-4"
                            @click="openAddUserDialog"
                        >
                            <Plus class="mr-2 size-4" />
                            Add user
                        </Button>
                    </div>

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
                                <Badge :variant="getBadgeVariant(user)">
                                    {{ user.displayableStatus }}
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
                                            :disabled="
                                                user.status ===
                                                UserStatus.Installing
                                            "
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

        <!-- Add User Dialog -->
        <Dialog v-model:open="showAddUserDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add Unix user</DialogTitle>
                    <DialogDescription>
                        Create a new system user on your server.
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
                        <Label for="username">Username</Label>
                        <Input
                            id="username"
                            name="username"
                            placeholder="e.g., deployer"
                        />
                        <p class="text-sm text-muted-foreground">
                            Lowercase letters, numbers, underscores, and hyphens
                            only.
                        </p>
                        <InputError :message="errors.username" />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="showAddUserDialog = false"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="processing">
                            {{ processing ? 'Creating...' : 'Create user' }}
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </ServerLayout>
</template>
