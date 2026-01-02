<script setup lang="ts">
import { SitePackage, type Site } from '@/types/generated';
import { computed } from 'vue';

interface Props {
    site: Site;
}

const props = defineProps<Props>();

interface PackageBadgeInfo {
    key: string;
    label: string;
    enabled: boolean;
}

const packageLabels: Record<string, string> = {
    [SitePackage.Laravel]: 'Laravel',
    [SitePackage.Horizon]: 'Horizon',
    [SitePackage.Octane]: 'Octane',
    [SitePackage.Pulse]: 'Pulse',
    [SitePackage.Reverb]: 'Reverb',
    [SitePackage.Inertia]: 'Inertia',
    [SitePackage.InertiaSsr]: 'Inertia SSR',
    [SitePackage.Nightwatch]: 'Nightwatch',
    [SitePackage.Scheduler]: 'Scheduler',
    [SitePackage.Maintenance]: 'Maintenance',
};

const visiblePackages = computed<PackageBadgeInfo[]>(() => {
    if (!props.site.packages) {
        return [];
    }

    return Object.entries(props.site.packages)
        .filter(([, value]) => value !== null)
        .map(([key, enabled]) => ({
            key,
            label: packageLabels[key] ?? key,
            enabled: enabled === true,
        }))
        .sort((a, b) => {
            // Laravel first, then enabled packages, then disabled
            if (a.key === SitePackage.Laravel) return -1;
            if (b.key === SitePackage.Laravel) return 1;
            if (a.enabled !== b.enabled) return a.enabled ? -1 : 1;
            return a.label.localeCompare(b.label);
        });
});

const hasPackages = computed(() => visiblePackages.value.length > 0);
</script>

<template>
    <div v-if="hasPackages" class="mt-3 flex flex-wrap gap-1.5">
        <span
            v-for="pkg in visiblePackages"
            :key="pkg.key"
            class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[10px] font-medium ring-1 ring-inset"
            :class="[
                pkg.enabled
                    ? 'bg-green-50 text-green-700 ring-green-600/20 dark:bg-green-500/10 dark:text-green-400 dark:ring-green-500/20'
                    : 'bg-muted text-muted-foreground ring-border',
            ]"
            :title="pkg.enabled ? `${pkg.label} (Active)` : `${pkg.label} (Installed)`"
        >
            {{ pkg.label }}
        </span>
    </div>
</template>
