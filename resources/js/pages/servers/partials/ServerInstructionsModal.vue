<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useClipboard } from '@/composables/useClipboard';
import type { Server } from '@/types';
import { Check, Copy, Terminal } from 'lucide-vue-next';

interface Props {
    server: Server;
    open: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const { copy, copied } = useClipboard();

function copyCommand() {
    if (props.server.provisioningCommand) {
        copy(props.server.provisioningCommand);
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-2xl">
            <DialogHeader>
                <DialogTitle>Run provisioning command</DialogTitle>
                <DialogDescription>
                    Run the provisioning command on your server to begin the
                    provisioning process.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-6 overflow-hidden py-4">
                <!-- Step 1 -->
                <div class="flex gap-4">
                    <div
                        class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-medium text-primary-foreground"
                    >
                        1
                    </div>
                    <div class="space-y-1">
                        <h4 class="font-medium">
                            SSH into your server as
                            <code
                                class="rounded bg-muted px-1.5 py-0.5 font-mono text-sm"
                                >root</code
                            >
                        </h4>
                    </div>
                </div>

                <!-- Step 2 -->
                <div class="flex gap-4">
                    <div
                        class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-medium text-primary-foreground"
                    >
                        2
                    </div>
                    <div class="min-w-0 flex-1 space-y-3 overflow-hidden">
                        <div class="space-y-1">
                            <h4 class="font-medium">
                                Run the following command in your terminal
                            </h4>
                            <p class="text-sm text-muted-foreground">
                                This command will begin the provisioning process
                                for your server, and will configure the server
                                so that it can be managed by Cloud.
                            </p>
                        </div>
                        <div
                            class="flex items-center gap-2 rounded-lg border bg-muted/50 p-3"
                        >
                            <Terminal
                                class="size-4 shrink-0 text-muted-foreground"
                            />
                            <div class="min-w-0 flex-1">
                                <code
                                    class="block truncate font-mono text-sm"
                                    >{{ server.provisioningCommand }}</code
                                >
                            </div>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-8 shrink-0"
                                @click="copyCommand"
                            >
                                <Check
                                    v-if="copied"
                                    class="size-4 text-green-500"
                                />
                                <Copy v-else class="size-4" />
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Step 3 -->
                <div class="flex gap-4">
                    <div
                        class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-medium text-primary-foreground"
                    >
                        3
                    </div>
                    <div class="space-y-1">
                        <h4 class="font-medium">
                            Provisioning will start automatically when you run
                            the command
                        </h4>
                        <p class="text-sm text-muted-foreground">
                            Cloud will be notified when your server is finished
                            provisioning.
                        </p>
                    </div>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="emit('update:open', false)">
                    Close
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
