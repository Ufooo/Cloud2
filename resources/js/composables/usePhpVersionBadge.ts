import { computed, type ComputedRef } from 'vue';

export interface PhpVersionBadgeConfig {
    class: string;
    label: string;
}

const defaultColorClass =
    'bg-gray-50 text-gray-600 border-gray-200 dark:bg-gray-950 dark:text-gray-400 dark:border-gray-800';

const versionColors: Record<string, string> = {
    '8.4': 'bg-emerald-50 text-emerald-600 border-emerald-200 dark:bg-emerald-950 dark:text-emerald-400 dark:border-emerald-800',
    '8.3': 'bg-cyan-50 text-cyan-600 border-cyan-200 dark:bg-cyan-950 dark:text-cyan-400 dark:border-cyan-800',
    '8.2': 'bg-blue-50 text-blue-600 border-blue-200 dark:bg-blue-950 dark:text-blue-400 dark:border-blue-800',
    '8.1': 'bg-violet-50 text-violet-600 border-violet-200 dark:bg-violet-950 dark:text-violet-400 dark:border-violet-800',
    '7.4': 'bg-red-50 text-red-600 border-red-200 dark:bg-red-950 dark:text-red-400 dark:border-red-800',
};

export function usePhpVersionBadge(
    version: () => string | null | undefined,
): ComputedRef<PhpVersionBadgeConfig | null> {
    return computed(() => {
        const currentVersion = version();

        if (!currentVersion) {
            return null;
        }

        const colorClass = versionColors[currentVersion] ?? defaultColorClass;

        return {
            class: colorClass,
            label: `PHP ${currentVersion}`,
        };
    });
}
