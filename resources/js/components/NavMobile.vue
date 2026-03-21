<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { LayoutGrid, QrCode, User, UserCheck, ClipboardList } from 'lucide-vue-next';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useScanner } from '@/composables/useScanner';
import { dashboard } from '@/routes';
import { edit as profileEdit } from '@/routes/profile';
import { index as reportsIndex } from '@/routes/reports';

const { isCurrentUrl } = useCurrentUrl();
const { open: openScanner } = useScanner();

const navItems = [
    {
        title: 'Dashboard',
        href: dashboard().url,
        icon: LayoutGrid,
    },
    {
        title: 'Attendance',
        href: '/manage-attendance',
        icon: UserCheck,
    },
    {
        title: 'Scan QR',
        icon: QrCode,
        isScanner: true,
    },
    {
        title: 'Reports',
        href: reportsIndex().url,
        icon: ClipboardList,
    },
    {
        title: 'Profile',
        href: profileEdit().url,
        icon: User,
    },
];

const handleItemClick = (item: any) => {
    if (item.isScanner) {
        openScanner();
    }
};
</script>

<template>
    <div class="fixed bottom-6 left-1/2 z-50 w-[calc(100%-2rem)] max-w-lg -translate-x-1/2 px-4 md:hidden">
        <nav class="flex h-20 items-center justify-around rounded-[2.5rem] bg-white px-2 shadow-[0_15px_50px_-12px_rgba(0,0,0,0.15)] ring-1 ring-zinc-950/5 backdrop-blur-md dark:bg-zinc-900 dark:ring-white/10 dark:shadow-[0_15px_50px_-12px_rgba(0,0,0,0.5)]">
            <template v-for="item in navItems" :key="item.title">
                <component
                    :is="item.isScanner ? 'button' : Link"
                    :href="item.href"
                    class="relative flex flex-1 flex-col items-center justify-center gap-1 transition-all active:scale-90"
                    @click="handleItemClick(item)"
                >
                    <!-- Dot indicator ABOVE the item -->
                    <div 
                        v-if="!item.isScanner && isCurrentUrl(item.href!)"
                        class="absolute -top-1 h-1 w-1 rounded-full bg-zinc-950 dark:bg-zinc-50"
                    ></div>

                    <div 
                        class="flex items-center justify-center rounded-xl transition-colors"
                        :class="[
                            !item.isScanner && isCurrentUrl(item.href!) 
                                ? 'text-zinc-950 dark:text-zinc-50' 
                                : 'text-zinc-400 group-hover:text-zinc-600 dark:text-zinc-500'
                        ]"
                    >
                        <component 
                            :is="item.icon" 
                            :class="!item.isScanner && isCurrentUrl(item.href!) ? 'size-6.5 stroke-[2.5px]' : 'size-6 stroke-[1.5px]'" 
                        />
                    </div>
                    
                    <span 
                        class="text-[10px] font-medium tracking-tight transition-colors"
                        :class="[
                            !item.isScanner && isCurrentUrl(item.href!) 
                                ? 'font-bold text-zinc-950 dark:text-zinc-50' 
                                : 'text-zinc-400 dark:text-zinc-500'
                        ]"
                    >
                        {{ item.title }}
                    </span>
                </component>
            </template>
        </nav>
    </div>
</template>

<style scoped>
/* Ensure smooth transitions */
.transition-all {
    transition-property: all;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 300ms;
}
</style>
