<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { useDraggable, useWindowSize } from '@vueuse/core';
import type { BreadcrumbItem } from '@/types';
import { Chart as ChartJS, Title, Tooltip, Legend, ArcElement, CategoryScale } from 'chart.js';
import { Doughnut } from 'vue-chartjs';
import gsap from 'gsap';
import { Users, Search, Plus, LayoutGrid, Table, Clock, XCircle, Calendar, PieChart, AlertTriangle, RefreshCw, Trash2, Check, QrCode, Scan, Download, UserPlus, CheckCircle2, UserCheck, UserX } from 'lucide-vue-next';
import QRCode from 'qrcode';
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { useToast } from '@/composables/useToast';
import { useScanner } from '@/composables/useScanner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

ChartJS.register(Title, Tooltip, Legend, ArcElement, CategoryScale);
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import commentsRoutes from '@/routes/comments';
import ratingsRoutes from '@/routes/ratings';

type AttendanceRecord = {
    id: number;
    status: string;
    scanned_at: string;
    slot_start?: string;
    slot_end?: string;
};

type Student = {
    id: number;
    name: string;
    student_number: string;
    email?: string | null;
    section?: string | null;
    qr_token: string;
    schedule?: { day: string; start: string; end: string; subject_id?: string | null }[];
    today_statuses?: { status: string; time: string; subject_id?: string | number }[];
    latest_attendance?: {
        id: number;
        status: string;
        scanned_at: string;
        subject_id?: string | number;
    } | null;
    deleted_at?: string | null;
    attendance_percentage?: number;
    total_records?: number;
};

const daysOfWeek = [
    'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'
];

type PageProps = {
    students: Student[];
    trashedStudents: Student[];
    subjects: { id: number; name: string }[];
    attendanceStats?: { Present: number; Late: number; Absent: number; Excused: number; };
    atRiskCount: number;
};

const props = defineProps<PageProps>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
];

const page = usePage();

const students = computed(() => props.students ?? []);
const searchQuery = ref('');
const searchInputRef = ref<{ $el: HTMLInputElement } | null>(null);
const toast = useToast();
const { open: openScanner } = useScanner();

const statusFilter = ref<'Present' | 'Late' | 'Absent' | null>(null);

const filteredStudents = computed(() => {
    let result = students.value;
    
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        result = result.filter(s => 
            s.name.toLowerCase().includes(q) || 
            s.student_number.toLowerCase().includes(q) ||
            (s.section && s.section.toLowerCase().includes(q))
        );
    }
    
    if (statusFilter.value) {
        result = result.filter(s => s.today_statuses?.some(ts => ts.status === statusFilter.value));
    }
    
    return result;
});

const filteredTrashedStudents = computed(() => {
    if (!searchQuery.value) return props.trashedStudents ?? [];
    const q = searchQuery.value.toLowerCase();
    return (props.trashedStudents ?? []).filter(s => 
        s.name.toLowerCase().includes(q) || 
        s.student_number.toLowerCase().includes(q) ||
        (s.section && s.section.toLowerCase().includes(q))
    );
});

const userName = computed(() => {
    const user = page.props.auth.user;
    if (!user || !user.name) return 'User';
    return user.name.split(' ')[0];
});

const greeting = computed(() => {
    const hour = new Date().getHours();
    let text = 'Good morning';
    if (hour < 12) text = 'Good morning';
    else if (hour < 17) text = 'Good afternoon';
    else if (hour < 21) text = 'Good evening';
    else text = 'Good night';
    
    return text;
});

const greetingSubtext = computed(() => {
    if (props.atRiskCount > 0) {
        return `You have ${props.atRiskCount} student(s) with low attendance.`;
    }
    return 'Everything looks good today!';
});

const formattedCurrentDate = computed(() => {
    return new Date().toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
});

const stats = computed(() => {
    const activeStudents = students.value;
    return {
        total: activeStudents.length,
        present: activeStudents.filter(s => s.today_statuses?.some(ts => ts.status === 'Present')).length,
        late: activeStudents.filter(s => s.today_statuses?.some(ts => ts.status === 'Late')).length,
        absent: activeStudents.filter(s => s.today_statuses?.some(ts => ts.status === 'Absent')).length,
        trashed: props.trashedStudents?.length || 0
    };
});

const animatedStats = ref({
    total: 0,
    present: 0,
    late: 0,
    absent: 0
});

watch(stats, (newStats) => {
    gsap.to(animatedStats.value, {
        total: newStats.total,
        present: newStats.present,
        late: newStats.late,
        absent: newStats.absent,
        duration: 1.5,
        ease: 'power3.out',
        snap: { total: 1, present: 1, late: 1, absent: 1 }
    });
}, { deep: true, immediate: true });

const recentActivity = computed(() => {
    const activity: { name: string; status: string; time: string; subject_id?: string | number; sortTime: number }[] = [];
    
    students.value.forEach(s => {
        s.today_statuses?.forEach(ts => {
            const [time, period] = ts.time.split(' ');
            let [hours, minutes] = time.split(':').map(Number);
            if (period === 'PM' && hours !== 12) hours += 12;
            if (period === 'AM' && hours === 12) hours = 0;
            const sortTime = hours * 60 + minutes;
            
            activity.push({
                name: s.name,
                status: ts.status,
                time: ts.time,
                subject_id: ts.subject_id,
                sortTime: sortTime
            });
        });
    });
    
    return activity.sort((a, b) => b.sortTime - a.sortTime).slice(0, 5);
});

const chartData = computed(() => {
    return {
        labels: ['Present', 'Late', 'Absent', 'Excused'],
        datasets: [
            {
                backgroundColor: ['#09090b', '#3f3f46', '#a1a1aa', '#e4e4e7'],
                borderColor: ['#000000', '#27272a', '#71717a', '#d4d4d8'],
                borderWidth: 1,
                data: [
                    props.attendanceStats?.Present || 0,
                    props.attendanceStats?.Late || 0,
                    props.attendanceStats?.Absent || 0,
                    props.attendanceStats?.Excused || 0
                ]
            }
        ]
    };
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { 
            position: 'bottom' as const,
            labels: {
                color: '#8b8b8b'
            }
        }
    }
};

const atRiskStudents = computed(() => {
    return students.value
        .filter(s => s.attendance_percentage !== undefined && s.attendance_percentage < 80)
        .sort((a, b) => (a.attendance_percentage || 0) - (b.attendance_percentage || 0));
});

const createModalOpen = ref(false);
const editModalOpen = ref(false);
const showOnlyScheduledToday = ref(false);
const activeTab = ref<'active' | 'deleted'>('active');
const visibleStudents = computed(() => {
    const source = activeTab.value === 'active' ? filteredStudents.value : filteredTrashedStudents.value;
    if (showOnlyScheduledToday.value && activeTab.value === 'active') {
        return source.filter(s => isScheduledForToday(s));
    }
    return source;
});
const selectedStudent = ref<Student | null>(null);
const qrModalOpen = ref(false);

const studentInfoModalOpen = ref(false);
const infoStudent = ref<Student | null>(null);
const attendanceHistory = ref<AttendanceRecord[]>([]);
const historyExpanded = ref(false);
const historyLoading = ref(false);
const updatingRecordId = ref<number | null>(null);

const el = ref<HTMLElement | null>(null);
const { width: windowWidth, height: windowHeight } = useWindowSize();

const viewMode = ref<'table' | 'grid'>('table');
const importModalOpen = ref(false);
const importFile = ref<File | null>(null);
const importing = ref(false);

// Automatic switching for mobile
watch(windowWidth, (newWidth) => {
    if (newWidth < 768) {
        viewMode.value = 'grid';
    }
}, { immediate: true });

const { x, y, isDragging } = useDraggable(el, {
  initialValue: { x: window.innerWidth - 100, y: window.innerHeight - 100 },
  preventDefault: true,
  onEnd: () => {
      // Chathead snapping logic: snap to nearest left/right edge
      const margin = 20;
      const buttonWidth = 100;
      const threshold = windowWidth.value / 2;
      
      if (x.value < threshold) {
          x.value = margin;
      } else {
          x.value = windowWidth.value - buttonWidth - margin;
      }
  }
});

// Boundary and resize handling
watch([windowWidth, windowHeight], ([newW, newH]) => {
    // Keep within viewport with margins
    const margin = 20;
    const buttonWidth = 100;
    const buttonHeight = 56;
    
    if (x.value > newW - buttonWidth - margin) x.value = newW - buttonWidth - margin;
    if (x.value < margin) x.value = margin;
    if (y.value > newH - buttonHeight - margin) y.value = newH - buttonHeight - margin;
    if (y.value < margin) y.value = margin;
}, { immediate: true });

const handleScanClick = () => {
    // Global scanner is handled via the bottom nav or sidebar
};

function handleFileChange(event: Event) {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        importFile.value = target.files[0];
    }
}

async function submitImport() {
    if (!importFile.value) return;

    importing.value = true;
    const formData = new FormData();
    formData.append('file', importFile.value);

    router.post('/students/import', formData, {
        onSuccess: () => {
            importModalOpen.value = false;
            importFile.value = null;
            toast.success('Students imported successfully');
        },
        onError: (errors) => {
            toast.error(errors.file || 'Error importing students');
        },
        onFinish: () => {
            importing.value = false;
        },
    });
}

// Group attendance records by local date (most-recent date first)
const groupedAttendanceHistory = computed(() => {
    const groups: { date: string; label: string; records: AttendanceRecord[] }[] = [];
    const seen = new Map<string, AttendanceRecord[]>();

    const list = historyExpanded.value
        ? attendanceHistory.value
        : attendanceHistory.value.slice(0, 10);

    for (const record of list) {
        const d = new Date(record.scanned_at);
        const key = d.toLocaleDateString();
        if (!seen.has(key)) {
            const isToday = key === new Date().toLocaleDateString();
            const isYesterday = key === new Date(Date.now() - 86400000).toLocaleDateString();
            const label = isToday ? 'Today' : isYesterday ? 'Yesterday' : key;
            seen.set(key, []);
            groups.push({ date: key, label, records: seen.get(key)! });
        }
        seen.get(key)!.push(record);
    }
    return groups;
});

