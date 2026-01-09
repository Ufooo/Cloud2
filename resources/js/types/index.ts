import { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from 'lucide-vue-next';

export type { PaginatedResponse as Paginated } from './pagination';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
    badge?: string | number;
}

export interface SidebarCounts {
    sites: number;
}

export type AppPageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    sidebarOpen: boolean;
    counts: SidebarCounts;
};

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;

export interface Deployment {
    id: string;
    status: string;
    statusLabel: string;
    statusColor: string;
    commitHash: string | null;
    shortCommitHash: string | null;
    commitMessage: string | null;
    commitAuthor: string | null;
    branch: string | null;
    deployedBy: string | null;
    startedAt: string | null;
    endedAt: string | null;
    duration: number | null;
    durationForHumans: string | null;
    createdAt: string | null;
    createdAtForHumans: string | null;
}

export interface DeploymentDetail extends Deployment {
    output: string | null;
}

export {
    IdentityColor,
    ProvisionScriptStatus,
    ServerProvider,
    ServerStatus,
    ServerType,
    type CertificateData,
    type DomainRecordData,
    type ProvisionScriptData,
    type ServerData as Server,
    type ServerCreateData,
    type ServerPermissionsData,
    type ServerProviderOptionData,
    type ServerTypeOptionData,
    type SiteData as Site,
} from './generated';
