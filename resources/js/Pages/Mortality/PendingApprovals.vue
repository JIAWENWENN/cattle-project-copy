<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, watch } from 'vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import { 
    Clock, Eye, Calendar, CheckCircle, 
    FileSignature, Shield, UserCheck, ClipboardCheck,
    ChevronRight, ChevronLeft, Microscope, Download, FileText, RotateCcw, AlertTriangle, Upload, Trash2, Plus, Edit, Search, FilterX
} from 'lucide-vue-next';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';

defineOptions({ 
    layout: (h, page) => h(AppLayout, { title: 'Mortality History', parent: 'Mortality', parentUrl: '/mortality/records' }, () => page)
});

const page = usePage();
const openMenus = ref(['mortality']);

const userRole = computed(() => page.props.auth?.user?.role || '');
const userId = computed(() => Number(page.props.auth?.user?.id || 0));
const userName = computed(() => page.props.auth?.user?.name || '');
const userRoleLabel = computed(() => page.props.auth?.user?.role || '');
const isAdmin = computed(() => String(userRole.value).toLowerCase() === 'admin');
const mortalityWorkflowAssignment = computed(() => page.props.mortalityWorkflowAssignment || null);
const workflowUsers = computed(() => page.props.users || []);

const assignmentKeyByStep = [
    'issued_by_user_ids',
    'verified_by_user_ids',
    'checked_by_user_ids',
    'witnessed_by_user_ids',
    'approved_by_user_ids',
];

const getAssignedIdsForStep = (stepIndex) => {
    const key = assignmentKeyByStep[stepIndex];
    const ids = mortalityWorkflowAssignment.value?.[key];
    return Array.isArray(ids) ? ids.map((v) => Number(v)) : [];
};

const mortalityPermissions = computed(() => {
    const perms = page.props.auth?.permissions?.['Mortality Records'];
    return Array.isArray(perms) ? perms : ['no-access'];
});
const hasMortalityPermission = (action) => {
    if (String(userRole.value).toLowerCase() === 'admin') return true;
    const perms = mortalityPermissions.value;
    return perms.includes('full') || perms.includes(action);
};
const canViewMortality = computed(() => hasMortalityPermission('view'));
const canEditMortality = computed(() => hasMortalityPermission('edit'));
const canDeleteMortality = computed(() => hasMortalityPermission('delete'));
const canCreateMortality = computed(() => hasMortalityPermission('create'));

const canUserActStep = (stepIndex) => {
    if (isAdmin.value) return true;
    const assignedIds = getAssignedIdsForStep(stepIndex);
    return assignedIds.length > 0 && assignedIds.includes(userId.value);
};

const getUserWorkflowStepIndex = () => {
    if (isAdmin.value) return null;
    for (let i = 0; i < docWorkflowSteps.length; i++) {
        if (canUserActStep(i)) return i;
    }
    return null;
};

const showModal = ref(false);
const selectedCase = ref(null);
const searchQuery = ref(page.props.filters?.search || '');
const selectedMonth = ref(page.props.filters?.month || '');
const selectedYear = ref(page.props.filters?.year || '');
const selectedUnit = ref(page.props.filters?.unit || '');
const isSubmitting = ref(false);
const docUploadForm = ref({
    name: '',
    date: '',
    signed_document: null,
});

const monthOptions = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sept', 'Oct', 'Nov', 'Dec'];
const years = computed(() => page.props.availableYears || []);
const operatingUnits = computed(() => page.props.operatingUnits || []);

// Document workflow steps (for endorsement)
const docWorkflowSteps = [
    { role: 'livestock', label: 'Issued by', field: 'issued', role_name: 'Sr. Assistant Livestock' },
    { role: 'security', label: 'Verified by', field: 'verified', role_name: 'Sr. Assistant Security' },
    { role: 'supervisor', label: 'Checked by', field: 'checked', role_name: 'Supervisor Livestock' },
    { role: 'penyelia', label: 'Witnessed by', field: 'witnessed', role_name: 'Penyelia Security' },
    { role: 'manager', label: 'Approved by', field: 'approved', role_name: 'Livestock Manager/OIC' },
];

const workflow = [
    { id: 'issued', label: 'Issued', role: 'livestock', icon: FileSignature, role_name: 'Sr. Assistant Livestock' },
    { id: 'pm_examination', label: 'PM Exam', role: 'livestock', icon: Microscope, role_name: 'Sr. Assistant Livestock' },
    { id: 'verified', label: 'Verified', role: 'security', icon: UserCheck, role_name: 'Sr. Assistant Security' },
    { id: 'checked', label: 'Checked', role: 'supervisor', icon: ClipboardCheck, role_name: 'Supervisor Livestock' },
    { id: 'witness', label: 'Witness', role: 'penyelia', icon: Shield, role_name: 'Penyelia Security' },
    { id: 'approved', label: 'Approved', role: 'manager', icon: CheckCircle, role_name: 'Livestock Manager / OIC' }
];

const workflowRoles = {
    'livestock': 'Sr. Assistant Livestock',
    'security': 'Sr. Assistant Security',
    'supervisor': 'Supervisor Livestock',
    'penyelia': 'Penyelia Security',
    'manager': 'Livestock Manager / OIC',
    'admin': 'Administrator'
};

