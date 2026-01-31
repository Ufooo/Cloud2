import { computed, type ComputedRef } from 'vue';

export interface DatabaseVersionBadgeConfig {
    class: string;
    label: string;
}

const defaultColorClass =
    'bg-gray-50 text-gray-600 border-gray-200 dark:bg-gray-950 dark:text-gray-400 dark:border-gray-800';

const databaseColors: Record<string, string> = {
    mysql: 'bg-orange-50 text-orange-600 border-orange-200 dark:bg-orange-950 dark:text-orange-400 dark:border-orange-800',
    mariadb:
        'bg-sky-50 text-sky-600 border-sky-200 dark:bg-sky-950 dark:text-sky-400 dark:border-sky-800',
    postgresql:
        'bg-blue-50 text-blue-600 border-blue-200 dark:bg-blue-950 dark:text-blue-400 dark:border-blue-800',
};

export function useDatabaseVersionBadge(
    displayableType: () => string | null | undefined,
): ComputedRef<DatabaseVersionBadgeConfig | null> {
    return computed(() => {
        const type = displayableType();

        if (!type) {
            return null;
        }

        const lowerType = type.toLowerCase();
        let colorClass = defaultColorClass;

        for (const [db, color] of Object.entries(databaseColors)) {
            if (lowerType.includes(db)) {
                colorClass = color;
                break;
            }
        }

        return {
            class: colorClass,
            label: type,
        };
    });
}
