<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';
import { onMounted, useTemplateRef } from 'vue';
import gsap from 'gsap';

defineProps<{
    title?: string;
    description?: string;
}>();

const container = useTemplateRef<HTMLElement>('container');
const logo = useTemplateRef<HTMLElement>('logo');
const header = useTemplateRef<HTMLElement>('header');
const content = useTemplateRef<HTMLElement>('content');
const bgCircles = useTemplateRef<HTMLElement[]>('bgCircles');

onMounted(() => {
    const tl = gsap.timeline({ defaults: { ease: 'power4.out', duration: 1 } });

    tl.from(container.value, { opacity: 0 })
      .from('.bg-blob', { 
          scale: 0, 
          opacity: 0, 
          duration: 2, 
          stagger: 0.2,
          ease: 'elastic.out(1, 0.5)'
      }, 0)
      .from(logo.value, { y: -30, opacity: 0 }, '-=0.8')
      .from(header.value, { y: 20, opacity: 0 }, '-=1')
      .from(content.value, { scale: 0.95, opacity: 0 }, '-=1');
      
    // Subtle background drift
    gsap.to('.bg-blob', {
        x: 'random(-40, 40)',
        y: 'random(-40, 40)',
        duration: 'random(10, 20)',
        repeat: -1,
        yoyo: true,
        ease: 'none',
        stagger: {
            each: 2,
            repeat: -1,
            yoyo: true
        }
    });
});
</script>

<template>
    <div
        ref="container"
        class="relative flex min-h-svh flex-col items-center justify-center overflow-hidden bg-zinc-50 p-4 transition-colors duration-500 dark:bg-zinc-950 md:p-8 selection:bg-zinc-900 selection:text-zinc-100 dark:selection:bg-zinc-100 dark:selection:text-zinc-900"
    >
        <!-- Animated Background Blobs -->
        <div class="pointer-events-none absolute inset-0 overflow-hidden opacity-20 dark:opacity-40">
            <div class="bg-blob absolute -top-24 -left-24 h-96 w-96 rounded-full bg-zinc-300 blur-3xl dark:bg-zinc-800"></div>
            <div class="bg-blob absolute -bottom-24 -right-24 h-96 w-96 rounded-full bg-zinc-200 blur-3xl dark:bg-zinc-900"></div>
            <div class="bg-blob absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 h-[500px] w-[500px] rounded-full bg-zinc-100 blur-[100px] dark:bg-zinc-900/50"></div>
        </div>

        <div class="relative z-10 w-full max-w-[400px] space-y-8">
            <div class="flex flex-col items-center gap-6">
                <Link
                    :href="home()"
                    ref="logo"
                    class="group relative flex flex-col items-center gap-3"
                >
                    <div
                        class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white shadow-[0_8px_30px_rgb(0,0,0,0.04)] ring-1 ring-zinc-200 transition-all duration-300 group-hover:scale-110 group-hover:shadow-xl dark:bg-zinc-900 dark:ring-zinc-800 dark:shadow-none"
                    >
                        <AppLogoIcon
                            class="size-9 fill-zinc-900 dark:fill-zinc-100"
                        />
                    </div>
                </Link>
                
                <div ref="header" class="space-y-2 text-center">
                    <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">{{ title }}</h1>
                    <p class="text-balance text-sm font-medium text-zinc-500 dark:text-zinc-400">
                        {{ description }}
                    </p>
                </div>
            </div>

            <div 
                ref="content" 
                class="overflow-hidden rounded-3xl border border-zinc-200 bg-white/70 p-6 shadow-[0_8px_30px_rgb(0,0,0,0.04)] backdrop-blur-xl dark:border-zinc-800 dark:bg-zinc-900/50 dark:shadow-none sm:p-8"
            >
                <slot />
            </div>
        </div>
    </div>
</template>
