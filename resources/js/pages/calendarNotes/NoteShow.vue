<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import DiaryHeader from '@/components/DiaryHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import BottomNav from '@/pages/BottomNav.vue';
import { calendar } from '@/routes';
import { type BreadcrumbItem } from '@/types';

const props = defineProps<{
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
}>();

const calendarUrl = calendar().url;

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Kalender', href: calendarUrl },
    { title: 'Märge', href: '#' },
];

const displayTitle = computed(
    () => props.note.title?.trim() || 'Pealkirjata märge',
);

const dateLabel = computed(() => {
    const [y, m, d] = props.note.note_date.split('-').map(Number);
    const date = new Date(y, (m ?? 1) - 1, d ?? 1);
    return date.toLocaleDateString('et-EE', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
});

const hasReminder = computed(
    () => !!(props.note.due_date && props.note.due_date.length),
);

const reminderLabel = computed(() => {
    if (!hasReminder.value) return '';
    const t = props.note.due_time?.trim();
    return t ? `${props.note.due_date} · ${t}` : (props.note.due_date ?? '');
});
</script>

<template>
    <Head :title="displayTitle" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="page page-with-bottomnav flex min-h-0 flex-col bg-muted/30"
        >
            <div
                class="border-beige/50 relative mx-auto w-full max-w-[480px] overflow-x-clip border-x bg-background shadow-2xl md:mx-0 md:max-w-none md:border-0 md:shadow-none"
            >
                <DiaryHeader title="Märge" back-href="/calendar/overview" />

                <div class="space-y-4 px-4 pb-28 pt-2">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground capitalize"
                            >
                                {{ dateLabel }}
                            </p>
                            <h1
                                class="mt-1 text-lg font-semibold leading-snug text-foreground"
                            >
                                {{ displayTitle }}
                            </h1>
                        </div>
                        <Link
                            :href="`/calendar/notes/${note.id}/edit`"
                            class="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-border bg-card px-3 py-1.5 text-sm font-medium text-foreground shadow-sm transition hover:border-primary/30 hover:bg-muted/40"
                        >
                            <span
                                class="material-symbols-outlined text-base text-primary"
                                >edit</span
                            >
                            Muuda
                        </Link>
                    </div>

                    <div
                        v-if="hasReminder"
                        class="rounded-xl border border-border/70 bg-muted/30 px-3 py-2.5"
                    >
                        <p
                            class="text-[11px] font-semibold tracking-wide text-muted-foreground uppercase"
                        >
                            Meeldetuletus
                        </p>
                        <p class="mt-1 text-sm font-medium text-foreground">
                            {{ reminderLabel }}
                        </p>
                    </div>

                    <div
                        v-if="note.bed || note.plant"
                        class="flex flex-wrap gap-2 text-sm"
                    >
                        <Link
                            v-if="note.bed"
                            :href="`/beds/${note.bed.id}`"
                            class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2.5 py-1 text-primary ring-1 ring-primary/15"
                        >
                            <span class="material-symbols-outlined text-base"
                                >grid_on</span
                            >
                            {{ note.bed.name }}
                        </Link>
                        <Link
                            v-if="note.plant"
                            :href="`/plants/${note.plant.id}`"
                            class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2.5 py-1 text-primary ring-1 ring-primary/15"
                        >
                            <span class="material-symbols-outlined text-base"
                                >local_florist</span
                            >
                            {{ note.plant.name }}
                        </Link>
                    </div>

                    <div
                        v-if="note.body?.trim()"
                        class="rounded-xl border border-border/60 bg-card/80 px-3 py-3 text-sm leading-relaxed text-foreground/90"
                    >
                        {{ note.body }}
                    </div>

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
                            class="aspect-square overflow-hidden rounded-lg border border-border bg-muted/40 bg-cover bg-center"
                            :style="{ backgroundImage: `url('${url}')` }"
                        />
                    </div>
                </div>
            </div>

            <BottomNav active="calendar" />
        </div>
    </AppLayout>
</template>
