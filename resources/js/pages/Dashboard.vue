<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, nextTick, watch } from 'vue';
import { useStorage } from '@vueuse/core';
import gsap from 'gsap';
import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';
import { useToast } from '@/composables/useToast';

// Layout & UI Components
import AppLayout from '@/layouts/AppLayout.vue';

// Dashboard Components
import DashboardHeader from '@/components/dashboard/DashboardHeader.vue';
import StatCards from '@/components/dashboard/StatCards.vue';
import QuickActions from '@/components/dashboard/QuickActions.vue';
import StudentList from '@/components/dashboard/StudentList.vue';
import AtRiskList from '@/components/dashboard/AtRiskList.vue';
import AttendanceChart from '@/components/dashboard/AttendanceChart.vue';
import LiveScanFeed from '@/components/dashboard/LiveScanFeed.vue';

// Modal Components
import StudentInfoModal from '@/components/dashboard/modals/StudentInfoModal.vue';
import StudentFormModal from '@/components/dashboard/modals/StudentFormModal.vue';
import QrCodeModal from '@/components/dashboard/modals/QrCodeModal.vue';
import ImportModal from '@/components/dashboard/modals/ImportModal.vue';
import ConfirmModal from '@/components/dashboard/modals/ConfirmModal.vue';
import WelcomeModal from '@/components/dashboard/modals/WelcomeModal.vue';

// Icons for QuickActions
import { Scan, PieChart, Calendar, UserPlus } from 'lucide-vue-next';

interface User {
    id: number;
    name: string;
    email: string;
}

interface Student {
    id: number;
    name: string;
    student_number: string;
    email: string | null;
    section: string | null;
    photo: string | null;
    qr_token: string;
    attendance_percentage: number;
    deleted_at: string | null;
    latest_attendance?: {
        id: number;
        status: string;
        scanned_at: string;
    } | null;
    today_statuses?: {
        status: string;
        time: string;
        subject_id?: number | string;
        subject_name?: string;
    }[];
    schedule?: {
        day: string;
        start: string;
        end: string;
        subject_id: number;
    }[];
}

interface Subject {
    id: number;
    name: string;
    description: string | null;
}

interface PageProps {
    students: {
        data: Student[];
    };
    trashedCount: number;
    subjects: Subject[];
    attendanceStats?: {
        Present: number;
        Late: number;
        Absent: number;
        Excused: number;
    };
    attendanceRate: number;
    recentActivity: Student[];
    auth: {
        user: User;
    };
}

const page = usePage<any>();
const props = computed(() => page.props as unknown as PageProps);
const toast = useToast();

// --- State ---
const activeTab = ref<'active' | 'deleted'>('active');
const viewMode = ref<'table' | 'grid'>('grid');
const searchQuery = ref('');
const statusFilter = ref<string | null>(null);
const showOnlyScheduledToday = ref(false);

const currentPage = ref(1);
const itemsPerPage = 12;

// --- Modals State ---
const infoModalOpen = ref(false);
const createModalOpen = ref(false);
const editModalOpen = ref(false);
const qrModalOpen = ref(false);
const importModalOpen = ref(false);
const confirmModalOpen = ref(false);
const welcomeModalOpen = ref(false);

const infoStudent = ref<Student | null>(null);
const selectedStudent = ref<Student | null>(null);
const editingStudentId = ref<number | null>(null);

// Form State
const form = ref({
    name: '',
    student_number: '',
    email: '',
    section: '',
    photo: null as File | null,
    photoPreview: null as string | null,
    selectedSubjectIds: [] as number[],
});

const formErrors = ref<Record<string, string | string[]>>({});
const submitting = ref(false);

// History State
const attendanceHistory = ref<any[]>([]);
const historyLoading = ref(false);
const updatingRecordId = ref<number | null>(null);

// Stats Animation State
const animatedStats = ref({
    total: 0,
    present: 0,
    late: 0,
    absent: 0
});

// Confirm Modal State
const confirmConfig = ref({
    title: '',
    description: '',
    isDestructive: false,
    onConfirm: () => {}
});

// Import State
const importFile = ref<File | null>(null);
const importing = ref(false);

// --- Computed ---
const greeting = computed(() => {
    const hour = new Date().getHours();
    if (hour < 12) return 'Good Morning';
    if (hour < 18) return 'Good Afternoon';
    return 'Good Evening';
});

