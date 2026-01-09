<script setup lang="ts">
/**
 * Sites Design V8 - Light theme with unified table/list box
 * All sites in one box, separated by dividers
 */
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import Avatar from '@/components/shared/Avatar.vue';
import { h } from 'vue';
import {
    Server,
    Globe,
    Database,
    Settings,
    Search,
    Plus,
    LayoutDashboard,
    ExternalLink,
    MoreHorizontal,
    User,
    Clock,
    ChevronLeft,
    ChevronRight,
    Check,
    Trash2,
    Bell,
} from 'lucide-vue-next';

const GithubIcon = {
    props: { class: String },
    setup(props: { class?: string }) {
        return () => h('svg', { viewBox: '0 0 24 24', fill: 'currentColor', class: props.class }, [
            h('path', { d: 'M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z' })
        ]);
    }
};

const GitlabIcon = {
    props: { class: String },
    setup(props: { class?: string }) {
        return () => h('svg', { viewBox: '0 0 24 24', fill: 'currentColor', class: props.class }, [
            h('path', { d: 'm23.6 9.593-.033-.086L20.3.98a.851.851 0 0 0-.336-.405.875.875 0 0 0-.994.053.855.855 0 0 0-.29.44l-2.204 6.748H7.525L5.32 1.067a.855.855 0 0 0-.29-.44.875.875 0 0 0-.994-.052.851.851 0 0 0-.336.405L.433 9.507l-.032.086a6.066 6.066 0 0 0 2.012 7.01l.01.008.028.02 4.984 3.73 2.466 1.867 1.503 1.136a1.014 1.014 0 0 0 1.224 0l1.503-1.136 2.466-1.866 5.012-3.752.012-.01a6.068 6.068 0 0 0 2.009-7.007' })
        ]);
    }
};

const BitbucketIcon = {
    props: { class: String },
    setup(props: { class?: string }) {
        return () => h('svg', { viewBox: '0 0 24 24', fill: 'currentColor', class: props.class }, [
            h('path', { d: 'M.778 1.211a.768.768 0 0 0-.768.892l3.263 19.81c.084.5.515.868 1.022.873H19.95a.772.772 0 0 0 .77-.646l3.27-20.03a.768.768 0 0 0-.768-.891zM14.52 15.53H9.522L8.17 8.466h7.561z' })
        ]);
    }
};

const WordPressIcon = {
    props: { class: String },
    setup(props: { class?: string }) {
        return () => h('svg', { viewBox: '0 0 24 24', fill: 'currentColor', class: props.class }, [
            h('path', { d: 'M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zM3.443 12c0-1.584.421-3.07 1.157-4.351l6.37 17.458A8.567 8.567 0 0 1 3.443 12zm8.557 8.557c-.882 0-1.734-.14-2.53-.396l2.687-7.806 2.753 7.544c.018.044.04.085.063.124a8.513 8.513 0 0 1-2.973.534zm1.171-12.593c.539-.028 1.024-.085 1.024-.085.482-.057.425-.765-.057-.737 0 0-1.449.114-2.384.114-.879 0-2.356-.114-2.356-.114-.483-.028-.539.708-.057.737 0 0 .457.057.94.085l1.396 3.826-1.961 5.878-3.264-9.704c.539-.028 1.024-.085 1.024-.085.483-.057.426-.765-.057-.737 0 0-1.449.114-2.384.114-.168 0-.366-.004-.578-.01A8.54 8.54 0 0 1 12 3.443c2.254 0 4.312.87 5.85 2.293-.037-.002-.073-.007-.111-.007-.879 0-1.502.765-1.502 1.587 0 .737.425 1.36.879 2.097.34.595.737 1.36.737 2.464 0 .765-.293 1.653-.68 2.891l-.892 2.979-3.11-9.247z' })
        ]);
    }
};

const navItems = [
    { icon: LayoutDashboard, label: 'Dashboard', active: false },
    { icon: Server, label: 'Servers', count: 12 },
    { icon: Globe, label: 'Sites', count: 34, active: true },
    { icon: Database, label: 'Databases', count: 8 },
    { icon: Settings, label: 'Settings' },
];

