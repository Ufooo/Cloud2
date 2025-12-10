<script setup lang="ts">
import {
    index,
    show,
} from '@/actions/Nip/Server/Http/Controllers/ServerController';
import CustomVpsLogo from '@/components/icons/CustomVpsLogo.vue';
import DigitalOceanLogo from '@/components/icons/DigitalOceanLogo.vue';
import VultrLogo from '@/components/icons/VultrLogo.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { useServerActions, useServerAvatar } from '@/composables/useServer';
import AppLayout from '@/layouts/AppLayout.vue';
import ServerProvisioning from '@/pages/servers/partials/ServerProvisioning.vue';
import ServerStatusBadge from '@/pages/servers/partials/ServerStatusBadge.vue';
import type { BreadcrumbItem, Server } from '@/types';
import { ServerProvider, ServerStatus } from '@/types/server';
import { Link } from '@inertiajs/vue3';
import {
    Activity,
    Clock,
    Code,
    Copy,
    Globe,
    Key,
    LayoutDashboard,
    Settings,
    Users,
} from 'lucide-vue-next';
import type { Component, FunctionalComponent } from 'vue';
import { computed } from 'vue';

interface Props {
    server: Server;
}

const props = defineProps<Props>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: 'Servers',
        href: index.url(),
    },
    {
        title: props.server.name,
        href: show.url(props.server),
    },
]);

const { avatarColorClass, initials } = useServerAvatar(() => props.server);
const { copyIpAddress } = useServerActions(() => props.server);

const providerLogos: Record<ServerProvider, Component> = {
    [ServerProvider.DigitalOcean]: DigitalOceanLogo,
    [ServerProvider.Vultr]: VultrLogo,
    [ServerProvider.Custom]: CustomVpsLogo,
};

const providerLogo = computed(
    () => providerLogos[props.server.provider] ?? CustomVpsLogo,
);

const isProvisioning = computed(
    () => props.server.status === ServerStatus.Provisioning,
);

interface NavItem {
    title: string;
    href: string;
    routeName: string;
    icon: FunctionalComponent;
}

const navItems = computed<NavItem[]>(() => [
    {
        title: 'Overview',
        href: `/servers/${props.server.slug}`,
        routeName: 'servers.show',
        icon: LayoutDashboard,
    },
    {
        title: 'Unix Users',
        href: `/servers/${props.server.slug}/unix-users`,
        routeName: 'servers.unix-users',
        icon: Users,
    },
    {
        title: 'PHP',
        href: `/servers/${props.server.slug}/php`,
        routeName: 'servers.php',
        icon: Code,
    },
    {
        title: 'Scheduler',
        href: `/servers/${props.server.slug}/scheduler`,
        routeName: 'servers.scheduler',
        icon: Clock,
    },
    {
        title: 'Background Processes',
        href: `/servers/${props.server.slug}/processes`,
        routeName: 'servers.processes',
        icon: Activity,
    },
    {
        title: 'SSH Keys',
        href: `/servers/${props.server.slug}/ssh-keys`,
        routeName: 'servers.ssh-keys',
        icon: Key,
    },
    {
        title: 'Network',
        href: `/servers/${props.server.slug}/network`,
        routeName: 'servers.network',
        icon: Globe,
    },
    {
        title: 'Settings',
        href: `/servers/${props.server.slug}/settings`,
        routeName: 'servers.settings',
        icon: Settings,
    },
]);

function isActive(item: NavItem): boolean {
    return window.location.pathname === item.href;
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 overflow-hidden">
            <!-- Provisioning View -->
            <div v-if="isProvisioning" class="flex-1 overflow-auto p-4">
                <ServerProvisioning :server="server" />
            </div>

            <!-- Normal Server View -->
            <template v-else>
                <!-- Left Sidebar -->
                <aside class="flex w-64 shrink-0 flex-col border-r bg-muted/30">
                    <!-- Server Header -->
                    <div class="border-b p-4">
                        <div class="flex items-center gap-3">
                            <Avatar class="size-10 rounded-lg">
                                <AvatarFallback
                                    :class="avatarColorClass"
                                    class="rounded-lg text-sm font-medium text-white"
                                >
                                    {{ initials }}
                                </AvatarFallback>
                            </Avatar>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <h1
                                        class="truncate text-sm font-semibold"
                                        :title="server.name"
                                    >
                                        {{ server.name }}
                                    </h1>
                                </div>
                                <ServerStatusBadge
                                    :status="server.status"
                                    class="mt-1"
                                />
                            </div>
                        </div>

                        <div
                            class="mt-3 space-y-1 text-xs text-muted-foreground"
                        >
                            <div class="flex items-center gap-1.5">
                                <component
                                    :is="providerLogo"
                                    class="size-3.5 rounded"
                                />
                                <span>{{ server.displayableProvider }}</span>
                            </div>

                            <button
                                v-if="server.ipAddress"
                                class="flex items-center gap-1.5 hover:text-foreground"
                                @click="copyIpAddress"
                                :title="`Copy IP: ${server.ipAddress}`"
                            >
                                <Copy class="size-3.5" />
                                <span class="hover:underline">{{
                                    server.ipAddress
                                }}</span>
                            </button>

                            <div
                                v-if="server.displayablePhpVersion"
                                class="flex items-center gap-1.5"
                            >
                                <Code class="size-3.5" />
                                <span>{{ server.displayablePhpVersion }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Navigation -->
                    <nav class="flex-1 overflow-y-auto p-2">
                        <ul class="space-y-1">
                            <li v-for="item in navItems" :key="item.routeName">
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
                    <slot />
                </main>
            </template>
        </div>
    </AppLayout>
</template>
