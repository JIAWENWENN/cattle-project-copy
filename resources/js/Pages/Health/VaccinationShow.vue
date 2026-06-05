<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { ArrowLeft, Edit, Syringe, CheckCircle, User } from 'lucide-vue-next';

defineOptions({ 
    layout: (h, page) => h(AppLayout, { title: 'Vaccination Details', parent: 'Health', parentUrl: '/health' }, () => page)
});

const props = defineProps({
    id: String
});

const page = usePage();
const schedule = ref({
    id: props.id || 1,
    vaccine: 'Foot and Mouth Disease (FMD)',
    date: '02/01/26',
    due_date: '02/01/26',
    category: 'All Cattle',
    batch_no: 'FMD-2026-001',
    administered_by: 'Dr. Ahmad',
    status: 'completed',
    notes: 'All cattle vaccinated successfully',
});

const goBack = () => {
    router.visit('/health/vaccination');
};

const editSchedule = () => {
    router.visit(`/health/vaccination/${schedule.value.id}/edit`);
};

const formatStatusLabel = (status) => {
    if (!status) return 'Pending';
    const normalized = String(status).replace(/_/g, ' ').toLowerCase();
    return normalized.charAt(0).toUpperCase() + normalized.slice(1);
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
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Vaccination Details</h1>
                    <p class="text-sm text-gray-500 mt-1">{{ schedule.vaccine }}</p>
                </div>
            </div>
            <button @click="editSchedule" class="flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
                <Edit class="w-4 h-4" />
                Edit
            </button>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
            <div class="p-6">
                <div class="flex items-center gap-4 mb-6">
                    <div class="w-16 h-16 bg-[#34554a]/10 rounded-xl flex items-center justify-center">
                        <Syringe class="w-8 h-8 text-[#34554a]" />
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-gray-900">{{ schedule.vaccine }}</h3>
                        <span class="px-2.5 py-1 rounded-full text-xs font-medium"
                            :class="schedule.status === 'completed' ? 'bg-[#1f5c19] text-white border-[#1f5c19]' : schedule.status === 'scheduled' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700'">
                            {{ formatStatusLabel(schedule.status) }}
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    <div>
                        <p class="text-xs text-gray-500 font-semibold mb-1">Due Date</p>
                        <p class="text-gray-900 font-medium">{{ schedule.due_date }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-semibold mb-1">Date Administered</p>
                        <p class="text-gray-900 font-medium">{{ schedule.date }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-semibold mb-1">Category</p>
                        <p class="text-gray-900 font-medium">{{ schedule.category }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-semibold mb-1">Batch Number</p>
                        <p class="text-gray-900 font-medium font-mono">{{ schedule.batch_no }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-semibold mb-1">Administered By</p>
                        <p class="text-gray-900 font-medium">{{ schedule.administered_by }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-semibold mb-1">Notes</p>
                        <p class="text-gray-900 font-medium">{{ schedule.notes }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
