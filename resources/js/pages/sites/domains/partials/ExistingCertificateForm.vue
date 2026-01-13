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
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import type { DomainRecordData } from '@/types';
import type { InertiaForm } from '@inertiajs/vue3';
import { computed } from 'vue';

interface CertificateFormData {
    domain: string;
    certificate: string;
    private_key: string;
    auto_activate: boolean;
    [key: string]: unknown;
}

interface Props {
    domainRecords: DomainRecordData[];
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
                Paste your full certificate chain here. This usually includes
                your primary certificate followed by any intermediate
                certificates.
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
                <Label for="auto-activate"
                    >Automatically enable certificate after installation</Label
                >
            </div>
            <Switch id="auto-activate" v-model="form.auto_activate" />
        </div>
    </div>
</template>
