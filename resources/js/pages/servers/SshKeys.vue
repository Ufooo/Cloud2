<script setup lang="ts">
import {
    destroy,
    store,
} from '@/actions/Nip/SshKey/Http/Controllers/SshKeyController';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useResourceDelete } from '@/composables/useResourceDelete';
import { useStatusPolling } from '@/composables/useStatusPolling';
import ServerLayout from '@/layouts/ServerLayout.vue';
import type { Server } from '@/types';
import type { PaginatedResponse } from '@/types/pagination';
import { Head } from '@inertiajs/vue3';
import { Key, MoreHorizontal, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface UnixUser {
    id: number;
    username: string;
}

interface SshKeyStatus {
    value: 'pending' | 'installed' | 'failed' | 'deleting';
    label: string;
    variant: 'default' | 'secondary' | 'destructive';
}

interface SshKey {
    id: number;
    name: string;
    fingerprint: string;
    status: SshKeyStatus;
    createdAt: string | null;
    unixUser: {
        id: number;
        username: string;
    };
    can: {
        delete: boolean;
    };
}

interface Props {
    server: Server;
    keys: PaginatedResponse<SshKey>;
    unixUsers: UnixUser[];
}

const props = defineProps<Props>();

const addKeyDialog = ref<InstanceType<typeof ResourceFormDialog>>();

const keys = computed(() => props.keys.data);

useStatusPolling({
    items: keys,
    getStatus: (key) => key.status.value,
    propName: 'keys',
    pendingStatuses: ['pending', 'deleting'],
});

const { deleteResource: deleteKey } = useResourceDelete<SshKey>({
    resourceName: 'SSH Key',
    getDisplayName: (key) => key.name,
    getDeleteUrl: (key) => destroy.url({ server: props.server, sshKey: key.id }),
});
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
                            <Button
                                variant="outline"
                                @click="addKeyDialog?.open()"
                            >
                                <Plus class="mr-2 size-4" />
                                Add key
                            </Button>
                        </div>
                    </div>
                </CardHeader>

                <CardContent>
                    <EmptyState
                        v-if="keys.length === 0"
                        :icon="Key"
                        title="No keys yet"
                        description="Get started and add your first SSH key."
                        compact
                    >
                        <template #action>
                            <Button
                                variant="outline"
                                @click="addKeyDialog?.open()"
                            >
                                <Plus class="mr-2 size-4" />
                                Add key
                            </Button>
                        </template>
                    </EmptyState>

                    <div v-else class="divide-y">
                        <div
                            v-for="key in keys"
                            :key="key.id"
                            class="flex items-center justify-between py-4 first:pt-0 last:pb-0"
                        >
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <p class="font-medium">{{ key.name }}</p>
                                    <Badge variant="secondary">
                                        {{ key.unixUser.username }}
                                    </Badge>
                                    <Badge
                                        v-if="key.status.value !== 'installed'"
                                        :variant="key.status.variant"
                                    >
                                        {{ key.status.label }}
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
                                            v-if="key.can.delete && key.status.value !== 'deleting'"
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

        <ResourceFormDialog
            ref="addKeyDialog"
            title="Add SSH key"
            description="Add a new SSH key to your server. Select which Unix user this key should be authorized for."
            submit-text="Add key"
            processing-text="Adding..."
            :form-action="store.form(server)"
        >
            <template #default="{ errors }">
                <FormField label="Name" name="name" :error="errors.name">
                    <Input
                        id="name"
                        name="name"
                        placeholder="e.g., MacBook Pro"
                    />
                </FormField>

                <FormField
                    label="Public key"
                    name="public_key"
                    :error="errors.public_key"
                    description="Paste your SSH public key (usually found in ~/.ssh/id_rsa.pub or ~/.ssh/id_ed25519.pub)."
                >
                    <Textarea
                        id="public_key"
                        name="public_key"
                        placeholder="ssh-rsa AAAA... or ssh-ed25519 AAAA..."
                        rows="4"
                        class="font-mono text-sm"
                    />
                </FormField>

                <FormField
                    label="Unix user"
                    name="unix_user_id"
                    :error="errors.unix_user_id"
                    description="The Unix user this key will be authorized for."
                >
                    <Select name="unix_user_id" required>
                        <SelectTrigger id="unix_user_id">
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
                </FormField>
            </template>
        </ResourceFormDialog>
    </ServerLayout>
</template>
