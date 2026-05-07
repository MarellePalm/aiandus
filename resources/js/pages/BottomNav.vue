<!-- resources/js/Pages/BottomNav.vue -->
<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import { calendar, dashboard, map } from '@/routes';

type NavKey = 'dashboard' | 'calendar' | 'map' | 'plants' | 'seeds';

const props = withDefaults(
    defineProps<{
        active: NavKey;
        /** Kui false, renderdab sticky nav (nt dashboardi lõpus). */
        fixed?: boolean;
    }>(),
    { fixed: true },
);

const activeKey = computed(() => props.active);

function itemClass(key: NavKey) {
    return [
        'flex flex-col items-center gap-1',
        key === activeKey.value ? 'text-primary' : 'text-muted-foreground',
    ].join(' ');
}

function labelClass(key: NavKey) {
    return [
        'text-[10px]',
        key === activeKey.value ? 'font-bold' : 'font-medium',
    ].join(' ');
}
</script>

<template>
    <nav
        :class="[
            props.fixed ? 'fixed right-0 bottom-0 left-0' : 'sticky bottom-0',
            'z-40 border-t border-border bg-card/95 backdrop-blur lg:hidden',
        ]"
        aria-label="Põhinavigatsioon"
    >
        <div class="page-container-wide">
            <div class="flex h-20 items-center justify-around">
                <!-- TÖÖLAUD -->
                <Link
                    :href="dashboard().url"
                    :class="itemClass('dashboard')"
                    :aria-current="
                        activeKey === 'dashboard' ? 'page' : undefined
                    "
                >
                    <span class="material-symbols-outlined text-2xl">home</span>
                    <span :class="labelClass('dashboard')">Töölaud</span>
                </Link>

                <!-- KALENDER -->
                <Link
                    :href="calendar().url"
                    :class="itemClass('calendar')"
                    :aria-current="
                        activeKey === 'calendar' ? 'page' : undefined
                    "
                >
                    <span class="material-symbols-outlined text-2xl"
                        >calendar_month</span
                    >
                    <span :class="labelClass('calendar')">Kalender</span>
                </Link>

                <!-- TAIMED -->
                <Link
                    href="/plants"
                    :class="itemClass('plants')"
                    :aria-current="activeKey === 'plants' ? 'page' : undefined"
                >
                    <span class="material-symbols-outlined text-2xl"
                        >local_florist</span
                    >
                    <span :class="labelClass('plants')">Taimed</span>
                </Link>

                <!-- VARUD -->
                <Link
                    href="/seeds"
                    :class="itemClass('seeds')"
                    :aria-current="activeKey === 'seeds' ? 'page' : undefined"
                >
                    <span class="material-symbols-outlined text-2xl"
                        >shelves</span
                    >
                    <span :class="labelClass('seeds')">Varud</span>
                </Link>

                <!-- AIAPLAAN -->
                <Link
                    :href="map().url"
                    :class="itemClass('map')"
                    :aria-current="activeKey === 'map' ? 'page' : undefined"
                >
                    <span class="material-symbols-outlined text-2xl">map</span>
                    <span :class="labelClass('map')">Aiaplaan</span>
                </Link>
            </div>
        </div>
    </nav>
</template>