const formatDateValue = (dateString) => {
    if (!dateString) return '-';
    const raw = String(dateString).trim();

    // Handles date strings from DB like YYYY-MM-DD without timezone shifts
    const ymd = raw.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (ymd) {
        return `${ymd[3]}/${ymd[2]}/${ymd[1]}`;
    }

    const date = new Date(raw);
    if (Number.isNaN(date.getTime())) return raw;
    return date.toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric' });
};

const formatTimeValue = (timeValue) => {
    if (!timeValue) return '-';
    const raw = String(timeValue).trim();

    // Handles datetime strings like 2026-03-31T10:15:22.000000Z
    const isoTime = raw.match(/T(\d{2}):(\d{2})(?::(\d{2}))?/);
    if (isoTime) {
        return `${isoTime[1]}:${isoTime[2]}:${(isoTime[3] || '00').padStart(2, '0')}`;
    }

    const match = raw.match(/^(\d{1,2}):(\d{2})(?::(\d{2}))?/);
    if (match) {
        const hh = match[1].padStart(2, '0');
        const mm = match[2];
        const ss = (match[3] || '00').padStart(2, '0');
        return `${hh}:${mm}:${ss}`;
    }
    const date = new Date(raw);
    if (!Number.isNaN(date.getTime())) {
        return date.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
    }
    return raw;
};

// Define getStepIndex before it's used in mortalityCases
const getStepIndex = (step) => {
    const steps = ['issued', 'verified', 'checked', 'witness', 'approved'];
    const index = steps.indexOf(step);
    return index >= 0 ? index : 0;
};

const mortalityCases = computed(() => {
    const casesData = page.props.cases;
    const cases = Array.isArray(casesData) ? casesData : (casesData?.data || []);
    return cases.map(c => ({
        id: c.id,
        lmc_no: c.lmc_no || `LMC-${c.id}`,
        death_date: c.death_date || null,
        date: formatDateValue(c.death_date),
        time: formatTimeValue(c.time_of_death || c.created_at),
        tag_no: c.cattle?.tag_no || '-',
        category: c.category || c.cattle?.category || '-',
        coat_colour: c.coat_colour || c.cattle?.coat_colour || c.cattle?.colour || '-',
        // Use mortality_case fields first, fallback to cattle fields
        location: c.location || c.cattle?.location_block || '-',
        block: c.block || '-',
        cause_of_death: c.cause_of_death || c.postmortem_examination?.confirmed_cause_of_death || '-',
        preliminary_cod: c.cause_of_death || c.postmortem_examination?.confirmed_cause_of_death || '-',
        clinical_signs: c.initial_notes || '-',
        treatment: c.treatment || '-',
        additional_info: c.additional_notes || c.initial_notes || '-',
        reported_by: c.reported_by || c.creator?.name || '-',
        reported_by_role: c.creator?.role || '-',
        current_step: getStepIndex(c.current_step),
        current_step_raw: c.current_step,
        workflow_status: c.status || 'pending',
        endorsement_step: c.endorsement_step || 0,
        endorsement_documents: c.endorsement_documents || {},
        postmortem_examination: c.postmortem_examination ? {
            external_skin: c.postmortem_examination.external_skin || '-',
            external_eyes: c.postmortem_examination.external_eyes || '-',
            external_mouth: c.postmortem_examination.external_mouth || '-',
            external_nostrils: c.postmortem_examination.external_nostrils || '-',
            external_ears: c.postmortem_examination.external_ears || '-',
            external_limbs: c.postmortem_examination.external_limbs || '-',
            external_anus: c.postmortem_examination.external_anus || '-',
            external_genital: c.postmortem_examination.external_genital || '-',
            external_general: c.postmortem_examination.external_general || '-',
            heart_findings: c.postmortem_examination.heart_findings || '-',
            trachea_findings: c.postmortem_examination.trachea_findings || '-',
            lung_floating_test: c.postmortem_examination.lung_floating_test || '-',
            lung_floating_test_details: c.postmortem_examination.lung_floating_test_details || '-',
            diaphragma_test: c.postmortem_examination.diaphragma_test || '-',
            diaphragma_test_details: c.postmortem_examination.diaphragma_test_details || '-',
            kidney_findings: c.postmortem_examination.kidney_findings || '-',
            urinary_bladder_findings: c.postmortem_examination.urinary_bladder_findings || '-',
            rumen_findings: c.postmortem_examination.rumen_findings || '-',
            reticulum_findings: c.postmortem_examination.reticulum_findings || '-',
            omasum_findings: c.postmortem_examination.omasum_findings || '-',
            abomasum_findings: c.postmortem_examination.abomasum_findings || '-',
            small_intestine_findings: c.postmortem_examination.small_intestine_findings || '-',
            colon_findings: c.postmortem_examination.colon_findings || '-',
            liver_findings: c.postmortem_examination.liver_findings || '-',
            spleen_findings: c.postmortem_examination.spleen_findings || '-',
            joint_findings: c.postmortem_examination.joint_findings || '-',
            reproductive_organ_findings: c.postmortem_examination.reproductive_organ_findings || '-',
            confirmed_cause_of_death: c.postmortem_examination.confirmed_cause_of_death || '-',
            additional_notes: c.postmortem_examination.additional_notes || '-',
            examination_date: formatDateValue(c.postmortem_examination.examination_date),
            examination_time: formatTimeValue(c.postmortem_examination.examination_time),
        } : null,
        approvals: c.approvals || [],
        cattle: c.cattle
    }));
});

