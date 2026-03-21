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
    if (svgById.value[student.id]) return;
    try {
        let svg = await QRCode.toString(student.qr_token, {
            type: 'svg',
            margin: 1,
            width: 220,
            color: {
                dark: '#09090b',
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
            <div class="mb-6 flex flex-col gap-3 rounded-2xl border border-sidebar-border/50 bg-background/50 backdrop-blur-xl p-4 shadow-lg print:hidden">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div class="space-y-1">
                        <h1 class="text-xl font-serif font-bold tracking-tight">Printable QR Cards</h1>
                        <p class="text-sm text-muted-foreground">
                            Tip: select students (optional), then hit Print. If nothing is selected, it prints the current filtered list.
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <Button variant="outline" size="sm" class="rounded-full" @click="selectAllVisible">
                            <CheckSquare class="mr-2 h-4 w-4" />
                            Select visible
                        </Button>
                        <Button variant="outline" size="sm" class="rounded-full" @click="clearSelection">
                            <Square class="mr-2 h-4 w-4" />
                            Clear
                        </Button>
                        <Button size="sm" class="rounded-full" @click="printNow">
                            <Printer class="mr-2 h-4 w-4" />
                            Print
                        </Button>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <Input v-model="query" placeholder="Search name, ID number, section..." class="max-w-md" />
                    <div class="text-xs text-muted-foreground">
                        Showing <span class="font-semibold text-foreground">{{ printingList.length }}</span>
                        card(s)
                        <span v-if="selected.size" class="ml-2">
                            (Selected: <span class="font-semibold text-foreground">{{ selected.size }}</span>)
                        </span>
                    </div>
                </div>
            </div>

            <!-- Print sheet -->
            <div class="mx-auto w-full max-w-[1200px] print:max-w-none">
                <div
                    class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 print:grid-cols-2 print:gap-2"
                    style="print-color-adjust: exact; -webkit-print-color-adjust: exact;"
                >
                    <button
                        v-for="student in printingList"
                        :key="student.id"
                        type="button"
                        class="text-left"
                        @click="toggle(student.id)"
                    >
                        <div
                            :class="
                                cn(
                                    'relative overflow-hidden rounded-2xl border bg-white p-4 shadow-sm transition-all',
                                    'border-zinc-200 hover:shadow-md',
                                    selected.has(student.id) ? 'ring-2 ring-ring ring-offset-2 ring-offset-background' : '',
                                    'print:shadow-none print:ring-0 print:ring-offset-0 print:border-zinc-300',
                                )
                            "
                            style="width: 3.375in; height: 2.125in;"
                        >
                            <div class="absolute inset-0 bg-[linear-gradient(to_right,#80808010_1px,transparent_1px),linear-gradient(to_bottom,#80808010_1px,transparent_1px)] bg-[size:28px_28px] opacity-60 print:opacity-30"></div>
                            <div class="absolute -right-8 -top-8 h-28 w-28 rounded-full bg-emerald-500/10 blur-2xl print:bg-emerald-500/5"></div>

                            <div class="relative z-10 flex h-full gap-3">
                                <div class="flex w-[1.35in] shrink-0 items-center justify-center overflow-hidden rounded-xl border border-zinc-200 bg-white p-2">
                                    <div
                                        v-if="svgById[student.id]"
                                        class="h-full w-full [&>svg]:block [&>svg]:h-full [&>svg]:w-full"
                                        v-html="svgById[student.id]"
                                    />
                                    <div v-else class="text-xs text-zinc-500">QR…</div>
                                </div>

                                <div class="flex min-w-0 flex-1 flex-col justify-between">
                                    <div class="space-y-1">
                                        <div class="text-[10px] font-semibold uppercase tracking-[0.25em] text-zinc-500">
                                            QR Attendance
                                        </div>
                                        <div class="line-clamp-2 text-base font-bold leading-tight text-zinc-900">
                                            {{ student.name }}
                                        </div>
                                        <div class="text-xs font-medium text-zinc-600">
                                            ID: <span class="font-mono text-zinc-900">{{ student.student_number }}</span>
                                        </div>
                                        <div v-if="student.section" class="text-xs text-zinc-600">
                                            Section: <span class="font-semibold text-zinc-900">{{ student.section }}</span>
                                        </div>
                                    </div>

                                    <div class="flex items-center justify-between pt-2">
                                        <div class="text-[10px] text-zinc-500">
                                            Scan to open portal
                                        </div>
                                        <div class="rounded-full border border-zinc-200 px-2 py-0.5 text-[10px] font-semibold text-zinc-700 print:hidden">
                                            {{ selected.has(student.id) ? 'Selected' : 'Tap to select' }}
                                        </div>
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

