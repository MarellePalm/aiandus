<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Aianduspäevik" />

    <div class="relative min-h-screen overflow-x-hidden bg-background text-foreground font-sans antialiased">
        <div class="pointer-events-none fixed -top-20 -left-16 h-72 w-72 rounded-full bg-primary/10 blur-3xl"></div>
        <div class="pointer-events-none fixed -right-24 -bottom-24 h-96 w-96 rounded-full bg-secondary blur-3xl"></div>

        <header class="fixed top-0 z-40 w-full bg-background/85 backdrop-blur-lg">
            <div class="mx-auto flex w-full max-w-3xl items-center justify-between px-6 py-4 md:px-8">
                <div class="flex items-center gap-2 text-primary">
                    <span class="material-symbols-outlined text-[24px]" :style="{ fontVariationSettings: `'FILL' 0, 'wght' 400, 'opsz' 24` }">
                        potted_plant
                    </span>
                    <span class="text-lg font-bold tracking-tight">Aianduspäevik</span>
                </div>
            </div>
        </header>

        <main class="relative z-10 mx-auto flex w-full max-w-3xl flex-col gap-6 px-5 pt-24 pb-10 md:gap-8 md:px-8 md:pt-32 md:pb-14">
            <section class="flex flex-col gap-5 md:gap-6">
                <div>
                    <p class="-mt-5 text-xs font-semibold tracking-[0.18em] text-primary/80 uppercase">
                        UUS! Kevadhooaeg 2026
                    </p>
                    <h1 class="max-w-xl text-3xl leading-[1.12] font-bold tracking-tight sm:text-5xl">
                        Sinu nutikas aiaabiline
                    </h1>

                    <p class="mt-4 max-w-xl text-sm leading-6 text-muted-foreground sm:text-lg sm:leading-7">
                        Planeeri, jälgi ja halda oma aeda ühes kohas. Tea alati, mis kus kasvab.
                    </p>
                </div>

                <Link
                    href="/login"
                    class="btn-primary group h-12 w-fit pr-5 pl-5 text-sm shadow-lg shadow-primary/20 md:h-14 md:px-7 md:text-base"
                >
                    <span>Alusta</span>
                    <span class="material-symbols-outlined text-[18px] text-primary-foreground transition-transform group-hover:translate-x-0.5 md:text-[20px]">arrow_forward_ios</span>
                </Link>
            </section>

            <section class="grid grid-cols-1 gap-3">
                <article class="flex items-center gap-3 rounded-2xl border border-primary/10 bg-card px-4 py-3 shadow-sm md:gap-4 md:px-5 md:py-4">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-background shadow-sm md:h-12 md:w-12">
                        <span class="material-symbols-outlined text-[20px] text-primary md:text-[24px]">calendar_month</span>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-foreground md:text-base">Kalender ja märkmed</h3>
                        <p class="text-xs text-muted-foreground md:text-sm">Ära unusta ühtegi aiatööd.</p>
                    </div>
                </article>

                <article class="flex items-center gap-3 rounded-2xl border border-primary/10 bg-card px-4 py-3 shadow-sm md:gap-4 md:px-5 md:py-4">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-background shadow-sm md:h-12 md:w-12">
                        <span class="material-symbols-outlined text-[20px] text-primary md:text-[24px]">local_florist</span>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-foreground md:text-base">Taimed</h3>
                        <p class="text-xs text-muted-foreground md:text-sm">Hoia oma taimed ühes kohas koos.</p>
                    </div>
                </article>

                <article class="flex items-center gap-3 rounded-2xl border border-primary/10 bg-card px-4 py-3 shadow-sm md:gap-4 md:px-5 md:py-4">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-background shadow-sm md:h-12 md:w-12">
                        <span class="material-symbols-outlined text-[20px] text-primary md:text-[24px]">shelves</span>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-foreground md:text-base">Varud</h3>
                        <p class="text-xs text-muted-foreground md:text-sm">Näe kiiresti, mis varud on olemas.</p>
                    </div>
                </article>

                <article class="flex items-center gap-3 rounded-2xl border border-primary/10 bg-card px-4 py-3 shadow-sm md:gap-4 md:px-5 md:py-4">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-background shadow-sm md:h-12 md:w-12">
                        <span class="material-symbols-outlined text-[20px] text-primary md:text-[24px]">map</span>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-foreground md:text-base">Visuaalne planeerimine</h3>
                        <p class="text-xs text-muted-foreground md:text-sm">Loo oma aia plaan digitaalselt.</p>
                    </div>
                </article>
            </section>

           
        </main>
    </div>
</template>

