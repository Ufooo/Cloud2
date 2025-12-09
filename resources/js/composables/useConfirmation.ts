import { ref } from 'vue';

export interface ConfirmationOptions {
    title: string;
    description: string;
    confirmText?: string;
    cancelText?: string;
}

export interface ConfirmationInputOptions extends ConfirmationOptions {
    value: string;
}

const isOpen = ref(false);
const currentOptions = ref<ConfirmationOptions | null>(null);
const currentInputOptions = ref<ConfirmationInputOptions | null>(null);
const resolvePromise = ref<((value: boolean) => void) | null>(null);

export function useConfirmation() {
    function confirmButton(options: ConfirmationOptions): Promise<boolean> {
        currentOptions.value = options;
        isOpen.value = true;

        return new Promise((resolve) => {
            resolvePromise.value = resolve;
        });
    }

    function confirmInput(options: ConfirmationInputOptions): Promise<boolean> {
        currentInputOptions.value = options;
        isOpen.value = true;

        return new Promise((resolve) => {
            resolvePromise.value = resolve;
        });
    }

    function confirm(): void {
        if (resolvePromise.value) {
            resolvePromise.value(true);
            reset();
        }
    }

    function cancel(): void {
        if (resolvePromise.value) {
            resolvePromise.value(false);
            reset();
        }
    }

    function reset(): void {
        isOpen.value = false;
        currentOptions.value = null;
        currentInputOptions.value = null;
        resolvePromise.value = null;
    }

    return {
        isOpen,
        currentOptions,
        currentInputOptions,
        confirmButton,
        confirmInput,
        confirm,
        cancel,
    };
}
