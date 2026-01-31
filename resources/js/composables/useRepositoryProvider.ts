import BitbucketIcon from '@/components/icons/BitbucketIcon.vue';
import GithubIcon from '@/components/icons/GithubIcon.vue';
import GitlabIcon from '@/components/icons/GitlabIcon.vue';
import { computed, type Component, type ComputedRef } from 'vue';

export interface RepositoryProviderConfig {
    icon: Component;
    iconClass: string;
    label: string;
}

const providerConfigs: Record<string, RepositoryProviderConfig> = {
    github: {
        icon: GithubIcon,
        iconClass: 'text-foreground',
        label: 'GitHub',
    },
    gitlab: {
        icon: GitlabIcon,
        iconClass: 'text-orange-500',
        label: 'GitLab',
    },
    bitbucket: {
        icon: BitbucketIcon,
        iconClass: 'text-blue-500',
        label: 'Bitbucket',
    },
};

export function useRepositoryProvider(
    provider: () => string | null | undefined,
): ComputedRef<RepositoryProviderConfig | null> {
    return computed(() => {
        const currentProvider = provider();
        if (!currentProvider) {
            return null;
        }
        return providerConfigs[currentProvider.toLowerCase()] ?? null;
    });
}

export function getRepositoryProviderConfig(
    provider: string | null | undefined,
): RepositoryProviderConfig | null {
    if (!provider) {
        return null;
    }
    return providerConfigs[provider.toLowerCase()] ?? null;
}
