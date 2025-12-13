<script setup lang="ts">
import { store as storeCertificate } from '@/actions/Nip/Domain/Http/Controllers/CertificateController';
import { store } from '@/actions/Nip/Domain/Http/Controllers/DomainRecordController';
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
import SiteLayout from '@/layouts/SiteLayout.vue';
import type { CertificateData, DomainRecordData, Site } from '@/types';
import type { PaginatedResponse } from '@/types/pagination';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ConfigureDomainModal from '../partials/ConfigureDomainModal.vue';
import CertificateItem from './partials/CertificateItem.vue';
import DomainRecordItem from './partials/DomainRecordItem.vue';

interface SelectOption {
    value: string;
    label: string;
}

interface WwwRedirectTypeOption {
    value: string;
    label: string;
    description: string;
    isDefault: boolean;
}

interface Props {
    site: Site;
    domainRecords: PaginatedResponse<DomainRecordData>;
    certificates: PaginatedResponse<CertificateData>;
    wwwRedirectTypes: WwwRedirectTypeOption[];
    certificateTypes: SelectOption[];
    can: {
        domains: { create: boolean };
        certificates: { create: boolean };
    };
}

const props = defineProps<Props>();

const showAddCertificateModal = ref(false);
const showConfigureDomainModal = ref(false);

const addDomainForm = useForm({
    name: '',
    type: 'alias',
    allow_wildcard: false,
    www_redirect_type: 'from_www',
});

const addCertificateForm = useForm({
    type: 'letsencrypt',
    domains: [] as string[],
    certificate: '',
    private_key: '',
});

const hasDomains = computed(() => props.domainRecords.data.length > 0);
const hasCertificates = computed(() => props.certificates.data.length > 0);

function openConfigureDomainModal() {
    if (!addDomainForm.name.trim()) {
        return;
    }
    addDomainForm.www_redirect_type = 'from_www';
    addDomainForm.allow_wildcard = false;
    showConfigureDomainModal.value = true;
}

function submitAddDomain() {
    addDomainForm.post(store.url({ site: props.site }), {
        preserveScroll: true,
        onSuccess: () => addDomainForm.reset(),
    });
}

function openAddCertificateModal() {
    addCertificateForm.reset();
    addCertificateForm.type = 'letsencrypt';
    addCertificateForm.domains = [];
    addCertificateForm.certificate = '';
    addCertificateForm.private_key = '';
    showAddCertificateModal.value = true;
}

function submitAddCertificate() {
    addCertificateForm.post(storeCertificate.url({ site: props.site }), {
        preserveScroll: true,
        onSuccess: () => {
            showAddCertificateModal.value = false;
        },
    });
}
</script>

