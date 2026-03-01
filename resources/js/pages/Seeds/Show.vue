<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';

type Seed = {
    id: number;
    name: string;
    subtitle?: string | null;
    amount?: number | null;
    year?: number | null;
    expires_at?: string | null;
    image_url?: string | null;
    notes?: string | null;
    is_favorite?: boolean;
};

const props = defineProps<{ seed: Seed }>();

const deleteSeed = () => {
    router.delete(`/seeds/${props.seed.id}`, {
        onSuccess: () => router.visit('/seeds'),
    });
};
</script>

<template>
    <Head :title="props.seed.name" />

    <div class="bg-background-light text-forest font-display min-h-screen antialiased">
        <div
            class="bg-background-light border-beige/50 relative mx-auto min-h-screen w-full max-w-[480px] overflow-x-hidden border-x shadow-2xl md:mx-0 md:max-w-none md:border-0 md:shadow-none"
        >
            <header class="bg-background-light/80 sticky top-0 z-20 px-6 pt-12 pb-4 backdrop-blur-md md:px-8">
                <div class="flex items-center justify-between">
                    <Link href="/seeds" class="flex h-10 w-10 items-center justify-center rounded-full hover:bg-primary/10">
                        <span class="material-symbols-outlined">arrow_back_ios_new</span>
                    </Link>
                    <h1 class="text-lg font-semibold">Seemne detail</h1>
                    <button
                        type="button"
                        class="flex h-10 w-10 items-center justify-center rounded-full text-red-600 hover:bg-red-50"
                        @click="deleteSeed"
                        aria-label="Kustuta seeme"
                    >
                        <span class="material-symbols-outlined">delete</span>
                    </button>
                </div>
            </header>

            <main class="px-6 py-6 md:px-8">
                <img
                    v-if="props.seed.image_url"
                    :src="props.seed.image_url"
                    alt=""
                    class="mb-6 h-56 w-full rounded-2xl object-cover"
                />
                <div
                    v-else
                    class="mb-6 flex h-56 w-full items-center justify-center rounded-2xl bg-primary/10 text-primary"
                >
                    <span class="material-symbols-outlined text-5xl">potted_plant</span>
                </div>

                <h2 class="text-2xl font-bold">{{ props.seed.name }}</h2>

                <div class="mt-4 space-y-2 text-sm text-forest/80">
                    <p>Ostmise aasta: <strong>{{ props.seed.year ?? '—' }}</strong></p>
                    <p>Aegumiskuupäev: <strong>{{ props.seed.expires_at ?? '—' }}</strong></p>
                </div>

                <p v-if="props.seed.notes" class="mt-6 text-sm text-forest/80">
                    {{ props.seed.notes }}
                </p>
            </main>
        </div>
    </div>
</template>
