<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import UserMenu from '@/pages/UserMenu.vue';

const props = withDefaults(
    defineProps<{
        title: string;
        diaryLabel?: string;
        showDiaryLabel?: boolean;
        titleClass?: string;
        headerClass?: string;
        topRowClass?: string;
        bottomRowClass?: string;
        /** Kui antud, kuvatakse pealkirjast vasakul tagasi-nupp. */
        backHref?: string | null;
    }>(),
    {
        diaryLabel: 'Minu Aia Päevik',
        showDiaryLabel: true,
        titleClass: 'text-forest text-3xl font-bold tracking-tight',
        headerClass: 'pt-6',
        topRowClass: 'mb-4',
        bottomRowClass: 'mb-6',
        backHref: null,
    },
);

const wrapperClass = computed(
    () =>
        `bg-background-light/80 sticky top-0 z-20 px-6 pb-4 backdrop-blur-md md:px-8 ${props.headerClass}`.trim(),
);
</script>

<template>
    <header :class="wrapperClass">
        <div :class="`flex items-center justify-between gap-3 ${topRowClass} ${bottomRowClass}`">
            <div class="flex min-w-0 items-center gap-3">
                <Link
                    v-if="backHref"
                    :href="backHref"
                    class="icon-btn size-9 shrink-0 rounded-full flex items-center justify-center text-foreground hover:bg-muted"
                    aria-label="Tagasi"
                >
                    <span class="material-symbols-outlined text-xl">arrow_back</span>
                </Link>
                <slot name="leading" />
                <h1 :class="titleClass">{{ title }}</h1>
            </div>
            <div class="flex shrink-0 items-center gap-2 sm:gap-5">
                <slot name="actions" />
                <UserMenu settings-href="/settings" />
            </div>
        </div>

        <slot />
    </header>
    <slot name="floating-action" />
</template>
