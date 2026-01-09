<script setup lang="ts">
import { index as databasesIndex } from '@/actions/Nip/Database/Http/Controllers/DatabaseController';
import { index as serversIndex } from '@/actions/Nip/Server/Http/Controllers/ServerController';
import { index as sitesIndex } from '@/actions/Nip/Site/Http/Controllers/SiteController';
import { index as sourceControlIndex } from '@/actions/Nip/SourceControl/Http/Controllers/SourceControlController';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { AppPageProps, NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    Database,
    GitBranch,
    Layers,
    LayoutGrid,
    PanelTop,
    Server,
    Settings,
} from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';
import NotificationsPanel from '@/components/NotificationsPanel.vue';

const page = usePage<AppPageProps>();
const counts = computed(() => page.props.counts);

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Servers',
        href: serversIndex.url(),
        icon: Server,
    },
    {
        title: 'Sites',
        href: sitesIndex.url(),
        icon: PanelTop,
        badge: counts.value.sites,
    },
    {
        title: 'Databases',
        href: databasesIndex.url(),
        icon: Database,
    },
    {
        title: 'Settings',
        href: sourceControlIndex.url(),
        icon: Settings,
        items: [
            {
                title: 'Source Control',
                href: sourceControlIndex.url(),
                icon: GitBranch,
            },
        ],
    },
]);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton as-child>
                        <Link :href="dashboard()" class="p-2!">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" label="Platform" :label-icon="Layers" />
        </SidebarContent>

        <SidebarFooter>
            <NotificationsPanel />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