// Watch for changes in mortalityCases and update selectedCase if it's open
watch(mortalityCases, (newCases) => {
    if (selectedCase.value) {
        const updatedCase = newCases.find(c => c.id === selectedCase.value.id);
        if (updatedCase) {
            selectedCase.value = updatedCase;
        }
    }
}, { deep: true });

const filteredCases = computed(() => {
    return mortalityCases.value;
});

const currentPage = ref(1);
const itemsPerPage = 10;

const paginatedCases = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    return filteredCases.value.slice(start, start + itemsPerPage);
});

const totalPages = computed(() => Math.ceil(filteredCases.value.length / itemsPerPage));

const pageNumbers = computed(() => {
    const pages = [];
    if (totalPages.value <= 7) {
        for (let i = 1; i <= totalPages.value; i++) {
            pages.push(i);
        }
    } else {
        if (currentPage.value <= 3) {
            pages.push(1, 2, 3, 4, '...', totalPages.value - 1, totalPages.value);
        } else if (currentPage.value >= totalPages.value - 2) {
            pages.push(1, 2, '...', totalPages.value - 3, totalPages.value - 2, totalPages.value - 1, totalPages.value);
        } else {
            pages.push(1, '...', currentPage.value - 1, currentPage.value, currentPage.value + 1, '...', totalPages.value);
        }
    }
    return pages;
});

const goToPage = (page) => {
    if (page !== '...' && page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
    }
};

const previousPage = () => {
    if (currentPage.value > 1) {
        currentPage.value--;
    }
};

const nextPage = () => {
    if (currentPage.value < totalPages.value) {
        currentPage.value++;
    }
};

watch(filteredCases, () => {
    currentPage.value = 1;
});

const getCurrentStepLabel = (caseData) => {
    // If no PM examination, show PM Exam pending
    if (!caseData.postmortem_examination) {
        return 'PM Exam Pending';
    }
    
    // Otherwise show endorsement step
    const stepIndex = caseData.endorsement_step || 0;
    if (stepIndex >= docWorkflowSteps.length) return 'Completed';
    return docWorkflowSteps[stepIndex]?.label || 'Unknown';
};

const canPerformAction = (caseData) => {
    if (!caseData.postmortem_examination) return false;

    const currentEndorsementStep = caseData.endorsement_step || 0;
    if (currentEndorsementStep >= docWorkflowSteps.length) return false;

    return canUserActStep(currentEndorsementStep);
};

const viewDetail = (caseData) => {
    selectedCase.value = caseData;
    // Initialize upload form when opening modal
    docUploadForm.value = {
        name: userName.value,
        date: new Date().toISOString().split('T')[0],
        signed_document: null,
    };
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    selectedCase.value = null;
    docUploadForm.value = {
        name: '',
        date: '',
        signed_document: null,
    };
};

const handleDocFileUpload = (event) => {
    const file = event.target.files[0];
    if (file) {
        docUploadForm.value.signed_document = file;
    }
};

// Helper functions that work with selectedCase (for the combined modal)
const getStepDocumentForCase = (caseData, stepIndex) => {
    if (!caseData?.endorsement_documents) return null;
    const docs = caseData.endorsement_documents;
    return docs[stepIndex] || docs[String(stepIndex)] || null;
};

const canUploadStepForCase = (caseData, stepIndex) => {
    if (!caseData) return false;
    if (caseData.workflow_status === 'completed') return false;
    if (!canUserActStep(stepIndex)) return false;

    const currentStep = caseData.endorsement_step || 0;

    if (stepIndex === 4) {
        return stepIndex <= currentStep;
    }

    if (currentStep === stepIndex) return true;

    if (stepIndex < currentStep) {
        const nextStepDoc = getStepDocumentForCase(caseData, stepIndex + 1);
        return !nextStepDoc;
    }

    return false;
};

// Check if user can view their OWN uploaded document (only in their own step column)
const canViewStepForCase = (caseData, stepIndex) => {
    const stepDoc = getStepDocumentForCase(caseData, stepIndex);
    if (!stepDoc) return false;
    if (isAdmin.value) return true;
    if (!canViewMortality.value) return false;
    const assignedIds = getAssignedIdsForStep(stepIndex);
    return assignedIds.length > 0 && assignedIds.includes(userId.value);
};

// Check if user can download previous step's document (only shown in their OWN step column)
const canDownloadPreviousForCase = (caseData, stepIndex) => {
    const userStepIndex = getUserWorkflowStepIndex();
    if (stepIndex !== userStepIndex && !canViewMortality.value) return false;
    if (stepIndex === 0) return false;
    return !!getStepDocumentForCase(caseData, stepIndex - 1);
};

const downloadStepDocumentForCase = (caseData, stepIndex) => {
    window.open(`/mortality/${caseData.id}/download-endorsement/${stepIndex}`, '_blank');
};

const downloadPreviousStepDocumentForCase = (caseData, stepIndex) => {
    if (stepIndex > 0) {
        window.open(`/mortality/${caseData.id}/download-endorsement/${stepIndex - 1}`, '_blank');
    }
};

// Check if all 5 endorsement steps are uploaded
const allStepsUploaded = (caseData) => {
    if (!caseData) return false;
    for (let i = 0; i < 5; i++) {
        if (!getStepDocumentForCase(caseData, i)) return false;
    }
    return true;
};

