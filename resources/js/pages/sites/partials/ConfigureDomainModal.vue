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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { computed, ref, watch } from 'vue';

interface WwwRedirectTypeOption {
    value: string;
    label: string;
    description: string;
    isDefault: boolean;
}

interface Props {
    open: boolean;
    domain: string;
    type?: string;
    redirectTarget?: string;
    wwwRedirectType: string;
    wwwRedirectTypes: WwwRedirectTypeOption[];
    allowWildcard?: boolean;
    isPrimary?: boolean;
    primaryDomain?: string | null;
}

const props = withDefaults(defineProps<Props>(), {
    type: 'alias',
    redirectTarget: '',
    allowWildcard: false,
    isPrimary: false,
    primaryDomain: null,
});

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'update:type', value: string): void;
    (e: 'update:redirectTarget', value: string): void;
    (e: 'update:wwwRedirectType', value: string): void;
    (e: 'update:allowWildcard', value: boolean): void;
    (e: 'save'): void;
}>();

const localType = ref(props.type);
const localRedirectTarget = ref(props.redirectTarget);
const localWwwRedirectType = ref(props.wwwRedirectType);
const localAllowWildcard = ref(props.allowWildcard);

const isRedirect = computed(() => localType.value === 'redirect');

const availableRedirectTypes = computed(() => {
    return props.wwwRedirectTypes.filter((type) => {
        if (type.value === 'to_primary') {
            return !props.isPrimary && props.primaryDomain;
        }
        return true;
    });
});

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            localType.value = props.type;
            localRedirectTarget.value = props.redirectTarget;
            localWwwRedirectType.value = props.wwwRedirectType;
            localAllowWildcard.value = props.allowWildcard;
        }
    },
);

function setWildcard(value: boolean) {
    localAllowWildcard.value = value;
    if (value) localWwwRedirectType.value = 'none';
}

const canSave = computed(() => {
    if (!isRedirect.value) return true;
    return localRedirectTarget.value.trim().length > 0;
});

