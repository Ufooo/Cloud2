import type { Server } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed, toValue, type MaybeRef } from 'vue';
import { useClipboard } from './useClipboard';
import { useToast } from './useToast';

export function useServer() {
    const page = usePage();
    const server = computed(() => page.props.server as Server | undefined);

    return { server };
}

export function useServerActions(server: MaybeRef<Server>) {
    const { copy } = useClipboard();
    const { success } = useToast();

    async function copyIpAddress() {
        const s = toValue(server);
        if (s.ipAddress) {
            await copy(s.ipAddress);
            success({ title: 'IP address copied to clipboard' });
        }
    }

    return { copyIpAddress };
}
