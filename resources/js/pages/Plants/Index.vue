<script setup lang="ts">
import { Head } from "@inertiajs/vue3";

import AppLayout from '@/layouts/AppLayout.vue';

import BottomNav from "../BottomNav.vue";

const breadcrumbs = [{ title: "Aed", href: "/plants" }];
</script>

<template>
  <Head title="Minu Taimed" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="page page-with-bottomnav">
      <div class="bg-background-light text-forest antialiased font-display min-h-screen">
        <!-- Botanical Line Art Accents (Decorative) -->
        <div class="fixed top-0 left-0 w-32 h-32 opacity-10 pointer-events-none select-none">
          <span class="material-symbols-outlined text-[120px] -rotate-45 translate-x-[-20%] translate-y-[-20%]">eco</span>
        </div>
        <div class="fixed bottom-20 right-0 w-32 h-32 opacity-10 pointer-events-none select-none">
          <span class="material-symbols-outlined text-[120px] rotate-12 translate-x-[20%]">potted_plant</span>
        </div>

     
        <div
          class="
            relative min-h-screen w-full
            bg-background-light overflow-x-hidden
            max-w-[480px] mx-auto shadow-2xl border-x border-beige/50
            md:max-w-none md:mx-0 md:shadow-none md:border-0
          "
        >
          <!-- Header -->
          <header class="sticky top-0 z-20 bg-background-light/80 backdrop-blur-md px-6 md:px-8 pt-12 pb-4">
            <div class="flex items-center justify-between mb-6">
              <div class="flex flex-col">
                <span class="text-xs font-semibold uppercase tracking-widest text-primary mb-1">
                  Minu Aia Päevik
                </span>
                <h1 class="text-3xl font-bold tracking-tight text-forest">Minu Taimed</h1>
              </div>
              <div class="flex gap-3">
                <button
                  class="size-10 flex items-center justify-center rounded-full bg-beige/50 text-forest hover:bg-beige transition-colors"
                  type="button"
                >
                  <span class="material-symbols-outlined text-xl">search</span>
                </button>
                <button
                  class="size-10 flex items-center justify-center rounded-full bg-beige/50 text-forest hover:bg-beige transition-colors"
                  type="button"
                >
                  <span class="material-symbols-outlined text-xl">tune</span>
                </button>
              </div>
            </div>

            <!-- Horizontal Quick Categories -->
            <div class="flex gap-3 overflow-x-auto pb-2 no-scrollbar">
              <div class="flex h-9 shrink-0 items-center justify-center gap-x-2 rounded-full bg-primary px-5 shadow-sm">
                <p class="text-white text-sm font-semibold">Kõik kategooriad</p>
              </div>
              <div class="flex h-9 shrink-0 items-center justify-center gap-x-2 rounded-full bg-beige/60 px-5 border border-beige">
                <p class="text-forest text-sm font-medium">Lemmikud</p>
              </div>
              <div class="flex h-9 shrink-0 items-center justify-center gap-x-2 rounded-full bg-beige/60 px-5 border border-beige">
                <p class="text-forest text-sm font-medium">Hiljuti lisatud</p>
              </div>
            </div>
          </header>

          <!-- Category Grid -->
          <main class="flex-1 px-6 md:px-8 py-4">
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
              <!-- Tomatid -->
              <div class="relative aspect-[1/1] rounded-2xl overflow-hidden shadow-lg group">
                <img
                  alt="Tomatoes"
                  class="absolute inset-0 h-full w-full object-cover transition-transform duration-500 group-hover:scale-110"
                  src="https://lh3.googleusercontent.com/aida-public/AB6AXuBiSMT8MRygYSTYMB9DNDMCEvoj0H4EuvzGpTM6jo5yerPI2p4Ksx9M2YLBW4ghQBh3sxQSC5RJ182BuqnUM6NSw2lFp7qV-JdchgoafAOg-4H5u79XUTHBAuyWbdsEeZFA3liIrXgnaPL7_-4M3PVEDjP0dD-D35yfNSXtDbUdJf9b1I-NBsOpkpDiKhaHei3VMSYVm5KAhsmHgL2d6uyknTOqUKRo6ZiPOb6cCF8GkZaZkMSjganwT44GB5k_mcga8ZHOEKa147ib"
                />
                <div class="matte-overlay absolute inset-0"></div>
                <div class="absolute bottom-0 left-0 p-4 w-full">
                  <span class="inline-block px-2 py-0.5 rounded-md bg-white/20 backdrop-blur-md text-[10px] font-bold text-white uppercase mb-1">
                    12 Sorthi
                  </span>
                  <h3 class="text-white text-lg font-bold">Tomatid</h3>
                </div>
              </div>

              <!-- Kurgid -->
              <div class="relative aspect-[1/1] rounded-2xl overflow-hidden shadow-lg group">
                <img
                  alt="Cucumbers"
                  class="absolute inset-0 h-full w-full object-cover transition-transform duration-500 group-hover:scale-110"
                  src="https://lh3.googleusercontent.com/aida-public/AB6AXuDUxO_J_VQlR5nPBiLjJuKC3TGuOuf5qzHgAlXYpe2LxtRsP8qxIepQdY8oeTGTfVsh1yQ3TqIY12DzHI_9T4hBOuqJIBjSgt1-PtJlwUFWEiU4cuS9sykfT2eU_BWanjrLi5fUSR0btRrvOwnF6ZSlxyZ0qBNZxtPHFugpkQfCfIlYVKYF-fEg1ueCshz2PmVpEv46OHbBJt_wye_-imKohE8jUJXCT9Mr6vKy5WlDvo0JfsmLCGXRV59IK6BZ8oBQioH_yYnwF6jx"
                />
                <div class="matte-overlay absolute inset-0"></div>
                <div class="absolute bottom-0 left-0 p-4 w-full">
                  <span class="inline-block px-2 py-0.5 rounded-md bg-white/20 backdrop-blur-md text-[10px] font-bold text-white uppercase mb-1">
                    5 Sorthi
                  </span>
                  <h3 class="text-white text-lg font-bold">Kurgid</h3>
                </div>
              </div>

              <!-- Kõrvitsad -->
              <div class="relative aspect-[1/1] rounded-2xl overflow-hidden shadow-lg group">
                <img
                  alt="Pumpkins"
                  class="absolute inset-0 h-full w-full object-cover transition-transform duration-500 group-hover:scale-110"
                  src="https://lh3.googleusercontent.com/aida-public/AB6AXuBOzpo2qyOQ7Vc5aRongVnCq-m8fHbpCNbZtQO-78U8Mqx5dmnmqJSAioFupcpnsx5DCOsdi4J0KwJfcmuQazLhU11YiA4eFJFHlSeX2-FjisRW5mD8EkhHvZa-9KBITgWRU91cVfoJ1kDFymU8Nlaqvy9yAPWtneU2BUP2cEsLAYBa97NBOQ9AWODYUdLwzREdcZtn2jkJJbByxqwujSp9dxp1LqZc5eIx3KR9IqHaJwwA4guIjkGsu78jnYga1ccAuQ_SapVETdte"
                />
                <div class="matte-overlay absolute inset-0"></div>
                <div class="absolute bottom-0 left-0 p-6 w-full flex justify-between items-end">
                  <div>
                    <span class="inline-block px-2 py-0.5 rounded-md bg-white/20 backdrop-blur-md text-[10px] font-bold text-white uppercase mb-1">
                      8 Sorthi
                    </span>
                    <h3 class="text-white text-lg font-bold tracking-tight">
                      Kõrvitsad
                    </h3>
                  </div>
                  <div class="size-10 rounded-full bg-white flex items-center justify-center text-forest">
                    <span class="material-symbols-outlined">arrow_forward</span>
                  </div>
                </div>
              </div>

              <!-- Lilled -->
              <div class="relative aspect-[1/1] rounded-2xl overflow-hidden shadow-lg group">
                <img
                  alt="Flowers"
                  class="absolute inset-0 h-full w-full object-cover transition-transform duration-500 group-hover:scale-110"
                  src="https://lh3.googleusercontent.com/aida-public/AB6AXuDSOpzjcZzmh1GeboogcLyLpgHGXhkN_5aA0qTHmzPXFp7rZO4x1xEGuKhxijAmddUc2gX_Jpbbh3iORfkL9DrYQ9GdOEr_i7VlgU92zanA7TxXfvNzxLvaTVgcTA0u-rxiqbwsCgWos1zSI0mK3k-sdVU-YfCUm3mfttMNErlpdgLRpdxLXrkLtzeYXhkI6Dm_fi7HxEij1B8VdHrOkRboGEd_Xvrji_YxenhpAhZQt7SLvF-RJm9jnc000-0zMRw-cWBO0PsaaZvW"
                />
                <div class="matte-overlay absolute inset-0"></div>
                <div class="absolute bottom-0 left-0 p-4 w-full">
                  <span class="inline-block px-2 py-0.5 rounded-md bg-white/20 backdrop-blur-md text-[10px] font-bold text-white uppercase mb-1">
                    24 Sorthi
                  </span>
                  <h3 class="text-white text-lg font-bold">Lilled</h3>
                </div>
              </div>

              <!-- Põõsad -->
              <div class="relative aspect-[1/1] rounded-2xl overflow-hidden shadow-lg group">
                <img
                  alt="Shrubs"
                  class="absolute inset-0 h-full w-full object-cover transition-transform duration-500 group-hover:scale-110"
                  src="https://lh3.googleusercontent.com/aida-public/AB6AXuAGtzF38dID7SJTIHO8Olk93x3olZEncPqrrOU0OUM2kh_G1mLu0RgLXjtDA13OQtfZxRrOqDvkC4_xzrcpvHy57v9hgyE6nZSul-Vq6vCBYmg0j-hVpjtdt1iV3WZDM75nrOkiiqITm-aM62ZFqvJ-bY31D1swjpBIFcQLKgsiJfWdC38MYCOEWk_KGGWpM9FP2o0eck09i1uuNQ2cSctDjpq0x4t4DzNhVHPc-c3785jZu4qV6CtTZ1cN4ttqVRuTcqwqL4cXzdv1"
                />
                <div class="matte-overlay absolute inset-0"></div>
                <div class="absolute bottom-0 left-0 p-4 w-full">
                  <span class="inline-block px-2 py-0.5 rounded-md bg-white/20 backdrop-blur-md text-[10px] font-bold text-white uppercase mb-1">
                    10 Sorthi
                  </span>
                  <h3 class="text-white text-lg font-bold">Põõsad</h3>
                </div>
              </div>
            </div>

            <!-- Tips -->
            <div class="mt-8 mb-4 p-5 rounded-2xl bg-forest text-beige relative overflow-hidden">
              <div class="relative z-10">
                <h4 class="font-bold text-lg mb-1">Aianipp</h4>
                <p class="text-sm opacity-90 leading-relaxed font-light">
                  Augustis on parim aeg küpsete tomatite korjamiseks ja põõsaste harvendamiseks.
                </p>
              </div>
              <span class="material-symbols-outlined absolute -right-4 -bottom-4 text-white/10 text-8xl">lightbulb</span>
            </div>

            <div class="h-24"></div>
          </main>
        </div>

        <BottomNav active="plants" />
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.matte-overlay {
  background: linear-gradient(to top, rgba(79, 106, 82, 0.8) 0%, rgba(79, 106, 82, 0) 60%);
}
.no-scrollbar::-webkit-scrollbar {
  display: none;
}
.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>
