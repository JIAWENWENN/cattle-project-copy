<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { ArrowLeft, Edit, AlertTriangle, Clock, CheckCircle } from 'lucide-vue-next';

defineOptions({ 
    layout: (h, page) => h(AppLayout, { title: 'Health Alert Details', parent: 'Health', parentUrl: '/health' }, () => page)
});

const props = defineProps({
    id: String
});

const page = usePage();
const alert = ref({
    id: props.id || 1,
    date: '14/01/26',
    tag_no: 'BF 24/01',
    category: 'Anak',
    severity: 'critical',
    type: 'High Fever',
    description: 'Temperature 40.5°C - Immediate attention required',
    status: 'pending',
    action_taken: '',
});

const goBack = () => {
    router.visit('/health/alerts');
};

const editAlert = () => {
    router.visit(`/health/alert/${alert.value.id}/edit`);
};
</script>

<template>
    <div class="w-full">
        <div class="mb-8 flex justify-between items-center">
            <div class="flex items-center gap-4">
                <button @click="goBack" class="w-10 h-10 flex items-center justify-center rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors">
                    <ArrowLeft class="w-5 h-5 text-gray-600" />
                </button>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Health Alert Details</h1>
                    <p class="text-sm text-gray-500 mt-1">{{ alert.type }}</p>
                </div>
            </div>
            <button @click="editAlert" class="flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
                <Edit class="w-4 h-4" />
                Edit
            </button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="p-4 border-b border-gray-100 flex items-center gap-3">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center"
                            :class="alert.severity === 'critical' ? 'bg-red-100' : alert.severity === 'warning' ? 'bg-amber-100' : 'bg-blue-100'">
                            <AlertTriangle class="w-6 h-6"
                                :class="alert.severity === 'critical' ? 'text-red-600' : alert.severity === 'warning' ? 'text-amber-600' : 'text-blue-600'" />
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-900">{{ alert.type }}</h3>
                            <p class="text-sm text-gray-500">{{ alert.description }}</p>
                        </div>
                    </div>
                    <div class="p-6 space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-xs text-gray-500 font-semibold mb-1">Tag Number</p>
                                <p class="text-gray-900 font-medium">{{ alert.tag_no }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 font-semibold mb-1">Category</p>
                                <p class="text-gray-900 font-medium">{{ alert.category }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 font-semibold mb-1">Date Reported</p>
                                <p class="text-gray-900 font-medium">{{ alert.date }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 font-semibold mb-1">Severity</p>
                                <span class="px-2.5 py-1 rounded-full text-xs font-medium"
                                    :class="alert.severity === 'critical' ? 'bg-red-100 text-red-700' : alert.severity === 'warning' ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700'">
                                    {{ alert.severity.toUpperCase() }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="p-4 border-b border-gray-100">
                        <h3 class="text-sm font-bold text-gray-900">Action Taken</h3>
                    </div>
                    <div class="p-6">
                        <p v-if="alert.action_taken" class="text-gray-700">{{ alert.action_taken }}</p>
                        <p v-else class="text-gray-400 italic">No action taken yet</p>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="p-4 border-b border-gray-100">
                        <h3 class="text-sm font-bold text-gray-900">Status</h3>
                    </div>
                    <div class="p-4">
                        <span class="px-3 py-1 rounded-full text-sm font-medium"
                            :class="alert.status === 'pending' ? 'bg-amber-100 text-amber-700' : alert.status === 'in_progress' ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700'">
                            {{ alert.status.replace('_', ' ') }}
                        </span>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="p-4 border-b border-gray-100">
                        <h3 class="text-sm font-bold text-gray-900">Quick Actions</h3>
                    </div>
                    <div class="p-4 space-y-2">
                        <button class="w-full py-2 px-3 text-left text-sm text-gray-600 hover:bg-gray-50 rounded-lg transition-colors">
                            Mark as Resolved
                        </button>
                        <button class="w-full py-2 px-3 text-left text-sm text-gray-600 hover:bg-gray-50 rounded-lg transition-colors">
                            Record Treatment
                        </button>
                        <button class="w-full py-2 px-3 text-left text-sm text-gray-600 hover:bg-gray-50 rounded-lg transition-colors">
                            Add to Disease Tracking
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
