<!-- resources/js/Pages/Calendar.vue -->
<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

import BackIconButton from '@/components/BackIconButton.vue';
import CalendarSwitchTabs from '@/components/calendar/CalendarSwitchTabs.vue';
import CardActionsMenu from '@/components/CardActionsMenu.vue';
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

function calendarReturnToQuery(): string {
    const m = viewDate.value.getMonth() + 1;
    const y = viewDate.value.getFullYear();
    return `/calendar?month=${m}&year=${y}`;
}

function editCalendarNote(noteId: number | string | undefined) {
    if (noteId == null || noteId === '') return;
    router.visit(`/calendar/notes/${noteId}/edit`);
}

function deleteCalendarNote(noteId: number | string | undefined) {
    if (noteId == null || noteId === '') return;
    if (!window.confirm('Kas kustutada see märge?')) return;
    const returnTo = encodeURIComponent(calendarReturnToQuery());
    router.delete(`/calendar/notes/${noteId}?return_to=${returnTo}`);
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
    if (marker === 'done') return 'bg-primary/55';
    return 'bg-stone-400/70';
}

function notesCountForDay(day: number): number {
    return notesForDay(day).length;
}

function hasReminderForDay(day: number): boolean {
    return notesForDay(day).some(
        (n) => n.due_at || n.reminder_enabled || n.reminder_time,
    );
}

function isToday(day: number): boolean {
    return (
        viewDate.value.getMonth() === today.getMonth() &&
        viewDate.value.getFullYear() === today.getFullYear() &&
        day === today.getDate()
    );
}

function isWeekendCol(idx: number): boolean {
    return idx === 5 || idx === 6;
}

function jumpToToday() {
    viewDate.value = new Date(today.getFullYear(), today.getMonth(), 1);
    selectedDay.value = today.getDate();
}

const viewingCurrentMonth = computed(
    () =>
        viewDate.value.getFullYear() === today.getFullYear() &&
        viewDate.value.getMonth() === today.getMonth(),
);

