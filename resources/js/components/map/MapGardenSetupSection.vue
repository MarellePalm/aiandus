<script setup lang="ts">
import GardenShapeEditor from '@/components/GardenShapeEditor.vue';
import { gardenSetupChoiceClass } from '@/pages/map/gardenAddressSearch';
import type { GardenAddressSearchResult, GardenSetupMode } from '@/pages/map/types';

defineProps<{
    setupMode: GardenSetupMode | null;
    addressSearchQuery: string;
    addressSearchResults: GardenAddressSearchResult[];
    addressSearchLoading: boolean;
    addressSearchError: boolean;
    addressSearchOpen: boolean;
    geolocationLoading: boolean;
    gardenDimensionsMessage: string | null;
    widthMeters: number;
    heightMeters: number;
    formProcessing: boolean;
    ortophotoSetupReady: boolean;
    manualSetupReady: boolean;
    manualShapeMask: number[][];
    manualCellSizeCm: number;
}>();

const emit = defineEmits<{
    'choose-mode': [GardenSetupMode];
    'reset-mode': [];
    'update:addressSearchQuery': [string];
    'address-search-input': [];
    'address-search-focus': [];
    'select-address': [GardenAddressSearchResult];
    'request-geolocation': [];
    'save-ortophoto': [];
    'save-manual': [];
    'update:widthMeters': [number];
    'update:heightMeters': [number];
    'update:manualShapeMask': [number[][]];
    'update:manualCellSizeCm': [number];
}>();
</script>

