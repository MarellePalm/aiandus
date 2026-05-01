<script setup lang="ts">
import PlantDetail from '../../components/PlantDetail.vue';
import BottomNav from '../BottomNav.vue';

type Plant = {
    id: number;
    name: string;
    subtitle?: string;
    image_url?: string;
    notes?: string;
    tags?: string[];
    watering_frequency?: string | null;
    fertilizing_frequency?: string | null;
    next_fertilizing_label?: string | null;
    category_slug?: string;
    quantity?: number | null;
};

const { plant } = defineProps<{ plant: Plant }>();
</script>

<template>
    <div class="page page-with-bottomnav">
        <div class="text-text-main font-display min-h-screen bg-background">
            <div
                class="relative mx-auto min-h-screen w-full max-w-[480px] overflow-x-hidden border-x border-primary/10 bg-background shadow-2xl md:mx-0 md:max-w-none md:border-0 md:shadow-none"
            >
                <main class="pb-24">
                    <PlantDetail :plant="plant" />
                </main>
            </div>

            <BottomNav active="plants" />
        </div>
    </div>
</template>
