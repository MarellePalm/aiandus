<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import BottomNav from '@/pages/BottomNav.vue'
import UserMenu from '@/pages/UserMenu.vue'

type ChipKey = 'all' | 'week' | 'month' | 'favorites'
type NoteKind = 'task' | 'reminder' | 'journal'

type Note = {
  id: string
  dateISO: string
  kind: NoteKind
  title: string
  body?: string | null
  time?: string | null
  tag: string
  tagStyle?: 'primary' | 'neutral'
  favorite?: boolean
  done?: boolean | null
  images?: string[]
}

type Group = { label: string; items: Note[] }

const props = withDefaults(
  defineProps<{
    notes?: Array<{
      id: number
      note_date: string
      title?: string | null
      body?: string | null
      type?: string
      done?: boolean | null
      due_at?: string | null
      media_urls?: string[]
    }>
    filters?: { q?: string; chip?: ChipKey }
  }>(),
  { notes: () => [] }
)

// Backend saadab "notes", komponent kasutab kujundatud "items"
const items = computed<Note[]>(() =>
  (props.notes ?? []).map((n) => ({
    id: String(n.id),
    dateISO: n.note_date,
    kind: (n.type === 'task' ? 'task' : n.type === 'reminder' ? 'reminder' : 'journal') as NoteKind,
    title: n.title ?? '',
    body: n.body ?? null,
    time: n.due_at ? new Date(n.due_at).toLocaleTimeString('et-EE', { hour: '2-digit', minute: '2-digit' }) : null,
    tag: n.type === 'task' ? 'Ülesanne' : n.type === 'reminder' ? 'Meeldetuletus' : 'Märge',
    tagStyle: (n.type === 'task' ? 'neutral' : 'primary') as 'primary' | 'neutral',
    done: n.done ?? null,
    favorite: false,
    images: n.media_urls ?? [],
  }))
)

const chips: { key: ChipKey; label: string }[] = [
  { key: 'all', label: 'Kõik' },
  { key: 'week', label: 'Sel nädalal' },
  { key: 'month', label: 'Sel kuul' },
  { key: 'favorites', label: 'Lemmikud' },
]

const query = ref(props.filters?.q ?? '')
const activeChip = ref<ChipKey>(props.filters?.chip ?? 'all')

function chipClass(key: ChipKey) {
  return key === activeChip.value
    ? 'bg-primary text-primary-foreground'
    : 'bg-secondary text-muted-foreground cursor-pointer hover:bg-secondary/80'
}

function parseISODate(iso: string) {
  const [y, m, d] = iso.split('-').map(Number)
  return new Date(y, (m ?? 1) - 1, d ?? 1)
}

function humanLabel(dateISO: string) {
  const d = parseISODate(dateISO)
  const dd = String(d.getDate()).padStart(2, '0')
  const mm = String(d.getMonth() + 1).padStart(2, '0')
  const yyyy = d.getFullYear()
  return `${dd}.${mm}.${yyyy}`
}

const filteredGroups = computed<Group[]>(() => {
  const map = new Map<string, Note[]>()

  for (const n of items.value) {
    const label = humanLabel(n.dateISO)
    map.set(label, [...(map.get(label) ?? []), n])
  }

  return [...map.entries()].map(([label, groupItems]) => ({ label, items: groupItems }))
})

function refresh() {
  router.get(
    '/calendar/overview',
    { q: query.value || undefined, chip: activeChip.value || undefined },
    { preserveState: true, preserveScroll: true, replace: true }
  )
}

function toggleDone(noteId: string) {
  router.post(`/calendar/notes/${noteId}/toggle-done`, {}, { preserveScroll: true, onSuccess: () => refresh() })
}

function onAdd() {
  window.location.href = '/calendar/note-form'
}

// debounce-lite: refresh 300ms pärast trükkimist
let t: number | undefined
watch(query, () => {
  window.clearTimeout(t)
  t = window.setTimeout(() => refresh(), 300)
})
</script>