<template>
    <Head :title="`Domains - ${site.domain}`" />

    <SiteLayout :site="site">
        <div class="space-y-6">
            <!-- Page Title -->
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold">Domains</h1>
            </div>

            <!-- Domains Card -->
            <Card>
                <CardHeader>
                    <CardTitle>Domains</CardTitle>
                    <CardDescription>
                        Manage your site's domains and SSL certificates.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <!-- Custom domains section -->
                    <div class="rounded-lg border bg-muted/30 p-4">
                        <div class="mb-3">
                            <p class="text-sm font-medium">Custom domains</p>
                            <p class="text-sm text-muted-foreground">
                                Add custom domains and aliases that you own.
                            </p>
                        </div>

                        <!-- Add domain inline form -->
                        <form
                            v-if="can.domains.create"
                            @submit.prevent="openConfigureDomainModal"
                            class="mb-4 flex gap-2"
                        >
                            <Input
                                v-model="addDomainForm.name"
                                type="text"
                                placeholder="your-domain.com"
                                class="flex-1"
                                :disabled="addDomainForm.processing"
                            />
                            <Button
                                type="submit"
                                variant="outline"
                                :disabled="
                                    addDomainForm.processing ||
                                    !addDomainForm.name.trim()
                                "
                            >
                                {{
                                    addDomainForm.processing
                                        ? 'Adding...'
                                        : 'Add domain'
                                }}
                            </Button>
                        </form>
                        <p
                            v-if="addDomainForm.errors.name"
                            class="-mt-2 mb-4 text-sm text-destructive"
                        >
                            {{ addDomainForm.errors.name }}
                        </p>

                        <!-- Domains list -->
                        <div
                            v-if="hasDomains"
                            class="space-y-2 rounded-lg border bg-background"
                        >
                            <DomainRecordItem
                                v-for="domain in domainRecords.data"
                                :key="domain.id"
                                :domain="domain"
                                :site="site"
                                :www-redirect-types="wwwRedirectTypes"
                            />
                        </div>
                        <div
                            v-else
                            class="rounded-lg border bg-background p-8 text-center"
                        >
                            <h3 class="font-medium">No custom domains yet</h3>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Get started and add your first custom domain.
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Certificates Card -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle>Certificates</CardTitle>
                            <CardDescription>
                                Manage your site's SSL certificates.
                            </CardDescription>
                        </div>
                        <Button
                            v-if="can.certificates.create && hasDomains"
                            variant="outline"
                            @click="openAddCertificateModal"
                        >
                            Add certificate
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <div v-if="hasCertificates" class="space-y-2">
                        <CertificateItem
                            v-for="cert in certificates.data"
                            :key="cert.id"
                            :certificate="cert"
                            :site="site"
                        />
                    </div>
                    <div v-else class="rounded-lg border p-8 text-center">
                        <h3 class="font-medium">No custom domains yet</h3>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Add a custom domain to generate and manage
                            certificates here.
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Add Certificate Modal -->
        <Dialog v-model:open="showAddCertificateModal">
            <DialogContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Add SSL certificate</DialogTitle>
                    <DialogDescription>
                        Install an SSL certificate for your domains.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitAddCertificate" class="space-y-4">
                    <div class="space-y-2">
                        <Label for="cert-type">Certificate type</Label>
                        <Select v-model="addCertificateForm.type">
                            <SelectTrigger id="cert-type">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="type in certificateTypes"
                                    :key="type.value"
                                    :value="type.value"
                                >
                                    {{ type.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p
                            v-if="addCertificateForm.errors.type"
                            class="text-sm text-destructive"
                        >
                            {{ addCertificateForm.errors.type }}
                        </p>
                    </div>

                    <div
                        v-if="addCertificateForm.type === 'letsencrypt'"
                        class="space-y-2"
                    >
                        <Label for="cert-domains">Domains</Label>
                        <Input
                            id="cert-domains"
                            type="text"
                            placeholder="example.com, www.example.com (comma-separated)"
                        />
                        <p class="text-sm text-muted-foreground">
                            Enter the domains to include in this certificate.
                        </p>
                    </div>

                    <div
                        v-if="addCertificateForm.type === 'existing'"
                        class="space-y-4"
                    >
                        <div class="space-y-2">
                            <Label for="cert-certificate">Certificate</Label>
                            <Textarea
                                id="cert-certificate"
                                v-model="addCertificateForm.certificate"
                                placeholder="-----BEGIN CERTIFICATE-----"
                                rows="6"
                            />
                            <p
                                v-if="addCertificateForm.errors.certificate"
                                class="text-sm text-destructive"
                            >
                                {{ addCertificateForm.errors.certificate }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="cert-private-key">Private key</Label>
                            <Textarea
                                id="cert-private-key"
                                v-model="addCertificateForm.private_key"
                                placeholder="-----BEGIN PRIVATE KEY-----"
                                rows="6"
                            />
                            <p
                                v-if="addCertificateForm.errors.private_key"
                                class="text-sm text-destructive"
                            >
                                {{ addCertificateForm.errors.private_key }}
                            </p>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="showAddCertificateModal = false"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :disabled="addCertificateForm.processing"
                        >
                            {{
                                addCertificateForm.processing
                                    ? 'Installing...'
                                    : 'Install certificate'
                            }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Configure Domain Modal -->
        <ConfigureDomainModal
            :open="showConfigureDomainModal"
            :domain="addDomainForm.name"
            :www-redirect-type="addDomainForm.www_redirect_type"
            :www-redirect-types="wwwRedirectTypes"
            :allow-wildcard="addDomainForm.allow_wildcard"
            @update:open="showConfigureDomainModal = $event"
            @update:www-redirect-type="addDomainForm.www_redirect_type = $event"
            @update:allow-wildcard="addDomainForm.allow_wildcard = $event"
            @save="submitAddDomain"
        />
    </SiteLayout>
</template>
