<script setup lang="ts">
import Avatar from '@/components/shared/Avatar.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import type { Site } from '@/types';
import { router } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import { Check } from 'lucide-vue-next';

interface Props {
    site: Site;
}

const props = defineProps<Props>();

function isCompleted(stepValue: number): boolean {
    if (props.site.provisioningStep === null) {
        return false;
    }
    return stepValue < props.site.provisioningStep;
}

function isActive(stepValue: number): boolean {
    return stepValue === props.site.provisioningStep;
}

useEcho(`sites.${props.site.id}`, '.SiteProvisioningStepChanged', () => {
    router.reload({ only: ['site'] });
});
</script>

<template>
    <div class="space-y-6">
        <Card>
            <CardContent class="flex items-center gap-4 p-6">
                <Avatar
                    :name="site.domain"
                    :color="site.avatarColor"
                    size="lg"
                />

                <div class="flex-1 space-y-1">
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-bold">
                            {{ site.domain }}
                        </h1>
                        <Badge variant="secondary">
                            {{ site.displayableStatus }}
                        </Badge>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        {{ site.displayableType }}
                    </p>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="p-6">
                <div class="space-y-2">
                    <h2 class="text-lg font-semibold">
                        We're setting up your site
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        This process typically takes about 2-3 minutes,
                        configuring Nginx, PHP-FPM, and deploying your
                        application.
                    </p>
                </div>

                <div class="mt-8">
                    <div class="space-y-0">
                        <div
                            v-for="(step, index) in site.provisioningSteps"
                            :key="step.value"
                            class="relative flex gap-4"
                        >
                            <div class="flex flex-col items-center">
                                <div
                                    class="relative z-10 flex size-6 shrink-0 items-center justify-center rounded-full border-2"
                                    :class="{
                                        'border-green-500 bg-green-500':
                                            isCompleted(step.value),
                                        'border-primary bg-primary': isActive(
                                            step.value,
                                        ),
                                        'border-muted-foreground/30 bg-background':
                                            !isCompleted(step.value) &&
                                            !isActive(step.value),
                                    }"
                                >
                                    <Check
                                        v-if="isCompleted(step.value)"
                                        class="size-3.5 text-white"
                                    />
                                    <div
                                        v-else-if="isActive(step.value)"
                                        class="size-2 animate-pulse rounded-full bg-white"
                                    />
                                </div>
                                <div
                                    v-if="
                                        site.provisioningSteps &&
                                        index <
                                            site.provisioningSteps.length - 1
                                    "
                                    class="h-12 w-0.5"
                                    :class="{
                                        'bg-green-500': isCompleted(step.value),
                                        'bg-muted-foreground/20': !isCompleted(
                                            step.value,
                                        ),
                                    }"
                                />
                            </div>

                            <div class="pb-8">
                                <h3
                                    class="font-medium"
                                    :class="{
                                        'text-green-600': isCompleted(
                                            step.value,
                                        ),
                                        'text-foreground': isActive(step.value),
                                        'text-muted-foreground':
                                            !isCompleted(step.value) &&
                                            !isActive(step.value),
                                    }"
                                >
                                    {{ step.label }}
                                </h3>
                                <p
                                    v-if="
                                        step.description && isActive(step.value)
                                    "
                                    class="mt-1 text-sm text-muted-foreground"
                                >
                                    {{ step.description }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
