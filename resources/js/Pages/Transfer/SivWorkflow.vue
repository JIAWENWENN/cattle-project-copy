<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { computed, ref } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle, ChevronRight, Download, Eye, FileSignature, Upload, Clock, RotateCcw } from 'lucide-vue-next';
import WorkflowEndorsementCards from '@/Components/WorkflowEndorsementCards.vue';

defineOptions({
    layout: (h, page) => h(AppLayout, { title: 'SIV Workflow', parent: 'Transfer', parentUrl: '/transfer/siv' }, () => page)
});

const page = usePage();
const document = computed(() => page.props.document || {});
const userRole = computed(() => page.props.auth?.user?.role || '');
const userId = computed(() => Number(page.props.auth?.user?.id || 0));
const userName = computed(() => page.props.auth?.user?.name || '');
const users = computed(() => page.props.users || []);
const transferWorkflowAssignment = computed(() => page.props.transferWorkflowAssignment || null);
const isSubmitting = ref(false);
const fileInputKey = ref(0);
const docUploadForm = ref({ name: userName.value, date: new Date().toISOString().split('T')[0], hasFile: false });
const selectedFiles = ref({});

const permissionModule = 'Transfer SIV';

const modulePermissions = computed(() => {
    const perms = page.props.auth?.permissions?.[permissionModule];
    return Array.isArray(perms) ? perms : ['no-access'];
});

const hasModulePermission = (action) => {
    if (String(userRole.value).toLowerCase() === 'admin') return true;
    const perms = modulePermissions.value;
    return perms.includes('full') || perms.includes(action);
};

const canViewModule = computed(() => hasModulePermission('view'));

const assignmentKeyByStep = ['issued_by_user_ids', 'approved_by_user_ids', 'received_by_user_ids', 'completed_by_user_ids'];

const getAssignedIdsForStep = (stepIndex) => {
    const key = assignmentKeyByStep[stepIndex];
    const ids = transferWorkflowAssignment.value?.[key];
    return Array.isArray(ids) ? ids.map((v) => Number(v)) : [];
};

const getAssignedNamesForStep = (stepIndex) => {
    const ids = getAssignedIdsForStep(stepIndex);
    if (!ids.length) return '';
    const nameMap = new Map(users.value.map((u) => [Number(u.id), u.name]));
    return ids.map((id) => nameMap.get(id)).filter(Boolean).join(', ');
};

const canUserHandleStep = (stepIndex) => {
    if (String(userRole.value).toLowerCase() === 'admin') return true;
    const assignedIds = getAssignedIdsForStep(stepIndex);
    return assignedIds.length > 0 && assignedIds.includes(userId.value);
};

const canViewWorkflowStep = (stepIndex) => {
    if (!getStepDocument(stepIndex)) return false;
    if (userRole.value === 'admin') return true;
    if (!canViewModule.value) return false;
    const assignedIds = getAssignedIdsForStep(stepIndex);
    return assignedIds.length > 0 && assignedIds.includes(userId.value);
};

const workflowSteps = computed(() => {
    return [
        { id: 'requested', label: 'Requested By', role_name: 'Sr. Assistant Livestock' },
        { id: 'verified', label: 'Verified By', role_name: 'Supervisor Livestock' },
        { id: 'approved', label: 'Approved By', role_name: 'Livestock Manager/OIC' },
        { id: 'received', label: 'Received By', role_name: '' },
    ];
});

const getStepDocument = (stepIndex) => {
    const docs = document.value?.endorsement_documents || {};
    return docs[stepIndex] || docs[String(stepIndex)] || null;
};

const getWorkflowDownloadStep = () => {
    const currentStep = Number(document.value?.endorsement_step ?? 0);
    if (Number.isNaN(currentStep)) return 0;
    return Math.max(0, Math.min(currentStep, workflowSteps.value.length - 1));
};

const allStepsUploaded = computed(() => {
    const docs = document.value?.endorsement_documents || {};
    for (let i = 0; i < workflowSteps.value.length; i++) {
        if (!docs[i] && !docs[String(i)]) return false;
    }
    return true;
});

