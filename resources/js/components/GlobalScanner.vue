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
            colors: ['#09090b', '#27272a', '#a1a1aa', '#ffffff'],
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
        <DialogContent class="max-w-md border-zinc-200 dark:border-zinc-800 bg-white/95 dark:bg-zinc-950/95 backdrop-blur-xl shadow-2xl rounded-3xl overflow-hidden p-0">
            <DialogHeader class="p-6 pb-2">
                <DialogTitle class="text-2xl font-black tracking-tight flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-zinc-900 border border-zinc-800 dark:bg-zinc-100 dark:border-white shadow-xl">
                        <Scan class="size-6 text-white dark:text-zinc-900" />
                    </div>
                    Scan Student QR
                </DialogTitle>
            </DialogHeader>

            <div class="space-y-6 px-6 pb-6 pt-2">
                <div class="relative aspect-video overflow-hidden rounded-2xl border-4 border-zinc-100 dark:border-zinc-900 bg-black shadow-2xl ring-1 ring-zinc-950/10 preserve-3d">
                    <video
                        ref="videoRef"
                        class="h-full w-full object-cover scale-x-[-1] opacity-90"
                        playsinline
                        muted
                    ></video>

                    <!-- 3D Grid Overlay -->
                    <div class="absolute inset-0 pointer-events-none overflow-hidden opacity-20">
                        <div class="absolute inset-0 [background-image:linear-gradient(to_right,rgba(255,255,255,0.1)_1px,transparent_1px),linear-gradient(to_bottom,rgba(255,255,255,0.1)_1px,transparent_1px)] [background-size:40px_40px] [transform:perspective(500px)_rotateX(60deg)_translateY(-50%)]"></div>
                    </div>

                    <!-- Scanning Volume / Corners -->
                    <div class="absolute inset-4 pointer-events-none opacity-40">
                        <div class="absolute top-0 left-0 w-8 h-8 border-t-2 border-l-2 border-white rounded-tl-lg shadow-[0_0_15px_rgba(255,255,255,0.5)]"></div>
                        <div class="absolute top-0 right-0 w-8 h-8 border-t-2 border-r-2 border-white rounded-tr-lg shadow-[0_0_15px_rgba(255,255,255,0.5)]"></div>
                        <div class="absolute bottom-0 left-0 w-8 h-8 border-b-2 border-l-2 border-white rounded-bl-lg shadow-[0_0_15px_rgba(255,255,255,0.5)]"></div>
                        <div class="absolute bottom-0 right-0 w-8 h-8 border-b-2 border-r-2 border-white rounded-br-lg shadow-[0_0_15px_rgba(255,255,255,0.5)]"></div>
                    </div>

                    <!-- Cooldown Overlay -->
                    <div 
                        v-if="isCooldownActive"
                        class="absolute inset-0 flex items-center justify-center bg-black/80 backdrop-blur-sm z-20"
                    >
                        <div class="flex flex-col items-center gap-4">
                            <div class="h-12 w-12 animate-spin rounded-full border-4 border-white border-t-transparent shadow-[0_0_15px_rgba(255,255,255,0.3)]"></div>
                            <span class="text-xs font-black text-white tracking-[0.2em]">READY IN 2S...</span>
                        </div>
                    </div>

                    <!-- Scanner Feedback Overlay -->
                    <div 
                        v-if="scanning"
                        class="absolute inset-0 pointer-events-none transition-all duration-500 z-10"
                        :class="{
                            'bg-white/10 border-white': scanFeedback === 'success',
                            'bg-zinc-900/40 border-zinc-800': scanFeedback === 'error',
                        }"
                    >
                        <!-- Scan Line (Enhanced with brighter laser glow) -->
                        <div 
                            v-if="!scanFeedback"
                            class="absolute left-0 right-0 h-[8px] bg-gradient-to-r from-transparent via-white/40 to-transparent shadow-[0_0_40px_rgba(255,255,255,0.8)] animate-scan-line-global after:content-[''] after:absolute after:inset-0 after:bg-white after:h-[1px] after:top-1/2 after:-translate-y-1/2 after:blur-[1px]"
                        ></div>

                        <div class="absolute inset-0 flex items-center justify-center">
                            <CheckCircle2 v-if="scanFeedback === 'success'" class="h-20 w-20 text-white drop-shadow-[0_0_20px_rgba(255,255,255,0.5)] animate-in zoom-in duration-300" />
                            <AlertCircle v-if="scanFeedback === 'error'" class="h-20 w-20 text-white drop-shadow-[0_0_20px_rgba(0,0,0,0.5)] animate-shake-global" />
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl bg-zinc-100 dark:bg-zinc-900 p-4 border border-zinc-200 dark:border-zinc-800 shadow-sm transition-all hover:bg-zinc-50 dark:hover:bg-zinc-800/80">
                    <p class="text-[11px] font-bold leading-relaxed text-zinc-600 dark:text-zinc-400 uppercase tracking-wide text-center">
                        Align the student's QR code within the frame for automatic detection
                    </p>
                </div>

                <p v-if="scanError && !scanResultModalOpen" class="text-[10px] font-black tracking-widest uppercase text-center text-white bg-zinc-900 p-3 rounded-xl border border-zinc-800 shadow-lg">
                    {{ scanError }}
                </p>

                <DialogFooter class="sm:justify-center">
                    <Button variant="outline" size="lg" class="w-full h-14 rounded-2xl border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-950 hover:bg-zinc-900 hover:text-white dark:hover:bg-white dark:hover:text-zinc-900 font-black tracking-widest uppercase transition-all shadow-md active:scale-95" @click="handleClose">
                        Exit Scanner
                    </Button>
                </DialogFooter>
            </div>
        </DialogContent>
    </Dialog>

    <!-- Scan Result Modal -->
    <Dialog :open="scanResultModalOpen" @update:open="(val) => !val && closeResultModal()">
        <DialogContent class="max-w-xs sm:max-w-sm p-0 overflow-hidden border-0 shadow-3xl rounded-[2.5rem] bg-background">
            <div 
                class="p-10 text-center space-y-6"
                :class="scanError ? 'bg-zinc-900 text-white' : 'bg-white dark:bg-black text-zinc-950 dark:text-white'"
            >
                <div 
                    class="mx-auto flex h-28 w-28 items-center justify-center rounded-[2rem] shadow-2xl transition-transform duration-500 hover:scale-110 ring-1 ring-zinc-950/10"
                    :class="[
                        scanError ? 'bg-zinc-800 text-white' : 'bg-zinc-100 dark:bg-zinc-900 text-zinc-900 dark:text-white',
                        !scanError ? 'animate-bounce' : 'animate-shake-global'
                    ]"
                >
                    <CheckCircle2 v-if="!scanError" class="h-14 w-14" />
                    <AlertCircle v-else class="h-14 w-14" />
                </div>
                
                <div class="space-y-3">
                    <h3 class="text-3xl font-black tracking-tighter" :class="scanError ? 'text-white' : 'text-zinc-950 dark:text-white'">
                        {{ scanError ? 'SCAN FAILED' : 'SUCCESSFUL!' }}
                    </h3>
                    <div v-if="!scanError && lastScanResult" class="space-y-1">
                        <p class="text-xl font-black tracking-tight leading-tight">
                            {{ lastScanResult.student.name }}
                        </p>
                        <p class="text-xs font-bold text-zinc-500 dark:text-zinc-500 uppercase tracking-widest">
                            {{ lastScanResult.student.student_number }}
                        </p>
                    </div>
                    <div class="flex flex-col items-center gap-3 mt-6">
                        <span 
                            class="px-6 py-2 rounded-xl text-[10px] font-black uppercase tracking-[0.2em] shadow-lg border border-zinc-200 dark:border-zinc-800"
                            :class="[
                                scanError ? 'bg-white text-zinc-900' : 'bg-zinc-900 dark:bg-white text-white dark:text-zinc-900'
                            ]"
                        >
                            {{ scanError ? 'ERROR' : lastScanResult?.status }}
                        </span>
                        <p v-if="!scanError && lastScanResult?.slot_start" class="text-[11px] font-bold text-zinc-500 dark:text-zinc-400 tracking-wider">
                            SCHEDULED: {{ formatTimeTo12h(lastScanResult.slot_start) }} – {{ formatTimeTo12h(lastScanResult.slot_end) }}
                        </p>
                    </div>
                </div>

                <Button 
                    class="w-full h-16 rounded-[1.5rem] text-sm font-black tracking-[0.2em] transition-all hover:scale-[1.02] active:scale-[0.95] shadow-xl uppercase"
                    :variant="scanError ? 'secondary' : 'default'"
                    @click="closeResultModal"
                >
                    Continue
                </Button>
                
                <p v-if="!scanError && lastScanResult" class="text-[9px] font-bold text-zinc-400 dark:text-zinc-600 uppercase tracking-widest">
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
    animation: shake-anim 0.4s cubic-bezier(.36,.07,.19,.97) both;
}

.animate-in {
    animation: zoom-in 0.3s ease-out;
}

@keyframes zoom-in {
    from { transform: scale(0.5); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}
</style>
