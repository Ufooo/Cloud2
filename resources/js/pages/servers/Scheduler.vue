<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import ServerLayout from '@/layouts/ServerLayout.vue';
import type { Server } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Clock } from 'lucide-vue-next';

interface Props {
    server: Server;
}

defineProps<Props>();
</script>

<template>
    <Head :title="`Scheduler - ${server.name}`" />

    <ServerLayout :server="server">
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Clock class="size-5" />
                    Scheduler
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div class="py-8 text-center text-muted-foreground">
                    <Clock class="mx-auto mb-4 size-12 opacity-50" />
                    <p class="text-lg font-medium">
                        Cron Jobs & Scheduled Tasks
                    </p>
                    <p class="mt-2 text-sm">
                        Manage scheduled tasks and cron jobs running on this
                        server.
                    </p>
                </div>
            </CardContent>
        </Card>
    </ServerLayout>
</template>
