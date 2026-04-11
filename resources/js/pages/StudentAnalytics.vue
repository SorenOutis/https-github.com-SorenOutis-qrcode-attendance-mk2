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
} from 'chart.js';
import gsap from 'gsap';
import { ArrowLeft, Flame, TrendingUp, BookOpen, Calendar, Download, Award, BarChart3 } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { Line, Bar } from 'vue-chartjs';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, BarElement, Title, Tooltip, Legend);

type DailyTrend = { date: string; present: number; late: number; absent: number; excused: number };
type SubjectBreakdown = { id: number; name: string; total_records: number; attendance_rate: number; present: number; late: number; absent: number; excused: number };
type HeatmapDay = { date: string; status: string; count: number };
type DetailedHistory = { 
    date: string; 
    day: string; 
    subject_name: string; 
    schedule_time: string; 
    status: string; 
    scanned_at: string | null 
};
type EnrolledSubject = { id: number; name: string; time: string; day: string };

const props = defineProps<{
    student: { id: number; name: string; student_number: string; section: string | null; photo: string | null };
    dailyTrend: DailyTrend[];
    subjectBreakdown: SubjectBreakdown[];
    detailedHistory: DetailedHistory[];
    enrolledSubjects: EnrolledSubject[];
    streak: { current: number; longest: number };
    stats: { total_records: number; percentage: number; present: number; late: number; absent: number; excused: number };
    heatmap: HeatmapDay[];
    filters: { days: number };
    subjects: { id: number; name: string }[];
}>();

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Student Analytics', href: '' },
    { title: props.student.name, href: '' },
];

const selectedDays = ref(props.filters.days);

const trendLineData = computed(() => ({
    labels: props.dailyTrend.map((d) => d.date),
    datasets: [
        {
            label: 'Present',
            borderColor: '#09090b',
            backgroundColor: '#09090b',
            data: props.dailyTrend.map((d) => d.present),
            tension: 0.4,
            pointRadius: 2,
        },
        {
            label: 'Late',
            borderColor: '#71717a',
            backgroundColor: '#71717a',
            data: props.dailyTrend.map((d) => d.late),
            tension: 0.4,
            pointRadius: 2,
        },
        {
            label: 'Absent',
            borderColor: '#e4e4e7',
            backgroundColor: '#e4e4e7',
            data: props.dailyTrend.map((d) => d.absent),
            tension: 0.4,
            pointRadius: 2,
        },
    ],
}));

