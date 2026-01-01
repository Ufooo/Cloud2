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
import { ref } from 'vue';

const showPassword = ref(false);

interface Props {
    storeUrl: string;
    users?: string[];
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

function submit() {
    form.transform((data) =>
        props.users
            ? data
            : {
                  repository: data.repository,
                  username: data.username,
                  password: data.password,
              },
    ).post(props.storeUrl, {
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
                <DialogTitle>Add Repository</DialogTitle>
                <DialogDescription>
                    Add authentication credentials for a private Composer
                    repository.
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submit" class="space-y-4">
                <div v-if="users" class="space-y-2">
                    <Label for="add-user">Unix User</Label>
                    <Select v-model="form.user" required>
                        <SelectTrigger id="add-user">
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
                    <Label for="add-repository">Repository</Label>
                    <Input
                        id="add-repository"
                        v-model="form.repository"
                        placeholder="repo.packagist.com"
                        required
                    />
                    <InputError :message="form.errors.repository" />
                </div>

                <div class="space-y-2">
                    <Label for="add-username">Username</Label>
                    <Input id="add-username" v-model="form.username" required />
                    <InputError :message="form.errors.username" />
                </div>

                <div class="space-y-2">
                    <Label for="add-password">Password</Label>
                    <div class="relative">
                        <Input
                            id="add-password"
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
                        {{ form.processing ? 'Adding...' : 'Add Repository' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
