<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import BackIconButton from '@/components/BackIconButton.vue';
import DiaryHeader from '@/components/DiaryHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import BottomNav from '@/pages/BottomNav.vue';
import AddBed from '@/pages/map/AddBed.vue';
import type {
    GardenPlacementBed,
    GardenPlacementPlan,
} from '@/pages/map/bedGardenPlacement';
import type { PlantWithoutBed } from '@/pages/map/types';

const props = defineProps<{
    showGuide?: boolean;
    gardenPlanId: number;
    gardenPlan: GardenPlacementPlan;
    existingBeds: GardenPlacementBed[];
    plantsWithoutBed?: PlantWithoutBed[];
}>();

const mapHref = `/map/${props.gardenPlanId}`;
const newBedHref = `/map/${props.gardenPlanId}/beds/new`;

const breadcrumbs = [
    { title: 'Aiaplaan', href: mapHref },
    { title: 'Lisa peenar', href: newBedHref },
];
</script>

<template>
    <Head title="Lisa peenar" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="page page-with-bottomnav">
            <main class="mx-auto w-full max-w-6xl space-y-4 px-4 pb-6">
                <DiaryHeader
                    title="Lisa peenar"
                    title-class="text-foreground text-2xl font-semibold tracking-tight"
                    header-class="pt-5 px-0 md:px-0"
                    top-row-class="mb-2"
                    bottom-row-class="mb-0"
                >
                    <template #leading>
                        <BackIconButton
                            :href="mapHref"
                            aria-label="Tagasi peenarde vaatesse"
                        />
                    </template>
                </DiaryHeader>
                <p
                    v-if="props.showGuide"
                    class="hidden rounded-xl bg-primary/8 px-4 py-3 text-sm leading-6 text-muted-foreground ring-1 ring-primary/15 md:block"
                >
                    Alusta nimest, joonista kuju ja vali ruutude tüübid,
                    seejärel paiguta peenar aiaplaanile. Taimed lisad hiljem
                    peenra vaates.
                </p>

                <section
                    class="rounded-[1.5rem] border border-primary/15 bg-primary/5 px-4 py-5 shadow-sm md:hidden"
                >
                    <p class="text-base font-semibold text-foreground">
                        Peenra loomine on suuremas vaates
                    </p>
                    <p class="mt-2 text-sm leading-6 text-muted-foreground">
                        Mobiilis näitame selle aia peenarde loendit ja
                        peenravaates saab taimi olemasolevatele ruutudele
                        lisada. Uue peenra kuju ja paigutus tee arvutis või
                        laiemas vaates.
                    </p>
                </section>

                <div class="hidden md:block">
                    <AddBed
                        :garden-plan-id="props.gardenPlanId"
                        :garden-plan="props.gardenPlan"
                        :existing-beds="props.existingBeds"
                        :plants-without-bed="props.plantsWithoutBed ?? []"
                    />
                </div>
            </main>

            <BottomNav active="map" />
        </div>
    </AppLayout>
</template>
