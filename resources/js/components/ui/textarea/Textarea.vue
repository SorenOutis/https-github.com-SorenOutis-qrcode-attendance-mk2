<script setup lang="ts">
import { useAttrs } from 'vue';
import { cn } from '@/lib/utils';

defineOptions({
  inheritAttrs: false,
});

const props = defineProps<{
  class?: any;
  modelValue?: string | number;
}>();

const emit = defineEmits<{
  (e: 'update:modelValue', payload: string | number): void;
}>();

const attrs = useAttrs();
</script>

<template>
  <textarea
    :class="
      cn(
        'flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50',
        props.class,
      )
    "
    v-bind="attrs"
    :value="modelValue"
    @input="emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
  />
</template>
