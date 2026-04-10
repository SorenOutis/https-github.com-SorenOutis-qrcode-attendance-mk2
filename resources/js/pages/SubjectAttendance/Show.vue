<script setup lang="ts">
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { store, update, destroy } from '@/routes/students';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    BarElement,
    Title,
    Tooltip,
    Legend,
    ArcElement,
} from 'chart.js';
import { gsap } from 'gsap';
import {
    ArrowLeft,
    BookOpen,
    TrendingUp,
    Users,
    Clock,
    Plus,
    Pencil,
    Trash2,
    X,
    ArrowRightLeft,
    ChevronDown,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { Line, Pie } from 'vue-chartjs';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { useToast } from '@/composables/useToast';
import AppLayout from '@/layouts/AppLayout.vue';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, BarElement, Title, Tooltip, Legend, ArcElement);

type StudentData = {
    id: number;
    name: string;
    student_number: string;
    email: string | null;
    section: string | null;
    schedule: any[] | null;
    photo: string | null;
    total_records: number;
    attendance_rate: number;
    present: number;
    late: number;
    absent: number;
    excused: number;
};

type PaginatedStudents = {
    data: StudentData[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
};

const props = defineProps<{
    subject: {
        id: number;
        name: string;
        icon: string | null;
        color: string | null;
        description: string | null;
        schedule: any[] | null;
    };
    daily: { date: string; count: number }[];
    statusDistribution: { status: string; count: number }[];
    allStudents: { id: number; name: string; student_number: string; section: string | null }[];
    students: PaginatedStudents;
    otherSubjects: { id: number; name: string }[];
    enrolled: number;
    filters: { start: string; end: string };
}>();

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Subject Attendance', href: '/subject-attendance' },
    { title: props.subject.name, href: `/subject-attendance/${props.subject.id}` },
];

const lineData = computed(() => ({
    labels: props.daily.map((d) => d.date),
    datasets: [
        {
            label: 'Daily Scans',
            backgroundColor: '#18181b',
            borderColor: '#18181b',
            data: props.daily.map((d) => d.count),
            tension: 0.4,
            fill: false,
        },
    ],
}));

const pieData = computed(() => ({
    labels: props.statusDistribution.map((s) => s.status),
    datasets: [
        {
            backgroundColor: ['#09090b', '#3f3f46', '#a1a1aa', '#e4e4e7'],
            data: props.statusDistribution.map((s) => s.count),
        },
    ],
}));

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'bottom' as const,
            labels: { boxWidth: 12, padding: 15, font: { family: 'ui-sans-serif, system-ui, sans-serif', size: 11 } },
        },
    },
    layout: { padding: { top: 20 } },
};

const overallRate = computed(() => {
    const total = props.statusDistribution.reduce((sum, s) => sum + s.count, 0);
    const positive = props.statusDistribution
        .filter((s) => ['Present', 'present', 'Late', 'late', 'Excused', 'excused'].includes(s.status))
        .reduce((sum, s) => sum + s.count, 0);
    return total > 0 ? Math.round((positive / total) * 100) : 0;
});

const studentRows = computed(() => props.students.data ?? []);
const displayLimit = ref(25);
const displayedStudents = computed(() => studentRows.value.slice(0, displayLimit.value));

const studentRankStart = computed(() => props.students.from ?? ((props.students.current_page - 1) * props.students.per_page + 1));

function showMore() {
    const list = document.querySelector('.student-list-container');
    if (list) {
        // Record current height
        const startHeight = list.clientHeight;
        displayLimit.value = studentRows.value.length;
        
        // Next tick wait for DOM update
        setTimeout(() => {
            const endHeight = list.clientHeight;
            // Immediate reset then animate
            gsap.fromTo(list, 
                { height: startHeight }, 
                { height: 'auto', duration: 0.6, ease: 'expo.out', clearProps: 'height' }
            );
            
            // Animate new items
            const currentItems = list.querySelectorAll('.student-row');
            const newItems = Array.from(currentItems).slice(25);
            if (newItems.length) {
                gsap.from(newItems, {
                    opacity: 0,
                    y: 20,
                    stagger: 0.02,
                    duration: 0.4,
                    ease: 'power2.out'
                });
            }
        }, 0);
    } else {
        displayLimit.value = studentRows.value.length;
    }
}

const { success, error } = useToast();

const showModal = ref(false);
const isEditing = ref(false);
const editingStudent = ref<StudentData | null>(null);

// Bulk Move States
const selectedIds = ref<number[]>([]);
const showMoveModal = ref(false);
const targetSubjectId = ref<string>('');

const moveForm = useForm({
    student_ids: [] as number[],
    to_subject_id: '',
});

function toggleAll() {
    if (selectedIds.value.length === props.students.data.length) {
        selectedIds.value = [];
    } else {
        selectedIds.value = props.students.data.map(s => s.id);
    }
}

function toggleStudentSelection(id: number) {
    const index = selectedIds.value.indexOf(id);
    if (index === -1) {
        selectedIds.value.push(id);
    } else {
        selectedIds.value.splice(index, 1);
    }
}

