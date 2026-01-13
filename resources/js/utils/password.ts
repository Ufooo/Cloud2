export function generateSecurePassword(length = 24): string {
    const chars =
        'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    return Array.from({ length }, () =>
        chars.charAt(Math.floor(Math.random() * chars.length)),
    ).join('');
}
