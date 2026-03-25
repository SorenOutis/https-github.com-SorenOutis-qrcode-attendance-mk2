<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Calendar as CalendarIcon, Filter, Search, X, Check, Clock, Info } from 'lucide-vue-next';
import { ref, computed, onMounted } from 'vue';
import gsap from 'gsap';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';

interface Attendance {
    id: number;
    student_name: string;
    status: string;
    scanned_at: string;
    subject_name: string;
}

const props = defineProps<{
    attendances: Attendance[];
    subjects: { id: number; name: string }[];
    filters: {
        subject_id: string | null;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Calendar', href: '/calendar' },
];

const selectedSubject = ref(props.filters.subject_id || 'all');
const currentMonth = ref(new Date().getMonth());
const currentYear = ref(new Date().getFullYear());

const monthNames = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
];

const daysInMonth = computed(() => {
    return new Date(currentYear.value, currentMonth.value + 1, 0).getDate();
});

const firstDayOfMonth = computed(() => {
    return new Date(currentYear.value, currentMonth.value, 1).getDay();
});

const calendarDays = computed(() => {
    const days = [];
    const prevMonthLastDay = new Date(currentYear.value, currentMonth.value, 0).getDate();
    
    // Previous month's trailing days
    for (let i = firstDayOfMonth.value - 1; i >= 0; i--) {
        days.push({
            day: prevMonthLastDay - i,
            month: currentMonth.value - 1,
            year: currentYear.value,
            currentMonth: false
        });
    }
    
    // Current month's days
    for (let i = 1; i <= daysInMonth.value; i++) {
        days.push({
            day: i,
            month: currentMonth.value,
            year: currentYear.value,
            currentMonth: true
        });
    }
    
    // Next month's leading days
    const remaining = 42 - days.length;
    for (let i = 1; i <= remaining; i++) {
        days.push({
            day: i,
            month: currentMonth.value + 1,
            year: currentYear.value,
            currentMonth: false
        });
    }
    
    return days;
});

function getAttendancesForDay(day: number, month: number, year: number) {
    return props.attendances.filter(a => {
        const d = new Date(a.scanned_at);
        return d.getDate() === day && d.getMonth() === month && d.getFullYear() === year;
    });
}

function nextMonth() {
    if (currentMonth.value === 11) {
        currentMonth.value = 0;
        currentYear.value++;
    } else {
        currentMonth.value++;
    }
}

function prevMonth() {
    if (currentMonth.value === 0) {
        currentMonth.value = 11;
        currentYear.value--;
    } else {
        currentMonth.value--;
    }
}

function applyFilter() {
    router.get('/calendar', {
        subject_id: selectedSubject.value === 'all' ? null : selectedSubject.value
    }, {
        preserveState: true,
        preserveScroll: true
    });
}

function getStatusColor(status: string) {
    const s = status.toLowerCase();
    if (s === 'present') return 'bg-zinc-900 dark:bg-white';
    if (s === 'late') return 'bg-zinc-500 dark:bg-zinc-400';
    if (s === 'absent') return 'bg-zinc-200 border border-zinc-300 dark:bg-zinc-800 dark:border-zinc-700';
    if (s === 'excused') return 'bg-zinc-300 dark:bg-zinc-600';
    return 'bg-zinc-100 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800';
}

const selectedDay = ref<{ day: number; month: number; year: number } | null>(null);
const dayModalOpen = ref(false);

function openDayDetails(day: number, month: number, year: number) {
    selectedDay.value = { day, month, year };
    dayModalOpen.value = true;
}

const selectedDayAttendances = computed(() => {
    if (!selectedDay.value) return [];
    return getAttendancesForDay(selectedDay.value.day, selectedDay.value.month, selectedDay.value.year);
});

