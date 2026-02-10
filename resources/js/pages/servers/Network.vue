<script setup lang="ts">
import {
    destroy,
    geoip,
    store,
    unban,
} from '@/actions/Nip/Network/Http/Controllers/NetworkController';
import EmptyState from '@/components/shared/EmptyState.vue';
import FormField from '@/components/shared/FormField.vue';
import ResourceFormDialog from '@/components/shared/ResourceFormDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
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
import { useConfirmation } from '@/composables/useConfirmation';
import { useResourceDelete } from '@/composables/useResourceDelete';
import { useResourceStatusUpdates } from '@/composables/useResourceStatusUpdates';
import ServerLayout from '@/layouts/ServerLayout.vue';
import type { Server } from '@/types';
import type { BannedIpData } from '@/types/generated';
import type { PaginatedResponse } from '@/types/pagination';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Ban,
    Loader2,
    MoreHorizontal,
    Plus,
    RefreshCw,
    Shield,
    ShieldOff,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface RuleStatus {
    value: 'pending' | 'installing' | 'installed' | 'failed' | 'deleting';
    label: string;
    variant: 'default' | 'secondary' | 'destructive';
}

interface RuleTypeObj {
    value: 'allow' | 'deny';
    label: string;
}

interface FirewallRule {
    id: number;
    name: string;
    port: string | null;
    ipAddress: string | null;
    type: RuleTypeObj;
    status: RuleStatus;
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
    bannedIps?: BannedIpData[];
}

const props = defineProps<Props>();

const addRuleDialog = ref<InstanceType<typeof ResourceFormDialog>>();

const rules = computed(() => props.rules.data);
const bannedIps = computed(() => props.bannedIps ?? []);
const isBannedIpsLoading = computed(() => props.bannedIps === undefined);

const geoData = ref<Record<string, { country: string | null; countryCode: string | null }>>({});

watch(bannedIps, async (bans) => {
    if (bans.length === 0) {
        return;
    }

    const ips = bans.map((b) => b.ip);

    try {
        const response = await axios.post(geoip.url(props.server), { ips });
        geoData.value = response.data;
    } catch {
        // GeoIP is non-critical, silently fail
    }
});

useResourceStatusUpdates({
    channelType: 'server',
    channelId: props.server.id,
    propNames: ['rules'],
});

const { deleteResource: deleteRule } = useResourceDelete<FirewallRule>({
    resourceName: 'Firewall Rule',
    getDisplayName: (rule) => rule.name,
    getDeleteUrl: (rule) =>
        destroy.url({ server: props.server, rule: rule.id }),
});

const { confirmButton } = useConfirmation();

function syncRules() {
    router.reload({ only: ['rules'] });
}

function refreshBannedIps() {
    router.reload({ only: ['bannedIps'] });
}

function getCountryFlag(countryCode: string | null): string {
    if (!countryCode || countryCode.length !== 2) {
        return '';
    }

    const codePoints = countryCode
        .toUpperCase()
        .split('')
        .map((char) => 127397 + char.charCodeAt(0));

    return String.fromCodePoint(...codePoints);
}

async function handleUnban(ban: BannedIpData) {
    const confirmed = await confirmButton({
        title: 'Unban IP',
        description: `Are you sure you want to unban ${ban.ip} from ${ban.jail}?`,
        confirmText: 'Unban',
    });

    if (!confirmed) {
        return;
    }

    router.post(unban.url(props.server), {
        jail: ban.jail,
        ip: ban.ip,
    });
}

function getBadgeVariant(
    rule: FirewallRule,
): 'default' | 'secondary' | 'destructive' {
    if (rule.status.value !== 'installed') {
        return rule.status.variant;
    }
    return rule.type.value === 'allow' ? 'default' : 'destructive';
}
</script>

