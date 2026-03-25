<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import gsap from 'gsap';
import { Filter, Calendar, X, Search, ArrowUpDown } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
    DialogClose,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import ratingsRoutes from '@/routes/ratings';
import type { BreadcrumbItem } from '@/types';

type Rating = {
    id: number;
    name?: string | null;
    email?: string | null;
    score: number;
    message?: string | null;
    is_public: boolean;
    created_at: string;
};

type AggregateStats = {
    average: number;
    total: number;
    distribution: Record<number, number>;
};

type PageProps = {
    ratings: Rating[];
    filters?: {
        from?: string | null;
        to?: string | null;
        sort?: string | null;
    };
    aggregateStats?: AggregateStats;
};

const props = defineProps<PageProps>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Ratings',
        href: '/ratings',
    },
];

const ratings = computed(() => props.ratings ?? []);
const searchQuery = ref('');
const sortValue = ref(props.filters?.sort ?? 'newest');

const filteredRatings = computed(() => {
    if (!searchQuery.value) return ratings.value;
    const q = searchQuery.value.toLowerCase();
    return ratings.value.filter(r =>
        (r.name && r.name.toLowerCase().includes(q)) ||
        (r.message && r.message.toLowerCase().includes(q))
    );
});

function applySort(value: string) {
    sortValue.value = value;
    router.get(
        ratingsRoutes.index.url({
            query: {
                from: from.value || undefined,
                to: to.value || undefined,
                sort: value,
            },
        }),
        {},
        { preserveScroll: true, preserveState: true },
    );
}

function distributionPercent(score: number): number {
    const total = props.aggregateStats?.total ?? 0;
    if (total === 0) return 0;
    return Math.round(((props.aggregateStats?.distribution?.[score] ?? 0) / total) * 100);
}

const listRef = ref<HTMLDivElement | null>(null);
const editingId = ref<number | null>(null);
const editScore = ref(5);
const editMessage = ref('');
const editIsPublic = ref(true);
const saving = ref(false);
const from = ref(props.filters?.from ?? '');
const to = ref(props.filters?.to ?? '');
const filterModalOpen = ref(false);

function clearFilters() {
    from.value = '';
    to.value = '';
    applyFilter();
    filterModalOpen.value = false;
}

function startEdit(rating: Rating) {
    editingId.value = rating.id;
    editScore.value = rating.score;
    editMessage.value = rating.message ?? '';
    editIsPublic.value = rating.is_public;
}

function cancelEdit() {
    editingId.value = null;
    editMessage.value = '';
}

function setEditScore(value: number) {
    editScore.value = value;
}

function saveEdit(rating: Rating) {
    if (!editingId.value) return;
    saving.value = true;

    router.put(
        ratingsRoutes.update.url({ rating: rating.id }),
        {
            score: editScore.value,
            message: editMessage.value,
            is_public: editIsPublic.value,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                saving.value = false;
                editingId.value = null;
            },
        },
    );
}

function remove(rating: Rating) {
    if (!confirm('Delete this rating?')) return;

    router.delete(ratingsRoutes.destroy.url({ rating: rating.id }), {
        preserveScroll: true,
    });
}

function applyFilter() {
    router.get(
        ratingsRoutes.index.url({
            query: {
                from: from.value || undefined,
                to: to.value || undefined,
                sort: sortValue.value || undefined,
            },
        }),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                filterModalOpen.value = false;
            }
        },
    );
}

function starsArray(count: number) {
    return Array.from({ length: 5 }, (_, i) => i < count);
}

onMounted(() => {
    // 1. Initial Page Heading Animation with 3D
    const header = document.querySelector('.bg-white.dark\\:bg-black');
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
    const cards = listRef.value.querySelectorAll<HTMLElement>('[data-rating-card]');
    
    // Wrap cards list with perspective
    gsap.set(listRef.value, { perspective: 1000 });
    
    // 2. Staggered 3D Entry for Rating Cards
    gsap.from(cards, {
        opacity: 0,
        y: 50,
        rotationX: -30,
        z: -100,
        duration: 1,
        stagger: 0.1,
        ease: 'back.out(1.2)',
    });

    // Hover interactions removed as per request

    // 4. Button Press Micro-interactions with 3D Depth
    const buttons = document.querySelectorAll('button');
    buttons.forEach((btn) => {
        gsap.set(btn, { transformStyle: "preserve-3d" });
        btn.addEventListener('mousedown', () => {
            gsap.to(btn, { scale: 0.95, z: -5, duration: 0.1, ease: 'power1.out' });
        });
        btn.addEventListener('mouseup', () => {
            gsap.to(btn, { scale: 1, z: 0, duration: 0.3, ease: 'bounce.out' });
        });
        btn.addEventListener('mouseleave', () => {
            gsap.to(btn, { scale: 1, z: 0, duration: 0.3, ease: 'power1.out' });
        });
    });
});
</script>

