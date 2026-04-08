<script setup lang="ts">
import { computed, ref } from 'vue';

/**
 * A manual entry time picker that allows users to type hour, minute, and AM/PM.
 *
 * Features:
 * - Numeric entry for HH and mm.
 * - Auto-tabbing (Hour -> Minute -> Period).
 * - Auto-padding on blur (8 -> 08).
 * - Smart AM/PM entry (typing 'a' for AM, 'p' for PM).
 *
 * Emits a 24-hour "HH:mm" string (e.g. "15:30") so it works as a drop-in
 * replacement wherever schedule times are stored.
 */

const props = defineProps<{
    modelValue: string; // "HH:mm" in 24-hour format
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const hourRef = ref<HTMLInputElement | null>(null);
const minuteRef = ref<HTMLInputElement | null>(null);
const periodRef = ref<HTMLInputElement | null>(null);

/**
 * Parse the 24h model value into { hour12, minute, period }.
 */
const parsed = computed(() => {
    const [hStr = '08', mStr = '00'] = (props.modelValue || '08:00').split(':');
    const h24 = parseInt(hStr, 10);
    const period = h24 >= 12 ? 'PM' : 'AM';
    const h12 = h24 % 12 === 0 ? 12 : h24 % 12;
    return {
        hour: String(h12).padStart(2, '0'),
        minute: mStr.padStart(2, '0'),
        period,
    };
});

function emit24h(hour: string, minute: string, period: string): void {
    let h = parseInt(hour, 10);
    // Boundary checks
    if (isNaN(h)) h = 8;
    if (h < 1) h = 1;
    if (h > 12) h = 12;

    let m = parseInt(minute, 10);
    if (isNaN(m)) m = 0;
    if (m < 0) m = 0;
    if (m > 59) m = 59;

    const finalMinute = String(m).padStart(2, '0');

    if (period === 'AM') {
        if (h === 12) h = 0;
    } else {
        if (h !== 12) h += 12;
    }
    
    emit('update:modelValue', `${String(h).padStart(2, '0')}:${finalMinute}`);
}

function onHourInput(e: Event): void {
    const val = (e.target as HTMLInputElement).value.replace(/\D/g, '').slice(0, 2);
    (e.target as HTMLInputElement).value = val;
    
    if (val.length === 2) {
        minuteRef.value?.focus();
        minuteRef.value?.select();
    }
    
    emit24h(val, parsed.value.minute, parsed.value.period);
}

function onHourBlur(e: Event): void {
    let val = (e.target as HTMLInputElement).value;
    if (!val) val = '08';
    const h = Math.max(1, Math.min(12, parseInt(val, 10)));
    const padded = String(h).padStart(2, '0');
    (e.target as HTMLInputElement).value = padded;
    emit24h(padded, parsed.value.minute, parsed.value.period);
}

function onMinuteInput(e: Event): void {
    const val = (e.target as HTMLInputElement).value.replace(/\D/g, '').slice(0, 2);
    (e.target as HTMLInputElement).value = val;
    
    if (val.length === 2) {
        periodRef.value?.focus();
        periodRef.value?.select();
    }
    
    emit24h(parsed.value.hour, val, parsed.value.period);
}

function onMinuteBlur(e: Event): void {
    let val = (e.target as HTMLInputElement).value;
    if (!val) val = '00';
    const m = Math.max(0, Math.min(59, parseInt(val, 10)));
    const padded = String(m).padStart(2, '0');
    (e.target as HTMLInputElement).value = padded;
    emit24h(parsed.value.hour, padded, parsed.value.period);
}

function onPeriodKeyDown(e: KeyboardEvent): void {
    if (e.key.toLowerCase() === 'a') {
        e.preventDefault();
        emit24h(parsed.value.hour, parsed.value.minute, 'AM');
    } else if (e.key.toLowerCase() === 'p') {
        e.preventDefault();
        emit24h(parsed.value.hour, parsed.value.minute, 'PM');
    }
}

function onPeriodClick(): void {
    const nextPeriod = parsed.value.period === 'AM' ? 'PM' : 'AM';
    emit24h(parsed.value.hour, parsed.value.minute, nextPeriod);
}
</script>

<template>
    <div 
        class="group flex items-center gap-1 rounded-md border border-input bg-background ring-offset-background focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-2 h-10 px-3 overflow-hidden transition-all duration-200"
        @click="hourRef?.focus()"
    >
        <!-- Hour -->
        <input
            ref="hourRef"
            type="text"
            inputmode="numeric"
            maxlength="2"
            :value="parsed.hour"
            @input="onHourInput"
            @blur="onHourBlur"
            @keydown.enter="minuteRef?.focus()"
            class="w-7 bg-transparent text-sm font-bold text-center appearance-none outline-none cursor-text py-1 text-foreground placeholder:text-muted-foreground/30 focus:bg-muted/50 rounded"
            aria-label="Hour"
            placeholder="08"
        />

        <span class="text-muted-foreground/60 font-black text-sm shrink-0 selection:bg-transparent">:</span>

        <!-- Minute -->
        <input
            ref="minuteRef"
            type="text"
            inputmode="numeric"
            maxlength="2"
            :value="parsed.minute"
            @input="onMinuteInput"
            @blur="onMinuteBlur"
            @keydown.enter="periodRef?.focus()"
            class="w-7 bg-transparent text-sm font-bold text-center appearance-none outline-none cursor-text py-1 text-foreground placeholder:text-muted-foreground/30 focus:bg-muted/50 rounded"
            aria-label="Minute"
            placeholder="00"
        />

        <span class="w-px h-4 bg-border/60 shrink-0 mx-1"></span>

        <!-- AM / PM (Manual Entry / Toggle) -->
        <input
            ref="periodRef"
            type="text"
            readonly
            :value="parsed.period"
            @keydown="onPeriodKeyDown"
            @click.stop="onPeriodClick"
            class="w-8 shrink-0 bg-transparent text-[11px] font-black tracking-tighter text-center appearance-none outline-none cursor-pointer py-1 text-foreground group-focus-within:text-primary transition-colors uppercase select-none focus:bg-muted/50 rounded"
            aria-label="AM or PM"
        />
    </div>
</template>

<style scoped>
/* Chrome, Safari, Edge, Opera */
input::-webkit-outer-spin-button,
input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}

/* Firefox */
input[type=number] {
  -moz-appearance: textfield;
}

input::selection {
    background: transparent;
}
</style>
