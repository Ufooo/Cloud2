import type {
    DatabaseOptionData,
    PhpVersionOptionData,
    SelectOptionData,
} from './generated';

export interface SiteTypeData {
    value: string;
    label: string;
    webDirectory: string;
    buildCommand: string | null;
    isPhpBased: boolean;
    supportsZeroDowntime: boolean;
}

export interface Repository {
    id: number;
    full_name: string;
    name: string;
    private: boolean;
    default_branch: string;
}

export interface SourceControlOption {
    id: number;
    provider: string;
    providerLabel: string;
    name: string;
}

export interface ServerOption {
    id: number;
    slug: string;
    name: string;
    phpVersions: PhpVersionOptionData[];
    unixUsers: SelectOptionData[];
    databases: DatabaseOptionData[];
    databaseUsers: SelectOptionData[];
}

export interface WwwRedirectTypeOption {
    value: string;
    label: string;
    description: string;
    isDefault: boolean;
}
