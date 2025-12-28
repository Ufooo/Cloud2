import { router } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';

interface UseResourceStatusUpdatesOptions {
    channelType: 'server' | 'site';
    channelId: number | string;
    propNames: string[];
}

export function useResourceStatusUpdates({
    channelType,
    channelId,
    propNames,
}: UseResourceStatusUpdatesOptions) {
    const channelName = `${channelType}s.${channelId}`;
    const eventName =
        channelType === 'server'
            ? 'ServerResourceStatusUpdated'
            : 'SiteResourceStatusUpdated';

    useEcho(channelName, eventName, () => {
        router.reload({ only: propNames, preserveScroll: true });
    });
}
