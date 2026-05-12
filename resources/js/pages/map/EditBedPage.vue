<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import BackIconButton from '@/components/BackIconButton.vue';
import DiaryHeader from '@/components/DiaryHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import BottomNav from '@/pages/BottomNav.vue';
import AddBed from '@/pages/map/AddBed.vue';

type Bed = {
    id: number;
    garden_plan_id: number;
    name: string;
    location: string | null;
    image_url?: string | null;
    cell_size_cm?: number;
    layout?: number[][] | null;
    plants?: {
        id: number;
        name: string;
        image_url: string | null;
        position_in_bed: string | null;
    }[];
};

const props = defineProps<{
    bed: Bed;
}>();

const mapHref = `/map/${props.bed.garden_plan_id}`;

const breadcrumbs = [
    { title: 'Aiaplaan', href: mapHref },
    { title: 'Muuda peenart', href: `/beds/${props.bed.id}/edit` },
];
</script>

<template>
    <Head title="Muuda peenart" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="page page-with-bottomnav">
            <main class="mx-auto w-full max-w-5xl space-y-4 px-4 pb-6">
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

                <AddBed mode="edit" :bed="bed" />
            </main>

            <BottomNav active="map" />
        </div>
    </AppLayout>
</template>
