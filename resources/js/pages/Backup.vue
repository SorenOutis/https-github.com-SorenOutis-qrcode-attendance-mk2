<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Database, RefreshCw, Trash2, Plus, Download, Upload } from 'lucide-vue-next';
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
    router.post('/backups', {}, {
        preserveScroll: true,
        onSuccess: () => toast.success('Backup created successfully.'),
        onError: () => toast.error('Failed to create backup.'),
        onFinish: () => { processing.value = false; }
    });
}

function restoreBackup(file: string) {
    showConfirm(
        'Restore Backup?',
        'Are you sure you want to restore this backup? This will overwrite the current database and you will lose any new data created since the backup.',
        () => {
            processing.value = true;
            router.post(`/backups/${file}/restore`, {}, {
                preserveScroll: true,
                onSuccess: () => toast.success('Database restored successfully.'),
                onError: () => toast.error('Failed to restore database.'),
                onFinish: () => { processing.value = false; }
            });
        },
        true
    );
}

function deleteBackup(file: string) {
    showConfirm(
        'Delete Backup?',
        'Are you sure you want to delete this backup file? This action cannot be undone.',
        () => {
            processing.value = true;
            router.delete(`/backups/${file}`, {
                preserveScroll: true,
                onSuccess: () => toast.success('Backup deleted successfully.'),
                onError: () => toast.error('Failed to delete backup.'),
                onFinish: () => { processing.value = false; }
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
    router.post('/backups/upload', { backup: file }, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => toast.success('Backup uploaded successfully.'),
        onError: (errors) => {
            toast.error(errors.backup || 'Failed to upload backup.');
        },
        onFinish: () => {
            processing.value = false;
            if (fileInput.value) fileInput.value.value = '';
        }
    });
}

function downloadBackup(file: string) {
    window.location.href = `/backups/${file}/download`;
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="System Backups" />

        <div class="flex h-full flex-1 flex-col gap-6 p-4 pt-0">
            <!-- Header -->
            <div class="flex items-center justify-between mt-4">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">System Backups</h1>
                    <p class="text-sm text-muted-foreground mt-1">Manage, create, and restore database backups.</p>
                </div>
                <div class="flex items-center gap-2">
                    <input type="file" ref="fileInput" class="hidden" accept=".sqlite" @change="handleUpload" />
                    <Button variant="outline" @click="triggerUpload" :disabled="processing">
                        <Upload class="mr-2 h-4 w-4" />
                        Upload Backup
                    </Button>
                    <Button @click="createBackup" :disabled="processing">
                        <Plus class="mr-2 h-4 w-4" />
                        Create Backup
                    </Button>
                </div>
            </div>

            <!-- Content -->
            <div class="rounded-xl border bg-card text-card-foreground shadow-sm">
                <div class="p-6">
                    <div v-if="backups.length === 0" class="flex flex-col items-center justify-center py-12 text-center">
                        <Database class="h-12 w-12 text-muted-foreground mb-4 opacity-50" />
                        <h3 class="text-lg font-medium">No backups found</h3>
                        <p class="text-sm text-muted-foreground max-w-sm mt-1">You haven't created any database backups yet. Create one to keep your data safe.</p>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted">
                                    <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground w-1/3">Backup Name</th>
                                    <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Date</th>
                                    <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Size</th>
                                    <th class="h-12 px-4 text-right align-middle font-medium text-muted-foreground w-[200px]">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="backup in backups" :key="backup.name" class="border-b transition-colors hover:bg-muted/50">
                                    <td class="p-4 align-middle">
                                        <div class="flex items-center gap-2 font-medium">
                                            <Database class="h-4 w-4 text-muted-foreground" />
                                            {{ backup.name }}
                                        </div>
                                    </td>
                                    <td class="p-4 align-middle">{{ backup.date_formatted }}</td>
                                    <td class="p-4 align-middle">
                                        <span class="inline-flex items-center gap-1">
                                            {{ backup.size }}
                                        </span>
                                    </td>
                                    <td class="p-4 align-middle text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <Button 
                                                variant="outline" 
                                                size="sm" 
                                                @click="downloadBackup(backup.name)"
                                                :disabled="processing"
                                                title="Download Backup"
                                            >
                                                <Download class="h-4 w-4 mr-1" />
                                                Download
                                            </Button>
                                            <Button 
                                                variant="outline" 
                                                size="sm" 
                                                @click="restoreBackup(backup.name)"
                                                :disabled="processing"
                                                title="Restore Backup"
                                            >
                                                <RefreshCw class="h-4 w-4 mr-1" />
                                                Restore
                                            </Button>
                                            <Button 
                                                variant="outline" 
                                                size="sm" 
                                                class="text-destructive hover:bg-destructive/10 border-destructive/20"
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
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirmation Dialog -->
        <Dialog :open="confirmModalOpen" @update:open="confirmModalOpen = $event">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>{{ confirmTitle }}</DialogTitle>
                </DialogHeader>
                <div class="py-4">
                    <p class="text-sm text-muted-foreground">{{ confirmDescription }}</p>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="confirmModalOpen = false" :disabled="processing">Cancel</Button>
                    <Button 
                        :variant="confirmIsDestructive ? 'destructive' : 'default'" 
                        @click="handleConfirm"
                        :disabled="processing"
                    >
                        {{ processing ? 'Processing...' : 'Confirm' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