<template>
    <Head :title="`Network - ${server.name}`" />

    <ServerLayout :server="server">
        <div class="space-y-4">
            <!-- Firewall Rules Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">Firewall rules</h1>
                    <p class="text-gray-500">
                        Manage firewall rules that control the incoming and
                        outgoing traffic to and from your server.
                    </p>
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
                    <Button variant="outline" @click="addRuleDialog?.open()">
                        <Plus class="mr-2 size-4" />
                        Add rule
                    </Button>
                </div>
            </div>

            <!-- Empty State -->
            <EmptyState
                v-if="rules.length === 0"
                :icon="Shield"
                title="No firewall rules yet"
                description="Get started and create your first rule."
            >
                <template #action>
                    <Button variant="outline" @click="addRuleDialog?.open()">
                        <Plus class="mr-2 size-4" />
                        Add rule
                    </Button>
                </template>
            </EmptyState>

            <!-- Rule List -->
            <Card v-else class="overflow-hidden bg-white py-0">
                <div class="divide-y">
                    <div
                        v-for="rule in rules"
                        :key="rule.id"
                        class="flex items-center justify-between px-4 py-4"
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
                                <span v-if="rule.status.value === 'installed'">
                                    {{ rule.type.label }}
                                </span>
                                <span v-else>
                                    {{ rule.status.label }}
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
                                        v-if="
                                            rule.can.delete &&
                                            rule.status.value !== 'deleting'
                                        "
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
            </Card>
        </div>

        <!-- Fail2ban Banned IPs -->
        <div class="mt-8 space-y-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-semibold">Banned IPs</h2>
                    <p class="text-gray-500">
                        IP addresses banned by fail2ban for brute-force
                        attempts.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="icon"
                        @click="refreshBannedIps"
                        title="Refresh banned IPs"
                    >
                        <RefreshCw class="size-4" />
                    </Button>
                </div>
            </div>

            <!-- Loading State -->
            <Card v-if="isBannedIpsLoading" class="bg-white py-8">
                <div class="flex items-center justify-center gap-2">
                    <Loader2
                        class="size-5 animate-spin text-muted-foreground"
                    />
                    <span class="text-sm text-muted-foreground"
                        >Loading banned IPs from server...</span
                    >
                </div>
            </Card>

            <!-- Empty State -->
            <EmptyState
                v-else-if="bannedIps.length === 0"
                :icon="Ban"
                title="No banned IPs"
                description="No IP addresses are currently banned by fail2ban."
            />

            <!-- Banned IP List -->
            <Card v-else class="overflow-hidden bg-white py-0">
                <div class="divide-y">
                    <div
                        v-for="ban in bannedIps"
                        :key="`${ban.jail}-${ban.ip}`"
                        class="flex items-center justify-between px-4 py-4"
                    >
                        <div>
                            <div class="flex items-center gap-2">
                                <p class="font-mono font-medium">{{ ban.ip }}</p>
                                <span
                                    v-if="geoData[ban.ip]?.countryCode"
                                    class="text-sm"
                                    :title="geoData[ban.ip]?.country || geoData[ban.ip]?.countryCode"
                                >
                                    {{ getCountryFlag(geoData[ban.ip]?.countryCode ?? null) }}
                                    {{ geoData[ban.ip]?.countryCode }}
                                </span>
                            </div>
                            <p class="text-sm text-muted-foreground">
                                Banned {{ ban.bannedSinceHuman }}
                                <span class="mx-1">·</span>
                                {{ ban.remainingTimeHuman }}
                                <span class="mx-1">·</span>
                                {{ ban.banCount }}
                                {{
                                    ban.banCount === 1
                                        ? 'violation'
                                        : 'violations'
                                }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <Badge variant="destructive">
                                {{ ban.jail }}
                            </Badge>
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button variant="ghost" size="icon">
                                        <MoreHorizontal class="size-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem @click="handleUnban(ban)">
                                        <ShieldOff class="mr-2 size-4" />
                                        Unban
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </div>
                </div>
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
                    <Select name="type" default-value="allow">
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
