<script setup lang="ts">
import {
    destroy,
    store,
} from '@/actions/Nip/Network/Http/Controllers/NetworkController';
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
import ServerLayout from '@/layouts/ServerLayout.vue';
import type { Server } from '@/types';
import type { PaginatedResponse } from '@/types/pagination';
import { RuleStatus, RuleType } from '@/types/generated';
import { Form, Head, router } from '@inertiajs/vue3';
import {
    MoreHorizontal,
    Plus,
    RefreshCw,
    Shield,
    Trash2,
} from 'lucide-vue-next';
import { ref } from 'vue';

interface FirewallRule {
    id: number;
    name: string;
    port: string | null;
    ipAddress: string | null;
    type: RuleType;
    status: RuleStatus;
    displayableType: string;
    displayableStatus: string;
    can: {
        delete: boolean;
    };
}

interface SelectOption {
    value: string;
    label: string;
}

interface Props {
    server: Server;
    rules: PaginatedResponse<FirewallRule>;
    ruleTypes: SelectOption[];
}

const props = defineProps<Props>();

const { confirmButton } = useConfirmation();

const showAddRuleDialog = ref(false);

function openAddRuleDialog() {
    showAddRuleDialog.value = true;
}

function onSuccess() {
    showAddRuleDialog.value = false;
}

async function deleteRule(rule: FirewallRule) {
    const confirmed = await confirmButton({
        title: 'Delete Firewall Rule',
        description: `Are you sure you want to delete the "${rule.name}" rule?`,
        confirmText: 'Delete',
    });

    if (!confirmed) {
        return;
    }

    router.delete(destroy.url({ server: props.server, rule: rule.id }));
}

function syncRules() {
    router.reload({ only: ['rules'] });
}

function getBadgeVariant(rule: FirewallRule) {
    if (rule.status !== RuleStatus.Installed) {
        return 'secondary';
    }
    return rule.type === RuleType.Allow ? 'default' : 'destructive';
}
</script>

<template>
    <Head :title="`Network - ${server.name}`" />

    <ServerLayout :server="server">
        <div class="space-y-6">
            <!-- Firewall Rules Section -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <Shield class="size-5" />
                                Firewall rules
                            </CardTitle>
                            <CardDescription>
                                Manage firewall rules that control the incoming
                                and outgoing traffic to and from your server.
                            </CardDescription>
                        </div>
                        <div class="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="icon"
                                @click="syncRules"
                                title="Sync rules"
                            >
                                <RefreshCw class="size-4" />
                            </Button>
                            <Button
                                variant="outline"
                                @click="openAddRuleDialog"
                            >
                                <Plus class="mr-2 size-4" />
                                Add rule
                            </Button>
                        </div>
                    </div>
                </CardHeader>

                <CardContent>
                    <div
                        v-if="rules.data.length === 0"
                        class="rounded-lg border border-dashed p-8 text-center"
                    >
                        <Shield
                            class="mx-auto mb-4 size-12 text-muted-foreground opacity-50"
                        />
                        <h3 class="text-lg font-medium">
                            No firewall rules yet
                        </h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Get started and create your first rule.
                        </p>
                        <Button
                            variant="outline"
                            class="mt-4"
                            @click="openAddRuleDialog"
                        >
                            <Plus class="mr-2 size-4" />
                            Add rule
                        </Button>
                    </div>

                    <div v-else class="divide-y">
                        <div
                            v-for="rule in rules.data"
                            :key="rule.id"
                            class="flex items-center justify-between py-4 first:pt-0 last:pb-0"
                        >
                            <div>
                                <p class="font-medium">{{ rule.name }}</p>
                                <p class="text-sm text-muted-foreground">
                                    <span v-if="rule.port"
                                        >Port {{ rule.port }}</span
                                    >
                                    <span v-else>Any port</span>
                                    <span class="mx-1">·</span>
                                    <span v-if="rule.ipAddress"
                                        >From {{ rule.ipAddress }}</span
                                    >
                                    <span v-else>From any IP address</span>
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <Badge :variant="getBadgeVariant(rule)">
                                    <span
                                        v-if="
                                            rule.status === RuleStatus.Installed
                                        "
                                    >
                                        {{ rule.displayableType }}
                                    </span>
                                    <span v-else>
                                        {{ rule.displayableStatus }}
                                    </span>
                                </Badge>
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button variant="ghost" size="icon">
                                            <MoreHorizontal class="size-4" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem
                                            v-if="rule.can.delete"
                                            class="text-destructive focus:text-destructive"
                                            :disabled="
                                                rule.status !==
                                                RuleStatus.Installed
                                            "
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
        <Dialog v-model:open="showAddRuleDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add firewall rule</DialogTitle>
                    <DialogDescription>
                        Create a new firewall rule to control traffic to your
                        server.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    v-bind="store.form(server)"
                    class="space-y-4"
                    :on-success="onSuccess"
                    reset-on-success
                    v-slot="{ errors, processing }"
                >
                    <div class="space-y-2">
                        <Label for="rule-name">Name</Label>
                        <Input
                            id="rule-name"
                            name="name"
                            placeholder="e.g., MySQL, Custom"
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="space-y-2">
                        <Label for="rule-port">Port</Label>
                        <Input
                            id="rule-port"
                            name="port"
                            placeholder="e.g., 3306"
                        />
                        <p class="text-sm text-muted-foreground">
                            Leave empty to allow any port.
                        </p>
                        <InputError :message="errors.port" />
                    </div>

                    <div class="space-y-2">
                        <Label for="rule-ip">IP address</Label>
                        <Input
                            id="rule-ip"
                            name="ip_address"
                            placeholder="e.g., 192.168.1.1"
                        />
                        <p class="text-sm text-muted-foreground">
                            Leave empty to allow from any IP address.
                        </p>
                        <InputError :message="errors.ip_address" />
                    </div>

                    <div class="space-y-2">
                        <Label for="rule-type">Type</Label>
                        <Select name="type" :default-value="RuleType.Allow">
                            <SelectTrigger id="rule-type">
                                <SelectValue placeholder="Select type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="ruleType in ruleTypes"
                                    :key="ruleType.value"
                                    :value="ruleType.value"
                                >
                                    {{ ruleType.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.type" />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="showAddRuleDialog = false"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="processing">
                            {{ processing ? 'Creating...' : 'Create rule' }}
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </ServerLayout>
</template>
