<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import ServerLayout from '@/layouts/ServerLayout.vue';
import type { Server } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Key } from 'lucide-vue-next';

interface Props {
    server: Server;
}

defineProps<Props>();
</script>

<template>
    <Head :title="`SSH Keys - ${server.name}`" />

    <ServerLayout :server="server">
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Key class="size-5" />
                    SSH Keys
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div class="py-8 text-center text-muted-foreground">
                    <Key class="mx-auto mb-4 size-12 opacity-50" />
                    <p class="text-lg font-medium">SSH Key Management</p>
                    <p class="mt-2 text-sm">
                        Manage SSH keys for secure access to this server.
                    </p>
                </div>
            </CardContent>
        </Card>
    </ServerLayout>
</template>