const todayDayName = computed(() => {
    return new Intl.DateTimeFormat('en-US', { weekday: 'long' }).format(new Date());
});

const visibleStudents = computed(() => {
    let list = props.value.students.data || [];
    
    if (activeTab.value === 'active') {
        list = list.filter(s => !s.deleted_at);
        if (showOnlyScheduledToday.value) {
            list = list.filter(s => s.schedule?.some(slot => slot.day === todayDayName.value));
        }
    } else {
        list = list.filter(s => s.deleted_at);
    }
    
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(s => 
            s.name.toLowerCase().includes(q) || 
            s.student_number.toLowerCase().includes(q) || 
            s.section?.toLowerCase().includes(q)
        );
    }
    
    if (statusFilter.value && activeTab.value === 'active') {
        list = list.filter(s => s.latest_attendance?.status === statusFilter.value);
    }
    
    return list;
});

const totalPages = computed(() => Math.ceil(visibleStudents.value.length / itemsPerPage));

const paginatedStudents = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    return visibleStudents.value.slice(start, start + itemsPerPage);
});

const atRiskStudents = computed(() => {
    return (props.value.students.data || [])
        .filter(s => !s.deleted_at && (s.attendance_percentage ?? 100) < 80)
        .sort((a, b) => (a.attendance_percentage ?? 0) - (b.attendance_percentage ?? 0))
        .slice(0, 10);
});

const mappedActivity = computed(() => {
    return (props.value.recentActivity || []).map(s => ({
        name: s.name,
        photo: s.photo,
        status: s.latest_attendance?.status || 'Active',
        time: s.latest_attendance?.scanned_at ? formatTime(s.latest_attendance.scanned_at) : 'Just now',
        subject_name: s.latest_attendance?.subject_name
    }));
});

// --- Action Configs ---
const quickActions = [
    { label: 'Scanner', sub: 'Live Check-in', icon: Scan, onClick: () => router.visit('/attendance/scan'), primary: true, tourId: 'tour-scan' },
    { label: 'Records', sub: 'View History', icon: PieChart, href: '/manage-attendance', tourId: 'tour-reports' },
    { label: 'Schedule', sub: 'Class Timing', icon: Calendar, href: '/subject-attendance', tourId: 'tour-schedule' },
    { label: 'Add', sub: 'New Student', icon: UserPlus, onClick: () => { createModalOpen.value = true; }, tourId: 'tour-add-student' },
];

// --- Methods ---

function formatTime(dateStr: string) {
    const date = new Date(dateStr);
    return date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', hour12: true });
}

function formatTimeTo12h(timeStr?: string) {
    if (!timeStr) return '';
    const parts = timeStr.split(':');
    if (parts.length < 2) return timeStr;
    let h = parseInt(parts[0]);
    const m = parts[1];
    const ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12 || 12;
    return `${h}:${m} ${ampm}`;
}

function getSubjectName(id: number | string | null | undefined) {
    if (id === null || id === undefined) return 'Unknown Subject';
    return props.value.subjects.find(s => s.id === Number(id))?.name || 'Unknown Subject';
}

function handlePhotoChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (file) {
        form.value.photo = file;
        form.value.photoPreview = URL.createObjectURL(file);
    }
}

function toggleSubject(id: number) {
    const idx = form.value.selectedSubjectIds.indexOf(id);
    if (idx > -1) form.value.selectedSubjectIds.splice(idx, 1);
    else form.value.selectedSubjectIds.push(id);
}

// --- API Methods ---

async function submitStudent() {
    submitting.value = true;
    formErrors.value = {};
    
    const url = editingStudentId.value ? `/students/${editingStudentId.value}` : '/students';
    const method = editingStudentId.value ? 'PUT' : 'POST';
    
    router.post(url, {
        _method: method,
        name: form.value.name,
        student_number: form.value.student_number,
        email: form.value.email || null,
        section: form.value.section || null,
        subjects: form.value.selectedSubjectIds,
        photo: form.value.photo,
    }, {
        onSuccess: () => {
            createModalOpen.value = false;
            editModalOpen.value = false;
            resetForm();
            toast.success(editingStudentId.value ? 'Student updated' : 'Student enrolled');
        },
        onError: (errors) => {
            formErrors.value = errors as any;
            toast.error('Operation failed');
        },
        onFinish: () => submitting.value = false,
        preserveScroll: true,
    });
}

