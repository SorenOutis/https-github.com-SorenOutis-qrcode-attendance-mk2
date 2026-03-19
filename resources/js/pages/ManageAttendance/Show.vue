<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { ChevronLeft, Save, CheckCircle2 } from 'lucide-vue-next';

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
        // Fallback logic if needed
    })
    .finally(() => {
        savingStatus.value[student.id] = false;
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="[
        { title: 'Manage Attendance', href: '/manage-attendance' },
        { title: `${subject.name} - ${date}`, href: `/manage-attendance/${subject.id}/${date}` }
    ]">
        <Head :title="`Attendance: ${subject.name}`" />

        <div class="flex h-full flex-col gap-6 p-6 lg:p-10 w-full">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <Button variant="ghost" size="icon" @click="goBack" class="-ml-2">
                            <ChevronLeft class="h-5 w-5" />
                        </Button>
                        <h1 class="text-2xl font-bold tracking-tight">{{ subject.name }}</h1>
                    </div>
                    <p class="text-muted-foreground ml-9">
                        Attendance for {{ new Date(date).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}
                    </p>
                </div>
            </div>

            <!-- Desktop Table View -->
            <div class="hidden md:block rounded-xl border bg-card shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs text-muted-foreground uppercase bg-muted/50 border-b">
                            <tr>
                                <th class="px-6 py-4 font-medium">Student</th>
                                <th class="px-6 py-4 font-medium">Schedule Slot</th>
                                <th class="px-6 py-4 font-medium">Scanned At</th>
                                <th class="px-6 py-4 font-medium text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="student in students" :key="student.id" class="hover:bg-muted/30 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-foreground">{{ student.name }}</div>
                                    <div class="text-xs text-muted-foreground">{{ student.student_number }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ student.slot_start }} - {{ student.slot_end }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <template v-if="student.attendance?.scanned_at && !student.attendance?.is_manual">
                                        <Badge variant="outline">
                                            {{ new Date(student.attendance.scanned_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                                        </Badge>
                                    </template>
                                    <template v-else-if="student.attendance?.is_manual">
                                        <span class="text-xs text-muted-foreground italic">Manual Entry</span>
                                    </template>
                                    <template v-else>
                                        <span class="text-xs text-muted-foreground">-</span>
                                    </template>
                                </td>
                                <td class="px-6 py-4 align-middle">
                                    <div class="flex items-center justify-end gap-2">
                                        <div v-show="savingStatus[student.id]" class="text-xs text-muted-foreground animate-pulse mr-2">Saving...</div>
                                        <div v-show="successStatus[student.id]" class="text-xs text-foreground mr-2 flex items-center">
                                            <CheckCircle2 class="h-4 w-4 mr-1" />
                                            Saved
                                        </div>
                                        <div class="flex rounded-md shadow-sm border overflow-hidden">
                                            <button 
                                                type="button" 
                                                @click="updateAttendance(student, 'Present')"
                                                :class="[
                                                    'px-3 py-1.5 text-xs font-medium transition-colors',
                                                    student.attendance?.status === 'Present' || student.attendance?.status === 'present' 
                                                        ? 'bg-primary text-primary-foreground hover:bg-primary/90' 
                                                        : 'bg-transparent text-muted-foreground hover:bg-muted'
                                                ]"
                                            >
                                                Present
                                            </button>
                                            <div class="w-px bg-border"></div>
                                            <button 
                                                type="button" 
                                                @click="updateAttendance(student, 'Late')"
                                                :class="[
                                                    'px-3 py-1.5 text-xs font-medium transition-colors',
                                                    student.attendance?.status === 'Late' || student.attendance?.status === 'late'
                                                        ? 'bg-primary text-primary-foreground hover:bg-primary/90' 
                                                        : 'bg-transparent text-muted-foreground hover:bg-muted'
                                                ]"
                                            >
                                                Late
                                            </button>
                                            <div class="w-px bg-border"></div>
                                            <button 
                                                type="button" 
                                                @click="updateAttendance(student, 'Absent')"
                                                :class="[
                                                    'px-3 py-1.5 text-xs font-medium transition-colors',
                                                    student.attendance?.status === 'Absent' || student.attendance?.status === 'absent'
                                                        ? 'bg-primary text-primary-foreground hover:bg-primary/90' 
                                                        : 'bg-transparent text-muted-foreground hover:bg-muted'
                                                ]"
                                            >
                                                Absent
                                            </button>
                                            <div class="w-px bg-border"></div>
                                            <button 
                                                type="button" 
                                                @click="updateAttendance(student, 'Excused')"
                                                :class="[
                                                    'px-3 py-1.5 text-xs font-medium transition-colors',
                                                    student.attendance?.status === 'Excused' || student.attendance?.status === 'excused'
                                                        ? 'bg-primary text-primary-foreground hover:bg-primary/90' 
                                                        : 'bg-transparent text-muted-foreground hover:bg-muted'
                                                ]"
                                            >
                                                Excused
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="students.length === 0">
                                <td colspan="4" class="px-6 py-8 text-center text-muted-foreground">
                                    No students are scheduled for this subject on this day.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Mobile Card View -->
            <div class="grid grid-cols-2 md:hidden gap-4">
                <div v-for="student in students" :key="student.id + '-mobile'" class="border bg-card rounded-xl p-4 shadow-sm flex flex-col justify-between space-y-3">
                    <div class="flex flex-col gap-2">
                        <div>
                            <div class="font-medium text-foreground text-base leading-tight">{{ student.name }}</div>
                            <div class="text-xs text-muted-foreground mt-1">{{ student.student_number }} &bull; {{ student.slot_start }}-{{ student.slot_end }}</div>
                        </div>
                        <div>
                            <template v-if="student.attendance?.scanned_at && !student.attendance?.is_manual">
                                <Badge variant="outline" class="text-[10px] px-1.5 py-0 h-5">
                                    {{ new Date(student.attendance.scanned_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                                </Badge>
                            </template>
                            <template v-else-if="student.attendance?.is_manual">
                                <span class="text-[10px] text-muted-foreground italic inline-block">Manual</span>
                            </template>
                        </div>
                    </div>
                    
                    <div class="pt-2 border-t">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Mark Status</span>
                            <div class="flex items-center">
                                <span v-show="savingStatus[student.id]" class="text-xs text-muted-foreground animate-pulse mr-2">Saving...</span>
                                <span v-show="successStatus[student.id]" class="text-xs text-foreground flex items-center">
                                    <CheckCircle2 class="h-4 w-4 mr-1" />
                                    Saved
                                </span>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-1.5">
                            <button 
                                type="button" 
                                @click="updateAttendance(student, 'Present')"
                                :class="[
                                    'px-1 py-2 text-[11px] sm:text-xs font-medium transition-colors border rounded-md text-center truncate',
                                    student.attendance?.status === 'Present' || student.attendance?.status === 'present' 
                                        ? 'bg-primary text-primary-foreground border-primary hover:bg-primary/90' 
                                        : 'bg-transparent text-muted-foreground border-border hover:bg-muted'
                                ]"
                            >
                                Present
                            </button>
                            <button 
                                type="button" 
                                @click="updateAttendance(student, 'Late')"
                                :class="[
                                    'px-1 py-2 text-[11px] sm:text-xs font-medium transition-colors border rounded-md text-center truncate',
                                    student.attendance?.status === 'Late' || student.attendance?.status === 'late'
                                        ? 'bg-primary text-primary-foreground border-primary hover:bg-primary/90' 
                                        : 'bg-transparent text-muted-foreground border-border hover:bg-muted'
                                ]"
                            >
                                Late
                            </button>
                            <button 
                                type="button" 
                                @click="updateAttendance(student, 'Absent')"
                                :class="[
                                    'px-1 py-2 text-[11px] sm:text-xs font-medium transition-colors border rounded-md text-center truncate',
                                    student.attendance?.status === 'Absent' || student.attendance?.status === 'absent'
                                        ? 'bg-primary text-primary-foreground border-primary hover:bg-primary/90' 
                                        : 'bg-transparent text-muted-foreground border-border hover:bg-muted'
                                ]"
                            >
                                Absent
                            </button>
                            <button 
                                type="button" 
                                @click="updateAttendance(student, 'Excused')"
                                :class="[
                                    'px-1 py-2 text-[11px] sm:text-xs font-medium transition-colors border rounded-md text-center truncate',
                                    student.attendance?.status === 'Excused' || student.attendance?.status === 'excused'
                                        ? 'bg-primary text-primary-foreground border-primary hover:bg-primary/90' 
                                        : 'bg-transparent text-muted-foreground border-border hover:bg-muted'
                                ]"
                            >
                                Excused
                            </button>
                        </div>
                    </div>
                </div>
                
                <div v-if="students.length === 0" class="border bg-card rounded-xl p-8 text-center text-muted-foreground shadow-sm">
                    No students are scheduled for this subject on this day.
                </div>
            </div>
        </div>
    </AppLayout>
</template>
