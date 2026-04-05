<script setup lang="ts">
import { ref, watch, onUnmounted, nextTick, computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import jsQR from 'jsqr';
import confetti from 'canvas-confetti';
import { CheckCircle2, AlertCircle, Scan, RotateCw } from 'lucide-vue-next';
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
    minutes_early?: number | null;
    minutes_late?: number | null;
    subject?: { id: number; name: string } | null;
} | null>(null);
const scanFeedback = ref<'success' | 'error' | null>(null);
const scanResultModalOpen = ref(false);
const isCooldownActive = ref(false);
const facingMode = ref<'user' | 'environment'>('environment');
const recentScans = ref<any[]>([]);

let scanInterval: number | null = null;
let mediaStream: MediaStream | null = null;

const subjects = computed(() => (page.props as any).subjects || []);

const scanHeadline = computed(() => {
    if (scanError.value || !lastScanResult.value) return null;
    const { status, minutes_early, minutes_late } = lastScanResult.value;

    if (status === 'Time Out') {
        return { verb: 'Timed Out', mood: 'neutral', detail: null };
    }
    if (status === 'Late') {
        const mins = minutes_late ?? 0;
        return { verb: 'Timed In', mood: 'late', detail: mins > 0 ? `${mins} min${mins !== 1 ? 's' : ''} late` : 'Running late' };
    }
    // Present
    const mins = minutes_early ?? 0;
    if (mins >= 3) {
        return { verb: 'Timed In', mood: 'early', detail: `${mins} min${mins !== 1 ? 's' : ''} early` };
    }
    return { verb: 'Timed In', mood: 'ontime', detail: 'Right on time!' };
});

function isCameraSecureContext(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.isSecureContext;
}

function getCameraErrorMessage(error?: unknown): string {
    if (!isCameraSecureContext()) {
        return 'Camera access requires HTTPS on this device. Open the app using a secure HTTPS address instead of the local IP.';
    }

    if (error instanceof DOMException) {
        switch (error.name) {
            case 'NotAllowedError':
            case 'PermissionDeniedError':
                return 'Camera permission was denied. Please allow camera access in your browser settings and try again.';
            case 'NotFoundError':
            case 'DevicesNotFoundError':
                return 'No camera was detected on this device.';
            case 'NotReadableError':
            case 'TrackStartError':
                return 'The camera is already being used by another app. Close it and try again.';
            case 'OverconstrainedError':
            case 'ConstraintNotSatisfiedError':
                return 'This device could not use the preferred camera automatically. Please try again.';
        }
    }

    return 'Unable to access camera. Please ensure permissions are granted and try again.';
}

async function requestCameraStream(): Promise<MediaStream> {
    const constraints: MediaStreamConstraints[] = [
        { video: { facingMode: { exact: facingMode.value } }, audio: false },
        { video: { facingMode: { ideal: facingMode.value } }, audio: false },
        { video: true, audio: false },
    ];

    let lastError: unknown = null;

    for (const constraint of constraints) {
        try {
            return await navigator.mediaDevices.getUserMedia(constraint);
        } catch (error) {
            lastError = error;
        }
    }

    throw lastError;
}

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
    scanError.value = null;
    stopCamera();

    if (!navigator.mediaDevices?.getUserMedia) {
        scanError.value = getCameraErrorMessage();
        return;
    }

    try {
        mediaStream = await requestCameraStream();

        if (!videoRef.value) {
            stopCamera();
            return;
        }

        videoRef.value.srcObject = mediaStream;
        await videoRef.value.play();
        startScanningLoop();
    } catch (error) {
        scanError.value = getCameraErrorMessage(error);
    }
}

