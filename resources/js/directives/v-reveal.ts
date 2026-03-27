import type { Directive } from 'vue';
import gsap from 'gsap';

export const vReveal: Directive = {
    mounted(el, binding) {
        const options = {
            duration: 1.2,
            distance: 40,
            rotation: -5,
            scale: 0.95,
            delay: (Number(binding.arg) || 0) / 1000,
            ...binding.value
        };

        gsap.set(el, {
            opacity: 0,
            y: options.distance,
            rotationX: options.rotation,
            scale: options.scale,
            transformPerspective: 1000
        });

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    gsap.to(el, {
                        opacity: 1,
                        y: 0,
                        rotationX: 0,
                        scale: 1,
                        duration: options.duration,
                        delay: options.delay,
                        ease: 'power3.out',
                        clearProps: 'transform, opacity'
                    });
                    observer.unobserve(el);
                }
            });
        }, { threshold: 0.1 });

        el._revealObserver = observer;
        observer.observe(el);
    },
    unmounted(el) {
        if (el._revealObserver) {
            el._revealObserver.disconnect();
            delete el._revealObserver;
        }
    }
};
