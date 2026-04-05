<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, NavItem } from '@/types';
import { 
    Users, Search, Plus, Filter, MoreHorizontal, 
    Pencil, Trash2, Download, Upload, UserPlus,
    ChevronLeft, ChevronRight, Mail, Hash, Layers,
    Check, X, AlertCircle, Loader2
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useToast } from '@/composables/useToast';
import TimeInput from '@/components/TimeInput.vue';

interface Student {
    id: number;
    name: string;
    student_number: string;
    email: string | null;
    section: string | null;
    photo: string | null;
    schedule: any[] | null;
}

interface Props {
    students: {
        data: Student[];
        current_page: number;
        last_page: number;
        total: number;
        links: any[];
    };
    subjects: { id: number; name: string }[];
    filters: {
        search: string | null;
    };
}

const props = defineProps<Props>();
const toast = useToast();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Students', href: '/students' }
];

const searchQuery = ref(props.filters.search ?? '');
const isCreateModalOpen = ref(false);
const isEditModalOpen = ref(false);
const editingStudent = ref<Student | null>(null);

const form = useForm({
    name: '',
    student_number: '',
    email: '',
    section: '',
    photo: null as File | null,
    schedule: [] as any[],
});

const photoPreview = ref<string | null>(null);

watch(searchQuery, (value) => {
    router.get('/students', { search: value }, {
        preserveState: true,
        preserveScroll: true,
        replace: true
    });
});

function openCreateModal() {
    form.reset();
    photoPreview.value = null;
    isCreateModalOpen.value = true;
}

function openEditModal(student: Student) {
    editingStudent.value = student;
    form.name = student.name;
    form.student_number = student.student_number;
    form.email = student.email ?? '';
    form.section = student.section ?? '';
    form.schedule = student.schedule ?? [];
    photoPreview.value = student.photo;
    isEditModalOpen.value = true;
}

function handlePhotoChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (file) {
        form.photo = file;
        photoPreview.value = URL.createObjectURL(file);
    }
}

function submit() {
    form.transform((data) => ({
        ...data,
        schedule: data.schedule.length > 0 ? data.schedule : null,
    }));

    if (editingStudent.value) {
        form.put(`/students/${editingStudent.value.id}`, {
            onSuccess: () => {
                isEditModalOpen.value = false;
                toast.success('Student updated successfully');
            }
        });
    } else {
        form.post('/students', {
            onSuccess: () => {
                isCreateModalOpen.value = false;
                toast.success('Student created successfully');
            }
        });
    }
}

function deleteStudent(student: Student) {
    if (confirm(`Are you sure you want to delete ${student.name}?`)) {
        router.delete(`/students/${student.id}`, {
            onSuccess: () => toast.success('Student deleted successfully')
        });
    }
}

// Import Logic
const isImportModalOpen = ref(false);
const importFile = ref<File | null>(null);
const importing = ref(false);

function handleImportFile(e: Event) {
    importFile.value = (e.target as HTMLInputElement).files?.[0] ?? null;
}