<template>
    <Head title="Ratings" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-hidden p-3 sm:p-4 pb-20 md:pb-4">
            <div class="rounded-xl sm:rounded-[2rem] border border-sidebar-border/50 bg-background/50 backdrop-blur-xl p-4 sm:p-8 shadow-2xl relative overflow-hidden group">
                <div class="absolute -right-16 -top-16 w-64 h-64 bg-primary/5 rounded-full blur-3xl pointer-events-none group-hover:bg-primary/10 transition-colors duration-700"></div>
                
                <div class="flex flex-col lg:flex-row lg:items-start gap-6 lg:gap-10">
                    <!-- Left: Title + Controls -->
                    <div class="flex-1 min-w-0">
                        <h1 class="text-2xl font-serif font-bold text-foreground tracking-tight">
                            System Ratings
                        </h1>
                        <p class="mt-2 text-sm text-muted-foreground/80 font-light max-w-2xl leading-relaxed">
                            Monitor satisfaction levels. Every star reflects a touchpoint in our seamless attendance journey.
                        </p>
                    </div>

                    <!-- Right: Aggregate Stats -->
                    <div v-if="aggregateStats" class="flex flex-col items-center gap-2 shrink-0 p-3 sm:p-4 rounded-xl sm:rounded-2xl bg-background/40 border border-sidebar-border/30 min-w-[200px]">
                        <div class="flex items-baseline gap-2">
                            <span class="text-4xl font-serif font-bold text-foreground tabular-nums">{{ aggregateStats.average || '0' }}</span>
                            <span class="text-amber-400 text-2xl">★</span>
                        </div>
                        <p class="text-[10px] uppercase tracking-widest text-muted-foreground font-semibold">{{ aggregateStats.total }} total ratings</p>

                        <!-- Distribution Bars -->
                        <div class="w-full space-y-1.5 mt-3">
                            <div v-for="star in [5, 4, 3, 2, 1]" :key="star" class="flex items-center gap-2">
                                <span class="text-[10px] font-bold text-muted-foreground w-4 text-right tabular-nums">{{ star }}</span>
                                <span class="text-amber-400 text-[10px]">★</span>
                                <div class="flex-1 h-2 rounded-full bg-muted/50 overflow-hidden">
                                    <div 
                                        class="h-full rounded-full bg-foreground/70 transition-all duration-700"
                                        :style="{ width: distributionPercent(star) + '%' }"
                                    ></div>
                                </div>
                                <span class="text-[9px] font-mono text-muted-foreground/60 w-7 text-right tabular-nums">{{ distributionPercent(star) }}%</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex flex-col sm:flex-row flex-wrap items-start sm:items-center gap-3">
                    <!-- Search -->
                    <div class="relative w-full sm:w-auto sm:min-w-[240px]">
                        <Search class="absolute left-3.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-muted-foreground" />
                        <Input
                            v-model="searchQuery"
                            placeholder="Search ratings..."
                            class="pl-9 h-10 rounded-full border-sidebar-border/50 bg-background/50 backdrop-blur-sm text-sm"
                        />
                    </div>

                    <!-- Sort -->
                    <div class="flex items-center gap-2">
                        <select
                            :value="sortValue"
                            @change="applySort(($event.target as HTMLSelectElement).value)"
                            class="h-10 px-4 rounded-full border border-sidebar-border/50 bg-background/50 backdrop-blur-sm text-xs font-semibold text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 transition-all cursor-pointer appearance-none pr-8"
                            style="background-image: url('data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2212%22 height=%2212%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22currentColor%22 stroke-width=%222%22><path d=%22m7 15 5 5 5-5%22/><path d=%22m7 9 5-5 5 5%22/></svg>'); background-repeat: no-repeat; background-position: right 12px center;"
                        >
                            <option value="newest">Newest First</option>
                            <option value="oldest">Oldest First</option>
                            <option value="highest">Highest Rated</option>
                            <option value="lowest">Lowest Rated</option>
                        </select>
                    </div>

                    <!-- Filter -->
                    <Button
                        variant="outline"
                        size="sm"
                        class="h-10 px-5 rounded-full border-sidebar-border/50 bg-background/50 backdrop-blur-sm hover:bg-muted/50 transition-all gap-2 text-xs font-semibold tracking-wide"
                        @click="filterModalOpen = true"
                    >
                        <Filter class="h-3.5 w-3.5" />
                        Filter
                    </Button>

                    <div v-if="from || to" class="flex items-center gap-2 rounded-full border border-sidebar-border bg-muted/30 px-4 py-2 text-[11px] font-medium tracking-wide animate-in fade-in zoom-in duration-500">
                        <Calendar class="h-3.5 w-3.5 text-primary" />
                        <span class="text-foreground">
                            {{ from || 'Initial' }} — {{ to || 'Latest' }}
                        </span>
                        <button
                            class="ml-1 rounded-full p-1 hover:bg-muted/80 transition-colors"
                            @click="clearFilters"
                        >
                            <X class="h-3 w-3" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Filter Modal -->
            <Dialog v-model:open="filterModalOpen">
                <DialogContent class="max-w-sm">
                    <DialogHeader>
                        <DialogTitle>Filter by Date</DialogTitle>
                    </DialogHeader>
                    <div class="space-y-4 py-2">
                        <div class="grid gap-4">
                            <div class="space-y-2">
                                <label class="text-xs font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
                                    From
                                </label>
                                <Input v-model="from" type="date" />
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
                                    To
                                </label>
                                <Input v-model="to" type="date" />
                            </div>
                        </div>
                    </div>
                    <DialogFooter class="flex flex-row justify-between sm:justify-between items-center gap-2 pt-2">
                        <Button
                            variant="ghost"
                            size="sm"
                            class="text-xs text-muted-foreground hover:text-foreground"
                            @click="clearFilters"
                        >
                            Clear filters
                        </Button>
                        <div class="flex gap-2">
                            <DialogClose as-child>
                                <Button variant="outline" size="sm">Cancel</Button>
                            </DialogClose>
                            <Button size="sm" @click="applyFilter">Apply Filter</Button>
                        </div>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <div
                v-if="ratings.length === 0"
                class="flex flex-col items-center justify-center p-12 sm:p-20 text-center rounded-[2rem] border border-dashed border-sidebar-border/70 bg-muted/20 backdrop-blur-sm"
            >
                <div class="rounded-full bg-zinc-50 dark:bg-zinc-900 p-6 mb-6 border border-zinc-100 dark:border-zinc-800 shadow-sm animate-in fade-in zoom-in duration-700">
                    <TrendingUp class="h-10 w-10 text-zinc-300" />
                </div>
                <div class="max-w-[320px] space-y-2">
                    <h3 class="text-lg font-serif font-bold text-foreground tracking-tight">No stars yet</h3>
                    <p class="text-sm text-muted-foreground font-light leading-relaxed">
                        Once guests rate their experience from the welcome page, their feedback will shine here. 
                        {{ from || to ? 'Try clearing your date filters to see the full constellation of ratings.' : 'Keep providing a 5-star experience!' }}
                    </p>
                    <Button v-if="from || to" variant="outline" size="sm" class="rounded-full mt-4 h-9 px-6 border-sidebar-border/50" @click="clearFilters">
                        Clear Date Range
                    </Button>
                </div>
            </div>

            <div
                v-else
                ref="listRef"
                class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-6"
            >

                <article
                    v-for="rating in filteredRatings"
                    :key="rating.id"
                    data-rating-card
                    class="relative flex flex-col rounded-2xl border border-sidebar-border/40 bg-background/40 p-5 shadow-sm transition-colors duration-200 hover:border-sidebar-border/80 hover:bg-background/60 overflow-hidden"
                >
                    
                    <div class="relative z-10 flex-1 flex flex-col gap-5">
                        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-2 overflow-hidden">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center border border-primary/20 group-hover:bg-primary/20 transition-colors shrink-0">
                                    <span class="text-[10px] font-bold text-primary">{{ (rating.name || 'A').charAt(0).toUpperCase() }}</span>
                                </div>
                                <div class="min-w-0">
                                    <h3 class="text-sm font-serif font-bold text-foreground leading-tight truncate">
                                        {{ rating.name || 'Anonymous' }}
                                    </h3>
                                    <div class="flex items-center gap-1 mt-1">
                                        <template v-if="editingId === rating.id">
                                            <button
                                                v-for="i in 5"
                                                :key="i"
                                                type="button"
                                                class="scale-90"
                                                @click="setEditScore(i)"
                                            >
                                                <span
                                                    class="text-sm transition-colors"
                                                    :class="i <= editScore ? 'text-primary shadow-primary/20' : 'text-zinc-300 dark:text-zinc-800'"
                                                >
                                                    ★
                                                </span>
                                            </button>
                                        </template>
                                        <template v-else>
                                            <span
                                                v-for="(filled, i) in starsArray(rating.score)"
                                                :key="i"
                                                class="text-[10px] transition-colors"
                                                :class="filled ? 'text-primary drop-shadow-[0_0_8px_rgba(var(--primary),0.3)]' : 'text-zinc-300 dark:text-zinc-800'"
                                            >
                                                ★
                                            </span>
                                        </template>
                                    </div>
                                </div>
                            </div>
                            <time class="text-[9px] text-muted-foreground/60 font-mono shrink-0 pt-1">
                                {{ new Date(rating.created_at).toLocaleDateString() }}
                            </time>
                        </div>

                        <div v-if="editingId === rating.id" class="space-y-4 py-2">
                            <textarea
                                v-model="editMessage"
                                rows="4"
                                class="w-full rounded-2xl border border-sidebar-border bg-background/50 px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-primary/20 transition-all backdrop-blur-sm"
                                placeholder="Edit feedback..."
                            />
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input
                                    v-model="editIsPublic"
                                    type="checkbox"
                                    class="h-4 w-4 rounded-full border-sidebar-border bg-background text-primary focus:ring-primary/20 transition-all"
                                />
                                <span class="text-xs text-muted-foreground font-medium">Visible to everyone</span>
                            </label>
                        </div>
                        <div v-else class="flex-1">
                            <p class="text-sm leading-relaxed text-foreground/90 font-light italic line-clamp-3 group-hover:line-clamp-none transition-all duration-500">
                                "{{ rating.message || 'No remarks provided.' }}"
                            </p>
                        </div>
                    </div>

                    <div class="relative z-10 mt-4 pt-4 border-t border-sidebar-border/30 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <span
                                class="inline-flex h-2 w-2 rounded-full"
                                :class="rating.is_public ? 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.5)]' : 'bg-zinc-400'"
                            ></span>
                            <span class="text-[10px] font-bold uppercase tracking-widest text-muted-foreground">
                                {{ rating.is_public ? 'Public' : 'Private' }}
                            </span>
                        </div>

                        <div class="flex items-center gap-1.5 opacity-100 md:opacity-0 md:group-hover:opacity-100 transition-opacity duration-300">
                            <template v-if="editingId === rating.id">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="h-8 px-4 rounded-full border-sidebar-border hover:bg-primary hover:text-primary-foreground hover:border-primary transition-all text-xs font-semibold"
                                    :disabled="saving"
                                    @click="saveEdit(rating)"
                                >
                                    Save
                                </Button>
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    class="h-8 px-4 rounded-full hover:bg-muted/50 transition-all text-xs font-semibold"
                                    @click="cancelEdit"
                                >
                                    Cancel
                                </Button>
                            </template>
                            <template v-else>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="h-8 px-4 rounded-full border-sidebar-border hover:bg-muted transition-all text-xs font-semibold"
                                    @click="startEdit(rating)"
                                >
                                    Edit
                                </Button>
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    class="h-8 px-4 rounded-full text-destructive hover:bg-destructive/10 transition-all text-xs font-semibold"
                                    @click="remove(rating)"
                                >
                                    Remove
                                </Button>
                            </template>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </AppLayout>
</template>

