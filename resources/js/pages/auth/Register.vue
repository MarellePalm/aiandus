<!-- resources/js/pages/Auth/Register.vue (või sinu olemasolev asukoht) -->
<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

const showPassword = ref(false);
const showPasswordConfirmation = ref(false);
</script>

<template>
  <div class="page min-h-screen">
    <Head title="Registreerimine" />

    <div class="page-container-wide py-6 sm:py-10">
      <!-- Top bar -->
      <div class="flex items-center justify-between pb-2">
        <Link href="/" class="icon-btn" aria-label="Tagasi">
          <span class="material-symbols-outlined">arrow_back_ios</span>
        </Link>
        <div class="flex-1" />
      </div>

      <main class="auth-shell">
        <!-- Header -->
        <div class="auth-header">
          <div class="auth-icon">
            <span class="material-symbols-outlined text-4xl text-primary">potted_plant</span>
          </div>

          <h1 class="auth-title">Registreerimine</h1>
          <p class="auth-subtitle">
            Alusta oma rohelist teekonda ja loo isiklik aia päevik täna.
          </p>
        </div>

        <!-- Form -->
        <div class="panel w-full">
          <Form
            v-bind="store.form()"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="auth-form"
          >
            <!-- Name -->
            <div class="flex flex-col gap-2">
              <label class="auth-field">
                <span class="auth-label">Nimi</span>
                <input
                  id="name"
                  name="name"
                  type="text"
                  required
                  autofocus
                  :tabindex="1"
                  autocomplete="name"
                  placeholder="Sinu nimi"
                  class="auth-input"
                />
              </label>
              <InputError :message="errors.name" />
            </div>

            <!-- Email -->
            <div class="flex flex-col gap-2">
              <label class="auth-field">
                <span class="auth-label">E-post</span>
                <input
                  id="email"
                  name="email"
                  type="email"
                  required
                  :tabindex="2"
                  autocomplete="email"
                  placeholder="aiapidaja@mail.ee"
                  class="auth-input"
                />
              </label>
              <InputError :message="errors.email" />
            </div>

            <!-- Password -->
            <div class="flex flex-col gap-2">
              <label class="auth-field relative">
                <span class="auth-label">Parool</span>
                <input
                  id="password"
                  name="password"
                  :type="showPassword ? 'text' : 'password'"
                  required
                  :tabindex="3"
                  autocomplete="new-password"
                  placeholder="••••••••"
                  class="auth-input pr-12"
                />
                <button
                  type="button"
                  class="auth-eye"
                  @click="showPassword = !showPassword"
                  tabindex="-1"
                  :aria-label="showPassword ? 'Peida parool' : 'Näita parooli'"
                >
                  <span class="material-symbols-outlined">
                    {{ showPassword ? 'visibility_off' : 'visibility' }}
                  </span>
                </button>
              </label>
              <InputError :message="errors.password" />
            </div>

            <!-- Password confirmation -->
            <div class="flex flex-col gap-2">
              <label class="auth-field relative">
                <span class="auth-label">Kinnita parool</span>
                <input
                  id="password_confirmation"
                  name="password_confirmation"
                  :type="showPasswordConfirmation ? 'text' : 'password'"
                  required
                  :tabindex="4"
                  autocomplete="new-password"
                  placeholder="••••••••"
                  class="auth-input pr-12"
                />
                <button
                  type="button"
                  class="auth-eye"
                  @click="showPasswordConfirmation = !showPasswordConfirmation"
                  tabindex="-1"
                  :aria-label="showPasswordConfirmation ? 'Peida parool' : 'Näita parooli'"
                >
                  <span class="material-symbols-outlined">
                    {{ showPasswordConfirmation ? 'visibility_off' : 'visibility' }}
                  </span>
                </button>
              </label>
              <InputError :message="errors.password_confirmation" />
            </div>

            <!-- Actions -->
            <div class="auth-actions">
              <Button
                type="submit"
                class="btn-primary w-full rounded-xl"
                tabindex="5"
                :disabled="processing"
                data-test="register-user-button"
              >
                <Spinner v-if="processing" class="mr-2" />
                Loo konto
              </Button>

              <p class="text-center text-sm text-muted-foreground">
                On juba konto?
                <TextLink :href="login()" class="auth-link" :tabindex="6">
                  Logi sisse
                </TextLink>
              </p>
            </div>
          </Form>
        </div>
      </main>

      <!-- Decorative leaf (jätsin alles, nüüd tokenitega) -->
      <div class="pointer-events-none fixed right-[-50px] bottom-[-50px] opacity-[0.03] dark:opacity-[0.07]">
        <span class="material-symbols-outlined text-[300px] select-none text-primary">eco</span>
      </div>
    </div>
  </div>
</template>
