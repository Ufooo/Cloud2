<script setup lang="ts">
import {
    regenerateToken,
    updateSettings,
} from '@/actions/Nip/Deployment/Http/Controllers/SiteDeploymentController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import SiteLayout from '@/layouts/SiteLayout.vue';
import type { Site } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Check, ClipboardCopy, Key, RefreshCw, Rocket } from 'lucide-vue-next';
import { ref } from 'vue';

interface Props {
    site: Site;
}

const props = defineProps<Props>();

const copied = ref(false);

const form = useForm({
    deploy_script: props.site.deployScript || '',
    push_to_deploy: props.site.pushToDeploy || false,
    auto_source: props.site.autoSource || false,
    deployment_retention: props.site.deploymentRetention || 5,
    healthcheck_endpoint: props.site.healthcheckEndpoint || '',
});

function submit() {
    form.patch(updateSettings.url(props.site), {
        preserveScroll: true,
    });
}

function handleRegenerateToken() {
    router.post(regenerateToken.url(props.site), {}, {
        preserveScroll: true,
    });
}

function copyDeployHookUrl() {
    if (props.site.deployHookUrl) {
        navigator.clipboard.writeText(props.site.deployHookUrl);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    }
}
</script>

<template>
    <Head :title="`Deployment Settings - ${site.domain}`" />

    <SiteLayout :site="site">
        <div class="space-y-6">
            <!-- Deployment Settings -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Rocket class="size-5" />
                        Deployments
                    </CardTitle>
                    <CardDescription>
                        Manage build and deployment settings.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <form class="space-y-6" @submit.prevent="submit">
                        <!-- Push to Deploy -->
                        <div
                            v-if="site.repository"
                            class="flex items-center justify-between"
                        >
                            <div class="space-y-0.5">
                                <Label>Push to deploy</Label>
                                <p class="text-sm text-muted-foreground">
                                    Automatically trigger a new deployment when
                                    changes are pushed to the Git branch.
                                </p>
                            </div>
                            <Switch
                                :checked="form.push_to_deploy"
                                @update:checked="form.push_to_deploy = $event"
                            />
                        </div>

                        <!-- Deploy Script -->
                        <div class="space-y-2">
                            <Label for="deploy_script">Deploy script</Label>
                            <Textarea
                                id="deploy_script"
                                v-model="form.deploy_script"
                                class="min-h-[300px] font-mono text-sm"
                                placeholder="cd $NIP_SITE_PATH&#10;git pull origin $NIP_SITE_BRANCH&#10;composer install --no-dev&#10;php artisan migrate --force"
                            />
                            <p class="text-sm text-muted-foreground">
                                The commands that will be run to deploy your
                                application.
                            </p>
                            <InputError :message="form.errors.deploy_script" />
                        </div>

                        <!-- Auto Source -->
                        <div class="flex items-center space-x-2">
                            <Checkbox
                                id="auto_source"
                                :checked="form.auto_source"
                                @update:checked="form.auto_source = $event"
                            />
                            <Label for="auto_source" class="font-normal">
                                Make
                                <code
                                    class="rounded bg-muted px-1 py-0.5 text-sm"
                                    >.env</code
                                >
                                variables available to deployment script
                            </Label>
                        </div>

                        <!-- Deployment Retention (only for zero-downtime) -->
                        <div v-if="site.zeroDowntime" class="space-y-2">
                            <Label for="deployment_retention"
                                >Deployment retention</Label
                            >
                            <Input
                                id="deployment_retention"
                                v-model.number="form.deployment_retention"
                                type="number"
                                min="1"
                                max="100"
                                class="max-w-[120px]"
                            />
                            <p class="text-sm text-muted-foreground">
                                Number of previous deployments to retain (1-100).
                            </p>
                            <InputError
                                :message="form.errors.deployment_retention"
                            />
                        </div>

                        <!-- Deploy Hook URL -->
                        <div class="space-y-2">
                            <Label>Deploy hook</Label>
                            <p class="text-sm text-muted-foreground">
                                Use this URL to trigger deployments from CI/CD
                                services.
                            </p>
                            <div class="flex gap-2">
                                <Input
                                    :model-value="site.deployHookUrl || ''"
                                    readonly
                                    class="font-mono text-sm"
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    @click="copyDeployHookUrl"
                                >
                                    <Check v-if="copied" class="size-4" />
                                    <ClipboardCopy v-else class="size-4" />
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    @click="handleRegenerateToken"
                                >
                                    <RefreshCw class="size-4" />
                                </Button>
                            </div>
                        </div>

                        <!-- Health Check -->
                        <div class="space-y-2">
                            <Label for="healthcheck_endpoint"
                                >Health check URL</Label
                            >
                            <Input
                                id="healthcheck_endpoint"
                                v-model="form.healthcheck_endpoint"
                                type="url"
                                placeholder="https://example.com/up"
                            />
                            <p class="text-sm text-muted-foreground">
                                After deployment, this URL will be pinged to
                                ensure your site is available. Leave empty to
                                disable.
                            </p>
                            <InputError
                                :message="form.errors.healthcheck_endpoint"
                            />
                        </div>

                        <!-- Submit Button -->
                        <div class="flex justify-end">
                            <Button type="submit" :disabled="form.processing">
                                {{
                                    form.processing ? 'Saving...' : 'Save Changes'
                                }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <!-- Keys -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Key class="size-5" />
                        Keys
                    </CardTitle>
                    <CardDescription>
                        Your site's SSH keys for repository access.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <div class="space-y-2">
                        <Label>Site public key</Label>
                        <p class="text-sm text-muted-foreground">
                            Add this key to your source control provider to allow
                            deployments.
                        </p>
                        <Textarea
                            :model-value="site.deployKey || 'No deploy key configured'"
                            readonly
                            class="min-h-[100px] font-mono text-xs"
                        />
                    </div>
                </CardContent>
            </Card>
        </div>
    </SiteLayout>
</template>
