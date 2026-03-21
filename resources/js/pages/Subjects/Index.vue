<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import gsap from 'gsap';
import { Plus, BookOpen, Trash2, Edit2, Users } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

type Student = {
    id: number;
    name: string;
    student_number: string;
};

type Subject = {
    id: number;
    name: string;
    students?: Student[];
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
const listRef = ref<HTMLDivElement | null>(null);

const studentsModalOpen = ref(false);
const selectedSubjectForStudents = ref<Subject | null>(null);

function openStudentsModal(subject: Subject) {
    selectedSubjectForStudents.value = subject;
    studentsModalOpen.value = true;
}

function closeStudentsModal() {
    studentsModalOpen.value = false;
    setTimeout(() => {
        selectedSubjectForStudents.value = null;
    }, 300);
}

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

onMounted(() => {
    const header = document.querySelector('.bg-background\\/50.backdrop-blur-xl');
    if (header) {
        gsap.set(header.parentElement, { perspective: 1000 });
        gsap.from(header, {
            opacity: 0,
            y: -30,
            rotationX: 20,
            z: -50,
            duration: 1,
            ease: 'power3.out'
        });
    }

    if (!listRef.value) return;
    const cards = listRef.value.querySelectorAll<HTMLElement>('[data-subject-card]');
    
    gsap.set(listRef.value, { perspective: 1000 });
    
    gsap.from(cards, {
        opacity: 0,
        y: 50,
        rotationX: -30,
        z: -100,
        duration: 1,
        stagger: 0.1,
        ease: 'back.out(1.2)',
    });

    const buttons = document.querySelectorAll('button');
    buttons.forEach((btn) => {
        gsap.set(btn, { transformStyle: "preserve-3d" });
        btn.addEventListener('mousedown', () => {
            gsap.to(btn, { scale: 0.95, z: -5, duration: 0.1, ease: 'power1.out' });
        });
        btn.addEventListener('mouseup', () => {
            gsap.to(btn, { scale: 1, z: 0, duration: 0.3, ease: 'bounce.out' });
        });
        btn.addEventListener('mouseleave', () => {
            gsap.to(btn, { scale: 1, z: 0, duration: 0.3, ease: 'power1.out' });
        });
    });
});
</script>

<template>
    <Head title="Subjects" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
            <div class="rounded-[2rem] border border-sidebar-border/50 bg-background/50 backdrop-blur-xl p-8 shadow-2xl relative overflow-hidden group">
                <div class="absolute -right-16 -top-16 w-64 h-64 bg-primary/5 rounded-full blur-3xl pointer-events-none group-hover:bg-primary/10 transition-colors duration-700"></div>
                <h1 class="text-2xl font-serif font-bold text-foreground tracking-tight">
                    Subjects
                </h1>
                <p class="mt-2 text-sm text-muted-foreground/80 font-light max-w-2xl leading-relaxed">
                    Manage subjects available for student schedules. Create and configure classes to accurately track attendance.
                </p>
                <div class="mt-8 flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-10 px-5 rounded-full border-sidebar-border/50 bg-background/50 backdrop-blur-sm hover:bg-muted/50 transition-all gap-2 text-xs font-semibold tracking-wide"
                            @click="openCreateModal"
                        >
                            <Plus class="h-3.5 w-3.5" />
                            Add Subject
                        </Button>
                    </div>
                </div>
            </div>

            <div
                v-if="props.subjects.length === 0"
                class="flex w-full items-center justify-center rounded-2xl border border-dashed border-sidebar-border/70 bg-muted/30 p-12 text-center text-sm text-muted-foreground shadow-sm backdrop-blur-sm dark:border-sidebar-border"
            >
                <div class="max-w-[300px] space-y-2">
                    <BookOpen class="h-12 w-12 mx-auto opacity-20 mb-4" />
                    <p class="font-medium text-foreground">No subjects yet</p>
                    <p>Click "Add Subject" to create one.</p>
                </div>
            </div>
            
            <div 
                v-else 
                ref="listRef" 
                class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-6 pb-12"
            >
                <div 
                    v-for="subject in props.subjects" 
                    :key="subject.id"
                    data-subject-card
                    class="h-full"
                >
                    <article 
                        class="group relative flex flex-col h-full rounded-2xl border border-sidebar-border/40 bg-background/40 backdrop-blur-xl p-5 shadow-lg transition-all duration-500 hover:shadow-2xl md:hover:-translate-y-1.5 hover:border-sidebar-border/80 hover:bg-background/60 overflow-hidden min-h-[140px]"
                    >
                        <!-- Silhouette Background Icon -->
                        <BookOpen class="absolute -right-6 -bottom-6 w-32 h-32 text-foreground/[0.03] dark:text-foreground/[0.05] group-hover:text-primary/10 transition-colors duration-500 z-0 pointer-events-none transform -rotate-12" stroke-width="1" />

                        <!-- Visual Depth -->
                        <div class="absolute -right-6 -top-6 w-24 h-24 bg-primary/5 rounded-full blur-2xl group-hover:bg-primary/10 transition-colors pointer-events-none z-0"></div>

                        <div class="relative z-10 flex-1 flex flex-col gap-5">
                            <div class="flex items-start justify-between gap-2 overflow-hidden">
                                <div class="flex items-center gap-3">
                                    <div class="min-w-0 flex items-center min-h-[40px]">
                                        <h3 class="text-xl font-serif font-bold text-foreground leading-tight line-clamp-2" :title="subject.name">
                                            {{ subject.name }}
                                        </h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="relative z-10 mt-4 pt-4 border-t border-sidebar-border/30 flex flex-wrap items-center justify-end gap-2 opacity-100 md:opacity-0 md:group-hover:opacity-100 transition-opacity duration-300">
                            <Button 
                                size="sm" 
                                variant="secondary" 
                                class="h-8 px-3 rounded-full transition-all text-xs font-semibold shrink-0" 
                                @click="openStudentsModal(subject)"
                            >
                                <Users class="h-3.5 w-3.5 xl:mr-1.5" />
                                <span class="hidden xl:inline">Students</span>
                            </Button>
                            <Button 
                                size="sm" 
                                variant="outline" 
                                class="h-8 px-3 rounded-full border-sidebar-border hover:bg-muted transition-all text-xs font-semibold shrink-0" 
                                @click="openEditModal(subject)"
                            >
                                <Edit2 class="h-3.5 w-3.5 xl:mr-1.5" />
                                <span class="hidden xl:inline">Edit</span>
                            </Button>
                            <Button 
                                size="sm" 
                                variant="ghost" 
                                class="h-8 px-3 rounded-full text-destructive hover:bg-destructive/10 transition-all text-xs font-semibold shrink-0" 
                                @click="deleteSubject(subject.id)"
                            >
                                <Trash2 class="h-3.5 w-3.5 xl:mr-1.5" />
                                <span class="hidden xl:inline">Remove</span>
                            </Button>
                        </div>
                    </article>
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
        <!-- Students Modal -->
        <Dialog v-model:open="studentsModalOpen">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>Students enrolled in {{ selectedSubjectForStudents?.name }}</DialogTitle>
                </DialogHeader>
                <div class="py-4 space-y-4 max-h-[60vh] overflow-y-auto pr-2 custom-scrollbar">
                    <div v-if="selectedSubjectForStudents?.students?.length" class="space-y-3">
                        <div v-for="student in selectedSubjectForStudents.students" :key="student.id" class="group flex items-center justify-between p-4 rounded-2xl border border-sidebar-border/50 bg-background/50 backdrop-blur-xl shadow-sm hover:shadow-md hover:border-sidebar-border/80 transition-all duration-300">
                            <div class="flex items-center gap-4">
                                <!-- Avatar Initial -->
                                <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center shrink-0 border border-primary/20 group-hover:bg-primary/20 transition-colors shadow-inner">
                                    <span class="text-sm font-bold text-primary">
                                        {{ student.name.charAt(0).toUpperCase() }}
                                    </span>
                                </div>
                                <div class="flex flex-col gap-0.5">
                                    <span class="font-bold text-sm text-foreground tracking-tight">{{ student.name }}</span>
                                    <span class="text-xs font-medium text-muted-foreground/80 flex items-center gap-1.5">
                                        <span class="w-1.5 h-1.5 rounded-full bg-green-500/80"></span>
                                        ID: {{ student.student_number }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-8 text-muted-foreground flex flex-col items-center justify-center gap-2">
                        <Users class="w-8 h-8 opacity-20" />
                        <p class="text-sm">No students enrolled.</p>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="closeStudentsModal">Close</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
