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
        class="group/item relative flex items-start gap-2 p-3 rounded-xl bg-white dark:bg-card border border-border hover:border-primary/50 hover:shadow-sm transition-all cursor-pointer"
    >
        <div :class="[getTypeColor(notification.type), 'w-2 h-2 rounded-full mt-1.5 flex-shrink-0']" />
        <div class="flex-1 min-w-0 pr-0 group-hover/item:pr-14 transition-all">
            <p class="text-xs text-foreground truncate">{{ notification.message }}</p>
            <p class="text-xs text-muted-foreground">{{ notification.time }}</p>
        </div>
        <div class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center gap-1 opacity-0 group-hover/item:opacity-100 transition-opacity">
            <button
                type="button"
                @click.stop="emit('markAsRead', notification.id)"
                class="p-1 rounded-md text-muted-foreground hover:text-primary hover:bg-primary/10 transition-colors"
            >
                <Check class="w-3.5 h-3.5" />
            </button>
            <button
                type="button"
                @click.stop="emit('delete', notification.id)"
                class="p-1 rounded-md text-muted-foreground hover:text-red-500 hover:bg-red-500/10 transition-colors"
            >
                <Trash2 class="w-3.5 h-3.5" />
            </button>
        </div>
    </div>
</template>
