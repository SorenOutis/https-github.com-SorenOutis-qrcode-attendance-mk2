<script setup lang="ts">
import { Scan, PieChart, Calendar, UserPlus } from 'lucide-vue-next';

type Action = {
    label: string;
    sub: string;
    icon: any;
    onClick?: () => void;
    href?: string;
    color?: string;
    primary?: boolean;
    tourId?: string;
};

const props = defineProps<{
    actions: Action[];
}>();

const emit = defineEmits(['action']);

function handleClick(action: Action) {
    if (action.onClick) {
        action.onClick();
    } else if (action.href) {
        emit('action', action.href);
    }
}
</script>

<template>
    <div class="grid grid-cols-4 gap-2 sm:gap-4 px-1">
        <button
            v-for="action in actions"
            :key="action.label"
            :id="action.tourId"
            @click="handleClick(action)"
            class="group flex flex-col items-center justify-center gap-1.5 sm:gap-2.5 p-2 sm:p-5 rounded-[1.5rem] sm:rounded-[2.5rem] border transition-all duration-300 hover:-translate-y-1 active:scale-[0.96]"
            :class="action.primary 
                ? 'bg-primary border-primary text-primary-foreground shadow-xl' 
                : 'bg-card/60 backdrop-blur-xl border-border text-muted-foreground hover:border-border hover:text-foreground shadow-sm'"
        >
            <div class="h-9 w-9 sm:h-12 sm:w-12 rounded-xl sm:rounded-2xl flex items-center justify-center transition-all duration-500 group-hover:scale-110"
                :class="action.primary ? 'bg-primary-foreground/10' : 'bg-muted'">
                <component :is="action.icon" class="h-4 w-4 sm:h-6 sm:w-6" />
            </div>
            <div class="flex flex-col items-center text-center">
                <span class="text-[8px] sm:text-[10px] font-black uppercase tracking-[0.15em] leading-none">{{ action.label }}</span>
                <span class="hidden sm:block text-[8px] font-bold opacity-40 mt-1 uppercase tracking-widest">{{ action.sub }}</span>
            </div>
        </button>
    </div>
</template>
