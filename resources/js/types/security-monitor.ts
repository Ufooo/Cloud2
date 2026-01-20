import type { BadgeVariants } from '@/components/ui/badge';

export type BadgeVariant = NonNullable<BadgeVariants['variant']>;

export interface SecurityScan {
    id: number;
    status: string;
    statusLabel: string;
    statusBadgeVariant: BadgeVariant;
    gitModifiedCount: number;
    gitUntrackedCount: number;
    gitDeletedCount: number;
    gitWhitelistedCount: number;
    gitNewCount: number;
    errorMessage: string | null;
    startedAt: string | null;
    completedAt: string | null;
    completedAtHuman: string | null;
    createdAt: string;
}

export interface GitChange {
    id: number;
    filePath: string;
    changeType: string;
    changeTypeLabel: string;
    changeTypeBadgeVariant: BadgeVariant;
    gitStatusCode: string;
    isWhitelisted: boolean;
    whitelistReason: string | null;
    whitelistedAt: string | null;
    createdAt: string;
}

export interface GitWhitelist {
    id: number;
    filePath: string;
    changeType: string;
    changeTypeLabel: string;
    reason: string | null;
    createdAt: string;
}

export interface SecuritySite {
    id: number;
    slug: string;
    domain: string;
    server: {
        id: number;
        name: string;
        slug: string;
    };
    lastScan: SecurityScan | null;
    gitMonitorEnabled: boolean;
    securityScanIntervalMinutes: number;
    securityScanRetentionDays: number;
}

export interface SecuritySummary {
    totalSites: number;
    cleanSites: number;
    gitIssuesSites: number;
    errorSites: number;
}

export interface ServerGroup {
    server: {
        id: number;
        name: string;
        slug: string;
        ipAddress: string;
    };
    sites: SecuritySite[];
}