const isCompleted = computed(() => String(document.value?.status || '').toLowerCase() === 'completed');

const canUploadStep = (stepIndex) => {
    if (document.value?.status === 'completed') return false;
    const step = workflowSteps.value[stepIndex];
    if (!step || !canUserHandleStep(stepIndex)) return false;
    const current = document.value?.endorsement_step ?? 0;
    return stepIndex <= current;
};

const canViewStep = (stepIndex) => canViewWorkflowStep(stepIndex);

const canDownloadPrevious = (stepIndex) => {
    if (stepIndex === 0) return false;
    if (!canUserHandleStep(stepIndex)) return false;
    return !!getStepDocument(stepIndex - 1);
};

const isWorkflowStepCompleted = (stepIndex) => {
    if (isCompleted.value) return true;
    return !!getStepDocument(stepIndex) || (document.value?.endorsement_step || 0) >= stepIndex + 1;
};

const resetFileInput = (stepIndex = null) => {
    if (stepIndex === null) {
        selectedFiles.value = {};
    } else {
        delete selectedFiles.value[stepIndex];
    }
    docUploadForm.value.hasFile = Object.keys(selectedFiles.value).length > 0;
    fileInputKey.value++;
};

const handleDocFileUpload = (event, stepIndex) => {
    const files = event.target.files;
    if (files && files.length > 0) {
        selectedFiles.value[stepIndex] = files[0];
    } else {
        delete selectedFiles.value[stepIndex];
    }
    docUploadForm.value.hasFile = Object.keys(selectedFiles.value).length > 0;
};

