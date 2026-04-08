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
                    v-reveal:[index%10*40]
                    v-tilt
                    data-card
                    @mouseenter="router.prefetch(`/manage-attendance/${subject.id}/${selectedDate}`, { method: 'get' })"
                    @click="router.get(`/manage-attendance/${subject.id}/${selectedDate}`)"
                    class="group relative flex flex-col bg-white/70 dark:bg-zinc-950/70 backdrop-blur-xl border border-zinc-100 dark:border-zinc-800/80 rounded-[2rem] p-4 sm:p-5 transition-all duration-500 shadow-xl shadow-zinc-200/40 dark:shadow-none hover:shadow-2xl hover:shadow-zinc-300/50 dark:hover:shadow-indigo-500/5 cursor-pointer overflow-hidden transform-gpu hover:-translate-y-1.5 active:translate-y-0"
                >
                    <!-- Accent Glow -->
                    <div 
                        class="absolute -top-24 -right-24 h-48 w-48 rounded-full blur-[80px] opacity-0 group-hover:opacity-60 transition-opacity duration-700 pointer-events-none"
                        :class="glowClasses(subject.color)"
                    />

                    <!-- Card Header -->
                    <div class="relative flex items-start justify-between mb-6 sm:mb-8">
                        <div class="flex items-center gap-3">
                            <div class="flex flex-col">
                                <h3 class="font-serif font-black text-base sm:text-lg tracking-tight leading-tight group-hover:text-zinc-600 dark:group-hover:text-zinc-300 transition-colors uppercase italic">
                                    {{ subject.name }}
                                </h3>
                                <p class="text-[9px] sm:text-[10px] font-black text-zinc-400 uppercase tracking-widest mt-0.5">{{ subject.stats?.enrolled || 0 }} enrolled</p>
                            </div>
                        </div>
                        
                        <div class="h-9 w-9 flex items-center justify-center rounded-full bg-zinc-50 dark:bg-zinc-900 border border-zinc-100 dark:border-zinc-800 group-hover:bg-zinc-950 dark:group-hover:bg-white group-hover:border-zinc-950 dark:group-hover:border-white transition-all duration-300">
                            <ArrowRight class="h-4 w-4 text-zinc-400 group-hover:text-white dark:group-hover:text-black group-hover:translate-x-0.5 transition-all" />
                        </div>
                    </div>

                    <!-- Attendance rate -->
                    <div class="relative mb-6 sm:mb-8">
                        <div class="flex items-baseline justify-between mb-2.5">
                            <div>
                                <span class="text-3xl sm:text-4xl font-serif font-black tabular-nums transition-all" :class="rateColor(subject.stats?.attendance_rate || 0)">
                                    {{ subject.stats?.attendance_rate || 0 }}
                                </span>
                                <span class="ml-0.5 text-sm sm:text-base font-bold opacity-30">%</span>
                            </div>
                            <span class="text-[9px] font-black text-zinc-400 uppercase tracking-[0.2em]">today's rate</span>
                        </div>
                        <div class="w-full h-2.5 rounded-full bg-zinc-100/50 dark:bg-zinc-900 overflow-hidden shadow-inner">
                            <div
                                class="h-full rounded-full transition-all duration-1000 ease-out"
                                :class="progressIndicatorClasses(subject.color)"
                                :style="{ width: `${subject.stats?.attendance_rate || 0}%` }"
                            >
                                <div class="w-full h-full bg-gradient-to-r from-transparent via-white/20 to-transparent animate-shimmer" />
                            </div>
                        </div>
                    </div>

                    <!-- Stats row (Modern Grid) -->
                    <div class="grid grid-cols-4 gap-2 text-center mb-0 sm:mb-2">
                        <div class="bg-emerald-50/40 dark:bg-emerald-500/5 border border-emerald-100/30 dark:border-emerald-500/10 rounded-xl py-2.5 group-hover:translate-y-[-2px] transition-transform duration-300">
                            <div class="text-sm sm:text-base font-black text-emerald-600 dark:text-emerald-400">{{ subject.stats?.present || 0 }}</div>
                            <div class="text-[7.5px] sm:text-[8px] font-black uppercase text-emerald-600/60 dark:text-emerald-400/50 tracking-wider">Present</div>
                        </div>
                        <div class="bg-amber-50/40 dark:bg-amber-500/5 border border-amber-100/30 dark:border-amber-500/10 rounded-xl py-2.5 group-hover:translate-y-[-2px] transition-transform duration-300 delay-[50ms]">
                            <div class="text-sm sm:text-base font-black text-amber-600 dark:text-amber-400">{{ subject.stats?.late || 0 }}</div>
                            <div class="text-[7.5px] sm:text-[8px] font-black uppercase text-amber-600/60 dark:text-amber-400/50 tracking-wider">Late</div>
                        </div>
                        <div class="bg-rose-50/40 dark:bg-rose-500/5 border border-rose-100/30 dark:border-rose-500/10 rounded-xl py-2.5 group-hover:translate-y-[-2px] transition-transform duration-300 delay-[100ms]">
                            <div class="text-sm sm:text-base font-black text-rose-600 dark:text-rose-400">{{ subject.stats?.absent || 0 }}</div>
                            <div class="text-[7.5px] sm:text-[8px] font-black uppercase text-rose-600/60 dark:text-rose-400/50 tracking-wider">Absent</div>
                        </div>
                        <div class="bg-zinc-50/40 dark:bg-zinc-500/5 border border-zinc-100/30 dark:border-zinc-500/10 rounded-xl py-2.5 group-hover:translate-y-[-2px] transition-transform duration-300 delay-[150ms]">
                            <div class="text-sm sm:text-base font-black text-zinc-500 dark:text-zinc-400">{{ subject.stats?.excused || 0 }}</div>
                            <div class="text-[7.5px] sm:text-[8px] font-black uppercase text-zinc-500/60 dark:text-zinc-400/50 tracking-wider">Excused</div>
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