const subjectBarData = computed(() => ({
    labels: props.subjectBreakdown.map((s) => s.name),
    datasets: [
        {
            label: 'Attendance Rate %',
            backgroundColor: props.subjectBreakdown.map((s) =>
                s.attendance_rate >= 90 ? '#09090b' : s.attendance_rate >= 75 ? '#71717a' : '#ef4444',
            ),
            data: props.subjectBreakdown.map((s) => s.attendance_rate),
            borderRadius: 8,
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
    layout: { padding: { top: 10 } },
};

const barOptions = {
    ...chartOptions,
    scales: {
        y: { beginAtZero: true, max: 100, ticks: { font: { size: 10 } } },
        x: { ticks: { font: { size: 10 } } },
    },
};

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

function heatmapColor(status: string): string {
    if (status === 'present') return 'bg-zinc-900 dark:bg-zinc-100';
    if (status === 'absent') return 'bg-rose-400 dark:bg-rose-600';
    return 'bg-zinc-300 dark:bg-zinc-700';
}

function exportCsv() {
    window.location.href = `/exports/student-analytics/${props.student.id}`;
}

// Build a 90-day grid from heatmap data
const heatmapGrid = computed(() => {
    const today = new Date();
    const days: { date: string; status: string | null }[] = [];
    for (let i = 89; i >= 0; i--) {
        const d = new Date(today);
        d.setDate(d.getDate() - i);
        const iso = d.toISOString().split('T')[0];
        const match = props.heatmap.find((h) => h.date === iso);
        days.push({ date: iso, status: match?.status ?? null });
    }
    return days;
});

onMounted(() => {
    gsap.from('.analytics-card', {
        opacity: 0,
        y: 20,
        stagger: 0.06,
        duration: 0.5,
        ease: 'power2.out',
    });
});
</script>

<template>
    <Head :title="`${student.name} — Analytics`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 sm:gap-6 p-3 sm:p-6 lg:p-8 pb-20 md:pb-6 w-full overflow-x-hidden animate-in fade-in slide-in-from-bottom-4 duration-700">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 sm:gap-6 px-1">
                <div>
                    <Link href="/dashboard" class="flex items-center gap-1 text-[10px] font-black uppercase tracking-widest text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 transition-colors mb-2">
                        <ArrowLeft class="h-3.5 w-3.5" />
                        Dashboard
                    </Link>
                    <p class="text-[10px] font-black uppercase tracking-[0.25em] text-zinc-400 dark:text-zinc-500 mb-1 leading-none">
                        Student Analytics
                    </p>
                    <h1 class="text-xl sm:text-4xl lg:text-5xl font-serif font-black tracking-tight text-zinc-900 dark:text-white leading-tight">
                        {{ student.name }}
                    </h1>
                    <p class="text-xs sm:text-sm text-zinc-400 font-medium mt-1">{{ student.student_number }} <span v-if="student.section">• {{ student.section }}</span></p>
                </div>
                <div class="flex items-center gap-2">
                    <Button variant="outline" @click="exportCsv" class="rounded-full h-9 sm:h-11 px-4 sm:px-6 border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white hover:bg-zinc-50 dark:hover:bg-zinc-800 shadow-sm font-bold text-[10px] sm:text-xs uppercase tracking-widest">
                        <Download class="mr-1.5 h-3.5 w-3.5" stroke-width="2.5" />
                        Export
                    </Button>
                </div>
            </div>

            <!-- Summary Stats Row -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
                <div class="analytics-card rounded-xl sm:rounded-3xl border border-zinc-100 dark:border-zinc-800/50 bg-white dark:bg-black p-4 sm:p-6 shadow-xl text-center">
                    <div class="text-3xl sm:text-4xl font-serif font-black" :class="rateColor(stats.percentage)">
                        {{ stats.percentage }}<span class="text-xl opacity-50">%</span>
                    </div>
                    <div class="text-[8px] sm:text-[9px] font-bold text-zinc-400 uppercase tracking-widest mt-1">Overall Rate</div>
                    <div class="w-16 h-1.5 rounded-full bg-zinc-100 dark:bg-zinc-900 overflow-hidden mx-auto mt-2">
                        <div class="h-full rounded-full" :class="rateBg(stats.percentage)" :style="{ width: `${stats.percentage}%` }"></div>
                    </div>
                </div>

                <div class="analytics-card rounded-xl sm:rounded-3xl border border-zinc-100 dark:border-zinc-800/50 bg-white dark:bg-black p-4 sm:p-6 shadow-xl text-center">
                    <div class="text-3xl sm:text-4xl font-serif font-black">{{ stats.total_records }}</div>
                    <div class="text-[8px] sm:text-[9px] font-bold text-zinc-400 uppercase tracking-widest mt-1">Total Records</div>
                </div>

                <div class="analytics-card rounded-xl sm:rounded-3xl border border-zinc-100 dark:border-zinc-800/50 bg-white dark:bg-black p-4 sm:p-6 shadow-xl text-center relative overflow-hidden group">
                    <div class="absolute -right-4 -top-3 text-zinc-100 dark:text-zinc-800/30 group-hover:scale-110 group-hover:text-amber-50 dark:group-hover:text-amber-900/30 transition-all duration-500 pointer-events-none">
                        <Flame class="w-20 h-20" stroke-width="1.5" />
                    </div>
                    <div class="relative z-10">
                        <div class="text-3xl sm:text-4xl font-serif font-black flex items-center justify-center gap-1">
                            {{ streak.current }}
                            <Flame v-if="streak.current > 2" class="w-5 h-5 text-amber-500 dark:text-amber-400 animate-pulse" stroke-width="3" />
                        </div>
                        <div class="text-[8px] sm:text-[9px] font-bold text-zinc-400 uppercase tracking-widest mt-1">Current Streak</div>
                    </div>
                </div>

                <div class="analytics-card rounded-xl sm:rounded-3xl border border-zinc-100 dark:border-zinc-800/50 bg-white dark:bg-black p-4 sm:p-6 shadow-xl text-center">
                    <div class="text-3xl sm:text-4xl font-serif font-black flex items-center justify-center gap-1">
                        {{ streak.longest }}
                        <Award class="w-5 h-5 text-zinc-400" />
                    </div>
                    <div class="text-[8px] sm:text-[9px] font-bold text-zinc-400 uppercase tracking-widest mt-1">Longest Streak</div>
                </div>
            </div>

            <!-- Attendance Heatmap -->
            <div class="analytics-card rounded-xl sm:rounded-[2.5rem] border border-zinc-100 dark:border-zinc-800/50 bg-white dark:bg-black p-4 sm:p-8 shadow-xl sm:shadow-2xl">
                <div class="mb-3 sm:mb-6 flex items-center gap-2 sm:gap-3">
                    <div class="h-8 w-8 sm:h-10 sm:w-10 rounded-xl sm:rounded-2xl bg-zinc-50 dark:bg-zinc-900 flex items-center justify-center border border-zinc-100 dark:border-zinc-800">
                        <Calendar class="h-4 w-4 sm:h-5 sm:w-5 text-zinc-400" />
                    </div>
                    <div>
                        <h3 class="font-serif font-black text-base sm:text-xl tracking-tight leading-none mb-0.5 sm:mb-1">Attendance Heatmap</h3>
                        <p class="text-[9px] sm:text-[10px] font-bold text-zinc-400 uppercase tracking-widest leading-none">Last 90 Days</p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-1">
                    <div
                        v-for="day in heatmapGrid"
                        :key="day.date"
                        :title="day.date + (day.status ? ` — ${day.status}` : '')"
                        class="w-3 h-3 sm:w-3.5 sm:h-3.5 rounded-sm transition-colors"
                        :class="day.status ? heatmapColor(day.status) : 'bg-zinc-50 dark:bg-zinc-900/50'"
                    ></div>
                </div>
                <div class="flex items-center gap-3 mt-3 text-[9px] font-bold text-zinc-400 uppercase tracking-widest">
                    <div class="flex items-center gap-1"><div class="w-3 h-3 rounded-sm bg-zinc-900 dark:bg-zinc-100"></div> Present</div>
                    <div class="flex items-center gap-1"><div class="w-3 h-3 rounded-sm bg-rose-400 dark:bg-rose-600"></div> Absent</div>
                    <div class="flex items-center gap-1"><div class="w-3 h-3 rounded-sm bg-zinc-50 dark:bg-zinc-900/50"></div> No Data</div>
                </div>
            </div>

            <div class="grid gap-4 sm:gap-6 md:grid-cols-2">
                <!-- Daily Trend -->
                <div class="analytics-card rounded-xl sm:rounded-[2.5rem] border border-zinc-100 dark:border-zinc-800/50 bg-white dark:bg-black p-4 sm:p-8 shadow-xl sm:shadow-2xl">
                    <div class="mb-3 sm:mb-6 flex items-center gap-2 sm:gap-3">
                        <div class="h-8 w-8 sm:h-10 sm:w-10 rounded-xl sm:rounded-2xl bg-zinc-50 dark:bg-zinc-900 flex items-center justify-center border border-zinc-100 dark:border-zinc-800">
                            <TrendingUp class="h-4 w-4 sm:h-5 sm:w-5 text-zinc-400" />
                        </div>
                        <div>
                            <h3 class="font-serif font-black text-base sm:text-xl tracking-tight leading-none mb-0.5 sm:mb-1">Daily Trend</h3>
                            <p class="text-[9px] sm:text-[10px] font-bold text-zinc-400 uppercase tracking-widest leading-none">{{ filters.days }} Day View</p>
                        </div>
                    </div>
                    <div class="h-[220px] sm:h-[300px] w-full">
                        <Line v-if="dailyTrend.length" :data="trendLineData" :options="chartOptions" />
                        <div v-else class="h-full flex items-center justify-center text-sm text-zinc-400">No data in range</div>
                    </div>
                </div>

                <!-- Subject Breakdown -->
                <div class="analytics-card rounded-xl sm:rounded-[2.5rem] border border-zinc-100 dark:border-zinc-800/50 bg-white dark:bg-black p-4 sm:p-8 shadow-xl sm:shadow-2xl">
                    <div class="mb-3 sm:mb-6 flex items-center gap-2 sm:gap-3">
                        <div class="h-8 w-8 sm:h-10 sm:w-10 rounded-xl sm:rounded-2xl bg-zinc-50 dark:bg-zinc-900 flex items-center justify-center border border-zinc-100 dark:border-zinc-800">
                            <BarChart3 class="h-4 w-4 sm:h-5 sm:w-5 text-zinc-400" />
                        </div>
                        <div>
                            <h3 class="font-serif font-black text-base sm:text-xl tracking-tight leading-none mb-0.5 sm:mb-1">Subject Breakdown</h3>
                            <p class="text-[9px] sm:text-[10px] font-bold text-zinc-400 uppercase tracking-widest leading-none">Per Subject Rate</p>
                        </div>
                    </div>
                    <div class="h-[220px] sm:h-[300px] w-full">
                        <Bar v-if="subjectBreakdown.length" :data="subjectBarData" :options="barOptions" />
                        <div v-else class="h-full flex items-center justify-center text-sm text-zinc-400">No subjects with data</div>
                    </div>
                </div>
            </div>

            <!-- Subject Detail Cards -->
            <div v-if="subjectBreakdown.length" class="grid gap-3 sm:gap-4 md:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="sub in subjectBreakdown"
                    :key="sub.id"
                    class="analytics-card rounded-xl sm:rounded-3xl border border-zinc-100 dark:border-zinc-800/50 bg-white dark:bg-black p-4 sm:p-6 shadow-xl"
                >
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <BookOpen class="h-4 w-4 text-zinc-400" />
                            <span class="font-bold text-sm">{{ sub.name }}</span>
                        </div>
                        <span class="text-lg font-serif font-black" :class="rateColor(sub.attendance_rate)">{{ sub.attendance_rate }}%</span>
                    </div>
                    <div class="w-full h-1.5 rounded-full bg-zinc-100 dark:bg-zinc-900 overflow-hidden mb-3">
                        <div class="h-full rounded-full" :class="rateBg(sub.attendance_rate)" :style="{ width: `${sub.attendance_rate}%` }"></div>
                    </div>
                    <div class="grid grid-cols-4 gap-1 text-center">
                        <div><div class="text-xs font-black">{{ sub.present }}</div><div class="text-[7px] font-bold text-zinc-400 uppercase">Present</div></div>
                        <div><div class="text-xs font-black">{{ sub.late }}</div><div class="text-[7px] font-bold text-zinc-400 uppercase">Late</div></div>
                        <div><div class="text-xs font-black">{{ sub.absent }}</div><div class="text-[7px] font-bold text-zinc-400 uppercase">Absent</div></div>
                        <div><div class="text-xs font-black">{{ sub.excused }}</div><div class="text-[7px] font-bold text-zinc-400 uppercase">Excused</div></div>
                    </div>
                </div>
            </div>

            <!-- Enrolled Subjects & Schedule -->
            <div class="analytics-card rounded-xl sm:rounded-[2.5rem] border border-zinc-100 dark:border-zinc-800/50 bg-white dark:bg-black p-4 sm:p-8 shadow-xl sm:shadow-2xl">
                <div class="mb-3 sm:mb-6 flex items-center gap-2 sm:gap-3">
                    <div class="h-8 w-8 sm:h-10 sm:w-10 rounded-xl sm:rounded-2xl bg-zinc-50 dark:bg-zinc-900 flex items-center justify-center border border-zinc-100 dark:border-zinc-800">
                        <BookOpen class="h-4 w-4 sm:h-5 sm:w-5 text-zinc-400" />
                    </div>
                    <div>
                        <h3 class="font-serif font-black text-base sm:text-xl tracking-tight leading-none mb-0.5 sm:mb-1">Enrolled Subjects</h3>
                        <p class="text-[9px] sm:text-[10px] font-bold text-zinc-400 uppercase tracking-widest leading-none">Weekly Schedule Overview</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <div v-for="item in enrolledSubjects" :key="item.id" class="p-4 rounded-2xl bg-zinc-50 dark:bg-zinc-900/50 border border-zinc-100 dark:border-zinc-800 flex flex-col gap-2">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-black uppercase tracking-widest text-zinc-400">{{ item.day }}</span>
                        </div>
                        <h4 class="text-sm font-black text-zinc-900 dark:text-white truncate">{{ item.name }}</h4>
                        <div class="flex items-center gap-1.5 text-[10px] font-bold text-zinc-500">
                            <Calendar class="h-3 w-3" />
                            {{ item.time }}
                        </div>
                    </div>
                </div>
                <!-- Empty state -->
                <div v-if="!enrolledSubjects.length" class="text-center py-6 text-zinc-400 text-sm font-bold uppercase tracking-widest">
                    No subjects found in schedule
                </div>
            </div>

            <!-- Detailed Attendance Log -->
            <div class="analytics-card rounded-xl sm:rounded-[2.5rem] border border-zinc-100 dark:border-zinc-800/50 bg-white dark:bg-black p-0 overflow-hidden shadow-xl sm:shadow-2xl">
                <div class="p-4 sm:p-8 border-b border-zinc-100 dark:border-zinc-800 flex items-center justify-between bg-zinc-50/50 dark:bg-zinc-900/20">
                    <div class="flex items-center gap-2 sm:gap-3">
                        <div class="h-8 w-8 sm:h-10 sm:w-10 rounded-xl sm:rounded-2xl bg-white dark:bg-zinc-900 flex items-center justify-center border border-zinc-100 dark:border-zinc-800">
                            <Table class="h-4 w-4 sm:h-5 sm:w-5 text-zinc-400" />
                        </div>
                        <div>
                            <h3 class="font-serif font-black text-base sm:text-xl tracking-tight leading-none mb-0.5 sm:mb-1">Attendance Log</h3>
                            <p class="text-[9px] sm:text-[10px] font-bold text-zinc-400 uppercase tracking-widest leading-none">Daily schedule execution</p>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto max-h-[800px] overflow-y-auto custom-scrollbar">
                    <table class="w-full text-left border-collapse relative">
                        <thead class="sticky top-0 z-10">
                            <tr class="bg-zinc-50 dark:bg-zinc-900 shadow-sm">
                                <th class="px-6 py-4 text-[10px] font-black uppercase tracking-[0.2em] text-zinc-400">Date</th>
                                <th class="px-6 py-4 text-[10px] font-black uppercase tracking-[0.2em] text-zinc-400">Subject</th>
                                <th class="px-6 py-4 text-[10px] font-black uppercase tracking-[0.2em] text-zinc-400">Schedule</th>
                                <th class="px-6 py-4 text-[10px] font-black uppercase tracking-[0.2em] text-zinc-400 text-center">Outcome</th>
                                <th class="px-6 py-4 text-[10px] font-black uppercase tracking-[0.2em] text-zinc-400 text-right">Scan Time</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800/50">
                            <tr v-for="log in detailedHistory" :key="log.date + log.subject_name" class="group hover:bg-zinc-50/50 dark:hover:bg-zinc-900/20 transition-colors">
                                <td class="px-6 py-5 whitespace-nowrap">
                                    <div class="flex flex-col">
                                        <span class="text-sm font-black text-zinc-900 dark:text-white">{{ new Date(log.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) }}</span>
                                        <span class="text-[9px] font-bold text-zinc-400 uppercase">{{ log.day }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-5">
                                    <div class="flex items-center gap-2">
                                        <div class="h-1.5 w-1.5 rounded-full bg-zinc-300 dark:bg-zinc-700"></div>
                                        <span class="text-sm font-bold text-zinc-600 dark:text-zinc-300">{{ log.subject_name }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-5 whitespace-nowrap">
                                    <span class="text-[10px] font-black tracking-widest text-zinc-400 uppercase">{{ log.schedule_time }}</span>
                                </td>
                                <td class="px-6 py-5 text-center">
                                    <span 
                                        class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest inline-block border"
                                        :class="{
                                            'bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900 border-zinc-900 dark:border-zinc-100': log.status === 'Present',
                                            'bg-zinc-500/10 text-zinc-500 border-zinc-500/20': log.status === 'Late',
                                            'bg-rose-500/10 text-rose-600 border-rose-500/20': log.status === 'Absent',
                                            'bg-zinc-100 dark:bg-zinc-800 text-zinc-400 border-zinc-200 dark:border-zinc-700': log.status === 'Time Out'
                                        }"
                                    >
                                        {{ log.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-5 text-right whitespace-nowrap">
                                    <span v-if="log.scanned_at" class="text-[10px] font-black text-zinc-900 dark:text-white tracking-widest">
                                        {{ new Date(log.scanned_at).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }) }}
                                    </span>
                                    <span v-else class="text-[10px] font-black text-zinc-300 dark:text-zinc-700 tracking-widest">-- : --</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div v-if="!detailedHistory.length" class="text-center py-20 bg-zinc-50/30">
                    <p class="text-zinc-400 text-sm font-bold uppercase tracking-widest">No history records found for this period</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(0,0,0,0.1);
    border-radius: 10px;
}
.dark .custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.05);
}
</style>
