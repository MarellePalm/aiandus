<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';

import GoogleLogo from '@/components/GoogleLogo.vue';
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
    <Head title="Registreerimine" />

    <div class="hidden min-h-screen bg-background p-6 lg:grid lg:grid-cols-[minmax(0,1fr)_minmax(430px,520px)] lg:gap-10">
        <section class="relative overflow-hidden rounded-3xl border border-border bg-card">
            <img src="/welcome-garden.png" alt="Aed" class="absolute inset-0 h-full w-full object-cover" />
            <div class="absolute inset-0 bg-gradient-to-r from-background/95 via-background/85 to-transparent"></div>
            <div class="relative z-10 flex h-full flex-col justify-between p-10">
                <div class="flex items-center gap-2 text-primary">
                    <span class="material-symbols-outlined text-[22px]">potted_plant</span>
                    <span class="text-2xl font-bold tracking-tight">Aiapäevik</span>
                </div>
                <div class="max-w-sm">
                    <h1 class="text-5xl leading-[1.05] font-extrabold tracking-tight text-foreground">
                        Loo oma
                        <span class="text-primary">aia</span>
                        konto
                    </h1>
                    <p class="mt-4 text-lg leading-8 text-foreground/80">
                        Alusta oma rohelist teekonda ja loo isiklik aiapäevik.
                    </p>
                </div>
            </div>
        </section>

        <section class="flex items-center justify-center">
            <div class="panel w-full max-w-xl rounded-3xl border border-border bg-card p-8 shadow-sm">
                <div class="mb-6 text-center">
                    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-primary/10">
                        <span class="material-symbols-outlined text-3xl text-primary">potted_plant</span>
                    </div>
                    <h2 class="text-4xl font-bold tracking-tight text-foreground">Registreerimine</h2>
                    <p class="mt-2 text-base text-muted-foreground">Loo oma konto ja alusta aiapäevikuga</p>
                </div>

                <Form
                    v-bind="store.form()"
                    :reset-on-success="['password', 'password_confirmation']"
                    v-slot="{ errors, processing }"
                    class="auth-form"
                >
                    <div class="flex flex-col gap-2">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-muted-foreground">Nimi</span>
                        </div>
                        <label class="auth-field">
                            <span class="sr-only">Nimi</span>
                            <input
                                id="name-desktop"
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

                    <div class="flex flex-col gap-2">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-muted-foreground">E-post</span>
                        </div>
                        <label class="auth-field">
                            <span class="sr-only">E-post</span>
                            <input
                                id="email-desktop"
                                name="email"
                                type="email"
                                required
                                :tabindex="2"
                                autocomplete="email"
                                placeholder="E-post"
                                class="auth-input"
                            />
                        </label>
                        <InputError :message="errors.email" />
                    </div>

                    <div class="flex flex-col gap-2">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-muted-foreground">Parool</span>
                        </div>
                        <label class="auth-field relative">
                            <span class="sr-only">Parool</span>
                            <input
                                id="password-desktop"
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

                    <div class="flex flex-col gap-2">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-muted-foreground">Kinnita parool</span>
                        </div>
                        <label class="auth-field relative">
                            <span class="sr-only">Kinnita parool</span>
                            <input
                                id="password_confirmation-desktop"
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
                                :aria-label="
                                    showPasswordConfirmation ? 'Peida parool' : 'Näita parooli'
                                "
                            >
                                <span class="material-symbols-outlined">
                                    {{ showPasswordConfirmation ? 'visibility_off' : 'visibility' }}
                                </span>
                            </button>
                        </label>
                        <InputError :message="errors.password_confirmation" />
                    </div>

                    <div class="auth-actions">
                        <Button
                            type="submit"
                            class="btn-primary w-full"
                            tabindex="5"
                            :disabled="processing"
                            data-test="register-user-button-desktop"
                        >
                            <Spinner v-if="processing" class="mr-2" />
                            Loo konto
                        </Button>
                        <a
                            href="/auth/redirect"
                            class="btn-primary flex w-full items-center justify-center gap-2 bg-primary/70 hover:bg-primary/75"
                            :tabindex="7"
                        >
                            <GoogleLogo />
                            Logi sisse Google kontoga
                        </a>

                        <p class="text-center text-sm text-muted-foreground">
                            On juba konto?
                            <TextLink :href="login()" class="auth-link" :tabindex="6">
                                Logi sisse
                            </TextLink>
                        </p>
                    </div>
                </Form>
            </div>
        </section>
    </div>

    <AuthBase
        title="Registreerimine"
        description="Alusta oma rohelist teekonda ja loo isiklik aiapäevik juba täna."
        :back-href="'/'"
        :show-logo="true"
        class="lg:hidden"
    >
        <div class="w-full">
            <div class="panel w-full">
                <Form
                    v-bind="store.form()"
                    :reset-on-success="['password', 'password_confirmation']"
                    v-slot="{ errors, processing }"
                    class="auth-form"
                >
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

                    <div class="flex flex-col gap-2">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-muted-foreground">E-post</span>
                        </div>
                        <label class="auth-field">
                            <span class="sr-only">E-post</span>
                            <input
                                id="email"
                                name="email"
                                type="email"
                                required
                                :tabindex="2"
                                autocomplete="email"
                                placeholder="E-post"
                                class="auth-input"
                            />
                        </label>
                        <InputError :message="errors.email" />
                    </div>

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
                                :aria-label="
                                    showPasswordConfirmation ? 'Peida parool' : 'Näita parooli'
                                "
                            >
                                <span class="material-symbols-outlined">
                                    {{ showPasswordConfirmation ? 'visibility_off' : 'visibility' }}
                                </span>
                            </button>
                        </label>
                        <InputError :message="errors.password_confirmation" />
                    </div>

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
                        <a
                            href="/auth/redirect"
                            class="btn-primary flex w-full items-center justify-center gap-2 bg-primary/70 hover:bg-primary/75"
                            :tabindex="7"
                        >
                            <GoogleLogo />
                            Logi sisse Google kontoga
                        </a>

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
