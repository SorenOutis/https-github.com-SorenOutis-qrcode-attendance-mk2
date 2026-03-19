<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
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
    CalendarDays
} from 'lucide-vue-next';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

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
    successStatus.value[student.id] = false;

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
            successStatus.value[student.id] = true;
            setTimeout(() => {
                successStatus.value[student.id] = false;
            }, 2000);
        }
    })
    .catch(err => {
        console.error('Failed to update attendance', err);
    })
    .finally(() => {
        savingStatus.value[student.id] = false;
    });
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
            successStatus.value[student.id] = true;
            setTimeout(() => {
                successStatus.value[student.id] = false;
            }, 2000);
        }
    })
    .catch(err => {
        console.error('Failed to update remarks', err);
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
        }
    })
    .catch(err => {
        console.error('Failed to mark all as absent', err);
        alert('An error occurred.');
    })
    .finally(() => {
        isMarkingAllAbsent.value = false;
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="[
        { title: 'Manage Attendance', href: '/manage-attendance' },
        { title: `${subject.name} - ${date}`, href: `/manage-attendance/${subject.id}/${date}` }
    ]">
        <Head :title="`Attendance: ${subject.name}`" />

        <div class="flex h-full flex-col gap-8 p-6 lg:p-10 w-full animate-in fade-in slide-in-from-bottom-4 duration-700">
            <!-- Header Section -->
            <div class="flex flex-col gap-6 sm:flex-row sm:items-center justify-between">
                <div class="space-y-1">
                    <div class="flex items-center gap-3">
                        <Button variant="ghost" size="icon" @click="goBack" class="-ml-2 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors rounded-full">
                            <ChevronLeft class="h-5 w-5" />
                        </Button>
                        <h1 class="text-3xl font-serif font-bold tracking-tight text-foreground">{{ subject.name }}</h1>
                    </div>
                    <div class="flex items-center gap-2 text-zinc-500 dark:text-zinc-400 ml-10">
                        <CalendarDays class="w-4 h-4" />
                        <span class="text-sm font-medium">
                            {{ new Date(date).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}
                        </span>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <Button 
                        variant="outline"
                        class="h-10 px-6 rounded-full font-bold text-rose-600 border-rose-200 hover:bg-rose-50 hover:text-rose-700 hover:border-rose-300 dark:bg-rose-950/20 dark:border-rose-900/50 dark:hover:bg-rose-900/40 transition-all active:scale-95 shadow-sm"
                        @click="markAllAbsent"
                        :disabled="isMarkingAllAbsent || students.every(s => s.attendance)"
                    >
                        <XCircle v-if="!isMarkingAllAbsent" class="w-4 h-4 mr-2" />
                        {{ isMarkingAllAbsent ? 'Marking...' : 'Mark Remaining Absent' }}
                    </Button>
                </div>
            </div>

            <!-- Stats Overview -->
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
                <!-- Total -->
                <div class="group relative overflow-hidden rounded-2xl p-5 transition-all duration-500 hover:shadow-lg hover:-translate-y-1 bg-white dark:bg-black border border-zinc-200 dark:border-zinc-800 text-zinc-900 dark:text-white shadow-md">
                    <div class="absolute -right-6 -top-6 h-32 w-32 rounded-full bg-zinc-100 dark:bg-zinc-900 blur-2xl transition-all duration-500 group-hover:bg-zinc-200 dark:group-hover:bg-zinc-800"></div>
                    <div class="absolute right-4 top-1/2 -translate-y-1/2 text-black/5 dark:text-white/5 transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-6 pointer-events-none z-0">
                        <Users class="h-16 w-16" />
                    </div>
                    <div class="relative z-10">
                        <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Total</p>
                        <p class="mt-1 text-4xl font-light tracking-tight text-zinc-900 dark:text-white drop-shadow-sm">{{ stats.total }}</p>
                    </div>
                </div>
                
                <!-- Present -->
                <div class="group relative overflow-hidden rounded-2xl p-5 transition-all duration-500 hover:shadow-lg hover:-translate-y-1 bg-white dark:bg-black border border-zinc-200 dark:border-zinc-800 text-zinc-900 dark:text-white shadow-md">
                    <div class="absolute -right-6 -top-6 h-32 w-32 rounded-full bg-emerald-50 dark:bg-emerald-950/20 blur-2xl transition-all duration-500 group-hover:bg-emerald-100 dark:group-hover:bg-emerald-900/30"></div>
                    <div class="absolute right-4 top-1/2 -translate-y-1/2 text-emerald-500/10 transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-6 pointer-events-none z-0">
                        <CheckCircle class="h-16 w-16" />
                    </div>
                    <div class="relative z-10">
                        <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-emerald-600 dark:text-emerald-400">Present</p>
                        <p class="mt-1 text-4xl font-light tracking-tight text-emerald-600 drop-shadow-sm">{{ stats.present }}</p>
                    </div>
                </div>

                <!-- Late -->
                <div class="group relative overflow-hidden rounded-2xl p-5 transition-all duration-500 hover:shadow-lg hover:-translate-y-1 bg-white dark:bg-black border border-zinc-200 dark:border-zinc-800 text-zinc-900 dark:text-white shadow-md">
                    <div class="absolute -right-6 -top-6 h-32 w-32 rounded-full bg-amber-50 dark:bg-amber-950/20 blur-2xl transition-all duration-500 group-hover:bg-amber-100 dark:group-hover:bg-amber-900/30"></div>
                    <div class="absolute right-4 top-1/2 -translate-y-1/2 text-amber-500/10 transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-6 pointer-events-none z-0">
                        <Clock class="h-16 w-16" />
                    </div>
                    <div class="relative z-10">
                        <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-amber-600 dark:text-amber-400">Late</p>
                        <p class="mt-1 text-4xl font-light tracking-tight text-amber-600 drop-shadow-sm">{{ stats.late }}</p>
                    </div>
                </div>

                <!-- Absent -->
                <div class="group relative overflow-hidden rounded-2xl p-5 transition-all duration-500 hover:shadow-lg hover:-translate-y-1 bg-white dark:bg-black border border-zinc-200 dark:border-zinc-800 text-zinc-900 dark:text-white shadow-md">
                    <div class="absolute -right-6 -top-6 h-32 w-32 rounded-full bg-rose-50 dark:bg-rose-950/20 blur-2xl transition-all duration-500 group-hover:bg-rose-100 dark:group-hover:bg-rose-900/30"></div>
                    <div class="absolute right-4 top-1/2 -translate-y-1/2 text-rose-500/10 transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-6 pointer-events-none z-0">
                        <XCircle class="h-16 w-16" />
                    </div>
                    <div class="relative z-10">
                        <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-rose-600 dark:text-rose-400">Absent</p>
                        <p class="mt-1 text-4xl font-light tracking-tight text-rose-600 drop-shadow-sm">{{ stats.absent }}</p>
                    </div>
                </div>

                <!-- Excused -->
                <div class="group relative overflow-hidden rounded-2xl p-5 transition-all duration-500 hover:shadow-lg hover:-translate-y-1 bg-white dark:bg-black border border-zinc-200 dark:border-zinc-800 text-zinc-900 dark:text-white shadow-md">
                    <div class="absolute -right-6 -top-6 h-32 w-32 rounded-full bg-zinc-100 dark:bg-zinc-900 blur-2xl transition-all duration-500 group-hover:bg-zinc-200 dark:group-hover:bg-zinc-800"></div>
                    <div class="absolute right-4 top-1/2 -translate-y-1/2 text-black/5 dark:text-white/5 transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-6 pointer-events-none z-0">
                        <Info class="h-16 w-16" />
                    </div>
                    <div class="relative z-10">
                        <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Excused</p>
                        <p class="mt-1 text-4xl font-light tracking-tight text-zinc-900 dark:text-white drop-shadow-sm">{{ stats.excused }}</p>
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
                        :class="['h-9 px-4 rounded-lg text-xs font-semibold transition-all shrink-0', statusFilter === 'present' ? 'bg-emerald-500 text-white shadow-sm' : 'text-emerald-600 dark:text-emerald-400 hover:bg-emerald-500/10']"
                    >
                        Present
                    </button>
                    <button 
                        @click="statusFilter = 'late'"
                        :class="['h-9 px-4 rounded-lg text-xs font-semibold transition-all shrink-0', statusFilter === 'late' ? 'bg-amber-500 text-white shadow-sm' : 'text-amber-600 dark:text-amber-400 hover:bg-amber-500/10']"
                    >
                        Late
                    </button>
                    <button 
                        @click="statusFilter = 'absent'"
                        :class="['h-9 px-4 rounded-lg text-xs font-semibold transition-all shrink-0', statusFilter === 'absent' ? 'bg-rose-500 text-white shadow-sm' : 'text-rose-600 dark:text-rose-400 hover:bg-rose-500/10']"
                    >
                        Absent
                    </button>
                </div>
            </div>

            <!-- Content Area -->
            <div class="space-y-4">
                <!-- Desktop Table View -->
                <div class="hidden md:block rounded-2xl border bg-white dark:bg-black shadow-xl overflow-hidden border-zinc-200 dark:border-zinc-800">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="text-[10px] text-zinc-500 dark:text-zinc-400 uppercase font-semibold bg-zinc-50/50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-800 tracking-wider">
                                <tr>
                                    <th class="px-8 py-4">Student Information</th>
                                    <th class="px-8 py-4">Shift / Schedule</th>
                                    <th class="px-8 py-4">Entry Source</th>
                                    <th class="px-8 py-4 text-right w-[400px]">Attendance Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-900">
                                <tr v-for="student in filteredStudents" :key="student.id" 
                                    class="group transition-all hover:bg-zinc-50 dark:hover:bg-zinc-900/50"
                                >
                                    <td class="px-8 py-5">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-full bg-muted flex items-center justify-center font-bold text-muted-foreground group-hover:bg-primary group-hover:text-primary-foreground transition-colors shrink-0">
                                                {{ student.name.charAt(0) }}
                                            </div>
                                            <div>
                                                <div class="font-bold text-foreground group-hover:text-primary transition-colors">{{ student.name }}</div>
                                                <div class="text-xs text-muted-foreground tracking-tight">{{ student.student_number }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-8 py-5">
                                        <div class="flex items-center gap-2 font-medium text-muted-foreground">
                                            <Clock class="w-3.5 h-3.5" />
                                            <span>{{ student.slot_start }} - {{ student.slot_end }}</span>
                                        </div>
                                    </td>
                                    <td class="px-8 py-5">
                                        <div v-if="student.attendance?.scanned_at && !student.attendance?.is_manual" class="flex items-center gap-2">
                                            <span class="inline-flex items-center gap-1.5 text-[10px] font-bold text-emerald-600 bg-emerald-50/50 dark:bg-emerald-950/20 px-2 py-1 rounded-full border border-emerald-100 dark:border-emerald-900/30">
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
                                            <div v-show="successStatus[student.id]" class="flex items-center gap-1.5 text-[9px] font-bold uppercase text-emerald-600 mr-2">
                                                <CheckCircle2 class="w-2.5 h-2.5" /> Saved
                                            </div>
                                            
                                            <div class="flex p-1 bg-zinc-100 dark:bg-zinc-900 rounded-xl gap-1 border border-zinc-200 dark:border-zinc-800 shadow-inner">
                                                <button 
                                                    @click="updateAttendance(student, 'Present')"
                                                    :class="['w-9 h-8 flex items-center justify-center rounded-lg text-xs font-bold transition-all', student.attendance?.status?.toLowerCase() === 'present' ? 'bg-white dark:bg-zinc-700 text-zinc-900 dark:text-white shadow-sm ring-1 ring-zinc-200 dark:ring-zinc-600' : 'text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200']"
                                                >
                                                    P
                                                </button>
                                                <button 
                                                    @click="updateAttendance(student, 'Late')"
                                                    :class="['w-9 h-8 flex items-center justify-center rounded-lg text-xs font-bold transition-all', student.attendance?.status?.toLowerCase() === 'late' ? 'bg-white dark:bg-zinc-700 text-amber-600 shadow-sm ring-1 ring-zinc-200 dark:ring-zinc-600' : 'text-zinc-400 hover:text-amber-600']"
                                                >
                                                    L
                                                </button>
                                                <button 
                                                    @click="updateAttendance(student, 'Absent')"
                                                    :class="['w-9 h-8 flex items-center justify-center rounded-lg text-xs font-bold transition-all', student.attendance?.status?.toLowerCase() === 'absent' ? 'bg-white dark:bg-zinc-700 text-rose-600 shadow-sm ring-1 ring-zinc-200 dark:ring-zinc-600' : 'text-zinc-400 hover:text-rose-600']"
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
                                    <td colspan="4" class="px-8 py-20 text-center">
                                        <div class="flex flex-col items-center gap-3">
                                            <div class="p-4 rounded-full bg-muted text-muted-foreground/20">
                                                <Search class="w-12 h-12" />
                                            </div>
                                            <p class="text-xl font-bold text-muted-foreground">No students found matching your filters.</p>
                                            <Button variant="link" @click="searchQuery = ''; statusFilter = 'all'" class="text-primary">Clear all filters</Button>
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
                                student.attendance?.status === 'Present' ? 'bg-emerald-500 text-white border-emerald-400 shadow-emerald-500/20' :
                                student.attendance?.status === 'Late' ? 'bg-amber-500 text-white border-amber-400 shadow-amber-500/20' :
                                student.attendance?.status === 'Absent' ? 'bg-rose-500 text-white border-rose-400 shadow-rose-500/20' :
                                'bg-zinc-500 text-white border-zinc-400'
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
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                <span class="text-emerald-600 dark:text-emerald-400 font-black italic tracking-tight">
                                    {{ !student.attendance.is_manual ? `Scan @ ${new Date(student.attendance.scanned_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}` : 'Manual' }}
                                </span>
                            </div>
                            <div v-else class="ml-auto flex items-center gap-2 text-zinc-400 italic font-bold">
                                <span class="h-1.5 w-1.5 rounded-full bg-zinc-300 dark:bg-zinc-700"></span>
                                Waiting...
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="grid grid-cols-3 gap-3">
                            <button 
                                @click="updateAttendance(student, 'Present')"
                                :class="[
                                    'py-4 rounded-2xl text-[10px] font-black tracking-[0.15em] transition-all duration-300 border-2',
                                    student.attendance?.status?.toLowerCase() === 'present' 
                                        ? 'bg-emerald-500 border-emerald-400 text-white shadow-[0_0_20px_rgba(16,185,129,0.3)] scale-[1.03] z-10' 
                                        : 'bg-zinc-50/50 dark:bg-zinc-900/50 border-emerald-500/10 text-emerald-600/60 dark:text-emerald-400/60 hover:border-emerald-500/30'
                                ]"
                            >
                                PRESENT
                            </button>
                            <button 
                                @click="updateAttendance(student, 'Late')"
                                :class="[
                                    'py-4 rounded-2xl text-[10px] font-black tracking-[0.15em] transition-all duration-300 border-2',
                                    student.attendance?.status?.toLowerCase() === 'late' 
                                        ? 'bg-amber-500 border-amber-400 text-white shadow-[0_0_20px_rgba(245,158,11,0.3)] scale-[1.03] z-10' 
                                        : 'bg-zinc-50/50 dark:bg-zinc-900/50 border-amber-500/10 text-amber-600/60 dark:text-amber-400/60 hover:border-amber-500/30'
                                ]"
                            >
                                LATE
                            </button>
                            <button 
                                @click="updateAttendance(student, 'Absent')"
                                :class="[
                                    'py-4 rounded-2xl text-[10px] font-black tracking-[0.15em] transition-all duration-300 border-2',
                                    student.attendance?.status?.toLowerCase() === 'absent' 
                                        ? 'bg-rose-500 border-rose-400 text-white shadow-[0_0_20px_rgba(244,63,94,0.3)] scale-[1.03] z-10' 
                                        : 'bg-zinc-50/50 dark:bg-zinc-900/50 border-rose-500/10 text-rose-600/60 dark:text-rose-400/60 hover:border-rose-500/30'
                                ]"
                            >
                                ABSENT
                            </button>
                        </div>
                    </div>
                    
                    <div v-if="filteredStudents.length === 0" class="bg-white dark:bg-black rounded-3xl p-16 text-center border-2 border-zinc-100 dark:border-zinc-800 shadow-xl">
                        <div class="relative inline-block mb-6">
                            <Search class="w-16 h-16 text-zinc-100 dark:text-zinc-800 mx-auto" stroke-width="3" />
                            <div class="absolute inset-0 bg-gradient-to-t from-white dark:from-black to-transparent opacity-50"></div>
                        </div>
                        <p class="font-black text-xl tracking-tight text-zinc-900 dark:text-zinc-100">No students found</p>
                        <p class="text-zinc-500 dark:text-zinc-400 text-sm mt-2 font-medium">Try adjusting your filters or search terms.</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
