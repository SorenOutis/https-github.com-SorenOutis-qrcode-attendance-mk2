<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import gsap from 'gsap';
import { 
    ChevronLeft, 
    Save, 
    CheckCircle2, 
    Search, 
    Filter, 
    Users, 
    CheckCircle, 
    Clock, 
    XCircle, 
    Info,
    MoreHorizontal,
    CalendarDays,
    Download,
    Check,
    X
} from 'lucide-vue-next';
import { ref, computed, onMounted, nextTick, watch } from 'vue';
import { useToast } from '@/composables/useToast';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';

type Attendance = {
    id: number;
    status: string;
    is_manual: boolean;
    remarks: string | null;
    scanned_at: string;
};

type Student = {
    id: number;
    name: string;
    student_number: string;
    slot_start: string | null;
    slot_end: string | null;
    attendance: Attendance | null;
};

const props = defineProps<{
    subject: { id: number; name: string };
    date: string;
    students: Student[];
}>();

const savingStatus = ref<Record<number, boolean>>({});
const successStatus = ref<Record<number, boolean>>({});
const searchQuery = ref('');
const selectedDate = ref(props.date);
const toast = useToast();

const selectedStudents = ref<number[]>([]);
const isBulkSaving = ref(false);

const allSelected = computed(() => {
    return filteredStudents.value.length > 0 && selectedStudents.value.length === filteredStudents.value.length;
});

function toggleSelectAll() {
    if (allSelected.value) {
        selectedStudents.value = [];
    } else {
        selectedStudents.value = filteredStudents.value.map(s => s.id);
    }
}

function toggleStudentSelection(id: number) {
    const index = selectedStudents.value.indexOf(id);
    if (index === -1) {
        selectedStudents.value.push(id);
    } else {
        selectedStudents.value.splice(index, 1);
    }
}

watch(selectedDate, (newDate) => {
    if (newDate && newDate !== props.date) {
        router.get(`/manage-attendance/${props.subject.id}/${newDate}`);
    }
});
const statusFilter = ref('all');

const filteredStudents = computed(() => {
    return props.students.filter(student => {
        const matchesSearch = student.name.toLowerCase().includes(searchQuery.value.toLowerCase()) || 
                             student.student_number.toLowerCase().includes(searchQuery.value.toLowerCase());
        
        const status = student.attendance?.status?.toLowerCase() || 'unmarked';
        const matchesFilter = statusFilter.value === 'all' || 
                             (statusFilter.value === 'unmarked' && !student.attendance) ||
                             status === statusFilter.value.toLowerCase();
        
        return matchesSearch && matchesFilter;
    });
});

const stats = computed(() => {
    const total = props.students.length;
    const present = props.students.filter(s => s.attendance?.status?.toLowerCase() === 'present').length;
    const late = props.students.filter(s => s.attendance?.status?.toLowerCase() === 'late').length;
    const absent = props.students.filter(s => s.attendance?.status?.toLowerCase() === 'absent').length;
    const excused = props.students.filter(s => s.attendance?.status?.toLowerCase() === 'excused').length;
    const unmarked = total - (present + late + absent + excused);
    
    return { total, present, late, absent, excused, unmarked };
});

function goBack() {
    router.get('/manage-attendance');
}

function updateAttendance(student: Student, newStatus: string) {
    // If it's already this status and we have an attendance record, do nothing
    if (student.attendance?.status === newStatus) return;

    savingStatus.value[student.id] = true;

    // Optimistically update the UI
    if (!student.attendance) {
        student.attendance = {
            id: 0,
            status: newStatus,
            is_manual: true,
            remarks: null,
            scanned_at: new Date().toISOString()
        };
    } else {
        student.attendance.status = newStatus;
        student.attendance.is_manual = true;
    }

    // Fire API request
    window.fetch('/manage-attendance/toggle', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || ''
        },
        body: JSON.stringify({
            student_id: student.id,
            subject_id: props.subject.id,
            date: props.date,
            status: newStatus,
            slot_start: student.slot_start,
            slot_end: student.slot_end,
            remarks: student.attendance?.remarks || null
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            student.attendance = data.attendance;
            toast.success(`Marked ${student.name} as ${newStatus}`);
        }
    })
    .catch(err => {
        console.error('Failed to update attendance', err);
        toast.error(`Could not update attendance for ${student.name}`);
    })
    .finally(() => {
        savingStatus.value[student.id] = false;
    });
}

async function bulkUpdateAttendance(newStatus: string) {
    if (selectedStudents.value.length === 0) return;
    
    isBulkSaving.value = true;
    const total = selectedStudents.value.length;
    let successCount = 0;

    toast.info(`Updating ${total} student(s)...`);

    for (const studentId of selectedStudents.value) {
        const student = props.students.find(s => s.id === studentId);
        if (!student || student.attendance?.status === newStatus) continue;

        try {
            const res = await window.fetch('/manage-attendance/toggle', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || ''
                },
                body: JSON.stringify({
                    student_id: student.id,
                    subject_id: props.subject.id,
                    date: props.date,
                    status: newStatus,
                    slot_start: student.slot_start,
                    slot_end: student.slot_end,
                    remarks: student.attendance?.remarks || null
                })
            });
            const data = await res.json();
            if (data.success) {
                student.attendance = data.attendance;
                successCount++;
            }
        } catch (err) {
            console.error(`Failed to update ${student.id}`, err);
        }
    }

    isBulkSaving.value = false;
    selectedStudents.value = [];
    toast.success(`Successfully updated ${successCount} student(s) to ${newStatus}`);
}

