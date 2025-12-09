<script setup lang="ts">
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { Head, Link } from '@inertiajs/vue3'
import { Copy, Trash2 } from 'lucide-vue-next'
import AppLayout from '@/layouts/AppLayout.vue'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import ServerStatusBadge from './partials/ServerStatusBadge.vue'
import DigitalOceanLogo from '@/components/icons/DigitalOceanLogo.vue'
import VultrLogo from '@/components/icons/VultrLogo.vue'
import CustomVpsLogo from '@/components/icons/CustomVpsLogo.vue'
import { ServerProvider } from '@/types/server'
import type { Component } from 'vue'
import { useConfirmation } from '@/composables/useConfirmation'
import { useServerAvatar, useServerActions } from '@/composables/useServer'
import { index, show, destroy } from '@/actions/Nip/Server/Http/Controllers/ServerController'
import type { BreadcrumbItem, Server } from '@/types'

interface Props {
    server: Server
}

const props = defineProps<Props>()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: 'Servers',
        href: index.url(),
    },
    {
        title: props.server.name,
        href: show.url(props.server),
    },
])

const { confirmInput } = useConfirmation()
const { avatarColorClass, initials } = useServerAvatar(() => props.server)
const { copyIpAddress } = useServerActions(() => props.server)

const providerLogos: Record<ServerProvider, Component> = {
    [ServerProvider.DigitalOcean]: DigitalOceanLogo,
    [ServerProvider.Vultr]: VultrLogo,
    [ServerProvider.Custom]: CustomVpsLogo,
}

const providerLogo = computed(() => providerLogos[props.server.provider] ?? CustomVpsLogo)

async function handleDelete() {
    const confirmed = await confirmInput({
        title: 'Delete server',
        description: `Type "${props.server.name}" to confirm permanently deleting this server. This action cannot be undone.`,
        value: props.server.name,
    })

    if (confirmed) {
        router.delete(destroy.url(props.server))
    }
}
</script>

<template>
    <Head :title="server.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
            <!-- Server Header -->
            <Card>
                <CardContent class="flex items-center gap-4 p-6">
                    <Avatar class="size-16 rounded-lg">
                        <AvatarFallback :class="avatarColorClass" class="rounded-lg text-xl font-medium text-white">
                            {{ initials }}
                        </AvatarFallback>
                    </Avatar>

                    <div class="flex-1 space-y-1">
                        <div class="flex items-center gap-2">
                            <h1 class="text-2xl font-bold">{{ server.name }}</h1>
                            <ServerStatusBadge :status="server.status" />
                        </div>

                        <div class="flex items-center gap-x-1 text-sm text-muted-foreground">
                            <component
                                :is="providerLogo"
                                class="size-4 rounded"
                            />
                            <span>{{ server.displayableProvider }}</span>

                            <template v-if="server.ipAddress">
                                <span>·</span>
                                <button
                                    class="flex items-center gap-1 hover:text-foreground"
                                    @click="copyIpAddress"
                                    :title="`Copy IP: ${server.ipAddress}`"
                                >
                                    <span class="hover:underline">{{ server.ipAddress }}</span>
                                    <Copy class="size-3.5" />
                                </button>
                            </template>

                            <template v-if="server.displayablePhpVersion">
                                <span>·</span>
                                <span>{{ server.displayablePhpVersion }}</span>
                            </template>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <Button
                            v-if="server.can.delete"
                            variant="destructive"
                            size="sm"
                            @click="handleDelete"
                        >
                            <Trash2 class="mr-2 size-4" />
                            Delete
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Server Information -->
            <Card>
                <CardHeader>
                    <CardTitle>Server Information</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-1">
                        <p class="text-sm font-medium">Server Type</p>
                        <p class="text-sm text-muted-foreground">
                            {{ server.displayableType }}
                        </p>
                    </div>

                    <div class="space-y-1">
                        <p class="text-sm font-medium">IP Address</p>
                        <p class="text-sm text-muted-foreground">
                            {{ server.ipAddress || 'N/A' }}
                        </p>
                    </div>

                    <div class="space-y-1">
                        <p class="text-sm font-medium">Private IP Address</p>
                        <p class="text-sm text-muted-foreground">
                            {{ server.privateIpAddress || 'N/A' }}
                        </p>
                    </div>

                    <div class="space-y-1">
                        <p class="text-sm font-medium">SSH Port</p>
                        <p class="text-sm text-muted-foreground">
                            {{ server.sshPort }}
                        </p>
                    </div>

                    <div v-if="server.displayablePhpVersion" class="space-y-1">
                        <p class="text-sm font-medium">PHP Version</p>
                        <p class="text-sm text-muted-foreground">
                            {{ server.displayablePhpVersion }}
                        </p>
                    </div>

                    <div v-if="server.displayableDatabaseType" class="space-y-1">
                        <p class="text-sm font-medium">Database</p>
                        <p class="text-sm text-muted-foreground">
                            {{ server.displayableDatabaseType }}
                        </p>
                    </div>

                    <div v-if="server.ubuntuVersion" class="space-y-1">
                        <p class="text-sm font-medium">Ubuntu Version</p>
                        <p class="text-sm text-muted-foreground">
                            {{ server.ubuntuVersion }}
                        </p>
                    </div>

                    <div class="space-y-1">
                        <p class="text-sm font-medium">Timezone</p>
                        <p class="text-sm text-muted-foreground">
                            {{ server.timezone }}
                        </p>
                    </div>

                    <div v-if="server.notes" class="col-span-2 space-y-1">
                        <p class="text-sm font-medium">Notes</p>
                        <p class="text-sm text-muted-foreground">
                            {{ server.notes }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <!-- Tabs placeholder for future features -->
            <Card>
                <CardHeader>
                    <CardTitle>Server Management</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="py-8 text-center text-muted-foreground">
                        <p>Additional server management features will be available here.</p>
                        <p class="mt-2 text-sm">
                            Sites, databases, cron jobs, SSL certificates, and more.
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
