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
    <div class="relative overflow-hidden rounded-[1.8rem] sm:rounded-[2.5rem] border border-border bg-card/60 backdrop-blur-xl shadow-xl">
        <!-- Header -->
        <div class="flex flex-col border-b border-border p-4 sm:p-6 gap-4 bg-muted/30">
            <!-- Title row -->
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="h-9 w-9 sm:h-11 sm:w-11 rounded-xl sm:rounded-2xl bg-primary flex items-center justify-center shrink-0 shadow-lg">
                        <Users class="h-4.5 w-4.5 sm:h-5 sm:w-5 text-primary-foreground" />
                    </div>
                    <div>
                        <h2 class="text-lg sm:text-2xl font-serif font-black tracking-tight leading-none text-foreground">
                            Student Database
                        </h2>
                        <p class="text-[9px] font-bold text-muted-foreground uppercase tracking-[0.2em] mt-1">Live management roster</p>
                    </div>
                </div>

                <!-- View Switcher -->
                <div class="hidden md:flex rounded-xl bg-muted p-1 shrink-0 border border-border">
                    <button
                        class="rounded-lg p-1.5 transition-all outline-none"
                        :class="viewMode === 'table' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                        @click="emit('update:viewMode', 'table')"
                    >
                        <Table class="h-4 w-4" />
                    </button>
                    <button
                        class="rounded-lg p-1.5 transition-all outline-none"
                        :class="viewMode === 'grid' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
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
                    <Search class="absolute left-4 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground transition-colors group-focus-within:text-foreground pointer-events-none" />
                    <Input
                        :model-value="searchQuery"
                        @update:model-value="val => emit('update:searchQuery', val as string)"
                        placeholder="Search students, sections or IDs…"
                        class="h-10 w-full pl-11 pr-4 rounded-xl bg-muted/70 border-0 focus:ring-2 focus:ring-ring transition-all text-sm font-bold"
                    />
                </div>

                <!-- Quick Add + Calendar toggle -->
                <div class="flex items-center gap-2 shrink-0">
                    <button
                        @click="emit('openCreate')"
                        class="flex items-center gap-2 h-10 px-4 rounded-xl bg-primary text-primary-foreground text-[10px] font-black uppercase tracking-widest transition-all hover:scale-[1.03] active:scale-[0.97] shadow-lg whitespace-nowrap"
                    >
                        <Plus class="h-4 w-4" />
                        Quick Add
                    </button>

                    <button
                        @click="emit('update:showOnlyScheduledToday', !showOnlyScheduledToday)"
                        class="flex items-center justify-center h-10 w-10 rounded-xl transition-all active:scale-90 border"
                        :class="showOnlyScheduledToday
                            ? 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20 shadow-inner'
                            : 'bg-muted text-muted-foreground border-transparent hover:bg-accent hover:text-accent-foreground'"
                        title="Show only today's scheduled students"
                    >
                        <Calendar class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <!-- Tab row -->
            <div class="flex items-center">
                <div class="flex rounded-xl bg-muted p-1 border border-border">
                    <button
                        class="rounded-lg px-4 py-1.5 text-[9px] font-black uppercase tracking-widest whitespace-nowrap transition-all"
                        :class="activeTab === 'active' ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                        @click="emit('update:activeTab', 'active')"
                    >
                        Active
                    </button>
                    <button
                        class="rounded-lg px-4 py-1.5 text-[9px] font-black uppercase tracking-widest whitespace-nowrap transition-all"
                        :class="activeTab === 'deleted' ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
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
                <thead class="bg-muted/50 border-b border-border">
                    <tr>
                        <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-muted-foreground">Student Identity</th>
                        <th class="px-4 py-4 text-[10px] font-black uppercase tracking-widest text-muted-foreground text-center">Status</th>
                        <th class="px-4 py-4 text-[10px] font-black uppercase tracking-widest text-muted-foreground text-center">Efficiency</th>
                        <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-muted-foreground">Class/Section</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr 
                        v-for="student in students" 
                        :key="student.id"
                        @click="emit('openInfo', student)"
                        class="group hover:bg-muted/50 cursor-pointer transition-colors"
                    >
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div v-if="student.photo" class="h-9 w-9 shrink-0 rounded-full border border-border shadow-sm overflow-hidden">
                                    <img :src="student.photo" class="h-full w-full object-cover" />
                                </div>
                                <div v-else :class="['h-9 w-9 shrink-0 rounded-full flex items-center justify-center border border-border/20 shadow-inner bg-gradient-to-br', getAvatarGradient(student.name)]">
                                    <span class="text-[11px] font-bold text-foreground">{{ student.name.charAt(0) }}</span>
                                </div>
                                <div class="flex flex-col min-w-0">
                                    <span class="text-sm font-bold text-foreground truncate transition-colors">{{ student.name }}</span>
                                    <span class="text-[10px] font-bold text-muted-foreground uppercase tracking-widest mt-0.5">{{ student.student_number }}</span>
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
                                        student.latest_attendance?.status === 'Time Out' ? 'bg-muted-foreground/40' :
                                        'bg-muted'
                                    ]"
                                ></div>
                            </div>
                        </td>
                        <td class="px-4 py-4 text-center">
                            <Badge variant="outline" class="text-[10px] font-black px-2 py-0">
                                {{ student.attendance_percentage ?? 100 }}%
                            </Badge>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-xs font-bold text-muted-foreground uppercase tracking-widest">{{ student.section || 'Unassigned' }}</span>
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
                class="group relative flex flex-col p-4 rounded-[1.8rem] border border-border bg-card/60 hover:border-primary/30 transition-all hover:-translate-y-1 cursor-pointer"
            >
                <div class="flex items-center gap-3 mb-4">
                    <div v-if="student.photo" class="h-10 w-10 shrink-0 rounded-full border border-border shadow-sm overflow-hidden">
                        <img :src="student.photo" class="h-full w-full object-cover" />
                    </div>
                    <div v-else :class="['h-10 w-10 shrink-0 rounded-full flex items-center justify-center border border-border/20 shadow-inner bg-gradient-to-br', getAvatarGradient(student.name)]">
                        <span class="text-xs font-black text-foreground">{{ student.name.charAt(0) }}</span>
                    </div>
                    <div class="flex flex-col min-w-0">
                        <span class="text-sm font-bold text-foreground truncate">{{ student.name }}</span>
                        <span class="text-[9px] font-bold text-muted-foreground uppercase tracking-widest">{{ student.student_number }}</span>
                    </div>
                </div>

                <div class="flex items-center justify-between mt-auto pt-4 border-t border-border">
                    <div class="flex flex-col">
                        <span class="text-[8px] font-black text-muted-foreground uppercase tracking-widest">Attendance</span>
                        <span class="text-xs font-black text-foreground">{{ student.attendance_percentage ?? 100 }}%</span>
                    </div>
                    <div class="flex flex-col items-end">
                        <span class="text-[8px] font-black text-muted-foreground uppercase tracking-widest">Class</span>
                        <span class="text-xs font-black text-muted-foreground">{{ student.section || 'N/A' }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else class="flex flex-col items-center justify-center py-20 px-6 text-center">
            <div class="h-20 w-20 rounded-[2.5rem] bg-muted flex items-center justify-center border border-border mb-6">
                <Search class="h-8 w-8 text-muted-foreground" />
            </div>
            <h3 class="text-xl font-serif font-black text-foreground mb-2">No students found</h3>
            <p class="text-xs font-bold text-muted-foreground uppercase tracking-widest max-w-[280px]">Adjust your search or filters to see more results</p>
        </div>

        <!-- Pagination -->
        <div v-if="totalPages > 1" class="flex items-center justify-between p-4 sm:p-6 border-t border-border">
            <span class="text-[10px] font-bold text-muted-foreground uppercase tracking-widest">Page {{ currentPage }}<span class="opacity-30 mx-1">/</span>{{ totalPages }}</span>
            <div class="flex items-center gap-2">
                <button
                    @click="emit('prevPage')"
                    :disabled="currentPage <= 1"
                    class="h-8 w-8 rounded-lg flex items-center justify-center bg-muted text-muted-foreground hover:text-foreground disabled:opacity-30 transition-all"
                >
                    <ChevronLeft class="h-4 w-4" />
                </button>
                <button
                    @click="emit('nextPage')"
                    :disabled="currentPage >= totalPages"
                    class="h-8 w-8 rounded-lg flex items-center justify-center bg-muted text-muted-foreground hover:text-foreground disabled:opacity-30 transition-all"
                >
                    <ChevronRight class="h-4 w-4" />
                </button>
            </div>
        </div>
    </div>
</template>
