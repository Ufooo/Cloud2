<script setup lang="ts">
import { Check, Trash2 } from 'lucide-vue-next';
import type { Notification } from './NotificationsPanel.vue';

defineProps<{
    notification: Notification;
}>();

const emit = defineEmits<{
    (e: 'markAsRead', id: number): void;
    (e: 'delete', id: number): void;
}>();

function getTypeColor(type: Notification['type']): string {
    const colors = {
        success: 'bg-primary',
        error: 'bg-red-500',
        warning: 'bg-amber-500',
    };
    return colors[type];
}
</script>

<template>
    <div
        class="group/item relative flex cursor-pointer items-start gap-2 rounded-xl border border-border bg-white p-3 transition-all hover:border-primary/50 hover:shadow-sm dark:bg-card"
    >
        <div
            :class="[
                getTypeColor(notification.type),
                'mt-1.5 h-2 w-2 flex-shrink-0 rounded-full',
            ]"
        />
        <div class="min-w-0 flex-1 pr-0 transition-all group-hover/item:pr-14">
            <p class="truncate text-xs text-foreground">
                {{ notification.message }}
            </p>
            <p class="text-xs text-muted-foreground">{{ notification.time }}</p>
        </div>
        <div
            class="absolute top-1/2 right-2 flex -translate-y-1/2 items-center gap-1 opacity-0 transition-opacity group-hover/item:opacity-100"
        >
            <button
                type="button"
                @click.stop="emit('markAsRead', notification.id)"
                class="rounded-md p-1 text-muted-foreground transition-colors hover:bg-primary/10 hover:text-primary"
            >
                <Check class="h-3.5 w-3.5" />
            </button>
            <button
                type="button"
                @click.stop="emit('delete', notification.id)"
                class="rounded-md p-1 text-muted-foreground transition-colors hover:bg-red-500/10 hover:text-red-500"
            >
                <Trash2 class="h-3.5 w-3.5" />
            </button>
        </div>
    </div>
</template>