function resetForm() {
    form.value = {
        name: '',
        student_number: '',
        email: '',
        section: '',
        photo: null,
        photoPreview: null,
        selectedSubjectIds: [],
    };
    editingStudentId.value = null;
    formErrors.value = {};
}

function openStudentInfoModal(student: Student) {
    infoStudent.value = student;
    infoModalOpen.value = true;
    fetchAttendanceHistory(student.id);
}

async function fetchAttendanceHistory(studentId: number) {
    historyLoading.value = true;
    try {
        const response = await fetch(`/students/${studentId}/attendance-history`);
        attendanceHistory.value = await response.json();
    } catch (e) {
        toast.error('Failed to load history');
    } finally {
        historyLoading.value = false;
    }
}

function openEditFromInfo() {
    if (!infoStudent.value) return;
    const s = infoStudent.value;
    editingStudentId.value = s.id;
    form.value = {
        name: s.name,
        student_number: s.student_number,
        email: s.email || '',
        section: s.section || '',
        photo: null,
        photoPreview: s.photo,
        selectedSubjectIds: s.schedule?.map(sc => sc.subject_id) || [],
    };
    infoModalOpen.value = false;
    editModalOpen.value = true;
}

function deleteStudent(id: number) {
    confirmConfig.value = {
        title: 'Archive Student?',
        description: 'This student will be moved to trash. You can restore them later.',
        isDestructive: true,
        onConfirm: () => {
            router.delete(`/students/${id}`, {
                onSuccess: () => {
                    infoModalOpen.value = false;
                    confirmModalOpen.value = false;
                    toast.success('Student archived');
                }
            });
        }
    };
    confirmModalOpen.value = true;
}

function restoreStudent(id: number) {
    router.post(`/students/${id}/restore`, {}, {
        onSuccess: () => toast.success('Student restored')
    });
}

function forceDeleteStudent(id: number) {
    confirmConfig.value = {
        title: 'Permanent Delete?',
        description: 'This action cannot be undone. All attendance records will be lost.',
        isDestructive: true,
        onConfirm: () => {
            router.delete(`/students/${id}/force`, {
                onSuccess: () => {
                    confirmModalOpen.value = false;
                    toast.success('Deleted permanently');
                }
            });
        }
    };
    confirmModalOpen.value = true;
}

function updateHistoryStatus(recordId: number, status: string) {
    if (updatingRecordId.value) return;
    updatingRecordId.value = recordId;

    router.put(`/attendance/${recordId}`, { status }, {
        preserveScroll: true,
        onSuccess: () => {
            const record = attendanceHistory.value.find(r => r.id === recordId);
            if (record) record.status = status;
            toast.success('Status updated');
        },
        onFinish: () => updatingRecordId.value = null
    });
}

// QR Methods
function openQrFromInfo() {
    if (!infoStudent.value) return;
    selectedStudent.value = infoStudent.value;
    qrModalOpen.value = true;
}

function regenerateQr() {
    if (!selectedStudent.value) return;
    router.post(`/students/${selectedStudent.value.id}/qr/regenerate`, {}, {
        onSuccess: () => {
            toast.success('QR Token regenerated');
            router.visit(window.location.href, {
                only: ['students'],
                preserveScroll: true,
                onSuccess: (p) => {
                    const updated = (p.props as any).students.data.find((s: any) => s.id === selectedStudent.value?.id);
                    if (updated) selectedStudent.value = updated;
                }
            });
        }
    });
}

function downloadQr() {
    if (!selectedStudent.value) return;
    const canvas = document.querySelector('#qr-canvas canvas') as HTMLCanvasElement | null;
    if (!canvas) return;
    const link = document.createElement('a');
    link.download = `qr-${selectedStudent.value.student_number}.png`;
    link.href = canvas.toDataURL('image/png');
    link.click();
}

const studentPortalUrl = (token: string) => {
    return `${window.location.origin}/portal/${encodeURIComponent(token)}`;
};

function copyPortalLink() {
    const url = studentPortalUrl(selectedStudent.value?.qr_token || '');
    navigator.clipboard.writeText(url);
    toast.success('Link copied to clipboard');
}

// --- Import Logic ---
function handleFileChange(e: Event) {
    importFile.value = (e.target as HTMLInputElement).files?.[0] || null;
}

async function submitImport() {
    if (!importFile.value) return;
    importing.value = true;
    
    const formData = new FormData();
    formData.append('file', importFile.value);
    
    router.post('/students/import', formData as any, {
        onSuccess: () => {
            importModalOpen.value = false;
            importFile.value = null;
            toast.success('Students imported successfully');
        },
        onError: () => toast.error('Import failed'),
        onFinish: () => importing.value = false
    });
}

