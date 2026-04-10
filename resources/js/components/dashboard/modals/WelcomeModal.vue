<script setup lang="ts">
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Scan, Zap, X } from 'lucide-vue-next';

type Props = {
    open: boolean;
    userName: string;
    studentCount: number;
    atRiskCount: number;
};

const props = defineProps<Props>();
const emit = defineEmits(['update:open', 'close']);
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-md p-0 overflow-hidden border-none bg-transparent shadow-none">
            <div class="relative bg-white dark:bg-zinc-950 rounded-[2.5rem] overflow-hidden border border-zinc-100 dark:border-zinc-800 shadow-2xl transition-all duration-500">
                <!-- Premium Background Elements -->
                <div class="absolute -top-32 -right-32 w-64 h-64 bg-zinc-900/5 dark:bg-white/5 rounded-full blur-[80px]"></div>
                <div class="absolute -bottom-32 -left-32 w-64 h-64 bg-zinc-900/5 dark:bg-white/5 rounded-full blur-[80px]"></div>

                <div class="relative p-8 flex flex-col items-center text-center">
                    <!-- Mascot / Icon -->
                    <div class="relative mb-8 group">
                        <div class="absolute inset-0 bg-zinc-900/10 dark:bg-white/10 rounded-full blur-2xl group-hover:scale-110 transition-transform duration-700"></div>
                        <div class="relative w-28 h-28 sm:w-32 sm:h-32 rounded-full border-4 border-white dark:border-zinc-800 shadow-2xl overflow-hidden bg-white dark:bg-zinc-900 flex items-center justify-center">
                            <Scan class="w-12 h-12 text-zinc-900 dark:text-zinc-100 transform group-hover:scale-110 transition-transform duration-700" stroke-width="1.5" />
                        </div>
                        <div class="absolute -bottom-2 -right-2 bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 p-2.5 rounded-2xl shadow-xl animate-bounce">
                            <Zap class="w-4 h-4" />
                        </div>
                    </div>

                    <div class="space-y-3">
                        <h2 class="text-3xl sm:text-4xl font-serif font-black tracking-tight text-zinc-900 dark:text-white">
                            Welcome Back, <span class="text-zinc-400 dark:text-zinc-600 italic">{{ userName.split(' ')[0] }}!</span>
                        </h2>
                        <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-zinc-400 max-w-[280px] mx-auto leading-relaxed">
                            Your attendance hub is synchronized and ready for management
                        </p>
                    </div>

                    <div class="mt-10 grid grid-cols-2 gap-3 w-full">
                        <div class="p-4 rounded-2xl bg-zinc-50/50 dark:bg-zinc-900/40 border border-zinc-100/50 dark:border-zinc-800/40 text-center hover:scale-[1.02] transition-transform">
                            <p class="text-[9px] font-black uppercase tracking-widest text-zinc-400 mb-1">Total Students</p>
                            <p class="text-xl font-serif font-black text-zinc-900 dark:text-white tabular-nums">{{ studentCount }}</p>
                        </div>
                        <div class="p-4 rounded-2xl bg-zinc-50/50 dark:bg-zinc-900/40 border border-zinc-100/50 dark:border-zinc-800/40 text-center hover:scale-[1.02] transition-transform">
                            <p class="text-[9px] font-black uppercase tracking-widest text-zinc-400 mb-1">Students at Risk</p>
                            <p class="text-xl font-serif font-black text-rose-500 tabular-nums">{{ atRiskCount }}</p>
                        </div>
                    </div>

                    <div class="mt-10 w-full space-y-3">
                        <button @click="emit('close')" class="w-full py-4 rounded-2xl bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 font-black text-xs uppercase tracking-[0.2em] shadow-2xl shadow-zinc-900/20 hover:scale-[1.02] active:scale-[0.98] transition-all">
                            Launch Dashboard
                        </button>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
