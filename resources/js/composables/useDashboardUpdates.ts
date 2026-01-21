import { useEcho } from '@laravel/echo-vue';

interface ServerMetricsPayload {
    serverId: number;
    status: string;
    statusLabel: string;
    isConnected: boolean;
    uptimeFormatted: string | null;
    loadAvgFormatted: string | null;
    cpuPercent: number;
    ramTotalBytes: number | null;
    ramUsedBytes: number | null;
    ramPercent: number;
    diskTotalBytes: number | null;
    diskUsedBytes: number | null;
    diskPercent: number;
    lastMetricsAt: string | null;
}

export function useDashboardUpdates(
    onServerMetricsUpdated: (payload: ServerMetricsPayload) => void,
) {
    useEcho('dashboard', '.ServerMetricsUpdated', (event: ServerMetricsPayload) => {
        onServerMetricsUpdated(event);
    });
}

export type { ServerMetricsPayload };
