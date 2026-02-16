<!-- resources/js/pages/Auth/ForgotPassword.vue -->
<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';

import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineProps<{
  status?: string;
}>();
</script>

<template>
  <AuthLayout
    title="Unustasid salasõna?"
    description="Sisesta oma meiliaadress, saadame sellele lingi salasõna taastamiseks"
  >
    <Head title="Taasta salasõna" />

    <div v-if="status" class="mb-4 text-center text-sm font-medium text-primary">
      {{ status }}
    </div>

    <div class="space-y-6">
      <div class="panel w-full">
        <Form v-bind="email.form()" v-slot="{ errors, processing }" class="auth-form">
          <div class="flex flex-col gap-2">
            <label class="auth-field">
              <span class="auth-label">E-post</span>
              <input
                id="email"
                type="email"
                name="email"
                autocomplete="off"
                autofocus
                placeholder="email@näide.com"
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

      <div class="text-center text-sm text-muted-foreground">
        <span>Tagasi</span>
        <TextLink :href="login()" class="auth-link">sisselogimislehele</TextLink>
      </div>
    </div>
  </AuthLayout>
</template>
