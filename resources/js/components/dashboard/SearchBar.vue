<script setup lang="ts">
import { Search, Plus, Calendar } from 'lucide-vue-next';
import { Input } from '@/components/ui/input';

type Props = {
    searchQuery: string;
    showOnlyScheduledToday: boolean;
};

const props = defineProps<Props>();
const emit = defineEmits(['update:searchQuery', 'update:showOnlyScheduledToday', 'openCreate']);

function onSearchInput(val: string) {
    emit('update:searchQuery', val);
}

function toggleScheduled() {
    emit('update:showOnlyScheduledToday', !props.showOnlyScheduledToday);
}
</script>

<template>
    <div class="sticky top-20 z-30 flex flex-col md:flex-row items-center gap-4 bg-white/60 dark:bg-zinc-950/60 backdrop-blur-2xl p-3 sm:p-4 rounded-[1.5rem] sm:rounded-[2.5rem] border border-white/40 dark:border-white/5 shadow-2xl shadow-zinc-200/50 dark:shadow-none mx-1">
        <div class="relative flex-1 group w-full" data-tour="search">
            <Search class="absolute left-6 top-1/2 -translate-y-1/2 h-4 w-4 text-zinc-400 transition-colors group-focus-within:text-zinc-900 dark:group-focus-within:text-white" />
            <Input
                :model-value="searchQuery"
                @update:model-value="onSearchInput"
                placeholder="Search Students, Sections or IDs..."
                class="h-11 sm:h-12 w-full pl-14 pr-4 rounded-2xl bg-zinc-50/50 dark:bg-black/30 border-0 focus:ring-2 focus:ring-zinc-900 dark:focus:ring-white transition-all text-sm font-bold"
            />
            <div class="pointer-events-none absolute inset-y-0 right-6 flex items-center">
                <kbd class="hidden sm:inline-flex h-5 items-center gap-0.5 rounded-md border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-1.5 font-mono text-[9px] font-bold text-zinc-400">
                    <span class="text-xs">⌘</span>K
                </kbd>
            </div>
        </div>
        
        <div class="flex items-center gap-3 shrink-0 w-full md:w-auto">
            <button
                @click="emit('openCreate')"
                class="flex-1 md:flex-none flex items-center justify-center gap-2 h-11 sm:h-12 px-6 rounded-2xl bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 text-[10px] font-black uppercase tracking-widest transition-all hover:scale-[1.03] active:scale-[0.97] shadow-xl shadow-zinc-900/10 dark:shadow-none"
            >
                <Plus class="h-4 w-4" />
                Quick Add
            </button>
            
            <div class="h-8 w-px bg-zinc-100 dark:bg-white/10 mx-1 hidden md:block"></div>
            
            <button
                @click="toggleScheduled"
                class="flex items-center justify-center h-11 w-11 sm:h-12 sm:w-12 rounded-2xl transition-all active:scale-90"
                :class="showOnlyScheduledToday ? 'bg-emerald-500/10 text-emerald-500 border border-emerald-500/20 shadow-inner' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-500 border border-transparent hover:bg-zinc-200'"
                title="Show only today's scheduled students"
            >
                <Calendar class="h-5 w-5" />
            </button>
        </div>
    </div>
</template>
