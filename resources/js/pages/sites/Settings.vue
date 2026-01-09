<script setup lang="ts">
import {
    destroy,
    update,
} from '@/actions/Nip/Site/Http/Controllers/SiteController';
import SiteTypeIcon from '@/components/icons/SiteTypeIcon.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useConfirmation } from '@/composables/useConfirmation';
import { IDENTITY_COLOR_MAP } from '@/composables/useIdentityColor';
import SiteLayout from '@/layouts/SiteLayout.vue';
import { IdentityColor, type Site } from '@/types';
import { isPhpBasedSiteType } from '@/utils/constants';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Folder, GitBranch, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';

interface SelectOption {
    value: string;
    label: string;
}

interface Props {
    site: Site;
    siteTypes: SelectOption[];
    phpVersions: SelectOption[];
    colors: SelectOption[];
}

const props = defineProps<Props>();

const { confirmInput } = useConfirmation();

// General settings form
const generalForm = useForm({
    type: props.site.type || '',
    php_version: props.site.phpVersionLabel || '',
    avatar_color: props.site.avatarColor as IdentityColor | null,
});

// Directories form
const directoriesForm = useForm({
    root_directory: props.site.rootDirectory || '/',
    web_directory: props.site.webDirectory || '/public',
});

// Base path for the site (e.g., /home/netipar/aaaaa.hu)
const basePath = computed(
    () => `/home/${props.site.user}/${props.site.domain}`,
);

// Full path including root directory (for web directory prefix)
const fullPathWithRoot = computed(() => {
    const root = directoriesForm.root_directory.replace(/\/+$/, '');
    return `${basePath.value}${root === '/' ? '' : root}`;
});

// Git form
const gitForm = useForm({
    repository: props.site.repository || '',
    branch: props.site.branch || '',
});

function submitGeneralForm() {
    generalForm.patch(update.url(props.site), {
        preserveScroll: true,
    });
}

function submitDirectoriesForm() {
    directoriesForm.patch(update.url(props.site), {
        preserveScroll: true,
    });
}

function submitGitForm() {
    gitForm.patch(update.url(props.site), {
        preserveScroll: true,
    });
}

async function handleDelete() {
    const confirmed = await confirmInput({
        title: 'Delete site',
        description: `Type "${props.site.domain}" to confirm permanently deleting this site. This action cannot be undone.`,
        value: props.site.domain,
    });

    if (confirmed) {
        router.delete(destroy.url(props.site));
    }
}

// Check if site type is PHP-based to show PHP version selector
const isPhpBased = computed(() => isPhpBasedSiteType(props.site.type));
</script>

