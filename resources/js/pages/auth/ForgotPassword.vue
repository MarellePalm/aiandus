<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';

import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
        <Head title="Forgot password" />

        <div
            v-if="status"
            class="mb-4 text-center text-sm font-medium text-green-600"
        >
            {{ status }}
        </div>

        <div class="space-y-6">
            <Form v-bind="email.form()" v-slot="{ errors, processing }">
                <div class="grid gap-2">
                    <Label for="email">E-post</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        autocomplete="off"
                        autofocus
                        placeholder="email@näide.com"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="my-6 flex items-center justify-start">
                    <Button
                        class="w-full  bg-[#5f8047]"
                        :disabled="processing"
                        data-test="email-password-reset-link-button"
                    >
                        <Spinner v-if="processing" />
                        Taasta
                    </Button>
                </div>
            </Form>

            <div class="space-x-1 text-center text-sm text-muted-foreground">
                <span>Tagasi</span>
                <TextLink :href="login()">sisselogimislehele</TextLink>
            </div>
        </div>
    </AuthLayout>
</template>
