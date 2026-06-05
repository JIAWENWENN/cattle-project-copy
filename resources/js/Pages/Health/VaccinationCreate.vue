<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { ArrowLeft, Save, Syringe } from 'lucide-vue-next';

defineOptions({ 
    layout: (h, page) => h(AppLayout, { title: 'Add Vaccination Schedule', parent: 'Health', parentUrl: '/health' }, () => page)
});

const page = usePage();
const saving = ref(false);

const form = ref({
    vaccine: '',
    due_date: '',
    category: '',
    batch_no: '',
    administered_by: '',
    notes: '',
});

const save = () => {
    saving.value = true;
    setTimeout(() => {
        saving.value = false;
        router.visit('/health/vaccination');
    }, 1000);
};

const cancel = () => {
    router.visit('/health/vaccination');
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
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Add Vaccination Schedule</h1>
                    <p class="text-sm text-gray-500 mt-1">Create new vaccination schedule.</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <button @click="cancel" class="px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">Cancel</button>
                <button @click="save" :disabled="saving" class="flex items-center gap-2 bg-[#34554a] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#2a443b] shadow-sm transition-colors disabled:opacity-50">
                    <Save class="w-4 h-4" />
                    {{ saving ? 'Saving...' : 'Save Schedule' }}
                </button>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden max-w-3xl">
            <div class="p-4 border-b border-gray-100">
                <h3 class="text-sm font-bold text-gray-900 flex items-center gap-2">
                    <Syringe class="w-4 h-4 text-[#34554a]" />
                    Vaccination Details
                </h3>
            </div>
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Vaccine Name *</label>
                        <input v-model="form.vaccine" type="text" placeholder="e.g., Foot and Mouth Disease" class="w-full px-3 py-2 rounded-lg border border-gray-200 outline-none text-sm focus:ring-2 focus:ring-[#34554a]">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Due Date *</label>
                        <input v-model="form.due_date" type="date" class="w-full px-3 py-2 rounded-lg border border-gray-200 outline-none text-sm focus:ring-2 focus:ring-[#34554a]">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Category</label>
                        <input v-model="form.category" type="text" placeholder="e.g., All Cattle, Calves" class="w-full px-3 py-2 rounded-lg border border-gray-200 outline-none text-sm focus:ring-2 focus:ring-[#34554a]">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Batch Number</label>
                        <input v-model="form.batch_no" type="text" placeholder="e.g., FMD-2026-001" class="w-full px-3 py-2 rounded-lg border border-gray-200 outline-none text-sm focus:ring-2 focus:ring-[#34554a]">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Administered By</label>
                    <input v-model="form.administered_by" type="text" placeholder="Veterinarian name" class="w-full px-3 py-2 rounded-lg border border-gray-200 outline-none text-sm focus:ring-2 focus:ring-[#34554a]">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Notes</label>
                    <textarea v-model="form.notes" rows="3" placeholder="Additional notes..." class="w-full px-3 py-2 rounded-lg border border-gray-200 outline-none text-sm focus:ring-2 focus:ring-[#34554a]"></textarea>
                </div>
            </div>
        </div>
    </div>
</template>