async function toggleCamera() {
    facingMode.value = facingMode.value === 'environment' ? 'user' : 'environment';
    await startCamera();
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
            minutes_early: data.attendance.minutes_early ?? null,
            minutes_late: data.attendance.minutes_late ?? null,
            subject: data.attendance.subject ?? null,
        };

        scanError.value = null;
        recentScans.value.unshift({
            ...lastScanResult.value,
            id: Date.now(), // for :key
        });
        if (recentScans.value.length > 5) {
            recentScans.value.pop();
        }

        scanFeedback.value = 'success';
        scanResultModalOpen.value = true;
        
        // Refresh the page data if we are on dashboard or attendance to show the update
        router.reload({ only: ['students', 'attendanceStats'] });

        if (navigator.vibrate) {
            navigator.vibrate([100, 50, 100]);
        }

        setTimeout(() => { scanFeedback.value = null; }, 1500);
    } catch (error: any) {
        scanError.value = error.message || 'An unexpected error occurred during scanning.';
        scanFeedback.value = 'error';
        scanResultModalOpen.value = true;
        
        if (navigator.vibrate) {
            navigator.vibrate(200);
        }

        toast.error(scanError.value || 'An error occurred');
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
                <div class="relative aspect-square overflow-hidden rounded-2xl border-4 border-zinc-100 dark:border-zinc-900 bg-black shadow-2xl ring-1 ring-zinc-950/10 preserve-3d">
                    <video
                        ref="videoRef"
                        class="h-full w-full object-cover opacity-90 transition-transform duration-500"
                        :class="{ '-scale-x-100': facingMode === 'user' }"
                        playsinline
                        muted
                    ></video>

                    <!-- Camera Switch Button -->
                    <Button
                        variant="secondary"
                        size="icon"
                        class="absolute top-4 right-4 h-10 w-10 rounded-full bg-black/50 border-white/20 text-white backdrop-blur-md z-30 hover:bg-black/70 transition-all active:scale-90"
                        @click="toggleCamera"
                    >
                        <RotateCw class="size-5" />
                    </Button>

                    <!-- 3D Grid Overlay -->
                    <div class="absolute inset-0 pointer-events-none overflow-hidden opacity-20">
                        <div class="absolute inset-0 [background-image:linear-gradient(to_right,rgba(255,255,255,0.1)_1px,transparent_1px),linear-gradient(to_bottom,rgba(255,255,255,0.1)_1px,transparent_1px)] [background-size:40px_40px] [transform:perspective(500px)_rotateX(60deg)_translateY(-50%)]"></div>
                    </div>

                    <!-- Scanning Volume / Corners -->
                    <div class="absolute inset-4 pointer-events-none opacity-40 z-20">
                        <div class="absolute top-0 left-0 w-12 h-12 border-t-4 border-l-4 border-white rounded-tl-2xl shadow-[0_0_20px_rgba(255,255,255,0.8)]"></div>
                        <div class="absolute top-0 right-0 w-12 h-12 border-t-4 border-r-4 border-white rounded-tr-2xl shadow-[0_0_20px_rgba(255,255,255,0.8)]"></div>
                        <div class="absolute bottom-0 left-0 w-12 h-12 border-b-4 border-l-4 border-white rounded-bl-2xl shadow-[0_0_20px_rgba(255,255,255,0.8)]"></div>
                        <div class="absolute bottom-0 right-0 w-12 h-12 border-b-4 border-r-4 border-white rounded-br-2xl shadow-[0_0_20px_rgba(255,255,255,0.8)]"></div>
                    </div>
                    
                    <!-- Center Targeting Dot -->
                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none opacity-30 z-20">
                        <div class="size-2 rounded-full bg-white shadow-[0_0_10px_white]"></div>
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
                        <!-- Scan Line (Enhanced 3D Laser) -->
                        <div 
                            v-if="!scanFeedback"
                            class="absolute left-0 right-0 h-[4px] bg-white shadow-[0_0_50px_10px_rgba(255,255,255,1),0_0_100px_20px_rgba(255,255,255,0.5)] animate-scan-line-laser after:content-[''] after:absolute after:inset-0 after:bg-white after:h-[2px] after:top-1/2 after:-translate-y-1/2 after:blur-[1px]"
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

                <div v-if="scanError && !scanResultModalOpen" class="space-y-3">
                    <p class="text-[10px] font-black tracking-widest uppercase text-center text-white bg-zinc-900 p-3 rounded-xl border border-zinc-800 shadow-lg">
                        {{ scanError }}
                    </p>

                    <Button
                        variant="outline"
                        size="sm"
                        class="w-full rounded-xl border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-950 font-black tracking-widest uppercase"
                        @click="startCamera"
                    >
                        Retry Camera
                    </Button>
                </div>

                <DialogFooter class="sm:justify-center">
                    <Button variant="outline" size="lg" class="w-full h-14 rounded-2xl border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-950 hover:bg-zinc-900 hover:text-white dark:hover:bg-white dark:hover:text-zinc-900 font-black tracking-widest uppercase transition-all shadow-md active:scale-95" @click="handleClose">
                        Exit Scanner
                    </Button>
                </DialogFooter>

                <!-- Recent Scans History -->
                <div v-if="recentScans.length > 0" class="mt-4 pt-4 border-t border-zinc-100 dark:border-zinc-900">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="text-[10px] font-black tracking-widest uppercase text-zinc-400">Recent Activity</h4>
                        <span class="px-2 py-0.5 rounded-full bg-zinc-100 dark:bg-zinc-800 text-[9px] font-black text-zinc-500 uppercase">{{ recentScans.length }} session{{ recentScans.length > 1 ? 's' : '' }}</span>
                    </div>
                    <div class="space-y-2 max-h-[160px] overflow-y-auto pr-2 custom-scrollbar">
                        <div 
                            v-for="scan in recentScans" 
                            :key="scan.id"
                            class="flex items-center gap-3 p-3 rounded-xl bg-zinc-50 dark:bg-zinc-900/50 border border-zinc-100 dark:border-zinc-800/50 animate-in slide-in-from-bottom-2 duration-300"
                        >
                            <div 
                                class="h-8 w-8 rounded-lg flex items-center justify-center shrink-0"
                                :class="[
                                    scan.status === 'Present' ? 'bg-emerald-500/10 text-emerald-500' :
                                    scan.status === 'Late' ? 'bg-amber-500/10 text-amber-500' :
                                    'bg-zinc-500/10 text-zinc-500'
                                ]"
                            >
                                <CheckCircle2 v-if="scan.status === 'Present'" class="size-4" />
                                <Clock v-else-if="scan.status === 'Late'" class="size-4" />
                                <Scan v-else class="size-4" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-[11px] font-bold text-zinc-950 dark:text-white truncate">{{ scan.student.name }}</p>
                                <p class="text-[9px] font-medium text-zinc-500 uppercase tracking-wider">
                                    {{ scan.status }} · {{ formatTimeTo12h(new Date(scan.scanned_at).toLocaleTimeString('en-US', { hour12: false, hour: '2-digit', minute: '2-digit' })) }}
                                </p>
                            </div>
                            <Badge 
                                variant="outline" 
                                class="text-[8px] font-black h-5 px-1.5 border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-950"
                            >
                                {{ scan.subject?.name || 'N/A' }}
                            </Badge>
                        </div>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>

    <!-- Scan Result Modal -->
    <Dialog :open="scanResultModalOpen" @update:open="(val) => !val && closeResultModal()">
        <DialogContent class="max-w-xs sm:max-w-sm p-0 overflow-hidden border-0 shadow-3xl rounded-[2.5rem] bg-background">
            <div 
                class="p-8 text-center space-y-5"
                :class="scanError ? 'bg-zinc-900 text-white' : 'bg-white dark:bg-black text-zinc-950 dark:text-white'"
            >
                <!-- Icon -->
                <div 
                    class="mx-auto flex h-24 w-24 items-center justify-center rounded-[2rem] shadow-2xl transition-transform duration-500 hover:scale-110 ring-1 ring-zinc-950/10"
                    :class="[
                        scanError ? 'bg-zinc-800 text-white' : '',
                        !scanError && scanHeadline?.mood === 'early' ? 'bg-emerald-50 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400' : '',
                        !scanError && scanHeadline?.mood === 'ontime' ? 'bg-blue-50 dark:bg-blue-950 text-blue-600 dark:text-blue-400' : '',
                        !scanError && scanHeadline?.mood === 'late' ? 'bg-amber-50 dark:bg-amber-950 text-amber-600 dark:text-amber-400' : '',
                        !scanError && scanHeadline?.mood === 'neutral' ? 'bg-zinc-100 dark:bg-zinc-900 text-zinc-900 dark:text-white' : '',
                        !scanError ? 'animate-bounce' : 'animate-shake-global'
                    ]"
                >
                    <CheckCircle2 v-if="!scanError" class="h-12 w-12" />
                    <AlertCircle v-else class="h-12 w-12" />
                </div>
                
                <div class="space-y-3">
                    <!-- Smart Headline -->
                    <template v-if="!scanError && lastScanResult && scanHeadline">
                        <div class="space-y-0.5">
                            <p class="text-[10px] font-black uppercase tracking-[0.25em] opacity-40">{{ scanHeadline.verb }}</p>
                            <h3 class="text-2xl font-black tracking-tight leading-tight text-zinc-950 dark:text-white">
                                {{ lastScanResult.student.name }}
                            </h3>
                            <p class="text-xs font-bold text-zinc-400 uppercase tracking-widest">
                                {{ lastScanResult.student.student_number }}
                            </p>
                        </div>

                        <!-- Smart Timing Badge -->
                        <div class="flex flex-col items-center gap-2 pt-1">
                            <span 
                                class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-full text-[11px] font-black uppercase tracking-widest shadow-sm border"
                                :class="[
                                    scanHeadline.mood === 'early' ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/20' : '',
                                    scanHeadline.mood === 'ontime' ? 'bg-blue-500/10 text-blue-700 dark:text-blue-400 border-blue-500/20' : '',
                                    scanHeadline.mood === 'late' ? 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-500/20' : '',
                                    scanHeadline.mood === 'neutral' ? 'bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border-zinc-200 dark:border-zinc-700' : '',
                                ]"
                            >
                                <span v-if="scanHeadline.mood === 'early'">⚡</span>
                                <span v-if="scanHeadline.mood === 'ontime'">✅</span>
                                <span v-if="scanHeadline.mood === 'late'">⏰</span>
                                <span v-if="scanHeadline.mood === 'neutral'">🏁</span>
                                {{ scanHeadline.detail ?? lastScanResult.status }}
                            </span>
                            <!-- Subject Name -->
                            <p v-if="lastScanResult.subject" class="text-[10px] font-black uppercase tracking-widest text-zinc-400">
                                for {{ lastScanResult.subject.name }}
                            </p>
                            <!-- Slot Time -->
                            <p v-if="lastScanResult.slot_start" class="text-[10px] font-bold text-zinc-400 tracking-wider">
                                {{ formatTimeTo12h(lastScanResult.slot_start) }} – {{ formatTimeTo12h(lastScanResult.slot_end) }}
                            </p>
                        </div>
                    </template>

                    <!-- Error State -->
                    <template v-else>
                        <h3 class="text-3xl font-black tracking-tighter text-white">SCAN FAILED</h3>
                    </template>
                </div>

                <Button 
                    class="w-full h-14 rounded-[1.5rem] text-sm font-black tracking-[0.2em] transition-all hover:scale-[1.02] active:scale-[0.95] shadow-xl uppercase"
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
@keyframes laser-anim {
    0% { top: 0; opacity: 0; filter: blur(4px); }
    10% { opacity: 1; filter: blur(0px); }
    20% { top: 10%; }
    80% { top: 90%; }
    90% { opacity: 1; filter: blur(0px); }
    100% { top: 100%; opacity: 0; filter: blur(4px); }
}

.animate-scan-line-laser {
    animation: laser-anim 1.8s cubic-bezier(0.4, 0, 0.2, 1) infinite;
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
