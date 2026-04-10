<script setup lang="ts">
import { ref, computed } from 'vue';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Clock, CheckCircle2, AlertCircle, X, Pencil, QrCode, Printer, Trash2 } from 'lucide-vue-next';

type AttendanceRecord = {
    id: number;
    status: string;
    scanned_at: string;
    slot_start?: string;
    slot_end?: string;
};

type Student = {
    id: number;
    name: string;
    student_number: string;
    email?: string | null;
    section?: string | null;
    photo?: string | null;
    schedule?: { day: string; start: string; end: string; subject_id?: string | null }[];
    latest_attendance?: {
        id: number;
        status: string;
        scanned_at: string;
    } | null;
};

type Props = {
    open: boolean;
    student: Student | null;
    attendanceHistory: AttendanceRecord[];
    historyLoading: boolean;
    updatingRecordId: number | null;
    getSubjectName: (id: string | number | null | undefined) => string;
    formatTimeTo12h: (time?: string) => string;
};

const props = defineProps<Props>();
const emit = defineEmits(['update:open', 'edit', 'viewQr', 'printCard', 'delete', 'updateStatus', 'markManually']);

const historyExpanded = ref(false);

const groupedAttendanceHistory = computed(() => {
    const groups: { date: string; label: string; records: AttendanceRecord[] }[] = [];
    const seen = new Map<string, AttendanceRecord[]>();

    const list = historyExpanded.value
        ? props.attendanceHistory
        : props.attendanceHistory.slice(0, 5);

    for (const record of list) {
        const d = new Date(record.scanned_at);
        const key = d.toLocaleDateString();
        if (!seen.has(key)) {
            const isToday = key === new Date().toLocaleDateString();
            const isYesterday = key === new Date(Date.now() - 86400000).toLocaleDateString();
            const label = isToday ? 'Today' : isYesterday ? 'Yesterday' : key;
            seen.set(key, []);
            groups.push({ date: key, label, records: seen.get(key)! });
        }
        seen.get(key)!.push(record);
    }
    return groups;
});

