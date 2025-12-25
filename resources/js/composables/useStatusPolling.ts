import { usePoll } from '@inertiajs/vue3';
import type { ComputedRef, Ref } from 'vue';
import { computed, watch } from 'vue';

type MaybeRef<T> = T | Ref<T> | ComputedRef<T>;

interface UseStatusPollingOptions<T> {
    items: MaybeRef<T[]>;
    getStatus: (item: T) => string;
    propName: string;
    pendingStatuses?: string[];
    interval?: number;
}

const DEFAULT_PENDING_STATUSES = ['pending', 'installing', 'deleting'];
const DEFAULT_INTERVAL = 3000;

export function useStatusPolling<T>({
    items,
    getStatus,
    propName,
    pendingStatuses = DEFAULT_PENDING_STATUSES,
    interval = DEFAULT_INTERVAL,
}: UseStatusPollingOptions<T>) {
    const itemsValue = computed(() =>
        'value' in items ? items.value : items,
    );

    const hasPending = computed(() =>
        itemsValue.value.some((item) =>
            pendingStatuses.includes(getStatus(item)),
        ),
    );

    const { start, stop } = usePoll(
        interval,
        { only: [propName] },
        { autoStart: false },
    );

    watch(
        hasPending,
        (pending) => {
            if (pending) {
                start();
            } else {
                stop();
            }
        },
        { immediate: true },
    );

    return { hasPending, start, stop };
}