function submitImport() {
    if (!importFile.value) return;
    importing.value = true;
    const formData = new FormData();
    formData.append('file', importFile.value);

    router.post('/students/import', formData, {
        onSuccess: () => {
            isImportModalOpen.value = false;
            toast.success('Students imported successfully');
        },
        onFinish: () => importing.value = false
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Students Management" />

        <div class="p-4 sm:p-8 max-w-7xl mx-auto space-y-8">
            <!-- Header Section -->
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-6 pb-6 border-b border-zinc-100 dark:border-zinc-800">
                <div class="space-y-1">
                    <div class="flex items-center gap-3">
                        <div class="h-12 w-12 rounded-2xl bg-zinc-950 dark:bg-white flex items-center justify-center shadow-lg shadow-zinc-200 dark:shadow-none">
                            <Users class="h-6 w-6 text-white dark:text-black" />
                        </div>
                        <div>
                            <h1 class="text-3xl font-serif font-black tracking-tight leading-none">Students</h1>
                            <p class="text-xs font-bold text-zinc-400 uppercase tracking-widest mt-1">Manage global student records</p>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Button variant="outline" @click="isImportModalOpen = true" class="rounded-xl font-bold uppercase tracking-widest text-[10px] h-10 px-4">
                        <Upload class="mr-2 h-3.5 w-3.5" /> Import CSV
                    </Button>
                    <a :href="`/template/download?type=students`" class="inline-flex items-center justify-center rounded-xl font-bold uppercase tracking-widest text-[10px] h-10 px-4 bg-zinc-50 hover:bg-zinc-100 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 transition-colors">
                        <Download class="mr-2 h-3.5 w-3.5" /> Template
                    </a>
                    <Button @click="openCreateModal" class="rounded-xl bg-zinc-950 dark:bg-white text-white dark:text-black font-bold uppercase tracking-widest text-[10px] h-10 px-5 hover:scale-[1.02] transition-transform active:scale-95 shadow-lg shadow-zinc-200 dark:shadow-none">
                        <UserPlus class="mr-2 h-4 w-4" /> Add Student
                    </Button>
                </div>
            </div>

            <!-- Filters & Search -->
            <div class="flex flex-col sm:flex-row gap-4">
                <div class="relative flex-1">
                    <Search class="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-zinc-400" />
                    <Input 
                        v-model="searchQuery"
                        placeholder="Search by name, ID, or section..."
                        class="pl-10 h-12 rounded-2xl border-zinc-100 dark:border-zinc-800 bg-white dark:bg-zinc-950 shadow-sm focus:ring-zinc-950 dark:focus:ring-white transition-all"
                    />
                </div>
            </div>

            <!-- Students List -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-for="student in props.students.data" :key="student.id" 
                    class="group relative bg-white dark:bg-black border border-zinc-100 dark:border-zinc-800 rounded-3xl p-5 hover:shadow-xl hover:shadow-zinc-200/50 dark:hover:shadow-none transition-all duration-300 hover:-translate-y-1"
                >
                    <div class="flex items-start gap-4">
                        <div class="h-16 w-16 rounded-2xl bg-zinc-100 dark:bg-zinc-900 overflow-hidden shrink-0 border border-zinc-50 dark:border-zinc-800 shadow-inner">
                            <img v-if="student.photo" :src="student.photo" class="h-full w-full object-cover" />
                            <div v-else class="h-full w-full flex items-center justify-center bg-zinc-50 dark:bg-zinc-900 text-zinc-400 font-serif text-2xl font-black">
                                {{ student.name.charAt(0) }}
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="font-serif font-black text-lg leading-tight truncate group-hover:text-zinc-600 dark:group-hover:text-zinc-300 transition-colors">
                                {{ student.name }}
                            </h3>
                            <div class="flex flex-col gap-1 mt-2">
                                <div class="flex items-center gap-2 text-[10px] font-bold text-zinc-400 uppercase tracking-widest">
                                    <Hash class="h-3 w-3" /> {{ student.student_number }}
                                </div>
                                <div v-if="student.section" class="flex items-center gap-2 text-[10px] font-bold text-zinc-400 uppercase tracking-widest">
                                    <Layers class="h-3 w-3" /> {{ student.section }}
                                </div>
                                <div v-if="student.email" class="flex items-center gap-2 text-[10px] font-bold text-zinc-400 uppercase tracking-widest truncate">
                                    <Mail class="h-3 w-3 shrink-0" /> {{ student.email }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="mt-6 pt-4 border-t border-zinc-50 dark:border-zinc-900 flex items-center justify-between">
                        <Link :href="`/students/${student.id}/analytics`" class="text-[10px] font-black uppercase tracking-[0.2em] text-zinc-400 hover:text-zinc-950 dark:hover:text-white transition-colors">
                            View Analytics
                        </Link>
                        <div class="flex items-center gap-1">
                            <button @click="openEditModal(student)" class="p-2 rounded-xl text-zinc-400 hover:bg-zinc-50 dark:hover:bg-zinc-900 hover:text-zinc-950 dark:hover:text-white transition-all active:scale-90">
                                <Pencil class="h-4 w-4" />
                            </button>
                            <button @click="deleteStudent(student)" class="p-2 rounded-xl text-zinc-400 hover:bg-rose-50 dark:hover:bg-rose-950/20 hover:text-rose-600 transition-all active:scale-90">
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="props.students.data.length === 0" class="py-20 flex flex-col items-center justify-center text-center space-y-4">
                <div class="h-20 w-20 rounded-full bg-zinc-50 dark:bg-zinc-900 flex items-center justify-center border-2 border-dashed border-zinc-200 dark:border-zinc-800">
                    <Users class="h-8 w-8 text-zinc-300" />
                </div>
                <div>
                    <h3 class="font-serif font-black text-xl">No students found</h3>
                    <p class="text-sm text-zinc-400 max-w-xs mx-auto mt-1">Try adjusting your search or add a new student to get started.</p>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="props.students.last_page > 1" class="flex items-center justify-center gap-2">
                <Button 
                    variant="outline" 
                    class="rounded-xl h-10 w-10 p-0"
                    :disabled="props.students.current_page === 1"
                    @click="router.get('/students', { search: searchQuery, page: props.students.current_page - 1 })"
                >
                    <ChevronLeft class="h-4 w-4" />
                </Button>
                <div class="flex items-center gap-1">
                    <span class="text-xs font-black px-3 py-2 rounded-xl bg-zinc-100 dark:bg-zinc-900">
                        {{ props.students.current_page }} / {{ props.students.last_page }}
                    </span>
                </div>
                <Button 
                    variant="outline" 
                    class="rounded-xl h-10 w-10 p-0"
                    :disabled="props.students.current_page === props.students.last_page"
                    @click="router.get('/students', { search: searchQuery, page: props.students.current_page + 1 })"
                >
                    <ChevronRight class="h-4 w-4" />
                </Button>
            </div>
        </div>

        <!-- Student Form Modal (Create/Edit) -->
        <Dialog :open="isCreateModalOpen || isEditModalOpen" @update:open="(val) => (isCreateModalOpen = isEditModalOpen = val)">
            <DialogContent class="sm:max-w-[450px] p-0 overflow-hidden rounded-[32px] border-none shadow-2xl">
                <DialogHeader class="p-8 pb-4">
                    <div class="h-12 w-12 rounded-2xl bg-zinc-50 dark:bg-zinc-900 flex items-center justify-center mb-4 border border-zinc-100 dark:border-zinc-800">
                        <Users class="h-6 w-6 text-zinc-400" />
                    </div>
                    <DialogTitle class="text-2xl font-serif font-black tracking-tight leading-none">
                        {{ editingStudent ? 'Edit Student' : 'Add New Student' }}
                    </DialogTitle>
                    <DialogDescription class="text-xs font-bold uppercase tracking-widest text-zinc-400 mt-2">
                        {{ editingStudent ? 'Update existing student record' : 'Create a new profile for the system' }}
                    </DialogDescription>

                    <!-- Global Error Alert -->
                    <div v-if="Object.keys(form.errors).length > 0" class="mt-4 rounded-xl bg-rose-50 p-3 border border-rose-100 dark:bg-rose-900/20 dark:border-rose-900/30">
                        <div class="flex gap-2">
                            <AlertCircle class="h-4 w-4 text-rose-500 shrink-0" />
                            <ul class="text-[10px] font-bold text-rose-600 dark:text-rose-400 list-disc list-inside">
                                <li v-for="(err, key) in form.errors" :key="key">{{ err }}</li>
                            </ul>
                        </div>
                    </div>
                </DialogHeader>

                <form @submit.prevent="submit" class="p-8 pt-4 space-y-6">
                    <!-- Photo Upload -->
                    <div class="flex flex-col items-center justify-center space-y-3 pb-2">
                        <div class="relative group h-24 w-24">
                            <div class="h-full w-full rounded-3xl bg-zinc-50 dark:bg-zinc-950 border-2 border-dashed border-zinc-200 dark:border-zinc-800 overflow-hidden flex items-center justify-center">
                                <img v-if="photoPreview" :src="photoPreview" class="h-full w-full object-cover" />
                                <Users v-else class="h-8 w-8 text-zinc-300" />
                            </div>
                            <input type="file" id="photo" @change="handlePhotoChange" class="hidden" accept="image/*" />
                            <label for="photo" class="absolute -bottom-2 -right-2 h-8 w-8 rounded-xl bg-zinc-950 dark:bg-white text-white dark:text-black flex items-center justify-center shadow-lg cursor-pointer hover:scale-110 transition-transform active:scale-95">
                                <Plus class="h-4 w-4" />
                            </label>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="space-y-1.5">
                            <label class="text-[10px] font-black uppercase tracking-widest text-zinc-400 ml-1">Full Name</label>
                            <Input v-model="form.name" placeholder="Juan Dela Cruz" class="rounded-xl h-11 border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 focus:ring-zinc-950 dark:focus:ring-white transition-all shadow-none" />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <label class="text-[10px] font-black uppercase tracking-widest text-zinc-400 ml-1">Student #</label>
                                <Input v-model="form.student_number" placeholder="2021-1001" class="rounded-xl h-11 border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 focus:ring-zinc-950 dark:focus:ring-white transition-all shadow-none" />
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-[10px] font-black uppercase tracking-widest text-zinc-400 ml-1">Section</label>
                                <Input v-model="form.section" placeholder="BSIT-4A" class="rounded-xl h-11 border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 focus:ring-zinc-950 dark:focus:ring-white transition-all shadow-none" />
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-[10px] font-black uppercase tracking-widest text-zinc-400 ml-1">Email Address</label>
                            <Input v-model="form.email" type="email" placeholder="student@example.com" class="rounded-xl h-11 border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 focus:ring-zinc-950 dark:focus:ring-white transition-all shadow-none" />
                        </div>
                    </div>

                    <DialogFooter class="sm:justify-between gap-4 mt-8 flex-col sm:flex-row">
                        <Button type="button" variant="ghost" @click="() => (isCreateModalOpen = isEditModalOpen = false)" class="rounded-xl font-bold uppercase tracking-widest text-[10px] grow">Cancel</Button>
                        <Button :disabled="form.processing" class="rounded-xl bg-zinc-950 dark:bg-white text-white dark:text-black font-bold uppercase tracking-widest text-[10px] h-11 px-8 grow shadow-lg shadow-zinc-200 dark:shadow-none">
                            <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                            {{ editingStudent ? 'Update Student' : 'Save Student' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Import Modal -->
        <Dialog :open="isImportModalOpen" @update:open="(val) => (isImportModalOpen = val)">
            <DialogContent class="sm:max-w-[400px] p-0 overflow-hidden rounded-[32px] border-none shadow-2xl">
                <DialogHeader class="p-8 pb-4">
                    <div class="h-12 w-12 rounded-2xl bg-zinc-50 dark:bg-zinc-900 flex items-center justify-center mb-4 border border-zinc-100 dark:border-zinc-800">
                        <Upload class="h-6 w-6 text-zinc-400" />
                    </div>
                    <DialogTitle class="text-2xl font-serif font-black tracking-tight leading-none">Import Students</DialogTitle>
                    <DialogDescription class="text-xs font-bold uppercase tracking-widest text-zinc-400 mt-2">Bulk upload via CSV file</DialogDescription>
                </DialogHeader>

                <div class="p-8 pt-4 space-y-6">
                    <div class="relative group">
                        <div class="h-32 rounded-3xl bg-zinc-50 dark:bg-zinc-900 border-2 border-dashed border-zinc-200 dark:border-zinc-800 flex flex-col items-center justify-center text-center p-4 transition-colors group-hover:border-zinc-400 dark:group-hover:border-zinc-600">
                            <Upload class="h-6 w-6 text-zinc-300 mb-2" />
                            <p class="text-[10px] font-bold text-zinc-400 uppercase tracking-widest">{{ importFile ? importFile.name : 'Click or drop CSV file here' }}</p>
                            <input type="file" @change="handleImportFile" class="absolute inset-0 opacity-0 cursor-pointer" accept=".csv" />
                        </div>
                    </div>

                    <div class="rounded-2xl bg-amber-50 dark:bg-amber-900/20 p-4 border border-amber-100 dark:border-amber-900/30">
                        <div class="flex gap-3">
                            <AlertCircle class="h-4 w-4 text-amber-500 shrink-0" />
                            <p class="text-[10px] font-bold text-amber-700 dark:text-amber-400 uppercase tracking-widest leading-relaxed">
                                Use the standard template for successful matching.
                            </p>
                        </div>
                    </div>

                    <DialogFooter class="flex flex-col sm:flex-row gap-2">
                        <Button variant="ghost" @click="isImportModalOpen = false" class="rounded-xl font-bold uppercase tracking-widest text-[10px] grow h-11">Cancel</Button>
                        <Button @click="submitImport" :disabled="!importFile || importing" class="rounded-xl bg-zinc-950 dark:bg-white text-white dark:text-black font-bold uppercase tracking-widest text-[10px] h-11 px-8 grow shadow-lg shadow-zinc-200 dark:shadow-none">
                            <Loader2 v-if="importing" class="mr-2 h-4 w-4 animate-spin" />
                            Start Import
                        </Button>
                    </DialogFooter>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
.font-serif {
    font-family: 'Outfit', sans-serif;
}
.shadow-3d {
    box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.1), 0 1px 4px rgba(0, 0, 0, 0.05);
}
</style>
