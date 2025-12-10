<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import ServerLayout from '@/layouts/ServerLayout.vue';
import type { Server } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Users } from 'lucide-vue-next';

interface Props {
    server: Server;
}

defineProps<Props>();
</script>

<template>
    <Head :title="`Unix Users - ${server.name}`" />

    <ServerLayout :server="server">
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Users class="size-5" />
                    Unix Users
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div class="py-8 text-center text-muted-foreground">
                    <Users class="mx-auto mb-4 size-12 opacity-50" />
                    <p class="text-lg font-medium">Unix Users Management</p>
                    <p class="mt-2 text-sm">
                        Manage system users and their permissions on this
                        server.
                    </p>
                </div>
            </CardContent>
        </Card>
    </ServerLayout>
</template>
