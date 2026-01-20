<script setup lang="ts">
import { update } from '@/actions/Nip/SecurityMonitor/Http/Controllers/SecuritySettingsController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import type { SecuritySettingsData, Site } from '@/types';
import { useForm } from '@inertiajs/vue3';

interface Props {
    site: Site;
    settings: SecuritySettingsData;
}

const props = defineProps<Props>();

const form = useForm({
    git_monitor_enabled: props.settings.gitMonitorEnabled,
    security_scan_interval_minutes: String(props.settings.securityScanIntervalMinutes),
    security_scan_retention_days: String(props.settings.securityScanRetentionDays),
});

const intervalOptions = [
    { value: '15', label: '15 minutes' },
    { value: '30', label: '30 minutes' },
    { value: '60', label: '1 hour' },
    { value: '120', label: '2 hours' },
    { value: '360', label: '6 hours' },
    { value: '720', label: '12 hours' },
    { value: '1440', label: '24 hours' },
];

const retentionOptions = [
    { value: '1', label: '1 day' },
    { value: '3', label: '3 days' },
    { value: '7', label: '7 days' },
    { value: '14', label: '14 days' },
    { value: '30', label: '30 days' },
];

function handleSubmit() {
    form.transform((data) => ({
        ...data,
        security_scan_interval_minutes: Number(data.security_scan_interval_minutes),
        security_scan_retention_days: Number(data.security_scan_retention_days),
    })).patch(update.url(props.site), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Card class="bg-white dark:bg-card">
        <CardHeader>
            <CardTitle>Settings</CardTitle>
            <CardDescription>
                Configure security monitoring for this site
            </CardDescription>
        </CardHeader>

        <CardContent>
            <form @submit.prevent="handleSubmit" class="space-y-6">
                <!-- Git Monitor Toggle -->
                <div class="flex items-center justify-between space-x-2">
                    <div class="space-y-0.5">
                        <Label for="git-monitor">Git Monitor</Label>
                        <p class="text-sm text-muted-foreground">
                            Track uncommitted file changes via git status
                        </p>
                    </div>
                    <Switch
                        id="git-monitor"
                        v-model="form.git_monitor_enabled"
                    />
                </div>

                <!-- Scan Interval -->
                <div class="space-y-2">
                    <Label for="interval">Scan Frequency</Label>
                    <Select v-model="form.security_scan_interval_minutes">
                        <SelectTrigger id="interval">
                            <SelectValue placeholder="Select interval" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in intervalOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.security_scan_interval_minutes" />
                </div>

                <!-- Retention -->
                <div class="space-y-2">
                    <Label for="retention">History Retention</Label>
                    <Select v-model="form.security_scan_retention_days">
                        <SelectTrigger id="retention">
                            <SelectValue placeholder="Select retention" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in retentionOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.security_scan_retention_days" />
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end">
                    <Button
                        type="submit"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Saving...' : 'Save Settings' }}
                    </Button>
                </div>
            </form>
        </CardContent>
    </Card>
</template>
