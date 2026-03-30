<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import gsap from 'gsap';
import { 
    MessageCircle, 
    Check, 
    X, 
    Clock, 
    User, 
    Calendar, 
    FileText, 
    ChevronRight,
    Search,
    Filter,
    ClipboardList,
    AlertCircle
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import { 
    Dialog, 
    DialogContent, 
    DialogDescription, 
    DialogFooter, 
    DialogHeader, 
    DialogTitle 
} from '@/components/ui/dialog';
import { useToast } from '@/composables/useToast';

type ExcuseItem = {
    id: number;
    student_id: number;
    date: string;
    reason: string;
    status: string;
    teacher_notes: string | null;
    created_at: string;
    student: { 
        id: number; 
        name: string; 
        student_number: string; 
        section: string | null; 
        photo: string | null 
    } | null;
};

type Paginator = {
    data: ExcuseItem[];
    current_page: number;
    last_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

const props = defineProps<{
    excuses: Paginator;
}>();

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Excuses', href: '/excuses' },
];

const toast = useToast();
const selectedExcuse = ref<ExcuseItem | null>(null);
const isReviewOpen = ref(false);
const activeTab = ref<'all' | 'pending' | 'approved' | 'rejected'>('all');

const reviewForm = useForm({
    status: 'approved' as 'approved' | 'rejected',
    teacher_notes: '',
});

const filteredExcuses = computed(() => {
    if (activeTab.value === 'all') return props.excuses.data;
    return props.excuses.data.filter(e => e.status.toLowerCase() === activeTab.value);
});

function openReview(excuse: ExcuseItem) {
    selectedExcuse.value = excuse;
    reviewForm.status = 'approved';
    reviewForm.teacher_notes = excuse.teacher_notes ?? '';
    isReviewOpen.value = true;
}

function submitReview() {
    if (!selectedExcuse.value) return;

    reviewForm.put(`/excuses/${selectedExcuse.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            isReviewOpen.value = false;
            selectedExcuse.value = null;
            toast.success('Excuse updated successfully');
        },
    });
}

function statusBadge(status: string) {
    const s = status.toLowerCase();
    if (s === 'approved') return 'bg-emerald-50 text-emerald-600 ring-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-400 dark:ring-emerald-800';
    if (s === 'rejected') return 'bg-rose-50 text-rose-600 ring-rose-200 dark:bg-rose-900/20 dark:text-rose-400 dark:ring-rose-800';
    return 'bg-amber-50 text-amber-600 ring-amber-200 dark:bg-amber-900/20 dark:text-amber-400 dark:ring-amber-800';
}

onMounted(() => {
    gsap.from('[data-excuse-card]', {
        opacity: 0,
        y: 20,
        stagger: 0.1,
        duration: 0.6,
        ease: 'power3.out',
    });
});
</script>

<template>
    <Head title="Excuse Requests" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-4 sm:p-6 lg:p-8 max-w-6xl mx-auto space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-700">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
                <div class="space-y-1">
                    <p class="text-[10px] font-black uppercase tracking-[0.3em] text-muted-foreground leading-none">Management</p>
                    <h1 class="text-4xl font-serif font-black tracking-tight flex items-center gap-3">
                        Excuse <span class="text-muted-foreground italic font-medium">Requests</span>
                        <div class="h-10 w-10 flex items-center justify-center rounded-2xl bg-zinc-100 dark:bg-zinc-900 text-zinc-900 dark:text-white">
                            <ClipboardList class="size-5" />
                        </div>
                    </h1>
                    <p class="text-sm text-muted-foreground">Review and process student attendance excuse applications.</p>
                </div>

                <!-- Tabs -->
                <div class="flex bg-muted/50 p-1 rounded-2xl ring-1 ring-border/50 backdrop-blur-sm self-start sm:self-auto overflow-x-auto scrollbar-hide">
                    <button 
                        v-for="tab in ['all', 'pending', 'approved', 'rejected'] as const" 
                        :key="tab"
                        @click="activeTab = tab"
                        class="px-4 py-2 text-xs font-bold capitalize transition-all rounded-xl whitespace-nowrap"
                        :class="activeTab === tab ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                    >
                        {{ tab }}
                        <span v-if="tab === 'pending'" class="ml-1.5 size-1.5 rounded-full bg-amber-500 inline-block animate-pulse"></span>
                    </button>
                </div>
            </div>

            <!-- Content -->
            <div v-if="filteredExcuses.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div
                    v-for="excuse in filteredExcuses"
                    :key="excuse.id"
                    data-excuse-card
                    class="group relative rounded-3xl border border-border/50 bg-background/50 backdrop-blur-xl p-6 shadow-xl transition-all hover:shadow-2xl hover:border-border/80 flex flex-col h-full"
                >
                    <!-- Status Badge -->
                    <div class="absolute top-6 right-6">
                        <span 
                            class="px-2.5 py-1 text-[9px] font-black uppercase tracking-widest rounded-full ring-1 shadow-sm"
                            :class="statusBadge(excuse.status)"
                        >
                            {{ excuse.status }}
                        </span>
                    </div>

                    <!-- Student Info -->
                    <div class="flex items-center gap-4 mb-6">
                        <div class="size-12 rounded-2xl bg-muted overflow-hidden ring-1 ring-border/50 flex-shrink-0">
                            <img v-if="excuse.student?.photo" :src="excuse.student.photo" class="size-full object-cover" />
                            <div v-else class="size-full flex items-center justify-center font-bold text-muted-foreground text-lg">
                                {{ excuse.student?.name?.[0] ?? '?' }}
                            </div>
                        </div>
                        <div class="min-w-0">
                            <h3 class="font-bold truncate group-hover:text-zinc-900 dark:group-hover:text-zinc-100 transition-colors">{{ excuse.student?.name }}</h3>
                            <p class="text-[11px] text-muted-foreground font-medium uppercase tracking-wider">{{ excuse.student?.student_number }} • {{ excuse.student?.section }}</p>
                        </div>
                    </div>

                    <!-- Excuse Content -->
                    <div class="flex-1 space-y-4">
                        <div class="bg-zinc-50/50 dark:bg-zinc-900/50 rounded-2xl p-4 border border-border/40">
                            <div class="flex items-center gap-2 mb-2">
                                <Calendar class="size-3.5 text-muted-foreground" />
                                <span class="text-[10px] font-bold uppercase tracking-widest text-muted-foreground">For: {{ new Date(excuse.date).toLocaleDateString() }}</span>
                            </div>
                            <p class="text-sm leading-relaxed line-clamp-4">{{ excuse.reason }}</p>
                        </div>

                        <div v-if="excuse.teacher_notes" class="flex gap-3 px-1">
                            <div class="flex-shrink-0 size-1 rounded-full bg-zinc-300 dark:bg-zinc-700 mt-2"></div>
                            <div class="text-[11px] text-muted-foreground italic leading-tight">
                                <span class="font-bold uppercase not-italic">Notes:</span> {{ excuse.teacher_notes }}
                            </div>
                        </div>
                    </div>

                    <!-- Footer / Action -->
                    <div class="mt-6 pt-4 border-t border-border/30 flex items-center justify-between">
                        <span class="text-[10px] text-muted-foreground font-medium uppercase tracking-tight">
                            Submitted {{ new Date(excuse.created_at).toLocaleDateString() }}
                        </span>
                        
                        <Button 
                            v-if="excuse.status === 'pending'"
                            @click="openReview(excuse)"
                            variant="outline" 
                            size="sm" 
                            class="rounded-xl h-8 text-[11px] font-bold uppercase tracking-widest group/btn"
                        >
                            Review
                            <ChevronRight class="ml-1 size-3 transition-transform group-hover/btn:translate-x-0.5" />
                        </Button>
                        <Button
                            v-else
                            @click="openReview(excuse)"
                            variant="ghost"
                            size="sm"
                            class="rounded-xl h-8 text-[10px] font-bold uppercase tracking-widest opacity-50 hover:opacity-100"
                        >
                            See History
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="flex flex-col items-center justify-center py-24 text-center space-y-4 animate-in fade-in duration-500">
                <div class="size-20 bg-muted/50 rounded-full flex items-center justify-center">
                    <AlertCircle class="size-10 text-muted-foreground/30" stroke-width="1.5" />
                </div>
                <div class="space-y-1">
                    <h3 class="text-lg font-bold">No excuses found</h3>
                    <p class="text-sm text-muted-foreground max-w-xs">There are no {{ activeTab === 'all' ? '' : activeTab }} excuse requests to show at the moment.</p>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="excuses.links.length > 3" class="flex justify-center pt-8">
                <nav class="flex items-center gap-1">
                    <Link
                        v-for="(link, i) in excuses.links"
                        :key="i"
                        :href="link.url ?? '#'"
                        class="px-3 py-1.5 text-xs font-bold rounded-xl transition-all"
                        :class="[
                            link.active ? 'bg-background shadow-sm ring-1 ring-border/50' : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground',
                            !link.url && 'opacity-30 cursor-not-allowed'
                        ]"
                        v-html="link.label"
                    />
                </nav>
            </div>
        </div>

        <!-- Review Modal -->
        <Dialog :open="isReviewOpen" @update:open="isReviewOpen = $event">
            <DialogContent class="sm:max-w-md rounded-3xl overflow-hidden p-0 border-none shadow-3d">
                <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-zinc-200 via-zinc-900 to-zinc-200 dark:from-zinc-800 dark:via-zinc-100 dark:to-zinc-800"></div>
                
                <div class="px-6 pt-8 pb-6 bg-background">
                    <DialogHeader class="text-left space-y-1">
                        <DialogTitle class="text-2xl font-serif font-black tracking-tight">Review Excuse</DialogTitle>
                        <DialogDescription class="text-xs font-medium uppercase tracking-widest text-muted-foreground">
                            For {{ selectedExcuse?.student?.name }}
                        </DialogDescription>
                    </DialogHeader>

                    <div class="mt-6 space-y-6">
                        <!-- Student Context -->
                        <div class="bg-muted/30 rounded-2xl p-4 space-y-3">
                            <div class="flex items-center gap-2">
                                <Calendar class="size-3.5 text-muted-foreground" />
                                <span class="text-[10px] font-bold uppercase tracking-widest">Date: {{ selectedExcuse?.date ? new Date(selectedExcuse.date).toLocaleDateString() : '' }}</span>
                            </div>
                            <p class="text-sm leading-relaxed text-zinc-600 dark:text-zinc-400 italic">
                                "{{ selectedExcuse?.reason }}"
                            </p>
                        </div>

                        <!-- Form -->
                        <div class="space-y-4">
                            <div class="space-y-2">
                                <Label class="text-[10px] font-black uppercase tracking-widest text-muted-foreground p-1">Status Decision</Label>
                                <div class="grid grid-cols-2 gap-3 p-1 bg-muted/50 rounded-2xl">
                                    <button 
                                        type="button"
                                        @click="reviewForm.status = 'approved'"
                                        class="flex items-center justify-center gap-2 py-2.5 rounded-xl text-xs font-bold transition-all"
                                        :class="reviewForm.status === 'approved' ? 'bg-background shadow-sm ring-1 ring-emerald-200 text-emerald-600 dark:ring-emerald-800 dark:text-emerald-400' : 'text-muted-foreground opacity-50'"
                                    >
                                        <Check class="size-3.5" />
                                        Approve
                                    </button>
                                    <button 
                                        type="button"
                                        @click="reviewForm.status = 'rejected'"
                                        class="flex items-center justify-center gap-2 py-2.5 rounded-xl text-xs font-bold transition-all"
                                        :class="reviewForm.status === 'rejected' ? 'bg-background shadow-sm ring-1 ring-rose-200 text-rose-600 dark:ring-rose-800 dark:text-rose-400' : 'text-muted-foreground opacity-50'"
                                    >
                                        <X class="size-3.5" />
                                        Reject
                                    </button>
                                </div>
                            </div>

                            <div class="space-y-2 px-1">
                                <Label for="notes" class="text-[10px] font-black uppercase tracking-widest text-muted-foreground">Teacher Notes (Optional)</Label>
                                <Textarea 
                                    id="notes" 
                                    v-model="reviewForm.teacher_notes"
                                    placeholder="Explain your decision to the student..." 
                                    class="rounded-2xl bg-muted/20 min-h-[100px] border-none ring-1 ring-border/50 focus:ring-zinc-950 dark:focus:ring-zinc-50"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter class="px-6 py-4 bg-muted/30 flex items-center gap-3">
                    <Button variant="ghost" class="rounded-xl flex-1 font-bold text-xs uppercase" @click="isReviewOpen = false">Cancel</Button>
                    <Button 
                        @click="submitReview"
                        :disabled="reviewForm.processing"
                        class="rounded-xl flex-1 font-bold text-xs uppercase shadow-lg shadow-zinc-950/10"
                    >
                        <RefreshCw v-if="reviewForm.processing" class="size-3 animate-spin mr-2" />
                        {{ selectedExcuse?.status === 'pending' ? 'Confirm Decision' : 'Update Record' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
.shadow-3d {
    box-shadow: 
        0 4px 6px -1px rgba(0, 0, 0, 0.1),
        0 2px 4px -1px rgba(0, 0, 0, 0.06),
        0 20px 25px -5px rgba(0, 0, 0, 0.1),
        0 10px 10px -5px rgba(0, 0, 0, 0.04);
}
</style>