const todayDayName = new Date().toLocaleDateString('en-US', { weekday: 'long' });

function isScheduledForToday(student: Student) {
    return student.schedule?.some(s => s.day === todayDayName) ?? false;
}

const name = ref('');
const studentNumber = ref('');
const email = ref('');
const section = ref('');
const schedules = ref<{ day: string; start: string; end: string; subject_id: string }[]>([
    { day: 'Monday', start: '', end: '', subject_id: '' },
]);

const editName = ref('');
const editStudentNumber = ref('');
const editEmail = ref('');
const editSection = ref('');
const editSchedules = ref<{ day: string; start: string; end: string; subject_id: string }[]>([
    { day: 'Monday', start: '', end: '', subject_id: '' },
]);
const editingStudentId = ref<number | null>(null);

const submitting = ref(false);
const formErrors = ref<Record<string, string[]>>({});

const cardsRef = ref<HTMLDivElement | null>(null);
const tableRef = ref<HTMLDivElement | null>(null);
const studentsGridRef = ref<HTMLDivElement | null>(null);
const studentsTableBodyRef = ref<HTMLTableSectionElement | null>(null);

function animateStudents() {
    nextTick(() => {
        const targets = viewMode.value === 'grid' 
            ? studentsGridRef.value?.querySelectorAll('[data-student-card]')
            : studentsTableBodyRef.value?.querySelectorAll('tr');

        if (!targets || targets.length === 0) return;

        gsap.killTweensOf(targets);
        
        if (viewMode.value === 'grid') {
            gsap.fromTo(targets, 
                { opacity: 0, y: 30, scale: 0.9, filter: 'blur(8px)' },
                { 
                    opacity: 1, 
                    y: 0, 
                    scale: 1, 
                    filter: 'blur(0px)',
                    duration: 0.6, 
                    stagger: 0.05, 
                    ease: 'back.out(1.2)',
                    clearProps: 'all'
                }
            );
        } else {
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
        }
    });
}

watch([searchQuery, activeTab, viewMode, statusFilter], () => {
    animateStudents();
});

const confirmModalOpen = ref(false);
const confirmTitle = ref('');
const confirmDescription = ref('');
const confirmAction = ref<(() => void) | null>(null);
const confirmIsDestructive = ref(false);

function showConfirm(title: string, description: string, action: () => void, isDestructive = false) {
    confirmTitle.value = title;
    confirmDescription.value = description;
    confirmAction.value = action;
    confirmIsDestructive.value = isDestructive;
    confirmModalOpen.value = true;
}

function handleConfirm() {
    if (confirmAction.value) {
        confirmAction.value();
    }
    confirmModalOpen.value = false;
    confirmAction.value = null;
}
function resetForm() {
    name.value = '';
    studentNumber.value = '';
    email.value = '';
    section.value = '';
    schedules.value = [{ day: 'Monday', start: '', end: '', subject_id: '' }];
    formErrors.value = {};
}

function openCreateModal() {
    resetForm();
    createModalOpen.value = true;
}

function closeCreateModal() {
    createModalOpen.value = false;
}

async function submitStudent() {
    submitting.value = true;
    formErrors.value = {};

    router.post(
        '/students',
        {
            name: name.value,
            student_number: studentNumber.value,
            email: email.value || null,
            section: section.value || null,
            schedule: schedules.value,
        },
        {
            onSuccess: () => {
                closeCreateModal();
                toast.success('Student added successfully');
            },
            onError: (errors) => {
                formErrors.value = errors as any;
                toast.error('Failed to add student');
            },
            onFinish: () => {
                submitting.value = false;
            },
            preserveScroll: true,
        },
    );
}

function addScheduleSlot() {
    schedules.value.push({ day: 'Monday', start: '', end: '', subject_id: '' });
}

function removeScheduleSlot(index: number) {
    if (schedules.value.length === 1) return;
    schedules.value.splice(index, 1);
}

