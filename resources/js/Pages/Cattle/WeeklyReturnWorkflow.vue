<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { computed, ref } from 'vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle, Clock, ChevronRight, Download, Eye, FileSignature, Lock, RotateCcw } from 'lucide-vue-next';
import WorkflowEndorsementCards from '@/Components/WorkflowEndorsementCards.vue';

const props = defineProps({
    workflow: { type: Object, default: null },
    workflowSteps: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    meta: { type: Object, default: () => ({}) },
    canMarkCompleted: { type: Boolean, default: false },
    canReopenWorkflow: { type: Boolean, default: false },
    workflowCompletedAt: { type: String, default: null },
});

const page = usePage();
const userRole = computed(() => page.props.auth?.user?.role || '');
const userName = computed(() => page.props.auth?.user?.name || '');
const isSubmitting = ref(false);
const fileInputKey = ref(0);
const selectedFiles = ref({});

const normalizeRole = (role) => {
    const r = String(role || '').toLowerCase().replace(/[._-]/g, ' ').replace(/\s+/g, ' ').trim();
    return r;
};

const canUserHandleStep = (stepIndex) => {
    if (normalizeRole(userRole.value) === 'admin') return true;
    return !!props.workflowSteps?.[stepIndex]?.can_handle;
};

const getStepDocument = (stepIndex) => {
    const docs = props.workflow?.endorsement_documents || {};
    return docs[stepIndex] || docs[String(stepIndex)] || null;
};

const stepStatuses = ['pending', 'prepared', 'verified', 'checked', 'approved', 'completed'];

const getStatusStepIndex = (status) => {
    if (props.workflow?.is_completed || status === 'completed') return props.workflowSteps.length;
    const idx = stepStatuses.indexOf(status || 'pending');
    return idx >= 0 ? idx : 0;
};

const currentStepIndex = computed(() => Number(props.workflow?.endorsement_step ?? 0));

const allStepsUploaded = computed(() => {
    if (!props.workflowSteps.length) return false;
    return props.workflowSteps.every((_, index) => !!getStepDocument(index));
});

const isWorkflowCompleted = computed(() => !!props.workflow?.is_completed || props.workflow?.status === 'completed');
const currentDisplayStep = computed(() => {
    if (isWorkflowCompleted.value) {
        return props.workflowSteps.length;
    }

    if (allStepsUploaded.value) {
        return props.workflowSteps.length;
    }

    return Math.min(currentStepIndex.value, props.workflowSteps.length);
});

const canUploadStep = (stepIndex) => {
    if (!props.workflow || isWorkflowCompleted.value) return false;
    if (!canUserHandleStep(stepIndex)) return false;
    if (normalizeRole(userRole.value) === 'admin') return true;
    return stepIndex <= currentStepIndex.value;
};

const docUploadForm = useForm({
    from: props.filters?.from || '',
    to: props.filters?.to || '',
    unit: props.filters?.unit || '',
    step_index: 0,
    name: userName.value,
    date: new Date().toISOString().split('T')[0],
    signed_document: null,
});

const handleFileUpload = (event, stepIndex) => {
    const file = event.target.files[0];
    if (!file) return;
    selectedFiles.value[stepIndex] = file;
};

const submitUpload = (stepIndex) => {
    const file = selectedFiles.value[stepIndex];
    if (!file || isSubmitting.value) return;

    isSubmitting.value = true;
    docUploadForm.from = props.filters?.from;
    docUploadForm.to = props.filters?.to;
    docUploadForm.unit = props.filters?.unit;
    docUploadForm.step_index = stepIndex;
    docUploadForm.signed_document = file;

    docUploadForm.post(route('cattle.weekly-return.upload-endorsement'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            selectedFiles.value[stepIndex] = null;
            fileInputKey.value++;
        },
        onFinish: () => {
            isSubmitting.value = false;
            docUploadForm.signed_document = null;
        },
    });
};

const canViewStep = (stepIndex) => !!getStepDocument(stepIndex);

const canDownloadPrevious = (stepIndex) => stepIndex > 0 && !!getStepDocument(stepIndex - 1);

const downloadPreviousStepDocument = (stepIndex) => {
    if (stepIndex <= 0) return;
    downloadStepDocument(stepIndex - 1);
};

const submitEndorsementUpload = ({ stepIndex, file, name, date }) => {
    selectedFiles.value = {
        ...selectedFiles.value,
        [stepIndex]: file,
    };
    docUploadForm.name = name || userName.value;
    docUploadForm.date = date || new Date().toISOString().split('T')[0];
    submitUpload(stepIndex);
};

const downloadStepDocument = (stepIndex) => {
    const query = new URLSearchParams({
        from: props.filters?.from || '',
        to: props.filters?.to || '',
        unit: props.filters?.unit || '',
    }).toString();

    window.open(`${route('cattle.weekly-return.download-endorsement', stepIndex)}?${query}`, '_blank');
};

const downloadEndorsementForm = () => {
    const query = new URLSearchParams({
        from: props.filters?.from || '',
        to: props.filters?.to || '',
        unit: props.filters?.unit || '',
    }).toString();

    window.open(`${route('cattle.weekly-return.endorsement-form')}?${query}`, '_blank');
};

const markCompleted = () => {
    if (!allStepsUploaded.value || isSubmitting.value) return;
    router.post(route('cattle.weekly-return.mark-completed'), {
        from: props.filters?.from,
        to: props.filters?.to,
        unit: props.filters?.unit,
    }, { preserveScroll: true });
};

const reopenWorkflow = () => {
    if (!props.canReopenWorkflow || !isWorkflowCompleted.value || isSubmitting.value) return;
    router.post(route('cattle.weekly-return.reopen'), {
        from: props.filters?.from,
        to: props.filters?.to,
        unit: props.filters?.unit,
    }, { preserveScroll: true });
};

