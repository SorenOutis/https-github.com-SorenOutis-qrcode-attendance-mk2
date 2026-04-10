<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <header
        class="sticky top-0 z-40 flex h-16 shrink-0 items-center gap-2 border-b border-zinc-100/50 dark:border-white/5 px-6 transition-all backdrop-blur-xl bg-white/60 dark:bg-zinc-950/60 md:px-8"
    >
        <div class="flex items-center gap-4">
            <SidebarTrigger class="-ml-1 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 transition-colors" />
            
            <div class="h-6 w-px bg-zinc-100 dark:bg-white/5 mx-1 hidden sm:block"></div>
            
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>
        
        <div class="ml-auto flex items-center space-x-4">
            <ThemeToggle />
        </div>
    </header>
</template>
