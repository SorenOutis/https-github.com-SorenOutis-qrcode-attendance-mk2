<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
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
import gsap from 'gsap';
import { ArrowLeft, BookOpen, TrendingUp, Users, Clock } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { Line, Pie, Bar } from 'vue-chartjs';
import { Button } from '@/components/ui/button';
import SkeletonCard from '@/components/SkeletonCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, BarElement, Title, Tooltip, Legend, ArcElement);

type StudentData = {
    id: number;
    name: string;
    student_number: string;
    section: string | null;
    photo: string | null;
    total_records: number;
    attendance_rate: number;
    present: number;
    late: number;
    absent: number;
    excused: number;
};

const props = defineProps<{
    subject: { id: number; name: string; icon: string | null; color: string | null; description: string | null };
    daily: { date: string; count: number }[];
    statusDistribution: { status: string; count: number }[];
    students: StudentData[];
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

function rateColor(rate: number): string {
    if (rate >= 90) return 'text-zinc-900 dark:text-white';
    if (rate >= 75) return 'text-zinc-600 dark:text-zinc-300';
    return 'text-rose-600 dark:text-rose-400';
}

function rateBg(rate: number): string {
    if (rate >= 90) return 'bg-zinc-900 dark:bg-zinc-100';
    if (rate >= 75) return 'bg-zinc-500';
    return 'bg-rose-500';
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
                ]" :key="i" class="subject-detail-card rounded-xl sm:rounded-3xl border border-zinc-100 dark:border-zinc-800/50 bg-white dark:bg-black p-4 sm:p-6 shadow-xl text-center">
                    <div class="h-8 w-8 sm:h-10 sm:w-10 rounded-xl sm:rounded-2xl bg-zinc-50 dark:bg-zinc-900 flex items-center justify-center mx-auto mb-2 sm:mb-3 border border-zinc-100 dark:border-zinc-800">
                        <component :is="stat.icon" class="h-4 w-4 sm:h-5 sm:w-5 text-zinc-400" />
                    </div>
                    <div class="text-xl sm:text-2xl font-serif font-black">{{ stat.value }}</div>
                    <div class="text-[8px] sm:text-[9px] font-bold text-zinc-400 uppercase tracking-widest mt-1">{{ stat.label }}</div>
                </div>
            </div>

            <div class="grid gap-4 sm:gap-6 md:grid-cols-2 lg:grid-cols-3">
                <!-- Trend Chart -->
                <div class="subject-detail-card md:col-span-2 rounded-xl sm:rounded-[2.5rem] border border-zinc-100 dark:border-zinc-800/50 bg-white dark:bg-black p-4 sm:p-8 shadow-xl sm:shadow-2xl">
                    <div class="mb-3 sm:mb-6 flex items-center gap-2 sm:gap-3">
                        <div class="h-8 w-8 sm:h-10 sm:w-10 rounded-xl sm:rounded-2xl bg-zinc-50 dark:bg-zinc-900 flex items-center justify-center border border-zinc-100 dark:border-zinc-800">
                            <TrendingUp class="h-4 w-4 sm:h-5 sm:w-5 text-zinc-400" />
                        </div>
                        <div>
                            <h3 class="font-serif font-black text-base sm:text-xl tracking-tight leading-none mb-0.5 sm:mb-1">Attendance Trend</h3>
                            <p class="text-[9px] sm:text-[10px] font-bold text-zinc-400 uppercase tracking-widest leading-none">Over Time</p>
                        </div>
                    </div>
                    <div class="h-[220px] sm:h-[350px] w-full">
                        <Line v-if="daily.length" :data="lineData" :options="chartOptions" />
                        <div v-else class="h-full flex items-center justify-center text-sm text-zinc-400">No data in range</div>
                    </div>
                </div>

                <!-- Status Pie -->
                <div class="subject-detail-card rounded-xl sm:rounded-[2.5rem] border border-zinc-100 dark:border-zinc-800/50 bg-white dark:bg-black p-4 sm:p-8 shadow-xl sm:shadow-2xl">
                    <div class="mb-3 sm:mb-6 flex items-center gap-2 sm:gap-3">
                        <div class="h-8 w-8 sm:h-10 sm:w-10 rounded-xl sm:rounded-2xl bg-zinc-50 dark:bg-zinc-900 flex items-center justify-center border border-zinc-100 dark:border-zinc-800">
                            <Clock class="h-4 w-4 sm:h-5 sm:w-5 text-zinc-400" />
                        </div>
                        <div>
                            <h3 class="font-serif font-black text-base sm:text-xl tracking-tight leading-none mb-0.5 sm:mb-1">Status Mix</h3>
                            <p class="text-[9px] sm:text-[10px] font-bold text-zinc-400 uppercase tracking-widest leading-none">Distribution</p>
                        </div>
                    </div>
                    <div class="h-[220px] sm:h-[350px] w-full">
                        <Pie v-if="statusDistribution.length" :data="pieData" :options="chartOptions" />
                        <div v-else class="h-full flex items-center justify-center text-sm text-zinc-400">No data</div>
                    </div>
                </div>
            </div>

            <!-- Student Leaderboard -->
            <div class="subject-detail-card rounded-xl sm:rounded-[2.5rem] border border-zinc-100 dark:border-zinc-800/50 bg-white dark:bg-black p-4 sm:p-8 shadow-xl sm:shadow-2xl">
                <div class="mb-4 sm:mb-6 flex items-center gap-2 sm:gap-3">
                    <div class="h-8 w-8 sm:h-10 sm:w-10 rounded-xl sm:rounded-2xl bg-zinc-50 dark:bg-zinc-900 flex items-center justify-center border border-zinc-100 dark:border-zinc-800">
                        <Users class="h-4 w-4 sm:h-5 sm:w-5 text-zinc-400" />
                    </div>
                    <div>
                        <h3 class="font-serif font-black text-base sm:text-xl tracking-tight leading-none mb-0.5 sm:mb-1">Student Leaderboard</h3>
                        <p class="text-[9px] sm:text-[10px] font-bold text-zinc-400 uppercase tracking-widest leading-none">Sorted by Attendance Rate</p>
                    </div>
                </div>

                <div v-if="students.length" class="space-y-2">
                    <Link
                        v-for="(student, idx) in students"
                        :key="student.id"
                        :href="`/students/${student.id}/analytics`"
                        class="flex items-center gap-3 sm:gap-4 rounded-xl sm:rounded-2xl border border-zinc-50 dark:border-zinc-900/50 bg-zinc-50/50 dark:bg-zinc-900/20 px-3 sm:px-5 py-3 sm:py-4 hover:bg-zinc-100 dark:hover:bg-zinc-900/40 transition-colors group"
                    >
                        <span class="text-[10px] font-black text-zinc-300 dark:text-zinc-700 w-6 text-center">{{ idx + 1 }}</span>
                        <div class="h-9 w-9 sm:h-10 sm:w-10 rounded-xl bg-zinc-200 dark:bg-zinc-800 overflow-hidden shrink-0">
                            <img v-if="student.photo" :src="student.photo" class="h-full w-full object-cover" :alt="student.name" />
                            <div v-else class="h-full w-full flex items-center justify-center text-xs font-bold text-zinc-400">
                                {{ student.name.charAt(0) }}
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-bold truncate">{{ student.name }}</div>
                            <div class="text-[10px] text-zinc-400 font-medium">{{ student.student_number }} <span v-if="student.section">• {{ student.section }}</span></div>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="text-sm sm:text-base font-black" :class="rateColor(student.attendance_rate)">
                                {{ student.attendance_rate }}%
                            </div>
                            <div class="text-[8px] font-bold text-zinc-400 uppercase tracking-wider">{{ student.total_records }} records</div>
                        </div>
                        <div class="hidden sm:block w-24">
                            <div class="w-full h-1.5 rounded-full bg-zinc-100 dark:bg-zinc-900 overflow-hidden">
                                <div class="h-full rounded-full transition-all" :class="rateBg(student.attendance_rate)" :style="{ width: `${student.attendance_rate}%` }"></div>
                            </div>
                        </div>
                    </Link>
                </div>
                <div v-else class="text-center py-8 text-sm text-zinc-400">No students enrolled in this subject.</div>
            </div>
        </div>
    </AppLayout>
</template>
