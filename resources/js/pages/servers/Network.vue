<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import ServerLayout from '@/layouts/ServerLayout.vue';
import type { Server } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Globe } from 'lucide-vue-next';

interface Props {
    server: Server;
}

defineProps<Props>();
</script>

<template>
    <Head :title="`Network - ${server.name}`" />

    <ServerLayout :server="server">
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Globe class="size-5" />
                    Network
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div class="py-8 text-center text-muted-foreground">
                    <Globe class="mx-auto mb-4 size-12 opacity-50" />
                    <p class="text-lg font-medium">Network Configuration</p>
                    <p class="mt-2 text-sm">
                        Configure firewall rules, ports, and network settings.
                    </p>
                </div>
            </CardContent>
        </Card>
    </ServerLayout>
</template>
