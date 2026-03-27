<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import gsap from 'gsap';
import { 
    LayoutGrid,
    Table as TableIcon,
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
    X,
    ChevronRight,
    QrCode,
    User,
    UserCheck,
    UserX,
    AlertTriangle
} from 'lucide-vue-next';
import QRCode from 'qrcode';
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { useToast } from '@/composables/useToast';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
    DialogClose,
} from '@/components/ui/dialog';
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
    photo: string | null;
    slot_start: string | null;
    slot_end: string | null;
    qr_token: string;
    attendance: Attendance | null;
    trend: string[];
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
const viewMode = ref<'grid' | 'table'>('grid');
const isBulkSaving = ref(false);

// Roll Call Mode State
const isRollCallMode = ref(false);
const currentRollCallIndex = ref(0);

const rollCallStudents = computed(() => {
    // Only mark students who are NOT marked yet
    return filteredStudents.value.filter(s => !s.attendance);
});

const currentRollCallStudent = computed(() => rollCallStudents.value[currentRollCallIndex.value]);

function startRollCall() {
    if (rollCallStudents.value.length === 0) {
        toast.info('All students are already marked!');
        return;
    }
    currentRollCallIndex.value = 0;
    isRollCallMode.value = true;
}

function nextRollCall() {
    if (currentRollCallIndex.value < rollCallStudents.value.length - 1) {
        currentRollCallIndex.value++;
    } else {
        isRollCallMode.value = false;
        toast.success('Roll call completed!');
    }
}

function prevRollCall() {
    if (currentRollCallIndex.value > 0) {
        currentRollCallIndex.value--;
    }
}

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

function goToPrevDay() {
    const d = new Date(selectedDate.value);
    d.setDate(d.getDate() - 1);
    selectedDate.value = d.toISOString().split('T')[0];
}

function goToNextDay() {
    const d = new Date(selectedDate.value);
    d.setDate(d.getDate() + 1);
    selectedDate.value = d.toISOString().split('T')[0];
}

function goToToday() {
    selectedDate.value = new Date().toISOString().split('T')[0];
}
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
    const marked = total - unmarked;
    const progress = total === 0 ? 0 : Math.round((marked / total) * 100);
    
    return { total, present, late, absent, excused, unmarked, marked, progress };
});

const isAllMarked = computed(() => stats.value.total > 0 && stats.value.unmarked === 0);

const animatedStats = ref({ total: 0, present: 0, late: 0, absent: 0, excused: 0, marked: 0, progress: 0 });

watch(stats, (newStats) => {
    gsap.to(animatedStats.value, {
        total: newStats.total,
        present: newStats.present,
        late: newStats.late,
        absent: newStats.absent,
        excused: newStats.excused,
        marked: newStats.marked,
        progress: newStats.progress,
        duration: 0.8,
        ease: 'power2.out',
        snap: { total: 1, present: 1, late: 1, absent: 1, excused: 1, marked: 1, progress: 1 }
    });
}, { immediate: true });

function avatarGradient(name: string): string {
    let hash = 0;
    for (let i = 0; i < name.length; i++) {
        hash = name.charCodeAt(i) + ((hash << 5) - hash);
    }
    const hue = Math.abs(hash) % 360;
    return `linear-gradient(135deg, hsl(${hue}, 15%, 20%) 0%, hsl(${(hue + 40) % 360}, 10%, 35%) 100%)`;
}

function goBack() {
    router.get('/manage-attendance');
}

