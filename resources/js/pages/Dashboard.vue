<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { useDraggable, useWindowSize, useStorage } from '@vueuse/core';
import type { BreadcrumbItem } from '@/types';
import { Chart as ChartJS, Title, Tooltip, Legend, ArcElement, CategoryScale } from 'chart.js';
import { Doughnut } from 'vue-chartjs';
import gsap from 'gsap';
import { Users, Search, Plus, LayoutGrid, Table, Clock, XCircle, Calendar, PieChart, AlertTriangle, AlertCircle, RefreshCw, Trash2, Check, QrCode, Scan, Download, UserPlus, CheckCircle2, UserCheck, UserX, Zap, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import QRCode from 'qrcode';
import { ref, computed, onMounted, onUnmounted, nextTick, watch, toValue } from 'vue';
import { useToast } from '@/composables/useToast';
import { useScanner } from '@/composables/useScanner';
import { useTilt } from '@/composables/useTilt';
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
    DialogDescription,
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
    photo?: string | null;
};

const daysOfWeek = [
    'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'
];

type Paginator<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

type PageProps = {
    students: Paginator<Student>;
    trashedStudents: Student[];
    subjects: { id: number; name: string }[];
    attendanceStats?: { Present: number; Late: number; Absent: number; Excused: number; };
    attendanceRate?: number;
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

const students = computed(() => props.students?.data ?? []);
const searchQuery = ref('');
const showWelcomeModal = useStorage('show-welcome-modal-v1', true, sessionStorage);
const searchInputRef = ref<{ $el: HTMLInputElement } | null>(null);
const toast = useToast();
const { open: openScanner } = useScanner();

const statusFilter = ref<'Present' | 'Late' | 'Absent' | null>(null);

const filteredStudents = computed(() => {
    let result = students.value;
    
    // Note: Search and filtering for active students should now ideally happen 
    // on the server for better performance, but we'll keep the client-side logic 
    // for the currently loaded page of students for now.
    
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
    return {
        total: props.students?.total ?? 0,
        present: statsPresent.value,
        late: statsLate.value,
        absent: statsAbsent.value,
        trashed: props.trashedStudents?.length || 0
    };
});

const attendanceRate = computed(() => {
    const rate = props.attendanceRate ?? 0;
    return Number.isFinite(rate) ? rate : 0;
});

const attendanceRateClass = computed(() => {
    if (attendanceRate.value >= 90) return 'bg-emerald-500 text-white';
    if (attendanceRate.value >= 75) return 'bg-amber-500 text-white';
    return 'bg-rose-500 text-white';
});

// Since we use pagination, we need the overall status counts from props
const statsPresent = computed(() => props.attendanceStats?.Present ?? 0);
const statsLate = computed(() => props.attendanceStats?.Late ?? 0);
const statsAbsent = computed(() => props.attendanceStats?.Absent ?? 0);

const animatedStats = ref({
    total: 0,
    present: 0,
    late: 0,
    absent: 0
});

watch(stats, (newStats) => {
    // 1. Animate the counter numbers
    gsap.to(animatedStats.value, {
        total: newStats.total,
        present: newStats.present,
        late: newStats.late,
        absent: newStats.absent,
        duration: 1.5,
        ease: 'power3.out',
        snap: { total: 1, present: 1, late: 1, absent: 1 }
    });

    // 2. 3D "Pop" animation for stat cards
    const cards = document.querySelectorAll('[data-card]');
    if (cards.length) {
        gsap.to(cards, {
            y: -10,
            scale: 1.05,
            duration: 0.2,
            stagger: 0.05,
            ease: 'back.out(2)',
            onComplete: () => {
                gsap.to(cards, {
                    y: 0,
                    scale: 1,
                    duration: 0.6,
                    ease: 'elastic.out(1, 0.3)'
                });
            }
        });
    }
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

const viewMode = ref<'table' | 'grid'>('grid');
const createModalOpen = ref(false);
const editModalOpen = ref(false);
const showOnlyScheduledToday = ref(false);
const activeTab = ref<'active' | 'deleted'>('active');

const itemsPerPage = ref(10);
const currentPage = ref(1);

const visibleStudents = computed(() => {
    return activeTab.value === 'active' ? filteredStudents.value : filteredTrashedStudents.value;
});

const paginatedStudents = computed(() => {
    // For active tab, we use server-side pagination
    if (activeTab.value === 'active') {
        return visibleStudents.value;
    }
    
    // For trashed tab, we still use client-side pagination as it's not paginated on server yet
    const start = (currentPage.value - 1) * itemsPerPage.value;
    const end = start + itemsPerPage.value;
    return visibleStudents.value.slice(start, end);
});

const totalPages = computed(() => {
    if (activeTab.value === 'active') {
        return props.students?.last_page ?? 1;
    }
    return Math.ceil(visibleStudents.value.length / itemsPerPage.value);
});

function nextPage() {
    if (activeTab.value === 'active') {
        if (props.students.last_page > props.students.current_page) {
            router.get(dashboard(), { page: props.students.current_page + 1 }, { preserveScroll: true });
        }
    } else {
        if (currentPage.value < totalPages.value) currentPage.value++;
    }
}

function prevPage() {
    if (activeTab.value === 'active') {
        if (props.students.current_page > 1) {
            router.get(dashboard(), { page: props.students.current_page - 1 }, { preserveScroll: true });
        }
    } else {
        if (currentPage.value > 1) currentPage.value--;
    }
}

watch(searchQuery, (q) => {
    // We'll use router.get to sync with the server, with a small debounce conceptually
    // though here we'll just trigger it. Inertia handles standard request cancellation.
    router.get(dashboard(), 
        { search: q, only_scheduled: showOnlyScheduledToday.value }, 
        { preserveState: true, preserveScroll: true, replace: true }
    );
});

watch(showOnlyScheduledToday, (val) => {
    router.get(dashboard(), 
        { search: searchQuery.value, only_scheduled: val }, 
        { preserveState: true, preserveScroll: true, replace: true }
    );
});

watch([activeTab, statusFilter, viewMode, currentPage], () => {
    // Only reset currentPage if it's not a server-side pagination change
    if (activeTab.value !== 'active') {
        currentPage.value = 1;
    }
    animateStudents();
});

const selectedStudent = ref<Student | null>(null);
const qrModalOpen = ref(false);

const studentInfoModalOpen = ref(false);
const infoStudent = ref<Student | null>(null);
const attendanceHistory = ref<AttendanceRecord[]>([]);
const historyExpanded = ref(false);
const historyLoading = ref(false);
const updatingRecordId = ref<number | null>(null);

const importModalOpen = ref(false);
const importFile = ref<File | null>(null);
const importing = ref(false);

// Summary Card Refs
const totalStudentsCard = ref(null);
const presentCard = ref(null);
const lateCard = ref(null);
const absentCard = ref(null);

useTilt(totalStudentsCard);
useTilt(presentCard);
useTilt(lateCard);
useTilt(absentCard);

const el = ref<HTMLElement | null>(null);
const { width: windowWidth, height: windowHeight } = useWindowSize();

// Automatic switching for mobile
watch(windowWidth, (w) => {
    if (Number(w) < 768) {
        viewMode.value = 'grid';
    }
}, { immediate: true });

const { x, y, isDragging } = useDraggable(el as any, {
  initialValue: { x: window.innerWidth - 100, y: window.innerHeight - 100 },
  preventDefault: true,
  onEnd: () => {
      // Chathead snapping logic: snap to nearest left/right edge
      const margin = 20;
      const buttonWidth = 100;
      const threshold = Number(windowWidth.value) / 2;
      
      if (x.value < threshold) {
          x.value = margin;
      } else {
          x.value = Number(windowWidth.value) - buttonWidth - margin;
      }
  }
});

// Boundary and resize handling
watch([windowWidth, windowHeight], ([newW, newH]) => {
    // Keep within viewport with margins
    const margin = 20;
    const buttonWidth = 100;
    const buttonHeight = 56;
    
    const w = Number(newW);
    const h = Number(newH);

    if (x.value > w - buttonWidth - margin) x.value = w - buttonWidth - margin;
    if (x.value < margin) x.value = margin;
    if (y.value > h - buttonHeight - margin) y.value = h - buttonHeight - margin;
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

function closeWelcomeModal() {
    showWelcomeModal.value = false;
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
const photo = ref<File | null>(null);
const photoPreview = ref<string | null>(null);
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
const editPhoto = ref<File | null>(null);
const editPhotoPreview = ref<string | null>(null);

const submitting = ref(false);
const formErrors = ref<Record<string, string[]>>({});

const cardsRef = ref<HTMLDivElement | null>(null);
const tableRef = ref<HTMLDivElement | null>(null);
const studentsGridRef = ref<HTMLDivElement | null>(null);
const studentsTableBodyRef = ref<HTMLTableSectionElement | null>(null);
const photoInput = ref<HTMLInputElement | null>(null);
const editPhotoInput = ref<HTMLInputElement | null>(null);

function animateStudents() {
    nextTick(() => {
        const targets = viewMode.value === 'grid' 
            ? studentsGridRef.value?.querySelectorAll('[data-student-card]')
            : studentsTableBodyRef.value?.querySelectorAll('tr');

        if (!targets || targets.length === 0) return;

        gsap.killTweensOf(targets);
        
        if (viewMode.value === 'grid') {
            gsap.fromTo(targets, 
                { opacity: 0, y: 15, scale: 0.98 },
                { 
                    opacity: 1, 
                    y: 0, 
                    scale: 1, 
                    duration: 0.4, 
                    stagger: 0.03, 
                    ease: 'power2.out',
                    clearProps: 'all'
                }
            );
        } else {
            gsap.fromTo(targets,
                { opacity: 0, x: -10 },
                { 
                    opacity: 1, 
                    x: 0, 
                    duration: 0.3, 
                    stagger: 0.02, 
                    ease: 'power1.out',
                    clearProps: 'all'
                }
            );
        }
    });
}

watch([searchQuery, activeTab, viewMode, statusFilter, currentPage], () => {
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
const handleConfirm = () => {
    if (confirmAction.value) {
        confirmAction.value();
    }
    confirmModalOpen.value = false;
};

function handlePhotoChange(event: Event, type: 'create' | 'edit') {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        const file = target.files[0];
        if (type === 'create') {
            photo.value = file;
            photoPreview.value = URL.createObjectURL(file);
        } else {
            editPhoto.value = file;
            editPhotoPreview.value = URL.createObjectURL(file);
        }
    }
}
function resetForm() {
    name.value = '';
    studentNumber.value = '';
    email.value = '';
    section.value = '';
    schedules.value = [{ day: 'Monday', start: '', end: '', subject_id: '' }];
    photo.value = null;
    photoPreview.value = null;
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
            photo: photo.value,
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
    editPhoto.value = null;
    editPhotoPreview.value = (student as any).photo || null;
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

    router.post(
        `/students/${editingStudentId.value}`,
        {
            _method: 'PUT',
            name: editName.value,
            student_number: editStudentNumber.value,
            email: editEmail.value || null,
            section: editSection.value || null,
            schedule: editSchedules.value,
            photo: editPhoto.value,
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

onMounted(() => {
    // 1. Entrance Animations for Cards
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
        <div class="flex min-h-full flex-1 flex-col gap-4 sm:gap-6 overflow-x-hidden p-3 sm:p-4 pb-20 md:pb-4">
            <!-- Welcome Header -->
            <div class="flex items-center justify-between gap-4 px-1">
                <div class="flex flex-col gap-0.5 sm:gap-1">
                    <h1 class="text-xl sm:text-3xl font-serif font-bold tracking-tight">
                        {{ greeting }}, {{ userName }}!
                    </h1>
                    <p class="text-xs sm:text-sm text-muted-foreground font-medium">
                        {{ greetingSubtext }}
                    </p>
                    <div class="flex items-center gap-1.5 sm:gap-2 text-[10px] text-muted-foreground mt-0.5 sm:mt-1">
                        <Calendar class="h-3 sm:h-3.5 w-3 sm:w-3.5" />
                        <span>{{ formattedCurrentDate }}</span>
                    </div>
                </div>

                <!-- Desktop Scan Button -->
                <Button 
                    variant="outline" 
                    size="lg" 
                    class="flex h-10 sm:h-12 items-center gap-2 sm:gap-3 rounded-2xl border-zinc-200/50 bg-white px-3 sm:px-5 text-sm font-bold shadow-sm transition-all hover:bg-zinc-50 hover:text-zinc-900 hover:shadow-md active:scale-95 dark:border-zinc-800 dark:bg-zinc-950 dark:hover:bg-zinc-900 dark:hover:text-zinc-50 group shrink-0"
                    @click="openScanner"
                >
                    <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900 group-hover:scale-110 transition-transform">
                        <QrCode class="size-4" />
                    </div>
                    <span class="hidden sm:inline">Scan QR Code</span>
                </Button>
            </div>

            <!-- Consolidated stats card (mobile + desktop) -->
            <div class="relative overflow-hidden rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-black shadow-sm p-4 sm:p-5">
                <Users class="absolute right-[-2%] top-1/2 -translate-y-1/2 h-24 w-24 sm:h-28 sm:w-28 text-zinc-900/[0.04] dark:text-white/[0.04] pointer-events-none" />
                <div class="relative z-10">
                    <div class="mb-4">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Total Students</p>
                        <p class="mt-1.5 text-4xl sm:text-5xl font-bold text-zinc-900 dark:text-white">{{ searchQuery ? filteredStudents.length : Math.round(animatedStats.total) }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <button
                            @click="statusFilter = statusFilter === 'Present' ? null : 'Present'"
                            class="relative rounded-xl border p-3 text-left text-xs font-semibold transition-all overflow-hidden"
                            :class="statusFilter === 'Present' ? 'border-emerald-400 bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700' : 'border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-950 text-zinc-900 dark:text-zinc-100 hover:border-emerald-300 hover:bg-emerald-50/50 dark:hover:bg-emerald-950/20'"
                        >
                            <CheckCircle2 class="absolute -right-2 -bottom-2 h-14 w-14 text-emerald-400/10 pointer-events-none" />
                            <p class="text-[10px] text-zinc-500 dark:text-zinc-400 mb-1">Present</p>
                            <p class="text-2xl font-bold">{{ Math.round(animatedStats.present) }}</p>
                        </button>

                        <button
                            @click="statusFilter = statusFilter === 'Late' ? null : 'Late'"
                            class="relative rounded-xl border p-3 text-left text-xs font-semibold transition-all overflow-hidden"
                            :class="statusFilter === 'Late' ? 'border-amber-400 bg-amber-50 dark:bg-amber-900/30 text-amber-700' : 'border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-950 text-zinc-900 dark:text-zinc-100 hover:border-amber-300 hover:bg-amber-50/50 dark:hover:bg-amber-950/20'"
                        >
                            <Zap class="absolute -right-2 -bottom-2 h-14 w-14 text-amber-400/10 pointer-events-none" />
                            <p class="text-[10px] text-zinc-500 dark:text-zinc-400 mb-1">Late</p>
                            <p class="text-2xl font-bold">{{ Math.round(animatedStats.late) }}</p>
                        </button>

                        <button
                            @click="statusFilter = statusFilter === 'Absent' ? null : 'Absent'"
                            class="relative rounded-xl border p-3 text-left text-xs font-semibold transition-all overflow-hidden"
                            :class="statusFilter === 'Absent' ? 'border-rose-400 bg-rose-50 dark:bg-rose-900/30 text-rose-700' : 'border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-950 text-zinc-900 dark:text-zinc-100 hover:border-rose-300 hover:bg-rose-50/50 dark:hover:bg-rose-950/20'"
                        >
                            <UserX class="absolute -right-2 -bottom-2 h-14 w-14 text-rose-400/10 pointer-events-none" />
                            <p class="text-[10px] text-zinc-500 dark:text-zinc-400 mb-1">Absent</p>
                            <p class="text-2xl font-bold">{{ Math.round(animatedStats.absent) }}</p>
                        </button>

                        <div class="relative rounded-xl border border-zinc-200 dark:border-zinc-800 p-3 text-left text-xs font-semibold bg-zinc-50 dark:bg-zinc-950 text-zinc-900 dark:text-zinc-100 overflow-hidden">
                            <PieChart class="absolute -right-2 -bottom-2 h-14 w-14 text-zinc-400/10 pointer-events-none" />
                            <p class="text-[10px] text-zinc-500 dark:text-zinc-400 mb-1">Attendance Rate</p>
                            <p class="text-2xl font-bold">{{ attendanceRate.toFixed(1) }}%</p>
                            <div class="mt-2 h-1.5 w-full rounded-full bg-zinc-200 dark:bg-zinc-800 overflow-hidden">
                                <div :class="['h-full transition-all duration-700', attendanceRateClass]" :style="{ width: Math.min(attendanceRate, 100) + '%' }"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions Row -->
            <div class="grid grid-cols-4 gap-2 sm:gap-3">
                <button
                    @click="openScanner"
                    class="flex flex-col sm:flex-row items-center gap-2 sm:gap-3 sm:rounded-2xl sm:border sm:border-zinc-200 dark:sm:border-zinc-800 sm:bg-white dark:sm:bg-black sm:p-4 p-1 text-center sm:text-left sm:hover:bg-zinc-50 dark:sm:hover:bg-zinc-900 transition-all sm:shadow-sm group"
                >
                    <div class="flex h-[52px] w-[52px] sm:h-9 sm:w-9 shrink-0 items-center justify-center rounded-full sm:rounded-xl bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 shadow-xl shadow-zinc-900/10 sm:shadow-none transition-transform sm:group-hover:scale-110 active:scale-95">
                        <Scan class="h-5 w-5 sm:h-4 sm:w-4" />
                    </div>
                    <div class="flex flex-col min-w-0 items-center sm:items-start w-full">
                        <p class="text-[9px] sm:text-xs font-bold text-zinc-900 dark:text-white truncate w-full">Scan QR</p>
                        <p class="hidden sm:block text-[10px] text-zinc-500 dark:text-zinc-400 truncate w-full">Record attendance</p>
                    </div>
                </button>

                <a
                    href="/reports"
                    class="flex flex-col sm:flex-row items-center gap-2 sm:gap-3 sm:rounded-2xl sm:border sm:border-zinc-200 dark:sm:border-zinc-800 sm:bg-white dark:sm:bg-black sm:p-4 p-1 text-center sm:text-left sm:hover:bg-zinc-50 dark:sm:hover:bg-zinc-900 transition-all sm:shadow-sm group"
                >
                    <div class="flex h-[52px] w-[52px] sm:h-9 sm:w-9 shrink-0 items-center justify-center rounded-full sm:rounded-xl bg-white sm:bg-zinc-100 dark:bg-zinc-900 dark:sm:bg-zinc-800 border border-zinc-200 dark:border-zinc-800 sm:border-transparent text-zinc-700 dark:text-zinc-300 shadow-sm sm:shadow-none transition-transform sm:group-hover:scale-110 active:scale-95">
                        <PieChart class="h-5 w-5 sm:h-4 sm:w-4" />
                    </div>
                    <div class="flex flex-col min-w-0 items-center sm:items-start w-full">
                        <p class="text-[9px] sm:text-xs font-bold text-zinc-700 sm:text-zinc-900 dark:text-zinc-300 dark:sm:text-white truncate w-full">Reports</p>
                        <p class="hidden sm:block text-[10px] text-zinc-500 dark:text-zinc-400 truncate w-full">View summaries</p>
                    </div>
                </a>

                <a
                    href="/calendar"
                    class="flex flex-col sm:flex-row items-center gap-2 sm:gap-3 sm:rounded-2xl sm:border sm:border-zinc-200 dark:sm:border-zinc-800 sm:bg-white dark:sm:bg-black sm:p-4 p-1 text-center sm:text-left sm:hover:bg-zinc-50 dark:sm:hover:bg-zinc-900 transition-all sm:shadow-sm group"
                >
                    <div class="flex h-[52px] w-[52px] sm:h-9 sm:w-9 shrink-0 items-center justify-center rounded-full sm:rounded-xl bg-white sm:bg-zinc-100 dark:bg-zinc-900 dark:sm:bg-zinc-800 border border-zinc-200 dark:border-zinc-800 sm:border-transparent text-zinc-700 dark:text-zinc-300 shadow-sm sm:shadow-none transition-transform sm:group-hover:scale-110 active:scale-95">
                        <Calendar class="h-5 w-5 sm:h-4 sm:w-4" />
                    </div>
                    <div class="flex flex-col min-w-0 items-center sm:items-start w-full">
                        <p class="text-[9px] sm:text-xs font-bold text-zinc-700 sm:text-zinc-900 dark:text-zinc-300 dark:sm:text-white truncate w-full">Calendar</p>
                        <p class="hidden sm:block text-[10px] text-zinc-500 dark:text-zinc-400 truncate w-full">Schedule view</p>
                    </div>
                </a>

                <button
                    @click="openCreateModal"
                    class="flex flex-col sm:flex-row items-center gap-2 sm:gap-3 sm:rounded-2xl sm:border sm:border-zinc-200 dark:sm:border-zinc-800 sm:bg-white dark:sm:bg-black sm:p-4 p-1 text-center sm:text-left sm:hover:bg-zinc-50 dark:sm:hover:bg-zinc-900 transition-all sm:shadow-sm group"
                >
                    <div class="flex h-[52px] w-[52px] sm:h-9 sm:w-9 shrink-0 items-center justify-center rounded-full sm:rounded-xl bg-white sm:bg-zinc-100 dark:bg-zinc-900 dark:sm:bg-zinc-800 border border-zinc-200 dark:border-zinc-800 sm:border-transparent text-zinc-700 dark:text-zinc-300 shadow-sm sm:shadow-none transition-transform sm:group-hover:scale-110 active:scale-95">
                        <UserPlus class="h-5 w-5 sm:h-4 sm:w-4" />
                    </div>
                    <div class="flex flex-col min-w-0 items-center sm:items-start w-full">
                        <p class="text-[9px] sm:text-xs font-bold text-zinc-700 sm:text-zinc-900 dark:text-zinc-300 dark:sm:text-white truncate w-full">Add Student</p>
                        <p class="hidden sm:block text-[10px] text-zinc-500 dark:text-zinc-400 truncate w-full">Register new</p>
                    </div>
                </button>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 sm:gap-6 items-start">
                <!-- Sidebar: 1/4 width on desktop, right side aligned with Add Student -->
                <div class="lg:col-span-1 flex flex-col gap-4 order-last lg:order-last">

                    <!-- Students at Risk -->
                    <div v-if="atRiskStudents.length > 0" class="overflow-hidden rounded-2xl border border-rose-200 dark:border-rose-900/50 bg-white dark:bg-black shadow-sm">
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
                                    <div class="flex flex-col min-w-0">
                                        <span class="text-xs font-semibold text-zinc-900 dark:text-white line-clamp-2 break-words" :title="student.name">{{ student.name }}</span>
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
                                        <!-- Photo/Avatar -->
                                        <div v-if="students.find(s => s.name === act.name)?.photo" class="h-8 w-8 shrink-0 rounded-full overflow-hidden border border-zinc-200 dark:border-zinc-800 shadow-sm">
                                            <img :src="(students.find(s => s.name === act.name)?.photo ?? undefined)" class="h-full w-full object-cover" />
                                        </div>
                                        <div v-else :class="['h-8 w-8 rounded-full flex items-center justify-center shrink-0 border border-white/20 shadow-inner bg-gradient-to-br', getAvatarGradient(act.name)]">
                                            <span class="text-[10px] font-bold text-zinc-900 dark:text-white drop-shadow-sm">{{ act.name.charAt(0) }}</span>
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

                <!-- Main: 3/4 width on desktop, left side aligned Scan QR→Calendar -->
                <div class="lg:col-span-3 order-first lg:order-first">
                    <div
                        ref="tableRef"
                        class="relative overflow-hidden rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-black shadow-xl"
                    >
                        <div class="flex flex-col border-b border-zinc-200 dark:border-zinc-800 p-3 sm:p-6 gap-3 sm:gap-4 bg-zinc-50 dark:bg-zinc-900/50">
                            <!-- Title row -->
                            <div class="flex items-center justify-between gap-2">
                                <h2 class="text-base sm:text-2xl font-serif font-bold tracking-tight text-foreground">
                                    Today's Attendance Status
                                </h2>
                            </div>

                            <!-- Filter controls: stacks on mobile -->
                            <div class="flex flex-row flex-wrap items-center gap-2 sm:gap-4">
                                <!-- Checkbox filter -->
                                <div class="flex items-center gap-1.5 sm:gap-2 bg-muted/50 px-2 sm:px-3 py-1 sm:py-1.5 rounded-full border border-zinc-200 dark:border-zinc-800 shrink-0">
                                    <input 
                                        type="checkbox" 
                                        id="today-toggle" 
                                        v-model="showOnlyScheduledToday" 
                                        class="w-3 h-3 sm:w-3.5 sm:h-3.5 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-900 shrink-0"
                                    />
                                    <label for="today-toggle" class="text-[8px] sm:text-[10px] font-black uppercase tracking-wider text-zinc-600 dark:text-zinc-400 cursor-pointer whitespace-nowrap">
                                        Scheduled Today
                                    </label>
                                </div>
 
                                <!-- Tabs -->
                                <div class="flex rounded-lg bg-zinc-200/50 dark:bg-zinc-800/50 p-0.5 sm:p-1 border border-zinc-200 dark:border-zinc-800 overflow-x-auto max-w-full shrink-0">
                                    <button
                                        class="rounded-md px-2 sm:px-3 py-0.5 sm:py-1 text-[10px] sm:text-xs font-medium transition-all whitespace-nowrap shrink-0"
                                        :class="activeTab === 'active' ? 'bg-white dark:bg-zinc-700 text-zinc-900 dark:text-white shadow-sm' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white'"
                                        @click="activeTab = 'active'"
                                    >
                                        Active
                                    </button>
                                    <button
                                        class="rounded-md px-2 sm:px-3 py-0.5 sm:py-1 text-[10px] sm:text-xs font-medium transition-all whitespace-nowrap shrink-0"
                                        :class="activeTab === 'deleted' ? 'bg-white dark:bg-zinc-700 text-zinc-900 dark:text-white shadow-sm' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white'"
                                        @click="activeTab = 'deleted'"
                                    >
                                        Trash ({{ props.trashedStudents.length }})
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
                                v-for="(student, index) in paginatedStudents"
                                :key="student.id"
                                v-reveal:[index%10*40]
                                class="transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-900/50 cursor-pointer text-zinc-900 dark:text-zinc-100"
                                @click="activeTab === 'active' ? openStudentInfoModal(student) : null"
                            >
                                <td class="px-2 lg:px-4 py-2 text-xs lg:text-sm font-medium">
                                    <div class="flex items-center gap-2.5">
                                        <!-- Photo/Avatar -->
                                        <div v-if="student.photo" class="h-7 w-7 shrink-0 rounded-full overflow-hidden border border-zinc-200 dark:border-zinc-800 shadow-sm">
                                            <img :src="student.photo" class="h-full w-full object-cover" />
                                        </div>
                                        <div v-else :class="['h-7 w-7 shrink-0 rounded-full flex items-center justify-center bg-gradient-to-br border border-white/20 shadow-inner', getAvatarGradient(student.name)]">
                                            <span class="text-[10px] font-bold text-zinc-900 dark:text-white drop-shadow-sm">{{ student.name.charAt(0) }}</span>
                                        </div>
                                        <span class="flex items-center gap-1.5 flex-wrap">
                                            <span class="" :title="student.name">{{ student.name }}</span>
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
                                    </div>
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
                            v-for="(student, index) in paginatedStudents"
                            :key="student.id"
                            data-student-card
                            v-reveal:[index%10*40]
                            class="group relative overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-black p-4 transition-all hover:shadow-md cursor-pointer"
                            @click="activeTab === 'active' ? openStudentInfoModal(student) : null"
                        >
                            <div class="flex flex-col mb-3">
                                <div class="flex items-start justify-between gap-3 w-full">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <!-- Photo/Avatar -->
                                        <div v-if="student.photo" class="h-10 w-10 shrink-0 rounded-full overflow-hidden border border-zinc-200 dark:border-zinc-800 shadow-sm">
                                            <img :src="student.photo" class="h-full w-full object-cover" />
                                        </div>
                                        <div v-else :class="['h-10 w-10 shrink-0 rounded-full flex items-center justify-center bg-gradient-to-br border border-white/20 shadow-inner', getAvatarGradient(student.name)]">
                                            <span class="text-xs font-bold text-zinc-900 dark:text-white drop-shadow-sm">{{ student.name.charAt(0) }}</span>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <h4 class="font-serif font-bold text-sm line-clamp-2 break-words group-hover:text-zinc-600 dark:group-hover:text-zinc-300 transition-colors text-zinc-900 dark:text-white" :title="student.name">
                                                {{ student.name }}
                                            </h4>
                                            <p class="text-[10px] text-zinc-500 font-mono">
                                                {{ student.student_number }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="shrink-0">
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
                                    class="flex flex-wrap gap-1 mt-3"
                                >
                                    <template v-for="s in student.today_statuses">
                                        <div 
                                            class="h-5 flex items-center gap-1 rounded-full px-1.5 py-0.5 border"
                                            :class="[
                                                s.status === 'Present' ? 'bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 border-zinc-200 dark:border-zinc-700' :
                                                s.status === 'Late' ? 'bg-zinc-200 dark:bg-zinc-700 text-zinc-900 dark:text-white border-zinc-300 dark:border-zinc-600' :
                                                s.status === 'Time Out' ? 'bg-zinc-300 dark:bg-zinc-600 text-zinc-900 dark:text-white border-zinc-400 dark:border-zinc-500' :
                                                'bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900'
                                            ]"
                                            :title="s.status"
                                        >
                                            <CheckCircle2 v-if="s.status !== 'Late' && s.status !== 'Absent'" class="w-2.5 h-2.5" />
                                            <AlertCircle v-else class="w-2.5 h-2.5" />
                                            <span class="text-[8px] font-bold uppercase tracking-wider">{{ s.time }}</span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 px-2 py-0.5 rounded-md text-zinc-500 dark:text-zinc-400 font-bold tracking-tight uppercase">
                                    {{ student.section || 'N/A' }}
                                </span>
                                <span 
                                    v-if="activeTab === 'active'"
                                    class="font-bold text-[10px] uppercase tracking-widest"
                                    :class="[
                                        student.latest_attendance?.status === 'Present' ? 'text-zinc-900 dark:text-white' :
                                        student.latest_attendance?.status === 'Late' ? 'text-zinc-500' :
                                        student.latest_attendance?.status === 'Time Out' ? 'text-zinc-400' :
                                        'text-zinc-300 dark:text-zinc-600'
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

                        <!-- Pagination Footer -->
                        <div v-if="totalPages > 1" class="flex items-center justify-between border-t border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-900/50 p-3 sm:p-4 shrink-0">
                            <div class="text-[10px] sm:text-xs text-zinc-500 dark:text-zinc-400">
                                Showing <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ (currentPage - 1) * itemsPerPage + 1 }}</span> to <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ Math.min(currentPage * itemsPerPage, visibleStudents.length) }}</span> of <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ visibleStudents.length }}</span> results
                            </div>
                            <div class="flex items-center gap-2">
                                <Button 
                                    variant="outline" 
                                    size="sm" 
                                    :disabled="currentPage === 1"
                                    @click="prevPage"
                                    class="h-7 sm:h-8 px-2 sm:px-3 text-[10px] sm:text-xs border-zinc-200 dark:border-zinc-800"
                                >
                                    <ChevronLeft class="h-3 w-3 sm:mr-1" />
                                    <span class="hidden sm:inline">Prev</span>
                                </Button>
                                <div class="flex items-center gap-1">
                                    <span class="text-[10px] sm:text-xs font-medium text-zinc-900 dark:text-zinc-100">
                                        Page {{ currentPage }} of {{ totalPages }}
                                    </span>
                                </div>
                                <Button 
                                    variant="outline" 
                                    size="sm" 
                                    :disabled="currentPage === totalPages"
                                    @click="nextPage"
                                    class="h-7 sm:h-8 px-2 sm:px-3 text-[10px] sm:text-xs border-zinc-200 dark:border-zinc-800"
                                >
                                    <span class="hidden sm:inline">Next</span>
                                    <ChevronRight class="h-3 w-3 sm:ml-1" />
                                </Button>
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
                            <!-- Photo Upload -->
                            <div class="flex flex-col items-center justify-center py-4 border-b border-zinc-100 dark:border-zinc-800/50 mb-2">
                                <div class="relative group cursor-pointer" @click="photoInput?.click()">
                                    <div class="h-24 w-24 rounded-full overflow-hidden border-2 border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-900 flex items-center justify-center transition-all group-hover:border-zinc-400 dark:group-hover:border-zinc-600 shadow-inner">
                                        <img v-if="photoPreview" :src="photoPreview" class="h-full w-full object-cover" />
                                        <div v-else class="flex flex-col items-center text-zinc-400">
                                            <Plus class="h-6 w-6 mb-1 opacity-50" />
                                            <span class="text-[10px] font-bold uppercase tracking-tighter">Photo</span>
                                        </div>
                                        <!-- Overlay -->
                                        <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                            <Scan class="h-5 w-5 text-white" />
                                        </div>
                                    </div>
                                    <input 
                                        type="file" 
                                        ref="photoInput" 
                                        class="hidden" 
                                        accept="image/*" 
                                        @change="e => handlePhotoChange(e, 'create')"
                                    />
                                    <div class="absolute -bottom-1 -right-1 bg-foreground text-background p-1.5 sm:p-2 rounded-2xl shadow-lg animate-bounce">
                                        <Plus class="h-3 w-3" />
                                    </div>
                                </div>
                                <p class="text-[10px] text-zinc-500 mt-2 font-medium uppercase tracking-widest">Student Portrait</p>
                                <p v-if="formErrors.photo" class="text-xs text-destructive mt-1">
                                    {{ Array.isArray(formErrors.photo) ? formErrors.photo[0] : formErrors.photo }}
                                </p>
                            </div>

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
                        <div class="rounded-lg border bg-muted/30 p-4 space-y-4">
                            <div class="flex items-center gap-4">
                                <!-- Student Photo -->
                                <div class="shrink-0">
                                    <div v-if="infoStudent.photo" class="h-16 w-16 rounded-full overflow-hidden border-2 border-white dark:border-zinc-800 shadow-sm">
                                        <img :src="infoStudent.photo" class="h-full w-full object-cover" />
                                    </div>
                                    <div v-else :class="['h-16 w-16 rounded-full flex items-center justify-center bg-gradient-to-br border-2 border-white dark:border-zinc-800 shadow-sm text-lg font-bold text-zinc-900 dark:text-white', getAvatarGradient(infoStudent.name)]">
                                        {{ infoStudent.name.charAt(0) }}
                                    </div>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <p class="text-lg font-bold leading-tight truncate" :title="infoStudent.name">
                                                {{ infoStudent.name }}
                                            </p>
                                            <p class="text-xs text-muted-foreground mt-0.5 font-medium tracking-tight">
                                                {{ infoStudent.student_number }}
                                                <span v-if="infoStudent.section"> · {{ infoStudent.section }}</span>
                                            </p>
                                        </div>
                                        <!-- Today's status badge -->
                                        <span
                                            v-if="infoStudent.latest_attendance"
                                            :class="[
                                                'shrink-0 rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider',
                                                infoStudent.latest_attendance.status === 'Present' ? 'bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900' :
                                                infoStudent.latest_attendance.status === 'Late'    ? 'bg-zinc-200 dark:bg-zinc-700 text-zinc-900 dark:text-white' :
                                                infoStudent.latest_attendance.status === 'Absent'  ? 'bg-rose-500 text-white' :
                                                                                                     'bg-muted text-muted-foreground'
                                            ]"
                                        >
                                            {{ infoStudent.latest_attendance.status }}
                                        </span>
                                    </div>
                                    <p v-if="infoStudent.email" class="text-xs text-muted-foreground mt-1 truncate">
                                        {{ infoStudent.email }}
                                    </p>
                                </div>
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
                            <!-- Photo Upload -->
                            <div class="flex flex-col items-center justify-center py-4 border-b border-zinc-100 dark:border-zinc-800/50 mb-2">
                                <div class="relative group cursor-pointer" @click="editPhotoInput?.click()">
                                    <div class="h-24 w-24 rounded-full overflow-hidden border-2 border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-900 flex items-center justify-center transition-all group-hover:border-zinc-400 dark:group-hover:border-zinc-600 shadow-inner">
                                        <img v-if="editPhotoPreview" :src="editPhotoPreview" class="h-full w-full object-cover" />
                                        <div v-else class="flex flex-col items-center text-zinc-400">
                                            <Plus class="h-6 w-6 mb-1 opacity-50" />
                                            <span class="text-[10px] font-bold uppercase tracking-tighter">Photo</span>
                                        </div>
                                        <!-- Overlay -->
                                        <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                            <Scan class="h-5 w-5 text-white" />
                                        </div>
                                    </div>
                                    <input 
                                        type="file" 
                                        ref="editPhotoInput" 
                                        class="hidden" 
                                        accept="image/*" 
                                        @change="e => handlePhotoChange(e, 'edit')"
                                    />
                                    <div class="absolute -bottom-1 -right-1 bg-foreground text-background p-1.5 sm:p-2 rounded-2xl shadow-lg animate-bounce">
                                        <RefreshCw class="h-3 w-3" />
                                    </div>
                                </div>
                                <p class="text-[10px] text-zinc-500 mt-2 font-medium uppercase tracking-widest">Update Portrait</p>
                                <p v-if="formErrors.photo" class="text-xs text-destructive mt-1">
                                    {{ Array.isArray(formErrors.photo) ? formErrors.photo[0] : formErrors.photo }}
                                </p>
                            </div>

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
                                                <SelectItem v-for="subject in props.subjects" :key="subject.id" :value="String(subject.id)">
                                                    {{ subject.name }}
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
            <Dialog :open="!!confirmModalOpen" @update:open="val => confirmModalOpen = val">
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

            <!-- Welcome Modal -->
            <Dialog :open="!!showWelcomeModal" @update:open="val => showWelcomeModal = val as any">
                <DialogContent class="sm:max-w-[440px] max-w-[95vw] p-0 overflow-hidden border-none bg-transparent shadow-none">
                    <div class="relative bg-card rounded-[32px] overflow-hidden border border-border/50 shadow-2xl animate-in zoom-in-95 duration-300">
                        <!-- Background Glow -->
                        <div class="absolute -top-24 -right-24 w-64 h-64 bg-zinc-500/10 rounded-full blur-[80px]"></div>
                        <div class="absolute -bottom-24 -left-24 w-64 h-64 bg-zinc-500/10 rounded-full blur-[80px]"></div>

                        <div class="relative p-6 sm:p-8 flex flex-col items-center text-center">
                            <!-- Mascot / Icon -->
                            <div class="relative mb-4 sm:mb-6 group">
                                <div class="absolute inset-0 bg-foreground/10 rounded-full blur-2xl group-hover:bg-foreground/20 transition-all duration-500 scale-110"></div>
                                <div class="relative w-24 h-24 sm:w-32 sm:h-32 rounded-full border-4 border-card shadow-xl overflow-hidden bg-background flex items-center justify-center">
                                    <Scan class="w-12 h-12 text-foreground transform group-hover:scale-110 transition-transform duration-500" stroke-width="1.5" />
                                </div>
                                <div class="absolute -bottom-1 -right-1 sm:-bottom-2 sm:-right-2 bg-foreground text-background p-1.5 sm:p-2 rounded-2xl shadow-lg animate-bounce">
                                    <Zap class="w-3 h-3 sm:w-4 sm:h-4" />
                                </div>
                            </div>

                            <DialogHeader class="space-y-2 sm:space-y-3">
                                <DialogTitle class="text-2xl sm:text-3xl font-black font-serif tracking-tight text-foreground">
                                    Welcome Back, <span class="bg-gradient-to-r from-zinc-800 to-zinc-500 dark:from-zinc-200 dark:to-zinc-500 bg-clip-text text-transparent">{{ page.props.auth?.user?.name?.split(' ')[0] || 'Admin' }}</span>!
                                </DialogTitle>
                                <DialogDescription class="text-sm sm:text-base text-muted-foreground font-medium leading-relaxed px-2 sm:px-4">
                                    We're excited to have you back! Your attendance hub is ready. Let's manage some records today.
                                </DialogDescription>
                            </DialogHeader>

                            <div class="mt-6 sm:mt-8 grid grid-cols-2 gap-2 sm:gap-3 w-full">
                                <div class="p-3 sm:p-4 rounded-2xl bg-muted/50 border border-border/40 text-left hover:border-foreground/20 transition-colors">
                                    <p class="text-[9px] sm:text-[10px] font-bold uppercase tracking-wider text-muted-foreground mb-1">Total Students</p>
                                    <p class="text-base sm:text-lg font-bold text-foreground tabular-nums">{{ students.length }}</p>
                                </div>
                                <div class="p-3 sm:p-4 rounded-2xl bg-muted/50 border border-border/40 text-left hover:border-foreground/20 transition-colors">
                                    <p class="text-[9px] sm:text-[10px] font-bold uppercase tracking-wider text-muted-foreground mb-1">At Risk</p>
                                    <p class="text-base sm:text-lg font-bold text-foreground tabular-nums">{{ atRiskCount }}</p>
                                </div>
                            </div>

                            <DialogFooter class="mt-6 sm:mt-8 w-full sm:justify-center">
                                <button @click="closeWelcomeModal" class="w-full sm:w-auto px-8 sm:px-10 py-3 sm:py-4 rounded-[18px] sm:rounded-[20px] bg-foreground text-background font-bold text-sm sm:text-base shadow-lg shadow-black/10 hover:shadow-black/20 hover:scale-[1.02] active:scale-[0.98] transition-all">
                                    Get Started
                                </button>
                            </DialogFooter>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>
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
