<!-- resources/js/pages/UserMenu.vue -->
<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        settingsHref?: string;
        logoutHref?: string;
        ariaLabel?: string;
    }>(),
    {
        settingsHref: '/settings',
        logoutHref: '/logout',
        ariaLabel: 'Kasutajamenüü',
    },
);

const page = usePage();
const user = computed(() => page.props.auth?.user);

const open = ref(false);
const rootEl = ref<HTMLElement | null>(null);

function toggle() {
    open.value = !open.value;
}
function close() {
    open.value = false;
}
function logout() {
    close();
    router.post(props.logoutHref);
}

function onDocClick(e: MouseEvent) {
    if (!open.value) return;
    const t = e.target as Node | null;
    if (t && rootEl.value && !rootEl.value.contains(t)) close();
}
function onKey(e: KeyboardEvent) {
    if (open.value && e.key === 'Escape') close();
}

onMounted(() => {
    document.addEventListener('click', onDocClick, true);
    document.addEventListener('keydown', onKey);
});
onBeforeUnmount(() => {
    document.removeEventListener('click', onDocClick, true);
    document.removeEventListener('keydown', onKey);
});
</script>

<template>
    <div ref="rootEl" class="relative ml-auto">
        <button
            type="button"
            class="icon-btn size-10 overflow-hidden"
            :aria-label="props.ariaLabel ?? 'Kasutajamenüü'"
            @click.stop="toggle"
        >
            <img
                v-if="user?.avatar_url"
                :src="String(user.avatar_url)"
                alt=""
                class="h-full w-full object-cover"
            />
            <span
                v-else
                class="material-symbols-outlined text-xl text-primary/80 dark:text-foreground/80"
                >person</span
            >
        </button>

        <div v-if="open" class="dropdown" role="menu">
            <div class="border-b border-border px-4 py-3">
                <p class="truncate text-sm font-semibold">
                    {{ user?.name ?? 'Kasutaja' }}
                </p>
                <p class="truncate text-xs text-muted-foreground">
                    {{ user?.email ?? '' }}
                </p>
            </div>

            <Link
                :href="settingsHref"
                class="menu-item"
                role="menuitem"
                @click="close"
            >
                <span class="material-symbols-outlined text-lg">settings</span>
                Seaded
            </Link>

            <Link
                href="/dashboard?layout=edit"
                class="menu-item"
                role="menuitem"
                @click="close"
            >
                <span class="material-symbols-outlined text-lg"
                    >dashboard_customize</span
                >
                Muuda avalehte
            </Link>

            <button
                type="button"
                class="menu-item menu-item-danger"
                role="menuitem"
                @click="logout"
            >
                <span class="material-symbols-outlined text-lg">logout</span>
                Logi välja
            </button>
        </div>
    </div>
</template>
