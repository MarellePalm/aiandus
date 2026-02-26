<script setup lang="ts">
import { computed } from 'vue'
import { getZodiacInfo } from '@/lib/moon/zodiac'

const props = withDefaults(defineProps<{ date?: Date }>(), { date: undefined })
const dateRef = computed(() => props.date ?? new Date())
const zodiac = computed(() => getZodiacInfo(dateRef.value))

const SIGN_SYMBOL: Record<string, string> = {
  Jäär:'♈', Sõnn:'♉', Kaksikud:'♊', Vähk:'♋', Lõvi:'♌', Neitsi:'♍',
  Kaalud:'♎', Skorpion:'♏', Ambur:'♐', Kaljukits:'♑', Veevalaja:'♒', Kalad:'♓'
}
const signSymbol = computed(() => SIGN_SYMBOL[zodiac.value.moonSign] ?? '☾')

const theme = computed(() => {
  switch (zodiac.value.biodynamicDayType) {
    case 'leaf':  return { bg: 'var(--bio-leaf-bg)',  accent: 'var(--bio-leaf-accent)',  icon: 'eco' }
    case 'fruit': return { bg: 'var(--bio-fruit-bg)', accent: 'var(--bio-fruit-accent)', icon: 'nutrition' }
    case 'root':  return { bg: 'var(--bio-root-bg)',  accent: 'var(--bio-root-accent)',  icon: 'grass' }
    default:      return { bg: 'var(--bio-flower-bg)',accent: 'var(--bio-flower-accent)',icon: 'local_florist' }
  }
})
</script>

<template>
  <div class="mzSeg h-full" :style="{ '--seg-bg': theme.bg, '--seg-accent': theme.accent }">
    <div class="left">
      <!-- ring sobib sinu rhythm-icon stiiliga -->
      <div class="size-8 rounded-full bg-card shadow-inner flex items-center justify-center">
        <span class="material-symbols-outlined icon" :style="{ color: 'var(--seg-accent)' }">
          {{ theme.icon }}
        </span>
      </div>
      <span class="vline"></span>
    </div>

    <div class="content">
      <div class="signRow">
        <span class="symbol">{{ signSymbol }}</span>
        <span class="name">{{ zodiac.moonSign }}</span>
      </div>
      <div class="meta">{{ zodiac.biodynamicDayLabel }}</div>
    </div>
  </div>
</template>