function openMoveModal(studentId?: number) {
    moveForm.reset();
    if (studentId) {
        moveForm.student_ids = [studentId];
    } else {
        moveForm.student_ids = [...selectedIds.value];
    }
    showMoveModal.value = true;
}

function submitMove() {
    if (!moveForm.to_subject_id) return;
    
    moveForm.post(`/subject-attendance/${props.subject.id}/move-students`, {
        onSuccess: () => {
            success('Students moved successfully');
            showMoveModal.value = false;
            selectedIds.value = [];
        },
        onError: () => error('Failed to move students'),
    });
}

function bulkRemove() {
    if (!confirm(`Are you sure you want to remove ${selectedIds.value.length} students from this subject?`)) return;

    router.post(`/subject-attendance/${props.subject.id}/remove-students`, {
        student_ids: selectedIds.value
    }, {
        onSuccess: () => {
            success('Students removed successfully');
            selectedIds.value = [];
        },
        onError: () => error('Failed to remove students'),
    });
}

function clearSelection() {
    selectedIds.value = [];
}

const form = useForm({
    name: '',
    student_number: '',
    email: '',
    section: '',
    schedule: [] as any[],
});

function resetForm() {
    form.reset();
    form.clearErrors();
    isEditing.value = false;
    editingStudent.value = null;
}

function openAddModal() {
    resetForm();
    // Default schedule to current subject if possible
    if (props.subject.schedule && props.subject.schedule.length > 0) {
        form.schedule = props.subject.schedule.map(slot => ({
            day: slot.day,
            subject_id: props.subject.id,
            start: slot.start,
            end: slot.end,
        }));
    }
    showModal.value = true;
}

function openEditModal(student: StudentData) {
    resetForm();
    isEditing.value = true;
    editingStudent.value = student;
    form.name = student.name;
    form.student_number = student.student_number;
    form.email = student.email ?? '';
    form.section = student.section ?? '';
    form.schedule = Array.isArray(student.schedule) ? JSON.parse(JSON.stringify(student.schedule)) : [];
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
    resetForm();
}

function submit() {
    form.transform((data) => {
        let schedule = Array.isArray(data.schedule) ? [...data.schedule] : [];
        
        // Ensure the current subject is in the schedule when adding/enrolling or editing on this page
        // We want the student to effectively be "enrolled" in the subject they are being managed through
        const hasSubject = schedule.some(slot => slot.subject_id === props.subject.id);
        if (!hasSubject) {
            schedule.push({
                day: 'Monday',
                subject_id: props.subject.id,
                start: '08:00',
                end: '09:00'
            });
        }

        // Sanitize schedule: Filter out items missing required fields to satisfy backend validation
        const sanitizedSchedule = schedule.filter(slot => 
            slot && 
            slot.day && 
            slot.subject_id && 
            slot.start && 
            slot.end
        );

        return {
            ...data,
            schedule: sanitizedSchedule.length > 0 ? sanitizedSchedule : null,
        };
    });

    if (isEditing.value && editingStudent.value) {
        form.put(update({ student: editingStudent.value.id }).url, {
            onSuccess: () => {
                success('Student record updated and enrolled');
                closeModal();
            },
            onError: () => error('Failed to update student. Please check the schedule in the Students page if errors persist.'),
        });
    } else {
        form.post(store().url, {
            onSuccess: () => {
                success('Student added successfully');
                closeModal();
            },
            onError: () => error('Failed to add student'),
        });
    }
}

function selectStudent(studentId: any) {
    if (!studentId) return;
    const student = props.allStudents.find(s => s.id.toString() === studentId.toString());
    if (!student) return;

    // When an existing student is selected, we switch to "Edit" (Update) mode
    // but we use it as an "Enrollment" flow.
    isEditing.value = true;
    editingStudent.value = student as any;

    form.name = student.name;
    form.student_number = student.student_number;
    form.section = student.section ?? '';
    form.email = (student as any).email ?? ''; 
    form.schedule = Array.isArray((student as any).schedule) ? JSON.parse(JSON.stringify((student as any).schedule)) : [];
    
    success(`Selected ${student.name}. Click Complete Onboarding to enroll them.`);
}

function deleteStudent(student: StudentData) {
    if (confirm(`Are you sure you want to remove ${student.name}?`)) {
        useForm({}).delete(destroy({ student: student.id }).url, {
            onSuccess: () => success('Student removed successfully'),
            onError: () => error('Failed to remove student'),
        });
    }
}

function rateColor(rate: number): string {
    if (rate >= 90) return 'text-zinc-900 dark:text-white';
    if (rate >= 75) return 'text-zinc-500 dark:text-zinc-400';
    return 'text-rose-500 dark:text-rose-400';
}

function rateBg(rate: number): string {
    if (rate >= 90) return 'bg-zinc-900 dark:bg-white';
    if (rate >= 75) return 'bg-zinc-500 dark:bg-zinc-400';
    return 'bg-rose-500 dark:bg-rose-400';
}

