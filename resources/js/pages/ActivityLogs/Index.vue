<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Activity, User as UserIcon, Clock, HardDrive, Info } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

interface ActivityLog {
    id: number;
    user_id: number | null;
    action: string;
    description: string;
    details: any;
    ip_address: string;
    user_agent: string;
    created_at: string;
    user: {
        name: string;
        email: string;
    } | null;
}

const props = defineProps<{
    logs: {
        data: ActivityLog[];
        links: any[];
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Activity Logs', href: '/activity-logs' },
];

function getActionLabel(action: string) {
    const parts = action.split('.');
    return parts.length > 1 ? parts[1].charAt(0).toUpperCase() + parts[1].slice(1) : action;
}

function getActionColor(action: string) {
    if (action.includes('delete') || action.includes('force-delete')) return 'text-destructive bg-destructive/10';
    if (action.includes('restore')) return 'text-blue-600 bg-blue-50';
    if (action.includes('create') || action.includes('store') || action.includes('upload')) return 'text-green-600 bg-green-50';
    if (action.includes('update')) return 'text-amber-600 bg-amber-50';
    return 'text-muted-foreground bg-muted';
}

function formatRelativeTime(dateString: string) {
    const date = new Date(dateString);
    return date.toLocaleString();
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="System Activity Logs" />

        <div class="flex h-full flex-1 flex-col gap-6 p-4 pt-0">
            <div class="flex items-center justify-between mt-4">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Activity Logs</h1>
                    <p class="text-sm text-muted-foreground mt-1">Track important system actions and changes.</p>
                </div>
            </div>

            <div class="rounded-xl border bg-card text-card-foreground shadow-sm overflow-hidden mb-8">
                <!-- Desktop Table View -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-muted/50 transition-colors">
                                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">User</th>
                                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Action</th>
                                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Description</th>
                                <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">IP Address</th>
                                <th class="h-12 px-4 text-right align-middle font-medium text-muted-foreground">Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="log in logs.data" :key="log.id" class="border-b transition-colors hover:bg-muted/30 last:border-0">
                                <td class="p-4 align-middle">
                                    <div class="flex items-center gap-2">
                                        <div class="h-8 w-8 rounded-full bg-secondary flex items-center justify-center">
                                            <UserIcon class="h-4 w-4 text-muted-foreground" />
                                        </div>
                                        <span class="font-medium">
                                            {{ log.user ? log.user.name : 'System' }}
                                        </span>
                                    </div>
                                </td>
                                <td class="p-4 align-middle">
                                    <span :class="['px-2 py-0.5 rounded-full text-xs font-semibold', getActionColor(log.action)]">
                                        {{ getActionLabel(log.action) }}
                                    </span>
                                </td>
                                <td class="p-4 align-middle text-muted-foreground">
                                    {{ log.description }}
                                </td>
                                <td class="p-4 align-middle font-mono text-xs text-muted-foreground">
                                    {{ log.ip_address }}
                                </td>
                                <td class="p-4 align-middle text-right text-muted-foreground whitespace-nowrap">
                                    {{ formatRelativeTime(log.created_at) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card View -->
                <div class="md:hidden divide-y">
                    <div v-for="log in logs.data" :key="log.id" class="p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="h-6 w-6 rounded-full bg-secondary flex items-center justify-center">
                                    <UserIcon class="h-3 w-3 text-muted-foreground" />
                                </div>
                                <span class="text-xs font-bold">{{ log.user ? log.user.name : 'System' }}</span>
                            </div>
                            <span :class="['px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider', getActionColor(log.action)]">
                                {{ getActionLabel(log.action) }}
                            </span>
                        </div>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400 leading-tight">
                            {{ log.description }}
                        </p>
                        <div class="flex items-center justify-between pt-1">
                            <div class="flex items-center gap-1.5 text-[10px] text-muted-foreground">
                                <HardDrive class="h-3 w-3" />
                                <span class="font-mono">{{ log.ip_address }}</span>
                            </div>
                            <div class="flex items-center gap-1.5 text-[10px] text-muted-foreground">
                                <Clock class="h-3 w-3" />
                                <span>{{ formatRelativeTime(log.created_at) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="logs.data.length === 0" class="p-12 text-center text-muted-foreground bg-card">
                    <div class="relative inline-flex mb-4">
                        <Activity class="h-12 w-12 opacity-10" />
                        <div class="absolute -top-1 -right-1 h-4 w-4 rounded-full bg-zinc-200 dark:bg-zinc-800 flex items-center justify-center ring-2 ring-card">
                            <Info class="h-2.5 w-2.5" />
                        </div>
                    </div>
                    <p class="text-sm font-medium">No activity logs found.</p>
                    <p class="text-xs text-muted-foreground mt-1">Actions performed in the system will appear here.</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
