<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import ServerLayout from '@/layouts/ServerLayout.vue';
import type { Server } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Code } from 'lucide-vue-next';

interface Props {
    server: Server;
}

defineProps<Props>();
</script>

<template>
    <Head :title="`PHP - ${server.name}`" />

    <ServerLayout :server="server">
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Code class="size-5" />
                    PHP Configuration
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div class="py-8 text-center text-muted-foreground">
                    <Code class="mx-auto mb-4 size-12 opacity-50" />
                    <p class="text-lg font-medium">PHP Management</p>
                    <p class="mt-2 text-sm">
                        Configure PHP versions, extensions, and settings for
                        this server.
                    </p>
                </div>
            </CardContent>
        </Card>
    </ServerLayout>
</template>
