<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Calendar as CalendarIcon, Filter, Search } from 'lucide-vue-next';
import { ref, computed } from 'vue';
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
    if (s === 'present') return 'bg-green-500';
    if (s === 'late') return 'bg-amber-500';
    if (s === 'absent') return 'bg-rose-500';
    if (s === 'excused') return 'bg-blue-500';
    return 'bg-zinc-500';
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Attendance Calendar" />

        <div class="flex h-full flex-1 flex-col gap-6 p-4 pt-0">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mt-4">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Attendance Calendar</h1>
                    <p class="text-sm text-muted-foreground mt-1">Visualize student attendance history.</p>
                </div>
                
                <div class="flex items-center gap-2">
                    <Select v-model="selectedSubject" @update:modelValue="applyFilter">
                        <SelectTrigger class="w-[180px]">
                            <SelectValue placeholder="All Subjects" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Subjects</SelectItem>
                            <SelectItem v-for="subject in subjects" :key="subject.id" :value="subject.id.toString()">
                                {{ subject.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <div class="rounded-xl border bg-card text-card-foreground shadow-sm overflow-hidden flex flex-col h-full min-h-[600px]">
                <div class="flex items-center justify-between p-4 border-b">
                    <h2 class="text-lg font-semibold">{{ monthNames[currentMonth] }} {{ currentYear }}</h2>
                    <div class="flex items-center gap-2">
                        <Button variant="outline" size="icon" @click="prevMonth">
                            <ChevronLeft class="h-4 w-4" />
                        </Button>
                        <Button variant="outline" size="icon" @click="nextMonth">
                            <ChevronRight class="h-4 w-4" />
                        </Button>
                    </div>
                </div>

                <div class="grid grid-cols-7 border-b bg-muted/50">
                    <div v-for="day in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']" :key="day" class="p-2 text-center text-xs font-bold uppercase tracking-wider text-muted-foreground border-r last:border-r-0">
                        {{ day }}
                    </div>
                </div>

                <div class="grid grid-cols-7 flex-1">
                    <div 
                        v-for="(day, i) in calendarDays" 
                        :key="i"
                        class="min-h-[100px] border-r border-b last:border-r-0 p-2 group transition-colors flex flex-col gap-1"
                        :class="[
                            day.currentMonth ? 'bg-card' : 'bg-muted/30 text-muted-foreground',
                            'hover:bg-muted/20'
                        ]"
                    >
                        <span class="text-sm font-medium" :class="{'text-muted-foreground/50': !day.currentMonth}">
                            {{ day.day }}
                        </span>
                        
                        <div class="flex flex-wrap gap-1 mt-1">
                            <div 
                                v-for="attendance in getAttendancesForDay(day.day, day.month, day.year)" 
                                :key="attendance.id"
                                class="h-1.5 w-1.5 rounded-full"
                                :class="getStatusColor(attendance.status)"
                                :title="`${attendance.student_name}: ${attendance.status} (${attendance.subject_name})`"
                            ></div>
                        </div>

                        <div class="flex flex-col gap-1 mt-auto pointer-events-none opacity-0 group-hover:opacity-100 transition-opacity">
                            <span v-if="getAttendancesForDay(day.day, day.month, day.year).length > 0" class="text-[10px] font-bold text-muted-foreground">
                                {{ getAttendancesForDay(day.day, day.month, day.year).length }} records
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-6 text-xs text-muted-foreground">
                <div class="flex items-center gap-2">
                    <div class="h-3 w-3 rounded-full bg-green-500"></div>
                    <span>Present</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="h-3 w-3 rounded-full bg-amber-500"></div>
                    <span>Late</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="h-3 w-3 rounded-full bg-rose-500"></div>
                    <span>Absent</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="h-3 w-3 rounded-full bg-blue-500"></div>
                    <span>Excused</span>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
