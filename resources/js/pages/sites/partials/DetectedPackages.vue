<script setup lang="ts">
import { enableLaravelScheduler } from '@/actions/Nip/Scheduler/Http/Controllers/SiteScheduledJobController';
import {
    detectPackages,
    disableSSR,
    enableSSR,
} from '@/actions/Nip/Site/Http/Controllers/SiteController';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import type { DetectedPackageData, Site } from '@/types';
import { router } from '@inertiajs/vue3';
import {
    Box,
    CheckCircle,
    Circle,
    Loader2,
    Package,
    RefreshCw,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Props {
    site: Site;
}

const props = defineProps<Props>();

const isDetecting = ref(false);
const isEnablingScheduler = ref(false);
const processingPackage = ref<string | null>(null);
const packageDetails = ref<DetectedPackageData[]>(
    props.site.packageDetails ?? [],
);
const localPackages = ref<Record<string, boolean>>(props.site.packages ?? {});

const hasDetected = computed(() => packageDetails.value.length > 0);

// Features that can be enabled (not composer packages)
interface EnableableFeature {
    key: string;
    label: string;
    description: string;
    enableLabel: string;
    isEnabled: boolean;
    isEnabling: boolean;
    onEnable: () => void;
}

const enableableFeatures = computed<EnableableFeature[]>(() => {
    const features: EnableableFeature[] = [];

    // Scheduler - enabled if has scheduled jobs
    features.push({
        key: 'scheduler',
        label: 'Scheduler',
        description: 'Run scheduled tasks with cron',
        enableLabel: 'Start Scheduler',
        isEnabled: localPackages.value['scheduler'] === true,
        isEnabling: isEnablingScheduler.value,
        onEnable: handleEnableScheduler,
    });

    return features;
});

async function handleDetectPackages() {
    isDetecting.value = true;

    try {
        const response = await fetch(detectPackages.url(props.site), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN':
                    document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute('content') ?? '',
            },
        });

        if (response.ok) {
            const data = await response.json();
            packageDetails.value = data.packageDetails;
        }
    } finally {
        isDetecting.value = false;
    }
}

function handleEnableScheduler() {
    isEnablingScheduler.value = true;
    localPackages.value['scheduler'] = true;
    router.post(
        enableLaravelScheduler.url(props.site),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                isEnablingScheduler.value = false;
            },
        },
    );
}

function handlePackageAction(pkg: DetectedPackageData) {
    if (!pkg.hasEnableAction) {
        return;
    }

    processingPackage.value = pkg.value;

    const url = pkg.isEnabled
        ? disableSSR.url(props.site)
        : enableSSR.url(props.site);

    router.post(
        url,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                processingPackage.value = null;
            },
        },
    );
}

function getPackageActionLabel(pkg: DetectedPackageData): string {
    return pkg.isEnabled
        ? (pkg.disableActionLabel ?? 'Disable')
        : (pkg.enableActionLabel ?? 'Enable');
}
</script>

<template>
    <Card>
        <CardHeader>
            <div class="flex items-center justify-between">
                <div>
                    <CardTitle class="flex items-center gap-2">
                        <Package class="size-5" />
                        Laravel packages
                    </CardTitle>
                    <CardDescription>
                        <template v-if="hasDetected">
                            {{ packageDetails.length }} package{{
                                packageDetails.length === 1 ? '' : 's'
                            }}
                            detected
                        </template>
                        <template v-else>
                            Detect installed Laravel packages
                        </template>
                    </CardDescription>
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    @click="handleDetectPackages"
                    :disabled="isDetecting"
                >
                    <Loader2
                        v-if="isDetecting"
                        class="mr-2 size-4 animate-spin"
                    />
                    <RefreshCw v-else class="mr-2 size-4" />
                    {{ hasDetected ? 'Refresh' : 'Detect' }}
                </Button>
            </div>
        </CardHeader>
        <CardContent>
            <!-- Loading state -->
            <div v-if="isDetecting" class="space-y-3">
                <div v-for="i in 4" :key="i" class="flex items-center gap-3">
                    <Skeleton class="size-6 rounded-full" />
                    <div class="space-y-1">
                        <Skeleton class="h-4 w-24" />
                        <Skeleton class="h-3 w-32" />
                    </div>
                </div>
            </div>

            <div v-else-if="hasDetected" class="space-y-3">
                <!-- Detected packages -->
                <div
                    v-for="pkg in packageDetails"
                    :key="pkg.value"
                    class="flex items-center justify-between rounded-lg border p-3"
                >
                    <div class="flex items-center gap-3">
                        <CheckCircle class="size-5 text-green-500" />
                        <div>
                            <p class="text-sm font-medium">{{ pkg.label }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ pkg.description }}
                            </p>
                        </div>
                    </div>
                    <Button
                        v-if="pkg.hasEnableAction"
                        variant="outline"
                        size="sm"
                        :disabled="processingPackage === pkg.value"
                        @click="handlePackageAction(pkg)"
                    >
                        <Loader2
                            v-if="processingPackage === pkg.value"
                            class="mr-2 size-4 animate-spin"
                        />
                        {{ getPackageActionLabel(pkg) }}
                    </Button>
                </div>

                <!-- Enableable features -->
                <div
                    v-for="feature in enableableFeatures"
                    :key="feature.key"
                    class="flex items-center justify-between rounded-lg border p-3"
                >
                    <div class="flex items-center gap-3">
                        <CheckCircle
                            v-if="feature.isEnabled"
                            class="size-5 text-green-500"
                        />
                        <Circle
                            v-else
                            class="size-5 text-muted-foreground/50"
                        />
                        <div>
                            <p class="text-sm font-medium">
                                {{ feature.label }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ feature.description }}
                            </p>
                        </div>
                    </div>
                    <Button
                        v-if="!feature.isEnabled"
                        variant="outline"
                        size="sm"
                        :disabled="feature.isEnabling"
                        @click="feature.onEnable"
                    >
                        <Loader2
                            v-if="feature.isEnabling"
                            class="mr-2 size-4 animate-spin"
                        />
                        {{ feature.enableLabel }}
                    </Button>
                </div>
            </div>

            <!-- No packages detected yet -->
            <div
                v-else
                class="flex flex-col items-center justify-center py-8 text-center"
            >
                <Box class="size-12 text-muted-foreground/50" />
                <p class="mt-4 text-sm text-muted-foreground">
                    Click "Detect" to scan for installed Laravel packages
                </p>
            </div>
        </CardContent>
    </Card>
</template>
