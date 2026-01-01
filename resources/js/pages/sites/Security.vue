<script setup lang="ts">
import {
    destroy,
    store,
    update,
} from '@/actions/Nip/Security/Http/Controllers/SiteSecurityRuleController';
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
import { useConfirmation } from '@/composables/useConfirmation';
import SiteLayout from '@/layouts/SiteLayout.vue';
import type { Site } from '@/types';
import type { PaginatedResponse } from '@/types/pagination';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Lock, MoreHorizontal, Pencil, Plus, Trash2, X } from 'lucide-vue-next';
import { ref } from 'vue';

type BadgeVariant =
    | 'default'
    | 'secondary'
    | 'destructive'
    | 'outline'
    | null
    | undefined;

interface Credential {
    id: string;
    username: string;
}

interface SecurityRule {
    id: string;
    name: string;
    path: string | null;
    status: string;
    displayableStatus: string;
    statusBadgeVariant: BadgeVariant;
    credentials: Credential[];
    createdAt: string | null;
    can: {
        update: boolean;
        delete: boolean;
    };
}

interface Props {
    site: Site;
    rules: PaginatedResponse<SecurityRule>;
}

const props = defineProps<Props>();

const { confirmButton } = useConfirmation();

const showAddDialog = ref(false);
const showEditDialog = ref(false);
const editingRule = ref<SecurityRule | null>(null);

interface CredentialInput {
    username: string;
    password: string;
}

const addForm = useForm({
    name: '',
    path: '',
    credentials: [{ username: '', password: '' }] as CredentialInput[],
});

const editForm = useForm({
    name: '',
    path: '',
    credentials: [{ username: '', password: '' }] as CredentialInput[],
});

function openAddDialog() {
    addForm.reset();
    addForm.credentials = [{ username: '', password: '' }];
    showAddDialog.value = true;
}

function openEditDialog(rule: SecurityRule) {
    editingRule.value = rule;
    editForm.name = rule.name;
    editForm.path = rule.path || '';
    editForm.credentials = rule.credentials.map((c) => ({
        username: c.username,
        password: '',
    }));
    if (editForm.credentials.length === 0) {
        editForm.credentials = [{ username: '', password: '' }];
    }
    showEditDialog.value = true;
}

function addCredential(form: typeof addForm | typeof editForm) {
    form.credentials.push({ username: '', password: '' });
}

function removeCredential(
    form: typeof addForm | typeof editForm,
    index: number,
) {
    if (form.credentials.length > 1) {
        form.credentials.splice(index, 1);
    }
}

function submitAdd() {
    addForm.post(store.url(props.site), {
        onSuccess: () => {
            showAddDialog.value = false;
            addForm.reset();
        },
    });
}

function submitEdit() {
    if (!editingRule.value) return;

    editForm.patch(
        update.url({ site: props.site, rule: editingRule.value.id }),
        {
            onSuccess: () => {
                showEditDialog.value = false;
                editingRule.value = null;
            },
        },
    );
}

async function deleteRule(rule: SecurityRule) {
    const confirmed = await confirmButton({
        title: 'Delete Security Rule',
        description: `Are you sure you want to delete "${rule.name}"? This will remove HTTP authentication from the protected path.`,
        confirmText: 'Delete',
    });

    if (!confirmed) {
        return;
    }

    router.delete(destroy.url({ site: props.site, rule: rule.id }));
}

function formatCredentials(credentials: Credential[]): string {
    return credentials.map((c) => c.username).join(', ');
}
</script>

