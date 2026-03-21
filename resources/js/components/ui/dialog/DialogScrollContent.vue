<script setup lang="ts">
import type { DialogContentEmits, DialogContentProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { X } from "lucide-vue-next"
import {
  DialogClose,
  DialogContent,
  DialogOverlay,
  DialogPortal,
  useForwardPropsEmits,
} from "reka-ui"
import { cn } from "@/lib/utils"
import { onMounted, useTemplateRef } from 'vue'
import gsap from 'gsap'

defineOptions({
  inheritAttrs: false,
})

const props = defineProps<DialogContentProps & { class?: HTMLAttributes["class"] }>()
const emits = defineEmits<DialogContentEmits>()

const delegatedProps = reactiveOmit(props, "class")

const forwarded = useForwardPropsEmits(delegatedProps, emits)

const overlayRef = useTemplateRef<HTMLElement>('overlay')
const contentRef = useTemplateRef<HTMLElement>('content')

onMounted(() => {
  if (overlayRef.value) {
    gsap.fromTo(overlayRef.value, 
      { opacity: 0 },
      { opacity: 1, duration: 0.4, ease: 'power2.out' }
    )
  }
  if (contentRef.value) {
    gsap.fromTo(contentRef.value,
      { opacity: 0, scale: 0.9, y: 20, filter: 'blur(10px)' },
      { 
        opacity: 1, 
        scale: 1, 
        y: 0, 
        filter: 'blur(0px)', 
        duration: 0.5, 
        ease: 'back.out(1.7)',
        clearProps: 'all'
      }
    )
  }
})
</script>

<template>
  <DialogPortal>
    <DialogOverlay
      ref="overlay"
      class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-black/80"
    >
      <DialogContent
        ref="content"
        :class="
          cn(
            'relative z-50 grid w-full max-w-lg my-8 gap-4 border border-border bg-background p-6 shadow-lg sm:rounded-lg md:w-full',
            props.class,
          )
        "
        v-bind="{ ...$attrs, ...forwarded }"
        @pointer-down-outside="(event) => {
          const originalEvent = event.detail.originalEvent;
          const target = originalEvent.target as HTMLElement;
          if (originalEvent.offsetX > target.clientWidth || originalEvent.offsetY > target.clientHeight) {
            event.preventDefault();
          }
        }"
      >
        <slot />

        <DialogClose
          class="absolute top-4 right-4 p-0.5 transition-colors rounded-md hover:bg-secondary"
        >
          <X class="w-4 h-4" />
          <span class="sr-only">Close</span>
        </DialogClose>
      </DialogContent>
    </DialogOverlay>
  </DialogPortal>
</template>
