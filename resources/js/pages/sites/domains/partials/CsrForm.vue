<script setup lang="ts">
/* eslint-disable vue/no-mutating-props -- Inertia form is designed to be mutated */
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { DomainRecordData } from '@/types';
import type { InertiaForm } from '@inertiajs/vue3';
import { computed } from 'vue';

interface CertificateFormData {
    domain: string;
    sans: string;
    csr_country: string;
    csr_state: string;
    csr_city: string;
    csr_organization: string;
    csr_department: string;
    [key: string]: unknown;
}

interface Props {
    domainRecords: DomainRecordData[];
    countries: Array<{ code: string; name: string }>;
    form: InertiaForm<CertificateFormData>;
}

const props = defineProps<Props>();

const availableDomains = computed(() => {
    return props.domainRecords.filter(
        (d) => d.status === 'enabled' && !d.hasActiveCertificate,
    );
});
</script>

<template>
    <div class="space-y-4">
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
            <p
                v-if="form.errors.csr_organization"
                class="text-sm text-destructive"
            >
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
            <p
                v-if="form.errors.csr_department"
                class="text-sm text-destructive"
            >
                {{ form.errors.csr_department }}
            </p>
        </div>
    </div>
</template>