const sites = [
    { domain: 'api.netipar.com', server: 'Production EU', unixUser: 'netipar', php: '8.4', source: 'github', repository: 'netipar/api', branch: 'main', lastDeploy: '2 hours ago', deploySuccess: true, avatarColor: 'blue' as const },
    { domain: 'app.netipar.com', server: 'Production EU', unixUser: 'netipar', php: '8.3', source: 'github', repository: 'netipar/app', branch: 'main', lastDeploy: '1 day ago', deploySuccess: true, avatarColor: 'green' as const },
    { domain: 'staging.netipar.com', server: 'Staging', unixUser: 'staging', php: '8.2', source: 'gitlab', repository: 'netipar/app', branch: 'develop', lastDeploy: '5 hours ago', deploySuccess: true, avatarColor: 'orange' as const },
    { domain: 'blog.example.hu', server: 'Production US', unixUser: 'bloguser', php: '8.1', source: 'bitbucket', repository: 'company/blog', branch: 'production', lastDeploy: '30 min ago', deploySuccess: false, avatarColor: 'red' as const },
    { domain: 'docs.netipar.com', server: 'Production EU', unixUser: 'docs', php: '7.4', source: 'github', repository: 'netipar/documentation', branch: 'main', lastDeploy: '3 days ago', deploySuccess: true, avatarColor: 'purple' as const },
    { domain: 'shop.example.hu', server: 'Production EU', unixUser: 'shopuser', php: '8.2', source: 'wordpress', repository: null, branch: null, lastDeploy: '1 week ago', deploySuccess: true, avatarColor: 'cyan' as const },
];

function getPhpColor(version: string): string {
    const colors: Record<string, string> = {
        '8.4': 'bg-emerald-50 text-emerald-600 border border-emerald-200',
        '8.3': 'bg-cyan-50 text-cyan-600 border border-cyan-200',
        '8.2': 'bg-blue-50 text-blue-600 border border-blue-200',
        '8.1': 'bg-amber-50 text-amber-600 border border-amber-200',
        '8.0': 'bg-orange-50 text-orange-600 border border-orange-200',
        '7.4': 'bg-red-50 text-red-600 border border-red-200',
    };
    return colors[version] || 'bg-gray-50 text-gray-600 border border-gray-200';
}

</script>

