<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

import SaveButton from '@/components/SaveButton.vue';
import { normalizeImageForUpload } from '@/lib/imageUpload';

const form = ref({
    species: '',
    category: '',
    planted_at: '',
    watering: '',
    fertilizing: '',
    notes: '',
});

const processing = ref(false);
const fileInputRef = ref<HTMLInputElement | null>(null);
const imagePreview = ref<string | null>(null);
const categoryOptions = ['Köögiviljad', 'Lilled', 'Toataimed', 'Maitsetaimed'];
const plantedAtDisplay = ref('');

const parseEtDateToIso = (value: string) => {
    const trimmed = value.trim();
    if (!trimmed) return '';
    const m = trimmed.match(/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/);
    if (!m) return null;
    const day = Number.parseInt(m[1], 10);
    const month = Number.parseInt(m[2], 10);
    const year = Number.parseInt(m[3], 10);
    const date = new Date(year, month - 1, day);
    if (
        date.getFullYear() !== year ||
        date.getMonth() !== month - 1 ||
        date.getDate() !== day
    ) {
        return null;
    }
    return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
};

function closeModal() {
    if (window.history.length > 1) {
        window.history.back();
        return;
    }
    router.visit('/dashboard');
}

function submit() {
    const parsed = parseEtDateToIso(plantedAtDisplay.value);
    if (parsed !== null) {
        form.value.planted_at = parsed;
    }
    // TODO: wire this up to an Inertia post when backend route is ready
    // example:
    // router.post(route('plants.store'), form.value, { onStart: () => (processing.value = true), onFinish: () => (processing.value = false) });
    // For now we just log so the page is usable without backend.

    console.log('Add plant form submitted:', form.value);
}

function openPicker() {
    fileInputRef.value?.click();
}

async function onFileChange(e: Event) {
    const input = e.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    if (!file || !file.type.startsWith('image/')) return;
    const normalizedFile = await normalizeImageForUpload(file);
    imagePreview.value = URL.createObjectURL(normalizedFile);
}
</script>

