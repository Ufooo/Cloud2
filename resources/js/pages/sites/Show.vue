<script setup lang="ts">
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import SiteLayout from '@/layouts/SiteLayout.vue';
import type { Site } from '@/types';
import { SiteStatus } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Folder } from 'lucide-vue-next';
import { computed } from 'vue';
import DetectedPackages from './partials/DetectedPackages.vue';
import SiteProvisioning from './partials/SiteProvisioning.vue';

interface Props {
    site: Site;
}

const props = defineProps<Props>();

const isInstalling = computed(() => props.site.status === SiteStatus.Installing);
</script>

<template>
    <Head :title="site.domain" />

    <SiteLayout :site="site" :show-sidebar="!isInstalling">
        <SiteProvisioning v-if="isInstalling" :site="site" />

        <div v-else class="space-y-6">
            <!-- Paths Info -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Folder class="size-5" />
                        Paths
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div>
                        <span class="text-sm text-muted-foreground">
                            Full Path
                        </span>
                        <p class="mt-1 break-all font-mono text-sm font-medium">
                            {{ site.fullPath }}
                        </p>
                    </div>
                    <div>
                        <span class="text-sm text-muted-foreground">
                            Web Path
                        </span>
                        <p class="mt-1 break-all font-mono text-sm font-medium">
                            {{ site.webPath }}
                        </p>
                    </div>
                    <div>
                        <span class="text-sm text-muted-foreground">
                            Root Directory
                        </span>
                        <p class="mt-1 break-all font-mono text-sm font-medium">
                            {{ site.rootDirectory }}
                        </p>
                    </div>
                    <div>
                        <span class="text-sm text-muted-foreground">
                            Web Directory
                        </span>
                        <p class="mt-1 break-all font-mono text-sm font-medium">
                            {{ site.webDirectory }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <!-- Detected Packages -->
            <DetectedPackages :site="site" />
        </div>
    </SiteLayout>
</template>
