<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import BackIconButton from '@/components/BackIconButton.vue';
import DiaryHeader from '@/components/DiaryHeader.vue';
import FloatingPlusButton from '@/components/FloatingPlusButton.vue';
import BottomNav from '@/pages/BottomNav.vue';

type ChipKey = 'all' | 'month' | 'reminders';
type NoteKind = 'reminder' | 'journal';

type Note = {
    id: string;
    dateISO: string;
    kind: NoteKind;
    title: string;
    body?: string | null;
    time?: string | null;
    tag: string;
    tagStyle?: 'primary' | 'neutral';
    favorite?: boolean;
    done?: boolean | null;
    images?: string[];
};

type Group = { label: string; iso: string; items: Note[] };

const props = withDefaults(
    defineProps<{
        notes?: Array<{
            id: number;
            note_date: string;
            title?: string | null;
            body?: string | null;
            type?: string;
            done?: boolean | null;
            due_at?: string | null;
            media_urls?: string[];
        }>;
        filters?: { q?: string; chip?: ChipKey };
    }>(),
    { notes: () => [] },
);

// Backend saadab "notes", komponent kasutab kujundatud "items"
const items = computed<Note[]>(() =>
    (props.notes ?? []).map((n) => ({
        id: String(n.id),
        dateISO: n.note_date,
        kind: (n.due_at ? 'reminder' : 'journal') as NoteKind,
        title: n.title ?? '',
        body: n.body ?? null,
        time: n.due_at
            ? new Date(n.due_at).toLocaleTimeString('et-EE', {
                  hour: '2-digit',
                  minute: '2-digit',
              })
            : null,
        tag: n.due_at ? 'Meeldetuletus' : 'Märge',
        tagStyle: (n.due_at ? 'neutral' : 'primary') as 'primary' | 'neutral',
        done: n.done ?? null,
        favorite: false,
        images: n.media_urls ?? [],
    })),
);

const chips: { key: ChipKey; label: string }[] = [
    { key: 'all', label: 'Kõik' },
    { key: 'month', label: 'Sel kuul' },
    { key: 'reminders', label: 'Meeldetuletused' },
];

const query = ref(props.filters?.q ?? '');
const activeChip = ref<ChipKey>(props.filters?.chip ?? 'all');

function chipClass(key: ChipKey) {
    const base =
        'min-h-9 w-full rounded-full px-1.5 py-1 text-center text-[11px] leading-tight font-semibold whitespace-nowrap transition-colors';
    return key === activeChip.value
        ? `${base} bg-primary text-white`
        : `${base} bg-primary/10 text-primary hover:bg-primary/15`;
}

function parseISODate(iso: string) {
    const [y, m, d] = iso.split('-').map(Number);
    return new Date(y, (m ?? 1) - 1, d ?? 1);
}

function humanLabel(dateISO: string) {
    const d = parseISODate(dateISO);
    const today = new Date();
    const tomorrowBase = new Date(today.getFullYear(), today.getMonth(), today.getDate());
    const dayOnly = new Date(d.getFullYear(), d.getMonth(), d.getDate());
    const diffDays = Math.round((dayOnly.getTime() - tomorrowBase.getTime()) / 86400000);

    if (diffDays === 0) return 'Täna';
    if (diffDays === -1) return 'Eile';

    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const yyyy = d.getFullYear();
    return `${dd}.${mm}.${yyyy}`;
}

