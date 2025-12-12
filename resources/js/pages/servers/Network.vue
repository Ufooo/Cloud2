<script setup lang="ts">
import {
    destroy,
    store,
} from '@/actions/Nip/Network/Http/Controllers/NetworkController';
import EmptyState from '@/components/shared/EmptyState.vue';
import FormField from '@/components/shared/FormField.vue';
import ResourceFormDialog from '@/components/shared/ResourceFormDialog.vue';
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
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useResourceDelete } from '@/composables/useResourceDelete';
import ServerLayout from '@/layouts/ServerLayout.vue';
import type { Server } from '@/types';
import type { PaginatedResponse } from '@/types/pagination';
import { RuleStatus, RuleType } from '@/types/generated';
import { Head, router } from '@inertiajs/vue3';
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

const addRuleDialog = ref<InstanceType<typeof ResourceFormDialog>>();

const { deleteResource: deleteRule } = useResourceDelete<FirewallRule>({
    resourceName: 'Firewall Rule',
    getDisplayName: (rule) => rule.name,
    getDeleteUrl: (rule) => destroy.url({ server: props.server, rule: rule.id }),
});

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
                                @click="addRuleDialog?.open()"
                            >
                                <Plus class="mr-2 size-4" />
                                Add rule
                            </Button>
                        </div>
                    </div>
                </CardHeader>

                <CardContent>
                    <EmptyState
                        v-if="rules.data.length === 0"
                        :icon="Shield"
                        title="No firewall rules yet"
                        description="Get started and create your first rule."
                        compact
                    >
                        <template #action>
                            <Button
                                variant="outline"
                                @click="addRuleDialog?.open()"
                            >
                                <Plus class="mr-2 size-4" />
                                Add rule
                            </Button>
                        </template>
                    </EmptyState>

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

        <ResourceFormDialog
            ref="addRuleDialog"
            title="Add firewall rule"
            description="Create a new firewall rule to control traffic to your server."
            submit-text="Create rule"
            processing-text="Creating..."
            :form-action="store.form(server)"
        >
            <template #default="{ errors }">
                <FormField label="Name" name="name" :error="errors.name">
                    <Input
                        id="name"
                        name="name"
                        placeholder="e.g., MySQL, Custom"
                    />
                </FormField>

                <FormField
                    label="Port"
                    name="port"
                    :error="errors.port"
                    description="Leave empty to allow any port."
                >
                    <Input id="port" name="port" placeholder="e.g., 3306" />
                </FormField>

                <FormField
                    label="IP address"
                    name="ip_address"
                    :error="errors.ip_address"
                    description="Leave empty to allow from any IP address."
                >
                    <Input
                        id="ip_address"
                        name="ip_address"
                        placeholder="e.g., 192.168.1.1"
                    />
                </FormField>

                <FormField label="Type" name="type" :error="errors.type">
                    <Select name="type" :default-value="RuleType.Allow">
                        <SelectTrigger id="type">
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
                </FormField>
            </template>
        </ResourceFormDialog>
    </ServerLayout>
</template>
