<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import ServerLayout from '@/layouts/ServerLayout.vue';
import type { Server } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Activity } from 'lucide-vue-next';

interface Props {
    server: Server;
}

defineProps<Props>();
</script>

<template>
    <Head :title="`Background Processes - ${server.name}`" />

    <ServerLayout :server="server">
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Activity class="size-5" />
                    Background Processes
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div class="py-8 text-center text-muted-foreground">
                    <Activity class="mx-auto mb-4 size-12 opacity-50" />
                    <p class="text-lg font-medium">Background Processes</p>
                    <p class="mt-2 text-sm">
                        Manage daemons and background processes like queue
                        workers.
                    </p>
                </div>
            </CardContent>
        </Card>
    </ServerLayout>
</template>
