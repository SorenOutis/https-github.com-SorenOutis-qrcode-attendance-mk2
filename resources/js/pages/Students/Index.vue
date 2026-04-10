<script setup lang="ts">
import { Head, router, useForm, Link } from '@inertiajs/vue3';
import { ref, computed, watch, nextTick } from 'vue';
import QRCode from 'qrcode';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, NavItem } from '@/types';
import { 
    Users, Search, Plus, Filter, MoreHorizontal, 
    Pencil, Trash2, Download, Upload, UserPlus,
    ChevronLeft, ChevronRight, Mail, Hash, Layers,
    Check, X, AlertCircle, Loader2, Scan, RefreshCw, Clock
} from 'lucide-vue-next';
import { watchDebounced, useDebounceFn } from '@vueuse/core';
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
import StudentFormModal from '@/components/dashboard/modals/StudentFormModal.vue';
import QrCodeModal from '@/components/dashboard/modals/QrCodeModal.vue';

interface Student {
    id: number;
    name: string;
    student_number: string;
    email: string | null;
    section: string | null;
    photo: string | null;
    schedule: any[] | null;
    qr_token: string | null;
}

interface Props {
    students: {
        data: Student[];
        current_page: number;
        last_page: number;
        total: number;
        links: any[];
    };
    subjects: { id: number; name: string; schedule: any[] }[];
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
const isSearching = ref(false);
const isCreateModalOpen = ref(false);
const isEditModalOpen = ref(false);
const editingStudent = ref<Student | null>(null);
const qrModalOpen = ref(false);
const selectedStudent = ref<Student | null>(null);

const form = useForm({
    name: '',
    student_number: '',
    email: '',
    section: '',
    photo: null as File | null,
    schedule: [] as any[],
});

const selectedSubjectIds = ref<number[]>([]);

const schedules = computed(() => {
    const slots: any[] = [];
    selectedSubjectIds.value.forEach(id => {
        const subject = props.subjects.find(s => s.id === id);
        if (subject && subject.schedule) {
            subject.schedule.forEach((s: any) => {
                slots.push({
                    day: s.day,
                    subject_id: subject.id,
                    start: s.start,
                    end: s.end
                });
            });
        }
    });
    return slots;
});

function toggleSubject(id: number) {
    const index = selectedSubjectIds.value.indexOf(id);
    if (index === -1) {
        selectedSubjectIds.value.push(id);
    } else {
        selectedSubjectIds.value.splice(index, 1);
    }
}

const photoPreview = ref<string | null>(null);

watchDebounced(
    searchQuery,
    (value) => {
        isSearching.value = true;

        // Update the URL query param manually without a full navigation
        const url = new URL(window.location.href);
        if (value) {
            url.searchParams.set('search', value);
        } else {
            url.searchParams.delete('search');
        }
        window.history.replaceState({}, '', url.toString());

        // Partial reload — only refreshes the `students` prop, no page transition fires
        router.reload({
            data: { search: value },
            only: ['students', 'filters'],
            preserveScroll: true,
            onFinish: () => {
                isSearching.value = false;
            },
        });
    },
    { debounce: 400, maxWait: 2000 },
);

function openCreateModal() {
    form.reset();
    selectedSubjectIds.value = [];
    photoPreview.value = null;
    isCreateModalOpen.value = true;
}

function openEditModal(student: Student) {
    editingStudent.value = student;
    form.name = student.name;
    form.student_number = student.student_number;
    form.email = student.email ?? '';
    form.section = student.section ?? '';
    
    const subjectIds = new Set<number>();
    if (student.schedule) {
        student.schedule.forEach((s: any) => {
            if (s.subject_id) subjectIds.add(Number(s.subject_id));
        });
    }
    selectedSubjectIds.value = Array.from(subjectIds);
    
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
        schedule: schedules.value.length > 0 ? schedules.value : null,
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

function openQrModal(student: Student) {
    selectedStudent.value = student;
    qrModalOpen.value = true;
}

function closeQrModal() {
    qrModalOpen.value = false;
    selectedStudent.value = null;
}

function regenerateQr() {
    if (!selectedStudent.value) return;

    router.post(
        `/students/${selectedStudent.value.id}/qr/regenerate`,
        {},
        {
            onSuccess: () => {
                router.visit('/students', {
                    only: ['students'],
                    preserveScroll: true,
                    onSuccess: (page) => {
                        const updated = (page.props as any).students.data.find(
                            (s: Student) => s.id === selectedStudent.value?.id,
                        );
                        if (updated) {
                            selectedStudent.value = updated;
                        }
                    },
                });
            },
        },
    );
}

function studentPortalUrl(token: string) {
    const base = window.location.origin;
    return `${base}/portal/${encodeURIComponent(token)}`;
}

function copyStudentPortalLink() {
    const token = selectedStudent.value?.qr_token;
    if (!token) return;
    const url = studentPortalUrl(token);
    navigator.clipboard.writeText(url);
    toast.success('Link copied to clipboard');
}

function openPrintCards(id?: number) {
    const ids = id ? id.toString() : selectedStudent.value?.id;
    if (!ids) return;
    window.open(`/students/print-cards?ids=${ids}`, '_blank', 'noopener,noreferrer');
}

function getAvatarGradient(name: string) {
    const colors = [
        'from-zinc-200 to-zinc-400 dark:from-zinc-700 dark:to-zinc-900',
        'from-zinc-300 to-zinc-500 dark:from-zinc-600 dark:to-zinc-800',
        'from-zinc-100 to-zinc-300 dark:from-zinc-800 dark:to-zinc-950',
        'from-zinc-400 to-zinc-600 dark:from-zinc-500 dark:to-zinc-700'
    ];
    let hash = 0;
    for (let i = 0; i < name.length; i++) {
        hash = name.charCodeAt(i) + ((hash << 5) - hash);
    }
    return colors[Math.abs(hash) % colors.length];
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
const photoInput = ref<HTMLInputElement | null>(null);

function getSubjectName(subjectId: string | number | null | undefined): string {
    if (!subjectId) return 'N/A';
    const subject = props.subjects?.find((s: any) => s.id.toString() === subjectId.toString());
    return subject ? subject.name : 'Unknown';
}

function formatTimeTo12h(timeStr?: string) {
    if (!timeStr) return '';
    const parts = timeStr.split(':');
    if (parts.length < 2) return timeStr;
    let h = parseInt(parts[0]);
    const m = parts[1];
    const ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12;
    h = h ? h : 12;
    return `${h}:${m} ${ampm}`;
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Students Management" />

        <div class="p-4 sm:p-8 space-y-8">
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
                    <a :href="`/students/sample`" class="inline-flex items-center justify-center rounded-xl font-bold uppercase tracking-widest text-[10px] h-10 px-4 bg-zinc-50 hover:bg-zinc-100 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 transition-colors">
                        <Download class="mr-2 h-3.5 w-3.5" /> Template
                    </a>
                    <Button @click="openCreateModal" class="rounded-xl bg-zinc-950 dark:bg-white text-white dark:text-black font-bold uppercase tracking-widest text-[10px] h-10 px-5 shadow-lg shadow-zinc-200 dark:shadow-none">
                        <UserPlus class="mr-2 h-4 w-4" /> Add Student
                    </Button>
                </div>
            </div>

            <!-- Filters & Search -->
            <div class="flex flex-col sm:flex-row gap-4">
                <div class="relative flex-1">
                    <Search
                        v-if="!isSearching"
                        class="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-zinc-400 pointer-events-none"
                    />
                    <Loader2
                        v-else
                        class="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-zinc-400 animate-spin pointer-events-none"
                    />
                    <Input
                        v-model="searchQuery"
                        placeholder="Search by name, ID, or section..."
                        class="pl-10 h-12 rounded-2xl border-zinc-100 dark:border-zinc-800 bg-white dark:bg-zinc-950 shadow-sm focus:ring-zinc-950 dark:focus:ring-white"
                    />
                    <button
                        v-if="searchQuery"
                        @click="searchQuery = ''"
                        class="absolute right-3.5 top-1/2 -translate-y-1/2 h-5 w-5 rounded-full bg-zinc-200 dark:bg-zinc-700 flex items-center justify-center text-zinc-500 hover:text-zinc-950 dark:hover:text-white hover:bg-zinc-300 dark:hover:bg-zinc-600 transition-all"
                        title="Clear search"
                    >
                        <X class="h-3 w-3" />
                    </button>
                </div>
            </div>

            <!-- Students List -->
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 lg:grid-cols-3 gap-4">
                <div v-for="student in props.students.data" :key="student.id" 
                    class="group relative bg-white dark:bg-black border border-zinc-100 dark:border-zinc-800 rounded-[28px] p-4 hover:shadow-xl hover:shadow-zinc-200/40 dark:hover:shadow-none"
                >
                    <div class="flex items-start gap-3">
                        <div class="h-12 w-12 rounded-2xl bg-zinc-100 dark:bg-zinc-900 overflow-hidden shrink-0 border border-zinc-50 dark:border-zinc-800 shadow-inner">
                            <img v-if="student.photo" :src="student.photo" class="h-full w-full object-cover" />
                            <div v-else :class="['h-full w-full flex items-center justify-center shrink-0 border border-white/20 shadow-inner bg-gradient-to-br transition-all duration-500', getAvatarGradient(student.name)]">
                                <span class="text-2xl font-serif font-black text-white dark:text-zinc-100 drop-shadow-sm uppercase tracking-tighter">{{ student.name.charAt(0) }}</span>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="font-serif font-black text-base leading-tight truncate group-hover:text-zinc-600 dark:group-hover:text-zinc-300 transition-colors">
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
                    <div class="mt-4 pt-3 border-t border-zinc-50 dark:border-zinc-900 flex items-center justify-between">
                        <Link :href="`/students/${student.id}/analytics`" class="text-[10px] font-black uppercase tracking-[0.2em] text-zinc-400 hover:text-zinc-950 dark:hover:text-white transition-colors">
                            View Analytics
                        </Link>
                        <div class="flex items-center gap-1">
                            <button @click="openQrModal(student)" class="p-2 rounded-xl text-zinc-400 hover:bg-zinc-50 dark:hover:bg-zinc-900 hover:text-zinc-950 dark:hover:text-white transition-all active:scale-90" title="View QR">
                                <Scan class="h-4 w-4" />
                            </button>
                            <button @click="openPrintCards(student.id)" class="p-2 rounded-xl text-zinc-400 hover:bg-zinc-50 dark:hover:bg-zinc-900 hover:text-zinc-950 dark:hover:text-white transition-all active:scale-90" title="Print Card">
                                <Download class="h-4 w-4" />
                            </button>
                            <button @click="openEditModal(student)" class="p-2 rounded-xl text-zinc-400 hover:bg-zinc-50 dark:hover:bg-zinc-900 hover:text-zinc-950 dark:hover:text-white transition-all active:scale-90" title="Edit">
                                <Pencil class="h-4 w-4" />
                            </button>
                            <button @click="deleteStudent(student)" class="p-2 rounded-xl text-zinc-400 hover:bg-rose-50 dark:hover:bg-rose-950/20 hover:text-rose-600 transition-all active:scale-90" title="Delete">
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
        <StudentFormModal 
            v-model:open="isCreateModalOpen"
            mode="create"
            :submitting="form.processing"
            :form-errors="form.errors"
            :subjects="props.subjects"
            v-model:name="form.name"
            v-model:studentNumber="form.student_number"
            v-model:email="form.email"
            v-model:section="form.section"
            v-model:selectedSubjectIds="selectedSubjectIds"
            :photo-preview="photoPreview"
            :schedules="schedules"
            :get-subject-name="getSubjectName"
            :format-time-to-12h="formatTimeTo12h"
            @handle-photo-change="handlePhotoChange"
            @toggle-subject="toggleSubject"
            @submit="submit"
        />

        <StudentFormModal 
            v-model:open="isEditModalOpen"
            mode="edit"
            :submitting="form.processing"
            :form-errors="form.errors"
            :subjects="props.subjects"
            v-model:name="form.name"
            v-model:studentNumber="form.student_number"
            v-model:email="form.email"
            v-model:section="form.section"
            v-model:selectedSubjectIds="selectedSubjectIds"
            :photo-preview="photoPreview"
            :schedules="schedules"
            :get-subject-name="getSubjectName"
            :format-time-to-12h="formatTimeTo12h"
            @handle-photo-change="handlePhotoChange"
            @toggle-subject="toggleSubject"
            @submit="submit"
        />

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

        <!-- QR Code Modal -->
        <QrCodeModal 
            v-model:open="qrModalOpen"
            :student="selectedStudent"
            :qr-canvas="null"
            :student-portal-url="studentPortalUrl"
            @regenerate="regenerateQr"
            @download="() => {}"
            @print="() => {}"
            @copy-link="copyStudentPortalLink"
        />
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
