<script setup lang="ts">
import { index as backgroundProcessIndex } from '@/actions/Nip/BackgroundProcess/Http/Controllers/BackgroundProcessController';
import { indexForServer as databasesIndex } from '@/actions/Nip/Database/Http/Controllers/DatabaseController';
import { index as networkIndex } from '@/actions/Nip/Network/Http/Controllers/NetworkController';
import { index as phpIndex } from '@/actions/Nip/Php/Http/Controllers/PhpController';
import { index as schedulerIndex } from '@/actions/Nip/Scheduler/Http/Controllers/ScheduledJobController';
import {
    index,
    settings,
    show,
} from '@/actions/Nip/Server/Http/Controllers/ServerController';
import { index as sitesIndex } from '@/actions/Nip/Site/Http/Controllers/ServerSiteController';
import { index as sshKeysIndex } from '@/actions/Nip/SshKey/Http/Controllers/SshKeyController';
import { index as unixUsersIndex } from '@/actions/Nip/UnixUser/Http/Controllers/UnixUserController';
import CustomVpsLogo from '@/components/icons/CustomVpsLogo.vue';
import DigitalOceanLogo from '@/components/icons/DigitalOceanLogo.vue';
import VultrLogo from '@/components/icons/VultrLogo.vue';
import Avatar from '@/components/shared/Avatar.vue';
import { useServerActions } from '@/composables/useServer';
import AppLayout from '@/layouts/AppLayout.vue';
import ServerProvisioning from '@/pages/servers/partials/ServerProvisioning.vue';
import ServerStatusBadge from '@/pages/servers/partials/ServerStatusBadge.vue';
import type { BreadcrumbItem, Server } from '@/types';
import { ServerProvider, ServerStatus } from '@/types';
import { Link } from '@inertiajs/vue3';
import {
    Activity,
    Clock,
    Code,
    Copy,
    Database,
    Globe,
    Key,
    LayoutDashboard,
    PanelTop,
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
    icon: FunctionalComponent;
}

const navItems = computed<NavItem[]>(() => [
    {
        title: 'Overview',
        href: show.url(props.server),
        icon: LayoutDashboard,
    },
    {
        title: 'Sites',
        href: sitesIndex.url(props.server),
        icon: PanelTop,
    },
    {
        title: 'Unix Users',
        href: unixUsersIndex.url(props.server),
        icon: Users,
    },
    {
        title: 'PHP',
        href: phpIndex.url(props.server),
        icon: Code,
    },
    {
        title: 'Scheduler',
        href: schedulerIndex.url(props.server),
        icon: Clock,
    },
    {
        title: 'Background Processes',
        href: backgroundProcessIndex.url(props.server),
        icon: Activity,
    },
    {
        title: 'SSH Keys',
        href: sshKeysIndex.url(props.server),
        icon: Key,
    },
    {
        title: 'Network',
        href: networkIndex.url(props.server),
        icon: Globe,
    },
    {
        title: 'Databases',
        href: databasesIndex.url(props.server),
        icon: Database,
    },
    {
        title: 'Settings',
        href: settings.url(props.server),
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
                            <Avatar :name="server.name" :color="server.avatarColor" />

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
                    <slot />
                </main>
            </template>
        </div>
    </AppLayout>
</template>
