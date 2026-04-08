<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
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

watch(searchQuery, (value) => {
    router.get('/students', { search: value }, {
        preserveState: true,
        preserveScroll: true,
        replace: true
    });
});

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
                            nextTick(() => drawQrToCanvas());
                        }
                    },
                });
            },
        },
    );
}

async function drawQrToCanvas() {
    const canvas = document.querySelector<HTMLCanvasElement>('#qr-canvas');
    const student = selectedStudent.value;
    if (!canvas || !student?.qr_token) return;

    try {
        await QRCode.toCanvas(canvas, student.qr_token, {
            width: 192,
            margin: 1,
            color: { dark: '#000000', light: '#ffffff' },
        });
    } catch (e) {
        console.error('QR code draw failed:', e);
    }
}

watch(
    [qrModalOpen, selectedStudent],
    ([open, student]) => {
        if (open && student) {
            nextTick(() => drawQrToCanvas());
        }
    },
    { immediate: true },
);

function downloadQr() {
    const canvas = document.querySelector<HTMLCanvasElement>('#qr-canvas');
    if (!canvas || !selectedStudent.value) return;

    const link = document.createElement('a');
    link.href = canvas.toDataURL('image/png');
    link.download = `${selectedStudent.value.name}-qr.png`;
    link.click();
}

function studentPortalUrl(token: string) {
    const base = window.location.origin;
    return `${base}/portal/${encodeURIComponent(token)}`;
}

