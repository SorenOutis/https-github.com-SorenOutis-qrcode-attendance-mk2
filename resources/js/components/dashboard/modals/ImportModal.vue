<script setup lang="ts">
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogClose } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { UserPlus, Download, X, UploadCloud } from 'lucide-vue-next';

type Props = {
    open: boolean;
    importing: boolean;
    importFile: File | null;
};

const props = defineProps<Props>();
const emit = defineEmits(['update:open', 'fileChange', 'submit']);

function onFileChange(e: Event) {
    emit('fileChange', e);
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
                    <UserPlus class="h-6 w-6" />
                </div>
                <DialogTitle class="text-2xl font-serif font-black leading-none tracking-tight text-zinc-900 dark:text-white">
                    Import Database
                </DialogTitle>
                <DialogDescription class="mt-2 text-[10px] font-bold uppercase tracking-widest text-zinc-400">
                    Bulk upload students via CSV file
                </DialogDescription>
            </DialogHeader>

            <div class="p-8 pt-0 space-y-6">
                <p class="text-xs font-bold text-zinc-500 leading-relaxed">
                    Upload a CSV file containing student information. The file should have the following headers: 
                    <span class="text-zinc-900 dark:text-zinc-300 font-mono bg-zinc-100 dark:bg-zinc-800 px-1 rounded">name</span>, 
                    <span class="text-zinc-900 dark:text-zinc-300 font-mono bg-zinc-100 dark:bg-zinc-800 px-1 rounded">student_number</span>, 
                    <span class="text-zinc-900 dark:text-zinc-300 font-mono bg-zinc-100 dark:bg-zinc-800 px-1 rounded">email</span>, 
                    <span class="text-zinc-900 dark:text-zinc-300 font-mono bg-zinc-100 dark:bg-zinc-800 px-1 rounded">section</span>.
                </p>

                <!-- Template Download -->
                <div class="flex items-center justify-between p-4 rounded-2xl bg-zinc-50/50 dark:bg-zinc-900/40 border border-zinc-100 dark:border-zinc-800/80">
                    <div class="flex items-center gap-3">
                        <div class="h-9 w-9 rounded-xl bg-white dark:bg-zinc-800 flex items-center justify-center border border-zinc-100 dark:border-zinc-800">
                            <Download class="h-4 w-4 text-zinc-400" />
                        </div>
                        <div class="flex flex-col">
                            <span class="text-[10px] font-black uppercase tracking-widest text-zinc-900 dark:text-white">Need a template?</span>
                            <span class="text-[9px] font-bold text-zinc-400 uppercase tracking-tighter mt-0.5">Download sample structure</span>
                        </div>
                    </div>
                    <Button variant="ghost" size="sm" class="h-8 rounded-lg text-[9px] font-black uppercase tracking-widest" as-child>
                        <a href="/students/sample" target="_blank">Download</a>
                    </Button>
                </div>

                <!-- File Input -->
                <div class="space-y-3">
                    <label class="text-[10px] font-black uppercase tracking-widest text-zinc-400 px-1">CSV Source File</label>
                    <div class="relative group">
                        <input 
                            type="file" 
                            accept=".csv"
                            @change="onFileChange"
                            class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                        />
                        <div class="flex flex-col items-center justify-center py-10 px-6 rounded-[2rem] border-2 border-dashed border-zinc-200 dark:border-zinc-800 bg-zinc-50/30 dark:bg-zinc-900/20 group-hover:border-zinc-400 dark:group-hover:border-zinc-600 transition-all">
                            <UploadCloud class="h-8 w-8 text-zinc-300 mb-2 group-hover:scale-110 transition-transform" />
                            <span class="text-xs font-serif font-black text-zinc-600 dark:text-zinc-400">
                                {{ importFile ? importFile.name : 'Select CSV File' }}
                            </span>
                            <span v-if="!importFile" class="text-[9px] font-bold text-zinc-400 uppercase tracking-widest mt-1">or drag and drop</span>
                        </div>
                    </div>
                </div>
            </div>

            <DialogFooter class="p-6 bg-zinc-50/50 dark:bg-zinc-900/40 border-t border-zinc-100 dark:border-zinc-800/50 gap-3">
                <DialogClose as-child>
                    <Button variant="outline" class="h-11 rounded-xl text-[10px] font-black uppercase tracking-widest flex-1 sm:flex-none border-zinc-200 dark:border-zinc-800">
                        Cancel
                    </Button>
                </DialogClose>
                <Button 
                    @click="emit('submit')"
                    :disabled="!importFile || importing"
                    class="h-11 rounded-xl text-[10px] font-black uppercase tracking-widest px-8 bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 shadow-xl flex-1 sm:flex-none"
                >
                    {{ importing ? 'Processing...' : 'Start Import' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
