<script setup lang="ts">
/* eslint-disable vue/no-mutating-props -- Inertia form is designed to be mutated */
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { CertificateData, DomainRecordData } from '@/types';
import type { InertiaForm } from '@inertiajs/vue3';
import { computed } from 'vue';

interface CertificateFormData {
    domain: string;
    source_certificate_id: string;
    [key: string]: unknown;
}

interface Props {
    domainRecords: DomainRecordData[];
    certificates: CertificateData[];
    form: InertiaForm<CertificateFormData>;
}

const props = defineProps<Props>();

const availableDomains = computed(() => {
    return props.domainRecords.filter(
        (d) => d.status === 'enabled' && !d.hasCertificate,
    );
});

const cloneableCertificates = computed(() => {
    return props.certificates.filter((c) => c.status === 'installed');
});
</script>

<template>
    <div class="space-y-4">
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
                        {{ cert.displayableType }} -
                        {{ Object.values(cert.domains).join(', ') }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <p
                v-if="cloneableCertificates.length === 0"
                class="text-sm text-muted-foreground"
            >
                No certificates available to clone.
            </p>
            <p
                v-if="form.errors.source_certificate_id"
                class="text-sm text-destructive"
            >
                {{ form.errors.source_certificate_id }}
            </p>
        </div>
    </div>
</template>
