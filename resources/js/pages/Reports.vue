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
import { Download, TrendingUp, Users, Clock } from 'lucide-vue-next';
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
};


onMounted(async () => {
    await fetchStats();
});

function exportCsv() {
    window.location.href = '/reports/export';
}
</script>

<template>
    <Head title="Reports & Analytics" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-3 sm:p-6 pb-20 md:pb-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-serif font-bold tracking-tight">Reports & Analytics</h1>
                    <p class="text-muted-foreground text-sm">Detailed overview of attendance trends and statistics.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Button variant="outline" @click="exportCsv" class="rounded-full h-9">
                        <Download class="mr-2 h-4 w-4" />
                        Export All
                    </Button>
                </div>
            </div>

            <!-- Filters Bar -->
            <div class="flex flex-col md:flex-row gap-4 p-4 rounded-2xl bg-zinc-50 dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-800 shadow-sm">
                <div class="flex-1 space-y-1.5">
                    <label class="text-[10px] uppercase font-bold tracking-wider text-zinc-500">Subject Filter</label>
                    <Select v-model="selectedSubject">
                        <SelectTrigger class="h-10 rounded-xl bg-white dark:bg-zinc-950">
                            <SelectValue placeholder="All Subjects" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Subjects</SelectItem>
                            <SelectItem v-for="subject in props.subjects" :key="subject.id" :value="subject.id.toString()">
                                {{ subject.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="flex-1 space-y-1.5">
                    <label class="text-[10px] uppercase font-bold tracking-wider text-zinc-500">From Date</label>
                    <Input type="date" v-model="startDate" class="h-10 rounded-xl bg-white dark:bg-zinc-950" />
                </div>
                <div class="flex-1 space-y-1.5">
                    <label class="text-[10px] uppercase font-bold tracking-wider text-zinc-500">To Date</label>
                    <Input type="date" v-model="endDate" class="h-10 rounded-xl bg-white dark:bg-zinc-950" />
                </div>
            </div>

            <div v-if="loading && !stats" class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                <div v-for="i in 3" :key="i" class="h-[350px] rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-black p-6 animate-pulse">
                    <div class="h-4 w-32 bg-zinc-100 dark:bg-zinc-900 rounded mb-4"></div>
                    <div class="h-full w-full bg-zinc-50 dark:bg-zinc-900/50 rounded"></div>
                </div>
            </div>

            <div v-else class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                <div class="report-card col-span-2 rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-black p-6 shadow-xl text-zinc-900 dark:text-white">
                    <div class="mb-4 flex items-center gap-2">
                        <TrendingUp class="h-5 w-5 text-zinc-900 dark:text-white" />
                        <h3 class="font-serif font-semibold text-lg">Attendance Trend (30 Days)</h3>
                    </div>
                    <div class="h-64">
                        <Line :data="lineData" :options="chartOptions" v-if="lineData" />
                    </div>
                </div>

                <!-- Status Distribution -->
                <div class="report-card rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-black p-6 shadow-xl text-zinc-900 dark:text-white">
                    <div class="mb-4 flex items-center gap-2">
                        <Clock class="h-5 w-5 text-zinc-900 dark:text-white" />
                        <h3 class="font-serif font-semibold text-lg">Status Distribution</h3>
                    </div>
                    <div class="h-64">
                        <Pie :data="pieData" :options="chartOptions" v-if="pieData" />
                    </div>
                </div>

                <!-- Section Comparison -->
                <div class="report-card col-span-2 rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-black p-6 shadow-xl text-zinc-900 dark:text-white">
                    <div class="mb-4 flex items-center gap-2">
                        <Users class="h-5 w-5 text-zinc-900 dark:text-white" />
                        <h3 class="font-serif font-semibold text-lg">Activity by Section</h3>
                    </div>
                    <div class="h-64">
                        <Bar :data="barData" :options="chartOptions" v-if="barData" />
                    </div>
                </div>
                
                <div class="report-card rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-black p-6 shadow-xl text-zinc-900 dark:text-white flex flex-col justify-center items-center text-center">
                    <div class="h-12 w-12 rounded-full bg-zinc-100 dark:bg-zinc-900 flex items-center justify-center mb-4 text-zinc-900 dark:text-white border border-zinc-200 dark:border-zinc-800">
                        <TrendingUp class="h-6 w-6" />
                    </div>
                    <h3 class="text-lg font-bold mb-2">Need detailed logs?</h3>
                    <p class="text-sm text-muted-foreground mb-4">Download the full attendance history for all students as an Excel-compatible CSV file.</p>
                    <Button variant="outline" class="rounded-full px-6" @click="exportCsv">Download Full History</Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
