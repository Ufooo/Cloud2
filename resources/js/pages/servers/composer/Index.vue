<script setup lang="ts">
import {
    destroy,
    store,
    update,
} from '@/actions/Nip/Composer/Http/Controllers/ServerComposerController';
import AddCredentialModal from '@/components/composer/AddCredentialModal.vue';
import EditCredentialModal from '@/components/composer/EditCredentialModal.vue';
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
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useConfirmation } from '@/composables/useConfirmation';
import { useResourceStatusUpdates } from '@/composables/useResourceStatusUpdates';
import ServerLayout from '@/layouts/ServerLayout.vue';
import type { Server } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Key, MoreHorizontal, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Credential {
    id: number;
    user: string;
    repository: string;
    username: string;
    password: string;
    status: string | null;
    displayableStatus: string | null;
    statusBadgeVariant: string | null;
    createdAt: string | null;
}

interface Props {
    server: Server;
    credentials: { data: Credential[] };
    users: string[];
}

const props = defineProps<Props>();

useResourceStatusUpdates({
    channelType: 'server',
    channelId: props.server.id,
    propNames: ['credentials'],
});

const { confirmButton } = useConfirmation();

const credentials = computed(() => props.credentials?.data ?? []);

const showAddDialog = ref(false);
const showEditDialog = ref(false);
const editingCredential = ref<Credential | null>(null);

const editUpdateUrl = computed(() => {
    if (!editingCredential.value) return '';
    return update.url({
        server: props.server,
        credential: editingCredential.value.id,
    });
});

function openEditDialog(credential: Credential) {
    editingCredential.value = credential;
    showEditDialog.value = true;
}

async function handleDelete(credential: Credential) {
    const confirmed = await confirmButton({
        title: 'Delete Credential',
        description: `Are you sure you want to delete the credential for ${credential.repository}? This action cannot be undone.`,
        confirmText: 'Delete',
    });

    if (!confirmed) return;

    router.delete(
        destroy.url({ server: props.server, credential: credential.id }),
        {
            preserveScroll: true,
        },
    );
}
</script>

<template>
    <Head :title="`Composer - ${server.name}`" />

    <ServerLayout :server="server">
        <Card>
            <CardHeader>
                <div class="flex items-center justify-between">
                    <div>
                        <CardTitle class="flex items-center gap-2">
                            <Key class="size-5" />
                            Repository Credentials
                        </CardTitle>
                        <CardDescription>
                            Manage authentication credentials for private
                            Composer repositories at the server level.
                        </CardDescription>
                    </div>
                    <Button variant="outline" @click="showAddDialog = true">
                        <Plus class="mr-2 size-4" />
                        Add Repository
                    </Button>
                </div>
            </CardHeader>

            <CardContent>
                <div
                    v-if="credentials.length === 0"
                    class="rounded-lg border border-dashed p-8 text-center"
                >
                    <Key
                        class="mx-auto mb-4 size-12 text-muted-foreground opacity-50"
                    />
                    <h3 class="text-lg font-medium">No repositories</h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Add credentials to authenticate with private Composer
                        repositories.
                    </p>
                    <Button
                        variant="outline"
                        class="mt-4"
                        @click="showAddDialog = true"
                    >
                        <Plus class="mr-2 size-4" />
                        Add Repository
                    </Button>
                </div>

                <div v-else class="divide-y">
                    <div
                        v-for="credential in credentials"
                        :key="credential.id"
                        class="flex items-center justify-between py-4 first:pt-0 last:pb-0"
                    >
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <p class="font-medium">
                                    {{ credential.repository }}
                                </p>
                                <Badge
                                    v-if="
                                        credential.status &&
                                        credential.status !== 'synced'
                                    "
                                    :variant="
                                        credential.statusBadgeVariant as any
                                    "
                                >
                                    {{ credential.displayableStatus }}
                                </Badge>
                            </div>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ credential.username }} ({{
                                    credential.user
                                }})
                            </p>
                            <p
                                v-if="credential.createdAt"
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                Added {{ credential.createdAt }}
                            </p>
                        </div>

                        <div class="ml-4 flex items-center gap-4">
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        :disabled="
                                            credential.status === 'deleting' ||
                                            credential.status === 'syncing'
                                        "
                                    >
                                        <MoreHorizontal class="size-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem
                                        @click="openEditDialog(credential)"
                                    >
                                        <Pencil class="mr-2 size-4" />
                                        Edit
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        class="text-destructive focus:text-destructive"
                                        @click="handleDelete(credential)"
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

        <AddCredentialModal
            :store-url="store.url(server)"
            :users="users"
            v-model:open="showAddDialog"
        />

        <EditCredentialModal
            :update-url="editUpdateUrl"
            :users="users"
            :credential="editingCredential"
            v-model:open="showEditDialog"
        />
    </ServerLayout>
</template>
