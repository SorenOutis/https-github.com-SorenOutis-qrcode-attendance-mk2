<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { LayoutGrid, QrCode, User, UserCheck, MessageCircle } from 'lucide-vue-next';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useScanner } from '@/composables/useScanner';
import { dashboard } from '@/routes';
import { edit as profileEdit } from '@/routes/profile';
import { onMounted, ref, watch } from 'vue';
import { gsap } from 'gsap';

const { isCurrentUrl } = useCurrentUrl();
const { open: openScanner } = useScanner();

const navItems = [
    {
        title: 'Dash',
        href: dashboard().url,
        icon: LayoutGrid,
    },
    {
        title: 'Sheets',
        href: '/manage-attendance',
        icon: UserCheck,
    },
    {
        title: 'Scan',
        icon: QrCode,
        isScanner: true,
    },
    {
        title: 'Inbox',
        href: '/excuses',
        icon: MessageCircle,
    },
    {
        title: 'Me',
        href: profileEdit().url,
        icon: User,
    },
];

const activeIndex = ref(-1);
const itemRefs = ref<HTMLElement[]>([]);
const indicatorRef = ref<HTMLElement | null>(null);

function updateActiveIndex() {
    activeIndex.value = navItems.findIndex(item => !item.isScanner && isCurrentUrl(item.href!));
}

function animateIndicator() {
    if (activeIndex.value !== -1 && itemRefs.value[activeIndex.value] && indicatorRef.value) {
        const target = itemRefs.value[activeIndex.value];
        gsap.to(indicatorRef.value, {
            x: target.offsetLeft + (target.offsetWidth / 2) - 20,
            opacity: 1,
            duration: 0.5,
            ease: 'power4.out'
        });
    } else if (indicatorRef.value) {
        gsap.to(indicatorRef.value, { opacity: 0, duration: 0.3 });
    }
}

onMounted(() => {
    updateActiveIndex();
    setTimeout(animateIndicator, 100);
});

watch(() => activeIndex.value, animateIndicator);

const handleItemClick = (item: any, index: number) => {
    if (item.isScanner) {
        openScanner();
    } else {
        activeIndex.value = index;
    }
};
</script>

<template>
    <div class="fixed bottom-[calc(1.5rem+env(safe-area-inset-bottom))] left-1/2 z-50 w-[calc(100%-2rem)] max-w-md -translate-x-1/2 px-4 md:hidden">
        <nav class="relative flex h-16 items-center justify-around rounded-[2rem] bg-zinc-900/90 dark:bg-zinc-50/90 px-1 shadow-2xl backdrop-blur-3xl border border-white/10 dark:border-black/5 overflow-hidden">
            
            <!-- Sliding Active Indicator -->
            <div 
                ref="indicatorRef"
                class="absolute bottom-2 h-1 w-10 rounded-full bg-white dark:bg-zinc-900 opacity-0 pointer-events-none z-0"
            ></div>

            <template v-for="(item, index) in navItems" :key="item.title">
                <component
                    :is="item.isScanner ? 'button' : Link"
                    :href="item.href"
                    ref="itemRefs"
                    class="relative z-10 flex flex-1 flex-col items-center justify-center gap-0.5 transition-all outline-none"
                    @click="handleItemClick(item, index)"
                >
                    <div 
                        class="flex items-center justify-center rounded-xl transition-all duration-500"
                        :class="[
                            !item.isScanner && activeIndex === index
                                ? 'text-white dark:text-zinc-900 scale-110' 
                                : 'text-zinc-500 group-hover:text-zinc-300'
                        ]"
                    >
                        <component 
                            :is="item.icon" 
                            :class="!item.isScanner && activeIndex === index ? 'size-6 stroke-[2.5px]' : 'size-5.5 stroke-[1.5px]'" 
                        />
                    </div>
                    
                    <span 
                        class="text-[8px] font-black uppercase tracking-[0.15em] transition-all duration-500"
                        :class="[
                            !item.isScanner && activeIndex === index 
                                ? 'text-white dark:text-zinc-900 opacity-100' 
                                : 'text-zinc-500 opacity-60'
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
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
