<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    ArrowLeft, Edit, Trash2, ClipboardList, CheckCircle, Download
} from 'lucide-vue-next';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';

defineOptions({
    layout: (h, page) => h(AppLayout, { title: 'Calving Checklist Details', parent: 'Calving Checklist', parentUrl: '/calving-checklist' }, () => page)
});

const props = defineProps({
    checklistRecord: {
        type: Object,
        required: true
    }
});

const showDeleteModal = ref(false);
const displayRecord = computed(() => props.checklistRecord);

const formatDate = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return '-';
    return date.toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric' });
};

const getSexColor = (sex) => {
    return sex === 'MC' ? 'bg-blue-100 text-blue-700' : 'bg-pink-100 text-pink-700';
};

const getStatusColor = (status) => {
    switch (status) {
        case 'approved': return 'bg-green-100 text-green-700';
        case 'issued': return 'bg-blue-100 text-blue-700';
        case 'verified': return 'bg-indigo-100 text-indigo-700';
        case 'checked': return 'bg-cyan-100 text-cyan-700';
        case 'witnessed': return 'bg-purple-100 text-purple-700';
        case 'rejected': return 'bg-red-100 text-red-700';
        case 'completed': return 'bg-[#34554a] text-white';
        default: return 'bg-amber-100 text-amber-700';
    }
};

const goBack = () => {
    router.visit('/calving-checklist');
};

const editRecord = () => {
    router.visit(`/calving-checklist/${displayRecord.value.id}/edit`);
};

const confirmDelete = () => {
    showDeleteModal.value = true;
};

const deleteRecord = () => {
    router.delete(`/calving-checklist/${displayRecord.value.id}`, {
        onSuccess: () => {
            showDeleteModal.value = false;
            router.visit('/calving-checklist');
        }
    });
};

const downloadEndorsementForm = () => {
    window.open(`/calving-checklist/${displayRecord.value.id}/endorsement-form`, '_blank');
};
</script>

<template>
    <div class="w-full">
        <div class="mb-6 flex justify-between items-center">
            <div class="flex items-center gap-4">
                <button @click="goBack" class="w-10 h-10 flex items-center justify-center rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors">
                    <ArrowLeft class="w-5 h-5 text-gray-600" />
                </button>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Calving Checklist Details</h1>
                    <p class="text-sm text-gray-500 mt-1">LCC No.: {{ displayRecord.lcc_running_number || '-' }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <button @click="downloadEndorsementForm" class="flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium text-[#34554a] hover:bg-[#34554a]/10 transition-colors">
                    <Download class="w-4 h-4" />
                    Download LCC
                </button>
                <button @click="confirmDelete" class="flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium text-red-600 hover:bg-red-50 transition-colors">
                    <Trash2 class="w-4 h-4" />
                    Delete
                </button>
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
                        <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide mb-1">Document Title</p>
                        <p class="text-lg font-bold text-gray-900">Monthly Calving Checklist Record</p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide mb-1">Status</p>
                        <span class="px-3 py-1 rounded-full text-sm font-medium" :class="getStatusColor(displayRecord.workflow_status || displayRecord.status)">
                            {{ ((displayRecord.workflow_status || displayRecord.status || 'pending').replace(/_/g, ' ').toUpperCase()) }}
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div>
                        <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide mb-1">LCC No.</p>
                        <p class="text-gray-900 font-mono font-medium">{{ displayRecord.lcc_running_number || '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide mb-1">Week</p>
                        <p class="text-gray-900 font-medium">{{ displayRecord.week || '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide mb-1">Calving Date</p>
                        <p class="text-gray-900 font-medium">{{ formatDate(displayRecord.calving_date) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide mb-1">Operating Unit</p>
                        <p class="text-gray-900 font-medium">{{ displayRecord.operating_unit || '-' }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div>
                        <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide mb-1">Calf Tag No.</p>
                        <p class="text-gray-900 font-mono font-medium">{{ displayRecord.tag_no || '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide mb-1">Sex</p>
                        <span class="px-2.5 py-1 rounded-full text-xs font-medium" :class="getSexColor(displayRecord.sex)">
                            {{ displayRecord.sex || '-' }}
                        </span>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide mb-1">Colour</p>
                        <p class="text-gray-900 font-medium">{{ displayRecord.colour || '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide mb-1">General Condition</p>
                        <p class="text-gray-900 font-medium">{{ displayRecord.general_condition || '-' }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div>
                        <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide mb-1">Times of Pregnancy</p>
                        <p class="text-gray-900 font-medium">{{ displayRecord.times_of_pregnancy || '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide mb-1">Dam Tag No.</p>
                        <p class="text-gray-900 font-mono font-medium">{{ displayRecord.dam_tag_no || '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide mb-1">Location</p>
                        <p class="text-gray-900 font-medium">{{ displayRecord.location || '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide mb-1">Location Block</p>
                        <p class="text-gray-900 font-medium">{{ displayRecord.location_block || '-' }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div>
                        <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide mb-1">Location Phase</p>
                        <p class="text-gray-900 font-medium">{{ displayRecord.location_phase || '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide mb-1">Treatment (Iodine)</p>
                        <p class="text-gray-900 font-medium">{{ displayRecord.treatment_iodine ? 'Yes' : 'No' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide mb-1">Treatment (Woundsarex)</p>
                        <p class="text-gray-900 font-medium">{{ displayRecord.treatment_woundsarex ? 'Yes' : 'No' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide mb-1">Colostrum Feeding (24h)</p>
                        <p class="text-gray-900 font-medium">{{ displayRecord.colostrum_feeding_24h ? 'Yes' : 'No' }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div>
                        <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide mb-1">Mamumune</p>
                        <p class="text-gray-900 font-medium">{{ displayRecord.mamumune ? 'Yes' : 'No' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide mb-1">Tagging/Checklist Date</p>
                        <p class="text-gray-900 font-medium">{{ formatDate(displayRecord.tagging_checklist_date) }}</p>
                    </div>
                </div>

                <div class="mb-2">
                    <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide mb-1">Remarks</p>
                    <p class="text-gray-900 font-medium">{{ displayRecord.remarks || '-' }}</p>
                </div>
            </div>
        </div>

        <DeleteConfirmationModal
            :show="showDeleteModal"
            title="Delete Calving Checklist Record"
            message="Are you sure you want to delete this calving checklist record?"
            :itemName="displayRecord.lcc_running_number || ''"
            @close="showDeleteModal = false"
            @confirm="deleteRecord"
        />
    </div>
</template>
