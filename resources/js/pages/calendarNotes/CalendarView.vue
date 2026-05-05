<!-- resources/js/Pages/Calendar.vue -->
<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

import BackIconButton from '@/components/BackIconButton.vue';
import CalendarSwitchTabs from '@/components/calendar/CalendarSwitchTabs.vue';
import DiaryHeader from '@/components/DiaryHeader.vue';
import FloatingPlusButton from '@/components/FloatingPlusButton.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import BottomNav from '@/pages/BottomNav.vue';
import { calendar } from '@/routes';
import { type BreadcrumbItem } from '@/types';

type Note = {
    id?: number | string;
    title?: string;
    body?: string;
    type?: string;
    done?: boolean;
    due_at?: string | null;
    reminder_enabled?: boolean;
    reminder_time?: string | null;
    media_urls?: string[];
};

const { month, year, notesByDate } = defineProps<{
    month: number;
    year: number;
    notesByDate: Record<string, Note[]>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Kalender', href: calendar().url },
];

const viewDate = ref(new Date(year, month - 1, 1));
const today = new Date();
const selectedDay = ref(
    today.getFullYear() === year && today.getMonth() === month - 1
        ? today.getDate()
        : 1,
);

function onAdd() {
    router.get('/calendar/note-form', { date: selectedISO.value });
}

const dayLabels = ['E', 'T', 'K', 'N', 'R', 'L', 'P'];

const monthTitle = computed(() => {
    const d = viewDate.value;
    return d.toLocaleDateString('et-EE', { month: 'long', year: 'numeric' });
});

function prevMonth() {
    const d = new Date(viewDate.value);
    d.setMonth(d.getMonth() - 1);
    viewDate.value = d;
    selectedDay.value = 1;
}

function nextMonth() {
    const d = new Date(viewDate.value);
    d.setMonth(d.getMonth() + 1);
    viewDate.value = d;
    selectedDay.value = 1;
}

const daysInMonth = computed(() => {
    const d = viewDate.value;
    return new Date(d.getFullYear(), d.getMonth() + 1, 0).getDate();
});

// Tühjad lahtrid enne kuu 1. päeva: Eesti nädal algab esmaspäevast (0=esmaspäev, 6=pühapäev)
const startOffset = computed(() => {
    const d = new Date(
        viewDate.value.getFullYear(),
        viewDate.value.getMonth(),
        1,
    );
    const day = d.getDay(); // JS: 0=pühapäev, 1=esmaspäev, ...
    return (day + 6) % 7; // esmaspäev=0 tühikuid, pühapäev=6 tühikuid
});

const markedDays = computed(() => {
    const s = new Set<number>();
    const obj = notesByDate || {};
    for (const key of Object.keys(obj)) {
        const d = new Date(key); // "YYYY-MM-DD"
        if (
            d.getFullYear() === viewDate.value.getFullYear() &&
            d.getMonth() === viewDate.value.getMonth()
        ) {
            s.add(d.getDate());
        }
    }
    return s;
});

type DayMarker = 'note' | 'reminder' | 'done';

function notesForDay(day: number): Note[] {
    const d = new Date(
        viewDate.value.getFullYear(),
        viewDate.value.getMonth(),
        day,
    );
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return notesByDate?.[`${yyyy}-${mm}-${dd}`] ?? [];
}

function markersForDay(day: number): DayMarker[] {
    const notes = notesForDay(day);
    const markers = new Set<DayMarker>();

    for (const note of notes) {
        if (note.done) {
            markers.add('done');
        }

        if (note.due_at || note.reminder_enabled || note.reminder_time) {
            markers.add('reminder');
        } else {
            markers.add('note');
        }
    }

    return Array.from(markers).slice(0, 3);
}

function markerClass(marker: DayMarker) {
    if (marker === 'reminder') return 'bg-primary';
    if (marker === 'done') return 'bg-emerald-500';
    return 'bg-stone-400';
}

const selectedDate = computed(() => {
    const d = viewDate.value;
    return new Date(d.getFullYear(), d.getMonth(), selectedDay.value);
});

