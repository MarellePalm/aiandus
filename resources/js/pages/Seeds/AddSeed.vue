<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

import SaveButton from '@/components/SaveButton.vue';

type Category = {
    id: number;
    name: string;
};

const props = withDefaults(
    defineProps<{
        open?: boolean;
        initialCategoryId?: number | null;
        categories?: Category[];
        standalone?: boolean;
    }>(),
    { open: false, standalone: false },
);

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'created'): void;
}>();

const close = () => emit('update:open', false);

const isStandalone = computed(() => props.standalone === true);

const form = useForm<{
    category_id: number | null;
    name: string;
    amount_text: string;
    year: string;
    expires_at: string;
    notes: string;
    image: File | null;
}>({
    category_id: props.initialCategoryId ?? props.categories?.[0]?.id ?? null,
    name: '',
    amount_text: '',
    year: '',
    expires_at: '',
    notes: '',
    image: null,
});

const nameInputRef = ref<HTMLInputElement | null>(null);
const categorySelectRef = ref<HTMLSelectElement | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);
const previewUrl = ref<string | null>(null);

const hasImage = computed(() => !!form.image);

const revokePreview = () => {
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
    previewUrl.value = null;
};

const setFile = (file: File | null) => {
    revokePreview();
    form.image = file;
    if (file) previewUrl.value = URL.createObjectURL(file);
};

const onFileChange = (e: Event) => {
    const input = e.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    if (file && !file.type.startsWith('image/')) return;
    setFile(file);
    input.value = '';
};

const openPicker = () => fileInputRef.value?.click();

const emptyOnFocus = ref<Record<'year', boolean>>({
    year: false,
});

const markEmptyOnFocus = (field: 'year') => {
    emptyOnFocus.value[field] = !form[field]?.trim();
};

const normalizeSpinnerStart = (field: 'year') => {
    if (!emptyOnFocus.value[field]) return;

    if (form[field] === '1' || form[field] === '-1' || form[field] === '0') {
        form[field] = '2020';
    }

    emptyOnFocus.value[field] = false;
};

