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
import { AlertTriangle, MoreVertical, RefreshCw, Shield, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

interface Props {
    certificate: CertificateData;
    site: Site;
}

const props = defineProps<Props>();

const showDeleteConfirm = ref(false);

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
        class="flex items-center justify-between gap-4 rounded-md border p-4"
        :class="{ 'border-yellow-500/50': certificate.isExpiringSoon }"
    >
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
                    <Badge :variant="certificate.statusBadgeVariant as 'default' | 'secondary' | 'destructive' | 'outline'">
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
                <div class="mt-1 space-y-0.5 text-sm text-muted-foreground">
                    <p v-if="Object.keys(certificate.domains).length > 0">
                        Domains:
                        {{
                            Object.values(certificate.domains)
                                .slice(0, 3)
                                .join(', ')
                        }}
                        <span
                            v-if="Object.keys(certificate.domains).length > 3"
                        >
                            +{{
                                Object.keys(certificate.domains).length - 3
                            }}
                            more
                        </span>
                    </p>
                    <p v-if="certificate.issuedAtHuman">
                        Issued {{ certificate.issuedAtHuman }}
                    </p>
                    <p v-if="certificate.expiresAtHuman">
                        Expires {{ certificate.expiresAtHuman }}
                        <span v-if="certificate.daysUntilExpiry !== null">
                            ({{ certificate.daysUntilExpiry }} days)
                        </span>
                    </p>
                </div>
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
