<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { 
    ArrowLeft, Edit, Trash2, ClipboardList, Calendar,
    FileText, CheckCircle, User
} from 'lucide-vue-next';

defineOptions({ 
    layout: (h, page) => h(AppLayout, { title: 'Treatment Record Details', parent: 'Health', parentUrl: '/health' }, () => page)
});

const props = defineProps({
    id: String
});

const page = usePage();
const record = ref({
    id: props.id || 1,
    no: 1,
    date: '02/09/24',
    tag_no: 'BF 24/01',
    category: 'W/nor',
    colour: 'Grey',
    symptoms: 'PN',
    treatment: 'OTC 12ml + Daxa 10ml',
    remarks: '',
    prepared_by: 'Sr. Asst. Livestock',
    checked_by: 'Supervisor Livestock',
    approved_by: 'Manager Livestock',
});

const goBack = () => {
    router.visit('/health/treatment');
};

const editRecord = () => {
    router.visit(`/health/treatment/${record.value.id}/edit`);
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
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Treatment Record Details</h1>
                    <p class="text-sm text-gray-500 mt-1">Record No. {{ record.no }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <button @click="editRecord" class="flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
                    <Edit class="w-4 h-4" />
                    Edit
                </button>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
            <div class="p-4 border-b border-gray-100 bg-[#34554a]">
                <h3 class="text-sm font-bold text-white flex items-center gap-2">
                    <ClipboardList class="w-4 h-4" />
                    Sawit Kinabalu Farm Products Sdn. Bhd.
                </h3>
                <p class="text-xs text-white/70 mt-1">Co. No.: 463571 | P.O. Box Locked Bag No. 28, 91009 Tawau, Sabah</p>
                <p class="text-xs text-white/70">Tel: 089-913056 / 019-8628285</p>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-2 gap-6 mb-6">
                    <div>
                        <p class="text-xs text-gray-500 font-bold uppercase tracking-wide mb-1">Document Title</p>
                        <p class="text-lg font-bold text-gray-900">Monthly Treatment Record</p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-gray-500 font-bold uppercase tracking-wide mb-1">Date</p>
                        <p class="text-lg font-bold text-gray-900">{{ record.date }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6">
                    <div>
                        <p class="text-xs text-gray-500 font-semibold mb-1">Tag Number</p>
                        <p class="text-gray-900 font-medium">{{ record.tag_no }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-semibold mb-1">Category</p>
                        <p class="text-gray-900 font-medium">{{ record.category }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-semibold mb-1">Colour</p>
                        <p class="text-gray-900 font-medium">{{ record.colour }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-semibold mb-1">Symptoms</p>
                        <p class="text-gray-900 font-medium">{{ record.symptoms }}</p>
                    </div>
                    <div class="col-span-2">
                        <p class="text-xs text-gray-500 font-semibold mb-1">Treatment / Dosage</p>
                        <p class="text-gray-900 font-medium">{{ record.treatment }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-semibold mb-1">Remarks</p>
                        <p class="text-gray-900 font-medium">{{ record.remarks || '-' }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <p class="text-xs text-gray-500 font-bold uppercase tracking-wide mb-2">Prepared By</p>
                <div class="border-b border-gray-200 mb-2"></div>
                <p class="text-sm text-gray-900 font-medium">{{ record.prepared_by }}</p>
                <p class="text-xs text-gray-400">Sr. Asst. Livestock</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <p class="text-xs text-gray-500 font-bold uppercase tracking-wide mb-2">Checked By</p>
                <div class="border-b border-gray-200 mb-2"></div>
                <p class="text-sm text-gray-900 font-medium">{{ record.checked_by }}</p>
                <p class="text-xs text-gray-400">Supervisor Livestock</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <p class="text-xs text-gray-500 font-bold uppercase tracking-wide mb-2">Approved By</p>
                <div class="border-b border-gray-200 mb-2"></div>
                <p class="text-sm text-gray-900 font-medium">{{ record.approved_by }}</p>
                <p class="text-xs text-gray-400">Manager Livestock</p>
            </div>
        </div>
    </div>
</template>
