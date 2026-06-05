<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import {
    Clock, Eye, Calendar, CheckCircle,
    FileSignature, Shield, UserCheck, ClipboardCheck,
    ChevronRight, Download, FileText, RotateCcw, AlertTriangle, Upload, ArrowLeft, User
} from 'lucide-vue-next';

defineOptions({
    layout: (h, page) => h(AppLayout, { title: 'Calving Checklist Pending Approvals', parent: 'Calving Checklist', parentUrl: '/calving-checklist' }, () => page)
});

const page = usePage();

const props = defineProps({
    checklistRecords: {
        type: Array,
        default: () => []
    },
    userRole: {
        type: String,
        default: ''
    },
    monthlyWorkflow: Object,
    availableMonths: Array,
    operatingUnits: Array,
    docWorkflowSteps: Array,
    workflowAssignment: Object,
});

const docWorkflowSteps = [
    { role: 'livestock', label: 'Issued by', role_name: 'Sr. Assistant Livestock' },
    { role: 'security', label: 'Verified by', role_name: 'Sr. Assistant Security' },
    { role: 'supervisor', label: 'Checked by', role_name: 'Supervisor Livestock' },
    { role: 'penyelia', label: 'Witnessed by', role_name: 'Penyelia Security' },
    { role: 'manager', label: 'Approved by', role_name: 'Livestock Manager / OIC' },
];

const getStepIndex = (step) => {
    const steps = ['pending', 'issued', 'verified', 'checked', 'witnessed', 'approved'];
    const index = steps.indexOf(step);
    return index >= 0 ? index : 0;
};

const displayWorkflow = computed(() => props.monthlyWorkflow || {});
const displayDocWorkflowSteps = computed(() => props.docWorkflowSteps || [
    { role: 'livestock', label: 'Issued by', role_name: 'Sr. Assistant Livestock' },
    { role: 'security', label: 'Verified by', role_name: 'Sr. Assistant Security' },
    { role: 'supervisor', label: 'Checked by', role_name: 'Supervisor Livestock' },
    { role: 'penyelia', label: 'Witnessed by', role_name: 'Penyelia Security' },
    { role: 'manager', label: 'Approved by', role_name: 'Livestock Manager / OIC' },
]);

const showModal = ref(false);
const selectedStepIndex = ref(null);
const isSubmitting = ref(false);
const docUploadForm = ref({
    name: '',
    date: new Date().toISOString().split('T')[0],
    signed_document: null,
});

const getStepDocument = (workflow, stepIndex) => {
    if (!workflow?.endorsement_documents) return null;
    return workflow.endorsement_documents[stepIndex] || null;
};

const workflowAssignment = computed(() => props.workflowAssignment || null);
const userId = computed(() => Number(page.props.auth?.user?.id || 0));

const getAssignedUserIdsForStep = (stepIndex) => {
    const cfg = workflowAssignment.value;
    if (!cfg) return [];

    let ids = [];
    if (stepIndex === 0) {
        ids = Array.isArray(cfg.issued_by_user_ids) ? cfg.issued_by_user_ids : [];
        if (!ids.length && cfg.issued_by_user_id) ids = [cfg.issued_by_user_id];
    } else if (stepIndex === 1) {
        ids = Array.isArray(cfg.verified_by_user_ids) ? cfg.verified_by_user_ids : [];
        if (!ids.length && cfg.verified_by_user_id) ids = [cfg.verified_by_user_id];
    } else if (stepIndex === 2) {
        ids = Array.isArray(cfg.checked_by_user_ids) ? cfg.checked_by_user_ids : [];
        if (!ids.length && cfg.checked_by_user_id) ids = [cfg.checked_by_user_id];
    } else if (stepIndex === 3) {
        ids = Array.isArray(cfg.witnessed_by_user_ids) ? cfg.witnessed_by_user_ids : [];
        if (!ids.length && cfg.witnessed_by_user_id) ids = [cfg.witnessed_by_user_id];
    } else if (stepIndex === 4) {
        ids = Array.isArray(cfg.approved_by_user_ids) ? cfg.approved_by_user_ids : [];
        if (!ids.length && cfg.approved_by_user_id) ids = [cfg.approved_by_user_id];
    }

    return [...new Set((ids || []).map(v => Number(v)).filter(v => Number.isInteger(v) && v > 0))];
};