onMounted(() => {
    gsap.from('.subject-detail-card', {
        opacity: 0,
        y: 20,
        stagger: 0.08,
        duration: 0.5,
        ease: 'power2.out',
    });
});
</script>

<template>
    <Head :title="`${subject.name} - Subject Attendance`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 sm:gap-6 p-3 sm:p-6 lg:p-8 pb-20 md:pb-6 w-full overflow-x-hidden animate-in fade-in slide-in-from-bottom-4 duration-700">
            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 sm:gap-6 px-1">
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <Link href="/subject-attendance" class="flex items-center gap-1 text-[10px] font-black uppercase tracking-widest text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 transition-colors">
                            <ArrowLeft class="h-3.5 w-3.5" />
                            Back
                        </Link>
                    </div>
                    <p class="text-[10px] font-black uppercase tracking-[0.25em] text-zinc-400 dark:text-zinc-500 mb-1 leading-none">
                        Subject Deep Dive
                    </p>
                    <h1 class="text-xl sm:text-4xl lg:text-5xl font-serif font-black tracking-tight text-zinc-900 dark:text-white leading-tight">
                        {{ subject.name }}
                    </h1>
                </div>
                <div class="flex items-center gap-3">
                    <button
                        @click="openAddModal"
                        class="flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2 text-[10px] font-black uppercase tracking-widest text-white transition-all hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200 shadow-lg shadow-zinc-200/50 dark:shadow-none active:scale-95"
                    >
                        <Plus class="h-3.5 w-3.5" />
                        Add Student
                    </button>
                    <div class="h-10 w-px bg-zinc-100 dark:bg-zinc-800 mx-1 hidden sm:block"></div>
                    <div class="text-right">
                        <div class="text-3xl sm:text-4xl font-serif font-black" :class="rateColor(overallRate)">
                            {{ overallRate }}<span class="text-xl opacity-50">%</span>
                        </div>
                        <div class="text-[9px] font-bold text-zinc-400 uppercase tracking-widest">overall rate</div>
                    </div>
                </div>
            </div>

            <!-- Summary Stats -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
                <div v-for="(stat, i) in [
                    { label: 'Enrolled', value: enrolled, icon: Users },
                    { label: 'Total Records', value: statusDistribution.reduce((s, x) => s + x.count, 0), icon: Clock },
                    { label: 'Present', value: statusDistribution.filter(s => ['Present','present'].includes(s.status)).reduce((s,x) => s + x.count, 0), icon: TrendingUp },
                    { label: 'Absent', value: statusDistribution.filter(s => ['Absent','absent'].includes(s.status)).reduce((s,x) => s + x.count, 0), icon: BookOpen },
                ]" :key="i" class="group relative overflow-hidden rounded-[20px] p-4 sm:p-5 transition-all duration-300 hover:-translate-y-1 bg-white/60 dark:bg-zinc-950/60 backdrop-blur-xl border border-zinc-200/50 dark:border-zinc-800/50 text-zinc-900 dark:text-white shadow-sm hover:shadow-md text-center">
                    <div class="h-8 w-8 sm:h-10 sm:w-10 rounded-xl bg-white dark:bg-zinc-900 flex items-center justify-center mx-auto mb-2 sm:mb-3 border border-zinc-200/50 dark:border-zinc-800/50 shadow-sm">
                        <component :is="stat.icon" class="h-4 w-4 sm:h-5 sm:w-5 text-zinc-400 dark:text-zinc-500" />
                    </div>
                    <div class="text-xl sm:text-2xl font-serif font-black">{{ stat.value }}</div>
                    <div class="text-[8px] sm:text-[9px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-widest mt-1">{{ stat.label }}</div>
                </div>
            </div>
            
            <!-- Student Leaderboard -->
            <div data-card class="subject-detail-card relative overflow-hidden rounded-[20px] p-4 sm:p-6 bg-white/60 dark:bg-zinc-950/60 backdrop-blur-xl border border-zinc-200/50 dark:border-zinc-800/50 text-zinc-900 dark:text-white shadow-sm transition-all duration-300 hover:shadow-xl hover:-translate-y-1 hover:border-zinc-300 dark:hover:border-zinc-700 isolate">
                <div class="absolute -right-12 -top-12 h-40 w-40 rounded-full bg-zinc-900/5 dark:bg-white/5 blur-3xl -z-10 transition-opacity"></div>
                <div class="mb-4 sm:mb-6 flex items-center gap-2 sm:gap-3">
                    <div class="h-8 w-8 sm:h-10 sm:w-10 rounded-xl bg-white dark:bg-zinc-900 flex items-center justify-center border border-zinc-200/50 dark:border-zinc-800/50 shadow-sm">
                        <Users class="h-4 w-4 sm:h-5 sm:w-5 text-zinc-400 dark:text-zinc-500" />
                    </div>
                    <div>
                        <h3 class="font-serif font-black text-base sm:text-xl tracking-tight leading-none mb-0.5 sm:mb-1">Student Leaderboard</h3>
                        <p class="text-[9px] sm:text-[10px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-widest leading-none">Sorted by Attendance Rate</p>
                    </div>
                </div>

                <div v-if="studentRows.length" class="space-y-2">
                    <!-- Bulk Select Header -->
                    <div class="flex items-center gap-2 sm:gap-4 px-2 sm:px-4 mb-2">
                        <input 
                            type="checkbox" 
                            :checked="selectedIds.length === displayedStudents.length && displayedStudents.length > 0"
                            @change="toggleAll"
                            class="h-4 w-4 rounded border-zinc-300 dark:border-zinc-700 text-zinc-900 focus:ring-zinc-900 transition-all cursor-pointer"
                        />
                        <span class="text-[10px] font-black uppercase tracking-widest text-zinc-400">Select All Visible Students</span>
                    </div>

                    <div class="student-list-container space-y-2 overflow-hidden">
                        <template v-for="(student, idx) in displayedStudents" :key="student.id">
                            <div
                                class="student-row flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4 rounded-[1.5rem] border border-zinc-200/50 dark:border-zinc-800/60 bg-white/80 dark:bg-zinc-900/80 p-3 sm:px-4 sm:py-3 hover:bg-zinc-50 dark:hover:bg-zinc-900 transition-all shadow-sm hover:shadow-md group w-full"
                            >
                                <!-- Top Group: Selection + Identity -->
                                <div class="flex items-center gap-3 sm:gap-4 min-w-0">
                                    <input 
                                        type="checkbox" 
                                        :checked="selectedIds.includes(student.id)"
                                        @change="toggleStudentSelection(student.id)"
                                        class="h-4 w-4 rounded border-zinc-300 dark:border-zinc-700 text-zinc-900 focus:ring-zinc-900 cursor-pointer shrink-0"
                                    />
                                    <div class="flex items-center justify-center shrink-0 h-6 w-6 rounded-md bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-[10px] font-black text-zinc-500">
                                        {{ studentRankStart + idx }}
                                    </div>
                                    <Link :href="`/students/${student.id}/analytics`" class="flex items-center gap-3 min-w-0 flex-1 group/info">
                                        <div class="h-10 w-10 sm:h-9 sm:w-9 rounded-xl bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 overflow-hidden shrink-0">
                                            <img v-if="student.photo" :src="student.photo" class="h-full w-full object-cover" :alt="student.name" />
                                            <div v-else class="h-full w-full flex items-center justify-center text-xs font-bold text-zinc-500 dark:text-zinc-400">
                                                {{ student.name.charAt(0) }}
                                            </div>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="text-sm font-bold truncate text-zinc-900 dark:text-white group-hover/info:text-zinc-600 dark:group-hover/info:text-zinc-300 transition-colors">{{ student.name }}</div>
                                            <div class="hidden sm:block text-[9px] text-zinc-500 dark:text-zinc-400 font-bold uppercase tracking-widest mt-0.5 whitespace-nowrap overflow-hidden text-ellipsis">
                                                {{ student.student_number }} <span v-if="student.section">• {{ student.section }}</span>
                                            </div>
                                        </div>
                                    </Link>
                                </div>

                                <!-- Mobile Sub-info (ID/Section) -->
                                <div class="sm:hidden flex items-center px-12 -mt-1">
                                    <div class="text-[10px] text-zinc-500 dark:text-zinc-400 font-bold uppercase tracking-[0.15em] leading-none">
                                        {{ student.student_number }} <span class="mx-1.5 opacity-30">•</span> {{ student.section || 'No Section' }}
                                    </div>
                                </div>

                                <!-- Stats & Actions Group -->
                                <div class="flex items-center justify-between sm:justify-end gap-2 sm:gap-6 mt-1 sm:mt-0 px-2 sm:px-0 sm:flex-1">
                                    <!-- Attendance Rate -->
                                    <div class="flex flex-col items-start sm:items-end min-w-[72px]">
                                        <div class="text-sm sm:text-base font-black leading-none" :class="rateColor(student.attendance_rate)">
                                            {{ student.attendance_rate }}%
                                        </div>
                                        <div class="text-[8px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-widest mt-1.5 whitespace-nowrap">
                                            {{ student.total_records }} Records
                                        </div>
                                    </div>

                                    <!-- Progress Bar (Desktop Only) -->
                                    <div class="hidden md:block w-24">
                                        <div class="w-full h-1.5 rounded-full bg-zinc-100 dark:bg-zinc-900 overflow-hidden mix-blend-multiply dark:mix-blend-lighten opacity-80 group-hover:opacity-100 transition-opacity">
                                            <div class="h-full rounded-full transition-all duration-700" :class="rateBg(student.attendance_rate)" :style="{ width: `${student.attendance_rate}%` }"></div>
                                        </div>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="flex items-center gap-1 sm:gap-2">
                                        <button 
                                            @click="openEditModal(student)" 
                                            class="p-2 sm:p-2.5 rounded-xl border border-transparent hover:border-zinc-200 dark:hover:border-zinc-800 hover:bg-white dark:hover:bg-zinc-900 text-zinc-400 hover:text-zinc-900 dark:hover:text-white transition-all active:scale-90"
                                            title="Edit Student"
                                        >
                                            <Pencil class="h-4 w-4 sm:h-3.5 sm:w-3.5" />
                                        </button>
                                        <button 
                                            @click="openMoveModal(student.id)" 
                                            class="p-2 sm:p-2.5 rounded-xl border border-transparent hover:border-zinc-200 dark:hover:border-zinc-800 hover:bg-white dark:hover:bg-zinc-900 text-zinc-400 hover:text-zinc-900 dark:hover:text-white transition-all active:scale-90"
                                            title="Move Student"
                                        >
                                            <ArrowRightLeft class="h-4 w-4 sm:h-3.5 sm:w-3.5" />
                                        </button>
                                        <button 
                                            @click="() => deleteStudent(student)" 
                                            class="p-2 sm:p-2.5 rounded-xl border border-transparent hover:border-rose-100 dark:hover:border-rose-900/40 hover:bg-rose-50 dark:hover:bg-rose-950/20 text-zinc-400 hover:text-rose-500 transition-all active:scale-90"
                                            title="Remove Student"
                                        >
                                            <Trash2 class="h-4 w-4 sm:h-3.5 sm:w-3.5" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Show More Button -->
                    <div v-if="studentRows.length > displayLimit" class="pt-4 flex justify-center">
                        <button 
                            @click="showMore"
                            class="group relative flex items-center gap-2 px-8 py-3 rounded-2xl bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 text-[10px] font-black uppercase tracking-[0.2em] transition-all hover:scale-[1.03] active:scale-[0.97] shadow-xl shadow-zinc-900/10 dark:shadow-none isolate"
                        >
                            <span class="relative z-10 flex items-center gap-2">
                                Show More Students
                                <ChevronDown class="h-3 w-3 group-hover:translate-y-0.5 transition-transform" />
                            </span>
                        </button>
                    </div>

                    <div
                        v-if="students.last_page > 1"
                        class="mt-4 flex flex-col gap-3 border-t border-zinc-100 pt-4 dark:border-zinc-800 sm:mt-6 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <p class="text-[10px] font-bold uppercase tracking-widest text-zinc-400">
                            Showing {{ students.from ?? 0 }}-{{ students.to ?? 0 }} of {{ students.total }} students
                        </p>

                        <div class="w-full sm:w-auto overflow-x-auto no-scrollbar">
                            <nav class="flex flex-wrap items-center gap-1">
                                <Link
                                    v-for="(link, index) in students.links"
                                    :key="`${link.label}-${index}`"
                                    :href="link.url ?? '#'"
                                    preserve-scroll
                                    class="rounded-xl px-3 py-1.5 text-xs font-bold transition-all"
                                    :class="[
                                        link.active
                                            ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900'
                                            : 'bg-zinc-50 text-zinc-500 hover:bg-zinc-100 dark:bg-zinc-900/40 dark:text-zinc-300 dark:hover:bg-zinc-900',
                                        !link.url ? 'pointer-events-none opacity-40' : '',
                                    ]"
                                    v-html="link.label"
                                />
                            </nav>
                        </div>
                    </div>
                </div>
                <div v-else class="py-8 text-center text-sm text-zinc-400">No students enrolled in this subject.</div>
            </div>

            <div class="grid gap-4 sm:gap-6 md:grid-cols-2 lg:grid-cols-3">
                <!-- Trend Chart -->
                <div class="subject-detail-card md:col-span-2 rounded-[20px] border border-zinc-200/50 dark:border-zinc-800/50 bg-white/60 dark:bg-zinc-950/60 backdrop-blur-xl p-4 sm:p-6 shadow-sm text-zinc-900 dark:text-white isolate relative overflow-hidden transition-all duration-300 hover:shadow-xl hover:-translate-y-1 hover:border-zinc-300 dark:hover:border-zinc-700">
                    <div class="absolute -right-12 -top-12 h-40 w-40 rounded-full bg-zinc-900/5 dark:bg-white/5 blur-3xl -z-10 transition-opacity group-hover:bg-zinc-900/10 dark:group-hover:bg-white/10"></div>
                    <div class="mb-3 sm:mb-6 flex items-center gap-2 sm:gap-3">
                        <div class="h-8 w-8 sm:h-10 sm:w-10 rounded-xl bg-white dark:bg-zinc-900 flex items-center justify-center border border-zinc-200/50 dark:border-zinc-800/50 shadow-sm">
                            <TrendingUp class="h-4 w-4 sm:h-5 sm:w-5 text-zinc-400 dark:text-zinc-500" />
                        </div>
                        <div>
                            <h3 class="font-serif font-black text-base sm:text-xl tracking-tight leading-none mb-0.5 sm:mb-1">Attendance Trend</h3>
                            <p class="text-[9px] sm:text-[10px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-widest leading-none">Over Time</p>
                        </div>
                    </div>
                    <div class="h-[180px] sm:h-[300px] w-full">
                        <Line v-if="daily.length" :data="lineData" :options="chartOptions" />
                        <div v-else class="h-full flex items-center justify-center text-sm font-bold text-zinc-400/60 dark:text-zinc-600/60 tracking-widest uppercase">No data in range</div>
                    </div>
                </div>

                <!-- Status Pie -->
                <div class="subject-detail-card rounded-[20px] border border-zinc-200/50 dark:border-zinc-800/50 bg-white/60 dark:bg-zinc-950/60 backdrop-blur-xl p-4 sm:p-6 shadow-sm text-zinc-900 dark:text-white isolate relative overflow-hidden transition-all duration-300 hover:shadow-xl hover:-translate-y-1 hover:border-zinc-300 dark:hover:border-zinc-700">
                    <div class="absolute -right-12 -top-12 h-40 w-40 rounded-full bg-zinc-900/5 dark:bg-white/5 blur-3xl -z-10 transition-opacity group-hover:bg-zinc-900/10 dark:group-hover:bg-white/10"></div>
                    <div class="mb-3 sm:mb-6 flex items-center gap-2 sm:gap-3">
                        <div class="h-8 w-8 sm:h-10 sm:w-10 rounded-xl bg-white dark:bg-zinc-900 flex items-center justify-center border border-zinc-200/50 dark:border-zinc-800/50 shadow-sm">
                            <Clock class="h-4 w-4 sm:h-5 sm:w-5 text-zinc-400 dark:text-zinc-500" />
                        </div>
                        <div>
                            <h3 class="font-serif font-black text-base sm:text-xl tracking-tight leading-none mb-0.5 sm:mb-1">Status Mix</h3>
                            <p class="text-[9px] sm:text-[10px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-widest leading-none">Distribution</p>
                        </div>
                    </div>
                    <div class="h-[180px] sm:h-[300px] w-full">
                        <Pie v-if="statusDistribution.length" :data="pieData" :options="chartOptions" />
                        <div v-else class="h-full flex items-center justify-center text-sm font-bold text-zinc-400/60 dark:text-zinc-600/60 tracking-widest uppercase">No data</div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>

    <!-- Student Modal -->
    <Dialog :open="showModal" @update:open="closeModal">
        <DialogContent class="sm:max-w-[425px] rounded-[2rem] border-zinc-100 dark:border-zinc-800 bg-white/80 dark:bg-black/80 backdrop-blur-2xl p-0 overflow-hidden shadow-2xl">
            <div class="absolute top-4 right-4 z-10">
                <button
                    @click="closeModal"
                    class="rounded-full p-2 text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>

            <DialogHeader class="p-8 pb-4 text-left">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-zinc-900 text-white dark:bg-white dark:text-zinc-900 shadow-xl">
                    <Users v-if="!isEditing" class="h-6 w-6" />
                    <Pencil v-else class="h-6 w-6" />
                </div>
                <DialogTitle class="text-2xl font-serif font-black leading-none tracking-tight text-zinc-900 dark:text-white">
                    {{ isEditing ? 'Edit Student' : 'Add Student' }}
                </DialogTitle>
                <DialogDescription class="mt-2 text-xs font-bold uppercase tracking-widest text-zinc-400">
                    {{ isEditing ? 'Update student information' : 'Create new or select existing student' }}
                </DialogDescription>

                <!-- Existing Student Selection -->
                <div v-if="!isEditing" class="mt-6 space-y-2">
                    <Label class="text-[10px] font-black uppercase tracking-widest text-zinc-400 px-1">Select Existing Student (Quick Add)</Label>
                    <Select @update:modelValue="selectStudent">
                        <SelectTrigger class="h-12 rounded-xl border-zinc-100 bg-zinc-50/50 px-4 text-sm font-bold dark:border-zinc-800 dark:bg-zinc-900/50 transition-all shadow-inner">
                            <SelectValue placeholder="Search existing students..." />
                        </SelectTrigger>
                        <SelectContent class="rounded-2xl border-zinc-100 dark:border-zinc-800 shadow-2xl">
                            <SelectItem v-for="student in props.allStudents" :key="student.id" :value="student.id.toString()" class="rounded-xl">
                                {{ student.name }} ({{ student.student_number }})
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <div class="flex items-center gap-2 py-2">
                        <div class="h-px bg-zinc-100 dark:bg-zinc-800 grow"></div>
                        <span class="text-[10px] font-bold text-zinc-300 uppercase tracking-widest">or create new</span>
                        <div class="h-px bg-zinc-100 dark:bg-zinc-800 grow"></div>
                    </div>
                </div>

                <!-- Global Error Alert -->
                <div v-if="Object.keys(form.errors).length > 0" class="mt-4 rounded-xl bg-rose-50 p-3 border border-rose-100 dark:bg-rose-900/20 dark:border-rose-900/30">
                    <div class="flex gap-2">
                        <X class="h-4 w-4 text-rose-500 shrink-0" />
                        <ul class="text-[10px] font-bold text-rose-600 dark:text-rose-400 list-disc list-inside">
                            <li v-for="(err, key) in form.errors" :key="key">{{ err }}</li>
                        </ul>
                    </div>
                </div>
            </DialogHeader>

            <form @submit.prevent="submit" class="space-y-4 p-8 pt-0">
                <div class="grid gap-4">
                    <div class="grid gap-2">
                        <Label for="name" class="text-[10px] font-black uppercase tracking-widest text-zinc-400 px-1">Full Name</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            placeholder="e.g. John Doe"
                            class="h-12 rounded-xl border-zinc-100 bg-zinc-50/50 px-4 text-sm font-bold placeholder:text-zinc-300 focus:border-zinc-900 focus:ring-0 dark:border-zinc-800 dark:bg-zinc-900/50 dark:focus:border-white transition-all shadow-inner"
                        />
                        <p v-if="form.errors.name" class="text-[10px] font-bold text-rose-500 px-1">{{ form.errors.name }}</p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="student_number" class="text-[10px] font-black uppercase tracking-widest text-zinc-400 px-1">Student Number</Label>
                        <Input
                            id="student_number"
                            v-model="form.student_number"
                            placeholder="e.g. 2024-0001"
                            class="h-12 rounded-xl border-zinc-100 bg-zinc-50/50 px-4 text-sm font-bold placeholder:text-zinc-300 focus:border-zinc-900 focus:ring-0 dark:border-zinc-800 dark:bg-zinc-900/50 dark:focus:border-white transition-all shadow-inner"
                        />
                        <p v-if="form.errors.student_number" class="text-[10px] font-bold text-rose-500 px-1">{{ form.errors.student_number }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="section" class="text-[10px] font-black uppercase tracking-widest text-zinc-400 px-1">Section</Label>
                            <Input
                                id="section"
                                v-model="form.section"
                                placeholder="e.g. BSCS-1A"
                                class="h-12 rounded-xl border-zinc-100 bg-zinc-50/50 px-4 text-sm font-bold placeholder:text-zinc-300 focus:border-zinc-900 focus:ring-0 dark:border-zinc-800 dark:bg-zinc-900/50 dark:focus:border-white transition-all shadow-inner"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="email" class="text-[10px] font-black uppercase tracking-widest text-zinc-400 px-1">Email (Optional)</Label>
                            <Input
                                id="email"
                                type="email"
                                v-model="form.email"
                                placeholder="john@example.com"
                                class="h-12 rounded-xl border-zinc-100 bg-zinc-50/50 px-4 text-sm font-bold placeholder:text-zinc-300 focus:border-zinc-900 focus:ring-0 dark:border-zinc-800 dark:bg-zinc-900/50 dark:focus:border-white transition-all shadow-inner"
                            />
                        </div>
                    </div>
                </div>

                <div class="pt-6">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full h-14 rounded-2xl bg-zinc-900 text-sm font-black uppercase tracking-[0.2em] text-white shadow-2xl transition-all hover:bg-zinc-800 disabled:opacity-50 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-100 active:scale-[0.98] flex items-center justify-center gap-2"
                    >
                        <span v-if="form.processing" class="h-4 w-4 animate-spin rounded-full border-2 border-white/20 border-t-white dark:border-black/20 dark:border-t-black"></span>
                        {{ isEditing ? 'Update Records' : 'Complete Onboarding' }}
                    </button>
                </div>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Move Student Modal -->
    <Dialog :open="showMoveModal" @update:open="showMoveModal = false">
        <DialogContent class="sm:max-w-[425px] rounded-[2rem] border-zinc-100 dark:border-zinc-800 bg-white/80 dark:bg-black/80 backdrop-blur-2xl p-0 overflow-hidden shadow-2xl">
            <div class="absolute top-4 right-4 z-10">
                <button
                    @click="showMoveModal = false"
                    class="rounded-full p-2 text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>

            <DialogHeader class="p-8 pb-4 text-left">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-zinc-900 text-white dark:bg-white dark:text-zinc-900 shadow-xl">
                    <ArrowRightLeft class="h-6 w-6" />
                </div>
                <DialogTitle class="text-2xl font-serif font-black leading-none tracking-tight text-zinc-900 dark:text-white">
                    Move Students
                </DialogTitle>
                <DialogDescription class="mt-2 text-xs font-bold uppercase tracking-widest text-zinc-400">
                    Rescheduling {{ moveForm.student_ids.length }} student(s) to another subject roster
                </DialogDescription>
            </DialogHeader>

            <div class="p-8 pt-0 space-y-6">
                <div class="space-y-4">
                    <div class="grid gap-2">
                        <Label class="text-[10px] font-black uppercase tracking-widest text-zinc-400 px-1">Target Subject</Label>
                        <Select v-model="moveForm.to_subject_id">
                            <SelectTrigger class="h-12 rounded-xl border-zinc-100 bg-zinc-50/50 px-4 text-sm font-bold dark:border-zinc-800 dark:bg-zinc-900/50 transition-all shadow-inner">
                                <SelectValue placeholder="Select target subject..." />
                            </SelectTrigger>
                            <SelectContent class="rounded-2xl border-zinc-100 dark:border-zinc-800 shadow-2xl">
                                <SelectItem 
                                    v-for="subj in props.otherSubjects" 
                                    :key="subj.id" 
                                    :value="subj.id.toString()" 
                                    class="rounded-xl"
                                >
                                    {{ subj.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="moveForm.errors.to_subject_id" class="text-[10px] font-bold text-rose-500 px-1">{{ moveForm.errors.to_subject_id }}</p>
                    </div>

                    <div class="rounded-xl bg-zinc-50 dark:bg-zinc-900/50 p-4 border border-zinc-100 dark:border-zinc-800/80">
                        <p class="text-[10px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-widest leading-relaxed">
                            <span class="text-zinc-900 dark:text-white">Note:</span> Moving students will update their schedule slots. They will no longer appear in the {{ subject.name }} leaderboard.
                        </p>
                    </div>
                </div>

                <div class="pt-2">
                    <button
                        type="button"
                        @click="submitMove"
                        :disabled="moveForm.processing || !moveForm.to_subject_id"
                        class="w-full h-14 rounded-2xl bg-zinc-900 text-sm font-black uppercase tracking-[0.2em] text-white shadow-2xl transition-all hover:bg-zinc-800 disabled:opacity-50 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-100 active:scale-[0.98] flex items-center justify-center gap-2"
                    >
                        <span v-if="moveForm.processing" class="h-4 w-4 animate-spin rounded-full border-2 border-white/20 border-t-white dark:border-black/20 dark:border-t-black"></span>
                        {{ moveForm.processing ? 'Moving...' : 'Move Students Now' }}
                    </button>
                    <button
                        type="button"
                        @click="showMoveModal = false"
                        class="w-full mt-2 h-10 text-[10px] font-black uppercase tracking-widest text-zinc-400 hover:text-zinc-600 transition-colors"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </DialogContent>
    </Dialog>

    <!-- Floating Bulk Action Bar -->
    <Transition
        @enter="(el, done) => gsap.fromTo(el, { y: 100, opacity: 0 }, { y: 0, opacity: 1, duration: 0.5, ease: 'power4.out', onComplete: done })"
        @leave="(el, done) => gsap.to(el, { y: 100, opacity: 0, duration: 0.3, ease: 'power4.in', onComplete: done })"
    >
        <div v-if="selectedIds.length > 0" class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[60] w-[90%] max-w-2xl px-4 py-4 rounded-[1.5rem] bg-zinc-900/90 dark:bg-zinc-50/90 text-white dark:text-zinc-900 backdrop-blur-2xl shadow-2xl border border-white/10 dark:border-black/5 flex items-center justify-between gap-4 overflow-hidden">
            <div class="flex items-center gap-4 shrink-0 px-2 sm:px-4 border-r border-white/10 dark:border-black/5">
                <div class="h-8 w-8 rounded-full bg-white/10 dark:bg-black/5 flex items-center justify-center font-serif font-black text-sm">
                    {{ selectedIds.length }}
                </div>
                <div>
                    <div class="text-[10px] font-black uppercase tracking-widest opacity-60">Students</div>
                    <div class="text-[9px] font-bold uppercase tracking-widest">Selected</div>
                </div>
            </div>

            <div class="flex items-center gap-2 sm:gap-4 overflow-x-auto no-scrollbar scroll-smooth">
                <button
                    @click="openMoveModal()"
                    class="flex items-center gap-2 px-3 sm:px-5 py-2 sm:py-2.5 rounded-xl bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white transition-all hover:scale-105 active:scale-95 hover:shadow-xl shadow-lg border border-white/20"
                >
                    <ArrowRightLeft class="h-4 w-4" />
                    <span class="text-[10px] sm:text-xs font-black uppercase tracking-widest">Move</span>
                </button>
                
                <button
                    @click="bulkRemove"
                    class="flex items-center gap-2 px-3 sm:px-5 py-2 sm:py-2.5 rounded-xl bg-rose-500/20 dark:bg-rose-500/10 text-rose-400 dark:text-rose-600 transition-all hover:bg-rose-500/30 active:scale-95 hover:scale-105 border border-rose-500/20"
                >
                    <Trash2 class="h-4 w-4" />
                    <span class="text-[10px] sm:text-xs font-black uppercase tracking-widest">Remove</span>
                </button>

                <div class="h-8 w-px bg-white/10 dark:bg-black/5 mx-1"></div>

                <button
                    @click="clearSelection"
                    class="flex items-center gap-2 px-3 sm:px-4 py-2 text-white/60 dark:text-zinc-500 transition-colors hover:text-white dark:hover:text-zinc-900"
                >
                    <X class="h-4 w-4" />
                    <span class="text-[10px] font-black uppercase tracking-widest">Clear</span>
                </button>
            </div>

            <!-- Accent Glow -->
            <div class="absolute -right-8 -top-8 h-24 w-24 bg-white/5 dark:bg-black/5 blur-3xl rounded-full pointer-events-none"></div>
        </div>
    </Transition>
</template>
