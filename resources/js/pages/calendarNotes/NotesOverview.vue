<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

import BackIconButton from '@/components/BackIconButton.vue'
import DiaryHeader from '@/components/DiaryHeader.vue'
import FloatingPlusButton from '@/components/FloatingPlusButton.vue'
import BottomNav from '@/pages/BottomNav.vue'

type ChipKey = 'all' | 'month' | 'reminders'
type NoteKind = 'reminder' | 'journal'

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
    kind: (n.due_at ? 'reminder' : 'journal') as NoteKind,
    title: n.title ?? '',
    body: n.body ?? null,
    time: n.due_at ? new Date(n.due_at).toLocaleTimeString('et-EE', { hour: '2-digit', minute: '2-digit' }) : null,
    tag: n.due_at ? 'Meeldetuletus' : 'Märge',
    tagStyle: (n.due_at ? 'neutral' : 'primary') as 'primary' | 'neutral',
    done: n.done ?? null,
    favorite: false,
    images: n.media_urls ?? [],
  }))
)

const chips: { key: ChipKey; label: string }[] = [
  { key: 'all', label: 'Kõik' },
  { key: 'month', label: 'Sel kuul' },
  { key: 'reminders', label: 'Meeldetuletused' },
]

const query = ref(props.filters?.q ?? '')
const activeChip = ref<ChipKey>(props.filters?.chip ?? 'all')

function chipClass(key: ChipKey) {
  const base = 'min-h-9 w-full rounded-full px-2 py-1 text-center text-xs leading-tight font-semibold whitespace-normal break-words transition-colors'
  return key === activeChip.value
    ? `${base} bg-primary text-white`
    : `${base} bg-primary/10 text-primary hover:bg-primary/15`
}

function parseISODate(iso: string) {
  const [y, m, d] = iso.split('-').map(Number)
  return new Date(y, (m ?? 1) - 1, d ?? 1)
}

function toISODate(date: Date) {
  const yyyy = date.getFullYear()
  const mm = String(date.getMonth() + 1).padStart(2, '0')
  const dd = String(date.getDate()).padStart(2, '0')
  return `${yyyy}-${mm}-${dd}`
}

function humanLabel(dateISO: string) {
  const d = parseISODate(dateISO)
  const dd = String(d.getDate()).padStart(2, '0')
  const mm = String(d.getMonth() + 1).padStart(2, '0')
  const yyyy = d.getFullYear()
  return `${dd}.${mm}.${yyyy}`
}

const filteredGroups = computed<Group[]>(() => {
  const now = new Date()
  const from = new Date(now)
  from.setDate(now.getDate() - 6)

  const byISO = new Map<string, Note[]>()
  for (const n of items.value) {
    const d = parseISODate(n.dateISO)
    const dayOnly = new Date(d.getFullYear(), d.getMonth(), d.getDate())

    // Ajafilter chip'i järgi
    if (
      activeChip.value === 'month' &&
      (dayOnly.getMonth() !== now.getMonth() || dayOnly.getFullYear() !== now.getFullYear())
    ) continue
    if (activeChip.value === 'reminders' && n.kind !== 'reminder') continue
    byISO.set(n.dateISO, [...(byISO.get(n.dateISO) ?? []), n])
  }

  // "Kõik" ja "Sel nädalal": näita alati viimase 7 päeva kuupäevi, ka tühje päevi.
  if (activeChip.value === 'all') {
    const groups: Group[] = []
    for (let i = 0; i < 7; i += 1) {
      const d = new Date(now)
      d.setDate(now.getDate() - i)
      const iso = toISODate(d)
      groups.push({
        label: humanLabel(iso),
        items: byISO.get(iso) ?? [],
      })
    }
    return groups
  }

  // Muudel filtritel näita ainult olemasolevaid kuupäevi, uusim ees.
  const sorted = [...byISO.entries()].sort(([a], [b]) => (a < b ? 1 : -1))
  return sorted.map(([iso, groupItems]) => ({ label: humanLabel(iso), items: groupItems }))
})

function refresh() {
  router.get(
    '/calendar/overview',
    { q: query.value || undefined, chip: activeChip.value || undefined },
    { preserveState: true, preserveScroll: true, replace: true }
  )
}

