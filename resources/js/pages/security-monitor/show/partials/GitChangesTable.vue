<script setup lang="ts">
import {
    removeByFile,
    whitelistAll as whitelistAllAction,
} from '@/actions/Nip/SecurityMonitor/Http/Controllers/GitWhitelistController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { Site } from '@/types';
import type { GitChange } from '@/types/security-monitor';
import { router } from '@inertiajs/vue3';
import { CheckCircle2, FileIcon, ShieldCheck, XCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import WhitelistModal from '../../components/WhitelistModal.vue';

interface Props {
    changes: GitChange[];
    site: Site;
    title: string;
    isWhitelisted?: boolean;
    showWhitelistAll?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    isWhitelisted: false,
    showWhitelistAll: false,
});

const showWhitelistModal = ref(false);
const selectedChange = ref<GitChange | null>(null);

function handleWhitelist(change: GitChange) {
    selectedChange.value = change;
    showWhitelistModal.value = true;
}

function handleWhitelistAll() {
    router.post(whitelistAllAction.url(props.site), {
        preserveScroll: true,
    });
}

function handleRemoveFromWhitelist(change: GitChange) {
    if (!confirm(`Remove "${change.filePath}" from whitelist? It will reappear on the next scan if the file still exists.`)) {
        return;
    }

    router.delete(removeByFile.url(props.site), {
        data: {
            file_path: change.filePath,
            change_type: change.changeType,
        },
        preserveScroll: true,
    });
}

const isEmpty = computed(() => props.changes.length === 0);
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-medium">{{ title }}</h3>
            <Button
                v-if="showWhitelistAll && !isWhitelisted"
                variant="outline"
                size="sm"
                @click="handleWhitelistAll"
            >
                <ShieldCheck class="mr-2 size-4" />
                Whitelist All
            </Button>
        </div>

        <div v-if="isEmpty" class="text-center py-8 text-muted-foreground">
            <FileIcon class="size-12 mx-auto mb-2 opacity-50" />
            <p>No {{ title.toLowerCase() }} found</p>
        </div>

        <div v-else class="rounded-md border">
            <table class="w-full">
                <thead>
                    <tr class="border-b bg-muted/50">
                        <th class="p-3 text-left text-sm font-medium w-16">Code</th>
                        <th class="p-3 text-left text-sm font-medium">File Path</th>
                        <th class="p-3 text-left text-sm font-medium w-32">Type</th>
                        <th v-if="isWhitelisted" class="p-3 text-left text-sm font-medium">Reason</th>
                        <th class="p-3 text-right text-sm font-medium w-32">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="change in changes"
                        :key="change.id"
                        class="border-b last:border-b-0 hover:bg-muted/50"
                        :class="{ 'opacity-60': isWhitelisted }"
                    >
                        <td class="p-3 text-sm font-mono">{{ change.gitStatusCode }}</td>
                        <td class="p-3 text-sm font-mono truncate max-w-md" :title="change.filePath">
                            {{ change.filePath }}
                        </td>
                        <td class="p-3">
                            <Badge :variant="change.changeTypeBadgeVariant">
                                {{ change.changeTypeLabel }}
                            </Badge>
                        </td>
                        <td v-if="isWhitelisted" class="p-3 text-sm text-muted-foreground">
                            {{ change.whitelistReason || '-' }}
                        </td>
                        <td class="p-3 text-right">
                            <Button
                                v-if="!isWhitelisted"
                                variant="outline"
                                size="sm"
                                @click="handleWhitelist(change)"
                            >
                                <CheckCircle2 class="mr-1 size-3" />
                                Whitelist
                            </Button>
                            <Button
                                v-else
                                variant="outline"
                                size="sm"
                                @click="handleRemoveFromWhitelist(change)"
                            >
                                <XCircle class="mr-1 size-3" />
                                Remove
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <WhitelistModal
        v-if="selectedChange"
        v-model:open="showWhitelistModal"
        :site="site"
        :file-path="selectedChange.filePath"
        :change-type="selectedChange.changeType"
        type="git"
    />
</template>
