<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <Head title="Forgot Password" />

    <div class="bg-gray-50 min-h-screen flex flex-col items-center justify-center p-4">
        <div class="w-full max-w-[380px]">
            <!-- Card Container -->
            <div class="bg-white rounded-2xl shadow-lg p-8">
                <!-- Logo Section -->
                <div class="text-center mb-6">
                    <div class="flex justify-center mb-4">
                        <img
                            src="/images/sawit-kinabalu-logo.png"
                            alt="Sawit Kinabalu Logo"
                            class="h-24 w-auto"
                        />
                    </div>

                    <h1 class="text-xl font-bold text-gray-900 mb-1">Forgot Password</h1>
                    <p class="text-gray-500 text-xs">Enter your email to receive a password reset link.</p>
                </div>

                <!-- Status Message -->
                <div v-if="status" class="mb-4 p-2.5 bg-green-50 border border-green-200 rounded-lg text-green-700 text-xs">
                    {{ status }}
                </div>

                <!-- Description -->
                <p class="text-xs text-gray-600 mb-4">
                    Forgot your password? No problem. Just let us know your email
                    address and we will email you a password reset link.
                </p>

                <!-- Form -->
                <form @submit.prevent="submit" class="space-y-4">
                    <!-- Email Input -->
                    <div class="space-y-1.5">
                        <label for="email" class="text-xs font-medium text-gray-700 block">Email Address</label>
                        <input
                            type="email"
                            id="email"
                            v-model="form.email"
                            class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#34554a]/20 focus:border-[#34554a] transition-all text-sm"
                            placeholder="your@email.com"
                            required
                            autofocus
                            autocomplete="username"
                        />
                        <div v-if="form.errors.email" class="text-red-600 text-xs mt-1">{{ form.errors.email }}</div>
                    </div>

                    <!-- Submit Button -->
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full flex justify-center py-2.5 px-4 border border-transparent rounded-lg shadow-sm text-sm font-semibold text-white bg-[#34554a] hover:bg-[#2c463d] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#34554a] transition-all transform active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed mt-2"
                    >
                        {{ form.processing ? 'Sending...' : 'Email Password Reset Link' }}
                    </button>
                </form>

                <!-- Back to Login -->
                <div class="mt-6 text-center">
                    <Link
                        :href="route('login')"
                        class="text-xs text-[#34554a] hover:text-[#2c463d] font-medium underline underline-offset-2"
                    >
                        Back to Login
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