const isMarkingAllAbsent = ref(false);
const editingRemarks = ref<Record<number, string>>({});

function updateRemarks(student: Student) {
    const remarks = editingRemarks.value[student.id];
    
    savingStatus.value[student.id] = true;
    
    window.fetch('/manage-attendance/toggle', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || ''
        },
        body: JSON.stringify({
            student_id: student.id,
            subject_id: props.subject.id,
            date: props.date,
            status: student.attendance?.status || 'Present', // Keep current status
            slot_start: student.slot_start,
            slot_end: student.slot_end,
            remarks: remarks
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            if (student.attendance) {
                student.attendance.remarks = remarks;
            }
            toast.success('Remark saved');
        }
    })
    .catch(err => {
        console.error('Failed to update remarks', err);
        toast.error('Failed to save remark');
    })
    .finally(() => {
        savingStatus.value[student.id] = false;
    });
}

function markAllAbsent() {
    const unscannedStudents = props.students.filter(s => !s.attendance);
    if (unscannedStudents.length === 0) {
        return;
    }

    if (!confirm(`Are you sure you want to mark ${unscannedStudents.length} remaining student(s) as Absent?`)) {
        return;
    }

    isMarkingAllAbsent.value = true;

    window.fetch('/manage-attendance/mark-all-absent', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || ''
        },
        body: JSON.stringify({
            subject_id: props.subject.id,
            date: props.date,
            students: unscannedStudents.map(s => ({
                id: s.id,
                slot_start: s.slot_start,
                slot_end: s.slot_end,
            }))
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            unscannedStudents.forEach(s => {
                s.attendance = {
                    id: 0,
                    status: 'Absent',
                    is_manual: true,
                    remarks: null,
                    scanned_at: new Date().toISOString()
                };
            });
            toast.success(`Marked ${unscannedStudents.length} as Absent`);
        }
    })
    .catch(err => {
        console.error('Failed to mark all as absent', err);
        toast.error('Operation failed');
    })
    .finally(() => {
        isMarkingAllAbsent.value = false;
    });
}

const cardsRef = ref<HTMLElement | null>(null);
const tableRef = ref<HTMLElement | null>(null);
const studentsTableBodyRef = ref<HTMLTableSectionElement | null>(null);

function animateStudents() {
    nextTick(() => {
        const targets = studentsTableBodyRef.value?.querySelectorAll('tr');

        if (!targets || targets.length === 0) return;

        gsap.killTweensOf(targets);
        
        gsap.fromTo(targets,
            { opacity: 0, x: -20, filter: 'blur(4px)' },
            { 
                opacity: 1, 
                x: 0, 
                filter: 'blur(0px)',
                duration: 0.5, 
                stagger: 0.03, 
                ease: 'power2.out',
                clearProps: 'all'
            }
        );
    });
}

watch([searchQuery, statusFilter], () => {
    animateStudents();
});