const getChecklistPermissionList = () => {
    const all = page.props.auth?.permissions || {};
    const aliases = ['Calving Checklist', 'Calving checklist', 'calving checklist', 'Calving Record'];

    for (const key of aliases) {
        const perms = all?.[key];
        if (Array.isArray(perms)) return perms;
    }

    const normalizedTarget = 'calving checklist';
    const matchedKey = Object.keys(all || {}).find((k) => String(k).trim().toLowerCase() === normalizedTarget);
    if (matchedKey && Array.isArray(all[matchedKey])) {
        return all[matchedKey];
    }

    return ['no-access'];
};

const hasCalvingChecklistPermission = (action) => {
    if (String(props.userRole).toLowerCase() === 'admin') return true;
    const permsArray = getChecklistPermissionList();
    return permsArray.includes('full') || permsArray.includes(action);
};

const canUserActStep = (stepIndex) => {
    if (props.userRole === 'admin') return true;
    // User must have at least 'view' permission on Calving Checklist to perform any workflow step
    if (!hasCalvingChecklistPermission('view')) return false;
    // Only users explicitly assigned in the ACM can act — no role-based fallback
    const assignedUserIds = getAssignedUserIdsForStep(stepIndex);
    return assignedUserIds.includes(userId.value);
};

const canUploadStep = (workflow, stepIndex) => {
    if (!workflow) return false;
    if (workflow.is_completed) return false;

    const currentStep = workflow.endorsement_step || 0;

    if (!canUserActStep(stepIndex)) return false;

    if (props.userRole === 'admin') {
        return stepIndex <= currentStep;
    }

    return stepIndex === currentStep || stepIndex === currentStep - 1;
};

const handleDocFileUpload = (event) => {
    const file = event.target.files[0];
    if (file) {
        docUploadForm.value.signed_document = file;
    }
};

const openUploadModal = (stepIndex) => {
    selectedStepIndex.value = stepIndex;
    const step = displayDocWorkflowSteps.value[stepIndex];
    const existingDoc = getStepDocument(displayWorkflow.value, stepIndex);
    
    docUploadForm.value = {
        name: existingDoc?.name || step?.role_name || '',
        date: existingDoc?.date || new Date().toISOString().split('T')[0],
        signed_document: null,
    };
    showModal.value = true;
};

const submitDocUpload = () => {
    if (isSubmitting.value) return;
    
    if (!docUploadForm.value.signed_document) {
        alert('Please upload the signed document');
        return;
    }
    
    if (!docUploadForm.value.name || !docUploadForm.value.date) {
        alert('Please fill in Name and Date');
        return;
    }

    isSubmitting.value = true;

    const formData = new FormData();
    formData.append('signed_document', docUploadForm.value.signed_document);
    formData.append('name', docUploadForm.value.name);
    formData.append('date', docUploadForm.value.date);
    formData.append('step_index', selectedStepIndex.value);
    formData.append('month_year', displayWorkflow.value.month_year);
    formData.append('operating_unit', displayWorkflow.value.operating_unit);

    router.post('/calving-checklist/upload-batch-endorsement', formData, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            docUploadForm.value.signed_document = null;
            isSubmitting.value = false;
            showModal.value = false;
        },
        onError: () => {
            isSubmitting.value = false;
        }
    });
};

const downloadStepDocument = (stepIndex) => {
    window.open(`/calving-checklist/download-batch-endorsement/${stepIndex}?month=${displayWorkflow.value.month_year}&unit=${displayWorkflow.value.operating_unit}`, '_blank');
};

const checklistRecords = computed(() => props.checklistRecords || []);

const myTasks = computed(() => {
    const allRecords = checklistRecords.value;
    
    if (props.userRole === 'admin') {
        return allRecords.filter(r => !r.is_completed && r.status !== 'rejected');
    }
    
    return allRecords.filter(r => {
        if (r.is_completed || r.status === 'rejected') return false;
        
        const stepIndex = getStepIndex(r.status);
        return canUserActStep(stepIndex);
    });
});

const getCurrentStepLabel = (record) => {
    const stepIndex = getStepIndex(record.status || 'pending');
    if (stepIndex >= docWorkflowSteps.length) return 'Completed';
    return docWorkflowSteps[stepIndex]?.label || 'Pending';
};

const canPerformAction = (record) => {
    if (record.is_completed || record.status === 'approved' || record.status === 'rejected') return false;
    
    const stepIndex = getStepIndex(record.status || 'pending');
    if (stepIndex >= docWorkflowSteps.length) return false;
    
    return canUserActStep(stepIndex);
};

const editRecord = (record) => {
    router.visit(`/calving-checklist/${record.id}/edit`);
};

const canEditRecord = (record) => {
    if (props.userRole !== 'admin') return false;
    return record.workflow_status !== 'completed' || !!record.is_reopened;
};

const viewDetail = (record) => {
    router.visit(`/calving-checklist/${record.id}`);
};

