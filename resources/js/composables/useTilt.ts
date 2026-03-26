import { ref, onMounted, onUnmounted } from 'vue';
import gsap from 'gsap';

export function useTilt(target: any, options = { max: 10, perspective: 1000, scale: 1.02 }) {
    const el = ref<HTMLElement | null>(null);

    const onMouseMove = (e: MouseEvent) => {
        if (!el.value) return;

        const rect = el.value.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        
        const centerX = rect.width / 2;
        const centerY = rect.height / 2;
        
        const rotateX = ((y - centerY) / centerY) * -options.max;
        const rotateY = ((x - centerX) / centerX) * options.max;

        gsap.to(el.value, {
            rotateX,
            rotateY,
            scale: options.scale,
            duration: 0.5,
            ease: 'power2.out',
            overwrite: true
        });
    };

    const onMouseLeave = () => {
        if (!el.value) return;

        gsap.to(el.value, {
            rotateX: 0,
            rotateY: 0,
            scale: 1,
            duration: 0.5,
            ease: 'power2.out',
            overwrite: true
        });
    };

    onMounted(() => {
        el.value = target.value?.$el || target.value;
        if (el.value) {
            el.value.style.perspective = `${options.perspective}px`;
            el.value.addEventListener('mousemove', onMouseMove);
            el.value.addEventListener('mouseleave', onMouseLeave);
        }
    });

    onUnmounted(() => {
        if (el.value) {
            el.value.removeEventListener('mousemove', onMouseMove);
            el.value.removeEventListener('mouseleave', onMouseLeave);
        }
    });

    return { el };
}
