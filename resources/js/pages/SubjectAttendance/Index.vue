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
import { gsap } from 'gsap';
import {
    Activity,
    ArrowRight,
    BookOpen,
    Calculator,
    Calendar,
    ChartBar,
    ChevronDown,
    Database,
    Download,
    FileUp,
    FlaskConical,
    LayoutGrid,
    Loader2,
    Star,
    Users,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { Line } from 'vue-chartjs';
import { Input } from '@/components/ui/input';
import { useToast } from '@/composables/useToast';
import AppLayout from '@/layouts/AppLayout.vue';
import { importMethod, sample } from '@/routes/students';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, BarElement, Title, Tooltip, Legend);

type SubjectData = {
    id: number;
    name: string;
    icon: string | null;
    color: string | null;
    description: string | null;
    enrolled: number;
    attendance_rate: number;
    total_records: number;
    present: number;
    late: number;
    absent: number;
    excused: number;
    daily: { date: string; count: number }[];
};

const props = defineProps<{
    subjects: SubjectData[];
    filters: { start: string; end: string };
}>();

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Subject Attendance', href: '/subject-attendance' },
];

const sortBy = ref<'worst' | 'best' | 'name'>('worst');
const startDate = ref(props.filters.start);
const endDate = ref(props.filters.end);
const filtersExpanded = ref(false);
const cardsRef = ref<HTMLDivElement | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const isImporting = ref(false);
const { success, error } = useToast();

function downloadTemplate() {
    window.location.href = sample().url;
}

function triggerFileInput() {
    fileInput.value?.click();
}

async function handleFileUpload(event: Event) {
    const target = event.target as HTMLInputElement;
    if (!target.files?.length) return;

    const file = target.files[0];
    const formData = new FormData();
    formData.append('file', file);

    isImporting.value = true;
    
    try {
        const response = await fetch(importMethod().url, {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                'Accept': 'application/json',
            },
        });

        const data = await response.json();
        
        if (response.ok) {
            success(data.message || 'Students and subjects imported successfully.');
            // Reload the page to show new subjects
            window.location.reload();
        } else {
            error(data.message || 'There was an error importing the file.');
        }
    } catch (err) {
        console.error('Import failed:', err);
        error('Import failed. Please check the file format.');
    } finally {
        isImporting.value = false;
        if (fileInput.value) fileInput.value.value = '';
    }
}

const iconMap: Record<string, any> = {
    LayoutGrid,
    BookOpen,
    Calculator,
    FlaskConical,
    Users,
    Calendar,
    ChartBar,
    Database,
    Activity,
    Star,
};

function getIcon(name: string | null) {
    return iconMap[name ?? 'BookOpen'] ?? BookOpen;
}

function iconBadgeClasses(color: string | null): string {
    const colors: Record<string, string> = {
        emerald: 'text-emerald-600 bg-emerald-50 border-emerald-100 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400',
        amber: 'text-amber-600 bg-amber-50 border-amber-100 dark:bg-amber-500/10 dark:border-amber-500/20 dark:text-amber-400',
        indigo: 'text-indigo-600 bg-indigo-50 border-indigo-100 dark:bg-indigo-500/10 dark:border-indigo-500/20 dark:text-indigo-400',
        rose: 'text-rose-600 bg-rose-50 border-rose-100 dark:bg-rose-500/10 dark:border-rose-500/20 dark:text-rose-400',
        blue: 'text-blue-600 bg-blue-50 border-blue-100 dark:bg-blue-500/10 dark:border-blue-500/20 dark:text-blue-400',
        zinc: 'text-zinc-600 bg-zinc-50 border-zinc-100 dark:bg-zinc-500/10 dark:border-zinc-500/20 dark:text-zinc-400',
        violet: 'text-violet-600 bg-violet-50 border-violet-100 dark:bg-violet-500/10 dark:border-violet-500/20 dark:text-violet-400',
        cyan: 'text-cyan-600 bg-cyan-50 border-cyan-100 dark:bg-cyan-500/10 dark:border-cyan-500/20 dark:text-cyan-400',
    };

    return colors[color ?? 'zinc'] ?? colors.zinc;
}

const sortedSubjects = computed(() => {
    const list = [...props.subjects];
    if (sortBy.value === 'worst') {
        return list.sort((a, b) => a.attendance_rate - b.attendance_rate);
    }
    if (sortBy.value === 'best') {
        return list.sort((a, b) => b.attendance_rate - a.attendance_rate);
    }
    return list.sort((a, b) => a.name.localeCompare(b.name));
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

function sparklineData(daily: { date: string; count: number }[]) {
    return {
        labels: daily.map((d) => d.date),
        datasets: [
            {
                data: daily.map((d) => d.count),
                borderColor: '#71717a',
                borderWidth: 1.5,
                pointRadius: 0,
                tension: 0.4,
                fill: false,
            },
        ],
    };
}

const sparklineOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false }, tooltip: { enabled: false } },
    scales: {
        x: { display: false },
        y: { display: false },
    },
};

