<script setup lang="ts">
import {
    SidebarGroup,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { urlIsActive } from '@/lib/utils';
import type { NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import type { Component } from 'vue';

defineProps<{
    items: NavItem[];
    label?: string;
    labelIcon?: Component;
}>();

const page = usePage();
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <div v-if="label" class="flex items-center gap-1.5 px-2 py-2">
            <component
                v-if="labelIcon"
                :is="labelIcon"
                class="size-3 text-muted-foreground/50"
            />
            <span
                class="text-[10px] font-medium tracking-wider text-muted-foreground/60 uppercase"
                >{{ label }}</span
            >
        </div>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="urlIsActive(item.href, page.url)"
                    :tooltip="item.title"
                    class="transition-colors"
                >
                    <Link :href="item.href" class="flex items-center gap-3">
                        <component :is="item.icon" class="size-5 shrink-0" />
                        <span class="truncate">{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
                <SidebarMenuBadge v-if="item.badge" :class="item.badgeClass">
                    {{ item.badge }}
                </SidebarMenuBadge>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
