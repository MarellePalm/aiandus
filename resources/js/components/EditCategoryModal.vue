<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

type CategoryItem = {
    id: number;
    name: string;
    image?: string | null;
};

const props = defineProps<{
    open: boolean;
    category: CategoryItem | null;
    submitUrlBase: string;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'updated'): void;
}>();

const close = () => emit('update:open', false);

const form = useForm<{
    name: string;
    image: File | null;
}>({
    name: '',
    image: null,
});

const nameInputRef = ref<HTMLInputElement | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);
const previewUrl = ref<string | null>(null);

const hasImage = computed(() => !!form.image || !!previewUrl.value);

const revokePreview = () => {
    if (previewUrl.value?.startsWith('blob:')) {
        URL.revokeObjectURL(previewUrl.value);
    }
    previewUrl.value = null;
};

const setFile = (file: File | null) => {
    if (previewUrl.value?.startsWith('blob:')) {
        URL.revokeObjectURL(previewUrl.value);
    }
    form.image = file;
    if (file) {
        previewUrl.value = URL.createObjectURL(file);
    } else if (props.category?.image) {
        previewUrl.value = props.category.image;
    } else {
        previewUrl.value = null;
    }
};

const openPicker = () => fileInputRef.value?.click();

const onFileChange = (e: Event) => {
    const input = e.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    if (file && !file.type.startsWith('image/')) return;
    setFile(file);
    input.value = '';
};

const reset = () => {
    form.clearErrors();
    form.name = props.category?.name ?? '';
    form.image = null;
    revokePreview();
    previewUrl.value = props.category?.image ?? null;
};

watch(
    () => props.open,
    async (open) => {
        if (open) {
            reset();
            document.body.style.overflow = 'hidden';
            await nextTick();
            nameInputRef.value?.focus();
            nameInputRef.value?.select();
        } else {
            document.body.style.overflow = '';
        }
    },
    { immediate: true },
);

watch(
    () => props.category,
    () => {
        if (props.open) reset();
    },
);

const submit = () => {
    if (!props.category) return;

    form.transform((data) => ({
        ...data,
        _method: 'patch',
    })).post(`${props.submitUrlBase}/${props.category.id}`, {
        forceFormData: true,
        onSuccess: () => {
            emit('updated');
            close();
        },
        onFinish: () => {
            form.transform((data) => data);
        },
    });
};

onBeforeUnmount(() => {
    document.body.style.overflow = '';
    revokePreview();
});
</script>

<template>
    <Teleport to="body">
        <transition name="fade">
            <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4" aria-modal="true" role="dialog">
                <button type="button" class="absolute inset-0 bg-black/30 backdrop-blur-[2px]" aria-label="Sulge" @click="close" />

                <div class="relative w-full max-w-lg rounded-3xl bg-[#FAF8F4] shadow-xl ring-1 ring-black/5">
                    <div class="p-5 sm:p-6">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-lg font-semibold text-[#2E2E2E]">Muuda kategooriat</h3>
                                <p class="mt-1 text-sm text-[#2E2E2E]/70">Muuda nime ja pilti.</p>
                            </div>
                            <button
                                type="button"
                                class="rounded-full p-2 text-[#2E2E2E]/60 hover:bg-black/5 hover:text-[#2E2E2E]"
                                aria-label="Sulge"
                                @click="close"
                            >
                                ✕
                            </button>
                        </div>

                        <div class="mt-5">
                            <label class="text-sm font-semibold tracking-widest text-[#2E2E2E]/70 uppercase">Kategooria nimi</label>
                            <input
                                ref="nameInputRef"
                                v-model="form.name"
                                type="text"
                                placeholder="Sisesta nimi"
                                class="mt-3 w-full rounded-2xl border border-black/10 bg-white px-4 py-3 text-[#2E2E2E] shadow-sm outline-none focus:border-[#6B8C68] focus:ring-2 focus:ring-[#6B8C68]/20"
                                @keydown.enter.prevent="submit"
                            />
                            <p v-if="form.errors.name" class="mt-2 text-sm text-red-600">{{ form.errors.name }}</p>
                        </div>

                        <div class="mt-5">
                            <label class="text-sm font-semibold tracking-widest text-[#2E2E2E]/70 uppercase">Pilt</label>
                            <input
                                ref="fileInputRef"
                                type="file"
                                accept="image/*"
                                class="hidden"
                                @change="onFileChange"
                            />
                            <button
                                type="button"
                                class="mt-3 w-full rounded-2xl border-2 border-dashed border-black/10 bg-white/60 px-6 py-8 text-center hover:bg-white/80"
                                @click="openPicker"
                            >
                                <template v-if="hasImage && previewUrl">
                                    <img :src="previewUrl" alt="Eelvaade" class="mx-auto max-h-40 rounded-xl object-cover" />
                                    <p class="mt-2 text-sm text-[#2E2E2E]/60">Vajuta, et pilti vahetada</p>
                                </template>
                                <template v-else>
                                    <span class="material-symbols-outlined text-5xl text-[#6B8C68]">add_a_photo</span>
                                    <p class="mt-2 text-sm text-[#2E2E2E]/70">Lisa kategooria pilt</p>
                                </template>
                            </button>
                            <p v-if="form.errors.image" class="mt-2 text-sm text-red-600">{{ form.errors.image }}</p>
                        </div>

                        <div class="mt-6 flex flex-col gap-3">
                            <button
                                type="button"
                                class="rounded-2xl bg-[#6B8C68] px-4 py-3 font-medium text-white transition hover:bg-[#4F6A52] disabled:opacity-60"
                                :disabled="form.processing || !form.name.trim()"
                                @click="submit"
                            >
                                {{ form.processing ? 'Salvestan...' : 'Salvesta muudatused' }}
                            </button>
                            <button
                                type="button"
                                class="text-sm text-[#2E2E2E]/60 hover:text-[#2E2E2E]"
                                :disabled="form.processing"
                                @click="close"
                            >
                                Tühista
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </Teleport>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 150ms ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
