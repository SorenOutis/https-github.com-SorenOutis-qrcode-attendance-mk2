<script setup lang="ts">
import { Scan } from 'lucide-vue-next';

type Activity = {
    name: string;
    photo?: string | null;
    status: string;
    time: string;
    subject_id?: string | number;
    subject_name?: string;
};

type Props = {
    recentActivity: Activity[];
};

const props = defineProps<Props>();

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
    <div class="overflow-hidden rounded-[1.5rem] border border-zinc-200/50 dark:border-zinc-800/50 bg-white/60 dark:bg-zinc-900/40 backdrop-blur-xl shadow-sm">
        <div class="border-b border-zinc-200/50 dark:border-zinc-800/50 p-4 flex items-center justify-between bg-zinc-50/50 dark:bg-zinc-800/30">
            <h2 class="text-[9px] font-black uppercase tracking-[0.2em] flex items-center gap-2 text-zinc-500">
                <Scan class="h-3 w-3" />
                Live Scan Feed
            </h2>
            <span class="text-[9px] font-bold text-zinc-400 italic">Recent activity</span>
        </div>
        <div class="p-0">
            <div v-if="recentActivity.length === 0" class="text-center py-10 text-[10px] font-bold uppercase tracking-widest text-zinc-400 italic">
                No activity today
            </div>
            <div v-else class="divide-y divide-zinc-200/50 dark:divide-zinc-800/50">
                <div v-for="(act, i) in recentActivity" :key="i" class="flex items-center justify-between p-3.5 hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors group">
                    <div class="flex items-center gap-3">
                        <div v-if="act.photo" class="h-8 w-8 shrink-0 rounded-full overflow-hidden border border-zinc-200 dark:border-zinc-800 shadow-sm">
                            <img :src="act.photo" class="h-full w-full object-cover" />
                        </div>
                        <div v-else :class="['h-8 w-8 rounded-full flex items-center justify-center shrink-0 border border-white/20 shadow-inner bg-gradient-to-br', getAvatarGradient(act.name)]">
                            <span class="text-[10px] font-bold text-zinc-900 dark:text-white drop-shadow-sm">{{ act.name.charAt(0) }}</span>
                        </div>
                        <div class="flex flex-col overflow-hidden">
                            <span class="text-xs font-bold text-zinc-900 dark:text-white truncate">{{ act.name }}</span>
                            <div class="flex items-center gap-1.5 mt-0.5">
                                <span 
                                    class="text-[9px] px-1.5 py-0.5 rounded-full font-black uppercase tracking-widest border"
                                    :class="{
                                        'bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 border-zinc-200 dark:border-zinc-700': act.status === 'Present',
                                        'bg-amber-100/50 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400 border-amber-200/30': act.status === 'Late',
                                        'bg-zinc-200/50 dark:bg-zinc-700/50 text-zinc-500 dark:text-zinc-300 border-zinc-300/30': act.status === 'Time Out',
                                        'bg-rose-100/50 dark:bg-rose-900/20 text-rose-600 dark:text-rose-400 border-rose-200/30': act.status === 'Absent'
                                    }"
                                >
                                    {{ act.status }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-col items-end gap-1 shrink-0">
                        <span class="text-[10px] font-black text-zinc-500 tracking-tighter">{{ act.time }}</span>
                        <span v-if="act.subject_name" class="text-[8px] font-bold text-zinc-400 uppercase tracking-widest truncate max-w-[70px]" :title="act.subject_name">
                            {{ act.subject_name }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
