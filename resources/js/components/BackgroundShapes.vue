<script setup lang="ts">
import { onMounted, ref } from 'vue';

const mouseX = ref(0);
const mouseY = ref(0);

onMounted(() => {
    window.addEventListener('mousemove', (e) => {
        mouseX.value = (e.clientX / window.innerWidth - 0.5) * 20;
        mouseY.value = (e.clientY / window.innerHeight - 0.5) * 20;
    });
});
</script>

<template>
    <div class="fixed inset-0 -z-10 overflow-hidden pointer-events-none">
        <!-- Floating Orb 1 -->
        <div 
            class="absolute top-[10%] left-[15%] w-64 h-64 rounded-full bg-primary/5 blur-[100px] transition-transform duration-1000 ease-out"
            :style="{ transform: `translate(${mouseX}px, ${mouseY}px)` }"
        ></div>
        
        <!-- Floating Orb 2 -->
        <div 
            class="absolute bottom-[20%] right-[10%] w-96 h-96 rounded-full bg-zinc-500/5 blur-[120px] transition-transform duration-1000 ease-out"
            :style="{ transform: `translate(${-mouseX * 1.5}px, ${-mouseY * 1.5}px)` }"
        ></div>

        <!-- Floating Orb 3 (Accent) -->
        <div 
            class="absolute top-[40%] right-[30%] w-48 h-48 rounded-full bg-primary/3 blur-[80px] transition-transform duration-[2000ms] ease-out"
            :style="{ transform: `translate(${mouseX * 0.8}px, ${-mouseY * 1.2}px)` }"
        ></div>
    </div>
</template>
