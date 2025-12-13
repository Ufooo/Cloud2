<script setup lang="ts">
import { Button } from '@/components/ui/button';
import type { PaginationMeta } from '@/types/pagination';
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    meta: PaginationMeta;
}

const props = defineProps<Props>();

const showPagination = computed(() => props.meta.last_page > 1);

const pageLinks = computed(() =>
    props.meta.links.filter(
        (link) =>
            link.label !== '&laquo; Previous' && link.label !== 'Next &raquo;',
    ),
);

const prevUrl = computed(
    () => props.meta.links.find((l) => l.label === '&laquo; Previous')?.url,
);

const nextUrl = computed(
    () => props.meta.links.find((l) => l.label === 'Next &raquo;')?.url,
);
</script>

<template>
    <div
        v-if="showPagination"
        class="flex items-center justify-between px-2 py-4"
    >
        <p class="text-sm text-muted-foreground">
            Showing {{ meta.from }} to {{ meta.to }} of {{ meta.total }} results
        </p>

        <div class="flex items-center gap-1">
            <Button
                variant="outline"
                size="icon"
                class="size-8"
                :disabled="!prevUrl"
                as-child
            >
                <Link v-if="prevUrl" :href="prevUrl" preserve-scroll>
                    <ChevronLeft class="size-4" />
                </Link>
                <span v-else><ChevronLeft class="size-4" /></span>
            </Button>

            <template v-for="link in pageLinks" :key="link.label">
                <Button
                    v-if="link.url"
                    :variant="link.active ? 'default' : 'outline'"
                    size="icon"
                    class="size-8"
                    as-child
                >
                    <Link :href="link.url" preserve-scroll>
                        {{ link.label }}
                    </Link>
                </Button>
                <span
                    v-else
                    class="flex size-8 items-center justify-center text-sm text-muted-foreground"
                >
                    {{ link.label }}
                </span>
            </template>

            <Button
                variant="outline"
                size="icon"
                class="size-8"
                :disabled="!nextUrl"
                as-child
            >
                <Link v-if="nextUrl" :href="nextUrl" preserve-scroll>
                    <ChevronRight class="size-4" />
                </Link>
                <span v-else><ChevronRight class="size-4" /></span>
            </Button>
        </div>
    </div>
</template>
