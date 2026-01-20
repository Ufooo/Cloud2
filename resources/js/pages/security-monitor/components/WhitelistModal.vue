<script setup lang="ts">
import { store as storeGit } from '@/actions/Nip/SecurityMonitor/Http/Controllers/GitWhitelistController';
import InputError from '@/components/InputError.vue';
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
import type { GitChangeType, Site } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

interface Props {
    open: boolean;
    site: Site;
    filePath: string;
    changeType?: GitChangeType;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const form = useForm({
    site_id: props.site.id,
    file_path: props.filePath,
    change_type: props.changeType ?? 'any',
    reason: '',
    apply_to_all_types: false,
});

watch(
    () => [props.site, props.filePath, props.changeType] as const,
    ([newSite, newFilePath, newChangeType]) => {
        form.site_id = newSite.id;
        form.file_path = newFilePath;
        form.change_type = newChangeType ?? 'any';
    },
    { immediate: true },
);

function handleSubmit() {
    form.post(storeGit.url(), {
        preserveScroll: true,
        onSuccess: () => {
            emit('update:open', false);
            form.reset();
        },
    });
}

function handleClose() {
    emit('update:open', false);
    form.reset();
}
</script>

<template>
    <Dialog :open="open" @update:open="handleClose">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Whitelist Git Change</DialogTitle>
                <DialogDescription>This file change will be ignored in future scans.</DialogDescription>
            </DialogHeader>

            <form @submit.prevent="handleSubmit" class="space-y-4">
                <!-- File Info -->
                <div class="space-y-2 rounded-md bg-muted p-3">
                    <div class="text-sm">
                        <span class="font-medium">File:</span>
                        <code class="ml-2 text-xs">{{ filePath }}</code>
                    </div>
                    <div class="text-sm">
                        <span class="font-medium">Type:</span>
                        <code class="ml-2 text-xs">{{ changeType }}</code>
                    </div>
                    <div class="text-sm">
                        <span class="font-medium">Site:</span>
                        <span class="ml-2">{{ site.domain }}</span>
                    </div>
                </div>

                <!-- Reason -->
                <div class="space-y-2">
                    <Label for="reason">Reason (optional)</Label>
                    <Input
                        id="reason"
                        v-model="form.reason"
                        placeholder="e.g., Manually modified config file"
                    />
                    <InputError :message="form.errors.reason" />
                </div>

                <!-- Options -->
                <div class="space-y-3">
                    <div class="flex items-center space-x-2">
                        <Checkbox
                            id="all-types"
                            v-model:checked="form.apply_to_all_types"
                        />
                        <label
                            for="all-types"
                            class="text-sm leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                        >
                            Apply to all change types
                            <span class="text-muted-foreground">
                                (modified, untracked, deleted, etc.)
                            </span>
                        </label>
                    </div>
                </div>

                <InputError :message="form.errors.site_id" />
                <InputError :message="form.errors.file_path" />
                <InputError :message="form.errors.change_type" />

                <DialogFooter>
                    <Button type="button" variant="outline" @click="handleClose">
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Adding...' : 'Whitelist' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
