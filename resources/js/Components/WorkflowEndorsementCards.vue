<script setup>
import { ref } from 'vue';
import { CheckCircle, Download, Eye, Trash2, Upload } from 'lucide-vue-next';

const props = defineProps({
    title: { type: String, default: 'Document Endorsement' },
    steps: { type: Array, default: () => [] },
    gridClass: { type: String, default: 'grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4' },
    userName: { type: String, default: '' },
    isSubmitting: { type: Boolean, default: false },
    getStepDocument: { type: Function, required: true },
    canUploadStep: { type: Function, default: () => false },
    canViewStep: { type: Function, default: null },
    canDownloadPrevious: { type: Function, default: () => false },
    canDeleteStep: { type: Function, default: () => false },
});

const emit = defineEmits(['upload', 'view', 'previous', 'delete']);

const selectedFiles = ref({});
const fileInputKey = ref(0);

const today = () => new Date().toISOString().split('T')[0];

const stepDocument = (stepIndex) => props.getStepDocument(stepIndex);

const canView = (stepIndex) => {
    if (props.canViewStep) return props.canViewStep(stepIndex);
    return !!stepDocument(stepIndex);
};

const documentName = (stepIndex) => {
    const doc = stepDocument(stepIndex);
    return doc?.name || doc?.filename || doc?.file_name || 'Document uploaded';
};

const documentDate = (stepIndex) => {
    const doc = stepDocument(stepIndex);
    return doc?.date || doc?.uploaded_at || '-';
};

const onFileChange = (event, stepIndex) => {
    const file = event.target.files?.[0] || null;
    selectedFiles.value = {
        ...selectedFiles.value,
        [stepIndex]: file,
    };
};

const submitUpload = (stepIndex) => {
    const file = selectedFiles.value[stepIndex];
    if (!file || props.isSubmitting) return;

    emit('upload', {
        stepIndex,
        file,
        name: props.userName || '',
        date: today(),
    });
};

const resetFileInput = (stepIndex) => {
    selectedFiles.value = {
        ...selectedFiles.value,
        [stepIndex]: null,
    };
    fileInputKey.value++;
};

defineExpose({ resetFileInput });
</script>

<template>
    <div class="bg-gray-50 rounded-xl p-5 mt-6 border border-gray-200">
        <h4 class="text-sm font-bold text-gray-800 mb-4 flex items-center gap-2">
            <CheckCircle class="w-4 h-4 text-[#34554a]" />
            {{ title }}
        </h4>

        <div :class="gridClass">
            <div
                v-for="(step, index) in steps"
                :key="step.id || step.field || index"
                class="bg-white rounded-lg border border-gray-200 p-4"
            >
                <p class="text-xs font-bold text-gray-700 mb-1">{{ step.label }}</p>
                <p v-if="step.role_name" class="text-[10px] text-gray-500 mb-3">{{ step.role_name }}</p>

                <div v-if="stepDocument(index)" class="space-y-2 mb-3">
                    <p class="text-xs text-gray-600 truncate">{{ documentName(index) }}</p>
                    <p class="text-xs text-gray-400">{{ documentDate(index) }}</p>
                    <button
                        v-if="canView(index)"
                        type="button"
                        class="flex items-center gap-1 text-xs text-[#34554a] hover:underline"
                        @click="emit('view', index)"
                    >
                        <Eye class="w-3 h-3" />
                        View PDF
                    </button>
                </div>

                <button
                    v-if="canDeleteStep(index)"
                    type="button"
                    class="mb-3 flex items-center gap-1 text-xs text-red-600 hover:underline"
                    @click="emit('delete', index)"
                >
                    <Trash2 class="w-3 h-3" />
                    Delete Upload
                </button>

                <div v-if="canUploadStep(index)" class="space-y-2">
                    <input
                        :key="`${fileInputKey}-${index}`"
                        type="file"
                        accept=".pdf,application/pdf"
                        class="text-xs w-full"
                        @change="onFileChange($event, index)"
                    >
                    <button
                        type="button"
                        class="w-full flex items-center justify-center gap-2 px-3 py-2 bg-[#34554a] text-white rounded text-xs font-medium hover:bg-[#2a443b] disabled:opacity-50"
                        :disabled="isSubmitting || !selectedFiles[index]"
                        @click="submitUpload(index)"
                    >
                        <Upload class="w-4 h-4" />
                        {{ stepDocument(index) ? 'Re-upload' : 'Upload' }}
                    </button>
                </div>

                <button
                    v-if="canDownloadPrevious(index)"
                    type="button"
                    class="mt-2 flex items-center gap-1 text-xs text-gray-600 hover:underline"
                    @click="emit('previous', index)"
                >
                    <Download class="w-3 h-3" />
                    Previous step PDF
                </button>
            </div>
        </div>
    </div>
</template>
