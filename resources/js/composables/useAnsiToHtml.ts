import { computed, type ComputedRef, type Ref } from 'vue';

const ANSI_COLORS: Record<string, string> = {
    '30': 'text-gray-900 dark:text-gray-100',
    '31': 'text-red-500',
    '32': 'text-green-500',
    '33': 'text-yellow-500',
    '34': 'text-blue-500',
    '35': 'text-purple-500',
    '36': 'text-cyan-500',
    '37': 'text-gray-300',
    '90': 'text-gray-500',
    '91': 'text-red-400',
    '92': 'text-green-400',
    '93': 'text-yellow-400',
    '94': 'text-blue-400',
    '95': 'text-purple-400',
    '96': 'text-cyan-400',
    '97': 'text-white',
};

const ANSI_BG_COLORS: Record<string, string> = {
    '40': 'bg-gray-900',
    '41': 'bg-red-500',
    '42': 'bg-green-500',
    '43': 'bg-yellow-500',
    '44': 'bg-blue-500',
    '45': 'bg-purple-500',
    '46': 'bg-cyan-500',
    '47': 'bg-gray-300',
};

// ANSI escape sequence patterns
const ANSI_PATTERNS = [
    { prefix: '\x1b[', prefixLength: 2 },
    { prefix: '\\u001b[', prefixLength: 7 },
] as const;

export function useAnsiToHtml(output: Ref<string | null | undefined>): {
    html: ComputedRef<string>;
} {
    const html = computed<string>(() => {
        if (!output.value) {
            return '';
        }

        return convertAnsiToHtml(output.value);
    });

    return { html };
}

function parseAnsiCodes(codes: string[]): string[] {
    const classes: string[] = [];

    for (const code of codes) {
        if (code === '0' || code === '') {
            return []; // Reset
        } else if (code === '1') {
            classes.push('font-bold');
        } else if (code === '3') {
            classes.push('italic');
        } else if (code === '4') {
            classes.push('underline');
        } else if (ANSI_COLORS[code]) {
            classes.push(ANSI_COLORS[code]);
        } else if (ANSI_BG_COLORS[code]) {
            classes.push(ANSI_BG_COLORS[code]);
        }
    }

    return classes;
}

export function convertAnsiToHtml(text: string): string {
    let result = '';
    let currentClasses: string[] = [];
    let i = 0;

    // Escape HTML first
    text = escapeHtml(text);

    while (i < text.length) {
        let matched = false;

        // Check for any ANSI escape sequence pattern
        for (const pattern of ANSI_PATTERNS) {
            if (
                text.substring(i, i + pattern.prefixLength) === pattern.prefix
            ) {
                const escapeStart = i + pattern.prefixLength;
                const escapeEnd = text.indexOf('m', escapeStart);

                if (escapeEnd !== -1) {
                    const codes = text
                        .substring(escapeStart, escapeEnd)
                        .split(';');

                    // Close previous span if exists
                    if (currentClasses.length > 0) {
                        result += '</span>';
                    }

                    currentClasses = parseAnsiCodes(codes);

                    // Open new span if we have classes
                    if (currentClasses.length > 0) {
                        result += `<span class="${currentClasses.join(' ')}">`;
                    }

                    i = escapeEnd + 1;
                    matched = true;
                    break;
                }
            }
        }

        if (!matched) {
            result += text[i];
            i++;
        }
    }

    // Close any remaining span
    if (currentClasses.length > 0) {
        result += '</span>';
    }

    return result;
}

function escapeHtml(text: string): string {
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
