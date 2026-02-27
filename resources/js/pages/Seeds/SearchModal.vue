<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";



const props = defineProps<{
  open: boolean;
  initialQuery?: string;
  placeholder?: string;
  suggestions?: string[];
}>();

const emit = defineEmits<{
  (e: "update:open", v: boolean): void;
  (e: "search", query: string): void;
  (e: "clear"): void;
}>();

const query = ref(props.initialQuery ?? "");
const inputRef = ref<HTMLInputElement | null>(null);

const close = () => emit("update:open", false);

function onKeydown(e: KeyboardEvent) {
  if (!props.open) return;
  if (e.key === "Escape") close();
}

watch(
  () => props.open,
  async (isOpen) => {
    if (isOpen) {
      // lukusta scroll
      document.body.style.overflow = "hidden";
      await nextTick();
      inputRef.value?.focus();
      inputRef.value?.select();
    } else {
      document.body.style.overflow = "";
    }
  },
  { immediate: true }
);

onMounted(() => window.addEventListener("keydown", onKeydown));
onBeforeUnmount(() => {
  window.removeEventListener("keydown", onKeydown);
  document.body.style.overflow = "";
});

const canSearch = computed(() => query.value.trim().length > 0);

function submit() {
  if (!canSearch.value) return;
  emit("search", query.value.trim());
  close();
}

const matches = computed(() => {
  const q = query.value.trim().toLowerCase();
  if (!q) return [];

  return (props.suggestions ?? [])
    .filter((s) => s.toLowerCase().includes(q))
    .slice(0, 6);
});


function clear() {
  query.value = "";
  emit("clear");
  nextTick(() => inputRef.value?.focus());
}
</script>

<template>
  <transition name="fade">
    <div
      v-if="open"
      class="fixed inset-0 z-50 flex items-center justify-center p-4"
      aria-modal="true"
      role="dialog"
    >
      <!-- overlay -->
      <button
        class="absolute inset-0 bg-black/30 backdrop-blur-[2px]"
        @click="close"
        aria-label="Sulge otsing"
      />

      <!-- modal -->
      <div
        class="relative w-full max-w-lg rounded-3xl bg-[#FAF8F4] shadow-xl ring-1 ring-black/5"
      >
        <!-- väike “botanical” nurga-ornament (lihtne) -->
        <div class="pointer-events-none absolute -top-3 -left-3 opacity-20">
          <div class="h-10 w-10 rounded-full bg-[#E6E2D5]" />
        </div>

        <div class="p-5 sm:p-6">
          <div class="flex items-start justify-between gap-3">
            <div>
              <h3 class="text-lg font-semibold text-[#2E2E2E]">
                Otsi taimi
              </h3>
              <p class="mt-1 text-sm text-[#2E2E2E]/70">
                Sisesta nimi, sort või kategooria.
              </p>
            </div>

            <button
              class="rounded-full p-2 text-[#2E2E2E]/60 hover:bg-black/5 hover:text-[#2E2E2E]"
              @click="close"
              aria-label="Sulge"
            >
              ✕
            </button>
          </div>

          <div class="mt-4 flex gap-2">
            <div class="relative flex-1">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[#2E2E2E]/40">
                🔎
              </span>
              <input
                ref="inputRef"
                v-model="query"
                :placeholder="placeholder ?? 'Nt: kurk, tomat, till…'"
                class="w-full rounded-2xl border border-black/10 bg-white px-10 py-3 text-[#2E2E2E] shadow-sm outline-none focus:border-[#6B8C68] focus:ring-2 focus:ring-[#6B8C68]/20"
                @keydown.enter.prevent="submit"
              />

              <!-- Suggestions dropdown -->
            <div
                v-if="matches.length"
                class="absolute left-0 right-0 top-[calc(100%+8px)] z-10 overflow-hidden rounded-2xl border border-black/10 bg-white shadow-lg"
>
            <button
    v-for="m in matches"
    :key="m"
    type="button"
    class="w-full px-4 py-2 text-left text-sm text-[#2E2E2E] hover:bg-black/5"
    @click="query = m; submit()"
  >
    {{ m }}
  </button>
</div>


              <button
                v-if="query.length"
                class="absolute right-2 top-1/2 -translate-y-1/2 rounded-xl px-2 py-1 text-xs text-[#2E2E2E]/60 hover:bg-black/5"
                @click="clear"
                type="button"
              >
                Puhasta
              </button>
            </div>

            <button
              class="rounded-2xl px-4 py-3 font-medium shadow-sm transition disabled:opacity-50"
              :class="canSearch ? 'bg-[#6B8C68] text-white hover:bg-[#4F6A52]' : 'bg-black/10 text-[#2E2E2E]'"
              :disabled="!canSearch"
              @click="submit"
              type="button"
            >
              Otsi
            </button>
          </div>

          <div class="mt-4 flex items-center justify-between text-xs text-[#2E2E2E]/60">
            <span>Enter = otsi</span>
            <span>Esc = sulge</span>
          </div>
        </div>
      </div>
    </div>
  </transition>
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
