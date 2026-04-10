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
    <div class="overflow-hidden rounded-[1.5rem] border border-border bg-card/60 backdrop-blur-xl shadow-sm">
        <div class="border-b border-border p-4 flex items-center justify-between bg-muted/30">
            <h2 class="text-[9px] font-black uppercase tracking-[0.2em] flex items-center gap-2 text-muted-foreground">
                <Scan class="h-3 w-3" />
                Live Scan Feed
            </h2>
            <span class="text-[9px] font-bold text-muted-foreground italic">Recent activity</span>
        </div>
        <div class="p-0">
            <div v-if="recentActivity.length === 0" class="text-center py-10 text-[10px] font-bold uppercase tracking-widest text-muted-foreground italic">
                No activity today
            </div>
            <div v-else class="divide-y divide-border">
                <div v-for="(act, i) in recentActivity" :key="i" class="flex items-center justify-between p-3.5 hover:bg-muted/50 transition-colors group">
                    <div class="flex items-center gap-3">
                        <div v-if="act.photo" class="h-8 w-8 shrink-0 rounded-full overflow-hidden border border-border shadow-sm">
                            <img :src="act.photo" class="h-full w-full object-cover" />
                        </div>
                        <div v-else :class="['h-8 w-8 rounded-full flex items-center justify-center shrink-0 border border-border/20 shadow-inner bg-gradient-to-br', getAvatarGradient(act.name)]">
                            <span class="text-[10px] font-bold text-foreground drop-shadow-sm">{{ act.name.charAt(0) }}</span>
                        </div>
                        <div class="flex flex-col overflow-hidden">
                            <span class="text-xs font-bold text-foreground truncate">{{ act.name }}</span>
                            <div class="flex items-center gap-1.5 mt-0.5">
                                <span 
                                    class="text-[9px] px-1.5 py-0.5 rounded-full font-black uppercase tracking-widest border"
                                    :class="{
                                        'bg-muted text-foreground border-border': act.status === 'Present',
                                        'bg-amber-100/50 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400 border-amber-200/30': act.status === 'Late',
                                        'bg-muted/50 text-muted-foreground border-border/30': act.status === 'Time Out',
                                        'bg-rose-100/50 dark:bg-rose-900/20 text-rose-600 dark:text-rose-400 border-rose-200/30': act.status === 'Absent'
                                    }"
                                >
                                    {{ act.status }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-col items-end gap-1 shrink-0">
                        <span class="text-[10px] font-black text-muted-foreground tracking-tighter">{{ act.time }}</span>
                        <span v-if="act.subject_name" class="text-[8px] font-bold text-muted-foreground uppercase tracking-widest truncate max-w-[70px]" :title="act.subject_name">
                            {{ act.subject_name }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
