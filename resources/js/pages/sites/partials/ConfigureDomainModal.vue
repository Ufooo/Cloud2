<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { ref, watch } from 'vue';

interface WwwRedirectTypeOption {
    value: string;
    label: string;
    description: string;
    isDefault: boolean;
}

interface Props {
    open: boolean;
    domain: string;
    wwwRedirectType: string;
    wwwRedirectTypes: WwwRedirectTypeOption[];
    allowWildcard?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    allowWildcard: false,
});

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'update:wwwRedirectType', value: string): void;
    (e: 'update:allowWildcard', value: boolean): void;
    (e: 'save'): void;
}>();

const localWwwRedirectType = ref(props.wwwRedirectType);
const localAllowWildcard = ref(props.allowWildcard);

watch(() => props.open, (isOpen) => {
    if (isOpen) {
        localWwwRedirectType.value = props.wwwRedirectType;
        localAllowWildcard.value = props.allowWildcard;
    }
});

function setWildcard(value: boolean) {
    localAllowWildcard.value = value;
    if (value) localWwwRedirectType.value = 'none';
}

function save() {
    emit('update:wwwRedirectType', localWwwRedirectType.value);
    emit('update:allowWildcard', localAllowWildcard.value);
    emit('save');
    emit('update:open', false);
}
</script>

<template>
    <Dialog :open="open" @update:open="$emit('update:open', $event)">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>Configure {{ domain || 'domain' }}</DialogTitle>
                <DialogDescription>
                    <span class="font-medium">{{ domain || 'Your domain' }}</span> will be used to access your site and can be configured with various redirect options.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-6 py-4">
                <!-- Wildcards Section -->
                <div class="space-y-3">
                    <Label>Wildcards</Label>
                    <p class="text-sm text-muted-foreground">
                        Allow all subdomains to accept traffic.
                    </p>
                    <div class="space-y-2">
                        <button
                            type="button"
                            class="flex w-full items-start gap-3 rounded-md border p-3 text-left transition-colors"
                            :class="!localAllowWildcard
                                ? 'border-primary bg-primary/5'
                                : 'border-border hover:bg-muted/50'"
                            @click="setWildcard(false)"
                        >
                            <div
                                class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full border"
                                :class="!localAllowWildcard
                                    ? 'border-primary'
                                    : 'border-muted-foreground'"
                            >
                                <div
                                    v-if="!localAllowWildcard"
                                    class="size-2 rounded-full bg-primary"
                                />
                            </div>
                            <div class="flex-1">
                                <span class="font-medium">Off</span>
                                <p class="text-sm text-muted-foreground">
                                    Support only the root domain, e.g. {{ domain || 'example.com' }}
                                </p>
                            </div>
                        </button>
                        <button
                            type="button"
                            class="flex w-full items-start gap-3 rounded-md border p-3 text-left transition-colors"
                            :class="localAllowWildcard
                                ? 'border-primary bg-primary/5'
                                : 'border-border hover:bg-muted/50'"
                            @click="setWildcard(true)"
                        >
                            <div
                                class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full border"
                                :class="localAllowWildcard
                                    ? 'border-primary'
                                    : 'border-muted-foreground'"
                            >
                                <div
                                    v-if="localAllowWildcard"
                                    class="size-2 rounded-full bg-primary"
                                />
                            </div>
                            <div class="flex-1">
                                <span class="font-medium">On</span>
                                <p class="text-sm text-muted-foreground">
                                    Support all subdomains, e.g. blog.{{ domain || 'example.com' }}
                                </p>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Redirects Section -->
                <div class="space-y-3">
                    <Label>Redirects</Label>
                    <p class="text-sm text-muted-foreground">
                        Manage how your domain handles www. redirects for this domain.
                    </p>
                    <div class="space-y-2">
                        <button
                            v-for="redirectType in wwwRedirectTypes"
                            :key="redirectType.value"
                            type="button"
                            class="flex w-full items-start gap-3 rounded-md border p-3 text-left transition-colors"
                            :class="[
                                localWwwRedirectType === redirectType.value
                                    ? 'border-primary bg-primary/5'
                                    : 'border-border hover:bg-muted/50',
                                localAllowWildcard && redirectType.value !== 'none' ? 'cursor-not-allowed opacity-50' : ''
                            ]"
                            :disabled="localAllowWildcard && redirectType.value !== 'none'"
                            @click="localWwwRedirectType = redirectType.value"
                        >
                            <div
                                class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full border"
                                :class="localWwwRedirectType === redirectType.value
                                    ? 'border-primary'
                                    : 'border-muted-foreground'"
                            >
                                <div
                                    v-if="localWwwRedirectType === redirectType.value"
                                    class="size-2 rounded-full bg-primary"
                                />
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium">{{ redirectType.label }}</span>
                                    <span
                                        v-if="redirectType.isDefault && !localAllowWildcard"
                                        class="rounded border border-green-600 px-1.5 py-0.5 text-xs font-normal text-green-600 dark:border-green-500 dark:text-green-500"
                                    >
                                        Recommended
                                    </span>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>
            </div>

            <DialogFooter>
                <Button type="button" variant="outline" @click="$emit('update:open', false)">Cancel</Button>
                <Button type="button" @click="save">Save</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
