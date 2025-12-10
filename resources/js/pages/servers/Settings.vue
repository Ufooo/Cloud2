<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import ServerLayout from '@/layouts/ServerLayout.vue';
import type { Server } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Settings as SettingsIcon } from 'lucide-vue-next';

interface Props {
    server: Server;
}

defineProps<Props>();
</script>

<template>
    <Head :title="`Settings - ${server.name}`" />

    <ServerLayout :server="server">
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <SettingsIcon class="size-5" />
                    Server Settings
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div class="py-8 text-center text-muted-foreground">
                    <SettingsIcon class="mx-auto mb-4 size-12 opacity-50" />
                    <p class="text-lg font-medium">Server Settings</p>
                    <p class="mt-2 text-sm">
                        Configure server name, timezone, and other general
                        settings.
                    </p>
                </div>
            </CardContent>
        </Card>
    </ServerLayout>
</template>
