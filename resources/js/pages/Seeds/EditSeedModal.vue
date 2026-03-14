<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

type SeedItem = {
    id: number;
    name: string;
    amount_text?: string | null;
    year?: number | null;
    expires_at?: string | null;
    notes?: string | null;
    image_url?: string | null;
};

const props = defineProps<{
    open: boolean;
    seed: SeedItem | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'updated'): void;
}>();

const close = () => emit('update:open', false);

const form = useForm<{
    name: string;
    amount_text: string;
    year: string;
    expires_at: string;
    notes: string;
    image: File | null;
}>({
    name: '',
    amount_text: '',
    year: '',
    expires_at: '',
    notes: '',
    image: null,
});

const nameInputRef = ref<HTMLInputElement | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);
const previewUrl = ref<string | null>(null);

const hasImage = computed(() => !!form.image || !!previewUrl.value);

const revokePreview = () => {
    if (previewUrl.value?.startsWith('blob:')) {
        URL.revokeObjectURL(previewUrl.value);
    }
    previewUrl.value = null;
};

const setFile = (file: File | null) => {
    if (previewUrl.value?.startsWith('blob:')) {
        URL.revokeObjectURL(previewUrl.value);
    }

    form.image = file;
    if (file) {
        previewUrl.value = URL.createObjectURL(file);
    } else {
        previewUrl.value = props.seed?.image_url ?? null;
    }
};

const onFileChange = (e: Event) => {
    const input = e.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    if (file && !file.type.startsWith('image/')) return;
    setFile(file);
    input.value = '';
};

const openPicker = () => fileInputRef.value?.click();

const emptyOnFocus = ref<Record<'year' | 'expires_at', boolean>>({
    year: false,
    expires_at: false,
});

const markEmptyOnFocus = (field: 'year' | 'expires_at') => {
    emptyOnFocus.value[field] = !form[field]?.trim();
};

const normalizeSpinnerStart = (field: 'year' | 'expires_at') => {
    if (!emptyOnFocus.value[field]) return;

    if (form[field] === '1' || form[field] === '-1' || form[field] === '0') {
        form[field] = '2020';
    }

    emptyOnFocus.value[field] = false;
};

const startYearFrom2020 = (field: 'year' | 'expires_at') => {
    const current = form[field]?.trim();
    if (!current) {
        form[field] = '2020';
        return;
    }

    const parsed = Number(current);
    if (Number.isNaN(parsed)) {
        form[field] = '2020';
    }
};

const resetFromSeed = () => {
    form.clearErrors();
    form.name = props.seed?.name ?? '';
    form.amount_text = props.seed?.amount_text ?? '';
    form.year = props.seed?.year ? String(props.seed.year) : '';
    form.expires_at = props.seed?.expires_at ? props.seed.expires_at.slice(0, 4) : '';
    form.notes = props.seed?.notes ?? '';
    form.image = null;
    revokePreview();
    previewUrl.value = props.seed?.image_url ?? null;
};

watch(
    () => props.open,
    async (open) => {
        if (open) {
            resetFromSeed();
            document.body.style.overflow = 'hidden';
            await nextTick();
            nameInputRef.value?.focus();
            nameInputRef.value?.select();
        } else {
            document.body.style.overflow = '';
        }
    },
    { immediate: true },
);

watch(
    () => props.seed,
    () => {
        if (props.open) resetFromSeed();
    },
);

const isEmptyValue = (v: unknown) => v === '' || v === null || v === undefined;

const shouldNormalizeTo2020 = (v: unknown) => {
    const n = Number(v);
    return Number.isFinite(n) && (n === 1 || n === 0 || n === -1);
};

watch(() => form.year, (value, previous) => {
    if (isEmptyValue(previous) && shouldNormalizeTo2020(value)) {
        form.year = '2020';
    }
});

watch(() => form.expires_at, (value, previous) => {
    if (isEmptyValue(previous) && shouldNormalizeTo2020(value)) {
        form.expires_at = '2020';
    }
});

const submit = () => {
    if (!props.seed) return;

    form.transform((data) => ({
        ...data,
        expires_at: data.expires_at ? `${data.expires_at}-12-31` : '',
        _method: 'patch',
    })).post(`/seeds/${props.seed.id}`, {
        forceFormData: true,
        onSuccess: () => {
            emit('updated');
            close();
        },
        onFinish: () => {
            form.transform((data) => data);
        },
    });
};

onBeforeUnmount(() => {
    document.body.style.overflow = '';
    revokePreview();
});
</script>

