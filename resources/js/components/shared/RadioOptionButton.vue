<script setup lang="ts">
interface Props {
    selected: boolean;
    label: string;
    description?: string;
    badge?: string;
}

defineProps<Props>();

const emit = defineEmits<{
    (e: 'select'): void;
}>();
</script>

<template>
    <button
        type="button"
        class="flex w-full items-start gap-3 rounded-md border p-3 text-left transition-colors"
        :class="
            selected
                ? 'border-primary bg-primary/5'
                : 'border-border hover:bg-muted/50'
        "
        @click="emit('select')"
    >
        <div
            class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full border"
            :class="selected ? 'border-primary' : 'border-muted-foreground'"
        >
            <div v-if="selected" class="size-2 rounded-full bg-primary" />
        </div>
        <div class="flex-1">
            <div class="flex items-center gap-2">
                <span class="font-medium">{{ label }}</span>
                <span
                    v-if="badge"
                    class="rounded border border-green-600 px-1.5 py-0.5 text-xs font-normal text-green-600 dark:border-green-500 dark:text-green-500"
                >
                    {{ badge }}
                </span>
            </div>
            <p v-if="description" class="text-sm text-muted-foreground">
                {{ description }}
            </p>
        </div>
    </button>
</template>
