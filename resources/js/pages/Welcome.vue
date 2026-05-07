<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

const quickItems = [
    {
        title: 'Kalender',
        description: 'Ära unusta ühtegi aiatööd.',
        icon: 'calendar_month',
        href: '/calendar',
        tone: 'from-green-50 to-white',
        iconTone: 'text-primary',
    },
    {
        title: 'Taimed',
        description: 'Hoia oma taimed ühes kohas koos.',
        icon: 'local_florist',
        href: '/plants',
        tone: 'from-emerald-50 to-white',
        iconTone: 'text-emerald-600',
    },
    {
        title: 'Varud',
        description: 'Näe kiiresti, mis varud on olemas.',
        icon: 'shelves',
        href: '/seeds',
        tone: 'from-amber-50 to-white',
        iconTone: 'text-amber-600',
    },
    {
        title: 'Peenrad',
        description: 'Loo oma aia plaan digitaalselt.',
        icon: 'map',
        href: '/map',
        tone: 'from-violet-50 to-white',
        iconTone: 'text-violet-600',
    },
];
</script>

<template>
    <Head title="Aiapäevik" />

    <div class="min-h-screen bg-[#f6f4ed] text-foreground">
        <main class="mx-auto w-full max-w-[1180px] px-3 py-5 sm:px-6 sm:py-6 lg:py-8">
            <section class="overflow-hidden rounded-[32px] border border-border/70 bg-[#fffdf8] shadow-xl shadow-black/5">
                <div class="min-h-[420px]">
                    <div class="relative z-10 flex flex-col justify-center px-5 py-8 sm:px-8 sm:py-10 lg:px-12 lg:py-12">
                        <div class="mb-8 flex items-center gap-2 text-primary sm:mb-10 lg:mb-20">
                            <span class="material-symbols-outlined text-[23px]">potted_plant</span>
                            <span class="text-lg font-bold tracking-tight sm:text-xl">Aiapäevik</span>
                        </div>

                        <p class="w-fit rounded-full bg-primary/10 px-3 py-1.5 text-[10px] font-bold uppercase tracking-[0.12em] text-primary sm:px-4 sm:py-2 sm:text-[11px] sm:tracking-[0.14em]">
                            Uus! Kevadhooaeg 2026
                        </p>

                        <h1 class="mt-6 max-w-[430px] text-4xl font-extrabold leading-[1] tracking-tight text-[#1f2520] sm:text-5xl lg:mt-6 lg:text-6xl lg:leading-[0.98]">
                            Sinu <span class="text-primary">nutikas</span>
                            aiaabiline
                        </h1>

                        <p class="mt-5 max-w-[360px] text-sm leading-6 text-muted-foreground sm:text-base sm:leading-7 lg:mt-5 lg:text-lg">
                            Planeeri, jälgi ja halda oma aeda ühes kohas. Tea alati, mis kus kasvab 🌿
                        </p>

                        <Link
                            href="/login"
                            class="mt-12 inline-flex w-fit items-center gap-2 rounded-full bg-primary px-6 py-3 text-sm font-semibold text-primary-foreground shadow-lg shadow-green-900/15 transition hover:-translate-y-0.5 hover:bg-primary/90 sm:mt-14 sm:px-7 sm:py-3.5 sm:text-base"
                        >
                            Alusta
                            <span class="material-symbols-outlined text-[19px]">arrow_forward</span>
                        </Link>
                    </div>
                </div>

                <section class="px-5 pb-6 pt-6 sm:px-8 sm:pb-8 sm:pt-7 lg:px-12 lg:pb-12">
                    <h2 class="text-2xl font-bold tracking-tight text-[#1f2520]">Kiire ligipääs</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Vaata, planeeri ja halda kõike oma aiast siit.
                    </p>

                    <div class="mt-5 grid grid-cols-2 gap-3 sm:mt-6 sm:gap-5 lg:grid-cols-4">
                        <Link
                            v-for="item in quickItems"
                            :key="item.title"
                            :href="item.href"
                            class="group relative min-h-[170px] overflow-hidden rounded-2xl border border-border/80 bg-gradient-to-br p-3.5 shadow-sm transition hover:-translate-y-1 hover:shadow-lg hover:shadow-black/5 sm:min-h-[220px] sm:rounded-[24px] sm:p-6"
                            :class="item.tone"
                        >
                            <div class="flex h-11 w-11 items-center justify-center rounded-full bg-white text-3xl shadow-md shadow-black/5 sm:h-16 sm:w-16">
                                <span class="material-symbols-outlined text-[22px] sm:text-[30px]" :class="item.iconTone">
                                    {{ item.icon }}
                                </span>
                            </div>

                            <h3 class="mt-4 text-base font-bold text-[#202820] sm:mt-8 sm:text-xl">
                                {{ item.title }}
                            </h3>

                            <p class="mt-1 max-w-[155px] text-xs leading-5 text-muted-foreground sm:mt-2 sm:text-sm sm:leading-6">
                                {{ item.description }}
                            </p>

                            <div class="absolute right-3 bottom-3 flex h-8 w-8 items-center justify-center rounded-full bg-white text-primary shadow-md shadow-black/5 transition group-hover:translate-x-0.5 sm:right-5 sm:bottom-5 sm:h-10 sm:w-10">
                                <span class="material-symbols-outlined text-[16px] sm:text-[18px]">arrow_forward</span>
                            </div>

                        </Link>
                    </div>
                </section>
            </section>
        </main>
    </div>
</template>