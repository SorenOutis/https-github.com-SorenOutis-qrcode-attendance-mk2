<script setup lang="ts">
import { Users, Table, LayoutGrid, Search, ChevronLeft, ChevronRight, UserCheck, Zap, UserX, Scan, UserPlus, ArrowLeftRight, Trash2, Plus, Calendar } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';

type Student = {
    id: number;
    name: string;
    student_number: string;
    email?: string | null;
    section?: string | null;
    photo?: string | null;
    attendance_percentage?: number;
    latest_attendance?: {
        status: string;
    } | null;
    today_statuses?: { status: string; time: string; subject_id?: string | number }[];
};

type Props = {
    students: Student[];
    activeTab: 'active' | 'deleted';
    viewMode: 'table' | 'grid';
    searchQuery: string;
    showOnlyScheduledToday: boolean;
    todayDayName: string;
    currentPage: number;
    totalPages: number;
    trashedCount: number;
};

const props = defineProps<Props>();
const emit = defineEmits([
    'update:activeTab',
    'update:viewMode',
    'update:searchQuery',
    'update:showOnlyScheduledToday',
    'nextPage',
    'prevPage',
    'openInfo',
    'openCreate',
]);

function getAvatarGradient(name: string) {
    const colors = [
        'from-zinc-200 to-zinc-400 dark:from-zinc-700 dark:to-zinc-900',
        'from-zinc-300 to-zinc-500 dark:from-zinc-600 dark:to-zinc-800',
        'from-zinc-100 to-zinc-300 dark:from-zinc-800 dark:to-zinc-950',
        'from-zinc-400 to-zinc-600 dark:from-zinc-500 dark:to-zinc-700'
    ];
    let hash = 0;
    for (let i = 0; i < name.length; i++) {
        hash = name.charCodeAt(i) + ((hash << 5) - hash);
    }
    return colors[Math.abs(hash) % colors.length];
}
</script>

