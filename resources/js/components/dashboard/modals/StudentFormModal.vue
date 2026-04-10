<script setup lang="ts">
import { ref } from 'vue';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogClose } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Plus, Scan, X, RefreshCw } from 'lucide-vue-next';

type Props = {
    open: boolean;
    mode: 'create' | 'edit';
    submitting: boolean;
    formErrors: Record<string, string | string[]>;
    subjects: { id: number; name: string }[];
    
    // Form fields (passed as individual props or via v-model)
    name: string;
    studentNumber: string;
    email: string;
    section: string;
    selectedSubjectIds: number[];
    photoPreview: string | null;
    schedules: any[];
    
    // Helpers
    getSubjectName: (id: string | number) => string;
    formatTimeTo12h: (time?: string) => string;
};

const props = defineProps<Props>();
const emit = defineEmits([
    'update:open', 
    'update:name', 
    'update:studentNumber', 
    'update:email', 
    'update:section', 
    'update:selectedSubjectIds', 
    'toggleSubject', 
    'handlePhotoChange', 
    'submit'
]);

const photoInput = ref<HTMLInputElement | null>(null);

function triggerPhoto() {
    photoInput.value?.click();
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-2xl rounded-[2rem] border-zinc-100 dark:border-zinc-800 bg-white/80 dark:bg-black/80 backdrop-blur-2xl p-0 overflow-hidden shadow-2xl">
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
                    <Plus v-if="mode === 'create'" class="h-6 w-6" />
                    <RefreshCw v-else class="h-6 w-6" />
                </div>
                <DialogTitle class="text-2xl font-serif font-black leading-none tracking-tight text-zinc-900 dark:text-white">
                    {{ mode === 'create' ? 'Enroll New Student' : 'Update Student' }}
                </DialogTitle>
                <DialogDescription class="mt-2 text-[10px] font-bold uppercase tracking-widest text-zinc-400">
                    {{ mode === 'create' ? 'Create a new entry in your database' : 'Modify existing student information' }}
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="emit('submit')" class="flex flex-col flex-1 min-h-0 overflow-hidden">
                <div class="p-8 pt-2 space-y-6 overflow-y-auto max-h-[60dvh] custom-scrollbar">
                    <div class="grid grid-cols-1 md:grid-cols-[160px_1fr] gap-8">
                        <!-- Photo Column -->
                        <div class="flex flex-col items-center gap-4">
                            <div class="relative group cursor-pointer" @click="triggerPhoto">
                                <div class="h-32 w-32 rounded-[2.5rem] overflow-hidden border-2 border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-900 flex items-center justify-center shadow-inner transition-transform duration-500 group-hover:scale-105">
                                    <img v-if="photoPreview" :src="photoPreview" class="h-full w-full object-cover" />
                                    <div v-else class="flex flex-col items-center text-zinc-300">
                                        <Plus class="h-6 w-6 mb-1 opacity-50" />
                                        <span class="text-[10px] font-black uppercase tracking-widest leading-none">Portrait</span>
                                    </div>
                                    <div class="absolute inset-0 bg-zinc-900/40 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity">
                                        <Scan class="h-6 w-6 text-white" />
                                    </div>
                                </div>
                                <input 
                                    type="file" 
                                    ref="photoInput" 
                                    class="hidden" 
                                    accept="image/*" 
                                    @change="e => emit('handlePhotoChange', e)"
                                />
                                <div class="absolute -bottom-2 -right-2 bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 p-2 rounded-2xl shadow-xl">
                                    <Plus class="h-4 w-4" />
                                </div>
                            </div>
                            <div class="text-center">
                                <p class="text-[9px] font-black uppercase tracking-[0.2em] text-zinc-400 leading-none">Student Photo</p>
                                <p v-if="formErrors.photo" class="text-[10px] font-bold text-rose-500 mt-2">{{ Array.isArray(formErrors.photo) ? formErrors.photo[0] : formErrors.photo }}</p>
                            </div>
                        </div>

                        <!-- Fields Column -->
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-1 gap-4">
                                <div class="space-y-1.5">
                                    <label class="text-[10px] font-black uppercase tracking-widest text-zinc-400 px-1">Full Name</label>
                                    <Input :model-value="name" @update:model-value="emit('update:name', $event)" placeholder="e.g. John Doe" class="h-11 rounded-xl bg-zinc-50/50 dark:bg-zinc-900/50 text-xs font-bold border-zinc-100 dark:border-zinc-800 focus:ring-zinc-900" />
                                    <p v-if="formErrors.name" class="text-[10px] font-bold text-rose-500 px-1">{{ Array.isArray(formErrors.name) ? formErrors.name[0] : formErrors.name }}</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="space-y-1.5">
                                    <label class="text-[10px] font-black uppercase tracking-widest text-zinc-400 px-1">Student #</label>
                                    <Input :model-value="studentNumber" @update:model-value="emit('update:studentNumber', $event)" placeholder="e.g. 2026-0001" class="h-11 rounded-xl bg-zinc-50/50 dark:bg-zinc-900/50 text-xs font-bold border-zinc-100 dark:border-zinc-800 focus:ring-zinc-900" />
                                    <p v-if="formErrors.student_number" class="text-[10px] font-bold text-rose-500 px-1">{{ Array.isArray(formErrors.student_number) ? formErrors.student_number[0] : formErrors.student_number }}</p>
                                </div>
                                <div class="space-y-1.5">
                                    <label class="text-[10px] font-black uppercase tracking-widest text-zinc-400 px-1">Section</label>
                                    <Input :model-value="section" @update:model-value="emit('update:section', $event)" placeholder="e.g. BSCS-3A" class="h-11 rounded-xl bg-zinc-50/50 dark:bg-zinc-900/50 text-xs font-bold border-zinc-100 dark:border-zinc-800 focus:ring-zinc-900" />
                                    <p v-if="formErrors.section" class="text-[10px] font-bold text-rose-500 px-1">{{ Array.isArray(formErrors.section) ? formErrors.section[0] : formErrors.section }}</p>
                                </div>
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-[10px] font-black uppercase tracking-widest text-zinc-400 px-1">Email Address</label>
                                <Input :model-value="email" @update:model-value="emit('update:email', $event)" type="email" placeholder="Optional" class="h-11 rounded-xl bg-zinc-50/50 dark:bg-zinc-900/50 text-xs font-bold border-zinc-100 dark:border-zinc-800 focus:ring-zinc-900" />
                                <p v-if="formErrors.email" class="text-[10px] font-bold text-rose-500 px-1">{{ Array.isArray(formErrors.email) ? formErrors.email[0] : formErrors.email }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Subject Selection -->
                    <div class="space-y-3">
                        <label class="text-[10px] font-black uppercase tracking-widest text-zinc-400 px-1">Enrolled Subjects</label>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="subj in subjects"
                                :key="subj.id"
                                type="button"
                                @click="emit('toggleSubject', subj.id)"
                                :class="[
                                    'px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest border transition-all',
                                    selectedSubjectIds.includes(subj.id)
                                        ? 'bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 border-transparent shadow-lg'
                                        : 'bg-zinc-50 dark:bg-zinc-900/40 border-zinc-100 dark:border-zinc-800 text-zinc-500 hover:border-zinc-300'
                                ]"
                            >
                                {{ subj.name }}
                            </button>
                        </div>
                    </div>

                    <!-- Schedule Preview -->
                    <div v-if="schedules.length > 0" class="p-4 rounded-[1.5rem] bg-zinc-50/50 dark:bg-zinc-900/30 border border-zinc-100 dark:border-zinc-800/50">
                        <p class="text-[9px] font-black uppercase tracking-[0.25em] text-zinc-400 mb-4 ml-1">Live Schedule Mapping</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div 
                                v-for="(slot, i) in schedules" 
                                :key="i"
                                class="flex items-center gap-3 px-4 py-3 rounded-xl bg-white dark:bg-zinc-900 shadow-sm border border-zinc-100 dark:border-zinc-800/80"
                            >
                                <div class="h-2 w-2 rounded-full bg-zinc-900 dark:bg-zinc-100" />
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-black truncate leading-none text-zinc-900 dark:text-white">{{ getSubjectName(slot.subject_id) }}</p>
                                    <p class="text-[9px] text-zinc-500 font-bold uppercase tracking-widest mt-1.5">
                                        {{ slot.day }} · {{ formatTimeTo12h(slot.start) }} – {{ formatTimeTo12h(slot.end) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter class="p-6 bg-zinc-50/50 dark:bg-zinc-900/40 border-t border-zinc-100 dark:border-zinc-800/50 gap-3">
                    <DialogClose as-child>
                        <Button type="button" variant="outline" class="h-11 rounded-xl text-[10px] font-black uppercase tracking-widest flex-1 sm:flex-none border-zinc-200 dark:border-zinc-800">
                            Cancel
                        </Button>
                    </DialogClose>
                    <Button type="submit" :disabled="submitting" class="h-11 rounded-xl text-[10px] font-black uppercase tracking-widest px-8 bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 shadow-xl flex-1 sm:flex-none">
                        <RefreshCw v-if="submitting" class="h-4 w-4 mr-2 animate-spin" />
                        {{ mode === 'create' ? 'Complete Enrollment' : 'Save Changes' }}
                    </Button>
                </DialogFooter>
            </form>
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
