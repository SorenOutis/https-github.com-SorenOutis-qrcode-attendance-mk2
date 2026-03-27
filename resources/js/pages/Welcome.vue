<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import gsap from 'gsap';
import { 
    ChevronLeft, 
    ChevronRight, 
    MessageSquare, 
    QrCode, 
    Camera, 
    BarChart3 
} from 'lucide-vue-next';
import { onMounted, onUnmounted, ref, computed } from 'vue';
import ScanningVisual from '@/components/ScanningVisual.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard, login, register } from '@/routes';
import commentsRoutes from '@/routes/comments';
import ratingsRoutes from '@/routes/ratings';

type Comment = {
    id: number;
    name?: string | null;
    message: string;
    created_at: string;
};

type Rating = {
    id: number;
    name?: string | null;
    score: number;
    message?: string | null;
    created_at: string;
};

const props = withDefaults(
    defineProps<{
        canRegister?: boolean;
        comments?: Comment[];
        ratings?: Rating[];
        stats?: {
            total_scans: number;
            present_today: number;
            average_rating: number;
            total_ratings: number;
        };
    }>(),
    {
        canRegister: true,
        comments: () => [],
        ratings: () => [],
        stats: () => ({ total_scans: 0, present_today: 0, average_rating: 0, total_ratings: 0 }),
    },
);

const animatedPresentToday = ref(0);
const animatedTotalScans = ref(0);
const animatedAverageRating = ref(0);

const containerRef = ref<HTMLElement | null>(null);
const titleRef = ref<HTMLElement | null>(null);
const textRef = ref<HTMLElement | null>(null);
const btnRef = ref<HTMLElement | null>(null);
const carouselContainerRef = ref<HTMLElement | null>(null);
const carouselRef = ref<HTMLElement | null>(null);

const cards = computed(() => {
    const items = [];
    if (props.ratings && props.ratings.length > 0) {
        items.push(...props.ratings.map(r => ({
            id: 'r' + r.id,
            title: r.name || 'Anonymous',
            desc: `Says: "${r.message || 'Rated the system.'}"\n\n${'★'.repeat(r.score)}${'☆'.repeat(5 - r.score)}`,
            color: 'from-zinc-200/20 via-background to-background dark:from-zinc-800/20'
        })));
    }
    if (props.comments && props.comments.length > 0) {
        items.push(...props.comments.map(c => ({
            id: 'c' + c.id,
            title: c.name || 'Anonymous',
            desc: `Says: "${c.message}"`,
            color: 'from-zinc-300/20 via-background to-background dark:from-zinc-700/20'
        })));
    }
    
    return items;
});

// Carousel state
const activeIndex = ref(0);
const cardWidth = 240; // 220px width + 20px gap (gap-5)

let slideInterval: ReturnType<typeof setInterval> | null = null;
const isHovering = ref(false);

const startAutoSlide = () => {
    if (slideInterval) clearInterval(slideInterval);
    slideInterval = setInterval(() => {
        if (!isHovering.value) {
            nextCard();
        }
    }, 4500); // Auto slide every 2.5 seconds for slower, smoother feel
};

const stopAutoSlide = () => {
    if (slideInterval) {
        clearInterval(slideInterval);
        slideInterval = null;
    }
};

const nextCard = () => {
    if (cards.value.length === 0) return;
    if (activeIndex.value < cards.value.length - 1) {
        activeIndex.value++;
    } else {
        activeIndex.value = 0; // Loop back to start
    }
    animateCarousel();
};

const prevCard = () => {
    if (cards.value.length === 0) return;
    if (activeIndex.value > 0) {
        activeIndex.value--;
    } else {
        activeIndex.value = cards.value.length - 1; // Loop to end
    }
    animateCarousel();
};

const animateCarousel = () => {
    if (!carouselRef.value) return;
    
    const items = carouselRef.value.querySelectorAll<HTMLElement>('.carousel-item');
    
    // Smooth transition between cards with slight 3D pop
    gsap.to(carouselRef.value, {
        x: -(activeIndex.value * cardWidth),
        duration: 0.8,
        ease: 'power3.inOut' // Very smooth, premium feel ease
    });
    
    // Animate individual items for 3D effect
    items.forEach((item, index) => {
        if (index === activeIndex.value) {
            gsap.to(item, {
                scale: 1,
                opacity: 1,
                rotationY: 0,
                z: 0,
                duration: 0.8,
                ease: 'power3.inOut'
            });
        } else {
            // Revert tilt and scale down inactive cards with 3D rotation
            gsap.to(item, {
                scale: 0.88,
                opacity: 0.5,
                rotationX: 0,
                rotationY: index < activeIndex.value ? 25 : -25,
                z: -50,
                duration: 0.8,
                ease: 'power3.inOut'
            });
        }
    });
};