// Check if user can mark case as completed
const canMarkAsCompleted = (caseData) => {
    if (!caseData) return false;
    if (!isAdmin.value) return false;
    if (caseData.workflow_status === 'completed') return false;
    return allStepsUploaded(caseData);
};

// Admin marks case as completed
const markAsCompleted = (caseData) => {
    if (!confirm('Are you sure you want to mark this case as completed? This will lock all uploads.')) {
        return;
    }
    
    router.post(`/mortality/${caseData.id}/mark-completed`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            // The watch on mortalityCases will update selectedCase automatically
        },
        onError: (errors) => {
            console.error('Mark as completed failed:', errors);
            alert('Failed to mark case as completed. Please try again.');
        }
    });
};

const downloadEndorsementFormForCase = (caseData) => {
    window.open(`/mortality/${caseData.id}/endorsement-form`, '_blank');
};

const submitDocUploadForCase = (caseData, stepIndex) => {
    if (isSubmitting.value) return;
    if (!docUploadForm.value.signed_document || !docUploadForm.value.name || !docUploadForm.value.date) {
        alert('Please fill in all fields and upload the signed document');
        return;
    }

    isSubmitting.value = true;

    const formData = new FormData();
    formData.append('signed_document', docUploadForm.value.signed_document);
    formData.append('name', docUploadForm.value.name);
    formData.append('date', docUploadForm.value.date);
    formData.append('step_index', stepIndex);

    router.post(`/mortality/${caseData.id}/upload-endorsement`, formData, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            docUploadForm.value.signed_document = null;
            isSubmitting.value = false;
            // The watch on mortalityCases will update selectedCase automatically
        },
        onError: (errors) => {
            console.error('Upload failed:', errors);
            alert('Failed to upload document. Please try again.');
            isSubmitting.value = false;
        }
    });
};

const startPMExam = (caseData) => {
    router.visit(`/mortality/${caseData.id}/pm-examination`);
};

const getApprovalDate = (stepId) => {
    if (!selectedCase.value?.approvals) return null;
    const approval = selectedCase.value.approvals.find(a => a.step === stepId);
    return approval ? formatDate(approval.created_at) : null;
};

const getApprovalBy = (stepId) => {
    if (!selectedCase.value?.approvals) return null;
    const approval = selectedCase.value.approvals.find(a => a.step === stepId);
    return approval?.user_name || null;
};

const formatDate = (dateString) => formatDateValue(dateString);

const downloadReport = (caseData) => {
    window.open(`/mortality/${caseData.id}/report`, '_blank');
};

const applyFilters = () => {
    router.get('/mortality/records', {
        search: searchQuery.value || undefined,
        month: selectedMonth.value || undefined,
        year: selectedYear.value || undefined,
        unit: selectedUnit.value || undefined,
    }, {
        preserveState: false,
        preserveScroll: true,
        replace: true,
    });
};

const clearFilters = () => {
    searchQuery.value = '';
    selectedMonth.value = '';
    selectedYear.value = '';
    selectedUnit.value = '';
    applyFilters();
};

const showDeleteModal = ref(false);
const caseToDelete = ref(null);

const confirmDeleteCase = (caseData) => {
    caseToDelete.value = caseData;
    showDeleteModal.value = true;
};

