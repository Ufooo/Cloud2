import { ref } from 'vue';

export function useClipboard() {
    const copied = ref(false);
    const error = ref<Error | null>(null);

    async function copy(text: string): Promise<void> {
        try {
            await navigator.clipboard.writeText(text);
            copied.value = true;
            error.value = null;

            setTimeout(() => {
                copied.value = false;
            }, 2000);
        } catch (err) {
            error.value = err as Error;
            copied.value = false;
        }
    }

    return {
        copied,
        error,
        copy,
    };
}
