<!-- resources/js/pages/Auth/Login.vue -->
<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';

import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineProps<{
  status?: string;
  canResetPassword: boolean;
  canRegister: boolean;
}>();
</script>

<template>
  <AuthBase
    title="Logi sisse"
    description="Tere tulemast tagasi aeda"
    :back-href="'/'"
    :show-logo="true"
  >
    <Head title="Logi sisse" />

    <div class="w-full">
      <div v-if="status" class="mb-4 text-center text-sm font-medium text-primary">
        {{ status }}
      </div>

      <div class="panel w-full">
        <Form
          v-bind="store.form()"
          :reset-on-success="['password']"
          v-slot="{ errors, processing }"
          class="auth-form"
        >
          <!-- Email -->
          <div class="flex flex-col gap-2">
            <label class="auth-field">
              <span class="auth-label">Email aadress</span>
              <input
                id="email"
                type="email"
                name="email"
                required
                autofocus
                :tabindex="1"
                autocomplete="email"
                placeholder="email@näide.com"
                class="auth-input"
              />
            </label>
            <InputError :message="errors.email" />
          </div>

          <!-- Password -->
          <div class="flex flex-col gap-2">
            <div class="flex items-center justify-between">
              <span class="text-sm font-medium text-muted-foreground">Parool</span>

              <TextLink
                v-if="canResetPassword"
                :href="request()"
                class="text-sm text-primary hover:underline"
                :tabindex="5"
              >
                Unustasid parooli?
              </TextLink>
            </div>

            <label class="auth-field">
              <span class="sr-only">Parool</span>
              <input
                id="password"
                type="password"
                name="password"
                required
                :tabindex="2"
                autocomplete="current-password"
                placeholder="Parool"
                class="auth-input"
              />
            </label>

            <InputError :message="errors.password" />
          </div>

          <!-- Remember -->
          <label class="flex items-center gap-3 pt-2 select-none">
            <input
              id="remember"
              name="remember"
              type="checkbox"
              class="h-5 w-5 rounded-md border-border bg-transparent text-primary focus:ring-0 focus:ring-offset-0"
              :tabindex="3"
            />
            <span class="text-sm text-foreground/90">Mäleta mind</span>
          </label>

          <!-- Actions -->
          <div class="pt-6 space-y-4">
            <Button
              type="submit"
              class="btn-primary w-full"
              :tabindex="4"
              :disabled="processing"
              data-test="login-button"
            >
              <Spinner v-if="processing" class="mr-2" />
              Logi sisse <span aria-hidden="true">→</span>
            </Button>

            <div v-if="canRegister" class="text-center text-sm text-muted-foreground">
              Sul pole veel kontot?
              <TextLink :href="register()" class="auth-link" :tabindex="6">
                Registreeru siin
              </TextLink>
            </div>
          </div>
        </Form>
      </div>
    </div>
  </AuthBase>
</template>
