<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
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
        /** Kui true, ei kuvata suurt pealkirja (nt ainult tagasi + tegevused). */
        hideTitle?: boolean;
        /** Lisa klassid päise alumisele paddingule (nt `pb-2`). */
        footerPaddingClass?: string;
    }>(),
    {
        diaryLabel: 'Minu Aia Päevik',
        showDiaryLabel: true,
        titleClass: 'text-forest text-3xl font-bold tracking-tight lg:text-4xl',
        headerClass: 'pt-6',
        topRowClass: 'mb-4',
        bottomRowClass: 'mb-6',
        backHref: null,
        hideTitle: false,
        footerPaddingClass: 'pb-4',
    },
);

type NavKey = 'dashboard' | 'calendar' | 'plants' | 'seeds' | 'map';

const page = usePage();

const desktopNavItems: Array<{
    key: NavKey;
    href: string;
    label: string;
    icon: string;
}> = [
    { key: 'dashboard', href: '/dashboard', label: 'Töölaud', icon: 'home' },
    {
        key: 'calendar',
        href: '/calendar',
        label: 'Kalender',
        icon: 'calendar_month',
    },
    { key: 'plants', href: '/plants', label: 'Taimed', icon: 'local_florist' },
    { key: 'seeds', href: '/seeds', label: 'Varud', icon: 'shelves' },
    { key: 'map', href: '/map', label: 'Aiaplaan', icon: 'map' },
];

const currentPath = computed(() => {
    const path = typeof page.url === 'string' ? page.url : '';
    return path.split('?')[0];
});

function isNavActive(item: { key: NavKey; href: string }): boolean {
    const path = currentPath.value;
    if (item.key === 'dashboard') return path === '/dashboard';
    if (item.key === 'calendar') return path.startsWith('/calendar');
    if (item.key === 'plants') return path.startsWith('/plants');
    if (item.key === 'seeds') return path.startsWith('/seeds');
    if (item.key === 'map') return path.startsWith('/map') || path.startsWith('/beds');
    return path === item.href;
}

const wrapperClass = computed(() =>
    `bg-background-light/92 sticky top-0 z-20 px-6 backdrop-blur-md md:px-8 lg:static lg:border-0 lg:bg-background lg:px-10 ${props.footerPaddingClass} ${props.headerClass}`.trim(),
);
</script>

<template>
    <header :class="wrapperClass">
        <div
            class="hidden lg:fixed lg:inset-x-0 lg:top-0 lg:z-50 lg:flex lg:h-16 lg:items-center lg:border-b lg:border-border/80 lg:bg-background/95 lg:px-10 lg:backdrop-blur-md"
        >
            <div class="flex w-full items-center justify-between">
                <Link
                    href="/dashboard"
                    class="flex w-44 items-center gap-2 text-primary"
                    aria-label="Aiapäevik avaleht"
                >
                    <span class="material-symbols-outlined text-[21px]"
                        >potted_plant</span
                    >
                    <span class="text-xl font-bold tracking-tight"
                        >Aiapäevik</span
                    >
                </Link>
                <nav
                    class="flex items-center gap-5 text-muted-foreground"
                    aria-label="Põhinavigatsioon"
                >
                    <Link
                        v-for="item in desktopNavItems"
                        :key="item.key"
                        :href="item.href"
                        class="group flex min-w-[58px] flex-col items-center justify-center gap-1 text-[11px] transition"
                        :class="
                            isNavActive(item)
                                ? 'font-semibold text-primary'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                    >
                        <span
                            class="material-symbols-outlined text-[20px] leading-none"
                        >
                            {{ item.icon }}
                        </span>
                        <span class="leading-none">{{ item.label }}</span>
                    </Link>
                </nav>
                <div class="flex w-44 items-center justify-end">
                    <UserMenu settings-href="/settings" />
                </div>
            </div>
        </div>

        <div class="hidden lg:block lg:h-16"></div>

        <div
            :class="`flex items-center justify-between gap-3 lg:hidden ${topRowClass} ${bottomRowClass}`"
        >
            <div class="flex min-w-0 items-center gap-3">
                <Link
                    v-if="backHref"
                    :href="backHref"
                    class="icon-btn flex size-9 shrink-0 items-center justify-center rounded-full text-foreground hover:bg-muted"
                    aria-label="Tagasi"
                >
                    <span class="material-symbols-outlined text-xl"
                        >arrow_back</span
                    >
                </Link>
                <slot name="leading" />
                <h1 v-if="!hideTitle" :class="titleClass">{{ title }}</h1>
            </div>
            <div class="flex shrink-0 items-center gap-2 sm:gap-5">
                <slot name="actions" />
                <UserMenu settings-href="/settings" />
            </div>
        </div>

        <div
            :class="`hidden items-center justify-between gap-6 lg:flex ${topRowClass} ${bottomRowClass}`"
        >
            <div class="flex min-w-0 items-center gap-3">
                <Link
                    v-if="backHref"
                    :href="backHref"
                    class="icon-btn flex size-9 shrink-0 items-center justify-center rounded-full text-foreground hover:bg-muted"
                    aria-label="Tagasi"
                >
                    <span class="material-symbols-outlined text-xl"
                        >arrow_back</span
                    >
                </Link>
                <slot name="leading" />
                <h1 v-if="!hideTitle" :class="titleClass">{{ title }}</h1>
            </div>
            <div class="flex shrink-0 items-center gap-5">
                <slot name="actions" />
            </div>
        </div>

        <slot />
    </header>
    <slot name="floating-action" />
</template>
