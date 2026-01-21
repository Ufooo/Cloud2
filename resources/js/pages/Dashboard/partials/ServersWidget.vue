<script setup lang="ts">
import {
    refreshMetrics,
    show,
} from '@/actions/Nip/Server/Http/Controllers/ServerController';
import { Card } from '@/components/ui/card';
import { useDashboardUpdates } from '@/composables/useDashboardUpdates';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { Check, Copy, Loader2, Monitor } from 'lucide-vue-next';
import { reactive, ref } from 'vue';

interface ServerWidget {
    id: number;
    name: string;
    slug: string;
    ipAddress: string;
    status: string;
    statusLabel: string;
    isConnected: boolean;
    sitesCount: number;
    uptimeFormatted: string | null;
    loadAvgFormatted: string | null;
    cpuPercent: number;
    ramTotalBytes: number | null;
    ramUsedBytes: number | null;
    ramPercent: number;
    diskTotalBytes: number | null;
    diskUsedBytes: number | null;
    diskPercent: number;
    lastMetricsAt: string | null;
}

interface Props {
    servers: ServerWidget[];
}

defineProps<Props>();

const copiedId = ref<number | null>(null);
const refreshingIds = ref<Set<number>>(new Set());
const localServerData = reactive<Record<number, Partial<ServerWidget>>>({});

useDashboardUpdates((payload) => {
    localServerData[payload.serverId] = {
        status: payload.status,
        statusLabel: payload.statusLabel,
        isConnected: payload.isConnected,
        uptimeFormatted: payload.uptimeFormatted,
        loadAvgFormatted: payload.loadAvgFormatted,
        cpuPercent: payload.cpuPercent,
        ramTotalBytes: payload.ramTotalBytes,
        ramUsedBytes: payload.ramUsedBytes,
        ramPercent: payload.ramPercent,
        diskTotalBytes: payload.diskTotalBytes,
        diskUsedBytes: payload.diskUsedBytes,
        diskPercent: payload.diskPercent,
        lastMetricsAt: payload.lastMetricsAt,
    };
});

async function copyIp(server: ServerWidget) {
    await navigator.clipboard.writeText(server.ipAddress);
    copiedId.value = server.id;
    setTimeout(() => (copiedId.value = null), 2000);
}

function getBarColor(percent: number): string {
    if (percent >= 90) return 'bg-red-500';
    if (percent >= 70) return 'bg-orange-500';
    return 'bg-green-500';
}

function formatBytes(bytes: number | null): string {
    if (bytes === null || bytes === 0) return '0 GB';
    const gb = bytes / (1024 * 1024 * 1024);
    return `${gb.toFixed(1)} GB`;
}

function getServerData(server: ServerWidget): ServerWidget {
    return { ...server, ...localServerData[server.id] };
}

function isRefreshing(serverId: number): boolean {
    return refreshingIds.value.has(serverId);
}

async function handleRefresh(server: ServerWidget) {
    if (refreshingIds.value.has(server.id)) return;

    refreshingIds.value.add(server.id);

    try {
        const response = await fetch(refreshMetrics.url({ server: server.slug }), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN':
                    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
                        ?.content ?? '',
            },
        });

        const data = await response.json();

        localServerData[server.id] = {
            status: data.status,
            statusLabel: data.statusLabel,
            isConnected: data.isConnected,
            uptimeFormatted: data.uptimeFormatted,
            loadAvgFormatted: data.loadAvgFormatted,
            cpuPercent: data.cpuPercent,
            ramTotalBytes: data.ramTotalBytes,
            ramUsedBytes: data.ramUsedBytes,
            ramPercent: data.ramPercent,
            diskTotalBytes: data.diskTotalBytes,
            diskUsedBytes: data.diskUsedBytes,
            diskPercent: data.diskPercent,
            lastMetricsAt: data.lastMetricsAt,
        };
    } finally {
        refreshingIds.value.delete(server.id);
    }
}
</script>

