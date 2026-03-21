<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import gsap from 'gsap';
import { CalendarDays, BookOpen, ArrowRight, Calendar } from 'lucide-vue-next';
import { ref, onMounted } from 'vue';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';

type Subject = {
    id: number;
    name: string;
};

const props = defineProps<{
    subjects: Subject[];
}>();

const selectedDate = ref<string>(new Date().toISOString().split('T')[0]);
const cardsRef = ref<HTMLElement | null>(null);

onMounted(() => {
    if (cardsRef.value) {
        const cards = cardsRef.value.querySelectorAll('[data-card]');
        
        // Entrance animation
        gsap.from(cards, {
            opacity: 0,
            y: 30,
            stagger: 0.1,
            duration: 0.8,
            ease: 'power2.out'
        });

        cards.forEach((card: any) => {
            card.addEventListener('mousemove', (e: MouseEvent) => {
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                
                const centerX = rect.width / 2;
                const centerY = rect.height / 2;
                
                const rotateX = ((y - centerY) / centerY) * -10;
                const rotateY = ((x - centerX) / centerX) * 10;
                
                gsap.to(card, {
                    rotationX: rotateX,
                    rotationY: rotateY,
                    scale: 1.05,
                    z: 30,
                    zIndex: 50,
                    boxShadow: '0 30px 40px -10px rgba(0, 0, 0, 0.3), 0 15px 15px -10px rgba(0, 0, 0, 0.1)',
                    duration: 0.4,
                    ease: 'power3.out'
                });
            });

            card.addEventListener('mouseleave', () => {
                gsap.to(card, {
                    rotationX: 0,
                    rotationY: 0,
                    scale: 1,
                    z: 0,
                    zIndex: 0,
                    boxShadow: '0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1)',
                    duration: 0.6,
                    ease: 'elastic.out(1, 0.3)'
                });
            });
        });
    }
});
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Manage Attendance', href: '/manage-attendance' }]">
        <Head title="Manage Attendance" />

        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-hidden p-3 sm:p-6 lg:p-10 pb-20 md:pb-6 bg-zinc-50 dark:bg-black">
            <!-- Header Section -->
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 px-1">
                <div class="flex flex-col gap-1">
                    <h1 class="text-2xl sm:text-3xl font-serif font-bold tracking-tight text-foreground">
                        Attendance Sheets
                    </h1>
                    <div class="flex items-center gap-2 text-sm text-muted-foreground">
                        <Calendar class="h-4 w-4" />
                        <span>Select a subject to manage roster</span>
                    </div>
                </div>

                <!-- Minimal Date Picker in Header -->
                <div class="inline-flex items-center gap-3 p-1.5 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-sm transition-all hover:shadow-md self-start md:self-auto">
                    <div class="pl-3 pr-1">
                        <span class="text-[9px] font-black uppercase tracking-widest text-zinc-400 block -mb-0.5">Reference Date</span>
                        <Input 
                            id="header-date"
                            type="date" 
                            v-model="selectedDate" 
                            class="h-7 w-32 bg-transparent border-0 p-0 focus-visible:ring-0 font-bold text-xs"
                        />
                    </div>
                    <div class="h-8 w-8 rounded-lg bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-400 shrink-0">
                        <CalendarDays class="w-4 h-4" />
                    </div>
                </div>
            </div>

            <!-- Subject Grid -->
            <div 
                ref="cardsRef"
                class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6"
            >
                <div 
                    v-for="(subject, index) in subjects" 
                    :key="subject.id"
                    data-card
                    @click="router.get(`/manage-attendance/${subject.id}/${selectedDate}`)"
                    class="group relative overflow-hidden rounded-[2rem] p-7 transition-all duration-700 hover:shadow-2xl hover:-translate-y-2 bg-white dark:bg-black border border-zinc-100 dark:border-zinc-800/50 text-zinc-900 dark:text-white shadow-xl cursor-pointer h-52 flex flex-col justify-between isolate"
                >
                    <!-- Background Glow Overlay -->
                    <div 
                        class="absolute -right-8 -top-8 h-40 w-40 rounded-full blur-3xl transition-all duration-700 -z-10 group-hover:scale-110"
                        :class="[
                            index % 4 === 0 ? 'bg-emerald-50/50 dark:bg-emerald-900/20' :
                            index % 4 === 1 ? 'bg-amber-50/50 dark:bg-amber-900/20' :
                            index % 4 === 2 ? 'bg-indigo-50/50 dark:bg-indigo-900/20' :
                            'bg-zinc-50/50 dark:bg-zinc-900/20'
                        ]"
                    ></div>
                    
                    <div class="relative z-10 flex flex-col h-full justify-between">
                        <div class="flex items-start justify-between">
                            <div class="h-14 w-14 rounded-2xl bg-zinc-50 dark:bg-zinc-900 flex items-center justify-center border border-zinc-100 dark:border-zinc-800 shadow-inner group-hover:scale-110 transition-transform duration-500">
                                <BookOpen class="h-7 w-7 text-zinc-400 group-hover:text-zinc-900 dark:group-hover:text-white transition-colors" />
                            </div>
                            
                            <div 
                                class="h-1.5 w-12 rounded-full"
                                :class="[
                                    index % 4 === 0 ? 'bg-emerald-500' :
                                    index % 4 === 1 ? 'bg-amber-500' :
                                    index % 4 === 2 ? 'bg-indigo-500' :
                                    'bg-zinc-900 dark:bg-white'
                                ]"
                            ></div>
                        </div>

                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.25em] text-zinc-400 dark:text-zinc-500 mb-1 leading-none">
                                Subject Roster
                            </p>
                            <h3 class="text-2xl font-serif font-black tracking-tight text-zinc-900 dark:text-white leading-[1.1] line-clamp-2 brightness-90 group-hover:brightness-110 transition-all">
                                {{ subject.name }}
                            </h3>
                        </div>
                        
                        <div class="flex items-center gap-2 text-[10px] font-black uppercase tracking-widest text-zinc-400 group-hover:text-zinc-900 dark:group-hover:text-white transition-colors">
                            <span class="px-3 py-1.5 rounded-full bg-zinc-50 dark:bg-zinc-900 border border-zinc-100 dark:border-zinc-800 flex items-center gap-2">
                                Open Class Sheet
                                <ArrowRight class="w-3 h-3 group-hover:translate-x-1 transition-transform" />
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="subjects.length === 0" class="col-span-full py-20 text-center space-y-6 bg-white dark:bg-black rounded-2xl border-2 border-dashed border-zinc-200 dark:border-zinc-800 shadow-md">
                    <div class="inline-flex h-20 w-20 rounded-full bg-zinc-50 dark:bg-zinc-900 items-center justify-center text-zinc-200 dark:text-zinc-800">
                        <BookOpen class="w-10 h-10" stroke-width="1" />
                    </div>
                    <div class="space-y-1 px-4">
                        <h4 class="text-xl font-serif font-bold text-zinc-900 dark:text-white">No subjects assigned</h4>
                        <p class="text-zinc-500 text-xs">Create subjects to see them listed here.</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
