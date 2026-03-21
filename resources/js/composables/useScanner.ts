import { ref } from 'vue';

const isOpen = ref(false);

export function useScanner() {
    function open() {
        isOpen.value = true;
    }

    function close() {
        isOpen.value = false;
    }

    function toggle() {
        isOpen.value = !isOpen.value;
    }

    return {
        isOpen,
        open,
        close,
        toggle,
    };
}