<template>
    <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
        <Card
            v-for="server in servers"
            :key="server.id"
            class="bg-white p-2 dark:bg-card"
        >
            <!-- Header -->
            <div class="mb-2 flex items-center justify-between">
                <a
                    :href="show.url({ server: server.slug })"
                    class="font-semibold hover:text-primary"
                >
                    {{ server.name }}
                </a>
                <div class="flex items-center gap-1.5 text-xs">
                    <span
                        v-if="isRefreshing(server.id)"
                        class="font-semibold text-blue-600"
                    >
                        Connecting
                    </span>
                    <span
                        v-else
                        class="font-semibold"
                        :class="getServerData(server).isConnected ? 'text-green-600' : 'text-red-600'"
                    >
                        {{ getServerData(server).isConnected ? 'Connected' : 'Disconnected' }}
                    </span>
                    <button
                        class="hover:text-primary disabled:opacity-50"
                        :disabled="isRefreshing(server.id)"
                        @click="handleRefresh(server)"
                    >
                        <Loader2
                            v-if="isRefreshing(server.id)"
                            class="size-4 animate-spin text-blue-600"
                        />
                        <Monitor v-else class="size-4 text-muted-foreground" />
                    </button>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="flex gap-6 overflow-hidden text-xs">
                <!-- Left Column -->
                <div class="shrink-0 space-y-1.5">
                    <div class="flex items-center gap-4">
                        <span class="w-16 shrink-0 text-muted-foreground">IP</span>
                        <button
                            class="flex items-center gap-1 font-mono hover:text-primary"
                            @click="copyIp(server)"
                        >
                            {{ server.ipAddress }}
                            <Copy v-if="copiedId !== server.id" class="size-2.5" />
                            <Check v-else class="size-2.5 text-green-500" />
                        </button>
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="w-16 shrink-0 text-muted-foreground">Uptime</span>
                        <span>{{ getServerData(server).uptimeFormatted ?? '-' }}</span>
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="w-16 shrink-0 text-muted-foreground">Load Avg</span>
                        <span class="font-mono">{{ getServerData(server).loadAvgFormatted ?? '-' }}</span>
                    </div>
                </div>

                <!-- Right Column - Progress Bars -->
                <TooltipProvider>
                    <div class="min-w-0 flex-1 space-y-1.5">
                        <div class="flex items-center">
                            <span class="w-8 text-muted-foreground">Disk</span>
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <div class="h-4 flex-1 cursor-default overflow-hidden bg-gray-100 dark:bg-gray-700 mx-2">
                                        <div
                                            class="h-full transition-all"
                                            :class="getBarColor(getServerData(server).diskPercent)"
                                            :style="{ width: `${getServerData(server).diskPercent}%` }"
                                        />
                                    </div>
                                </TooltipTrigger>
                                <TooltipContent>
                                    {{ formatBytes(getServerData(server).diskUsedBytes) }} / {{ formatBytes(getServerData(server).diskTotalBytes) }}
                                </TooltipContent>
                            </Tooltip>
                        </div>
                        <div class="flex items-center">
                            <span class="w-8 text-muted-foreground">CPU</span>
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <div class="h-4 flex-1 cursor-default overflow-hidden bg-gray-100 dark:bg-gray-700 mx-2">
                                        <div
                                            class="h-full transition-all"
                                            :class="getBarColor(getServerData(server).cpuPercent)"
                                            :style="{ width: `${getServerData(server).cpuPercent}%` }"
                                        />
                                    </div>
                                </TooltipTrigger>
                                <TooltipContent>
                                    {{ getServerData(server).cpuPercent }}%
                                </TooltipContent>
                            </Tooltip>
                        </div>
                        <div class="flex items-center">
                            <span class="w-8 text-muted-foreground">RAM</span>
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <div class="h-4 flex-1 cursor-default overflow-hidden bg-gray-100 dark:bg-gray-700 mx-2">
                                        <div
                                            class="h-full transition-all"
                                            :class="getBarColor(getServerData(server).ramPercent)"
                                            :style="{ width: `${getServerData(server).ramPercent}%` }"
                                        />
                                    </div>
                                </TooltipTrigger>
                                <TooltipContent>
                                    {{ formatBytes(getServerData(server).ramUsedBytes) }} / {{ formatBytes(getServerData(server).ramTotalBytes) }}
                                </TooltipContent>
                            </Tooltip>
                        </div>
                        <!-- Scale -->
                        <div class="flex">
                            <span class="w-8"></span>
                            <div class="flex flex-1 justify-between text-xxs text-muted-foreground/60 ml-1">
                                <div class="flex flex-col items-center">
                                    <div class="h-1 w-px bg-muted-foreground/40"></div>
                                    <span>%</span>
                                </div>
                                <div class="flex flex-col items-center">
                                    <div class="h-1 w-px bg-muted-foreground/40"></div>
                                    <span>50</span>
                                </div>
                                <div class="flex flex-col items-center">
                                    <div class="h-1 w-px bg-muted-foreground/40"></div>
                                    <span>100</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </TooltipProvider>
            </div>
        </Card>
    </div>
</template>
