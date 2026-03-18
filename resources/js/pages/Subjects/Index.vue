<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Plus, BookOpen, Trash2, Edit2 } from 'lucide-vue-next';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type Subject = {
    id: number;
    name: string;
};

type PageProps = {
    subjects: Subject[];
};

const props = defineProps<PageProps>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Subjects',
        href: '/subjects',
    },
];

const createModalOpen = ref(false);
const editModalOpen = ref(false);
const name = ref('');
const editName = ref('');
const editingSubjectId = ref<number | null>(null);
const submitting = ref(false);
const formErrors = ref<Record<string, string[]>>({});

const confirmModalOpen = ref(false);
const confirmTitle = ref('');
const confirmDescription = ref('');
const confirmAction = ref<(() => void) | null>(null);

function showConfirm(title: string, description: string, action: () => void) {
    confirmTitle.value = title;
    confirmDescription.value = description;
    confirmAction.value = action;
    confirmModalOpen.value = true;
}

function handleConfirm() {
    if (confirmAction.value) {
        confirmAction.value();
    }
    confirmModalOpen.value = false;
    confirmAction.value = null;
}

function openCreateModal() {
    name.value = '';
    formErrors.value = {};
    createModalOpen.value = true;
}

function closeCreateModal() {
    createModalOpen.value = false;
}

async function submitSubject() {
    submitting.value = true;
    formErrors.value = {};

    router.post(
        '/subjects',
        {
            name: name.value,
        },
        {
            onError: (errors) => {
                formErrors.value = errors as any;
            },
            onSuccess: () => {
                closeCreateModal();
            },
            onFinish: () => {
                submitting.value = false;
            },
            preserveScroll: true,
        },
    );
}

function openEditModal(subject: Subject) {
    editingSubjectId.value = subject.id;
    editName.value = subject.name;
    formErrors.value = {};
    editModalOpen.value = true;
}

function closeEditModal() {
    editModalOpen.value = false;
    editingSubjectId.value = null;
}

async function submitEditSubject() {
    if (!editingSubjectId.value) return;

    submitting.value = true;
    formErrors.value = {};

    router.put(
        `/subjects/${editingSubjectId.value}`,
        {
            name: editName.value,
        },
        {
            onError: (errors) => {
                formErrors.value = errors as any;
            },
            onSuccess: () => {
                closeEditModal();
            },
            onFinish: () => {
                submitting.value = false;
            },
            preserveScroll: true,
        },
    );
}

function deleteSubject(id: number) {
    showConfirm(
        'Delete Subject?',
        'Are you sure you want to delete this subject? It cannot be undone.',
        () => {
            router.delete(`/subjects/${id}`, {
                preserveScroll: true,
            });
        }
    );
}
</script>

<template>
    <Head title="Subjects" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4 sm:p-6 lg:p-8 max-w-5xl mx-auto w-full">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <h1 class="text-3xl font-serif font-bold tracking-tight text-foreground flex items-center gap-2">
                        <BookOpen class="h-6 w-6 text-zinc-500" />
                        Subjects
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Manage subjects available for student schedules.
                    </p>
                </div>
                <Button 
                    class="rounded-full gap-1.5 shrink-0 bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 hover:bg-zinc-800 dark:hover:bg-zinc-200 shadow-sm"
                    @click="openCreateModal"
                >
                    <Plus class="h-4 w-4" />
                    Add Subject
                </Button>
            </div>

            <div class="overflow-hidden rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-black shadow-xl">
                <div v-if="props.subjects.length === 0" class="p-12 text-center text-zinc-500 dark:text-zinc-400">
                    <BookOpen class="h-12 w-12 mx-auto opacity-20 mb-4" />
                    <p class="text-sm">No subjects yet. Click "Add Subject" to create one.</p>
                </div>
                <div v-else class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    <div 
                        v-for="subject in props.subjects" 
                        :key="subject.id"
                        class="flex items-center justify-between p-4 hover:bg-zinc-50 dark:hover:bg-zinc-900/50 transition-colors group"
                    >
                        <div class="flex flex-col">
                            <span class="text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ subject.name }}</span>
                        </div>
                        <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                            <Button 
                                size="icon-sm" 
                                variant="ghost" 
                                class="h-8 w-8 text-zinc-500 hover:text-zinc-900 dark:hover:text-white" 
                                title="Edit" 
                                @click="openEditModal(subject)"
                            >
                                <Edit2 class="h-4 w-4" />
                            </Button>
                            <Button 
                                size="icon-sm" 
                                variant="ghost" 
                                class="h-8 w-8 text-rose-500 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30" 
                                title="Delete" 
                                @click="deleteSubject(subject.id)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Modal -->
        <Dialog v-model:open="createModalOpen">
            <DialogContent class="max-w-sm">
                <DialogHeader>
                    <DialogTitle>Add Subject</DialogTitle>
                </DialogHeader>

                <form class="space-y-4 pt-2" @submit.prevent="submitSubject">
                    <div class="space-y-1.5">
                        <label class="text-xs font-medium">Subject Name</label>
                        <Input v-model="name" placeholder="e.g. Mathematics" autofocus />
                        <p v-if="formErrors.name" class="text-xs text-destructive">
                            {{ Array.isArray(formErrors.name) ? formErrors.name[0] : formErrors.name }}
                        </p>
                    </div>

                    <DialogFooter class="mt-4 flex justify-end gap-2">
                        <DialogClose as-child>
                            <Button type="button" variant="outline">Cancel</Button>
                        </DialogClose>
                        <Button type="submit" :disabled="submitting">
                            {{ submitting ? 'Saving…' : 'Save Subject' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Edit Modal -->
        <Dialog v-model:open="editModalOpen">
            <DialogContent class="max-w-sm">
                <DialogHeader>
                    <DialogTitle>Edit Subject</DialogTitle>
                </DialogHeader>

                <form class="space-y-4 pt-2" @submit.prevent="submitEditSubject">
                    <div class="space-y-1.5">
                        <label class="text-xs font-medium">Subject Name</label>
                        <Input v-model="editName" placeholder="e.g. Mathematics" autofocus />
                        <p v-if="formErrors.name" class="text-xs text-destructive">
                            {{ Array.isArray(formErrors.name) ? formErrors.name[0] : formErrors.name }}
                        </p>
                    </div>

                    <DialogFooter class="mt-4 flex justify-end gap-2">
                        <DialogClose as-child>
                            <Button type="button" variant="outline">Cancel</Button>
                        </DialogClose>
                        <Button type="submit" :disabled="submitting">
                            {{ submitting ? 'Saving…' : 'Save Changes' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Confirm Modal -->
        <Dialog v-model:open="confirmModalOpen">
            <DialogContent class="max-w-xs">
                <DialogHeader>
                    <DialogTitle>{{ confirmTitle }}</DialogTitle>
                </DialogHeader>
                <div class="py-2">
                    <p class="text-sm text-muted-foreground">{{ confirmDescription }}</p>
                </div>
                <DialogFooter class="flex justify-end gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="outline" @click="confirmModalOpen = false">Cancel</Button>
                    </DialogClose>
                    <Button type="button" variant="destructive" @click="handleConfirm">
                        Delete
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
