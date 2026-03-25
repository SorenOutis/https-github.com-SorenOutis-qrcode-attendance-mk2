<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Database, RefreshCw, Trash2, Plus, Download, Upload, Loader2, AlertTriangle, CloudRain, CheckCircle2 } from 'lucide-vue-next';
import { onMounted } from 'vue';
import gsap from 'gsap';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useToast } from '@/composables/useToast';
import type { BreadcrumbItem } from '@/types';

type Backup = {
    name: string;
    size: string;
    size_bytes: number;
    date: string;
    date_formatted: string;
};

const props = defineProps<{
    backups: Backup[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Backups',
        href: '/backups',
    },
];

const toast = useToast();
const processing = ref(false);
const processingMessage = ref('');
const fileInput = ref<HTMLInputElement | null>(null);

const confirmModalOpen = ref(false);
const confirmTitle = ref('');
const confirmDescription = ref('');
const confirmAction = ref<(() => void) | null>(null);
const confirmIsDestructive = ref(false);

function showConfirm(title: string, description: string, action: () => void, isDestructive = false) {
    confirmTitle.value = title;
    confirmDescription.value = description;
    confirmAction.value = action;
    confirmIsDestructive.value = isDestructive;
    confirmModalOpen.value = true;
}

function handleConfirm() {
    if (confirmAction.value) {
        confirmAction.value();
    }
    confirmModalOpen.value = false;
    confirmAction.value = null;
}

function createBackup() {
    processing.value = true;
    processingMessage.value = 'Creating system backup...';
    router.post('/backups', {}, {
        preserveScroll: true,
        onSuccess: () => toast.success('Backup created successfully.'),
        onError: () => toast.error('Failed to create backup.'),
        onFinish: () => { 
            processing.value = false;
            processingMessage.value = '';
        }
    });
}

function restoreBackup(file: string) {
    showConfirm(
        'Restore Database Backup?',
        'PERMANENT ACTION: Are you sure you want to restore this backup? This will overwrite your current database. All data added since this backup was created will be PERMANENTLY LOST.',
        () => {
            processing.value = true;
            processingMessage.value = 'Restoring database... Please wait.';
            router.post(`/backups/${file}/restore`, {}, {
                preserveScroll: true,
                onSuccess: () => toast.success('Database restored successfully.'),
                onError: () => toast.error('Failed to restore database.'),
                onFinish: () => { 
                    processing.value = false;
                    processingMessage.value = '';
                }
            });
        },
        true
    );
}

function deleteBackup(file: string) {
    showConfirm(
        'Delete Backup File?',
        'Are you sure you want to delete this backup file? This action is permanent and cannot be undone.',
        () => {
            processing.value = true;
            processingMessage.value = 'Deleting backup file...';
            router.delete(`/backups/${file}`, {
                preserveScroll: true,
                onSuccess: () => toast.success('Backup deleted successfully.'),
                onError: () => toast.error('Failed to delete backup.'),
                onFinish: () => { 
                    processing.value = false;
                    processingMessage.value = '';
                }
            });
        },
        true
    );
}

function triggerUpload() {
    fileInput.value?.click();
}

function handleUpload(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;

    processing.value = true;
    processingMessage.value = 'Uploading backup file...';
    router.post('/backups/upload', { backup: file }, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => toast.success('Backup uploaded successfully.'),
        onError: (errors) => {
            toast.error(errors.backup || 'Failed to upload backup.');
        },
        onFinish: () => {
            processing.value = false;
            processingMessage.value = '';
            if (fileInput.value) fileInput.value.value = '';
        }
    });
}

