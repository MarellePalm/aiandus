<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue'

import BottomNav from '../pages/BottomNav.vue';


type Task = {
  id: string
  title: string
  latin?: string
  metaLeftIcon: string
  metaLeftText: string
  metaRightIcon: string
  metaRightText: string
  metaRightClass?: string
  progress?: number
  hasProgress?: boolean
  done?: boolean
}

const { month, year, notesByDate } = defineProps<{
  month: number
  year: number
  notesByDate: Record<string, any[]>
}>()

const viewDate = ref(new Date(year, month - 1, 1))
const today = new Date()
const selectedDay = ref(
  (today.getFullYear() === year && today.getMonth() === month - 1) ? today.getDate() : 1
)


const monthTitle = computed(() => {
  const d = viewDate.value
  return d.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })
})

const phaseLabel = computed(() => 'Planning Phase')

const dayLabels = ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT']

function prevMonth() {
  const d = new Date(viewDate.value)
  d.setMonth(d.getMonth() - 1)
  viewDate.value = d
  selectedDay.value = 1
}

function nextMonth() {
  const d = new Date(viewDate.value)
  d.setMonth(d.getMonth() + 1)
  viewDate.value = d
  selectedDay.value = 1
}

const daysInMonth = computed(() => {
  const d = viewDate.value
  const year = d.getFullYear()
  const month = d.getMonth()
  return new Date(year, month + 1, 0).getDate()
})

const startWeekday = computed(() => {
  const d = viewDate.value
  const year = d.getFullYear()
  const month = d.getMonth()
  // 0 = Sunday
  return new Date(year, month, 1).getDay()
})

// demo: päevad, kus on marker (nagu Stitchis)
const markedDays = computed(() => {
  const s = new Set<number>()
  const obj = notesByDate || {}
  
  for (const key of Object.keys(obj)) {
    const d = new Date(key) // "YYYY-MM-DD"
    if (d.getFullYear() === viewDate.value.getFullYear() && d.getMonth() === viewDate.value.getMonth()) {
      s.add(d.getDate())
    }
  }
  return s
})


const tasks = ref<Task[]>([
  {
    id: 't1',
    title: 'Pruning the Roses',
    latin: "Rosa gallica 'Officinalis'",
    metaLeftIcon: 'location_on',
    metaLeftText: 'South Bed',
    metaRightIcon: 'priority_high',
    metaRightText: 'High Priority',
    metaRightClass: 'text-orange-600 dark:text-orange-400 font-semibold',
    progress: 65,
    hasProgress: true,
    done: false,
  },
  {
    id: 't2',
    title: 'Seeding Spinach',
    latin: 'Spinacia oleracea',
    metaLeftIcon: 'eco',
    metaLeftText: 'Vegetable Patch',
    metaRightIcon: 'schedule',
    metaRightText: 'Morning',
    hasProgress: false,
    done: false,
  },
])

const selectedDateLabel = computed(() => {
  const d = viewDate.value
  const dt = new Date(d.getFullYear(), d.getMonth(), selectedDay.value)
  return dt.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
})

function selectDay(day: number) {
  selectedDay.value = day
}
</script>

