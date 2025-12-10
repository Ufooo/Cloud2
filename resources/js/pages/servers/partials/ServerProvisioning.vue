<script setup lang="ts">
import { destroy } from '@/actions/Nip/Server/Http/Controllers/ServerController';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useConfirmation } from '@/composables/useConfirmation';
import { useServerAvatar } from '@/composables/useServer';
import type { Server as ServerType } from '@/types';
import { router } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import { Check } from 'lucide-vue-next';
import { ref } from 'vue';
import ServerInstructionsModal from './ServerInstructionsModal.vue';
import ServerStatusBadge from './ServerStatusBadge.vue';

interface Props {
    server: ServerType;
}

const props = defineProps<Props>();

const { confirmButton } = useConfirmation();
const { avatarColorClass, initials } = useServerAvatar(() => props.server);

const showInstructionsModal = ref(false);

// Step status helpers
function isCompleted(stepValue: number): boolean {
    return stepValue < props.server.provisionStep;
}

function isActive(stepValue: number): boolean {
    return stepValue === props.server.provisionStep;
}

// Echo listener for real-time updates (useEcho handles lifecycle automatically)
useEcho(`servers.${props.server.id}`, 'ServerProvisioningUpdated', () => {
    router.reload({ only: ['server'] });
});

async function handleCancelProvisioning() {
    const confirmed = await confirmButton({
        title: 'Cancel provisioning',
        description:
            'Are you sure you want to cancel provisioning? This will delete the server.',
    });

    if (confirmed) {
        router.delete(destroy.url(props.server));
    }
}
</script>

<template>
    <div class="grid gap-6 lg:grid-cols-3">
        <!-- Main Content -->
        <div class="space-y-6 lg:col-span-2">
            <!-- Server Header -->
            <Card>
                <CardContent class="flex items-center gap-4 p-6">
                    <Avatar class="size-16 rounded-lg">
                        <AvatarFallback
                            :class="avatarColorClass"
                            class="rounded-lg text-xl font-medium text-white"
                        >
                            {{ initials }}
                        </AvatarFallback>
                    </Avatar>

                    <div class="flex-1 space-y-1">
                        <div class="flex items-center gap-3">
                            <h1 class="text-2xl font-bold">
                                {{ server.name }}
                            </h1>
                            <ServerStatusBadge :status="server.status" />
                        </div>
                        <p class="text-sm text-muted-foreground">
                            {{ server.displayableType }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <!-- Provisioning Steps -->
            <Card>
                <CardContent class="p-6">
                    <div class="space-y-2">
                        <h2 class="text-lg font-semibold">
                            We're provisioning your server
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            This process typically takes about 10 minutes, and
                            completely configures your new server.
                        </p>
                    </div>

                    <div class="mt-8">
                        <div class="space-y-0">
                            <div
                                v-for="(
                                    step, index
                                ) in server.provisioningSteps"
                                :key="step.value"
                                class="relative flex gap-4"
                            >
                                <!-- Timeline -->
                                <div class="flex flex-col items-center">
                                    <!-- Dot -->
                                    <div
                                        class="relative z-10 flex size-6 shrink-0 items-center justify-center rounded-full border-2"
                                        :class="{
                                            'border-green-500 bg-green-500':
                                                isCompleted(step.value),
                                            'border-primary bg-primary':
                                                isActive(step.value),
                                            'border-muted-foreground/30 bg-background':
                                                !isCompleted(step.value) &&
                                                !isActive(step.value),
                                        }"
                                    >
                                        <!-- Completed checkmark -->
                                        <Check
                                            v-if="isCompleted(step.value)"
                                            class="size-3.5 text-white"
                                        />
                                        <!-- Active pulse -->
                                        <div
                                            v-else-if="isActive(step.value)"
                                            class="size-2 animate-pulse rounded-full bg-white"
                                        />
                                    </div>
                                    <!-- Line -->
                                    <div
                                        v-if="
                                            index <
                                            server.provisioningSteps.length - 1
                                        "
                                        class="h-12 w-0.5"
                                        :class="{
                                            'bg-green-500': isCompleted(
                                                step.value,
                                            ),
                                            'bg-muted-foreground/20':
                                                !isCompleted(step.value),
                                        }"
                                    />
                                </div>

                                <!-- Content -->
                                <div class="pb-8">
                                    <h3
                                        class="font-medium"
                                        :class="{
                                            'text-green-600': isCompleted(
                                                step.value,
                                            ),
                                            'text-foreground': isActive(
                                                step.value,
                                            ),
                                            'text-muted-foreground':
                                                !isCompleted(step.value) &&
                                                !isActive(step.value),
                                        }"
                                    >
                                        {{ step.label }}
                                    </h3>
                                    <p
                                        v-if="
                                            step.description &&
                                            isActive(step.value)
                                        "
                                        class="mt-1 text-sm text-muted-foreground"
                                    >
                                        {{ step.description }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="mt-6 flex gap-3">
                        <Button
                            variant="outline"
                            size="sm"
                            @click="showInstructionsModal = true"
                        >
                            Show instructions
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                            @click="handleCancelProvisioning"
                        >
                            Cancel provisioning
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Details -->
            <Card>
                <CardHeader class="pb-3">
                    <CardTitle class="text-base">Details</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-muted-foreground">ID</span>
                        <span class="font-medium">{{ server.id }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-muted-foreground">Type</span>
                        <span class="font-medium">{{
                            server.displayableType
                        }}</span>
                    </div>
                    <div
                        v-if="server.displayableDatabaseType"
                        class="flex justify-between text-sm"
                    >
                        <span class="text-muted-foreground">Database Type</span>
                        <span class="font-medium">{{
                            server.displayableDatabaseType
                        }}</span>
                    </div>
                    <div
                        v-if="server.displayablePhpVersion"
                        class="flex justify-between text-sm"
                    >
                        <span class="text-muted-foreground">PHP</span>
                        <span class="font-medium">{{
                            server.displayablePhpVersion
                        }}</span>
                    </div>
                    <div
                        v-if="server.ubuntuVersion"
                        class="flex justify-between text-sm"
                    >
                        <span class="text-muted-foreground">Ubuntu</span>
                        <span class="font-medium">{{
                            server.ubuntuVersion
                        }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-muted-foreground">Created</span>
                        <span class="font-medium">{{ server.createdAt }}</span>
                    </div>
                </CardContent>
            </Card>

            <!-- Networking -->
            <Card>
                <CardHeader class="pb-3">
                    <CardTitle class="text-base">Networking</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div
                        v-if="server.ipAddress"
                        class="flex justify-between text-sm"
                    >
                        <span class="text-muted-foreground">Public IP</span>
                        <span class="font-mono font-medium">{{
                            server.ipAddress
                        }}</span>
                    </div>
                    <div
                        v-if="server.privateIpAddress"
                        class="flex justify-between text-sm"
                    >
                        <span class="text-muted-foreground">Private IP</span>
                        <span class="font-mono font-medium">{{
                            server.privateIpAddress
                        }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-muted-foreground">SSH Port</span>
                        <span class="font-mono font-medium">{{
                            server.sshPort
                        }}</span>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>

    <!-- Instructions Modal -->
    <ServerInstructionsModal
        :server="server"
        v-model:open="showInstructionsModal"
    />
</template>
