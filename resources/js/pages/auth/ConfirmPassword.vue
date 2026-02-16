<!-- resources/js/pages/Auth/ConfirmPassword.vue -->
<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';

import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { store } from '@/routes/password/confirm';
</script>

<template>
  <AuthLayout
    title="Kinnita parool"
    description="Turvalisuse huvides kinnita oma parool enne jätkamist."
  >
    <Head title="Kinnita parool" />

    <div class="panel w-full">
      <Form v-bind="store.form()" reset-on-success v-slot="{ errors, processing }" class="auth-form">
        <div class="flex flex-col gap-2">
          <label class="auth-field">
            <span class="auth-label">Parool</span>
            <input
              id="password"
              type="password"
              name="password"
              required
              autocomplete="current-password"
              autofocus
              placeholder="Parool"
              class="auth-input"
            />
          </label>

          <InputError :message="errors.password" />
        </div>

        <div class="pt-6">
          <Button
            class="btn-primary w-full"
            :disabled="processing"
            data-test="confirm-password-button"
          >
            <Spinner v-if="processing" class="mr-2" />
            Kinnita
          </Button>
        </div>
      </Form>
    </div>
  </AuthLayout>
</template>
