<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { ArrowLeft, Save, Activity, MapPin } from 'lucide-vue-next';

defineOptions({ 
    layout: (h, page) => h(AppLayout, { title: 'Report Disease Case', parent: 'Health', parentUrl: '/health' }, () => page)
});

const page = usePage();
const saving = ref(false);

const form = ref({
    disease: '',
    date_onset: '',
    tag_no: '',
    category: '',
    location: '',
    affected_count: 1,
    severity: 'medium',
    treatment: '',
    notes: '',
});

const diseases = [
    'Foot and Mouth Disease', 'Bloat', 'Mastitis', 'Diarrhea (Neonatal)', 
    'Tick Fever (Anaplasmosis)', 'Respiratory Infection', 'Blackleg', 
    'Anthrax', 'Brucellosis', 'Rabies', 'Other'
];

const save = () => {
    saving.value = true;
    setTimeout(() => {
        saving.value = false;
        router.visit('/health/disease');
    }, 1000);
};

const cancel = () => {
    router.visit('/health/disease');
};
</script>

<template>
    <div class="w-full">
        <div class="mb-8 flex justify-between items-center">
            <div class="flex items-center gap-4">
                <button @click="cancel" class="w-10 h-10 flex items-center justify-center rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors">
                    <ArrowLeft class="w-5 h-5 text-gray-600" />
                </button>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Report Disease Case</h1>
                    <p class="text-sm text-gray-500 mt-1">Report new disease case or outbreak.</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <button @click="cancel" class="px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">Cancel</button>
                <button @click="save" :disabled="saving" class="flex items-center gap-2 bg-[#34554a] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#2a443b] shadow-sm transition-colors disabled:opacity-50">
                    <Save class="w-4 h-4" />
                    {{ saving ? 'Saving...' : 'Report Case' }}
                </button>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden max-w-3xl">
            <div class="p-4 border-b border-gray-100">
                <h3 class="text-sm font-bold text-gray-900 flex items-center gap-2">
                    <Activity class="w-4 h-4 text-red-600" />
                    Disease Information
                </h3>
            </div>
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Disease *</label>
                        <select v-model="form.disease" class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white outline-none text-sm focus:ring-2 focus:ring-[#34554a]">
                            <option value="">Select Disease</option>
                            <option v-for="d in diseases" :key="d" :value="d">{{ d }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Date of Onset *</label>
                        <input v-model="form.date_onset" type="date" class="w-full px-3 py-2 rounded-lg border border-gray-200 outline-none text-sm focus:ring-2 focus:ring-[#34554a]">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Tag Number(s) *</label>
                        <input v-model="form.tag_no" type="text" placeholder="e.g., BF 24/01" class="w-full px-3 py-2 rounded-lg border border-gray-200 outline-none text-sm focus:ring-2 focus:ring-[#34554a]">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Category</label>
                        <input v-model="form.category" type="text" placeholder="e.g., Anak, W/n" class="w-full px-3 py-2 rounded-lg border border-gray-200 outline-none text-sm focus:ring-2 focus:ring-[#34554a]">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Location</label>
                        <input v-model="form.location" type="text" placeholder="e.g., Barn 3" class="w-full px-3 py-2 rounded-lg border border-gray-200 outline-none text-sm focus:ring-2 focus:ring-[#34554a]">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Affected Count</label>
                        <input v-model="form.affected_count" type="number" min="1" class="w-full px-3 py-2 rounded-lg border border-gray-200 outline-none text-sm focus:ring-2 focus:ring-[#34554a]">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Severity *</label>
                    <div class="flex gap-3">
                        <label class="flex items-center gap-2">
                            <input type="radio" v-model="form.severity" value="low" class="text-green-600 focus:ring-green-600">
                            <span class="text-sm text-gray-700">Low</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="radio" v-model="form.severity" value="medium" class="text-blue-600 focus:ring-blue-600">
                            <span class="text-sm text-gray-700">Medium</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="radio" v-model="form.severity" value="high" class="text-amber-600 focus:ring-amber-600">
                            <span class="text-sm text-gray-700">High</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="radio" v-model="form.severity" value="critical" class="text-red-600 focus:ring-red-600">
                            <span class="text-sm text-gray-700">Critical</span>
                        </label>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Treatment Given</label>
                    <textarea v-model="form.treatment" rows="2" placeholder="Describe treatment administered..." class="w-full px-3 py-2 rounded-lg border border-gray-200 outline-none text-sm focus:ring-2 focus:ring-[#34554a]"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Notes</label>
                    <textarea v-model="form.notes" rows="2" placeholder="Additional observations..." class="w-full px-3 py-2 rounded-lg border border-gray-200 outline-none text-sm focus:ring-2 focus:ring-[#34554a]"></textarea>
                </div>
            </div>
        </div>
    </div>
</template>
