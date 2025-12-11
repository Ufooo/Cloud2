<script setup lang="ts">
import {
    destroy,
    index,
    store,
} from '@/actions/Nip/SshKey/Http/Controllers/UserSshKeyController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
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
import { Textarea } from '@/components/ui/textarea';
import { useConfirmation } from '@/composables/useConfirmation';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedResponse } from '@/types/pagination';
import { Form, Head, router } from '@inertiajs/vue3';
import { Key, MoreHorizontal, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

const { confirmButton } = useConfirmation();

interface UserSshKey {
    id: number;
    name: string;
    fingerprint: string;
    createdAt: string | null;
}

interface Props {
    keys: PaginatedResponse<UserSshKey>;
}

defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'SSH Keys',
        href: index.url(),
    },
];

const showAddKeyDialog = ref(false);

function openAddKeyDialog() {
    showAddKeyDialog.value = true;
}

function onSuccess() {
    showAddKeyDialog.value = false;
}

async function deleteKey(key: UserSshKey) {
    const confirmed = await confirmButton({
        title: 'Delete SSH Key',
        description: `Are you sure you want to delete the "${key.name}" key?`,
        confirmText: 'Delete',
    });

    if (!confirmed) {
        return;
    }

    router.delete(destroy.url({ key: key.id }));
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="SSH Keys" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall
                    title="SSH keys"
                    description="Manage your account-level SSH keys. These keys can quickly be added to any server you have access to."
                />

                <div class="space-y-4">
                    <div class="flex justify-end">
                        <Button variant="outline" @click="openAddKeyDialog">
                            <Plus class="mr-2 size-4" />
                            Add key
                        </Button>
                    </div>

                    <div
                        v-if="keys.data.length === 0"
                        class="rounded-lg border border-dashed p-8 text-center"
                    >
                        <Key
                            class="mx-auto mb-4 size-12 text-muted-foreground opacity-50"
                        />
                        <h3 class="text-lg font-medium">No keys yet</h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Get started and create your first SSH key.
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

                    <div v-else class="divide-y rounded-lg border">
                        <div
                            v-for="key in keys.data"
                            :key="key.id"
                            class="flex items-center justify-between p-4"
                        >
                            <div>
                                <p class="font-medium">{{ key.name }}</p>
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
                </div>
            </div>

            <!-- Add Key Dialog -->
            <Dialog v-model:open="showAddKeyDialog">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Add SSH key</DialogTitle>
                        <DialogDescription>
                            Add a new SSH key to your account. This key can be
                            added to any server you have access to.
                        </DialogDescription>
                    </DialogHeader>

                    <Form
                        v-bind="store.form()"
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
        </SettingsLayout>
    </AppLayout>
</template>
