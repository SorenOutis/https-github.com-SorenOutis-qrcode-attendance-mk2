<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
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
import { Download, TrendingUp, Users, Clock, ChevronDown, Table as TableIcon, LayoutGrid } from 'lucide-vue-next';
import { onMounted, ref, watch } from 'vue';
import { Line, Bar, Pie } from 'vue-chartjs';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import SkeletonCard from '@/components/SkeletonCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';

ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    BarElement,
    Title,
    Tooltip,
    Legend,
    ArcElement
);

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Reports', href: '/reports' },
];

const props = defineProps<{
    subjects: { id: number; name: string }[];
}>();

const loading = ref(true);
const filtersExpanded = ref(false);
const stats = ref<any>(null);

const selectedSubject = ref<string>('all');
const startDate = ref<string>(new Date(Date.now() - 30 * 86400000).toISOString().split('T')[0]);
const endDate = ref<string>(new Date().toISOString().split('T')[0]);

async function fetchStats() {
    loading.value = true;
    try {
        const params = new URLSearchParams();
        if (selectedSubject.value !== 'all') {
            params.append('subject_id', selectedSubject.value);
        }
        params.append('start', startDate.value);
        params.append('end', endDate.value);

        const response = await fetch(`/api/reports/stats?${params.toString()}`);
        stats.value = await response.json();
        updateCharts();
    } catch (e) {
        console.error('Failed to fetch stats', e);
    } finally {
        loading.value = false;
    }
}

function updateCharts() {
    if (!stats.value) return;

    lineData.value = {
        labels: stats.value.daily.map((d: any) => d.date),
        datasets: [
            {
                label: 'Daily Scans',
                backgroundColor: '#18181b',
                borderColor: '#18181b',
                data: stats.value.daily.map((d: any) => d.count),
                tension: 0.4,
            },
        ],
    };

    barData.value = {
        labels: Object.keys(stats.value.sections),
        datasets: [
            {
                label: 'Scans by Section',
                backgroundColor: '#3f3f46',
                data: Object.values(stats.value.sections),
            },
        ],
    };

    pieData.value = {
        labels: stats.value.status.map((s: any) => s.status),
        datasets: [
            {
                backgroundColor: ['#09090b', '#3f3f46', '#a1a1aa', '#e4e4e7'],
                data: stats.value.status.map((s: any) => s.count),
            },
        ],
    };

    nextTick(() => {
        gsap.from('.report-card', {
            opacity: 0,
            y: 20,
            stagger: 0.1,
            duration: 0.6,
            ease: 'power2.out'
        });
    });
}

import { nextTick } from 'vue';

watch([selectedSubject, startDate, endDate], () => {
    fetchStats();
});

const lineData = ref<any>(null);
const barData = ref<any>(null);
const pieData = ref<any>(null);

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'bottom' as const,
            labels: {
                boxWidth: 12,
                padding: 15,
                font: {
                    family: "ui-sans-serif, system-ui, sans-serif",
                    size: 11
                }
            }
        }
    },
    layout: {
        padding: {
            top: 20
        }
    }
};

onMounted(async () => {
    await fetchStats();
});

function exportCsv() {
    const params = new URLSearchParams({
        start: startDate.value,
        end: endDate.value,
        subject_id: selectedSubject.value
    });
    window.location.href = `/reports/export/csv?${params.toString()}`;
}

function exportExcel() {
    const params = new URLSearchParams({
        start: startDate.value,
        end: endDate.value,
        subject_id: selectedSubject.value
    });
    window.location.href = `/reports/export/excel?${params.toString()}`;
}

function exportPdf() {
    const params = new URLSearchParams({
        start: startDate.value,
        end: endDate.value,
        subject_id: selectedSubject.value
    });
    window.location.href = `/reports/export/pdf?${params.toString()}`;
}
</script>