onMounted(() => {
    if (!cardsRef.value) {
        return;
    }

    const cards = cardsRef.value.querySelectorAll<HTMLElement>('[data-subject-card]');

    if (!cards.length) {
        return;
    }

    gsap.fromTo(
        cards,
        { autoAlpha: 0, y: 20 },
        {
            autoAlpha: 1,
            y: 0,
            stagger: 0.05,
            duration: 0.5,
            ease: 'power2.out',
            clearProps: 'opacity,transform,visibility',
        },
    );
});
</script>

<template>
    <Head title="Subject Attendance" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 sm:gap-6 p-3 sm:p-6 lg:p-8 pb-20 md:pb-6 w-full overflow-x-hidden animate-in fade-in slide-in-from-bottom-4 duration-700">
            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 sm:gap-6 px-1">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 w-full">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.25em] text-zinc-400 dark:text-zinc-500 mb-1 leading-none">
                            Breakdown
                        </p>
                        <h1 class="text-xl sm:text-4xl lg:text-5xl font-serif font-black tracking-tight text-zinc-900 dark:text-white leading-tight">
                            Subject <span class="text-zinc-400 dark:text-zinc-500 italic font-medium">Attendance.</span>
                        </h1>
                    </div>
                    
                    <div class="flex items-center gap-2">
                        <button 
                            @click="downloadTemplate"
                            class="flex items-center gap-2 px-4 py-2 rounded-full border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 text-[10px] font-black uppercase tracking-widest hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-all active:scale-95"
                        >
                            <Download class="w-3 h-3" />
                            <span class="hidden sm:inline">Template</span>
                        </button>
                        
                        <input 
                            type="file" 
                            ref="fileInput" 
                            class="hidden" 
                            accept=".csv"
                            @change="handleFileUpload"
                        />
                        
                        <button 
                            @click="triggerFileInput"
                            :disabled="isImporting"
                            class="flex items-center gap-2 px-4 py-2 rounded-full bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 text-[10px] font-black uppercase tracking-widest hover:opacity-90 transition-all active:scale-95 disabled:opacity-50"
                        >
                            <Loader2 v-if="isImporting" class="w-3 h-3 animate-spin" />
                            <FileUp v-else class="w-3 h-3" />
                            {{ isImporting ? 'Importing...' : 'Import Students' }}
                        </button>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 px-1">
                <button
                    v-for="option in (['worst', 'best', 'name'] as const)"
                    :key="option"
                    class="rounded-full px-4 py-2 text-[10px] font-black uppercase tracking-widest transition-all border"
                    :class="sortBy === option
                        ? 'bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 border-zinc-900 dark:border-white'
                        : 'bg-white dark:bg-zinc-900 text-zinc-500 border-zinc-200 dark:border-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-800'"
                    @click="sortBy = option"
                >
                    {{ option === 'worst' ? 'Worst First' : option === 'best' ? 'Best First' : 'A-Z' }}
                </button>
            </div>

            <!-- Filters -->
            <div class="rounded-xl sm:rounded-[2rem] bg-zinc-50 dark:bg-zinc-900/30 border border-zinc-100 dark:border-zinc-800/50 shadow-inner overflow-hidden">
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
                    class="grid grid-cols-1 md:grid-cols-2 gap-3 sm:gap-4 p-3 sm:p-6"
                    :class="filtersExpanded ? 'block' : 'hidden sm:grid'"
                >
                    <div class="space-y-1.5 sm:space-y-2">
                        <label class="text-[10px] uppercase font-black tracking-[0.2em] text-zinc-400 dark:text-zinc-500 ml-1">From</label>
                        <Input type="date" v-model="startDate" class="h-9 sm:h-11 rounded-xl sm:rounded-2xl bg-white dark:bg-black border-zinc-100 dark:border-zinc-800 shadow-sm focus:ring-0 text-xs sm:text-sm" />
                    </div>
                    <div class="space-y-1.5 sm:space-y-2">
                        <label class="text-[10px] uppercase font-black tracking-[0.2em] text-zinc-400 dark:text-zinc-500 ml-1">To</label>
                        <Input type="date" v-model="endDate" class="h-9 sm:h-11 rounded-xl sm:rounded-2xl bg-white dark:bg-black border-zinc-100 dark:border-zinc-800 shadow-sm focus:ring-0 text-xs sm:text-sm" />
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div v-if="!subjects.length" class="rounded-3xl border border-dashed border-zinc-200 dark:border-zinc-800 p-12 text-center">
                <BookOpen class="mx-auto h-12 w-12 text-zinc-300 dark:text-zinc-700 mb-4" />
                <p class="text-sm text-zinc-500 font-medium">No subjects found. Add subjects to see attendance data.</p>
            </div>

            <!-- Subject Cards Grid -->
            <div ref="cardsRef" class="grid gap-4 sm:gap-6 md:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="subject in sortedSubjects"
                    :key="subject.id"
                    :href="`/subject-attendance/${subject.id}`"
                    data-card
                    class="group relative overflow-hidden rounded-2xl border border-zinc-100 dark:border-zinc-800/50 bg-white dark:bg-black p-4 sm:p-5 transition-all text-zinc-900 dark:text-white shadow-sm hover:bg-zinc-50 dark:hover:bg-zinc-900 hover:shadow-md transform-gpu hover:-translate-y-0.5 active:translate-y-0.5"
                >
                    <div class="flex items-start justify-between mb-4 sm:mb-6">
                        <div class="flex items-center gap-2 sm:gap-3">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-xl border sm:h-12 sm:w-12 sm:rounded-2xl"
                                :class="iconBadgeClasses(subject.color)"
                            >
                                <component :is="getIcon(subject.icon)" class="h-5 w-5 sm:h-6 sm:w-6" />
                            </div>
                            <div>
                                <h3 class="font-serif font-black text-base sm:text-lg tracking-tight leading-tight">{{ subject.name }}</h3>
                                <p class="text-[9px] sm:text-[10px] font-bold text-zinc-400 uppercase tracking-widest">{{ subject.enrolled }} enrolled</p>
                            </div>
                        </div>
                        <ArrowRight class="h-4 w-4 text-zinc-300 dark:text-zinc-700 group-hover:text-zinc-500 dark:group-hover:text-zinc-400 group-hover:translate-x-1 transition-all" />
                    </div>

                    <!-- Attendance rate -->
                    <div class="mb-4 sm:mb-6">
                        <div class="flex items-end justify-between mb-2">
                            <span class="text-3xl sm:text-4xl font-serif font-black" :class="rateColor(subject.attendance_rate)">
                                {{ subject.attendance_rate }}<span class="text-xl opacity-50">%</span>
                            </span>
                            <span class="text-[9px] font-bold text-zinc-400 uppercase tracking-widest">rate</span>
                        </div>
                        <div class="w-full h-1.5 rounded-full bg-zinc-100 dark:bg-zinc-900 overflow-hidden">
                            <div
                                class="h-full rounded-full transition-all duration-1000 ease-out"
                                :class="rateBg(subject.attendance_rate)"
                                :style="{ width: `${subject.attendance_rate}%` }"
                            ></div>
                        </div>
                    </div>

                    <!-- Stats row -->
                    <div class="grid grid-cols-4 gap-2 text-center mb-4 sm:mb-6">
                        <div>
                            <div class="text-xs sm:text-sm font-black">{{ subject.present }}</div>
                            <div class="text-[8px] sm:text-[9px] font-bold uppercase text-zinc-400 tracking-wider">Present</div>
                        </div>
                        <div>
                            <div class="text-xs sm:text-sm font-black">{{ subject.late }}</div>
                            <div class="text-[8px] sm:text-[9px] font-bold uppercase text-zinc-400 tracking-wider">Late</div>
                        </div>
                        <div>
                            <div class="text-xs sm:text-sm font-black">{{ subject.absent }}</div>
                            <div class="text-[8px] sm:text-[9px] font-bold uppercase text-zinc-400 tracking-wider">Absent</div>
                        </div>
                        <div>
                            <div class="text-xs sm:text-sm font-black">{{ subject.excused }}</div>
                            <div class="text-[8px] sm:text-[9px] font-bold uppercase text-zinc-400 tracking-wider">Excused</div>
                        </div>
                    </div>

                    <!-- Sparkline -->
                    <div v-if="subject.daily.length" class="h-12 sm:h-16 w-full">
                        <Line :data="sparklineData(subject.daily)" :options="sparklineOptions" />
                    </div>
                    <div v-else class="h-12 sm:h-16 flex items-center justify-center">
                        <span class="text-[9px] font-bold text-zinc-300 dark:text-zinc-700 uppercase tracking-widest">No trend data</span>
                    </div>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