onMounted(() => {
    gsap.from('.stagger-animate', {
        y: 20,
        opacity: 0,
        duration: 0.6,
        stagger: 0.1,
        ease: 'power2.out',
    });
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Attendance Calendar" />

        <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8 pt-0 w-full overflow-x-hidden animate-in fade-in slide-in-from-bottom-4 duration-700">
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-6 pb-4 border-b border-zinc-100 dark:border-zinc-900 mt-4 stagger-animate">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="h-1 w-1 rounded-full bg-zinc-400 animate-pulse"></span>
                        <span class="text-[10px] font-black uppercase tracking-widest text-zinc-400">Tracker</span>
                    </div>
                    <h1 class="text-xl sm:text-4xl font-serif font-black tracking-tighter text-foreground leading-none">Attendance Calendar</h1>
                    <p class="text-[10px] sm:text-xs text-muted-foreground mt-1 sm:mt-2 uppercase tracking-widest font-bold">Visualize attendance history</p>
                </div>
                
                <div class="flex items-center gap-2 bg-zinc-50 dark:bg-zinc-900/50 p-1 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-sm">
                    <Select v-model="selectedSubject" @update:modelValue="applyFilter">
                        <SelectTrigger class="w-[180px] h-10 border-0 bg-transparent focus:ring-0 font-bold text-xs uppercase tracking-widest">
                            <SelectValue placeholder="All Subjects" />
                        </SelectTrigger>
                        <SelectContent class="rounded-xl border-zinc-200 dark:border-zinc-800">
                            <SelectItem value="all" class="text-xs font-bold uppercase tracking-wider">All Subjects</SelectItem>
                            <SelectItem v-for="subject in subjects" :key="subject.id" :value="subject.id.toString()" class="text-xs font-bold uppercase tracking-wider">
                                {{ subject.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-black shadow-sm overflow-hidden flex flex-col h-full min-h-[600px] stagger-animate relative">
                <CalendarIcon class="absolute right-[-10%] top-[-5%] h-64 w-64 text-zinc-900/[0.02] dark:text-white/[0.02] pointer-events-none" />
                
                <div class="flex items-center justify-between p-3 sm:p-6 border-b border-zinc-100 dark:border-zinc-800 relative z-10 bg-zinc-50/50 dark:bg-zinc-900/50 backdrop-blur-sm">
                    <h2 class="text-lg sm:text-xl font-serif font-bold uppercase tracking-widest">{{ monthNames[currentMonth] }} {{ currentYear }}</h2>
                    <div class="flex items-center gap-1 sm:gap-2">
                        <Button variant="outline" size="icon" @click="prevMonth" class="h-10 w-10 rounded-full border-zinc-200 dark:border-zinc-800 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-all flex items-center justify-center">
                            <ChevronLeft class="h-5 w-5 text-zinc-600 dark:text-zinc-400" />
                        </Button>
                        <Button variant="outline" size="icon" @click="nextMonth" class="h-10 w-10 rounded-full border-zinc-200 dark:border-zinc-800 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-all flex items-center justify-center">
                            <ChevronRight class="h-5 w-5 text-zinc-600 dark:text-zinc-400" />
                        </Button>
                    </div>
                </div>

                <div class="grid grid-cols-7 border-b border-zinc-100 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-900/30 relative z-10">
                    <div v-for="day in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']" :key="day" class="p-3 sm:p-4 text-center text-[10px] font-black uppercase tracking-widest text-zinc-500 border-r border-zinc-100 dark:border-zinc-800 last:border-r-0">
                        <span class="hidden sm:inline">{{ day }}</span>
                        <span class="inline sm:hidden">{{ day.charAt(0) }}</span>
                    </div>
                </div>

                <div class="grid grid-cols-7 flex-1 relative z-10 bg-white/50 dark:bg-black/50">
                    <div 
                        v-for="(day, i) in calendarDays" 
                        :key="i"
                        @click="openDayDetails(day.day, day.month, day.year)"
                        class="min-h-[60px] sm:min-h-[120px] border-r border-zinc-100 dark:border-zinc-800 border-b last:border-r-0 p-1.5 sm:p-3 group transition-colors flex flex-col gap-1 cursor-pointer hover:bg-zinc-50/80 dark:hover:bg-zinc-900/80 hover:shadow-inner"
                        :class="[
                            day.currentMonth ? '' : 'bg-zinc-50 dark:bg-zinc-900/30 text-zinc-400 dark:text-zinc-600 opacity-60',
                        ]"
                    >
                        <span class="text-sm sm:text-base font-bold font-serif tabular-nums" :class="{'text-zinc-400 dark:text-zinc-600': !day.currentMonth, 'text-zinc-900 dark:text-zinc-100': day.currentMonth}">
                            {{ day.day }}
                        </span>
                        
                        <div class="flex flex-wrap gap-1 mt-1">
                            <div 
                                v-for="attendance in getAttendancesForDay(day.day, day.month, day.year).slice(0, 12)" 
                                :key="attendance.id"
                                class="h-1.5 w-1.5 sm:h-2 sm:w-2 rounded-full shadow-sm"
                                :class="getStatusColor(attendance.status)"
                            ></div>
                            <div v-if="getAttendancesForDay(day.day, day.month, day.year).length > 12" class="h-1.5 w-1.5 sm:h-2 sm:w-2 rounded-full bg-zinc-300 dark:bg-zinc-700 flex items-center justify-center">
                                <span class="text-[6px] font-bold text-zinc-900 dark:text-white">+</span>
                            </div>
                        </div>

                        <div class="flex flex-col gap-1 mt-auto pointer-events-none opacity-0 group-hover:opacity-100 transition-opacity">
                            <span v-if="getAttendancesForDay(day.day, day.month, day.year).length > 0" class="text-[8px] sm:text-[10px] font-black uppercase tracking-widest text-zinc-500">
                                {{ getAttendancesForDay(day.day, day.month, day.year).length }} scans
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-center sm:justify-start gap-3 sm:gap-6 text-[10px] font-black uppercase tracking-widest text-muted-foreground stagger-animate bg-zinc-50 dark:bg-zinc-900/50 p-3 sm:p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 self-start">
                <div class="flex items-center gap-2">
                    <div class="h-2.5 w-2.5 rounded-full bg-zinc-900 dark:bg-white shadow-[0_0_10px_rgba(0,0,0,0.1)] dark:shadow-[0_0_10px_rgba(255,255,255,0.2)]"></div>
                    <span>Present</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="h-2.5 w-2.5 rounded-full bg-zinc-500 dark:bg-zinc-400"></div>
                    <span>Late</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="h-2.5 w-2.5 rounded-full bg-zinc-200 border border-zinc-300 dark:bg-zinc-800 dark:border-zinc-700"></div>
                    <span>Absent</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="h-2.5 w-2.5 rounded-full bg-zinc-300 dark:bg-zinc-600"></div>
                    <span>Excused</span>
                </div>
            </div>
        </div>

        <!-- Day Details Modal -->
        <Dialog v-model:open="dayModalOpen">
            <DialogContent class="sm:max-w-md md:max-w-lg p-0 rounded-[2rem] overflow-hidden border-0 shadow-2xl">
                <div v-if="selectedDay" class="flex flex-col max-h-[85vh]">
                    <div class="p-6 pb-4 bg-zinc-50 dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-800 flex items-center justify-between">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-zinc-500">Attendance Log</p>
                            <h2 class="text-2xl font-serif font-black tracking-tight text-foreground mt-1">
                                {{ monthNames[selectedDay.month] }} {{ selectedDay.day }}, {{ selectedDay.year }}
                            </h2>
                        </div>
                    </div>
                    
                    <div class="p-6 overflow-y-auto bg-white dark:bg-black">
                        <div v-if="selectedDayAttendances.length === 0" class="py-12 text-center flex flex-col items-center">
                            <div class="w-16 h-16 rounded-full bg-zinc-100 dark:bg-zinc-800/50 flex items-center justify-center mb-6">
                                <CalendarIcon class="w-8 h-8 text-zinc-300 dark:text-zinc-600" />
                            </div>
                            <h3 class="font-black text-xl tracking-tighter text-zinc-900 dark:text-zinc-100">No records</h3>
                            <p class="text-zinc-500 text-sm mt-2">There were no attendance scans on this date.</p>
                        </div>
                        
                        <div v-else class="space-y-3">
                            <div 
                                v-for="att in selectedDayAttendances" 
                                :key="att.id"
                                class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 rounded-2xl border border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/30 hover:bg-zinc-50 dark:hover:bg-zinc-900 transition-colors"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="h-10 w-10 min-w-[40px] rounded-xl flex items-center justify-center text-sm font-black shadow-inner border"
                                        :class="
                                            att.status.toLowerCase() === 'present' ? 'bg-zinc-900 text-white border-zinc-900 dark:bg-zinc-100 dark:text-zinc-900 dark:border-zinc-100' :
                                            att.status.toLowerCase() === 'late' ? 'bg-zinc-500 text-white border-zinc-500 dark:bg-zinc-600' :
                                            att.status.toLowerCase() === 'absent' ? 'bg-zinc-100 text-zinc-400 border-zinc-200 dark:bg-zinc-800 dark:text-zinc-500 dark:border-zinc-700' :
                                            'bg-zinc-300 text-zinc-600 border-zinc-300 dark:bg-zinc-700 dark:text-zinc-400'
                                        "
                                    >
                                        <Check v-if="att.status.toLowerCase() === 'present'" class="w-5 h-5" />
                                        <Clock v-else-if="att.status.toLowerCase() === 'late'" class="w-5 h-5" />
                                        <X v-else-if="att.status.toLowerCase() === 'absent'" class="w-5 h-5" />
                                        <Info v-else class="w-5 h-5" />
                                    </div>
                                    <div>
                                        <p class="font-bold text-sm text-foreground line-clamp-1">{{ att.student_name }}</p>
                                        <p class="text-[10px] font-black uppercase tracking-widest text-zinc-500 mt-0.5">{{ att.subject_name }}</p>
                                    </div>
                                </div>
                                <div class="flex sm:flex-col items-center sm:items-end justify-between sm:justify-center gap-1 shrink-0">
                                    <span class="text-[10px] font-black uppercase tracking-widest px-2 py-0.5 rounded-full border bg-white dark:bg-black"
                                        :class="
                                            att.status.toLowerCase() === 'present' ? 'text-zinc-900 border-zinc-900/20 dark:text-zinc-100 dark:border-zinc-100/20' :
                                            att.status.toLowerCase() === 'late' ? 'text-zinc-500 border-zinc-500/20 dark:text-zinc-400' :
                                            att.status.toLowerCase() === 'absent' ? 'text-zinc-400 border-zinc-200 dark:text-zinc-500 dark:border-zinc-800' :
                                            'text-zinc-500 border-zinc-300 dark:text-zinc-400 dark:border-zinc-700'
                                        "
                                    >
                                        {{ att.status }}
                                    </span>
                                    <span class="text-[10px] font-mono font-bold text-zinc-400 dark:text-zinc-500">
                                        {{ new Date(att.scanned_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
