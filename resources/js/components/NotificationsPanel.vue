<script setup lang="ts">
import { computed } from 'vue';
import { Bell } from 'lucide-vue-next';
import NotificationItem from './NotificationItem.vue';

export interface Notification {
    id: number;
    message: string;
    time: string;
    type: 'success' | 'error' | 'warning';
    read: boolean;
}

const props = withDefaults(defineProps<{
    notifications?: Notification[];
}>(), {
    notifications: () => [],
});

const emit = defineEmits<{
    (e: 'markAsRead', id: number): void;
    (e: 'delete', id: number): void;
}>();

const unreadCount = computed(() => props.notifications.filter(n => !n.read).length);
</script>

<template>
    <div class="rounded-xl bg-secondary border border-border p-3">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-xs font-semibold text-muted-foreground uppercase tracking-wide">Notifications</h3>
            <span v-if="unreadCount > 0" class="text-xs bg-red-500 text-white rounded-full px-1.5 py-0.5">
                {{ unreadCount }}
            </span>
        </div>

        <!-- Empty State -->
        <div v-if="props.notifications.length === 0" class="py-6 flex flex-col items-center justify-center text-center">
            <div class="size-10 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center mb-2">
                <Bell class="size-5 text-muted-foreground" />
            </div>
            <p class="text-xs text-muted-foreground">No notifications</p>
            <p class="text-xs text-muted-foreground/70">You're all caught up!</p>
        </div>

        <!-- Notifications List -->
        <div v-else class="space-y-2">
            <NotificationItem
                v-for="notification in props.notifications"
                :key="notification.id"
                :notification="notification"
                @markAsRead="emit('markAsRead', $event)"
                @delete="emit('delete', $event)"
            />
        </div>

        <button
            v-if="props.notifications.length > 0"
            class="w-full mt-3 py-1.5 text-xs text-primary hover:text-primary/80 font-medium transition-colors"
        >
            View all notifications
        </button>
    </div>
</template>
