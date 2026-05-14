<!-- resources/js/Pages/Calendar/NoteForm.vue -->
<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import LocalDatePicker from '@/components/LocalDatePicker.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { normalizeImageForUpload } from '@/lib/imageUpload';
import { calendar } from '@/routes';
import { type BreadcrumbItem } from '@/types';

type ReminderPreset =
    | 'none'
    | 'same_day_morning'
    | 'day_before_evening'
    | 'custom';

const props = defineProps<{
    note?: {
        id: number;
        note_date: string;
        title: string | null;
        body: string;
        media_urls?: string[];
        due_date?: string | null;
        due_time?: string | null;
        bed_id?: number | null;
        plant_id?: number | null;
    };
    beds?: { id: number; name: string; location?: string | null }[];
    plants?: { id: number; label: string; image_url?: string | null }[];
    editMode?: boolean;
    initialBedId?: number | null;
    initialDate?: string | null;
    returnTo?: string | null;
}>();

const calendarUrl = calendar().url;
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Kalender', href: calendarUrl },
    {
        title: props.editMode ? 'Muuda märget' : 'Lisa märge',
        href: props.editMode ? '#' : '/calendar/note-form',
    },
];

const initialNoteDate =
    props.note?.note_date ??
    props.initialDate ??
    new Date().toISOString().slice(0, 10);

const form = useForm<{
    note_date: string;
    title: string;
    body: string;
    type: 'note';

    due_date: string;
    due_time: string;

    bed_id: number | null;
    plant_id: number | null;

    photos: File[];
}>({
    note_date: initialNoteDate,
    title: props.note?.title ?? '',
    body: props.note?.body ?? '',
    type: 'note',

    due_date: props.note?.due_date ?? initialNoteDate,
    due_time: props.note?.due_time ?? '09:00',

    bed_id: props.note?.bed_id ?? props.initialBedId ?? null,
    plant_id: props.note?.plant_id ?? null,

    photos: [],
});

const reminderPreset = ref<ReminderPreset>(
    props.note?.due_date || props.note?.due_time ? 'custom' : 'none',
);

const reminderOptions: { value: ReminderPreset; label: string }[] = [
    { value: 'none', label: 'Ei soovi meeldetuletust' },
    { value: 'same_day_morning', label: 'Sama päeva hommikul (09:00)' },
    { value: 'day_before_evening', label: 'Eelmisel õhtul (18:00)' },
    { value: 'custom', label: 'Määra ise' },
];

const showCustomReminderFields = computed(
    () => reminderPreset.value === 'custom',
);

function shiftDays(isoDate: string, delta: number): string {
    const d = new Date(`${isoDate}T00:00:00`);
    d.setDate(d.getDate() + delta);
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
}

function applyReminderPreset() {
    if (reminderPreset.value === 'none') {
        form.due_date = '';
        form.due_time = '';
        return;
    }
    if (reminderPreset.value === 'same_day_morning') {
        form.due_date = form.note_date;
        form.due_time = '09:00';
        return;
    }
    if (reminderPreset.value === 'day_before_evening') {
        form.due_date = shiftDays(form.note_date, -1);
        form.due_time = '18:00';
    }
}

const photoPreviews = ref<string[]>([]);

async function onPhotosChange(e: Event) {
    const input = e.target as HTMLInputElement;
    const files = input.files ? Array.from(input.files) : [];
    photoPreviews.value.forEach((url) => URL.revokeObjectURL(url));
    const normalizedFiles = await Promise.all(
        files.map((file) => normalizeImageForUpload(file)),
    );
    form.photos = normalizedFiles;
    photoPreviews.value = normalizedFiles.map((f) => URL.createObjectURL(f));
}

function removePhoto(index: number) {
    form.photos = form.photos.filter((_, i) => i !== index);
    if (photoPreviews.value[index])
        URL.revokeObjectURL(photoPreviews.value[index]);
    photoPreviews.value = photoPreviews.value.filter((_, i) => i !== index);
}

function submit() {
    applyReminderPreset();
    const onSuccess = () => {
        form.reset('title', 'body', 'photos');
        photoPreviews.value = [];
        const fallback = `/calendar?month=${new Date(form.note_date).getMonth() + 1}&year=${new Date(form.note_date).getFullYear()}`;
        router.visit(props.returnTo || fallback);
    };
    if (props.editMode && props.note) {
        form.transform((data) => ({ ...data, _method: 'PUT' }));
        form.post(`/calendar/notes/${props.note.id}`, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess,
        });
    } else {
        form.post('/calendar/notes', {
            preserveScroll: true,
            forceFormData: true,
            onSuccess,
        });
    }
}

