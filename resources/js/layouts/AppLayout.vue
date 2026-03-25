<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { ref } from 'vue';
import { useSessionStorage } from '@vueuse/core';
import LoadingScreen from '@/components/LoadingScreen.vue';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const showLoading = useSessionStorage('has-seen-loading-screen-v1', false);
const isLoadingVisible = ref(!showLoading.value);

const onLoadingComplete = () => {
    showLoading.value = true;
    isLoadingVisible.value = false;
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <LoadingScreen v-if="isLoadingVisible" @complete="onLoadingComplete" />
        <slot />
    </AppLayout>
</template>