function clearQuery() {
  query.value = ''
  refresh()
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
      <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet"
      />
    </Head>

    <div class="bg-background text-foreground font-display min-h-screen antialiased">
      <div class="bg-background border-beige/50 relative mx-auto min-h-screen w-full max-w-[480px] overflow-x-hidden border-x shadow-2xl md:mx-0 md:max-w-none md:border-0 md:shadow-none">
        <DiaryHeader
          title="Märkmed"
          title-class="text-foreground text-3xl font-bold tracking-tight"
          header-class="pt-6"
          top-row-class="mb-3"
          bottom-row-class="mb-4"
        >
          <template #leading>
            <BackIconButton href="/calendar" aria-label="Tagasi kalendrisse" />
          </template>
        </DiaryHeader>

        <main class="flex-1 px-6 py-4 pb-24 md:px-8">
          <div class="rounded-2xl border border-primary/10 bg-white/70 p-4 shadow-soft">
          <div class="relative mb-3">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground">
              search
            </span>
            <input
              v-model.trim="query"
              class="w-full pl-10 pr-4 h-11 bg-secondary/60 border-none rounded-xl focus:ring-2 focus:ring-ring/40 text-sm outline-none"
              placeholder="Otsi märkmeid..."
              type="text"
            />
          </div>

          <div class="grid grid-cols-3 gap-2 pb-1">
            <button
              v-for="chip in chips"
              :key="chip.key"
              type="button"
              :class="chipClass(chip.key)"
              @click="activeChip = chip.key; refresh()"
            >
              {{ chip.label }}
            </button>
          </div>
        </div>

      <div class="space-y-6 pt-6">
        <!-- Tühi olek -->
        <div
          v-if="filteredGroups.length === 0"
          class="rounded-2xl border-2 border-dashed border-border bg-muted/20 p-8 text-center"
        >
          <span class="material-symbols-outlined text-4xl text-muted-foreground mb-3 block">edit_note</span>
          <p class="text-sm font-medium text-foreground mb-1">
            {{ query ? 'Otsingutulemusi ei leitud' : 'Märkmeid pole' }}
          </p>
          <p class="text-xs text-muted-foreground mb-4">
            {{ query ? 'Proovi teistsugust otsisõna või filtri.' : 'Lisa märge kalendrist või paremal alanurgas oleva + nupuga.' }}
          </p>
          <Link
            v-if="!query"
            href="/calendar/note-form"
            class="inline-flex items-center gap-2 rounded-xl bg-primary text-primary-foreground px-4 py-2.5 text-sm font-semibold hover:bg-primary/90 transition"
          >
            <span class="material-symbols-outlined text-lg">add</span>
            Lisa märge
          </Link>
          <button
            v-else
            type="button"
            class="inline-flex items-center gap-2 rounded-xl border border-border bg-card px-4 py-2.5 text-sm font-semibold hover:bg-muted transition"
            @click="clearQuery()"
          >
            Tühjenda otsing
          </button>
        </div>

        <section v-for="group in filteredGroups" :key="group.label">
          <h3 class="text-sm font-bold text-muted-foreground mb-3 uppercase tracking-wider">
            {{ group.label }}
          </h3>

          <div class="grid grid-cols-2 gap-3">
            <article
              v-for="note in group.items"
              :key="note.id"
              class="rounded-2xl border border-border/60 bg-card p-3 shadow-soft transition hover:border-primary/30 hover:shadow-md cursor-pointer"
              role="button"
              tabindex="0"
              @click="router.visit(`/calendar/notes/${note.id}/edit`)"
              @keydown.enter="router.visit(`/calendar/notes/${note.id}/edit`)"
            >
              <div class="mb-2 flex items-start justify-between gap-2">
                <span
                  class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold"
                  :class="note.kind === 'journal' ? 'bg-primary/10 text-primary' : 'bg-secondary text-muted-foreground'"
                >
                  <span class="material-symbols-outlined text-[13px]">
                    {{ note.kind === 'reminder' ? 'notifications_active' : 'description' }}
                  </span>
                  {{ note.tag }}
                </span>
              </div>

              <h4 class="font-semibold text-base leading-snug line-clamp-2" :class="note.done ? 'line-through opacity-60' : ''">
                {{ note.title || 'Nimetu kirje' }}
              </h4>
              <p v-if="note.body" class="mt-1 text-xs text-muted-foreground leading-relaxed line-clamp-2">
                {{ note.body }}
              </p>

              <div class="mt-2">
                <p v-if="note.time" class="text-[11px] font-semibold text-primary">{{ note.time }}</p>
                <div v-if="note.images?.length" class="mt-2 flex gap-2 overflow-x-auto no-scrollbar">
                  <div
                    v-for="(img, idx) in note.images"
                    :key="idx"
                    class="size-14 shrink-0 rounded-lg bg-center bg-cover border border-border"
                    :style="{ backgroundImage: `url('${img}')` }"
                    role="img"
                    :aria-label="`Pilt ${idx + 1}`"
                  />
                </div>
              </div>
            </article>

            <p v-if="group.items.length === 0" class="col-span-2 text-sm text-muted-foreground">
              Pole kirjeid.
            </p>
          </div>
        </section>
      </div>
        </main>

        <FloatingPlusButton aria-label="Lisa märge" :size-px="52" :icon-size-px="30" @click="onAdd" />

        <BottomNav active="calendar" />
      </div>
    </div>
  </div>
</template>