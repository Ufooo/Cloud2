import { verifyDns } from '@/actions/Nip/Domain/Http/Controllers/DomainRecordController';
import { onUnmounted, ref, watch, type Ref } from 'vue';

export interface VerificationRecord {
    requiresVerification?: boolean;
    verified: boolean;
    type: string;
    name: string;
    value: string;
    ttl: number;
}

interface UseDnsVerificationOptions {
    siteSlug: string;
    domainRecordId: Ref<string | null>;
    enabled: Ref<boolean>;
}

export function useDnsVerification(options: UseDnsVerificationOptions) {
    const { siteSlug, domainRecordId, enabled } = options;

    const liveRecords = ref<VerificationRecord[]>([]);
    const allVerified = ref(false);
    const pollingIntervalId = ref<ReturnType<typeof setInterval> | null>(null);

    async function checkVerification() {
        if (!domainRecordId.value || !enabled.value) {
            return;
        }

        try {
            const response = await fetch(
                verifyDns.url({
                    site: siteSlug,
                    domainRecord: domainRecordId.value,
                }),
                {
                    credentials: 'include',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                },
            );

            if (!response.ok) {
                return;
            }

            const data = await response.json();

            liveRecords.value = data.records;
            allVerified.value = data.allVerified;

            if (data.allVerified) {
                stopPolling();
            }
        } catch {
            // Silent fail - polling will retry
        }
    }

    function startPolling() {
        if (pollingIntervalId.value) {
            return;
        }

        checkVerification();
        pollingIntervalId.value = setInterval(checkVerification, 5000);
    }

    function stopPolling() {
        if (pollingIntervalId.value) {
            clearInterval(pollingIntervalId.value);
            pollingIntervalId.value = null;
        }
    }

    function reset() {
        stopPolling();
        liveRecords.value = [];
        allVerified.value = false;
    }

    watch([enabled, domainRecordId], ([isEnabled, recordId]) => {
        if (isEnabled && recordId) {
            startPolling();
        } else {
            stopPolling();
            liveRecords.value = [];
        }
    });

    onUnmounted(() => {
        stopPolling();
    });

    return {
        liveRecords,
        allVerified,
        checkVerification,
        startPolling,
        stopPolling,
        reset,
    };
}
