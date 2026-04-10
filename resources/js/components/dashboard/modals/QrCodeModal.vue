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
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-md rounded-[2rem] border-zinc-100 dark:border-zinc-800 bg-white/80 dark:bg-black/80 backdrop-blur-2xl p-0 overflow-hidden shadow-2xl">
            <div class="absolute top-4 right-4 z-10">
                <button
                    @click="emit('update:open', false)"
                    class="rounded-full p-2 text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>

            <DialogHeader class="p-8 pb-4 text-left">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-zinc-900 text-white dark:bg-white dark:text-zinc-900 shadow-xl">
                    <QrCode class="h-6 w-6" />
                </div>
                <DialogTitle class="text-2xl font-serif font-black leading-none tracking-tight text-zinc-900 dark:text-white">
                    Student QR Identity
                </DialogTitle>
                <DialogDescription class="mt-2 text-[10px] font-bold uppercase tracking-widest text-zinc-400">
                    Secure authentication token for scanning
                </DialogDescription>
            </DialogHeader>

            <div v-if="student" class="p-8 pt-0 space-y-6">
                <!-- Profile Snippet -->
                <div class="flex items-center gap-3 p-4 rounded-2xl bg-zinc-50/50 dark:bg-zinc-900/40 border border-zinc-100 dark:border-zinc-800/80">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-zinc-900 dark:text-white truncate">{{ student.name }}</p>
                        <p class="text-[10px] font-bold text-zinc-400 uppercase tracking-widest mt-1">
                            {{ student.student_number }} · {{ student.section || 'N/A' }}
                        </p>
                    </div>
                </div>

                <!-- QR Display -->
                <div class="relative flex flex-col items-center justify-center p-8 rounded-[2rem] bg-white dark:bg-zinc-900 border border-zinc-100 dark:border-zinc-800 shadow-inner group">
                    <div class="relative">
                        <canvas ref="localCanvasRef" class="h-48 w-48 sm:h-56 sm:w-56 drop-shadow-sm"></canvas>
                        <div class="absolute inset-0 bg-white/10 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none"></div>
                    </div>
                    
                    <p class="mt-6 text-[10px] text-center font-bold text-zinc-400 uppercase tracking-[0.2em] leading-relaxed max-w-[200px]">
                        Scan this code to record attendance instantly
                    </p>
                </div>

                <!-- Link Actions -->
                <div class="space-y-2">
                    <label class="text-[10px] font-black uppercase tracking-widest text-zinc-400 px-1">Direct Portal Link</label>
                    <div class="flex items-center gap-2 p-2 pl-4 rounded-xl bg-zinc-50/50 dark:bg-zinc-900/40 border border-zinc-100 dark:border-zinc-800/50">
                        <span class="flex-1 text-[10px] font-bold text-zinc-500 truncate">{{ studentPortalUrl(student.qr_token) }}</span>
                        <Button variant="ghost" size="sm" class="h-8 rounded-lg text-[9px] font-black uppercase tracking-widest" @click="handleCopy">
                            <component :is="copied ? Check : Copy" class="h-3 w-3 mr-1.5" :class="copied ? 'text-emerald-500' : ''" />
                            {{ copied ? 'Copied' : 'Copy' }}
                        </Button>
                    </div>
                </div>
            </div>

            <DialogFooter class="p-6 bg-zinc-50/50 dark:bg-zinc-900/40 border-t border-zinc-100 dark:border-zinc-800/50 flex-row gap-2">
                <Button variant="outline" size="sm" class="h-10 rounded-xl text-[9px] font-black uppercase tracking-widest border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 flex-1" @click="emit('regenerate')">
                    <RefreshCw class="h-3.5 w-3.5 mr-1.5" />
                    Regenerate
                </Button>
                <Button variant="outline" size="sm" class="h-10 rounded-xl text-[9px] font-black uppercase tracking-widest border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 flex-1" @click="emit('download')">
                    <Download class="h-3.5 w-3.5 mr-1.5" />
                    Download
                </Button>
                <Button variant="outline" size="sm" class="h-10 rounded-xl text-[9px] font-black uppercase tracking-widest border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 flex-1" @click="emit('print')">
                    <Printer class="h-3.5 w-3.5 mr-1.5" />
                    Print
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
