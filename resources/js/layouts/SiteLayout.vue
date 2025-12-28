<script setup lang="ts">
import { index as composerIndex } from '@/actions/Nip/Composer/Http/Controllers/ComposerController';
import { indexForSite as databasesIndex } from '@/actions/Nip/Database/Http/Controllers/DatabaseController';
import { index as sitesIndex } from '@/actions/Nip/Site/Http/Controllers/SiteController';
import FailedScriptsAlert from '@/components/FailedScriptsAlert.vue';
import SiteTypeIcon from '@/components/icons/SiteTypeIcon.vue';
import ScriptOutputModal from '@/components/ScriptOutputModal.vue';
import Avatar from '@/components/shared/Avatar.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SiteStatusBadge from '@/pages/sites/partials/SiteStatusBadge.vue';
import type { BreadcrumbItem, ProvisionScriptData, Site } from '@/types';
import { Link, router } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import {
    Activity,
    Bell,
    Clock,
    Database,
    ExternalLink,
    Globe,
    LayoutDashboard,
    Lock,
    Package,
    Rocket,
    Settings,
    Share2,
} from 'lucide-vue-next';
import type { FunctionalComponent } from 'vue';
import { computed, ref } from 'vue';

interface Props {
    site: Site;
}

const props = defineProps<Props>();

// Real-time updates via WebSocket (useEcho handles lifecycle automatically)
useEcho(`sites.${props.site.id}`, '.SiteStatusUpdated', () => {
    router.reload({ only: ['site'], preserveScroll: true });
});

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: 'Sites',
        href: sitesIndex.url(),
    },
    {
        title: props.site.domain,
        href: `/sites/${props.site.slug}`,
    },
]);

interface NavItem {
    title: string;
    href: string;
    icon: FunctionalComponent;
}

const navItems = computed<NavItem[]>(() => [
    {
        title: 'Overview',
        href: `/sites/${props.site.slug}`,
        icon: LayoutDashboard,
    },
    {
        title: 'Deployments',
        href: `/sites/${props.site.slug}/deployments`,
        icon: Rocket,
    },
    {
        title: 'Databases',
        href: databasesIndex.url(props.site),
        icon: Database,
    },
    {
        title: 'Composer',
        href: composerIndex.url(props.site),
        icon: Package,
    },
    {
        title: 'Scheduler',
        href: `/sites/${props.site.slug}/scheduler`,
        icon: Clock,
    },
    {
        title: 'Background Processes',
        href: `/sites/${props.site.slug}/background-processes`,
        icon: Activity,
    },
    {
        title: 'Security',
        href: `/sites/${props.site.slug}/security`,
        icon: Lock,
    },
    {
        title: 'Redirects',
        href: `/sites/${props.site.slug}/redirects`,
        icon: Share2,
    },
    {
        title: 'Domains',
        href: `/sites/${props.site.slug}/domains`,
        icon: Globe,
    },
    {
        title: 'Notifications',
        href: `/sites/${props.site.slug}/notifications`,
        icon: Bell,
    },
    {
        title: 'Settings',
        href: `/sites/${props.site.slug}/settings`,
        icon: Settings,
    },
]);

function isActive(item: NavItem): boolean {
    const pathname = window.location.pathname;
    // Exact match for overview, prefix match for others
    if (item.href === `/sites/${props.site.slug}`) {
        return pathname === item.href;
    }
    return pathname.startsWith(item.href);
}

const scriptOutputModal = ref<InstanceType<typeof ScriptOutputModal> | null>(null);

function handleScriptClick(script: ProvisionScriptData) {
    scriptOutputModal.value?.open(script);
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 overflow-hidden">
            <!-- Left Sidebar -->
            <aside class="flex w-64 shrink-0 flex-col border-r bg-muted/30">
                <!-- Site Header -->
                <div class="border-b p-4">
                    <div class="flex items-center gap-3">
                        <Avatar
                            :name="site.domain"
                            :color="site.avatarColor ?? undefined"
                        />

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <h1
                                    class="truncate text-sm font-semibold"
                                    :title="site.domain"
                                >
                                    {{ site.domain }}
                                </h1>
                            </div>
                            <SiteStatusBadge :status="site.status" class="mt-1" />
                        </div>
                    </div>

                    <div class="mt-3 space-y-1 text-xs text-muted-foreground">
                        <div class="flex items-center gap-1.5">
                            <SiteTypeIcon :type="site.type" class="size-3.5" />
                            <span>{{ site.displayableType }}</span>
                        </div>

                        <a
                            v-if="site.url"
                            :href="site.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="flex items-center gap-1.5 hover:text-foreground"
                            :title="`Open ${site.url}`"
                        >
                            <ExternalLink class="size-3.5" />
                            <span class="truncate hover:underline">{{
                                site.url
                            }}</span>
                        </a>

                        <div
                            v-if="site.serverName"
                            class="flex items-center gap-1.5"
                        >
                            <Globe class="size-3.5" />
                            <span>{{ site.serverName }}</span>
                        </div>
                    </div>
                </div>

                <!-- Navigation -->
                <nav class="flex-1 overflow-y-auto p-2">
                    <ul class="space-y-1">
                        <li v-for="item in navItems" :key="item.href">
                            <Link
                                :href="item.href"
                                class="flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors"
                                :class="[
                                    isActive(item)
                                        ? 'bg-primary text-primary-foreground'
                                        : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                                ]"
                            >
                                <component :is="item.icon" class="size-4" />
                                {{ item.title }}
                            </Link>
                        </li>
                    </ul>
                </nav>
            </aside>

            <!-- Main Content -->
            <main class="flex-1 overflow-auto p-4">
                <FailedScriptsAlert
                    :site="site"
                    class="mb-4"
                    @script-click="handleScriptClick"
                />
                <slot />
            </main>
        </div>

        <ScriptOutputModal ref="scriptOutputModal" />
    </AppLayout>
</template>
