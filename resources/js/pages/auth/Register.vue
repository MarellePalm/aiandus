<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

// Password visibility toggle
const showPassword = ref(false);
const showPasswordConfirmation = ref(false);

// Load Stitch registration code
onMounted(() => {
    // Add your Stitch script loading code here
    // Example: const script = document.createElement('script');
    // script.src = 'YOUR_STITCH_SCRIPT_URL';
    // script.async = true;
    // document.head.appendChild(script);
});
</script>

<template>
    <div
        class="flex min-h-screen flex-col bg-[#f8f7f7] text-[#3B3835] dark:bg-[#292623] dark:text-gray-100"
    >
        <Head title="Registreerimine">
            <!-- Add your Stitch script tag here if needed -->
            <!-- Example: <script src="YOUR_STITCH_SCRIPT_URL" async></script> -->
        </Head>

        <!-- Top Navigation / Bar -->
        <div class="flex items-center justify-between bg-transparent p-4 pb-2">
            <Link
                href="/"
                class="flex size-12 shrink-0 cursor-pointer items-center justify-start text-[#3B3835] dark:text-gray-200"
            >
                <span class="material-symbols-outlined">arrow_back_ios</span>
            </Link>
            <div class="flex-1"></div>
        </div>

        <main class="flex flex-1 flex-col px-6">
            <!-- Seedling Icon & Heading Section -->
            <div class="flex flex-col items-center pt-4 pb-10">
                <div
                    class="mb-6 flex h-16 w-16 items-center justify-center rounded-full bg-[#597d36]/10 dark:bg-[#597d36]/20"
                >
                    <span
                        class="material-symbols-outlined text-4xl text-[#597d36]"
                        >potted_plant</span
                    >
                </div>
                <h1
                    class="pb-2 text-center text-3xl leading-tight font-bold tracking-tight text-[#3B3835] dark:text-white"
                >
                    Registreerimine
                </h1>
                <p
                    class="max-w-[280px] text-center text-base leading-normal font-normal text-[#3B3835]/70 dark:text-gray-400"
                >
                    Alusta oma rohelist teekonda ja loo isiklik aia päevik täna.
                </p>
            </div>

            <!-- Registration Form -->
            <Form
                v-bind="store.form()"
                :reset-on-success="['password', 'password_confirmation']"
                v-slot="{ errors, processing }"
                class="space-y-4"
            >
                <!-- Name Field -->
                <div class="flex flex-col gap-2">
                    <label
                        class="flex flex-col rounded-xl border border-[#597d36]/10 bg-[#F5EFE9]/50 p-1 transition-shadow focus-within:shadow-md dark:border-white/10 dark:bg-white/5"
                    >
                        <span
                            class="px-4 pt-3 text-xs font-semibold tracking-wider text-[#3B3835] uppercase dark:text-gray-300"
                            >Nimi</span
                        >
                        <input
                            id="name"
                            name="name"
                            type="text"
                            required
                            autofocus
                            :tabindex="1"
                            autocomplete="name"
                            placeholder="Sinu nimi"
                            class="form-input h-12 w-full border-none bg-transparent px-4 text-base font-normal text-[#3B3835] placeholder:text-[#3B3835]/40 focus:ring-0 dark:text-white dark:placeholder:text-gray-500"
                        />
                    </label>
                    <InputError :message="errors.name" />
                </div>

                <!-- Email Field -->
                <div class="flex flex-col gap-2">
                    <label
                        class="flex flex-col rounded-xl border border-[#597d36]/10 bg-[#F5EFE9]/50 p-1 transition-shadow focus-within:shadow-md dark:border-white/10 dark:bg-white/5"
                    >
                        <span
                            class="px-4 pt-3 text-xs font-semibold tracking-wider text-[#3B3835] uppercase dark:text-gray-300"
                            >E-post</span
                        >
                        <input
                            id="email"
                            name="email"
                            type="email"
                            required
                            :tabindex="2"
                            autocomplete="email"
                            placeholder="aiapidaja@mail.ee"
                            class="form-input h-12 w-full border-none bg-transparent px-4 text-base font-normal text-[#3B3835] placeholder:text-[#3B3835]/40 focus:ring-0 dark:text-white dark:placeholder:text-gray-500"
                        />
                    </label>
                    <InputError :message="errors.email" />
                </div>

                <!-- Password Field -->
                <div class="flex flex-col gap-2">
                    <label
                        class="relative flex flex-col rounded-xl border border-[#597d36]/10 bg-[#F5EFE9]/50 p-1 transition-shadow focus-within:shadow-md dark:border-white/10 dark:bg-white/5"
                    >
                        <span
                            class="px-4 pt-3 text-xs font-semibold tracking-wider text-[#3B3835] uppercase dark:text-gray-300"
                            >Parool</span
                        >
                        <input
                            id="password"
                            name="password"
                            :type="showPassword ? 'text' : 'password'"
                            required
                            :tabindex="3"
                            autocomplete="new-password"
                            placeholder="••••••••"
                            class="form-input h-12 w-full border-none bg-transparent px-4 pr-12 text-base font-normal text-[#3B3835] placeholder:text-[#3B3835]/40 focus:ring-0 dark:text-white dark:placeholder:text-gray-500"
                        />
                        <button
                            type="button"
                            @click="showPassword = !showPassword"
                            class="material-symbols-outlined absolute right-4 bottom-3 cursor-pointer text-[#3B3835]/40 hover:text-[#3B3835]/60 dark:text-gray-500 dark:hover:text-gray-400"
                            tabindex="-1"
                        >
                            {{ showPassword ? 'visibility_off' : 'visibility' }}
                        </button>
                    </label>
                    <InputError :message="errors.password" />
                </div>

                <!-- Password Confirmation Field -->
                <div class="flex flex-col gap-2">
                    <label
                        class="relative flex flex-col rounded-xl border border-[#597d36]/10 bg-[#F5EFE9]/50 p-1 transition-shadow focus-within:shadow-md dark:border-white/10 dark:bg-white/5"
                    >
                        <span
                            class="px-4 pt-3 text-xs font-semibold tracking-wider text-[#3B3835] uppercase dark:text-gray-300"
                            >Kinnita parool</span
                        >
                        <input
                            id="password_confirmation"
                            name="password_confirmation"
                            :type="
                                showPasswordConfirmation ? 'text' : 'password'
                            "
                            required
                            :tabindex="4"
                            autocomplete="new-password"
                            placeholder="••••••••"
                            class="form-input h-12 w-full border-none bg-transparent px-4 pr-12 text-base font-normal text-[#3B3835] placeholder:text-[#3B3835]/40 focus:ring-0 dark:text-white dark:placeholder:text-gray-500"
                        />
                        <button
                            type="button"
                            @click="
                                showPasswordConfirmation =
                                    !showPasswordConfirmation
                            "
                            class="material-symbols-outlined absolute right-4 bottom-3 cursor-pointer text-[#3B3835]/40 hover:text-[#3B3835]/60 dark:text-gray-500 dark:hover:text-gray-400"
                            tabindex="-1"
                        >
                            {{
                                showPasswordConfirmation
                                    ? 'visibility_off'
                                    : 'visibility'
                            }}
                        </button>
                    </label>
                    <InputError :message="errors.password_confirmation" />
                </div>

                <!-- Spacer for push -->
                <div class="flex-1"></div>

                <!-- Footer Actions -->
                <div class="mt-12 mb-8 space-y-4">
                    <Button
                        type="submit"
                        class="w-full rounded-xl bg-[#597d36] py-4 font-bold text-white shadow-lg shadow-[#597d36]/20 transition-all hover:bg-[#597d36]/90 active:scale-[0.98]"
                        tabindex="5"
                        :disabled="processing"
                        data-test="register-user-button"
                    >
                        <Spinner v-if="processing" class="mr-2" />
                        Loo konto
                    </Button>

                    <p
                        class="text-center text-sm text-[#3B3835]/60 dark:text-gray-400"
                    >
                        On juba konto?
                        <TextLink
                            :href="login()"
                            class="ml-1 font-bold text-[#597d36] hover:underline"
                            :tabindex="6"
                        >
                            Logi sisse
                        </TextLink>
                    </p>
                </div>
            </Form>
        </main>

        <!-- Visual Element: Decorative Leaf Pattern (Optional Background Aesthetic) -->
        <div
            class="pointer-events-none fixed right-[-50px] bottom-[-50px] opacity-[0.03] dark:opacity-[0.07]"
        >
            <span class="material-symbols-outlined text-[300px] select-none"
                >eco</span
            >
        </div>
    </div>
</template>
