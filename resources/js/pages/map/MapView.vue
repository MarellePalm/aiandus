<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

import DiaryHeader from '@/components/DiaryHeader.vue';
import FloatingPlusButton from '@/components/FloatingPlusButton.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import BottomNav from '@/pages/BottomNav.vue';

type PlantInBed = { id: number; name: string; image_url: string | null; position_in_bed: string | null };
type Bed = { id: number; name: string; location: string | null; image_url?: string | null; rows: number; columns: number; layout?: number[][] | null; plants: PlantInBed[] };
type PlantWithoutBed = { id: number; name: string; image_url: string | null; category?: { name: string; slug: string } | null };

const props = defineProps<{
  beds: Bed[];
  plantsWithoutBed: PlantWithoutBed[];
}>();

const breadcrumbs = [{ title: 'Aiaplaan', href: '/map' }];
const showOnboardingHint = computed(
  () => props.beds.length === 0 && props.plantsWithoutBed.length === 0,
);
const showPlantsWithoutBedHint = ref(false);
const PLANTS_WITHOUT_BED_HINT_SEEN_KEY = 'mapPlantsWithoutBedHintSeen';

const coverTick = ref(0);
let coverTimer: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
  coverTimer = setInterval(() => {
    coverTick.value += 1;
  }, 3500);

  try {
    const hasSeenHint = localStorage.getItem(PLANTS_WITHOUT_BED_HINT_SEEN_KEY) === '1';
    if (!hasSeenHint) {
      showPlantsWithoutBedHint.value = true;
      localStorage.setItem(PLANTS_WITHOUT_BED_HINT_SEEN_KEY, '1');
    }
  } catch {
    showPlantsWithoutBedHint.value = false;
  }
});

onBeforeUnmount(() => {
  if (coverTimer) clearInterval(coverTimer);
});

function bedCoverImage(bed: Bed): string | null {
  if (bed.image_url) return bed.image_url;
  const images = bed.plants.map((p) => p.image_url).filter((x): x is string => Boolean(x));
  if (!images.length) return null;
  const idx = (coverTick.value + (bed.id % images.length)) % images.length;
  return images[idx];
}
</script>

<template>
  <Head title="Aiaplaan" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="page page-with-bottomnav">
      <div class="bg-background-light text-forest font-display min-h-screen antialiased">
        <div class="bg-background-light border-beige/50 relative mx-auto min-h-screen w-full max-w-[480px] overflow-x-hidden border-x shadow-2xl md:mx-0 md:max-w-none md:border-0 md:shadow-none">
          <DiaryHeader
            title="Minu peenrad"
            header-class="pt-6"
            top-row-class="mb-3"
            bottom-row-class="mb-4"
          />

          <main class="flex-1 px-6 py-4 md:px-8">
        <div class="space-y-6 sm:space-y-8">
        <section class="space-y-6">
          <div
            v-if="showOnboardingHint"
            class="rounded-2xl border border-primary/20 bg-linear-to-r from-primary/10 via-primary/5 to-transparent px-4 py-3 shadow-sm"
          >
            <p class="text-sm text-foreground/85 leading-relaxed">
              Ava peenar ja kliki tühjal ruudul <strong>+</strong>, et lisada taim. Allpool on taimed, kes ootavad peenrale määramist.
            </p>
          </div>
            <div
              v-if="!props.beds.length"
              class="rounded-2xl border-2 border-dashed border-primary/30 bg-linear-to-br from-muted/30 to-primary/5 p-8 text-center text-muted-foreground"
            >
              Peenraid pole. Lisa esimene peenar paremal alanurgas oleva + nupuga – siis näed teda siin pildiliselt.
            </div>

            <div v-else class="grid grid-cols-2 gap-3">
                <Link
                  v-for="bed in props.beds"
                  :key="bed.id"
                  :href="`/beds/${bed.id}`"
                  class="group relative aspect-square overflow-hidden rounded-2xl border border-border/70 text-left shadow-soft transition-all duration-200 hover:border-primary/25 hover:shadow-md"
                >
                  <div
                    class="absolute inset-0 bg-cover bg-center"
                    :style="bedCoverImage(bed) ? { backgroundImage: `url('${bedCoverImage(bed)}')` } : {}"
                  />
                  <div
                    v-if="!bedCoverImage(bed)"
                    class="absolute inset-0 bg-linear-to-br from-primary/12 via-muted/40 to-muted/20"
                  />
                  <div class="absolute inset-0 bg-linear-to-t from-black/65 via-black/25 to-transparent" />
                  <div class="absolute bottom-0 left-0 right-0 p-3">
                    <p class="text-sm font-semibold text-white truncate">{{ bed.name }}</p>
                    <p class="text-[11px] text-white/85 mt-0.5">
                      {{ bed.plants.length ? `${bed.plants.length} taime` : 'Tühi peenar' }}
                    </p>
                  </div>
                </Link>
              </div>
        </section>

          <!-- Taimed ilma peenrata -->
          <section class="rounded-2xl border border-border/60 bg-card p-4 sm:p-5 shadow-soft">
            <div class="mb-3 border-b border-border/60 pb-3">
              <div class="flex items-center justify-between gap-3">
                <h2 class="text-base sm:text-lg font-semibold">Taimed ilma peenrata</h2>
                <span class="inline-flex items-center rounded-full border border-primary/20 bg-primary/8 px-2.5 py-1 text-[11px] font-semibold text-primary">
                  {{ plantsWithoutBed.length }}
                </span>
              </div>
              <p v-if="showPlantsWithoutBedHint" class="text-xs text-muted-foreground mt-1.5">
                Vali peenral tühi ruut ja lisa taim.
              </p>
            </div>

            <div
              v-if="!plantsWithoutBed.length"
              class="rounded-xl bg-muted/35 py-3 px-4 text-center text-xs text-muted-foreground"
            >
              Kõik taimed on peenrale määratud või sul pole taimi.
            </div>

            <div v-else class="flex gap-3 overflow-x-auto pb-2 pr-16 no-scrollbar snap-x snap-mandatory">
              <article
                v-for="plant in plantsWithoutBed"
                :key="plant.id"
                class="group relative h-36 w-36 shrink-0 snap-start overflow-hidden rounded-2xl border border-border/60 bg-card shadow-soft"
              >
                <div
                  class="absolute inset-0 bg-cover bg-center"
                  :style="plant.image_url ? { backgroundImage: `url('${plant.image_url}')` } : {}"
                />
                <div
                  v-if="!plant.image_url"
                  class="absolute inset-0 flex items-center justify-center bg-linear-to-br from-primary/12 via-muted/40 to-muted/20 text-primary"
                >
                  <span class="material-symbols-outlined text-3xl">eco</span>
                </div>
                <div class="absolute inset-0 bg-linear-to-t from-black/75 via-black/30 to-transparent" />
                <div class="absolute bottom-0 left-0 right-0 p-2.5 backdrop-blur-[1px]">
                  <p class="text-sm font-semibold text-white truncate">{{ plant.name }}</p>
                  <p class="text-[11px] text-white/85 truncate">Vali ruut peenral</p>
                </div>
              </article>
            </div>
          </section>
      </div>
          </main>
        </div>

        <FloatingPlusButton
          aria-label="Lisa peenar"
          :size-px="52"
          :icon-size-px="30"
          @click="router.visit('/map/beds/new')"
        />

        <BottomNav active="map" />
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
  display: none;
}
.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>
