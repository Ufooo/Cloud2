import { router } from '@inertiajs/vue3';
import { useConfirmation } from './useConfirmation';

interface DeleteOptions<T> {
    resourceName: string;
    getDisplayName: (resource: T) => string;
    getDeleteUrl: (resource: T) => string;
    confirmText?: string;
}

export function useResourceDelete<T>(options: DeleteOptions<T>) {
    const { confirmButton } = useConfirmation();

    async function deleteResource(resource: T): Promise<void> {
        const confirmed = await confirmButton({
            title: `Delete ${options.resourceName}`,
            description: `Are you sure you want to delete "${options.getDisplayName(resource)}"?`,
            confirmText: options.confirmText ?? 'Delete',
        });

        if (!confirmed) {
            return;
        }

        router.delete(options.getDeleteUrl(resource));
    }

    return { deleteResource };
}
