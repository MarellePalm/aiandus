<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import BackIconButton from '@/components/BackIconButton.vue';
import DiaryHeader from '@/components/DiaryHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import BottomNav from '@/pages/BottomNav.vue';
import AddBed from '@/pages/map/AddBed.vue';
import type { PlantWithoutBed } from '@/pages/map/types';

type Bed = {
    id: number;
    garden_plan_id: number;
    name: string;
    location: string | null;
    image_url?: string | null;
    is_favorite?: boolean;
    cell_size_cm?: number;
    layout?: number[][] | null;
    cell_bricks?: Array<{
        x: number;
        y: number;
        w: number;
        h: number;
        kind: 'plantable' | 'walkway' | 'empty';
    }> | null;
    plants?: {
        id: number;
        name: string;
        image_url: string | null;
        position_in_bed: string | null;
    }[];
};

const props = defineProps<{
    bed: Bed;
    startStep?: number;
    plantsWithoutBed?: PlantWithoutBed[];
}>();

const mapHref = `/map/${props.bed.garden_plan_id}`;

const wizardStartStep = computed(() => {
    const step = props.startStep ?? 1;
    return step === 2 || step === 3 ? step : 1;
});

const breadcrumbs = [
    { title: 'Aiaplaan', href: mapHref },
    { title: 'Muuda peenart', href: `/beds/${props.bed.id}/edit` },
];
</script>

<template>
    <Head title="Muuda peenart" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="page page-with-bottomnav">
            <main class="mx-auto w-full max-w-6xl space-y-4 px-4 pb-6">
                <DiaryHeader
                    title="Muuda peenart"
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

                <section
                    class="rounded-[1.5rem] border border-primary/15 bg-primary/5 px-4 py-5 shadow-sm md:hidden"
                >
                    <p class="text-base font-semibold text-foreground">
                        Peenra kuju muutmine on suuremas vaates
                    </p>
                    <p class="mt-2 text-sm leading-6 text-muted-foreground">
                        Mobiilis saab peenravaates taimi olemasolevatele
                        ruutudele lisada. Peenra kuju, mõõte ja asukohta muuda
                        arvutis või laiemas vaates.
                    </p>
                    <Link
                        :href="`/beds/${bed.id}`"
                        class="mt-4 inline-flex items-center justify-center rounded-full border border-primary/20 bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90"
                    >
                        Ava peenravaade
                    </Link>
                </section>

                <div class="hidden md:block">
                    <AddBed
                        mode="edit"
                        :bed="bed"
                        :initial-step="wizardStartStep"
                        :plants-without-bed="props.plantsWithoutBed ?? []"
                    />
                </div>
            </main>

            <BottomNav active="map" />
        </div>
    </AppLayout>
</template>