function downloadBackup(file: string) {
    window.location.href = `/backups/${file}/download`;
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
        <Head title="System Backups" />

        <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8 pt-0 w-full overflow-x-hidden animate-in fade-in slide-in-from-bottom-4 duration-700">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-6 pb-4 border-b border-zinc-100 dark:border-zinc-900 mt-4 stagger-animate">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="h-1 w-1 rounded-full bg-zinc-400 animate-pulse"></span>
                        <span class="text-[10px] font-black uppercase tracking-widest text-zinc-400">Security</span>
                    </div>
                    <h1 class="text-3xl sm:text-4xl font-serif font-black tracking-tighter text-foreground leading-none">System Backups</h1>
                    <p class="text-xs text-muted-foreground mt-2 uppercase tracking-widest font-bold">Manage, create, and restore database runs</p>
                </div>
                <div class="flex flex-wrap items-center gap-2 bg-zinc-50 dark:bg-zinc-900/50 p-1 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-sm">
                    <input type="file" ref="fileInput" class="hidden" accept=".sqlite" @change="handleUpload" />
                    <Button variant="outline" @click="triggerUpload" :disabled="processing" class="flex-1 sm:flex-none h-10 border-0 bg-transparent hover:bg-zinc-200 dark:hover:bg-zinc-800 text-xs font-bold uppercase tracking-widest">
                        <Upload class="mr-2 h-4 w-4" />
                        Upload
                    </Button>
                    <Button @click="createBackup" :disabled="processing" class="flex-1 sm:flex-none h-10 bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 hover:bg-zinc-800 dark:hover:bg-zinc-200 text-[10px] font-black uppercase tracking-widest rounded-lg px-6 shadow-sm">
                        <Plus class="mr-2 h-4 w-4" />
                        Create Backup
                    </Button>
                </div>
            </div>

            <!-- Content -->
            <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-black shadow-sm overflow-hidden mb-8 stagger-animate relative">
                <Database class="absolute right-[-5%] top-[-10%] h-64 w-64 text-zinc-900/[0.02] dark:text-white/[0.02] pointer-events-none" />
                
                <div class="relative z-10">
                    <div v-if="backups.length === 0" class="py-16 text-center text-zinc-500 bg-white dark:bg-black">
                        <div class="relative inline-flex mb-6">
                            <div class="h-20 w-20 rounded-full bg-zinc-100 dark:bg-zinc-900/50 flex items-center justify-center">
                                <CloudRain class="h-10 w-10 text-zinc-300 dark:text-zinc-600" />
                            </div>
                            <div class="absolute -top-1 -right-1 h-6 w-6 rounded-full bg-zinc-200 dark:bg-zinc-800 flex items-center justify-center ring-4 ring-white dark:ring-black">
                                <AlertTriangle class="h-3 w-3 text-zinc-500" />
                            </div>
                        </div>
                        <h3 class="font-black text-xl tracking-tighter text-zinc-900 dark:text-zinc-100 mb-2">No backups found</h3>
                        <p class="text-xs font-medium max-w-sm mx-auto">You haven't created any database backups yet. Create one to keep your data safe.</p>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <!-- Desktop Table -->
                        <table class="w-full text-sm hidden md:table">
                            <thead>
                                <tr class="border-b border-zinc-100 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-900/50 transition-colors">
                                    <th class="h-12 px-6 text-left align-middle text-[10px] font-black uppercase tracking-widest text-zinc-500 w-1/3">Backup Name</th>
                                    <th class="h-12 px-6 text-left align-middle text-[10px] font-black uppercase tracking-widest text-zinc-500">Date</th>
                                    <th class="h-12 px-6 text-left align-middle text-[10px] font-black uppercase tracking-widest text-zinc-500">Size</th>
                                    <th class="h-12 px-6 text-right align-middle text-[10px] font-black uppercase tracking-widest text-zinc-500 w-[200px]">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="backup in backups" :key="backup.name" class="border-b border-zinc-100 dark:border-zinc-800/50 transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-900/30">
                                    <td class="p-6 align-middle">
                                        <div class="flex items-center gap-3">
                                            <div class="h-10 w-10 min-w-[40px] rounded-xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center border border-zinc-200 dark:border-zinc-700 shadow-inner">
                                                <Database class="h-5 w-5 text-zinc-500" />
                                            </div>
                                            <span class="font-bold font-mono text-sm tracking-tight">
                                                {{ backup.name }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="p-6 align-middle font-mono font-bold text-xs text-zinc-500">{{ backup.date_formatted }}</td>
                                    <td class="p-6 align-middle">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-[10px] font-black uppercase tracking-widest">
                                            {{ backup.size }}
                                        </span>
                                    </td>
                                    <td class="p-6 align-middle text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <Button 
                                                variant="outline" 
                                                size="sm" 
                                                @click="downloadBackup(backup.name)"
                                                :disabled="processing"
                                                title="Download Backup"
                                                class="h-8 w-8 p-0 rounded-lg bg-white dark:bg-black border-zinc-200 dark:border-zinc-800 hover:bg-zinc-100 dark:hover:bg-zinc-900"
                                            >
                                                <Download class="h-4 w-4" />
                                            </Button>
                                            <Button 
                                                variant="outline" 
                                                size="sm" 
                                                @click="restoreBackup(backup.name)"
                                                :disabled="processing"
                                                title="Restore Backup"
                                                class="h-8 w-8 p-0 rounded-lg bg-white dark:bg-black border-zinc-200 dark:border-zinc-800 hover:bg-zinc-100 dark:hover:bg-zinc-900 text-amber-600 border-amber-500/20 hover:bg-amber-50 dark:hover:bg-amber-500/10"
                                            >
                                                <RefreshCw class="h-4 w-4" />
                                            </Button>
                                            <Button 
                                                variant="outline" 
                                                size="sm" 
                                                class="h-8 w-8 p-0 rounded-lg text-rose-600 bg-white dark:bg-black border-rose-500/20 hover:bg-rose-50 dark:hover:bg-rose-500/10"
                                                @click="deleteBackup(backup.name)"
                                                :disabled="processing"
                                                title="Delete Backup"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Mobile Card List -->
                        <div class="md:hidden divide-y divide-zinc-100 dark:divide-zinc-800">
                            <div v-for="backup in backups" :key="backup.name" class="p-5 space-y-4 hover:bg-zinc-50 dark:hover:bg-zinc-900/30 transition-colors cursor-default">
                                <div class="flex items-start gap-4">
                                    <div class="h-10 w-10 min-w-[40px] rounded-xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center border border-zinc-200 dark:border-zinc-700 shadow-inner mt-1">
                                        <Database class="h-5 w-5 text-zinc-500" />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="font-bold font-mono text-sm break-all leading-tight">{{ backup.name }}</p>
                                        <div class="flex items-center gap-3 mt-2">
                                            <span class="font-mono font-bold text-[10px] text-zinc-500">{{ backup.date_formatted }}</span>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-sm border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-[9px] font-black uppercase tracking-widest text-zinc-600 dark:text-zinc-400">
                                                {{ backup.size }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 pt-2 border-t border-zinc-100 dark:border-zinc-800/50">
                                    <Button 
                                        variant="outline" 
                                        size="sm" 
                                        @click="downloadBackup(backup.name)"
                                        :disabled="processing"
                                        class="flex-1 h-9 rounded-lg border-zinc-200 dark:border-zinc-800 bg-white dark:bg-black font-bold text-[10px] uppercase tracking-widest"
                                    >
                                        <Download class="h-3.5 w-3.5 mr-1.5" />
                                        Get
                                    </Button>
                                    <Button 
                                        variant="outline" 
                                        size="sm" 
                                        @click="restoreBackup(backup.name)"
                                        :disabled="processing"
                                        class="flex-1 h-9 rounded-lg text-amber-600 border-amber-500/20 bg-amber-50/50 dark:bg-amber-500/5 hover:bg-amber-50 dark:hover:bg-amber-500/10 font-bold text-[10px] uppercase tracking-widest"
                                    >
                                        <RefreshCw class="h-3.5 w-3.5 mr-1.5" />
                                        Restore
                                    </Button>
                                    <Button 
                                        variant="outline" 
                                        size="sm" 
                                        class="flex-1 h-9 rounded-lg text-rose-600 border-rose-500/20 bg-rose-50/50 dark:bg-rose-500/5 hover:bg-rose-50 dark:hover:bg-rose-500/10 font-bold text-[10px] uppercase tracking-widest"
                                        @click="deleteBackup(backup.name)"
                                        :disabled="processing"
                                    >
                                        <Trash2 class="h-3.5 w-3.5 mr-1.5" />
                                        Delete
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirmation Dialog -->
        <Dialog :open="confirmModalOpen" @update:open="confirmModalOpen = $event">
            <DialogContent class="sm:max-w-md p-0 rounded-[2rem] overflow-hidden border-0 shadow-2xl">
                <div class="p-8">
                    <div class="flex flex-col items-center text-center">
                        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl mb-6 shadow-inner" :class="confirmIsDestructive ? 'bg-rose-50 text-rose-600 dark:bg-rose-500/10' : 'bg-zinc-50 text-zinc-900 dark:bg-zinc-800 dark:text-white'">
                            <AlertTriangle v-if="confirmIsDestructive" class="h-8 w-8" />
                            <CheckCircle2 v-else class="h-8 w-8" />
                        </div>
                        <DialogTitle class="text-2xl font-serif font-black tracking-tight mb-2">{{ confirmTitle }}</DialogTitle>
                        <p class="text-sm font-medium text-zinc-500 max-w-xs">{{ confirmDescription }}</p>
                    </div>
                    <DialogFooter class="grid grid-cols-2 gap-3 mt-8 sm:space-x-0 w-full">
                        <Button variant="outline" @click="confirmModalOpen = false" :disabled="processing" class="rounded-xl h-12 font-bold text-xs uppercase tracking-widest border-zinc-200 dark:border-zinc-800">Cancel</Button>
                        <Button 
                            :variant="confirmIsDestructive ? 'destructive' : 'default'" 
                            @click="handleConfirm"
                            :disabled="processing"
                            class="rounded-xl h-12 font-bold text-[10px] uppercase tracking-widest shadow-lg"
                        >
                            Confirm Action
                        </Button>
                    </DialogFooter>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Loading Indicator Modal -->
        <Dialog :open="processing">
            <DialogContent class="sm:max-w-[400px] flex flex-col items-center justify-center py-10 outline-none border-none shadow-none bg-transparent" @pointerDownOutside="(e) => e.preventDefault()">
                <div class="bg-white dark:bg-black border border-zinc-200 dark:border-zinc-800 p-8 rounded-3xl shadow-2xl flex flex-col items-center gap-5 min-w-[320px]">
                    <div class="relative">
                        <Loader2 class="h-14 w-14 animate-spin text-zinc-900 dark:text-white" />
                        <div class="absolute inset-0 flex items-center justify-center">
                            <Database class="h-5 w-5 text-zinc-400" />
                        </div>
                    </div>
                    <div class="text-center">
                        <h3 class="font-serif font-black text-xl tracking-tight">{{ processingMessage }}</h3>
                        <p class="text-[10px] font-black uppercase tracking-widest text-zinc-500 mt-2">Please wait, do not close this window</p>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
