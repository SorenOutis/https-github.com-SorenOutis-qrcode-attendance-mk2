<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import gsap from 'gsap';
import { 
    CalendarDays, BookOpen, ArrowRight, Calendar, Users, Activity, 
    Database, FlaskConical, LayoutGrid, Calculator, ChartBar, Star, 
    CheckCircle2, XCircle, UserMinus, Percent
} from 'lucide-vue-next';
import { ref, onMounted, computed } from 'vue';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';

type Subject = {
    id: number;
    name: string;
    icon?: string;
    color?: string;
    description?: string;
    stats?: {
        enrolled: number;
        present: number;
        late: number;
        absent: number;
        excused: number;
        attendance_rate: number;
    };
};

const props = defineProps<{
    subjects: Subject[];
    filters: {
        date: string;
    };
}>();

const selectedDate = ref<string>(props.filters.date);
const cardsRef = ref<HTMLElement | null>(null);

const glowClasses = (color?: string) => {
    const colors: Record<string, string> = {
        emerald: 'bg-emerald-400',
        amber: 'bg-amber-400',
        indigo: 'bg-indigo-400',
        rose: 'bg-rose-400',
        blue: 'bg-blue-400',
        zinc: 'bg-zinc-400',
        violet: 'bg-violet-400',
        cyan: 'bg-cyan-400',
    };
    return colors[color || 'zinc'] || colors.zinc;
};

const progressIndicatorClasses = (color?: string) => {
    const colors: Record<string, string> = {
        emerald: 'bg-emerald-500 shadow-[0_0_12px_rgba(16,185,129,0.4)]',
        amber: 'bg-amber-500 shadow-[0_0_12px_rgba(245,158,11,0.4)]',
        indigo: 'bg-indigo-500 shadow-[0_0_12px_rgba(99,102,241,0.4)]',
        rose: 'bg-rose-500 shadow-[0_0_12px_rgba(244,63,94,0.4)]',
        blue: 'bg-blue-500 shadow-[0_0_12px_rgba(59,130,246,0.4)]',
        zinc: 'bg-zinc-500 shadow-[0_0_12px_rgba(113,113,122,0.4)]',
        violet: 'bg-violet-500 shadow-[0_0_12px_rgba(139,92,246,0.4)]',
        cyan: 'bg-cyan-500 shadow-[0_0_12px_rgba(6,182,212,0.4)]',
    };
    return colors[color || 'zinc'] || colors.zinc;
};

const rateColor = (rate: number) => {
    if (rate >= 90) return 'text-emerald-500 dark:text-emerald-400';
    if (rate >= 75) return 'text-blue-500 dark:text-blue-400';
    if (rate >= 50) return 'text-amber-500 dark:text-amber-400';
    return 'text-rose-500 dark:text-rose-400';
};

const getColorClasses = (color?: string) => {
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
    return colors[color || 'zinc'] || colors.zinc;
};

const getProgressBarColor = (color?: string) => {
    const colors: Record<string, string> = {
        emerald: 'bg-emerald-500',
        amber: 'bg-amber-500',
        indigo: 'bg-indigo-500',
        rose: 'bg-rose-500',
        blue: 'bg-blue-500',
        zinc: 'bg-zinc-500',
        violet: 'bg-violet-500',
        cyan: 'bg-cyan-500',
    };
    return colors[color || 'zinc'] || colors.zinc;
};

const handleDateChange = () => {
    router.get('/manage-attendance', { date: selectedDate.value }, { preserveState: true });
};

