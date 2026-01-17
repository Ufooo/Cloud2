<script setup lang="ts">
/* eslint-disable vue/no-mutating-props -- Inertia form is designed to be mutated */
import RadioOptionButton from '@/components/shared/RadioOptionButton.vue';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { useDnsVerification } from '@/composables/useDnsVerification';
import type { DomainRecordData, Site } from '@/types';
import type { InertiaForm } from '@inertiajs/vue3';
import { computed, toRef, watch } from 'vue';
import DnsVerificationRecords from './DnsVerificationRecords.vue';

interface CertificateFormData {
    type: string;
    domain: string;
    verification_method: 'http' | 'dns';
    key_algorithm: 'ecdsa' | 'rsa';
    isrg_root_chain: boolean;
    acme_subdomains: Record<string, string>;
    certificate: string;
    private_key: string;
    auto_activate: boolean;
    sans: string;
    csr_country: string;
    csr_state: string;
    csr_city: string;
    csr_organization: string;
    csr_department: string;
    source_certificate_id: string;
}

interface Props {
    site: Site;
    domainRecords: DomainRecordData[];
    form: InertiaForm<CertificateFormData>;
}

const props = defineProps<Props>();

const availableDomains = computed(() => {
    return props.domainRecords
        .filter((d) => d.status === 'enabled' && !d.hasCertificate)
        .map((d) => ({
            ...d,
            displayName: d.allowWildcard ? `*.${d.name}` : d.name,
        }));
});

const selectedDomainRecord = computed(() => {
    return availableDomains.value.find((d) => d.name === props.form.domain);
});

const isWildcardDomain = computed(() => {
    return selectedDomainRecord.value?.allowWildcard === true;
});

const isDnsVerification = computed(() => {
    return props.form.verification_method === 'dns';
});

const domainRecordId = computed(() => selectedDomainRecord.value?.id ?? null);

const { liveRecords } = useDnsVerification({
    siteSlug: props.site.slug,
    domainRecordId: toRef(domainRecordId),
    enabled: isDnsVerification,
});

const verificationRecords = computed(() => {
    if (!isDnsVerification.value || !selectedDomainRecord.value) return [];
    if (liveRecords.value.length > 0) {
        return liveRecords.value;
    }
    return selectedDomainRecord.value.verificationRecords ?? [];
});

watch(isWildcardDomain, (isWildcard) => {
    if (isWildcard) {
        props.form.verification_method = 'dns';
    }
});

watch(
    () => props.form.domain,
    () => {
        if (selectedDomainRecord.value?.acmeSubdomains) {
            props.form.acme_subdomains = {
                ...selectedDomainRecord.value.acmeSubdomains,
            };
        } else {
            props.form.acme_subdomains = {};
        }
    },
);
</script>

<template>
    <div class="space-y-4">
        <!-- Domain Select -->
        <div class="space-y-2">
            <Label for="le-domain">Custom domain</Label>
            <Select v-model="form.domain">
                <SelectTrigger id="le-domain">
                    <SelectValue placeholder="Select domain">
                        {{ selectedDomainRecord?.displayName ?? 'Select domain' }}
                    </SelectValue>
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="domain in availableDomains"
                        :key="domain.id"
                        :value="domain.name"
                    >
                        {{ domain.displayName }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <p
                v-if="availableDomains.length === 0"
                class="text-xs text-muted-foreground"
            >
                No enabled domains available.
            </p>
            <p v-if="form.errors.domain" class="text-sm text-destructive">
                {{ form.errors.domain }}
            </p>
        </div>

        <!-- Verification Method -->
        <div v-if="!isWildcardDomain" class="space-y-3">
            <Label>Verification method</Label>
            <div class="space-y-2">
                <RadioOptionButton
                    :selected="form.verification_method === 'http'"
                    label="HTTP-01"
                    description="Verify domain ownership via HTTP request."
                    badge="Recommended"
                    @select="form.verification_method = 'http'"
                />
                <RadioOptionButton
                    :selected="form.verification_method === 'dns'"
                    label="DNS-01"
                    description="Verify domain ownership via DNS record."
                    @select="form.verification_method = 'dns'"
                />
            </div>
            <p
                v-if="form.errors.verification_method"
                class="text-sm text-destructive"
            >
                {{ form.errors.verification_method }}
            </p>
        </div>

        <!-- DNS Verification Records -->
        <DnsVerificationRecords
            v-if="isDnsVerification"
            :records="verificationRecords"
        />

        <!-- Public Key Algorithm -->
        <div class="space-y-3">
            <Label>Public key algorithm</Label>
            <div class="space-y-2">
                <RadioOptionButton
                    :selected="form.key_algorithm === 'ecdsa'"
                    label="ECDSA secp384r1"
                    description="Modern algorithm with smaller keys and faster performance."
                    badge="Recommended"
                    @select="form.key_algorithm = 'ecdsa'"
                />
                <RadioOptionButton
                    :selected="form.key_algorithm === 'rsa'"
                    label="RSA"
                    description="Traditional algorithm with maximum compatibility."
                    @select="form.key_algorithm = 'rsa'"
                />
            </div>
            <p v-if="form.errors.key_algorithm" class="text-sm text-destructive">
                {{ form.errors.key_algorithm }}
            </p>
        </div>

        <!-- ISRG Root X1 Chain -->
        <div class="flex items-center justify-between">
            <div class="space-y-0.5">
                <Label for="isrg-root-chain"
                    >Enable "ISRG Root X1" chain</Label
                >
                <p class="text-xs text-muted-foreground">
                    Use the modern ISRG Root X1 certificate chain for broader
                    compatibility.
                </p>
            </div>
            <Switch id="isrg-root-chain" v-model="form.isrg_root_chain" />
        </div>
    </div>
</template>
