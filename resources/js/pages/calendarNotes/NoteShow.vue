<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import BackIconButton from '@/components/BackIconButton.vue';
import DiaryHeader from '@/components/DiaryHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import BottomNav from '@/pages/BottomNav.vue';
import { type BreadcrumbItem } from '@/types';

const props = withDefaults(
    defineProps<{
        backHref?: string;
        note: {
            id: number;
            note_date: string;
            title: string | null;
            body: string;
            media_urls?: string[];
            type?: string;
            done?: boolean | null;
            due_date?: string | null;
            due_time?: string | null;
            bed?: { id: number; name: string } | null;
            plant?: { id: number; name: string } | null;
        };
    }>(),
    { backHref: '/calendar/overview' },
);

function formatNoteDate(iso: string | null | undefined): string {
    if (!iso) return '';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return iso;
    return d.toLocaleDateString('et-EE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

const displayTitle = computed(
    () => props.note.title?.trim() || props.note.body?.trim() || 'Märge',
);

const breadcrumbs = computed<BreadcrumbItem[]>(() => {
    const back = props.backHref;
    let parentTitle = 'Kalender';
    if (back.startsWith('/beds/')) {
        parentTitle = 'Peenar';
    } else if (back.startsWith('/plants/')) {
        parentTitle = 'Taimed';
    } else if (back.includes('/calendar/overview')) {
        parentTitle = 'Märkmed';
    }
    return [
        { title: parentTitle, href: back },
        { title: 'Märge', href: '#' },
    ];
});

const bottomNavActive = computed(
    (): 'dashboard' | 'calendar' | 'map' | 'plants' | 'seeds' => {
        const h = props.backHref;
        if (h.startsWith('/beds/') || h.startsWith('/map')) {
            return 'map';
        }
        if (h.startsWith('/plants')) {
            return 'plants';
        }
        return 'calendar';
    },
);

const editHref = computed(
    () =>
        `/calendar/notes/${props.note.id}/edit?return_to=${encodeURIComponent(props.backHref)}`,
);

const hasReminder = computed(
    () => !!(props.note.due_date && props.note.due_date.length),
);

const reminderLabel = computed(() => {
    if (!hasReminder.value) return '';
    const t = props.note.due_time?.trim();
    return t ? `${props.note.due_date} · ${t}` : (props.note.due_date ?? '');
});

const hasBodyOrMedia = computed(
    () =>
        !!props.note.body?.trim() ||
        ((props.note.media_urls?.length ?? 0) > 0),
);
</script>

<template>
    <Head :title="displayTitle" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="page page-with-bottomnav">
            <div
                class="font-display min-h-screen bg-background text-foreground antialiased"
            >
                <div
                    class="border-beige/50 relative mx-auto min-h-screen w-full max-w-[480px] overflow-x-hidden border-x bg-background shadow-2xl md:mx-0 md:max-w-none md:border-0 md:shadow-none"
                >
                    <DiaryHeader
                        title=""
                        header-class="pt-6"
                        top-row-class="mb-2"
                        bottom-row-class="mb-4"
                    >
                        <template #leading>
                            <BackIconButton
                                :href="backHref"
                                aria-label="Tagasi"
                            />
                        </template>
                        <template #actions>
                            <Link
                                :href="editHref"
                                class="text-sm font-semibold text-primary transition hover:text-primary/80"
                            >
                                Muuda
                            </Link>
                        </template>
                    </DiaryHeader>

                    <main
                        class="flex-1 space-y-4 px-4 py-3 pb-52 sm:px-6 md:px-8 md:pb-44"
                    >
                        <section
                            class="rounded-3xl border border-border/70 bg-card/95 p-4 shadow-[0_10px_24px_rgba(16,24,40,0.06)]"
                        >
                            <div
                                class="mb-3 flex items-center justify-between gap-2"
                            >
                                <h3
                                    class="text-sm font-semibold text-foreground"
                                >
                                    Märkmed
                                </h3>
                            </div>

                            <article
                                class="rounded-2xl border border-border/60 bg-background/70 px-3.5 py-3 shadow-[0_2px_10px_rgba(16,24,40,0.04)]"
                            >
                                <div
                                    class="flex items-start justify-between gap-2"
                                >
                                    <p
                                        class="min-w-0 flex-1 text-sm leading-snug font-medium text-foreground"
                                    >
                                        {{ displayTitle }}
                                    </p>
                                    <span
                                        class="shrink-0 text-xs text-muted-foreground"
                                    >
                                        {{
                                            formatNoteDate(note.note_date)
                                        }}
                                    </span>
                                </div>

                                <div
                                    class="mt-2 flex flex-wrap items-center gap-2"
                                >
                                    <span
                                        v-if="note.done"
                                        class="inline-flex items-center gap-1 rounded-full bg-emerald-500/12 px-2.5 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-500/20"
                                    >
                                        <span
                                            class="material-symbols-outlined text-[13px]"
                                            >check_circle</span
                                        >
                                        Tehtud
                                    </span>
                                    <span
                                        v-if="hasReminder"
                                        class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary ring-1 ring-primary/20"
                                    >
                                        <span
                                            class="material-symbols-outlined text-[13px]"
                                            >notifications</span
                                        >
                                        {{ reminderLabel }}
                                    </span>
                                    <Link
                                        v-if="note.bed"
                                        :href="`/beds/${note.bed.id}`"
                                        class="inline-flex items-center gap-1 rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-muted-foreground ring-1 ring-border transition hover:text-foreground"
                                    >
                                        <span
                                            class="material-symbols-outlined text-[13px]"
                                            >grid_on</span
                                        >
                                        {{ note.bed.name }}
                                    </Link>
                                    <Link
                                        v-if="note.plant"
                                        :href="`/plants/${note.plant.id}`"
                                        class="inline-flex items-center gap-1 rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-muted-foreground ring-1 ring-border transition hover:text-foreground"
                                    >
                                        <span
                                            class="material-symbols-outlined text-[13px]"
                                            >local_florist</span
                                        >
                                        {{ note.plant.name }}
                                    </Link>
                                </div>

                                <div v-if="hasBodyOrMedia" class="mt-3 space-y-4">
                                    <p
                                        v-if="note.body?.trim()"
                                        class="text-sm leading-relaxed whitespace-pre-wrap text-foreground/90"
                                    >
                                        {{ note.body }}
                                    </p>

                                    <div
                                        v-if="note.media_urls?.length"
                                        class="grid grid-cols-2 gap-2 sm:grid-cols-3"
                                    >
                                        <a
                                            v-for="(url, i) in note.media_urls"
                                            :key="i"
                                            :href="url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="aspect-square overflow-hidden rounded-xl bg-muted/40 ring-1 ring-border/30"
                                        >
                                            <img
                                                :src="url"
                                                :alt="`Foto ${i + 1}`"
                                                class="h-full w-full object-cover transition duration-300 hover:scale-105"
                                            />
                                        </a>
                                    </div>
                                </div>
                            </article>
                        </section>
                    </main>
                </div>

                <BottomNav :active="bottomNavActive" />
            </div>
        </div>
    </AppLayout>
</template>
