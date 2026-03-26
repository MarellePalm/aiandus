<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';

import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { store } from '@/routes/register';

const showPassword = ref(false);
const showPasswordConfirmation = ref(false);
</script>

<template>
  <AuthBase
    title="Registreerimine"
    description="Alusta oma rohelist teekonda ja loo isiklik aia paevik tana."
    :back-href="'/'"
    :show-logo="true"
  >
    <Head title="Registreerimine" />

    <div class="w-full">
      <div class="panel w-full">
        <Form
          v-bind="store.form()"
          :reset-on-success="['password', 'password_confirmation']"
          v-slot="{ errors, processing }"
          class="auth-form"
        >
            <!-- Name -->
            <div class="flex flex-col gap-2">
              <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-muted-foreground">Nimi</span>
              </div>
              <label class="auth-field">
                <span class="sr-only">Nimi</span>
                <input
                  id="name"
                  name="name"
                  type="text"
                  required
                  autofocus
                  :tabindex="1"
                  autocomplete="name"
                  placeholder="Nimi"
                  class="auth-input"
                />
              </label>
              <InputError :message="errors.name" />
            </div>

            <!-- Email -->
            <div class="flex flex-col gap-2">
              <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-muted-foreground">Email</span>
              </div>
              <label class="auth-field">
                <span class="sr-only">Email</span>
                <input
                  id="email"
                  name="email"
                  type="email"
                  required
                  :tabindex="2"
                  autocomplete="email"
                  placeholder="Email"
                  class="auth-input"
                />
              </label>
              <InputError :message="errors.email" />
            </div>

            <!-- Password -->
            <div class="flex flex-col gap-2">
              <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-muted-foreground">Parool</span>
              </div>
              <label class="auth-field relative">
                <span class="sr-only">Parool</span>
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
              <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-muted-foreground">Kinnita parool</span>
              </div>
              <label class="auth-field relative">
                <span class="sr-only">Kinnita parool</span>
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
                class="btn-primary w-full"
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
    </div>
  </AuthBase>
</template>
