<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="relative min-h-screen overflow-hidden bg-[#F5F5EE]">
        <!-- Taustapilt -->
        
            <div class="absolute inset-0 bg-[#F5F5EE]/70"></div>

        <!-- Sisu -->
        <div class="relative z-10 flex min-h-screen items-center justify-center px-6">
            <div class="w-full max-w-xl text-center">
                <!-- Ikoon -->
                <div class="mx-auto mb-8 flex h-20 w-20 items-center justify-center rounded-full bg-[#E7ECD9] shadow-sm">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-9 w-9 text-[#5C7C45]"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M12 21c4.5-2 7-5.5 7-10 0-1.5-.3-2.9-.8-4.2C15.5 7 13 8.5 11.5 11c-1.5 2.3-1.8 4.9-.9 7.7M12 21c-3.5-1.2-6.5-4.5-7-8.8-.3-2.3.1-4.4 1.2-6.2 2.3.2 4.3 1.1 5.8 2.7"
                        />
                    </svg>
                </div>

                <!-- Pealkiri -->
                <h1 class="mb-5 text-4xl font-bold tracking-tight text-[#2F2F2F] sm:text-5xl">
                    Minu Aiapäevik
                </h1>

                <!-- Kirjeldus -->
                <p class="mx-auto mb-10 max-w-md text-lg leading-8 text-[#7C7C73]">
                    Alusta oma rohelise teekonda ja kogu ühte kohta taimed, ideed ja väikesed võidud oma aias.
                </p>

                <!-- Nupud -->
                <div class="mx-auto flex max-w-md flex-col gap-4">
                    <Link
                        href="/login"
                        class="inline-flex h-16 items-center justify-center rounded-full bg-[#4F7F3B] px-8 text-xl font-semibold text-white shadow-md transition hover:scale-[1.01] hover:bg-[#456F34]"
                    >
                        Logi sisse
                    </Link>

                    <Link
                        href="/register"
                        class="inline-flex h-16 items-center justify-center rounded-full bg-[#E3E7D8] px-8 text-xl font-semibold text-[#5D7843] transition hover:scale-[1.01] hover:bg-[#DCE2CF]"
                    >
                        Loo konto
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>

