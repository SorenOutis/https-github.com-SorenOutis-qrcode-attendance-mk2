<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Printer, CheckSquare, Square } from 'lucide-vue-next';
import QRCode from 'qrcode';
import { computed, onMounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

type Student = {
    id: number;
    name: string;
    student_number: string;
    section?: string | null;
    qr_token: string;
};

const props = defineProps<{
    students: Student[];
    preselectedIds: number[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Print QR Cards', href: '/students/print-cards' },
];

const query = ref('');
const selected = ref<Set<number>>(new Set(props.preselectedIds ?? []));
const svgById = ref<Record<number, string>>({});
const organizationName = ref('ST. JUDE COLLEGE');
const accentColor = ref('#09090b');
const showPhoto = ref(true);

const colors = [
    { name: 'Black', value: '#09090b' },
    { name: 'Blue', value: '#2563eb' },
    { name: 'Indigo', value: '#4f46e5' },
    { name: 'Rose', value: '#e11d48' },
    { name: 'Emerald', value: '#10b981' },
];

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return props.students;
    return props.students.filter((s) => {
        return (
            s.name.toLowerCase().includes(q) ||
            s.student_number.toLowerCase().includes(q) ||
            (s.section ?? '').toLowerCase().includes(q)
        );
    });
});

const printingList = computed(() => {
    if (selected.value.size === 0) return filtered.value;
    return filtered.value.filter((s) => selected.value.has(s.id));
});

async function ensureSvg(student: Student) {
    if (svgById.value[student.id] && !accentColor.value) return; // Basic cache
    try {
        let svg = await QRCode.toString(student.qr_token, {
            type: 'svg',
            margin: 1,
            width: 220,
            color: {
                dark: accentColor.value,
                light: '#ffffff',
            },
        });

        // Ensure the injected SVG always fits the box (prevents cropping/overflow)
        svg = svg.replace(
            '<svg',
            '<svg preserveAspectRatio="xMidYMid meet"',
        );

        svgById.value = { ...svgById.value, [student.id]: svg };
    } catch {
        svgById.value = { ...svgById.value, [student.id]: '' };
    }
}

async function ensureVisibleSvgs() {
    await Promise.all(printingList.value.map(ensureSvg));
}

// Watch accent color to regenerate SVGs
watch(accentColor, async () => {
    svgById.value = {}; // Clear cache
    await ensureVisibleSvgs();
});

function toggle(id: number) {
    const next = new Set(selected.value);
    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }
    selected.value = next;
}

function selectAllVisible() {
    const next = new Set(selected.value);
    for (const s of filtered.value) next.add(s.id);
    selected.value = next;
}

function clearSelection() {
    selected.value = new Set();
}

function printNow() {
    window.print();
}

onMounted(async () => {
    await ensureVisibleSvgs();
});

watch([query, selected], async () => {
    await ensureVisibleSvgs();
});
</script>

