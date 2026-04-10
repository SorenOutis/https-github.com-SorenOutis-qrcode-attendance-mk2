<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import gsap from 'gsap';
import { Plus, BookOpen, Trash2, Edit2, Users, CalendarDays, Clock } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import TimeInput from '@/components/TimeInput.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

type Student = {
    id: number;
    name: string;
    student_number: string;
};

type Subject = {
    id: number;
    name: string;
    schedule?: Array<{
        day: string;
        start: string;
        end: string;
    }>;
    students?: Student[];
};

type PageProps = {
    subjects: Subject[];
};

const props = defineProps<PageProps>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Subjects',
        href: '/subjects',
    },
];

const createModalOpen = ref(false);
const editModalOpen = ref(false);
const name = ref('');
const editName = ref('');
const editingSubjectId = ref<number | null>(null);
const submitting = ref(false);
const formErrors = ref<Record<string, string[]>>({});
const listRef = ref<HTMLDivElement | null>(null);

const daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
const schedules = ref([{ day: 'Monday', start: '08:00', end: '10:00' }]);
const editSchedules = ref<Array<{ day: string; start: string; end: string }>>([]);

function addScheduleSlot() {
    schedules.value.push({ day: 'Monday', start: '08:00', end: '10:00' });
}

function removeScheduleSlot(index: number) {
    schedules.value.splice(index, 1);
}

function addEditScheduleSlot() {
    editSchedules.value.push({ day: 'Monday', start: '08:00', end: '10:00' });
}

function removeEditScheduleSlot(index: number) {
    editSchedules.value.splice(index, 1);
}

const studentsModalOpen = ref(false);
const selectedSubjectForStudents = ref<Subject | null>(null);

function openStudentsModal(subject: Subject) {
    selectedSubjectForStudents.value = subject;
    studentsModalOpen.value = true;
}

function closeStudentsModal() {
    studentsModalOpen.value = false;
    setTimeout(() => {
        selectedSubjectForStudents.value = null;
    }, 300);
}

const confirmModalOpen = ref(false);
const confirmTitle = ref('');
const confirmDescription = ref('');
const confirmAction = ref<(() => void) | null>(null);

function showConfirm(title: string, description: string, action: () => void) {
    confirmTitle.value = title;
    confirmDescription.value = description;
    confirmAction.value = action;
    confirmModalOpen.value = true;
}

function handleConfirm() {
    if (confirmAction.value) {
        confirmAction.value();
    }
    confirmModalOpen.value = false;
    confirmAction.value = null;
}

function formatTime(timeStr: string) {
    if (!timeStr) return '';
    const [h, m] = timeStr.split(':');
    const d = new Date();
    d.setHours(parseInt(h, 10));
    d.setMinutes(parseInt(m, 10));
    return d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
}

function openCreateModal() {
    name.value = '';
    schedules.value = [{ day: 'Monday', start: '08:00', end: '10:00' }];
    formErrors.value = {};
    createModalOpen.value = true;
}

function closeCreateModal() {
    createModalOpen.value = false;
}

async function submitSubject() {
    submitting.value = true;
    formErrors.value = {};

    router.post(
        '/subjects',
        {
            name: name.value,
            schedule: schedules.value,
        },
        {
            onError: (errors) => {
                formErrors.value = errors as any;
            },
            onSuccess: () => {
                closeCreateModal();
            },
            onFinish: () => {
                submitting.value = false;
            },
            preserveScroll: true,
        },
    );
}

function openEditModal(subject: Subject) {
    editingSubjectId.value = subject.id;
    editName.value = subject.name;
    editSchedules.value = subject.schedule ? JSON.parse(JSON.stringify(subject.schedule)) : [];
    formErrors.value = {};
    editModalOpen.value = true;
}

function closeEditModal() {
    editModalOpen.value = false;
    editingSubjectId.value = null;
}

