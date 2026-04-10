<script setup lang="ts">
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { QrCode, Download, Printer, RefreshCw, Copy, X, Check } from 'lucide-vue-next';
import { ref, onMounted, watch } from 'vue';
import QRCode from 'qrcode';

type Student = {
    id: number;
    name: string;
    student_number: string;
    section?: string | null;
    qr_token: string;
    photo?: string | null;
};

type Props = {
    open: boolean;
    student: Student | null;
    qrCanvas: HTMLCanvasElement | null;
    studentPortalUrl: (token: string) => string;
};

const props = defineProps<Props>();
const emit = defineEmits(['update:open', 'regenerate', 'download', 'print', 'copyLink']);

const localCanvasRef = ref<HTMLCanvasElement | null>(null);
const copied = ref(false);

async function generateQr() {
    if (props.student && localCanvasRef.value) {
        try {
            await QRCode.toCanvas(localCanvasRef.value, props.student.qr_token, {
                width: 300,
                margin: 2,
                color: {
                    dark: '#000000',
                    light: '#ffffff'
                }
            });
        } catch (err) {
            console.error('QR generation failed:', err);
        }
    }
}

watch(() => props.student?.qr_token, generateQr);
watch(() => props.open, (val) => {
    if (val) setTimeout(generateQr, 100);
});

function handleCopy() {
    emit('copyLink');
    copied.value = true;
    setTimeout(() => copied.value = false, 2000);
}

function handleDownload() {
    if (!localCanvasRef.value || !props.student) return;
    const link = document.createElement('a');
    link.download = `qr-${props.student.student_number}.png`;
    link.href = localCanvasRef.value.toDataURL('image/png');
    link.click();
}