<template>
  <div class="page page-with-bottomnav">
    <Head title="Koondvaade">
      <link rel="preconnect" href="https://fonts.googleapis.com" />
      <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="anonymous" />
      <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet"
      />
      <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet"
      />
    </Head>

    <header class="topappbar border-b border-border bg-background/80">
      <div class="flex items-center gap-2">
        <span class="material-symbols-outlined text-primary text-3xl">potted_plant</span>
      </div>

      <h1 class="text-xl font-bold tracking-tight">Minu märkmed</h1>

      <UserMenu settings-href="/settings" />
    </header>

    <main class="flex-1 pb-24">
      <div class="p-4">
        <div class="card p-4">
          <div class="relative mb-4">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground">
              search
            </span>
            <input
              v-model.trim="query"
              class="w-full pl-10 pr-4 h-11 bg-secondary/60 border-none rounded-lg focus:ring-2 focus:ring-ring/40 text-sm outline-none"
              placeholder="Otsi märkmeid..."
              type="text"
            />
          </div>

          <div class="flex gap-2 overflow-x-auto no-scrollbar mb-4">
            <button
              v-for="chip in chips"
              :key="chip.key"
              type="button"
              class="px-4 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap transition"
              :class="chipClass(chip.key)"
              @click="activeChip = chip.key; refresh()"
            >
              {{ chip.label }}
            </button>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <button
              type="button"
              class="flex items-center justify-between px-3 py-2 bg-secondary/60 rounded-lg text-xs font-medium text-muted-foreground"
              disabled
            >
              <span>Tüüp</span>
              <span class="material-symbols-outlined cursor-pointer text-sm">expand_more</span>
            </button>
            <button
              type="button"
              class="flex items-center justify-between px-3 py-2 bg-secondary/60 rounded-lg text-xs font-medium text-muted-foreground"
              disabled
            >
              <span>Kategooria</span>
              <span class="material-symbols-outlined cursor-pointer text-sm">expand_more</span>
            </button>
          </div>
        </div>
      </div>

      <div class="px-4 space-y-6">
        <section v-for="group in filteredGroups" :key="group.label">
          <h3 class="text-sm font-bold text-muted-foreground mb-3 uppercase tracking-wider">
            {{ group.label }}
          </h3>

          <div class="space-y-3">
            <article
              v-for="note in group.items"
              :key="note.id"
              class="card p-4"
              :class="note.kind === 'reminder' ? 'border-l-4 border-l-primary' : ''"
            >
              <div v-if="note.kind === 'task'" class="flex items-start gap-3">
                <div class="mt-0.5">
                  <input
                    class="size-5 rounded border-input text-primary focus:ring-ring"
                    type="checkbox"
                    :checked="!!note.done"
                    @change="toggleDone(note.id)"
                  />
                </div>

                <div class="flex-1">
                  <div class="flex justify-between items-start gap-3">
                    <h4 class="font-semibold" :class="note.done ? 'line-through opacity-60' : ''">
                      {{ note.title }}
                    </h4>

                    <span
                      class="text-[10px] px-2 py-0.5 rounded uppercase font-bold"
                      :class="note.tagStyle === 'primary' ? 'bg-primary/15 text-primary' : 'bg-secondary text-muted-foreground'"
                    >
                      {{ note.tag }}
                    </span>
                  </div>

                  <p v-if="note.body" class="text-xs text-muted-foreground mt-1">
                    {{ note.body }}
                  </p>

                  <div class="mt-3 flex gap-2">
                    <Link
                      class="px-3 py-2 rounded-lg text-xs font-semibold bg-secondary text-muted-foreground"
                      :href="`/calendar/notes/${note.id}/edit`"
                    >
                      Muuda
                    </Link>
                  </div>
                </div>
              </div>

              <div v-else-if="note.kind === 'reminder'">
                <div class="flex justify-between items-start mb-2">
                  <div class="flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-primary text-lg">
                      notifications_active
                    </span>
                    <h4 class="font-semibold">{{ note.title }}</h4>
                  </div>
                  <span class="text-[10px] text-muted-foreground font-medium">{{ note.time }}</span>
                </div>
                <p class="text-sm text-muted-foreground">{{ note.body }}</p>

                <div class="mt-3 flex gap-2">
                  <button
                    type="button"
                    class="px-3 py-2 rounded-lg text-xs font-semibold bg-primary text-primary-foreground"
                    @click="toggleDone(note.id)"
                  >
                    Märgi tehtuks
                  </button>
                  <Link
                    class="px-3 py-2 rounded-lg text-xs font-semibold bg-secondary text-muted-foreground"
                    :href="`/calendar/notes/${note.id}/edit`"
                  >
                    Muuda
                  </Link>
                </div>
              </div>

              <div v-else class="space-y-3">
                <div class="flex justify-between items-start gap-3">
                  <h4 class="font-semibold">{{ note.title }}</h4>
                  <span class="text-[10px] px-2 py-0.5 rounded uppercase font-bold bg-primary/15 text-primary">
                    {{ note.tag }}
                  </span>
                </div>

                <p class="text-sm text-muted-foreground">
                  {{ note.body }}
                </p>

                <div v-if="note.images?.length" class="flex gap-2 overflow-x-auto no-scrollbar">
                  <div
                    v-for="(img, idx) in note.images"
                    :key="idx"
                    class="size-20 shrink-0 rounded-lg bg-center bg-cover border border-border"
                    :style="{ backgroundImage: `url('${img}')` }"
                    role="img"
                    :aria-label="`Pilt ${idx + 1}`"
                  />
                </div>

                <div class="flex gap-2">
                  <Link
                    class="px-3 py-2 rounded-lg text-xs font-semibold bg-secondary text-muted-foreground"
                    :href="`/calendar/notes/${note.id}/edit`"
                  >
                    Muuda
                  </Link>
                </div>
              </div>
            </article>

            <p v-if="group.items.length === 0" class="text-sm text-muted-foreground">
              Pole kirjeid.
            </p>
          </div>
        </section>
      </div>
    </main>

    <button
      type="button"
      class="fixed bottom-24 right-6 cursor-pointer size-14 bg-primary text-primary-foreground rounded-full shadow-lg flex items-center justify-center z-40 active:scale-95 transition-transform"
      aria-label="Lisa"
      @click="onAdd"
    >
      <span class="material-symbols-outlined text-3xl">add</span>
    </button>

    <BottomNav active="calendar" />
  </div>
</template>

<style scoped>
:global(body) {
  font-family: "Plus Jakarta Sans", var(--font-sans);
}
</style>