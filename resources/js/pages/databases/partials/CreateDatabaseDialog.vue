<script setup lang="ts">
import { store } from '@/actions/Nip/Database/Http/Controllers/DatabaseController';
import { Badge } from '@/components/ui/badge';
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
import type { Server } from '@/types';
import { generateSecurePassword } from '@/utils/password';
import { useForm } from '@inertiajs/vue3';
import { Eye, EyeOff } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface Props {
    server: Server;
}

const props = defineProps<Props>();
const open = defineModel<boolean>('open', { default: false });

const showPassword = ref(false);
const form = useForm({
    name: '',
    user: '',
    password: '',
});

watch(open, (isOpen) => {
    if (isOpen) {
        form.reset();
        showPassword.value = false;
    }
});

function handleGeneratePassword() {
    form.password = generateSecurePassword();
    showPassword.value = true;
}

function submit() {
    form.post(store.url(props.server), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            form.reset();
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Add database</DialogTitle>
                <DialogDescription>
                    Create a new database for your
                    <strong>{{ server.name }}</strong> server. You can
                    optionally create a new database user if needed.
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submit">
                <div class="space-y-4">
                    <div class="space-y-2">
                        <Label for="db-name">Name</Label>
                        <Input
                            id="db-name"
                            v-model="form.name"
                            placeholder="my_database"
                            :class="{
                                'border-destructive': form.errors.name,
                            }"
                        />
                        <p
                            v-if="form.errors.name"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <Label for="db-user">User</Label>
                            <Badge variant="outline" class="text-xs font-normal"
                                >Optional</Badge
                            >
                        </div>
                        <Input
                            id="db-user"
                            v-model="form.user"
                            placeholder="db_user"
                            :class="{
                                'border-destructive': form.errors.user,
                            }"
                        />
                        <p
                            v-if="form.errors.user"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.user }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <Label for="db-password">Password</Label>
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
                                id="db-password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                placeholder="••••••••"
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
                </div>

                <DialogFooter class="mt-6">
                    <Button
                        type="submit"
                        class="w-full"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Creating...' : 'Create database' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
