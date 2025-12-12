<script setup lang="ts">
import {
    destroy,
    index,
    store,
} from '@/actions/Nip/SshKey/Http/Controllers/UserSshKeyController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import EmptyState from '@/components/shared/EmptyState.vue';
import FormField from '@/components/shared/FormField.vue';
import ResourceFormDialog from '@/components/shared/ResourceFormDialog.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useResourceDelete } from '@/composables/useResourceDelete';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedResponse } from '@/types/pagination';
import { Head } from '@inertiajs/vue3';
import { Key, MoreHorizontal, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

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

const addKeyDialog = ref<InstanceType<typeof ResourceFormDialog>>();

const { deleteResource: deleteKey } = useResourceDelete<UserSshKey>({
    resourceName: 'SSH Key',
    getDisplayName: (key) => key.name,
    getDeleteUrl: (key) => destroy.url({ key: key.id }),
});
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
                        <Button variant="outline" @click="addKeyDialog?.open()">
                            <Plus class="mr-2 size-4" />
                            Add key
                        </Button>
                    </div>

                    <EmptyState
                        v-if="keys.data.length === 0"
                        :icon="Key"
                        title="No keys yet"
                        description="Get started and create your first SSH key."
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

            <ResourceFormDialog
                ref="addKeyDialog"
                title="Add SSH key"
                description="Add a new SSH key to your account. This key can be added to any server you have access to."
                submit-text="Add key"
                processing-text="Adding..."
                :form-action="store.form()"
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
                </template>
            </ResourceFormDialog>
        </SettingsLayout>
    </AppLayout>
</template>
