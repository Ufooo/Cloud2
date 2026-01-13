<script setup lang="ts">
import { updateUser } from '@/actions/Nip/Database/Http/Controllers/DatabaseController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import {
    useDatabaseUserForm,
    type DatabaseItem,
} from '@/composables/useDatabaseUserForm';
import type { Server } from '@/types';
import { generateSecurePassword } from '@/utils/password';
import { useForm } from '@inertiajs/vue3';
import { Eye, EyeOff } from 'lucide-vue-next';
import { toRef, watch } from 'vue';

interface DatabaseUserItem {
    id: string;
    username: string;
    readonly?: boolean;
    databaseIds?: string[];
}

interface Props {
    server: Server;
    databases: DatabaseItem[];
    user: DatabaseUserItem | null;
}

const props = defineProps<Props>();
const open = defineModel<boolean>('open', { default: false });

const databasesRef = toRef(props, 'databases');

const { showPassword, databaseSearch, filteredDatabases } = useDatabaseUserForm(
    { databases: databasesRef },
);

const form = useForm({
    password: '',
    databases: [] as string[],
    readonly: false,
});

watch(
    () => props.user,
    (user) => {
        if (user) {
            form.reset();
            form.databases = user.databaseIds ?? [];
            form.readonly = user.readonly ?? false;
            showPassword.value = false;
            databaseSearch.value = '';
        }
    },
);

watch(open, (isOpen) => {
    if (!isOpen) {
        form.reset();
        showPassword.value = false;
        databaseSearch.value = '';
    }
});

function handleGeneratePassword() {
    form.password = generateSecurePassword();
    showPassword.value = true;
}

function handleToggleDatabase(databaseId: string) {
    const index = form.databases.indexOf(databaseId);
    if (index === -1) {
        form.databases.push(databaseId);
    } else {
        form.databases.splice(index, 1);
    }
}

function handleSelectAll() {
    form.databases = filteredDatabases.value.map((db) => db.id);
}

function submit() {
    if (!props.user) return;

    form.transform((data) => ({
        ...data,
        readonly: Boolean(data.readonly),
    })).put(
        updateUser.url({
            server: props.server.slug,
            databaseUser: props.user.id,
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;
                form.reset();
            },
        },
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>Edit database user</DialogTitle>
                <DialogDescription>
                    Update the database user
                    <strong>{{ user?.username }}</strong> on server
                    <strong>{{ server.name }}</strong
                    >.
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submit">
                <div class="space-y-4">
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <Label for="edit-user-password">Password</Label>
                                <Badge
                                    variant="outline"
                                    class="text-xs font-normal"
                                    >Optional</Badge
                                >
                            </div>
                            <Button
                                type="button"
                                variant="link"
                                size="sm"
                                class="h-auto p-0 text-primary"
                                @click="handleGeneratePassword"
                            >
                                Generate password
                            </Button>
                        </div>
                        <div class="relative">
                            <Input
                                id="edit-user-password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                placeholder="Leave empty to keep current"
                                class="pr-10"
                                :class="{
                                    'border-destructive': form.errors.password,
                                }"
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="absolute top-1/2 right-1 size-8 -translate-y-1/2"
                                @click="showPassword = !showPassword"
                            >
                                <Eye v-if="!showPassword" class="size-4" />
                                <EyeOff v-else class="size-4" />
                            </Button>
                        </div>
                        <p
                            v-if="form.errors.password"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label>Grant access to</Label>
                        <Input
                            v-model="databaseSearch"
                            placeholder="Search"
                            class="mb-2"
                        />
                        <div
                            class="max-h-48 space-y-1 overflow-y-auto rounded-md border p-2"
                        >
                            <div
                                v-for="db in filteredDatabases"
                                :key="db.id"
                                class="flex items-center gap-2 rounded px-2 py-1.5 hover:bg-accent"
                            >
                                <Checkbox
                                    :id="`edit-db-${db.id}`"
                                    :model-value="form.databases.includes(db.id)"
                                    @update:model-value="
                                        handleToggleDatabase(db.id)
                                    "
                                />
                                <label
                                    :for="`edit-db-${db.id}`"
                                    class="flex-1 cursor-pointer text-sm"
                                >
                                    {{ db.name }}
                                </label>
                            </div>
                            <p
                                v-if="filteredDatabases.length === 0"
                                class="py-2 text-center text-sm text-muted-foreground"
                            >
                                No databases found
                            </p>
                        </div>
                        <Button
                            type="button"
                            variant="link"
                            size="sm"
                            class="h-auto p-0 text-primary"
                            @click="handleSelectAll"
                        >
                            Select all databases
                        </Button>
                    </div>

                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <div>
                                <Label for="edit-readonly"
                                    >Read-only access?</Label
                                >
                                <p class="text-sm text-muted-foreground">
                                    If enabled, this user will only be able to
                                    read data from the selected databases.
                                </p>
                            </div>
                            <Switch id="edit-readonly" v-model="form.readonly" />
                        </div>
                    </div>
                </div>

                <DialogFooter class="mt-6">
                    <Button
                        type="submit"
                        class="w-full"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Saving...' : 'Save changes' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
