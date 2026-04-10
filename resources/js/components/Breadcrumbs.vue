<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

type Props = {
    breadcrumbs: BreadcrumbItemType[];
};

defineProps<Props>();
</script>

<template>
    <Breadcrumb class="hidden md:block">
        <BreadcrumbList class="flex items-center gap-1 sm:gap-1.5">
            <template v-for="(item, index) in breadcrumbs" :key="index">
                <BreadcrumbItem>
                    <template v-if="index === breadcrumbs.length - 1">
                        <BreadcrumbPage class="text-[10px] sm:text-[11px] font-black uppercase tracking-[0.25em] text-zinc-900 dark:text-white transition-all">
                            {{ item.title }}
                        </BreadcrumbPage>
                    </template>
                    <template v-else>
                        <BreadcrumbLink as-child>
                            <Link 
                                :href="item.href" 
                                class="text-[10px] sm:text-[11px] font-bold uppercase tracking-[0.25em] text-zinc-400 hover:text-zinc-900 dark:hover:text-white transition-all cursor-pointer"
                            >
                                {{ item.title }}
                            </Link>
                        </BreadcrumbLink>
                    </template>
                </BreadcrumbItem>
                <BreadcrumbSeparator v-if="index !== breadcrumbs.length - 1" class="text-zinc-200 dark:text-zinc-800 scale-75" />
            </template>
        </BreadcrumbList>
    </Breadcrumb>
</template>
