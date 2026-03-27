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
        absent: number;
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

// Map icon names to components
const iconMap: Record<string, any> = {
    LayoutGrid, BookOpen, Calculator, FlaskConical, Users, 
    Calendar, ChartBar, Database, Activity, Star
};

const getIcon = (name?: string) => iconMap[name || 'BookOpen'] || BookOpen;

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
                class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6"
            >
                <div 
                    v-for="(subject, index) in subjects" 
                    :key="subject.id"
                    v-reveal:[index%10*40]
                    v-tilt
                    data-card
                    @mouseenter="router.prefetch(`/manage-attendance/${subject.id}/${selectedDate}`, { method: 'get' })"
                    @click="router.get(`/manage-attendance/${subject.id}/${selectedDate}`)"
                    class="group relative flex flex-col h-[280px] bg-zinc-50 dark:bg-zinc-900/30 border border-zinc-200/60 dark:border-zinc-800/60 rounded-3xl p-6 transition-all duration-500 hover:shadow-2xl hover:shadow-zinc-200 dark:hover:shadow-none hover:-translate-y-1 cursor-pointer overflow-hidden"
                >
                    <!-- Background Icon Decoration -->
                    <component 
                        :is="getIcon(subject.icon)" 
                        class="absolute -right-8 -bottom-8 h-48 w-48 text-zinc-900/[0.03] dark:text-white/[0.03] transition-transform duration-700 group-hover:scale-110 group-hover:-rotate-12 pointer-events-none" 
                    />

                    <!-- Card Header -->
                    <div class="flex items-start justify-between mb-4">
                        <div 
                            class="h-14 w-14 rounded-2xl border flex items-center justify-center transition-transform duration-500 group-hover:scale-110 group-hover:rotate-3 shadow-sm shadow-inherit"
                            :class="getColorClasses(subject.color)"
                        >
                            <component :is="getIcon(subject.icon)" class="w-7 h-7" />
                        </div>
                        
                        <div class="flex flex-col items-end">
                            <div class="px-3 py-1 rounded-full bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-[10px] font-black uppercase tracking-tighter text-zinc-500 flex items-center gap-1.5 shadow-sm">
                                <Activity class="w-3 h-3 text-zinc-400" />
                                Live Stats
                            </div>
                        </div>
                    </div>

                    <!-- Subject Info -->
                    <div class="mb-auto">
                        <h3 class="text-xl font-black tracking-tight text-zinc-900 dark:text-white leading-tight uppercase mb-1 line-clamp-1">
                            {{ subject.name }}
                        </h3>
                        <p class="text-xs text-zinc-500 font-medium line-clamp-2 pr-10">
                            {{ subject.description || 'Manage daily attendance records and monitoring for this subject.' }}
                        </p>
                    </div>

                    <!-- Statistics Section -->
                    <div class="mt-6 space-y-4 relative z-10">
                        <!-- Progress info -->
                        <div class="flex items-end justify-between">
                            <div class="flex gap-4">
                                <div class="flex flex-col">
                                    <span class="text-[9px] font-black uppercase tracking-widest text-zinc-400 mb-0.5">Enrolled</span>
                                    <div class="flex items-center gap-1.5 font-black text-zinc-900 dark:text-white">
                                        <Users class="w-3.5 h-3.5 text-zinc-400" />
                                        {{ subject.stats?.enrolled || 0 }}
                                    </div>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-[9px] font-black uppercase tracking-widest text-zinc-400 mb-0.5">Present</span>
                                    <div class="flex items-center gap-1.5 font-black text-emerald-500">
                                        <CheckCircle2 class="w-3.5 h-3.5" />
                                        {{ subject.stats?.present || 0 }}
                                    </div>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-[9px] font-black uppercase tracking-widest text-zinc-400 mb-0.5">Absent</span>
                                    <div class="flex items-center gap-1.5 font-black text-rose-500">
                                        <XCircle class="w-3.5 h-3.5" />
                                        {{ subject.stats?.absent || 0 }}
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="text-[9px] font-black uppercase tracking-widest text-zinc-400 mb-0.5 block">Attendance</span>
                                <div class="text-2xl font-black tracking-tighter text-zinc-900 dark:text-white italic">
                                    {{ subject.stats?.attendance_rate || 0 }}<span class="text-sm not-italic opacity-40 ml-0.5">%</span>
                                </div>
                            </div>
                        </div>

                        <!-- Progress Bar -->
                        <div class="h-2 w-full bg-zinc-200 dark:bg-zinc-800 rounded-full overflow-hidden">
                            <div 
                                class="h-full rounded-full transition-all duration-1000 ease-out"
                                :class="getProgressBarColor(subject.color)"
                                :style="{ width: `${subject.stats?.attendance_rate || 0}%` }"
                            ></div>
                        </div>
                    </div>

                    <!-- Floating Action Indicator Overlay -->
                    <div class="absolute inset-0 bg-white/60 dark:bg-zinc-900/60 backdrop-blur-[2px] opacity-0 group-hover:opacity-100 flex items-center justify-center transition-all duration-500 z-20">
                        <div class="px-6 py-3 rounded-2xl bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 font-black uppercase tracking-widest text-xs flex items-center gap-3 shadow-2xl translate-y-4 group-hover:translate-y-0 transition-transform duration-500">
                            Open Sheet
                            <ArrowRight class="w-4 h-4" />
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
