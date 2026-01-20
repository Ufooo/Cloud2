<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { SecuritySummary } from '@/types/security-monitor';
import { AlertTriangle, CheckCircle2, Globe, XCircle } from 'lucide-vue-next';

interface Props {
    summary: SecuritySummary;
}

defineProps<Props>();
</script>

<template>
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <!-- Total Sites -->
        <Card class="bg-white dark:bg-card">
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium">Total Sites</CardTitle>
                <Globe class="size-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold">{{ summary.totalSites }}</div>
                <p class="text-xs text-muted-foreground">Monitored sites</p>
            </CardContent>
        </Card>

        <!-- Git Changes -->
        <Card class="bg-white dark:bg-card">
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium">Git Changes</CardTitle>
                <AlertTriangle class="size-4 text-yellow-600" />
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold text-yellow-600">{{ summary.gitIssuesSites }}</div>
                <p class="text-xs text-muted-foreground">Sites with changes</p>
            </CardContent>
        </Card>

        <!-- Clean -->
        <Card class="bg-white dark:bg-card">
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium">Clean</CardTitle>
                <CheckCircle2 class="size-4 text-green-600" />
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold text-green-600">{{ summary.cleanSites }}</div>
                <p class="text-xs text-muted-foreground">No issues detected</p>
            </CardContent>
        </Card>

        <!-- Errors -->
        <Card class="bg-white dark:bg-card">
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium">Errors</CardTitle>
                <XCircle class="size-4 text-red-600" />
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold text-red-600">{{ summary.errorSites }}</div>
                <p class="text-xs text-muted-foreground">Scan errors</p>
            </CardContent>
        </Card>
    </div>
</template>
