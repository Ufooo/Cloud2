<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { MoreHorizontal, Pencil, Trash2, User } from 'lucide-vue-next';

interface DatabaseUserItem {
    id: string;
    serverId: number;
    serverName?: string;
    username: string;
    readonly?: boolean;
    databaseCount?: number;
    databaseIds?: string[];
    createdAt?: string;
    createdAtHuman?: string;
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
</script>

<template>
    <div class="flex items-center gap-4 px-4 py-3 transition-colors hover:bg-accent/50">
        <div class="flex size-10 items-center justify-center rounded-lg bg-muted">
            <User class="size-5 text-muted-foreground" />
        </div>

        <!-- Content -->
        <div class="flex min-w-0 flex-1 flex-col gap-y-0.5">
            <div class="flex items-center gap-2">
                <span class="truncate font-medium text-foreground">
                    {{ user.username }}
                </span>
                <span v-if="user.readonly" class="text-xs text-muted-foreground">
                    Read only
                </span>
            </div>
            <span class="flex items-center gap-x-1 text-xs text-muted-foreground">
                <template v-if="user.serverName && showServer">
                    <span>{{ user.serverName }}</span>
                    <span v-if="user.databaseCount !== undefined">·</span>
                </template>
                <span v-if="user.databaseCount !== undefined">
                    {{ user.databaseCount }} database{{ user.databaseCount !== 1 ? 's' : '' }}
                </span>
            </span>
        </div>

        <!-- Right side -->
        <div class="flex items-center gap-4">
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button variant="ghost" size="icon" class="size-8">
                        <MoreHorizontal class="size-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem @click="emit('edit', props.user)">
                        <Pencil class="mr-2 size-4" />
                        Edit
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
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