async function submitEditSubject() {
    if (!editingSubjectId.value) return;

    submitting.value = true;
    formErrors.value = {};

    router.put(
        `/subjects/${editingSubjectId.value}`,
        {
            name: editName.value,
            schedule: editSchedules.value,
        },
        {
            onError: (errors) => {
                formErrors.value = errors as any;
            },
            onSuccess: () => {
                closeEditModal();
            },
            onFinish: () => {
                submitting.value = false;
            },
            preserveScroll: true,
        },
    );
}

function deleteSubject(id: number) {
    showConfirm(
        'Delete Subject?',
        'Are you sure you want to delete this subject? It cannot be undone.',
        () => {
            router.delete(`/subjects/${id}`, {
                preserveScroll: true,
            });
        }
    );
}

onMounted(() => {
    const header = document.querySelector('.bg-background\\/50.backdrop-blur-xl');
    if (header) {
        gsap.set(header.parentElement, { perspective: 1000 });
        gsap.from(header, {
            opacity: 0,
            y: -30,
            rotationX: 20,
            z: -50,
            duration: 1,
            ease: 'power3.out'
        });
    }

    if (!listRef.value) return;
    const cards = listRef.value.querySelectorAll<HTMLElement>('[data-subject-card]');
    
    gsap.set(listRef.value, { perspective: 1000 });
    
    gsap.from(cards, {
        opacity: 0,
        y: 50,
        rotationX: -30,
        z: -100,
        duration: 1,
        stagger: 0.1,
        ease: 'back.out(1.2)',
    });
});
</script>

