<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { usePage, router } from '@inertiajs/vue3';
import { Clock, CheckCircle } from 'lucide-vue-next';

defineOptions({
    layout: (h, page) => h(AppLayout, { title: 'Pending Endorsements', parent: 'Calving', parentUrl: '/calving' }, () => page)
});

const page = usePage();
const pendingRecords = computed(() => page.props.pendingRecords || []);
</script>

<template>
    <div class="w-full max-w-md mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Pending Endorsements</h1>
        </div>

        <div v-if="pendingRecords.length > 0" class="bg-blue-50 border border-blue-200 rounded-xl p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center">
                    <Clock class="w-5 h-5 text-blue-600" />
                </div>
                <div>
                    <h3 class="font-bold text-gray-900">Your Pending Actions</h3>
                    <p class="text-sm text-gray-600">{{ pendingRecords.length }} record(s) waiting for your endorsement</p>
                </div>
            </div>
            <button 
                @click="router.visit('/calving')"
                class="w-full py-3 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700"
            >
                View in Records
            </button>
        </div>

        <div v-else class="bg-green-50 border border-green-200 rounded-xl p-6">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-green-100 rounded-xl flex items-center justify-center">
                    <CheckCircle class="w-5 h-5 text-green-600" />
                </div>
                <div>
                    <h3 class="font-bold text-gray-900">All Caught Up!</h3>
                    <p class="text-sm text-gray-600">No pending endorsements.</p>
                </div>
            </div>
        </div>
    </div>
</template>