function escapeHtml(value: string) {
    return value
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

function highlightHtml(text?: string | null) {
    if (!text) return '';
    const safe = escapeHtml(text);
    const q = query.value.trim();
    if (!q) return safe;

    const escapedQuery = q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const regex = new RegExp(`(${escapedQuery})`, 'ig');

    return safe.replace(
        regex,
        '<mark class="rounded bg-primary/15 px-0.5 text-foreground">$1</mark>',
    );
}

function snippetFromBody(text?: string | null, max = 110) {
    if (!text) return '';
    const normalized = text.trim();
    const q = query.value.trim().toLowerCase();

    if (!q) {
        return normalized.length > max
            ? `${normalized.slice(0, max).trim()}...`
            : normalized;
    }

    const index = normalized.toLowerCase().indexOf(q);
    if (index === -1) {
        return normalized.length > max
            ? `${normalized.slice(0, max).trim()}...`
            : normalized;
    }

    const start = Math.max(0, index - 28);
    const end = Math.min(normalized.length, index + q.length + 52);
    const prefix = start > 0 ? '... ' : '';
    const suffix = end < normalized.length ? ' ...' : '';

    return `${prefix}${normalized.slice(start, end).trim()}${suffix}`;
}

function sortNotes(items: Note[]) {
    return [...items].sort((a, b) => {
        if (a.kind !== b.kind) {
            return a.kind === 'reminder' ? -1 : 1;
        }

        if (a.time && b.time) {
            return a.time.localeCompare(b.time);
        }

        if (a.time) return -1;
        if (b.time) return 1;
        return a.title.localeCompare(b.title, 'et');
    });
}

const filteredGroups = computed<Group[]>(() => {
    const now = new Date();

    const byISO = new Map<string, Note[]>();
    const filteredNotes: Note[] = [];
    for (const n of items.value) {
        const d = parseISODate(n.dateISO);
        const dayOnly = new Date(d.getFullYear(), d.getMonth(), d.getDate());

        // Ajafilter chip'i järgi
        if (
            activeChip.value === 'month' &&
            (dayOnly.getMonth() !== now.getMonth() ||
                dayOnly.getFullYear() !== now.getFullYear())
        )
            continue;
        if (activeChip.value === 'reminders' && n.kind !== 'reminder') continue;
        filteredNotes.push(n);
        byISO.set(n.dateISO, [...(byISO.get(n.dateISO) ?? []), n]);
    }

    // "Kõik": näita 7 viimast kirjet, mitte tühje kuupäevi.
    if (activeChip.value === 'all') {
        const latestSeven = filteredNotes.slice(0, 7);
        const byDate = new Map<string, Note[]>();
        for (const note of latestSeven) {
            byDate.set(note.dateISO, [
                ...(byDate.get(note.dateISO) ?? []),
                note,
            ]);
        }

        return [...byDate.entries()]
            .sort(([a], [b]) => (a < b ? 1 : -1))
            .map(([iso, groupItems]) => ({
                label: humanLabel(iso),
                iso,
                items: sortNotes(groupItems),
            }));
    }

    // Muudel filtritel näita ainult olemasolevaid kuupäevi, uusim ees.
    const sorted = [...byISO.entries()].sort(([a], [b]) => (a < b ? 1 : -1));
    return sorted.map(([iso, groupItems]) => ({
        label: humanLabel(iso),
        iso,
        items: sortNotes(groupItems),
    }));
});

function refresh() {
    router.get(
        '/calendar/overview',
        { q: query.value || undefined, chip: activeChip.value || undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function clearQuery() {
    query.value = '';
    refresh();
}

function onAdd() {
    window.location.href = '/calendar/note-form';
}

// debounce-lite: refresh 300ms pärast trükkimist
let t: number | undefined;
watch(query, () => {
    window.clearTimeout(t);
    t = window.setTimeout(() => refresh(), 300);
});
</script>

<template>
    <div class="page page-with-bottomnav">
        <Head title="Koondvaade">
            <link
                href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
                rel="stylesheet"
            />
        </Head>

        <div
            class="font-display min-h-screen bg-background text-foreground antialiased"
        >
            <div
                class="border-beige/50 relative mx-auto min-h-screen w-full max-w-[480px] overflow-x-hidden border-x bg-background shadow-2xl md:mx-0 md:max-w-none md:border-0 md:shadow-none"
            >
                <DiaryHeader
                    title="Märkmed"
                    header-class="pt-6"
                    top-row-class="mb-3"
                    bottom-row-class="mb-4"
                >
                    <template #leading>
                        <BackIconButton
                            href="/calendar"
                            aria-label="Tagasi kalendrisse"
                        />
                    </template>
                </DiaryHeader>

                <main class="flex-1 px-6 py-4 pb-24 md:px-8">
                    <div
                        class="rounded-2xl border border-primary/10 bg-white/70 p-4 shadow-soft"
                    >
                        <div class="relative mb-3">
                            <span
                                class="material-symbols-outlined absolute top-1/2 left-3 -translate-y-1/2 text-muted-foreground"
                            >
                                search
                            </span>
                            <input
                                v-model.trim="query"
                                class="h-11 w-full rounded-xl border-none bg-secondary/60 pr-4 pl-10 text-sm outline-none focus:ring-2 focus:ring-ring/40"
                                placeholder="Otsi märkmeid..."
                                type="text"
                            />
                        </div>

                        <div class="grid grid-cols-3 gap-2 pb-1">
                            <button
                                v-for="chip in chips"
                                :key="chip.key"
                                type="button"
                                :class="chipClass(chip.key)"
                                @click="
                                    activeChip = chip.key;
                                    refresh();
                                "
                            >
                                {{ chip.label }}
                            </button>
                        </div>
                    </div>

                    <div class="space-y-4 pt-4 sm:space-y-6 sm:pt-6">
                        <!-- Tühi olek -->
                        <div
                            v-if="filteredGroups.length === 0"
                            class="rounded-2xl border-2 border-dashed border-border bg-muted/20 p-8 text-center"
                        >
                            <span
                                class="material-symbols-outlined mb-3 block text-4xl text-muted-foreground"
                                >edit_note</span
                            >
                            <p class="mb-1 text-sm font-medium text-foreground">
                                {{
                                    query
                                        ? 'Otsingutulemusi ei leitud'
                                        : 'Märkmeid pole'
                                }}
                            </p>
                            <p class="mb-4 text-xs text-muted-foreground">
                                {{
                                    query
                                        ? 'Proovi teistsugust otsisõna või filtri.'
                                        : 'Lisa märge kalendrist või paremal alanurgas oleva + nupuga.'
                                }}
                            </p>
                            <Link
                                v-if="!query"
                                href="/calendar/note-form"
                                class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground transition hover:bg-primary/90"
                            >
                                <span class="material-symbols-outlined text-lg"
                                    >add</span
                                >
                                Lisa märge
                            </Link>
                            <button
                                v-else
                                type="button"
                                class="inline-flex items-center gap-2 rounded-xl border border-border bg-card px-4 py-2.5 text-sm font-semibold transition hover:bg-muted"
                                @click="clearQuery()"
                            >
                                Tühjenda otsing
                            </button>
                        </div>

                        <section
                            v-for="group in filteredGroups"
                            :key="group.label"
                        >
                            <h3
                                class="mb-2 flex items-center justify-between gap-3 text-sm font-bold tracking-wider text-muted-foreground uppercase sm:mb-3"
                            >
                                <span>{{ group.label }}</span>
                                <span
                                    class="text-[11px] font-medium tracking-normal text-muted-foreground/80 normal-case"
                                >
                                    {{ group.items.length }} kirjet
                                </span>
                            </h3>

                            <div class="space-y-2.5 sm:space-y-3">
                                <article
                                    v-for="note in group.items"
                                    :key="note.id"
                                    class="cursor-pointer border-b border-border/60 py-2.5 transition hover:bg-muted/20 sm:rounded-xl sm:border sm:bg-card sm:px-3 sm:shadow-soft sm:hover:border-primary/30"
                                    role="button"
                                    tabindex="0"
                                    @click="
                                        router.visit(
                                            `/calendar/notes/${note.id}`,
                                        )
                                    "
                                    @keydown.enter="
                                        router.visit(
                                            `/calendar/notes/${note.id}`,
                                        )
                                    "
                                >
                                    <div class="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-3">
                                        <div class="min-w-0">
                                            <div class="mb-1.5 flex items-start justify-between gap-2">
                                                <span
                                                    class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                                    :class="
                                                        note.kind === 'journal'
                                                            ? 'bg-primary/10 text-primary'
                                                            : 'bg-secondary text-muted-foreground'
                                                    "
                                                >
                                                    {{ note.tag }}
                                                </span>
                                            </div>

                                            <h4
                                                class="line-clamp-2 text-[15px] leading-snug font-semibold"
                                                :class="
                                                    note.done
                                                        ? 'line-through opacity-60'
                                                        : ''
                                                "
                                                v-html="highlightHtml(note.title || 'Nimetu kirje')"
                                            >
                                            </h4>
                                            <p
                                                v-if="note.body"
                                                class="mt-0.5 line-clamp-2 text-xs leading-relaxed text-muted-foreground"
                                                v-html="highlightHtml(snippetFromBody(note.body, 90))"
                                            >
                                            </p>

                                            <p
                                                v-if="note.time"
                                                class="mt-1 text-[11px] font-semibold text-primary"
                                            >
                                                {{ note.time }}
                                            </p>
                                        </div>

                                        <div
                                            v-if="note.images?.[0]"
                                            class="h-14 w-14 shrink-0 rounded-lg border border-border/70 bg-cover bg-center sm:h-16 sm:w-16"
                                            :style="{
                                                backgroundImage: `url('${note.images[0]}')`,
                                            }"
                                            role="img"
                                            aria-label="Märkme pilt"
                                        />
                                    </div>
                                </article>

                                <p
                                    v-if="group.items.length === 0"
                                    class="text-sm text-muted-foreground"
                                >
                                    Pole kirjeid.
                                </p>
                            </div>
                        </section>
                    </div>
                </main>

                <FloatingPlusButton
                    aria-label="Lisa märge"
                    :size-px="52"
                    :icon-size-px="30"
                    @click="onAdd"
                />

                <BottomNav active="calendar" />
            </div>
        </div>
    </div>
</template>
