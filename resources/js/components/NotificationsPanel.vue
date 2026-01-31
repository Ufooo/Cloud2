<script setup lang="ts">
import {
    dismiss,
    show,
} from '@/actions/Nip/Server/Http/Controllers/ProvisionScriptController';
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

const scriptOutputModal = ref<InstanceType<typeof ScriptOutputModal> | null>(
    null,
);

function handleDismiss(script: FailedScript) {
    if (dismissingIds.value.has(script.id)) return;

    dismissingIds.value = new Set([...dismissingIds.value, script.id]);
    router.post(
        dismiss.url(script.id),
        {},
        {
            preserveScroll: true,
            onError: () => {
                const next = new Set(dismissingIds.value);
                next.delete(script.id);
                dismissingIds.value = next;
            },
        },
    );
}

async function handleOpenDetails(script: FailedScript) {
    const response = await fetch(show.url(script.id));
    const data: ProvisionScriptData = await response.json();

    scriptOutputModal.value?.open(data);
}
</script>

<template>
    <div class="rounded-xl border border-border bg-secondary p-3">
        <div class="mb-3 flex items-center justify-between">
            <h3
                class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
            >
                Notifications
            </h3>
            <span
                v-if="totalCount > 0"
                class="rounded-full bg-red-500 px-1.5 py-0.5 text-xs text-white"
            >
                {{ totalCount }}
            </span>
        </div>

        <!-- Empty State -->
        <div
            v-if="totalCount === 0"
            class="flex flex-col items-center justify-center py-6 text-center"
        >
            <div
                class="mb-2 flex size-10 items-center justify-center rounded-full bg-gray-200 dark:bg-gray-700"
            >
                <Bell class="size-5 text-muted-foreground" />
            </div>
            <p class="text-xs text-muted-foreground">No notifications</p>
            <p class="text-xs text-muted-foreground/70">
                You're all caught up!
            </p>
        </div>

        <!-- Failed Scripts List -->
        <div v-else class="space-y-2">
            <div
                v-for="script in displayedScripts"
                :key="script.id"
                class="group/item relative flex items-start gap-2 rounded-xl border border-border bg-white p-3 transition-all hover:border-red-500/50 hover:shadow-sm dark:bg-card"
            >
                <div
                    class="mt-1.5 h-2 w-2 flex-shrink-0 rounded-full bg-red-500"
                />
                <div
                    class="min-w-0 flex-1 pr-0 transition-all group-hover/item:pr-8"
                >
                    <button
                        type="button"
                        class="block w-full truncate text-left text-xs font-medium text-foreground hover:text-primary"
                        @click="handleOpenDetails(script)"
                    >
                        {{ script.displayableName ?? 'Unknown operation' }}
                    </button>
                    <p
                        v-if="script.serverName"
                        class="truncate text-xs text-muted-foreground"
                    >
                        {{ script.serverName }}
                    </p>
                    <p
                        v-if="script.errorMessage"
                        class="mt-0.5 truncate text-xs text-red-500/80"
                    >
                        {{ script.errorMessage }}
                    </p>
                    <p class="mt-0.5 text-xs text-muted-foreground/70">
                        {{ script.createdAtHuman }}
                    </p>
                </div>
                <button
                    type="button"
                    class="absolute top-2 right-2 rounded-md p-1 text-muted-foreground opacity-0 transition-colors group-hover/item:opacity-100 hover:bg-red-500/10 hover:text-red-500"
                    @click.stop="handleDismiss(script)"
                >
                    <X class="h-3.5 w-3.5" />
                </button>
            </div>
        </div>
    </div>

    <Teleport to="body">
        <ScriptOutputModal ref="scriptOutputModal" />
    </Teleport>
</template>
