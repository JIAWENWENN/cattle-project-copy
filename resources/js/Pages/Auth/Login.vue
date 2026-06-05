<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { onMounted } from 'vue';

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

onMounted(() => {
    form.clearErrors();
    form.reset('password');
});

const submit = () => {
    if (form.processing) {
        return;
    }

    form.post(route('login'), {
        preserveScroll: true,
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Log in" />

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

                    <h1 class="text-xl font-bold text-gray-900 mb-1">User Login</h1>
                    <p class="text-gray-500 text-xs">Welcome Back. Sign in to Sawit Kinabalu Cattle System.</p>
                </div>

                <!-- Status Message -->
                <div v-if="status" class="mb-4 p-2.5 bg-green-50 border border-green-200 rounded-lg text-green-700 text-xs">
                    {{ status }}
                </div>

                <!-- Error Message -->
                <div v-if="form.errors.email || form.errors.password" class="mb-4 p-2.5 bg-red-50 border border-red-200 rounded-lg text-red-700 text-xs">
                    {{ form.errors.email || form.errors.password }}
                </div>

                <!-- Login Form -->
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
                    </div>

                    <!-- Password Input -->
                    <div class="space-y-1.5">
                        <label for="password" class="text-xs font-medium text-gray-700 block">Password</label>
                        <input
                            type="password"
                            id="password"
                            v-model="form.password"
                            class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#34554a]/20 focus:border-[#34554a] transition-all text-sm"
                            placeholder="••••••••"
                            required
                            autocomplete="current-password"
                        />
                    </div>

                    <!-- Remember Me Checkbox ← ADD THIS -->
                    <div class="flex items-center">
                        <input
                            id="remember"
                            v-model="form.remember"
                            type="checkbox"
                            class="w-4 h-4 text-[#34554a] bg-gray-100 border-gray-300 rounded focus:ring-[#34554a] focus:ring-2"
                        />
                        <label for="remember" class="ml-2 text-xs text-gray-700 cursor-pointer">
                            Remember me
                        </label>
                    </div>

                    <!-- Submit Button -->
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full flex justify-center py-2.5 px-4 border border-transparent rounded-lg shadow-sm text-sm font-semibold text-white bg-[#34554a] hover:bg-[#2c463d] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#34554a] transition-all transform active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed mt-6"
                    >
                        {{ form.processing ? 'Signing In...' : 'Sign In' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>
