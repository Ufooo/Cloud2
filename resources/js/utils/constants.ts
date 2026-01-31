/**
 * Site types that support PHP and require PHP version selection
 */
export const PHP_BASED_SITE_TYPES = [
    'laravel',
    'symfony',
    'statamic',
    'wordpress',
    'phpmyadmin',
    'php',
] as const;

/**
 * Check if a site type is PHP-based
 */
export function isPhpBasedSiteType(type: string | null): boolean {
    if (!type) return false;
    return PHP_BASED_SITE_TYPES.includes(
        type as (typeof PHP_BASED_SITE_TYPES)[number],
    );
}