<template>
    <Head :title="`Security - ${site.domain}`" />

    <SiteLayout :site="site">
        <div class="space-y-6">
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <Lock class="size-5" />
                                Security rules
                            </CardTitle>
                            <CardDescription>
                                Configure HTTP basic authentication to protect
                                specific paths on your site.
                            </CardDescription>
                        </div>
                        <div class="flex items-center gap-2">
                            <Button variant="outline" @click="openAddDialog">
                                <Plus class="mr-2 size-4" />
                                Add security rule
                            </Button>
                        </div>
                    </div>
                </CardHeader>

                <CardContent>
                    <div
                        v-if="rules.data.length === 0"
                        class="rounded-lg border border-dashed p-8 text-center"
                    >
                        <Lock
                            class="mx-auto mb-4 size-12 text-muted-foreground opacity-50"
                        />
                        <h3 class="text-lg font-medium">
                            No security rules yet
                        </h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Get started and create your first security rule to
                            protect paths with HTTP authentication.
                        </p>
                        <Button
                            variant="outline"
                            class="mt-4"
                            @click="openAddDialog"
                        >
                            <Plus class="mr-2 size-4" />
                            Add security rule
                        </Button>
                    </div>

                    <div v-else class="divide-y">
                        <div
                            v-for="rule in rules.data"
                            :key="rule.id"
                            class="flex items-center justify-between py-4 first:pt-0 last:pb-0"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <p
                                        class="truncate font-medium"
                                        :title="rule.name"
                                    >
                                        {{ rule.name }}
                                    </p>
                                    <Badge
                                        v-if="rule.status !== 'installed'"
                                        :variant="rule.statusBadgeVariant"
                                    >
                                        {{ rule.displayableStatus }}
                                    </Badge>
                                </div>
                                <p
                                    class="mt-1 truncate text-sm text-muted-foreground"
                                >
                                    {{ formatCredentials(rule.credentials) }}
                                </p>
                            </div>
                            <div class="ml-4 flex items-center gap-4">
                                <span
                                    v-if="rule.path"
                                    class="font-mono text-sm text-muted-foreground"
                                >
                                    {{ rule.path }}
                                </span>
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button variant="ghost" size="icon">
                                            <MoreHorizontal class="size-4" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem
                                            v-if="rule.can.update"
                                            @click="openEditDialog(rule)"
                                        >
                                            <Pencil class="mr-2 size-4" />
                                            Edit
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator
                                            v-if="rule.can.delete"
                                        />
                                        <DropdownMenuItem
                                            v-if="rule.can.delete"
                                            class="text-destructive focus:text-destructive"
                                            @click="deleteRule(rule)"
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

        <!-- Add Rule Dialog -->
        <Dialog v-model:open="showAddDialog">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>New security rule</DialogTitle>
                    <DialogDescription>
                        Create a security rule to protect a path with HTTP basic
                        authentication.
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="submitAdd">
                    <div class="space-y-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            v-model="addForm.name"
                            placeholder="e.g., Admin Area"
                        />
                        <InputError :message="addForm.errors.name" />
                    </div>

                    <div class="space-y-2">
                        <Label for="path">Path (optional)</Label>
                        <Input
                            id="path"
                            v-model="addForm.path"
                            class="font-mono text-sm"
                            placeholder="e.g., /admin"
                        />
                        <p class="text-xs text-muted-foreground">
                            Leave empty to protect the entire site.
                        </p>
                        <InputError :message="addForm.errors.path" />
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <Label>Credentials</Label>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                @click="addCredential(addForm)"
                            >
                                <Plus class="mr-1 size-4" />
                                Add
                            </Button>
                        </div>

                        <div
                            v-for="(credential, index) in addForm.credentials"
                            :key="index"
                            class="flex items-start gap-2"
                        >
                            <div class="flex-1 space-y-1">
                                <Input
                                    v-model="credential.username"
                                    placeholder="Username"
                                />
                                <InputError
                                    :message="
                                        addForm.errors[
                                            `credentials.${index}.username`
                                        ]
                                    "
                                />
                            </div>
                            <div class="flex-1 space-y-1">
                                <Input
                                    v-model="credential.password"
                                    type="password"
                                    placeholder="Password"
                                />
                                <InputError
                                    :message="
                                        addForm.errors[
                                            `credentials.${index}.password`
                                        ]
                                    "
                                />
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="shrink-0"
                                :disabled="addForm.credentials.length <= 1"
                                @click="removeCredential(addForm, index)"
                            >
                                <X class="size-4" />
                            </Button>
                        </div>
                        <InputError :message="addForm.errors.credentials" />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="showAddDialog = false"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="addForm.processing">
                            {{
                                addForm.processing
                                    ? 'Creating...'
                                    : 'Create security rule'
                            }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Edit Rule Dialog -->
        <Dialog v-model:open="showEditDialog">
            <DialogContent v-if="editingRule" class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Edit security rule</DialogTitle>
                    <DialogDescription>
                        Update the security rule configuration. Enter new
                        passwords to update credentials.
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="submitEdit">
                    <div class="space-y-2">
                        <Label for="edit-name">Name</Label>
                        <Input id="edit-name" v-model="editForm.name" />
                        <InputError :message="editForm.errors.name" />
                    </div>

                    <div class="space-y-2">
                        <Label for="edit-path">Path (optional)</Label>
                        <Input
                            id="edit-path"
                            v-model="editForm.path"
                            class="font-mono text-sm"
                            placeholder="e.g., /admin"
                        />
                        <InputError :message="editForm.errors.path" />
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <Label>Credentials</Label>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                @click="addCredential(editForm)"
                            >
                                <Plus class="mr-1 size-4" />
                                Add
                            </Button>
                        </div>

                        <div
                            v-for="(credential, index) in editForm.credentials"
                            :key="index"
                            class="flex items-start gap-2"
                        >
                            <div class="flex-1 space-y-1">
                                <Input
                                    v-model="credential.username"
                                    placeholder="Username"
                                />
                                <InputError
                                    :message="
                                        editForm.errors[
                                            `credentials.${index}.username`
                                        ]
                                    "
                                />
                            </div>
                            <div class="flex-1 space-y-1">
                                <Input
                                    v-model="credential.password"
                                    type="password"
                                    placeholder="New password"
                                />
                                <InputError
                                    :message="
                                        editForm.errors[
                                            `credentials.${index}.password`
                                        ]
                                    "
                                />
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="shrink-0"
                                :disabled="editForm.credentials.length <= 1"
                                @click="removeCredential(editForm, index)"
                            >
                                <X class="size-4" />
                            </Button>
                        </div>
                        <InputError :message="editForm.errors.credentials" />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="showEditDialog = false"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="editForm.processing">
                            {{
                                editForm.processing
                                    ? 'Saving...'
                                    : 'Save changes'
                            }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </SiteLayout>
</template>