function handlePrint() {
    if (!props.student) return;
    window.open(`/students/print-cards?ids=${props.student.id}`, '_blank');
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-[calc(100vw-2rem)] sm:max-w-3xl rounded-[2.5rem] border-zinc-100 dark:border-white/5 bg-white/95 dark:bg-zinc-950/95 backdrop-blur-3xl p-0 overflow-hidden shadow-3xl">
            <div class="absolute top-6 right-6 z-50">
                <button
                    @click="emit('update:open', false)"
                    class="rounded-full p-2 text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>

            <div v-if="student" class="grid grid-cols-1 md:grid-cols-2">
                <!-- Left Section: Details -->
                <div class="p-6 sm:p-10 flex flex-col justify-between space-y-8 border-b md:border-b-0 md:border-r border-zinc-100 dark:border-white/5">
                    <div>
                        <DialogHeader class="p-0 text-left">
                            <div class="mb-5 flex h-14 w-14 items-center justify-center rounded-2xl bg-zinc-900 text-white dark:bg-white dark:text-zinc-900 shadow-2xl shadow-zinc-900/10 dark:shadow-white/5">
                                <QrCode class="h-7 w-7" />
                            </div>
                            <DialogTitle class="text-[1.75rem] font-serif font-black leading-tight tracking-tight text-zinc-900 dark:text-white mb-2">
                                Student Identity
                            </DialogTitle>
                            <div class="text-[10px] font-black uppercase tracking-[0.2em] text-emerald-500 flex items-center gap-2">
                                <span class="inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                Secure Authentication
                            </div>
                        </DialogHeader>

                        <!-- Profile Card -->
                        <div class="mt-8 flex items-center gap-4 p-5 rounded-2xl bg-zinc-50 dark:bg-white/5 border border-zinc-100 dark:border-white/5">
                            <div class="h-12 w-12 rounded-xl bg-zinc-900 dark:bg-zinc-800 flex items-center justify-center text-sm font-black text-white shrink-0 overflow-hidden shadow-lg shadow-zinc-950/20 dark:shadow-white/5">
                                <template v-if="student.photo">
                                    <img :src="student.photo" :alt="student.name" class="h-full w-full object-cover">
                                </template>
                                <template v-else>
                                    {{ student.name.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase() }}
                                </template>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-base font-bold text-zinc-900 dark:text-white truncate leading-none mb-1.5">{{ student.name }}</p>
                                <p class="text-[10px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-widest flex items-center gap-2">
                                    {{ student.student_number }} <span class="opacity-30">|</span> {{ student.section || 'General' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Link Integration -->
                    <div class="space-y-3">
                        <label class="text-[10px] font-black uppercase tracking-widest text-zinc-500 px-1">Digital Access Token</label>
                        <div class="group relative flex items-center gap-2 p-1.5 pl-4 rounded-xl bg-zinc-100/50 dark:bg-white/5 border border-zinc-200 dark:border-white/5 hover:border-zinc-300 dark:hover:border-white/10 transition-all">
                            <span class="flex-1 text-[10px] font-bold text-zinc-400 dark:text-zinc-500 truncate">{{ studentPortalUrl(student.qr_token) }}</span>
                            <Button 
                                variant="secondary" 
                                size="sm" 
                                class="h-9 rounded-lg text-[9px] font-black uppercase tracking-widest px-4 shadow-sm"
                                @click="handleCopy"
                            >
                                <component :is="copied ? Check : Copy" class="h-3 w-3 mr-2" :class="copied ? 'text-emerald-500' : ''" />
                                {{ copied ? 'Copied' : 'Copy' }}
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Right Section: QR Focus -->
                <div class="p-6 sm:p-10 bg-zinc-50/50 dark:bg-white/[0.02] flex flex-col items-center justify-center space-y-6">
                    <div class="relative group">
                        <!-- QR Outer Frame -->
                        <div class="p-4 sm:p-6 rounded-[2.5rem] bg-white dark:bg-white shadow-2xl ring-1 ring-zinc-100 dark:ring-zinc-800 transition-transform group-hover:scale-[1.02] duration-500">
                            <canvas ref="localCanvasRef" class="h-48 w-48 sm:h-56 sm:w-56 drop-shadow-sm"></canvas>
                        </div>

                        <!-- Decorative Scan Hint -->
                        <div class="absolute -bottom-3 left-1/2 -translate-x-1/2 whitespace-nowrap px-4 py-2 rounded-full bg-zinc-900 dark:bg-zinc-800 text-[9px] font-black text-white uppercase tracking-[0.25em] shadow-xl">
                            Instant Check-in
                        </div>
                        
                        <!-- Floating Circles Decoration -->
                        <div class="absolute -top-4 -right-4 h-12 w-12 rounded-full bg-gradient-to-tr from-emerald-500 to-teal-400 opacity-20 blur-xl"></div>
                        <div class="absolute -bottom-4 -left-4 h-12 w-12 rounded-full bg-gradient-to-tr from-blue-500 to-indigo-400 opacity-20 blur-xl"></div>
                    </div>

                    <p class="text-[10px] text-center font-bold text-zinc-400 dark:text-zinc-500 uppercase tracking-widest leading-relaxed max-w-[200px]">
                        Point camera here to automatically record student attendance
                    </p>
                </div>
            </div>

            <DialogFooter class="p-6 sm:p-8 bg-zinc-100/50 dark:bg-white/5 border-t border-zinc-200 dark:border-white/5 flex flex-col sm:flex-row gap-3">
                <div class="flex flex-1 gap-2">
                    <Button variant="outline" size="sm" class="h-11 rounded-[1.2rem] text-[9px] font-black uppercase tracking-widest border-zinc-200 dark:border-white/10 bg-white dark:bg-zinc-900 flex-1 hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-all" @click="emit('regenerate')">
                        <RefreshCw class="h-3.5 w-3.5 mr-2" />
                        Regenerate
                    </Button>
                    <Button variant="outline" size="sm" class="h-11 rounded-[1.2rem] text-[9px] font-black uppercase tracking-widest border-zinc-200 dark:border-white/10 bg-white dark:bg-zinc-900 flex-1 hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-all" @click="handleDownload">
                        <Download class="h-3.5 w-3.5 mr-2" />
                        Save PNG
                    </Button>
                </div>
                <Button size="sm" class="h-11 w-full sm:w-auto rounded-[1.2rem] text-[9px] font-black uppercase tracking-widest bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 px-8 hover:scale-[1.02] active:scale-[0.98] transition-all" @click="handlePrint">
                    <Printer class="h-3.5 w-3.5 mr-2" />
                    Print ID Card
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
