<script setup lang="ts">
import { store } from '@/actions/Nip/Domain/Http/Controllers/CertificateController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import type { CertificateData, DomainRecordData, Site } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { ArrowLeft, FileKey, Lock, RefreshCcw, Shield } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface CertificateTypeOption {
    value: string;
    label: string;
    description: string;
}

interface Props {
    open: boolean;
    site: Site;
    domainRecords: DomainRecordData[];
    certificates: CertificateData[];
    certificateTypes: CertificateTypeOption[];
    countries: Array<{ code: string; name: string }>;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const step = ref<'type' | 'form'>('type');
const selectedType = ref('letsencrypt');

const form = useForm({
    type: 'letsencrypt',
    // All types use domain
    domain: '',
    // Let's Encrypt specific
    verification_method: 'http' as 'http' | 'dns',
    key_algorithm: 'ecdsa' as 'ecdsa' | 'rsa',
    isrg_root_chain: false,
    // Existing
    certificate: '',
    private_key: '',
    auto_activate: true,
    // CSR
    sans: '',
    csr_country: '',
    csr_state: '',
    csr_city: '',
    csr_organization: '',
    csr_department: '',
    // Clone
    source_certificate_id: '',
});

const typeIcons = {
    letsencrypt: Lock,
    existing: FileKey,
    csr: Shield,
    clone: RefreshCcw,
};

const availableDomains = computed(() => {
    return props.domainRecords.filter(d => d.status === 'enabled');
});

const cloneableCertificates = computed(() => {
    return props.certificates.filter(c => c.status === 'installed');
});

const formTitle = computed(() => {
    switch (selectedType.value) {
        case 'letsencrypt':
            return "Let's Encrypt certificate";
        case 'existing':
            return 'Install existing certificate';
        case 'csr':
            return 'Create signing request';
        case 'clone':
            return 'Clone certificate';
        default:
            return 'Add certificate';
    }
});

const formDescription = computed(() => {
    switch (selectedType.value) {
        case 'letsencrypt':
            return 'Configure and obtain a free SSL certificate from Let\'s Encrypt.';
        case 'existing':
            return 'Provide your existing SSL certificate and private key.';
        case 'csr':
            return 'Generate a certificate signing request for an external certificate authority.';
        case 'clone':
            return 'Pick a certificate from another site to clone.';
        default:
            return '';
    }
});

const submitLabel = computed(() => {
    if (form.processing) {
        return 'Processing...';
    }
    switch (selectedType.value) {
        case 'letsencrypt':
            return 'Obtain certificate';
        case 'existing':
            return 'Install certificate';
        case 'csr':
            return 'Create signing request';
        case 'clone':
            return 'Clone certificate';
        default:
            return 'Submit';
    }
});

const canSubmit = computed(() => {
    if (form.processing) {
        return false;
    }
    switch (selectedType.value) {
        case 'letsencrypt':
            return !!form.domain;
        case 'existing':
            return form.domain && form.certificate && form.private_key;
        case 'csr':
            return form.domain && form.csr_country && form.csr_state && form.csr_city && form.csr_organization && form.csr_department;
        case 'clone':
            return form.domain && form.source_certificate_id;
        default:
            return false;
    }
});

watch(() => props.open, (isOpen) => {
    if (isOpen) {
        resetForm();
    }
});

function resetForm() {
    step.value = 'type';
    selectedType.value = 'letsencrypt';
    form.reset();
    form.clearErrors();
}

function selectType(type: string) {
    selectedType.value = type;
}

function continueToForm() {
    form.type = selectedType.value;
    step.value = 'form';
}

function goBack() {
    step.value = 'type';
    form.clearErrors();
}

function submit() {
    form.post(store.url({ site: props.site }), {
        preserveScroll: true,
        onSuccess: () => {
            emit('update:open', false);
        },
    });
}

function close() {
    emit('update:open', false);
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-lg">
            <!-- Step 1: Type Selection -->
            <template v-if="step === 'type'">
                <DialogHeader>
                    <DialogTitle>New SSL certificate</DialogTitle>
                    <DialogDescription>
                        Add a certificate to your site.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-2 py-4">
                    <button
                        v-for="certType in certificateTypes"
                        :key="certType.value"
                        type="button"
                        class="flex w-full items-center gap-3 rounded-lg border p-4 text-left transition-colors hover:bg-muted/50"
                        :class="{ 'border-primary bg-primary/5': selectedType === certType.value }"
                        @click="selectType(certType.value)"
                    >
                        <component
                            :is="typeIcons[certType.value as keyof typeof typeIcons]"
                            class="size-5 shrink-0 text-muted-foreground"
                        />
                        <div class="min-w-0 flex-1">
                            <div class="font-medium">{{ certType.label }}</div>
                            <div class="text-sm text-muted-foreground">
                                {{ certType.description }}
                            </div>
                        </div>
                    </button>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="close">
                        Cancel
                    </Button>
                    <Button @click="continueToForm">
                        Continue
                    </Button>
                </DialogFooter>
            </template>

            <!-- Step 2: Form -->
            <template v-else>
                <DialogHeader>
                    <button
                        type="button"
                        class="mb-2 flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                        @click="goBack"
                    >
                        <ArrowLeft class="size-4" />
                        Back to SSL certificates
                    </button>
                    <DialogTitle>{{ formTitle }}</DialogTitle>
                    <DialogDescription>
                        {{ formDescription }}
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submit" class="space-y-4 py-4">
                    <!-- Let's Encrypt Form -->
                    <template v-if="selectedType === 'letsencrypt'">
                        <!-- Domain Select -->
                        <div class="space-y-2">
                            <Label for="le-domain">Custom domain</Label>
                            <Select v-model="form.domain">
                                <SelectTrigger id="le-domain">
                                    <SelectValue placeholder="Select domain" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="domain in availableDomains"
                                        :key="domain.id"
                                        :value="domain.name"
                                    >
                                        {{ domain.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="availableDomains.length === 0" class="text-xs text-muted-foreground">
                                No enabled domains available.
                            </p>
                            <p v-if="form.errors.domain" class="text-sm text-destructive">
                                {{ form.errors.domain }}
                            </p>
                        </div>

                        <!-- Verification Method -->
                        <div class="space-y-3">
                            <Label>Verification method</Label>
                            <div class="space-y-2">
                                <button
                                    type="button"
                                    class="flex w-full items-start gap-3 rounded-md border p-3 text-left transition-colors"
                                    :class="form.verification_method === 'http'
                                        ? 'border-primary bg-primary/5'
                                        : 'border-border hover:bg-muted/50'"
                                    @click="form.verification_method = 'http'"
                                >
                                    <div
                                        class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full border"
                                        :class="form.verification_method === 'http'
                                            ? 'border-primary'
                                            : 'border-muted-foreground'"
                                    >
                                        <div
                                            v-if="form.verification_method === 'http'"
                                            class="size-2 rounded-full bg-primary"
                                        />
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium">HTTP-01</span>
                                            <span class="rounded border border-green-600 px-1.5 py-0.5 text-xs font-normal text-green-600 dark:border-green-500 dark:text-green-500">
                                                Recommended
                                            </span>
                                        </div>
                                        <p class="text-sm text-muted-foreground">
                                            Verify domain ownership via HTTP request.
                                        </p>
                                    </div>
                                </button>
                                <button
                                    type="button"
                                    class="flex w-full items-start gap-3 rounded-md border p-3 text-left transition-colors"
                                    :class="form.verification_method === 'dns'
                                        ? 'border-primary bg-primary/5'
                                        : 'border-border hover:bg-muted/50'"
                                    @click="form.verification_method = 'dns'"
                                >
                                    <div
                                        class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full border"
                                        :class="form.verification_method === 'dns'
                                            ? 'border-primary'
                                            : 'border-muted-foreground'"
                                    >
                                        <div
                                            v-if="form.verification_method === 'dns'"
                                            class="size-2 rounded-full bg-primary"
                                        />
                                    </div>
                                    <div class="flex-1">
                                        <span class="font-medium">DNS-01</span>
                                        <p class="text-sm text-muted-foreground">
                                            Verify domain ownership via DNS TXT record.
                                        </p>
                                    </div>
                                </button>

                                <!-- DNS-01 Info Box -->
                                <div
                                    v-if="form.verification_method === 'dns'"
                                    class="rounded-md border border-amber-500/50 bg-amber-500/10 p-3"
                                >
                                    <p class="text-sm text-amber-700 dark:text-amber-400">
                                        <strong>Note:</strong> DNS-01 verification requires you to add a TXT record to your domain's DNS settings. After requesting the certificate, you'll need to create a <code class="rounded bg-amber-500/20 px-1">_acme-challenge</code> TXT record with the provided verification token.
                                    </p>
                                </div>
                            </div>
                            <p v-if="form.errors.verification_method" class="text-sm text-destructive">
                                {{ form.errors.verification_method }}
                            </p>
                        </div>

                        <!-- Public Key Algorithm -->
                        <div class="space-y-3">
                            <Label>Public key algorithm</Label>
                            <div class="space-y-2">
                                <button
                                    type="button"
                                    class="flex w-full items-start gap-3 rounded-md border p-3 text-left transition-colors"
                                    :class="form.key_algorithm === 'ecdsa'
                                        ? 'border-primary bg-primary/5'
                                        : 'border-border hover:bg-muted/50'"
                                    @click="form.key_algorithm = 'ecdsa'"
                                >
                                    <div
                                        class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full border"
                                        :class="form.key_algorithm === 'ecdsa'
                                            ? 'border-primary'
                                            : 'border-muted-foreground'"
                                    >
                                        <div
                                            v-if="form.key_algorithm === 'ecdsa'"
                                            class="size-2 rounded-full bg-primary"
                                        />
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium">ECDSA secp384r1</span>
                                            <span class="rounded border border-green-600 px-1.5 py-0.5 text-xs font-normal text-green-600 dark:border-green-500 dark:text-green-500">
                                                Recommended
                                            </span>
                                        </div>
                                        <p class="text-sm text-muted-foreground">
                                            Modern algorithm with smaller keys and faster performance.
                                        </p>
                                    </div>
                                </button>
                                <button
                                    type="button"
                                    class="flex w-full items-start gap-3 rounded-md border p-3 text-left transition-colors"
                                    :class="form.key_algorithm === 'rsa'
                                        ? 'border-primary bg-primary/5'
                                        : 'border-border hover:bg-muted/50'"
                                    @click="form.key_algorithm = 'rsa'"
                                >
                                    <div
                                        class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full border"
                                        :class="form.key_algorithm === 'rsa'
                                            ? 'border-primary'
                                            : 'border-muted-foreground'"
                                    >
                                        <div
                                            v-if="form.key_algorithm === 'rsa'"
                                            class="size-2 rounded-full bg-primary"
                                        />
                                    </div>
                                    <div class="flex-1">
                                        <span class="font-medium">RSA</span>
                                        <p class="text-sm text-muted-foreground">
                                            Traditional algorithm with maximum compatibility.
                                        </p>
                                    </div>
                                </button>
                            </div>
                            <p v-if="form.errors.key_algorithm" class="text-sm text-destructive">
                                {{ form.errors.key_algorithm }}
                            </p>
                        </div>

                        <!-- ISRG Root X1 Chain -->
                        <div class="flex items-center justify-between">
                            <div class="space-y-0.5">
                                <Label for="isrg-root-chain">Enable "ISRG Root X1" chain</Label>
                                <p class="text-xs text-muted-foreground">
                                    Use the modern ISRG Root X1 certificate chain for broader compatibility.
                                </p>
                            </div>
                            <Switch
                                id="isrg-root-chain"
                                v-model="form.isrg_root_chain"
                            />
                        </div>
                    </template>

                    <!-- Existing Certificate Form -->
                    <template v-if="selectedType === 'existing'">
                        <div class="space-y-2">
                            <Label for="existing-domain">Custom domain</Label>
                            <Select v-model="form.domain">
                                <SelectTrigger id="existing-domain">
                                    <SelectValue placeholder="Select domain" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="domain in availableDomains"
                                        :key="domain.id"
                                        :value="domain.name"
                                    >
                                        {{ domain.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.domain" class="text-sm text-destructive">
                                {{ form.errors.domain }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="certificate">Certificate</Label>
                            <Textarea
                                id="certificate"
                                v-model="form.certificate"
                                placeholder="-----BEGIN CERTIFICATE-----"
                                rows="5"
                                class="font-mono text-xs"
                            />
                            <p class="text-xs text-muted-foreground">
                                Paste your full certificate chain here. This usually includes your primary certificate followed by any intermediate certificates.
                            </p>
                            <p v-if="form.errors.certificate" class="text-sm text-destructive">
                                {{ form.errors.certificate }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="private-key">Private key</Label>
                            <Textarea
                                id="private-key"
                                v-model="form.private_key"
                                placeholder="-----BEGIN PRIVATE KEY-----"
                                rows="5"
                                class="font-mono text-xs"
                            />
                            <p class="text-xs text-muted-foreground">
                                Your private key should be unencrypted.
                            </p>
                            <p v-if="form.errors.private_key" class="text-sm text-destructive">
                                {{ form.errors.private_key }}
                            </p>
                        </div>

                        <div class="flex items-center justify-between">
                            <div class="space-y-0.5">
                                <Label for="auto-activate">Automatically enable certificate after installation</Label>
                            </div>
                            <Switch
                                id="auto-activate"
                                v-model="form.auto_activate"
                            />
                        </div>
                    </template>

                    <!-- CSR Form -->
                    <template v-if="selectedType === 'csr'">
                        <div class="space-y-2">
                            <Label for="csr-domain">Custom domain</Label>
                            <Select v-model="form.domain">
                                <SelectTrigger id="csr-domain">
                                    <SelectValue placeholder="Select domain" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="domain in availableDomains"
                                        :key="domain.id"
                                        :value="domain.name"
                                    >
                                        {{ domain.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p class="text-xs text-muted-foreground">
                                Select the domain to create the certificate for.
                            </p>
                            <p v-if="form.errors.domain" class="text-sm text-destructive">
                                {{ form.errors.domain }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="sans">Subject Alternative Names (SANs)</Label>
                            <Textarea
                                id="sans"
                                v-model="form.sans"
                                placeholder="www.example.com&#10;api.example.com"
                                rows="3"
                            />
                            <p class="text-xs text-muted-foreground">
                                Enter additional domain names, one per line.
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="csr-country">Country</Label>
                            <Select v-model="form.csr_country">
                                <SelectTrigger id="csr-country">
                                    <SelectValue placeholder="Select country" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="country in countries"
                                        :key="country.code"
                                        :value="country.code"
                                    >
                                        {{ country.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.csr_country" class="text-sm text-destructive">
                                {{ form.errors.csr_country }}
                            </p>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="csr-state">State</Label>
                                <Input
                                    id="csr-state"
                                    v-model="form.csr_state"
                                    placeholder="California"
                                />
                                <p v-if="form.errors.csr_state" class="text-sm text-destructive">
                                    {{ form.errors.csr_state }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="csr-city">City</Label>
                                <Input
                                    id="csr-city"
                                    v-model="form.csr_city"
                                    placeholder="San Francisco"
                                />
                                <p v-if="form.errors.csr_city" class="text-sm text-destructive">
                                    {{ form.errors.csr_city }}
                                </p>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="csr-organization">Organization</Label>
                            <Input
                                id="csr-organization"
                                v-model="form.csr_organization"
                                placeholder="My Company Inc."
                            />
                            <p v-if="form.errors.csr_organization" class="text-sm text-destructive">
                                {{ form.errors.csr_organization }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="csr-department">Department</Label>
                            <Input
                                id="csr-department"
                                v-model="form.csr_department"
                                placeholder="IT Department"
                            />
                            <p v-if="form.errors.csr_department" class="text-sm text-destructive">
                                {{ form.errors.csr_department }}
                            </p>
                        </div>
                    </template>

                    <!-- Clone Form -->
                    <template v-if="selectedType === 'clone'">
                        <div class="space-y-2">
                            <Label for="clone-domain">Custom domain</Label>
                            <Select v-model="form.domain">
                                <SelectTrigger id="clone-domain">
                                    <SelectValue placeholder="Select domain" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="domain in availableDomains"
                                        :key="domain.id"
                                        :value="domain.name"
                                    >
                                        {{ domain.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p class="text-xs text-muted-foreground">
                                Select the domain to create the certificate for.
                            </p>
                            <p v-if="form.errors.domain" class="text-sm text-destructive">
                                {{ form.errors.domain }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="source-certificate">Certificate</Label>
                            <Select v-model="form.source_certificate_id">
                                <SelectTrigger id="source-certificate">
                                    <SelectValue placeholder="Select a certificate" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="cert in cloneableCertificates"
                                        :key="cert.id"
                                        :value="cert.id"
                                    >
                                        {{ cert.displayableType }} - {{ Object.values(cert.domains).join(', ') }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="cloneableCertificates.length === 0" class="text-sm text-muted-foreground">
                                No certificates available to clone.
                            </p>
                            <p v-if="form.errors.source_certificate_id" class="text-sm text-destructive">
                                {{ form.errors.source_certificate_id }}
                            </p>
                        </div>
                    </template>

                    <DialogFooter class="pt-4">
                        <Button type="button" variant="outline" @click="close">
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="!canSubmit">
                            {{ submitLabel }}
                        </Button>
                    </DialogFooter>
                </form>
            </template>
        </DialogContent>
    </Dialog>
</template>
