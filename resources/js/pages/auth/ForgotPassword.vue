<!-- resources/js/pages/Auth/ForgotPassword.vue -->
<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';

import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineProps<{
  status?: string;
}>();
</script>

<template>
  <AuthBase
    title="Unustasid salasõna?"
    description="Sisesta oma meiliaadress, saadame sellele lingi salasõna taastamiseks"
    :back-href="'/'"
    :show-logo="true"
  >
    <Head title="Taasta salasõna" />

    <div v-if="status" class="mb-4 text-center text-sm font-medium text-primary">
      {{ status }}
    </div>

    <div class="w-full">
      <div class="panel w-full">
        <Form v-bind="email.form()" v-slot="{ errors, processing }" class="auth-form">
          <div class="flex flex-col gap-2">
            <div class="flex items-center justify-between">
              <span class="text-sm font-medium text-muted-foreground">Email</span>
            </div>
            <label class="auth-field">
              <span class="sr-only">Email</span>
              <input
                id="email"
                type="email"
                name="email"
                autocomplete="off"
                autofocus
                placeholder="Email"
                class="auth-input"
              />
            </label>
            <InputError :message="errors.email" />
          </div>

          <div class="pt-6">
            <Button
              class="btn-primary w-full"
              :disabled="processing"
              data-test="email-password-reset-link-button"
            >
              <Spinner v-if="processing" class="mr-2" />
              Taasta
            </Button>
          </div>
        </Form>
      </div>

      <div class="pt-4 text-center text-sm text-muted-foreground">
        Tagasi
        <TextLink :href="login()" class="auth-link">sisselogimislehele</TextLink>
      </div>
    </div>
  </AuthBase>
</template>

<style scoped>
input:-webkit-autofill,
input:-webkit-autofill:hover,
input:-webkit-autofill:focus,
input:-webkit-autofill:active {
  -webkit-text-fill-color: inherit;
  -webkit-box-shadow: 0 0 0 1000px transparent inset;
  transition: background-color 9999s ease-in-out 0s;
}
</style>