const submitDocUpload = async (stepIndex) => {
    if (isSubmitting.value) return;
    const selectedFile = selectedFiles.value[stepIndex] || null;
    if (!selectedFile) return alert('Please upload PDF file');
    if (!docUploadForm.value.name || !docUploadForm.value.date) return alert('Please fill in name and date');

    isSubmitting.value = true;
    const formData = new FormData();
    formData.append('signed_document', selectedFile);
    formData.append('name', docUploadForm.value.name);
    formData.append('date', docUploadForm.value.date);
    formData.append('step_index', stepIndex);

    router.post(`/transfer/${document.value.id}/upload-endorsement`, formData, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            resetFileInput(stepIndex);
            router.reload({ preserveScroll: true });
        },
        onError: (errors) => {
            const message = errors?.error || errors?.signed_document || errors?.name || errors?.date || 'Upload failed. Please try again.';
            alert(Array.isArray(message) ? message[0] : message);
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};

const submitEndorsementUpload = ({ stepIndex, file, name, date }) => {
    selectedFiles.value = {
        ...selectedFiles.value,
        [stepIndex]: file,
    };
    docUploadForm.value.name = name || userName.value;
    docUploadForm.value.date = date || new Date().toISOString().split('T')[0];
    submitDocUpload(stepIndex);
};

const downloadEndorsementForm = () => {
    const stepIndex = getWorkflowDownloadStep();
    const url = `/transfer/${document.value.id}/endorsement-form/${stepIndex}`;
    window.open(url, '_blank');
};

const markAsCompleted = () => {
    if (!confirm('Mark this transfer workflow as completed?')) return;
    isSubmitting.value = true;
    router.post(`/transfer/${document.value.id}/mark-completed`, {}, {
        preserveScroll: true,
        onFinish: () => {
            isSubmitting.value = false;
            router.reload({ preserveScroll: true });
        },
    });
};

const downloadStepDocument = (stepIndex) => window.open(`/transfer/${document.value.id}/download-endorsement/${stepIndex}`, '_blank');
const downloadPreviousStepDocument = (stepIndex) => window.open(`/transfer/${document.value.id}/download-endorsement/${stepIndex - 1}`, '_blank');
const reopenWorkflow = () => {
    if (!confirm('Reopen this transfer workflow?')) return;
    isSubmitting.value = true;
    router.post(`/transfer/${document.value.id}/reopen`, {}, {
        preserveScroll: true,
        onFinish: () => {
            isSubmitting.value = false;
            router.reload({ preserveScroll: true });
        },
    });
};

const goBack = () => router.visit('/transfer/siv');
</script>

<template>
    <div class="w-full">
        <div class="mb-6 flex justify-between items-center">
            <div class="flex items-center gap-4">
                <button @click="goBack" class="w-10 h-10 flex items-center justify-center rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors">
                    <ArrowLeft class="w-5 h-5 text-gray-600" />
                </button>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">SIV Workflow - {{ document.document_no || '-' }}</h1>
                    <p class="text-sm text-gray-500 mt-1">Workflow notifications and permissions follow Access Control Matrix assignments</p>
                </div>
            </div>
        </div>

        <div class="px-6 py-4 bg-gray-50 border border-gray-200 rounded-xl mb-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-xs text-gray-500 font-medium">Current Step</p>
                    <p class="text-lg font-bold text-gray-900">{{ Math.min(document.endorsement_step ?? 0, workflowSteps.length) }} / {{ workflowSteps.length }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="downloadEndorsementForm" class="flex items-center gap-2 px-3 py-1.5 text-sm text-[#34554a] border border-[#34554a] rounded-lg hover:bg-[#34554a]/10">
                        <FileSignature class="w-4 h-4" />
                        Download Form
                    </button>
                    <button
                        v-if="userRole === 'admin' && isCompleted"
                        :disabled="isSubmitting"
                        @click="reopenWorkflow"
                        class="flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-amber-700 bg-amber-100 rounded-lg hover:bg-amber-200 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <RotateCcw class="w-4 h-4" />
                        {{ isSubmitting ? 'Processing...' : 'Reopen Workflow' }}
                    </button>
                    <button
                        v-if="userRole === 'admin' && !isCompleted"
                        :disabled="!allStepsUploaded || isSubmitting"
                        @click="markAsCompleted"
                        class="flex items-center gap-2 px-3 py-1.5 text-sm text-white rounded-lg"
                        :class="allStepsUploaded && !isSubmitting ? 'bg-[#34554a] hover:bg-[#2a443b]' : 'bg-gray-300 cursor-not-allowed'"
                    >
                        <CheckCircle class="w-4 h-4" />
                        {{ isSubmitting ? 'Processing...' : 'Mark Completed' }}
                    </button>
                </div>
            </div>

            <div class="max-w-5xl mx-auto flex justify-center gap-2 flex-wrap p-4 bg-gray-50 rounded-xl">
                <template v-for="(step, index) in workflowSteps" :key="step.id">
                    <div class="flex flex-col items-center min-w-[76px]">
                        <div
                            class="w-10 h-10 rounded-full flex items-center justify-center text-sm mb-1"
                            :class="isWorkflowStepCompleted(index) ? 'bg-[#34554a] text-white' : 'bg-gray-200 text-gray-400'"
                        >
                            <CheckCircle v-if="isWorkflowStepCompleted(index)" class="w-5 h-5" />
                            <span v-else>{{ index + 1 }}</span>
                        </div>
                        <span
                            class="text-[10px] text-center"
                            :class="isWorkflowStepCompleted(index) ? 'text-[#34554a] font-semibold' : 'text-gray-400'"
                        >{{ step.label }}</span>
                    </div>
                    <div
                        v-if="index < workflowSteps.length - 1"
                        class="pb-5 flex items-center"
                        :class="isWorkflowStepCompleted(index) ? 'text-[#34554a]' : 'text-gray-300'"
                    >
                        <ChevronRight class="w-4 h-4" />
                    </div>
                </template>
            </div>
        </div>

        <WorkflowEndorsementCards
            :steps="workflowSteps"
            :get-step-document="getStepDocument"
            :can-upload-step="canUploadStep"
            :can-view-step="canViewStep"
            :can-download-previous="canDownloadPrevious"
            :is-submitting="isSubmitting"
            :user-name="userName"
            @upload="submitEndorsementUpload"
            @view="downloadStepDocument"
            @previous="downloadPreviousStepDocument"
        />
    </div>
</template>
