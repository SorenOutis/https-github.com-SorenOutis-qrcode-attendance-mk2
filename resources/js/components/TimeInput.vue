<script setup lang="ts">
import { computed } from 'vue';

/**
 * A mobile-friendly time picker that uses three selects (hour, minute, AM/PM)
 * instead of the native <input type="time">, which shows a 24-hour dial on Android.
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

const hours12 = Array.from({ length: 12 }, (_, i) => String(i + 1).padStart(2, '0'));
const minutes = ['00', '05', '10', '15', '20', '25', '30', '35', '40', '45', '50', '55'];

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
    if (period === 'AM') {
        if (h === 12) {
            h = 0;
        }
    } else {
        if (h !== 12) {
            h += 12;
        }
    }
    emit('update:modelValue', `${String(h).padStart(2, '0')}:${minute}`);
}

function onHourChange(e: Event): void {
    emit24h((e.target as HTMLSelectElement).value, parsed.value.minute, parsed.value.period);
}

function onMinuteChange(e: Event): void {
    emit24h(parsed.value.hour, (e.target as HTMLSelectElement).value, parsed.value.period);
}

function onPeriodChange(e: Event): void {
    emit24h(parsed.value.hour, parsed.value.minute, (e.target as HTMLSelectElement).value);
}
</script>

<template>
    <div class="flex items-center gap-1 rounded-md border border-input bg-background ring-offset-background focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-2 h-10 px-2 overflow-hidden">
        <!-- Hour -->
        <select
            :value="parsed.hour"
            @change="onHourChange"
            class="flex-1 min-w-0 bg-transparent text-sm font-medium text-center appearance-none outline-none cursor-pointer py-2 text-foreground"
            aria-label="Hour"
        >
            <option v-for="h in hours12" :key="h" :value="h" class="bg-background text-foreground">{{ h }}</option>
        </select>

        <span class="text-muted-foreground font-bold text-sm shrink-0">:</span>

        <!-- Minute -->
        <select
            :value="parsed.minute"
            @change="onMinuteChange"
            class="flex-1 min-w-0 bg-transparent text-sm font-medium text-center appearance-none outline-none cursor-pointer py-2 text-foreground"
            aria-label="Minute"
        >
            <option v-for="m in minutes" :key="m" :value="m" class="bg-background text-foreground">{{ m }}</option>
        </select>

        <span class="w-px h-5 bg-border shrink-0 mx-0.5"></span>

        <!-- AM / PM -->
        <select
            :value="parsed.period"
            @change="onPeriodChange"
            class="shrink-0 bg-transparent text-sm font-black tracking-wider text-center appearance-none outline-none cursor-pointer py-2 text-foreground"
            aria-label="AM or PM"
        >
            <option value="AM" class="bg-background text-foreground">AM</option>
            <option value="PM" class="bg-background text-foreground">PM</option>
        </select>
    </div>
</template>