const selectedISO = computed(() => {
    const d = selectedDate.value;
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
});

const selectedMonthLabel = computed(() =>
    selectedDate.value.toLocaleDateString('et-EE', {
        month: 'long',
        year: 'numeric',
    }),
);

const selectedWeekday = computed(() =>
    selectedDate.value.toLocaleDateString('et-EE', { weekday: 'long' }),
);

const selectedNotes = computed<Note[]>(
    () => notesByDate?.[selectedISO.value] ?? [],
);
type DayFeedItem = {
    key: string;
    kind: 'reminder' | 'note';
    note: Note;
};
const dayFeedItems = computed<DayFeedItem[]>(() =>
    selectedNotes.value
        .map((note, index) => ({
            key: `note-${String(note.id ?? index)}`,
            kind:
                note.due_at || note.reminder_enabled || note.reminder_time
                    ? 'reminder'
                    : 'note',
            note,
        }))
        .sort((a, b) => {
            if (a.kind !== b.kind) return a.kind === 'reminder' ? -1 : 1;

            const aTime = a.note.due_at ?? a.note.reminder_time ?? '';
            const bTime = b.note.due_at ?? b.note.reminder_time ?? '';
            return aTime.localeCompare(bTime);
        }),
);
const selectedTotal = computed(() => dayFeedItems.value.length);
const showCalendarEmptyHint = ref(false);
const CALENDAR_EMPTY_HINT_SEEN_KEY = 'calendarEmptyHintSeen';

function formatDueAt(iso: string) {
    const d = new Date(iso);
    const date = d.toLocaleDateString('et-EE', {
        day: 'numeric',
        month: 'numeric',
        year: 'numeric',
    });
    const time = d.toLocaleTimeString('et-EE', {
        hour: '2-digit',
        minute: '2-digit',
    });
    return `${date} kell ${time}`;
}

function selectDay(day: number) {
    selectedDay.value = day;
}

function previewText(text?: string, max = 120) {
    if (!text) return '';
    return text.length > max ? `${text.slice(0, max).trim()}...` : text;
}

onMounted(() => {
    try {
        const seen = localStorage.getItem(CALENDAR_EMPTY_HINT_SEEN_KEY) === '1';
        if (!seen) {
            showCalendarEmptyHint.value = true;
            localStorage.setItem(CALENDAR_EMPTY_HINT_SEEN_KEY, '1');
        }
    } catch {
        showCalendarEmptyHint.value = false;
    }
});
</script>