const monthNotesCount = computed(() => {
    let count = 0;
    const obj = notesByDate || {};
    for (const key of Object.keys(obj)) {
        const d = new Date(key);
        if (
            d.getFullYear() === viewDate.value.getFullYear() &&
            d.getMonth() === viewDate.value.getMonth()
        ) {
            count += obj[key]?.length ?? 0;
        }
    }
    return count;
});

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
        .map(
            (note, index): DayFeedItem => ({
                key: `note-${String(note.id ?? index)}`,
                kind:
                    note.due_at || note.reminder_enabled || note.reminder_time
                        ? 'reminder'
                        : 'note',
                note,
            }),
        )
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
                        class="mx-auto flex-1 space-y-5 px-6 pt-4 pb-40 md:max-w-6xl md:px-8 lg:grid lg:grid-cols-[minmax(0,1.45fr)_minmax(320px,0.95fr)] lg:items-start lg:gap-6 lg:space-y-0 lg:px-10"
                    >
                        <!-- Calendar card -->
                        <section
                            class="cal-shell relative overflow-hidden rounded-[1.4rem] border border-primary/15 bg-linear-to-br from-card via-muted/20 to-primary/8 p-3.5 shadow-[0_10px_28px_rgba(43,74,52,0.08)] ring-1 ring-amber-300/15 sm:p-4 lg:order-1 dark:from-card dark:via-muted/15 dark:to-primary/12"
                        >
                            <span
                                class="pointer-events-none absolute -top-10 -right-8 h-32 w-32 rounded-full bg-amber-300/20 blur-3xl dark:bg-amber-500/10"
                                aria-hidden="true"
                            />
                            <span
                                class="pointer-events-none absolute -bottom-12 -left-10 h-28 w-28 rounded-full bg-primary/10 blur-3xl dark:bg-primary/15"
                                aria-hidden="true"
                            />

                            <div
                                class="relative mb-3 flex items-center justify-between"
                            >
                                <button
                                    type="button"
                                    class="flex h-10 w-10 items-center justify-center rounded-full bg-card text-foreground/85 shadow-sm ring-1 ring-border transition hover:bg-primary/8 hover:text-primary hover:ring-primary/30"
                                    @click="prevMonth"
                                    aria-label="Eelmine kuu"
                                >
                                    <span class="material-symbols-outlined"
                                        >chevron_left</span
                                    >
                                </button>

                                <div class="text-center">
                                    <p
                                        class="flex items-center justify-center gap-1 text-[11px] font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                                    >
                                        <span
                                            class="material-symbols-outlined text-[14px] text-primary/70"
                                            aria-hidden="true"
                                        >
                                            calendar_month
                                        </span>
                                        Kalender
                                    </p>
                                    <h3
                                        class="text-xl font-bold tracking-tight capitalize"
                                    >
                                        {{ monthTitle }}
                                    </h3>
                                    <p
                                        v-if="monthNotesCount > 0"
                                        class="mt-0.5 text-[11px] font-medium text-muted-foreground"
                                    >
                                        {{ monthNotesCount }} kirjet kuus
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    class="flex h-10 w-10 items-center justify-center rounded-full bg-card text-foreground/85 shadow-sm ring-1 ring-border transition hover:bg-primary/8 hover:text-primary hover:ring-primary/30"
                                    @click="nextMonth"
                                    aria-label="Järgmine kuu"
                                >
                                    <span class="material-symbols-outlined"
                                        >chevron_right</span
                                    >
                                </button>
                            </div>

                            <div
                                v-if="!viewingCurrentMonth"
                                class="relative mb-3 flex justify-center"
                            >
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary ring-1 ring-primary/25 transition hover:bg-primary/15"
                                    @click="jumpToToday"
                                >
                                    <span
                                        class="material-symbols-outlined text-[14px]"
                                        aria-hidden="true"
                                        >today</span
                                    >
                                    Mine tänase juurde
                                </button>
                            </div>

                            <div
                                class="mb-3 flex items-center justify-between gap-2 border-t border-border/30 pt-3 lg:hidden"
                            >
                                <div>
                                    <p
                                        class="text-[10px] font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                                    >
                                        Valitud päev
                                    </p>
                                    <p
                                        class="mt-0.5 text-sm font-semibold text-foreground capitalize"
                                    >
                                        {{ selectedWeekday }},
                                        {{ selectedDay }}.
                                        {{ selectedMonthLabel }}
                                    </p>
                                </div>
                                <Link
                                    href="/calendar/overview"
                                    class="shrink-0 text-xs font-medium text-primary transition hover:text-primary/80"
                                >
                                    Kõik märkmed
                                </Link>
                            </div>

                            <div
                                class="relative grid grid-cols-7 gap-1.5 sm:gap-2"
                            >
                                <div
                                    v-for="(lbl, lblIdx) in dayLabels"
                                    :key="lbl"
                                    class="pb-1 text-center text-[11px] font-bold tracking-[0.14em] uppercase sm:text-xs"
                                    :class="
                                        isWeekendCol(lblIdx)
                                            ? 'text-amber-600/85 dark:text-amber-300/85'
                                            : 'text-foreground/75'
                                    "
                                >
                                    {{ lbl }}
                                </div>

                                <div
                                    v-for="n in startOffset"
                                    :key="'sp-' + n"
                                    class="h-13 sm:h-14"
                                />

                                <button
                                    v-for="(day, dayIdx) in daysInMonth"
                                    :key="day"
                                    type="button"
                                    class="cal-cell relative flex h-13 flex-col items-center justify-center gap-1 rounded-xl border text-sm transition-all duration-200 sm:h-14"
                                    :class="[
                                        day === selectedDay
                                            ? 'cal-cell-selected z-[2] border-primary/40 bg-linear-to-br from-primary to-primary/85 font-bold text-primary-foreground shadow-[0_8px_22px_rgba(43,74,52,0.28)] ring-2 ring-primary/30 ring-offset-2 ring-offset-background'
                                            : isToday(day)
                                              ? 'border-amber-400/60 bg-amber-50/40 ring-1 ring-amber-400/40 hover:-translate-y-0.5 hover:border-amber-400/80 dark:bg-amber-500/10'
                                              : markedDays.has(day)
                                                ? 'border-primary/20 bg-primary/5 hover:-translate-y-0.5 hover:border-primary/35 hover:shadow-md'
                                                : 'border-border/40 bg-card/60 hover:-translate-y-0.5 hover:border-primary/25 hover:bg-card hover:shadow-md',
                                    ]"
                                    :style="{
                                        '--cal-delay': `${
                                            (startOffset + dayIdx) * 12
                                        }ms`,
                                    }"
                                    @click="selectDay(day)"
                                    :aria-label="`Vali päev ${day}`"
                                    :aria-pressed="day === selectedDay"
                                >
                                    <span
                                        class="leading-none font-semibold"
                                        :class="[
                                            day === selectedDay
                                                ? 'text-primary-foreground'
                                                : isToday(day)
                                                  ? 'text-amber-700 dark:text-amber-200'
                                                  : 'text-foreground',
                                        ]"
                                    >
                                        {{ day }}
                                    </span>

                                    <span
                                        v-if="
                                            hasReminderForDay(day) &&
                                            day !== selectedDay
                                        "
                                        class="material-symbols-outlined absolute top-0.5 right-0.5 text-[12px] text-primary/70"
                                        aria-hidden="true"
                                    >
                                        notifications
                                    </span>
                                    <span
                                        v-else-if="
                                            hasReminderForDay(day) &&
                                            day === selectedDay
                                        "
                                        class="material-symbols-outlined absolute top-0.5 right-0.5 text-[12px] text-primary-foreground/85"
                                        aria-hidden="true"
                                    >
                                        notifications
                                    </span>

                                    <div
                                        class="flex min-h-1.5 items-center justify-center gap-1"
                                        aria-hidden="true"
                                    >
                                        <span
                                            v-for="marker in markersForDay(day)"
                                            :key="`${day}-${marker}`"
                                            class="h-1.5 w-1.5 rounded-full transition"
                                            :class="[
                                                markerClass(marker),
                                                day === selectedDay
                                                    ? 'bg-primary-foreground/90'
                                                    : '',
                                            ]"
                                        />
                                    </div>

                                    <span
                                        v-if="
                                            notesCountForDay(day) > 3 &&
                                            day !== selectedDay
                                        "
                                        class="absolute right-1 bottom-0.5 text-[9px] font-bold text-primary/70"
                                        aria-hidden="true"
                                    >
                                        +{{ notesCountForDay(day) - 3 }}
                                    </span>
                                </button>
                            </div>
                        </section>

                        <!-- Valitud päev: ülesanded ja märkmed -->
                        <section
                            class="pt-1 pb-4 lg:sticky lg:top-6 lg:order-2 lg:rounded-2xl lg:border lg:border-border lg:bg-card/90 lg:p-4 lg:shadow-soft"
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
                                            {{ selectedWeekday }},
                                            {{ selectedDay }}.
                                            {{ selectedMonthLabel }}
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
                                        class="relative cursor-pointer overflow-hidden rounded-xl border border-border/50 bg-card px-3 py-2.5 pl-4 transition hover:border-primary/30 hover:bg-muted/30"
                                        :class="
                                            item.note.done ? 'opacity-70' : ''
                                        "
                                        role="button"
                                        tabindex="0"
                                        @click="
                                            router.visit(
                                                `/calendar/notes/${item.note.id}`,
                                            )
                                        "
                                        @keydown.enter="
                                            router.visit(
                                                `/calendar/notes/${item.note.id}`,
                                            )
                                        "
                                    >
                                        <span
                                            class="absolute inset-y-0 left-0 w-[3px] rounded-l-xl"
                                            :class="
                                                item.note.done
                                                    ? 'bg-emerald-400'
                                                    : item.kind === 'reminder'
                                                      ? 'bg-primary'
                                                      : 'bg-primary/40'
                                            "
                                            aria-hidden="true"
                                        />
                                        <div
                                            class="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-3"
                                        >
                                            <div class="min-w-0">
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
                                                        previewText(
                                                            item.note.body,
                                                            90,
                                                        )
                                                    }}
                                                </p>
                                                <p
                                                    v-if="item.note.due_at"
                                                    class="mt-1 line-clamp-1 text-[11px] font-semibold text-primary"
                                                >
                                                    {{
                                                        formatDueAt(
                                                            item.note.due_at,
                                                        )
                                                    }}
                                                </p>
                                                <p
                                                    v-else-if="
                                                        item.note.reminder_time
                                                    "
                                                    class="mt-1 line-clamp-1 text-[11px] font-semibold text-primary"
                                                >
                                                    {{
                                                        item.note.reminder_time
                                                    }}
                                                </p>
                                            </div>

                                            <div
                                                class="flex shrink-0 flex-col items-end gap-2"
                                            >
                                                <CardActionsMenu
                                                    v-if="item.note.id != null"
                                                    placement="inline"
                                                    @edit="
                                                        editCalendarNote(
                                                            item.note.id,
                                                        )
                                                    "
                                                    @delete="
                                                        deleteCalendarNote(
                                                            item.note.id,
                                                        )
                                                    "
                                                />
                                                <img
                                                    v-if="
                                                        item.note
                                                            .media_urls?.[0]
                                                    "
                                                    :src="
                                                        item.note.media_urls[0]
                                                    "
                                                    alt="Märkme foto"
                                                    class="h-20 w-20 rounded-xl border border-border/60 object-cover sm:h-24 sm:w-24"
                                                />
                                            </div>
                                        </div>
                                    </article>
                                </div>

                                <div
                                    v-if="selectedTotal === 0"
                                    class="py-6 text-center"
                                >
                                    <span
                                        class="material-symbols-outlined mb-2 block text-3xl text-muted-foreground/40"
                                        >calendar_today</span
                                    >
                                    <p class="text-sm text-muted-foreground">
                                        Sellel päeval pole kirjeid.
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

