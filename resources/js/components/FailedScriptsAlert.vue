<script setup lang="ts">
import {
    dismiss,
    failed,
    failedForServer,
    failedForSite,
} from '@/actions/Nip/Server/Http/Controllers/ProvisionScriptController';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import type { ProvisionScriptData, Server, Site } from '@/types';
import { router } from '@inertiajs/vue3';
import { AlertCircle, ChevronRight, X } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

interface Props {
    server?: Server;
    site?: Site;
    resourceTypes?: string[];
    title?: string;
    showServerName?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    title: 'Failed Operations',
    showServerName: false,
});

const emit = defineEmits<{
    (e: 'scriptClick', script: ProvisionScriptData): void;
}>();

const scripts = ref<ProvisionScriptData[]>([]);
const loading = ref(true);
const dismissed = ref<number[]>([]);

async function fetchFailedScripts() {
    try {
        const queryParams: Record<string, string> = {};
        if (props.resourceTypes?.length) {
            queryParams.types = props.resourceTypes.join(',');
        }

        let url: string;
        if (props.site) {
            url = failedForSite.url(props.site, { query: queryParams });
        } else if (props.server) {
            url = failedForServer.url(props.server, { query: queryParams });
        } else {
            url = failed.url({ query: queryParams });
        }

        const response = await fetch(url);
        const data = await response.json();
        scripts.value = data.data || data;
    } catch (error) {
        console.error('Failed to fetch failed scripts:', error);
    } finally {
        loading.value = false;
    }
}

watch(
    () => props.resourceTypes,
    () => {
        loading.value = true;
        fetchFailedScripts();
    },
);

function dismissScript(script: ProvisionScriptData, event: Event) {
    event.stopPropagation();
    dismissed.value.push(script.id);
    router.post(dismiss.url(script), {}, { preserveScroll: true });
}

function handleScriptClick(script: ProvisionScriptData) {
    emit('scriptClick', script);
}

const visibleScripts = computed(() =>
    scripts.value.filter((s) => !dismissed.value.includes(s.id)),
);

onMounted(() => {
    fetchFailedScripts();
});
</script>

<template>
    <div v-if="!loading && visibleScripts.length > 0" class="space-y-2">
        <Alert
            class="border-red-500/50 bg-red-50 text-red-800 dark:bg-red-950/50 dark:text-red-200"
        >
            <AlertCircle class="size-4 text-red-600 dark:text-red-400" />
            <AlertTitle class="text-red-800 dark:text-red-200">
                {{ title }}
            </AlertTitle>
            <AlertDescription>
                <div class="mt-2 space-y-1">
                    <button
                        v-for="script in visibleScripts"
                        :key="script.id"
                        class="flex w-full items-center justify-between rounded-md p-2 text-left transition-colors hover:bg-red-100 dark:hover:bg-red-900/50"
                        @click="handleScriptClick(script)"
                    >
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="font-medium">
                                    {{ script.displayableName }}
                                </span>
                                <span
                                    v-if="showServerName && script.serverName"
                                    class="text-xs text-red-600/70 dark:text-red-400/70"
                                >
                                    on {{ script.serverName }}
                                </span>
                                <span class="text-xs text-red-600/70 dark:text-red-400/70">
                                    {{ script.createdAt ? new Date(script.createdAt).toLocaleString() : '' }}
                                </span>
                            </div>
                            <p
                                v-if="script.errorMessage"
                                class="mt-0.5 truncate text-sm text-red-600/80 dark:text-red-300/80"
                            >
                                {{ script.errorMessage }}
                            </p>
                        </div>
                        <div class="ml-2 flex items-center gap-1">
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-6 hover:bg-red-200 dark:hover:bg-red-800"
                                title="Dismiss"
                                @click="dismissScript(script, $event)"
                            >
                                <X class="size-3.5" />
                            </Button>
                            <ChevronRight class="size-4 text-red-500/50" />
                        </div>
                    </button>
                </div>
            </AlertDescription>
        </Alert>
    </div>
</template>
