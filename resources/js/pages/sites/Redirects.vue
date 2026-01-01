<script setup lang="ts">
import {
    destroy,
    store,
    update,
} from '@/actions/Nip/Redirect/Http/Controllers/SiteRedirectRuleController';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useConfirmation } from '@/composables/useConfirmation';
import SiteLayout from '@/layouts/SiteLayout.vue';
import type { Site } from '@/types';
import type { PaginatedResponse } from '@/types/pagination';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    ArrowRight,
    MoreHorizontal,
    Pencil,
    Plus,
    Share2,
    Trash2,
} from 'lucide-vue-next';
import { ref } from 'vue';

type BadgeVariant =
    | 'default'
    | 'secondary'
    | 'destructive'
    | 'outline'
    | null
    | undefined;

interface RedirectRule {
    id: string;
    from: string;
    to: string;
    type: string;
    displayableType: string;
    status: string;
    displayableStatus: string;
    statusBadgeVariant: BadgeVariant;
    createdAt: string | null;
    can: {
        update: boolean;
        delete: boolean;
    };
}

interface Props {
    site: Site;
    rules: PaginatedResponse<RedirectRule>;
}

const props = defineProps<Props>();

const { confirmButton } = useConfirmation();

const showAddDialog = ref(false);
const showEditDialog = ref(false);
const editingRule = ref<RedirectRule | null>(null);

const addForm = useForm({
    from: '',
    to: '',
    type: 'permanent',
});

const editForm = useForm({
    from: '',
    to: '',
    type: 'permanent',
});

function openAddDialog() {
    addForm.reset();
    addForm.type = 'permanent';
    showAddDialog.value = true;
}

function openEditDialog(rule: RedirectRule) {
    editingRule.value = rule;
    editForm.from = rule.from;
    editForm.to = rule.to;
    editForm.type = rule.type;
    showEditDialog.value = true;
}

function submitAdd() {
    addForm.post(store.url(props.site), {
        onSuccess: () => {
            showAddDialog.value = false;
            addForm.reset();
        },
    });
}

function submitEdit() {
    if (!editingRule.value) return;

    editForm.patch(
        update.url({ site: props.site, rule: editingRule.value.id }),
        {
            onSuccess: () => {
                showEditDialog.value = false;
                editingRule.value = null;
            },
        },
    );
}

async function deleteRule(rule: RedirectRule) {
    const confirmed = await confirmButton({
        title: 'Delete Redirect Rule',
        description: `Are you sure you want to delete the redirect from "${rule.from}" to "${rule.to}"?`,
        confirmText: 'Delete',
    });

    if (!confirmed) {
        return;
    }

    router.delete(destroy.url({ site: props.site, rule: rule.id }));
}
</script>

