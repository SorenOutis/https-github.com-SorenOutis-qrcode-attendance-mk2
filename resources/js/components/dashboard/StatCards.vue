<script setup lang="ts">
import { Users, UserCheck, Zap, UserX } from 'lucide-vue-next';

type Props = {
    attendanceStats: { Present: number; Late: number; Absent: number; Excused: number } | undefined;
    attendanceRate: number;
    animatedStats: { total: number; present: number; late: number; absent: number };
    statusFilter: string | null;
};

const props = defineProps<Props>();
const emit = defineEmits(['update:statusFilter']);

const statsConfig = [
    { label: 'Present', key: 'Present', color: 'emerald', icon: UserCheck },
    { label: 'Late', key: 'Late', color: 'amber', icon: Zap },
    { label: 'Absent', key: 'Absent', color: 'rose', icon: UserX },
    { label: 'Total', key: null, color: 'zinc', icon: Users },
];

function toggleFilter(key: string | null) {
    if (key === null) return;
    const newVal = props.statusFilter === key ? null : key;
    emit('update:statusFilter', newVal);
}

function getStatValue(key: string | null) {
    if (key === 'Present') return props.animatedStats.present;
    if (key === 'Late') return props.animatedStats.late;
    if (key === 'Absent') return props.animatedStats.absent;
    return props.animatedStats.total;
}
</script>

<template>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4 px-1">
        <template v-for="stat in statsConfig" :key="stat.label">
            <button
                data-card
                @click="toggleFilter(stat.key)"
                class="group relative overflow-hidden rounded-[1.5rem] sm:rounded-[2rem] p-4 sm:p-6 transition-all duration-300 hover:-translate-y-1 active:scale-[0.98]"
                :class="statusFilter === stat.key 
                    ? 'bg-primary text-primary-foreground shadow-xl' 
                    : 'bg-card/60 backdrop-blur-xl border border-border shadow-sm hover:shadow-lg'"
            >
                <div class="relative z-10">
                    <div class="flex items-center justify-between mb-2 sm:mb-4">
                        <div class="h-8 w-8 sm:h-10 sm:w-10 rounded-xl sm:rounded-2xl flex items-center justify-center transition-all duration-300 group-hover:scale-110" 
                            :class="statusFilter === stat.key ? 'bg-primary-foreground/20 text-primary-foreground' : 'bg-muted text-muted-foreground'">
                            <component :is="stat.icon" class="h-4 w-4 sm:h-5 sm:w-5" />
                        </div>
                        <div v-if="statusFilter === stat.key" class="h-1.5 w-1.5 rounded-full bg-current animate-pulse"></div>
                    </div>
                    <div class="text-2xl sm:text-3xl font-serif font-black tabular-nums">{{ Math.round(getStatValue(stat.key)) }}</div>
                    <div class="text-[9px] sm:text-[10px] font-bold uppercase tracking-widest opacity-60 mt-1">{{ stat.label }}</div>
                </div>
                
                <!-- Subtle Decoration -->
                <div class="absolute -right-4 -bottom-4 h-16 w-16 blur-2xl opacity-0 group-hover:opacity-10 transition-opacity duration-500" :class="`bg-${stat.color}-500`"></div>
            </button>
        </template>
    </div>
</template>
