<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Database, MoreHorizontal, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';

type BadgeVariant = 'default' | 'secondary' | 'destructive' | 'outline' | null | undefined;

interface DatabaseItem {
    id: string;
    serverId: number;
    serverName?: string;
    serverSlug?: string;
    siteId?: string;
    siteDomain?: string;
    siteSlug?: string;
    name: string;
    size?: number;
    displayableSize?: string;
    status?: string;
    displayableStatus?: string;
    statusBadgeVariant?: BadgeVariant;
    createdAt?: string;
    createdAtHuman?: string;
    can?: {
        delete: boolean;
    };
}

interface Props {
    database: DatabaseItem;
    showServer?: boolean;
    showSite?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    showServer: true,
    showSite: true,
});

const emit = defineEmits<{
    delete: [database: DatabaseItem];
}>();

const canDelete = computed(() => props.database.can?.delete ?? true);
const showStatusBadge = computed(() =>
    props.database.status && props.database.status !== 'installed',
);
</script>

<template>
    <div class="flex items-center gap-4 px-4 py-3 transition-colors hover:bg-accent/50">
        <div class="flex size-10 items-center justify-center rounded-lg bg-muted">
            <Database class="size-5 text-muted-foreground" />
        </div>

        <!-- Content -->
        <div class="flex min-w-0 flex-1 flex-col gap-y-0.5">
            <div class="flex items-center gap-2">
                <span class="truncate font-medium text-foreground">
                    {{ database.name }}
                </span>
            </div>
            <span class="flex items-center gap-x-1 text-xs text-muted-foreground">
                <template v-if="database.serverName && showServer">
                    <span>{{ database.serverName }}</span>
                </template>
                <template v-if="database.siteDomain && showSite">
                    <span v-if="database.serverName && showServer">·</span>
                    <span>{{ database.siteDomain }}</span>
                </template>
            </span>
        </div>

        <!-- Right side -->
        <div class="flex items-center gap-4">
            <Badge v-if="showStatusBadge" :variant="database.statusBadgeVariant">
                {{ database.displayableStatus }}
            </Badge>
            <Badge v-else-if="database.displayableSize" variant="secondary">
                {{ database.displayableSize }}
            </Badge>

            <DropdownMenu v-if="canDelete">
                <DropdownMenuTrigger as-child>
                    <Button variant="ghost" size="icon" class="size-8">
                        <MoreHorizontal class="size-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem
                        class="text-destructive focus:text-destructive"
                        @click="emit('delete', props.database)"
                    >
                        <Trash2 class="mr-2 size-4" />
                        Delete
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </div>
</template>
