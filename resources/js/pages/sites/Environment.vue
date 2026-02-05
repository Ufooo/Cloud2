<script setup lang="ts">
import {
    get,
    update,
} from '@/actions/Nip/Site/Http/Controllers/SiteEnvironmentController';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import SiteLayout from '@/layouts/SiteLayout.vue';
import type { Site } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Eye,
    EyeOff,
    FileCode2,
    Loader2,
    RotateCcw,
    Save,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Props {
    site: Site;
}

const props = defineProps<Props>();

// State
const isRevealed = ref(false);
const isLoading = ref(false);
const isSaving = ref(false);
const loadError = ref<string | null>(null);
const originalContent = ref('');
const successMessage = ref<string | null>(null);

// Switch values from database (initial state)
const initialClearConfigCache = props.site.envClearConfigCache ?? false;
const initialRestartQueue = props.site.envRestartQueue ?? false;

// Current switch values
const clearConfigCache = ref(initialClearConfigCache);
const restartQueue = ref(initialRestartQueue);

// Form
const form = useForm({
    environment: '',
});

// Computed - detect changes in env content or switches
const hasEnvChanges = computed(
    () => isRevealed.value && form.environment !== originalContent.value && originalContent.value !== '',
);

const hasSwitchChanges = computed(
    () =>
        clearConfigCache.value !== initialClearConfigCache ||
        restartQueue.value !== initialRestartQueue,
);

const hasChanges = computed(() => hasEnvChanges.value || hasSwitchChanges.value);

const envPath = computed(() => {
    if (props.site.zeroDowntime) {
        return `/home/${props.site.user}/${props.site.domain}/.env`;
    }
    const root = props.site.rootDirectory === '/' ? '' : props.site.rootDirectory;
    return `/home/${props.site.user}/${props.site.domain}${root}/.env`;
});

// Methods
async function toggleReveal() {
    if (isRevealed.value) {
        // Hide
        isRevealed.value = false;
        form.environment = '';
        originalContent.value = '';
        loadError.value = null;
        successMessage.value = null;
    } else {
        // Reveal - fetch content
        await fetchContent();
    }
}

async function fetchContent() {
    isLoading.value = true;
    loadError.value = null;
    successMessage.value = null;

    try {
        const response = await fetch(get.url(props.site));
        const data = await response.json();

        if (data.success) {
            form.environment = data.content;
            originalContent.value = data.content;
            isRevealed.value = true;
        } else {
            loadError.value = data.error || 'Failed to load environment file.';
        }
    } catch {
        loadError.value = 'Failed to connect to the server.';
    } finally {
        isLoading.value = false;
    }
}

async function submitForm() {
    successMessage.value = null;
    isSaving.value = true;

    try {
        const response = await fetch(update.url(props.site), {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN':
                    document.querySelector<HTMLMetaElement>(
                        'meta[name="csrf-token"]',
                    )?.content || '',
            },
            body: JSON.stringify({
                environment: hasEnvChanges.value ? form.environment : null,
                clear_config_cache: clearConfigCache.value,
                restart_queue: restartQueue.value,
            }),
        });

        const data = await response.json();

        if (data.success) {
            if (hasEnvChanges.value) {
                originalContent.value = form.environment;
            }
            successMessage.value = data.message;
        } else {
            form.errors.environment = data.error || 'Failed to save.';
        }
    } catch {
        form.errors.environment = 'Failed to connect to the server.';
    } finally {
        isSaving.value = false;
    }
}

function resetForm() {
    form.environment = originalContent.value;
    clearConfigCache.value = initialClearConfigCache;
    restartQueue.value = initialRestartQueue;
    form.clearErrors();
    successMessage.value = null;
}
</script>