const backToReport = () => {
    router.get(route('cattle.weekly-return'), {
        mode: props.filters?.mode,
        month: props.filters?.month,
        week: props.filters?.week,
        from: props.filters?.from,
        to: props.filters?.to,
        unit: props.filters?.unit,
    });
};
</script>

<template>
    <Head title="Weekly Return Workflow" />

    <AppLayout title="Weekly Return Workflow" parent="Cattle" :parentUrl="route('cattle.weekly-return')">
        <div class="mb-6 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <button @click="backToReport" class="w-10 h-10 flex items-center justify-center rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors">
                    <ArrowLeft class="w-5 h-5 text-gray-600" />
                </button>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Weekly Return Workflow - {{ filters.unit || '-' }}</h1>
                    <p class="text-sm text-gray-500 mt-1">{{ meta.submissionLabel }}</p>
                </div>
            </div>
        </div>

        <div class="px-6 py-4 bg-gray-50 border border-gray-200 rounded-xl mb-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-xs text-gray-500 font-medium">Current Step</p>
                    <p class="text-lg font-bold text-gray-900">{{ currentDisplayStep }} / {{ workflowSteps.length }}</p>
                </div>
                <div class="flex gap-2">
                    <button
                        @click="downloadEndorsementForm"
                        class="flex items-center gap-2 px-3 py-1.5 text-sm text-[#34554a] border-2 border-[#34554a] hover:bg-[#34554a] hover:text-white rounded-lg font-medium transition-colors"
                    >
                        <Download class="w-4 h-4" />
                        Download Form
                    </button>
                    <button
                        v-if="canMarkCompleted && !isWorkflowCompleted"
                        :disabled="!allStepsUploaded || isSubmitting"
                        @click="markCompleted"
                        class="flex items-center gap-2 px-3 py-1.5 text-sm text-white rounded-lg"
                        :class="allStepsUploaded && !isSubmitting ? 'bg-[#34554a] hover:bg-[#2a443b]' : 'bg-gray-300 cursor-not-allowed'"
                    >
                        <CheckCircle class="w-4 h-4" />
                        Mark Completed
                    </button>
                    <button
                        v-if="canReopenWorkflow && isWorkflowCompleted"
                        :disabled="isSubmitting"
                        @click="reopenWorkflow"
                        class="flex items-center gap-2 px-3 py-1.5 text-sm text-white rounded-lg bg-amber-600 hover:bg-amber-700"
                    >
                        <RotateCcw class="w-4 h-4" />
                        Reopen
                    </button>
                </div>
            </div>

            <div v-if="isWorkflowCompleted" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 flex items-center gap-2">
                <Lock class="w-4 h-4 flex-shrink-0" />
                <span>Completed weekly workflow{{ workflowCompletedAt ? ` on ${workflowCompletedAt}` : '' }}. Reopen to continue workflow actions.</span>
            </div>

            <div class="max-w-xl mx-auto flex justify-center gap-2 flex-wrap p-4 bg-gray-50 rounded-xl">
                <template v-for="(step, index) in workflowSteps" :key="`progress-${index}`">
                    <div class="flex flex-col items-center min-w-[70px]">
                        <div
                            class="w-10 h-10 rounded-full flex items-center justify-center text-sm mb-1"
                            :class="isWorkflowCompleted || getStatusStepIndex(workflow?.status) >= index + 1 || getStepDocument(index) ? 'bg-[#34554a] text-white' : 'bg-gray-200 text-gray-400'"
                        >
                            <CheckCircle v-if="isWorkflowCompleted || getStatusStepIndex(workflow?.status) >= index + 1 || getStepDocument(index)" class="w-5 h-5" />
                            <span v-else>{{ index + 1 }}</span>
                        </div>
                        <span
                            class="text-[10px] text-center"
                            :class="isWorkflowCompleted || getStatusStepIndex(workflow?.status) >= index + 1 || getStepDocument(index) ? 'text-[#34554a] font-semibold' : 'text-gray-400'"
                        >{{ step.label }}</span>
                    </div>
                    <div v-if="index < workflowSteps.length - 1" class="pb-5 flex items-center" :class="isWorkflowCompleted || getStatusStepIndex(workflow?.status) >= index + 1 || getStepDocument(index) ? 'text-[#34554a]' : 'text-gray-300'">
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
            grid-class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4"
            @upload="submitEndorsementUpload"
            @view="downloadStepDocument"
            @previous="downloadPreviousStepDocument"
        />

            <div
                v-if="!isWorkflowCompleted && !(allStepsUploaded && canMarkCompleted)"
                class="mt-4 p-4 rounded-lg border-2"
                :class="allStepsUploaded ? 'bg-amber-50 border-amber-500' : 'bg-gray-50 border-gray-200'"
            >
                <div v-if="allStepsUploaded && !canMarkCompleted" class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center">
                        <Clock class="w-5 h-5 text-white" />
                    </div>
                    <div>
                        <p class="font-bold text-blue-800">Ready for Completion</p>
                        <p class="text-sm text-blue-600">All endorsements are uploaded. Admin can mark this workflow as completed.</p>
                    </div>
                </div>

                <div v-else-if="!allStepsUploaded" class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-gray-400 rounded-full flex items-center justify-center">
                        <Clock class="w-5 h-5 text-white" />
                    </div>
                    <div>
                        <p class="font-bold text-gray-700">In Progress</p>
                        <p class="text-sm text-gray-500">Waiting for all endorsement steps to be completed.</p>
                    </div>
                </div>
            </div>
    </AppLayout>
</template>
