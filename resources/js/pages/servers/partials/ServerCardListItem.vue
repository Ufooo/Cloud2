<script setup lang="ts">
import { show } from '@/actions/Nip/Server/Http/Controllers/ServerController';
import Avatar from '@/components/shared/Avatar.vue';
import type { Server } from '@/types';
import { Link } from '@inertiajs/vue3';
import ServerStatusBadge from './ServerStatusBadge.vue';

interface Props {
    server: Server;
}

defineProps<Props>();
</script>

<template>
    <Link
        :href="show.url(server)"
        class="flex items-center gap-4 px-4 py-3 transition-colors hover:bg-accent/50"
    >
        <Avatar :name="server.name" :color="server.avatarColor" size="sm" />

        <!-- Content -->
        <div class="flex min-w-0 flex-1 cursor-pointer flex-col gap-y-0.5">
            <div class="flex items-center gap-2">
                <span class="truncate font-medium text-foreground">{{
                    server.name
                }}</span>
                <ServerStatusBadge
                    v-if="server.status !== 'connected'"
                    :status="server.status"
                    class="h-5 sm:hidden"
                    icon-only
                />
            </div>
            <span
                class="flex items-center gap-x-1 text-xs text-muted-foreground"
            >
                <template v-if="server.ipAddress">
                    <span>{{ server.ipAddress }}</span>
                    <span>·</span>
                </template>
                <span>{{ server.displayableType }}</span>
                <template v-if="server.displayablePhpVersion">
                    <span>·</span>
                    <span>{{ server.displayablePhpVersion }}</span>
                </template>
                <template v-if="server.displayableDatabaseType">
                    <span>·</span>
                    <span>{{ server.displayableDatabaseType }}</span>
                </template>
            </span>
        </div>

        <!-- Right side -->
        <ServerStatusBadge
            v-if="server.status !== 'connected'"
            :status="server.status"
            class="hidden shrink-0 sm:inline-flex"
        />
    </Link>
</template>