<template>
    <div
        class="font-display min-h-screen bg-background text-foreground antialiased"
    >
        <Head title="Lisa taim">
            <link
                href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
                rel="stylesheet"
            />
        </Head>

        <div
            class="fixed inset-0 z-50 flex items-start justify-center p-4 pt-6 sm:items-center sm:pt-4"
        >
            <button
                type="button"
                class="absolute inset-0 bg-black/30 backdrop-blur-[2px]"
                aria-label="Sulge"
                @click="closeModal"
            />

            <div
                class="relative max-h-[92vh] w-full max-w-lg overflow-hidden rounded-3xl bg-[#FAF8F4] shadow-xl ring-1 ring-black/5"
            >
                <div class="max-h-[92vh] overflow-y-auto p-5 sm:p-6">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-[#2E2E2E]">
                                Lisa taim
                            </h3>
                            <p class="mt-1 text-sm text-[#2E2E2E]/70">
                                Lisa taime põhiandmed.
                            </p>
                        </div>
                        <button
                            type="button"
                            class="rounded-full p-2 text-[#2E2E2E]/60 hover:bg-black/5 hover:text-[#2E2E2E]"
                            aria-label="Sulge"
                            @click="closeModal"
                        >
                            ✕
                        </button>
                    </div>

                    <form
                        class="mt-5 flex flex-col gap-5"
                        @submit.prevent="submit"
                    >
                        <div>
                            <label
                                class="text-sm font-semibold tracking-widest text-[#2E2E2E]/70 uppercase"
                                >Sort</label
                            >
                            <input
                                v-model="form.species"
                                type="text"
                                placeholder="nt. Monstera Deliciosa"
                                class="mt-3 w-full rounded-2xl border border-black/10 bg-white px-4 py-3 text-[#2E2E2E] shadow-sm outline-none focus:border-[#6B8C68] focus:ring-2 focus:ring-[#6B8C68]/20"
                            />
                        </div>

                        <div>
                            <label
                                class="text-sm font-semibold tracking-widest text-[#2E2E2E]/70 uppercase"
                                >Kategooria</label
                            >
                            <select
                                v-model="form.category"
                                class="mt-3 w-full rounded-2xl border border-black/10 bg-white px-4 py-3 text-[#2E2E2E] shadow-sm outline-none focus:border-[#6B8C68] focus:ring-2 focus:ring-[#6B8C68]/20"
                            >
                                <option value="" disabled>
                                    Vali kategooria...
                                </option>
                                <option
                                    v-for="option in categoryOptions"
                                    :key="option"
                                    :value="option"
                                >
                                    {{ option }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label
                                class="text-sm font-semibold tracking-widest text-[#2E2E2E]/70 uppercase"
                                >Istutamise kuupäev</label
                            >
                            <input
                                v-model="plantedAtDisplay"
                                type="text"
                                inputmode="numeric"
                                placeholder="PP.KK.AAAA"
                                class="mt-3 w-full rounded-2xl border border-black/10 bg-white px-4 py-3 text-[#2E2E2E] shadow-sm outline-none focus:border-[#6B8C68] focus:ring-2 focus:ring-[#6B8C68]/20"
                            />
                            <p class="mt-2 text-xs text-[#2E2E2E]/60">
                                Kuupäeva formaat: (PP.KK.AAAA), nt 05.09.2026
                            </p>
                        </div>

                        <div>
                            <label
                                class="text-sm font-semibold tracking-widest text-[#2E2E2E]/70 uppercase"
                                >Kastmine</label
                            >
                            <input
                                v-model="form.watering"
                                type="text"
                                placeholder="nt. iga nädal"
                                class="mt-3 w-full rounded-2xl border border-black/10 bg-white px-4 py-3 text-[#2E2E2E] shadow-sm outline-none focus:border-[#6B8C68] focus:ring-2 focus:ring-[#6B8C68]/20"
                            />
                        </div>

                        <div>
                            <label
                                class="text-sm font-semibold tracking-widest text-[#2E2E2E]/70 uppercase"
                                >Väetamine</label
                            >
                            <input
                                v-model="form.fertilizing"
                                type="text"
                                placeholder="nt. iga 2 nädala tagant"
                                class="mt-3 w-full rounded-2xl border border-black/10 bg-white px-4 py-3 text-[#2E2E2E] shadow-sm outline-none focus:border-[#6B8C68] focus:ring-2 focus:ring-[#6B8C68]/20"
                            />
                        </div>

                        <div>
                            <label
                                class="text-sm font-semibold tracking-widest text-[#2E2E2E]/70 uppercase"
                                >Pilt</label
                            >
                            <input
                                ref="fileInputRef"
                                type="file"
                                accept="image/*"
                                class="hidden"
                                @change="onFileChange"
                            />
                            <button
                                type="button"
                                class="mt-3 w-full rounded-2xl border-2 border-dashed border-black/10 bg-white/60 px-6 py-8 text-center hover:bg-white/80"
                                @click="openPicker"
                            >
                                <template v-if="!imagePreview">
                                    <span
                                        class="material-symbols-outlined text-5xl text-[#6B8C68]"
                                        >add_a_photo</span
                                    >
                                    <p class="mt-2 text-sm text-[#2E2E2E]/70">
                                        Lisa taime pilt
                                    </p>
                                </template>
                                <template v-else>
                                    <img
                                        :src="imagePreview"
                                        alt="Eelvaade"
                                        class="mx-auto max-h-40 rounded-xl object-cover"
                                    />
                                </template>
                            </button>
                        </div>

                        <div>
                            <label
                                class="text-sm font-semibold tracking-widest text-[#2E2E2E]/70 uppercase"
                                >Märkmed</label
                            >
                            <textarea
                                v-model="form.notes"
                                rows="4"
                                placeholder="Lisa siia täiendav info..."
                                class="mt-3 w-full rounded-2xl border border-black/10 bg-white px-4 py-3 text-[#2E2E2E] shadow-sm outline-none focus:border-[#6B8C68] focus:ring-2 focus:ring-[#6B8C68]/20"
                            />
                        </div>

                        <div class="sticky bottom-0 bg-[#FAF8F4] pt-4 pb-1">
                            <SaveButton
                                type="submit"
                                :disabled="processing"
                                class="bg-[#6B8C68] text-white hover:bg-[#4F6A52]"
                            />
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