<template>
    <Head :title="`Settings - ${site.domain}`" />

    <SiteLayout :site="site">
        <div class="space-y-6">
            <!-- General Settings -->
            <Card>
                <CardHeader>
                    <CardTitle>Settings</CardTitle>
                    <CardDescription>
                        Configure your site's basic settings.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <form @submit.prevent="submitGeneralForm" class="space-y-6">
                        <!-- Framework -->
                        <div class="space-y-2">
                            <Label for="type">Framework</Label>
                            <Select v-model="generalForm.type">
                                <SelectTrigger id="type">
                                    <SelectValue
                                        placeholder="Select framework"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="siteType in siteTypes"
                                        :key="siteType.value"
                                        :value="siteType.value"
                                    >
                                        <div class="flex items-center gap-2">
                                            <SiteTypeIcon
                                                :type="siteType.value"
                                                class="size-4"
                                            />
                                            {{ siteType.label }}
                                        </div>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p class="text-sm text-muted-foreground">
                                The framework used by the installed application.
                                Changing the framework
                                <span class="font-medium">does not</span> modify
                                the Nginx configuration.
                            </p>
                            <InputError :message="generalForm.errors.type" />
                        </div>

                        <!-- PHP Version -->
                        <div v-if="isPhpBased" class="space-y-2">
                            <Label for="php_version">PHP version</Label>
                            <Select v-model="generalForm.php_version">
                                <SelectTrigger id="php_version">
                                    <SelectValue
                                        placeholder="Select PHP version"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="version in phpVersions"
                                        :key="version.value"
                                        :value="version.value"
                                    >
                                        {{ version.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p class="text-sm text-muted-foreground">
                                You may need to update your deployment script,
                                schedulers, and background processes when
                                changing the site's PHP version.
                            </p>
                            <InputError
                                :message="generalForm.errors.php_version"
                            />
                        </div>

                        <!-- Color -->
                        <div class="space-y-2">
                            <Label>Color</Label>
                            <p class="text-sm text-muted-foreground">
                                Select a color to identify your site.
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="color in colors"
                                    :key="color.value"
                                    type="button"
                                    class="size-8 rounded-md ring-offset-background transition-all focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                                    :class="[
                                        IDENTITY_COLOR_MAP[
                                            color.value as IdentityColor
                                        ] || 'bg-gray-500',
                                        generalForm.avatar_color === color.value
                                            ? 'ring-2 ring-ring ring-offset-2'
                                            : 'hover:opacity-80',
                                    ]"
                                    :title="color.label"
                                    @click="
                                        generalForm.avatar_color =
                                            color.value as IdentityColor
                                    "
                                />
                            </div>
                            <InputError
                                :message="generalForm.errors.avatar_color"
                            />
                        </div>

                        <!-- Submit Button -->
                        <div class="flex justify-end">
                            <Button
                                type="submit"
                                :disabled="generalForm.processing"
                            >
                                {{
                                    generalForm.processing
                                        ? 'Saving...'
                                        : 'Save Changes'
                                }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <!-- Directories Settings -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Folder class="size-5" />
                        Directories
                    </CardTitle>
                    <CardDescription>
                        Configure your site's directory settings. If you have
                        queue workers, background processes or scheduled jobs
                        configured for this site, you will need to re-create
                        them after updating the directories.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <form
                        @submit.prevent="submitDirectoriesForm"
                        class="space-y-6"
                    >
                        <!-- Root Directory -->
                        <div class="space-y-2">
                            <Label for="root_directory">Root directory</Label>
                            <div class="flex">
                                <div
                                    class="flex items-center rounded-l-md border border-r-0 bg-muted px-3 text-sm text-muted-foreground"
                                >
                                    {{ basePath }}
                                </div>
                                <Input
                                    id="root_directory"
                                    v-model="directoriesForm.root_directory"
                                    class="rounded-l-none"
                                    placeholder="/"
                                />
                            </div>
                            <p class="text-sm text-muted-foreground">
                                The root directory for your site. This is where
                                your application code lives.
                            </p>
                            <InputError
                                :message="directoriesForm.errors.root_directory"
                            />
                        </div>

                        <!-- Web Directory -->
                        <div class="space-y-2">
                            <Label for="web_directory">Web directory</Label>
                            <div class="flex">
                                <div
                                    class="flex items-center rounded-l-md border border-r-0 bg-muted px-3 text-sm text-muted-foreground transition-all"
                                >
                                    {{ fullPathWithRoot }}
                                </div>
                                <Input
                                    id="web_directory"
                                    v-model="directoriesForm.web_directory"
                                    class="rounded-l-none"
                                    placeholder="/public"
                                />
                            </div>
                            <p class="text-sm text-muted-foreground">
                                The publicly accessible directory that Nginx
                                will serve the site from.
                            </p>
                            <InputError
                                :message="directoriesForm.errors.web_directory"
                            />
                        </div>

                        <!-- Submit Button -->
                        <div class="flex justify-end">
                            <Button
                                type="submit"
                                :disabled="directoriesForm.processing"
                            >
                                {{
                                    directoriesForm.processing
                                        ? 'Saving...'
                                        : 'Save Changes'
                                }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <!-- Git Settings -->
            <Card v-if="site.repository">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <GitBranch class="size-5" />
                        Git
                    </CardTitle>
                    <CardDescription>
                        Configure your site's Git settings.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <form @submit.prevent="submitGitForm" class="space-y-6">
                        <!-- Repository -->
                        <div class="space-y-2">
                            <Label for="repository">Repository</Label>
                            <Input
                                id="repository"
                                v-model="gitForm.repository"
                                placeholder="git@github.com:user/repo.git"
                            />
                            <p class="text-sm text-muted-foreground">
                                Configure the Git repository that should be
                                deployed.
                            </p>
                            <InputError :message="gitForm.errors.repository" />
                        </div>

                        <!-- Branch -->
                        <div class="space-y-2">
                            <Label for="branch">Branch</Label>
                            <Input
                                id="branch"
                                v-model="gitForm.branch"
                                placeholder="main"
                            />
                            <p class="text-sm text-muted-foreground">
                                Configure the Git branch that should be
                                deployed.
                            </p>
                            <InputError :message="gitForm.errors.branch" />
                        </div>

                        <!-- Submit Button -->
                        <div class="flex justify-end">
                            <Button
                                type="submit"
                                :disabled="gitForm.processing"
                            >
                                {{
                                    gitForm.processing
                                        ? 'Saving...'
                                        : 'Save Changes'
                                }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <!-- Danger Zone -->
            <Card class="border-destructive/50">
                <CardHeader>
                    <CardTitle class="text-destructive">Danger</CardTitle>
                    <CardDescription>
                        Destructive actions that cannot be undone.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <div class="flex items-center justify-between">
                        <div class="space-y-1">
                            <p class="font-medium">Delete site</p>
                            <p class="text-sm text-muted-foreground">
                                Deleting a site will remove all installed
                                application code and untracked files from within
                                the
                                <code class="rounded bg-muted px-1 py-0.5">{{
                                    site.fullPath
                                }}</code>
                                directory.
                            </p>
                        </div>
                        <Button
                            v-if="site.can?.delete"
                            variant="destructive"
                            @click="handleDelete"
                        >
                            <Trash2 class="mr-2 size-4" />
                            Delete site
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </SiteLayout>
</template>
