<script setup lang="ts">
import { ref, watch, onUnmounted, nextTick, computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import jsQR from 'jsqr';
import confetti from 'canvas-confetti';
import { CheckCircle2, AlertCircle, Scan } from 'lucide-vue-next';
import { useScanner } from '@/composables/useScanner';
import { useToast } from '@/composables/useToast';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';

const { isOpen, close } = useScanner();
const toast = useToast();
const page = usePage();

const videoRef = ref<HTMLVideoElement | null>(null);
const scanning = ref(false);
const scanError = ref<string | null>(null);
const lastScanResult = ref<{
    student: any;
    scanned_at: string;
    status: string;
    slot_start?: string;
    slot_end?: string;
} | null>(null);
const scanFeedback = ref<'success' | 'error' | null>(null);
const scanResultModalOpen = ref(false);
const isCooldownActive = ref(false);

let scanInterval: number | null = null;
let mediaStream: MediaStream | null = null;

const subjects = computed(() => (page.props as any).subjects || []);

function getSubjectName(subjectId: string | number | null | undefined): string {
    if (!subjectId) return 'N/A';
    const subject = subjects.value.find((s: any) => s.id.toString() === subjectId.toString());
    return subject ? subject.name : 'Unknown';
}

function formatTimeTo12h(timeStr?: string) {
    if (!timeStr) return '';
    const parts = timeStr.split(':');
    if (parts.length < 2) return timeStr;
    let h = parseInt(parts[0]);
    const m = parts[1];
    const ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12;
    h = h ? h : 12;
    return `${h}:${m} ${ampm}`;
}

function formatDateTime(iso: string) {
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return iso;
    return `${d.toLocaleDateString()} ${d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', hour12: true })}`;
}

async function startCamera() {
    if (!navigator.mediaDevices?.getUserMedia) {
        scanError.value = 'Camera not supported in this browser.';
        return;
    }

    try {
        mediaStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment' },
        });
        if (!videoRef.value) return;
        videoRef.value.srcObject = mediaStream;
        await videoRef.value.play();
        startScanningLoop();
    } catch (e) {
        scanError.value = 'Unable to access camera. Please ensure permissions are granted.';
    }
}

function stopCamera() {
    if (scanInterval !== null) {
        window.clearInterval(scanInterval);
        scanInterval = null;
    }
    if (mediaStream) {
        mediaStream.getTracks().forEach((track) => track.stop());
        mediaStream = null;
    }
    scanning.value = false;
}

function startScanningLoop() {
    if (!videoRef.value) return;

    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    if (!ctx) {
        scanError.value = 'Unable to initialize scanner.';
        return;
    }

    scanning.value = true;

    scanInterval = window.setInterval(async () => {
        if (!videoRef.value || !scanning.value || isCooldownActive.value) return;
        if (videoRef.value.readyState !== videoRef.value.HAVE_ENOUGH_DATA) {
            return;
        }

        canvas.width = videoRef.value.videoWidth;
        canvas.height = videoRef.value.videoHeight;
        ctx.drawImage(videoRef.value, 0, 0, canvas.width, canvas.height);

        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        const code = jsQR(imageData.data, canvas.width, canvas.height);

        if (!code || !code.data) return;

        const token = code.data.trim();
        if (!token) return;

        handleCodeDetected(token);
    }, 400);
}

async function handleCodeDetected(token: string) {
    // Pause loop
    scanning.value = false;
    if (scanInterval !== null) {
        window.clearInterval(scanInterval);
        scanInterval = null;
    }

    try {
        const response = await window.fetch('/attendance/scan', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '',
            },
            body: JSON.stringify({ token }),
        });

        if (!response.ok) {
            const contentType = response.headers.get('content-type') || '';
            let message = `Scan failed (HTTP ${response.status}).`;
            if (contentType.includes('application/json')) {
                const err = await response.json();
                message = err?.message || message;
            }
            throw new Error(message);
        }

        const data = await response.json();
        lastScanResult.value = {
            student: data.student,
            scanned_at: data.attendance.scanned_at,
            status: data.attendance.status,
            slot_start: data.attendance.slot_start,
            slot_end: data.attendance.slot_end,
        };

        scanError.value = null;
        scanFeedback.value = 'success';
        scanResultModalOpen.value = true;
        
        // Refresh the page data if we are on dashboard or attendance to show the update
        router.reload({ only: ['students', 'attendanceStats'] });

        toast.success(`Attendance recorded for ${data.student.name}`);
        
        confetti({
            particleCount: 150,
            spread: 70,
            origin: { y: 0.6 },
            colors: ['#10b981', '#34d399', '#6ee7b7', '#ffffff'],
            zIndex: 2000
        });

        setTimeout(() => { scanFeedback.value = null; }, 1500);
    } catch (error: any) {
        scanError.value = error instanceof Error ? error.message : 'Failed to record attendance.';
        scanFeedback.value = 'error';
        scanResultModalOpen.value = true;
        toast.error(scanError.value);
        setTimeout(() => { scanFeedback.value = null; }, 1500);
    }
}

