<script setup lang="ts">
import { dismiss, show } from '@/actions/Nip/Server/Http/Controllers/ProvisionScriptController';
import ScriptOutputModal from '@/components/ScriptOutputModal.vue';
import type { AppPageProps, FailedScript, ProvisionScriptData } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { Bell, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const page = usePage<AppPageProps>();
const failedScripts = computed(() => page.props.failedScripts ?? []);

const dismissingIds = ref<Set<number>>(new Set());

const visibleScripts = computed(() =>
    failedScripts.value.filter((s) => !dismissingIds.value.has(s.id)),
);

const displayedScripts = computed(() => visibleScripts.value.slice(0, 3));
const totalCount = computed(() => visibleScripts.value.length);

const scriptOutputModal = ref<InstanceType<typeof ScriptOutputModal> | null>(null);

function handleDismiss(script: FailedScript) {
    if (dismissingIds.value.has(script.id)) return;

    dismissingIds.value.add(script.id);
    router.post(dismiss.url(script.id), {}, {
        preserveScroll: true,
        onError: () => dismissingIds.value.delete(script.id),
    });
}

async function handleOpenDetails(script: FailedScript) {
    const response = await fetch(show.url(script.id));
    const data: ProvisionScriptData = await response.json();

    scriptOutputModal.value?.open(data);
}
</script>

<template>
    <div class="rounded-xl bg-secondary border border-border p-3">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-xs font-semibold text-muted-foreground uppercase tracking-wide">
                Notifications
            </h3>
            <span
                v-if="totalCount > 0"
                class="text-xs bg-red-500 text-white rounded-full px-1.5 py-0.5"
            >
                {{ totalCount }}
            </span>
        </div>

        <!-- Empty State -->
        <div
            v-if="totalCount === 0"
            class="py-6 flex flex-col items-center justify-center text-center"
        >
            <div class="size-10 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center mb-2">
                <Bell class="size-5 text-muted-foreground" />
            </div>
            <p class="text-xs text-muted-foreground">No notifications</p>
            <p class="text-xs text-muted-foreground/70">You're all caught up!</p>
        </div>

        <!-- Failed Scripts List -->
        <div v-else class="space-y-2">
            <div
                v-for="script in displayedScripts"
                :key="script.id"
                class="group/item relative flex items-start gap-2 p-3 rounded-xl bg-white dark:bg-card border border-border hover:border-red-500/50 hover:shadow-sm transition-all"
            >
                <div class="w-2 h-2 rounded-full mt-1.5 flex-shrink-0 bg-red-500" />
                <div class="flex-1 min-w-0 pr-0 group-hover/item:pr-8 transition-all">
                    <button
                        type="button"
                        class="block text-left text-xs font-medium text-foreground truncate hover:text-primary w-full"
                        @click="handleOpenDetails(script)"
                    >
                        {{ script.displayableName ?? 'Unknown operation' }}
                    </button>
                    <p v-if="script.serverName" class="text-xs text-muted-foreground truncate">
                        {{ script.serverName }}
                    </p>
                    <p v-if="script.errorMessage" class="text-xs text-red-500/80 truncate mt-0.5">
                        {{ script.errorMessage }}
                    </p>
                    <p class="text-xs text-muted-foreground/70 mt-0.5">
                        {{ script.createdAtHuman }}
                    </p>
                </div>
                <button
                    type="button"
                    class="absolute right-2 top-2 p-1 rounded-md text-muted-foreground hover:text-red-500 hover:bg-red-500/10 transition-colors opacity-0 group-hover/item:opacity-100"
                    @click.stop="handleDismiss(script)"
                >
                    <X class="w-3.5 h-3.5" />
                </button>
            </div>
        </div>
    </div>

    <Teleport to="body">
        <ScriptOutputModal ref="scriptOutputModal" />
    </Teleport>
</template>