const deleteCase = () => {
    if (!caseToDelete.value) return;
    
    router.delete(`/mortality/${caseToDelete.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
            caseToDelete.value = null;
        },
        onError: (errors) => {
            console.error('Delete failed:', errors);
            alert('Failed to delete case. Please try again.');
        }
    });
};

const goToCreateMortality = () => {
    router.visit('/mortality/create');
};

const editCase = (caseData) => {
    router.visit(`/mortality/${caseData.id}/edit`);
};

const viewWorkflow = (caseData) => {
    if (isCaseActionLocked(caseData)) return;
    router.visit(`/mortality/${caseData.id}/workflow`);
};

const isCaseActionLocked = (caseData) => {
    return caseData?.workflow_status === 'completed' && !caseData?.is_reopened;
};

const canEditCase = (caseData) => {
    if (!canEditMortality.value) return false;
    return !isCaseActionLocked(caseData);
};

const getCompletedDisabledReason = (actionLabel, caseData) => {
    if (isCaseActionLocked(caseData)) {
        return `Disabled: mortality workflow already marked as completed`;
    }
    return actionLabel;
};

const canReopenCase = (caseData) => {
    if (!isAdmin.value) return false;
    if (caseData?.is_reopened) return false;
    return caseData?.workflow_status === 'completed' || caseData?.workflow_status === 'approved';
};

const reopenCase = (caseData) => {
    if (!canReopenCase(caseData)) return;

    if (!confirm('Reopen this mortality record? It will restart the workflow from the reopened state.')) {
        return;
    }

    router.post(route('mortality.reopen', caseData.id), {}, {
        preserveScroll: true,
    });
};

const viewDetailGuarded = (caseData) => {
    viewDetail(caseData);
};

const editCaseGuarded = (caseData) => {
    if (!canEditCase(caseData)) return;
    editCase(caseData);
};

const deleteCaseGuarded = (caseData) => {
    if (isCaseActionLocked(caseData)) return;
    confirmDeleteCase(caseData);
};
</script>

<template>
    <div class="max-w-7xl mx-auto">
        <div class="mb-6 flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Mortality History</h1>
                <p class="text-sm text-gray-500 mt-1">View and manage mortality cases.</p>
            </div>
            <button
                v-if="canCreateMortality"
                @click="goToCreateMortality"
                class="inline-flex items-center gap-2 bg-[#34554a] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#2a443b] transition-colors shadow-sm"
            >
                <Plus class="w-4 h-4" />
                Add New Mortality
            </button>
        </div>

        <div v-if="!userRole" class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-6">
            <AlertTriangle class="w-12 h-12 text-yellow-500 mx-auto mb-4" />
            <h2 class="text-lg font-bold text-yellow-700 mb-2 text-center">No Role Assigned</h2>
            <p class="text-yellow-600 text-center">Your user account doesn't have a role assigned. Please contact your administrator.</p>
        </div>

        <template v-else>
            <!-- Filter Row -->
            <div class="bg-white rounded-xl border border-gray-200 p-4 mb-6">
                <div class="flex flex-wrap gap-3 items-center">
                    <div class="relative flex-1 min-w-[220px]">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 w-4 h-4" />
                        <input
                            v-model="searchQuery"
                            @keyup.enter="applyFilters"
                            type="text"
                            placeholder="Search by tag number..."
                            class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-200 outline-none text-sm focus:ring-2 focus:ring-[#34554a]"
                        >
                    </div>
                    <select
                        v-model="selectedMonth"
                        @change="applyFilters"
                        class="px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]"
                    >
                        <option value="">All Months</option>
                        <option v-for="month in monthOptions" :key="month" :value="month">{{ month }}</option>
                    </select>
                    <select
                        v-model="selectedYear"
                        @change="applyFilters"
                        class="px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]"
                    >
                        <option value="">All Years</option>
                        <option v-for="year in years" :key="year" :value="year">{{ year }}</option>
                    </select>
                    <select
                        v-model="selectedUnit"
                        @change="applyFilters"
                        class="px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]"
                    >
                        <option value="">All Operating Units</option>
                        <option v-for="unit in operatingUnits" :key="unit" :value="unit">{{ unit }}</option>
                    </select>
                    <button
                        @click="applyFilters"
                        class="px-4 py-2 bg-[#34554a] text-white rounded-lg text-sm font-medium hover:bg-[#2a443b] transition-colors"
                    >
                        Search
                    </button>
                    <button
                        v-if="searchQuery || selectedMonth || selectedYear || selectedUnit"
                        @click="clearFilters"
                        class="px-3 py-2 text-gray-500 text-sm hover:text-gray-700 font-medium flex items-center gap-1"
                    >
                        <FilterX class="w-4 h-4" />
                        Clear
                    </button>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-200">
            <div class="overflow-x-auto">
                <table class="w-full text-left whitespace-nowrap">
                    <thead>
                        <tr class="bg-[#34554a] text-white text-sm">
                            <th class="p-4 font-semibold">LMC No.</th>
                            <th class="p-4 font-semibold">Date</th>
                            <th class="p-4 font-semibold">Tag No.</th>
                            <th class="p-4 font-semibold">Category</th>
                            <th class="p-4 font-semibold">Location</th>
                            <th class="p-4 font-semibold">Preliminary COD</th>
                            <th class="p-4 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm text-gray-700">
                        <tr v-for="caseData in paginatedCases" :key="caseData.id" class="transition-colors">
                            <td class="p-4">
                                <code class="bg-[#34554a]/10 text-[#34554a] px-2 py-1 rounded font-bold text-xs">{{ caseData.lmc_no }}</code>
                            </td>
                            <td class="p-4">{{ caseData.date }}</td>
                            <td class="p-4 font-medium">{{ caseData.tag_no }}</td>
                            <td class="p-4">
                                <span class="px-2.5 py-0.5 bg-gray-100 rounded text-xs font-medium">{{ caseData.category }}</span>
                            </td>
                            <td class="p-4 text-gray-600">{{ caseData.location }}</td>
                            <td class="p-4 text-gray-600">{{ caseData.preliminary_cod }}</td>
                            <td class="p-4">
                                <div class="flex items-center gap-1">
                                    <button
                                        v-if="canEditMortality"
                                        @click="editCaseGuarded(caseData)"
                                        :disabled="!canEditCase(caseData)"
                                        :title="!canEditCase(caseData) ? getCompletedDisabledReason('Edit', caseData) : 'Edit'"
                                        class="w-8 h-8 rounded flex items-center justify-center transition-colors"
                                        :class="canEditCase(caseData) ? 'text-gray-400 hover:text-gray-600 hover:bg-gray-100' : 'text-gray-300 bg-gray-100 cursor-not-allowed'"
                                    >
                                        <Edit class="w-4 h-4" />
                                    </button>
                                    <button 
                                        v-if="canViewMortality"
                                        @click="viewWorkflow(caseData)"
                                        :disabled="isCaseActionLocked(caseData)"
                                        :title="isCaseActionLocked(caseData) ? getCompletedDisabledReason('Workflow', caseData) : 'Workflow'"
                                        class="w-8 h-8 rounded flex items-center justify-center transition-colors"
                                        :class="!isCaseActionLocked(caseData) ? 'text-gray-400 hover:text-gray-600 hover:bg-gray-100' : 'text-gray-300 bg-gray-100 cursor-not-allowed'"
                                    >
                                        <FileSignature class="w-4 h-4" />
                                    </button>
                                    <button 
                                        v-if="canViewMortality"
                                        @click="viewDetailGuarded(caseData)"
                                        title="View Details"
                                        class="w-8 h-8 rounded flex items-center justify-center transition-colors text-gray-400 hover:text-gray-600 hover:bg-gray-100"
                                    >
                                        <Eye class="w-4 h-4" />
                                    </button>
                                    <button
                                        v-if="canReopenCase(caseData)"
                                        @click="reopenCase(caseData)"
                                        class="w-8 h-8 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded flex items-center justify-center transition-colors"
                                        title="Reopen"
                                    >
                                        <RotateCcw class="w-4 h-4" />
                                    </button>
                                    <button 
                                        v-if="!caseData.postmortem_examination && userRole === 'livestock'"
                                        @click="startPMExam(caseData)"
                                        class="w-8 h-8 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded flex items-center justify-center transition-colors"
                                        title="Start PM Exam">
                                        <Microscope class="w-4 h-4" />
                                    </button>
                                    <button 
                                        v-if="canDeleteMortality"
                                        @click="deleteCaseGuarded(caseData)"
                                        :disabled="isCaseActionLocked(caseData)"
                                        :title="isCaseActionLocked(caseData) ? getCompletedDisabledReason('Delete', caseData) : 'Delete'"
                                        class="w-8 h-8 rounded flex items-center justify-center transition-colors"
                                        :class="!isCaseActionLocked(caseData) ? 'text-gray-400 hover:text-gray-600 hover:bg-gray-100' : 'text-gray-300 bg-gray-100 cursor-not-allowed'"
                                    >
                                        <Trash2 class="w-4 h-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="filteredCases.length === 0">
                            <td colspan="7" class="p-8 text-center text-gray-400 italic">No cases found</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination Controls -->
            <div class="flex flex-col md:flex-row justify-between items-center p-4 border-t border-gray-100 bg-gray-50">
                <div class="flex items-center gap-2 mb-4 md:mb-0">
                    <button
                        @click="previousPage"
                        :disabled="currentPage === 1"
                        :class="currentPage === 1 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-white'"
                        class="w-8 h-8 flex items-center justify-center border border-gray-200 rounded-lg text-gray-500"
                    >
                        <ChevronLeft class="w-4 h-4" />
                    </button>

                    <button
                        v-for="page in pageNumbers"
                        :key="page"
                        @click="page !== '...' && goToPage(page)"
                        :class="[
                            page === currentPage ? 'bg-[#34554a] text-white' : 'text-gray-600 hover:bg-white',
                            page === '...' ? 'cursor-default' : ''
                        ]"
                        class="w-8 h-8 flex items-center justify-center rounded-lg text-sm font-bold transition-colors"
                    >
                        {{ page }}
                    </button>

                    <button
                        @click="nextPage"
                        :disabled="currentPage === totalPages || totalPages === 0"
                        :class="currentPage === totalPages || totalPages === 0 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-white'"
                        class="w-8 h-8 flex items-center justify-center border border-gray-200 rounded-lg text-gray-500"
                    >
                        <ChevronRight class="w-4 h-4" />
                    </button>
                </div>

                <div class="text-sm text-gray-500">
                    Showing <span class="font-semibold text-gray-800">{{ filteredCases.length > 0 ? (currentPage - 1) * itemsPerPage + 1 : 0 }}-{{ Math.min(currentPage * itemsPerPage, filteredCases.length) }}</span> of <span class="font-semibold text-gray-800">{{ filteredCases.length }}</span> records
                </div>
            </div>
        </div>
        </template>

        <!-- View Detail Modal (Calving-style) -->
        <Teleport to="body">
            <div
                v-if="showModal && selectedCase"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-5 overflow-y-auto"
                @click.self="closeModal"
            >
                <div class="bg-white rounded-xl shadow-xl w-full max-w-7xl mx-auto overflow-hidden border border-gray-100 max-h-[90vh] flex flex-col" @click.stop>
                    <div class="flex justify-between items-center p-6 border-b border-gray-100 bg-gray-50 sticky top-0">
                        <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                            <Skull class="w-5 h-5 text-[#34554a]" />
                            <span>Mortality Record Details - {{ selectedCase?.tag_no || '-' }}</span>
                        </h3>
                        <button
                            type="button"
                            @click="closeModal"
                            class="text-gray-400 hover:text-gray-600 transition-colors w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-200"
                        >
                            ✕
                        </button>
                    </div>

                    <div class="p-6 overflow-y-auto flex-1 space-y-5">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="bg-gray-50 rounded-xl p-4">
                                <h4 class="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2">
                                    <FileText class="w-4 h-4 text-[#34554a]" />
                                    Case Information
                                </h4>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between py-1.5 border-b border-gray-200"><span class="text-gray-500">LMC No.</span><span class="font-semibold text-gray-900">{{ selectedCase?.lmc_no || '-' }}</span></div>
                                    <div class="flex justify-between py-1.5 border-b border-gray-200"><span class="text-gray-500">Tag No.</span><span class="font-semibold text-gray-900">{{ selectedCase?.tag_no || '-' }}</span></div>
                                    <div class="flex justify-between py-1.5 border-b border-gray-200"><span class="text-gray-500">Category</span><span class="font-semibold text-gray-900">{{ selectedCase?.category || '-' }}</span></div>
                                    <div class="flex justify-between py-1.5 border-b border-gray-200"><span class="text-gray-500">Coat Colour</span><span class="font-semibold text-gray-900">{{ selectedCase?.coat_colour || '-' }}</span></div>
                                    <div class="flex justify-between py-1.5 border-b border-gray-200"><span class="text-gray-500">Date</span><span class="font-semibold text-gray-900">{{ selectedCase?.date || '-' }}</span></div>
                                    <div class="flex justify-between py-1.5 border-b border-gray-200"><span class="text-gray-500">Time</span><span class="font-semibold text-gray-900">{{ selectedCase?.time || '-' }}</span></div>
                                    <div class="flex justify-between py-1.5 border-b border-gray-200"><span class="text-gray-500">Location</span><span class="font-semibold text-gray-900">{{ selectedCase?.location || '-' }}</span></div>
                                    <div class="flex justify-between py-1.5 border-b border-gray-200"><span class="text-gray-500">Block</span><span class="font-semibold text-gray-900">{{ selectedCase?.block || '-' }}</span></div>
                                    <div class="flex justify-between py-1.5 border-b border-gray-200"><span class="text-gray-500">Cause of Death</span><span class="font-semibold text-gray-900">{{ selectedCase?.cause_of_death || '-' }}</span></div>
                                    <div class="flex justify-between py-1.5"><span class="text-gray-500">Reported By</span><span class="font-semibold text-gray-900">{{ selectedCase?.reported_by || '-' }}</span></div>
                                </div>
                            </div>

                            <div class="bg-gray-50 rounded-xl p-4">
                                <h4 class="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2">
                                    <Microscope class="w-4 h-4 text-[#34554a]" />
                                    Notes & Diagnosis
                                </h4>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between py-1.5 border-b border-gray-200"><span class="text-gray-500">Preliminary COD</span><span class="font-semibold text-gray-900">{{ selectedCase?.preliminary_cod || '-' }}</span></div>
                                    <div class="flex justify-between py-1.5 border-b border-gray-200"><span class="text-gray-500">Clinical Signs</span><span class="font-semibold text-gray-900">{{ selectedCase?.clinical_signs || '-' }}</span></div>
                                    <div class="flex justify-between py-1.5 border-b border-gray-200"><span class="text-gray-500">Treatment</span><span class="font-semibold text-gray-900">{{ selectedCase?.treatment || '-' }}</span></div>
                                    <div class="flex justify-between py-1.5"><span class="text-gray-500">Additional Info</span><span class="font-semibold text-gray-900">{{ selectedCase?.additional_info || '-' }}</span></div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50 rounded-xl p-4">
                            <h4 class="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2">
                                <Microscope class="w-4 h-4 text-[#34554a]" />
                                PM Examination Details
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                                <div class="bg-white rounded-lg p-3 border border-gray-200"><span class="text-gray-500 block mb-1">Examination Date</span><span class="font-semibold text-gray-900">{{ selectedCase?.postmortem_examination?.examination_date || '-' }}</span></div>
                                <div class="bg-white rounded-lg p-3 border border-gray-200"><span class="text-gray-500 block mb-1">Examination Time</span><span class="font-semibold text-gray-900">{{ selectedCase?.postmortem_examination?.examination_time || '-' }}</span></div>
                                <div class="bg-white rounded-lg p-3 border border-gray-200"><span class="text-gray-500 block mb-1">Confirmed Cause</span><span class="font-semibold text-gray-900">{{ selectedCase?.postmortem_examination?.confirmed_cause_of_death || '-' }}</span></div>

                                <div class="bg-white rounded-lg p-3 border border-gray-200"><span class="text-gray-500 block mb-1">Skin</span><span class="font-semibold text-gray-900">{{ selectedCase?.postmortem_examination?.external_skin || '-' }}</span></div>
                                <div class="bg-white rounded-lg p-3 border border-gray-200"><span class="text-gray-500 block mb-1">Eyes</span><span class="font-semibold text-gray-900">{{ selectedCase?.postmortem_examination?.external_eyes || '-' }}</span></div>
                                <div class="bg-white rounded-lg p-3 border border-gray-200"><span class="text-gray-500 block mb-1">Mouth</span><span class="font-semibold text-gray-900">{{ selectedCase?.postmortem_examination?.external_mouth || '-' }}</span></div>
                                <div class="bg-white rounded-lg p-3 border border-gray-200"><span class="text-gray-500 block mb-1">Nostrils</span><span class="font-semibold text-gray-900">{{ selectedCase?.postmortem_examination?.external_nostrils || '-' }}</span></div>
                                <div class="bg-white rounded-lg p-3 border border-gray-200"><span class="text-gray-500 block mb-1">Ears</span><span class="font-semibold text-gray-900">{{ selectedCase?.postmortem_examination?.external_ears || '-' }}</span></div>
                                <div class="bg-white rounded-lg p-3 border border-gray-200"><span class="text-gray-500 block mb-1">Limbs</span><span class="font-semibold text-gray-900">{{ selectedCase?.postmortem_examination?.external_limbs || '-' }}</span></div>
                                <div class="bg-white rounded-lg p-3 border border-gray-200"><span class="text-gray-500 block mb-1">Anus</span><span class="font-semibold text-gray-900">{{ selectedCase?.postmortem_examination?.external_anus || '-' }}</span></div>
                                <div class="bg-white rounded-lg p-3 border border-gray-200"><span class="text-gray-500 block mb-1">Genital</span><span class="font-semibold text-gray-900">{{ selectedCase?.postmortem_examination?.external_genital || '-' }}</span></div>
                                <div class="bg-white rounded-lg p-3 border border-gray-200"><span class="text-gray-500 block mb-1">General</span><span class="font-semibold text-gray-900">{{ selectedCase?.postmortem_examination?.external_general || '-' }}</span></div>

                                <div class="bg-white rounded-lg p-3 border border-gray-200"><span class="text-gray-500 block mb-1">Heart</span><span class="font-semibold text-gray-900">{{ selectedCase?.postmortem_examination?.heart_findings || '-' }}</span></div>
                                <div class="bg-white rounded-lg p-3 border border-gray-200"><span class="text-gray-500 block mb-1">Trachea</span><span class="font-semibold text-gray-900">{{ selectedCase?.postmortem_examination?.trachea_findings || '-' }}</span></div>
                                <div class="bg-white rounded-lg p-3 border border-gray-200"><span class="text-gray-500 block mb-1">Lung Floating Test</span><span class="font-semibold text-gray-900">{{ selectedCase?.postmortem_examination?.lung_floating_test || '-' }}</span></div>
                                <div class="bg-white rounded-lg p-3 border border-gray-200"><span class="text-gray-500 block mb-1">Diaphragma Test</span><span class="font-semibold text-gray-900">{{ selectedCase?.postmortem_examination?.diaphragma_test || '-' }}</span></div>
                                <div class="bg-white rounded-lg p-3 border border-gray-200"><span class="text-gray-500 block mb-1">Kidney</span><span class="font-semibold text-gray-900">{{ selectedCase?.postmortem_examination?.kidney_findings || '-' }}</span></div>
                                <div class="bg-white rounded-lg p-3 border border-gray-200"><span class="text-gray-500 block mb-1">Urinary Bladder</span><span class="font-semibold text-gray-900">{{ selectedCase?.postmortem_examination?.urinary_bladder_findings || '-' }}</span></div>
                                <div class="bg-white rounded-lg p-3 border border-gray-200"><span class="text-gray-500 block mb-1">Rumen</span><span class="font-semibold text-gray-900">{{ selectedCase?.postmortem_examination?.rumen_findings || '-' }}</span></div>
                                <div class="bg-white rounded-lg p-3 border border-gray-200"><span class="text-gray-500 block mb-1">Reticulum</span><span class="font-semibold text-gray-900">{{ selectedCase?.postmortem_examination?.reticulum_findings || '-' }}</span></div>
                                <div class="bg-white rounded-lg p-3 border border-gray-200"><span class="text-gray-500 block mb-1">Omasum</span><span class="font-semibold text-gray-900">{{ selectedCase?.postmortem_examination?.omasum_findings || '-' }}</span></div>
                                <div class="bg-white rounded-lg p-3 border border-gray-200"><span class="text-gray-500 block mb-1">Abomasum</span><span class="font-semibold text-gray-900">{{ selectedCase?.postmortem_examination?.abomasum_findings || '-' }}</span></div>
                                <div class="bg-white rounded-lg p-3 border border-gray-200"><span class="text-gray-500 block mb-1">Small Intestine</span><span class="font-semibold text-gray-900">{{ selectedCase?.postmortem_examination?.small_intestine_findings || '-' }}</span></div>
                                <div class="bg-white rounded-lg p-3 border border-gray-200"><span class="text-gray-500 block mb-1">Colon</span><span class="font-semibold text-gray-900">{{ selectedCase?.postmortem_examination?.colon_findings || '-' }}</span></div>
                                <div class="bg-white rounded-lg p-3 border border-gray-200"><span class="text-gray-500 block mb-1">Liver</span><span class="font-semibold text-gray-900">{{ selectedCase?.postmortem_examination?.liver_findings || '-' }}</span></div>
                                <div class="bg-white rounded-lg p-3 border border-gray-200"><span class="text-gray-500 block mb-1">Spleen</span><span class="font-semibold text-gray-900">{{ selectedCase?.postmortem_examination?.spleen_findings || '-' }}</span></div>
                                <div class="bg-white rounded-lg p-3 border border-gray-200"><span class="text-gray-500 block mb-1">Joint</span><span class="font-semibold text-gray-900">{{ selectedCase?.postmortem_examination?.joint_findings || '-' }}</span></div>
                                <div class="bg-white rounded-lg p-3 border border-gray-200"><span class="text-gray-500 block mb-1">Reproductive Organ</span><span class="font-semibold text-gray-900">{{ selectedCase?.postmortem_examination?.reproductive_organ_findings || '-' }}</span></div>
                                <div class="bg-white rounded-lg p-3 border border-gray-200 md:col-span-2"><span class="text-gray-500 block mb-1">Additional Notes</span><span class="font-semibold text-gray-900">{{ selectedCase?.postmortem_examination?.additional_notes || '-' }}</span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <DeleteConfirmationModal
            :show="showDeleteModal"
            title="Delete Mortality Case"
            :message="`Are you sure you want to delete mortality case`"
            :itemName="caseToDelete?.lmc_no || ''"
            @close="showDeleteModal = false"
            @confirm="deleteCase"
        />
    </div>
</template>