<template>
  <div class="relative min-h-screen flex flex-col max-w-sm mx-auto overflow-x-hidden">
    <!-- Background Corner Accents -->
    <div
      class="fixed top-0 right-0 -z-10 opacity-10 dark:opacity-20 pointer-events-none transform translate-x-1/4 -translate-y-1/4"
    >
      <img
        class="w-64 h-64 object-cover rounded-full"
        alt="Close up of green plant leaf veins"
        src="https://lh3.googleusercontent.com/aida-public/AB6AXuDY9V_pvCrwk4SI1BBsGlZggekCXonO77dQ1kfKEP4rmUgw5-zbxXQto8P1i_nWRL_lbsKBUoFY9rCdO0O_HCSR-6gLGAEy-4WRqADSsdr8pwfnOKquNIRphzd_Mpf5IsLcvC3OXSlontrHAiqm33H4l9bVMgWj_RqQF8Ee9h4fHlL5unoZoYwbCInWxxXUBMOmyVJLuywoy1yFciEy0os2ciiw-P8WMleZH-guvfmf4hV-I8WTsExo88-AB8H3bRsIUREIuTeY758"
      />
    </div>
    <div
      class="fixed bottom-0 left-0 -z-10 opacity-10 dark:opacity-20 pointer-events-none transform -translate-x-1/4 translate-y-1/4"
    >
      <img
        class="w-64 h-64 object-cover rounded-full"
        alt="Soft focused garden plants and soil"
        src="https://lh3.googleusercontent.com/aida-public/AB6AXuBSXrk6yUJIgr9aS0-3mL-VUz1j4Cj2AadgJPVhwYF3KySv9IKYsAj9zU8_VFocr7P-X8kiQU_cAEWp1memDi0n9-OQJJooEwehoes88b8_QdvbRiRLWYeeXRG5lzfDfT8NXfT2tjZQ1XKC_jk32nznv9CaLuR5-iUebEhhgB6ateoYuwb0l_r8c0Cl47l4LfROavlR_Icj5Z4hHSoVQqObPsOGED0HH3vd__VSYcdT4GG4xZSyy-P5eY0yWH4I_yaxAMHkX0fkQNY"
      />
    </div>

    <!-- TopAppBar -->
    <header class="flex items-center bg-transparent p-4 pt-3 pb-2 justify-between sticky top-0 z-20 backdrop-blur-sm">
      <div class="text-primary flex size-12 shrink-0 items-center">
        <span class="material-symbols-outlined text-3xl">potted_plant</span>
      </div>

      <h2
        class="text-[#141514] dark:text-white text-lg font-bold leading-tight tracking-[-0.015em] flex-1 text-center"
      >
        Minu Aia Päevik
      </h2>

      <div class="flex w-12 items-center justify-end">
        <button
          type="button"
          class="flex size-10 cursor-pointer items-center justify-center overflow-hidden rounded-full bg-white dark:bg-[#323631] text-primary shadow-sm"
          aria-label="Profile"
        >
          <span class="material-symbols-outlined">person</span>
        </button>
      </div>
    </header>

    <main class="flex-1 px-3 pt-2 pb-24">
      <!-- Calendar Section -->
      <div class="bg-white/60 dark:bg-[#323631]/60 backdrop-blur-md rounded-xl p-4 mb-6 soft-shadow">
        <div class="flex items-center justify-between mb-4">
          <button
            type="button"
            class="size-8 flex items-center justify-center rounded-full hover:bg-primary/10 text-primary"
            @click="prevMonth"
            aria-label="Previous month"
          >
            <span class="material-symbols-outlined">chevron_left</span>
          </button>

          <div class="text-center">
            <h3 class="text-lg font-bold">{{ monthTitle }}</h3>
            <p class="text-xs text-primary font-medium tracking-widest uppercase">{{ phaseLabel }}</p>
          </div>

          <button
            type="button"
            class="size-8 flex items-center justify-center rounded-full hover:bg-primary/10 text-primary"
            @click="nextMonth"
            aria-label="Next month"
          >
            <span class="material-symbols-outlined">chevron_right</span>
          </button>
        </div>

        <div class="grid grid-cols-7 gap-1">
          <div
            v-for="lbl in dayLabels"
            :key="lbl"
            class="text-center text-[10px] font-bold text-primary/60 pb-2"
          >
            {{ lbl }}
          </div>

          <!-- Spacer -->
          <div v-for="n in startWeekday" :key="'sp-' + n" class="h-10"></div>

          <!-- Days -->
          <button
            v-for="day in daysInMonth"
            :key="day"
            type="button"
            class="h-10 flex flex-col items-center justify-center rounded-lg text-sm font-medium relative"
            :class="day === selectedDay ? 'leaf-shape bg-primary text-white text-sm font-bold shadow-md shadow-primary/30' : ''"
            @click="selectDay(day)"
          >
            {{ day }}
            <span
              v-if="markedDays.has(day) && day !== selectedDay"
              class="absolute bottom-1.5 size-1 bg-primary rounded-full"
            ></span>
          </button>
        </div>
      </div>

      <!-- SectionHeader -->
      <div class="flex items-center justify-between px-2 pb-4">
        <h2 class="text-[#141514] dark:text-white text-[22px] font-bold leading-tight tracking-[-0.015em]">
          Today's Tasks
        </h2>
        <span class="text-xs bg-primary/20 text-primary font-bold px-2 py-1 rounded-full uppercase tracking-tighter">
          {{ selectedDateLabel }}
        </span>
      </div>

      <!-- List of Tasks (Leaf Cards) -->
      <div class="space-y-4">
        <div
          v-for="t in tasks"
          :key="t.id"
          class="bg-white dark:bg-[#323631] p-4 leaf-shape soft-shadow border-l-4"
          :class="t.id === 't1' ? 'border-primary' : 'border-primary/40'"
        >
          <div class="flex items-start justify-between">
            <div class="flex-1">
              <h4 class="text-[#141514] dark:text-white text-lg font-bold leading-tight">
                {{ t.title }}
              </h4>

              <p v-if="t.latin" class="font-serif-journal italic text-primary text-sm mb-2">
                {{ t.latin }}
              </p>

              <div class="flex items-center gap-2 text-[#727972] dark:text-[#a0aaa0] text-xs">
                <span class="material-symbols-outlined text-sm">{{ t.metaLeftIcon }}</span>
                <span>{{ t.metaLeftText }}</span>
                <span class="mx-1">•</span>
                <span class="material-symbols-outlined text-sm">{{ t.metaRightIcon }}</span>
                <span :class="t.metaRightClass">{{ t.metaRightText }}</span>
              </div>
            </div>

            <div class="flex size-8 items-center justify-center bg-background-light dark:bg-background-dark rounded-full">
              <input
                v-model="t.done"
                type="checkbox"
                class="h-5 w-5 rounded-full border-primary/30 border-2 bg-transparent text-primary checked:bg-primary checked:border-primary focus:ring-0 focus:ring-offset-0 focus:outline-none transition-all cursor-pointer"
              />
            </div>
          </div>

          <div v-if="t.hasProgress" class="mt-4 w-full bg-primary/10 h-1.5 rounded-full overflow-hidden">
            <div class="bg-primary h-full rounded-full" :style="{ width: (t.progress ?? 0) + '%' }"></div>
          </div>
        </div>

        <!-- Empty State (Placeholder/Upcoming) -->
        <div class="flex flex-col items-center justify-center py-8 opacity-40">
          <span class="material-symbols-outlined text-4xl text-primary mb-2">potted_plant</span>
          <p class="text-sm font-medium">A day for rest and growth.</p>
        </div>
      </div>
    </main>

    <!-- Bottom Navigation / Actions (Stitch) -->
    <div class="fixed bottom-4 left-1/2 -translate-x-1/2 w-full max-w-sm px-4 flex justify-between items-center z-50 pointer-events-none">

      <!-- Pill nav -->
      <div
        class="pointer-events-auto flex bg-white/80 dark:bg-background-dark/80 backdrop-blur-lg rounded-full
               px-5 py-2 shadow-xl border border-primary/10 gap-6"
      >
        <Link href="/calendar" class="text-primary flex flex-col items-center">
          <span class="material-symbols-outlined">calendar_today</span>
          <span class="text-[10px] font-bold mt-1">Plan</span>
        </Link>

        <Link href="/map" class="text-[#727972] dark:text-[#a0aaa0] flex flex-col items-center">
          <span class="material-symbols-outlined">map</span>
          <span class="text-[10px] font-bold mt-1">Map</span>
        </Link>

        <Link href="/journal" class="text-[#727972] dark:text-[#a0aaa0] flex flex-col items-center">
          <span class="material-symbols-outlined">history_edu</span>
          <span class="text-[10px] font-bold mt-1">Journal</span>
        </Link>
      </div>

      <!-- Plus -->
      <Link
        href="/calendar/note-form"
        class="pointer-events-auto size-14 bg-primary text-white rounded-full flex items-center justify-center
               shadow-lg shadow-primary/40 active:scale-95 transition-transform"
        aria-label="Lisa märge"
      >
        <span class="material-symbols-outlined text-3xl">add</span>
      </Link>
    </div>
  </div>
</template>


<style scoped>
.leaf-shape {
  border-top-left-radius: 2rem;
  border-bottom-right-radius: 2rem;
}
.soft-shadow {
  box-shadow: 0 10px 25px -5px rgba(107, 141, 104, 0.1), 0 8px 10px -6px rgba(107, 141, 104, 0.1);
}
.custom-scrollbar::-webkit-scrollbar {
  width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #6b8d6833;
  border-radius: 10px;
}
</style>