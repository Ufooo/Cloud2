<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { ProvisionScriptData } from '@/types';
import { ProvisionScriptStatus } from '@/types';
import { Copy } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const isOpen = ref(false);
const script = ref<ProvisionScriptData | null>(null);

function open(scriptData: ProvisionScriptData) {
    script.value = scriptData;
    isOpen.value = true;
}

function close() {
    isOpen.value = false;
    script.value = null;
}

async function copyOutput() {
    if (script.value?.output) {
        await navigator.clipboard.writeText(script.value.output);
    }
}

const statusVariant = computed(() => {
    if (!script.value) return 'default';

    switch (script.value.status) {
        case ProvisionScriptStatus.Completed:
            return 'success';
        case ProvisionScriptStatus.Failed:
            return 'destructive';
        case ProvisionScriptStatus.Executing:
            return 'warning';
        default:
            return 'secondary';
    }
});

const formattedDuration = computed(() => {
    if (!script.value?.duration) return null;
    const seconds = script.value.duration;
    if (seconds < 60) return `${seconds}s`;
    const minutes = Math.floor(seconds / 60);
    const remainingSeconds = seconds % 60;
    return `${minutes}m ${remainingSeconds}s`;
});

defineExpose({ open, close });
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent class="max-w-4xl">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <span>{{ script?.displayableName }} Output</span>
                    <Badge :variant="statusVariant">
                        {{ script?.status }}
                    </Badge>
                </DialogTitle>
                <DialogDescription class="flex items-center gap-4 text-sm">
                    <span v-if="script?.executedAt">
                        Executed:
                        {{ new Date(script.executedAt).toLocaleString() }}
                    </span>
                    <span v-if="formattedDuration">
                        Duration: {{ formattedDuration }}
                    </span>
                    <span v-if="script && script.exitCode !== null">
                        Exit Code: {{ script.exitCode }}
                    </span>
                </DialogDescription>
            </DialogHeader>

            <div
                class="h-[400px] overflow-auto rounded-md border bg-zinc-950 p-4"
            >
                <pre
                    v-if="script?.output"
                    class="font-mono text-sm whitespace-pre-wrap text-zinc-100"
                    >{{ script.output }}</pre
                >
                <p v-else class="text-sm text-zinc-500">No output available</p>
            </div>

            <DialogFooter>
                <Button
                    v-if="script?.output"
                    type="button"
                    variant="outline"
                    @click="copyOutput"
                >
                    <Copy class="mr-2 size-4" />
                    Copy Output
                </Button>
                <Button type="button" @click="close">Close</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
