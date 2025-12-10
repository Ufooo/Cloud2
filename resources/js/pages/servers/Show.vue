 <script setup lang="ts">
import { destroy } from '@/actions/Nip/Server/Http/Controllers/ServerController';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useConfirmation } from '@/composables/useConfirmation';
import ServerLayout from '@/layouts/ServerLayout.vue';
import type { Server } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Trash2 } from 'lucide-vue-next';

interface Props {
    server: Server;
}

const props = defineProps<Props>();

const { confirmInput } = useConfirmation();

async function handleDelete() {
    const confirmed = await confirmInput({
        title: 'Delete server',
        description: `Type "${props.server.name}" to confirm permanently deleting this server. This action cannot be undone.`,
        value: props.server.name,
    });

    if (confirmed) {
        router.delete(destroy.url(props.server));
    }
}
</script>

<template>
    <Head :title="server.name" />

    <ServerLayout :server="server">
        <!-- Server Information -->
        <Card>
            <CardHeader class="flex flex-row items-center justify-between">
                <CardTitle>Server Information</CardTitle>
                <Button
                    v-if="server.can?.delete"
                    variant="destructive"
                    size="sm"
                    @click="handleDelete"
                >
                    <Trash2 class="mr-2 size-4" />
                    Delete Server
                </Button>
            </CardHeader>
            <CardContent class="grid gap-4 md:grid-cols-2">
                <div class="space-y-1">
                    <p class="text-sm font-medium">Server Type</p>
                    <p class="text-sm text-muted-foreground">
                        {{ server.displayableType }}
                    </p>
                </div>

                <div class="space-y-1">
                    <p class="text-sm font-medium">IP Address</p>
                    <p class="text-sm text-muted-foreground">
                        {{ server.ipAddress || 'N/A' }}
                    </p>
                </div>

                <div class="space-y-1">
                    <p class="text-sm font-medium">Private IP Address</p>
                    <p class="text-sm text-muted-foreground">
                        {{ server.privateIpAddress || 'N/A' }}
                    </p>
                </div>

                <div class="space-y-1">
                    <p class="text-sm font-medium">SSH Port</p>
                    <p class="text-sm text-muted-foreground">
                        {{ server.sshPort }}
                    </p>
                </div>

                <div v-if="server.displayablePhpVersion" class="space-y-1">
                    <p class="text-sm font-medium">PHP Version</p>
                    <p class="text-sm text-muted-foreground">
                        {{ server.displayablePhpVersion }}
                    </p>
                </div>

                <div v-if="server.displayableDatabaseType" class="space-y-1">
                    <p class="text-sm font-medium">Database</p>
                    <p class="text-sm text-muted-foreground">
                        {{ server.displayableDatabaseType }}
                    </p>
                </div>

                <div v-if="server.ubuntuVersion" class="space-y-1">
                    <p class="text-sm font-medium">Ubuntu Version</p>
                    <p class="text-sm text-muted-foreground">
                        {{ server.ubuntuVersion }}
                    </p>
                </div>

                <div class="space-y-1">
                    <p class="text-sm font-medium">Timezone</p>
                    <p class="text-sm text-muted-foreground">
                        {{ server.timezone }}
                    </p>
                </div>

                <div v-if="server.notes" class="col-span-2 space-y-1">
                    <p class="text-sm font-medium">Notes</p>
                    <p class="text-sm text-muted-foreground">
                        {{ server.notes }}
                    </p>
                </div>
            </CardContent>
        </Card>
    </ServerLayout>
</template>