<template>
    <Head :title="`Environment - ${site.domain}`" />

    <SiteLayout :site="site">
        <div class="space-y-6">
            <!-- Environment Editor Card -->
            <Card class="bg-white">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <FileCode2 class="size-5" />
                        Environment
                    </CardTitle>
                    <CardDescription>
                        Edit the
                        <code class="rounded bg-muted px-1.5 py-0.5 font-mono text-xs">.env</code>
                        file for your application. Environment variables are
                        typically loaded by your application to configure
                        database connections, API keys, and other settings.
                    </CardDescription>
                </CardHeader>

                <CardContent class="space-y-6">
                    <!-- Success Message -->
                    <Alert v-if="successMessage" class="border-green-200 bg-green-50">
                        <AlertDescription class="text-green-800">
                            {{ successMessage }}
                        </AlertDescription>
                    </Alert>

                    <!-- Error Message -->
                    <Alert v-if="loadError" variant="destructive">
                        <AlertTriangle class="size-4" />
                        <AlertDescription>
                            {{ loadError }}
                        </AlertDescription>
                    </Alert>

                    <!-- Hidden State -->
                    <div
                        v-if="!isRevealed"
                        class="flex flex-col items-center justify-center rounded-lg border-2 border-dashed bg-muted/30 py-16"
                    >
                        <AlertTriangle class="mb-4 size-8 text-amber-500" />
                        <p class="mb-4 text-center text-muted-foreground">
                            Environment variables should not be shared publicly.
                        </p>
                        <Button
                            variant="outline"
                            :disabled="isLoading"
                            @click="toggleReveal"
                        >
                            <Loader2 v-if="isLoading" class="mr-2 size-4 animate-spin" />
                            <Eye v-else class="mr-2 size-4" />
                            {{ isLoading ? 'Loading...' : 'Reveal Environment' }}
                        </Button>
                    </div>

                    <!-- Revealed State -->
                    <div v-else class="space-y-4">
                        <!-- File path info -->
                        <div class="flex items-center justify-between">
                            <p class="text-sm text-muted-foreground">
                                <code class="rounded bg-muted px-1.5 py-0.5 font-mono text-xs">
                                    {{ envPath }}
                                </code>
                            </p>
                            <Button
                                variant="ghost"
                                size="sm"
                                @click="toggleReveal"
                            >
                                <EyeOff class="mr-2 size-4" />
                                Hide
                            </Button>
                        </div>

                        <!-- Editor -->
                        <Textarea
                            v-model="form.environment"
                            class="min-h-[400px] font-mono text-sm"
                            placeholder="APP_NAME=Laravel
APP_ENV=production
APP_KEY=..."
                        />
                        <InputError :message="form.errors.environment" />
                    </div>

                    <!-- Post-save Actions - Always visible -->
                    <div class="space-y-4 rounded-lg border bg-muted/30 p-4">
                        <p class="text-sm font-medium">After saving</p>

                        <div class="flex items-center justify-between">
                            <div class="space-y-1">
                                <Label for="clear_config_cache" class="cursor-pointer">
                                    Clear config cache
                                </Label>
                                <p class="text-sm text-muted-foreground">
                                    Run
                                    <code class="rounded bg-muted px-1.5 py-0.5 font-mono text-xs">
                                        php artisan config:cache
                                    </code>
                                    after updating environment variables.
                                </p>
                            </div>
                            <Switch
                                id="clear_config_cache"
                                v-model="clearConfigCache"
                            />
                        </div>

                        <div class="flex items-center justify-between">
                            <div class="space-y-1">
                                <Label for="restart_queue" class="cursor-pointer">
                                    Restart queue workers
                                </Label>
                                <p class="text-sm text-muted-foreground">
                                    Run
                                    <code class="rounded bg-muted px-1.5 py-0.5 font-mono text-xs">
                                        php artisan queue:restart
                                    </code>
                                    after updating environment variables.
                                </p>
                            </div>
                            <Switch
                                id="restart_queue"
                                v-model="restartQueue"
                            />
                        </div>
                    </div>

                    <!-- Action Buttons - Always visible when there are changes -->
                    <div
                        v-if="hasChanges"
                        class="flex items-center justify-end gap-2"
                    >
                        <Button
                            variant="outline"
                            :disabled="isSaving"
                            @click="resetForm"
                        >
                            <RotateCcw class="mr-2 size-4" />
                            Reset
                        </Button>
                        <Button
                            :disabled="isSaving"
                            @click="submitForm"
                        >
                            <Loader2
                                v-if="isSaving"
                                class="mr-2 size-4 animate-spin"
                            />
                            <Save v-else class="mr-2 size-4" />
                            {{ isSaving ? 'Saving...' : 'Save Changes' }}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </SiteLayout>
</template>
