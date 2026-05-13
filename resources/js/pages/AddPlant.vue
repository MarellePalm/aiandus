<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { onBeforeUnmount, ref } from 'vue';

import SaveButton from '@/components/SaveButton.vue';
import { normalizeImageForUpload } from '@/lib/imageUpload';

type Category = {
    id: number;
    name: string;
};

const props = defineProps<{
    categories: Category[];
}>();

type PlantForm = {
    category_id: number | null;
    subtitle: string;
    planted_at: string;
    watering_frequency: string;
    fertilizing_frequency: string;
    notes: string;
    image: File | null;
    quantity: number;
};

const form = useForm<PlantForm>({
    category_id: props.categories?.[0]?.id ?? null,
    subtitle: '',
    planted_at: '',
    watering_frequency: '',
    fertilizing_frequency: '',
    notes: '',
    image: null,
    quantity: 1,
});

const fileInputRef = ref<HTMLInputElement | null>(null);
const imagePreview = ref<string | null>(null);

const revokePreview = () => {
    if (imagePreview.value) URL.revokeObjectURL(imagePreview.value);
    imagePreview.value = null;
};

function closeModal() {
    if (window.history.length > 1) {
        window.history.back();
        return;
    }
    router.visit('/dashboard');
}

function submit() {
    const quantity = Number(form.quantity);
    if (!Number.isInteger(quantity) || quantity < 1 || quantity > 100) {
        form.setError(
            'quantity',
            'Taimede arv peab olema täisarv vahemikus 1 kuni 100.',
        );
        return;
    }
    form.clearErrors('quantity');
    form.quantity = quantity;

    form.post('/plants', {
        forceFormData: true,
        onSuccess: () => {
            revokePreview();
        },
    });
}

function openPicker() {
    fileInputRef.value?.click();
}

async function onFileChange(e: Event) {
    const input = e.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    if (!file) return;
    if (!file.type.startsWith('image/')) return;

    revokePreview();
    const normalizedFile = await normalizeImageForUpload(file);
    form.image = normalizedFile;
    form.clearErrors('image');
    imagePreview.value = URL.createObjectURL(normalizedFile);
    input.value = '';
}

onBeforeUnmount(() => {
    revokePreview();
});
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

                    <div
                        v-if="!categories.length"
                        class="mt-6 rounded-2xl border border-black/10 bg-white/80 p-5 text-center"
                    >
                        <p class="text-sm font-medium text-[#2E2E2E]">
                            Taimede kategooriaid pole veel lisatud.
                        </p>
                        <p class="mt-2 text-sm text-[#2E2E2E]/70">
                            Loo esmalt vähemalt üks kategooria taimevaates.
                        </p>
                        <Link
                            href="/plants"
                            class="mt-4 inline-flex items-center justify-center rounded-full bg-[#6B8C68] px-4 py-2 text-sm font-semibold text-white hover:bg-[#4F6A52]"
                        >
                            Mine taimevaatesse
                        </Link>
                    </div>

                    <form
                        v-else
                        class="mt-5 flex flex-col gap-5"
                        @submit.prevent="submit"
                    >
                        <div>
                            <label
                                class="text-sm font-semibold tracking-widest text-[#2E2E2E]/70 uppercase"
                                >Sort</label
                            >
                            <input
                                v-model="form.subtitle"
                                type="text"
                                placeholder="nt. Monstera Deliciosa"
                                class="mt-3 w-full rounded-2xl border border-black/10 bg-white px-4 py-3 text-[#2E2E2E] shadow-sm outline-none focus:border-[#6B8C68] focus:ring-2 focus:ring-[#6B8C68]/20"
                                @input="form.clearErrors('subtitle')"
                            />
                            <p
                                v-if="form.errors.subtitle"
                                class="mt-2 text-sm text-red-600"
                            >
                                {{ form.errors.subtitle }}
                            </p>
                        </div>

                        <div>
                            <label
                                class="text-sm font-semibold tracking-widest text-[#2E2E2E]/70 uppercase"
                                >Kategooria</label
                            >
                            <select
                                v-model="form.category_id"
                                class="mt-3 w-full rounded-2xl border border-black/10 bg-white px-4 py-3 text-[#2E2E2E] shadow-sm outline-none focus:border-[#6B8C68] focus:ring-2 focus:ring-[#6B8C68]/20"
                                @change="form.clearErrors('category_id')"
                            >
                                <option :value="null" disabled>
                                    Vali kategooria…
                                </option>
                                <option
                                    v-for="c in categories"
                                    :key="c.id"
                                    :value="c.id"
                                >
                                    {{ c.name }}
                                </option>
                            </select>
                            <p
                                v-if="form.errors.category_id"
                                class="mt-2 text-sm text-red-600"
                            >
                                {{ form.errors.category_id }}
                            </p>
                        </div>

                        <div>
                            <label
                                class="text-sm font-semibold tracking-widest text-[#2E2E2E]/70 uppercase"
                                >Istutamise kuupäev</label
                            >
                            <input
                                v-model="form.planted_at"
                                type="date"
                                lang="et-EE"
                                class="mt-3 w-full rounded-2xl border border-black/10 bg-white px-4 py-3 text-[#2E2E2E] shadow-sm outline-none focus:border-[#6B8C68] focus:ring-2 focus:ring-[#6B8C68]/20"
                                @change="form.clearErrors('planted_at')"
                            />
                            <p class="mt-2 text-xs text-[#2E2E2E]/60">
                                Vali kuupäev kalendrist (vorming sõltub
                                brauserist).
                            </p>
                            <p
                                v-if="form.errors.planted_at"
                                class="mt-2 text-sm text-red-600"
                            >
                                {{ form.errors.planted_at }}
                            </p>
                        </div>

                        <div>
                            <label
                                class="text-sm font-semibold tracking-widest text-[#2E2E2E]/70 uppercase"
                                >Taimede arv (tk)</label
                            >
                            <input
                                v-model="form.quantity"
                                type="number"
                                min="1"
                                max="100"
                                step="1"
                                placeholder="1"
                                class="mt-3 w-full rounded-2xl border border-black/10 bg-white px-4 py-3 text-[#2E2E2E] shadow-sm outline-none focus:border-[#6B8C68] focus:ring-2 focus:ring-[#6B8C68]/20"
                                @input="form.clearErrors('quantity')"
                            />
                            <p
                                v-if="form.errors.quantity"
                                class="mt-2 text-sm text-red-600"
                            >
                                {{ form.errors.quantity }}
                            </p>
                        </div>

                        <div>
                            <label
                                class="text-sm font-semibold tracking-widest text-[#2E2E2E]/70 uppercase"
                                >Kastmine</label
                            >
                            <input
                                v-model="form.watering_frequency"
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
                                v-model="form.fertilizing_frequency"
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
                            <p
                                v-if="form.errors.image"
                                class="mt-2 text-sm text-red-600"
                            >
                                {{ form.errors.image }}
                            </p>
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
                                :disabled="form.processing"
                                class="bg-[#6B8C68] text-white hover:bg-[#4F6A52]"
                            />
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
