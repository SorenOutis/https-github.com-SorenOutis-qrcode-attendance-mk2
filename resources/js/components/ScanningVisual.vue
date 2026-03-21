<script setup lang="ts">
import gsap from 'gsap';
import { onMounted, ref } from 'vue';

const props = defineProps<{
    small?: boolean;
}>();

const qrRef = ref<HTMLElement | null>(null);
const lineRef = ref<HTMLElement | null>(null);
const meshRef = ref<HTMLElement | null>(null);
const containerRef = ref<HTMLElement | null>(null);

onMounted(() => {
    if (!containerRef.value) return;

    // Line animation
    gsap.to(lineRef.value, {
        top: '100%',
        duration: 2.5,
        ease: 'none',
        repeat: -1,
        yoyo: true,
    });

    // Mesh pulse
    gsap.to('.mesh-dot', {
        opacity: 0.2,
        duration: 'random(1, 2)',
        repeat: -1,
        yoyo: true,
        stagger: {
            amount: 1.5,
            grid: [10, 10],
            from: 'center',
        },
    });

    // QR Pulse
    gsap.to(qrRef.value, {
        scale: 1.05,
        duration: 2,
        repeat: -1,
        yoyo: true,
        ease: 'sine.inOut'
    });

    // Floating particles
    const particles = props.small ? 5 : 15;
    for (let i = 0; i < particles; i++) {
        const p = document.createElement('div');
        p.className = 'absolute w-1 h-1 bg-foreground/40 rounded-full blur-[1px] pointer-events-none';
        containerRef.value.appendChild(p);
        
        const startX = Math.random() * 100;
        const startY = Math.random() * 100;
        
        gsap.set(p, { left: `${startX}%`, top: `${startY}%` });
        
        gsap.to(p, {
            x: 'random(-30, 30)',
            y: 'random(-30, 30)',
            opacity: 0,
            duration: 'random(3, 6)',
            repeat: -1,
            ease: 'sine.inOut',
            delay: Math.random() * 5
        });
    }
});
</script>

<template>
    <div ref="containerRef" class="relative w-full h-full flex items-center justify-center p-4 lg:p-8 overflow-hidden rounded-3xl">
        <!-- Technical Mesh Grid (Hidden on very small) -->
        <div v-if="!small" ref="meshRef" class="absolute inset-0 grid grid-cols-10 grid-rows-10 gap-x-8 gap-y-8 p-4 opacity-10">
            <div v-for="i in 100" :key="i" class="mesh-dot w-1 h-1 bg-foreground/50 rounded-full"></div>
        </div>

        <!-- Scanning Card -->
        <div 
            class="relative w-full bg-background/40 backdrop-blur-2xl rounded-3xl border border-white/20 dark:border-white/10 shadow-2xl flex flex-col items-center justify-center overflow-hidden group transition-all duration-500"
            :class="[
                small ? 'max-w-[120px] aspect-square gap-3 rounded-2xl' : 'max-w-[280px] aspect-[3/4] gap-8',
            ]"
        >
            
            <!-- Scanning Line -->
            <div ref="lineRef" class="absolute w-full h-[2px] top-0 left-0 z-20 overflow-visible">
                <div class="absolute inset-0 bg-foreground shadow-[0_0_15px_2px_rgba(255,255,255,0.3)] dark:shadow-[0_0_15px_2px_rgba(255,255,255,0.1)]"></div>
                <!-- Line glow trail -->
                <div class="absolute bottom-full left-0 w-full h-20 bg-gradient-to-t from-foreground/10 to-transparent"></div>
            </div>

            <!-- Stylized QR Code -->
            <div 
                ref="qrRef" 
                class="relative z-10 p-2 lg:p-3 bg-white/5 rounded-xl lg:rounded-2xl border border-white/10 flex items-center justify-center shadow-inner group-hover:border-foreground/30 transition-colors duration-500"
                :class="small ? 'w-16 h-16' : 'w-32 h-32'"
            >
                <div class="grid grid-cols-3 grid-rows-3 gap-1 lg:gap-2 w-full h-full opacity-80">
                    <div class="border lg:border-2 border-foreground/40 rounded-sm m-0.5"></div>
                    <div class="border lg:border-2 border-foreground/40 rounded-sm m-0.5"></div>
                    <div class="bg-foreground/20 rounded-sm m-0.5"></div>
                    <div class="bg-foreground/10 rounded-sm m-0.5 group-hover:bg-foreground/20 transition-colors"></div>
                    <div class="border lg:border-2 border-foreground/40 rounded-sm m-0.5"></div>
                    <div class="bg-foreground/20 rounded-sm m-0.5"></div>
                    <div class="bg-foreground/20 rounded-sm m-0.5"></div>
                    <div class="bg-foreground/20 rounded-sm m-0.5"></div>
                    <div class="border lg:border-2 border-foreground/40 rounded-sm m-0.5"></div>
                </div>
                
                <!-- Corner Decorations -->
                <div class="absolute -top-1 -left-1 w-2 lg:w-4 h-2 lg:h-4 border-t lg:border-t-2 border-l lg:border-l-2 border-primary"></div>
                <div class="absolute -top-1 -right-1 w-2 lg:w-4 h-2 lg:h-4 border-t lg:border-t-2 border-r lg:border-r-2 border-primary"></div>
                <div class="absolute -bottom-1 -left-1 w-2 lg:w-4 h-2 lg:h-4 border-b lg:border-b-2 border-l lg:border-l-2 border-primary"></div>
                <div class="absolute -bottom-1 -right-1 w-2 lg:w-4 h-2 lg:h-4 border-b lg:border-b-2 border-r lg:border-r-2 border-primary"></div>
            </div>

            <!-- Data HUD elements (Hidden on small) -->
            <div v-if="!small" class="w-full space-y-3 px-8 z-10">
                <div class="flex items-center justify-between">
                    <div class="h-1 w-20 bg-foreground/20 rounded-full overflow-hidden">
                        <div class="h-full bg-primary w-1/2 animate-[pulse_2s_infinite]"></div>
                    </div>
                    <span class="text-[8px] font-mono text-muted-foreground uppercase tracking-widest">Scanning...</span>
                </div>
                <div class="h-1.5 w-full bg-foreground/10 rounded-full flex gap-1">
                    <div v-for="i in 8" :key="i" class="h-full flex-1 rounded-full" :class="i < 6 ? 'bg-foreground/10' : 'bg-transparent'"></div>
                </div>
                <div class="flex justify-center gap-4 pt-2">
                    <div class="w-8 h-1 bg-foreground/5 rounded-full"></div>
                    <div class="w-12 h-1 bg-foreground/5 rounded-full"></div>
                </div>
            </div>

            <!-- Internal glow -->
            <div class="absolute inset-0 bg-gradient-to-br from-primary/5 to-transparent pointer-events-none"></div>
        </div>
        
        <!-- Background Orbs -->
        <div class="absolute top-1/4 -right-1/4 w-32 h-32 lg:w-64 lg:h-64 bg-primary/5 rounded-full blur-[50px] lg:blur-[100px] animate-pulse"></div>
        <div class="absolute bottom-1/4 -left-1/4 w-32 h-32 lg:w-64 lg:h-64 bg-primary/5 rounded-full blur-[50px] lg:blur-[100px] animate-pulse" style="animation-delay: 1s"></div>
    </div>
</template>

<style scoped>
.mesh-dot {
    transition: opacity 0.5s ease;
}
</style>