async function copyStudentPortalLink() {
    const token = selectedStudent.value?.qr_token;
    if (!token) return;
    const url = studentPortalUrl(token);

    try {
        await navigator.clipboard.writeText(url);
    } catch {
        // Fallback for older browsers / blocked clipboard
        const input = document.createElement('input');
        input.value = url;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
    }
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
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 lg:grid-cols-3 gap-4">
                <div v-for="student in props.students.data" :key="student.id" 
                    class="group relative bg-white dark:bg-black border border-zinc-100 dark:border-zinc-800 rounded-[28px] p-4 hover:shadow-xl hover:shadow-zinc-200/40 dark:hover:shadow-none transition-all duration-300 hover:-translate-y-1"
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
        <Dialog :open="isCreateModalOpen || isEditModalOpen" @update:open="(val) => {
            if (!val) {
                isCreateModalOpen = false;
                isEditModalOpen = false;
                editingStudent = null;
                form.reset();
                selectedSubjectIds = [];
                photoPreview = null;
            }
        }">
            <DialogContent class="max-w-[400px] md:max-w-2xl flex flex-col max-h-[90dvh] md:max-h-none overflow-hidden p-0 border-none rounded-[32px] shadow-2xl">
                <DialogHeader class="p-6 md:p-8 pb-4">
                    <DialogTitle class="text-2xl font-serif font-black tracking-tight leading-none text-zinc-900 dark:text-zinc-100 flex items-center gap-3">
                        <div class="h-10 w-10 rounded-2xl bg-zinc-950 dark:bg-white flex items-center justify-center shrink-0">
                            <Users class="h-5 w-5 text-white dark:text-black" />
                        </div>
                        {{ editingStudent ? 'Edit student' : 'Add new student' }}
                    </DialogTitle>
                </DialogHeader>

                <form class="flex flex-col flex-1 min-h-0" @submit.prevent="submit">
                    <div class="flex-1 overflow-y-auto p-6 md:p-8 pt-0 space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-[180px_1fr] gap-6">
                            <!-- Photo Upload -->
                            <div class="flex flex-col items-center justify-center p-4 rounded-3xl border border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-950/30">
                                <div class="relative group cursor-pointer" @click="photoInput?.click()">
                                    <div class="h-24 w-24 rounded-[32px] overflow-hidden border-2 border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-900 flex items-center justify-center transition-all group-hover:border-zinc-400 dark:group-hover:border-zinc-600 shadow-inner">
                                        <img v-if="photoPreview" :src="photoPreview" class="h-full w-full object-cover" />
                                        <div v-else class="flex flex-col items-center text-zinc-400">
                                            <Plus class="h-6 w-6 mb-1 opacity-50" />
                                            <span class="text-[10px] font-black uppercase tracking-widest leading-none">Photo</span>
                                        </div>
                                        <!-- Overlay -->
                                        <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                            <Scan class="h-6 w-6 text-white" />
                                        </div>
                                    </div>
                                    <input 
                                        type="file" 
                                        ref="photoInput" 
                                        class="hidden" 
                                        accept="image/*" 
                                        @change="handlePhotoChange"
                                    />
                                    <div class="absolute -bottom-2 -right-2 bg-zinc-950 dark:bg-white text-white dark:text-zinc-950 p-2 rounded-2xl shadow-xl">
                                        <Plus class="h-4 w-4" />
                                    </div>
                                </div>
                                <p class="text-[10px] text-zinc-400 mt-4 font-black uppercase tracking-widest text-center leading-none">Student Portrait</p>
                                <p v-if="form.errors.photo" class="text-[10px] text-rose-500 mt-2 text-center font-bold">
                                    {{ form.errors.photo }}
                                </p>
                            </div>

                            <div class="space-y-4">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div class="space-y-2">
                                        <label class="text-[10px] font-black uppercase tracking-widest text-zinc-400 ml-1">Full name</label>
                                        <Input v-model="form.name" placeholder="Juan Dela Cruz" class="h-11 rounded-2xl border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 focus:ring-zinc-950 dark:focus:ring-white transition-all shadow-none font-medium" />
                                        <p v-if="form.errors.name" class="text-[10px] text-rose-500 font-bold ml-1">{{ form.errors.name }}</p>
                                    </div>

                                    <div class="space-y-2">
                                        <label class="text-[10px] font-black uppercase tracking-widest text-zinc-400 ml-1">Student number</label>
                                        <Input v-model="form.student_number" placeholder="2026-0001" class="h-11 rounded-2xl border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 focus:ring-zinc-950 dark:focus:ring-white transition-all shadow-none font-medium" />
                                        <p v-if="form.errors.student_number" class="text-[10px] text-rose-500 font-bold ml-1">{{ form.errors.student_number }}</p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div class="space-y-2">
                                        <label class="text-[10px] font-black uppercase tracking-widest text-zinc-400 ml-1">Section</label>
                                        <Input v-model="form.section" placeholder="BSIT-3A" class="h-11 rounded-2xl border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 focus:ring-zinc-950 dark:focus:ring-white transition-all shadow-none font-medium" />
                                        <p v-if="form.errors.section" class="text-[10px] text-rose-500 font-bold ml-1">{{ form.errors.section }}</p>
                                    </div>

                                    <div class="space-y-2">
                                        <label class="text-[10px] font-black uppercase tracking-widest text-zinc-400 ml-1">Email (Optional)</label>
                                        <Input v-model="form.email" type="email" placeholder="Optional" class="h-11 rounded-2xl border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 focus:ring-zinc-950 dark:focus:ring-white transition-all shadow-none font-medium" />
                                        <p v-if="form.errors.email" class="text-[10px] text-rose-500 font-bold ml-1">{{ form.errors.email }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4 pt-6 border-t border-zinc-100 dark:border-zinc-800/80">
                            <div class="flex items-center justify-between">
                                <label class="text-[10px] font-black uppercase tracking-widest text-zinc-400 ml-1">
                                    Enrolled Subjects
                                </label>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="subj in props.subjects"
                                    :key="subj.id"
                                    type="button"
                                    @click="toggleSubject(subj.id)"
                                    :class="[
                                        'px-4 py-2 rounded-2xl text-[10px] font-black uppercase tracking-widest border transition-all active:scale-95',
                                        selectedSubjectIds.includes(subj.id)
                                            ? 'bg-zinc-950 dark:bg-white text-white dark:text-zinc-950 border-transparent shadow-lg shadow-zinc-200 dark:shadow-none'
                                            : 'bg-zinc-50 dark:bg-zinc-900/50 border-zinc-100 dark:border-zinc-800 text-zinc-500 dark:text-zinc-400 hover:border-zinc-300 dark:hover:border-zinc-700'
                                    ]"
                                >
                                    {{ subj.name }}
                                </button>
                            </div>

                            <!-- Preview of schedule -->
                            <div v-if="schedules.length > 0" class="p-4 rounded-3xl bg-zinc-50/50 dark:bg-zinc-950/30 border border-zinc-100 dark:border-zinc-800/80">
                                <p class="text-[9px] font-black uppercase tracking-[0.2em] text-zinc-400 mb-4 ml-1">Schedule Preview</p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div 
                                        v-for="(slot, i) in schedules" 
                                        :key="i"
                                        class="flex items-center gap-3 p-3 rounded-2xl bg-white dark:bg-zinc-900 shadow-sm border border-zinc-100 dark:border-zinc-800/80"
                                    >
                                        <div class="h-2 w-2 rounded-full bg-zinc-950 dark:bg-white shrink-0" />
                                        <div class="flex-1 min-w-0">
                                            <p class="text-[11px] font-black truncate leading-none uppercase tracking-tight">{{ getSubjectName(slot.subject_id) }}</p>
                                            <p class="text-[10px] text-zinc-500 font-bold tabular-nums mt-1.5 opacity-80">
                                                {{ slot.day }} · {{ formatTimeTo12h(slot.start) }} – {{ formatTimeTo12h(slot.end) }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div v-if="form.errors.schedule" class="text-[10px] text-rose-500 font-bold ml-1 pt-1">
                                • {{ form.errors.schedule }}
                            </div>
                        </div>
                    </div>

                    <DialogFooter class="p-6 md:p-8 bg-zinc-50/50 dark:bg-zinc-950/30 border-t border-zinc-100 dark:border-zinc-800 flex flex-col sm:flex-row gap-3">
                        <Button type="button" variant="ghost" @click="() => (isCreateModalOpen = isEditModalOpen = false)" class="rounded-2xl font-black uppercase tracking-widest text-[10px] h-12 order-2 sm:order-1 flex-1">
                            Cancel
                        </Button>
                        <Button :disabled="form.processing" class="rounded-2xl bg-zinc-950 dark:bg-white text-white dark:text-zinc-950 font-black uppercase tracking-widest text-[10px] h-12 px-8 flex-1 order-1 sm:order-2 shadow-xl shadow-zinc-200 dark:shadow-none hover:scale-[1.02] transition-transform active:scale-95">
                            <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                            {{ editingStudent ? 'Update student' : 'Save student' }}
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

        <!-- QR Code Modal -->
        <Dialog :open="qrModalOpen" @update:open="closeQrModal">
            <DialogContent class="sm:max-w-md p-0 overflow-hidden rounded-[32px] border-none shadow-2xl">
                <DialogHeader class="p-8 pb-4">
                    <DialogTitle class="text-2xl font-serif font-black tracking-tight leading-none">Student QR Code</DialogTitle>
                    <DialogDescription class="text-xs font-bold uppercase tracking-widest text-zinc-400 mt-2">Unique entry pass for {{ selectedStudent?.name }}</DialogDescription>
                </DialogHeader>

                <div class="p-8 pt-4 flex flex-col items-center">
                    <div class="relative group p-4 bg-white rounded-3xl shadow-3d border border-zinc-100 dark:border-zinc-800 mb-8">
                        <canvas id="qr-canvas" class="rounded-xl"></canvas>
                        <div class="absolute inset-0 bg-black/5 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center rounded-3xl pointer-events-none">
                            <Download class="h-8 w-8 text-white/50" />
                        </div>
                    </div>

                    <div class="w-full space-y-3">
                        <Button 
                            variant="outline" 
                            @click="downloadQr" 
                            class="w-full rounded-2xl h-12 font-black uppercase tracking-widest text-xs"
                        >
                            <Download class="mr-2 h-4 w-4" /> Download PNG
                        </Button>
                        <div class="grid grid-cols-2 gap-3">
                            <Button 
                                variant="ghost" 
                                @click="copyStudentPortalLink" 
                                class="rounded-2xl h-12 font-black uppercase tracking-widest text-[10px]"
                            >
                                <Mail class="mr-2 h-3.5 w-3.5" /> Portal Link
                            </Button>
                            <Button 
                                variant="ghost" 
                                @click="regenerateQr" 
                                class="rounded-2xl h-12 font-black uppercase tracking-widest text-[10px] text-muted-foreground hover:text-zinc-950"
                            >
                                <RefreshCw class="mr-2 h-3.5 w-3.5" /> Reset Token
                            </Button>
                        </div>
                    </div>

                    <div class="mt-8 pt-8 w-full border-t border-zinc-100 dark:border-zinc-800/80 flex justify-between items-center px-2">
                        <Button
                            variant="ghost"
                            class="rounded-xl h-10 px-4 text-xs font-bold hover:bg-zinc-50 dark:hover:bg-zinc-900"
                            @click="openPrintCards()"
                        >
                            <Download class="mr-2 h-3.5 w-3.5" /> Print Card
                        </Button>
                        <Button
                            variant="ghost"
                            class="rounded-xl h-10 px-4 text-xs font-bold text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/20"
                            @click="closeQrModal"
                        >
                            Dismiss
                        </Button>
                    </div>
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
