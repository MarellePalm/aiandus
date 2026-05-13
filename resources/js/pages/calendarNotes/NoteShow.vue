<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import BackIconButton from '@/components/BackIconButton.vue';
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
            class="page page-with-bottomnav flex min-h-0 flex-col bg-[#f5f8f3]"
        >
            <div
                class="border-beige/50 relative mx-auto flex min-h-screen w-full max-w-[480px] flex-col overflow-x-clip border-x bg-[#f5f8f3] md:mx-0 md:max-w-none md:border-0 md:shadow-none"
            >
                <DiaryHeader
                    title="Märge"
                    header-class="pt-6"
                    top-row-class="mb-3"
                    bottom-row-class="mb-4"
                >
                    <template #leading>
                        <BackIconButton
                            href="/calendar/overview"
                            aria-label="Tagasi märkmete juurde"
                        />
                    </template>
                </DiaryHeader>

                <div
                    class="flex flex-1 flex-col px-6 pt-2 pb-40 md:px-8 lg:px-10"
                >
                    <!-- Päiseplokk -->
                    <div
                        class="mb-6 overflow-hidden rounded-[1.6rem] bg-[linear-gradient(160deg,#d8efd4_0%,#e8f5e4_50%,#f0f7ed_100%)] px-5 pt-5 pb-6 shadow-[0_10px_28px_rgba(69,120,58,0.08)] ring-1 ring-[#a8cc9f]/40 md:px-6"
                    >
                        <p
                            class="text-[11px] font-semibold tracking-[0.15em] text-primary/80 uppercase"
                        >
                            {{ dateLabel }}
                        </p>
                        <h1
                            class="mt-1.5 text-2xl leading-snug font-bold text-foreground"
                        >
                            {{ displayTitle }}
                        </h1>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
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
                                class="inline-flex items-center gap-1 rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-muted-foreground ring-1 ring-border hover:text-foreground"
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
                                class="inline-flex items-center gap-1 rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-muted-foreground ring-1 ring-border hover:text-foreground"
                            >
                                <span
                                    class="material-symbols-outlined text-[13px]"
                                    >local_florist</span
                                >
                                {{ note.plant.name }}
                            </Link>
                        </div>
                    </div>

                    <!-- Sisu -->
                    <div
                        v-if="note.body?.trim() || note.media_urls?.length"
                        class="space-y-5 overflow-hidden rounded-[1.6rem] border border-border/35 bg-card/50 p-5 shadow-[0_8px_24px_rgba(43,74,52,0.06)] md:p-6"
                    >
                        <div v-if="note.body?.trim()">
                            <p
                                class="text-[15px] leading-[1.75] whitespace-pre-wrap text-foreground/85"
                            >
                                {{ note.body }}
                            </p>
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
                                class="aspect-square overflow-hidden rounded-2xl bg-muted/40 ring-1 ring-border/30"
                            >
                                <img
                                    :src="url"
                                    :alt="`Foto ${i + 1}`"
                                    class="h-full w-full object-cover transition duration-300 hover:scale-105"
                                />
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <BottomNav active="calendar" />
        </div>
    </AppLayout>
</template>
