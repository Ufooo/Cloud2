<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';

interface Props {
    label: string;
    name: string;
    error?: string;
    description?: string;
    required?: boolean;
}

defineProps<Props>();
</script>

<template>
    <div class="space-y-2">
        <Label :for="name">
            {{ label }}
            <span v-if="required" class="text-destructive">*</span>
        </Label>

        <slot />

        <p v-if="description" class="text-sm text-muted-foreground">
            {{ description }}
        </p>

        <InputError :message="error" />
    </div>
</template>