function updateAttendance(student: Student, newStatus: string) {
    const isRemoving = student.attendance?.status === newStatus;
    const finalStatus = isRemoving ? null : newStatus;

    savingStatus.value[student.id] = true;

    // Optimistically update the UI
    if (isRemoving) {
        student.attendance = null;
    } else {
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
            status: finalStatus,
            slot_start: student.slot_start,
            slot_end: student.slot_end,
            remarks: student.attendance?.remarks || null
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            student.attendance = data.attendance;
            if (isRemoving) {
                toast.success(`Removed attendance for ${student.name}`);
            } else {
                toast.success(`Marked ${student.name} as ${newStatus}`);
            }
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
    
    // If EVERY selected student already has this status, we toggle it OFF for all
    const allHaveStatus = selectedStudents.value.every(id => {
        const s = props.students.find(st => st.id === id);
        return s?.attendance?.status === newStatus;
    });

    const isRemoving = allHaveStatus;
    const finalStatus = isRemoving ? null : newStatus;

    isBulkSaving.value = true;
    const total = selectedStudents.value.length;
    let successCount = 0;

    toast.info(`${isRemoving ? 'Clearing' : 'Updating'} ${total} student(s)...`);

    for (const studentId of selectedStudents.value) {
        const student = props.students.find(s => s.id === studentId);
        if (!student || !student.slot_start) continue;
        
        // If we are NOT removing, skip those who already have the status
        if (!isRemoving && student.attendance?.status === newStatus) continue;

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
                    status: finalStatus,
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
    
    if (isRemoving) {
        toast.success(`Successfully removed attendance for ${successCount} student(s)`);
    } else {
        toast.success(`Successfully marked ${successCount} student(s) as ${newStatus}`);
    }
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
    // Only mark students who have a schedule AND don't have attendance yet
    const unmarkedScheduledStudents = props.students.filter(s => s.slot_start && !s.attendance);
    
    if (unmarkedScheduledStudents.length === 0) {
        toast.info('No unmarked scheduled students to mark as absent');
        return;
    }

    if (!confirm(`Are you sure you want to mark ${unmarkedScheduledStudents.length} remaining student(s) as Absent?`)) {
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
            students: unmarkedScheduledStudents.map((s: Student) => ({
                id: s.id,
                slot_start: s.slot_start,
                slot_end: s.slot_end,
            }))
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            unmarkedScheduledStudents.forEach((s: Student) => {
                s.attendance = {
                    id: 0,
                    status: 'Absent',
                    is_manual: true,
                    remarks: null,
                    scanned_at: new Date().toISOString()
                };
            });
            toast.success(`Marked ${unmarkedScheduledStudents.length} as Absent`);
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

const selectedStudentForQr = ref<Student | null>(null);
const qrModalOpen = ref(false);

function openQrModal(student: Student) {
    selectedStudentForQr.value = student;
    qrModalOpen.value = true;
}

function closeQrModal() {
    qrModalOpen.value = false;
    selectedStudentForQr.value = null;
}

async function drawQrToCanvas() {
    const canvas = document.querySelector<HTMLCanvasElement>('#qr-canvas');
    const student = selectedStudentForQr.value;
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
    [qrModalOpen, selectedStudentForQr],
    ([open, student]) => {
        if (open && student) {
            nextTick(() => drawQrToCanvas());
        }
    },
    { immediate: true },
);

function downloadQr() {
    const canvas = document.querySelector<HTMLCanvasElement>('#qr-canvas');
    if (!canvas || !selectedStudentForQr.value) return;

    const link = document.createElement('a');
    link.href = canvas.toDataURL('image/png');
    link.download = `${selectedStudentForQr.value.name}-qr.png`;
    link.click();
}

function studentPortalUrl(token: string) {
    const base = window.location.origin;
    return `${base}/portal/${token}`;
}

async function copyStudentPortalLink() {
    const token = selectedStudentForQr.value?.qr_token;
    if (!token) return;
    const url = studentPortalUrl(token);

    try {
        await navigator.clipboard.writeText(url);
        toast.success('Link copied to clipboard');
    } catch {
        const input = document.createElement('input');
        input.value = url;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
        toast.success('Link copied to clipboard');
    }
}

function openPrintCards() {
    if (!selectedStudentForQr.value) return;
    window.open(`/students/print-cards?ids=${selectedStudentForQr.value.id}`, '_blank');
}

const cardsRef = ref<HTMLElement | null>(null);
const gridRef = ref<HTMLElement | null>(null);

function animateStudents() {
    nextTick(() => {
        const targets = gridRef.value?.querySelectorAll('[data-student-card]');

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

watch([searchQuery, statusFilter, () => props.students], () => {
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
            // Directive handles tilt now, but we keeps transformStyle for children
            gsap.set(card, { transformStyle: "preserve-3d" });
        });
    }

    // 2. Grid and Card Entrance
    if (gridRef.value) {
        gsap.set(gridRef.value, { opacity: 1, visibility: 'visible', perspective: 1000 });

        gsap.from(gridRef.value, {
            opacity: 0,
            y: 10,
            duration: 0.6,
            ease: 'power2.out',
            clearProps: 'all'
        });
        
        // Initial student card animation
        animateStudents();
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

    // 4. Keyboard Shortcuts for Rapid Marking
    const handleKeydown = (e: KeyboardEvent) => {
        // Prevent triggering if user is typing in the search box or remarks input
        if (e.target instanceof HTMLInputElement || e.target instanceof HTMLTextAreaElement) return;
        
        if (selectedStudents.value.length > 0 && !isBulkSaving.value) {
            if (e.key === '1') bulkUpdateAttendance('Present');
            if (e.key === '2') bulkUpdateAttendance('Late');
            if (e.key === '3') bulkUpdateAttendance('Absent');
            if (e.key === '4') bulkUpdateAttendance('Excused');
        }
    };
    
    window.addEventListener('keydown', handleKeydown);
    
    onUnmounted(() => {
        window.removeEventListener('keydown', handleKeydown);
    });
});
</script>

<template>
    <AppLayout :breadcrumbs="[
        { title: 'Manage Attendance', href: '/manage-attendance' },
        { title: `${subject.name} - ${date}`, href: `/manage-attendance/${subject.id}/${date}` }
    ]">
        <Head :title="`Attendance: ${subject.name}`" />

        <div class="flex min-h-full flex-col gap-3 sm:gap-5 p-3 sm:p-6 lg:p-10 pb-20 md:pb-6 w-full overflow-x-hidden animate-in fade-in slide-in-from-bottom-4 duration-700">
            <div class="flex items-center justify-between gap-2 sm:gap-6 pb-4 border-b border-zinc-100 dark:border-zinc-900">
                <div class="flex items-center gap-2 sm:gap-4 flex-1 min-w-0">
                    <Button variant="ghost" size="icon" @click="goBack" class="-ml-1 sm:-ml-2 h-9 w-9 sm:h-12 sm:w-12 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-all rounded-full border border-transparent hover:border-zinc-200 dark:hover:border-zinc-700 shadow-sm active:scale-90 shrink-0">
                        <ChevronLeft class="h-4 w-4 sm:h-6 sm:w-6 text-zinc-600 dark:text-zinc-400" />
                    </Button>
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5 sm:gap-2 mb-0.5 sm:mb-1">
                            <span class="h-1 w-1 rounded-full bg-zinc-400 animate-pulse shrink-0"></span>
                            <span class="text-[8px] sm:text-[10px] font-black uppercase tracking-widest text-zinc-400 truncate">Attendance Roster</span>
                        </div>
                        <h1 class="text-xl sm:text-4xl font-serif font-bold tracking-tighter text-foreground leading-none truncate">{{ subject.name }}</h1>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-1.5 sm:gap-3 shrink-0">
                    <!-- Date Selector Revamp -->
                    <div class="inline-flex items-center bg-white dark:bg-black rounded-xl sm:rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-xl overflow-hidden group/picker h-10 sm:h-14 transition-all hover:shadow-2xl">
                        <button 
                            @click="goToPrevDay"
                            class="h-full px-2 sm:px-4 hover:bg-zinc-50 dark:hover:bg-zinc-900 border-r border-zinc-100 dark:border-zinc-800 transition-colors group/prev shrink-0"
                        >
                            <ChevronLeft class="w-3.5 h-3.5 sm:w-5 sm:h-5 text-zinc-400 group-hover/prev:text-zinc-900 dark:group-hover/prev:text-white transition-colors" />
                        </button>
                        
                        <div class="relative px-2 sm:px-6 flex flex-col justify-center min-w-[70px] sm:min-w-[200px]">
                            <div class="hidden sm:flex items-center justify-between mb-0.5 gap-2">
                                <span class="text-[8px] sm:text-[9px] font-black uppercase tracking-widest text-zinc-400 block truncate">Selected Date</span>
                                <button 
                                    @click="goToToday"
                                    class="text-[8px] sm:text-[9px] font-black uppercase tracking-widest text-zinc-400 hover:text-zinc-900 dark:hover:text-white transition-all hover:scale-105 active:scale-95 px-1.5 py-0.5 bg-zinc-50 dark:bg-zinc-900 rounded-md border border-zinc-100 dark:border-zinc-800 shrink-0"
                                >
                                    Today
                                </button>
                            </div>
                            <div class="relative flex items-center group/input">
                                <input
                                    id="show-date"
                                    type="date"
                                    v-model="selectedDate"
                                    class="absolute inset-0 opacity-0 cursor-pointer z-10 w-full h-full"
                                />
                                <div class="flex items-center justify-between w-full group-hover/input:translate-x-1 transition-transform pointer-events-none">
                                    <span class="font-black text-[10px] sm:text-sm text-zinc-900 dark:text-zinc-100 tracking-tight truncate mr-1 sm:mr-2">
                                        <span class="sm:hidden">{{ new Date(props.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) }}</span>
                                        <span class="hidden sm:inline">{{ new Date(props.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) }}</span>
                                    </span>
                                    <CalendarDays class="w-3 h-3 sm:w-4 sm:h-4 text-zinc-400 shrink-0" />
                                </div>
                            </div>
                        </div>

                        <button 
                            @click="goToNextDay"
                            class="h-full px-2 sm:px-4 hover:bg-zinc-50 dark:hover:bg-zinc-900 border-l border-zinc-100 dark:border-zinc-800 transition-colors group/next shrink-0"
                        >
                            <ChevronRight class="w-3.5 h-3.5 sm:w-5 sm:h-5 text-zinc-400 group-hover/next:text-zinc-900 dark:group-hover/next:text-white transition-colors" />
                        </button>
                    </div>

                    <!-- Mobile Action Menu -->
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="outline" size="icon" class="sm:hidden h-10 w-10 rounded-xl border-zinc-200 dark:border-zinc-800 bg-white dark:bg-black shadow-xl shrink-0">
                                <MoreHorizontal class="h-4 w-4 text-zinc-600 dark:text-zinc-400" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-56 rounded-xl">
                            <DropdownMenuItem asChild>
                                <a :href="`/manage-attendance/${subject.id}/${date}/export`" target="_blank" class="w-full flex items-center cursor-pointer">
                                    <Download class="w-4 h-4 mr-2" />
                                    Export CSV
                                </a>
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem @click="markAllAbsent" :disabled="isMarkingAllAbsent || students.every(s => s.attendance)" class="cursor-pointer text-rose-600 focus:text-rose-600 dark:text-rose-400 dark:focus:text-rose-400">
                                <XCircle class="w-4 h-4 mr-2" />
                                {{ isMarkingAllAbsent ? 'Marking...' : 'Mark Remaining Absent' }}
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <!-- Desktop Actions -->
                    <div class="hidden sm:flex items-center gap-2 sm:gap-3">
                        <Button 
                            variant="outline"
                            class="h-10 px-6 rounded-full font-bold text-zinc-900 border-zinc-200 bg-white dark:bg-zinc-900 dark:border-zinc-800 dark:text-white transition-all active:scale-95 shadow-sm text-sm"
                            @click="startRollCall"
                            :disabled="rollCallStudents.length === 0"
                        >
                            <Users class="w-4 h-4 mr-2 shrink-0" />
                            Roll Call
                        </Button>
                        <Button 
                            variant="outline"
                            as-child
                            class="h-10 px-6 rounded-full font-bold text-zinc-600 border-zinc-200 hover:bg-zinc-50 dark:bg-zinc-900/50 dark:border-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-900 transition-all active:scale-95 shadow-sm text-sm"
                        >
                            <a :href="`/manage-attendance/${subject.id}/${date}/export`" target="_blank" class="flex items-center justify-center">
                                <Download class="w-4 h-4 mr-2" />
                                Export CSV
                            </a>
                        </Button>
                        <Button 
                            variant="outline"
                            class="h-10 px-6 rounded-full font-bold text-zinc-900 border-zinc-200 hover:bg-zinc-50 hover:text-black hover:border-zinc-300 dark:bg-zinc-900/50 dark:border-zinc-800 dark:text-zinc-100 dark:hover:bg-zinc-900 transition-all active:scale-95 shadow-sm text-sm"
                            @click="markAllAbsent"
                            :disabled="isMarkingAllAbsent || students.every(s => s.attendance)"
                        >
                            <XCircle v-if="!isMarkingAllAbsent" class="w-4 h-4 mr-2 shrink-0" />
                            {{ isMarkingAllAbsent ? 'Marking...' : 'Mark Remaining Absent' }}
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Attendance Progress Bar -->
            <div class="mb-2">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold uppercase tracking-widest text-zinc-500">Attendance Completion</span>
                    <span class="text-xs font-bold text-zinc-900 dark:text-zinc-100 tabular-nums">{{ Math.round(animatedStats.marked) }} / {{ Math.round(animatedStats.total) }} Marked (<span class="text-primary">{{ Math.round(animatedStats.progress) }}%</span>)</span>
                </div>
                <div class="h-2.5 w-full bg-zinc-100 dark:bg-zinc-800 rounded-full overflow-hidden flex">
                    <div 
                        class="h-full bg-primary transition-all duration-1000 ease-out"
                        :style="`width: ${animatedStats.progress}%`"
                    ></div>
                </div>
            </div>

            <!-- Stats Overview (Horizontal scroll on mobile) -->
            <div ref="cardsRef" class="flex sm:grid sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4 overflow-x-auto sm:overflow-visible pb-3 sm:pb-0 snap-x no-scrollbar -mx-3 px-3 sm:mx-0 sm:px-0 scroll-pl-3">
                <!-- Total -->
                <div 
                    v-tilt
                    data-card 
                    class="group relative overflow-hidden rounded-2xl p-3 sm:p-5 transition-colors bg-white dark:bg-black border border-zinc-200 dark:border-zinc-800 text-zinc-900 dark:text-white shadow-sm hover:bg-zinc-50 dark:hover:bg-zinc-900 min-w-[120px] sm:min-w-0 flex-shrink-0 snap-start preserve-3d shadow-3d"
                >
                    <Users class="absolute right-[-10%] top-1/2 -translate-y-1/2 h-16 w-16 sm:h-24 sm:w-24 text-zinc-900/[0.03] dark:text-white/[0.03] transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-6 pointer-events-none" />
                    <div class="relative z-10">
                        <p class="text-[9px] sm:text-[10px] font-bold uppercase tracking-[0.15em] sm:tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Total</p>
                        <p class="mt-0.5 sm:mt-1 text-3xl sm:text-4xl font-light tracking-tight text-zinc-900 dark:text-white drop-shadow-sm tabular-nums">{{ Math.round(animatedStats.total) }}</p>
                    </div>
                </div>
                
                <!-- Present -->
                <div 
                    v-tilt
                    data-card 
                    class="group relative overflow-hidden rounded-2xl p-3 sm:p-5 transition-colors bg-white dark:bg-black border border-zinc-200 dark:border-zinc-800 text-zinc-900 dark:text-white shadow-sm hover:bg-zinc-50 dark:hover:bg-zinc-900 min-w-[120px] sm:min-w-0 flex-shrink-0 snap-start preserve-3d shadow-3d"
                >
                    <UserCheck class="absolute right-[-10%] top-1/2 -translate-y-1/2 h-16 w-16 sm:h-24 sm:w-24 text-zinc-900/[0.03] dark:text-white/[0.03] transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-6 pointer-events-none" />
                    <div class="relative z-10">
                        <p class="text-[9px] sm:text-[10px] font-bold uppercase tracking-[0.15em] sm:tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Present</p>
                        <p class="mt-0.5 sm:mt-1 text-3xl sm:text-4xl font-light tracking-tight text-zinc-900 dark:text-100 drop-shadow-sm tabular-nums">{{ Math.round(animatedStats.present) }}</p>
                    </div>
                </div>

                <!-- Late -->
                <div 
                    v-tilt
                    data-card 
                    class="group relative overflow-hidden rounded-2xl p-3 sm:p-5 transition-colors bg-white dark:bg-black border border-zinc-200 dark:border-zinc-800 text-zinc-900 dark:text-white shadow-sm hover:bg-zinc-50 dark:hover:bg-zinc-900 min-w-[120px] sm:min-w-0 flex-shrink-0 snap-start preserve-3d shadow-3d"
                >
                    <Clock class="absolute right-[-10%] top-1/2 -translate-y-1/2 h-16 w-16 sm:h-24 sm:w-24 text-zinc-900/[0.03] dark:text-white/[0.03] transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-6 pointer-events-none" />
                    <div class="relative z-10">
                        <p class="text-[9px] sm:text-[10px] font-bold uppercase tracking-[0.15em] sm:tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Late</p>
                        <p class="mt-0.5 sm:mt-1 text-3xl sm:text-4xl font-light tracking-tight text-zinc-700 dark:text-zinc-300 drop-shadow-sm tabular-nums">{{ Math.round(animatedStats.late) }}</p>
                    </div>
                </div>

                <!-- Absent -->
                <div 
                    v-tilt
                    data-card 
                    class="group relative overflow-hidden rounded-2xl p-3 sm:p-5 transition-colors bg-white dark:bg-black border border-zinc-200 dark:border-zinc-800 text-zinc-900 dark:text-white shadow-sm hover:bg-zinc-50 dark:hover:bg-zinc-900 min-w-[120px] sm:min-w-0 flex-shrink-0 snap-start preserve-3d shadow-3d"
                >
                    <UserX class="absolute right-[-10%] top-1/2 -translate-y-1/2 h-16 w-16 sm:h-24 sm:w-24 text-zinc-900/[0.03] dark:text-white/[0.03] transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-6 pointer-events-none" />
                    <div class="relative z-10">
                        <p class="text-[9px] sm:text-[10px] font-bold uppercase tracking-[0.15em] sm:tracking-[0.2em] text-zinc-400 dark:text-zinc-500">Absent</p>
                        <p class="mt-0.5 sm:mt-1 text-3xl sm:text-4xl font-light tracking-tight text-zinc-400 dark:text-zinc-500 drop-shadow-sm tabular-nums">{{ Math.round(animatedStats.absent) }}</p>
                    </div>
                </div>

                <!-- Excused -->
                <div 
                    v-tilt
                    data-card 
                    class="group relative overflow-hidden rounded-2xl p-3 sm:p-5 transition-colors bg-white dark:bg-black border border-zinc-200 dark:border-zinc-800 text-zinc-900 dark:text-white shadow-sm hover:bg-zinc-50 dark:hover:bg-zinc-900 min-w-[120px] sm:min-w-0 flex-shrink-0 snap-start preserve-3d shadow-3d"
                >
                    <Info class="absolute right-[-10%] top-1/2 -translate-y-1/2 h-16 w-16 sm:h-24 sm:w-24 text-zinc-900/[0.03] dark:text-white/[0.03] transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-6 pointer-events-none" />
                    <div class="relative z-10">
                        <p class="text-[9px] sm:text-[10px] font-bold uppercase tracking-[0.15em] sm:tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Excused</p>
                        <p class="mt-0.5 sm:mt-1 text-3xl sm:text-4xl font-light tracking-tight text-zinc-900 dark:text-white drop-shadow-sm tabular-nums">{{ Math.round(animatedStats.excused) }}</p>
                    </div>
                </div>
            </div>

            <!-- Toolbar: Search & Filters (Sticky) -->
            <div class="sticky top-[env(safe-area-inset-top)] z-20 flex gap-2 sm:gap-4 items-center bg-zinc-50/95 dark:bg-zinc-900/95 backdrop-blur-lg p-2 sm:p-4 rounded-xl sm:rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-sm w-full overflow-hidden mt-0 sm:mt-2">
                <div class="relative w-full sm:max-w-md flex-1 min-w-[120px]">
                    <Search class="absolute left-3.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 sm:w-4 sm:h-4 text-zinc-500 dark:text-zinc-400 shrink-0" />
                    <Input 
                        v-model="searchQuery" 
                        placeholder="Search..." 
                        class="pl-9 sm:pl-10 h-9 sm:h-10 text-xs sm:text-sm rounded-full bg-white dark:bg-black border-zinc-200 dark:border-zinc-800 focus-visible:ring-zinc-400 dark:focus-visible:ring-zinc-600 shadow-sm w-full"
                    />
                </div>
                
                <!-- View Switcher -->
                <div class="hidden sm:flex rounded-full bg-zinc-200/50 dark:bg-zinc-800/50 p-1 shrink-0 border border-zinc-200 dark:border-zinc-800 mr-1 sm:mr-3">
                    <button
                        class="rounded-full p-1.5 transition-all outline-none"
                        :class="viewMode === 'table' ? 'bg-white dark:bg-zinc-700 text-zinc-900 dark:text-white shadow-sm' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white'"
                        title="Table View"
                        @click="viewMode = 'table'"
                    >
                        <TableIcon class="h-4 w-4" />
                    </button>
                    <button
                        class="rounded-full p-1.5 transition-all outline-none"
                        :class="viewMode === 'grid' ? 'bg-white dark:bg-zinc-700 text-zinc-900 dark:text-white shadow-sm' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white'"
                        title="Grid View"
                        @click="viewMode = 'grid'"
                    >
                        <LayoutGrid class="h-4 w-4" />
                    </button>
                </div>
                
                <div class="flex items-center gap-1 sm:gap-3 bg-zinc-200/50 dark:bg-zinc-800/50 p-1 rounded-xl border border-zinc-200 dark:border-zinc-800 shrink-0 overflow-x-auto no-scrollbar sm:w-auto">
                    <div class="flex items-center px-2 sm:px-3 border-r border-zinc-300 dark:border-zinc-700 mr-0.5 sm:mr-1 shrink-0">
                        <input 
                            type="checkbox" 
                            :checked="allSelected" 
                            @change="toggleSelectAll"
                            class="rounded border-zinc-300 text-zinc-900 focus:ring-zinc-900 dark:bg-zinc-900 dark:border-zinc-700 h-4 w-4 cursor-pointer"
                        />
                        <span class="ml-2 text-[10px] font-black uppercase tracking-widest text-zinc-500 whitespace-nowrap hidden sm:block">Select All</span>
                    </div>
                    <button 
                        @click="statusFilter = 'all'"
                        :class="['h-8 sm:h-9 px-2.5 sm:px-4 rounded-lg text-[10px] sm:text-xs font-semibold transition-all shrink-0', statusFilter === 'all' ? 'bg-white dark:bg-zinc-700 text-zinc-900 dark:text-white shadow-sm' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white']"
                    >
                        All
                    </button>
                    <button 
                        @click="statusFilter = 'unmarked'"
                        :class="['h-8 sm:h-9 px-2.5 sm:px-4 rounded-lg text-[10px] sm:text-xs font-semibold transition-all shrink-0', statusFilter === 'unmarked' ? 'bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900 shadow-sm' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white']"
                    >
                        <span class="sm:hidden">{{ stats.unmarked }}</span>
                        <span class="hidden sm:inline">Unmarked ({{ stats.unmarked }})</span>
                    </button>
                    <button 
                        @click="statusFilter = 'present'"
                        :class="['h-8 sm:h-9 px-2.5 sm:px-4 rounded-lg text-[10px] sm:text-xs font-semibold transition-all shrink-0', statusFilter === 'present' ? 'bg-zinc-900 text-white shadow-sm dark:bg-zinc-100 dark:text-zinc-900' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white']"
                    >
                        P
                    </button>
                    <button 
                        @click="statusFilter = 'late'"
                        :class="['h-8 sm:h-9 px-2.5 sm:px-4 rounded-lg text-[10px] sm:text-xs font-semibold transition-all shrink-0', statusFilter === 'late' ? 'bg-zinc-500 text-white shadow-sm' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white']"
                    >
                        L
                    </button>
                    <button 
                        @click="statusFilter = 'absent'"
                        :class="['h-8 sm:h-9 px-2.5 sm:px-4 rounded-lg text-[10px] sm:text-xs font-semibold transition-all shrink-0', statusFilter === 'absent' ? 'bg-zinc-100 text-zinc-600 shadow-sm border border-zinc-200 dark:bg-zinc-800 dark:text-zinc-400' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white']"
                    >
                        A
                    </button>
                </div>
            </div>

            <!-- Content Area -->
            <div class="space-y-4">
                <!-- Unified Responsive Grid View -->
                <!-- Table View -->
                <div v-if="viewMode === 'table'" class="overflow-x-auto w-full rounded-[1.5rem] sm:rounded-[2rem] border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-black shadow-sm animate-in fade-in zoom-in duration-700 pb-20 sm:pb-0">
                    <table class="min-w-full text-left text-sm whitespace-nowrap">
                        <thead class="bg-zinc-50/95 dark:bg-zinc-900/95 backdrop-blur text-zinc-500 dark:text-zinc-400 border-b border-zinc-200 dark:border-zinc-800 sticky top-0 z-10">
                            <tr>
                                <th class="px-4 py-3 text-xs font-bold uppercase tracking-wider w-10">
                                    <input 
                                        type="checkbox" 
                                        :checked="allSelected" 
                                        @change="toggleSelectAll"
                                        class="rounded border-zinc-300 text-zinc-900 focus:ring-zinc-900 dark:bg-zinc-900 dark:border-zinc-700 h-4 w-4 cursor-pointer"
                                    />
                                </th>
                                <th class="px-4 py-3 text-[10px] sm:text-xs font-bold uppercase tracking-wider">Student</th>
                                <th class="px-4 py-3 text-[10px] sm:text-xs font-bold uppercase tracking-wider">Schedule</th>
                                <th class="px-4 py-3 text-[10px] sm:text-xs font-bold uppercase tracking-wider text-center">Status</th>
                                <th class="px-4 py-3 text-[10px] sm:text-xs font-bold uppercase tracking-wider text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            <tr v-if="filteredStudents.length === 0">
                                <td colspan="5" class="py-12 text-center text-zinc-500 dark:text-zinc-400 text-sm">
                                    No students found for this filter/search.
                                </td>
                            </tr>
                            <tr 
                                v-for="(student, index) in filteredStudents" 
                                :key="student.id"
                                v-reveal:[index%10*40]
                                @click="toggleStudentSelection(student.id)"
                                class="transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-900/50 cursor-pointer"
                                :class="{'bg-zinc-50 dark:bg-zinc-900/30': selectedStudents.includes(student.id)}"
                            >
                                <td class="px-4 py-3 w-10" @click.stop>
                                    <input 
                                        type="checkbox" 
                                        :value="student.id" 
                                        v-model="selectedStudents"
                                        class="rounded border-zinc-300 text-zinc-900 focus:ring-zinc-900 dark:bg-zinc-900 dark:border-zinc-700 h-4 w-4 cursor-pointer"
                                    />
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <!-- Photo/Avatar -->
                                        <div v-if="student.photo" class="h-8 w-8 sm:h-10 sm:w-10 shrink-0 rounded-full overflow-hidden border border-zinc-200 dark:border-zinc-800 shadow-sm">
                                            <img :src="student.photo" class="h-full w-full object-cover" />
                                        </div>
                                        <div v-else :class="['h-8 w-8 sm:h-10 sm:w-10 shrink-0 rounded-full flex items-center justify-center bg-gradient-to-br border border-white/20 shadow-inner', avatarGradient(student.name)]">
                                            <span class="text-xs sm:text-sm font-bold text-white drop-shadow-sm">{{ student.name.charAt(0) }}</span>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <h4 class="font-bold text-xs sm:text-sm line-clamp-1 group-hover:text-zinc-600 dark:group-hover:text-zinc-300 transition-colors text-zinc-900 dark:text-white" :title="student.name">
                                                {{ student.name }}
                                            </h4>
                                            <p class="text-[9px] sm:text-[10px] text-zinc-500 font-mono tracking-widest mt-0.5">
                                                {{ student.student_number || 'NO ID' }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2 text-xs font-semibold text-zinc-600 dark:text-zinc-400">
                                        <Clock class="w-3.5 h-3.5 text-zinc-400" />
                                        <span v-if="student.slot_start" class="tracking-widest">{{ student.slot_start }} - {{ student.slot_end }}</span>
                                        <span v-else class="text-zinc-400/60 uppercase text-[10px] tracking-widest flex items-center gap-1">
                                            <AlertTriangle class="w-3 h-3" /> No slot
                                        </span>
                                    </div>
                                    <div v-if="student.attendance?.scanned_at" class="mt-1 text-[10px] text-zinc-500 dark:text-zinc-500 font-semibold uppercase tracking-widest flex items-center gap-1">
                                        Scanned @ <span class="text-zinc-900 dark:text-zinc-300">{{ new Date(student.attendance.scanned_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span v-if="student.attendance?.status" :class="[
                                        'inline-flex items-center rounded-full px-2 sm:px-2.5 py-0.5 sm:py-1 text-[8px] sm:text-[9px] uppercase font-black tracking-widest shadow-sm border backdrop-blur-md',
                                        student.attendance?.status.toLowerCase() === 'present' ? 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20' :
                                        student.attendance?.status.toLowerCase() === 'late' ? 'bg-amber-500/10 text-amber-600 border-amber-500/20' :
                                        student.attendance?.status.toLowerCase() === 'absent' ? 'bg-rose-500/10 text-rose-600 border-rose-500/20' :
                                        'bg-zinc-100 text-zinc-500 border-zinc-200 dark:bg-zinc-800'
                                    ]">
                                        {{ student.attendance.status }}
                                    </span>
                                    <span v-else class="text-[10px] font-bold text-zinc-400 uppercase tracking-widest">—</span>
                                </td>
                                <td class="px-4 py-3 w-10 text-center" @click.stop>
                                    <div :class="['flex items-center justify-center gap-1', student.slot_start ? '' : 'opacity-30 grayscale pointer-events-none']">
                                        <button 
                                            v-for="status in ['Present', 'Late', 'Absent', 'Excused']"
                                            :key="status"
                                            @click="updateAttendance(student, status)"
                                            :disabled="!student.slot_start"
                                            :title="'Mark ' + status"
                                            :class="[
                                                'h-7 w-7 sm:h-8 sm:w-8 flex items-center justify-center rounded-lg border transition-all active:scale-95 group/btn',
                                                student.attendance?.status === status 
                                                    ? 'bg-zinc-900 border-zinc-900 text-white dark:bg-zinc-100 dark:border-zinc-100 dark:text-zinc-900 shadow-sm' 
                                                    : 'bg-white dark:bg-zinc-900/40 border-zinc-100 dark:border-zinc-800 text-zinc-400 hover:border-zinc-300 dark:hover:border-zinc-600 hover:text-zinc-900 dark:hover:text-white'
                                            ]"
                                        >
                                            <span class="text-[9px] sm:text-[10px] font-black uppercase tracking-tighter">{{ status.charAt(0) }}</span>
                                        </button>
                                        <Button 
                                            variant="ghost" 
                                            size="icon-sm" 
                                            class="ml-1 h-7 w-7 sm:h-8 sm:w-8 rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-800 group/qr"
                                            @click.stop="openQrModal(student)"
                                            title="View QR Code"
                                        >
                                            <QrCode class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-zinc-400 group-hover/qr:text-zinc-900 dark:group-hover/qr:text-zinc-100" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Unified 3-Column Grid View -->
                <div v-else ref="gridRef" class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-2.5 sm:gap-6 animate-in fade-in slide-in-from-bottom-4 duration-700">
                    <div v-for="(student, index) in filteredStudents" :key="student.id" 
                        data-student-card
                        v-reveal:[index%10*40]
                        v-tilt
                        :class="[
                            'relative overflow-hidden bg-white dark:bg-black rounded-xl sm:rounded-[2rem] p-2 sm:p-5 shadow-sm border-2 transition-colors duration-200 cursor-pointer flex flex-col hover:bg-zinc-50 dark:hover:bg-zinc-900',
                            student.attendance?.status === 'Present' ? 'border-emerald-500/20' : 
                            student.attendance?.status === 'Late' ? 'border-amber-500/20' :
                            student.attendance?.status === 'Absent' ? 'border-rose-500/20' : 'border-zinc-100 dark:border-zinc-800',
                            selectedStudents.includes(student.id) ? 'ring-2 ring-zinc-900 dark:ring-zinc-100 border-zinc-900 dark:border-zinc-100' : ''
                        ]"
                        @click="toggleStudentSelection(student.id)"
                    >

                        <!-- Checkbox Overlay -->
                        <div class="absolute top-2 left-2 sm:top-4 sm:left-4 z-30" @click.stop>
                            <input 
                                type="checkbox" 
                                :value="student.id" 
                                v-model="selectedStudents"
                                class="rounded border-zinc-300 text-zinc-900 focus:ring-zinc-900 dark:bg-zinc-900 dark:border-zinc-700 h-4 w-4 sm:h-5 sm:w-5 cursor-pointer shadow-sm"
                            />
                        </div>

                        <!-- Header Area: Stacked on mobile, side-by-side on desktop -->
                        <div class="flex flex-col sm:flex-row items-center sm:items-start justify-between mb-2 sm:mb-4 relative z-20 gap-2 sm:gap-0">
                            <div class="flex flex-col sm:flex-row items-center gap-1.5 sm:gap-4 text-center sm:text-left">
                                <!-- Enhanced Avatar with Photo Support -->
                                <div class="relative">
                                    <div 
                                        class="h-10 w-10 sm:h-14 sm:w-14 shrink-0 rounded-2xl flex items-center justify-center text-xs sm:text-lg font-black shadow-lg group-hover:scale-105 transition-transform duration-500 text-white border border-white/10 overflow-hidden bg-zinc-100 dark:bg-zinc-800 shadow-3d"
                                        :style="!student.photo ? { background: avatarGradient(student.name) } : {}"
                                    >
                                        <img 
                                            v-if="student.photo" 
                                            :src="student.photo" 
                                            alt="" 
                                            class="h-full w-full object-cover"
                                        />
                                        <span v-else>{{ student.name.charAt(0) }}</span>
                                    </div>
                                    
                                    <!-- Status Indicator Dot -->
                                    <div 
                                        v-if="student.attendance"
                                        class="absolute -bottom-1 -right-1 h-4 w-4 rounded-full border-2 border-white dark:border-black shadow-sm"
                                        :class="[
                                            student.attendance.status.toLowerCase() === 'present' ? 'bg-emerald-500' :
                                            student.attendance.status.toLowerCase() === 'late' ? 'bg-amber-500' :
                                            student.attendance.status.toLowerCase() === 'absent' ? 'bg-rose-500' : 'bg-zinc-400'
                                        ]"
                                    ></div>
                                </div>

                                <div class="w-full max-w-[100px] sm:max-w-[140px]">
                                    <h4 class="font-black text-[10px] sm:text-base tracking-tight truncate sm:line-clamp-1 text-zinc-900 dark:text-zinc-100 leading-tight">{{ student.name }}</h4>
                                    <p class="text-[8px] sm:text-[10px] font-bold font-mono text-zinc-400 dark:text-zinc-500 mt-0.5 tracking-wider uppercase truncate">{{ student.student_number || 'No ID' }}</p>
                                    
                                    <!-- Attendance Trend Dots -->
                                    <div class="flex gap-1 mt-1.5 justify-center sm:justify-start">
                                        <div 
                                            v-for="(status, tIdx) in student.trend" 
                                            :key="tIdx"
                                            class="h-1.5 w-1.5 rounded-full"
                                            :class="[
                                                status.toLowerCase() === 'present' ? 'bg-emerald-500' :
                                                status.toLowerCase() === 'late' ? 'bg-amber-500' :
                                                status.toLowerCase() === 'absent' ? 'bg-rose-500' : 'bg-zinc-200 dark:bg-zinc-800'
                                            ]"
                                            :title="status"
                                        ></div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center sm:flex-col sm:items-end gap-1.5 sm:gap-2">
                                <!-- Status Badge (Minimized on mobile) -->
                                <div v-if="student.attendance?.status" class="animate-in fade-in zoom-in duration-500">
                                    <span :class="[
                                        'inline-flex items-center rounded-full px-1.5 sm:px-2.5 py-0.5 sm:py-1 text-[7px] sm:text-[9px] uppercase font-black tracking-widest shadow-md border backdrop-blur-md transition-all',
                                        student.attendance?.status.toLowerCase() === 'present' ? 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20' :
                                        student.attendance?.status.toLowerCase() === 'late' ? 'bg-amber-500/10 text-amber-600 border-amber-500/20' :
                                        student.attendance?.status.toLowerCase() === 'absent' ? 'bg-rose-500/10 text-rose-600 border-rose-500/20' :
                                        'bg-zinc-100 text-zinc-500 border-zinc-200'
                                    ]">
                                        <span class="sm:hidden">{{ student.attendance.status.charAt(0) }}</span>
                                        <span class="hidden sm:block">{{ student.attendance.status }}</span>
                                    </span>
                                </div>
                                
                                <Button 
                                    variant="ghost" 
                                    size="icon-sm" 
                                    class="h-6 w-6 sm:h-8 sm:w-8 rounded-full hover:bg-zinc-100 dark:hover:bg-zinc-800 group/qr"
                                    @click.stop="openQrModal(student)"
                                >
                                    <QrCode class="w-3 h-3 sm:w-4 sm:h-4 text-zinc-400 group-hover/qr:text-zinc-900 dark:group-hover/qr:text-zinc-100" />
                                </Button>
                            </div>
                        </div>

                        <!-- Info Pill -->
                        <div class="hidden sm:flex items-center justify-between gap-3 text-[10px] font-black text-zinc-500 dark:text-zinc-400 mb-6 bg-zinc-50/80 dark:bg-zinc-900/80 p-3 rounded-2xl border border-zinc-100 dark:border-zinc-800 relative z-20 shadow-inner">
                            <div class="flex items-center gap-2">
                                <Clock class="w-3.5 h-3.5" />
                                <span v-if="student.slot_start" class="tracking-widest">{{ student.slot_start }} - {{ student.slot_end }}</span>
                                <span v-else class="italic text-zinc-400/60 dark:text-zinc-600 uppercase tracking-widest flex items-center gap-1.5">
                                    <AlertTriangle class="w-3 h-3" />
                                    No schedule assigned
                                </span>
                            </div>
                            <div v-if="student.attendance?.scanned_at" class="flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-white dark:bg-black border border-zinc-100 dark:border-zinc-800 shadow-sm transition-all group-hover:border-zinc-300 dark:group-hover:border-zinc-600">
                                <span class="text-[8px] uppercase font-black opacity-40">Marked @</span>
                                <span class="text-zinc-900 dark:text-zinc-100 font-bold whitespace-nowrap">{{ new Date(student.attendance.scanned_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}</span>
                            </div>
                        </div>

                        <!-- Quick Actions Grid (Ultra-compact on mobile) -->
                        <div :class="['grid grid-cols-4 gap-1 sm:gap-2 relative z-20 transition-all mt-auto', student.slot_start ? '' : 'opacity-30 grayscale pointer-events-none']" @click.stop>
                            <button 
                                v-for="status in ['Present', 'Late', 'Absent', 'Excused']"
                                :key="status"
                                @click="updateAttendance(student, status)"
                                :disabled="!student.slot_start"
                                :class="[
                                    'flex flex-col items-center justify-center py-0.5 sm:py-2.5 rounded-md sm:rounded-2xl border transition-all active:scale-95 group/btn',
                                    student.attendance?.status === status 
                                        ? 'bg-zinc-900 border-zinc-900 text-white dark:bg-zinc-100 dark:border-zinc-100 dark:text-zinc-900 shadow-lg' 
                                        : 'bg-white dark:bg-zinc-900/40 border-zinc-100 dark:border-zinc-800 text-zinc-400 hover:border-zinc-300 dark:hover:border-zinc-600 hover:text-zinc-900 dark:hover:text-white'
                                ]"
                            >
                                <span class="text-[8px] sm:text-[10px] font-black uppercase tracking-tighter">{{ status.charAt(0) }}</span>
                            </button>
                        </div>

                        <!-- No Slot Indicator (Mobile) -->
                        <div v-if="!student.slot_start" class="sm:hidden mt-1 flex items-center justify-center gap-1 text-[8px] text-zinc-400/60 font-bold uppercase tracking-widest">
                            <AlertTriangle class="w-2.5 h-2.5" />
                            No slot
                        </div>
                    </div>

                    <!-- Celebration State (All Marked) -->
                    <div v-if="isAllMarked && filteredStudents.length > 0" class="col-span-full bg-zinc-50/50 dark:bg-zinc-900/20 rounded-[2rem] p-10 sm:p-16 text-center border border-zinc-200 dark:border-zinc-800 animate-in fade-in zoom-in duration-700">
                        <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-full bg-zinc-900 dark:bg-white flex items-center justify-center mx-auto mb-6 shadow-xl">
                            <CheckCircle2 class="w-8 h-8 sm:w-10 sm:h-10 text-white dark:text-zinc-900" stroke-width="1.5" />
                        </div>
                        <h3 class="font-black text-xl sm:text-2xl tracking-tighter text-zinc-900 dark:text-zinc-100 leading-tight">All Attendance Marked</h3>
                        <p class="text-zinc-500 dark:text-zinc-400 text-xs sm:text-sm mt-2 font-medium max-w-[280px] mx-auto leading-relaxed">Every student has been accounted for today. Great work!</p>
                    </div>

                    <!-- Empty State -->
                    <div v-if="filteredStudents.length === 0" class="col-span-full bg-zinc-50/50 dark:bg-zinc-900/20 rounded-[2rem] sm:rounded-[3rem] p-10 sm:p-20 text-center border-2 border-dashed border-zinc-100 dark:border-zinc-800 animate-in fade-in zoom-in duration-700">
                        <div class="w-16 h-16 sm:w-24 sm:h-24 rounded-full bg-zinc-100 dark:bg-zinc-800/50 flex items-center justify-center mx-auto mb-6 sm:mb-8 shadow-inner">
                            <Users class="w-7 h-7 sm:w-10 sm:h-10 text-zinc-300 dark:text-zinc-700" stroke-width="1.5" />
                        </div>
                        <h3 class="font-black text-xl sm:text-2xl tracking-tighter text-zinc-900 dark:text-zinc-100 leading-tight">No students found</h3>
                        <p class="text-zinc-500 dark:text-zinc-400 text-xs sm:text-sm mt-3 font-medium max-w-[240px] mx-auto leading-relaxed">Try adjusting your filters or search terms to find what you're looking for.</p>
                        <Button variant="outline" size="sm" @click="searchQuery = ''; statusFilter = 'all'" class="mt-6 sm:mt-8 rounded-full px-6 sm:px-8 h-10 sm:h-12 font-black border-zinc-200 hover:bg-zinc-900 hover:text-white dark:hover:bg-zinc-100 dark:hover:text-zinc-900 transition-all text-xs sm:text-sm">Clear Filters</Button>
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
                                v-for="(status, index) in ['Present', 'Late', 'Absent', 'Excused']" 
                                :key="status"
                                @click="bulkUpdateAttendance(status)"
                                :disabled="isBulkSaving"
                                class="h-11 px-4 sm:px-6 rounded-xl text-[10px] font-black tracking-widest hover:bg-white dark:hover:bg-black hover:text-zinc-900 dark:hover:text-white transition-all active:scale-95 disabled:opacity-50 flex items-center gap-2"
                            >
                                <kbd class="hidden sm:inline-flex h-5 items-center justify-center rounded border border-white/20 dark:border-black/20 bg-white/10 dark:bg-black/10 px-1.5 font-mono text-[9px] font-medium text-white/70 dark:text-black/70">{{ index + 1 }}</kbd>
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

        <!-- Student QR Modal -->
        <Dialog v-model:open="qrModalOpen">
            <DialogContent class="max-w-[320px] sm:max-w-xs flex max-h-[90dvh] flex-col overflow-hidden border-0 shadow-2xl p-0 rounded-[2rem]">
                <div class="p-5 space-y-4">
                    <DialogHeader class="space-y-1">
                        <div class="flex items-center justify-between">
                            <DialogTitle class="text-xl font-black tracking-tight text-zinc-900 dark:text-zinc-100 italic">
                                STUDENT QR
                            </DialogTitle>
                            <Badge variant="outline" class="rounded-full border-zinc-200 dark:border-zinc-800 text-[10px] font-bold tracking-widest uppercase py-0.5">
                                Verified Secure
                            </Badge>
                        </div>
                    </DialogHeader>

                    <div v-if="selectedStudentForQr" class="space-y-4">
                        <div class="flex items-center gap-3 p-3 rounded-xl bg-zinc-50 dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-800 transition-all hover:bg-white dark:hover:bg-zinc-900 shadow-sm group">
                            <div v-if="selectedStudentForQr.photo" class="h-10 w-10 rounded-lg overflow-hidden border border-zinc-200 dark:border-zinc-800 shadow-sm group-hover:scale-110 transition-transform">
                                <img :src="selectedStudentForQr.photo" class="h-full w-full object-cover" />
                            </div>
                            <div v-else class="h-10 w-10 rounded-lg bg-zinc-900 dark:bg-zinc-100 flex items-center justify-center text-white dark:text-zinc-900 font-black text-lg italic group-hover:scale-110 transition-transform">
                                {{ selectedStudentForQr.name.charAt(0) }}
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-black text-zinc-900 dark:text-zinc-100 leading-tight truncate italic">
                                    {{ selectedStudentForQr.name }}
                                </p>
                                <p class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest mt-0.5">
                                    {{ selectedStudentForQr.student_number }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center justify-center p-5 bg-white dark:bg-zinc-950 rounded-2xl border-2 border-dashed border-zinc-200 dark:border-zinc-800 relative group overflow-hidden">
                            <div class="absolute inset-0 bg-zinc-900/5 dark:bg-zinc-100/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                            <canvas id="qr-canvas" class="h-40 w-40 relative z-10 transition-transform duration-500 group-hover:scale-105"></canvas>
                        </div>

                        <div class="space-y-3">
                            <div class="p-3 rounded-xl bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900 space-y-2 shadow-xl">
                                <div class="flex items-center justify-between">
                                    <p class="text-[9px] font-black uppercase tracking-widest opacity-60 italic">
                                        Activation Link
                                    </p>
                                    <button 
                                        @click="copyStudentPortalLink"
                                        class="text-[9px] font-black uppercase tracking-widest hover:underline active:scale-95 transition-all"
                                    >
                                        Copy
                                    </button>
                                </div>
                                <p class="text-[10px] font-mono whitespace-nowrap overflow-hidden text-ellipsis opacity-90 select-all cursor-pointer" @click="copyStudentPortalLink">
                                    {{ studentPortalUrl(selectedStudentForQr.qr_token) }}
                                </p>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <Button 
                                    @click="downloadQr"
                                    variant="outline" 
                                    class="h-9 rounded-lg text-[9px] font-black tracking-widest uppercase border-zinc-200 dark:border-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-900"
                                >
                                    Save
                                </Button>
                                <Button 
                                    @click="openPrintCards"
                                    variant="outline" 
                                    class="h-9 rounded-lg text-[9px] font-black tracking-widest uppercase border-zinc-200 dark:border-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-900"
                                >
                                    Print
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-4 bg-zinc-50 dark:bg-zinc-900/50 border-t border-zinc-200 dark:border-zinc-800">
                    <Button 
                        @click="closeQrModal"
                        class="w-full h-10 rounded-xl bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900 font-black tracking-widest uppercase italic shadow-lg active:scale-[0.98] transition-all text-xs"
                    >
                        OK
                    </Button>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Roll Call Mode Overlay -->
        <Transition
            enter-active-class="transition-all duration-500 ease-out"
            enter-from-class="opacity-0 scale-110"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition-all duration-300 ease-in"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-110"
        >
            <div v-if="isRollCallMode && currentRollCallStudent" class="fixed inset-0 z-[100] bg-white dark:bg-zinc-950 flex flex-col items-center justify-center p-6 text-center overflow-hidden">
                <!-- Close Button -->
                <button 
                    @click="isRollCallMode = false"
                    class="absolute top-8 right-8 h-12 w-12 rounded-full bg-zinc-100 dark:bg-zinc-900 flex items-center justify-center text-zinc-500 hover:text-zinc-900 dark:hover:text-white transition-all active:scale-95"
                >
                    <X class="w-6 h-6" />
                </button>

                <!-- Progress Header -->
                <div class="absolute top-8 left-1/2 -translate-x-1/2 flex flex-col items-center gap-2">
                    <span class="text-[10px] font-black uppercase tracking-[0.3em] text-zinc-400">Roll Call Progress</span>
                    <div class="flex items-center gap-4">
                        <div class="h-1.5 w-48 bg-zinc-100 dark:bg-zinc-900 rounded-full overflow-hidden">
                            <div 
                                class="h-full bg-zinc-900 dark:bg-white transition-all duration-500"
                                :style="{ width: `${((currentRollCallIndex + 1) / rollCallStudents.length) * 100}%` }"
                            ></div>
                        </div>
                        <span class="text-xs font-black text-zinc-900 dark:text-white tabular-nums">
                            {{ currentRollCallIndex + 1 }} / {{ rollCallStudents.length }}
                        </span>
                    </div>
                </div>

                <!-- Student Content -->
                <div class="max-w-xl w-full flex flex-col items-center gap-8 animate-in fade-in zoom-in duration-700">
                    <!-- Big Avatar -->
                    <div 
                        class="h-48 w-48 sm:h-64 sm:w-64 rounded-[3rem] shadow-[0_32px_64px_-16px_rgba(0,0,0,0.2)] dark:shadow-none border-8 border-white dark:border-zinc-900 overflow-hidden bg-zinc-100 dark:bg-zinc-900 flex items-center justify-center transition-all duration-1000"
                        :style="!currentRollCallStudent.photo ? { background: avatarGradient(currentRollCallStudent.name) } : {}"
                    >
                        <img 
                            v-if="currentRollCallStudent.photo" 
                            :src="currentRollCallStudent.photo" 
                            class="h-full w-full object-cover"
                        />
                        <span v-else class="text-7xl font-black text-white italic drop-shadow-2xl">
                            {{ currentRollCallStudent.name.charAt(0) }}
                        </span>
                    </div>

                    <div class="space-y-2">
                        <h2 class="text-4xl sm:text-6xl font-black tracking-tighter text-zinc-900 dark:text-white uppercase italic leading-none">
                            {{ currentRollCallStudent.name }}
                        </h2>
                        <p class="text-sm font-black uppercase tracking-[0.2em] text-zinc-400">
                            {{ currentRollCallStudent.student_number || 'NO ID' }}
                        </p>
                    </div>

                    <!-- Action Buttons -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4 w-full mt-4">
                        <button 
                            v-for="status in ['Present', 'Late', 'Absent', 'Excused']"
                            :key="status"
                            @click="updateAttendance(currentRollCallStudent, status); nextRollCall()"
                            class="h-24 sm:h-32 rounded-[2rem] border-2 flex flex-col items-center justify-center gap-2 group/btn transition-all active:scale-95 shadow-lg shadow-inherit"
                            :class="[
                                status === 'Present' ? 'border-emerald-500/20 hover:bg-emerald-500 hover:text-white dark:hover:bg-emerald-500/10 dark:hover:text-emerald-500 text-emerald-600' :
                                status === 'Late' ? 'border-amber-500/20 hover:bg-amber-500 hover:text-white dark:hover:bg-amber-500/10 dark:hover:text-amber-500 text-amber-600' :
                                status === 'Absent' ? 'border-rose-500/20 hover:bg-rose-500 hover:text-white dark:hover:bg-rose-500/10 dark:hover:text-rose-500 text-rose-600' :
                                'border-zinc-200 hover:bg-zinc-900 hover:text-white dark:border-zinc-800 dark:hover:bg-white dark:hover:text-zinc-900 text-zinc-500'
                            ]"
                        >
                            <span class="text-xs font-black uppercase tracking-widest">{{ status }}</span>
                            <span class="text-[10px] opacity-40 font-bold hidden sm:block">Press {{ ['1', '2', '3', '4'][['Present', 'Late', 'Absent', 'Excused'].indexOf(status)] }}</span>
                        </button>
                    </div>

                    <!-- Navigation -->
                    <div class="flex items-center gap-8 mt-4">
                        <button 
                            @click="prevRollCall"
                            :disabled="currentRollCallIndex === 0"
                            class="flex items-center gap-2 text-xs font-black uppercase tracking-widest text-zinc-400 hover:text-zinc-900 dark:hover:text-white disabled:opacity-0 transition-all"
                        >
                            <ChevronLeft class="w-4 h-4" />
                            Previous
                        </button>
                        <button 
                            @click="nextRollCall"
                            class="flex items-center gap-2 text-xs font-black uppercase tracking-widest text-zinc-400 hover:text-zinc-900 dark:hover:text-white transition-all"
                        >
                            Skip
                            <ChevronRight class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </AppLayout>
</template>
