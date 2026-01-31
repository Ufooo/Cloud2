<script setup lang="ts">
import { index as composerIndex } from '@/actions/Nip/Composer/Http/Controllers/ComposerController';
import { indexForSite as databasesIndex } from '@/actions/Nip/Database/Http/Controllers/DatabaseController';
import { show as securityMonitorShow } from '@/actions/Nip/SecurityMonitor/Http/Controllers/SecurityMonitorController';
import {
    deploy,
    index as sitesIndex,
} from '@/actions/Nip/Site/Http/Controllers/SiteController';
import FailedScriptsAlert from '@/components/FailedScriptsAlert.vue';
import SiteTypeIcon from '@/components/icons/SiteTypeIcon.vue';
import ScriptOutputModal from '@/components/ScriptOutputModal.vue';
import Avatar from '@/components/shared/Avatar.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import PackageBadges from '@/pages/sites/partials/PackageBadges.vue';
import SiteStatusBadge from '@/pages/sites/partials/SiteStatusBadge.vue';
import type { BreadcrumbItem, ProvisionScriptData, Site } from '@/types';
import { SiteStatus, SiteType, SslStatus } from '@/types';
import { Link, router } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import {
    Activity,
    Bell,
    Bug,
    Clock,
    Database,
    ExternalLink,
    GitBranch,
    Globe,
    Layers,
    LayoutDashboard,
    Lock,
    Package,
    Rocket,
    Server,
    Settings,
    Share2,
    User,
} from 'lucide-vue-next';
import type { FunctionalComponent } from 'vue';
import { computed, ref } from 'vue';

interface Props {
    site: Site;
    showSidebar?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    showSidebar: true,
});

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
    badge?: number;
    badgeClass?: string;
}

const isWordPress = computed(() => props.site.type === SiteType.WordPress);
const isHtml = computed(() => props.site.type === SiteType.Html);