function deleteStudent(id: number) {
    showConfirm(
        'Delete Student?',
        'Are you sure you want to move this student to the Trash? You can restore them later.',
        () => {
            router.delete(`/students/${id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    closeStudentInfoModal();
                }
            });
        },
        true
    );
}

function restoreStudent(id: number) {
    router.post(`/students/${id}/restore`, {}, {
        preserveScroll: true,
    });
}

function forceDeleteStudent(id: number) {
    showConfirm(
        'Permanently Delete?',
        'This will permanently delete the student and all their records. This action cannot be undone. Are you sure?',
        () => {
            router.delete(`/students/${id}/force-delete`, {
                preserveScroll: true,
            });
        },
        true
    );
}

function openStudentInfoModal(student: Student) {
    infoStudent.value = student;
    attendanceHistory.value = [];
    historyExpanded.value = false;
    historyLoading.value = true;
    studentInfoModalOpen.value = true;

    window.fetch(`/students/${student.id}/attendance`, {
        credentials: 'same-origin',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN':
                (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)
                    ?.content ?? '',
        },
    })
        .then((r) => r.json())
        .then((data: { history: AttendanceRecord[] }) => {
            attendanceHistory.value = data.history;
        })
        .catch(() => {})
        .finally(() => {
            historyLoading.value = false;
        });
}

function closeStudentInfoModal() {
    studentInfoModalOpen.value = false;
    infoStudent.value = null;
    attendanceHistory.value = [];
}

function openEditFromInfo() {
    const student = infoStudent.value;
    if (!student) return;
    closeStudentInfoModal();
    // small delay so modal closes first
    setTimeout(() => openEditModal(student), 80);
}

function openQrFromInfo() {
    const student = infoStudent.value;
    if (!student) return;
    closeStudentInfoModal();
    // small delay so modal closes first
    setTimeout(() => openQrModal(student), 80);
}

function openPrintCardsFromInfo() {
    const student = infoStudent.value;
    if (!student) return;
    window.open(`/students/print-cards?ids=${student.id}`, '_blank', 'noopener,noreferrer');
}

function openEditModal(student: Student) {
    editingStudentId.value = student.id;
    editName.value = student.name;
    editStudentNumber.value = student.student_number;
    editEmail.value = student.email || '';
    editSection.value = student.section || '';
    editSchedules.value =
        student.schedule && student.schedule.length > 0
            ? student.schedule.map((s) => ({ day: s.day || 'Monday', start: s.start, end: s.end, subject_id: s.subject_id?.toString() || '' }))
            : [{ day: 'Monday', start: '', end: '', subject_id: '' }];
    formErrors.value = {};
    editModalOpen.value = true;
}

function formatDateTime(iso: string) {
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return iso;
    return `${d.toLocaleDateString()} ${d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', hour12: true })}`;
}

function getSubjectName(subjectId: string | number | null | undefined): string {
    if (!subjectId) return 'N/A';
    const subject = props.subjects?.find(s => s.id.toString() === subjectId.toString());
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

function updateLatestStatus(student: Student, status: string) {
    if (!student.latest_attendance?.id) return;

    router.put(
        `/attendance/${student.latest_attendance.id}`,
        { status },
        {
            preserveScroll: true,
            onSuccess: () => {
                router.visit(dashboard().url, {
                    only: ['students'],
                    preserveScroll: true,
                });
            },
        },
    );
}

function updateHistoryStatus(recordId: number, status: string) {
    if (updatingRecordId.value) return;
    updatingRecordId.value = recordId;

    router.put(
        `/attendance/${recordId}`,
        { status },
        {
            preserveScroll: true,
            onSuccess: () => {
                // Update local history
                const record = attendanceHistory.value.find((r) => r.id === recordId);
                if (record) {
                    record.status = status;
                }
            },
            onFinish: () => {
                updatingRecordId.value = null;
            },
        },
    );
}

function closeEditModal() {
    editModalOpen.value = false;
    editingStudentId.value = null;
}

function addEditScheduleSlot() {
    editSchedules.value.push({ day: 'Monday', start: '', end: '', subject_id: '' });
}

function removeEditScheduleSlot(index: number) {
    if (editSchedules.value.length === 1) return;
    editSchedules.value.splice(index, 1);
}

async function submitEditStudent() {
    if (!editingStudentId.value) return;

    submitting.value = true;
    formErrors.value = {};

    router.put(
        `/students/${editingStudentId.value}`,
        {
            name: editName.value,
            student_number: editStudentNumber.value,
            email: editEmail.value || null,
            section: editSection.value || null,
            schedule: editSchedules.value,
        },
        {
            onSuccess: () => {
                closeEditModal();
                toast.success('Student updated');
            },
            onError: (errors) => {
                formErrors.value = errors as any;
                toast.error('Update failed');
            },
            onFinish: () => {
                submitting.value = false;
            },
            preserveScroll: true,
        },
    );
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
                router.visit(dashboard().url, {
                    only: ['students'],
                    preserveScroll: true,
                    onSuccess: (page) => {
                        const updated = (page.props as unknown as PageProps)
                            .students.find(
                                (s) => s.id === selectedStudent.value?.id,
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

function qrData(token: string) {
    return token;
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

function openPrintCards() {
    if (!selectedStudent.value) return;
    window.open(`/students/print-cards?ids=${selectedStudent.value.id}`, '_blank', 'noopener,noreferrer');
}

onMounted(() => {
    // 1. Enter and Hover Animations for Cards
    if (cardsRef.value) {
        const cards = cardsRef.value.querySelectorAll<HTMLElement>('[data-card]');
        
        // Wrap with a perspective container
        gsap.set(cardsRef.value, { perspective: 1000 });

        // Ensure cards are visible before animation starts if something fails
        gsap.set(cards, { opacity: 1, visibility: 'visible' });

        gsap.from(cards, {
            opacity: 0,
            y: 30,
            rotationX: -15,
            z: -20,
            duration: 0.8,
            stagger: 0.1,
            ease: 'power2.out',
            clearProps: 'all' // Crucial: removes GSAP inline styles after completion
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

    // 3. Status Badge Pulsing
    gsap.to('.status-pulse', {
        scale: 1.05,
        opacity: 0.8,
        duration: 1.5,
        repeat: -1,
        yoyo: true,
        ease: 'sine.inOut'
    });

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

    // Keyboard shortcut for search
    const handleKeydown = (e: KeyboardEvent) => {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            const inputEl = searchInputRef.value?.$el;
            if (inputEl) {
                inputEl.focus();
            }
        }
    };
    window.addEventListener('keydown', handleKeydown);

    onUnmounted(() => {
        window.removeEventListener('keydown', handleKeydown);
    });

    // Initial student animation
    animateStudents();
});
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 sm:gap-6 overflow-x-hidden p-3 sm:p-4 pb-20 md:pb-4">
            <!-- Welcome Header -->
            <div class="flex items-center justify-between gap-4 px-1">
                <div class="flex flex-col gap-1">
                    <h1 class="text-3xl font-serif font-bold tracking-tight">
                        {{ greeting }}, {{ userName }}!
                    </h1>
                    <p class="text-sm text-muted-foreground font-medium">
                        {{ greetingSubtext }}
                    </p>
                    <div class="flex items-center gap-2 text-[10px] text-muted-foreground mt-1">
                        <Calendar class="h-3.5 w-3.5" />
                        <span>{{ formattedCurrentDate }}</span>
                    </div>
                </div>

                <!-- Desktop Scan Button -->
                <Button 
                    variant="outline" 
                    size="lg" 
                    class="hidden h-12 items-center gap-3 rounded-2xl border-zinc-200/50 bg-white px-5 text-sm font-bold shadow-sm transition-all hover:bg-zinc-50 hover:text-zinc-900 hover:shadow-md active:scale-95 dark:border-zinc-800 dark:bg-zinc-950 dark:hover:bg-zinc-900 dark:hover:text-zinc-50 sm:flex group"
                    @click="openScanner"
                >
                    <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900 group-hover:scale-110 transition-transform">
                        <QrCode class="size-4" />
                    </div>
                    <span>Scan QR Code</span>
                </Button>
            </div>

            <div
                ref="cardsRef"
                class="grid gap-3 sm:gap-4 lg:gap-6 grid-cols-2 lg:grid-cols-4"
            >
                <!-- Total Students Card -->
                <button
                    @click="statusFilter = null"
                    data-card
                    class="group relative overflow-hidden rounded-2xl p-3 sm:p-5 text-left shadow-sm w-full transition-colors flex items-center justify-between"
                    :class="!statusFilter ? 'bg-zinc-50 dark:bg-zinc-900/50 border-2 border-zinc-900 dark:border-white text-zinc-900 dark:text-white ring-2 ring-zinc-900/20 dark:ring-white/20' : 'bg-white dark:bg-black border border-zinc-200 dark:border-zinc-800 text-zinc-900 dark:text-white hover:bg-zinc-50 dark:hover:bg-zinc-900'"
                >
                    <Users class="absolute right-[-5%] top-1/2 -translate-y-1/2 h-20 w-20 sm:h-24 sm:w-24 text-zinc-900/[0.03] dark:text-white/[0.03] transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-6 pointer-events-none" />
                    <div class="relative w-full z-10">
                        <p class="text-[9px] sm:text-[10px] font-bold uppercase tracking-[0.15em] sm:tracking-[0.2em] text-zinc-500 dark:text-zinc-400">
                            Total Students
                        </p>
                        <p class="mt-1 text-3xl sm:text-4xl font-light tracking-tight text-zinc-900 dark:text-white drop-shadow-sm flex items-center justify-between">
                            {{ searchQuery ? filteredStudents.length : Math.round(animatedStats.total) }}
                            <CheckCircle2 v-if="!statusFilter" class="w-5 h-5 text-zinc-900 dark:text-white opacity-50" />
                        </p>
                    </div>
                </button>

                <!-- Present Today Card -->
                <button
                    @click="statusFilter = statusFilter === 'Present' ? null : 'Present'"
                    data-card
                    class="group relative overflow-hidden rounded-2xl p-3 sm:p-5 text-left shadow-sm w-full transition-colors flex items-center justify-between"
                    :class="statusFilter === 'Present' ? 'bg-zinc-50 dark:bg-zinc-900/50 border-2 border-zinc-900 dark:border-white text-zinc-900 dark:text-white ring-2 ring-zinc-900/20 dark:ring-white/20' : 'bg-white dark:bg-black border border-zinc-200 dark:border-zinc-800 text-zinc-900 dark:text-white hover:bg-zinc-50 dark:hover:bg-zinc-900'"
                >
                    <UserCheck class="absolute right-[-5%] top-1/2 -translate-y-1/2 h-20 w-20 sm:h-24 sm:w-24 text-zinc-900/[0.03] dark:text-white/[0.03] transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-6 pointer-events-none" />
                    <div class="relative w-full z-10">
                        <p class="text-[9px] sm:text-[10px] font-bold uppercase tracking-[0.15em] sm:tracking-[0.2em] text-zinc-500 dark:text-zinc-400">
                            Present Today
                        </p>
                        <p class="mt-1 text-3xl sm:text-4xl font-light tracking-tight text-zinc-900 dark:text-white drop-shadow-sm flex items-center justify-between">
                            {{ Math.round(animatedStats.present) }}
                            <CheckCircle2 v-if="statusFilter === 'Present'" class="w-5 h-5 text-zinc-900 dark:text-white opacity-50" />
                        </p>
                    </div>
                </button>

                <!-- Late Today Card -->
                <button
                    @click="statusFilter = statusFilter === 'Late' ? null : 'Late'"
                    data-card
                    class="group relative overflow-hidden rounded-2xl p-3 sm:p-5 text-left shadow-sm w-full transition-colors flex items-center justify-between"
                    :class="statusFilter === 'Late' ? 'bg-zinc-50 dark:bg-zinc-900/50 border-2 border-zinc-900 dark:border-white text-zinc-900 dark:text-white ring-2 ring-zinc-900/20 dark:ring-white/20' : 'bg-white dark:bg-black border border-zinc-200 dark:border-zinc-800 text-zinc-900 dark:text-white hover:bg-zinc-50 dark:hover:bg-zinc-900'"
                >
                    <Clock class="absolute right-[-5%] top-1/2 -translate-y-1/2 h-20 w-20 sm:h-24 sm:w-24 text-zinc-900/[0.03] dark:text-white/[0.03] transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-6 pointer-events-none" />
                    <div class="relative w-full z-10">
                        <p class="text-[9px] sm:text-[10px] font-bold uppercase tracking-[0.15em] sm:tracking-[0.2em] text-zinc-500 dark:text-zinc-400">
                            Late Today
                        </p>
                        <p class="mt-1 text-3xl sm:text-4xl font-light tracking-tight text-zinc-900 dark:text-white drop-shadow-sm flex items-center justify-between">
                            {{ Math.round(animatedStats.late) }}
                            <CheckCircle2 v-if="statusFilter === 'Late'" class="w-5 h-5 text-zinc-900 dark:text-white opacity-50" />
                        </p>
                    </div>
                </button>

                <!-- Absent Today Card -->
                <button
                    @click="statusFilter = statusFilter === 'Absent' ? null : 'Absent'"
                    data-card
                    class="group relative overflow-hidden rounded-2xl p-3 sm:p-5 text-left shadow-sm w-full transition-colors flex items-center justify-between"
                    :class="statusFilter === 'Absent' ? 'bg-zinc-50 dark:bg-zinc-900/50 border-2 border-zinc-900 dark:border-white text-zinc-900 dark:text-white ring-2 ring-zinc-900/20 dark:ring-white/20' : 'bg-white dark:bg-black border border-zinc-200 dark:border-zinc-800 text-zinc-900 dark:text-white hover:bg-zinc-50 dark:hover:bg-zinc-900'"
                >
                    <UserX class="absolute right-[-5%] top-1/2 -translate-y-1/2 h-20 w-20 sm:h-24 sm:w-24 text-zinc-900/[0.03] dark:text-white/[0.03] transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-6 pointer-events-none" />
                    <div class="relative w-full z-10">
                        <p class="text-[9px] sm:text-[10px] font-bold uppercase tracking-[0.15em] sm:tracking-[0.2em] text-zinc-500 dark:text-zinc-400">
                            Absent Today
                        </p>
                        <p class="mt-1 text-3xl sm:text-4xl font-light tracking-tight text-zinc-900 dark:text-white drop-shadow-sm flex items-center justify-between">
                            {{ Math.round(animatedStats.absent) }}
                            <CheckCircle2 v-if="statusFilter === 'Absent'" class="w-5 h-5 text-zinc-900 dark:text-white opacity-50" />
                        </p>
                    </div>
                </button>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-4 gap-6 items-start">
                <div class="xl:col-span-1 flex flex-col gap-6 order-last">
                    
                    <!-- Students at Risk -->
                    <div v-if="atRiskStudents.length > 0" class="overflow-hidden rounded-2xl border border-rose-200 dark:border-rose-900/50 bg-white dark:bg-black shadow-xl">
                        <div class="border-b border-rose-100 dark:border-rose-900/30 p-4 flex items-center justify-between bg-rose-50 dark:bg-rose-950/20">
                            <h2 class="text-[10px] font-bold uppercase tracking-wider flex items-center gap-2 text-rose-600 dark:text-rose-400">
                                <AlertTriangle class="h-3.5 w-3.5" />
                                Students at Risk
                            </h2>
                            <span class="text-[10px] bg-rose-100 dark:bg-rose-900/50 text-rose-600 px-2 py-0.5 rounded-full font-bold">{{ atRiskStudents.length }}</span>
                        </div>
                        <div class="p-0">
                            <div class="divide-y divide-zinc-200 dark:divide-zinc-800 max-h-64 overflow-y-auto">
                                <div v-for="student in atRiskStudents" :key="'risk-' + student.id" class="flex items-center justify-between p-3.5 hover:bg-zinc-50 dark:hover:bg-zinc-900/50 transition-colors cursor-pointer" @click="openStudentInfoModal(student)">
                                    <div class="flex flex-col overflow-hidden">
                                        <span class="text-xs font-semibold text-zinc-900 dark:text-white truncate" :title="student.name">{{ student.name }}</span>
                                        <span class="text-[10px] text-zinc-500">{{ student.student_number }}</span>
                                    </div>
                                    <div class="flex flex-col items-end gap-1 shrink-0">
                                        <span class="text-xs font-bold text-rose-600 dark:text-rose-400">{{ student.attendance_percentage }}%</span>
                                        <span class="text-[9px] text-zinc-400">Attendance</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Attendance Overview Chart -->
                    <div v-if="props.attendanceStats" class="overflow-hidden rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-black shadow-xl">
                        <div class="border-b border-zinc-200 dark:border-zinc-800 p-4 flex items-center justify-between bg-zinc-50 dark:bg-zinc-900/50">
                            <h2 class="text-[10px] font-bold uppercase tracking-wider flex items-center gap-2 text-zinc-500 dark:text-zinc-400">
                                <PieChart class="h-3.5 w-3.5" />
                                Overall Attendance
                            </h2>
                        </div>
                        <div class="p-4 h-64 flex items-center justify-center relative">
                            <Doughnut v-if="(props.attendanceStats?.Present || props.attendanceStats?.Late || props.attendanceStats?.Absent || props.attendanceStats?.Excused)" :data="chartData" :options="chartOptions" />
                            <div v-else class="text-xs text-muted-foreground italic absolute">No data yet</div>
                        </div>
                    </div>

                    <!-- Recent Activity Feed -->
                    <div class="overflow-hidden rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-black shadow-xl">
                        <div class="border-b border-zinc-200 dark:border-zinc-800 p-4 flex items-center justify-between bg-zinc-50 dark:bg-zinc-900/50">
                            <h2 class="text-[10px] font-bold uppercase tracking-wider flex items-center gap-2 text-zinc-500 dark:text-zinc-400">
                                <Scan class="h-3.5 w-3.5" />
                                Live Scan Feed
                            </h2>
                            <span class="text-[10px] text-zinc-400 dark:text-zinc-500 italic">Last 5</span>
                        </div>
                        <div class="p-0">
                            <div v-if="recentActivity.length === 0" class="text-center py-8 text-sm text-zinc-500 dark:text-zinc-400 italic">
                                No activity today.
                            </div>
                            <div v-else class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                <div v-for="(act, i) in recentActivity" :key="i" class="flex items-center justify-between p-3.5 hover:bg-zinc-50 dark:hover:bg-zinc-900/50 transition-colors group">
                                    <div class="flex items-center gap-3">
                                        <div class="h-8 w-8 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center shrink-0 group-hover:bg-zinc-200 dark:group-hover:bg-zinc-700 transition-colors border border-zinc-200 dark:border-zinc-700">
                                            <span class="text-[10px] font-bold text-zinc-900 dark:text-white">{{ act.name.charAt(0) }}</span>
                                        </div>
                                        <div class="flex flex-col overflow-hidden">
                                            <span class="text-xs font-semibold group-hover:text-zinc-600 dark:group-hover:text-zinc-300 transition-colors text-zinc-900 dark:text-white truncate">{{ act.name }}</span>
                                            <div class="flex items-center gap-1.5 mt-0.5">
                                                <span 
                                                    class="text-[9px] px-1.5 py-0.5 rounded-full font-bold uppercase tracking-widest"
                                                    :class="{
                                                        'bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 border border-zinc-200 dark:border-zinc-700': act.status === 'Present',
                                                        'bg-zinc-200 dark:bg-zinc-700 text-zinc-900 dark:text-white border border-zinc-300 dark:border-zinc-600': act.status === 'Late',
                                                        'bg-zinc-300 dark:bg-zinc-600 text-zinc-900 dark:text-white border border-zinc-400 dark:border-zinc-500': act.status === 'Time Out',
                                                        'bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900': act.status === 'Absent'
                                                    }"
                                                >
                                                    {{ act.status }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex flex-col items-end gap-1 shrink-0">
                                        <span class="text-[10px] font-bold text-zinc-500 dark:text-zinc-400">{{ act.time }}</span>
                                        <span v-if="act.subject_id" class="text-[9px] text-zinc-400 dark:text-zinc-500 line-clamp-1 max-w-[80px] text-right" :title="getSubjectName(act.subject_id)">
                                            {{ getSubjectName(act.subject_id) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="xl:col-span-3 order-first">
                    <div
                        ref="tableRef"
                        class="relative overflow-hidden rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-black shadow-xl"
                    >
                        <div class="flex flex-col border-b border-zinc-200 dark:border-zinc-800 p-4 sm:p-6 gap-4 bg-zinc-50 dark:bg-zinc-900/50">
                            <!-- Title row -->
                            <div class="flex items-center justify-between gap-2">
                                <h2 class="text-lg sm:text-2xl font-serif font-bold tracking-tight text-foreground">
                                    Today's Attendance Status
                                </h2>
                            </div>

                            <!-- Filter controls: stacks on mobile -->
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <!-- Checkbox filter -->
                                <div class="flex items-center gap-2 bg-muted/50 px-3 py-1.5 rounded-full border border-zinc-200 dark:border-zinc-800 self-start">
                                    <input 
                                        type="checkbox" 
                                        id="today-toggle" 
                                        v-model="showOnlyScheduledToday" 
                                        class="w-3.5 h-3.5 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-900 shrink-0"
                                    />
                                    <label for="today-toggle" class="text-[10px] font-bold uppercase tracking-wider text-zinc-600 dark:text-zinc-400 cursor-pointer whitespace-nowrap">
                                        Only Scheduled Today
                                    </label>
                                </div>

                                <!-- Tabs -->
                                <div class="flex rounded-lg bg-zinc-200/50 dark:bg-zinc-800/50 p-1 border border-zinc-200 dark:border-zinc-800 self-start overflow-x-auto max-w-full">
                                    <button
                                        class="rounded-md px-3 py-1 text-xs font-medium transition-all whitespace-nowrap shrink-0"
                                        :class="activeTab === 'active' ? 'bg-white dark:bg-zinc-700 text-zinc-900 dark:text-white shadow-sm' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white'"
                                        @click="activeTab = 'active'"
                                    >
                                        Active Students
                                    </button>
                                    <button
                                        class="rounded-md px-3 py-1 text-xs font-medium transition-all whitespace-nowrap shrink-0"
                                        :class="activeTab === 'deleted' ? 'bg-white dark:bg-zinc-700 text-zinc-900 dark:text-white shadow-sm' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white'"
                                        @click="activeTab = 'deleted'"
                                    >
                                        Deleted ({{ props.trashedStudents.length }})
                                    </button>
                                </div>
                            </div>

                            <!-- Search + actions row -->
                            <div class="flex items-center gap-2 w-full">
                                <div class="relative flex-1 min-w-0 group">
                                    <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-zinc-500 dark:text-zinc-400 group-focus-within:text-zinc-900 dark:group-focus-within:text-white transition-colors" />
                                    <Input
                                        ref="searchInputRef"
                                        v-model="searchQuery"
                                        type="search"
                                        placeholder="Search students..."
                                        class="pl-9 pr-12 h-9 text-xs rounded-full bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 focus-visible:ring-1 focus-visible:ring-zinc-400 dark:focus-visible:ring-zinc-600 text-zinc-900 dark:text-white placeholder:text-zinc-500 dark:placeholder:text-zinc-400 shadow-sm"
                                    />
                                    <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center">
                                        <kbd class="hidden sm:inline-flex h-5 items-center gap-1 rounded border border-zinc-200 dark:border-zinc-700 bg-zinc-100 dark:bg-zinc-800 px-1.5 font-mono text-[10px] font-medium text-zinc-500 dark:text-zinc-400 opacity-100 transition-opacity">
                                            <span class="text-xs">⌘</span>K
                                        </kbd>
                                    </div>
                                </div>
                            
                                <!-- View Switcher -->
                                <div class="hidden md:flex rounded-full bg-zinc-200/50 dark:bg-zinc-800/50 p-1 shrink-0 border border-zinc-200 dark:border-zinc-800">
                                    <button
                                        class="rounded-full p-1.5 transition-all outline-none"
                                        :class="viewMode === 'table' ? 'bg-white dark:bg-zinc-700 text-zinc-900 dark:text-white shadow-sm' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white'"
                                        title="Table View"
                                        @click="viewMode = 'table'"
                                    >
                                        <Table class="h-4 w-4" />
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

                                <Button 
                                    v-if="activeTab === 'active'"
                                    variant="outline"
                                    size="sm" 
                                    class="rounded-full shrink-0 gap-1.5 border-zinc-200 dark:border-zinc-800" 
                                    @click="importModalOpen = true"
                                >
                                    <UserPlus class="h-4 w-4" />
                                    <span class="hidden sm:inline">Import</span>
                                </Button>

                                <Button 
                                    v-if="activeTab === 'active'"
                                    size="sm" 
                                    class="rounded-full shrink-0 gap-1.5 bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 hover:bg-zinc-800 dark:hover:bg-zinc-200" 
                                    @click="openCreateModal"
                                >
                                    <Plus class="h-4 w-4" />
                                    <span class="hidden sm:inline">Add Student</span>
                                </Button>
                            </div>
                        </div>

                <div v-if="viewMode === 'table'" class="max-h-[520px] overflow-x-auto overflow-y-auto w-full">
                    <table class="min-w-full text-left text-sm whitespace-nowrap">
                        <thead
                            class="sticky top-0 z-10 border-b border-zinc-200 dark:border-zinc-800 bg-white/95 dark:bg-black/95 backdrop-blur text-zinc-500 dark:text-zinc-400"
                        >
                            <tr>
                                <th class="px-2 lg:px-4 py-2 text-[10px] lg:text-xs font-semibold uppercase tracking-wider">
                                    Name
                                </th>
                                <th class="px-2 lg:px-4 py-2 text-[10px] lg:text-xs font-semibold uppercase tracking-wider">
                                    Student #
                                </th>
                                <th class="px-1 lg:px-2 py-2 text-[10px] lg:text-xs font-semibold uppercase tracking-wider text-center">
                                    Rate
                                </th>
                                <th class="px-1 lg:px-2 py-2 text-[10px] lg:text-xs font-semibold uppercase tracking-wider text-center">
                                    Present
                                </th>
                                <th class="px-1 lg:px-2 py-2 text-[10px] lg:text-xs font-semibold uppercase tracking-wider text-center">
                                    Late
                                </th>
                                <th class="px-1 lg:px-2 py-2 text-[10px] lg:text-xs font-semibold uppercase tracking-wider text-center">
                                    Time Out
                                </th>
                                <th class="px-1 lg:px-2 py-2 text-[10px] lg:text-xs font-semibold uppercase tracking-wider text-center">
                                    Absent
                                </th>
                                <th class="px-2 lg:px-4 py-2 text-[10px] lg:text-xs font-semibold uppercase tracking-wider">
                                    Section
                                </th>
                                <th class="px-2 lg:px-4 py-2 text-[10px] lg:text-xs font-semibold uppercase tracking-wider">
                                    Email
                                </th>
                            </tr>
                        </thead>
                        <tbody ref="studentsTableBodyRef" class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            <tr
                                v-if="visibleStudents.length === 0"
                            >
                                <td
                                    colspan="10"
                                    class="px-3 lg:px-4 py-8 lg:py-12 text-center"
                                >
                                    <div class="flex flex-col items-center justify-center space-y-3">
                                        <div class="rounded-full bg-zinc-50 dark:bg-zinc-900 p-4 border border-zinc-100 dark:border-zinc-800">
                                            <Search v-if="searchQuery" class="h-6 w-6 text-zinc-400" />
                                            <Users v-else class="h-6 w-6 text-zinc-400" />
                                        </div>
                                        <div class="max-w-[280px] mx-auto">
                                            <p v-if="searchQuery" class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                                No matches for "{{ searchQuery }}"
                                            </p>
                                            <p v-else-if="showOnlyScheduledToday && activeTab === 'active' && students.length > 0" class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                                No students scheduled for {{ todayDayName }}
                                            </p>
                                            <p v-else-if="activeTab === 'deleted'" class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                                Trash is empty
                                            </p>
                                            <p v-else class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                                No students found
                                            </p>
                                            <p class="text-xs text-zinc-500 mt-1">
                                                <span v-if="searchQuery">Try adjusting your search or filters to find what you're looking for.</span>
                                                <span v-else-if="showOnlyScheduledToday && activeTab === 'active' && students.length > 0">
                                                    Check another day or 
                                                    <button @click="showOnlyScheduledToday = false" class="text-zinc-900 dark:text-white font-semibold underline underline-offset-4 hover:text-zinc-600 transition-colors">
                                                        view all students
                                                    </button>.
                                                </span>
                                                <span v-else-if="activeTab === 'deleted'">Students you delete will appear here for 30 days before being permanently removed.</span>
                                                <span v-else>Get started by adding your first student to this subject's roster.</span>
                                            </p>
                                        </div>
                                        <Button v-if="!searchQuery && !showOnlyScheduledToday && activeTab === 'active'" variant="outline" size="sm" class="rounded-full mt-2" @click="createModalOpen = true">
                                            <Plus class="mr-2 h-3.5 w-3.5" />
                                            Add Student
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                            <tr
                                v-for="student in visibleStudents"
                                :key="student.id"
                                class="transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-900/50 cursor-pointer text-zinc-900 dark:text-zinc-100"
                                @click="activeTab === 'active' ? openStudentInfoModal(student) : null"
                            >
                                <td class="px-2 lg:px-4 py-2 text-xs lg:text-sm font-medium">
                                    <span class="flex items-center gap-1.5 flex-wrap">
                                        <span class="truncate max-w-[120px] lg:max-w-none">{{ student.name }}</span>
                                        <div 
                                            v-if="activeTab === 'active'"
                                            class="h-1 w-1 lg:h-1.5 lg:w-1.5 rounded-full status-pulse shrink-0"
                                            :class="[
                                                student.latest_attendance?.status === 'Present'  ? 'bg-zinc-900 dark:bg-white shadow-sm' :
                                                student.latest_attendance?.status === 'Late'     ? 'bg-zinc-500 dark:bg-zinc-400' :
                                                student.latest_attendance?.status === 'Time Out' ? 'bg-zinc-300 dark:bg-zinc-600' :
                                                'bg-zinc-200 dark:bg-zinc-800'
                                            ]"
                                        ></div>
                                    </span>
                                </td>
                                <td class="px-2 lg:px-4 py-2 text-[10px] lg:text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ student.student_number }}
                                </td>
                                <!-- Attendance Rate Badge -->
                                <td class="px-1 lg:px-2 py-2 text-center" @click.stop>
                                    <Badge 
                                        variant="outline" 
                                        class="text-[9px] lg:text-[10px] font-bold px-1.5 py-0"
                                        :class="{
                                            'border-rose-200 bg-rose-50 text-rose-600 dark:border-rose-900/50 dark:bg-rose-950/20 dark:text-rose-400': (student.attendance_percentage ?? 100) < 80,
                                            'border-zinc-200 bg-zinc-50 text-zinc-600 dark:border-zinc-800 dark:bg-zinc-900/50 dark:text-zinc-400': (student.attendance_percentage ?? 100) >= 80
                                        }"
                                    >
                                        {{ student.attendance_percentage ?? 100 }}%
                                    </Badge>
                                </td>
                                <!-- Status indicator columns (active students) -->
                                <template v-if="activeTab === 'active'">
                                    <!-- Present -->
                                    <td class="px-1 lg:px-2 py-2 text-center" @click.stop>
                                        <div v-if="student.today_statuses?.some(s => s.status === 'Present')" class="flex flex-col items-center gap-1">
                                            <template v-for="s in student.today_statuses?.filter(st => st.status === 'Present')">
                                                <span class="inline-flex items-center gap-0.5 lg:gap-1 text-[9px] lg:text-[10px] font-bold text-zinc-900 dark:text-zinc-100 bg-zinc-100 dark:bg-zinc-800 px-1.5 lg:px-2 py-0.5 rounded-full border border-zinc-200 dark:border-zinc-700 whitespace-nowrap">
                                                    <CheckCircle2 class="w-2.5 h-2.5 lg:w-3 lg:h-3 shrink-0" />
                                                    {{ s.time }}
                                                </span>
                                            </template>
                                        </div>
                                        <span v-else class="inline-block w-3 lg:w-4 h-px bg-zinc-200 dark:bg-zinc-800"></span>
                                    </td>
                                    <!-- Late -->
                                    <td class="px-1 lg:px-2 py-2 text-center" @click.stop>
                                        <div v-if="student.today_statuses?.some(s => s.status === 'Late')" class="flex flex-col items-center gap-1">
                                            <template v-for="s in student.today_statuses?.filter(st => st.status === 'Late')">
                                                <span class="inline-flex items-center gap-0.5 lg:gap-1 text-[9px] lg:text-[10px] font-bold text-zinc-900 dark:text-white bg-zinc-200 dark:bg-zinc-700 px-1.5 lg:px-2 py-0.5 rounded-full border border-zinc-300 dark:border-zinc-600 whitespace-nowrap">
                                                    <AlertCircle class="w-2.5 h-2.5 lg:w-3 lg:h-3 shrink-0" />
                                                    {{ s.time }}
                                                </span>
                                            </template>
                                        </div>
                                        <span v-else class="inline-block w-3 lg:w-4 h-px bg-zinc-200 dark:bg-zinc-800"></span>
                                    </td>
                                    <!-- Time Out -->
                                    <td class="px-1 lg:px-2 py-2 text-center" @click.stop>
                                        <div v-if="student.today_statuses?.some(s => s.status === 'Time Out')" class="flex flex-col items-center gap-1">
                                            <template v-for="s in student.today_statuses?.filter(st => st.status === 'Time Out')">
                                                <span class="inline-flex items-center gap-0.5 lg:gap-1 text-[9px] lg:text-[10px] font-bold text-zinc-900 dark:text-white bg-zinc-300 dark:bg-zinc-600 px-1.5 lg:px-2 py-0.5 rounded-full border border-zinc-400 dark:border-zinc-500 whitespace-nowrap">
                                                    <CheckCircle2 class="w-2.5 h-2.5 lg:w-3 lg:h-3 shrink-0" />
                                                    {{ s.time }}
                                                </span>
                                            </template>
                                        </div>
                                        <span v-else class="inline-block w-3 lg:w-4 h-px bg-zinc-200 dark:bg-zinc-800"></span>
                                    </td>
                                    <!-- Absent -->
                                    <td class="px-1 lg:px-2 py-2 text-center" @click.stop v-if="activeTab === 'active'">
                                        <span v-if="(isScheduledForToday(student) && (!student.today_statuses || student.today_statuses.length === 0)) || student.today_statuses?.some(s => s.status === 'Absent')"
                                            class="inline-flex items-center justify-center w-5 h-5 lg:w-6 lg:h-6 rounded-full bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900 border border-zinc-900 dark:border-zinc-100"
                                            title="Absent"
                                        >
                                            <Check class="w-3 h-3 lg:w-3.5 lg:h-3.5" stroke-width="3" />
                                        </span>
                                        <span v-else class="inline-block w-3 lg:w-4 h-px bg-zinc-200 dark:bg-zinc-800"></span>
                                    </td>
                                </template>
                                <!-- Deleted students: span across 4 status cols + show restore/delete buttons -->
                                <template v-else>
                                    <td colspan="4" class="px-4 py-2" @click.stop>
                                        <div class="flex items-center gap-2">
                                            <Button size="icon-sm" variant="ghost" class="h-8 w-8 text-zinc-800 hover:text-zinc-950 dark:text-zinc-200 dark:hover:text-white hover:bg-zinc-100 dark:hover:bg-zinc-800" title="Restore" @click="restoreStudent(student.id)">
                                                <RefreshCw class="w-4 h-4" />
                                            </Button>
                                            <Button size="icon-sm" variant="ghost" class="h-8 w-8 text-zinc-500 hover:text-red-600 dark:text-zinc-400 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20" title="Delete Permanently" @click="forceDeleteStudent(student.id)">
                                                <Trash2 class="w-4 h-4" />
                                            </Button>
                                        </div>
                                    </td>
                                </template>
                                <td class="px-2 lg:px-4 py-2 text-[10px] lg:text-xs text-muted-foreground">
                                    {{ student.section || '—' }}
                                </td>
                                <td class="px-2 lg:px-4 py-2 text-[10px] lg:text-xs text-muted-foreground" v-if="activeTab === 'active'">
                                    {{ student.email || '—' }}
                                </td>
                                <td class="px-2 lg:px-4 py-2 text-[10px] lg:text-xs text-rose-500 font-medium" v-else>
                                    Deleted {{ formatDateTime(student.deleted_at!) }}
                                </td>
                                <!-- <td class="px-4 py-2 text-right text-xs text-muted-foreground" @click.stop>
                                    <button
                                        type="button"
                                        class="underline-offset-2 hover:underline"
                                        @click="openQrModal(student)"
                                    >
                                        View QR
                                    </button>
                                </td> -->
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Grid View -->
                <div v-else class="p-4 max-h-[520px] overflow-y-auto">
                    <div 
                        v-if="visibleStudents.length === 0"
                        class="flex flex-col items-center justify-center py-16 text-center"
                    >
                        <div class="rounded-full bg-zinc-50 dark:bg-zinc-900 p-5 mb-4 border border-zinc-100 dark:border-zinc-800">
                            <Search v-if="searchQuery" class="h-8 w-8 text-zinc-300" />
                            <Users v-else class="h-8 w-8 text-zinc-300" />
                        </div>
                        <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                            {{ searchQuery ? `No matches for "${searchQuery}"` : 'No students found' }}
                        </h3>
                        <p class="text-xs text-zinc-500 mt-1 max-w-[240px]">
                            {{ searchQuery ? 'Try a different search term or clear your filters.' : 'Your student roster will appear here once you add some students.' }}
                        </p>
                        <Button v-if="!searchQuery && activeTab === 'active'" variant="outline" size="sm" class="rounded-full mt-4" @click="createModalOpen = true">
                            <Plus class="mr-2 h-3.5 w-3.5" />
                            Add First Student
                        </Button>
                    </div>
                    <div 
                        v-else
                        ref="studentsGridRef"
                        class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4"
                    >
                        <div 
                            v-for="student in visibleStudents"
                            :key="student.id"
                            data-student-card
                            class="group relative overflow-hidden rounded-xl border bg-card p-4 transition-all hover:shadow-md hover:border-primary/30 cursor-pointer"
                            @click="activeTab === 'active' ? openStudentInfoModal(student) : null"
                        >
                            <div class="flex items-start justify-between mb-3">
                                <div>
                                    <h4 class="font-semibold text-sm line-clamp-1 group-hover:text-primary transition-colors">
                                        {{ student.name }}
                                    </h4>
                                    <p class="text-[10px] text-muted-foreground font-mono">
                                        {{ student.student_number }}
                                    </p>
                                    <div class="mt-2">
                                        <Badge 
                                            variant="outline" 
                                            class="text-[9px] font-bold px-1.5 py-0"
                                            :class="{
                                                'border-rose-200 bg-rose-50 text-rose-600 dark:border-rose-900/50 dark:bg-rose-950/20 dark:text-rose-400': (student.attendance_percentage ?? 100) < 80,
                                                'border-zinc-200 bg-zinc-50 text-zinc-600 dark:border-zinc-800 dark:bg-zinc-900/50 dark:text-zinc-400': (student.attendance_percentage ?? 100) >= 80
                                            }"
                                        >
                                            {{ student.attendance_percentage ?? 100 }}% Rate
                                        </Badge>
                                    </div>
                                </div>
                                <div 
                                    v-if="activeTab === 'active'"
                                    class="flex flex-wrap gap-1 justify-end"
                                >
                                    <template v-for="s in student.today_statuses">
                                        <div 
                                            class="h-5 flex items-center gap-1 rounded-full px-1.5 py-0.5"
                                            :class="[
                                                s.status === 'Present' ? 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400' :
                                                s.status === 'Late' ? 'bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400' :
                                                s.status === 'Time Out' ? 'bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400' :
                                                'bg-muted'
                                            ]"
                                            :title="s.status"
                                        >
                                            <CheckCircle2 v-if="s.status !== 'Late'" class="w-2.5 h-2.5" />
                                            <AlertCircle v-else class="w-2.5 h-2.5" />
                                            <span class="text-[8px] font-bold">{{ s.time }}</span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="bg-muted px-2 py-0.5 rounded-md text-muted-foreground">
                                    {{ student.section || 'N/A' }}
                                </span>
                                <span 
                                    v-if="activeTab === 'active'"
                                    class="font-medium"
                                    :class="[
                                        student.latest_attendance?.status === 'Present' ? 'text-emerald-600' :
                                        student.latest_attendance?.status === 'Late' ? 'text-amber-600' :
                                        student.latest_attendance?.status === 'Time Out' ? 'text-blue-600' :
                                        'text-muted-foreground'
                                    ]"
                                >
                                    {{ student.latest_attendance?.status || (isScheduledForToday(student) ? 'Scheduled' : 'No record') }}
                                </span>
                                <div v-else class="flex gap-1">
                                    <Button size="icon-sm" variant="ghost" class="h-6 w-6 text-emerald-600" title="Restore" @click.stop="restoreStudent(student.id)">
                                        <RefreshCw class="w-3.5 h-3.5" />
                                    </Button>
                                    <Button size="icon-sm" variant="ghost" class="h-6 w-6 text-rose-600" title="Delete" @click.stop="forceDeleteStudent(student.id)">
                                        <Trash2 class="w-3.5 h-3.5" />
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <Dialog v-model:open="createModalOpen">
                <DialogContent class="max-w-sm flex flex-col max-h-[80dvh]">
                    <DialogHeader>
                        <DialogTitle>
                            Add student
                        </DialogTitle>
                    </DialogHeader>

                    <form class="flex flex-col flex-1 min-h-0" @submit.prevent="submitStudent">
                        <div class="flex-1 overflow-y-auto space-y-3 pr-0.5">
                        <div class="space-y-1.5">
                            <label class="text-xs font-medium">
                                Full name
                            </label>
                            <Input v-model="name" placeholder="e.g. Juan Dela Cruz" />
                            <p
                                v-if="formErrors.name"
                                class="text-xs text-destructive"
                            >
                                {{ Array.isArray(formErrors.name) ? formErrors.name[0] : formErrors.name }}
                            </p>
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-xs font-medium">
                                Student number
                            </label>
                            <Input
                                v-model="studentNumber"
                                placeholder="e.g. 2026-0001"
                            />
                            <p
                                v-if="formErrors.student_number"
                                class="text-xs text-destructive"
                            >
                                {{ Array.isArray(formErrors.student_number) ? formErrors.student_number[0] : formErrors.student_number }}
                            </p>
                        </div>

                        <div class="grid gap-3 md:grid-cols-2">
                            <div class="space-y-1.5">
                                <label class="text-xs font-medium">
                                    Section
                                </label>
                                <Input v-model="section" placeholder="e.g. BSIT-3A" />
                                <p
                                    v-if="formErrors.section"
                                    class="text-xs text-destructive"
                                >
                                    {{ Array.isArray(formErrors.section) ? formErrors.section[0] : formErrors.section }}
                                </p>
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-xs font-medium">
                                    Email
                                </label>
                                <Input
                                    v-model="email"
                                    type="email"
                                    placeholder="Optional"
                                />
                                <p
                                    v-if="formErrors.email"
                                    class="text-xs text-destructive"
                                >
                                    {{ Array.isArray(formErrors.email) ? formErrors.email[0] : formErrors.email }}
                                </p>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="text-xs font-medium">
                                    Time slots
                                </label>
                                <Button
                                    type="button"
                                    size="icon-sm"
                                    variant="outline"
                                    @click="addScheduleSlot"
                                >
                                    +
                                </Button>
                            </div>
                            <p class="text-[11px] text-muted-foreground">
                                Example:
                                10:00–12:00,&nbsp;13:00–14:00,&nbsp;15:00–17:00.
                                A 15-minute grace period is applied.
                            </p>

                            <div class="space-y-2">
                                <div
                                    v-for="(slot, index) in schedules"
                                    :key="index"
                                    class="relative flex flex-col gap-2.5 rounded-md border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/20 p-2.5"
                                >
                                    <div class="flex items-center gap-2 pr-6">
                                        <Select v-model="slot.day">
                                            <SelectTrigger class="h-8 flex-1 text-left text-xs">
                                                <SelectValue :placeholder="slot.day" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="d in daysOfWeek" :key="d" :value="d">
                                                    {{ d }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <Select v-model="slot.subject_id">
                                            <SelectTrigger class="h-8 flex-1 text-left text-xs">
                                                <SelectValue placeholder="Subject" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="subj in props.subjects" :key="subj.id" :value="subj.id.toString()">
                                                    {{ subj.name }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <Input
                                            v-model="slot.start"
                                            type="time"
                                            class="h-8 w-full flex-1 text-xs"
                                        />
                                        <span class="text-[10px] font-medium uppercase tracking-wider text-muted-foreground">
                                            to
                                        </span>
                                        <Input
                                            v-model="slot.end"
                                            type="time"
                                            class="h-8 w-full flex-1 text-xs"
                                        />
                                    </div>
                                    <Button
                                        v-if="schedules.length > 1"
                                        type="button"
                                        size="icon-sm"
                                        variant="ghost"
                                        class="absolute right-1 top-1 h-6 w-6 rounded-full text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                        @click="removeScheduleSlot(index)"
                                    >
                                        <Trash2 class="h-3 w-3" />
                                    </Button>
                                </div>
                            </div>
                            <div
                                v-if="Object.keys(formErrors).some(k => k.startsWith('schedule'))"
                                class="mt-2 space-y-1 rounded-md bg-destructive/5 p-2"
                            >
                                <p v-for="(err, key) in formErrors" :key="key" v-show="key.startsWith('schedule')" class="text-[10px] text-destructive leading-tight">
                                    • {{ Array.isArray(err) ? err[0] : err }}
                                </p>
                            </div>
                        </div>

                        </div>
                        <DialogFooter class="mt-4 flex justify-end gap-2">
                            <DialogClose as-child>
                                <Button type="button" variant="outline">
                                    Cancel
                                </Button>
                            </DialogClose>
                            <Button type="submit" :disabled="submitting">
                                {{ submitting ? 'Saving…' : 'Save student' }}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <!-- Student Info Modal -->
            <Dialog v-model:open="studentInfoModalOpen">
                <DialogContent class="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Student Info</DialogTitle>
                    </DialogHeader>

                    <div v-if="infoStudent" class="space-y-4">
                        <!-- Profile card -->
                        <div class="rounded-lg border bg-muted/30 p-4 space-y-2">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="text-base font-semibold leading-tight">
                                        {{ infoStudent.name }}
                                    </p>
                                    <p class="text-xs text-muted-foreground mt-0.5">
                                        {{ infoStudent.student_number }}
                                        <span v-if="infoStudent.section"> · {{ infoStudent.section }}</span>
                                    </p>
                                    <p v-if="infoStudent.email" class="text-xs text-muted-foreground">
                                        {{ infoStudent.email }}
                                    </p>
                                </div>
                                <!-- Today's status badge -->
                                <span
                                    v-if="infoStudent.latest_attendance"
                                    :class="[
                                        'shrink-0 rounded-full px-2.5 py-0.5 text-[11px] font-semibold',
                                        infoStudent.latest_attendance.status === 'Present' ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400' :
                                        infoStudent.latest_attendance.status === 'Late'    ? 'bg-amber-500/15 text-amber-600 dark:text-amber-400' :
                                        infoStudent.latest_attendance.status === 'Absent'  ? 'bg-red-500/15 text-red-600 dark:text-red-400' :
                                                                                             'bg-muted text-muted-foreground'
                                    ]"
                                >
                                    {{ infoStudent.latest_attendance.status }}
                                </span>
                                <span
                                    v-else
                                    class="shrink-0 rounded-full px-2.5 py-0.5 text-[11px] font-semibold bg-muted text-muted-foreground"
                                >
                                    No record today
                                </span>
                            </div>

                            <!-- Schedule -->
                            <div v-if="infoStudent.schedule && infoStudent.schedule.length > 0" class="pt-1">
                                <p class="text-[11px] font-medium uppercase text-muted-foreground mb-1">Schedule</p>
                                <div class="space-y-2">
                                    <div
                                        v-for="(slot, i) in infoStudent.schedule"
                                        :key="i"
                                        class="flex items-center justify-between rounded-md border p-2 hover:bg-muted/50 transition-colors"
                                    >
                                        <div class="flex flex-col">
                                            <span class="text-xs font-semibold">{{ getSubjectName(slot.subject_id) }}</span>
                                            <span class="text-[10px] font-mono text-muted-foreground">{{ slot.day }}: {{ formatTimeTo12h(slot.start) }} – {{ formatTimeTo12h(slot.end) }}</span>
                                        </div>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            class="h-7 text-[10px] px-2 gap-1"
                                            @click="() => {
                                                const today = new Date().toISOString().split('T')[0];
                                                router.visit(`/manage-attendance/${slot.subject_id}/${today}`);
                                            }"
                                        >
                                            <Clock class="h-3 w-3" />
                                            Mark Manually
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Attendance History -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    Attendance History
                                </p>
                                <button
                                    v-if="attendanceHistory.length > 5"
                                    type="button"
                                    class="flex items-center gap-1 text-[11px] text-primary hover:underline"
                                    @click="historyExpanded = !historyExpanded"
                                >
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        width="12" height="12"
                                        viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor"
                                        stroke-width="2"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        :class="['transition-transform', historyExpanded ? 'rotate-180' : '']"
                                    >
                                        <path d="m6 9 6 6 6-6"/>
                                    </svg>
                                    {{ historyExpanded ? 'Collapse' : `Show all (${attendanceHistory.length})` }}
                                </button>
                            </div>

                            <!-- Loading state -->
                            <div v-if="historyLoading" class="py-4 text-center text-xs text-muted-foreground">
                                Loading history…
                            </div>

                            <!-- Empty state -->
                            <div
                                v-else-if="attendanceHistory.length === 0"
                                class="py-4 text-center text-xs text-muted-foreground"
                            >
                                No attendance records found.
                            </div>

                            <!-- History list grouped by date -->
                            <div
                                v-else
                                :class="['overflow-y-auto rounded-lg border transition-all', historyExpanded ? 'max-h-72' : 'max-h-52']"
                            >
                                <template v-for="group in groupedAttendanceHistory" :key="group.date">
                                    <!-- Date header -->
                                    <div class="sticky top-0 z-10 flex items-center gap-2 bg-muted/80 backdrop-blur px-3 py-1.5 border-b">
                                        <span class="text-[10px] font-bold uppercase tracking-widest text-muted-foreground">
                                            {{ group.label }}
                                        </span>
                                        <span class="ml-auto text-[10px] text-muted-foreground/60">
                                            {{ group.records.length }} record{{ group.records.length !== 1 ? 's' : '' }}
                                        </span>
                                    </div>
                                    <!-- Records for this date -->
                                    <div
                                        v-for="record in group.records"
                                        :key="record.id"
                                        class="flex items-center justify-between px-3 py-2 text-xs border-b last:border-b-0"
                                    >
                                        <div class="flex flex-col">
                                            <span class="font-medium">
                                                {{ new Date(record.scanned_at).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', hour12: true }) }}
                                            </span>
                                            <span v-if="record.slot_start" class="text-[10px] text-muted-foreground">
                                                {{ formatTimeTo12h(record.slot_start || undefined) }} – {{ formatTimeTo12h(record.slot_end || undefined) }}
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <Select 
                                                :model-value="record.status" 
                                                @update:model-value="(val) => updateHistoryStatus(record.id, String(val))"
                                                :disabled="updatingRecordId === record.id"
                                            >
                                                <SelectTrigger class="h-6 min-w-[80px] border-none bg-transparent p-0 hover:bg-muted/50 focus:ring-0">
                                                    <div v-if="updatingRecordId === record.id" class="flex items-center justify-center w-full">
                                                        <span class="animate-pulse text-[10px] text-muted-foreground italic">Saving...</span>
                                                    </div>
                                                    <SelectValue v-else :placeholder="record.status">
                                                        <span
                                                            :class="[
                                                                'rounded-full px-2 py-0.5 text-[10px] font-bold',
                                                                record.status === 'Present'  ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400' :
                                                                record.status === 'Late'     ? 'bg-amber-500/15 text-amber-600 dark:text-amber-400' :
                                                                record.status === 'Time Out' ? 'bg-blue-500/15 text-blue-600 dark:text-blue-400' :
                                                                record.status === 'Absent'   ? 'bg-red-500/15 text-red-600 dark:text-red-400' :
                                                                                               'bg-muted text-muted-foreground'
                                                            ]"
                                                        >
                                                            {{ record.status }}
                                                        </span>
                                                    </SelectValue>
                                                </SelectTrigger>
                                                <SelectContent class="min-w-[120px]">
                                                    <SelectItem value="Present" class="text-xs">Present</SelectItem>
                                                    <SelectItem value="Late" class="text-xs">Late</SelectItem>
                                                    <SelectItem value="Time Out" class="text-xs">Time Out</SelectItem>
                                                    <SelectItem value="Absent" class="text-xs">Absent</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <DialogFooter class="mt-2 flex flex-col gap-2 sm:flex-row sm:justify-between sm:items-center">
                        <div class="flex flex-wrap gap-2 w-full sm:w-auto">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="openEditFromInfo"
                            >
                                Edit student
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="openQrFromInfo"
                            >
                                View QR
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="openPrintCardsFromInfo"
                            >
                                Print Card
                            </Button>
                                <Button
                                    v-if="infoStudent"
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    class="text-rose-600 border-rose-200 hover:bg-rose-50 hover:text-rose-700 hover:border-rose-300"
                                    @click="deleteStudent(infoStudent.id)"
                                >
                                    Delete
                                </Button>
                        </div>
                        <Button
                            type="button"
                            size="sm"
                            class="w-full sm:w-auto"
                            @click="closeStudentInfoModal"
                        >
                            Close
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog v-model:open="editModalOpen">
                <DialogContent class="max-w-md flex flex-col max-h-[80dvh]">
                    <DialogHeader>
                        <DialogTitle>
                            Edit student
                        </DialogTitle>
                    </DialogHeader>

                    <form class="flex flex-col flex-1 min-h-0" @submit.prevent="submitEditStudent">
                        <div class="flex-1 overflow-y-auto space-y-3 pr-0.5">
                        <div class="space-y-1.5">
                            <label class="text-xs font-medium">
                                Full name
                            </label>
                            <Input
                                v-model="editName"
                                placeholder="e.g. Juan Dela Cruz"
                            />
                            <p
                                v-if="formErrors.name"
                                class="text-xs text-destructive"
                            >
                                {{ Array.isArray(formErrors.name) ? formErrors.name[0] : formErrors.name }}
                            </p>
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-xs font-medium">
                                Student number
                            </label>
                            <Input
                                v-model="editStudentNumber"
                                placeholder="e.g. 2026-0001"
                            />
                            <p
                                v-if="formErrors.student_number"
                                class="text-xs text-destructive"
                            >
                                {{ Array.isArray(formErrors.student_number) ? formErrors.student_number[0] : formErrors.student_number }}
                            </p>
                        </div>

                        <div class="grid gap-3 md:grid-cols-2">
                            <div class="space-y-1.5">
                                <label class="text-xs font-medium">
                                    Section
                                </label>
                                <Input
                                    v-model="editSection"
                                    placeholder="e.g. BSIT-3A"
                                />
                                <p
                                    v-if="formErrors.section"
                                    class="text-xs text-destructive"
                                >
                                    {{ Array.isArray(formErrors.section) ? formErrors.section[0] : formErrors.section }}
                                </p>
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-xs font-medium">
                                    Email
                                </label>
                                <Input
                                    v-model="editEmail"
                                    type="email"
                                    placeholder="Optional"
                                />
                                <p
                                    v-if="formErrors.email"
                                    class="text-xs text-destructive"
                                >
                                    {{ Array.isArray(formErrors.email) ? formErrors.email[0] : formErrors.email }}
                                </p>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="text-xs font-medium">
                                    Time slots
                                </label>
                                <Button
                                    type="button"
                                    size="icon-sm"
                                    variant="outline"
                                    @click="addEditScheduleSlot"
                                >
                                    +
                                </Button>
                            </div>
                            <p class="text-[11px] text-muted-foreground">
                                Update the student's schedule. A 15-minute grace
                                period is applied to each start time.
                            </p>

                            <div class="space-y-2">
                                <div
                                    v-for="(slot, index) in editSchedules"
                                    :key="index"
                                    class="relative flex flex-col gap-2.5 rounded-md border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/20 p-2.5"
                                >
                                    <div class="flex items-center gap-2 pr-6">
                                        <Select v-model="slot.day">
                                            <SelectTrigger class="h-8 flex-1 text-left text-xs">
                                                <SelectValue :placeholder="slot.day" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="d in daysOfWeek" :key="d" :value="d">
                                                    {{ d }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <Select v-model="slot.subject_id">
                                            <SelectTrigger class="h-8 flex-1 text-left text-xs">
                                                <SelectValue placeholder="Subject" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="subj in props.subjects" :key="subj.id" :value="subj.id.toString()">
                                                    {{ subj.name }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <Input
                                            v-model="slot.start"
                                            type="time"
                                            class="h-8 w-full flex-1 text-xs"
                                        />
                                        <span class="text-[10px] font-medium uppercase tracking-wider text-muted-foreground">
                                            to
                                        </span>
                                        <Input
                                            v-model="slot.end"
                                            type="time"
                                            class="h-8 w-full flex-1 text-xs"
                                        />
                                    </div>
                                    <Button
                                        v-if="editSchedules.length > 1"
                                        type="button"
                                        size="icon-sm"
                                        variant="ghost"
                                        class="absolute right-1 top-1 h-6 w-6 rounded-full text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                        @click="removeEditScheduleSlot(index)"
                                    >
                                        <Trash2 class="h-3 w-3" />
                                    </Button>
                                </div>
                            </div>
                            <div
                                v-if="Object.keys(formErrors).some(k => k.startsWith('schedule'))"
                                class="mt-2 space-y-1 rounded-md bg-destructive/5 p-2"
                            >
                                <p v-for="(err, key) in formErrors" :key="key" v-show="key.startsWith('schedule')" class="text-[10px] text-destructive leading-tight">
                                    • {{ Array.isArray(err) ? err[0] : err }}
                                </p>
                            </div>
                        </div>
                        </div>

                        <DialogFooter class="mt-4 flex justify-end gap-2">
                            <DialogClose as-child>
                                <Button type="button" variant="outline">
                                    Cancel
                                </Button>
                            </DialogClose>
                            <Button type="submit" :disabled="submitting">
                                {{ submitting ? 'Saving…' : 'Save changes' }}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog v-model:open="qrModalOpen">
                <DialogContent class="max-w-sm flex max-h-[85dvh] flex-col">
                    <DialogHeader>
                        <DialogTitle>
                            Student QR code
                        </DialogTitle>
                    </DialogHeader>

                    <div v-if="selectedStudent" class="min-h-0 flex-1 overflow-y-auto pr-1 space-y-4">
                        <div class="space-y-1">
                            <p class="text-sm font-semibold">
                                {{ selectedStudent.name }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ selectedStudent.student_number }}
                                ·
                                {{ selectedStudent.section || 'No section' }}
                            </p>
                        </div>

                        <div class="flex items-center justify-center rounded-lg border bg-white p-4">
                            <canvas id="qr-canvas" ref="qrCanvas" class="h-48 w-48"></canvas>
                        </div>

                        <p class="text-xs text-muted-foreground">
                            This QR encodes a secure token for this student. You
                            can print or share it, and regenerate it anytime to
                            invalidate older copies.
                        </p>

                        <div class="rounded-lg border border-sidebar-border/50 bg-background/50 p-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                                Student portal link
                            </p>
                            <div class="mt-1 flex flex-col gap-2 sm:flex-row sm:items-center">
                                <a
                                    :href="studentPortalUrl(selectedStudent.qr_token)"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="min-w-0 flex-1 break-all text-xs font-mono text-primary hover:underline sm:truncate sm:break-normal"
                                    :title="studentPortalUrl(selectedStudent.qr_token)"
                                >
                                    {{ studentPortalUrl(selectedStudent.qr_token) }}
                                </a>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    class="shrink-0 self-start sm:self-auto"
                                    @click="copyStudentPortalLink"
                                >
                                    Copy
                                </Button>
                            </div>
                        </div>
                    </div>

                    <DialogFooter v-if="selectedStudent" class="mt-3 flex flex-wrap items-center justify-between gap-2">
                        <Button type="button" size="sm" variant="outline" @click="regenerateQr">
                            Regenerate
                        </Button>
                        <div class="flex flex-wrap gap-2 justify-end">
                            <Button type="button" size="sm" variant="outline" @click="downloadQr">
                                Download
                            </Button>
                            <Button type="button" size="sm" variant="outline" @click="openPrintCards">
                                Print
                            </Button>
                            <Button type="button" size="sm" @click="closeQrModal">
                                Close
                            </Button>
                        </div>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Import Students Dialog -->
            <Dialog v-model:open="importModalOpen">
                <DialogContent class="max-w-md">
                    <DialogHeader>
                        <DialogTitle class="flex items-center gap-2">
                            <UserPlus class="h-5 w-5" />
                            Import Students
                        </DialogTitle>
                    </DialogHeader>
                    <div class="py-4 space-y-4">
                        <p class="text-sm text-muted-foreground">
                            Upload a CSV file containing student information. The file should have the following headers: 
                            <code class="text-xs bg-muted px-1 rounded font-bold">name</code>, 
                            <code class="text-xs bg-muted px-1 rounded font-bold">student_number</code>, 
                            <code class="text-xs bg-muted px-1 rounded font-bold">email</code>, 
                            <code class="text-xs bg-muted px-1 rounded font-bold">section</code>.
                        </p>

                        <div class="flex items-center justify-between p-3 border rounded-lg bg-zinc-50 dark:bg-zinc-900/50">
                            <div class="flex items-center gap-3">
                                <div class="p-2 rounded-full bg-zinc-200 dark:bg-zinc-800">
                                    <Download class="h-4 w-4 text-zinc-600 dark:text-zinc-400" />
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-xs font-bold">Need a template?</span>
                                    <span class="text-[10px] text-muted-foreground">Download our sample CSV file.</span>
                                </div>
                            </div>
                            <Button 
                                variant="outline" 
                                size="sm" 
                                class="text-[10px] font-bold h-7"
                                as-child
                            >
                                <a href="/students/sample" target="_blank">Download Sample</a>
                            </Button>
                        </div>

                        <div class="space-y-2">
                            <label class="text-[10px] font-bold uppercase tracking-wider text-zinc-500">CSV File</label>
                            <input 
                                type="file" 
                                accept=".csv"
                                class="w-full text-xs file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-[10px] file:font-semibold file:bg-zinc-900 file:text-white dark:file:bg-white dark:file:text-zinc-900 hover:file:bg-zinc-800 transition-all cursor-pointer border rounded-lg p-1"
                                @change="handleFileChange"
                            />
                        </div>
                    </div>
                    <DialogFooter class="gap-2">
                        <Button variant="outline" @click="importModalOpen = false">
                            Cancel
                        </Button>
                        <Button 
                            :disabled="!importFile || importing"
                            @click="submitImport"
                        >
                            {{ importing ? 'Importing...' : 'Start Import' }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Generic Confirmation Modal -->
            <Dialog v-model:open="confirmModalOpen">
                <DialogContent class="max-w-sm">
                    <DialogHeader>
                        <DialogTitle>{{ confirmTitle }}</DialogTitle>
                    </DialogHeader>
                    <div class="py-2">
                        <p class="text-sm text-muted-foreground">
                            {{ confirmDescription }}
                        </p>
                    </div>
                    <DialogFooter class="flex gap-2">
                        <Button variant="outline" @click="confirmModalOpen = false">
                            Cancel
                        </Button>
                        <Button 
                            :variant="confirmIsDestructive ? 'destructive' : 'default'"
                            @click="handleConfirm"
                        >
                            Confirm
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>

<style scoped>
.status-pulse {
    transition: all 0.3s ease;
}

.glass-card {
    background: rgba(23, 23, 23, 0.7);
    backdrop-filter: blur(16px);
    border: 1px solid rgba(255, 255, 255, 0.08);
}

.dark .glass-card {
    background: rgba(15, 15, 15, 0.7);
    border: 1px solid rgba(255, 255, 255, 0.05);
}
</style>
