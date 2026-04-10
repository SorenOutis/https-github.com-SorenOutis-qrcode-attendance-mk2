<script setup lang="ts">
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { AlertCircle, X } from 'lucide-vue-next';

type Props = {
    open: boolean;
    title: string;
    description: string;
    isDestructive?: boolean;
};

const props = defineProps<Props>();
const emit = defineEmits(['update:open', 'confirm']);
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-sm rounded-[2rem] border-zinc-100 dark:border-zinc-800 bg-white/80 dark:bg-black/80 backdrop-blur-2xl p-0 overflow-hidden shadow-2xl">
            <div class="p-8 pb-4 text-center">
                <div 
                    class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl shadow-xl"
                    :class="isDestructive ? 'bg-rose-500 text-white shadow-rose-500/20' : 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900'"
                >
                    <AlertCircle class="h-7 w-7" />
                </div>
                <DialogTitle class="text-xl font-serif font-black leading-none tracking-tight text-zinc-900 dark:text-white">
                    {{ title }}
                </DialogTitle>
                <p class="mt-4 text-xs font-bold text-zinc-500 leading-relaxed">
                    {{ description }}
                </p>
            </div>

            <DialogFooter class="p-6 pt-2 bg-transparent flex-row gap-3">
                <Button variant="outline" class="h-11 rounded-xl text-[10px] font-black uppercase tracking-widest flex-1 border-zinc-200 dark:border-zinc-800" @click="emit('update:open', false)">
                    Cancel
                </Button>
                <Button 
                    @click="emit('confirm')"
                    class="h-11 rounded-xl text-[10px] font-black uppercase tracking-widest px-8 shadow-xl flex-1"
                    :class="isDestructive ? 'bg-rose-500 hover:bg-rose-600 text-white shadow-rose-500/20' : 'bg-zinc-900 dark:bg-white text-white dark:text-zinc-900'"
                >
                    Confirm
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
