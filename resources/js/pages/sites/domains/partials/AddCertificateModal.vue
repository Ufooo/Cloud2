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
import type { CertificateData, DomainRecordData, Site } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { ArrowLeft, FileKey, Lock, RefreshCcw, Shield } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import CloneCertificateForm from './CloneCertificateForm.vue';
import CsrForm from './CsrForm.vue';
import ExistingCertificateForm from './ExistingCertificateForm.vue';
import LetsEncryptForm from './LetsEncryptForm.vue';

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
    cloneableCertificates: CertificateData[];
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
    domain: '',
    verification_method: 'http' as 'http' | 'dns',
    key_algorithm: 'ecdsa' as 'ecdsa' | 'rsa',
    isrg_root_chain: false,
    acme_subdomains: {} as Record<string, string>,
    certificate: '',
    private_key: '',
    auto_activate: true,
    sans: '',
    csr_country: '',
    csr_state: '',
    csr_city: '',
    csr_organization: '',
    csr_department: '',
    source_certificate_id: '',
});

const typeIcons = {
    letsencrypt: Lock,
    existing: FileKey,
    csr: Shield,
    clone: RefreshCcw,
};

const formTitle = computed(() => {
    const titles: Record<string, string> = {
        letsencrypt: "Let's Encrypt certificate",
        existing: 'Install existing certificate',
        csr: 'Create signing request',
        clone: 'Clone certificate',
    };
    return titles[selectedType.value] ?? 'Add certificate';
});

const formDescription = computed(() => {
    const descriptions: Record<string, string> = {
        letsencrypt:
            "Configure and obtain a free SSL certificate from Let's Encrypt.",
        existing: 'Provide your existing SSL certificate and private key.',
        csr: 'Generate a certificate signing request for an external certificate authority.',
        clone: 'Pick a certificate from another site to clone.',
    };
    return descriptions[selectedType.value] ?? '';
});

const submitLabel = computed(() => {
    if (form.processing) return 'Processing...';

    const labels: Record<string, string> = {
        letsencrypt: 'Obtain certificate',
        existing: 'Install certificate',
        csr: 'Create signing request',
        clone: 'Clone certificate',
    };
    return labels[selectedType.value] ?? 'Submit';
});

const canSubmit = computed(() => {
    if (form.processing) return false;

    switch (selectedType.value) {
        case 'letsencrypt':
            return !!form.domain;
        case 'existing':
            return form.domain && form.certificate && form.private_key;
        case 'csr':
            return (
                form.domain &&
                form.csr_country &&
                form.csr_state &&
                form.csr_city &&
                form.csr_organization &&
                form.csr_department
            );
        case 'clone':
            return form.domain && form.source_certificate_id;
        default:
            return false;
    }
});

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            resetForm();
        }
    },
);

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
                        :class="{
                            'border-primary bg-primary/5':
                                selectedType === certType.value,
                        }"
                        @click="selectType(certType.value)"
                    >
                        <component
                            :is="
                                typeIcons[
                                    certType.value as keyof typeof typeIcons
                                ]
                            "
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
                    <Button variant="outline" @click="close"> Cancel </Button>
                    <Button @click="continueToForm"> Continue </Button>
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

                <form class="space-y-4 py-4" @submit.prevent="submit">
                    <LetsEncryptForm
                        v-if="selectedType === 'letsencrypt'"
                        :site="site"
                        :domain-records="domainRecords"
                        :form="form"
                    />

                    <ExistingCertificateForm
                        v-else-if="selectedType === 'existing'"
                        :domain-records="domainRecords"
                        :form="form"
                    />

                    <CsrForm
                        v-else-if="selectedType === 'csr'"
                        :domain-records="domainRecords"
                        :countries="countries"
                        :form="form"
                    />

                    <CloneCertificateForm
                        v-else-if="selectedType === 'clone'"
                        :domain-records="domainRecords"
                        :certificates="cloneableCertificates"
                        :form="form"
                    />

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
