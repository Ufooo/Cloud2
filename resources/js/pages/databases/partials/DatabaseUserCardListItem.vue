<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Database, MoreHorizontal, Pencil, Server, Trash2, User } from 'lucide-vue-next';
import { computed } from 'vue';

type BadgeVariant =
    | 'default'
    | 'secondary'
    | 'destructive'
    | 'outline'
    | null
    | undefined;

interface DatabaseUserItem {
    id: string;
    serverId: number;
    serverName?: string;
    serverSlug?: string;
    username: string;
    readonly?: boolean;
    status?: string;
    displayableStatus?: string;
    statusBadgeVariant?: BadgeVariant;
    databaseCount?: number;
    databaseIds?: string[];
    createdAt?: string;
    createdAtHuman?: string;
    can?: {
        update: boolean;
        delete: boolean;
    };
}

interface Props {
    user: DatabaseUserItem;
    showServer?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    showServer: true,
});

const emit = defineEmits<{
    edit: [user: DatabaseUserItem];
    delete: [user: DatabaseUserItem];
}>();

const canUpdate = computed(() => props.user.can?.update ?? true);
const canDelete = computed(() => props.user.can?.delete ?? true);
const showStatusBadge = computed(
    () => props.user.status && props.user.status !== 'installed',
);
const showDropdown = computed(() => canUpdate.value || canDelete.value);
</script>

<template>
    <div
        class="flex items-center gap-4 px-4 py-4 transition-colors hover:bg-muted/50"
    >
        <div
            class="flex size-10 items-center justify-center rounded-lg bg-muted"
        >
            <User class="size-5 text-muted-foreground" />
        </div>

        <!-- Content -->
        <div class="flex min-w-0 flex-1 flex-col gap-y-0.5">
            <div class="flex items-center gap-2">
                <span class="truncate font-medium text-foreground">
                    {{ user.username }}
                </span>
                <Badge
                    v-if="user.readonly"
                    variant="outline"
                    class="px-1.5 py-0 text-xs font-normal"
                >
                    Read only
                </Badge>
            </div>
            <div class="flex items-center gap-x-2">
                <span
                    v-if="user.serverName && showServer"
                    class="flex items-center gap-x-1.5 text-xs text-muted-foreground"
                >
                    <Server class="size-3.5" />
                    <span>{{ user.serverName }}</span>
                </span>
                <Badge
                    v-if="user.databaseCount !== undefined"
                    variant="outline"
                    class="gap-1 px-1.5 py-0 text-xs font-normal"
                >
                    <Database class="size-3" />
                    {{ user.databaseCount }}
                </Badge>
            </div>
        </div>

        <!-- Right side -->
        <div class="flex items-center gap-4">
            <Badge v-if="showStatusBadge" :variant="user.statusBadgeVariant">
                {{ user.displayableStatus }}
            </Badge>

            <DropdownMenu v-if="showDropdown">
                <DropdownMenuTrigger as-child>
                    <Button variant="ghost" size="icon" class="size-8">
                        <MoreHorizontal class="size-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem
                        v-if="canUpdate"
                        @click="emit('edit', props.user)"
                    >
                        <Pencil class="mr-2 size-4" />
                        Edit
                    </DropdownMenuItem>
                    <DropdownMenuSeparator v-if="canUpdate && canDelete" />
                    <DropdownMenuItem
                        v-if="canDelete"
                        class="text-destructive focus:text-destructive"
                        @click="emit('delete', props.user)"
                    >
                        <Trash2 class="mr-2 size-4" />
                        Delete
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </div>
</template>
