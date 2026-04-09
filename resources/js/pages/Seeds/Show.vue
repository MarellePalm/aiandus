<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

import BackIconButton from '@/components/BackIconButton.vue';
import CardActionsMenu from '@/components/CardActionsMenu.vue';
import DiaryHeader from '@/components/DiaryHeader.vue';
import BottomNav from '@/pages/BottomNav.vue';

import DeleteConfirmModal from './DeleteConfirmModal.vue';
import EditSeedModal from './EditSeedModal.vue';

type Seed = {
    id: number;
    name: string;
    subtitle?: string | null;
    amount?: number | null;
    amount_text?: string | null;
    year?: number | null;
    expires_at?: string | null;
    image_url?: string | null;
    notes?: string | null;
    is_favorite?: boolean;
};

const props = defineProps<{ seed: Seed }>();
const showDeleteSeed = ref(false);
const deleteProcessing = ref(false);
const showEditSeed = ref(false);

const openDeleteModal = () => {
    showDeleteSeed.value = true;
};

const closeDeleteModal = () => {
    showDeleteSeed.value = false;
    deleteProcessing.value = false;
};

const openEditModal = () => {
    showEditSeed.value = true;
};

const deleteSeed = () => {
    if (deleteProcessing.value) return;
    deleteProcessing.value = true;
    router.delete(`/seeds/${props.seed.id}`, {
        onSuccess: () => router.visit('/seeds'),
        onFinish: () => {
            deleteProcessing.value = false;
        },
    });
};
</script>

<template>
    <Head :title="props.seed.name" />

    <div class="bg-background text-foreground font-display min-h-screen antialiased">
        <div
            class="bg-background border-beige/50 relative mx-auto min-h-screen w-full max-w-[480px] overflow-x-hidden border-x shadow-2xl md:mx-0 md:max-w-none md:border-0 md:shadow-none"
        >
            <DiaryHeader :title="props.seed.name">
                <template #leading>
                    <BackIconButton href="/seeds" />
                </template>
                <template #actions>
                    <CardActionsMenu
                        placement="inline"
                        @edit="openEditModal"
                        @delete="openDeleteModal"
                    />
                </template>
            </DiaryHeader>

            <main class="px-6 py-6 md:px-8">
                <img
                    v-if="props.seed.image_url"
                    :src="props.seed.image_url"
                    alt=""
                    class="mb-6 h-56 w-full rounded-2xl object-cover"
                />
                <div
                    v-else
                    class="mb-6 flex h-56 w-full items-center justify-center rounded-2xl bg-primary/10 text-primary"
                >
                    <span class="material-symbols-outlined text-5xl">potted_plant</span>
                </div>

                <h2 class="text-2xl font-bold">{{ props.seed.name }}</h2>

                <div
                    v-if="props.seed.amount_text || props.seed.year || props.seed.expires_at"
                    class="mt-4 space-y-2 text-sm text-foreground/80"
                >
                    <p v-if="props.seed.amount_text">Kogus: <strong>{{ props.seed.amount_text }}</strong></p>
                    <p v-if="props.seed.year">Ostmise aasta: <strong>{{ props.seed.year }}</strong></p>
                    <p v-if="props.seed.expires_at">Aegumisaasta: <strong>{{ props.seed.expires_at }}</strong></p>
                </div>

                <div class="mt-8">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-lg font-bold tracking-tight">Märkmed</h3>
                    </div>

                    <div class="rounded-2xl border border-border/70 bg-card/70 p-6">
                        <p class="font-body text-sm leading-relaxed text-foreground/85">
                            {{ props.seed.notes || 'Märkmeid veel pole.' }}
                        </p>
                    </div>
                </div>
            </main>
        </div>
        <DeleteConfirmModal
            :open="showDeleteSeed"
            :title="'Kustuta seeme?'"
            :message="`${props.seed.name} eemaldatakse jäädavalt.`"
            :processing="deleteProcessing"
            @close="closeDeleteModal"
            @confirm="deleteSeed"
        />
        <EditSeedModal
            v-model:open="showEditSeed"
            :seed="props.seed"
            @updated="router.reload({ only: ['seed'] })"
        />
        <BottomNav active="seeds" />
    </div>
</template>