<template>
    <div class="relative overflow-hidden rounded-[1.8rem] sm:rounded-[2.5rem] border border-zinc-200/50 dark:border-zinc-800/80 bg-white/60 dark:bg-zinc-950/60 backdrop-blur-xl shadow-xl">
        <!-- Header -->
        <div class="flex flex-col border-b border-zinc-200/50 dark:border-zinc-800/80 p-4 sm:p-6 gap-4 bg-zinc-50/30 dark:bg-zinc-900/40">
            <!-- Title row -->
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="h-9 w-9 sm:h-11 sm:w-11 rounded-xl sm:rounded-2xl bg-zinc-900 dark:bg-white flex items-center justify-center shrink-0 shadow-lg">
                        <Users class="h-4.5 w-4.5 sm:h-5 sm:w-5 text-white dark:text-black" />
                    </div>
                    <div>
                        <h2 class="text-lg sm:text-2xl font-serif font-black tracking-tight leading-none text-zinc-900 dark:text-white">
                            Student Database
                        </h2>
                        <p class="text-[9px] font-bold text-zinc-400 uppercase tracking-[0.2em] mt-1">Live management roster</p>
                    </div>
                </div>

                <!-- View Switcher -->
                <div class="hidden md:flex rounded-xl bg-zinc-100 dark:bg-zinc-800/50 p-1 shrink-0 border border-zinc-200/50 dark:border-zinc-700/50">
                    <button
                        class="rounded-lg p-1.5 transition-all outline-none"
                        :class="viewMode === 'table' ? 'bg-white dark:bg-zinc-700 text-zinc-900 dark:text-white shadow-sm' : 'text-zinc-400 hover:text-zinc-900 dark:hover:text-white'"
                        @click="emit('update:viewMode', 'table')"
                    >
                        <Table class="h-4 w-4" />
                    </button>
                    <button
                        class="rounded-lg p-1.5 transition-all outline-none"
                        :class="viewMode === 'grid' ? 'bg-white dark:bg-zinc-700 text-zinc-900 dark:text-white shadow-sm' : 'text-zinc-400 hover:text-zinc-900 dark:hover:text-white'"
                        @click="emit('update:viewMode', 'grid')"
                    >
                        <LayoutGrid class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <!-- Search + Actions row -->
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                <!-- Search input -->
                <div class="relative flex-1 group" data-tour="search">
                    <Search class="absolute left-4 top-1/2 -translate-y-1/2 h-4 w-4 text-zinc-400 transition-colors group-focus-within:text-zinc-900 dark:group-focus-within:text-white pointer-events-none" />
                    <Input
                        :model-value="searchQuery"
                        @update:model-value="val => emit('update:searchQuery', val as string)"
                        placeholder="Search students, sections or IDs…"
                        class="h-10 w-full pl-11 pr-4 rounded-xl bg-zinc-100/70 dark:bg-zinc-800/50 border-0 focus:ring-2 focus:ring-zinc-900 dark:focus:ring-white transition-all text-sm font-bold"
                    />
                </div>

                <!-- Quick Add + Calendar toggle -->
                <div class="flex items-center gap-2 shrink-0">
                    <button
                        @click="emit('openCreate')"
                        class="flex items-center gap-2 h-10 px-4 rounded-xl bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 text-[10px] font-black uppercase tracking-widest transition-all hover:scale-[1.03] active:scale-[0.97] shadow-lg shadow-zinc-900/10 dark:shadow-none whitespace-nowrap"
                    >
                        <Plus class="h-4 w-4" />
                        Quick Add
                    </button>

                    <button
                        @click="emit('update:showOnlyScheduledToday', !showOnlyScheduledToday)"
                        class="flex items-center justify-center h-10 w-10 rounded-xl transition-all active:scale-90"
                        :class="showOnlyScheduledToday
                            ? 'bg-emerald-500/10 text-emerald-500 border border-emerald-500/20 shadow-inner'
                            : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-500 border border-transparent hover:bg-zinc-200 dark:hover:bg-zinc-700'"
                        title="Show only today's scheduled students"
                    >
                        <Calendar class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <!-- Tab row -->
            <div class="flex items-center">
                <div class="flex rounded-xl bg-zinc-100 dark:bg-zinc-800/50 p-1 border border-zinc-200/50 dark:border-zinc-700/50">
                    <button
                        class="rounded-lg px-4 py-1.5 text-[9px] font-black uppercase tracking-widest whitespace-nowrap transition-all"
                        :class="activeTab === 'active' ? 'bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 shadow-sm' : 'text-zinc-500 hover:text-zinc-900'"
                        @click="emit('update:activeTab', 'active')"
                    >
                        Active
                    </button>
                    <button
                        class="rounded-lg px-4 py-1.5 text-[9px] font-black uppercase tracking-widest whitespace-nowrap transition-all"
                        :class="activeTab === 'deleted' ? 'bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 shadow-sm' : 'text-zinc-500 hover:text-zinc-900'"
                        @click="emit('update:activeTab', 'deleted')"
                    >
                        Trash ({{ trashedCount }})
                    </button>
                </div>
            </div>
        </div>

        <!-- Table View -->
        <div v-if="viewMode === 'table' && students.length > 0" class="overflow-x-auto">
            <table class="w-full text-left text-sm whitespace-nowrap">
                <thead class="bg-zinc-50/50 dark:bg-zinc-900/20 border-b border-zinc-100 dark:border-zinc-800">
                    <tr>
                        <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-zinc-400">Student Identity</th>
                        <th class="px-4 py-4 text-[10px] font-black uppercase tracking-widest text-zinc-400 text-center">Status</th>
                        <th class="px-4 py-4 text-[10px] font-black uppercase tracking-widest text-zinc-400 text-center">Efficiency</th>
                        <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-zinc-400">Class/Section</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200/50 dark:divide-zinc-800/50">
                    <tr 
                        v-for="student in students" 
                        :key="student.id"
                        @click="emit('openInfo', student)"
                        class="group hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 cursor-pointer transition-colors"
                    >
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div v-if="student.photo" class="h-9 w-9 shrink-0 rounded-full border border-zinc-200 dark:border-zinc-800 shadow-sm overflow-hidden">
                                    <img :src="student.photo" class="h-full w-full object-cover" />
                                </div>
                                <div v-else :class="['h-9 w-9 shrink-0 rounded-full flex items-center justify-center border border-white/20 shadow-inner bg-gradient-to-br', getAvatarGradient(student.name)]">
                                    <span class="text-[11px] font-bold text-zinc-900 dark:text-white">{{ student.name.charAt(0) }}</span>
                                </div>
                                <div class="flex flex-col min-w-0">
                                    <span class="text-sm font-bold text-zinc-900 dark:text-white truncate group-hover:text-zinc-700 dark:group-hover:text-zinc-300 transition-colors">{{ student.name }}</span>
                                    <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-widest mt-0.5">{{ student.student_number }}</span>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-4 text-center">
                            <div class="flex items-center justify-center">
                                <div 
                                    class="h-2 w-2 rounded-full ring-4 ring-offset-2 ring-transparent transition-all"
                                    :class="[
                                        student.latest_attendance?.status === 'Present' ? 'bg-emerald-500 shadow-emerald-500/50 ring-emerald-500/10' :
                                        student.latest_attendance?.status === 'Late' ? 'bg-amber-500 shadow-amber-500/50 ring-amber-500/10' :
                                        student.latest_attendance?.status === 'Time Out' ? 'bg-zinc-400 shadow-zinc-400/50 ring-zinc-400/10' :
                                        'bg-zinc-200 dark:bg-zinc-800'
                                    ]"
                                ></div>
                            </div>
                        </td>
                        <td class="px-4 py-4 text-center">
                            <Badge variant="outline" class="text-[10px] font-black px-2 py-0 border-zinc-200 dark:border-zinc-800">
                                {{ student.attendance_percentage ?? 100 }}%
                            </Badge>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-xs font-bold text-zinc-500 uppercase tracking-widest">{{ student.section || 'Unassigned' }}</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Grid View / Mobile View -->
        <div v-else-if="students.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 p-4 sm:p-6">
            <div 
                v-for="student in students" 
                :key="student.id"
                @click="emit('openInfo', student)"
                class="group relative flex flex-col p-4 rounded-[1.8rem] border border-zinc-200/50 dark:border-zinc-800/50 bg-white/60 dark:bg-zinc-900/40 hover:border-zinc-300 dark:hover:border-zinc-700 transition-all hover:-translate-y-1 cursor-pointer"
            >
                <div class="flex items-center gap-3 mb-4">
                    <div v-if="student.photo" class="h-10 w-10 shrink-0 rounded-full border border-zinc-200 dark:border-zinc-800 shadow-sm overflow-hidden">
                        <img :src="student.photo" class="h-full w-full object-cover" />
                    </div>
                    <div v-else :class="['h-10 w-10 shrink-0 rounded-full flex items-center justify-center border border-white/20 shadow-inner bg-gradient-to-br', getAvatarGradient(student.name)]">
                        <span class="text-xs font-black text-zinc-900 dark:text-white">{{ student.name.charAt(0) }}</span>
                    </div>
                    <div class="flex flex-col min-w-0">
                        <span class="text-sm font-bold text-zinc-900 dark:text-white truncate">{{ student.name }}</span>
                        <span class="text-[9px] font-bold text-zinc-400 uppercase tracking-widest">{{ student.student_number }}</span>
                    </div>
                </div>

                <div class="flex items-center justify-between mt-auto pt-4 border-t border-zinc-100 dark:border-zinc-800/50">
                    <div class="flex flex-col">
                        <span class="text-[8px] font-black text-zinc-400 uppercase tracking-widest">Attendance</span>
                        <span class="text-xs font-black text-zinc-900 dark:text-white">{{ student.attendance_percentage ?? 100 }}%</span>
                    </div>
                    <div class="flex flex-col items-end">
                        <span class="text-[8px] font-black text-zinc-400 uppercase tracking-widest">Class</span>
                        <span class="text-xs font-black text-zinc-500">{{ student.section || 'N/A' }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else class="flex flex-col items-center justify-center py-20 px-6 text-center">
            <div class="h-20 w-20 rounded-[2.5rem] bg-zinc-50 dark:bg-zinc-900/50 flex items-center justify-center border border-zinc-100 dark:border-zinc-800 mb-6">
                <Search class="h-8 w-8 text-zinc-300" />
            </div>
            <h3 class="text-xl font-serif font-black text-zinc-900 dark:text-white mb-2">No students found</h3>
            <p class="text-xs font-bold text-zinc-400 uppercase tracking-widest max-w-[280px]">Adjust your search or filters to see more results</p>
        </div>

        <!-- Pagination -->
        <div v-if="totalPages > 1" class="flex items-center justify-between p-4 sm:p-6 border-t border-zinc-100 dark:border-zinc-800/50">
            <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-widest">Page {{ currentPage }}<span class="opacity-30 mx-1">/</span>{{ totalPages }}</span>
            <div class="flex items-center gap-2">
                <button
                    @click="emit('prevPage')"
                    :disabled="currentPage <= 1"
                    class="h-8 w-8 rounded-lg flex items-center justify-center bg-zinc-100 dark:bg-zinc-800 text-zinc-500 hover:text-zinc-900 dark:hover:text-white disabled:opacity-30 transition-all"
                >
                    <ChevronLeft class="h-4 w-4" />
                </button>
                <button
                    @click="emit('nextPage')"
                    :disabled="currentPage >= totalPages"
                    class="h-8 w-8 rounded-lg flex items-center justify-center bg-zinc-100 dark:bg-zinc-800 text-zinc-500 hover:text-zinc-900 dark:hover:text-white disabled:opacity-30 transition-all"
                >
                    <ChevronRight class="h-4 w-4" />
                </button>
            </div>
        </div>
    </div>
</template>
