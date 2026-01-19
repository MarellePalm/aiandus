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
    <div class="min-h-screen flex flex-col bg-[#f8f7f7] dark:bg-[#292623] text-[#3B3835] dark:text-gray-100">
        <Head title="Registreerimine">
            <!-- Add your Stitch script tag here if needed -->
            <!-- Example: <script src="YOUR_STITCH_SCRIPT_URL" async></script> -->
        </Head>

        <!-- Top Navigation / Bar -->
        <div class="flex items-center bg-transparent p-4 pb-2 justify-between">
            <Link href="/" class="text-[#3B3835] dark:text-gray-200 flex size-12 shrink-0 items-center justify-start cursor-pointer">
                <span class="material-symbols-outlined">arrow_back_ios</span>
            </Link>
            <div class="flex-1"></div>
        </div>

        <main class="flex-1 flex flex-col px-6">
            <!-- Seedling Icon & Heading Section -->
            <div class="flex flex-col items-center pt-4 pb-10">
                <div class="w-16 h-16 bg-[#597d36]/10 dark:bg-[#597d36]/20 rounded-full flex items-center justify-center mb-6">
                    <span class="material-symbols-outlined text-[#597d36] text-4xl">potted_plant</span>
                </div>
                <h1 class="text-[#3B3835] dark:text-white text-3xl font-bold leading-tight tracking-tight text-center pb-2">
                    Registreerimine
                </h1>
                <p class="text-[#3B3835]/70 dark:text-gray-400 text-base font-normal leading-normal text-center max-w-[280px]">
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
                    <label class="flex flex-col bg-[#F5EFE9]/50 dark:bg-white/5 p-1 rounded-xl border border-[#597d36]/10 dark:border-white/10 transition-shadow focus-within:shadow-md">
                        <span class="text-[#3B3835] dark:text-gray-300 text-xs font-semibold px-4 pt-3 uppercase tracking-wider">Nimi</span>
                        <input
                            id="name"
                            name="name"
                            type="text"
                            required
                            autofocus
                            :tabindex="1"
                            autocomplete="name"
                            placeholder="Sinu nimi"
                            class="form-input w-full border-none bg-transparent focus:ring-0 text-[#3B3835] dark:text-white h-12 placeholder:text-[#3B3835]/40 dark:placeholder:text-gray-500 px-4 text-base font-normal"
                        />
                    </label>
                    <InputError :message="errors.name" />
                </div>

                <!-- Email Field -->
                <div class="flex flex-col gap-2">
                    <label class="flex flex-col bg-[#F5EFE9]/50 dark:bg-white/5 p-1 rounded-xl border border-[#597d36]/10 dark:border-white/10 transition-shadow focus-within:shadow-md">
                        <span class="text-[#3B3835] dark:text-gray-300 text-xs font-semibold px-4 pt-3 uppercase tracking-wider">E-post</span>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            required
                            :tabindex="2"
                            autocomplete="email"
                            placeholder="aiapidaja@mail.ee"
                            class="form-input w-full border-none bg-transparent focus:ring-0 text-[#3B3835] dark:text-white h-12 placeholder:text-[#3B3835]/40 dark:placeholder:text-gray-500 px-4 text-base font-normal"
                        />
                    </label>
                    <InputError :message="errors.email" />
                </div>

                <!-- Password Field -->
                <div class="flex flex-col gap-2">
                    <label class="flex flex-col bg-[#F5EFE9]/50 dark:bg-white/5 p-1 rounded-xl border border-[#597d36]/10 dark:border-white/10 transition-shadow focus-within:shadow-md relative">
                        <span class="text-[#3B3835] dark:text-gray-300 text-xs font-semibold px-4 pt-3 uppercase tracking-wider">Parool</span>
                        <input
                            id="password"
                            name="password"
                            :type="showPassword ? 'text' : 'password'"
                            required
                            :tabindex="3"
                            autocomplete="new-password"
                            placeholder="••••••••"
                            class="form-input w-full border-none bg-transparent focus:ring-0 text-[#3B3835] dark:text-white h-12 placeholder:text-[#3B3835]/40 dark:placeholder:text-gray-500 px-4 text-base font-normal pr-12"
                        />
                        <button
                            type="button"
                            @click="showPassword = !showPassword"
                            class="material-symbols-outlined absolute right-4 bottom-3 text-[#3B3835]/40 dark:text-gray-500 cursor-pointer hover:text-[#3B3835]/60 dark:hover:text-gray-400"
                            tabindex="-1"
                        >
                            {{ showPassword ? 'visibility_off' : 'visibility' }}
                        </button>
                    </label>
                    <InputError :message="errors.password" />
                </div>

                <!-- Password Confirmation Field -->
                <div class="flex flex-col gap-2">
                    <label class="flex flex-col bg-[#F5EFE9]/50 dark:bg-white/5 p-1 rounded-xl border border-[#597d36]/10 dark:border-white/10 transition-shadow focus-within:shadow-md relative">
                        <span class="text-[#3B3835] dark:text-gray-300 text-xs font-semibold px-4 pt-3 uppercase tracking-wider">Kinnita parool</span>
                        <input
                            id="password_confirmation"
                            name="password_confirmation"
                            :type="showPasswordConfirmation ? 'text' : 'password'"
                            required
                            :tabindex="4"
                            autocomplete="new-password"
                            placeholder="••••••••"
                            class="form-input w-full border-none bg-transparent focus:ring-0 text-[#3B3835] dark:text-white h-12 placeholder:text-[#3B3835]/40 dark:placeholder:text-gray-500 px-4 text-base font-normal pr-12"
                        />
                        <button
                            type="button"
                            @click="showPasswordConfirmation = !showPasswordConfirmation"
                            class="material-symbols-outlined absolute right-4 bottom-3 text-[#3B3835]/40 dark:text-gray-500 cursor-pointer hover:text-[#3B3835]/60 dark:hover:text-gray-400"
                            tabindex="-1"
                        >
                            {{ showPasswordConfirmation ? 'visibility_off' : 'visibility' }}
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
                        class="w-full bg-[#597d36] hover:bg-[#597d36]/90 text-white font-bold py-4 rounded-xl shadow-lg shadow-[#597d36]/20 transition-all active:scale-[0.98]"
                        tabindex="5"
                        :disabled="processing"
                        data-test="register-user-button"
                    >
                        <Spinner v-if="processing" class="mr-2" />
                        Loo konto
                    </Button>

                    <p class="text-center text-sm text-[#3B3835]/60 dark:text-gray-400">
                        On juba konto?
                        <TextLink
                            :href="login()"
                            class="text-[#597d36] font-bold ml-1 hover:underline"
                            :tabindex="6"
                        >
                            Logi sisse
                        </TextLink>
                    </p>
                </div>
            </Form>
        </main>

        <!-- Visual Element: Decorative Leaf Pattern (Optional Background Aesthetic) -->
        <div class="fixed bottom-[-50px] right-[-50px] opacity-[0.03] dark:opacity-[0.07] pointer-events-none">
            <span class="material-symbols-outlined text-[300px] select-none">eco</span>
        </div>
    </div>
</template>