<template>
    <div
        class="rounded-xl border-2 border-dashed border-primary/25 bg-background p-6 text-left sm:p-8"
    >
        <p class="text-center text-base font-semibold text-foreground">
            Aiaplaan on veel tühi
        </p>
        <p
            class="mx-auto mt-2 max-w-xl text-center text-sm leading-6 text-muted-foreground"
        >
            Vali, kuidas alustad. Ortofoto puhul määra asukoht ja aia mõõdud;
            käsitsi saad joonistada kuju ruudustikul (nt rõdu või terrass).
        </p>

        <div
            v-if="!setupMode"
            class="mx-auto mt-5 grid max-w-xl gap-3 sm:grid-cols-2"
        >
            <button
                type="button"
                :class="gardenSetupChoiceClass(false)"
                @click="emit('choose-mode', 'ortophoto')"
            >
                <span
                    class="flex items-center gap-2 text-sm font-semibold text-foreground"
                >
                    <span
                        class="material-symbols-outlined text-base text-primary"
                        >satellite_alt</span
                    >
                    Ortofoto
                </span>
                <span class="text-xs leading-5 text-muted-foreground"
                    >Aadress või asukoht + mõõdud meetrites.</span
                >
            </button>
            <button
                type="button"
                :class="gardenSetupChoiceClass(false)"
                @click="emit('choose-mode', 'manual')"
            >
                <span
                    class="flex items-center gap-2 text-sm font-semibold text-foreground"
                >
                    <span
                        class="material-symbols-outlined text-base text-primary"
                        >straighten</span
                    >
                    Käsitsi mõõdud
                </span>
                <span class="text-xs leading-5 text-muted-foreground"
                    >Sisesta laius ja sügavus meetrites (nt rõdu plaan).</span
                >
            </button>
        </div>

        <div v-else class="mx-auto mt-4 flex max-w-xl justify-center">
            <button
                type="button"
                class="text-xs font-semibold text-muted-foreground underline-offset-2 hover:text-foreground hover:underline"
                @click="emit('reset-mode')"
            >
                ← Vali teine viis
            </button>
        </div>

        <div
            v-if="setupMode === 'manual'"
            class="mx-auto mt-5 max-w-xl space-y-4"
        >
            <div>
                <p
                    class="mb-2 text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                >
                    Ruudu suurus
                </p>
                <div class="flex flex-wrap gap-1">
                    <button
                        v-for="size in [25, 50, 100, 200, 500, 1000]"
                        :key="size"
                        type="button"
                        class="rounded-full border px-2.5 py-1 text-xs font-medium transition"
                        :class="
                            manualCellSizeCm === size
                                ? 'border-primary/30 bg-primary/10 text-primary'
                                : 'border-border/70 bg-card text-foreground hover:bg-muted'
                        "
                        @click="emit('update:manualCellSizeCm', size)"
                    >
                        {{ size >= 100 ? size / 100 + ' m' : size + ' cm' }}
                    </button>
                </div>
            </div>

            <GardenShapeEditor
                :model-value="manualShapeMask"
                :cell-size-cm="manualCellSizeCm"
                @update:model-value="emit('update:manualShapeMask', $event)"
            />

            <p
                v-if="gardenDimensionsMessage"
                class="text-xs leading-5 text-muted-foreground"
            >
                {{ gardenDimensionsMessage }}
            </p>

            <div class="flex flex-wrap justify-center gap-2 pt-1">
                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-full border border-primary/20 bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="formProcessing || !manualSetupReady"
                    @click="emit('save-manual')"
                >
                    Salvesta kuju ja ava kaart
                </button>
            </div>
        </div>

        <div
            v-else-if="setupMode === 'ortophoto'"
            class="mx-auto mt-5 max-w-xl space-y-4"
        >
            <div>
                <p
                    class="text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                >
                    Aia asukoht
                </p>
                <p class="mt-1 text-sm leading-6 text-muted-foreground">
                    Otsi aadress või kasuta geolokatsiooni, seejärel sisesta aia
                    laius ja sügavus meetrites.
                </p>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start">
                <div class="relative min-w-0 flex-1">
                    <div
                        class="flex items-center rounded-2xl border border-border/70 bg-card/85 px-3 py-2 ring-offset-background focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20"
                    >
                        <input
                            :value="addressSearchQuery"
                            type="search"
                            autocomplete="off"
                            class="min-w-0 flex-1 bg-transparent text-sm font-medium text-foreground outline-none"
                            placeholder="Otsi aadressi..."
                            @input="
                                emit(
                                    'update:addressSearchQuery',
                                    ($event.target as HTMLInputElement).value,
                                );
                                emit('address-search-input');
                            "
                            @focus="emit('address-search-focus')"
                        />
                        <span
                            v-if="addressSearchLoading"
                            class="material-symbols-outlined shrink-0 animate-spin text-base text-muted-foreground"
                            aria-hidden="true"
                            >progress_activity</span
                        >
                    </div>
                    <div
                        v-if="
                            addressSearchOpen &&
                            (addressSearchResults.length > 0 ||
                                addressSearchLoading ||
                                addressSearchError)
                        "
                        class="absolute top-full right-0 left-0 z-30 mt-1 overflow-hidden rounded-xl border border-border/80 bg-card shadow-lg"
                    >
                        <ul
                            v-if="addressSearchResults.length"
                            class="max-h-52 overflow-y-auto py-1"
                        >
                            <li
                                v-for="result in addressSearchResults"
                                :key="result.id"
                            >
                                <button
                                    type="button"
                                    class="w-full px-3 py-2.5 text-left text-sm transition hover:bg-muted"
                                    @mousedown.prevent="
                                        emit('select-address', result)
                                    "
                                >
                                    {{ result.label }}
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>
                <button
                    type="button"
                    class="inline-flex h-11 shrink-0 items-center justify-center gap-1.5 rounded-2xl border border-border/70 bg-card/85 px-3 text-sm font-semibold text-foreground transition hover:bg-muted disabled:opacity-60"
                    :disabled="geolocationLoading"
                    @click="emit('request-geolocation')"
                >
                    <span
                        v-if="geolocationLoading"
                        class="material-symbols-outlined animate-spin text-base"
                        >progress_activity</span
                    >
                    <span v-else>📍</span>
                    <span>Minu asukoht</span>
                </button>
            </div>

            <p
                v-if="gardenDimensionsMessage"
                class="text-xs leading-5 text-muted-foreground"
            >
                {{ gardenDimensionsMessage }}
            </p>

            <div class="grid gap-3 sm:grid-cols-2">
                <label
                    class="rounded-2xl border border-border/70 bg-card/85 px-3 py-3 text-sm"
                >
                    <span
                        class="mb-1 block text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                        >Laius (m)</span
                    >
                    <input
                        :value="widthMeters"
                        type="number"
                        min="0.01"
                        max="1000"
                        step="0.01"
                        class="w-full bg-transparent text-sm font-medium text-foreground outline-none"
                        placeholder="12"
                        @input="
                            emit(
                                'update:widthMeters',
                                Number(
                                    ($event.target as HTMLInputElement).value,
                                ),
                            )
                        "
                    />
                </label>
                <label
                    class="rounded-2xl border border-border/70 bg-card/85 px-3 py-3 text-sm"
                >
                    <span
                        class="mb-1 block text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                        >Sügavus (m)</span
                    >
                    <input
                        :value="heightMeters"
                        type="number"
                        min="0.01"
                        max="1000"
                        step="0.01"
                        class="w-full bg-transparent text-sm font-medium text-foreground outline-none"
                        placeholder="8"
                        @input="
                            emit(
                                'update:heightMeters',
                                Number(
                                    ($event.target as HTMLInputElement).value,
                                ),
                            )
                        "
                    />
                </label>
            </div>

            <div class="flex flex-wrap justify-center gap-2 pt-1">
                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-full border border-primary/20 bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="formProcessing || !ortophotoSetupReady"
                    @click="emit('save-ortophoto')"
                >
                    Salvesta ja ava kaart
                </button>
            </div>
        </div>
    </div>
</template>