const navItems = computed<NavItem[]>(() =>
    [
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
        !isWordPress.value && !isHtml.value
            ? {
                  title: 'Composer',
                  href: composerIndex.url(props.site),
                  icon: Package,
              }
            : null,
        {
            title: 'Scheduler',
            href: `/sites/${props.site.slug}/scheduler`,
            icon: Clock,
        },
        !isWordPress.value && !isHtml.value
            ? {
                  title: 'Background Processes',
                  href: `/sites/${props.site.slug}/background-processes`,
                  icon: Activity,
              }
            : null,
        {
            title: 'Security',
            href: `/sites/${props.site.slug}/security`,
            icon: Lock,
        },
        {
            title: 'Security Monitor',
            href: securityMonitorShow.url(props.site),
            icon: Bug,
            badge: props.site.securityIssuesCount || undefined,
            badgeClass:
                'bg-orange-100 text-orange-600 border border-orange-200',
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
    ].filter((item): item is NavItem => item !== null),
);

function isActive(item: NavItem): boolean {
    const pathname = window.location.pathname;

    if (item.href === `/sites/${props.site.slug}`) {
        return pathname === item.href;
    }

    return (
        pathname === item.href ||
        pathname.startsWith(item.href + '/') ||
        pathname.startsWith(item.href + '?')
    );
}

const scriptOutputModal = ref<InstanceType<typeof ScriptOutputModal> | null>(
    null,
);

function handleScriptClick(script: ProvisionScriptData) {
    scriptOutputModal.value?.open(script);
}

const isInstalled = computed(() => props.site.status === SiteStatus.Installed);
const isDeployingInProgress = ref(false);

const sslIndicatorClass = computed(() => {
    switch (props.site.sslStatus) {
        case SslStatus.Active:
            return 'bg-emerald-500';
        case SslStatus.Expiring:
            return 'bg-orange-500';
        case SslStatus.Expired:
            return 'bg-red-500';
        default:
            return null;
    }
});

const showSslIndicator = computed(
    () => props.site.sslStatus && props.site.sslStatus !== SslStatus.None,
);

function triggerDeploy() {
    if (isDeployingInProgress.value || !props.site.can?.deploy) return;

    isDeployingInProgress.value = true;
    router.post(
        deploy.url(props.site),
        {},
        {
            onFinish: () => {
                isDeployingInProgress.value = false;
            },
        },
    );
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col overflow-hidden">
            <!-- Full Width Header -->
            <header
                class="shrink-0 bg-gradient-to-b from-muted/40 to-background"
            >
                <div class="flex items-center justify-between px-8 py-5">
                    <!-- Left: Avatar + Essential Info -->
                    <div class="flex items-center gap-4">
                        <!-- Avatar with status -->
                        <div class="relative">
                            <Avatar
                                :name="site.domain"
                                :color="site.avatarColor ?? undefined"
                                size="lg"
                            />
                            <div
                                v-if="showSslIndicator"
                                class="absolute -right-0.5 -bottom-0.5 flex h-4 w-4 items-center justify-center rounded-full ring-2 ring-background"
                                :class="sslIndicatorClass"
                            >
                                <Lock class="h-2 w-2 text-white" />
                            </div>
                        </div>

                        <!-- Domain + Type + PHP -->
                        <div>
                            <div class="flex items-center gap-3">
                                <h1 class="text-lg font-bold tracking-tight">
                                    {{ site.domain }}
                                </h1>
                                <SiteStatusBadge :status="site.status" />
                            </div>
                            <div
                                class="mt-0.5 flex items-center gap-2 text-sm text-muted-foreground"
                            >
                                <div class="flex items-center gap-1">
                                    <SiteTypeIcon
                                        :type="site.type"
                                        class="size-3.5"
                                    />
                                    <span>{{ site.displayableType }}</span>
                                </div>
                                <span
                                    v-if="site.phpVersionLabel"
                                    class="text-border"
                                    >•</span
                                >
                                <span
                                    v-if="site.phpVersionLabel"
                                    class="font-medium text-emerald-600 dark:text-emerald-400"
                                >
                                    PHP {{ site.phpVersionLabel }}
                                </span>
                                <span v-if="site.url" class="text-border"
                                    >•</span
                                >
                                <a
                                    v-if="site.url"
                                    :href="site.url"
                                    target="_blank"
                                    class="inline-flex items-center gap-1 transition-colors hover:text-foreground"
                                >
                                    <ExternalLink class="size-3" />
                                    {{ site.url }}
                                </a>
                            </div>
                            <PackageBadges :site="site" class="mt-2" />
                        </div>
                    </div>

                    <!-- Right: Deploy Button -->
                    <Button
                        v-if="site.can?.deploy && isInstalled"
                        class="shadow-sm"
                        :disabled="
                            site.deployStatus === 'deploying' ||
                            isDeployingInProgress
                        "
                        @click="triggerDeploy"
                    >
                        <Rocket class="mr-2 size-4" />
                        {{
                            isDeployingInProgress ? 'Starting...' : 'Deploy Now'
                        }}
                    </Button>
                </div>

                <!-- Connected Info Boxes Row -->
                <div v-if="showSidebar" class="flex border-y bg-transparent">
                    <!-- Server Box -->
                    <div class="flex-1 border-r px-6 py-3">
                        <div
                            class="flex items-center gap-2 text-xs font-medium tracking-wider text-muted-foreground uppercase"
                        >
                            <Server class="size-3.5" />
                            Server
                        </div>
                        <div class="mt-1 text-sm font-medium">
                            {{ site.serverName ?? 'N/A' }}
                        </div>
                    </div>

                    <!-- Unix User Box -->
                    <div class="flex-1 border-r px-6 py-3">
                        <div
                            class="flex items-center gap-2 text-xs font-medium tracking-wider text-muted-foreground uppercase"
                        >
                            <User class="size-3.5" />
                            Unix User
                        </div>
                        <div class="mt-1">
                            <code class="font-mono text-sm">{{
                                site.user
                            }}</code>
                        </div>
                    </div>

                    <!-- Repository Box -->
                    <div
                        v-if="site.repository"
                        class="flex-1 border-r px-6 py-3"
                    >
                        <div
                            class="flex items-center gap-2 text-xs font-medium tracking-wider text-muted-foreground uppercase"
                        >
                            <GitBranch class="size-3.5" />
                            Repository
                        </div>
                        <div
                            class="mt-1 flex items-center gap-2 text-sm font-medium"
                        >
                            {{ site.repository }}
                            <Badge
                                v-if="site.branch"
                                variant="secondary"
                                class="px-1.5 py-0 font-mono text-[10px]"
                            >
                                {{ site.branch }}
                            </Badge>
                        </div>
                    </div>

                    <!-- Deployment Mode Box -->
                    <div class="flex-1 border-r px-6 py-3">
                        <div
                            class="flex items-center gap-2 text-xs font-medium tracking-wider text-muted-foreground uppercase"
                        >
                            <Layers class="size-3.5" />
                            Deployment
                        </div>
                        <div class="mt-1">
                            <Badge
                                v-if="site.zeroDowntime"
                                class="border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400"
                            >
                                Zero-Downtime
                            </Badge>
                            <Badge
                                v-else
                                variant="outline"
                                class="text-muted-foreground"
                            >
                                Standard
                            </Badge>
                        </div>
                    </div>

                    <!-- Last Deploy Box -->
                    <div class="flex-1 px-6 py-3">
                        <div
                            class="flex items-center gap-2 text-xs font-medium tracking-wider text-muted-foreground uppercase"
                        >
                            <Clock class="size-3.5" />
                            Last Deploy
                        </div>
                        <div
                            class="mt-1 flex items-center gap-2 text-sm font-medium"
                        >
                            <span>{{
                                site.lastDeployedAtHuman ?? 'Never'
                            }}</span>
                            <Badge
                                v-if="site.deployStatus"
                                :variant="
                                    site.deployStatusBadgeVariant ?? 'secondary'
                                "
                            >
                                {{ site.displayableDeployStatus }}
                            </Badge>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Sidebar + Content below header -->
            <div class="flex flex-1 overflow-hidden">
                <!-- Left Sidebar -->
                <aside
                    v-if="showSidebar"
                    class="flex w-64 shrink-0 flex-col bg-muted/30"
                >
                    <nav class="flex-1 overflow-y-auto p-2 pt-3">
                        <ul class="space-y-0.5">
                            <li v-for="item in navItems" :key="item.href">
                                <Link
                                    :href="item.href"
                                    prefetch
                                    class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm transition-colors"
                                    :class="[
                                        isActive(item)
                                            ? 'bg-primary font-medium text-primary-foreground'
                                            : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                                    ]"
                                >
                                    <component :is="item.icon" class="size-4" />
                                    <span class="flex-1">{{ item.title }}</span>
                                    <span
                                        v-if="item.badge"
                                        class="flex h-5 min-w-5 items-center justify-center rounded-md px-1.5 text-xs font-medium tabular-nums"
                                        :class="item.badgeClass"
                                    >
                                        {{ item.badge }}
                                    </span>
                                </Link>
                            </li>
                        </ul>
                    </nav>
                </aside>

                <!-- Content -->
                <main class="flex-1 overflow-auto p-6">
                    <FailedScriptsAlert
                        :site="site"
                        class="mb-4"
                        @script-click="handleScriptClick"
                    />
                    <slot />
                </main>
            </div>
        </div>

        <ScriptOutputModal ref="scriptOutputModal" />
    </AppLayout>
</template>