function getAvatarGradient(name: string) {
    const colors = [
        'from-zinc-200 to-zinc-400 dark:from-zinc-700 dark:to-zinc-900',
        'from-zinc-300 to-zinc-500 dark:from-zinc-600 dark:to-zinc-800',
        'from-zinc-100 to-zinc-300 dark:from-zinc-800 dark:to-zinc-950',
        'from-zinc-400 to-zinc-600 dark:from-zinc-500 dark:to-zinc-700'
    ];
    let hash = 0;
    for (let i = 0; i < name.length; i++) {
        hash = name.charCodeAt(i) + ((hash << 5) - hash);
    }
    return colors[Math.abs(hash) % colors.length];
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

            <div v-if="student" class="p-6 space-y-6">
                <!-- Profile Header -->
                <div class="flex items-center gap-4">
                    <div v-if="student.photo" class="h-16 w-16 rounded-2xl overflow-hidden border-2 border-white dark:border-zinc-800 shadow-xl">
                        <img :src="student.photo" class="h-full w-full object-cover" />
                    </div>
                    <div v-else :class="['h-16 w-16 rounded-2xl flex items-center justify-center border-2 border-white dark:border-zinc-800 shadow-xl text-xl font-serif font-black text-white dark:text-zinc-100', getAvatarGradient(student.name)]">
                        {{ student.name.charAt(0) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <h2 class="text-xl font-serif font-black leading-none truncate text-zinc-900 dark:text-white" :title="student.name">
                            {{ student.name }}
                        </h2>
                        <p class="text-[10px] font-bold text-zinc-400 uppercase tracking-widest mt-1.5 flex items-center gap-1.5">
                            {{ student.student_number }}
                            <span v-if="student.section" class="inline-block w-1.5 h-1.5 rounded-full bg-zinc-200 dark:bg-zinc-800"></span>
                            {{ student.section }}
                        </p>
                    </div>
                </div>

                <!-- Latest Status -->
                <div class="flex items-center gap-3 p-4 rounded-2xl bg-zinc-50/50 dark:bg-zinc-900/40 border border-zinc-100 dark:border-zinc-800/80">
                    <div class="h-10 w-10 rounded-xl bg-white dark:bg-zinc-800 flex items-center justify-center shadow-sm border border-zinc-100 dark:border-zinc-800">
                        <CheckCircle2 class="h-5 w-5 text-zinc-400" />
                    </div>
                    <div class="flex-1">
                        <p class="text-[9px] font-black uppercase tracking-widest text-zinc-400 leading-none mb-1">Latest Status</p>
                        <p class="text-xs font-bold" :class="student.latest_attendance ? 'text-zinc-900 dark:text-white' : 'text-zinc-400 italic'">
                            {{ student.latest_attendance?.status || 'No record today' }}
                        </p>
                    </div>
                    <Button variant="ghost" size="sm" class="h-8 rounded-lg text-[10px] font-black uppercase tracking-widest" @click="emit('edit')">
                        <Pencil class="h-3 w-3 mr-1.5" />
                        Edit
                    </Button>
                </div>

                <!-- Schedule Section -->
                <div v-if="student.schedule?.length" class="space-y-3">
                    <h3 class="text-[10px] font-black uppercase tracking-widest text-zinc-400 px-1">Enrolled Schedule</h3>
                    <div class="grid gap-2">
                        <div v-for="(slot, i) in student.schedule" :key="i" class="flex items-center justify-between p-3 rounded-xl border border-zinc-100 dark:border-zinc-800 bg-white/50 dark:bg-zinc-900/20 hover:bg-zinc-50 dark:hover:bg-zinc-900/50 transition-colors">
                            <div class="flex flex-col min-w-0">
                                <span class="text-xs font-bold text-zinc-900 dark:text-white truncate">{{ getSubjectName(slot.subject_id) }}</span>
                                <span class="text-[10px] font-bold text-zinc-400 uppercase mt-0.5 tracking-tighter">{{ slot.day }}: {{ formatTimeTo12h(slot.start) }} – {{ formatTimeTo12h(slot.end) }}</span>
                            </div>
                            <Button variant="ghost" size="sm" class="h-7 px-2 rounded-lg text-[9px] font-black uppercase tracking-widest text-zinc-500 hover:text-zinc-900" @click="emit('markManually', slot.subject_id)">
                                <Clock class="h-3 w-3 mr-1" />
                                Mark
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Attendance History -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between px-1">
                        <h3 class="text-[10px] font-black uppercase tracking-widest text-zinc-400">Activity History</h3>
                        <button v-if="attendanceHistory.length > 5" @click="historyExpanded = !historyExpanded" class="text-[10px] font-black uppercase tracking-widest text-zinc-900 dark:text-white hover:opacity-70 transition-opacity">
                            {{ historyExpanded ? 'View Less' : `View All (${attendanceHistory.length})` }}
                        </button>
                    </div>
                    
                    <div v-if="historyLoading" class="py-10 text-center">
                        <span class="text-[10px] font-black uppercase tracking-widest text-zinc-400 animate-pulse">Loading history...</span>
                    </div>
                    <div v-else-if="attendanceHistory.length === 0" class="py-10 text-center rounded-2xl border border-dashed border-zinc-200 dark:border-zinc-800">
                        <span class="text-[10px] font-black uppercase tracking-widest text-zinc-400">No records found</span>
                    </div>
                    <div v-else class="space-y-4 max-h-64 overflow-y-auto pr-1 custom-scrollbar">
                        <div v-for="group in groupedAttendanceHistory" :key="group.date" class="space-y-2">
                            <div class="text-[9px] font-black uppercase tracking-widest text-zinc-400 bg-zinc-50/50 dark:bg-zinc-900/50 sticky top-0 py-1.5 px-3 rounded-lg backdrop-blur-sm z-10 border border-zinc-100/50 dark:border-zinc-800/50">
                                {{ group.label }}
                            </div>
                            <div class="space-y-1.5 px-1">
                                <div v-for="record in group.records" :key="record.id" class="flex items-center justify-between py-2 px-3 rounded-xl bg-zinc-50/30 dark:bg-zinc-900/20 border border-transparent hover:border-zinc-100 dark:hover:border-zinc-800 transition-colors">
                                    <div class="flex flex-col">
                                        <span class="text-[11px] font-bold text-zinc-900 dark:text-white tabular-nums">
                                            {{ new Date(record.scanned_at).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', hour12: true }) }}
                                        </span>
                                        <span v-if="record.slot_start" class="text-[9px] font-bold text-zinc-400 uppercase tracking-tighter tabular-nums mt-0.5">
                                            {{ formatTimeTo12h(record.slot_start) }} – {{ formatTimeTo12h(record.slot_end) }}
                                        </span>
                                    </div>
                                    <Select 
                                        :model-value="record.status" 
                                        @update:model-value="(val: string) => emit('updateStatus', record.id, val)"
                                        :disabled="updatingRecordId === record.id"
                                    >
                                        <SelectTrigger class="h-6 w-24 rounded-lg border-none bg-transparent hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors focus:ring-0 text-[10px] font-black uppercase tracking-widest p-0 justify-end gap-1.5">
                                            <div v-if="updatingRecordId === record.id" class="flex items-center gap-1.5">
                                                <span class="animate-pulse text-zinc-400 italic">Saving</span>
                                            </div>
                                            <SelectValue v-else>
                                                <span :class="[
                                                    'px-2 py-0.5 rounded-md inline-block',
                                                    record.status === 'Present'  ? 'text-emerald-500 bg-emerald-500/10' :
                                                    record.status === 'Late'     ? 'text-amber-500 bg-amber-500/10' :
                                                    record.status === 'Time Out' ? 'text-zinc-500 bg-zinc-500/10' :
                                                    record.status === 'Absent'   ? 'text-rose-500 bg-rose-500/10' : 'text-zinc-400'
                                                ]">
                                                    {{ record.status }}
                                                </span>
                                            </SelectValue>
                                        </SelectTrigger>
                                        <SelectContent class="rounded-xl shadow-2xl">
                                            <SelectItem value="Present" class="text-[10px] font-black uppercase tracking-widest">Present</SelectItem>
                                            <SelectItem value="Late" class="text-[10px] font-black uppercase tracking-widest">Late</SelectItem>
                                            <SelectItem value="Time Out" class="text-[10px] font-black uppercase tracking-widest">Time Out</SelectItem>
                                            <SelectItem value="Absent" class="text-[10px] font-black uppercase tracking-widest">Absent</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Actions -->
            <DialogFooter class="p-4 bg-zinc-50/50 dark:bg-zinc-900/40 border-t border-zinc-100 dark:border-zinc-800/80 gap-2 flex-row flex-wrap justify-center sm:justify-start">
                <Button variant="outline" size="sm" class="h-9 rounded-xl text-[9px] font-black uppercase tracking-widest border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900" @click="emit('viewQr')">
                    <QrCode class="h-3.5 w-3.5 mr-1.5" />
                    QR Code
                </Button>
                <Button variant="outline" size="sm" class="h-9 rounded-xl text-[9px] font-black uppercase tracking-widest border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900" @click="emit('printCard')">
                    <Printer class="h-3.5 w-3.5 mr-1.5" />
                    Print
                </Button>
                <div class="flex-1 hidden sm:block"></div>
                <Button variant="outline" size="sm" class="h-9 rounded-xl text-[9px] font-black uppercase tracking-widest text-rose-500 border-rose-100 hover:bg-rose-50 hover:text-rose-600 dark:border-rose-900/30 dark:hover:bg-rose-900/20" @click="emit('delete')">
                    <Trash2 class="h-3.5 w-3.5 mr-1.5" />
                    Delete
                </Button>
                <Button size="sm" class="h-9 sm:w-20 rounded-xl text-[9px] font-black uppercase tracking-widest bg-zinc-900 text-white dark:bg-white dark:text-zinc-900" @click="emit('update:open', false)">
                    Close
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 3px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: rgba(0, 0, 0, 0.05);
  border-radius: 10px;
}
.dark .custom-scrollbar::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.05);
}
</style>
