import {
    branches as fetchBranches,
    repositories as fetchRepositories,
} from '@/actions/Nip/SourceControl/Http/Controllers/SourceControlController';
import type {
    DatabaseOptionData,
    PhpVersionOptionData,
    SelectOptionData,
} from '@/types/generated';
import axios from 'axios';
import { computed, ref, watch, type Ref } from 'vue';

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

export interface UseSiteCreationFormOptions {
    servers: Ref<ServerOption[]>;
    wwwRedirectTypes: Ref<WwwRedirectTypeOption[]>;
}

export function useSiteCreationForm(options: UseSiteCreationFormOptions) {
    const { servers, wwwRedirectTypes } = options;

    // Server and user selection
    const selectedServer = ref<string>(servers.value[0]?.id.toString() || '');
    const selectedPhpVersion = ref<string>('');
    const selectedUser = ref<string>('');

    // Domain configuration
    const selectedWwwRedirect = ref<string>(
        wwwRedirectTypes.value.find((t) => t.isDefault)?.value || 'from_www',
    );
    const allowWildcard = ref(false);
    const domainValue = ref('');
    const showDomainModal = ref(false);

    // Site options
    const installComposer = ref(true);
    const zeroDowntime = ref(false);

    // Database configuration
    const createDatabase = ref(false);
    const selectedDatabase = ref<string | undefined>(undefined);
    const selectedDatabaseUser = ref<string | undefined>(undefined);
    const databaseName = ref('');
    const databaseUser = ref('');
    const databasePassword = ref('');

    // Source control
    const selectedSourceControl = ref<string>('');
    const selectedRepository = ref<string>('');
    const selectedBranch = ref<string>('');
    const repositories = ref<Repository[]>([]);
    const branches = ref<string[]>([]);
    const loadingRepositories = ref(false);
    const loadingBranches = ref(false);
    const repositorySearchQuery = ref<string>('');
    const repositoryComboboxOpen = ref(false);

    // Computed properties for server-dependent data
    const currentServer = computed(() => {
        return servers.value.find(
            (s) => s.id.toString() === selectedServer.value,
        );
    });

    const availablePhpVersions = computed(() => {
        return currentServer.value?.phpVersions || [];
    });

    const availableUnixUsers = computed(() => {
        return currentServer.value?.unixUsers || [];
    });

    const availableDatabases = computed(() => {
        return currentServer.value?.databases || [];
    });

    const selectedDatabaseObject = computed(() => {
        if (!selectedDatabase.value) return null;
        return (
            availableDatabases.value.find(
                (db) => db.value.toString() === selectedDatabase.value,
            ) || null
        );
    });

    const availableDatabaseUsers = computed(() => {
        if (!selectedDatabaseObject.value) return null;
        const allowedUserIds = Object.values(
            selectedDatabaseObject.value.userIds,
        );
        return (currentServer.value?.databaseUsers || []).filter((dbu) =>
            allowedUserIds.includes(dbu.value as number),
        );
    });

    const defaultPhpVersion = computed(() => {
        const defaultVersion = availablePhpVersions.value.find(
            (v) => v.isDefault,
        );
        return (
            defaultVersion?.value || availablePhpVersions.value[0]?.value || ''
        );
    });

    const defaultUnixUser = computed(() => {
        return availableUnixUsers.value[0]?.value || '';
    });

    // Repository filtering
    const filteredRepositories = computed(() => {
        if (!repositorySearchQuery.value) {
            return repositories.value;
        }
        const query = repositorySearchQuery.value.toLowerCase();
        return repositories.value.filter(
            (repo) =>
                repo.full_name.toLowerCase().includes(query) ||
                repo.name.toLowerCase().includes(query),
        );
    });

    const selectedRepositoryDisplay = computed(() => {
        const repo = repositories.value.find(
            (r) => r.full_name === selectedRepository.value,
        );
        return repo?.full_name || '';
    });

    // WWW redirect display
    const selectedWwwRedirectLabel = computed(() => {
        const type = wwwRedirectTypes.value.find(
            (t) => t.value === selectedWwwRedirect.value,
        );
        if (!type) return '';

        if (type.value === 'from_www') return 'Will redirect from www.';
        if (type.value === 'to_www') return 'Will redirect to www.';
        return 'No redirects.';
    });

    // Watchers
    // Update selected values when server changes
    watch(
        selectedServer,
        () => {
            selectedPhpVersion.value = defaultPhpVersion.value;
            selectedUser.value = defaultUnixUser.value;
            selectedDatabase.value = undefined;
            selectedDatabaseUser.value = undefined;
        },
        { immediate: true },
    );

    // Reset database user when database changes
    watch(selectedDatabase, () => {
        selectedDatabaseUser.value = undefined;
    });

    // Load repositories when source control changes
    watch(selectedSourceControl, async (newValue) => {
        selectedRepository.value = '';
        selectedBranch.value = '';
        repositories.value = [];
        branches.value = [];
        repositorySearchQuery.value = '';

        if (!newValue) return;

        loadingRepositories.value = true;
        try {
            const response = await axios.get(
                fetchRepositories.url({ sourceControl: parseInt(newValue) }),
            );
            repositories.value = response.data;
        } catch (error) {
            console.error('Failed to load repositories:', error);
        } finally {
            loadingRepositories.value = false;
        }
    });

    // Load branches when repository changes
    watch(selectedRepository, async (newValue) => {
        selectedBranch.value = '';
        branches.value = [];

        if (!newValue || !selectedSourceControl.value) return;

        loadingBranches.value = true;
        try {
            const response = await axios.get(
                fetchBranches.url({
                    sourceControl: parseInt(selectedSourceControl.value),
                    repository: newValue,
                }),
            );
            branches.value = response.data;

            // Auto-select default branch if available
            const selectedRepo = repositories.value.find(
                (r) => r.full_name === newValue,
            );
            if (
                selectedRepo &&
                branches.value.includes(selectedRepo.default_branch)
            ) {
                selectedBranch.value = selectedRepo.default_branch;
            } else if (branches.value.length > 0) {
                selectedBranch.value = branches.value[0];
            }
        } catch (error) {
            console.error('Failed to load branches:', error);
        } finally {
            loadingBranches.value = false;
        }
    });

    // Methods
    function generatePassword(): void {
        const chars =
            'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        let password = '';
        for (let i = 0; i < 20; i++) {
            password += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        databasePassword.value = password;
    }

    return {
        // Server selection
        selectedServer,
        selectedPhpVersion,
        selectedUser,
        currentServer,
        availablePhpVersions,
        availableUnixUsers,
        defaultPhpVersion,
        defaultUnixUser,

        // Domain configuration
        selectedWwwRedirect,
        allowWildcard,
        domainValue,
        showDomainModal,
        selectedWwwRedirectLabel,

        // Site options
        installComposer,
        zeroDowntime,

        // Database
        createDatabase,
        selectedDatabase,
        selectedDatabaseUser,
        databaseName,
        databaseUser,
        databasePassword,
        availableDatabases,
        availableDatabaseUsers,
        selectedDatabaseObject,
        generatePassword,

        // Source control
        selectedSourceControl,
        selectedRepository,
        selectedBranch,
        repositories,
        branches,
        loadingRepositories,
        loadingBranches,
        repositorySearchQuery,
        repositoryComboboxOpen,
        filteredRepositories,
        selectedRepositoryDisplay,
    };
}
