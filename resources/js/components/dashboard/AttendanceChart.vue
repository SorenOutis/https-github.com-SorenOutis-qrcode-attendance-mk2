<script setup lang="ts">
import { PieChart } from 'lucide-vue-next';
import { Doughnut } from 'vue-chartjs';
import SkeletonCard from '@/components/SkeletonCard.vue';

type Props = {
    attendanceStats: { Present: number; Late: number; Absent: number; Excused: number } | undefined;
    chartData: any;
    chartOptions: any;
};

const props = defineProps<Props>();
</script>

<template>
    <div class="overflow-hidden rounded-[1.5rem] border border-zinc-200/50 dark:border-zinc-800/50 bg-white/60 dark:bg-zinc-900/40 backdrop-blur-xl shadow-sm">
        <div class="border-b border-zinc-200/50 dark:border-zinc-800/50 p-4 flex items-center justify-between bg-zinc-50/50 dark:bg-zinc-800/30">
            <h2 class="text-[9px] font-black uppercase tracking-[0.2em] flex items-center gap-2 text-zinc-500">
                <PieChart class="h-3 w-3" />
                Overall Attendance
            </h2>
        </div>
        <div class="p-6 h-64 flex items-center justify-center relative">
            <SkeletonCard v-if="!attendanceStats" variant="chart" />
            <template v-else>
                <Doughnut 
                    v-if="(attendanceStats.Present || attendanceStats.Late || attendanceStats.Absent || attendanceStats.Excused)" 
                    :data="chartData" 
                    :options="chartOptions" 
                />
                <div v-else class="text-[10px] font-bold uppercase tracking-widest text-zinc-400 italic absolute">No data available</div>
            </template>
        </div>
    </div>
</template>
