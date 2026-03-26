import gsap from 'gsap';
import { Directive } from 'vue';

interface TiltOptions {
    max?: number;
    perspective?: number;
    scale?: number;
}

export const vTilt: Directive<HTMLElement, TiltOptions | undefined> = {
    mounted(el, binding) {
        const options = {
            max: binding.value?.max ?? 10,
            perspective: binding.value?.perspective ?? 1000,
            scale: binding.value?.scale ?? 1.02,
        };

        el.style.perspective = `${options.perspective}px`;
        el.style.transformStyle = 'preserve-3d';

        const onMouseMove = (e: MouseEvent) => {
            const rect = el.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            const centerX = rect.width / 2;
            const centerY = rect.height / 2;
            
            const rotateX = ((y - centerY) / centerY) * -options.max;
            const rotateY = ((x - centerX) / centerX) * options.max;

            gsap.to(el, {
                rotateX,
                rotateY,
                scale: options.scale,
                duration: 0.5,
                ease: 'power2.out',
                overwrite: true
            });
        };

        const onMouseLeave = () => {
            gsap.to(el, {
                rotateX: 0,
                rotateY: 0,
                scale: 1,
                duration: 0.5,
                ease: 'power2.out',
                overwrite: true
            });
        };

        el.addEventListener('mousemove', onMouseMove);
        el.addEventListener('mouseleave', onMouseLeave);

        // Store cleanup
        (el as any)._tiltCleanup = () => {
            el.removeEventListener('mousemove', onMouseMove);
            el.removeEventListener('mouseleave', onMouseLeave);
        };
    },
    unmounted(el) {
        if ((el as any)._tiltCleanup) {
            (el as any)._tiltCleanup();
        }
    }
};