const startYearFrom2020 = (field: 'year') => {
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

const reset = () => {
    form.reset();
    form.clearErrors();
    revokePreview();
    form.category_id =
        props.initialCategoryId ?? props.categories?.[0]?.id ?? null;
};

watch(
    () => props.open,
    async (open) => {
        if (open) {
            document.body.style.overflow = 'hidden';
            await nextTick();
            nameInputRef.value?.focus();
        } else {
            document.body.style.overflow = '';
            reset();
        }
    },
    { immediate: true },
);

watch(
    () => props.initialCategoryId,
    (next) => {
        form.category_id = next ?? props.categories?.[0]?.id ?? null;
    },
);

const isEmptyValue = (v: unknown) => v === '' || v === null || v === undefined;

const shouldNormalizeTo2020 = (v: unknown) => {
    const n = Number(v);
    return Number.isFinite(n) && (n === 1 || n === 0 || n === -1);
};

watch(
    () => form.year,
    (value, previous) => {
        if (isEmptyValue(previous) && shouldNormalizeTo2020(value)) {
            form.year = '2020';
        }
    },
);

const submit = () => {
    form.post('/seeds', {
        forceFormData: true,
        onSuccess: () => {
            emit('created');
            if (isStandalone.value) {
                router.visit('/seeds');
            } else {
                close();
            }
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
    <!-- Lehe režiim: /seeds/create -->
    <template v-if="isStandalone">
        <Head title="Lisa varu" />
        <div
            class="font-display min-h-screen bg-background text-foreground antialiased"
        >
            <div
                class="fixed inset-0 z-50 flex items-start justify-center p-4 pt-6 sm:items-center sm:pt-4"
            >
                <Link
                    href="/seeds"
                    class="absolute inset-0 bg-black/30 backdrop-blur-[2px]"
                    aria-label="Sulge"
                />
                <div
                    class="relative max-h-[92vh] w-full max-w-lg overflow-hidden rounded-3xl bg-card shadow-xl ring-1 ring-border"
                >
                    <div class="max-h-[92vh] overflow-y-auto p-5 sm:p-6">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h1
                                    class="text-lg font-semibold text-foreground"
                                >
                                    Lisa varu
                                </h1>
                                <p class="mt-1 mb-5 text-sm text-foreground/70">
                                    Lisa nimi, kogus, pilt, ostmisaasta ja
                                    aegumise kuupäev.
                                </p>
                            </div>
                            <Link
                                href="/seeds"
                                class="rounded-full p-2 text-foreground/60 hover:bg-black/5 hover:text-foreground"
                                aria-label="Sulge"
                            >
                                ✕
                            </Link>
                        </div>

                        <div class="mt-5">
                            <label
                                class="text-sm font-semibold tracking-widest text-foreground/70 uppercase"
                                >Nimi</label
                            >
                            <input
                                ref="nameInputRef"
                                v-model="form.name"
                                type="text"
                                placeholder="nt Tomat"
                                class="mt-3 w-full rounded-2xl border border-border bg-background px-4 py-3 text-foreground shadow-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                            />
                            <p
                                v-if="form.errors.name"
                                class="mt-2 text-sm text-red-600"
                            >
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <div class="mt-5">
                            <label
                                class="text-sm font-semibold tracking-widest text-foreground/70 uppercase"
                                >Kategooria</label
                            >
                            <select
                                ref="categorySelectRef"
                                v-model="form.category_id"
                                class="mt-3 w-full rounded-2xl border border-border bg-background px-4 py-3 text-foreground shadow-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                            >
                                <option :value="null" disabled>
                                    Vali kategooria...
                                </option>
                                <option
                                    v-for="c in props.categories ?? []"
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

                        <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label
                                    class="text-sm font-semibold tracking-widest text-foreground/70 uppercase"
                                    >Ostmise aasta</label
                                >
                                <input
                                    v-model="form.year"
                                    type="number"
                                    max="2100"
                                    class="mt-3 w-full rounded-2xl border border-border bg-background px-4 py-3 text-foreground shadow-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                                    @focus="markEmptyOnFocus('year')"
                                    @input="normalizeSpinnerStart('year')"
                                    @keydown.up="startYearFrom2020('year')"
                                    @keydown.down="startYearFrom2020('year')"
                                />
                                <p
                                    v-if="form.errors.year"
                                    class="mt-2 text-sm text-red-600"
                                >
                                    {{ form.errors.year }}
                                </p>
                            </div>
                            <div>
                                <label
                                    class="text-sm font-semibold tracking-widest text-foreground/70 uppercase"
                                    >Aegub</label
                                >
                                <input
                                    v-model="form.expires_at"
                                    type="date"
                                    lang="et-EE"
                                    class="mt-3 w-full rounded-2xl border border-border bg-background px-4 py-3 text-foreground shadow-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                                    @change="form.clearErrors('expires_at')"
                                    @click="
                                        (
                                            $event.target as HTMLInputElement
                                        ).showPicker?.()
                                    "
                                />
                                <p
                                    v-if="form.errors.expires_at"
                                    class="mt-2 text-sm text-red-600"
                                >
                                    {{ form.errors.expires_at }}
                                </p>
                                <p class="mt-2 text-xs text-foreground/50">
                                    Kuupäeva formaat: (PP.KK.AAAA), nt 05.09.2026
                                </p>
                            </div>
                        </div>

                        <div class="mt-5">
                            <label
                                class="text-sm font-semibold tracking-widest text-foreground/70 uppercase"
                                >Kogus</label
                            >
                            <input
                                v-model="form.amount_text"
                                type="text"
                                placeholder="nt 1 tk või 2 liitrit"
                                class="mt-3 w-full rounded-2xl border border-border bg-background px-4 py-3 text-foreground shadow-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                            />
                            <p
                                v-if="form.errors.amount_text"
                                class="mt-2 text-sm text-red-600"
                            >
                                {{ form.errors.amount_text }}
                            </p>
                        </div>

                        <div class="mt-5">
                            <label
                                class="text-sm font-semibold tracking-widest text-foreground/70 uppercase"
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
                                class="mt-3 w-full rounded-2xl border-2 border-dashed border-border bg-background px-6 py-8 text-center hover:bg-muted/50"
                                @click="openPicker"
                            >
                                <template v-if="!hasImage">
                                    <img
                                        src="/logo.png"
                                        alt=""
                                        class="mx-auto h-20 w-auto object-contain opacity-90"
                                    />
                                    <p class="mt-2 text-sm text-foreground/70">
                                        Lisa varu pilt
                                    </p>
                                </template>
                                <template v-else>
                                    <img
                                        v-if="previewUrl"
                                        :src="previewUrl"
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

                        <div class="mt-5">
                            <label
                                class="text-sm font-semibold tracking-widest text-foreground/70 uppercase"
                                >Märkused</label
                            >
                            <textarea
                                v-model="form.notes"
                                rows="4"
                                placeholder="Soovi korral lisa meelespea või lisainfo..."
                                class="mt-3 w-full rounded-2xl border border-border bg-background px-4 py-3 text-foreground shadow-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                            />
                            <p
                                v-if="form.errors.notes"
                                class="mt-2 text-sm text-red-600"
                            >
                                {{ form.errors.notes }}
                            </p>
                        </div>

                        <div class="mt-6 flex flex-col gap-3">
                            <SaveButton
                                type="button"
                                :disabled="form.processing || !form.name.trim()"
                                @click="submit"
                            />
                            <Link
                                href="/seeds"
                                class="text-center text-sm text-foreground/60 hover:text-foreground"
                            >
                                Tühista
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>

    <!-- Modaal -->
    <Teleport v-else to="body">
        <transition name="fade">
            <div
                v-if="props.open"
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

                <div
                    class="relative max-h-[92vh] w-full max-w-lg overflow-hidden rounded-3xl bg-card shadow-xl ring-1 ring-border"
                >
                    <div class="max-h-[92vh] overflow-y-auto p-5 sm:p-6">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3
                                    class="text-lg font-semibold text-foreground"
                                >
                                    Lisa varu
                                </h3>
                                <p class="mt-1 text-sm text-foreground/70">
                                    Lisa nimi, kogus, pilt, ostmisaasta ja
                                    aegumise kuupäev.
                                </p>
                            </div>
                            <button
                                type="button"
                                class="rounded-full p-2 text-foreground/60 hover:bg-black/5 hover:text-foreground"
                                @click="close"
                                aria-label="Sulge"
                            >
                                ✕
                            </button>
                        </div>

                        <div class="mt-5">
                            <label
                                class="text-sm font-semibold tracking-widest text-foreground/70 uppercase"
                            >
                                Nimi
                            </label>
                            <input
                                ref="nameInputRef"
                                v-model="form.name"
                                type="text"
                                placeholder="nt Tomat"
                                class="mt-3 w-full rounded-2xl border border-border bg-background px-4 py-3 text-foreground shadow-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                            />
                            <p
                                v-if="form.errors.name"
                                class="mt-2 text-sm text-red-600"
                            >
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <div class="mt-5">
                            <label
                                class="text-sm font-semibold tracking-widest text-foreground/70 uppercase"
                            >
                                Kategooria
                            </label>
                            <select
                                ref="categorySelectRef"
                                v-model="form.category_id"
                                class="mt-3 w-full rounded-2xl border border-border bg-background px-4 py-3 text-foreground shadow-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                            >
                                <option :value="null" disabled>
                                    Vali kategooria...
                                </option>
                                <option
                                    v-for="c in props.categories ?? []"
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

                        <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label
                                    class="text-sm font-semibold tracking-widest text-foreground/70 uppercase"
                                >
                                    Ostmise aasta
                                </label>
                                <input
                                    v-model="form.year"
                                    type="number"
                                    max="2100"
                                    class="mt-3 w-full rounded-2xl border border-border bg-background px-4 py-3 text-foreground shadow-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                                    @focus="markEmptyOnFocus('year')"
                                    @input="normalizeSpinnerStart('year')"
                                    @keydown.up="startYearFrom2020('year')"
                                    @keydown.down="startYearFrom2020('year')"
                                />
                                <p
                                    v-if="form.errors.year"
                                    class="mt-2 text-sm text-red-600"
                                >
                                    {{ form.errors.year }}
                                </p>
                            </div>
                            <div>
                                <label
                                    class="text-sm font-semibold tracking-widest text-foreground/70 uppercase"
                                >
                                    Aegub
                                </label>
                                <input
                                    v-model="form.expires_at"
                                    type="date"
                                    lang="et-EE"
                                    class="mt-3 w-full rounded-2xl border border-border bg-background px-4 py-3 text-foreground shadow-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                                    @change="form.clearErrors('expires_at')"
                                    @click="
                                        (
                                            $event.target as HTMLInputElement
                                        ).showPicker?.()
                                    "
                                />
                                <p
                                    v-if="form.errors.expires_at"
                                    class="mt-2 text-sm text-red-600"
                                >
                                    {{ form.errors.expires_at }}
                                </p>
                                <p class="mt-2 text-xs text-foreground/50">
                                    Kuupäeva formaat: (PP.KK.AAAA), nt 05.09.2026
                                </p>
                            </div>
                        </div>

                        <div class="mt-5">
                            <label
                                class="text-sm font-semibold tracking-widest text-foreground/70 uppercase"
                            >
                                Kogus
                            </label>
                            <input
                                v-model="form.amount_text"
                                type="text"
                                placeholder="nt 1 tk või 2 liitrit"
                                class="mt-3 w-full rounded-2xl border border-border bg-background px-4 py-3 text-foreground shadow-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                            />
                            <p
                                v-if="form.errors.amount_text"
                                class="mt-2 text-sm text-red-600"
                            >
                                {{ form.errors.amount_text }}
                            </p>
                        </div>

                        <div class="mt-5">
                            <label
                                class="text-sm font-semibold tracking-widest text-foreground/70 uppercase"
                            >
                                Pilt
                            </label>
                            <input
                                ref="fileInputRef"
                                type="file"
                                accept="image/*"
                                class="hidden"
                                @change="onFileChange"
                            />
                            <button
                                type="button"
                                class="mt-3 w-full rounded-2xl border-2 border-dashed border-border bg-background px-6 py-8 text-center hover:bg-muted/50"
                                @click="openPicker"
                            >
                                <template v-if="!hasImage">
                                    <img
                                        src="/logo.png"
                                        alt=""
                                        class="mx-auto h-20 w-auto object-contain opacity-90"
                                    />
                                    <p class="mt-2 text-sm text-foreground/70">
                                        Lisa varu pilt
                                    </p>
                                </template>
                                <template v-else>
                                    <img
                                        v-if="previewUrl"
                                        :src="previewUrl"
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

                        <div class="mt-5">
                            <label
                                class="text-sm font-semibold tracking-widest text-foreground/70 uppercase"
                            >
                                Märkused
                            </label>
                            <textarea
                                v-model="form.notes"
                                rows="4"
                                placeholder="Soovi korral lisa meelespea või lisainfo..."
                                class="mt-3 w-full rounded-2xl border border-border bg-background px-4 py-3 text-foreground shadow-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                            />
                            <p
                                v-if="form.errors.notes"
                                class="mt-2 text-sm text-red-600"
                            >
                                {{ form.errors.notes }}
                            </p>
                        </div>

                        <div
                            class="sticky bottom-0 mt-6 flex flex-col gap-3 bg-card pt-4 pb-1"
                        >
                            <SaveButton
                                type="button"
                                :disabled="form.processing || !form.name.trim()"
                                @click="submit"
                            />
                            <button
                                type="button"
                                class="text-sm text-foreground/60 hover:text-foreground"
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