function closeResultModal() {
    scanResultModalOpen.value = false;
    if (isOpen.value && mediaStream) {
        isCooldownActive.value = true;
        setTimeout(() => {
            isCooldownActive.value = false;
            if (isOpen.value && mediaStream) {
                startScanningLoop();
            }
        }, 2000);
    }
}

watch(isOpen, async (newVal) => {
    if (newVal) {
        scanError.value = null;
        lastScanResult.value = null;
        await nextTick();
        await startCamera();
    } else {
        stopCamera();
    }
});

onUnmounted(() => {
    stopCamera();
});

function handleClose() {
    close();
}
</script>

<template>
    <Dialog :open="isOpen" @update:open="(val) => !val && handleClose()">
        <DialogContent class="max-w-md border-zinc-200 dark:border-zinc-800 bg-white/95 dark:bg-zinc-950/95 backdrop-blur-xl shadow-2xl rounded-2xl">
            <DialogHeader>
                <DialogTitle class="text-xl font-bold flex items-center gap-2">
                    <Scan class="size-5 text-emerald-500" />
                    Scan Student QR
                </DialogTitle>
            </DialogHeader>

            <div class="space-y-4 py-2">
                <div class="relative aspect-video overflow-hidden rounded-xl border-2 border-zinc-100 dark:border-zinc-800 bg-black shadow-inner">
                    <video
                        ref="videoRef"
                        class="h-full w-full object-cover scale-x-[-1]"
                        playsinline
                        muted
                    ></video>

                    <!-- Cooldown Overlay -->
                    <div 
                        v-if="isCooldownActive"
                        class="absolute inset-0 flex items-center justify-center bg-black/60 z-20"
                    >
                        <div class="flex flex-col items-center gap-3">
                            <div class="h-10 w-10 animate-spin rounded-full border-4 border-emerald-500 border-t-transparent"></div>
                            <span class="text-sm font-bold text-white tracking-wide">READY IN 2S...</span>
                        </div>
                    </div>

                    <!-- Scanner Feedback Overlay -->
                    <div 
                        v-if="scanning"
                        class="absolute inset-0 pointer-events-none transition-all duration-300 z-10"
                        :class="{
                            'bg-emerald-500/10 border-emerald-500': scanFeedback === 'success',
                            'bg-rose-500/10 border-rose-500': scanFeedback === 'error',
                        }"
                    >
                        <div 
                            v-if="!scanFeedback"
                            class="absolute left-0 right-0 h-[3px] bg-gradient-to-r from-transparent via-emerald-500 to-transparent shadow-[0_0_20px_rgba(16,185,129,0.9)] animate-scan-line-global"
                        ></div>

                        <div class="absolute inset-0 flex items-center justify-center">
                            <CheckCircle2 v-if="scanFeedback === 'success'" class="h-16 w-16 text-emerald-500 drop-shadow-lg" />
                            <AlertCircle v-if="scanFeedback === 'error'" class="h-16 w-16 text-rose-500 drop-shadow-lg" />
                        </div>
                    </div>
                </div>

                <div class="rounded-lg bg-emerald-50/50 dark:bg-emerald-500/5 p-3 border border-emerald-100/50 dark:border-emerald-500/10">
                    <p class="text-[11px] font-medium leading-relaxed text-emerald-800 dark:text-emerald-300">
                        Point your camera at the student's QR code. The scanner will automatically detect and record attendance.
                    </p>
                </div>

                <p v-if="scanError && !scanResultModalOpen" class="text-xs font-semibold text-rose-500 bg-rose-50 dark:bg-rose-500/10 p-2 rounded border border-rose-100 dark:border-rose-500/20">
                    {{ scanError }}
                </p>

                <DialogFooter class="sm:justify-center">
                    <Button variant="outline" size="lg" class="w-full rounded-xl border-zinc-200 dark:border-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-900 font-semibold" @click="handleClose">
                        Close Scanner
                    </Button>
                </DialogFooter>
            </div>
        </DialogContent>
    </Dialog>

    <!-- Scan Result Modal -->
    <Dialog :open="scanResultModalOpen" @update:open="(val) => !val && closeResultModal()">
        <DialogContent class="max-w-xs sm:max-w-sm p-0 overflow-hidden border-0 shadow-3xl rounded-3xl">
            <div 
                class="p-8 text-center space-y-5"
                :class="scanError ? 'bg-rose-50/80 dark:bg-rose-950/40 backdrop-blur-xl' : 'bg-emerald-50/80 dark:bg-emerald-950/40 backdrop-blur-xl'"
            >
                <div 
                    class="mx-auto flex h-24 w-24 items-center justify-center rounded-full shadow-lg transition-transform duration-500 hover:scale-110"
                    :class="[
                        scanError ? 'bg-rose-100/80 dark:bg-rose-900/80 text-rose-600' : 'bg-emerald-100/80 dark:bg-emerald-900/80 text-emerald-600',
                        !scanError ? 'animate-bounce' : 'animate-shake-global'
                    ]"
                >
                    <CheckCircle2 v-if="!scanError" class="h-12 w-12" />
                    <AlertCircle v-else class="h-12 w-12" />
                </div>
                
                <div class="space-y-2">
                    <h3 class="text-2xl font-black tracking-tight" :class="scanError ? 'text-rose-950 dark:text-rose-100' : 'text-emerald-950 dark:text-emerald-100'">
                        {{ scanError ? 'SCAN FAILED' : 'RECORDED!' }}
                    </h3>
                    <div v-if="!scanError && lastScanResult" class="space-y-1">
                        <p class="text-lg font-bold text-zinc-900 dark:text-white leading-tight">
                            {{ lastScanResult.student.name }}
                        </p>
                        <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">
                            {{ lastScanResult.student.student_number }}
                        </p>
                    </div>
                    <div class="flex flex-col items-center gap-2 mt-4">
                        <span 
                            class="px-4 py-1.5 rounded-full text-xs font-black uppercase tracking-widest shadow-sm"
                            :class="[
                                scanError ? 'bg-rose-500 text-white' : 'bg-zinc-900 dark:bg-white text-white dark:text-zinc-900'
                            ]"
                        >
                            {{ scanError ? 'ERROR' : lastScanResult?.status }}
                        </span>
                        <p v-if="!scanError && lastScanResult?.slot_start" class="text-xs font-bold text-emerald-700 dark:text-emerald-400">
                            {{ formatTimeTo12h(lastScanResult.slot_start) }} – {{ formatTimeTo12h(lastScanResult.slot_end) }}
                        </p>
                    </div>
                </div>

                <Button 
                    class="w-full h-14 rounded-2xl text-lg font-black transition-all hover:scale-[1.02] active:scale-[0.95] shadow-xl"
                    :variant="scanError ? 'destructive' : 'default'"
                    @click="closeResultModal"
                >
                    CONTINUE
                </Button>
                
                <p v-if="!scanError && lastScanResult" class="text-[10px] font-bold text-zinc-400 dark:text-zinc-500 uppercase tracking-tighter">
                    {{ formatDateTime(lastScanResult.scanned_at) }}
                </p>
            </div>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
@keyframes scan-line-anim {
    0% { top: 0; opacity: 0; }
    15% { opacity: 1; }
    85% { opacity: 1; }
    100% { top: 100%; opacity: 0; }
}

.animate-scan-line-global {
    animation: scan-line-anim 2.5s ease-in-out infinite;
}

@keyframes shake-anim {
    0%, 100% { transform: translateX(0); }
    15%, 45%, 75% { transform: translateX(-6px); }
    30%, 60%, 90% { transform: translateX(6px); }
}

.animate-shake-global {
    animation: shake-anim 0.6s cubic-bezier(.36,.07,.19,.97) both;
}
</style>
