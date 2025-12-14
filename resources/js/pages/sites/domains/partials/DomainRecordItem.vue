<script setup lang="ts">
import {
    destroy,
    markAsPrimary,
    update,
} from '@/actions/Nip/Domain/Http/Controllers/DomainRecordController';
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
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import type { DomainRecordData, Site } from '@/types';
import { router, useForm } from '@inertiajs/vue3';
import { Globe, Lock, LockOpen, MoreVertical, Settings, Shield, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import ConfigureDomainModal from '../../partials/ConfigureDomainModal.vue';

interface WwwRedirectTypeOption {
    value: string;
    label: string;
    description: string;
    isDefault: boolean;
}

interface Props {
    domain: DomainRecordData;
    site: Site;
    wwwRedirectTypes: WwwRedirectTypeOption[];
}

const props = defineProps<Props>();

const showEditModal = ref(false);
const showDeleteConfirm = ref(false);

const editForm = useForm({
    allow_wildcard: props.domain.allowWildcard,
    www_redirect_type: props.domain.wwwRedirectType,
});

const certificateTooltip = computed(() => {
    if (props.domain.isSecured) {
        return `This domain is secured with ${props.domain.certificateType}.`;
    }
    return "This domain's certificate is currently disabled.";
});

function openEditModal() {
    editForm.allow_wildcard = props.domain.allowWildcard;
    editForm.www_redirect_type = props.domain.wwwRedirectType;
    showEditModal.value = true;
}

function saveEdit() {
    editForm.patch(update.url({ site: props.site, domainRecord: props.domain }), {
        preserveScroll: true,
    });
}

function handleMarkAsPrimary() {
    router.post(markAsPrimary.url({ site: props.site, domainRecord: props.domain }), {}, { preserveScroll: true });
}

function handleDelete() {
    router.delete(destroy.url({ site: props.site, domainRecord: props.domain }), {
        preserveScroll: true,
        onSuccess: () => (showDeleteConfirm.value = false),
    });
}
</script>

<template>
    <div class="flex items-center justify-between gap-4 rounded-md border p-4">
        <div class="flex min-w-0 flex-1 items-center gap-4">
            <Globe class="size-5 shrink-0 text-muted-foreground" />

            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <span class="truncate font-medium">
                        {{ domain.name }}
                    </span>
                    <TooltipProvider>
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <button type="button" class="shrink-0">
                                    <Lock
                                        v-if="domain.isSecured"
                                        class="size-4 text-green-500"
                                    />
                                    <LockOpen
                                        v-else
                                        class="size-4 text-amber-500"
                                    />
                                </button>
                            </TooltipTrigger>
                            <TooltipContent>
                                {{ certificateTooltip }}
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                    <Badge v-if="domain.isPrimary" variant="secondary">
                        Primary
                    </Badge>
                    <Badge
                        v-if="domain.status !== 'enabled'"
                        :variant="domain.statusBadgeVariant"
                    >
                        {{ domain.displayableStatus }}
                    </Badge>
                    <span v-if="domain.allowWildcard" class="text-xs text-muted-foreground">
                        Wildcard
                    </span>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-4">
            <span class="text-sm text-muted-foreground">
                {{ domain.wwwRedirectTypeLabel }}
            </span>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button variant="ghost" size="sm">
                        <MoreVertical class="size-4" />
                    </Button>
                </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-48">
                <DropdownMenuItem
                    v-if="domain.can.update"
                    @click="openEditModal"
                >
                    <Settings class="mr-2 size-4" />
                    Edit
                </DropdownMenuItem>
                <DropdownMenuItem
                    v-if="domain.can.makePrimary && !domain.isPrimary"
                    @click="handleMarkAsPrimary"
                >
                    <Shield class="mr-2 size-4" />
                    Mark as primary
                </DropdownMenuItem>
                <DropdownMenuSeparator v-if="domain.can.delete" />
                <DropdownMenuItem
                    v-if="domain.can.delete"
                    class="text-destructive"
                    @click="showDeleteConfirm = true"
                >
                    <Trash2 class="mr-2 size-4" />
                    Delete
                </DropdownMenuItem>
            </DropdownMenuContent>
            </DropdownMenu>
        </div>

        <!-- Edit Modal -->
        <ConfigureDomainModal
            :open="showEditModal"
            :domain="domain.name"
            :www-redirect-type="editForm.www_redirect_type"
            :www-redirect-types="wwwRedirectTypes"
            :allow-wildcard="editForm.allow_wildcard"
            @update:open="showEditModal = $event"
            @update:www-redirect-type="editForm.www_redirect_type = $event"
            @update:allow-wildcard="editForm.allow_wildcard = $event"
            @save="saveEdit"
        />

        <!-- Delete Confirmation Dialog -->
        <Dialog v-model:open="showDeleteConfirm">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete domain?</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete "{{ domain.name }}"?
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
