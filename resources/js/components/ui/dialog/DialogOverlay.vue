<script setup lang="ts">
import type { DialogOverlayProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { DialogOverlay } from "reka-ui"
import { cn } from "@/lib/utils"
import { onMounted, useTemplateRef } from 'vue'
import gsap from 'gsap'

const props = defineProps<DialogOverlayProps & { class?: HTMLAttributes["class"] }>()

const delegatedProps = reactiveOmit(props, "class")

const overlayRef = useTemplateRef<HTMLElement>('overlay')

onMounted(() => {
  if (overlayRef.value) {
    gsap.fromTo(overlayRef.value, 
      { opacity: 0 },
      { opacity: 1, duration: 0.4, ease: 'power2.out' }
    )
  }
})
</script>

<template>
  <DialogOverlay
    ref="overlay"
    data-slot="dialog-overlay"
    v-bind="delegatedProps"
    :class="cn('fixed inset-0 z-50 bg-black/80', props.class)"
  >
    <slot />
  </DialogOverlay>
</template>
