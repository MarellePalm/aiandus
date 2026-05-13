<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        placement?: 'overlay' | 'inline';
        /** mediaTile: taime/varu kategooria kaartidel (kontrastne nupp). listRow: nimekirjaridadel nagu PlantCard. */
        appearance?: 'mediaTile' | 'listRow';
    }>(),
    { placement: 'overlay', appearance: 'mediaTile' },
);

const emit = defineEmits<{
    (e: 'edit'): void;
    (e: 'delete'): void;
}>();

const open = ref(false);
const root = ref<HTMLElement | null>(null);

const buttonClass = computed(() => {
    if (props.placement === 'inline') {
        if (props.appearance === 'listRow') {
            return 'flex h-9 w-9 items-center justify-center rounded-full border border-border/70 bg-background/90 text-foreground/75 shadow-sm transition hover:border-primary/35 hover:bg-muted/50 hover:text-foreground sm:h-10 sm:w-10';
        }
        return 'flex h-9 w-9 items-center justify-center rounded-full text-primary transition hover:bg-primary/10 sm:h-10 sm:w-10';
    }
    if (props.appearance === 'listRow') {
        return 'flex h-9 w-9 items-center justify-center rounded-full border border-border/70 bg-background/90 text-foreground/75 shadow-sm transition hover:border-primary/35 hover:bg-muted/50 hover:text-foreground';
    }
    return 'flex h-8 w-8 items-center justify-center rounded-full bg-card/80 text-primary shadow-sm backdrop-blur-md transition hover:scale-105 hover:bg-card';
});

const menuClass = computed(() =>
    props.placement === 'inline'
        ? 'absolute bottom-10 right-0 z-50 w-44 overflow-hidden rounded-2xl border border-border bg-card shadow-xl ring-1 ring-border'
        : 'absolute top-10 right-0 z-50 w-44 overflow-hidden rounded-2xl border border-border bg-card shadow-xl ring-1 ring-border',
);

const rootClass = computed(() =>
    props.placement === 'inline'
        ? 'relative z-20'
        : `absolute top-2 right-2 ${props.appearance === 'listRow' ? 'z-50' : 'z-20'}`,
);

const toggle = () => {
    open.value = !open.value;
};

const close = () => {
    open.value = false;
};

const onClickOutside = (e: MouseEvent) => {
    if (!root.value) return;
    if (!root.value.contains(e.target as Node)) {
        close();
    }
};

onMounted(() => {
    document.addEventListener('click', onClickOutside, true);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onClickOutside, true);
});

const onEdit = () => {
    close();
    emit('edit');
};

const onDelete = () => {
    close();
    emit('delete');
};
</script>

<template>
    <div ref="root" :class="rootClass">
        <button
            type="button"
            :class="buttonClass"
            aria-label="Menüü"
            @click.prevent.stop="toggle"
        >
            <span
                class="material-symbols-outlined"
                :class="
                    appearance === 'listRow'
                        ? 'text-[22px] text-primary'
                        : placement === 'inline'
                          ? 'text-xl'
                          : 'text-[18px]'
                "
                >more_horiz</span
            >
        </button>

        <transition name="menu">
            <div v-if="open" :class="menuClass" @click.stop>
                <button
                    type="button"
                    class="flex w-full items-center gap-2 px-4 py-3 text-left text-sm text-foreground hover:bg-black/5"
                    @click.prevent.stop="onEdit"
                >
                    <span
                        class="material-symbols-outlined text-[20px] text-[#6B8C68]"
                        >edit</span
                    >
                    Muuda
                </button>

                <button
                    type="button"
                    class="flex w-full items-center gap-2 px-4 py-3 text-left text-sm text-foreground hover:bg-black/5"
                    @click.prevent.stop="onDelete"
                >
                    <span
                        class="material-symbols-outlined text-[20px] text-red-600"
                        >delete</span
                    >
                    Kustuta
                </button>
            </div>
        </transition>
    </div>
</template>

<style scoped>
.menu-enter-active,
.menu-leave-active {
    transition: all 0.15s ease;
}
.menu-enter-from,
.menu-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}
</style>
