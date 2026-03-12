<script setup lang="ts">
import { Moon, Sun } from 'lucide-vue-next';
import { useAppearance } from '@/composables/useAppearance';

const { resolvedAppearance, updateAppearance } = useAppearance();

const toggleTheme = () => {
    updateAppearance(resolvedAppearance.value === 'dark' ? 'light' : 'dark');
};
</script>

<template>
    <button
        @click="toggleTheme"
        class="relative inline-flex h-9 w-9 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-black/5 hover:text-foreground dark:hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary overflow-hidden group shrink-0"
        aria-label="Toggle theme"
    >
        <span class="sr-only">Toggle theme</span>
        <Sun
            class="h-[1.2rem] w-[1.2rem] transition-all duration-500 ease-[cubic-bezier(0.34,1.56,0.64,1)] absolute"
            :class="resolvedAppearance === 'dark' ? 'scale-0 -rotate-90 opacity-0' : 'scale-100 rotate-0 opacity-100'"
        />
        <Moon
            class="h-[1.2rem] w-[1.2rem] transition-all duration-500 ease-[cubic-bezier(0.34,1.56,0.64,1)] absolute"
            :class="resolvedAppearance === 'dark' ? 'scale-100 rotate-0 opacity-100' : 'scale-0 rotate-90 opacity-0'"
        />
    </button>
</template>