<template>
    <Head title="Subjects" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
            <div class="rounded-xl sm:rounded-[2rem] border border-sidebar-border/50 bg-background/50 backdrop-blur-xl p-4 sm:p-8 shadow-md relative overflow-hidden">
                <h1 class="text-2xl font-serif font-bold text-foreground tracking-tight">
                    Subjects
                </h1>
                <p class="mt-2 text-sm text-muted-foreground/80 font-light max-w-2xl leading-relaxed">
                    Manage subjects available for student schedules. Create and configure classes to accurately track attendance.
                </p>
                <div class="mt-8 flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-10 px-5 rounded-full border-sidebar-border/50 bg-background/50 backdrop-blur-sm hover:bg-muted/50 transition-all gap-2 text-xs font-semibold tracking-wide"
                            @click="router.get('/manage-attendance')"
                        >
                            <CalendarDays class="h-3.5 w-3.5" />
                            Attendance Sheets
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-10 px-5 rounded-full border-sidebar-border/50 bg-background/50 backdrop-blur-sm hover:bg-muted/50 transition-all gap-2 text-xs font-semibold tracking-wide"
                            @click="openCreateModal"
                        >
                            <Plus class="h-3.5 w-3.5" />
                            Add Subject
                        </Button>
                    </div>
                </div>
            </div>

            <div
                v-if="props.subjects.length === 0"
                class="flex w-full items-center justify-center rounded-2xl border border-dashed border-sidebar-border/70 bg-muted/30 p-12 text-center text-sm text-muted-foreground shadow-sm backdrop-blur-sm dark:border-sidebar-border"
            >
                <div class="max-w-[300px] space-y-2">
                    <BookOpen class="h-12 w-12 mx-auto opacity-20 mb-4" />
                    <p class="font-medium text-foreground">No subjects yet</p>
                    <p>Click "Add Subject" to create one.</p>
                </div>
            </div>
            
            <div 
                v-else 
                ref="listRef" 
                class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 pb-12"
            >
                <div 
                    v-for="subject in props.subjects" 
                    :key="subject.id"
                    data-subject-card
                    class="h-full"
                >
                    <article 
                        class="group relative flex flex-col h-36 rounded-[20px] border border-sidebar-border/40 bg-background/40 p-4 shadow-sm transition-all duration-300 hover:shadow-xl hover:shadow-primary/5 hover:-translate-y-0.5 hover:border-sidebar-border overflow-hidden"
                    >
                        <BookOpen class="absolute right-[-8%] bottom-[-15%] h-32 w-32 text-foreground/[0.02] transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-12 pointer-events-none" />
                        
                        <div class="relative z-10 flex-1 flex flex-col">
                            <!-- Title Row -->
                            <div class="flex items-start justify-between min-w-0">
                                <h3 class="text-lg font-serif font-black text-foreground leading-tight line-clamp-1 break-all" :title="subject.name">
                                    {{ subject.name }}
                                </h3>
                            </div>
                            
                            <!-- Middle section: Schedules -->
                            <div class="mt-2 flex-1 relative">
                                <div v-if="subject.schedule && subject.schedule.length > 0" class="flex flex-wrap gap-1.5 relative z-10">
                                    <span 
                                        v-for="slot in subject.schedule.slice(0, 2)" 
                                        :key="slot.day + slot.start" 
                                        class="inline-flex items-center gap-1 bg-background/80 backdrop-blur-md border border-sidebar-border/50 px-2 py-0.5 rounded-md text-[9.5px] font-bold uppercase tracking-wider text-muted-foreground/90 shadow-sm"
                                    >
                                        <Clock class="w-3 h-3 text-muted-foreground/70 shrink-0" />
                                        <span>{{ slot.day.substring(0, 3) }} {{ formatTime(slot.start).toLowerCase() }}—{{ formatTime(slot.end).toLowerCase() }}</span>
                                    </span>
                                    <span 
                                        v-if="subject.schedule.length > 2" 
                                        class="inline-flex items-center justify-center bg-muted/30 border border-sidebar-border/30 px-1.5 py-0.5 rounded-md text-[9px] font-black text-muted-foreground"
                                    >
                                        +{{ subject.schedule.length - 2 }}
                                    </span>
                                </div>
                                <div v-else class="text-[10px] font-bold uppercase tracking-widest text-muted-foreground/50 italic mt-1">
                                    No schedule set
                                </div>
                            </div>
                            
                            <!-- Footer: Stats and Actions -->
                            <div class="flex items-center justify-between mt-auto border-t border-sidebar-border/30 pt-3 relative z-10">
                                <div class="flex items-center gap-2">
                                    <div class="flex items-center gap-1.5 bg-muted/40 border border-sidebar-border/40 px-2 py-1 rounded-lg">
                                        <Users class="h-3.5 w-3.5 text-muted-foreground/70" />
                                        <span class="text-[10px] font-black text-foreground">{{ subject.students?.length || 0 }}</span>
                                    </div>
                                </div>

                                <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                    <Button 
                                        size="icon-sm" 
                                        variant="secondary" 
                                        class="h-7 w-7 rounded-lg transition-all" 
                                        title="View Students"
                                        @click="openStudentsModal(subject)"
                                    >
                                        <Users class="h-3.5 w-3.5" />
                                    </Button>
                                    <Button 
                                        size="icon-sm" 
                                        variant="outline" 
                                        class="h-7 w-7 rounded-lg transition-all" 
                                        title="Edit Subject"
                                        @click="openEditModal(subject)"
                                    >
                                        <Edit2 class="h-3.5 w-3.5" />
                                    </Button>
                                    <Button 
                                        size="icon-sm" 
                                        variant="ghost" 
                                        class="h-7 w-7 rounded-lg text-destructive hover:bg-destructive/10 hover:text-destructive transition-all" 
                                        title="Remove Subject"
                                        @click="deleteSubject(subject.id)"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>
            </div>
        </div>

        <!-- Create Modal -->
        <Dialog v-model:open="createModalOpen">
            <DialogContent class="max-w-md flex flex-col max-h-[90dvh]">
                <DialogHeader>
                    <DialogTitle>Add Subject</DialogTitle>
                </DialogHeader>

                <form class="flex flex-col flex-1 min-h-0 space-y-4 pt-2" @submit.prevent="submitSubject">
                    <div class="flex-1 overflow-y-auto space-y-4 pr-0.5">
                        <div class="space-y-1.5">
                            <label class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Subject Name</label>
                            <Input v-model="name" placeholder="e.g. Mathematics" class="h-10" autofocus />
                            <p v-if="formErrors.name" class="text-xs text-destructive">
                                {{ Array.isArray(formErrors.name) ? formErrors.name[0] : formErrors.name }}
                            </p>
                        </div>

                        <div class="space-y-3 pt-2 border-t border-zinc-100 dark:border-zinc-800/50">
                            <div class="flex items-center justify-between">
                                <label class="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                    Time slots
                                </label>
                                <Button
                                    type="button"
                                    size="icon-sm"
                                    variant="outline"
                                    class="h-7 w-7"
                                    @click="addScheduleSlot"
                                >
                                    <Plus class="h-3.5 w-3.5" />
                                </Button>
                            </div>

                            <div class="space-y-3">
                                <div
                                    v-for="(slot, index) in schedules"
                                    :key="index"
                                    class="relative flex flex-col gap-2.5 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/20 p-3"
                                >
                                    <div class="flex items-center gap-2 pr-8">
                                        <Select v-model="slot.day">
                                            <SelectTrigger class="h-8 flex-1 text-xs">
                                                <SelectValue :placeholder="slot.day" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="d in daysOfWeek" :key="d" :value="d" class="text-xs">
                                                    {{ d }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div class="flex flex-col gap-1">
                                            <label class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground/60 ml-1">Start</label>
                                            <TimeInput
                                                v-model="slot.start"
                                                class="h-9"
                                            />
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <label class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground/60 ml-1">End</label>
                                            <TimeInput
                                                v-model="slot.end"
                                                class="h-9"
                                            />
                                        </div>
                                    </div>

                                    <Button
                                        v-if="schedules.length > 1"
                                        type="button"
                                        size="icon-sm"
                                        variant="ghost"
                                        class="absolute right-2 top-2 h-7 w-7 rounded-full text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                        @click="removeScheduleSlot(index)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                            
                            <div
                                v-if="Object.keys(formErrors).some(k => k.startsWith('schedule'))"
                                class="mt-2 space-y-1"
                            >
                                <p v-for="(err, key) in formErrors" :key="key" v-show="key.startsWith('schedule')" class="text-[10px] text-destructive leading-tight font-medium">
                                    • {{ Array.isArray(err) ? err[0] : err }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <DialogFooter class="mt-4 flex justify-end gap-2 pt-4 border-t border-zinc-100 dark:border-zinc-800/50">
                        <DialogClose as-child>
                            <Button type="button" variant="outline">Cancel</Button>
                        </DialogClose>
                        <Button type="submit" :disabled="submitting" class="bg-zinc-900 dark:bg-zinc-100 text-zinc-100 dark:text-zinc-900 px-6">
                            {{ submitting ? 'Saving…' : 'Save Subject' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Edit Modal -->
        <Dialog v-model:open="editModalOpen">
            <DialogContent class="max-w-md flex flex-col max-h-[90dvh]">
                <DialogHeader>
                    <DialogTitle>Edit Subject</DialogTitle>
                </DialogHeader>

                <form class="flex flex-col flex-1 min-h-0 space-y-4 pt-2" @submit.prevent="submitEditSubject">
                    <div class="flex-1 overflow-y-auto space-y-4 pr-0.5">
                        <div class="space-y-1.5">
                            <label class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Subject Name</label>
                            <Input v-model="editName" placeholder="e.g. Mathematics" class="h-10" autofocus />
                            <p v-if="formErrors.name" class="text-xs text-destructive">
                                {{ Array.isArray(formErrors.name) ? formErrors.name[0] : formErrors.name }}
                            </p>
                        </div>

                        <div class="space-y-3 pt-2 border-t border-zinc-100 dark:border-zinc-800/50">
                            <div class="flex items-center justify-between">
                                <label class="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                    Time slots
                                </label>
                                <Button
                                    type="button"
                                    size="icon-sm"
                                    variant="outline"
                                    class="h-7 w-7"
                                    @click="addEditScheduleSlot"
                                >
                                    <Plus class="h-3.5 w-3.5" />
                                </Button>
                            </div>

                            <div class="space-y-3">
                                <div
                                    v-for="(slot, index) in editSchedules"
                                    :key="index"
                                    class="relative flex flex-col gap-2.5 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/20 p-3"
                                >
                                    <div class="flex items-center gap-2 pr-8">
                                        <Select v-model="slot.day">
                                            <SelectTrigger class="h-8 flex-1 text-xs">
                                                <SelectValue :placeholder="slot.day" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="d in daysOfWeek" :key="d" :value="d" class="text-xs">
                                                    {{ d }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div class="flex flex-col gap-1">
                                            <label class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground/60 ml-1">Start</label>
                                            <TimeInput
                                                v-model="slot.start"
                                                class="h-9"
                                            />
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <label class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground/60 ml-1">End</label>
                                            <TimeInput
                                                v-model="slot.end"
                                                class="h-9"
                                            />
                                        </div>
                                    </div>

                                    <Button
                                        v-if="editSchedules.length > 1"
                                        type="button"
                                        size="icon-sm"
                                        variant="ghost"
                                        class="absolute right-2 top-2 h-7 w-7 rounded-full text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                        @click="removeEditScheduleSlot(index)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>

                            <div
                                v-if="Object.keys(formErrors).some(k => k.startsWith('schedule'))"
                                class="mt-2 space-y-1"
                            >
                                <p v-for="(err, key) in formErrors" :key="key" v-show="key.startsWith('schedule')" class="text-[10px] text-destructive leading-tight font-medium">
                                    • {{ Array.isArray(err) ? err[0] : err }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <DialogFooter class="mt-4 flex justify-end gap-2 pt-4 border-t border-zinc-100 dark:border-zinc-800/50">
                        <DialogClose as-child>
                            <Button type="button" variant="outline">Cancel</Button>
                        </DialogClose>
                        <Button type="submit" :disabled="submitting" class="bg-zinc-900 dark:bg-zinc-100 text-zinc-100 dark:text-zinc-900 px-6">
                            {{ submitting ? 'Saving…' : 'Save Changes' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Confirm Modal -->
        <Dialog v-model:open="confirmModalOpen">
            <DialogContent class="max-w-xs">
                <DialogHeader>
                    <DialogTitle>{{ confirmTitle }}</DialogTitle>
                </DialogHeader>
                <div class="py-2">
                    <p class="text-sm text-muted-foreground">{{ confirmDescription }}</p>
                </div>
                <DialogFooter class="flex justify-end gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="outline" @click="confirmModalOpen = false">Cancel</Button>
                    </DialogClose>
                    <Button type="button" variant="destructive" @click="handleConfirm">
                        Delete
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
        <!-- Students Modal -->
        <Dialog v-model:open="studentsModalOpen">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>Students enrolled in {{ selectedSubjectForStudents?.name }}</DialogTitle>
                </DialogHeader>
                <div class="py-4 space-y-4 max-h-[60vh] overflow-y-auto pr-2 custom-scrollbar">
                    <div v-if="selectedSubjectForStudents?.students?.length" class="space-y-3">
                        <div v-for="student in selectedSubjectForStudents.students" :key="student.id" class="group flex items-center justify-between p-4 rounded-2xl border border-sidebar-border/50 bg-background/50 backdrop-blur-xl shadow-sm hover:shadow-md hover:border-sidebar-border/80 transition-all duration-300">
                            <div class="flex items-center gap-4">
                                <!-- Avatar Initial -->
                                <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center shrink-0 border border-primary/20 group-hover:bg-primary/20 transition-colors shadow-inner">
                                    <span class="text-sm font-bold text-primary">
                                        {{ student.name.charAt(0).toUpperCase() }}
                                    </span>
                                </div>
                                <div class="flex flex-col gap-0.5">
                                    <span class="font-bold text-sm text-foreground tracking-tight">{{ student.name }}</span>
                                    <span class="text-xs font-medium text-muted-foreground/80 flex items-center gap-1.5">
                                        <span class="w-1.5 h-1.5 rounded-full bg-green-500/80"></span>
                                        ID: {{ student.student_number }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-8 text-muted-foreground flex flex-col items-center justify-center gap-2">
                        <Users class="w-8 h-8 opacity-20" />
                        <p class="text-sm">No students enrolled.</p>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="closeStudentsModal">Close</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
