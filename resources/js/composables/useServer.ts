import type { IdentityColor, Server } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed, toValue, type MaybeRef } from 'vue';
import { useClipboard } from './useClipboard';
import { useToast } from './useToast';

const avatarColors: Record<IdentityColor, string> = {
    blue: 'bg-blue-500',
    green: 'bg-green-500',
    orange: 'bg-orange-500',
    purple: 'bg-purple-500',
    red: 'bg-red-500',
    yellow: 'bg-yellow-500',
    cyan: 'bg-cyan-500',
    gray: 'bg-gray-500',
};

export function useServer() {
    const page = usePage();
    const server = computed(() => page.props.server as Server | undefined);

    return { server };
}

export function useServerAvatar(server: MaybeRef<Server>) {
    const avatarColorClass = computed(() => {
        const s = toValue(server);
        return avatarColors[s.avatarColor] || avatarColors.gray;
    });

    const initials = computed(() => {
        const s = toValue(server);
        return s.name
            .split(' ')
            .map((word) => word[0])
            .join('')
            .toUpperCase()
            .substring(0, 2);
    });

    return { avatarColorClass, initials };
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
