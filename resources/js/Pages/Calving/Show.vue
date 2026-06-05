<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import {
    ArrowLeft, Edit, Trash2, ClipboardList,
    CheckCircle, Download
} from 'lucide-vue-next';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';

defineOptions({
    layout: (h, page) => h(AppLayout, { title: 'Calving Record Details', parent: 'Calving', parentUrl: '/calving' }, () => page)
});

const props = defineProps({
    calvingRecord: {
        type: Object,
        required: true
    }
});

const page = usePage();
const showDeleteModal = ref(false);
const calvingRecord = computed(() => props.calvingRecord);

const workflowSteps = [
    { id: 'issued', label: 'Issued', role: 'livestock', role_name: 'Sr. Assistant Livestock' },
    { id: 'verified', label: 'Verified', role: 'security', role_name: 'Sr. Assistant Security' },
    { id: 'checked', label: 'Checked', role: 'supervisor', role_name: 'Supervisor Livestock' },
    { id: 'witnessed', label: 'Witnessed', role: 'estate', role_name: 'Estate Management' },
    { id: 'approved', label: 'Approved', role: 'manager', role_name: 'Livestock Manager / OIC' }
];

const userRole = computed(() => page.props.auth?.user?.role || '');

const getStatusStepIndex = (status) => {
    const steps = ['pending', 'issued', 'verified', 'checked', 'witnessed', 'approved'];
    const index = steps.indexOf(status);
    return index >= 0 ? index : 0;
};

const canPerformAction = (record) => {
    if (record.status === 'approved' || record.status === 'completed') return false;
    if (record.status === 'rejected') return false;
    
    const stepIndex = getStatusStepIndex(record.status);
    const currentStep = workflowSteps[stepIndex];
    
    if (!currentStep) return false;
    
    if (userRole.value === 'admin') return true;
    
    return userRole.value === currentStep.role;
};

const getCurrentStep = (record) => {
    const stepIndex = getStatusStepIndex(record.status);
    if (stepIndex >= workflowSteps.length) return null;
    return workflowSteps[stepIndex];
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric' });
};

const formatDateTime = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric' }) + ' ' + date.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
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
        default: return 'bg-amber-100 text-amber-700';
    }
};

const goBack = () => {
    router.visit('/calving');
};

const editRecord = () => {
    router.visit(`/calving/${calvingRecord.value.id}/edit`);
};

const confirmDelete = () => {
    showDeleteModal.value = true;
};

const deleteRecord = () => {
    router.post(route('calving.delete', calvingRecord.value.id), {}, {
        onSuccess: () => {
            showDeleteModal.value = false;
            router.visit('/calving');
        },
        onError: () => {
            alert('Failed to delete calving record. Please try again.');
        },
    });
};

