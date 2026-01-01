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
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import {
    Database,
    GitBranch,
    LayoutGrid,
    PanelTop,
    Server,
    Settings,
} from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const mainNavItems: NavItem[] = [
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
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
