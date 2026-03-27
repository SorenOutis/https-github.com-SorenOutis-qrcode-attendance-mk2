import { ref, onMounted, onUnmounted } from 'vue';
import gsap from 'gsap';

export function useTilt(target: any, options = { max: 10, perspective: 1000, scale: 1.02, shine: true }) {
    const el = ref<HTMLElement | null>(null);
    let shineEl: HTMLElement | null = null;

    const createShine = () => {
        const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
        if (!el.value || !options.shine || isTouchDevice) return;
        
        // Ensure el has relative position for absolute shine
        const computedStyle = window.getComputedStyle(el.value);
        if (computedStyle.position === 'static') {
            el.value.style.position = 'relative';
        }
        
        shineEl = document.createElement('div');
        shineEl.className = 'tilt-shine';
        Object.assign(shineEl.style, {
            position: 'absolute',
            top: '0',
            left: '0',
            width: '100%',
            height: '100%',
            zIndex: '1',
            pointerEvents: 'none',
            background: 'radial-gradient(circle at 50% 50%, rgba(255,255,255,0.2) 0%, rgba(255,255,255,0) 80%)',
            opacity: '0',
            transition: 'opacity 0.3s ease'
        });
        
        el.value.appendChild(shineEl);
    };

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

        if (shineEl) {
            const shineX = (x / rect.width) * 100;
            const shineY = (y / rect.height) * 100;
            
            gsap.to(shineEl, {
                opacity: 1,
                background: `radial-gradient(circle at ${shineX}% ${shineY}%, rgba(255,255,255,0.2) 0%, rgba(255,255,255,0) 60%)`,
                duration: 0.2,
                overwrite: 'auto'
            });
        }
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

        if (shineEl) {
            gsap.to(shineEl, {
                opacity: 0,
                duration: 0.5,
                overwrite: 'auto'
            });
        }
    };

    onMounted(() => {
        el.value = target.value?.$el || target.value;
        const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
        
        if (el.value && !isTouchDevice) {
            el.value.style.perspective = `${options.perspective}px`;
            createShine();
            el.value.addEventListener('mousemove', onMouseMove);
            el.value.addEventListener('mouseleave', onMouseLeave);
        }
    });

    onUnmounted(() => {
        if (el.value) {
            el.value.removeEventListener('mousemove', onMouseMove);
            el.value.removeEventListener('mouseleave', onMouseLeave);
            if (shineEl && el.value.contains(shineEl)) {
                el.value.removeChild(shineEl);
            }
        }
    });

    return { el };
}
