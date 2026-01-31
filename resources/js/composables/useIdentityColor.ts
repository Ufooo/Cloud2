import { IdentityColor } from '@/types';

/**
 * Identity color mapping for simple background classes
 * Used in Settings pages for color selection buttons
 */
export const IDENTITY_COLOR_MAP: Record<IdentityColor, string> = {
    [IdentityColor.Blue]: 'bg-blue-500',
    [IdentityColor.Green]: 'bg-green-500',
    [IdentityColor.Orange]: 'bg-orange-500',
    [IdentityColor.Purple]: 'bg-purple-500',
    [IdentityColor.Red]: 'bg-red-500',
    [IdentityColor.Yellow]: 'bg-yellow-500',
    [IdentityColor.Cyan]: 'bg-cyan-500',
    [IdentityColor.Gray]: 'bg-gray-500',
};

/**
 * Composable for working with Identity colors
 */
export function useIdentityColor() {
    /**
     * Get the Tailwind background class for a given identity color
     */
    function getColorClass(color: IdentityColor | null): string {
        return color
            ? IDENTITY_COLOR_MAP[color]
            : IDENTITY_COLOR_MAP[IdentityColor.Gray];
    }

    return {
        IDENTITY_COLOR_MAP,
        getColorClass,
    };
}
