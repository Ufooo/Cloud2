<script setup lang="ts">
import {
    destroy,
    store,
} from '@/actions/Nip/SshKey/Http/Controllers/SshKeyController';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useConfirmation } from '@/composables/useConfirmation';
import ServerLayout from '@/layouts/ServerLayout.vue';
import type { Server } from '@/types';
import { Form, Head, router } from '@inertiajs/vue3';
import { Key, MoreHorizontal, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

interface UnixUser {
    id: number;
    username: string;
}

interface SshKey {
    id: number;
    name: string;
    fingerprint: string;
    createdAt: string | null;
    unixUser: {
        id: number;
        username: string;
    };
    can: {
        delete: boolean;
    };
}

interface PaginatedKeys {
    data: SshKey[];
    links: { url: string | null; label: string; active: boolean }[];
    meta?: {
        current_page: number;
        last_page: number;
        total: number;
    };
}

interface Props {
    server: Server;
    keys: PaginatedKeys;
    unixUsers: UnixUser[];
}

const props = defineProps<Props>();

const { confirmButton } = useConfirmation();

const showAddKeyDialog = ref(false);

function openAddKeyDialog() {
    showAddKeyDialog.value = true;
}

function onSuccess() {
    showAddKeyDialog.value = false;
}

async function deleteKey(key: SshKey) {
    const confirmed = await confirmButton({
        title: 'Delete SSH Key',
        description: `Are you sure you want to delete the "${key.name}" key?`,
        confirmText: 'Delete',
    });

    if (!confirmed) {
        return;
    }

    router.delete(destroy.url({ server: props.server, sshKey: key.id }));
}
</script>

<template>
    <Head :title="`SSH Keys - ${server.name}`" />

    <ServerLayout :server="server">
        <div class="space-y-6">
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <Key class="size-5" />
                                SSH keys
                            </CardTitle>
                            <CardDescription>
                                Manage SSH keys for secure access to your
                                server. Keys can be assigned to specific Unix
                                users.
                            </CardDescription>
                        </div>
                        <div class="flex items-center gap-2">
                            <Button variant="outline" @click="openAddKeyDialog">
                                <Plus class="mr-2 size-4" />
                                Add key
                            </Button>
                        </div>
                    </div>
                </CardHeader>

                <CardContent>
                    <div
                        v-if="keys.data.length === 0"
                        class="rounded-lg border border-dashed p-8 text-center"
                    >
                        <Key
                            class="mx-auto mb-4 size-12 text-muted-foreground opacity-50"
                        />
                        <h3 class="text-lg font-medium">No keys yet</h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Get started and add your first SSH key.
                        </p>
                        <Button
                            variant="outline"
                            class="mt-4"
                            @click="openAddKeyDialog"
                        >
                            <Plus class="mr-2 size-4" />
                            Add key
                        </Button>
                    </div>

                    <div v-else class="divide-y">
                        <div
                            v-for="key in keys.data"
                            :key="key.id"
                            class="flex items-center justify-between py-4 first:pt-0 last:pb-0"
                        >
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <p class="font-medium">{{ key.name }}</p>
                                    <Badge variant="secondary">
                                        {{ key.unixUser.username }}
                                    </Badge>
                                </div>
                                <p
                                    class="font-mono text-sm text-muted-foreground"
                                >
                                    {{ key.fingerprint }}
                                </p>
                                <p
                                    v-if="key.createdAt"
                                    class="text-xs text-muted-foreground"
                                >
                                    Added on {{ key.createdAt }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button variant="ghost" size="icon">
                                            <MoreHorizontal class="size-4" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem
                                            v-if="key.can.delete"
                                            class="text-destructive focus:text-destructive"
                                            @click="deleteKey(key)"
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

        <!-- Add Key Dialog -->
        <Dialog v-model:open="showAddKeyDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add SSH key</DialogTitle>
                    <DialogDescription>
                        Add a new SSH key to your server. Select which Unix
                        user this key should be authorized for.
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
                        <Label for="key-name">Name</Label>
                        <Input
                            id="key-name"
                            name="name"
                            placeholder="e.g., MacBook Pro"
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="space-y-2">
                        <Label for="public-key">Public key</Label>
                        <Textarea
                            id="public-key"
                            name="public_key"
                            placeholder="ssh-rsa AAAA... or ssh-ed25519 AAAA..."
                            rows="4"
                            class="font-mono text-sm"
                        />
                        <p class="text-sm text-muted-foreground">
                            Paste your SSH public key (usually found in
                            ~/.ssh/id_rsa.pub or ~/.ssh/id_ed25519.pub).
                        </p>
                        <InputError :message="errors.public_key" />
                    </div>

                    <div class="space-y-2">
                        <Label for="unix-user">Unix user</Label>
                        <Select name="unix_user_id" required>
                            <SelectTrigger id="unix-user">
                                <SelectValue placeholder="Select a user" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="user in unixUsers"
                                    :key="user.id"
                                    :value="user.id.toString()"
                                >
                                    {{ user.username }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p class="text-sm text-muted-foreground">
                            The Unix user this key will be authorized for.
                        </p>
                        <InputError :message="errors.unix_user_id" />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="showAddKeyDialog = false"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="processing">
                            {{ processing ? 'Adding...' : 'Add key' }}
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </ServerLayout>
</template>
