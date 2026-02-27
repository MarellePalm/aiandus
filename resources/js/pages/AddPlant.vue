<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

const form = ref({
    name: '',
    species: '',
    location: '',
    planted_at: '',
    notes: '',
});

const processing = ref(false);

function submit() {
    // TODO: wire this up to an Inertia post when backend route is ready
    // example:
    // router.post(route('plants.store'), form.value, { onStart: () => (processing.value = true), onFinish: () => (processing.value = false) });
    // For now we just log so the page is usable without backend.
    // eslint-disable-next-line no-console
    console.log('Add plant form submitted:', form.value);
}
</script>

<template>
    <div
        class="bg-[#fafaf9] dark:bg-[#171b17] text-[#141514] dark:text-gray-100 min-h-screen flex justify-center font-sans"
    >
        <Head title="Lisa taim">
            <!-- Ensure Material Symbols for icons -->
            <link
                href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
                rel="stylesheet"
            />
        </Head>

        <!-- Phone Form Factor Container -->
        <div
            class="w-full max-w-[430px] min-h-screen flex flex-col relative overflow-hidden"
        >
            <!-- TopAppBar -->
            <header
                class="flex items-center px-6 pt-12 pb-6 bg-[#fafaf9]/80 dark:bg-[#171b17]/80 backdrop-blur-md sticky top-0 z-10"
            >
                <button
                    type="button"
                    class="flex items-center justify-center size-10 rounded-full hover:bg-black/5 dark:hover:bg-white/5 transition-colors"
                    @click="window.history.back()"
                >
                    <span
                        class="material-symbols-outlined text-[#141514] dark:text-white"
                        >arrow_back_ios_new</span
                    >
                </button>
                <h1
                    class="flex-1 text-center text-lg font-bold tracking-tight text-[#141514] dark:text-white pr-10"
                >
                    Lisa Taim
                </h1>
            </header>

            <main class="flex-1 px-6 pb-24 flex flex-col gap-8">
                <!-- EmptyState / Photo Placeholder -->
                <div class="mt-4">
                    <div
                        class="group relative flex flex-col items-center justify-center aspect-square w-full rounded-[3rem] border-2 border-dashed border-[#dfe2df] dark:border-gray-700 bg-white/50 dark:bg-white/5 transition-all hover:border-[#679263]/50 overflow-hidden shadow-[0_4px_20px_-2px_rgba(0,0,0,0.05)]"
                    >
                        <div class="flex flex-col items-center gap-4 p-8 text-center">
                            <div
                                class="size-16 rounded-full bg-[#679263]/10 flex items-center justify-center text-[#679263]"
                            >
                                <span class="material-symbols-outlined !text-3xl"
                                    >add_a_photo</span
                                >
                            </div>
                            <div class="flex flex-col gap-1">
                                <p
                                    class="text-[#141514] dark:text-white text-lg font-bold leading-tight"
                                >
                                    Lisa foto
                                </p>
                                <p
                                    class="text-gray-500 dark:text-gray-400 text-sm font-normal"
                                >
                                    Sinu taime portree
                                </p>
                            </div>
                            <button
                                type="button"
                                class="mt-2 flex min-w-[120px] cursor-pointer items-center justify-center rounded-full h-11 px-6 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-[#141514] dark:text-white text-sm font-bold shadow-sm active:scale-95 transition-transform"
                            >
                                Vali fail
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Form Fields -->
                <form class="flex flex-col gap-6" @submit.prevent="submit">
                    <!-- TextField: Taime nimi -->
                    <div class="flex flex-col gap-2">
                        <label
                            class="text-[#141514] dark:text-gray-300 text-sm font-semibold px-1"
                            >Taime nimi</label
                        >
                        <div class="relative">
                            <input
                                v-model="form.name"
                                type="text"
                                required
                                placeholder="Kirjuta siia..."
                                class="w-full h-14 px-5 rounded-xl border-none bg-white dark:bg-gray-800 shadow-[0_4px_20px_-2px_rgba(0,0,0,0.05)] focus:ring-2 focus:ring-[#679263]/20 placeholder:italic placeholder:text-gray-400 text-[#141514] dark:text-white transition-all"
                            />
                        </div>
                    </div>

                    <!-- TextField: Sort -->
                    <div class="flex flex-col gap-2">
                        <label
                            class="text-[#141514] dark:text-gray-300 text-sm font-semibold px-1"
                            >Sort</label
                        >
                        <div class="relative">
                            <input
                                v-model="form.species"
                                type="text"
                                placeholder="nt. Monstera Deliciosa"
                                class="w-full h-14 px-5 rounded-xl border-none bg-white dark:bg-gray-800 shadow-[0_4px_20px_-2px_rgba(0,0,0,0.05)] focus:ring-2 focus:ring-[#679263]/20 placeholder:text-gray-400 text-[#141514] dark:text-white transition-all"
                            />
                        </div>
                    </div>

                    <!-- TextField: Istutamise kuupäev -->
                    <div class="flex flex-col gap-2">
                        <label
                            class="text-[#141514] dark:text-gray-300 text-sm font-semibold px-1"
                            >Istutamise kuupäev</label
                        >
                        <div class="relative">
                            <input
                                v-model="form.planted_at"
                                type="date"
                                class="w-full h-14 px-5 rounded-xl border-none bg-white dark:bg-gray-800 shadow-[0_4px_20px_-2px_rgba(0,0,0,0.05)] focus:ring-2 focus:ring-[#679263]/20 text-[#141514] dark:text-white transition-all cursor-pointer"
                            />
                        </div>
                    </div>

                    <!-- Extra Spacing for Slow Paced Feel -->
                    <div class="h-12" />

                    <!-- Bottom Primary Button -->
                    <div class="mt-auto">
                        <button
                            type="submit"
                            :disabled="processing"
                            class="w-full h-16 bg-[#679263] text-white font-bold text-lg rounded-xl shadow-lg shadow-[#679263]/20 active:scale-[0.98] transition-all flex items-center justify-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed"
                        >
                            <span class="material-symbols-outlined">potted_plant</span>
                            Salvesta taim
                        </button>
                    </div>
                </form>
            </main>
        </div>
    </div>
</template>
