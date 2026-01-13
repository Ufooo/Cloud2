<script setup lang="ts">
import { Label } from '@/components/ui/label';
import { Copy } from 'lucide-vue-next';

interface VerificationRecord {
    requiresVerification?: boolean;
    verified: boolean;
    type: string;
    name: string;
    value: string;
    ttl: number;
}

interface Props {
    records: VerificationRecord[];
}

defineProps<Props>();

async function copyToClipboard(text: string) {
    await navigator.clipboard.writeText(text);
}
</script>

<template>
    <div v-if="records.length > 0" class="space-y-3">
        <div>
            <Label class="mb-2 block">Verification records</Label>
            <p class="mb-3 text-sm text-muted-foreground">
                The following DNS records must be added to your DNS provider
                before you can obtain a Let's Encrypt certificate.
            </p>
        </div>

        <div
            v-for="record in records"
            :key="record.name"
            class="rounded-md border"
        >
            <div class="flex items-center justify-between border-b px-4 py-2.5">
                <span class="text-sm text-muted-foreground">Type</span>
                <div class="flex items-center gap-3">
                    <span class="font-mono text-sm">{{ record.type }}</span>
                    <span
                        v-if="record.verified"
                        class="flex items-center gap-1 text-xs text-green-600 dark:text-green-500"
                    >
                        <span class="size-1.5 rounded-full bg-green-500" />
                        Verified
                    </span>
                    <span
                        v-else
                        class="flex items-center gap-1 text-xs text-amber-600 dark:text-amber-500"
                    >
                        <span
                            class="size-1.5 animate-pulse rounded-full bg-amber-500"
                        />
                        Verifying
                    </span>
                </div>
            </div>
            <div class="flex items-center justify-between border-b px-4 py-2.5">
                <span class="text-sm text-muted-foreground">Name</span>
                <div class="flex items-center gap-2">
                    <code class="font-mono text-sm">{{ record.name }}</code>
                    <button
                        type="button"
                        class="text-muted-foreground hover:text-foreground"
                        @click="copyToClipboard(record.name)"
                    >
                        <Copy class="size-4" />
                    </button>
                </div>
            </div>
            <div class="flex items-center justify-between border-b px-4 py-2.5">
                <span class="text-sm text-muted-foreground">Value</span>
                <div class="flex items-center gap-2">
                    <code class="font-mono text-sm">{{ record.value }}</code>
                    <button
                        type="button"
                        class="text-muted-foreground hover:text-foreground"
                        @click="copyToClipboard(record.value)"
                    >
                        <Copy class="size-4" />
                    </button>
                </div>
            </div>
            <div class="flex items-center justify-between px-4 py-2.5">
                <span class="text-sm text-muted-foreground">TTL</span>
                <span class="font-mono text-sm">{{ record.ttl }} seconds</span>
            </div>
        </div>

        <div class="rounded-md border border-amber-500/50 bg-amber-500/10 p-3">
            <p class="text-sm text-amber-700 dark:text-amber-400">
                <strong>Using Cloudflare?</strong> Make sure the CNAME records
                have the proxy (orange cloud) turned off for DNS-01 verification
                to work.
            </p>
        </div>
    </div>
</template>
