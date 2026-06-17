<script setup>
import PasswordStrengthMeter from '@/Components/PasswordStrengthMeter.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    email: {
        type: String,
        required: true,
    },
    token: {
        type: String,
        required: true,
    },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Reset Password" />

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

                    <h1 class="text-xl font-bold text-gray-900 mb-1">Reset Password</h1>
                    <p class="text-gray-500 text-xs">Choose a new strong password for your account.</p>
                </div>

                <!-- Error Messages -->
                <div v-if="form.errors.email" class="mb-4 p-2.5 bg-red-50 border border-red-200 rounded-lg text-red-700 text-xs">
                    {{ form.errors.email }}
                </div>

                <!-- Form -->
                <form @submit.prevent="submit" class="space-y-4">
                    <!-- Email (hidden via disabled input, sent as hidden) -->
                    <input type="hidden" name="email" :value="form.email" />
                    <input type="hidden" name="token" :value="form.token" />

                    <div class="space-y-1.5">
                        <label for="email" class="text-xs font-medium text-gray-700 block">Email Address</label>
                        <input
                            type="email"
                            id="email"
                            :value="form.email"
                            class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 bg-gray-100 cursor-not-allowed text-sm"
                            disabled
                        />
                    </div>

                    <!-- New Password -->
                    <div class="space-y-1.5">
                        <label for="password" class="text-xs font-medium text-gray-700 block">New Password</label>
                        <input
                            type="password"
                            id="password"
                            v-model="form.password"
                            class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#34554a]/20 focus:border-[#34554a] transition-all text-sm"
                            placeholder="••••••••"
                            required
                            autocomplete="new-password"
                        />
                        <div v-if="form.errors.password" class="text-red-600 text-xs mt-1">{{ form.errors.password }}</div>
                        <PasswordStrengthMeter :password="form.password" />
                    </div>

                    <!-- Confirm Password -->
                    <div class="space-y-1.5">
                        <label for="password_confirmation" class="text-xs font-medium text-gray-700 block">Confirm Password</label>
                        <input
                            type="password"
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#34554a]/20 focus:border-[#34554a] transition-all text-sm"
                            placeholder="••••••••"
                            required
                            autocomplete="new-password"
                        />
                        <div v-if="form.errors.password_confirmation" class="text-red-600 text-xs mt-1">{{ form.errors.password_confirmation }}</div>
                    </div>

                    <!-- Submit Button -->
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full flex justify-center py-2.5 px-4 border border-transparent rounded-lg shadow-sm text-sm font-semibold text-white bg-[#34554a] hover:bg-[#2c463d] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#34554a] transition-all transform active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {{ form.processing ? 'Resetting...' : 'Reset Password' }}
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
