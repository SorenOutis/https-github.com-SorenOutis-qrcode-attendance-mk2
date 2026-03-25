<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Activity, User as UserIcon, Clock, HardDrive, Info } from 'lucide-vue-next';
import { onMounted } from 'vue';
import gsap from 'gsap';
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
    if (action.includes('delete') || action.includes('force-delete')) return 'text-zinc-600 bg-zinc-200 border border-zinc-300 dark:text-zinc-300 dark:bg-zinc-800 dark:border-zinc-700';
    if (action.includes('restore')) return 'text-zinc-900 bg-white border border-zinc-200 dark:text-white dark:bg-zinc-900 dark:border-zinc-700';
    if (action.includes('create') || action.includes('store') || action.includes('upload')) return 'text-zinc-100 bg-zinc-900 dark:text-zinc-900 dark:bg-zinc-100';
    if (action.includes('update')) return 'text-zinc-700 bg-zinc-100 border border-zinc-200 dark:text-zinc-300 dark:bg-zinc-800 dark:border-zinc-700';
    return 'text-muted-foreground bg-muted border border-border';
}

function formatRelativeTime(dateString: string) {
    const date = new Date(dateString);
    return date.toLocaleString();
}

onMounted(() => {
    gsap.from('.stagger-animate', {
        y: 20,
        opacity: 0,
        duration: 0.6,
        stagger: 0.1,
        ease: 'power2.out',
    });
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="System Activity Logs" />

        <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8 pt-0 w-full overflow-x-hidden animate-in fade-in slide-in-from-bottom-4 duration-700">
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-6 pb-4 border-b border-zinc-100 dark:border-zinc-900 mt-4 stagger-animate">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="h-1 w-1 rounded-full bg-zinc-400 animate-pulse"></span>
                        <span class="text-[10px] font-black uppercase tracking-widest text-zinc-400">System</span>
                    </div>
                    <h1 class="text-3xl sm:text-4xl font-serif font-black tracking-tighter text-foreground leading-none">Activity Logs</h1>
                    <p class="text-xs text-muted-foreground mt-2 uppercase tracking-widest font-bold">Track important actions and changes</p>
                </div>
            </div>

            <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-black shadow-sm overflow-hidden mb-8 stagger-animate relative">
                <Activity class="absolute right-[-5%] top-[-10%] h-64 w-64 text-zinc-900/[0.02] dark:text-white/[0.02] pointer-events-none" />
                
                <!-- Desktop Table View -->
                <div class="hidden md:block overflow-x-auto relative z-10">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-zinc-100 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-900/50 transition-colors">
                                <th class="h-12 px-6 text-left align-middle text-[10px] font-black uppercase tracking-widest text-zinc-500">User</th>
                                <th class="h-12 px-6 text-left align-middle text-[10px] font-black uppercase tracking-widest text-zinc-500">Action</th>
                                <th class="h-12 px-6 text-left align-middle text-[10px] font-black uppercase tracking-widest text-zinc-500">Description</th>
                                <th class="h-12 px-6 text-left align-middle text-[10px] font-black uppercase tracking-widest text-zinc-500">IP Address</th>
                                <th class="h-12 px-6 text-right align-middle text-[10px] font-black uppercase tracking-widest text-zinc-500">Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="log in logs.data" :key="log.id" class="border-b border-zinc-100 dark:border-zinc-800/50 transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-900/30 last:border-0 group">
                                <td class="p-6 align-middle">
                                    <div class="flex items-center gap-3">
                                        <div class="h-10 w-10 min-w-[40px] rounded-xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center border border-zinc-200 dark:border-zinc-700 shadow-inner">
                                            <UserIcon class="h-5 w-5 text-zinc-500" />
                                        </div>
                                        <span class="font-bold text-sm">
                                            {{ log.user ? log.user.name : 'System' }}
                                        </span>
                                    </div>
                                </td>
                                <td class="p-6 align-middle">
                                    <span :class="['px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest', getActionColor(log.action)]">
                                        {{ getActionLabel(log.action) }}
                                    </span>
                                </td>
                                <td class="p-6 align-middle text-zinc-500 dark:text-zinc-400 font-medium text-xs leading-relaxed max-w-[300px] truncate group-hover:text-zinc-900 dark:group-hover:text-zinc-200 transition-colors">
                                    {{ log.description }}
                                </td>
                                <td class="p-6 align-middle font-mono font-bold tracking-tight text-xs text-zinc-400 dark:text-zinc-500">
                                    {{ log.ip_address }}
                                </td>
                                <td class="p-6 align-middle text-right font-mono font-bold text-xs text-zinc-400 dark:text-zinc-500 whitespace-nowrap">
                                    {{ formatRelativeTime(log.created_at) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card View -->
                <div class="md:hidden divide-y divide-zinc-100 dark:divide-zinc-800 relative z-10">
                    <div v-for="log in logs.data" :key="log.id" class="p-5 space-y-4 hover:bg-zinc-50 dark:hover:bg-zinc-900/30 transition-colors cursor-default">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 min-w-[40px] rounded-xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center border border-zinc-200 dark:border-zinc-700 shadow-inner">
                                    <UserIcon class="h-5 w-5 text-zinc-500" />
                                </div>
                                <span class="font-bold text-sm">{{ log.user ? log.user.name : 'System' }}</span>
                            </div>
                            <span :class="['px-2.5 py-1 rounded-full text-[9px] font-black uppercase tracking-widest shadow-sm', getActionColor(log.action)]">
                                {{ getActionLabel(log.action) }}
                            </span>
                        </div>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed font-medium">
                            {{ log.description }}
                        </p>
                        <div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-zinc-100 dark:border-zinc-800/50">
                            <div class="flex items-center gap-1.5 text-[10px] font-black uppercase tracking-widest text-zinc-400">
                                <HardDrive class="h-3.5 w-3.5" />
                                <span class="font-mono mt-0.5">{{ log.ip_address }}</span>
                            </div>
                            <div class="flex items-center gap-1.5 text-[10px] font-black uppercase tracking-widest text-zinc-400">
                                <Clock class="h-3.5 w-3.5" />
                                <span class="font-mono mt-0.5">{{ formatRelativeTime(log.created_at) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="logs.data.length === 0" class="py-16 text-center text-zinc-500 bg-white dark:bg-black relative z-10">
                    <div class="relative inline-flex mb-6">
                        <div class="h-20 w-20 rounded-full bg-zinc-100 dark:bg-zinc-900/50 flex items-center justify-center">
                            <Activity class="h-10 w-10 text-zinc-300 dark:text-zinc-600" />
                        </div>
                        <div class="absolute -top-1 -right-1 h-6 w-6 rounded-full bg-zinc-200 dark:bg-zinc-800 flex items-center justify-center ring-4 ring-white dark:ring-black">
                            <Info class="h-3 w-3 text-zinc-500" />
                        </div>
                    </div>
                    <h3 class="font-black text-xl tracking-tighter text-zinc-900 dark:text-zinc-100 mb-2">No logs found</h3>
                    <p class="text-xs font-medium max-w-sm mx-auto">Actions performed in the system will automatically be recorded here.</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
