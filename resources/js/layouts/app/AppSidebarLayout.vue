<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import NavMobile from '@/components/NavMobile.vue';
import GlobalScanner from '@/components/GlobalScanner.vue';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden pb-20 md:pb-0 relative">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <div class="relative w-full min-h-full">
                <Transition name="spatial" mode="out-in">
                    <div :key="($page.url as string)">
                        <slot />
                    </div>
                </Transition>
            </div>
        </AppContent>
        <GlobalScanner />
        <NavMobile />
    </AppShell>
</template>
