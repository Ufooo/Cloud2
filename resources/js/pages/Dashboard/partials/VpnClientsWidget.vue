<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { Network, Wifi, WifiOff } from 'lucide-vue-next';
import { computed } from 'vue';

interface VpnClient {
    id: number;
    commonName: string;
    realAddress: string;
    virtualAddress: string;
    bytesReceived: number;
    bytesSent: number;
    bytesReceivedFormatted: string;
    bytesSentFormatted: string;
    connectedSince: string;
    connectedSinceHuman: string;
    isOnline: boolean;
    lastSeenAt: string;
    lastSeenAtHuman: string;
}

interface Props {
    clients: VpnClient[];
}

const props = defineProps<Props>();

const onlineClients = computed(() => props.clients.filter((c) => c.isOnline));
const offlineClients = computed(() => props.clients.filter((c) => !c.isOnline));
</script>

<template>
    <Card class="bg-white dark:bg-card">
        <div class="border-b px-3 py-2.5">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <Network class="size-4 text-muted-foreground" />
                    <span class="text-sm font-semibold">VPN Clients</span>
                </div>
                <div class="flex items-center gap-2">
                    <Badge
                        variant="secondary"
                        class="bg-green-100 text-xs text-green-700 dark:bg-green-900/30 dark:text-green-400"
                    >
                        <Wifi class="mr-1 size-3" />
                        {{ onlineClients.length }}
                    </Badge>
                    <Badge
                        v-if="offlineClients.length > 0"
                        variant="secondary"
                        class="text-xs"
                    >
                        <WifiOff class="mr-1 size-3" />
                        {{ offlineClients.length }}
                    </Badge>
                </div>
            </div>
        </div>

        <!-- Client list -->
        <div v-if="clients.length > 0">
            <div class="divide-y">
                <div
                    v-for="client in clients"
                    :key="client.id"
                    class="flex items-center gap-2 px-3 py-2 hover:bg-muted/30"
                >
                    <div
                        class="size-2 shrink-0 rounded-full"
                        :class="
                            client.isOnline ? 'bg-green-500' : 'bg-gray-300'
                        "
                    />
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-medium">
                            {{ client.commonName }}
                        </div>
                        <div
                            class="flex items-center gap-2 text-xs text-muted-foreground"
                        >
                            <span class="font-mono">{{
                                client.virtualAddress
                            }}</span>
                            <span>·</span>
                            <span class="text-green-600">
                                ↓{{ client.bytesReceivedFormatted }}
                            </span>
                            <span class="text-blue-600">
                                ↑{{ client.bytesSentFormatted }}
                            </span>
                        </div>
                    </div>
                    <div class="shrink-0 text-right text-xs text-muted-foreground">
                        <template v-if="client.isOnline">
                            {{ client.connectedSinceHuman }}
                        </template>
                        <template v-else-if="client.lastSeenAtHuman">
                            {{ client.lastSeenAtHuman }}
                        </template>
                    </div>
                </div>
            </div>
        </div>
        <div v-else class="flex items-center justify-center p-4">
            <span class="text-xs text-muted-foreground">No VPN clients</span>
        </div>
    </Card>
</template>
