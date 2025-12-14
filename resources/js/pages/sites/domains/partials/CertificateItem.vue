<script setup lang="ts">
import {
    activate,
    deactivate,
    destroy,
    renew,
} from '@/actions/Nip/Domain/Http/Controllers/CertificateController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { CertificateData, Site } from '@/types';
import { router } from '@inertiajs/vue3';
import { AlertTriangle, Copy, Loader2, MoreVertical, RefreshCw, Shield, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Props {
    certificate: CertificateData;
    site: Site;
}

const props = defineProps<Props>();

const showDeleteConfirm = ref(false);

const isPendingVerification = computed(() => props.certificate.status === 'pending_verification');
const hasVerificationRecords = computed(() =>
    isPendingVerification.value &&
    props.certificate.verificationRecords &&
    props.certificate.verificationRecords.length > 0
);

const domains = computed(() => Object.values(props.certificate.domains) as string[]);
const primaryDomain = computed(() => domains.value[0] ?? '');
const additionalDomainsCount = computed(() => Math.max(0, domains.value.length - 1));
const isInstalled = computed(() => props.certificate.status === 'installed');

async function copyToClipboard(text: string) {
    await navigator.clipboard.writeText(text);
}

function postAction(action: typeof activate | typeof deactivate | typeof renew) {
    router.post(action.url({ site: props.site, certificate: props.certificate }), {}, { preserveScroll: true });
}

function handleDelete() {
    router.delete(destroy.url({ site: props.site, certificate: props.certificate }), {
        preserveScroll: true,
        onSuccess: () => (showDeleteConfirm.value = false),
    });
}
</script>

<template>
    <div
        class="rounded-md border"
        :class="{ 'border-yellow-500/50': certificate.isExpiringSoon }"
    >
        <!-- Certificate Header -->
        <div class="flex items-center justify-between gap-4 p-4">
            <div class="flex min-w-0 flex-1 items-center gap-4">
                <Shield
                    class="size-5 shrink-0"
                    :class="
                        certificate.active
                            ? 'text-green-500'
                            : 'text-muted-foreground'
                    "
                />

                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <span class="font-medium">
                            {{ certificate.displayableType }}
                        </span>
                        <Badge :variant="certificate.statusBadgeVariant">
                            <Loader2 v-if="isPendingVerification" class="mr-1 size-3 animate-spin" />
                            {{ certificate.displayableStatus }}
                        </Badge>
                        <Badge v-if="certificate.active" variant="default">Active</Badge>
                        <Badge
                            v-if="certificate.isExpiringSoon"
                            variant="destructive"
                            class="flex items-center gap-1"
                        >
                            <AlertTriangle class="size-3" />
                            Expiring soon
                        </Badge>
                    </div>
                    <p v-if="domains.length > 0" class="mt-1 text-sm text-muted-foreground">
                        {{ primaryDomain }}
                        <span v-if="additionalDomainsCount > 0">
                            + {{ additionalDomainsCount }} more
                        </span>
                        <template v-if="isInstalled && certificate.expiresAtHuman">
                            <span class="mx-1">·</span>
                            Expires {{ certificate.expiresAtHuman }}
                        </template>
                        <template v-else-if="certificate.createdAt">
                            <span class="mx-1">·</span>
                            Created {{ certificate.createdAtHuman ?? 'Just now' }}
                        </template>
                    </p>
                </div>
            </div>

            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button variant="ghost" size="sm">
                        <MoreVertical class="size-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-48">
                    <DropdownMenuItem v-if="certificate.can.activate && !certificate.active" @click="postAction(activate)">
                        <Shield class="mr-2 size-4" />
                        Activate
                    </DropdownMenuItem>
                    <DropdownMenuItem v-if="certificate.can.deactivate && certificate.active" @click="postAction(deactivate)">
                        Deactivate
                    </DropdownMenuItem>
                    <DropdownMenuItem v-if="certificate.can.renew" @click="postAction(renew)">
                        <RefreshCw class="mr-2 size-4" />
                        Renew
                    </DropdownMenuItem>
                    <DropdownMenuSeparator v-if="certificate.can.delete" />
                    <DropdownMenuItem v-if="certificate.can.delete" class="text-destructive" @click="showDeleteConfirm = true">
                        <Trash2 class="mr-2 size-4" />
                        Delete
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>

        <!-- Verification Records Table -->
        <div v-if="hasVerificationRecords" class="border-t px-4 py-3">
            <div class="mb-2">
                <p class="text-sm font-medium">Verification records</p>
                <p class="text-xs text-muted-foreground">
                    The following DNS records must be added to your DNS provider before you can obtain a Let's Encrypt certificate.
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left text-xs text-muted-foreground">
                            <th class="pb-2 pr-4 font-medium">Type</th>
                            <th class="pb-2 pr-4 font-medium">Name</th>
                            <th class="pb-2 pr-4 font-medium">Value</th>
                            <th class="pb-2 font-medium">TTL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(record, index) in certificate.verificationRecords"
                            :key="index"
                            class="border-b last:border-b-0"
                        >
                            <td class="py-2 pr-4">
                                <Badge variant="outline" class="font-mono text-xs">
                                    {{ record.type }}
                                </Badge>
                            </td>
                            <td class="py-2 pr-4">
                                <div class="flex items-center gap-1">
                                    <code class="rounded bg-muted px-1 py-0.5 text-xs">
                                        {{ record.name }}
                                    </code>
                                    <button
                                        type="button"
                                        class="text-muted-foreground hover:text-foreground"
                                        @click="copyToClipboard(record.name)"
                                    >
                                        <Copy class="size-3" />
                                    </button>
                                </div>
                            </td>
                            <td class="py-2 pr-4">
                                <div class="flex items-center gap-1">
                                    <code class="rounded bg-muted px-1 py-0.5 text-xs">
                                        {{ record.value }}
                                    </code>
                                    <button
                                        type="button"
                                        class="text-muted-foreground hover:text-foreground"
                                        @click="copyToClipboard(record.value)"
                                    >
                                        <Copy class="size-3" />
                                    </button>
                                </div>
                            </td>
                            <td class="py-2 text-muted-foreground">
                                {{ record.ttl }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Delete Confirmation Dialog -->
        <Dialog v-model:open="showDeleteConfirm">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete certificate?</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete this {{ certificate.displayableType }} certificate?
                        This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="showDeleteConfirm = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        @click="handleDelete"
                    >
                        Delete
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
