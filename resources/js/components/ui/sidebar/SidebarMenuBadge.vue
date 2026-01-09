<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

const props = defineProps<{
  class?: HTMLAttributes['class']
}>()
</script>

<template>
  <div
    data-slot="sidebar-menu-badge"
    data-sidebar="menu-badge"
    :class="cn(
      'pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 flex h-5 min-w-5 items-center justify-center rounded-md px-1.5 text-xs font-medium tabular-nums select-none',
      'bg-gray-100 text-gray-600 border border-gray-200',
      'peer-data-[active=true]/menu-button:bg-emerald-50 peer-data-[active=true]/menu-button:text-emerald-600 peer-data-[active=true]/menu-button:border-emerald-200',
      'group-data-[collapsible=icon]:hidden',
      props.class,
    )"
  >
    <slot />
  </div>
</template>
