import { ref } from 'vue';

export interface Toast {
    id: string;
    title: string;
    description?: string;
    variant?: 'default' | 'success' | 'error' | 'warning';
}

const toasts = ref<Toast[]>([]);

export function useToast() {
    function add(toast: Omit<Toast, 'id'>): string {
        const id = Math.random().toString(36).substring(7);
        toasts.value.push({ ...toast, id });

        setTimeout(() => {
            remove(id);
        }, 5000);

        return id;
    }

    function success(options: { title: string; description?: string }): string {
        return add({ ...options, variant: 'success' });
    }

    function error(options: { title: string; description?: string }): string {
        return add({ ...options, variant: 'error' });
    }

    function warning(options: { title: string; description?: string }): string {
        return add({ ...options, variant: 'warning' });
    }

    function remove(id: string): void {
        const index = toasts.value.findIndex((t) => t.id === id);
        if (index !== -1) {
            toasts.value.splice(index, 1);
        }
    }

    return {
        toasts,
        add,
        success,
        error,
        warning,
        remove,
    };
}
