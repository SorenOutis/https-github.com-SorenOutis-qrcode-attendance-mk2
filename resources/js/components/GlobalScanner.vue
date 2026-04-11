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
        
        // Refresh the page data if we are on dashboard or attendance to show the update
        router.reload({ only: ['students', 'attendanceStats'] });

        if (navigator.vibrate) {
            navigator.vibrate([100, 50, 100]);
        }

        setTimeout(() => { scanFeedback.value = null; }, 1500);

        // Hands-free auto-resume after success
        if (isOpen.value && mediaStream) {
            isCooldownActive.value = true;
            setTimeout(() => {
                isCooldownActive.value = false;
                if (isOpen.value && mediaStream) {
                    startScanningLoop();
                }
            }, 2500); // 2.5s cooldown before next student can scan
        }
    } catch (error: any) {
        scanError.value = error.message || 'An unexpected error occurred during scanning.';
        scanFeedback.value = 'error';
        
        if (navigator.vibrate) {
            navigator.vibrate(200);
        }

        toast.error(scanError.value || 'An error occurred');
        setTimeout(() => { scanFeedback.value = null; }, 1500);

        // Clear error display after 1 second
        setTimeout(() => {
            if (scanError.value) {
                scanError.value = null;
            }
        }, 1000);

        // Hands-free auto-resume after error
        if (isOpen.value && mediaStream) {
            isCooldownActive.value = true;
            setTimeout(() => {
                isCooldownActive.value = false;
                if (isOpen.value && mediaStream) {
                    startScanningLoop();
                }
            }, 2500);
        }
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
        <DialogContent class="max-w-[calc(100vw-2rem)] md:max-w-4xl border-zinc-200 dark:border-zinc-800 bg-white/95 dark:bg-zinc-950/95 backdrop-blur-xl shadow-2xl rounded-3xl overflow-hidden p-0 transition-all duration-500">
            <div class="flex flex-col md:grid md:grid-cols-2 h-full overflow-hidden">
                <!-- Left Section: Scanner -->
                <div class="h-[35vh] md:h-full flex flex-col bg-zinc-950 p-3 md:p-6 lg:p-10 relative overflow-hidden group">
                    <DialogHeader class="p-0 mb-4">
                        <DialogTitle class="text-xl md:text-2xl font-black tracking-tight flex items-center gap-3 text-white">
                            <div class="flex h-8 w-8 md:h-10 md:w-10 items-center justify-center rounded-xl bg-white/10 border border-white/20 shadow-xl">
                                <Scan class="size-5 md:size-6 text-white" />
                            </div>
                            Scan QR
                        </DialogTitle>
                    </DialogHeader>

                    <div class="relative aspect-square md:aspect-auto md:flex-1 overflow-hidden rounded-2xl border-4 border-zinc-100 dark:border-zinc-900 bg-black shadow-2xl ring-1 ring-zinc-950/10 preserve-3d">
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
                </div>

                <!-- Right Section: Persistent Details & History -->
                <div class="p-4 md:p-8 bg-zinc-50/50 dark:bg-white/[0.02] border-t md:border-t-0 md:border-l border-zinc-100 dark:border-zinc-900/50 flex flex-col min-h-0 overflow-hidden">
                    <div class="space-y-4 md:space-y-6 flex-1 overflow-y-auto pr-1 custom-scrollbar pb-4">
                        <!-- Active Scan Result (High Visibility) -->
                        <div v-if="lastScanResult && !scanError" class="animate-in zoom-in slide-in-from-top-4 duration-500">
                            <div class="rounded-2xl md:rounded-3xl bg-white dark:bg-zinc-900 p-4 md:p-6 border-2 border-zinc-100 dark:border-zinc-800 shadow-xl space-y-4 md:space-y-5 text-center relative overflow-hidden ring-1 ring-zinc-950/5">
                                <!-- Status Badge Overlay -->
                                <div class="absolute top-0 right-0 p-2 md:p-3">
                                    <span 
                                        class="px-2 md:px-3 py-0.5 md:py-1 rounded-full text-[8px] md:text-[9px] font-black uppercase tracking-widest border"
                                        :class="{
                                            'bg-emerald-500/10 text-emerald-600 border-emerald-500/20': lastScanResult.status === 'Present',
                                            'bg-amber-500/10 text-amber-600 border-amber-500/20': lastScanResult.status === 'Late',
                                            'bg-zinc-500/10 text-zinc-600 border-zinc-500/20': lastScanResult.status === 'Time Out'
                                        }"
                                    >
                                        {{ lastScanResult.status }}
                                    </span>
                                </div>

                                <!-- Profile Photo -->
                                <div class="mx-auto size-16 md:size-24 rounded-2xl md:rounded-[2rem] overflow-hidden border-2 md:border-4 border-zinc-50 dark:border-zinc-800 shadow-2xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center">
                                    <img v-if="lastScanResult.student.photo" :src="lastScanResult.student.photo" class="h-full w-full object-cover">
                                    <span v-else class="text-xl md:text-2xl font-black text-zinc-300">{{ lastScanResult.student.name.charAt(0) }}</span>
                                </div>

                                <div class="space-y-0.5 md:space-y-1">
                                    <p class="text-[8px] md:text-[10px] font-black uppercase tracking-[0.25em] text-zinc-400">Scan Successful</p>
                                    <h3 class="text-lg md:text-xl font-black tracking-tight text-zinc-900 dark:text-white leading-tight">
                                        {{ lastScanResult.student.name }}
                                    </h3>
                                    <p class="text-[9px] md:text-[11px] font-bold text-zinc-400 tracking-widest">{{ lastScanResult.student.student_number }}</p>
                                </div>

                                <div class="pt-2 flex flex-col items-center gap-1 border-t border-zinc-100 dark:border-zinc-800 mt-2">
                                    <p class="text-[8px] md:text-[9px] font-bold text-zinc-400 flex items-center gap-2">
                                        <span class="opacity-50 italic">Subject:</span> {{ lastScanResult.subject?.name || 'N/A' }} 
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Instructions (hidden when someone is scanned successfully) -->
                        <div v-if="!lastScanResult || scanError" class="rounded-2xl bg-white dark:bg-zinc-900 p-4 border border-zinc-200 dark:border-zinc-800 shadow-sm transition-all animate-in fade-in duration-500">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="size-8 rounded-lg bg-emerald-500/10 flex items-center justify-center text-emerald-600">
                                    <Scan class="size-4" />
                                </div>
                                <h4 class="text-[10px] font-black uppercase tracking-widest text-zinc-900 dark:text-white">Waiting for Scan</h4>
                            </div>
                            <p class="text-[11px] font-bold leading-relaxed text-zinc-400 uppercase tracking-wide">
                                Align the student's QR code within the frame for automatic detection.
                            </p>
                        </div>

                        <!-- Error State -->
                        <div v-if="scanError" class="animate-shake-global">
                            <div class="bg-zinc-900 p-4 rounded-2xl border border-zinc-800 shadow-2xl space-y-3">
                                <div class="flex items-center gap-2 text-rose-500">
                                    <AlertCircle class="size-4" />
                                    <span class="text-[10px] font-black uppercase tracking-[0.2em]">Scan Refused</span>
                                </div>
                                <p class="text-[11px] font-bold tracking-wide text-zinc-400 leading-relaxed uppercase">
                                    {{ scanError }}
                                </p>
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    class="w-full h-9 rounded-xl border-white/5 bg-white/10 text-white font-black tracking-widest uppercase text-[9px] hover:bg-white/20"
                                    @click="startCamera"
                                >
                                    Try Again
                                </Button>
                            </div>
                        </div>

                        <!-- Recent Scans History -->
                        <div v-if="recentScans.length > 0" class="space-y-3 pt-2">
                            <div class="flex items-center justify-between">
                                <h4 class="text-[10px] font-black tracking-widest uppercase text-zinc-400/60">Recent History</h4>
                                <span class="px-2 py-0.5 rounded-full bg-zinc-100 dark:bg-zinc-800 text-[8px] font-black text-zinc-400 uppercase">{{ recentScans.length }}</span>
                            </div>
                            <div class="space-y-2">
                                <div 
                                    v-for="scan in recentScans" 
                                    :key="scan.id"
                                    class="flex items-center gap-3 p-2.5 rounded-xl bg-white/50 dark:bg-zinc-900/30 border border-zinc-100/50 dark:border-zinc-800/30 opacity-60 hover:opacity-100 transition-opacity"
                                >
                                    <div class="size-7 rounded-lg overflow-hidden shrink-0 border border-zinc-100 dark:border-zinc-800 bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center">
                                        <img v-if="scan.student.photo" :src="scan.student.photo" class="h-full w-full object-cover">
                                        <span v-else class="text-[10px] font-black text-zinc-400">{{ scan.student.name.charAt(0) }}</span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-[10px] font-bold text-zinc-950 dark:text-white truncate">{{ scan.student.name }}</p>
                                        <p class="text-[8px] font-medium text-zinc-400 uppercase tracking-widest">
                                            {{ scan.status }} · {{ formatTimeTo12h(new Date(scan.scanned_at).toLocaleTimeString('en-US', { hour12: false, hour: '2-digit', minute: '2-digit' })) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <DialogFooter class="sm:justify-stretch pt-4 border-t border-zinc-100 dark:border-zinc-900/50">
                        <Button variant="outline" size="lg" class="w-full h-14 rounded-2xl border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-950 hover:bg-zinc-900 hover:text-white dark:hover:bg-white dark:hover:text-zinc-900 font-black tracking-widest uppercase transition-all shadow-md active:scale-95" @click="handleClose">
                            Exit Scanner
                        </Button>
                    </DialogFooter>
                </div>
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