const formatDate = (dateString) => {
    if (!dateString) return '';
    
    // Handle different date formats (YYYY-MM-DD from Laravel/date picker)
    let date;
    if (typeof dateString === 'string' && dateString.match(/^\d{4}-\d{2}-\d{2}$/)) {
        // Format: YYYY-MM-DD
        const [year, month, day] = dateString.split('-');
        date = new Date(parseInt(year), parseInt(month) - 1, parseInt(day));
    } else if (typeof dateString === 'string' && dateString.match(/^\d{4}-\d{2}-\d{2}T/)) {
        // Format: ISO string with time
        date = new Date(dateString);
    } else {
        // Try standard parsing
        date = new Date(dateString);
    }
    
    // Check if date is valid
    if (isNaN(date.getTime())) {
        return '';
    }
    
    return date.toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric' });
};

const performAction = (record, action) => {
    if (confirm(`Are you sure you want to ${action} this record?`)) {
        router.post(`/calving-checklist/${record.id}/${action}`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                // Record will be refreshed automatically
            },
            onError: (errors) => {
                console.error(`${action} failed:`, errors);
                alert(`Failed to ${action} record. Please try again.`);
            }
        });
    }
};

const getStatusColor = (status) => {
    switch (status) {
        case 'approved': return 'bg-green-100 text-green-700';
        case 'issued': return 'bg-blue-100 text-blue-700';
        case 'verified': return 'bg-indigo-100 text-indigo-700';
        case 'checked': return 'bg-purple-100 text-purple-700';
        case 'witnessed': return 'bg-orange-100 text-orange-700';
        case 'rejected': return 'bg-red-100 text-red-700';
        case 'completed': return 'bg-[#1f5c19] text-white border-[#1f5c19]';
        default: return 'bg-amber-100 text-amber-700';
    }
};

const getSexColor = (sex) => {
    return sex === 'MC' ? 'bg-blue-100 text-blue-700' : 'bg-pink-100 text-pink-700';
};
</script>