function cancel() {
    router.visit(props.returnTo || '/calendar');
}
</script>

<template>
    <Head :title="props.editMode ? 'Muuda märget' : 'Lisa märge'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="font-display min-h-screen bg-background text-foreground antialiased"
        >
            <div
                class="fixed inset-0 z-50 flex items-start justify-center p-4 pt-6 sm:items-center sm:pt-4"
            >
                <button
                    type="button"
                    class="absolute inset-0 bg-black/30 backdrop-blur-[2px]"
                    aria-label="Sulge"
                    @click="cancel"
                />

                <div
                    class="relative max-h-[92vh] w-full max-w-2xl overflow-hidden rounded-3xl bg-card shadow-xl ring-1 ring-border"
                >
                    <div class="max-h-[92vh] overflow-y-auto p-5 sm:p-6">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3
                                    class="text-lg font-semibold text-foreground"
                                >
                                    {{
                                        props.editMode
                                            ? 'Muuda märget'
                                            : 'Lisa märge'
                                    }}
                                </h3>
                                <p class="mt-1 text-sm text-foreground/70">
                                    {{
                                        props.editMode
                                            ? 'Uuenda märkme sisu.'
                                            : 'Lisa märkus või meeldetuletus.'
                                    }}
                                </p>
                            </div>
                            <button
                                type="button"
                                class="rounded-full p-2 text-foreground/60 hover:bg-black/5 hover:text-foreground"
                                @click="cancel"
                                aria-label="Sulge"
                            >
                                ✕
                            </button>
                        </div>

                        <form class="mt-5 space-y-6" @submit.prevent="submit">
                            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                                <div>
                                    <label
                                        class="text-sm font-semibold tracking-widest text-foreground/70 uppercase"
                                        for="note_date"
                                        >Kuupäev</label
                                    >
                                    <LocalDatePicker
                                        id="note_date"
                                        v-model="form.note_date"
                                        placeholder="pp.kk.aaaa"
                                        @update:model-value="
                                            form.clearErrors('note_date')
                                        "
                                    />
                                    <p class="mt-1 text-xs text-foreground/50">
                                        Kuupäeva formaat: (PP.KK.AAAA), nt
                                        05.09.2026
                                    </p>
                                    <p
                                        v-if="form.errors.note_date"
                                        class="mt-1 text-xs text-red-600 dark:text-red-400"
                                    >
                                        {{ form.errors.note_date }}
                                    </p>
                                </div>

                                <div>
                                    <label
                                        class="text-sm font-semibold tracking-widest text-foreground/70 uppercase"
                                        for="title"
                                        >Pealkiri</label
                                    >
                                    <div class="relative">
                                        <input
                                            id="title"
                                            v-model="form.title"
                                            type="text"
                                            placeholder="nt. Väetasin tomatid"
                                            class="mt-3 w-full rounded-2xl border border-border bg-background px-4 py-3 pr-12 text-foreground shadow-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                                            autocomplete="off"
                                        />
                                        <span
                                            class="material-symbols-outlined absolute top-1/2 right-4 -translate-y-1/2 text-muted-foreground"
                                            aria-hidden="true"
                                        >
                                            label
                                        </span>
                                    </div>
                                    <p
                                        v-if="form.errors.title"
                                        class="mt-1 text-xs text-red-600 dark:text-red-400"
                                    >
                                        {{ form.errors.title }}
                                    </p>
                                </div>
                            </div>

                            <div>
                                <label
                                    class="text-sm font-semibold tracking-widest text-foreground/70 uppercase"
                                    for="body"
                                    >Märkme sisu</label
                                >
                                <div class="relative">
                                    <textarea
                                        id="body"
                                        v-model="form.body"
                                        rows="7"
                                        placeholder="Kirjelda lähemalt oma tegevusi või tähelepanekuid..."
                                        class="mt-3 w-full rounded-2xl border border-border bg-background px-4 py-3 pr-12 text-foreground shadow-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                                    />
                                    <span
                                        class="material-symbols-outlined absolute top-4 right-4 text-muted-foreground"
                                        aria-hidden="true"
                                    >
                                        edit_note
                                    </span>
                                </div>
                                <p
                                    v-if="form.errors.body"
                                    class="mt-1 text-xs text-red-600 dark:text-red-400"
                                >
                                    {{ form.errors.body }}
                                </p>
                            </div>

                            <div>
                                <label
                                    class="text-sm font-semibold tracking-widest text-foreground/70 uppercase"
                                    >Meeldetuletus</label
                                >
                                <div class="mt-3 grid grid-cols-2 gap-2">
                                    <button
                                        v-for="opt in reminderOptions"
                                        :key="opt.value"
                                        type="button"
                                        class="rounded-2xl border px-3 py-2.5 text-left text-sm transition"
                                        :class="
                                            reminderPreset === opt.value
                                                ? 'border-primary bg-primary/8 font-semibold text-primary ring-1 ring-primary/30'
                                                : 'border-border bg-background text-foreground/70 hover:border-primary/30 hover:bg-muted/40'
                                        "
                                        @click="
                                            reminderPreset = opt.value;
                                            applyReminderPreset();
                                        "
                                    >
                                        {{ opt.label }}
                                    </button>
                                </div>
                            </div>

                            <div
                                v-if="showCustomReminderFields"
                                class="grid grid-cols-1 gap-4 sm:grid-cols-2"
                            >
                                <div>
                                    <label
                                        class="text-sm font-semibold tracking-widest text-foreground/70 uppercase"
                                        for="due_date"
                                        >Meeldetuletuse kuupäev</label
                                    >
                                    <LocalDatePicker
                                        id="due_date"
                                        v-model="form.due_date"
                                        placeholder="pp.kk.aaaa"
                                        @update:model-value="
                                            form.clearErrors('due_date')
                                        "
                                    />
                                    <p class="mt-1 text-xs text-foreground/50">
                                        Kuupäeva formaat: (PP.KK.AAAA), nt
                                        05.09.2026
                                    </p>
                                    <p
                                        v-if="form.errors.due_date"
                                        class="mt-1 text-xs text-red-600 dark:text-red-400"
                                    >
                                        {{ form.errors.due_date }}
                                    </p>
                                </div>
                                <div>
                                    <label
                                        class="text-sm font-semibold tracking-widest text-foreground/70 uppercase"
                                        for="due_time"
                                        >Meeldetuletus kell</label
                                    >
                                    <input
                                        id="due_time"
                                        v-model="form.due_time"
                                        type="time"
                                        class="mt-3 w-full rounded-2xl border border-border bg-background px-4 py-3 text-foreground shadow-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                                    />
                                    <p
                                        v-if="form.errors.due_time"
                                        class="mt-1 text-xs text-red-600 dark:text-red-400"
                                    >
                                        {{ form.errors.due_time }}
                                    </p>
                                </div>
                            </div>

                            <!-- Photos -->
                            <div>
                                <label
                                    class="text-sm font-semibold tracking-widest text-foreground/70 uppercase"
                                    >Fotod</label
                                >
                                <input
                                    type="file"
                                    accept="image/*"
                                    multiple
                                    class="mt-3 w-full rounded-2xl border border-border bg-background px-4 py-3 text-sm text-foreground shadow-sm outline-none file:mr-3 file:rounded-full file:border-0 file:bg-primary/10 file:px-4 file:py-2 file:font-medium file:text-primary"
                                    @change="onPhotosChange"
                                />
                                <p
                                    v-if="form.errors.photos"
                                    class="mt-1 text-xs text-red-600 dark:text-red-400"
                                >
                                    {{ form.errors.photos }}
                                </p>
                                <div
                                    v-if="props.note?.media_urls?.length"
                                    class="mt-3 flex flex-wrap gap-2"
                                >
                                    <img
                                        v-for="(url, i) in props.note
                                            .media_urls"
                                        :key="'existing-' + i"
                                        :src="url"
                                        :alt="`Olemasolev foto ${i + 1}`"
                                        class="h-20 w-20 rounded-lg border border-border object-cover"
                                    />
                                </div>
                                <div
                                    v-if="photoPreviews.length"
                                    class="mt-3 flex flex-wrap gap-2"
                                >
                                    <div
                                        v-for="(url, i) in photoPreviews"
                                        :key="'new-' + i"
                                        class="group relative"
                                    >
                                        <img
                                            :src="url"
                                            :alt="`Uus foto ${i + 1}`"
                                            class="h-20 w-20 rounded-lg border border-border object-cover"
                                        />
                                        <button
                                            type="button"
                                            class="absolute -top-1.5 -right-1.5 flex size-6 items-center justify-center rounded-full bg-destructive text-destructive-foreground opacity-90 shadow group-hover:opacity-100"
                                            aria-label="Eemalda foto"
                                            @click="removePhoto(i)"
                                        >
                                            <span
                                                class="material-symbols-outlined text-sm"
                                                >close</span
                                            >
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Bed + Plant (no duplicate frequency here) -->
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label
                                        class="text-sm font-semibold tracking-widest text-foreground/70 uppercase"
                                        for="bed"
                                        >Seo peenraga</label
                                    >
                                    <select
                                        id="bed"
                                        v-model.number="form.bed_id"
                                        class="mt-3 w-full rounded-2xl border border-border bg-background px-4 py-3 text-foreground shadow-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                                    >
                                        <option :value="null">
                                            Ei ole seotud
                                        </option>
                                        <option
                                            v-for="bed in props.beds ?? []"
                                            :key="bed.id"
                                            :value="bed.id"
                                        >
                                            {{ bed.name }}
                                        </option>
                                    </select>
                                    <p
                                        v-if="form.errors.bed_id"
                                        class="mt-1 text-xs text-red-600 dark:text-red-400"
                                    >
                                        {{ form.errors.bed_id }}
                                    </p>
                                </div>

                                <div>
                                    <p
                                        id="plant-label"
                                        class="text-sm font-semibold tracking-widest text-foreground/70 uppercase"
                                    >
                                        Seo taimega
                                    </p>
                                    <div
                                        class="mt-3 max-h-52 space-y-2 overflow-y-auto rounded-2xl border border-border bg-background p-2 shadow-sm"
                                        role="listbox"
                                        aria-labelledby="plant-label"
                                    >
                                        <button
                                            type="button"
                                            role="option"
                                            :aria-selected="form.plant_id === null"
                                            class="flex w-full items-center gap-3 rounded-xl border px-3 py-2.5 text-left text-sm transition outline-none focus-visible:ring-2 focus-visible:ring-primary/30"
                                            :class="
                                                form.plant_id === null
                                                    ? 'border-primary bg-primary/10 text-foreground'
                                                    : 'border-transparent hover:bg-muted/60'
                                            "
                                            @click="form.plant_id = null"
                                        >
                                            <span
                                                class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground"
                                            >
                                                <span
                                                    class="material-symbols-outlined text-xl"
                                                    >close</span
                                                >
                                            </span>
                                            <span class="font-medium"
                                                >Ei ole seotud</span
                                            >
                                        </button>
                                        <button
                                            v-for="plant in props.plants ?? []"
                                            :key="plant.id"
                                            type="button"
                                            role="option"
                                            :aria-selected="form.plant_id === plant.id"
                                            class="flex w-full items-center gap-3 rounded-xl border px-3 py-2.5 text-left text-sm transition outline-none focus-visible:ring-2 focus-visible:ring-primary/30"
                                            :class="
                                                form.plant_id === plant.id
                                                    ? 'border-primary bg-primary/10 text-foreground'
                                                    : 'border-transparent hover:bg-muted/60'
                                            "
                                            @click="form.plant_id = plant.id"
                                        >
                                            <img
                                                :src="
                                                    plant.image_url ||
                                                    '/logo.png'
                                                "
                                                :alt="plant.label"
                                                class="size-10 shrink-0 rounded-lg border border-border object-cover"
                                            />
                                            <span class="font-medium">{{
                                                plant.label
                                            }}</span>
                                        </button>
                                    </div>
                                    <p
                                        v-if="form.errors.plant_id"
                                        class="mt-1 text-xs text-red-600 dark:text-red-400"
                                    >
                                        {{ form.errors.plant_id }}
                                    </p>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div
                                class="sticky bottom-0 mt-6 space-y-3 bg-card pt-4 pb-1"
                            >
                                <button
                                    type="submit"
                                    class="w-full rounded-2xl bg-primary px-4 py-3 font-medium text-primary-foreground transition hover:bg-primary/90 disabled:opacity-60"
                                    :disabled="form.processing"
                                >
                                    {{
                                        props.editMode
                                            ? 'Salvesta muudatused'
                                            : 'Salvesta märge'
                                    }}
                                </button>

                                <button
                                    type="button"
                                    class="w-full rounded-2xl border border-border bg-background px-4 py-3 font-medium text-foreground/80 transition hover:bg-black/5"
                                    @click="cancel"
                                >
                                    Tühista
                                </button>

                                <p
                                    v-if="form.recentlySuccessful"
                                    class="text-center text-sm font-semibold text-primary"
                                >
                                    Salvestatud ✅
                                </p>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
