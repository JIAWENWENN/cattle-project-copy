<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import {
    ArrowLeft, CheckCircle, Clock, ChevronRight, Eye,
    Download, FileSignature, RotateCcw
} from 'lucide-vue-next';
import WorkflowEndorsementCards from '@/Components/WorkflowEndorsementCards.vue';

defineOptions({
    layout: (h, page) => h(AppLayout, { title: 'Mortality Workflow', parent: 'Mortality', parentUrl: '/mortality/history' }, () => page)
});

const props = defineProps({
    case: {
        type: Object,
        required: true,
    },
    mortalityWorkflowAssignment: {
        type: Object,
        default: null,
    },
    users: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const caseData = computed(() => props.case);
const userRole = computed(() => page.props.auth?.user?.role || '');
const userId = computed(() => Number(page.props.auth?.user?.id || 0));
const userName = computed(() => page.props.auth?.user?.name || '');
const isAdmin = computed(() => String(userRole.value).toLowerCase() === 'admin');

const mortalityPermissions = computed(() => {
    const perms = page.props.auth?.permissions?.['Mortality Records'];
    return Array.isArray(perms) ? perms : ['no-access'];
});

const hasViewPermission = computed(() => {
    if (String(userRole.value).toLowerCase() === 'admin') return true;
    const perms = mortalityPermissions.value;
    return perms.includes('full') || perms.includes('view');
});
const isSubmitting = ref(false);
const fileInputKey = ref(0);

const docUploadForm = ref({
    name: userName.value || '',
    date: new Date().toISOString().split('T')[0],
    hasFile: false,
});

const workflowSteps = [
    { id: 'issued', label: 'Issued by', role_name: 'Sr. Assistant Livestock' },
    { id: 'verified', label: 'Verified by', role_name: 'Sr. Assistant Security' },
    { id: 'checked', label: 'Checked by', role_name: 'Supervisor Livestock' },
    { id: 'witnessed', label: 'Witness by', role_name: 'Estate Management' },
    { id: 'approved', label: 'Approved by', role_name: 'Livestock Manager/OIC' },
];

const assignmentKeyByStep = [
    'issued_by_user_ids',
    'verified_by_user_ids',
    'checked_by_user_ids',
    'witnessed_by_user_ids',
    'approved_by_user_ids',
];

const stepStatuses = ['pending', 'issued', 'verified', 'checked', 'witness', 'approved', 'completed'];

const getAssignedIdsForStep = (stepIndex) => {
    const key = assignmentKeyByStep[stepIndex];
    const ids = props.mortalityWorkflowAssignment?.[key];
    return Array.isArray(ids) ? ids.map((v) => Number(v)) : [];
};

const getCurrentStepIndex = (record) => {
    if (record?.endorsement_step !== undefined && record?.endorsement_step !== null) {
        return Number(record.endorsement_step);
    }
    const index = stepStatuses.indexOf(record?.status || 'pending');
    return index >= 0 ? index : 0;
};

const getStatusStepIndex = (status) => {
    if (status === 'completed') return 6;
    const index = stepStatuses.indexOf(status);
    return index >= 0 ? index : 0;
};

const getStepDocumentForRecord = (record, stepIndex) => {
    if (!record?.endorsement_documents) return null;
    const docs = record.endorsement_documents;
    return docs[stepIndex] || docs[String(stepIndex)] || null;
};

const canUserActStep = (stepIndex) => {
    if (isAdmin.value) return true;
    const assignedIds = getAssignedIdsForStep(stepIndex);
    return assignedIds.length > 0 && assignedIds.includes(userId.value);
};

const hasStepDocument = (record, stepIndex) => !!getStepDocumentForRecord(record, stepIndex);

const isStepCompleted = (record, stepIndex) => {
    if (!record) return false;
    if (isRecordCompleted(record)) return true;
    return hasStepDocument(record, stepIndex);
};

const canUploadStepForRecord = (record, stepIndex) => {
    if (!record) return false;
    if (record.status === 'approved' || record.status === 'rejected' || record.status === 'completed') return false;

    const currentStep = record.endorsement_step ?? 0;
    if (!canUserActStep(stepIndex)) return false;

    if (stepIndex === 4) return stepIndex <= currentStep;

    if (currentStep === stepIndex) return true;
    if (stepIndex < currentStep) {
        const nextStepDoc = getStepDocumentForRecord(record, stepIndex + 1);
        return !nextStepDoc;
    }
    return false;
};

const canViewStepForRecord = (record, stepIndex) => {
    const stepDoc = getStepDocumentForRecord(record, stepIndex);
    if (!stepDoc) return false;
    if (isAdmin.value) return true;
    if (!hasViewPermission.value) return false;
    const assignedIds = getAssignedIdsForStep(stepIndex);
    return assignedIds.length > 0 && assignedIds.includes(userId.value);
};

const canDownloadPreviousForRecord = (record, stepIndex) => {
    if (stepIndex === 0) return false;
    const hasPreviousDoc = !!getStepDocumentForRecord(record, stepIndex - 1);
    if (!hasPreviousDoc) return false;

    if (isAdmin.value) {
        return true;
    }

    // Non-admin users can only download previous on their own assigned step.
    return canUserActStep(stepIndex);
};

const downloadStepDocumentForRecord = (record, stepIndex) => {
    window.open(`/mortality/${record.id}/download-endorsement/${stepIndex}`, '_blank');
};

const downloadPreviousStepDocumentForRecord = (record, stepIndex) => {
    if (stepIndex > 0) {
        window.open(`/mortality/${record.id}/download-endorsement/${stepIndex - 1}`, '_blank');
    }
};

const handleDocFileUpload = (event) => {
    const files = event.target.files;
    docUploadForm.value.hasFile = !!(files && files.length > 0);
};

const resetFileInput = () => {
    docUploadForm.value.hasFile = false;
    fileInputKey.value++;
};

const submitDocUploadForRecord = async (record, stepIndex) => {
    if (isSubmitting.value) return;

    const fileInput = document.querySelector(`#file-input-${stepIndex}`);
    if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
        alert('Please select a PDF file to upload');
        return;
    }

    if (!docUploadForm.value.name || !docUploadForm.value.date) {
        alert('Please fill in all fields');
        return;
    }

    isSubmitting.value = true;

    const formData = new FormData();
    formData.append('signed_document', fileInput.files[0]);
    formData.append('name', docUploadForm.value.name);
    formData.append('date', docUploadForm.value.date);
    formData.append('step_index', String(stepIndex));

    router.post(`/mortality/${record.id}/upload-endorsement`, formData, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            resetFileInput();
            docUploadForm.value.name = userName.value;
            docUploadForm.value.date = new Date().toISOString().split('T')[0];
            router.reload({ preserveScroll: true });
        },
        onError: (errors) => {
            const message = errors?.signed_document
                || errors?.error
                || errors?.message
                || 'Failed to upload document. Please try again.';
            alert(Array.isArray(message) ? message[0] : message);
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};

const submitEndorsementUpload = ({ stepIndex, file, name, date }) => {
    if (isSubmitting.value) return;
    if (!file) {
        alert('Please select a PDF file to upload');
        return;
    }
    if (!caseData.value?.id) {
        alert('Error: No record ID found');
        return;
    }

    isSubmitting.value = true;

    const formData = new FormData();
    formData.append('signed_document', file);
    formData.append('name', name || userName.value || '');
    formData.append('date', date || new Date().toISOString().split('T')[0]);
    formData.append('step_index', String(stepIndex));

    router.post(`/mortality/${caseData.value.id}/upload-endorsement`, formData, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            resetFileInput();
            router.reload({ preserveScroll: true });
        },
        onError: (errors) => {
            const message = errors?.signed_document
                || errors?.error
                || errors?.message
                || 'Failed to upload document. Please try again.';
            alert(Array.isArray(message) ? message[0] : message);
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};

const allStepsUploaded = (record) => {
    if (!record?.endorsement_documents) return false;
    const docs = record.endorsement_documents;
    for (let i = 0; i < 5; i++) {
        if (!docs[i] && !docs[String(i)]) return false;
    }
    return true;
};

const isRecordCompleted = (record) => record?.status === 'completed';

const markAsCompleted = async (record) => {
    if (!isAdmin.value || !allStepsUploaded(record) || isRecordCompleted(record)) return;

    if (!confirm('Are you sure you want to mark this record as completed? This will lock all uploads and cannot be undone.')) {
        return;
    }

    isSubmitting.value = true;

    router.post(`/mortality/${record.id}/mark-completed`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            router.reload({ preserveScroll: true });
        },
        onError: (errors) => {
            const message = errors?.error || errors?.message || 'Failed to mark as completed. Please try again.';
            alert(Array.isArray(message) ? message[0] : message);
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};

const reopenRecord = (record) => {
    if (!isAdmin.value || !isRecordCompleted(record)) return;

    if (!confirm('Are you sure you want to reopen this record?')) {
        return;
    }

    isSubmitting.value = true;

    router.post(`/mortality/${record.id}/reopen`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            router.reload({ preserveScroll: true });
        },
        onError: (errors) => {
            const message = errors?.error || errors?.message || 'Failed to reopen record. Please try again.';
            alert(Array.isArray(message) ? message[0] : message);
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};

const getWorkflowDownloadStep = (record) => {
    const step = getCurrentStepIndex(record);
    return Math.max(0, Math.min(step, workflowSteps.length - 1));
};

const downloadEndorsementForm = (record) => {
    window.open(`/mortality/${record.id}/endorsement-form`, '_blank');
};

const goBack = () => {
    window.history.back();
};
</script>

<template>
    <div class="w-full max-w-7xl mx-auto">
        <div class="mb-6 flex justify-between items-center">
            <div class="flex items-center gap-4">
                <button @click="goBack" class="w-10 h-10 flex items-center justify-center rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors">
                    <ArrowLeft class="w-5 h-5 text-gray-600" />
                </button>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Mortality Record Workflow - {{ caseData?.cattle?.tag_no || '-' }}</h1>
                </div>
            </div>
        </div>

        <div class="px-6 py-4 bg-gray-50 border border-gray-200 rounded-xl mb-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-xs text-gray-500 font-medium">Current Step</p>
                    <p class="text-lg font-bold text-gray-900">{{ Math.min(caseData?.endorsement_step ?? 0, workflowSteps.length) }} / {{ workflowSteps.length }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        @click="downloadEndorsementForm(caseData)"
                        class="flex items-center gap-2 px-3 py-1.5 text-sm text-[#34554a] border border-[#34554a] rounded-lg hover:bg-[#34554a]/10"
                    >
                        <FileSignature class="w-4 h-4" />
                        Download Form
                    </button>
                    <button
                        v-if="isAdmin && !isRecordCompleted(caseData)"
                        :disabled="!allStepsUploaded(caseData) || isSubmitting"
                        @click="markAsCompleted(caseData)"
                        class="flex items-center gap-2 px-3 py-1.5 text-sm text-white rounded-lg"
                        :class="allStepsUploaded(caseData) && !isSubmitting ? 'bg-[#34554a] hover:bg-[#2a443b]' : 'bg-gray-300 cursor-not-allowed'"
                    >
                        <CheckCircle class="w-4 h-4" />
                        {{ isSubmitting ? 'Processing...' : 'Mark Completed' }}
                    </button>
                    <button
                        v-if="isAdmin && isRecordCompleted(caseData)"
                        :disabled="isSubmitting"
                        @click="reopenRecord(caseData)"
                        class="flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-amber-700 bg-amber-100 rounded-lg hover:bg-amber-200 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <RotateCcw class="w-4 h-4" />
                        {{ isSubmitting ? 'Processing...' : 'Reopen' }}
                    </button>
                </div>
            </div>

            <div class="max-w-xl mx-auto flex justify-center gap-2 flex-wrap p-4 bg-gray-50 rounded-xl">
                <template v-for="(step, index) in workflowSteps" :key="step.id">
                    <div class="flex flex-col items-center min-w-[70px]">
                        <div
                            class="w-10 h-10 rounded-full flex items-center justify-center text-sm mb-1"
                            :class="isStepCompleted(caseData, index) ? 'bg-[#34554a] text-white' : 'bg-gray-200 text-gray-400'"
                        >
                            <CheckCircle v-if="isStepCompleted(caseData, index)" class="w-5 h-5" />
                            <span v-else>{{ index + 1 }}</span>
                        </div>
                        <span
                            class="text-[10px] text-center"
                            :class="isStepCompleted(caseData, index) ? 'text-[#34554a] font-semibold' : 'text-gray-400'"
                        >{{ step.label }}</span>
                    </div>
                    <div v-if="index < workflowSteps.length - 1" class="pb-5 flex items-center" :class="isStepCompleted(caseData, index) ? 'text-[#34554a]' : 'text-gray-300'">
                        <ChevronRight class="w-4 h-4" />
                    </div>
                </template>
            </div>
        </div>

        <WorkflowEndorsementCards
            :steps="workflowSteps"
            :get-step-document="(index) => getStepDocumentForRecord(caseData, index)"
            :can-upload-step="(index) => canUploadStepForRecord(caseData, index)"
            :can-view-step="(index) => canViewStepForRecord(caseData, index)"
            :can-download-previous="(index) => canDownloadPreviousForRecord(caseData, index)"
            :is-submitting="isSubmitting"
            :user-name="userName"
            grid-class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4"
            @upload="submitEndorsementUpload"
            @view="(index) => downloadStepDocumentForRecord(caseData, index)"
            @previous="(index) => downloadPreviousStepDocumentForRecord(caseData, index)"
        />

        <div
            v-if="!isRecordCompleted(caseData) && !(allStepsUploaded(caseData) && isAdmin)"
            class="mt-4 p-4 rounded-lg border-2"
            :class="{
                'bg-amber-50 border-amber-500': allStepsUploaded(caseData),
                'bg-gray-50 border-gray-200': !allStepsUploaded(caseData)
            }"
        >
            <div v-if="allStepsUploaded(caseData) && !isAdmin" class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center">
                    <Clock class="w-5 h-5 text-white" />
                </div>
                <div>
                    <p class="font-bold text-blue-800">Awaiting Admin Approval</p>
                    <p class="text-sm text-blue-600">All endorsements uploaded. Awaiting admin to mark as completed.</p>
                </div>
            </div>

            <div v-else class="flex items-center gap-3">
                <div class="w-10 h-10 bg-gray-400 rounded-full flex items-center justify-center">
                    <Clock class="w-5 h-5 text-white" />
                </div>
                <div>
                    <p class="font-bold text-gray-700">In Progress</p>
                    <p class="text-sm text-gray-500">Waiting for all endorsement steps to be completed.</p>
                </div>
            </div>
        </div>
    </div>
</template>