onMounted(() => {
    const tl = gsap.timeline();

    // Animated number counters
    const counterTargets = { present: 0, scans: 0, rating: 0 };
    gsap.to(counterTargets, {
        present: props.stats.present_today,
        scans: props.stats.total_scans,
        rating: props.stats.average_rating,
        duration: 2,
        ease: 'power2.out',
        delay: 0.5,
        snap: { present: 1, scans: 1 },
        onUpdate: () => {
            animatedPresentToday.value = Math.round(counterTargets.present);
            animatedTotalScans.value = Math.round(counterTargets.scans);
            animatedAverageRating.value = Math.round(counterTargets.rating * 10) / 10;
        }
    });

    // Setup 3D perspectives
    if (containerRef.value) {
        gsap.set([titleRef.value, textRef.value, btnRef.value], { transformStyle: "preserve-3d" });
        tl.fromTo(titleRef.value, { y: 60, opacity: 0, rotationX: 45, z: -100 }, { y: 0, opacity: 1, rotationX: 0, z: 0, duration: 1.2, ease: 'power4.out' })
          .fromTo(textRef.value, { y: 40, opacity: 0, rotationX: 20, z: -50 }, { y: 0, opacity: 1, rotationX: 0, z: 0, duration: 1, ease: 'power4.out' }, "-=0.9")
          .fromTo(btnRef.value, { y: 30, opacity: 0, z: -30 }, { y: 0, opacity: 1, z: 0, duration: 0.8, ease: 'power4.out' }, "-=0.7");
    }

    if (carouselContainerRef.value) {
        gsap.set(carouselContainerRef.value, { perspective: 1200 });
        const items = carouselContainerRef.value.querySelectorAll<HTMLElement>('.carousel-item');
        
        items.forEach(item => gsap.set(item, { transformStyle: "preserve-3d" }));
        
        // Initial setup for inactive vs active cards
        items.forEach((item, index) => {
            if (index !== activeIndex.value) {
                gsap.set(item, { scale: 0.88, opacity: 0.5, rotationY: index < activeIndex.value ? 25 : -25, z: -50 });
            }
        });

        gsap.from(items, 
            { x: 150, opacity: 0, rotationY: -45, z: -150, duration: 1.2, stagger: 0.1, ease: 'power3.out' }
        );

        // 3D tilt effect on hover for active item
        items.forEach((card, index) => {
            card.addEventListener('mousemove', (e: MouseEvent) => {
                if (activeIndex.value !== index) return;
                
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                
                const centerX = rect.width / 2;
                const centerY = rect.height / 2;
                
                const rotateX = ((y - centerY) / centerY) * -15;
                const rotateY = ((x - centerX) / centerX) * 15;
                
                gsap.to(card, {
                    rotationX: rotateX,
                    rotationY: rotateY,
                    scale: 1.05,
                    z: 50,
                    boxShadow: '0 30px 40px -10px rgba(0, 0, 0, 0.3), 0 15px 15px -10px rgba(0, 0, 0, 0.1)',
                    duration: 0.4,
                    ease: 'power3.out'
                });
            });

            card.addEventListener('mouseleave', () => {
                if (activeIndex.value !== index) return;
                
                gsap.to(card, {
                    rotationX: 0,
                    rotationY: 0,
                    scale: 1,
                    z: 0,
                    boxShadow: 'none',
                    duration: 0.6,
                    ease: 'elastic.out(1, 0.3)'
                });
            });
        });
    }
    
    startAutoSlide();
});

const ratingModalOpen = ref(false);
const commentModalOpen = ref(false);

const ratingForm = useForm({
    name: '',
    email: '',
    score: 5,
    message: '',
});

const commentForm = useForm({
    name: '',
    email: '',
    message: '',
});

function openRatingModal() {
    ratingForm.reset();
    ratingModalOpen.value = true;
}

function openCommentModal() {
    commentForm.reset();
    commentModalOpen.value = true;
}

function submitRating() {
    ratingForm.post(ratingsRoutes.store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            ratingModalOpen.value = false;
            ratingForm.reset();
        },
    });
}

function submitComment() {
    commentForm.post(commentsRoutes.store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            commentModalOpen.value = false;
            commentForm.reset();
        },
    });
}

onUnmounted(() => {
    stopAutoSlide();
});
const mouseGlowRef = ref<HTMLElement | null>(null);
let glowAnimId: number | null = null;
let targetX = 0, targetY = 0, currentX = 0, currentY = 0;

onMounted(() => {
    const onMouseMove = (e: MouseEvent) => {
        targetX = e.clientX;
        targetY = e.clientY;
    };

    const lerp = (a: number, b: number, t: number) => a + (b - a) * t;

    const animate = () => {
        currentX = lerp(currentX, targetX, 0.06);
        currentY = lerp(currentY, targetY, 0.06);
        if (mouseGlowRef.value) {
            mouseGlowRef.value.style.transform = `translate(${currentX}px, ${currentY}px) translate(-50%, -50%)`;
        }
        glowAnimId = requestAnimationFrame(animate);
    };

    window.addEventListener('mousemove', onMouseMove, { passive: true });
    glowAnimId = requestAnimationFrame(animate);
});