<template>
    <Teleport to="body">
        <transition name="fade">
            <div
                v-if="open"
                class="fixed inset-0 z-50 flex items-start justify-center p-4 pt-6 sm:items-center sm:pt-4"
                aria-modal="true"
                role="dialog"
            >
                <button
                    type="button"
                    class="absolute inset-0 bg-black/30 backdrop-blur-[2px]"
                    aria-label="Sulge"
                    @click="close"
                />

                <div class="relative w-full max-w-lg max-h-[92vh] overflow-hidden rounded-3xl bg-[#FAF8F4] shadow-xl ring-1 ring-black/5">
                    <div class="max-h-[92vh] overflow-y-auto p-5 sm:p-6">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-lg font-semibold text-[#2E2E2E]">Muuda seemet</h3>
                                <p class="mt-1 text-sm text-[#2E2E2E]/70">
                                    Muuda nime, ostuaastat, aegumist ja märkuseid.
                                </p>
                            </div>
                            <button
                                type="button"
                                class="rounded-full p-2 text-[#2E2E2E]/60 hover:bg-black/5 hover:text-[#2E2E2E]"
                                aria-label="Sulge"
                                @click="close"
                            >
                                ✕
                            </button>
                        </div>

                        <div class="mt-5">
                            <label class="text-sm font-semibold tracking-widest text-[#2E2E2E]/70 uppercase">
                                    Nimi
                            </label>
                            <input
                                ref="nameInputRef"
                                v-model="form.name"
                                type="text"
                                class="mt-3 w-full rounded-2xl border border-black/10 bg-white px-4 py-3 text-[#2E2E2E] shadow-sm outline-none focus:border-[#6B8C68] focus:ring-2 focus:ring-[#6B8C68]/20"
                            />
                            <p v-if="form.errors.name" class="mt-2 text-sm text-red-600">{{ form.errors.name }}</p>
                        </div>

                        <div class="mt-5">
                            <label class="text-sm font-semibold tracking-widest text-[#2E2E2E]/70 uppercase">
                                Kogus
                            </label>
                            <input
                                v-model="form.amount_text"
                                type="text"
                                placeholder="nt 1 tk või 2 liitrit"
                                class="mt-3 w-full rounded-2xl border border-black/10 bg-white px-4 py-3 text-[#2E2E2E] shadow-sm outline-none focus:border-[#6B8C68] focus:ring-2 focus:ring-[#6B8C68]/20"
                            />
                            <p v-if="form.errors.amount_text" class="mt-2 text-sm text-red-600">{{ form.errors.amount_text }}</p>
                        </div>

                        <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="text-sm font-semibold tracking-widest text-[#2E2E2E]/70 uppercase">
                                    Ostmise aasta
                                </label>
                                <input
                                    v-model="form.year"
                                    type="number"
                                    max="2100"
                                    class="mt-3 w-full rounded-2xl border border-black/10 bg-white px-4 py-3 text-[#2E2E2E] shadow-sm outline-none focus:border-[#6B8C68] focus:ring-2 focus:ring-[#6B8C68]/20"
                                    @focus="markEmptyOnFocus('year')"
                                    @input="normalizeSpinnerStart('year')"
                                    @keydown.up="startYearFrom2020('year')"
                                    @keydown.down="startYearFrom2020('year')"
                                />
                                <p v-if="form.errors.year" class="mt-2 text-sm text-red-600">{{ form.errors.year }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-semibold tracking-widest text-[#2E2E2E]/70 uppercase">
                                    Aegumisaasta
                                </label>
                                <input
                                    v-model="form.expires_at"
                                    type="number"
                                    max="2100"
                                    class="mt-3 w-full rounded-2xl border border-black/10 bg-white px-4 py-3 text-[#2E2E2E] shadow-sm outline-none focus:border-[#6B8C68] focus:ring-2 focus:ring-[#6B8C68]/20"
                                    @focus="markEmptyOnFocus('expires_at')"
                                    @input="normalizeSpinnerStart('expires_at')"
                                    @keydown.up="startYearFrom2020('expires_at')"
                                    @keydown.down="startYearFrom2020('expires_at')"
                                />
                                <p v-if="form.errors.expires_at" class="mt-2 text-sm text-red-600">{{ form.errors.expires_at }}</p>
                            </div>
                        </div>

                        <div class="mt-5">
                            <label class="text-sm font-semibold tracking-widest text-[#2E2E2E]/70 uppercase">
                                Märkused
                            </label>
                            <textarea
                                v-model="form.notes"
                                rows="4"
                                class="mt-3 w-full rounded-2xl border border-black/10 bg-white px-4 py-3 text-[#2E2E2E] shadow-sm outline-none focus:border-[#6B8C68] focus:ring-2 focus:ring-[#6B8C68]/20"
                            />
                            <p v-if="form.errors.notes" class="mt-2 text-sm text-red-600">{{ form.errors.notes }}</p>
                        </div>

                        <div class="mt-5">
                            <label class="text-sm font-semibold tracking-widest text-[#2E2E2E]/70 uppercase">Pilt</label>
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
                                <template v-if="hasImage && previewUrl">
                                    <img :src="previewUrl" alt="Eelvaade" class="mx-auto max-h-40 rounded-xl object-cover" />
                                    <p class="mt-2 text-sm text-[#2E2E2E]/60">Vajuta, et pilti vahetada</p>
                                </template>
                                <template v-else>
                                    <span class="material-symbols-outlined text-5xl text-[#6B8C68]">add_a_photo</span>
                                    <p class="mt-2 text-sm text-[#2E2E2E]/70">Lisa seemne pilt</p>
                                </template>
                            </button>
                            <p v-if="form.errors.image" class="mt-2 text-sm text-red-600">{{ form.errors.image }}</p>
                        </div>

                        <div class="sticky bottom-0 mt-6 flex flex-col gap-3 bg-[#FAF8F4] pt-4 pb-1">
                            <button
                                type="button"
                                class="rounded-2xl bg-[#6B8C68] px-4 py-3 font-medium text-white transition hover:bg-[#4F6A52] disabled:opacity-60"
                                :disabled="form.processing || !form.name.trim()"
                                @click="submit"
                            >
                                {{ form.processing ? 'Salvestan...' : 'Salvesta muudatused' }}
                            </button>
                            <button
                                type="button"
                                class="text-sm text-[#2E2E2E]/60 hover:text-[#2E2E2E]"
                                :disabled="form.processing"
                                @click="close"
                            >
                                Tühista
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </Teleport>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 150ms ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