<template>
    <Head :title="`Redirects - ${site.domain}`" />

    <SiteLayout :site="site">
        <div class="space-y-6">
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <Share2 class="size-5" />
                                Redirect rules
                            </CardTitle>
                            <CardDescription>
                                Configure redirect rules for your site. These
                                are handled by Nginx.
                            </CardDescription>
                        </div>
                        <div class="flex items-center gap-2">
                            <Button variant="outline" @click="openAddDialog">
                                <Plus class="mr-2 size-4" />
                                Add redirect rule
                            </Button>
                        </div>
                    </div>
                </CardHeader>

                <CardContent>
                    <div
                        v-if="rules.data.length === 0"
                        class="rounded-lg border border-dashed p-8 text-center"
                    >
                        <Share2
                            class="mx-auto mb-4 size-12 text-muted-foreground opacity-50"
                        />
                        <h3 class="text-lg font-medium">
                            No redirect rules yet
                        </h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Get started and create your first redirect rule.
                        </p>
                        <Button
                            variant="outline"
                            class="mt-4"
                            @click="openAddDialog"
                        >
                            <Plus class="mr-2 size-4" />
                            Add redirect rule
                        </Button>
                    </div>

                    <div v-else class="divide-y">
                        <div
                            v-for="rule in rules.data"
                            :key="rule.id"
                            class="flex items-center justify-between py-4 first:pt-0 last:pb-0"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="truncate font-mono text-sm"
                                        :title="rule.from"
                                    >
                                        {{ rule.from }}
                                    </span>
                                    <ArrowRight
                                        class="size-4 shrink-0 text-muted-foreground"
                                    />
                                    <span
                                        class="truncate font-mono text-sm"
                                        :title="rule.to"
                                    >
                                        {{ rule.to }}
                                    </span>
                                    <Badge
                                        v-if="rule.status !== 'installed'"
                                        :variant="rule.statusBadgeVariant"
                                    >
                                        {{ rule.displayableStatus }}
                                    </Badge>
                                </div>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{ rule.displayableType }}
                                </p>
                            </div>
                            <div class="ml-4 flex items-center gap-4">
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button variant="ghost" size="icon">
                                            <MoreHorizontal class="size-4" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem
                                            v-if="rule.can.update"
                                            @click="openEditDialog(rule)"
                                        >
                                            <Pencil class="mr-2 size-4" />
                                            Edit
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator
                                            v-if="rule.can.delete"
                                        />
                                        <DropdownMenuItem
                                            v-if="rule.can.delete"
                                            class="text-destructive focus:text-destructive"
                                            @click="deleteRule(rule)"
                                        >
                                            <Trash2 class="mr-2 size-4" />
                                            Delete
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Add Rule Dialog -->
        <Dialog v-model:open="showAddDialog">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>New redirect rule</DialogTitle>
                    <DialogDescription>
                        Create a redirect rule to forward requests from one path
                        to another.
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="submitAdd">
                    <div class="space-y-2">
                        <Label for="from">From</Label>
                        <Input
                            id="from"
                            v-model="addForm.from"
                            class="font-mono text-sm"
                            placeholder="/old-path"
                        />
                        <InputError :message="addForm.errors.from" />
                    </div>

                    <div class="space-y-2">
                        <Label for="to">To</Label>
                        <Input
                            id="to"
                            v-model="addForm.to"
                            class="font-mono text-sm"
                            placeholder="/new-path"
                        />
                        <InputError :message="addForm.errors.to" />
                    </div>

                    <div class="space-y-2">
                        <Label for="type">Type</Label>
                        <Select v-model="addForm.type">
                            <SelectTrigger>
                                <SelectValue placeholder="Select type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="permanent">
                                    Permanent (301)
                                </SelectItem>
                                <SelectItem value="temporary">
                                    Temporary (302)
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="addForm.errors.type" />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="showAddDialog = false"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="addForm.processing">
                            {{
                                addForm.processing
                                    ? 'Creating...'
                                    : 'Create redirect rule'
                            }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Edit Rule Dialog -->
        <Dialog v-model:open="showEditDialog">
            <DialogContent v-if="editingRule" class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Edit redirect rule</DialogTitle>
                    <DialogDescription>
                        Update the redirect rule configuration.
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="submitEdit">
                    <div class="space-y-2">
                        <Label for="edit-from">From</Label>
                        <Input
                            id="edit-from"
                            v-model="editForm.from"
                            class="font-mono text-sm"
                        />
                        <InputError :message="editForm.errors.from" />
                    </div>

                    <div class="space-y-2">
                        <Label for="edit-to">To</Label>
                        <Input
                            id="edit-to"
                            v-model="editForm.to"
                            class="font-mono text-sm"
                        />
                        <InputError :message="editForm.errors.to" />
                    </div>

                    <div class="space-y-2">
                        <Label for="edit-type">Type</Label>
                        <Select v-model="editForm.type">
                            <SelectTrigger>
                                <SelectValue placeholder="Select type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="permanent">
                                    Permanent (301)
                                </SelectItem>
                                <SelectItem value="temporary">
                                    Temporary (302)
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="editForm.errors.type" />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="showEditDialog = false"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="editForm.processing">
                            {{
                                editForm.processing
                                    ? 'Saving...'
                                    : 'Save changes'
                            }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </SiteLayout>
</template>
