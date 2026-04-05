<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { 
    Calendar, 
    Clock, 
    Printer, 
    Shield, 
    History, 
    Flame, 
    ClipboardList, 
    Send, 
    LayoutGrid,
    FileText,
    RefreshCw
} from 'lucide-vue-next';
import QRCode from 'qrcode';
import { computed, onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/composables/useToast';
import { cn } from '@/lib/utils';

type Subject = { id: number; name: string };

type PortalStudent = {
    id: number;
    name: string;
    student_number: string;
    email?: string | null;
    section?: string | null;
    qr_token: string;
};

type TodayStatus = {
    id: number;
    status: string;
    time: string;
    subject_id?: number | null;
};

type HistoryRecord = {
    id: number;
    status: string;
    scanned_at: string;
    slot_start?: string | null;
    slot_end?: string | null;
    subject_id?: number | null;
};

type PortalExcuse = {
    id: number;
    attendance_id: number | null;
    date: string;
    reason: string;
    status: string;
    teacher_notes?: string | null;
    created_at: string;
};

const props = defineProps<{
    student: PortalStudent;
    subjects: Subject[];
    stats: { percentage: number; streak: number };
    todaySchedule: { day: string; start: string; end: string; subject_id?: number | null }[];
    todayStatuses: TodayStatus[];
    history: HistoryRecord[];
    excuses: PortalExcuse[];
}>();

const qrSvg = ref<string>('');
const activeTab = ref<'overview' | 'history' | 'schedule' | 'excuses'>('overview');
const toast = useToast();

const excuseForm = useForm({
    student_id: props.student.id,
    attendance_id: null as number | null,
    date: new Date().toISOString().split('T')[0],
    reason: '',
});

function submitExcuse() {
    excuseForm.post('/excuses/submit', {
        preserveScroll: true,
        onSuccess: () => {
            excuseForm.reset('reason', 'attendance_id');
            toast.success('Excuse submitted successfully!');
        },
    });
}

const subjectName = (id: number | null | undefined) => {
    if (!id) return 'N/A';
    return props.subjects.find((s) => s.id === id)?.name ?? 'Unknown';
};

const latestStatus = computed(() => {
    if (!props.todayStatuses.length) return null;
    return props.todayStatuses[props.todayStatuses.length - 1];
});

function badgeClass(status: string) {
    const s = status.toLowerCase();
    if (s === 'present') return 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-950 ring-zinc-900/20';
    if (s === 'late') return 'bg-zinc-500 text-white ring-zinc-500/20';
    if (s === 'time out') return 'bg-zinc-200 text-zinc-900 ring-zinc-300/20 border border-zinc-300 dark:bg-zinc-800 dark:text-zinc-200';
    if (s === 'absent') return 'bg-zinc-100 text-zinc-500 ring-zinc-200/50 border border-zinc-200 dark:bg-zinc-900/50 dark:text-zinc-500';
    return 'bg-zinc-500/10 text-zinc-700 ring-zinc-500/20';
}

function printCard() {
    window.print();
}

onMounted(async () => {
    qrSvg.value = await QRCode.toString(props.student.qr_token, {
        type: 'svg',
        margin: 1,
        width: 280,
        color: { dark: '#09090b', light: '#ffffff' },
    });
});
</script>

<template>
    <Head :title="`Student Portal - ${student.name}`" />

    <div class="min-h-screen bg-background text-foreground">
        <!-- Header (hidden in print) -->
        <header class="sticky top-0 z-40 border-b border-sidebar-border/30 bg-background/80 backdrop-blur-xl print:hidden">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-foreground text-background font-bold">
                        Q
                    </div>
                    <div class="min-w-0">
                        <div class="text-sm font-semibold tracking-tight">Student Self‑Service</div>
                        <div class="text-xs text-muted-foreground">QR Attendance Portal</div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Button variant="outline" class="rounded-full" @click="printCard">
                        <Printer class="mr-2 h-4 w-4" />
                        Print my card
                    </Button>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-6 py-6 print:max-w-none print:p-0">
            <div class="grid grid-cols-1 gap-6 md:grid-cols-12">
                <!-- Print card (also visible on screen) -->
                <section class="md:col-span-5 print:col-span-12">
                    <div
                        v-tilt
                        class="rounded-3xl border border-sidebar-border/50 bg-background/50 backdrop-blur-xl p-6 shadow-2xl print:border-zinc-300 print:bg-white print:shadow-none preserve-3d shadow-3d sticky top-24"
                    >
                        <div class="mb-4 flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="text-[10px] font-semibold uppercase tracking-[0.25em] text-muted-foreground print:text-zinc-500">
                                    QR Attendance
                                </div>
                                <h1 class="mt-1 line-clamp-2 text-2xl font-serif font-bold tracking-tight print:text-zinc-900">
                                    {{ student.name }}
                                </h1>
                                <div class="mt-2 text-sm text-muted-foreground print:text-zinc-600">
                                    ID: <span class="font-mono font-semibold text-foreground print:text-zinc-900">{{ student.student_number }}</span>
                                    <span v-if="student.section" class="ml-2">
                                        • Section: <span class="font-semibold text-foreground print:text-zinc-900">{{ student.section }}</span>
                                    </span>
                                </div>
                            </div>
    
                            <div
                                v-if="latestStatus"
                                class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold ring-1"
                                :class="badgeClass(latestStatus.status)"
                            >
                                {{ latestStatus.status }} • {{ latestStatus.time }}
                            </div>
                        </div>
    
                        <div class="flex items-center justify-center rounded-2xl border border-sidebar-border/50 bg-background/60 p-4 print:border-zinc-200 print:bg-white">
                            <div v-if="qrSvg" class="w-[280px]" v-html="qrSvg" />
                            <div v-else class="text-sm text-muted-foreground">Generating QR…</div>
                        </div>
    
                        <div class="mt-4 flex items-center gap-2 text-xs text-muted-foreground print:text-zinc-600">
                            <Shield class="h-4 w-4" />
                            This QR encodes your secure token used for attendance scanning.
                        </div>
                    </div>
                </section>
    
                <!-- Navigation Tabs + Content -->
                <section class="md:col-span-7 print:hidden">
                    <!-- Custom Tabs -->
                    <div class="mb-6 flex overflow-x-auto rounded-2xl bg-zinc-100/50 p-1 dark:bg-zinc-900/50 scrollbar-hide">
                        <button
                            v-for="tab in (['overview', 'history', 'schedule', 'excuses'] as const)"
                            :key="tab"
                            @click="activeTab = tab"
                            class="flex flex-1 items-center justify-center gap-2 rounded-[0.9rem] px-4 py-2 text-xs font-bold capitalize transition-all"
                            :class="activeTab === tab ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-800 dark:text-white' : 'text-zinc-500 hover:text-zinc-900 dark:hover:text-zinc-100'"
                        >
                            <LayoutGrid v-if="tab === 'overview'" class="size-3.5" />
                            <History v-if="tab === 'history'" class="size-3.5" />
                            <Calendar v-if="tab === 'schedule'" class="size-3.5" />
                            <FileText v-if="tab === 'excuses'" class="size-3.5" />
                            {{ tab }}
                        </button>
                    </div>

                    <div v-if="activeTab === 'overview'" class="space-y-6 animate-in slide-in-from-right-4 duration-500">
                        <!-- Stats Overview -->
                        <div class="grid grid-cols-2 gap-4">
                            <div class="rounded-3xl border border-sidebar-border/50 bg-background/50 backdrop-blur-xl p-5 shadow-2xl flex flex-col justify-center items-center text-center isolate">
                                <div class="text-[10px] font-bold uppercase tracking-widest text-muted-foreground mb-1">Attendance Rate</div>
                                <div class="text-4xl font-serif font-black" :class="stats.percentage >= 80 ? 'text-zinc-900 dark:text-white' : 'text-rose-600 dark:text-rose-400'">{{ stats.percentage }}<span class="text-2xl opacity-50">%</span></div>
                                <div class="mt-3 w-20 h-1.5 rounded-full bg-zinc-200 dark:bg-zinc-800 overflow-hidden relative">
                                    <div class="absolute left-0 top-0 h-full rounded-full transition-all duration-1000 ease-out" :class="stats.percentage >= 80 ? 'bg-zinc-900 dark:bg-zinc-100' : 'bg-rose-500'" :style="{ width: stats.percentage + '%' }"></div>
                                </div>
                            </div>
                            <div class="rounded-3xl border border-sidebar-border/50 bg-background/50 backdrop-blur-xl p-5 shadow-2xl flex flex-col justify-center items-center text-center relative overflow-hidden group">
                               <div class="absolute -right-6 -top-4 text-zinc-100 dark:text-zinc-800/30 group-hover:scale-110 group-hover:text-amber-50 dark:group-hover:text-amber-900/30 transition-all duration-500 pointer-events-none">
                                    <Flame class="w-32 h-32" stroke-width="1.5" />
                               </div>
                               <div class="relative z-10 w-full flex flex-col items-center">
                                    <div class="text-[10px] font-bold uppercase tracking-widest text-muted-foreground mb-1">Current Streak</div>
                                    <div class="text-4xl font-serif font-black text-zinc-900 dark:text-white flex items-center gap-1">
                                        {{ stats.streak }}
                                        <Flame v-if="stats.streak > 2" class="w-5 h-5 text-amber-500 dark:text-amber-400 animate-pulse" stroke-width="3" />
                                    </div>
                                    <div class="text-[9px] text-muted-foreground font-semibold mt-1 uppercase tracking-widest">Consecutive Scans</div>
                               </div>
                            </div>
                        </div>
    
                        <div class="rounded-3xl border border-sidebar-border/50 bg-background/50 backdrop-blur-xl p-6 shadow-2xl">
                            <div class="mb-4 flex items-center gap-2">
                                <Clock class="h-5 w-5 text-muted-foreground" />
                                <h2 class="text-lg font-serif font-semibold">Today’s scans</h2>
                            </div>
    
                            <div v-if="todayStatuses.length" class="space-y-3">
                                <div
                                    v-for="s in todayStatuses"
                                    :key="s.id"
                                    class="flex items-center justify-between rounded-2xl border border-sidebar-border/40 bg-background/60 px-4 py-3"
                                >
                                    <div class="min-w-0">
                                        <div class="text-sm font-semibold">{{ s.status }}</div>
                                        <div class="text-xs text-muted-foreground">
                                            {{ subjectName(s.subject_id ?? null) }}
                                        </div>
                                    </div>
                                    <div class="text-sm font-mono text-muted-foreground">{{ s.time }}</div>
                                </div>
                            </div>
                            <div v-else class="rounded-2xl border border-dashed border-sidebar-border/60 bg-background/40 p-8 text-center text-sm text-muted-foreground">
                                No scans recorded yet today.
                            </div>
                        </div>
                    </div>

                    <div v-if="activeTab === 'history'" class="animate-in slide-in-from-right-4 duration-500">
                        <div class="rounded-3xl border border-sidebar-border/50 bg-background/50 backdrop-blur-xl p-6 shadow-2xl">
                            <div class="mb-4 flex items-center gap-2">
                                <History class="h-5 w-5 text-muted-foreground" />
                                <h2 class="text-lg font-serif font-semibold">Recent history</h2>
                            </div>
    
                            <div v-if="history.length" class="space-y-2">
                                <div
                                    v-for="r in history"
                                    :key="r.id"
                                    class="flex items-center justify-between rounded-2xl border border-sidebar-border/40 bg-background/60 px-4 py-3"
                                >
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="rounded-full px-2 py-0.5 text-xs font-semibold ring-1"
                                                :class="badgeClass(r.status)"
                                            >
                                                {{ r.status }}
                                            </span>
                                            <span class="text-xs text-muted-foreground">
                                                {{ subjectName(r.subject_id ?? null) }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ new Date(r.scanned_at).toLocaleString() }}
                                    </div>
                                </div>
                            </div>
                            <div v-else class="rounded-2xl border border-dashed border-sidebar-border/60 bg-background/40 p-8 text-center text-sm text-muted-foreground">
                                No history yet.
                            </div>
                        </div>
                    </div>

                    <div v-if="activeTab === 'schedule'" class="animate-in slide-in-from-right-4 duration-500">
                        <div class="rounded-3xl border border-sidebar-border/50 bg-background/50 backdrop-blur-xl p-6 shadow-2xl">
                            <div class="mb-4 flex items-center gap-2">
                                <Calendar class="h-5 w-5 text-muted-foreground" />
                                <h2 class="text-lg font-serif font-semibold">Today’s schedule</h2>
                            </div>
    
                            <div v-if="todaySchedule.length" class="space-y-3">
                                <div
                                    v-for="(slot, idx) in todaySchedule"
                                    :key="idx"
                                    class="flex items-center justify-between rounded-2xl border border-sidebar-border/40 bg-background/60 px-4 py-3"
                                >
                                    <div class="min-w-0">
                                        <div class="text-sm font-semibold">{{ subjectName(slot.subject_id ?? null) }}</div>
                                        <div class="text-xs text-muted-foreground">{{ slot.day }}</div>
                                    </div>
                                    <div class="text-sm font-mono text-muted-foreground">
                                        {{ slot.start }}–{{ slot.end }}
                                    </div>
                                </div>
                            </div>
                            <div v-else class="rounded-2xl border border-dashed border-sidebar-border/60 bg-background/40 p-8 text-center text-sm text-muted-foreground">
                                No schedule configured for today.
                            </div>
                        </div>
                    </div>

                    <div v-if="activeTab === 'excuses'" class="space-y-6 animate-in slide-in-from-right-4 duration-500">
                        <!-- Excuse Form -->
                        <div class="rounded-3xl border border-sidebar-border/50 bg-background/50 backdrop-blur-xl p-6 shadow-2xl">
                            <h2 class="text-lg font-serif font-semibold mb-4 flex items-center gap-2">
                                <Send class="h-5 w-5 text-zinc-400" />
                                Submit an Excuse
                            </h2>
                            <form @submit.prevent="submitExcuse" class="space-y-4">
                                <div class="space-y-2">
                                    <Label for="date">Date</Label>
                                    <Input id="date" type="date" v-model="excuseForm.date" required class="rounded-xl" />
                                </div>
                                <div class="space-y-2">
                                    <Label for="reason">Reason</Label>
                                    <Textarea id="reason" v-model="excuseForm.reason" placeholder="Explain why you missed class..." required class="rounded-xl min-h-[100px]" />
                                </div>
                                <Button type="submit" :disabled="excuseForm.processing" class="w-full rounded-xl gap-2">
                                    <Send v-if="!excuseForm.processing" class="size-4" />
                                    <RefreshCw v-else class="size-4 animate-spin text-zinc-400" />
                                    Submit Request
                                </Button>
                            </form>
                        </div>

                        <!-- Excuses List -->
                        <div class="rounded-3xl border border-sidebar-border/50 bg-background/50 backdrop-blur-xl p-6 shadow-2xl">
                            <h2 class="text-lg font-serif font-semibold mb-4 flex items-center gap-2">
                                <ClipboardList class="h-5 w-5 text-zinc-400" />
                                My Requests
                            </h2>
                            <div v-if="excuses.length" class="space-y-3">
                                <div v-for="e in excuses" :key="e.id" class="rounded-2xl border border-sidebar-border/40 bg-background/60 p-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-xs font-bold text-zinc-400 uppercase tracking-widest">{{ e.date }}</span>
                                        <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full ring-1" :class="{
                                            'bg-emerald-50 text-emerald-600 ring-emerald-200': e.status === 'Approved',
                                            'bg-rose-50 text-rose-600 ring-rose-200': e.status === 'Rejected',
                                            'bg-zinc-50 text-zinc-600 ring-zinc-200': e.status === 'Pending'
                                        }">{{ e.status }}</span>
                                    </div>
                                    <p class="text-sm text-zinc-700 dark:text-zinc-300">{{ e.reason }}</p>
                                    <div v-if="e.teacher_notes" class="mt-3 p-2.5 rounded-xl bg-zinc-50 dark:bg-zinc-900/50 border border-zinc-100 dark:border-zinc-800 text-[11px]">
                                        <span class="block font-bold text-zinc-400 uppercase mb-1">Teacher Notes:</span>
                                        {{ e.teacher_notes }}
                                    </div>
                                </div>
                            </div>
                            <div v-else class="rounded-2xl border border-dashed border-sidebar-border/60 bg-background/40 p-8 text-center text-sm text-muted-foreground italic">
                                No excuse requests found.
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </main>

        <!-- Print-only spacing / cut hint -->
        <div class="hidden print:block" style="height: 0.25in;"></div>
    </div>
</template>
