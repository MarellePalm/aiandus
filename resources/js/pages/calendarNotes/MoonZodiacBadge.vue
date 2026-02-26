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
  <div class="mzSeg mzSeg--stacked h-full" :style="{ '--seg-bg': theme.bg, '--seg-accent': theme.accent }">
    <div class="mzSeg-icon">
      <div class="size-8 rounded-full bg-card shadow-inner flex items-center justify-center shrink-0">
        <span
          class="material-symbols-outlined icon"
          :style="{ color: 'var(--seg-accent)' }"
        >
          {{ theme.icon }}
        </span>
      </div>
    </div>
    <div class="content">
      <div class="signRow">
        <span class="symbol">{{ signSymbol }}</span>
        <div class="signText">
          <span class="name">{{ zodiac.moonSign }}</span>
          <span class="meta">{{ zodiac.biodynamicDayLabel }}</span>
        </div>
      </div>
    </div>
  </div>
</template>