<template>
    <Head title="Print QR Cards" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6 print:p-0">
            <!-- Toolbar (hidden on print) -->
            <div class="mb-6 flex flex-col gap-6 rounded-2xl border border-sidebar-border/50 bg-background/50 backdrop-blur-xl p-6 shadow-lg print:hidden">
                <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div class="space-y-1">
                        <h1 class="text-2xl font-serif font-bold tracking-tight">ID Card Designer</h1>
                        <p class="text-sm text-muted-foreground">
                            Customize and print premium student ID cards with QR codes.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <Button variant="outline" size="sm" class="rounded-full h-9" @click="selectAllVisible">
                            <CheckSquare class="mr-2 h-4 w-4" />
                            Select All
                        </Button>
                        <Button variant="outline" size="sm" class="rounded-full h-9" @click="clearSelection">
                            <Square class="mr-2 h-4 w-4" />
                            Clear
                        </Button>
                        <Button size="sm" class="rounded-full h-9 px-6 bg-zinc-900 text-white hover:bg-zinc-800 dark:bg-white dark:text-zinc-900" @click="printNow">
                            <Printer class="mr-2 h-4 w-4" />
                            Print Now
                        </Button>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-4 border-t">
                    <div class="space-y-2">
                        <label class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Search Students</label>
                        <Input v-model="query" placeholder="Name, ID number, section..." class="h-9 rounded-xl" />
                    </div>

                    <div class="space-y-2">
                        <label class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Organization Name</label>
                        <Input v-model="organizationName" placeholder="e.g. UNIVERSITY NAME" class="h-9 rounded-xl" />
                    </div>

                    <div class="space-y-2">
                        <label class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Accent Color</label>
                        <div class="flex items-center gap-2 flex-wrap">
                            <button 
                                v-for="color in colors" 
                                :key="color.value"
                                @click="accentColor = color.value"
                                class="h-8 w-8 rounded-full border-2 transition-transform hover:scale-110 flex items-center justify-center relative"
                                :style="{ backgroundColor: color.value }"
                                :class="accentColor === color.value ? 'border-zinc-400 ring-2 ring-offset-2 ring-zinc-200' : 'border-transparent'"
                            >
                                <div v-if="accentColor === color.value" class="h-2 w-2 rounded-full bg-white shadow-sm"></div>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Print sheet -->
            <div class="mx-auto w-full max-w-[1200px] print:max-w-none">
                <div
                    class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-2 xl:grid-cols-2 print:grid-cols-2 print:gap-4"
                    style="print-color-adjust: exact; -webkit-print-color-adjust: exact;"
                >
                    <button
                        v-for="student in printingList"
                        :key="student.id"
                        type="button"
                        class="text-left outline-none group"
                        @click="toggle(student.id)"
                    >
                        <div
                            :class="
                                cn(
                                    'relative overflow-hidden rounded-[1.25rem] border bg-white shadow-xl transition-all duration-300',
                                    'border-zinc-200 hover:shadow-2xl group-hover:-translate-y-1',
                                    selected.has(student.id) ? 'ring-2 ring-offset-4 ring-offset-background' : '',
                                    'print:shadow-none print:ring-0 print:ring-offset-0 print:border-zinc-300 print:hover:translate-y-0',
                                )
                            "
                            :style="{ 
                                width: '3.375in', 
                                height: '2.125in',
                                borderColor: selected.has(student.id) ? accentColor : undefined,
                                ringColor: accentColor 
                            }"
                        >
                            <!-- Decorative background -->
                            <div 
                                class="absolute top-0 right-0 w-32 h-32 -mr-8 -mt-8 rounded-full opacity-10 blur-2xl"
                                :style="{ backgroundColor: accentColor }"
                            ></div>
                            <div 
                                class="absolute bottom-0 left-0 w-24 h-24 -ml-6 -mb-6 rounded-full opacity-5 blur-xl"
                                :style="{ backgroundColor: accentColor }"
                            ></div>

                            <div class="relative z-10 flex h-full flex-col">
                                <!-- Card Header -->
                                <div 
                                    class="h-10 px-4 flex items-center justify-between border-b"
                                    :style="{ borderBottomColor: accentColor + '20' }"
                                >
                                    <div class="text-[9px] font-black uppercase tracking-[0.2em]" :style="{ color: accentColor }">
                                        {{ organizationName }}
                                    </div>
                                    <div class="text-[8px] font-semibold text-zinc-400 uppercase tracking-widest">
                                        Identity Card
                                    </div>
                                </div>

                                <div class="flex-1 flex p-4 gap-4">
                                    <!-- Photo Placeholder or QR -->
                                    <div class="flex flex-col gap-2 shrink-0">
                                        <div 
                                            class="w-20 h-24 rounded-lg bg-zinc-50 border border-dashed border-zinc-200 flex items-center justify-center overflow-hidden relative"
                                        >
                                            <div class="absolute inset-0 flex items-center justify-center opacity-10">
                                                <UserIcon class="w-12 h-12" />
                                            </div>
                                            <span class="text-[8px] text-zinc-400 font-bold uppercase text-center px-2 z-10">Photo Here</span>
                                        </div>
                                    </div>

                                    <!-- Student Details -->
                                    <div class="flex-1 flex flex-col justify-between min-w-0">
                                        <div class="space-y-1">
                                            <div class="line-clamp-1 text-base font-black leading-tight text-zinc-900 uppercase">
                                                {{ student.name }}
                                            </div>
                                            <div class="flex flex-col gap-0.5">
                                                <div class="text-[9px] font-bold text-zinc-400 uppercase tracking-wider">Student Number</div>
                                                <div class="text-xs font-black font-mono text-zinc-900">{{ student.student_number }}</div>
                                            </div>
                                            <div v-if="student.section" class="flex flex-col gap-0.5">
                                                <div class="text-[9px] font-bold text-zinc-400 uppercase tracking-wider">Section</div>
                                                <div class="text-xs font-bold text-zinc-700">{{ student.section }}</div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- QR Code (Smaller and Sleek) -->
                                    <div class="w-20 flex flex-col items-center gap-1 shrink-0 ml-auto">
                                        <div class="w-16 h-16 rounded-lg border border-zinc-100 bg-white p-1 shadow-sm">
                                            <div
                                                v-if="svgById[student.id]"
                                                class="h-full w-full [&>svg]:block [&>svg]:h-full [&>svg]:w-full"
                                                v-html="svgById[student.id]"
                                            />
                                        </div>
                                        <div class="text-[7px] font-bold uppercase tracking-tighter text-zinc-400">Scan to verify</div>
                                    </div>
                                </div>
                                
                                <div class="px-4 py-2 border-t flex items-center justify-between" :style="{ borderTopColor: accentColor + '10' }">
                                    <div class="text-[7px] text-zinc-300 font-mono italic">
                                        {{ student.qr_token.substring(0, 16) }}...
                                    </div>
                                    <div 
                                        class="h-1.5 w-1.5 rounded-full"
                                        :style="{ backgroundColor: accentColor }"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
@media print {
    @page {
        size: auto;
        margin: 0.5in;
    }
}
</style>

