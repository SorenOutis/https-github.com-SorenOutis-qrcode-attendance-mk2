<script setup lang="ts">
type Props = {
    greeting: string;
    userName: string;
    attendanceRate: number;
    rateColorClass: string;
};

const props = defineProps<Props>();
</script>

<template>
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 px-1 pt-6 pb-2">
        <div id="dashboard-welcome">
            <div class="flex items-center gap-2 mb-2">
                <span class="inline-flex h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="text-[10px] font-black uppercase tracking-[0.3em] text-zinc-400 dark:text-zinc-500">Live Portal</span>
            </div>
            <h1 class="text-3xl sm:text-5xl lg:text-6xl font-serif font-black tracking-tighter text-zinc-900 dark:text-white leading-none">
                {{ greeting }}, <span class="text-zinc-400 dark:text-zinc-600 italic font-medium">{{ userName }}.</span>
            </h1>
        </div>

        <div class="flex flex-col items-end gap-1 shrink-0">
            <div class="text-4xl sm:text-5xl font-serif font-black" :class="rateColorClass">
                {{ (attendanceRate ?? 0).toFixed(1) }}<span class="text-xl opacity-40">%</span>
            </div>
            <div class="text-[9px] font-black text-zinc-400 dark:text-zinc-500 uppercase tracking-[0.2em] px-1">Average Attendance</div>
        </div>
    </div>
</template>
