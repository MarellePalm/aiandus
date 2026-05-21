<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';

import BackIconButton from '@/components/BackIconButton.vue';
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

type BedOption = {
    id: number;
    name: string;
    garden_plan_id: number;
};

type BedUsed = {
    bed_id: number;
    bed_name: string;
    garden_plan_id: number;
};

const props = defineProps<{
    seed: Seed;
    beds: BedOption[];
    bedsUsed: BedUsed[];
    hasPendingGermination: boolean;
}>();

const showDeleteSeed = ref(false);
const deleteProcessing = ref(false);
const showEditSeed = ref(false);
const menuOpen = ref(false);
const selectedBedId = ref<number | ''>(
    props.beds.length === 1 ? props.beds[0].id : '',
);
const plantFromSeedProcessing = ref(false);
const germinatedCount = ref(0);
const markGerminatedProcessing = ref(false);

const openDeleteModal = () => {
    menuOpen.value = false;
    showDeleteSeed.value = true;
};

const closeDeleteModal = () => {
    showDeleteSeed.value = false;
    deleteProcessing.value = false;
};

const openEditModal = () => {
    menuOpen.value = false;
    showEditSeed.value = true;
};

const formatDateEE = (value?: string | null) => {
    if (!value) return '';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return value;
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const yyyy = d.getFullYear();
    return `${dd}.${mm}.${yyyy}`;
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

const plantFromSeed = () => {
    if (plantFromSeedProcessing.value || !selectedBedId.value) return;
    plantFromSeedProcessing.value = true;
    router.post(
        `/seeds/${props.seed.id}/plant-from-seed`,
        { bed_id: selectedBedId.value },
        {
            onFinish: () => {
                plantFromSeedProcessing.value = false;
            },
        },
    );
};

const markGerminated = () => {
    if (markGerminatedProcessing.value) return;
    markGerminatedProcessing.value = true;
    router.post(
        `/seeds/${props.seed.id}/mark-germinated`,
        { germinated_count: germinatedCount.value },
        {
            preserveScroll: true,
            onFinish: () => {
                markGerminatedProcessing.value = false;
            },
        },
    );
};

const onDocClick = (e: MouseEvent) => {
    if (!menuOpen.value) return;
    const t = e.target as HTMLElement | null;
    if (t?.closest?.('[data-seed-menu]')) return;
    menuOpen.value = false;
};

onMounted(() => document.addEventListener('click', onDocClick));
onBeforeUnmount(() => document.removeEventListener('click', onDocClick));
</script>

<template>
    <Head :title="props.seed.name" />

    <div
        class="font-display min-h-screen bg-background text-foreground antialiased"
    >
        <div
            class="border-beige/50 relative mx-auto min-h-screen w-full max-w-[480px] overflow-x-hidden border-x bg-background shadow-2xl md:mx-0 md:max-w-none md:border-0 md:shadow-none"
        >
            <DiaryHeader :title="props.seed.name">
                <template #leading>
                    <BackIconButton href="/seeds" />
                </template>
                <template #actions>
                    <div class="relative" data-seed-menu>
                        <button
                            type="button"
                            class="flex items-center justify-center rounded-full bg-card/70 p-2 backdrop-blur-md transition-colors"
                            @click.stop="menuOpen = !menuOpen"
                            aria-label="Menüü"
                        >
                            <span class="material-symbols-outlined"
                                >more_vert</span
                            >
                        </button>

                        <div
                            v-if="menuOpen"
                            class="absolute top-12 right-0 z-50 w-44 overflow-hidden rounded-2xl border border-border bg-card shadow-xl ring-1 ring-border"
                        >
                            <button
                                type="button"
                                class="w-full px-4 py-3 text-left text-sm text-foreground hover:bg-black/5"
                                @click="openEditModal"
                            >
                                Muuda varu
                            </button>

                            <button
                                type="button"
                                class="w-full px-4 py-3 text-left text-sm text-foreground hover:bg-black/5"
                                @click="openDeleteModal"
                            >
                                Kustuta
                            </button>
                        </div>
                    </div>
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
                    <span class="material-symbols-outlined text-5xl"
                        >potted_plant</span
                    >
                </div>

                <h2 class="text-2xl font-bold">{{ props.seed.name }}</h2>

                <div
                    v-if="
                        props.seed.amount_text ||
                        props.seed.year ||
                        props.seed.expires_at
                    "
                    class="mt-4 space-y-2 text-sm text-foreground/80"
                >
                    <p v-if="props.seed.amount_text">
                        Kogus: <strong>{{ props.seed.amount_text }}</strong>
                    </p>
                    <p v-if="props.seed.year">
                        Ostmise aasta: <strong>{{ props.seed.year }}</strong>
                    </p>
                    <p v-if="props.seed.expires_at">
                        Aegub:
                        <strong>{{
                            formatDateEE(props.seed.expires_at)
                        }}</strong>
                    </p>
                </div>

                <div
                    v-if="props.beds.length"
                    class="mt-8 rounded-2xl border border-border/70 bg-card/70 p-5"
                >
                    <h3 class="text-lg font-bold tracking-tight">
                        Külva peenrale
                    </h3>
                    <p class="mt-2 text-sm text-foreground/70">
                        Loob taimerea seemnepaketist (kogus teadmata) ja avab
                        valitud peenra.
                    </p>
                    <label
                        class="mt-4 block text-xs font-semibold tracking-widest text-foreground/70 uppercase"
                    >
                        Vali peenar
                    </label>
                    <select
                        v-model="selectedBedId"
                        class="mt-2 w-full rounded-2xl border border-border bg-background px-4 py-3 text-sm text-foreground shadow-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                    >
                        <option disabled value="">Vali peenar…</option>
                        <option
                            v-for="bed in props.beds"
                            :key="bed.id"
                            :value="bed.id"
                        >
                            {{ bed.name }}
                        </option>
                    </select>
                    <button
                        type="button"
                        class="mt-4 flex min-h-11 w-full items-center justify-center gap-2 rounded-2xl bg-primary px-4 py-3 text-sm font-semibold text-primary-foreground transition hover:brightness-105 disabled:opacity-50"
                        :disabled="!selectedBedId || plantFromSeedProcessing"
                        @click="plantFromSeed"
                    >
                        <span class="material-symbols-outlined text-base"
                            >grass</span
                        >
                        Külva peenrale
                    </button>
                </div>
                <p
                    v-else
                    class="mt-8 rounded-2xl border border-dashed border-border/60 bg-background/60 px-4 py-3 text-sm text-muted-foreground"
                >
                    Peenart pole veel. Loo esmalt peenar aiaplaanil.
                </p>

                <div class="mt-8">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-lg font-bold tracking-tight">
                            Märkmed
                        </h3>
                        <button
                            type="button"
                            class="text-sm font-semibold text-primary"
                            @click="openEditModal"
                        >
                            Muuda
                        </button>
                    </div>

                    <div
                        class="rounded-2xl border border-border/70 bg-card/70 p-6"
                    >
                        <p
                            class="font-body text-sm leading-relaxed whitespace-pre-line text-foreground/85"
                        >
                            {{ props.seed.notes || 'Märkmeid veel pole.' }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="props.hasPendingGermination"
                    class="mt-8 rounded-2xl border border-border/70 bg-card/70 p-5"
                >
                    <h3 class="text-lg font-bold tracking-tight">
                        Märgi idanemine
                    </h3>
                    <p class="mt-2 text-sm text-foreground/70">
                        Kui seemned on tõusnud, sisesta mitu tükki tõusis.
                    </p>
                    <label
                        class="mt-4 block text-xs font-semibold tracking-widest text-foreground/70 uppercase"
                    >
                        Tõusis (tk)
                    </label>
                    <input
                        v-model.number="germinatedCount"
                        type="number"
                        min="0"
                        max="10000"
                        class="mt-2 w-full rounded-2xl border border-border bg-background px-4 py-3 text-sm text-foreground shadow-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                    />
                    <button
                        type="button"
                        class="mt-4 flex min-h-11 w-full items-center justify-center rounded-2xl border border-primary/30 bg-primary/10 px-4 py-3 text-sm font-semibold text-primary transition hover:bg-primary/15 disabled:opacity-50"
                        :disabled="markGerminatedProcessing"
                        @click="markGerminated"
                    >
                        Salvesta idanemine
                    </button>
                </div>

                <div v-if="props.bedsUsed.length" class="mt-8">
                    <h3 class="text-lg font-bold tracking-tight">
                        Kasutatud peenardel
                    </h3>
                    <ul class="mt-4 space-y-2">
                        <li v-for="bed in props.bedsUsed" :key="bed.bed_id">
                            <Link
                                :href="`/beds/${bed.bed_id}`"
                                class="flex items-center justify-between rounded-2xl border border-border/70 bg-card/70 px-4 py-3 text-sm transition hover:bg-muted/40"
                            >
                                <span class="font-medium">{{
                                    bed.bed_name
                                }}</span>
                                <span
                                    class="material-symbols-outlined text-base text-primary"
                                    >chevron_right</span
                                >
                            </Link>
                        </li>
                    </ul>
                </div>
            </main>
        </div>
        <DeleteConfirmModal
            :open="showDeleteSeed"
            :title="'Kustuta varu?'"
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