onMounted(() => {
    // 1. Entrance and Hover Animations for Stats Cards
    if (cardsRef.value) {
        const cards = cardsRef.value.querySelectorAll<HTMLElement>('[data-card]');
        
        gsap.set(cardsRef.value, { perspective: 1000 });
        gsap.set(cards, { opacity: 1, visibility: 'visible' });

        gsap.from(cards, {
            opacity: 0,
            y: 30,
            rotationX: -15,
            z: -20,
            duration: 0.8,
            stagger: 0.1,
            ease: 'power2.out',
            clearProps: 'all'
        });
        
        cards.forEach((card) => {
            gsap.set(card, { transformStyle: "preserve-3d" });

            card.addEventListener('mousemove', (e: MouseEvent) => {
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                
                const centerX = rect.width / 2;
                const centerY = rect.height / 2;
                
                const rotateX = ((y - centerY) / centerY) * -10;
                const rotateY = ((x - centerX) / centerX) * 10;
                
                gsap.to(card, {
                    rotationX: rotateX,
                    rotationY: rotateY,
                    scale: 1.05,
                    z: 30,
                    zIndex: 50,
                    boxShadow: '0 30px 40px -10px rgba(0, 0, 0, 0.3), 0 15px 15px -10px rgba(0, 0, 0, 0.1)',
                    duration: 0.4,
                    ease: 'power3.out'
                });
            });

            card.addEventListener('mouseleave', () => {
                gsap.to(card, {
                    rotationX: 0,
                    rotationY: 0,
                    scale: 1,
                    z: 0,
                    zIndex: 0,
                    boxShadow: '0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1)',
                    duration: 0.6,
                    ease: 'elastic.out(1, 0.3)'
                });
            });
        });
    }

    // 2. Table and Row Entrance
    if (tableRef.value) {
        gsap.set(tableRef.value, { opacity: 1, visibility: 'visible', perspective: 1000 });

        gsap.from(tableRef.value, {
            opacity: 0,
            y: 20,
            rotationX: 10,
            duration: 0.8,
            delay: 0.2,
            ease: 'power2.out',
            clearProps: 'opacity,transform'
        });
        
        const rows = tableRef.value.querySelectorAll('tbody tr');
        rows.forEach(row => gsap.set(row, { transformStyle: "preserve-3d" }));

        gsap.from(rows, {
            opacity: 0,
            x: -30,
            filter: 'blur(10px)',
            duration: 0.8,
            stagger: 0.04,
            delay: 0.3,
            ease: 'expo.out',
        });
    }

    // 3. Button Press Micro-interactions
    const buttons = document.querySelectorAll('button');
    buttons.forEach((btn) => {
        gsap.set(btn, { transformStyle: "preserve-3d" });
        btn.addEventListener('mousedown', () => {
            gsap.to(btn, { scale: 0.95, z: -10, duration: 0.1, ease: 'power1.out' });
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
    <AppLayout :breadcrumbs="[
        { title: 'Manage Attendance', href: '/manage-attendance' },
        { title: `${subject.name} - ${date}`, href: `/manage-attendance/${subject.id}/${date}` }
    ]">
        <Head :title="`Attendance: ${subject.name}`" />

        <div class="flex h-full flex-col gap-5 p-3 sm:p-6 lg:p-10 pb-20 md:pb-6 w-full overflow-x-hidden animate-in fade-in slide-in-from-bottom-4 duration-700">
            <!-- Header Section -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="space-y-1">
                    <div class="flex items-center gap-3">
                        <Button variant="ghost" size="icon" @click="goBack" class="-ml-2 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors rounded-full">
                            <ChevronLeft class="h-5 w-5" />
                        </Button>
                        <h1 class="text-2xl sm:text-3xl font-serif font-bold tracking-tight text-foreground">{{ subject.name }}</h1>
                    </div>

                    <!-- Date Picker -->
                    <div class="ml-10 inline-flex items-center gap-2 p-1.5 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-sm hover:shadow-md transition-all">
                        <div class="pl-2 pr-1">
                            <span class="text-[9px] font-black uppercase tracking-widest text-zinc-400 block -mb-0.5">Date</span>
                            <Input
                                id="show-date"
                                type="date"
                                v-model="selectedDate"
                                class="h-7 w-36 bg-transparent border-0 p-0 focus-visible:ring-0 font-bold text-xs text-zinc-900 dark:text-white"
                            />
                        </div>
                        <div class="h-8 w-8 rounded-lg bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-400 shrink-0">
                            <CalendarDays class="w-4 h-4" />
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3 self-start sm:self-auto">
                    <Button 
                        variant="outline"
                        as-child
                        class="h-10 px-4 sm:px-6 rounded-full font-bold text-zinc-600 border-zinc-200 hover:bg-zinc-50 dark:bg-zinc-900/50 dark:border-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-900 transition-all active:scale-95 shadow-sm text-sm"
                    >
                        <a :href="`/manage-attendance/${subject.id}/${date}/export`" target="_blank">
                            <Download class="w-4 h-4 mr-2" />
                            Export CSV
                        </a>
                    </Button>
                    <Button 
                        variant="outline"
                        class="h-10 px-4 sm:px-6 rounded-full font-bold text-zinc-900 border-zinc-200 hover:bg-zinc-50 hover:text-black hover:border-zinc-300 dark:bg-zinc-900/50 dark:border-zinc-800 dark:text-zinc-100 dark:hover:bg-zinc-900 transition-all active:scale-95 shadow-sm text-sm"
                        @click="markAllAbsent"
                        :disabled="isMarkingAllAbsent || students.every(s => s.attendance)"
                    >
                        <XCircle v-if="!isMarkingAllAbsent" class="w-4 h-4 mr-2" />
                        {{ isMarkingAllAbsent ? 'Marking...' : 'Mark Remaining Absent' }}
                    </Button>
                </div>
            </div>

            <!-- Stats Overview -->
            <div ref="cardsRef" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4">
                <!-- Total -->
                <div data-card class="group relative overflow-hidden rounded-2xl p-3 sm:p-5 transition-all duration-500 hover:shadow-lg hover:-translate-y-1 bg-white dark:bg-black border border-zinc-200 dark:border-zinc-800 text-zinc-900 dark:text-white shadow-md">
                    <div class="absolute -right-6 -top-6 h-32 w-32 rounded-full bg-zinc-100 dark:bg-zinc-900 blur-2xl transition-all duration-500 group-hover:bg-zinc-200 dark:group-hover:bg-zinc-800"></div>
                    <div class="absolute right-4 top-1/2 -translate-y-1/2 text-black/5 dark:text-white/5 transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-6 pointer-events-none z-0">
                        <Users class="h-12 w-12 sm:h-16 sm:w-16" />
                    </div>
                    <div class="relative z-10">
                        <p class="text-[9px] sm:text-[10px] font-bold uppercase tracking-[0.15em] sm:tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Total</p>
                        <p class="mt-1 text-3xl sm:text-4xl font-light tracking-tight text-zinc-900 dark:text-white drop-shadow-sm">{{ stats.total }}</p>
                    </div>
                </div>
                
                <!-- Present -->
                <div data-card class="group relative overflow-hidden rounded-2xl p-3 sm:p-5 transition-all duration-500 hover:shadow-lg hover:-translate-y-1 bg-white dark:bg-black border border-zinc-200 dark:border-zinc-800 text-zinc-900 dark:text-white shadow-md">
                    <div class="absolute -right-6 -top-6 h-32 w-32 rounded-full bg-zinc-900/5 dark:bg-zinc-100/5 blur-2xl transition-all duration-500 group-hover:bg-zinc-100 dark:group-hover:bg-zinc-900/30"></div>
                    <div class="absolute right-4 top-1/2 -translate-y-1/2 text-black/5 dark:text-white/5 transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-6 pointer-events-none z-0">
                        <CheckCircle class="h-12 w-12 sm:h-16 sm:w-16" />
                    </div>
                    <div class="relative z-10">
                        <p class="text-[9px] sm:text-[10px] font-bold uppercase tracking-[0.15em] sm:tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Present</p>
                        <p class="mt-1 text-3xl sm:text-4xl font-light tracking-tight text-zinc-900 dark:text-zinc-100 drop-shadow-sm">{{ stats.present }}</p>
                    </div>
                </div>

                <!-- Late -->
                <div data-card class="group relative overflow-hidden rounded-2xl p-3 sm:p-5 transition-all duration-500 hover:shadow-lg hover:-translate-y-1 bg-white dark:bg-black border border-zinc-200 dark:border-zinc-800 text-zinc-900 dark:text-white shadow-md">
                    <div class="absolute -right-6 -top-6 h-32 w-32 rounded-full bg-zinc-400/5 dark:bg-zinc-500/5 blur-2xl transition-all duration-500 group-hover:bg-zinc-100 dark:group-hover:bg-zinc-900/30"></div>
                    <div class="absolute right-4 top-1/2 -translate-y-1/2 text-black/5 dark:text-white/5 transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-6 pointer-events-none z-0">
                        <Clock class="h-12 w-12 sm:h-16 sm:w-16" />
                    </div>
                    <div class="relative z-10">
                        <p class="text-[9px] sm:text-[10px] font-bold uppercase tracking-[0.15em] sm:tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Late</p>
                        <p class="mt-1 text-3xl sm:text-4xl font-light tracking-tight text-zinc-700 dark:text-zinc-300 drop-shadow-sm">{{ stats.late }}</p>
                    </div>
                </div>

                <!-- Absent -->
                <div data-card class="group relative overflow-hidden rounded-2xl p-3 sm:p-5 transition-all duration-500 hover:shadow-lg hover:-translate-y-1 bg-white dark:bg-black border border-zinc-200 dark:border-zinc-800 text-zinc-900 dark:text-white shadow-md">
                    <div class="absolute -right-6 -top-6 h-32 w-32 rounded-full bg-zinc-200/5 dark:bg-zinc-800/5 blur-2xl transition-all duration-500 group-hover:bg-zinc-100 dark:group-hover:bg-zinc-900/30"></div>
                    <div class="absolute right-4 top-1/2 -translate-y-1/2 text-black/5 dark:text-white/5 transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-6 pointer-events-none z-0">
                        <XCircle class="h-12 w-12 sm:h-16 sm:w-16" />
                    </div>
                    <div class="relative z-10">
                        <p class="text-[9px] sm:text-[10px] font-bold uppercase tracking-[0.15em] sm:tracking-[0.2em] text-zinc-400 dark:text-zinc-500">Absent</p>
                        <p class="mt-1 text-3xl sm:text-4xl font-light tracking-tight text-zinc-400 dark:text-zinc-500 drop-shadow-sm">{{ stats.absent }}</p>
                    </div>
                </div>

                <!-- Excused -->
                <div data-card class="group relative overflow-hidden rounded-2xl p-3 sm:p-5 transition-all duration-500 hover:shadow-lg hover:-translate-y-1 bg-white dark:bg-black border border-zinc-200 dark:border-zinc-800 text-zinc-900 dark:text-white shadow-md">
                    <div class="absolute -right-6 -top-6 h-32 w-32 rounded-full bg-zinc-100 dark:bg-zinc-900 blur-2xl transition-all duration-500 group-hover:bg-zinc-200 dark:group-hover:bg-zinc-800"></div>
                    <div class="absolute right-4 top-1/2 -translate-y-1/2 text-black/5 dark:text-white/5 transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-6 pointer-events-none z-0">
                        <Info class="h-12 w-12 sm:h-16 sm:w-16" />
                    </div>
                    <div class="relative z-10">
                        <p class="text-[9px] sm:text-[10px] font-bold uppercase tracking-[0.15em] sm:tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Excused</p>
                        <p class="mt-1 text-3xl sm:text-4xl font-light tracking-tight text-zinc-900 dark:text-white drop-shadow-sm">{{ stats.excused }}</p>
                    </div>
                </div>
            </div>

            <!-- Toolbar: Search & Filters -->
            <div class="flex flex-col md:flex-row gap-4 items-center bg-zinc-50 dark:bg-zinc-900/50 p-4 rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-sm">
                <div class="relative w-full md:max-w-md">
                    <Search class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-zinc-500 dark:text-zinc-400" />
                    <Input 
                        v-model="searchQuery" 
                        placeholder="Search student name or number..." 
                        class="pl-10 h-10 rounded-full bg-white dark:bg-black border-zinc-200 dark:border-zinc-800 focus-visible:ring-zinc-400 dark:focus-visible:ring-zinc-600 shadow-sm"
                    />
                </div>
                
                <div class="flex items-center gap-1.5 bg-zinc-200/50 dark:bg-zinc-800/50 p-1 rounded-xl border border-zinc-200 dark:border-zinc-800 w-full md:w-auto overflow-x-auto no-scrollbar">
                    <button 
                        @click="statusFilter = 'all'"
                        :class="['h-9 px-4 rounded-lg text-xs font-semibold transition-all shrink-0', statusFilter === 'all' ? 'bg-white dark:bg-zinc-700 text-zinc-900 dark:text-white shadow-sm' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white']"
                    >
                        All Students
                    </button>
                    <button 
                        @click="statusFilter = 'unmarked'"
                        :class="['h-9 px-4 rounded-lg text-xs font-semibold transition-all shrink-0', statusFilter === 'unmarked' ? 'bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900 shadow-sm' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white']"
                    >
                        Unmarked ({{ stats.unmarked }})
                    </button>
                    <button 
                        @click="statusFilter = 'present'"
                        :class="['h-9 px-4 rounded-lg text-xs font-semibold transition-all shrink-0', statusFilter === 'present' ? 'bg-zinc-900 text-white shadow-sm dark:bg-zinc-100 dark:text-zinc-900' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white']"
                    >
                        Present
                    </button>
                    <button 
                        @click="statusFilter = 'late'"
                        :class="['h-9 px-4 rounded-lg text-xs font-semibold transition-all shrink-0', statusFilter === 'late' ? 'bg-zinc-500 text-white shadow-sm' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white']"
                    >
                        Late
                    </button>
                    <button 
                        @click="statusFilter = 'absent'"
                        :class="['h-9 px-4 rounded-lg text-xs font-semibold transition-all shrink-0', statusFilter === 'absent' ? 'bg-zinc-100 text-zinc-600 shadow-sm border border-zinc-200 dark:bg-zinc-800 dark:text-zinc-400' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white']"
                    >
                        Absent
                    </button>
                </div>
            </div>

            <!-- Content Area -->
            <div class="space-y-4">
                <!-- Desktop Table View -->
                <div ref="tableRef" class="hidden md:block rounded-2xl border bg-white dark:bg-black shadow-xl overflow-hidden border-zinc-200 dark:border-zinc-800">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="sticky top-0 z-10 text-[10px] text-zinc-500 dark:text-zinc-400 uppercase font-semibold bg-zinc-50/95 dark:bg-zinc-900/95 backdrop-blur-md border-b border-zinc-200 dark:border-zinc-800 tracking-wider">
                                <tr>
                                    <th class="px-6 py-4 w-10">
                                        <input 
                                            type="checkbox" 
                                            :checked="allSelected" 
                                            @change="toggleSelectAll"
                                            class="rounded border-zinc-300 text-zinc-900 focus:ring-zinc-900 dark:bg-zinc-900 dark:border-zinc-700 h-4 w-4"
                                        />
                                    </th>
                                    <th class="px-6 py-4">Student Information</th>
                                    <th class="px-6 py-4">Shift / Schedule</th>
                                    <th class="px-6 py-4 border-l border-zinc-200 dark:border-zinc-800/50">Entry Source</th>
                                    <th class="px-8 py-4 text-right w-[350px]">Attendance Status</th>
                                </tr>
                            </thead>
                            <tbody ref="studentsTableBodyRef" class="divide-y divide-zinc-100 dark:divide-zinc-900">
                                <tr v-for="student in filteredStudents" :key="student.id" 
                                    class="group transition-all hover:bg-zinc-50 dark:hover:bg-zinc-900/50"
                                    :class="{ 'bg-zinc-50 dark:bg-zinc-900/50': selectedStudents.includes(student.id) }"
                                >
                                    <td class="px-6 py-5">
                                        <input 
                                            type="checkbox" 
                                            :value="student.id" 
                                            v-model="selectedStudents"
                                            class="rounded border-zinc-300 text-zinc-900 focus:ring-zinc-900 dark:bg-zinc-900 dark:border-zinc-700 h-4 w-4"
                                            @click.stop
                                        />
                                    </td>
                                    <td class="px-6 py-5" @click="toggleStudentSelection(student.id)">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-full bg-muted flex items-center justify-center font-bold text-muted-foreground group-hover:bg-primary group-hover:text-primary-foreground transition-colors shrink-0">
                                                {{ student.name.charAt(0) }}
                                            </div>
                                            <div>
                                                <div class="font-bold text-foreground group-hover:text-primary transition-colors cursor-pointer">{{ student.name }}</div>
                                                <div class="text-xs text-muted-foreground tracking-tight">{{ student.student_number }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-5">
                                        <div class="flex items-center gap-2 font-medium text-muted-foreground">
                                            <Clock class="w-3.5 h-3.5" />
                                            <span>{{ student.slot_start }} - {{ student.slot_end }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-5 border-l border-zinc-200 dark:border-zinc-800/50">
                                        <div v-if="student.attendance?.scanned_at && !student.attendance?.is_manual" class="flex items-center gap-2">
                                            <span class="inline-flex items-center gap-1.5 text-[10px] font-bold text-zinc-700 bg-zinc-100 dark:bg-zinc-800 dark:text-zinc-300 px-2 py-1 rounded-full border border-zinc-200 dark:border-zinc-700">
                                                QR Scan @ {{ new Date(student.attendance.scanned_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                                            </span>
                                        </div>
                                        <div v-else-if="student.attendance?.is_manual" class="flex items-center gap-2">
                                            <span class="inline-flex items-center gap-1.5 text-[10px] font-bold text-zinc-600 dark:text-zinc-400 bg-zinc-100 dark:bg-zinc-800 px-2 py-1 rounded-full border border-zinc-200 dark:border-zinc-700">
                                                Manual Entry
                                            </span>
                                        </div>
                                        <div v-else class="text-zinc-400 dark:text-zinc-600 italic text-[10px] font-medium ml-2">Waiting for scan...</div>
                                    </td>
                                    <td class="px-8 py-5">
                                        <div class="flex items-center justify-end gap-3">
                                            <div v-show="savingStatus[student.id]" class="flex items-center gap-1.5 text-[9px] font-bold uppercase text-zinc-500 animate-pulse mr-2">
                                                <Save class="w-2.5 h-2.5" /> Updating
                                            </div>
                                            
                                            <div class="flex p-1 bg-zinc-100 dark:bg-zinc-900 rounded-xl gap-1 border border-zinc-200 dark:border-zinc-800 shadow-inner">
                                                <button 
                                                    @click="updateAttendance(student, 'Present')"
                                                    :class="['w-9 h-8 flex items-center justify-center rounded-lg text-xs font-bold transition-all', student.attendance?.status?.toLowerCase() === 'present' ? 'bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900 shadow-sm ring-1 ring-zinc-200 dark:ring-zinc-600' : 'text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200']"
                                                >
                                                    P
                                                </button>
                                                <button 
                                                    @click="updateAttendance(student, 'Late')"
                                                    :class="['w-9 h-8 flex items-center justify-center rounded-lg text-xs font-bold transition-all', student.attendance?.status?.toLowerCase() === 'late' ? 'bg-zinc-500 text-white shadow-sm ring-1 ring-zinc-200 dark:ring-zinc-600' : 'text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200']"
                                                >
                                                    L
                                                </button>
                                                <button 
                                                    @click="updateAttendance(student, 'Absent')"
                                                    :class="['w-9 h-8 flex items-center justify-center rounded-lg text-xs font-bold transition-all', student.attendance?.status?.toLowerCase() === 'absent' ? 'bg-white dark:bg-zinc-800 text-zinc-500 shadow-sm ring-1 ring-zinc-200 dark:ring-zinc-600 border border-zinc-200 dark:border-zinc-700' : 'text-zinc-400 hover:text-zinc-500']"
                                                >
                                                    A
                                                </button>
                                                
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger asChild>
                                                        <button class="w-8 h-8 flex items-center justify-center rounded-lg text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200 hover:bg-white dark:hover:bg-zinc-700 transition-all">
                                                            <MoreHorizontal class="w-4 h-4" />
                                                        </button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end" class="w-56 rounded-xl shadow-2xl border-zinc-200 dark:border-zinc-800">
                                                        <DropdownMenuLabel class="text-xs font-bold uppercase tracking-wider text-zinc-500">Other Actions</DropdownMenuLabel>
                                                        <DropdownMenuSeparator />
                                                        <DropdownMenuItem @click="updateAttendance(student, 'Excused')" class="text-xs font-medium focus:bg-zinc-100 dark:focus:bg-zinc-800">
                                                            <Info class="w-3.5 h-3.5 mr-2 text-zinc-400" /> Mark as Excused
                                                        </DropdownMenuItem>
                                                        <DropdownMenuSeparator />
                                                        <div class="p-3 space-y-2">
                                                            <p class="text-[10px] font-bold uppercase tracking-wider text-zinc-400">Remarks</p>
                                                            <Input 
                                                                v-model="editingRemarks[student.id]" 
                                                                placeholder="Add a remark..." 
                                                                class="h-9 text-xs rounded-lg border-zinc-200 dark:border-zinc-800"
                                                                @keydown.enter="updateRemarks(student)"
                                                                @blur="student.attendance?.remarks !== editingRemarks[student.id] && updateRemarks(student)"
                                                            />
                                                        </div>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="filteredStudents.length === 0">
                                    <td colspan="5" class="px-8 py-20 text-center">
                                        <div class="flex flex-col items-center gap-5">
                                            <div class="relative">
                                                <div class="w-24 h-24 rounded-full bg-zinc-50 dark:bg-zinc-900 flex items-center justify-center">
                                                    <Search class="w-10 h-10 text-zinc-200 dark:text-zinc-800" stroke-width="1.5" />
                                                </div>
                                                <div class="absolute -bottom-2 -right-2 w-10 h-10 rounded-full bg-white dark:bg-black border border-zinc-100 dark:border-zinc-800 flex items-center justify-center shadow-lg">
                                                    <Filter class="w-4 h-4 text-zinc-400" />
                                                </div>
                                            </div>
                                            <div class="space-y-1">
                                                <h3 class="text-lg font-bold text-foreground">No students found</h3>
                                                <p class="text-sm text-muted-foreground max-w-[250px] mx-auto">We couldn't find any students matching your current search or status filter.</p>
                                            </div>
                                            <Button variant="outline" size="sm" @click="searchQuery = ''; statusFilter = 'all'" class="rounded-full px-6">Clear all filters</Button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Mobile Card View -->
                <div class="grid grid-cols-1 gap-4 md:hidden">
                    <div v-for="student in filteredStudents" :key="student.id + '-mobile'" 
                        :class="[
                            'relative overflow-hidden bg-white dark:bg-black rounded-3xl p-6 shadow-xl border-2 transition-all active:scale-[0.98] group',
                            student.attendance?.status === 'Present' ? 'border-emerald-500/30 ring-1 ring-emerald-500/10' : 
                            student.attendance?.status === 'Late' ? 'border-amber-500/30 ring-1 ring-amber-500/10' :
                            student.attendance?.status === 'Absent' ? 'border-rose-500/30 ring-1 ring-rose-500/10' : 'border-zinc-100 dark:border-zinc-800'
                        ]"
                    >
                        <!-- Status Badge (Top Right) -->
                        <div v-if="student.attendance?.status" class="absolute top-6 right-6 animate-in fade-in zoom-in duration-500">
                            <span :class="[
                                'inline-flex items-center rounded-full px-3 py-1 text-[10px] uppercase font-black tracking-widest shadow-lg border backdrop-blur-md',
                                student.attendance?.status === 'Present' ? 'bg-zinc-900 text-white border-zinc-800 shadow-zinc-900/20' :
                                student.attendance?.status === 'Late' ? 'bg-zinc-500 text-white border-zinc-400 shadow-zinc-500/20' :
                                student.attendance?.status === 'Absent' ? 'bg-zinc-100 text-zinc-500 border-zinc-200' :
                                'bg-zinc-300 text-zinc-800 border-zinc-200'
                            ]">
                                {{ student.attendance.status }}
                            </span>
                        </div>

                        <div class="flex items-center gap-4 mb-6">
                            <div class="h-14 w-14 shrink-0 border-2 border-zinc-100 dark:border-zinc-800 rounded-2xl flex items-center justify-center bg-zinc-50 dark:bg-zinc-900 text-xl font-black shadow-inner group-hover:scale-110 transition-transform duration-500">
                                {{ student.name.charAt(0) }}
                            </div>
                            <div class="pr-20"> <!-- Space for status badge -->
                                <h4 class="font-black text-lg tracking-tight line-clamp-1 text-zinc-900 dark:text-zinc-100 leading-tight">{{ student.name }}</h4>
                                <p class="text-[11px] font-bold font-mono text-zinc-400 dark:text-zinc-500 mt-0.5 tracking-wider">{{ student.student_number || 'N/A' }}</p>
                            </div>
                        </div>

                        <!-- Schedule Info Pill -->
                        <div class="flex items-center gap-3 text-[10px] font-black text-zinc-500 dark:text-zinc-400 mb-8 bg-zinc-50/80 dark:bg-zinc-900/80 p-4 rounded-2xl border border-zinc-100 dark:border-zinc-800 shadow-inner group-hover:border-zinc-200 dark:group-hover:border-zinc-700 transition-colors">
                            <div class="flex items-center gap-2.5">
                                <Clock class="w-4 h-4 text-zinc-400" />
                                <span class="tracking-wide">{{ student.slot_start }} - {{ student.slot_end }}</span>
                            </div>
                            <div v-if="student.attendance?.scanned_at" class="flex items-center gap-2 ml-auto">
                                <span class="h-1.5 w-1.5 rounded-full bg-zinc-900 dark:bg-white animate-pulse"></span>
                                <span class="text-zinc-900 dark:text-white font-black italic tracking-tight">
                                    {{ !student.attendance.is_manual ? `Scan @ ${new Date(student.attendance.scanned_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}` : 'Manual' }}
                                </span>
                            </div>
                            <div v-else class="ml-auto flex items-center gap-2 text-zinc-400 italic font-bold">
                                <span class="h-1.5 w-1.5 rounded-full bg-zinc-300 dark:bg-zinc-700"></span>
                                Waiting...
                            </div>
                        </div>

                        <!-- Action Buttons -->
                         <div class="grid grid-cols-4 gap-2">
                            <button 
                                @click="updateAttendance(student, 'Present')"
                                :class="[
                                    'py-3 rounded-xl text-[9px] font-black tracking-widest transition-all duration-300 border',
                                    student.attendance?.status?.toLowerCase() === 'present' 
                                        ? 'bg-zinc-900 border-zinc-800 text-white shadow-lg dark:bg-white dark:text-zinc-900' 
                                        : 'bg-zinc-50/50 dark:bg-zinc-900/50 border-zinc-200/50 dark:border-zinc-800 text-zinc-400'
                                ]"
                            >
                                P
                            </button>
                            <button 
                                @click="updateAttendance(student, 'Late')"
                                :class="[
                                    'py-3 rounded-xl text-[9px] font-black tracking-widest transition-all duration-300 border',
                                    student.attendance?.status?.toLowerCase() === 'late' 
                                        ? 'bg-zinc-500 border-zinc-400 text-white shadow-lg' 
                                        : 'bg-zinc-50/50 dark:bg-zinc-900/50 border-zinc-200/50 dark:border-zinc-800 text-zinc-400'
                                ]"
                            >
                                L
                            </button>
                            <button 
                                @click="updateAttendance(student, 'Absent')"
                                :class="[
                                    'py-3 rounded-xl text-[9px] font-black tracking-widest transition-all duration-300 border',
                                    student.attendance?.status?.toLowerCase() === 'absent' 
                                        ? 'bg-white border-zinc-200 text-zinc-500 shadow-sm dark:bg-zinc-800 dark:border-zinc-700 dark:text-zinc-400' 
                                        : 'bg-zinc-50/50 dark:bg-zinc-900/50 border-zinc-200/50 dark:border-zinc-800 text-zinc-400'
                                ]"
                            >
                                A
                            </button>
                            <button 
                                @click="updateAttendance(student, 'Excused')"
                                :class="[
                                    'py-3 rounded-xl text-[9px] font-black tracking-widest transition-all duration-300 border',
                                    student.attendance?.status?.toLowerCase() === 'excused' 
                                        ? 'bg-zinc-200 border-zinc-300 text-zinc-700 dark:bg-zinc-700 dark:border-zinc-600 dark:text-zinc-200' 
                                        : 'bg-zinc-50/50 dark:bg-zinc-900/50 border-zinc-200/50 dark:border-zinc-800 text-zinc-400'
                                ]"
                            >
                                E
                            </button>
                        </div>
                    </div>
                    
                    <div v-if="filteredStudents.length === 0" class="bg-white dark:bg-black rounded-[2.5rem] p-12 text-center border-2 border-dashed border-zinc-100 dark:border-zinc-800/50">
                        <div class="w-20 h-20 rounded-full bg-zinc-50 dark:bg-zinc-900/50 flex items-center justify-center mx-auto mb-6">
                            <Users class="w-8 h-8 text-zinc-200 dark:text-zinc-800" stroke-width="1.5" />
                        </div>
                        <h3 class="font-black text-xl tracking-tight text-zinc-900 dark:text-zinc-100">No students found</h3>
                        <p class="text-zinc-500 dark:text-zinc-400 text-sm mt-2 font-medium max-w-[200px] mx-auto">Try adjusting your filters or search terms.</p>
                    </div>
                </div>
            </div>

            <!-- Bulk Action Bar -->
            <Transition
                enter-active-class="transition-all duration-500 ease-out"
                enter-from-class="translate-y-24 opacity-0 scale-95"
                enter-to-class="translate-y-0 opacity-100 scale-100"
                leave-active-class="transition-all duration-300 ease-in"
                leave-from-class="translate-y-0 opacity-100 scale-100"
                leave-to-class="translate-y-24 opacity-0 scale-95"
            >
                <div v-if="selectedStudents.length > 0" class="fixed bottom-24 md:bottom-8 left-1/2 -translate-x-1/2 z-[40] w-[92%] max-w-2xl pointer-events-none">
                    <div class="bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 rounded-[2rem] p-3 shadow-[0_32px_64px_-16px_rgba(0,0,0,0.5)] border border-white/10 dark:border-black/10 backdrop-blur-xl pointer-events-auto flex items-center justify-between gap-4">
                        <div class="pl-4 flex items-center gap-3">
                            <div class="h-10 w-10 rounded-2xl bg-white/10 dark:bg-black/10 flex items-center justify-center font-black text-sm">
                                {{ selectedStudents.length }}
                            </div>
                            <div class="hidden sm:block">
                                <p class="text-[10px] font-black uppercase tracking-[0.2em] opacity-40">Selected</p>
                                <p class="text-xs font-bold leading-none">Students to update</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-1.5 p-1 bg-white/5 dark:bg-black/5 rounded-2xl border border-white/10 dark:border-black/10">
                            <button 
                                v-for="status in ['Present', 'Late', 'Absent', 'Excused']" 
                                :key="status"
                                @click="bulkUpdateAttendance(status)"
                                :disabled="isBulkSaving"
                                class="h-11 px-4 sm:px-6 rounded-xl text-[10px] font-black tracking-widest hover:bg-white dark:hover:bg-black hover:text-zinc-900 dark:hover:text-white transition-all active:scale-95 disabled:opacity-50 flex items-center gap-2"
                            >
                                <Check v-if="!isBulkSaving" class="w-3 h-3 hidden sm:block" />
                                {{ status.toUpperCase() }}
                            </button>
                        </div>

                        <button 
                            @click="selectedStudents = []"
                            class="h-10 w-10 flex items-center justify-center rounded-full hover:bg-white/10 dark:hover:bg-black/10 transition-colors mr-1"
                        >
                            <X class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </Transition>
        </div>
    </AppLayout>
</template>
