<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { ArrowLeft, Edit, Activity, MapPin, Users, TrendingUp } from 'lucide-vue-next';

defineOptions({ 
    layout: (h, page) => h(AppLayout, { title: 'Disease Case Details', parent: 'Health', parentUrl: '/health' }, () => page)
});

const props = defineProps({
    id: String
});

const page = usePage();
const caseItem = ref({
    id: props.id || 1,
    disease: 'Foot and Mouth Disease',
    date_onset: '02/01/26',
    tag_no: 'BF 24/01',
    category: 'Anak',
    location: 'Barn 3',
    affected_count: 1,
    recovered_count: 0,
    status: 'active',
    severity: 'high',
    treatment: 'Supportive care + antibiotics',
    notes: 'Isolated immediately',
});

const goBack = () => {
    router.visit('/health/disease');
};

const editCase = () => {
    router.visit(`/health/disease/${caseItem.value.id}/edit`);
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
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Disease Case Details</h1>
                    <p class="text-sm text-gray-500 mt-1">{{ caseItem.disease }}</p>
                </div>
            </div>
            <button @click="editCase" class="flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
                <Edit class="w-4 h-4" />
                Edit
            </button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="p-6">
                        <div class="flex items-center gap-4 mb-6">
                            <div class="w-16 h-16 bg-red-100 rounded-xl flex items-center justify-center">
                                <Activity class="w-8 h-8 text-red-600" />
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-gray-900">{{ caseItem.disease }}</h3>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="px-2.5 py-1 rounded-full text-xs font-medium"
                                        :class="caseItem.status === 'active' ? 'bg-red-100 text-red-700' : caseItem.status === 'recovered' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'">
                                        {{ caseItem.status }}
                                    </span>
                                    <span class="px-2.5 py-1 rounded-full text-xs font-medium border"
                                        :class="caseItem.severity === 'critical' ? 'bg-red-100 text-red-700 border-red-200' : caseItem.severity === 'high' ? 'bg-amber-100 text-amber-700 border-amber-200' : 'bg-blue-100 text-blue-700 border-blue-200'">
                                        {{ caseItem.severity }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-xs text-gray-500 font-semibold mb-1">Onset Date</p>
                                <p class="text-gray-900 font-medium">{{ caseItem.date_onset }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 font-semibold mb-1">Tag Number</p>
                                <p class="text-gray-900 font-medium">{{ caseItem.tag_no }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 font-semibold mb-1">Category</p>
                                <p class="text-gray-900 font-medium">{{ caseItem.category }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 font-semibold mb-1">Location</p>
                                <p class="text-gray-900 font-medium flex items-center gap-1">
                                    <MapPin class="w-4 h-4 text-gray-400" />
                                    {{ caseItem.location }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 font-semibold mb-1">Affected Count</p>
                                <p class="text-gray-900 font-medium">{{ caseItem.affected_count }} head(s)</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 font-semibold mb-1">Recovered Count</p>
                                <p class="text-gray-900 font-medium">{{ caseItem.recovered_count }} head(s)</p>
                            </div>
                            <div class="col-span-2">
                                <p class="text-xs text-gray-500 font-semibold mb-1">Treatment</p>
                                <p class="text-gray-900 font-medium">{{ caseItem.treatment }}</p>
                            </div>
                            <div class="col-span-2">
                                <p class="text-xs text-gray-500 font-semibold mb-1">Notes</p>
                                <p class="text-gray-900 font-medium">{{ caseItem.notes }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                    <h4 class="text-sm font-bold text-gray-900 mb-3">Case Statistics</h4>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Mortality Rate</span>
                            <span class="text-sm font-medium text-gray-900">0%</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Recovery Rate</span>
                            <span class="text-sm font-medium text-green-600">0%</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Days Active</span>
                            <span class="text-sm font-medium text-gray-900">12 days</span>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                    <h4 class="text-sm font-bold text-gray-900 mb-3">Quick Actions</h4>
                    <div class="space-y-2">
                        <button class="w-full py-2 px-3 text-left text-sm text-gray-600 hover:bg-gray-50 rounded-lg transition-colors">
                            Mark as Recovered
                        </button>
                        <button class="w-full py-2 px-3 text-left text-sm text-gray-600 hover:bg-gray-50 rounded-lg transition-colors">
                            Record Mortality
                        </button>
                        <button class="w-full py-2 px-3 text-left text-sm text-gray-600 hover:bg-gray-50 rounded-lg transition-colors">
                            Generate Report
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
