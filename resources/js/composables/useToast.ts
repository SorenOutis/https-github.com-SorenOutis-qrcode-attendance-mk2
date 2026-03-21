import { reactive } from 'vue';

export type ToastType = 'success' | 'error' | 'info';

export interface Toast {
    id: number;
    message: string;
    type: ToastType;
}

const state = reactive<{ toasts: Toast[] }>({ toasts: [] });
let nextId = 0;

function add(message: string, type: ToastType, duration = 3000): void {
    const id = ++nextId;
    state.toasts.push({ id, message, type });
    setTimeout(() => remove(id), duration);
}

function remove(id: number): void {
    const index = state.toasts.findIndex((t) => t.id === id);
    if (index !== -1) {
        state.toasts.splice(index, 1);
    }
}

export function useToast() {
    return {
        toasts: state.toasts,
        success: (message: string, duration?: number) => add(message, 'success', duration),
        error: (message: string, duration?: number) => add(message, 'error', duration),
        info: (message: string, duration?: number) => add(message, 'info', duration),
        remove,
    };
}