// --- Lifecycle & Animations ---
onMounted(() => {
    nextTick(() => {
        animateStats();

        // Entrance animations — target all major dashboard sections
        const targets = document.querySelectorAll('[data-card], [data-section]');
        if (targets.length) {
            gsap.from(targets, {
                opacity: 0,
                y: 24,
                stagger: 0.08,
                duration: 0.7,
                ease: 'power3.out',
                clearProps: 'all',
            });
        }
    });

    const tourStore = useStorage('has-seen-tour-v2', false);
    if (!tourStore.value) {
        setTimeout(startTour, 1800);
    }
});

function animateStats() {
    const stats = props.value.attendanceStats || { Present: 0, Late: 0, Absent: 0, Excused: 0 };
    const total = props.value.students.data?.length || 0;

    gsap.to(animatedStats.value, {
        total,
        present: stats.Present,
        late: stats.Late,
        absent: stats.Absent,
        duration: 1.8,
        ease: 'power4.out',
        snap: { total: 1, present: 1, late: 1, absent: 1 },
    });
}

watch(() => props.value.attendanceStats, animateStats, { deep: true });

// --- Chart Logic ---
const chartData = computed(() => {
    const s = props.value.attendanceStats || { Present: 0, Late: 0, Absent: 0, Excused: 0 };
    return {
        labels: ['Present', 'Late', 'Absent', 'Excused'],
        datasets: [{
            data: [s.Present, s.Late, s.Absent, s.Excused],
            backgroundColor: ['#10b981', '#f59e0b', '#ef4444', '#6366f1'],
            borderWidth: 0,
            hoverOffset: 10
        }]
    };
});

const chartOptions = {
    cutout: '75%',
    plugins: {
        legend: { display: false }
    },
    responsive: true,
    maintainAspectRatio: false
};

// --- Tour ---
const startTour = () => {
    const d = driver({
        showProgress: true,
        steps: [
            { element: '#dashboard-welcome', popover: { title: 'Welcome', description: 'Your new premium management dashboard.', side: 'bottom' } },
            { element: '[data-tour="stats"]', popover: { title: 'Live Stats', description: 'Real-time attendance performance metrics.', side: 'bottom' } },
            { element: '[data-tour="search"]', popover: { title: 'Global Search', description: 'Instantly find students, sections, or IDs.', side: 'bottom' } },
            { element: '#tour-scan', popover: { title: 'QR Scanner', description: 'Tap to launch the high-speed attendance scanner.', side: 'left' } },
            { element: '#tour-add-student', popover: { title: 'Enrollment', description: 'Add students manually or import in bulk.', side: 'bottom' } }
        ]
    });
    d.drive();
    useStorage('has-seen-tour-v2', true).value = true;
};
</script>