<template>
    <div class="min-h-screen bg-[#E7EEEF] p-4" style="font-family: 'Poppins', sans-serif; font-weight: 500;">
        <div class="flex min-h-[calc(100vh-2rem)] gap-4">
            <!-- Sidebar - No background -->
            <aside class="w-64">
                <div class="h-full py-4 flex flex-col">
                    <!-- Logo -->
                    <div class="flex items-center gap-3 px-2 mb-8">
                        <div class="w-10 h-10 rounded-xl bg-[#22C55E] flex items-center justify-center">
                            <Globe class="w-5 h-5 text-white" />
                        </div>
                        <div>
                            <h1 class="font-semibold text-gray-900">NETipar Cloud</h1>
                            <p class="text-xs text-gray-500">Server Management</p>
                        </div>
                    </div>

                    <!-- Navigation -->
                    <nav class="flex-1 space-y-1">
                        <a
                            v-for="item in navItems"
                            :key="item.label"
                            href="#"
                            :class="[
                                'flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all',
                                item.active
                                    ? 'bg-[#22C55E]/15 text-[#16A34A] border-l-[3px] border-l-[#16A34A] pl-[9px]'
                                    : 'text-gray-600 hover:bg-[#F7F8F9] hover:text-gray-900'
                            ]"
                        >
                            <component :is="item.icon" class="w-5 h-5" />
                            <span class="flex-1">{{ item.label }}</span>
                            <Badge v-if="item.count" :class="item.active ? 'bg-emerald-50 text-emerald-600 border border-emerald-200' : 'bg-gray-100 text-gray-600 border border-gray-200'">
                                {{ item.count }}
                            </Badge>
                        </a>
                    </nav>

                    <!-- Notifications - Empty State -->
                    <div class="mt-4 rounded-xl bg-[#F7F8F9] border border-gray-300 p-3">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Notifications</h3>
                        </div>
                        <div class="py-6 flex flex-col items-center justify-center text-center">
                            <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center mb-2">
                                <Bell class="w-5 h-5 text-gray-400" />
                            </div>
                            <p class="text-xs text-gray-500">No notifications</p>
                            <p class="text-xs text-gray-400">You're all caught up!</p>
                        </div>
                    </div>

                    <!-- Notifications - With Items -->
                    <div class="mt-4 rounded-xl bg-[#F7F8F9] border border-gray-300 p-3">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Notifications</h3>
                            <span class="text-xs bg-red-500 text-white rounded-full px-1.5 py-0.5">3</span>
                        </div>
                        <div class="space-y-2">
                            <div class="group relative flex items-start gap-2 p-3 rounded-xl bg-white border border-gray-200 hover:border-[#22C55E]/50 hover:shadow-sm transition-all cursor-pointer">
                                <div class="w-2 h-2 rounded-full bg-[#22C55E] mt-1.5 flex-shrink-0"></div>
                                <div class="flex-1 min-w-0 pr-0 group-hover:pr-14 transition-all">
                                    <p class="text-xs text-gray-700 truncate">Deploy completed on api.netipar.com</p>
                                    <p class="text-xs text-gray-400">2 min ago</p>
                                </div>
                                <div class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button class="p-1 rounded-md text-gray-400 hover:text-[#16A34A] hover:bg-[#22C55E]/10 transition-colors">
                                        <Check class="w-3.5 h-3.5" />
                                    </button>
                                    <button class="p-1 rounded-md text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors">
                                        <Trash2 class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </div>
                            <div class="group relative flex items-start gap-2 p-3 rounded-xl bg-white border border-gray-200 hover:border-[#22C55E]/50 hover:shadow-sm transition-all cursor-pointer">
                                <div class="w-2 h-2 rounded-full bg-red-500 mt-1.5 flex-shrink-0"></div>
                                <div class="flex-1 min-w-0 pr-0 group-hover:pr-14 transition-all">
                                    <p class="text-xs text-gray-700 truncate">Deploy failed on blog.example.hu</p>
                                    <p class="text-xs text-gray-400">30 min ago</p>
                                </div>
                                <div class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button class="p-1 rounded-md text-gray-400 hover:text-[#16A34A] hover:bg-[#22C55E]/10 transition-colors">
                                        <Check class="w-3.5 h-3.5" />
                                    </button>
                                    <button class="p-1 rounded-md text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors">
                                        <Trash2 class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </div>
                            <div class="group relative flex items-start gap-2 p-3 rounded-xl bg-white border border-gray-200 hover:border-[#22C55E]/50 hover:shadow-sm transition-all cursor-pointer">
                                <div class="w-2 h-2 rounded-full bg-amber-500 mt-1.5 flex-shrink-0"></div>
                                <div class="flex-1 min-w-0 pr-0 group-hover:pr-14 transition-all">
                                    <p class="text-xs text-gray-700 truncate">SSL certificate expiring soon</p>
                                    <p class="text-xs text-gray-400">1 hour ago</p>
                                </div>
                                <div class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button class="p-1 rounded-md text-gray-400 hover:text-[#16A34A] hover:bg-[#22C55E]/10 transition-colors">
                                        <Check class="w-3.5 h-3.5" />
                                    </button>
                                    <button class="p-1 rounded-md text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors">
                                        <Trash2 class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </div>
                        </div>
                        <button class="w-full mt-3 py-1.5 text-xs text-[#16A34A] hover:text-[#22C55E] font-medium transition-colors">
                            View all notifications
                        </button>
                    </div>

                    <!-- User -->
                    <div class="mt-4 pt-4 border-t border-gray-300/50">
                        <div class="flex items-center gap-3 px-2">
                            <div class="w-9 h-9 rounded-full bg-[#22C55E] flex items-center justify-center text-white font-medium text-sm">
                                HD
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">Hornig Dániel</p>
                                <p class="text-xs text-gray-500 truncate">Admin</p>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- Main Content -->
            <main class="flex-1">
                <div class="h-full rounded-2xl bg-[#F7F8F9] border border-gray-300 p-6 overflow-auto">
                    <!-- Breadcrumb -->
                    <nav class="flex items-center gap-2 text-sm mb-6">
                        <a href="#" class="text-gray-400 hover:text-[#16A34A] transition-colors">
                            <LayoutDashboard class="w-4 h-4" />
                        </a>
                        <span class="text-gray-300">/</span>
                        <span class="text-gray-600 font-medium">Sites</span>
                    </nav>

                    <!-- Header -->
                    <div class="flex items-center justify-between mb-8">
                        <div>
                            <h2 class="text-2xl font-semibold text-gray-900">Sites</h2>
                            <p class="text-gray-500">Manage your websites and deployments</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="relative">
                                <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                                <Input
                                    placeholder="Search sites..."
                                    class="w-64 pl-9 bg-white border-gray-200 text-gray-900 placeholder:text-gray-400 focus:border-[#22C55E] focus:ring-[#22C55E]/20"
                                />
                            </div>
                            <Button class="bg-[#22C55E] hover:bg-[#16A34A] text-white border-0">
                                <Plus class="w-4 h-4 mr-2" />
                                New Site
                            </Button>
                        </div>
                    </div>

                    <!-- Sites List - Unified Box -->
                    <div class="rounded-xl bg-white border border-gray-200 overflow-hidden">
                        <div
                            v-for="(site, index) in sites"
                            :key="site.domain"
                            :class="[
                                'flex items-center gap-4 px-4 py-4 hover:bg-gray-50 transition-colors',
                                index !== sites.length - 1 ? 'border-b border-gray-200' : ''
                            ]"
                        >
                            <!-- Icon -->
                            <Avatar :name="site.domain" :color="site.avatarColor" size="md" />

                            <!-- Main Info -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <h4 class="font-semibold text-gray-900">{{ site.domain }}</h4>
                                    <ExternalLink class="w-3.5 h-3.5 text-gray-400 cursor-pointer hover:text-[#22C55E]" />
                                </div>
                                <div class="flex items-center gap-4 text-sm">
                                    <span class="text-gray-500">
                                        <Server class="w-3.5 h-3.5 inline mr-1" />
                                        {{ site.server }}
                                    </span>
                                    <span class="text-gray-500">
                                        <User class="w-3.5 h-3.5 inline mr-1" />
                                        {{ site.unixUser }}
                                    </span>
                                    <Badge :class="[getPhpColor(site.php), 'text-xs font-medium']">
                                        PHP {{ site.php }}
                                    </Badge>
                                </div>
                            </div>

                            <!-- Repository Info -->
                            <div class="flex items-center gap-3 px-3 py-1.5 rounded-md bg-gray-100 border border-gray-200">
                                <template v-if="site.source === 'wordpress'">
                                    <WordPressIcon class="w-5 h-5 text-[#21759b]" />
                                    <span class="text-sm text-gray-600">WordPress</span>
                                </template>
                                <template v-else>
                                    <GithubIcon v-if="site.source === 'github'" class="w-5 h-5 text-gray-700" />
                                    <GitlabIcon v-else-if="site.source === 'gitlab'" class="w-5 h-5 text-orange-500" />
                                    <BitbucketIcon v-else class="w-5 h-5 text-blue-500" />
                                    <div class="text-sm">
                                        <span class="text-gray-600">{{ site.repository }}</span>
                                        <span class="text-gray-400 mx-1">:</span>
                                        <span class="text-[#22C55E] font-medium">{{ site.branch }}</span>
                                    </div>
                                </template>
                            </div>

                            <!-- Last Deploy -->
                            <div class="text-right flex-shrink-0 w-28">
                                <div class="text-xs text-gray-400 mb-1">Last deploy</div>
                                <div class="flex items-center justify-end gap-1 text-sm text-gray-600">
                                    <Clock class="w-3.5 h-3.5" />
                                    {{ site.lastDeploy }}
                                </div>
                            </div>

                            <!-- Actions -->
                            <Button variant="ghost" size="icon" class="h-9 w-9 text-gray-400 hover:text-gray-600 hover:bg-gray-100 flex-shrink-0">
                                <MoreHorizontal class="w-4 h-4" />
                            </Button>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div class="flex items-center justify-between mt-6">
                        <p class="text-sm text-gray-500">Showing <span class="font-medium text-gray-700">1-6</span> of <span class="font-medium text-gray-700">480</span> sites</p>
                        <div class="flex items-center gap-1">
                            <Button variant="outline" size="icon" class="h-9 w-9 border-gray-200 text-gray-400" disabled>
                                <ChevronLeft class="w-4 h-4" />
                            </Button>
                            <Button variant="outline" size="sm" class="h-9 w-9 bg-[#22C55E] text-white border-[#22C55E] hover:bg-[#16A34A]">1</Button>
                            <Button variant="outline" size="sm" class="h-9 w-9 border-gray-200 text-gray-600 hover:bg-gray-50">2</Button>
                            <Button variant="outline" size="sm" class="h-9 w-9 border-gray-200 text-gray-600 hover:bg-gray-50">3</Button>
                            <span class="px-2 text-gray-400">...</span>
                            <Button variant="outline" size="sm" class="h-9 w-9 border-gray-200 text-gray-600 hover:bg-gray-50">78</Button>
                            <Button variant="outline" size="sm" class="h-9 w-9 border-gray-200 text-gray-600 hover:bg-gray-50">79</Button>
                            <Button variant="outline" size="sm" class="h-9 w-9 border-gray-200 text-gray-600 hover:bg-gray-50">80</Button>
                            <Button variant="outline" size="icon" class="h-9 w-9 border-gray-200 text-gray-600 hover:bg-gray-50">
                                <ChevronRight class="w-4 h-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</template>
