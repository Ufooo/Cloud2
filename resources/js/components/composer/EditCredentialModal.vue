<script setup lang="ts">
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useForm } from '@inertiajs/vue3';
import { Eye, EyeOff } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const showPassword = ref(false);

interface Credential {
    id: number;
    user?: string;
    repository: string;
    username: string;
    password: string;
    status: string | null;
    displayableStatus: string | null;
    statusBadgeVariant: string | null;
    createdAt: string | null;
}

interface Props {
    updateUrl: string;
    users?: string[];
    credential: Credential | null;
    open: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const form = useForm({
    user: '',
    repository: '',
    username: '',
    password: '',
});

watch(
    () => props.credential,
    (credential) => {
        if (credential) {
            form.user = credential.user ?? '';
            form.repository = credential.repository;
            form.username = credential.username;
            form.password = credential.password;
            form.clearErrors();
        }
    },
);

function submit() {
    form.transform((data) =>
        props.users
            ? data
            : {
                  repository: data.repository,
                  username: data.username,
                  password: data.password,
              },
    ).put(props.updateUrl, {
        preserveScroll: true,
        onSuccess: () => {
            emit('update:open', false);
            form.reset();
        },
    });
}

function close() {
    emit('update:open', false);
    form.reset();
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Edit Repository</DialogTitle>
                <DialogDescription>
                    Update credentials for this Composer repository.
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submit" class="space-y-4">
                <div v-if="users" class="space-y-2">
                    <Label for="edit-user">Unix User</Label>
                    <Select v-model="form.user" required>
                        <SelectTrigger id="edit-user">
                            <SelectValue placeholder="Select user" />
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
                    <InputError :message="form.errors.user" />
                </div>

                <div class="space-y-2">
                    <Label for="edit-repository">Repository</Label>
                    <Input
                        id="edit-repository"
                        v-model="form.repository"
                        placeholder="repo.packagist.com"
                        required
                    />
                    <InputError :message="form.errors.repository" />
                </div>

                <div class="space-y-2">
                    <Label for="edit-username">Username</Label>
                    <Input
                        id="edit-username"
                        v-model="form.username"
                        required
                    />
                    <InputError :message="form.errors.username" />
                </div>

                <div class="space-y-2">
                    <Label for="edit-password">Password</Label>
                    <div class="relative">
                        <Input
                            id="edit-password"
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            autocomplete="off"
                            required
                        />
                        <button
                            type="button"
                            class="absolute top-1/2 right-3 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                            @click="showPassword = !showPassword"
                        >
                            <EyeOff v-if="showPassword" class="size-4" />
                            <Eye v-else class="size-4" />
                        </button>
                    </div>
                    <InputError :message="form.errors.password" />
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="close">
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Saving...' : 'Save Changes' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
