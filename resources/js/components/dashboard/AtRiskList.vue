<script setup lang="ts">
import { AlertTriangle } from 'lucide-vue-next';

type Student = {
    id: number;
    name: string;
    student_number: string;
    attendance_percentage?: number;
};

type Props = {
    atRiskStudents: Student[];
};

const props = defineProps<Props>();
const emit = defineEmits(['select']);
</script>

<template>
    <div v-if="atRiskStudents.length > 0" class="overflow-hidden rounded-[1.5rem] border border-rose-200/50 dark:border-rose-900/30 bg-card/60 backdrop-blur-xl shadow-sm">
        <div class="border-b border-rose-100/50 dark:border-rose-900/30 p-4 flex items-center justify-between bg-rose-50/50 dark:bg-rose-950/20">
            <h2 class="text-[9px] font-black uppercase tracking-[0.2em] flex items-center gap-2 text-rose-600 dark:text-rose-400">
                <AlertTriangle class="h-3 w-3" />
                At Risk Students
            </h2>
            <span class="text-[10px] bg-rose-100 dark:bg-rose-900/50 text-rose-600 px-2 py-0.5 rounded-full font-black tabular-nums">{{ atRiskStudents.length }}</span>
        </div>
        <div class="p-0">
            <div class="divide-y divide-rose-100/30 dark:divide-rose-900/30 max-h-64 overflow-y-auto">
                <div 
                    v-for="student in atRiskStudents" 
                    :key="'risk-' + student.id" 
                    @click="emit('select', student)"
                    class="flex items-center justify-between p-3.5 hover:bg-rose-50/30 dark:hover:bg-rose-950/10 transition-colors cursor-pointer group"
                >
                    <div class="flex flex-col min-w-0">
                        <span class="text-xs font-bold text-foreground truncate" :title="student.name">{{ student.name }}</span>
                        <span class="text-[9px] font-bold text-muted-foreground uppercase tracking-widest mt-0.5">{{ student.student_number }}</span>
                    </div>
                    <div class="flex flex-col items-end shrink-0">
                        <span class="text-[11px] font-black text-rose-600 dark:text-rose-400 tabular-nums">{{ student.attendance_percentage }}%</span>
                        <span class="text-[8px] font-bold text-muted-foreground uppercase tracking-widest leading-none mt-1">Rate</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
