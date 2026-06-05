<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';

defineProps({
    message: {
        type: String,
        default: "Sorry, you don't have permission to access this page. If you think this is a mistake, please contact your admin."
    }
});

const goBack = () => {
    router.visit('/dashboard');
};
</script>

<template>
    <Head title="Access Denied" />

    <AppLayout title="Access Denied">
        <div class="min-h-[calc(100vh-200px)] flex items-center justify-center p-4">
            <div class="max-w-lg w-full text-center">

                <!-- Lock Illustration SVG -->
                <div class="mb-8 mx-auto w-64 h-56">
                    <svg viewBox="0 0 200 160" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-full h-full drop-shadow-sm">
                        <!-- Background Blobs -->
                        <path d="M164.5 110C164.5 137.614 142.114 155 114.5 155C86.8858 155 45 137.614 45 110C45 82.3858 67.3858 60 95 60C122.614 60 164.5 82.3858 164.5 110Z" fill="#E8F5F1"/>
                        <circle cx="150" cy="40" r="15" fill="#FFE4E6"/>
                        <circle cx="30" cy="120" r="8" fill="#FEF3C7"/>

                        <!-- Lock Body -->
                        <rect x="65" y="75" width="70" height="60" rx="8" fill="#34554a"/>
                        <rect x="65" y="75" width="70" height="60" rx="8" fill="url(#paint0_linear)" fill-opacity="0.2"/>

                        <!-- Shackle -->
                        <path d="M80 75V55C80 43.9543 88.9543 35 100 35C111.046 35 120 43.9543 120 55V75" stroke="#4d7568" stroke-width="12" stroke-linecap="round"/>

                        <!-- Keyhole -->
                        <circle cx="100" cy="100" r="6" fill="white"/>
                        <rect x="97" y="100" width="6" height="15" rx="3" fill="white"/>

                        <!-- No Sign -->
                        <circle cx="145" cy="125" r="20" fill="#EF4444" stroke="white" stroke-width="3"/>
                        <path d="M138 118L152 132" stroke="white" stroke-width="3" stroke-linecap="round"/>

                        <defs>
                            <linearGradient id="paint0_linear" x1="100" y1="75" x2="100" y2="135" gradientUnits="userSpaceOnUse">
                                <stop stop-color="white" stop-opacity="0.3"/>
                                <stop offset="1" stop-color="white" stop-opacity="0"/>
                            </linearGradient>
                        </defs>
                    </svg>
                </div>

                <!-- Title & Message -->
                <h2 class="text-3xl font-bold text-gray-900 mb-3 tracking-tight">Access Denied</h2>
                <p class="text-gray-500 mb-8 text-base leading-relaxed">
                    {{ message }}
                </p>

                <!-- Action Button -->
                <div class="flex justify-center gap-3">
                    <button
                        @click="goBack"
                        class="px-8 py-3 bg-[#34554a] text-white font-medium rounded-lg hover:bg-[#2c463d] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#34554a] transition-all shadow-sm flex items-center gap-2 transform active:scale-95"
                    >
                        <ArrowLeft class="w-4 h-4" />
                        Go Back to Dashboard
                    </button>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
