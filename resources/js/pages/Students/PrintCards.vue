<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Printer, CheckSquare, Square, Download, User as UserIcon } from 'lucide-vue-next';
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
const organizationName = ref('KOAMISHIN.ORG');
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

async function downloadCard(student: Student) {
    const scale = 4; // High DPI
    const w = 3.375 * 96 * scale;
    const h = 2.125 * 96 * scale;
    
    const canvas = document.createElement('canvas');
    canvas.width = w;
    canvas.height = h;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    // 1. Draw Background (Rounded)
    ctx.fillStyle = '#ffffff';
    ctx.beginPath();
    const r = 20 * scale;
    ctx.moveTo(r, 0);
    ctx.lineTo(w - r, 0);
    ctx.quadraticCurveTo(w, 0, w, r);
    ctx.lineTo(w, h - r);
    ctx.quadraticCurveTo(w, h, w - r, h);
    ctx.lineTo(r, h);
    ctx.quadraticCurveTo(0, h, 0, h - r);
    ctx.lineTo(0, r);
    ctx.quadraticCurveTo(0, 0, r, 0);
    ctx.closePath();
    ctx.fill();
    ctx.clip();

    // 2. Decorative Blobs
    ctx.globalAlpha = 0.1;
    ctx.fillStyle = accentColor.value;
    ctx.beginPath();
    ctx.arc(w - 20 * scale, 0, 100 * scale, 0, Math.PI * 2);
    ctx.fill();
    ctx.globalAlpha = 0.05;
    ctx.beginPath();
    ctx.arc(20 * scale, h, 80 * scale, 0, Math.PI * 2);
    ctx.fill();
    ctx.globalAlpha = 1.0;

    // 3. Header
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, w, 40 * scale);
    ctx.strokeStyle = accentColor.value + '20';
    ctx.lineWidth = 1 * scale;
    ctx.beginPath();
    ctx.moveTo(0, 40 * scale);
    ctx.lineTo(w, 40 * scale);
    ctx.stroke();

    ctx.fillStyle = accentColor.value;
    ctx.font = `900 ${9 * scale}px Arial`; // Use 900 for "black" weight
    ctx.fillText(organizationName.value.toUpperCase(), 16 * scale, 26 * scale);
    
    ctx.fillStyle = '#a1a1aa';
    ctx.font = `600 ${8 * scale}px Arial`;
    ctx.textAlign = 'right';
    ctx.fillText('IDENTITY CARD', w - 16 * scale, 26 * scale);
    ctx.textAlign = 'left';

    // 4. Photo Placeholder
    ctx.fillStyle = '#fafafa';
    ctx.beginPath();
    const photoR = 8 * scale;
    const px = 16 * scale, py = 56 * scale, pw = 80 * scale, ph = 96 * scale;
    ctx.moveTo(px + photoR, py);
    ctx.lineTo(px + pw - photoR, py);
    ctx.quadraticCurveTo(px + pw, py, px + pw, py + photoR);
    ctx.lineTo(px + pw, py + ph - photoR);
    ctx.quadraticCurveTo(px + pw, py + ph, px + pw - photoR, py + ph);
    ctx.lineTo(px + photoR, py + ph);
    ctx.quadraticCurveTo(px, py + ph, px, py + ph - photoR);
    ctx.lineTo(px, py + photoR);
    ctx.quadraticCurveTo(px, py, px + photoR, py);
    ctx.fill();
    ctx.strokeStyle = '#e4e4e7';
    const dashSize = 4 * scale;
    ctx.setLineDash([dashSize, dashSize]);
    ctx.lineWidth = 0.5 * scale;
    ctx.stroke();
    
    // Draw "PHOTO HERE" text more precisely
    ctx.setLineDash([]);
    ctx.fillStyle = '#a1a1aa';
    
    // Draw simple User icon
    ctx.beginPath();
    ctx.arc(px + pw/2, py + ph/2 - 10 * scale, 12 * scale, 0, Math.PI * 2);
    ctx.fill();
    ctx.beginPath();
    ctx.arc(px + pw/2, py + ph/2 + 20 * scale, 20 * scale, Math.PI, 0);
    ctx.fill();

    ctx.font = `bold ${6 * scale}px Arial`;
    ctx.textAlign = 'center';
    ctx.fillText('PHOTO HERE', px + pw/2, py + ph/2 + 28 * scale);
    ctx.textAlign = 'left';

    // 5. Student Details
    ctx.fillStyle = '#09090b';
    ctx.font = `900 ${16 * scale}px Arial`; // Name should be boldest
    ctx.fillText(student.name.toUpperCase(), 112 * scale, 76 * scale);

    const labelFont = `bold ${9 * scale}px Arial`;
    const valueFont = `900 ${11 * scale}px Arial`;

    ctx.fillStyle = '#a1a1aa';
    ctx.font = labelFont;
    ctx.fillText('STUDENT NUMBER', 112 * scale, 98 * scale);
    ctx.fillStyle = '#09090b';
    ctx.font = valueFont;
    ctx.fillText(student.student_number, 112 * scale, 114 * scale);

    if (student.section) {
        ctx.fillStyle = '#a1a1aa';
        ctx.font = labelFont;
        ctx.fillText('SECTION', 112 * scale, 134 * scale);
        ctx.fillStyle = '#3f3f46';
        ctx.font = `bold ${11 * scale}px Arial`;
        ctx.fillText(student.section, 112 * scale, 150 * scale);
    }

    // 6. Footer bar
    ctx.strokeStyle = accentColor.value + '20';
    ctx.lineWidth = 0.5 * scale;
    ctx.beginPath();
    ctx.moveTo(0, h - 30 * scale);
    ctx.lineTo(w, h - 30 * scale);
    ctx.stroke();

    ctx.fillStyle = '#d4d4d8';
    ctx.font = `italic ${7 * scale}px Courier New`;
    ctx.fillText(student.qr_token.substring(0, 16) + '...', 16 * scale, h - 12 * scale);
    
    ctx.fillStyle = accentColor.value;
    ctx.beginPath();
    ctx.arc(w - 20 * scale, h - 15 * scale, 3 * scale, 0, Math.PI * 2);
    ctx.fill();

    // 7. QR Code
    const svgString = svgById.value[student.id];
    if (svgString) {
        const img = new Image();
        const svg64 = btoa(svgString);
        img.src = 'data:image/svg+xml;base64,' + svg64;
        await new Promise((resolve) => {
            img.onload = () => {
                ctx.fillStyle = '#ffffff';
                ctx.beginPath();
                const qrx = w - 90 * scale, qry = 54 * scale, qrw = 74 * scale, qrh = 74 * scale;
                // Draw a nice rounded box for the QR
                const qrr = 6 * scale;
                ctx.moveTo(qrx - 2 * scale + qrr, qry - 2 * scale);
                ctx.lineTo(qrx + qrw + 2 * scale - qrr, qry - 2 * scale);
                ctx.quadraticCurveTo(qrx + qrw + 2 * scale, qry - 2 * scale, qrx + qrw + 2 * scale, qry - 2 * scale + qrr);
                ctx.lineTo(qrx + qrw + 2 * scale, qry + qrh + 2 * scale - qrr);
                ctx.quadraticCurveTo(qrx + qrw + 2 * scale, qry + qrh + 2 * scale, qrx + qrw + 2 * scale - qrr, qry + qrh + 2 * scale);
                ctx.lineTo(qrx - 2 * scale + qrr, qry + qrh + 2 * scale);
                ctx.quadraticCurveTo(qrx - 2 * scale, qry + qrh + 2 * scale, qrx - 2 * scale, qry + qrh + 2 * scale - qrr);
                ctx.lineTo(qrx - 2 * scale, qry - 2 * scale + qrr);
                ctx.quadraticCurveTo(qrx - 2 * scale, qry - 2 * scale, qrx - 2 * scale + qrr, qry - 2 * scale);
                ctx.closePath();
                ctx.fill();

                ctx.drawImage(img, qrx, qry, qrw, qrh);

                // Add "SCAN TO VERIFY" below QR
                ctx.fillStyle = '#a1a1aa';
                ctx.font = `bold ${7 * scale}px Arial`;
                ctx.textAlign = 'center';
                ctx.fillText('SCAN TO VERIFY', qrx + qrw/2, qry + qrh + 8 * scale);
                ctx.textAlign = 'left';
                resolve(null);
            };
        });
    }

    // Download
    const link = document.createElement('a');
    link.download = `${student.student_number}-${student.name.replace(/\s+/g, '_')}.png`;
    link.href = canvas.toDataURL('image/png');
    link.click();
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
            <div class="mb-6 flex flex-col gap-4 rounded-xl border border-sidebar-border/50 bg-background/50 backdrop-blur-xl p-4 sm:p-6 shadow-lg print:hidden">
                <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div class="space-y-1">
                        <h1 class="text-xl sm:text-2xl font-serif font-bold tracking-tight">ID Card Designer</h1>
                        <p class="text-xs sm:text-sm text-muted-foreground">
                            Customize and print premium student ID cards.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <Button variant="outline" size="sm" class="rounded-full h-8 sm:h-9 text-[10px] sm:text-xs" @click="selectAllVisible">
                            <CheckSquare class="mr-1.5 h-3.5 w-3.5" />
                            <span class="hidden sm:inline">Select All</span>
                            <span class="sm:hidden">All</span>
                        </Button>
                        <Button variant="outline" size="sm" class="rounded-full h-8 sm:h-9 text-[10px] sm:text-xs" @click="clearSelection">
                            <Square class="mr-1.5 h-3.5 w-3.5" />
                            <span class="hidden sm:inline">Clear Selection</span>
                            <span class="sm:hidden">Clear</span>
                        </Button>
                        <Button size="sm" class="rounded-full h-8 sm:h-9 px-4 sm:px-6 bg-zinc-900 text-white hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 text-[10px] sm:text-xs" @click="printNow">
                            <Printer class="mr-1.5 h-3.5 w-3.5" />
                            Print Now
                        </Button>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 sm:gap-6 pt-4 border-t">
                    <div class="space-y-2">
                        <label class="text-[9px] sm:text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Search Students</label>
                        <Input v-model="query" placeholder="Name, ID..." class="h-8 sm:h-9 rounded-lg sm:rounded-xl text-xs sm:text-sm" />
                    </div>

                    <div class="space-y-2">
                        <label class="text-[9px] sm:text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Organization Name</label>
                        <Input v-model="organizationName" placeholder="e.g. UNIVERSITY NAME" class="h-8 sm:h-9 rounded-lg sm:rounded-xl text-xs sm:text-sm" />
                    </div>

                    <div class="space-y-2">
                        <label class="text-[9px] sm:text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Accent Color</label>
                        <div class="flex items-center gap-2 flex-wrap">
                            <button 
                                v-for="color in colors" 
                                :key="color.value"
                                @click="accentColor = color.value"
                                class="h-6 w-6 sm:h-8 sm:w-8 rounded-full border-2 transition-transform hover:scale-110 flex items-center justify-center relative"
                                :style="{ backgroundColor: color.value }"
                                :class="accentColor === color.value ? 'border-zinc-400 ring-2 ring-offset-2 ring-zinc-200' : 'border-transparent'"
                            >
                                <div v-if="accentColor === color.value" class="h-1.5 w-1.5 sm:h-2 sm:w-2 rounded-full bg-white shadow-sm"></div>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Print sheet -->
            <div class="mx-auto w-full max-w-[1200px] print:max-w-none pb-24 sm:pb-0">
                <div
                    class="grid grid-cols-1 gap-6 sm:gap-8 justify-items-center sm:grid-cols-2 lg:grid-cols-2 xl:grid-cols-2 print:grid-cols-2 print:gap-4 lg:justify-items-start"
                    style="print-color-adjust: exact; -webkit-print-color-adjust: exact;"
                >
                    <button
                        v-for="student in printingList"
                        :key="student.id"
                        type="button"
                        class="text-left outline-none group w-fit"
                        @click="toggle(student.id)"
                    >
                        <!-- Scale container for mobile browsing -->
                        <div class="origin-top scale-[0.85] xs:scale-[0.9] sm:scale-100 transition-transform">
                            <div
                                :class="
                                    cn(
                                        'relative overflow-hidden rounded-[1.25rem] border bg-white shadow-xl transition-all duration-300',
                                        'border-zinc-200 hover:shadow-2xl group-hover:-translate-y-1',
                                        selected.has(student.id) ? 'ring-2 ring-offset-4 ring-offset-background' : '',
                                        'print:shadow-none print:ring-0 print:ring-offset-0 print:border-zinc-300 print:hover:translate-y-0 print:scale-100',
                                    )
                                "
                                :style="{ 
                                    width: '3.375in', 
                                    height: '2.125in',
                                    borderColor: selected.has(student.id) ? accentColor : undefined,
                                    '--tw-ring-color': accentColor 
                                }"
                            >
                                <!-- Quick Download Button (hidden on print) -->
                                <Button 
                                    type="button"
                                    variant="secondary" 
                                    size="icon" 
                                    class="absolute top-2 right-2 h-7 w-7 rounded-full opacity-0 group-hover:opacity-100 transition-opacity z-20 print:hidden bg-white/80 backdrop-blur shadow-sm hover:bg-white"
                                    @click.stop="downloadCard(student)"
                                    title="Download as PNG"
                                >
                                    <Download class="h-3.5 w-3.5" />
                                </Button>    
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

/* Base card container sizing for non-print */
@media screen and (max-width: 480px) {
    /* Scale container to fix wrapping issues on very small screens */
    .scale-\[0\.85\] {
        transform: scale(0.85);
    }
}
</style>

