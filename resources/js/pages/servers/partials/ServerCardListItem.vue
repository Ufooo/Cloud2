<script setup lang="ts">
import { show } from '@/actions/Nip/Server/Http/Controllers/ServerController';
import Avatar from '@/components/shared/Avatar.vue';
import DatabaseVersionBadge from '@/components/DatabaseVersionBadge.vue';
import PhpVersionBadge from '@/components/PhpVersionBadge.vue';
import { Button } from '@/components/ui/button';
import ServerStatusBadge from './ServerStatusBadge.vue';
import { ServerStatus, type Server } from '@/types';
import { Link } from '@inertiajs/vue3';
import { Globe, MoreHorizontal, Network } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    server: Server;
}

const props = defineProps<Props>();

const isConnected = computed(() => props.server.status === ServerStatus.Connected);

const statsItems = computed(() => {
    const items: string[] = [];
    const s = props.server;

    if (s.sitesCount !== undefined && s.sitesCount > 0) {
        items.push(`${s.sitesCount} site${s.sitesCount !== 1 ? 's' : ''}`);
    }
    if (s.backgroundProcessesCount !== undefined && s.backgroundProcessesCount > 0) {
        items.push(`${s.backgroundProcessesCount} daemon${s.backgroundProcessesCount !== 1 ? 's' : ''}`);
    }
    if (s.scheduledJobsCount !== undefined && s.scheduledJobsCount > 0) {
        items.push(`${s.scheduledJobsCount} job${s.scheduledJobsCount !== 1 ? 's' : ''}`);
    }

    return items;
});
</script>

<template>
    <Link
        :href="show.url(server)"
        class="flex items-center gap-4 px-4 py-4 transition-colors hover:bg-muted/50"
    >
        <!-- Avatar -->
        <Avatar :name="server.name" :color="server.avatarColor" size="md" />

        <!-- Main Info -->
        <div class="flex-1 min-w-0">
            <h4 class="font-semibold text-foreground truncate mb-1">{{ server.name }}</h4>
            <div class="flex items-center gap-4 text-sm">
                <span v-if="server.ipAddress" class="text-muted-foreground">
                    <Network class="w-3.5 h-3.5 inline mr-1" />
                    {{ server.ipAddress }}
                </span>
                <span class="text-muted-foreground">
                    <Globe class="w-3.5 h-3.5 inline mr-1" />
                    {{ server.displayableType }}
                </span>
                <PhpVersionBadge v-if="server.phpVersionLabel" :version="server.phpVersionLabel" />
                <DatabaseVersionBadge v-if="server.displayableDatabaseType && isConnected" :type="server.displayableDatabaseType" />
            </div>
        </div>

        <!-- Server Stats -->
        <div v-if="isConnected && statsItems.length > 0" class="flex-shrink-0 text-sm text-muted-foreground">
            {{ statsItems.join(' · ') }}
        </div>

        <!-- Right side - Status -->
        <div class="flex-shrink-0 w-28 text-right">
            <div class="text-xs text-muted-foreground mb-1">Status</div>
            <template v-if="isConnected">
                <div class="text-sm text-green-600 dark:text-green-400 font-medium">Connected</div>
            </template>
            <ServerStatusBadge v-else :status="server.status" />
        </div>

        <!-- Actions - TODO: Implement actions menu -->
        <Button variant="ghost" size="icon" class="h-9 w-9 text-muted-foreground hover:text-foreground hover:bg-muted flex-shrink-0" @click.prevent>
            <MoreHorizontal class="w-4 h-4" />
        </Button>
    </Link>
</template>
