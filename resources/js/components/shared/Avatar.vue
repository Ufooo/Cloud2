<script setup lang="ts">
import type { IdentityColor } from '@/types';
import { computed } from 'vue';

interface Props {
    name: string;
    color?: IdentityColor | null;
    size?: 'sm' | 'md' | 'lg';
}

const props = withDefaults(defineProps<Props>(), {
    color: null,
    size: 'md',
});

// Avatar color definitions with background, text, and border colors
const colorStyles: Record<IdentityColor, { bg: string; bgDark: string; text: string; border: string; borderDark: string }> = {
    blue: {
        bg: 'bg-[#EDF6FF]',
        bgDark: 'dark:bg-[#10243E]',
        text: 'text-[#0091FF]',
        border: 'border-[#d6e6ff]',
        borderDark: 'dark:border-[#d6e6ff]/10',
    },
    green: {
        bg: 'bg-[#E9F9EE]',
        bgDark: 'dark:bg-[#0F2E1A]',
        text: 'text-[#30A46C]',
        border: 'border-[#c4e8d1]',
        borderDark: 'dark:border-[#c4e8d1]/10',
    },
    orange: {
        bg: 'bg-[#FFF1E7]',
        bgDark: 'dark:bg-[#331E0B]',
        text: 'text-[#F76B15]',
        border: 'border-[#ffd5b8]',
        borderDark: 'dark:border-[#ffd5b8]/10',
    },
    purple: {
        bg: 'bg-[#F3E7FC]',
        bgDark: 'dark:bg-[#2B1040]',
        text: 'text-[#8E4EC6]',
        border: 'border-[#e3ccf4]',
        borderDark: 'dark:border-[#e3ccf4]/10',
    },
    red: {
        bg: 'bg-[#FFEFEF]',
        bgDark: 'dark:bg-[#3B1219]',
        text: 'text-[#E5484D]',
        border: 'border-[#fdd8d8]',
        borderDark: 'dark:border-[#fdd8d8]/10',
    },
    yellow: {
        bg: 'bg-[#FEF9E7]',
        bgDark: 'dark:bg-[#352800]',
        text: 'text-[#F5D90A]',
        border: 'border-[#f5e79e]',
        borderDark: 'dark:border-[#f5e79e]/10',
    },
    cyan: {
        bg: 'bg-[#E7F9FB]',
        bgDark: 'dark:bg-[#0D3840]',
        text: 'text-[#05A2C2]',
        border: 'border-[#b8ecf4]',
        borderDark: 'dark:border-[#b8ecf4]/10',
    },
    gray: {
        bg: 'bg-[#F4F4F5]',
        bgDark: 'dark:bg-[#27272A]',
        text: 'text-[#71717A]',
        border: 'border-[#e4e4e7]',
        borderDark: 'dark:border-[#e4e4e7]/10',
    },
};

const defaultColor = colorStyles.gray;

const styles = computed(() => props.color ? colorStyles[props.color] : defaultColor);

const avatarClasses = computed(() => `${styles.value.bg} ${styles.value.bgDark}`);
const textClasses = computed(() => styles.value.text);
const borderClasses = computed(() => `${styles.value.border} ${styles.value.borderDark}`);

const sizeClasses = computed(() => {
    switch (props.size) {
        case 'sm':
            return 'size-8';
        case 'lg':
            return 'size-16';
        default:
            return 'size-10';
    }
});

const initial = computed(() => props.name.charAt(0).toUpperCase());
</script>

<template>
    <div
        :class="[avatarClasses, sizeClasses]"
        class="inline-grid shrink-0 rounded *:col-start-1 *:row-start-1 *:rounded"
    >
        <svg :class="textClasses" class="fill-current text-5xl font-medium uppercase" viewBox="0 0 100 100">
            <title>{{ name }}</title>
            <text
                x="50%"
                y="50%"
                alignment-baseline="middle"
                dominant-baseline="middle"
                text-anchor="middle"
                dy=".125em"
                fill="currentColor"
            >
                {{ initial }}
            </text>
        </svg>
        <div :class="borderClasses" class="border" aria-hidden="true" />
    </div>
</template>