function save() {
    if (!canSave.value) return;
    emit('update:type', localType.value);
    emit('update:redirectTarget', isRedirect.value ? localRedirectTarget.value.trim() : '');
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
                    <span class="font-medium">{{
                        domain || 'Your domain'
                    }}</span>
                    will be used to access your site and can be configured with
                    various redirect options.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-6 py-4">
                <!-- Domain Type -->
                <div class="space-y-3">
                    <Label>Domain type</Label>
                    <div class="space-y-2">
                        <button
                            type="button"
                            class="flex w-full items-start gap-3 rounded-md border p-3 text-left transition-colors"
                            :class="
                                localType === 'alias'
                                    ? 'border-primary bg-primary/5'
                                    : 'border-border hover:bg-muted/50'
                            "
                            @click="localType = 'alias'"
                        >
                            <div
                                class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full border"
                                :class="
                                    localType === 'alias'
                                        ? 'border-primary'
                                        : 'border-muted-foreground'
                                "
                            >
                                <div
                                    v-if="localType === 'alias'"
                                    class="size-2 rounded-full bg-primary"
                                />
                            </div>
                            <div class="flex-1">
                                <span class="font-medium">Alias</span>
                                <p class="text-sm text-muted-foreground">
                                    Serve this site under this domain too.
                                </p>
                            </div>
                        </button>
                        <button
                            type="button"
                            class="flex w-full items-start gap-3 rounded-md border p-3 text-left transition-colors"
                            :class="
                                localType === 'redirect'
                                    ? 'border-primary bg-primary/5'
                                    : 'border-border hover:bg-muted/50'
                            "
                            @click="localType = 'redirect'"
                        >
                            <div
                                class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full border"
                                :class="
                                    localType === 'redirect'
                                        ? 'border-primary'
                                        : 'border-muted-foreground'
                                "
                            >
                                <div
                                    v-if="localType === 'redirect'"
                                    class="size-2 rounded-full bg-primary"
                                />
                            </div>
                            <div class="flex-1">
                                <span class="font-medium">Redirect</span>
                                <p class="text-sm text-muted-foreground">
                                    301-redirect every request on this domain
                                    to another domain.
                                </p>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Redirect target (only for type=redirect) -->
                <div v-if="isRedirect" class="space-y-2">
                    <Label for="redirect-target">Redirect target</Label>
                    <Input
                        id="redirect-target"
                        v-model="localRedirectTarget"
                        type="text"
                        placeholder="example.com"
                    />
                    <p class="text-sm text-muted-foreground">
                        Requests to <span class="font-medium">{{ domain || 'this domain' }}</span>
                        will be permanently redirected to
                        <span class="font-medium">https://{{ localRedirectTarget || 'example.com' }}</span>.
                    </p>
                </div>

                <!-- Wildcards Section (hidden for redirect type) -->
                <div v-if="!isRedirect" class="space-y-3">
                    <Label>Wildcards</Label>
                    <p class="text-sm text-muted-foreground">
                        Allow all subdomains to accept traffic.
                    </p>
                    <div class="space-y-2">
                        <button
                            type="button"
                            class="flex w-full items-start gap-3 rounded-md border p-3 text-left transition-colors"
                            :class="
                                !localAllowWildcard
                                    ? 'border-primary bg-primary/5'
                                    : 'border-border hover:bg-muted/50'
                            "
                            @click="setWildcard(false)"
                        >
                            <div
                                class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full border"
                                :class="
                                    !localAllowWildcard
                                        ? 'border-primary'
                                        : 'border-muted-foreground'
                                "
                            >
                                <div
                                    v-if="!localAllowWildcard"
                                    class="size-2 rounded-full bg-primary"
                                />
                            </div>
                            <div class="flex-1">
                                <span class="font-medium">Off</span>
                                <p class="text-sm text-muted-foreground">
                                    Support only the root domain, e.g.
                                    {{ domain || 'example.com' }}
                                </p>
                            </div>
                        </button>
                        <button
                            type="button"
                            class="flex w-full items-start gap-3 rounded-md border p-3 text-left transition-colors"
                            :class="
                                localAllowWildcard
                                    ? 'border-primary bg-primary/5'
                                    : 'border-border hover:bg-muted/50'
                            "
                            @click="setWildcard(true)"
                        >
                            <div
                                class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full border"
                                :class="
                                    localAllowWildcard
                                        ? 'border-primary'
                                        : 'border-muted-foreground'
                                "
                            >
                                <div
                                    v-if="localAllowWildcard"
                                    class="size-2 rounded-full bg-primary"
                                />
                            </div>
                            <div class="flex-1">
                                <span class="font-medium">On</span>
                                <p class="text-sm text-muted-foreground">
                                    Support all subdomains, e.g. blog.{{
                                        domain || 'example.com'
                                    }}
                                </p>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Redirects Section (hidden for redirect type) -->
                <div v-if="!isRedirect" class="space-y-3">
                    <Label>Redirects</Label>
                    <p class="text-sm text-muted-foreground">
                        Manage how your domain handles redirects.
                    </p>
                    <div class="space-y-2">
                        <button
                            v-for="redirectType in availableRedirectTypes"
                            :key="redirectType.value"
                            type="button"
                            class="flex w-full items-start gap-3 rounded-md border p-3 text-left transition-colors"
                            :class="[
                                localWwwRedirectType === redirectType.value
                                    ? 'border-primary bg-primary/5'
                                    : 'border-border hover:bg-muted/50',
                                localAllowWildcard &&
                                redirectType.value !== 'none' &&
                                redirectType.value !== 'to_primary'
                                    ? 'cursor-not-allowed opacity-50'
                                    : '',
                            ]"
                            :disabled="
                                localAllowWildcard &&
                                redirectType.value !== 'none' &&
                                redirectType.value !== 'to_primary'
                            "
                            @click="localWwwRedirectType = redirectType.value"
                        >
                            <div
                                class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full border"
                                :class="
                                    localWwwRedirectType === redirectType.value
                                        ? 'border-primary'
                                        : 'border-muted-foreground'
                                "
                            >
                                <div
                                    v-if="
                                        localWwwRedirectType ===
                                        redirectType.value
                                    "
                                    class="size-2 rounded-full bg-primary"
                                />
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium">{{
                                        redirectType.label
                                    }}</span>
                                    <span
                                        v-if="
                                            redirectType.isDefault &&
                                            !localAllowWildcard
                                        "
                                        class="rounded border border-green-600 px-1.5 py-0.5 text-xs font-normal text-green-600 dark:border-green-500 dark:text-green-500"
                                    >
                                        Recommended
                                    </span>
                                </div>
                                <p
                                    v-if="
                                        redirectType.value === 'to_primary' &&
                                        primaryDomain
                                    "
                                    class="mt-1 text-sm text-muted-foreground"
                                >
                                    Redirects to {{ primaryDomain }}
                                </p>
                            </div>
                        </button>
                    </div>
                </div>
            </div>

            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    @click="$emit('update:open', false)"
                    >Cancel</Button
                >
                <Button type="button" :disabled="!canSave" @click="save">Save</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
