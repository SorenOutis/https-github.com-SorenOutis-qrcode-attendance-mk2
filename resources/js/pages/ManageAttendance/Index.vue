<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import gsap from 'gsap';
import { CalendarDays, BookOpen, ArrowRight, Calendar, Users } from 'lucide-vue-next';
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
        // Setup perspective
        gsap.set(cardsRef.value, { perspective: 1000 });
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
                    <h1 class="text-lg sm:text-3xl font-serif font-bold tracking-tight text-foreground">
                        Attendance Sheets
                    </h1>
                    <div class="flex items-center gap-2 text-sm text-muted-foreground">
                        <Calendar class="h-4 w-4" />
                        <span>Select a subject to manage roster</span>
                    </div>
                </div>

                <!-- Header Actions -->
                <div class="flex items-center gap-2 sm:gap-3 self-start md:self-auto shrink-0 flex-wrap sm:flex-nowrap">
                    <button 
                        @click="router.get('/subjects')"
                        class="inline-flex items-center justify-center gap-2 h-10 px-4 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-sm transition-all hover:shadow-md hover:bg-zinc-50 dark:hover:bg-zinc-800 text-xs font-bold text-zinc-900 dark:text-zinc-100 group"
                    >
                        <BookOpen class="w-3.5 h-3.5 text-zinc-400 group-hover:text-zinc-900 dark:group-hover:text-zinc-100 transition-colors" />
                        Manage Subjects
                    </button>

                    <!-- Minimal Date Picker in Header -->
                    <div class="inline-flex items-center gap-3 p-1.5 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-sm transition-all hover:shadow-md">
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
            </div>

            <!-- Subject Grid -->
            <div 
                ref="cardsRef"
                class="grid grid-cols-2 lg:grid-cols-3 gap-4"
            >
                    <div 
                    v-for="(subject, index) in subjects" 
                    :key="subject.id"
                    v-reveal:[index%10*40]
                    v-tilt
                    data-card
                    @mouseenter="router.prefetch(`/manage-attendance/${subject.id}/${selectedDate}`, { method: 'get' })"
                    @click="router.get(`/manage-attendance/${subject.id}/${selectedDate}`)"
                    class="group relative overflow-hidden rounded-[1rem] p-3 sm:p-4 transition-colors duration-200 hover:bg-zinc-50 dark:hover:bg-zinc-900 bg-white dark:bg-black border border-zinc-200 dark:border-zinc-800 text-zinc-900 dark:text-white shadow-sm cursor-pointer h-28 sm:h-36 flex flex-col justify-between preserve-3d shadow-3d"
                >
                    <Users class="absolute right-[-5%] bottom-[-10%] h-24 w-24 text-zinc-900/[0.03] dark:text-white/[0.03] transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-6 pointer-events-none" />
                    <div class="relative z-10 flex flex-col h-full justify-between">
                        <div class="flex items-start justify-end">
                            <div 
                                class="h-1 w-10 rounded-full"
                                :class="[
                                    index % 4 === 0 ? 'bg-emerald-500' :
                                    index % 4 === 1 ? 'bg-amber-500' :
                                    index % 4 === 2 ? 'bg-indigo-500' :
                                    'bg-zinc-900 dark:bg-white'
                                ]"
                            ></div>
                        </div>

                        <div>
                             <h3 class="text-lg font-serif font-black tracking-tight text-zinc-900 dark:text-white leading-tight line-clamp-2">
                                {{ subject.name }}
                            </h3>
                        </div>
                        
                        <div class="flex items-center gap-2 text-[8px] font-black uppercase tracking-widest text-zinc-400">
                            <span class="px-2 py-1 rounded-full bg-zinc-50 dark:bg-zinc-900 border border-zinc-100 dark:border-zinc-800 flex items-center gap-1.5">
                                Open Class
                                <ArrowRight class="w-2.5 h-2.5" />
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