<template>
    <div class="max-w-7xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Calving Checklist Pending Approvals</h1>
            <p class="text-sm text-gray-500 mt-1">Review and approve calving checklist records</p>
        </div>

        <div v-if="false" class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-6">
            <AlertTriangle class="w-12 h-12 text-yellow-500 mx-auto mb-4" />
            <h2 class="text-lg font-bold text-yellow-700 mb-2 text-center">No Role Assigned</h2>
            <p class="text-yellow-600 text-center">Your user account doesn't have a role assigned. Please contact your administrator.</p>
        </div>

        <template v-else>
            <div class="bg-white rounded-xl border border-gray-200 p-4 mb-6">
                <div class="flex items-center justify-between flex-wrap gap-4">
                    <div class="flex items-center gap-4">
                        <span class="px-3 py-1 bg-[#34554a] text-white rounded-full text-sm font-bold">
                            {{ myTasks.length }} Pending Task{{ myTasks.length !== 1 ? 's' : '' }}
                        </span>
                        <span class="text-sm text-gray-500">Your role: <strong>{{ userRole }}</strong></span>
                    </div>
                </div>
            </div>

            <!-- Monthly Endorsement Workflow Section -->
            <div v-if="displayWorkflow && displayWorkflow.month_year" class="mb-8 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden mt-6">
                <div class="p-4 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="text-sm font-bold text-gray-900 flex items-center gap-2">
                        <FileSignature class="w-4 h-4 text-[#34554a]" />
                        Monthly Endorsement Workflow (Batch) - {{ displayWorkflow.month_year }} ({{ displayWorkflow.operating_unit }})
                    </h3>
                    <div v-if="displayWorkflow.is_completed" class="flex items-center gap-2 text-green-600 text-xs font-bold">
                        <CheckCircle class="w-4 h-4" />
                        MONTHLY COMPLETED
                    </div>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                        <div v-for="(step, index) in displayDocWorkflowSteps" :key="index" 
                             class="p-4 rounded-xl border transition-all"
                             :class="[
                                 getStepDocument(displayWorkflow, index) ? 'bg-green-50 border-green-100' : 
                                 canUploadStep(displayWorkflow, index) ? 'bg-amber-50 border-amber-200 ring-2 ring-amber-400/20' : 
                                 'bg-gray-50 border-gray-100 opacity-60'
                             ]"
                        >
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">{{ step.label }}</span>
                                <CheckCircle v-if="getStepDocument(displayWorkflow, index)" class="w-4 h-4 text-green-500" />
                                <Clock v-else-if="canUploadStep(displayWorkflow, index)" class="w-4 h-4 text-amber-500 animate-pulse" />
                            </div>
                            
                            <p class="text-xs font-bold text-gray-900 mb-1">{{ step.role_name }}</p>
                            
                            <div v-if="getStepDocument(displayWorkflow, index)" class="mt-3">
                                <div class="text-[9px] text-gray-500 mb-2 italic">
                                    Signed by {{ getStepDocument(displayWorkflow, index).name }}<br>
                                    on {{ formatDate(getStepDocument(displayWorkflow, index).uploaded_at) }}
                                </div>
                                <div class="flex gap-2">
                                    <button 
                                        @click="downloadStepDocument(index)"
                                        class="flex-1 flex items-center justify-center gap-1 py-1.5 bg-white text-green-700 rounded-lg border border-green-200 hover:bg-green-50 text-[10px] font-bold transition-all"
                                     Calving Checklist="">
                                        VIEW
                                    </button>
                                    <button 
                                        v-if="canUploadStep(displayWorkflow, index)"
                                        @click="openUploadModal(index)"
                                        class="flex-1 flex items-center justify-center gap-1 py-1.5 bg-white text-amber-700 rounded-lg border border-amber-200 hover:bg-amber-50 text-[10px] font-bold transition-all"
                                    >
                                        RE-UPLOAD
                                    </button>
                                </div>
                            </div>
                            <div v-else-if="canUploadStep(displayWorkflow, index)" class="mt-4">
                                <button 
                                    @click="openUploadModal(index)"
                                    class="w-full py-2 bg-[#34554a] text-white rounded-lg text-[10px] font-bold hover:bg-[#2c463d] transition-all shadow-sm"
                                >
                                    <Upload class="w-3 h-3 inline mr-1" />
                                    UPLOAD
                                </button>
                            </div>
                            <div v-else class="mt-4 text-center">
                                <span class="text-[9px] font-bold text-gray-400 uppercase italic">
                                    {{ index < (displayWorkflow.endorsement_step || 0) ? 'COMPLETED' : 'AWAITING' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-200">
                <div class="overflow-x-auto">
                    <table class="w-full text-left whitespace-nowrap">
                        <thead>
                            <tr class="bg-[#34554a] text-white text-sm">
                                <th class="p-4 font-semibold">LCC No.</th>
                                <th class="p-4 font-semibold">Date</th>
                                <th class="p-4 font-semibold">Tag No.</th>
                                <th class="p-4 font-semibold">Sex</th>
                                <th class="p-4 font-semibold">Dam Tag No.</th>
                                <th class="p-4 font-semibold">Location</th>
                                <th class="p-4 font-semibold">Condition</th>
                                <th class="p-4 font-semibold">Current Step</th>
                                <th class="p-4 font-semibold">Action Required</th>
                                <th class="p-4 font-semibold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-sm text-gray-700">
                            <tr v-for="record in checklistRecords" :key="record.id" class="hover:bg-gray-50 transition-colors">
                                <td class="p-4">
                                    <code class="bg-[#34554a]/10 text-[#34554a] px-2 py-1 rounded font-bold text-xs">{{ record.lcc_running_number || `LCC-${record.id}` }}</code>
                                </td>
                                <td class="p-4">{{ formatDate(record.calving_date) }}</td>
                                <td class="p-4 font-medium">{{ record.tag_no }}</td>
                                <td class="p-4">
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-medium" :class="getSexColor(record.sex)">
                                        {{ record.sex }}
                                    </span>
                                </td>
                                <td class="p-4 font-mono text-xs">{{ record.dam_tag_no || '-' }}</td>
                                <td class="p-4 text-gray-600">{{ record.location_block || '-' }}</td>
                                <td class="p-4">
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-medium" :class="record.general_condition === 'Good' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'">
                                        {{ record.general_condition || '-' }}
                                    </span>
                                </td>
                                <td class="p-4">
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-medium border" :class="getStatusColor(record.workflow_status || record.status)">
                                        {{ getCurrentStepLabel(record) }}
                                    </span>
                                </td>
                                <td class="p-4">
                                    <span 
                                        v-if="canPerformAction(record)"
                                        class="px-2.5 py-0.5 bg-green-100 text-green-800 rounded-full text-xs font-medium border border-green-200 animate-pulse"
                                    >
                                        Your Action Required
                                    </span>
                                    <span 
                                        v-else
                                        class="px-2.5 py-0.5 bg-gray-100 text-gray-500 rounded-full text-xs font-medium"
                                    >
                                        {{ docWorkflowSteps[getStepIndex(record.status || 'pending')]?.role_name || 'Completed' }}
                                    </span>
                                </td>
                                <td class="p-4 text-right flex justify-end gap-2">
                                    <button 
                                        v-if="canEditRecord(record)"
                                        @click="editRecord(record)"
                                        class="w-7 h-7 text-gray-400 hover:text-[#34554a] hover:bg-[#34554a]/10 rounded flex items-center justify-center transition-colors"
                                        title="Edit Record">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-edit"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </button>
                                    <button 
                                        @click="viewDetail(record)"
                                        class="w-7 h-7 text-gray-400 hover:text-[#34554a] hover:bg-[#34554a]/10 rounded flex items-center justify-center transition-colors"
                                        title="View Details">
                                        <Eye class="w-4 h-4" />
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="checklistRecords.length === 0">
                                <td colspan="10" class="p-8 text-center text-gray-400 italic">
                                    No calving checklist records found
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pending Tasks Summary -->
            <div v-if="myTasks.length > 0" class="mt-6 bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Your Pending Tasks</h3>
                <div class="space-y-3">
                    <div 
                        v-for="task in myTasks" 
                        :key="task.id"
                        class="flex items-center justify-between p-4 bg-gray-50 rounded-lg border border-gray-200"
                    >
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 bg-[#34554a]/10 rounded-full flex items-center justify-center">
                                <Clock class="w-5 h-5 text-[#34554a]" />
                            </div>
                            <div>
                                <p class="font-medium text-gray-900">{{ task.lcc_running_number || `LCC-${task.id}` }} - {{ task.tag_no }}</p>
                                <p class="text-sm text-gray-500">{{ formatDate(task.calving_date) }} - {{ task.location_block }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <button 
                                @click="viewDetail(task)"
                                class="px-4 py-2 border border-gray-200 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-50"
                            >
                                View & Process
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <!-- Batch Upload Endorsement Modal -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-sm" @click.self="showModal = false">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden animate-in fade-in zoom-in duration-300">
            <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-[#34554a] text-white">
                <div>
                    <h3 class="text-lg font-bold">Upload Batch Endorsement</h3>
                    <p class="text-xs opacity-80">{{ displayDocWorkflowSteps[selectedStepIndex]?.label }} - {{ displayDocWorkflowSteps[selectedStepIndex]?.role_name }}</p>
                </div>
                <button @click="showModal = false" class="text-white/70 hover:text-white transition-colors">
                    <ArrowLeft class="w-5 h-5" />
                </button>
            </div>
            
            <div class="p-8">
                <!-- Name Input -->
                <div class="mb-6">
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Signatory Name</label>
                    <div class="relative">
                        <User class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                        <input 
                            v-model="docUploadForm.name"
                            type="text"
                            class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-[#34554a]/20 focus:border-[#34554a] transition-all"
                            placeholder="Enter the name of person signing"
                        />
                    </div>
                </div>

                <!-- Date Input -->
                <div class="mb-6">
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Signature Date</label>
                    <div class="relative">
                        <Calendar class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                        <input 
                            v-model="docUploadForm.date"
                            type="date"
                            class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-[#34554a]/20 focus:border-[#34554a] transition-all"
                        />
                    </div>
                </div>

                <!-- File Upload -->
                <div class="mb-6">
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Signed Document (PDF)</label>
                    <div class="relative group">
                        <input 
                            type="file" 
                            @change="handleDocFileUpload"
                            class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                            accept="application/pdf"
                        />
                        <div class="w-full py-6 border-2 border-dashed border-gray-200 rounded-xl group-hover:border-[#34554a]/30 flex flex-col items-center justify-center transition-all bg-gray-50 group-hover:bg-[#34554a]/5">
                            <Upload class="w-6 h-6 text-gray-400 mb-2" />
                            <p class="text-xs font-bold text-gray-900">
                                {{ docUploadForm.signed_document ? docUploadForm.signed_document.name : 'CLICK TO BROWSE' }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-3">
                    <button 
                        @click="submitDocUpload"
                        :disabled="isSubmitting || !docUploadForm.signed_document || !docUploadForm.name"
                        class="w-full py-3 bg-[#34554a] text-white rounded-xl text-sm font-bold hover:bg-[#2c463d] transition-all shadow-lg active:scale-95 disabled:opacity-50 disabled:active:scale-100"
                    >
                        {{ isSubmitting ? 'UPLOADING...' : 'SUBMIT ENDORSEMENT' }}
                    </button>
                    <button 
                        @click="showModal = false"
                        class="w-full py-3 bg-white text-gray-600 rounded-xl text-sm font-bold hover:bg-gray-50 transition-all border border-gray-100"
                    >
                        CANCEL
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
