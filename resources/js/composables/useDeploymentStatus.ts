import { CheckCircle, Clock, Loader2, XCircle } from 'lucide-vue-next';
import type { Component } from 'vue';

export type DeploymentStatusType = 'finished' | 'failed' | 'deploying' | 'pending';

export function useDeploymentStatus() {
    function getStatusIcon(status: string): Component {
        switch (status) {
            case 'finished':
                return CheckCircle;
            case 'failed':
                return XCircle;
            case 'deploying':
                return Loader2;
            default:
                return Clock;
        }
    }

    function getStatusLabel(status: string): string {
        switch (status) {
            case 'finished':
                return 'Finished';
            case 'failed':
                return 'Failed';
            case 'deploying':
                return 'Deploying';
            default:
                return 'Pending';
        }
    }

    function getStatusClass(status: string): string {
        switch (status) {
            case 'finished':
                return 'text-green-500';
            case 'failed':
                return 'text-red-500';
            case 'deploying':
                return 'text-blue-500';
            default:
                return 'text-yellow-500';
        }
    }

    function getStatusBgClass(status: string): string {
        switch (status) {
            case 'finished':
                return 'bg-green-500/10 text-green-600 dark:text-green-400';
            case 'failed':
                return 'bg-red-500/10 text-red-600 dark:text-red-400';
            case 'deploying':
                return 'bg-blue-500/10 text-blue-600 dark:text-blue-400';
            default:
                return 'bg-yellow-500/10 text-yellow-600 dark:text-yellow-400';
        }
    }

    function isDeploying(status: string): boolean {
        return status === 'deploying';
    }

    return {
        getStatusIcon,
        getStatusLabel,
        getStatusClass,
        getStatusBgClass,
        isDeploying,
    };
}