<style scoped>
.cal-shell {
    background-image:
        radial-gradient(
            circle at 20% 12%,
            rgba(251, 191, 36, 0.06) 0,
            transparent 55%
        ),
        radial-gradient(
            circle at 85% 88%,
            rgba(43, 74, 52, 0.08) 0,
            transparent 60%
        );
}

.cal-cell {
    opacity: 0;
    transform: translate3d(0, 6px, 0);
    animation: cal-cell-in 420ms cubic-bezier(0.22, 1, 0.36, 1) forwards;
    animation-delay: var(--cal-delay, 0ms);
    will-change: opacity, transform;
}
@keyframes cal-cell-in {
    to {
        opacity: 1;
        transform: translate3d(0, 0, 0);
    }
}

.cal-cell-selected {
    animation:
        cal-cell-in 420ms cubic-bezier(0.22, 1, 0.36, 1) forwards,
        cal-cell-pulse 2.6s ease-in-out 700ms infinite;
}
@keyframes cal-cell-pulse {
    0%,
    100% {
        box-shadow:
            0 8px 22px rgba(43, 74, 52, 0.28),
            0 0 0 0 rgba(43, 74, 52, 0.35);
    }
    50% {
        box-shadow:
            0 8px 22px rgba(43, 74, 52, 0.28),
            0 0 0 8px rgba(43, 74, 52, 0);
    }
}

@media (prefers-reduced-motion: reduce) {
    .cal-cell,
    .cal-cell-selected {
        animation: none !important;
        opacity: 1;
        transform: none;
    }
}
</style>