onUnmounted(() => {
    if (glowAnimId) cancelAnimationFrame(glowAnimId);
});
</script>

<template>
    <Head title="Welcome">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="anonymous" />
        <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@1,500;1,600&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet" />
    </Head>
    
    <div ref="containerRef" class="min-h-screen relative flex flex-col bg-background text-foreground font-sans overflow-x-hidden">
        <!-- Abstract gradient background matching Dashboard feel -->
        <div class="absolute inset-0 z-0 overflow-hidden pointer-events-none">
            <!-- These classes match the gradients used in Dashboard cards -->
            <!-- Noise Overlay -->
            <div class="absolute inset-0 opacity-[0.03] pointer-events-none z-50 bg-[url('https://grainy-gradients.vercel.app/noise.svg')] bg-repeat"></div>
            
            <!-- Mouse Follow Glow -->
            <div ref="mouseGlowRef" class="hidden lg:block fixed top-0 left-0 w-[600px] h-[600px] rounded-full bg-primary/10 blur-[140px] pointer-events-none z-0" style="will-change: transform;"></div>

            <div class="absolute top-[-20%] left-[-10%] w-[50%] h-[50%] rounded-full bg-primary/10 blur-[150px]"></div>
            <div class="absolute bottom-[-10%] right-[-10%] w-[50%] h-[50%] rounded-full bg-primary/5 blur-[170px]"></div>
            
            <div class="absolute inset-0 bg-[linear-gradient(to_right,#80808012_1px,transparent_1px),linear-gradient(to_bottom,#80808012_1px,transparent_1px)] bg-[size:32px_32px]"></div>
            
            <!-- Vignette to draw attention to center -->
            <div class="absolute inset-0 shadow-[inset_0_0_150px_60px_hsl(var(--background))] backdrop-blur-sm bg-background/50"></div>
        </div>
        <header class="relative z-50 w-full px-6 lg:px-16 py-4 lg:py-6 flex justify-between items-center bg-background/80 backdrop-blur-xl border-b border-sidebar-border/30 sticky top-0 transition-all">
            <div class="flex items-center gap-2 lg:gap-3">
                <div class="w-9 h-9 lg:w-10 lg:h-10 rounded-xl bg-foreground text-background flex items-center justify-center font-bold text-lg lg:text-xl drop-shadow-lg shadow-black/20 dark:shadow-white/20">
                    Q
                </div>
                <div class="flex flex-col">
                    <span class="font-serif font-bold text-base lg:text-lg leading-none tracking-tight lg:tracking-wide text-foreground">QR Attendance</span>
                    <span class="text-[8px] lg:text-[10px] uppercase tracking-[0.2em] text-muted-foreground mt-0.5 opacity-80">System</span>
                </div>
            </div>
            <nav class="flex items-center gap-2 lg:gap-6 text-sm font-medium">
                <ThemeToggle />
                <template v-if="$page.props.auth.user">
                    <Link
                        :href="dashboard.url()"
                        class="text-xs lg:text-sm text-foreground hover:opacity-80 transition-all font-semibold whitespace-nowrap"
                    >
                        Dashboard
                    </Link>
                </template>
                <template v-else>
                    <Link
                        :href="login.url()"
                        class="text-xs lg:text-sm text-muted-foreground hover:text-foreground transition-colors px-2 lg:px-3 py-1.5 rounded-full hover:bg-sidebar-border/20 whitespace-nowrap"
                    >
                        Log in
                    </Link>
                    <Link
                        v-if="canRegister"
                        :href="register.url()"
                        class="text-xs lg:text-sm px-3 lg:px-4 py-1.5 rounded-full bg-foreground text-background hover:opacity-90 transition-all font-semibold shadow-sm whitespace-nowrap"
                    >
                        Register
                    </Link>
                </template>
            </nav>
        </header>

        <main class="relative z-10 flex flex-col lg:flex-row lg:min-h-[calc(100vh-100px)] w-full gap-4 lg:gap-0 lg:py-0 pb-6 lg:pb-0">
            <!-- Left Side Content -->
            <div class="w-full lg:w-5/12 flex flex-col justify-center px-6 lg:px-16 z-20">
                <div ref="titleRef" class="space-y-3 lg:space-y-4 mb-6 lg:mb-8">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full border border-sidebar-border/70 bg-background/50 backdrop-blur-md text-[10px] font-semibold uppercase tracking-widest text-muted-foreground mb-4">
                        <span class="w-1.5 h-1.5 rounded-full bg-primary animate-pulse"></span>
                        Live System Active
                    </div>
                    
                    <div class="flex items-center justify-between lg:block gap-4">
                        <h1 class="text-4xl xs:text-5xl sm:text-6xl lg:text-7xl font-serif font-bold text-foreground leading-[1.1] tracking-tight flex-1">
                            Elevate<br/>
                            <span class="italic text-muted-foreground">Attendance.</span>
                        </h1>
                        
                        <!-- Small Scanning Visual for Mobile -->
                        <div class="lg:hidden w-32 h-32 shrink-0">
                            <ScanningVisual small />
                        </div>
                    </div>
                </div>
                
                <p ref="textRef" class="text-sm sm:text-base lg:text-lg text-muted-foreground/90 font-light leading-relaxed mb-8 lg:mb-10 max-w-sm">
                    Experience a seamless, contactless, and elegant approach to tracking presence in real-time.
                </p>
                
                <div ref="btnRef" class="flex flex-col sm:flex-row gap-3 lg:gap-4">
                    <template v-if="$page.props.auth.user">
                        <Link
                            :href="dashboard.url()"
                            class="inline-flex items-center justify-center h-12 lg:h-14 px-8 rounded-full bg-foreground text-background hover:bg-foreground/90 text-sm font-semibold tracking-wide transition-all shadow-xl hover:shadow-2xl hover:-translate-y-1"
                        >
                            Go to Dashboard
                        </Link>
                    </template>
                    <template v-else>
                        <Button @click="ratingModalOpen = true" class="h-12 lg:h-14 px-8 rounded-full bg-foreground text-background hover:bg-foreground/90 text-sm font-semibold tracking-wide transition-all shadow-xl hover:shadow-2xl hover:-translate-y-1">
                            Rate System
                        </Button>
                        <Button @click="commentModalOpen = true" variant="outline" class="h-12 lg:h-14 px-8 rounded-full border-sidebar-border hover:bg-sidebar-border/20 text-foreground text-sm font-semibold tracking-wide transition-all backdrop-blur-sm">
                            Leave Feedback
                        </Button>
                    </template>
                </div>

                <!-- Live Quick-Stats Widget -->
                <div class="mt-8 lg:mt-16 grid grid-cols-3 p-4 lg:p-6 rounded-3xl border border-sidebar-border/50 bg-background/30 backdrop-blur-xl shadow-2xl relative overflow-hidden group hover:border-sidebar-border/80 transition-all duration-500 w-full max-w-[500px] divide-x divide-sidebar-border/50">
                    <div class="relative z-10 min-w-0 pr-2 lg:pr-4 flex flex-col justify-center">
                        <div class="text-xl sm:text-2xl lg:text-3xl font-serif font-black text-foreground tabular-nums">
                            {{ animatedPresentToday }}
                        </div>
                        <div class="text-[8px] sm:text-[9px] lg:text-[10px] uppercase tracking-widest text-muted-foreground font-semibold mt-1">Present Today</div>
                    </div>
                    
                    <div class="relative z-10 min-w-0 px-2 lg:px-4 flex flex-col justify-center">
                        <div class="text-xl sm:text-2xl lg:text-3xl font-serif font-black text-foreground tabular-nums">
                            {{ animatedTotalScans }}
                        </div>
                        <div class="text-[8px] sm:text-[9px] lg:text-[10px] uppercase tracking-widest text-muted-foreground font-semibold mt-1">Total Scans</div>
                    </div>
                    
                    <div class="relative z-10 min-w-0 pl-2 lg:pl-4 flex flex-col justify-center">
                        <div class="flex items-baseline gap-1">
                            <span class="text-xl sm:text-2xl lg:text-3xl font-serif font-black text-foreground tabular-nums">{{ animatedAverageRating }}</span>
                            <span class="text-foreground text-sm lg:text-lg drop-shadow-sm">★</span>
                        </div>
                        <div class="text-[8px] sm:text-[9px] lg:text-[10px] uppercase tracking-widest text-muted-foreground font-semibold mt-1">{{ props.stats.total_ratings }} Ratings</div>
                    </div>
                </div>
            </div>

            <!-- Right Visuals (Desktop Only) -->
            <div class="hidden lg:flex w-full lg:w-7/12 relative h-auto min-h-[350px] lg:min-h-[500px] items-center justify-center lg:justify-end px-4 lg:pr-[10%] overflow-hidden">
                <div class="relative w-full max-w-[500px] aspect-square">
                    <div class="absolute inset-0 bg-gradient-to-tr from-zinc-200/20 to-zinc-50/5 dark:from-zinc-800/20 dark:to-zinc-900/5 rounded-full blur-3xl animate-pulse" style="animation-duration: 4s;"></div>
                    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[80%] h-[80%] rounded-full border border-sidebar-border/40 animate-[spin_60s_linear_infinite]"></div>
                    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[60%] h-[60%] rounded-full border border-sidebar-border/60 border-dashed animate-[spin_40s_linear_infinite_reverse]"></div>
                    
                    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full h-full transform hover:scale-[1.02] transition-transform duration-700">
                        <ScanningVisual />
                    </div>
                </div>
            </div>
        </main>

        <!-- Feedback Section -->
        <section class="relative z-10 w-full px-6 lg:px-16 py-8 lg:py-12 bg-background/40 backdrop-blur-md border-t border-sidebar-border/30">
            <div class="max-w-7xl mx-auto flex flex-col lg:flex-row items-center justify-between gap-8 lg:gap-12">
                <div class="w-full lg:w-4/12 flex flex-col justify-center">
                    <h2 class="text-2xl lg:text-3xl font-serif font-bold text-foreground mb-3 lg:mb-4">What Our Users Say</h2>
                    <p class="text-sm lg:text-base text-muted-foreground/90 font-light mb-6 lg:mb-8">
                        Real feedback from students and faculty experiencing the streamlined attendance process.
                    </p>
                </div>

                <!-- Right Side Carousel -->
                <div ref="carouselContainerRef" 
                     v-if="cards.length > 0"
                     class="w-full lg:w-7/12 mt-6 lg:mt-0 relative h-[320px] lg:h-[420px] flex flex-col justify-end"
                     @mouseenter="isHovering = true"
                     @mouseleave="isHovering = false"
                >
                    <div class="w-full overflow-hidden absolute right-0 bottom-16" style="mask-image: linear-gradient(to right, transparent, black 10%, black 75%, transparent); -webkit-mask-image: linear-gradient(to right, transparent, black 10%, black 75%, transparent);">
                        <div ref="carouselRef" class="flex gap-5 px-4 lg:pl-[10%] w-max border-l border-transparent">
                            <div 
                                v-for="(card, index) in cards" 
                                :key="card.id"
                                class="carousel-item relative w-[220px] h-[280px] rounded-[24px] p-6 flex flex-col justify-between overflow-hidden shadow-sm bg-gradient-to-br border border-sidebar-border/70 shrink-0 cursor-pointer"
                                :class="[card.color]"
                                @click="activeIndex = index; animateCarousel()"
                            >
                                <div class="absolute inset-0 bg-background/60 backdrop-blur-xl pointer-events-none" :class="{ 'bg-background/40': activeIndex === index }"></div>
                                <div class="absolute inset-0 bg-gradient-to-t from-background/95 via-background/40 to-transparent pointer-events-none"></div>
                                <div class="relative z-10 space-y-4 flex-1 flex flex-col justify-end pb-2">
                                    <p class="text-muted-foreground font-serif italic text-[15.5px] leading-relaxed pb-1 whitespace-pre-wrap select-none w-full line-clamp-3 group-hover:line-clamp-none transition-all duration-500">
                                        {{ card.desc }}
                                    </p>
                                </div>
                                <div class="relative z-10 flex items-center gap-3 pt-4 border-t border-sidebar-border/50 w-full select-none">
                                    <div class="w-8 h-8 rounded-full bg-primary/20 flex items-center justify-center shrink-0">
                                        <span class="text-xs font-bold text-primary">{{ card.title.charAt(0).toUpperCase() }}</span>
                                    </div>
                                    <div>
                                        <h3 class="text-foreground font-medium text-[13px] drop-shadow-sm font-['Inter'] line-clamp-1 leading-tight">{{ card.title }}</h3>
                                        <div class="text-[10px] text-muted-foreground uppercase tracking-widest mt-0.5">User</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Carousel Controls -->
                    <div class="absolute bottom-0 right-4 lg:right-[10%] flex items-center gap-10 text-sm">
                        <div class="flex items-center gap-3">
                            <button @click="prevCard" class="w-10 h-10 rounded-full border border-sidebar-border/70 flex items-center justify-center text-muted-foreground hover:bg-muted/50 hover:text-foreground transition-all">
                                <ChevronLeft class="w-[18px] h-[18px]" stroke-width="1.5" />
                            </button>
                            <button @click="nextCard" class="w-10 h-10 rounded-full border border-sidebar-border/70 flex items-center justify-center text-muted-foreground hover:bg-muted/50 hover:text-foreground transition-all relative overflow-hidden group">
                                <div class="absolute top-0 bottom-0 left-0 bg-primary/10 transition-all pointer-events-none" :style="{ width: isHovering ? '0%' : '100%' }" style="transition: width 1s linear;" :key="activeIndex"></div>
                                <ChevronRight class="relative z-10 w-[18px] h-[18px]" stroke-width="1.5" />
                            </button>
                        </div>
                        <div class="flex items-center gap-4 font-mono text-[10px] tracking-[0.2em] text-muted-foreground">
                            <span class="text-foreground">{{ String(activeIndex + 1).padStart(2, '0') }}</span>
                            <div class="w-12 h-[1px] bg-sidebar-border/70 shrink-0"></div>
                            <span>{{ String(cards.length).padStart(2, '0') }}</span>
                        </div>
                    </div>
                </div>

                <!-- Empty State for Carousel -->
                <div v-else class="w-full lg:w-7/12 relative h-[260px] lg:h-[420px] flex items-center justify-center">
                    <div class="w-full max-w-[320px] text-center space-y-5 rounded-3xl border border-dashed border-sidebar-border bg-background/30 backdrop-blur-sm p-10 shadow-sm relative overflow-hidden group hover:border-sidebar-border/80 transition-all duration-500">
    
                        <div class="w-16 h-16 rounded-full bg-background/50 mx-auto flex items-center justify-center text-muted-foreground/50 border border-sidebar-border/50 shadow-inner relative z-10">
                            <MessageSquare class="w-8 h-8 opacity-75" stroke-width="1.5" />
                        </div>
                        <p class="text-foreground/80 font-serif italic text-[16px] relative z-10">
                            Be the first to tell us what you think!
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- How It Works Section -->
        <section class="relative z-10 w-full px-6 lg:px-16 py-16 lg:py-24 border-t border-sidebar-border/30">
            <div class="max-w-7xl mx-auto">
                <!-- Section Header -->
                <div class="text-center mb-12 lg:mb-20">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full border border-sidebar-border/70 bg-background/50 backdrop-blur-md text-[10px] font-semibold uppercase tracking-widest text-muted-foreground mb-6">
                        <span class="w-1.5 h-1.5 rounded-full bg-primary"></span>
                        Seamless Process
                    </div>
                    <h2 class="text-3xl lg:text-5xl font-serif font-bold text-foreground leading-tight mb-4">
                        How It <span class="italic text-muted-foreground">Works</span>
                    </h2>
                    <p class="text-sm lg:text-base text-muted-foreground/80 font-light max-w-md mx-auto">
                        Three simple steps to a fully contactless, real-time attendance system.
                    </p>
                </div>

                <!-- Mobile: Snap Scroll Carousel | Desktop: Grid -->
                <div class="relative">
                    <!-- Desktop connecting line -->
                    <div class="hidden md:block absolute top-10 left-[20%] right-[20%] h-[1px] bg-gradient-to-r from-transparent via-sidebar-border/50 to-transparent z-0"></div>

                    <!-- Cards Container -->
                    <div 
                        id="how-it-works-carousel"
                        class="flex md:grid md:grid-cols-3 gap-4 lg:gap-8 overflow-x-auto md:overflow-x-visible snap-x snap-mandatory scroll-smooth pb-4 md:pb-0 -mx-6 px-6 md:mx-0 md:px-0"
                        style="scrollbar-width: none; -ms-overflow-style: none;"
                    >
                    <!-- Step 1 -->
                    <div class="relative flex flex-col items-center text-center p-8 rounded-3xl border border-sidebar-border/40 bg-background/20 backdrop-blur-sm hover:border-foreground/30 hover:bg-background/40 transition-colors shrink-0 w-[80vw] sm:w-[60vw] md:w-auto snap-center snap-always">
                        <div class="absolute inset-0 rounded-3xl bg-gradient-to-br from-foreground/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500 pointer-events-none"></div>
                        <div class="relative z-10 w-20 h-20 rounded-2xl bg-foreground/5 border border-sidebar-border/60 flex items-center justify-center mb-6 group-hover:border-foreground/40 group-hover:bg-foreground/5 transition-all duration-500 shadow-inner">
                            <QrCode class="w-9 h-9 text-muted-foreground group-hover:text-foreground transition-colors" stroke-width="1.5" />
                        </div>
                        <div class="text-xs font-mono text-muted-foreground/50 uppercase tracking-widest mb-2">Step 01</div>
                        <h3 class="text-lg font-serif font-bold text-foreground mb-3">Admin Generates</h3>
                        <p class="text-sm text-muted-foreground/80 font-light leading-relaxed">Faculty creates a session-unique, encrypted QR code from the dashboard in seconds.</p>
                    </div>

                    <!-- Step 2 -->
                    <div class="relative flex flex-col items-center text-center p-8 rounded-3xl border border-sidebar-border/40 bg-background/20 backdrop-blur-sm hover:border-foreground/30 hover:bg-background/40 transition-colors shrink-0 w-[80vw] sm:w-[60vw] md:w-auto snap-center snap-always">
                        <div class="absolute inset-0 rounded-3xl bg-gradient-to-br from-foreground/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500 pointer-events-none"></div>
                        <div class="relative z-10 w-20 h-20 rounded-2xl bg-foreground/10 border border-sidebar-border/30 flex items-center justify-center mb-6 group-hover:border-foreground/60 group-hover:bg-foreground/20 transition-all duration-500 shadow-[0_0_30px_rgba(0,0,0,0.1)]">
                            <Camera class="w-9 h-9 text-foreground transition-colors" stroke-width="1.5" />
                        </div>
                        <div class="text-xs font-mono text-muted-foreground uppercase tracking-widest mb-2">Step 02</div>
                        <h3 class="text-lg font-serif font-bold text-foreground mb-3">Student Scans</h3>
                        <p class="text-sm text-muted-foreground/80 font-light leading-relaxed">Students scan with any mobile device — contactless, instant, and seamless.</p>
                    </div>

                    <!-- Step 3 -->
                    <div class="relative flex flex-col items-center text-center p-8 rounded-3xl border border-sidebar-border/40 bg-background/20 backdrop-blur-sm hover:border-foreground/30 hover:bg-background/40 transition-colors shrink-0 w-[80vw] sm:w-[60vw] md:w-auto snap-center snap-always">
                        <div class="absolute inset-0 rounded-3xl bg-gradient-to-br from-foreground/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500 pointer-events-none"></div>
                        <div class="relative z-10 w-20 h-20 rounded-2xl bg-foreground/5 border border-sidebar-border/60 flex items-center justify-center mb-6 group-hover:border-foreground/40 group-hover:bg-foreground/5 transition-all duration-500 shadow-inner">
                            <BarChart3 class="w-9 h-9 text-muted-foreground group-hover:text-foreground transition-colors" stroke-width="1.5" />
                        </div>
                        <div class="text-xs font-mono text-muted-foreground/50 uppercase tracking-widest mb-2">Step 03</div>
                        <h3 class="text-lg font-serif font-bold text-foreground mb-3">Real-time Tracking</h3>
                        <p class="text-sm text-muted-foreground/80 font-light leading-relaxed">Attendance is instantly recorded and visible in the admin dashboard — live, accurate, and exportable.</p>
                    </div>
                    </div>

                    <!-- Mobile Dots Indicator -->
                    <div class="flex md:hidden items-center justify-center gap-2 mt-6">
                        <div class="w-6 h-1.5 rounded-full bg-foreground/60 transition-all duration-300"></div>
                        <div class="w-1.5 h-1.5 rounded-full bg-foreground/20 transition-all duration-300"></div>
                        <div class="w-1.5 h-1.5 rounded-full bg-foreground/20 transition-all duration-300"></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Powered By Section -->
        <section class="relative z-10 w-full px-6 lg:px-16 py-10 lg:py-12 border-t border-sidebar-border/20">
            <div class="max-w-7xl mx-auto">
                <p class="text-center text-[10px] uppercase tracking-[0.3em] text-muted-foreground/50 mb-8 font-semibold">Powered by</p>
                <div class="flex flex-wrap items-center justify-center gap-8 lg:gap-16 opacity-40 hover:opacity-60 transition-opacity duration-500">
                    <!-- Laravel -->
                    <div class="flex items-center gap-2 group cursor-default">
                        <svg class="h-6 opacity-70 grayscale transition-all duration-300 group-hover:opacity-100" viewBox="0 0 66 66" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M62.91 16.81c.08.43.08.86.08 1.3v14.37c0 1.71-.92 3.3-2.41 4.16L48.84 43.3v13.56c0 1.72-.91 3.3-2.4 4.16L33.27 68.3a4.82 4.82 0 01-4.8 0L15.3 61.02A4.82 4.82 0 0112.89 57V43.44L1.58 36.84A4.82 4.82 0 01.17 32.8V18.11c0-1.72.91-3.3 2.4-4.16L15.74 6.7a4.82 4.82 0 014.8 0l12.45 7.25a4.82 4.82 0 014.8 0l13.17-7.66a4.82 4.82 0 014.8 0l11.75 6.85c1.13.65 1.88 1.72 2.1 2.97z" fill="#18181b" fill-opacity="0.1" class="dark:fill-white dark:fill-opacity-20"/>
                            <text x="33" y="40" font-family="serif" font-size="22" font-weight="bold" fill="currentColor" text-anchor="middle">L</text>
                        </svg>
                        <span class="text-sm font-semibold tracking-wide text-foreground" style="font-family: 'Inter', sans-serif;">Laravel</span>
                    </div>
                    <!-- Vue -->
                    <div class="flex items-center gap-2 group cursor-default">
                        <svg class="h-5 grayscale transition-all duration-300 opacity-70 group-hover:opacity-100" viewBox="0 0 256 221" xmlns="http://www.w3.org/2000/svg">
                            <path d="M204.8 0H256L128 220.8 0 0h97.92L128 51.2 157.44 0h47.36z" fill="#18181b" class="dark:fill-white"/>
                            <path d="M0 0l128 220.8L256 0h-51.2L128 132.48 50.56 0H0z" fill="#3f3f46" class="dark:fill-zinc-300"/>
                            <path d="M50.56 0L128 133.12 204.8 0h-47.36L128 51.2 97.92 0H50.56z" fill="#09090b" class="dark:fill-zinc-100"/>
                        </svg>
                        <span class="text-sm font-semibold tracking-wide text-foreground" style="font-family: 'Inter', sans-serif;">Vue.js</span>
                    </div>
                    <!-- Tailwind -->
                    <div class="flex items-center gap-2 group cursor-default">
                        <svg class="h-5 grayscale transition-all duration-300 opacity-70 group-hover:opacity-100" viewBox="0 0 248 31" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M25.517 0C18.712 0 14.46 3.382 12.758 10.146c2.552-3.382 5.529-4.65 8.931-3.805 1.941.482 3.329 1.882 4.864 3.432 2.502 2.524 5.398 5.545 11.722 5.545 6.804 0 11.057-3.382 12.758-10.145-2.551 3.382-5.528 4.65-8.93 3.804-1.942-.482-3.33-1.882-4.865-3.431C34.736 3.022 31.841 0 25.517 0zM12.758 15.218C5.954 15.218 1.701 18.6 0 25.364c2.552-3.382 5.529-4.65 8.93-3.805 1.942.482 3.33 1.882 4.865 3.432 2.502 2.524 5.397 5.545 11.722 5.545 6.804 0 11.057-3.381 12.758-10.145-2.552 3.382-5.529 4.65-8.931 3.805-1.941-.483-3.329-1.883-4.864-3.432-2.502-2.524-5.398-5.546-11.722-5.546z" fill="#18181b" class="dark:fill-white"/>
                        </svg>
                        <span class="text-sm font-semibold tracking-wide text-foreground" style="font-family: 'Inter', sans-serif;">Tailwind CSS</span>
                    </div>
                    <!-- Inertia -->
                    <div class="flex items-center gap-2 group cursor-default">
                        <div class="h-5 w-5 rounded bg-foreground/5 border border-foreground/30 flex items-center justify-center transition-all opacity-70 group-hover:opacity-100">
                            <span class="text-[8px] font-black text-foreground drop-shadow-sm">I</span>
                        </div>
                        <span class="text-sm font-semibold tracking-wide text-foreground" style="font-family: 'Inter', sans-serif;">Inertia.js</span>
                    </div>
                    <!-- GSAP -->
                    <div class="flex items-center gap-2 group cursor-default">
                        <div class="h-5 w-5 rounded-full bg-foreground/10 border border-foreground/30 flex items-center justify-center transition-all opacity-70 group-hover:opacity-100">
                            <span class="text-[8px] font-black text-foreground drop-shadow-sm">G</span>
                        </div>
                        <span class="text-sm font-semibold tracking-wide text-foreground" style="font-family: 'Inter', sans-serif;">GSAP</span>
                    </div>
                </div>
            </div>
        </section>

        <footer class="relative z-10 w-full px-8 lg:px-16 pb-8 flex flex-col sm:flex-row justify-center lg:justify-start items-center gap-4 sm:gap-8 text-[11px] uppercase tracking-wider text-muted-foreground mt-auto">
            <div class="flex gap-8">
                <a href="https://koamishin.org" class="hover:text-foreground transition-colors">Koamishin.org</a>
            </div>
            <span class="text-sidebar-border/70 hidden sm:inline">|</span>
            <p class="text-[10px]">&copy; {{ new Date().getFullYear() }} All rights reserved Koamishin.org</p>
        </footer>

        <!-- Rating Modal -->
        <Dialog v-model:open="ratingModalOpen">
            <DialogContent class="max-w-sm">
                <DialogHeader>
                    <DialogTitle>Rate the system</DialogTitle>
                    <DialogDescription>
                        How was your experience using our attendance system?
                    </DialogDescription>
                </DialogHeader>
                <form @submit.prevent="submitRating" class="space-y-4 py-2">
                    <div class="flex justify-center gap-2">
                        <button
                            v-for="i in 5"
                            :key="i"
                            type="button"
                            class="text-3xl transition-all hover:scale-110 active:scale-95"
                            :class="i <= ratingForm.score ? 'text-zinc-900 dark:text-zinc-100 drop-shadow-sm' : 'text-muted-foreground/30'"
                            @click="ratingForm.score = i"
                        >
                             ★
                        </button>
                    </div>

                    <div class="grid gap-3">
                        <div class="space-y-1.5">
                            <Label for="r-name" class="text-xs">Name (Optional)</Label>
                            <Input id="r-name" v-model="ratingForm.name" placeholder="John Doe" />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="r-message" class="text-xs">Any suggestions?</Label>
                            <textarea
                                id="r-message"
                                v-model="ratingForm.message"
                                placeholder="Optional comments..."
                                rows="3"
                                class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            ></textarea>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button type="submit" class="w-full" :disabled="ratingForm.processing">
                            Submit Rating
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Suggestion Modal -->
        <Dialog v-model:open="commentModalOpen">
            <DialogContent class="max-w-sm">
                <DialogHeader>
                    <DialogTitle>Suggestions</DialogTitle>
                    <DialogDescription>
                        We'd love to hear how we can improve.
                    </DialogDescription>
                </DialogHeader>
                <form @submit.prevent="submitComment" class="space-y-4 py-2">
                    <div class="grid gap-3">
                        <div class="space-y-1.5">
                            <Label for="c-name" class="text-xs">Name (Optional)</Label>
                            <Input id="c-name" v-model="commentForm.name" placeholder="John Doe" />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="c-message" class="text-xs">Your Feedback</Label>
                            <textarea
                                id="c-message"
                                v-model="commentForm.message"
                                placeholder="What's on your mind?"
                                rows="4"
                                required
                                class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            ></textarea>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button type="submit" class="w-full" :disabled="commentForm.processing">
                            Send Feedback
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