<template>
    <Head title="Kalender" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="page page-with-bottomnav">
            <div
                class="font-display min-h-screen bg-background text-foreground antialiased"
            >
                <div
                    class="border-beige/50 relative mx-auto min-h-screen w-full max-w-[480px] overflow-x-hidden border-x bg-background shadow-2xl md:mx-0 md:max-w-none md:border-0 md:shadow-none"
                >
                    <DiaryHeader
                        title="Kalender"
                        header-class="pt-6"
                        top-row-class="mb-3"
                        bottom-row-class="mb-4"
                    >
                        <template #leading>
                            <BackIconButton
                                href="/dashboard"
                                aria-label="Tagasi avalehele"
                            />
                        </template>
                        <div class="flex items-center justify-center pb-2">
                            <CalendarSwitchTabs active="day" />
                        </div>
                    </DiaryHeader>

                    <main
                        class="mx-auto flex-1 space-y-5 px-6 py-4 md:max-w-6xl md:px-8 lg:grid lg:grid-cols-[minmax(0,1.45fr)_minmax(320px,0.95fr)] lg:items-start lg:gap-6 lg:space-y-0"
                    >
                        <!-- Calendar card -->
                        <section class="card p-3.5 sm:p-4 lg:order-1">
                            <div class="mb-3 flex items-center justify-between">
                                <button
                                    type="button"
                                    class="icon-btn size-11"
                                    @click="prevMonth"
                                    aria-label="Eelmine kuu"
                                >
                                    <span class="material-symbols-outlined"
                                        >chevron_left</span
                                    >
                                </button>

                                <div class="text-center">
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                                    >
                                        Kalender
                                    </p>
                                    <h3 class="text-xl font-bold tracking-tight">
                                        {{ monthTitle }}
                                    </h3>
                                </div>

                                <button
                                    type="button"
                                    class="icon-btn size-11"
                                    @click="nextMonth"
                                    aria-label="Järgmine kuu"
                                >
                                    <span class="material-symbols-outlined"
                                        >chevron_right</span
                                    >
                                </button>
                            </div>

                            <div
                                class="mb-3 flex items-center justify-between rounded-2xl border border-border/60 bg-muted/25 px-3 py-2 lg:hidden"
                            >
                                <div>
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-muted-foreground uppercase"
                                    >
                                        Valitud päev
                                    </p>
                                    <p class="mt-0.5 text-sm font-medium text-foreground capitalize">
                                        {{ selectedWeekday }}, {{ selectedDay }}. {{ selectedMonthLabel }}
                                    </p>
                                </div>
                                <Link
                                    href="/calendar/overview"
                                    class="text-sm font-medium text-primary transition hover:text-primary/80"
                                >
                                    Kõik märkmed
                                </Link>
                            </div>

                            <div class="grid grid-cols-7 gap-1.5 sm:gap-2">
                                <div
                                    v-for="lbl in dayLabels"
                                    :key="lbl"
                                    class="pb-1 text-center text-[10px] font-bold tracking-[0.12em] text-primary/60"
                                >
                                    {{ lbl }}
                                </div>

                                <div
                                    v-for="n in startOffset"
                                    :key="'sp-' + n"
                                    class="h-11 sm:h-12"
                                />

                                <button
                                    v-for="day in daysInMonth"
                                    :key="day"
                                    type="button"
                                    class="relative flex h-12 flex-col items-center justify-between rounded-xl border border-transparent px-1 py-1.5 text-sm font-medium transition-colors sm:h-14"
                                    :class="[
                                        day === selectedDay
                                            ? 'leaf-shape border-primary/20 bg-primary font-bold text-primary-foreground shadow-md shadow-primary/20'
                                            : '',
                                        day !== selectedDay &&
                                        viewDate.getMonth() ===
                                            today.getMonth() &&
                                        viewDate.getFullYear() ===
                                            today.getFullYear() &&
                                        day === today.getDate()
                                            ? 'border-primary/35 bg-primary/5 text-foreground ring-1 ring-primary/25 ring-offset-1 ring-offset-background'
                                            : 'hover:border-border/70 hover:bg-muted/40',
                                    ]"
                                    @click="selectDay(day)"
                                    :aria-label="`Vali päev ${day}`"
                                >
                                    <span
                                        class="leading-none"
                                        :class="
                                            day === selectedDay
                                                ? 'text-primary-foreground'
                                                : 'text-foreground'
                                        "
                                    >
                                        {{ day }}
                                    </span>
                                    <div
                                        class="flex min-h-1.5 items-center justify-center gap-1"
                                        aria-hidden="true"
                                    >
                                        <span
                                            v-for="marker in markersForDay(day)"
                                            :key="`${day}-${marker}`"
                                            class="size-1.5 rounded-full"
                                            :class="[
                                                markerClass(marker),
                                                day === selectedDay
                                                    ? 'bg-primary-foreground/90'
                                                    : '',
                                            ]"
                                        />
                                        <span
                                            v-if="
                                                !markersForDay(day).length &&
                                                markedDays.has(day) &&
                                                day !== selectedDay
                                            "
                                            class="size-1.5 rounded-full bg-primary/55"
                                        />
                                    </div>
                                </button>
                            </div>
                        </section>

                        <!-- Valitud päev: ülesanded ja märkmed -->
                        <section
                            class="rounded-2xl border border-border bg-card/90 p-3.5 shadow-soft sm:p-4 lg:order-2 lg:sticky lg:top-6"
                        >
                            <div class="mb-3 hidden lg:block">
                                <div
                                    class="flex items-start justify-between gap-3 rounded-2xl border border-border/60 bg-muted/25 px-3 py-2.5"
                                >
                                    <div>
                                        <p
                                            class="text-[11px] font-semibold tracking-[0.14em] text-muted-foreground uppercase"
                                        >
                                            Valitud päev
                                        </p>
                                        <p
                                            class="mt-0.5 text-sm font-medium text-foreground capitalize"
                                        >
                                            {{ selectedWeekday }}, {{ selectedDay }}. {{ selectedMonthLabel }}
                                        </p>
                                        <p
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            {{ selectedTotal }} kirjet
                                        </p>
                                    </div>
                                    <Link
                                        href="/calendar/overview"
                                        class="text-sm font-medium text-primary transition hover:text-primary/80"
                                    >
                                        Kõik märkmed
                                    </Link>
                                </div>
                            </div>

                            <section class="mb-2 space-y-3">
                                <div
                                    v-if="dayFeedItems.length"
                                    class="space-y-3"
                                >
                                    <article
                                        v-for="item in dayFeedItems"
                                        :key="item.key"
                                        class="cursor-pointer rounded-xl border border-border/60 bg-card px-3 py-2.5 shadow-soft hover:border-primary/30"
                                        role="button"
                                        tabindex="0"
                                        @click="router.visit(`/calendar/notes/${item.note.id}`)"
                                        @keydown.enter="router.visit(`/calendar/notes/${item.note.id}`)"
                                    >
                                        <div class="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-3">
                                            <div class="min-w-0">
                                                <div
                                                    class="mb-1.5 flex items-start justify-between gap-2"
                                                >
                                                    <span
                                                        class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-1.5 py-0.5 text-[9px] font-semibold text-primary"
                                                    >
                                                        {{
                                                            item.kind === 'reminder'
                                                                ? 'Meeldetuletus'
                                                                : 'Märge'
                                                        }}
                                                    </span>
                                                </div>
                                                <p
                                                    class="line-clamp-2 text-[15px] leading-snug font-bold"
                                                >
                                                    {{
                                                        item.note.title ||
                                                        item.note.body ||
                                                        'Kirje'
                                                    }}
                                                </p>
                                                <p
                                                    v-if="
                                                        item.note.body &&
                                                        item.note.title
                                                    "
                                                    class="mt-0.5 line-clamp-2 text-xs text-muted-foreground"
                                                >
                                                    {{
                                                        previewText(item.note.body, 90)
                                                    }}
                                                </p>
                                                <p
                                                    v-if="item.note.due_at"
                                                    class="mt-1 line-clamp-1 text-[11px] font-semibold text-primary"
                                                >
                                                    {{ formatDueAt(item.note.due_at) }}
                                                </p>
                                                <p
                                                    v-else-if="item.note.reminder_time"
                                                    class="mt-1 line-clamp-1 text-[11px] font-semibold text-primary"
                                                >
                                                    {{ item.note.reminder_time }}
                                                </p>
                                            </div>

                                            <img
                                                v-if="item.note.media_urls?.[0]"
                                                :src="item.note.media_urls[0]"
                                                alt="Märkme foto"
                                                class="h-16 w-16 rounded-lg border border-border/60 object-cover"
                                            />
                                        </div>
                                    </article>
                                </div>

                                <div
                                    v-if="selectedTotal === 0"
                                    class="rounded-xl border border-border bg-muted/25 px-4 py-3"
                                >
                                    <p class="text-sm text-muted-foreground">
                                        Sellel päeval pole veel kirjeid.
                                    </p>
                                    <p
                                        v-if="showCalendarEmptyHint"
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        Lisa siia märge või meeldetuletus, et
                                        päevik püsiks ajakohane.
                                    </p>
                                </div>
                            </section>
                        </section>
                    </main>
                </div>

                <FloatingPlusButton
                    aria-label="Lisa märge"
                    :size-px="52"
                    :icon-size-px="30"
                    @click="onAdd"
                />

                <BottomNav active="calendar" />
            </div>
        </div>
    </AppLayout>
</template>