onMounted(() => {
    if (cardsRef.value) {
        gsap.set(cardsRef.value, { perspective: 1000 });
    }
});
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Manage Attendance', href: '/manage-attendance' }]">
        <Head title="Manage Attendance" />

        <div class="flex min-h-screen flex-1 flex-col gap-6 overflow-x-hidden p-3 sm:p-6 lg:p-10 pb-20 md:pb-6 bg-white dark:bg-zinc-950">
            <!-- Header Section -->
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 px-1">
                <div class="flex flex-col gap-2">
                    <h1 class="text-2xl sm:text-4xl font-black tracking-tight text-zinc-900 dark:text-white uppercase italic">
                        Attendance sheets
                    </h1>
                    <div class="flex items-center gap-2 text-sm font-medium text-zinc-500">
                        <Calendar class="h-4 w-4" />
                        <span>Real-time monitoring for {{ selectedDate }}</span>
                    </div>
                </div>

                <!-- Header Actions -->
                <div class="flex items-center gap-3 self-start md:self-auto shrink-0 flex-wrap sm:flex-nowrap">
                    <button 
                        @click="router.get('/subjects')"
                        class="inline-flex items-center justify-center gap-2 h-11 px-5 bg-zinc-900 dark:bg-white rounded-xl shadow-lg shadow-zinc-200 dark:shadow-none transition-all hover:scale-105 active:scale-95 text-xs font-black uppercase tracking-widest text-white dark:text-zinc-900 group"
                    >
                        <BookOpen class="w-4 h-4" />
                        Subjects
                    </button>

                    <!-- Minimal Date Picker in Header -->
                    <div class="inline-flex items-center gap-4 py-1.5 pl-4 pr-1.5 bg-zinc-50 dark:bg-zinc-900/50 rounded-xl border border-zinc-200 dark:border-zinc-800 transition-all focus-within:border-zinc-400 dark:focus-within:border-zinc-600">
                        <div class="flex flex-col">
                            <span class="text-[9px] font-black uppercase tracking-widest text-zinc-400">Reference Date</span>
                            <input 
                                type="date" 
                                v-model="selectedDate" 
                                @change="handleDateChange"
                                class="h-6 w-32 bg-transparent border-0 p-0 focus:ring-0 font-bold text-sm text-zinc-900 dark:text-white"
                            />
                        </div>
                        <div class="h-9 w-9 rounded-lg bg-white dark:bg-zinc-800 flex items-center justify-center text-zinc-500 shadow-sm border border-zinc-200 dark:border-zinc-700">
                            <CalendarDays class="w-4 h-4" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Subject Grid -->
            <div 
                ref="cardsRef"
                class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-6"
            >
                <div 
                    v-for="(subject, index) in subjects" 
                    :key="subject.id"
                    data-card
                    @mouseenter="router.prefetch(`/manage-attendance/${subject.id}/${selectedDate}`, { method: 'get' })"
                    @click="router.get(`/manage-attendance/${subject.id}/${selectedDate}`)"
                    class="group relative overflow-hidden rounded-[20px] border border-zinc-100 dark:border-zinc-800 bg-white dark:bg-black p-4 hover:shadow-xl hover:shadow-zinc-200/40 dark:hover:shadow-none hover:border-zinc-200 dark:hover:border-zinc-700 cursor-pointer transition-all duration-200 flex flex-col h-36"
                >
                    <!-- Background Icon -->
                    <BookOpen class="absolute right-[-8%] bottom-[-15%] h-32 w-32 text-foreground/[0.02] transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-12 pointer-events-none" />

                    <div class="relative z-10 flex-1 flex flex-col">
                        <!-- Top Row: Title & Enrolled -->
                        <div class="flex items-start justify-between min-w-0 mb-3">
                            <div class="min-w-0 flex-1 pr-3">
                                <h3 class="font-serif font-black text-lg leading-tight line-clamp-1 break-all group-hover:text-zinc-600 dark:group-hover:text-zinc-300 transition-colors text-zinc-900 dark:text-white" :title="subject.name">
                                    {{ subject.name }}
                                </h3>
                                <p class="text-[10px] font-bold text-zinc-400 uppercase tracking-widest mt-0.5">
                                    {{ subject.stats?.enrolled || 0 }} Enrolled
                                </p>
                            </div>
                            <!-- Small percentage badge -->
                            <div 
                                class="shrink-0 flex items-center gap-1 rounded-lg px-2 py-0.5 border"
                                :class="{
                                    'border-rose-200 bg-rose-50 text-rose-600 dark:border-rose-900/50 dark:bg-rose-950/20 dark:text-rose-400': (subject.stats?.attendance_rate ?? 0) < 80,
                                    'border-zinc-200 bg-zinc-50 text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400': (subject.stats?.attendance_rate ?? 0) >= 80
                                }"
                            >
                                <span class="text-[9px] font-black">{{ subject.stats?.attendance_rate || 0 }}% Rate</span>
                            </div>
                        </div>

                        <!-- Progress Bar -->
                        <div class="mt-auto mb-3">
                            <div class="w-full h-1.5 rounded-full bg-zinc-100 dark:bg-zinc-800/50 overflow-hidden border border-zinc-200/50 dark:border-zinc-700/50">
                                <div
                                    class="h-full rounded-full transition-all duration-1000 ease-out"
                                    :class="getProgressBarColor(subject.color)"
                                    :style="{ width: `${subject.stats?.attendance_rate || 0}%` }"
                                ></div>
                            </div>
                        </div>

                        <!-- Footer: Stats Row -->
                        <div class="pt-2.5 border-t border-zinc-50 dark:border-zinc-900 flex items-center justify-between text-[10px] font-black uppercase tracking-widest">
                            <div class="flex items-center gap-2 sm:gap-3">
                                <div class="flex items-center gap-1">
                                    <span class="text-zinc-400">P/</span>
                                    <span class="text-zinc-900 dark:text-zinc-100">{{ subject.stats?.present || 0 }}</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <span class="text-zinc-400">L/</span>
                                    <span class="text-amber-600 dark:text-amber-500">{{ subject.stats?.late || 0 }}</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <span class="text-zinc-400">A/</span>
                                    <span class="text-rose-600 dark:text-rose-500">{{ subject.stats?.absent || 0 }}</span>
                                </div>
                            </div>

                            <div class="opacity-0 group-hover:opacity-100 transition-opacity -mr-1">
                                <ArrowRight class="h-4 w-4 text-zinc-400" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="subjects.length === 0" class="col-span-full py-20 text-center space-y-6 bg-zinc-50 dark:bg-zinc-900/30 rounded-3xl border-2 border-dashed border-zinc-200 dark:border-zinc-800">
                    <div class="inline-flex h-20 w-20 rounded-full bg-white dark:bg-zinc-800 items-center justify-center text-zinc-200 dark:text-zinc-800 shadow-sm">
                        <BookOpen class="w-10 h-10" stroke-width="1" />
                    </div>
                    <div class="space-y-1 px-4">
                        <h4 class="text-2xl font-black text-zinc-900 dark:text-white uppercase tracking-tight">No subjects assigned</h4>
                        <p class="text-zinc-500 text-sm font-medium">Create subjects to see them listed here.</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