<template>
    <Head title="Reports & Analytics" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 sm:gap-6 p-3 sm:p-6 lg:p-8 pb-20 md:pb-6 w-full overflow-x-hidden animate-in fade-in slide-in-from-bottom-4 duration-700">
            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 sm:gap-6 px-1">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.25em] text-zinc-400 dark:text-zinc-500 mb-1 leading-none">
                        Analytics
                    </p>
                    <h1 class="text-xl sm:text-4xl lg:text-5xl font-serif font-black tracking-tight text-zinc-900 dark:text-white leading-tight">
                        Reports <span class="text-zinc-400 dark:text-zinc-500 italic font-medium">&</span> Insights.
                    </h1>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button variant="outline" class="rounded-full h-9 sm:h-11 px-4 sm:px-6 border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white hover:bg-zinc-50 dark:hover:bg-zinc-800 shadow-sm font-bold text-[10px] sm:text-xs uppercase tracking-widest">
                                <Download class="mr-1.5 sm:mr-2 h-3.5 w-3.5 sm:h-4 sm:w-4" stroke-width="2.5" />
                                Export
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-48 rounded-xl">
                            <DropdownMenuItem @select="exportCsv" class="flex items-center gap-2 py-2.5">
                                <TableIcon class="h-4 w-4 text-zinc-400" />
                                <span>Export as CSV</span>
                            </DropdownMenuItem>
                            <DropdownMenuItem @select="exportExcel" class="flex items-center gap-2 py-2.5">
                                <LayoutGrid class="h-4 w-4 text-zinc-400" />
                                <span>Export as Excel</span>
                            </DropdownMenuItem>
                            <DropdownMenuItem @select="exportPdf" class="flex items-center gap-2 py-2.5">
                                <Download class="h-4 w-4 text-zinc-400" />
                                <span>Export as PDF</span>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>

            <!-- Filters Bar -->
            <div class="rounded-[20px] bg-background/50 backdrop-blur-xl border border-sidebar-border/50 shadow-sm overflow-hidden mb-6 mt-2">
                <!-- Mobile filter toggle -->
                <button
                    class="flex w-full items-center justify-between px-4 py-2.5 sm:hidden"
                    @click="filtersExpanded = !filtersExpanded"
                >
                    <span class="text-[10px] font-black uppercase tracking-widest text-zinc-500">Filters</span>
                    <ChevronDown
                        class="h-4 w-4 text-zinc-400 transition-transform duration-200"
                        :class="filtersExpanded ? 'rotate-180' : ''"
                    />
                </button>
                <div
                    class="grid grid-cols-1 md:grid-cols-3 gap-3 sm:gap-4 p-3 sm:p-5 text-foreground"
                    :class="filtersExpanded ? 'block' : 'hidden sm:grid'"
                >
                    <div class="space-y-1.5 sm:space-y-2">
                        <label class="text-[9px] uppercase font-black tracking-widest text-muted-foreground ml-1">Subject Scope</label>
                        <Select v-model="selectedSubject">
                            <SelectTrigger class="h-9 sm:h-10 rounded-xl bg-background border-sidebar-border/80 shadow-sm focus:ring-0 text-xs font-bold font-serif uppercase tracking-widest">
                                <SelectValue placeholder="All Subjects" />
                            </SelectTrigger>
                            <SelectContent class="rounded-xl">
                                <SelectItem value="all">Global Perspective</SelectItem>
                                <SelectItem v-for="subject in props.subjects" :key="subject.id" :value="subject.id.toString()">
                                    {{ subject.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-1.5 sm:space-y-2">
                        <label class="text-[9px] uppercase font-black tracking-widest text-muted-foreground ml-1">Time Horizon (From)</label>
                        <Input type="date" v-model="startDate" class="h-9 sm:h-10 rounded-xl bg-background border-sidebar-border/80 shadow-sm focus:ring-0 text-[10px] sm:text-xs font-bold uppercase tracking-widest uppercase" />
                    </div>
                    <div class="space-y-1.5 sm:space-y-2">
                        <label class="text-[9px] uppercase font-black tracking-widest text-muted-foreground ml-1">Time Horizon (To)</label>
                        <Input type="date" v-model="endDate" class="h-9 sm:h-10 rounded-xl bg-background border-sidebar-border/80 shadow-sm focus:ring-0 text-[10px] sm:text-xs font-bold uppercase tracking-widest" />
                    </div>
                </div>
            </div>

            <div v-if="loading && !stats" class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                <SkeletonCard variant="chart" v-for="i in 3" :key="i" />
            </div>

            <div v-else class="grid gap-4 sm:gap-6 md:grid-cols-2 lg:grid-cols-3">
                <!-- Trend Card -->
                <div class="report-card md:col-span-2 rounded-[20px] border border-sidebar-border/40 bg-background/50 backdrop-blur-xl p-4 sm:p-6 shadow-sm text-foreground isolate relative overflow-hidden transition-all duration-300 hover:shadow-xl hover:-translate-y-1 hover:border-sidebar-border">
                    <div class="absolute -right-12 -top-12 h-40 w-40 rounded-full bg-primary/5 blur-3xl -z-10 transition-opacity group-hover:bg-primary/10"></div>
                    <div class="mb-3 sm:mb-6 flex items-center justify-between">
                        <div class="flex items-center gap-2 sm:gap-3">
                            <div class="h-8 w-8 sm:h-10 sm:w-10 rounded-xl bg-background flex items-center justify-center border border-sidebar-border/50 shadow-sm">
                                <TrendingUp class="h-4 w-4 sm:h-5 sm:w-5 text-muted-foreground" />
                            </div>
                            <div>
                                <h3 class="font-serif font-black text-base sm:text-xl tracking-tight leading-none mb-0.5 sm:mb-1">Attendance Pulse</h3>
                                <p class="text-[9px] sm:text-[10px] font-bold text-muted-foreground uppercase tracking-widest leading-none">30 Day Horizon</p>
                            </div>
                        </div>
                    </div>
                    <div class="h-[220px] sm:h-[350px] lg:h-[400px] w-full mt-3 sm:mt-6">
                        <Line :data="lineData" :options="chartOptions" v-if="lineData" />
                    </div>
                </div>

                <!-- Status Distribution -->
                <div class="report-card rounded-[20px] border border-sidebar-border/40 bg-background/50 backdrop-blur-xl p-4 sm:p-6 shadow-sm text-foreground isolate relative overflow-hidden transition-all duration-300 hover:shadow-xl hover:-translate-y-1 hover:border-sidebar-border">
                    <div class="absolute -right-12 -top-12 h-40 w-40 rounded-full bg-primary/5 blur-3xl -z-10 transition-opacity group-hover:bg-primary/10"></div>
                    <div class="mb-3 sm:mb-6 flex items-center gap-2 sm:gap-3">
                        <div class="h-8 w-8 sm:h-10 sm:w-10 rounded-xl bg-background flex items-center justify-center border border-sidebar-border/50 shadow-sm">
                            <Clock class="h-4 w-4 sm:h-5 sm:w-5 text-muted-foreground" />
                        </div>
                        <div>
                            <h3 class="font-serif font-black text-base sm:text-xl tracking-tight leading-none mb-0.5 sm:mb-1">Status Mix</h3>
                            <p class="text-[9px] sm:text-[10px] font-bold text-muted-foreground uppercase tracking-widest leading-none">Distribution</p>
                        </div>
                    </div>
                    <div class="h-[220px] sm:h-[350px] lg:h-[400px] w-full mt-3 sm:mt-6">
                        <Pie :data="pieData" :options="chartOptions" v-if="pieData" />
                    </div>
                </div>

                <!-- Section Comparison -->
                <div class="report-card md:col-span-2 rounded-[20px] border border-sidebar-border/40 bg-background/50 backdrop-blur-xl p-4 sm:p-6 shadow-sm text-foreground isolate relative overflow-hidden transition-all duration-300 hover:shadow-xl hover:-translate-y-1 hover:border-sidebar-border">
                    <div class="absolute -right-12 -top-12 h-40 w-40 rounded-full bg-primary/5 blur-3xl -z-10 transition-opacity group-hover:bg-primary/10"></div>
                    <div class="mb-3 sm:mb-6 flex items-center gap-2 sm:gap-3">
                        <div class="h-8 w-8 sm:h-10 sm:w-10 rounded-xl bg-background flex items-center justify-center border border-sidebar-border/50 shadow-sm">
                            <Users class="h-4 w-4 sm:h-5 sm:w-5 text-muted-foreground" />
                        </div>
                        <div>
                            <h3 class="font-serif font-black text-base sm:text-xl tracking-tight leading-none mb-0.5 sm:mb-1">Section Leaderboard</h3>
                            <p class="text-[9px] sm:text-[10px] font-bold text-muted-foreground uppercase tracking-widest leading-none">Comparison</p>
                        </div>
                    </div>
                    <div class="h-[220px] sm:h-[350px] lg:h-[400px] w-full mt-3 sm:mt-6">
                        <Bar :data="barData" :options="chartOptions" v-if="barData" />
                    </div>
                </div>
                
                <div class="report-card rounded-[20px] border border-zinc-200/50 dark:border-zinc-800/50 bg-background p-5 sm:p-8 shadow-sm text-foreground flex flex-col justify-center items-center text-center isolate relative overflow-hidden transition-all duration-300 hover:shadow-xl hover:-translate-y-1">
                    <div class="absolute -right-12 -top-12 h-40 w-40 rounded-full bg-zinc-100 dark:bg-zinc-900 blur-3xl -z-10"></div>
                    <div class="h-10 w-10 sm:h-14 sm:w-14 rounded-2xl bg-zinc-50 dark:bg-zinc-900 flex items-center justify-center mb-4 sm:mb-6 border border-sidebar-border shadow-sm">
                        <TrendingUp class="h-5 w-5 sm:h-7 sm:w-7 text-muted-foreground" />
                    </div>
                    <h3 class="text-base sm:text-lg font-black mb-1.5 sm:mb-2 font-serif uppercase tracking-tight leading-tight">Generate Deep Logs</h3>
                    <p class="text-[9.5px] sm:text-[10px] text-muted-foreground font-bold tracking-wide leading-relaxed mb-6">Extract full student trajectories and attendance maps in CSV format.</p>
                    <Button variant="outline" class="rounded-full px-6 sm:px-8 h-9 sm:h-10 border-sidebar-border bg-background shadow-sm hover:bg-muted transition-all font-black uppercase tracking-widest text-[9px]" @click="exportCsv">Initiate Export</Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
