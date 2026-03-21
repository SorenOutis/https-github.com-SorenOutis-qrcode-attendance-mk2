<script setup lang="ts">
import { useToast } from '@/composables/useToast';
import { CheckCircle2, XCircle, Info, X } from 'lucide-vue-next';

const { toasts, remove } = useToast();
</script>

<template>
    <Teleport to="body">
        <div class="fixed bottom-24 md:bottom-6 right-4 z-[9999] flex flex-col gap-2 pointer-events-none max-w-sm w-full">
            <TransitionGroup
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="opacity-0 translate-y-4 scale-95"
                enter-to-class="opacity-100 translate-y-0 scale-100"
                leave-active-class="transition-all duration-200 ease-in"
                leave-from-class="opacity-100 translate-y-0 scale-100"
                leave-to-class="opacity-0 translate-y-2 scale-95"
            >
                <div
                    v-for="toast in toasts"
                    :key="toast.id"
                    class="pointer-events-auto flex items-center gap-3 px-4 py-3 rounded-2xl shadow-2xl border text-sm font-medium backdrop-blur-xl"
                    :class="{
                        'bg-zinc-900/95 dark:bg-white/95 text-white dark:text-zinc-900 border-zinc-800 dark:border-zinc-200': toast.type === 'success',
                        'bg-white/95 dark:bg-zinc-900/95 text-red-600 dark:text-red-400 border-red-200 dark:border-red-900': toast.type === 'error',
                        'bg-white/95 dark:bg-zinc-900/95 text-zinc-700 dark:text-zinc-300 border-zinc-200 dark:border-zinc-800': toast.type === 'info',
                    }"
                >
                    <CheckCircle2 v-if="toast.type === 'success'" class="h-4 w-4 shrink-0" />
                    <XCircle v-else-if="toast.type === 'error'" class="h-4 w-4 shrink-0" />
                    <Info v-else class="h-4 w-4 shrink-0" />

                    <span class="flex-1 leading-snug">{{ toast.message }}</span>

                    <button
                        @click="remove(toast.id)"
                        class="ml-1 opacity-60 hover:opacity-100 transition-opacity shrink-0"
                    >
                        <X class="h-3.5 w-3.5" />
                    </button>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>
