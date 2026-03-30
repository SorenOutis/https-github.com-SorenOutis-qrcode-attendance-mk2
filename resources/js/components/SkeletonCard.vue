<script setup lang="ts">
defineProps<{
    variant?: 'card' | 'stat' | 'row' | 'chart' | 'table-row';
    lines?: number;
}>();
</script>

<template>
    <div>
        <!-- Stat card skeleton -->
        <div
            v-if="variant === 'stat'"
            class="animate-pulse rounded-3xl border border-zinc-100 dark:border-zinc-800/50 bg-white dark:bg-black p-5 shadow-xl"
        >
            <div class="flex items-center gap-3 mb-4">
                <div class="h-10 w-10 rounded-2xl bg-zinc-100 dark:bg-zinc-900"></div>
                <div class="h-3 w-20 rounded-full bg-zinc-100 dark:bg-zinc-900"></div>
            </div>
            <div class="h-8 w-16 rounded-lg bg-zinc-100 dark:bg-zinc-900 mb-2"></div>
            <div class="h-2 w-24 rounded-full bg-zinc-50 dark:bg-zinc-900/50"></div>
        </div>

        <!-- Card skeleton -->
        <div
            v-else-if="variant === 'card'"
            class="animate-pulse rounded-3xl border border-zinc-100 dark:border-zinc-800/50 bg-white dark:bg-black p-6 shadow-xl"
        >
            <div class="flex items-center gap-3 mb-4">
                <div class="h-12 w-12 rounded-2xl bg-zinc-100 dark:bg-zinc-900"></div>
                <div class="flex-1 space-y-2">
                    <div class="h-4 w-3/4 rounded-full bg-zinc-100 dark:bg-zinc-900"></div>
                    <div class="h-3 w-1/2 rounded-full bg-zinc-50 dark:bg-zinc-900/50"></div>
                </div>
            </div>
            <div class="space-y-2.5">
                <div v-for="i in (lines ?? 3)" :key="i" class="h-3 rounded-full bg-zinc-50 dark:bg-zinc-900/50" :style="{ width: `${85 - (i * 10)}%` }"></div>
            </div>
        </div>

        <!-- Chart skeleton -->
        <div
            v-else-if="variant === 'chart'"
            class="animate-pulse rounded-xl sm:rounded-[2.5rem] border border-zinc-100 dark:border-zinc-800/50 bg-white dark:bg-black p-4 sm:p-8 shadow-xl sm:shadow-2xl"
        >
            <div class="flex items-center gap-3 mb-6">
                <div class="h-10 w-10 rounded-2xl bg-zinc-100 dark:bg-zinc-900"></div>
                <div class="space-y-2">
                    <div class="h-4 w-32 rounded-full bg-zinc-100 dark:bg-zinc-900"></div>
                    <div class="h-2.5 w-20 rounded-full bg-zinc-50 dark:bg-zinc-900/50"></div>
                </div>
            </div>
            <div class="h-[220px] sm:h-[350px] w-full flex items-end gap-2 px-4">
                <div v-for="i in 12" :key="i" class="flex-1 rounded-t-lg bg-zinc-50 dark:bg-zinc-900/50" :style="{ height: `${20 + Math.sin(i * 0.8) * 30 + 30}%` }"></div>
            </div>
        </div>

        <!-- Table row skeleton -->
        <div
            v-else-if="variant === 'table-row'"
            class="animate-pulse flex items-center gap-4 px-4 py-3 border-b border-zinc-50 dark:border-zinc-900/50"
        >
            <div class="h-10 w-10 rounded-xl bg-zinc-100 dark:bg-zinc-900"></div>
            <div class="flex-1 space-y-2">
                <div class="h-3.5 w-1/3 rounded-full bg-zinc-100 dark:bg-zinc-900"></div>
                <div class="h-2.5 w-1/5 rounded-full bg-zinc-50 dark:bg-zinc-900/50"></div>
            </div>
            <div class="h-6 w-16 rounded-full bg-zinc-100 dark:bg-zinc-900"></div>
        </div>

        <!-- Row skeleton (default) -->
        <div
            v-else
            class="animate-pulse rounded-2xl border border-zinc-100 dark:border-zinc-800/50 bg-white dark:bg-black p-4 shadow-sm"
        >
            <div class="flex items-center gap-3">
                <div class="h-10 w-10 rounded-xl bg-zinc-100 dark:bg-zinc-900 shrink-0"></div>
                <div class="flex-1 space-y-2">
                    <div class="h-3.5 w-2/3 rounded-full bg-zinc-100 dark:bg-zinc-900"></div>
                    <div class="h-2.5 w-1/3 rounded-full bg-zinc-50 dark:bg-zinc-900/50"></div>
                </div>
            </div>
        </div>
    </div>
</template>
