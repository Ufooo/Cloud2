import { computed, type ComputedRef } from 'vue';

export interface StatusBadgeConfig {
    variant: 'default' | 'secondary' | 'destructive' | 'outline';
    class: string;
    label: string;
    pulse: boolean;
}

export type StatusType = 'success' | 'progress' | 'warning' | 'error' | 'neutral';

const statusColors: Record<StatusType, string> = {
    success: 'bg-green-500/10 text-green-600 border-green-500/20 dark:bg-green-500/20 dark:text-green-400',
    progress: 'bg-blue-500/10 text-blue-600 border-blue-500/20 dark:bg-blue-500/20 dark:text-blue-400',
    warning: 'bg-yellow-500/10 text-yellow-600 border-yellow-500/20 dark:bg-yellow-500/20 dark:text-yellow-400',
    error: 'bg-red-500/10 text-red-600 border-red-500/20 dark:bg-red-500/20 dark:text-red-400',
    neutral: '',
};

const additionalColors: Record<string, string> = {
    purple: 'bg-purple-500/10 text-purple-600 border-purple-500/20 dark:bg-purple-500/20 dark:text-purple-400',
    orange: 'bg-orange-500/10 text-orange-600 border-orange-500/20 dark:bg-orange-500/20 dark:text-orange-400',
};

export interface StatusDefinition {
    type: StatusType;
    label: string;
    pulse?: boolean;
    color?: string;
}

export function useStatusBadge<T extends string>(
    status: () => T,
    definitions: Record<T, StatusDefinition>,
    fallbackLabel?: (status: T) => string,
): ComputedRef<StatusBadgeConfig> {
    return computed(() => {
        const currentStatus = status();
        const definition = definitions[currentStatus];

        if (!definition) {
            return {
                variant: 'secondary' as const,
                class: '',
                label: fallbackLabel?.(currentStatus) ?? capitalize(currentStatus),
                pulse: false,
            };
        }

        const colorClass = definition.color
            ? additionalColors[definition.color] ?? ''
            : statusColors[definition.type];

        return {
            variant: getVariant(definition.type),
            class: colorClass,
            label: definition.label,
            pulse: definition.pulse ?? isProgressType(definition.type),
        };
    });
}

function getVariant(type: StatusType): StatusBadgeConfig['variant'] {
    switch (type) {
        case 'success':
            return 'default';
        case 'error':
            return 'destructive';
        default:
            return 'secondary';
    }
}

function isProgressType(type: StatusType): boolean {
    return type === 'progress' || type === 'warning';
}

function capitalize(str: string): string {
    return str.charAt(0).toUpperCase() + str.slice(1);
}
