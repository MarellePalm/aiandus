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
            <main class="space-y-4 pb-6">
                <DiaryHeader
                    title="Lisa peenar"
                    title-class="text-foreground text-3xl font-bold tracking-tight"
                    header-class="pt-6 px-0 md:px-0"
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
                <p v-if="props.showGuide" class="-mt-2 text-muted-foreground">
                    Loo uus peenar ja joonista selle kuju ruutudest.
                </p>

                <AddBed :garden-plan-id="props.gardenPlanId" />
            </main>

            <BottomNav active="map" />
        </div>
    </AppLayout>
</template>