const performAction = (action) => {
    if (confirm(`Are you sure you want to ${action} this record?`)) {
        router.post(`/calving/${calvingRecord.value.id}/${action}`);
    }
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
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Calving Record Details</h1>
                    <p class="text-sm text-gray-500 mt-1">Tag No.: {{ calvingRecord.tag_no || '-' }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <button @click="router.visit(`/calving/${calvingRecord.id}/lcc`)" class="flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium text-[#34554a] hover:bg-[#34554a]/10 transition-colors">
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
            <div class="px-6 py-4 bg-[#34554a] border-b border-gray-200">
                <h3 class="text-sm font-bold text-white uppercase tracking-wide">Calf Identification</h3>
            </div>
            <div class="p-6 grid grid-cols-2 md:grid-cols-4 gap-6">
                <div>
                    <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide mb-1">Identification Tag</p>
                    <p class="text-gray-900 font-mono font-medium">{{ calvingRecord.tag_no || '-' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide mb-1">LCC No.</p>
                    <p class="text-gray-900 font-mono font-medium">{{ calvingRecord.lcc_running_number || '-' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide mb-1">Cattle No. Request Form</p>
                    <p class="text-gray-900 font-medium">{{ calvingRecord.cattle_no_request_form || '-' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide mb-1">Date of Birth</p>
                    <p class="text-gray-900 font-medium">{{ formatDate(calvingRecord.calving_date) }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide mb-1">Gender</p>
                    <span class="px-2.5 py-1 rounded-full text-xs font-medium" :class="getSexColor(calvingRecord.sex)">
                        {{ calvingRecord.sex || '-' }}
                    </span>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide mb-1">Coat Colour</p>
                    <p class="text-gray-900 font-medium">{{ calvingRecord.colour || '-' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide mb-1">Cattle Condition</p>
                    <span class="px-2.5 py-1 rounded-full text-xs font-medium" :class="calvingRecord.general_condition === 'Good' ? 'bg-green-100 text-green-700' : calvingRecord.general_condition === 'Fair' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700'">
                        {{ calvingRecord.general_condition || '-' }}
                    </span>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
            <div class="px-6 py-4 bg-green-50 border-b border-gray-100">
                <h3 class="text-sm font-bold text-green-700 uppercase tracking-wide">Breeder's Details</h3>
            </div>
            <div class="p-6 grid grid-cols-2 md:grid-cols-4 gap-6">
                <div>
                    <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide mb-1">Dam's Identification Tag</p>
                    <p class="text-gray-900 font-mono font-medium">{{ calvingRecord.dam_tag_no || '-' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide mb-1">Dam Coat Colour</p>
                    <p class="text-gray-900 font-medium">{{ calvingRecord.dam_colour || '-' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide mb-1">Sire Identification Tag</p>
                    <p class="text-gray-900 font-mono font-medium">{{ calvingRecord.sire_tag_no || '-' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide mb-1">Sire Coat Colour</p>
                    <p class="text-gray-900 font-medium">{{ calvingRecord.sire_colour || '-' }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
            <div class="px-6 py-4 bg-purple-50 border-b border-gray-100">
                <h3 class="text-sm font-bold text-purple-700 uppercase tracking-wide">Other Details</h3>
            </div>
            <div class="p-6 grid grid-cols-2 md:grid-cols-3 gap-6">
                <div>
                    <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide mb-1">Worker's Name</p>
                    <p class="text-gray-900 font-medium">{{ calvingRecord.worker_name || '-' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide mb-1">Operating Unit</p>
                    <p class="text-gray-900 font-medium">{{ calvingRecord.operating_unit || '-' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide mb-1">Remarks</p>
                    <p class="text-gray-900 font-medium">{{ calvingRecord.remarks || '-' }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
            <div class="px-6 py-4 bg-amber-50 border-b border-gray-100">
                <h3 class="text-sm font-bold text-amber-700 uppercase tracking-wide">Location</h3>
            </div>
            <div class="p-6 grid grid-cols-2 md:grid-cols-2 gap-6">
                <div>
                    <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide mb-1">Block</p>
                    <p class="text-gray-900 font-medium">{{ calvingRecord.location_block || '-' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide mb-1">Phase</p>
                    <p class="text-gray-900 font-medium">{{ calvingRecord.location_phase || '-' }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
            <div class="px-6 py-4 bg-gray-100 border-b border-gray-200">
                <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide">Status</h3>
            </div>
            <div class="p-6">
                <div class="flex items-center gap-4 mb-4">
                    <span class="px-3 py-1 rounded-full text-sm font-medium" :class="getStatusColor(calvingRecord.status)">
                        {{ (calvingRecord.status || 'pending').toUpperCase() }}
                    </span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <div class="p-4 border border-gray-200 rounded-lg bg-gray-50">
                        <p class="text-xs font-bold text-gray-600 uppercase tracking-wide mb-1">Issued by</p>
                        <p class="text-[10px] text-gray-500 mb-2">Sr. Assistant Livestock</p>
                        <p class="text-[10px] text-gray-500 mb-1">Name:</p>
                        <div class="h-8 border-b border-gray-300 mb-2">
                            <span class="text-sm text-gray-800">{{ calvingRecord.issued_by_name || '-' }}</span>
                        </div>
                        <p class="text-[10px] text-gray-500 mb-1">Date:</p>
                        <div class="h-8 border-b border-gray-300 mb-2">
                            <span class="text-sm text-gray-800">{{ formatDate(calvingRecord.issued_at) }}</span>
                        </div>
                        <div class="h-10 border-b border-dashed border-gray-300 flex items-end justify-center mt-2">
                            <span class="text-[10px] text-gray-400 italic">Signature</span>
                        </div>
                    </div>
                    <div class="p-4 border border-gray-200 rounded-lg bg-gray-50">
                        <p class="text-xs font-bold text-gray-600 uppercase tracking-wide mb-1">Verified by</p>
                        <p class="text-[10px] text-gray-500 mb-2">Sr. Assistant Security</p>
                        <p class="text-[10px] text-gray-500 mb-1">Name:</p>
                        <div class="h-8 border-b border-gray-300 mb-2">
                            <span class="text-sm text-gray-800">{{ calvingRecord.verified_by_name || '-' }}</span>
                        </div>
                        <p class="text-[10px] text-gray-500 mb-1">Date:</p>
                        <div class="h-8 border-b border-gray-300 mb-2">
                            <span class="text-sm text-gray-800">{{ formatDate(calvingRecord.verified_at) }}</span>
                        </div>
                        <div class="h-10 border-b border-dashed border-gray-300 flex items-end justify-center mt-2">
                            <span class="text-[10px] text-gray-400 italic">Signature</span>
                        </div>
                    </div>
                    <div class="p-4 border border-gray-200 rounded-lg bg-gray-50">
                        <p class="text-xs font-bold text-gray-600 uppercase tracking-wide mb-1">Checked by</p>
                        <p class="text-[10px] text-gray-500 mb-2">Supervisor Livestock</p>
                        <p class="text-[10px] text-gray-500 mb-1">Name:</p>
                        <div class="h-8 border-b border-gray-300 mb-2">
                            <span class="text-sm text-gray-800">{{ calvingRecord.checked_by_name || '-' }}</span>
                        </div>
                        <p class="text-[10px] text-gray-500 mb-1">Date:</p>
                        <div class="h-8 border-b border-gray-300 mb-2">
                            <span class="text-sm text-gray-800">{{ formatDate(calvingRecord.checked_at) }}</span>
                        </div>
                        <div class="h-10 border-b border-dashed border-gray-300 flex items-end justify-center mt-2">
                            <span class="text-[10px] text-gray-400 italic">Signature</span>
                        </div>
                    </div>
                    <div class="p-4 border border-gray-200 rounded-lg bg-gray-50">
                        <p class="text-xs font-bold text-gray-600 uppercase tracking-wide mb-1">Witnessed by</p>
                        <p class="text-[10px] text-gray-500 mb-2">Estate Management</p>
                        <p class="text-[10px] text-gray-500 mb-1">Name:</p>
                        <div class="h-8 border-b border-gray-300 mb-2">
                            <span class="text-sm text-gray-800">{{ calvingRecord.witnessed_by_name || '-' }}</span>
                        </div>
                        <p class="text-[10px] text-gray-500 mb-1">Date:</p>
                        <div class="h-8 border-b border-gray-300 mb-2">
                            <span class="text-sm text-gray-800">{{ formatDate(calvingRecord.witnessed_at) }}</span>
                        </div>
                        <div class="h-10 border-b border-dashed border-gray-300 flex items-end justify-center mt-2">
                            <span class="text-[10px] text-gray-400 italic">Signature</span>
                        </div>
                    </div>
                    <div class="p-4 border border-gray-200 rounded-lg bg-gray-50">
                        <p class="text-xs font-bold text-gray-600 uppercase tracking-wide mb-1">Approved by</p>
                        <p class="text-[10px] text-gray-500 mb-2">Livestock Manager/OIC</p>
                        <p class="text-[10px] text-gray-500 mb-1">Name:</p>
                        <div class="h-8 border-b border-gray-300 mb-2">
                            <span class="text-sm text-gray-800">{{ calvingRecord.approved_by_name || '-' }}</span>
                        </div>
                        <p class="text-[10px] text-gray-500 mb-1">Date:</p>
                        <div class="h-8 border-b border-gray-300 mb-2">
                            <span class="text-sm text-gray-800">{{ formatDate(calvingRecord.approved_at) }}</span>
                        </div>
                        <div class="h-10 border-b border-dashed border-gray-300 flex items-end justify-center mt-2">
                            <span class="text-[10px] text-gray-400 italic">Signature</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <DeleteConfirmationModal
            :show="showDeleteModal"
            title="Delete Calving Record"
            message="Are you sure you want to delete this calving record?"
            :itemName="calvingRecord.tag_no || ''"
            @close="showDeleteModal = false"
            @confirm="deleteRecord"
        />
    </div>
</template>
