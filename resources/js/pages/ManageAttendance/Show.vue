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
    X,
    ChevronRight,
    QrCode,
    User
} from 'lucide-vue-next';
import QRCode from 'qrcode';
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { useToast } from '@/composables/useToast';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
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
    slot_start: string | null;
    slot_end: string | null;
    qr_token: string;
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

        <div class="flex h-full flex-col gap-5 p-3 sm:p-6 lg:p-10 pb-20 md:pb-6 w-full overflow-x-hidden animate-in fade-in slide-in-from-bottom-4 duration-700">
            <div class="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between pb-4 border-b border-zinc-100 dark:border-zinc-900">
                <div class="space-y-4">
                    <div class="flex items-center gap-4">
                        <Button variant="ghost" size="icon" @click="goBack" class="-ml-2 h-12 w-12 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-all rounded-full border border-transparent hover:border-zinc-200 dark:hover:border-zinc-700 shadow-sm active:scale-90">
                            <ChevronLeft class="h-6 w-6 text-zinc-600 dark:text-zinc-400" />
                        </Button>
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <span class="h-1 w-1 rounded-full bg-zinc-400 animate-pulse"></span>
                                <span class="text-[10px] font-black uppercase tracking-widest text-zinc-400">Attendance Roster</span>
                            </div>
                            <h1 class="text-3xl sm:text-4xl font-serif font-bold tracking-tighter text-foreground leading-none">{{ subject.name }}</h1>
                        </div>
                    </div>

                    <!-- Date Selector Revamp -->
                    <div class="inline-flex items-center bg-white dark:bg-black rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-xl overflow-hidden group/picker h-14 transition-all hover:shadow-2xl">
                        <button 
                            @click="goToPrevDay"
                            class="h-full px-4 hover:bg-zinc-50 dark:hover:bg-zinc-900 border-r border-zinc-100 dark:border-zinc-800 transition-colors group/prev"
                        >
                            <ChevronLeft class="w-5 h-5 text-zinc-400 group-hover/prev:text-zinc-900 dark:group-hover/prev:text-white transition-colors" />
                        </button>
                        
                        <div class="relative px-6 flex flex-col justify-center min-w-[200px]">
                            <div class="flex items-center justify-between mb-0.5">
                                <span class="text-[9px] font-black uppercase tracking-widest text-zinc-400 block">Selected Date</span>
                                <button 
                                    @click="goToToday"
                                    class="text-[9px] font-black uppercase tracking-widest text-zinc-400 hover:text-zinc-900 dark:hover:text-white transition-all hover:scale-105 active:scale-95 px-1.5 py-0.5 bg-zinc-50 dark:bg-zinc-900 rounded-md border border-zinc-100 dark:border-zinc-800"
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
                                <div class="flex items-center gap-2 pointer-events-none group-hover/input:translate-x-1 transition-transform">
                                    <span class="font-black text-sm text-zinc-900 dark:text-zinc-100 tracking-tight">
                                        {{ new Date(props.date).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) }}
                                    </span>
                                    <CalendarDays class="w-4 h-4 text-zinc-400" />
                                </div>
                            </div>
                        </div>

                        <button 
                            @click="goToNextDay"
                            class="h-full px-4 hover:bg-zinc-50 dark:hover:bg-zinc-900 border-l border-zinc-100 dark:border-zinc-800 transition-colors group/next"
                        >
                            <ChevronRight class="w-5 h-5 text-zinc-400 group-hover/next:text-zinc-900 dark:group-hover/next:text-white transition-colors" />
                        </button>
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

            <!-- Attendance Progress Bar -->
            <div class="mb-2">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold uppercase tracking-widest text-zinc-500">Attendance Completion</span>
                    <span class="text-xs font-bold text-zinc-900 dark:text-zinc-100 tabular-nums">{{ stats.marked }} / {{ stats.total }} Marked (<span class="text-primary">{{ stats.progress }}%</span>)</span>
                </div>
                <div class="h-2.5 w-full bg-zinc-100 dark:bg-zinc-800 rounded-full overflow-hidden flex">
                    <div 
                        class="h-full bg-primary transition-all duration-1000 ease-out"
                        :style="`width: ${stats.progress}%`"
                    ></div>
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
                
                <div class="flex items-center gap-3 bg-zinc-200/50 dark:bg-zinc-800/50 p-1 rounded-xl border border-zinc-200 dark:border-zinc-800 w-full md:w-auto overflow-x-auto no-scrollbar">
                    <div class="flex items-center px-3 border-r border-zinc-300 dark:border-zinc-700 mr-1 shrink-0">
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
                        :class="['h-9 px-4 rounded-lg text-xs font-semibold transition-all shrink-0', statusFilter === 'all' ? 'bg-white dark:bg-zinc-700 text-zinc-900 dark:text-white shadow-sm' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white']"
                    >
                        All
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
                <!-- Unified Responsive Grid View -->
                <!-- Unified 3-Column Grid View -->
                <div ref="gridRef" class="grid grid-cols-3 gap-2.5 sm:gap-6 animate-in fade-in slide-in-from-bottom-4 duration-700">
                    <div v-for="student in filteredStudents" :key="student.id" 
                        data-student-card
                        :class="[
                            'relative overflow-hidden bg-white dark:bg-black rounded-[1.5rem] sm:rounded-[2rem] p-2.5 sm:p-5 shadow-lg border-2 transition-all duration-300 hover:shadow-2xl hover:-translate-y-1 group cursor-pointer flex flex-col',
                            student.attendance?.status === 'Present' ? 'border-emerald-500/20' : 
                            student.attendance?.status === 'Late' ? 'border-amber-500/20' :
                            student.attendance?.status === 'Absent' ? 'border-rose-500/20' : 'border-zinc-100 dark:border-zinc-800',
                            selectedStudents.includes(student.id) ? 'ring-2 ring-zinc-900 dark:ring-zinc-100 border-zinc-900 dark:border-zinc-100' : ''
                        ]"
                        @click="toggleStudentSelection(student.id)"
                    >
                        <!-- Silhouette background icon (Hidden on mobile grid for clarity) -->
                        <div class="hidden sm:block absolute -right-4 top-1/2 -translate-y-1/2 text-zinc-400/5 transition-transform duration-700 group-hover:scale-110 group-hover:-rotate-12 pointer-events-none z-10">
                            <User class="h-32 w-32" />
                        </div>

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
                                <div class="h-8 w-8 sm:h-12 sm:w-12 shrink-0 border border-zinc-100 dark:border-zinc-800 rounded-xl sm:rounded-2xl flex items-center justify-center bg-zinc-50 dark:bg-zinc-900 text-xs sm:text-lg font-black shadow-inner group-hover:scale-110 transition-transform duration-500">
                                    {{ student.name.charAt(0) }}
                                </div>
                                <div class="w-full max-w-[80px] sm:max-w-[140px]">
                                    <h4 class="font-black text-[9px] sm:text-base tracking-tight truncate sm:line-clamp-1 text-zinc-900 dark:text-zinc-100 leading-tight">{{ student.name }}</h4>
                                    <p class="hidden sm:block text-[10px] font-bold font-mono text-zinc-400 dark:text-zinc-500 mt-1 tracking-wider uppercase">{{ student.student_number || 'No ID' }}</p>
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

                        <!-- Info Pill (Desktop Only) -->
                        <div class="hidden sm:flex items-center justify-between gap-3 text-[10px] font-black text-zinc-500 dark:text-zinc-400 mb-6 bg-zinc-50/80 dark:bg-zinc-900/80 p-3 rounded-2xl border border-zinc-100 dark:border-zinc-800 relative z-20 shadow-inner">
                            <div class="flex items-center gap-2">
                                <Clock class="w-3.5 h-3.5" />
                                <span v-if="student.slot_start" class="tracking-widest">{{ student.slot_start }} - {{ student.slot_end }}</span>
                                <span v-else class="italic opacity-50 uppercase tracking-widest">No slot</span>
                            </div>
                            <div v-if="student.attendance?.scanned_at" class="flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-white dark:bg-black border border-zinc-100 dark:border-zinc-800 shadow-sm transition-all group-hover:border-zinc-300 dark:group-hover:border-zinc-600">
                                <span class="text-[8px] uppercase font-black opacity-40">Marked @</span>
                                <span class="text-zinc-900 dark:text-zinc-100 font-bold whitespace-nowrap">{{ new Date(student.attendance.scanned_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}</span>
                            </div>
                        </div>

                        <!-- Quick Actions Grid (Ultra-compact on mobile) -->
                        <div :class="['grid grid-cols-4 gap-1 sm:gap-2 relative z-20 transition-opacity mt-auto', student.slot_start ? '' : 'opacity-40 grayscale-[0.5]']" @click.stop>
                            <button 
                                v-for="status in ['Present', 'Late', 'Absent', 'Excused']"
                                :key="status"
                                @click="updateAttendance(student, status)"
                                :disabled="!student.slot_start"
                                :class="[
                                    'flex flex-col items-center justify-center py-1 sm:py-2.5 rounded-lg sm:rounded-2xl border transition-all active:scale-95 group/btn',
                                    student.attendance?.status === status 
                                        ? 'bg-zinc-900 border-zinc-900 text-white dark:bg-zinc-100 dark:border-zinc-100 dark:text-zinc-900 shadow-lg' 
                                        : 'bg-white dark:bg-zinc-900/40 border-zinc-100 dark:border-zinc-800 text-zinc-400 hover:border-zinc-300 dark:hover:border-zinc-600 hover:text-zinc-900 dark:hover:text-white'
                                ]"
                            >
                                <span class="text-[8px] sm:text-[10px] font-black uppercase tracking-tighter">{{ status.charAt(0) }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-if="filteredStudents.length === 0" class="col-span-full bg-zinc-50/50 dark:bg-zinc-900/20 rounded-[3rem] p-20 text-center border-2 border-dashed border-zinc-100 dark:border-zinc-800 animate-in fade-in zoom-in duration-700">
                        <div class="w-24 h-24 rounded-full bg-zinc-100 dark:bg-zinc-800/50 flex items-center justify-center mx-auto mb-8 shadow-inner">
                            <Users class="w-10 h-10 text-zinc-300 dark:text-zinc-700" stroke-width="1.5" />
                        </div>
                        <h3 class="font-black text-2xl tracking-tighter text-zinc-900 dark:text-zinc-100 leading-tight">No students found</h3>
                        <p class="text-zinc-500 dark:text-zinc-400 text-sm mt-3 font-medium max-w-[240px] mx-auto leading-relaxed">Try adjusting your filters or search terms to find what you're looking for.</p>
                        <Button variant="outline" size="sm" @click="searchQuery = ''; statusFilter = 'all'" class="mt-8 rounded-full px-8 h-12 font-black border-zinc-200 hover:bg-zinc-900 hover:text-white dark:hover:bg-zinc-100 dark:hover:text-zinc-900 transition-all">Clear Filters</Button>
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
                            <div class="h-10 w-10 rounded-lg bg-zinc-900 dark:bg-zinc-100 flex items-center justify-center text-white dark:text-zinc-900 font-black text-lg italic group-hover:scale-110 transition-transform">
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
    </AppLayout>
</template>