<template>
    <AppLayout title="Dashboard">
        <Head title="Dashboard" />

        <div class="max-w-[1600px] mx-auto space-y-4 sm:space-y-8 pb-20 px-4 sm:px-6 lg:px-8 pt-2 sm:pt-0">
            <!-- Header Section -->
            <DashboardHeader 
                :greeting="greeting"
                :user-name="props.auth.user.name"
                :attendance-rate="props.attendanceRate"
                :rate-color-class="props.attendanceRate >= 90 ? 'text-emerald-500' : props.attendanceRate >= 75 ? 'text-amber-500' : 'text-rose-500'"
            />

            <!-- Stats & Quick Actions -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 sm:gap-6 items-start">
                <div class="lg:col-span-8 space-y-4 sm:space-y-6" data-tour="stats" data-section>
                    <StatCards 
                        :attendance-stats="props.attendanceStats"
                        :attendance-rate="props.attendanceRate"
                        :animated-stats="animatedStats"
                        v-model:status-filter="statusFilter"
                    />
                </div>

                <div class="lg:col-span-4" data-tour="actions" data-section>
                    <QuickActions 
                        :actions="quickActions"
                        @action="href => router.visit(href)"
                    />
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                <!-- Left: Student List -->
                <div class="lg:col-span-8 space-y-6" data-section>
                    <StudentList 
                        :students="paginatedStudents"
                        v-model:active-tab="activeTab"
                        v-model:view-mode="viewMode"
                        v-model:search-query="searchQuery"
                        v-model:show-only-scheduled-today="showOnlyScheduledToday"
                        :today-day-name="todayDayName"
                        :current-page="currentPage"
                        :total-pages="totalPages"
                        :trashed-count="props.trashedCount"
                        @next-page="currentPage++"
                        @prev-page="currentPage--"
                        @open-info="openStudentInfoModal"
                        @open-create="createModalOpen = true"
                    />
                </div>

                <!-- Right: High Impact Widgets -->
                <div class="lg:col-span-4 space-y-6" data-section>
                    <AtRiskList 
                        :at-risk-students="atRiskStudents" 
                        @select="openStudentInfoModal"
                    />

                    <AttendanceChart 
                        :attendance-stats="props.attendanceStats"
                        :chart-data="chartData"
                        :chart-options="chartOptions"
                    />

                    <LiveScanFeed 
                        :recent-activity="mappedActivity"
                    />
                </div>
            </div>
        </div>

        <!-- Modals -->
        <StudentInfoModal 
            v-model:open="infoModalOpen"
            :student="infoStudent"
            :attendance-history="attendanceHistory"
            :history-loading="historyLoading"
            :updating-record-id="updatingRecordId"
            :get-subject-name="getSubjectName"
            :format-time-to-12h="formatTimeTo12h"
            @edit="openEditFromInfo"
            @view-qr="openQrFromInfo"
            @delete="deleteStudent(infoStudent!.id)"
            @update-status="updateHistoryStatus"
            @mark-manually="subjId => router.visit(`/manage-attendance/${subjId}/${new Date().toISOString().split('T')[0]}`)"
        />

        <StudentFormModal 
            v-model:open="createModalOpen"
            mode="create"
            :submitting="submitting"
            :form-errors="formErrors"
            :subjects="props.subjects"
            v-model:name="form.name"
            v-model:student-number="form.student_number"
            v-model:email="form.email"
            v-model:section="form.section"
            v-model:selected-subject-ids="form.selectedSubjectIds"
            :photo-preview="form.photoPreview"
            :schedules="[]"
            :get-subject-name="getSubjectName"
            :format-time-to-12h="formatTimeTo12h"
            @handle-photo-change="handlePhotoChange"
            @toggle-subject="toggleSubject"
            @submit="submitStudent"
        />

        <StudentFormModal 
            v-model:open="editModalOpen"
            mode="edit"
            :submitting="submitting"
            :form-errors="formErrors"
            :subjects="props.subjects"
            v-model:name="form.name"
            v-model:student-number="form.student_number"
            v-model:email="form.email"
            v-model:section="form.section"
            v-model:selected-subject-ids="form.selectedSubjectIds"
            :photo-preview="form.photoPreview"
            :schedules="[]"
            :get-subject-name="getSubjectName"
            :format-time-to-12h="formatTimeTo12h"
            @handle-photo-change="handlePhotoChange"
            @toggle-subject="toggleSubject"
            @submit="submitStudent"
        />

        <QrCodeModal 
            v-model:open="qrModalOpen"
            :student="selectedStudent"
            :qr-canvas="null"
            :student-portal-url="studentPortalUrl"
            @regenerate="regenerateQr"
            @download="downloadQr"
            @print="() => (window as any).open(`/students/print-cards?ids=${selectedStudent?.id}`, '_blank')"
            @copy-link="copyPortalLink"
        />

        <ImportModal 
            v-model:open="importModalOpen"
            :importing="importing"
            :import-file="importFile"
            @file-change="handleFileChange"
            @submit="submitImport"
        />

        <ConfirmModal 
            v-model:open="confirmModalOpen"
            :title="confirmConfig.title"
            :description="confirmConfig.description"
            :is-destructive="confirmConfig.isDestructive"
            @confirm="confirmConfig.onConfirm"
        />

        <WelcomeModal 
            v-model:open="welcomeModalOpen"
            :user-name="props.auth.user.name"
            :student-count="props.students.data.length"
            :at-risk-count="atRiskStudents.length"
            @close="welcomeModalOpen = false"
        />

    </AppLayout>
</template>

<style scoped>
/* Page-level fade-in for the main wrapper */
.fade-enter-active {
    transition: opacity 0.4s ease, transform 0.4s ease;
}
.fade-leave-active {
    transition: opacity 0.2s ease;
}
.fade-enter-from {
    opacity: 0;
    transform: translateY(8px);
}
.fade-leave-to {
    opacity: 0;
}
</style>
