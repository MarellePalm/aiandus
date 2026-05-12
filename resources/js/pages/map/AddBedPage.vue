<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import BackIconButton from '@/components/BackIconButton.vue';
import DiaryHeader from '@/components/DiaryHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import BottomNav from '@/pages/BottomNav.vue';
import AddBed from '@/pages/map/AddBed.vue';

const props = defineProps<{
    showGuide?: boolean;
    gardenPlanId: number;
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
            <main class="mx-auto w-full max-w-5xl space-y-5 px-4 pb-6">
                <DiaryHeader
                    title="Lisa peenar"
                    title-class="text-foreground text-2xl font-bold tracking-tight sm:text-3xl"
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
                <div
                    v-if="props.showGuide"
                    class="flex items-start gap-3 rounded-[1.25rem] bg-primary/8 px-4 py-3.5 ring-1 ring-primary/15"
                >
                    <span
                        class="material-symbols-outlined mt-0.5 shrink-0 text-[18px] text-primary"
                        >info</span
                    >
                    <p class="text-sm leading-6 text-muted-foreground">
                        Alusta nimest ja asukohast. Peenra kuju saad kohe
                        allpool ruutudest kokku joonistada.
                    </p>
                </div>

                <AddBed :garden-plan-id="props.gardenPlanId" />
            </main>

            <BottomNav active="map" />
        </div>
    </AppLayout>
</template>
