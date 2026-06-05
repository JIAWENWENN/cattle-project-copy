<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, watch } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import {
    ClipboardList, Baby, Plus, Search, Filter, Calendar,
    Eye, Edit, Trash2, ChevronRight, ChevronLeft,
    CheckCircle, FilterX, Download, AlertTriangle, FileSignature, Upload, X, RotateCcw
} from 'lucide-vue-next';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import WorkflowEndorsementCards from '@/Components/WorkflowEndorsementCards.vue';

defineOptions({
    layout: (h, page) => h(AppLayout, { title: 'Calving Checklist', parent: 'Calving Checklist', parentUrl: '/calving-checklist' }, () => page)
});

const props = defineProps({
    checklistRecords: Array,
    stats: Object,
    monthYear: String,
    operatingUnit: String,
    availableMonths: Array,
    operatingUnits: Array,
    monthlyWorkflow: Object,
    docWorkflowSteps: Array,
    userRole: String,
    workflowAssignment: Object,
    completedWorkflowScopeKeys: Array,
});

const page = usePage();
const currentUserName = computed(() => String(page.props.auth?.user?.name || ''));
const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = 10;

// Bulk action state
const selectedRecords = ref([]);
const showBulkActions = ref(false);
const showDeleteModal = ref(false);
const recordToDelete = ref(null);
const showRecordModal = ref(false);
const selectedRecord = ref(null);
const showWorkflowModal = ref(false);

const isRecordSelected = (recordId) => {
    return selectedRecords.value.includes(recordId);
};

const toggleSelectAll = () => {
    if (allSelected.value) {
        const pageIds = paginatedRecords.value.map(r => r.id);
        selectedRecords.value = selectedRecords.value.filter(id => !pageIds.includes(id));
    } else {
        const pageIds = paginatedRecords.value.map(r => r.id);
        selectedRecords.value = [...new Set([...selectedRecords.value, ...pageIds])];
    }
};

const toggleSelectRecord = (recordId) => {
    const index = selectedRecords.value.indexOf(recordId);
    if (index > -1) {
        selectedRecords.value.splice(index, 1);
    } else {
        selectedRecords.value.push(recordId);
    }
};

const bulkDelete = () => {
    if (selectedRecords.value.length === 0) return;
    
    const confirmMessage = selectedRecords.value.length === 1 
        ? 'Are you sure you want to delete this record?' 
        : `Are you sure you want to delete ${selectedRecords.value.length} records?`;
    
    if (confirm(confirmMessage)) {
        router.post('/calving-checklist/bulk-delete', {
            ids: selectedRecords.value
        }, {
            onSuccess: () => {
                selectedRecords.value = [];
                showBulkActions.value = false;
            }
        });
    }
};

const clearSelection = () => {
    selectedRecords.value = [];
    showBulkActions.value = false;
};

const displayRecords = computed(() => props.checklistRecords || []);
const displayStats = computed(() => props.stats || {});
const displayMonthYear = computed(() => props.monthYear || '');
const displayOperatingUnit = computed(() => props.operatingUnit || '');
const displayMonths = computed(() => props.availableMonths || []);
const displayUnits = computed(() => props.operatingUnits || []);
const displayWorkflow = computed(() => props.monthlyWorkflow || {});
const displayDocWorkflowSteps = computed(() => props.docWorkflowSteps || []);
const displayUserRole = computed(() => props.userRole || '');
const completedWorkflowScopeKeySet = computed(() => new Set((props.completedWorkflowScopeKeys || []).map(v => String(v))));

const selectedUnit = ref(displayOperatingUnit.value);

const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sept', 'Oct', 'Nov', 'Dec'];

const parseStorageMonthYear = (value) => {
    if (!value || value === 'All Records') return { month: '', year: '' };
    const raw = String(value).trim();

    const slashParts = raw.split('/');
    if (slashParts.length === 2) {
        const monthToken = slashParts[0] === 'Sep' ? 'Sept' : slashParts[0];
        return { month: monthToken, year: slashParts[1] };
    }

    const ymMatch = raw.match(/^(\d{4})-(\d{2})$/);
    if (ymMatch) {
        const year = ymMatch[1];
        const monthNum = Number(ymMatch[2]);
        if (monthNum >= 1 && monthNum <= 12) {
            return { month: months[monthNum - 1], year };
        }
    }

    return { month: '', year: '' };
};

const initialScope = parseStorageMonthYear(displayMonthYear.value);
const monthFilter = ref(initialScope.month);
const yearFilter = ref(initialScope.year);

const years = computed(() => {
    const fromServer = (displayMonths.value || [])
        .map((m) => parseStorageMonthYear(m).year)
        .filter(Boolean);
    const currentYear = String(new Date().getFullYear());
    return [...new Set([currentYear, ...fromServer])].sort((a, b) => Number(b) - Number(a));
});

const hasWorkflowScope = computed(() => Boolean(selectedUnit.value && monthFilter.value && yearFilter.value));
const canExportMonthly = computed(() => hasWorkflowScope.value);
const selectedMonthYearStorage = computed(() => hasWorkflowScope.value ? `${monthFilter.value}/${yearFilter.value}` : '');
const selectedMonthYearParam = computed(() => {
    if (!hasWorkflowScope.value) return '';
    const monthIndex = months.indexOf(monthFilter.value);
    if (monthIndex < 0) return '';
    return `${yearFilter.value}-${String(monthIndex + 1).padStart(2, '0')}`;
});

// Display text for the current filter period
const displayFilterPeriod = computed(() => {
    if (!hasWorkflowScope.value) return 'Select Month, Year and Operational Unit';
    return `${monthFilter.value} ${yearFilter.value}`;
});

const isSubmitting = ref(false);
const docUploadForm = ref({
    name: currentUserName.value,
    date: new Date().toISOString().split('T')[0],
    signed_document: null,
});

const getStepDocument = (workflow, stepIndex) => {
    if (!workflow?.endorsement_documents) return null;
    return workflow.endorsement_documents[stepIndex] || workflow.endorsement_documents[String(stepIndex)] || null;
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
        ids = Array.isArray(cfg.witnessed_by_user_ids) ? cfg.witnessed_by_user_ids : [];
        if (!ids.length && cfg.witnessed_by_user_id) ids = [cfg.witnessed_by_user_id];
    } else if (stepIndex === 3) {
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
    if (String(displayUserRole.value).toLowerCase() === 'admin') return true;
    const permsArray = getChecklistPermissionList();
    return permsArray.includes('full') || permsArray.includes(action);
};

const canViewChecklist = computed(() => hasCalvingChecklistPermission('view'));
const canEditChecklist = computed(() => hasCalvingChecklistPermission('edit'));
const canDeleteChecklist = computed(() => hasCalvingChecklistPermission('delete'));

const isMonthlyWorkflowCompleted = computed(() => {
    return displayWorkflow.value && displayWorkflow.value.is_completed;
});

const getRecordWorkflowScopeKey = (record) => {
    const monthYear = String(record?.month_year || '').trim();
    const operatingUnit = String(record?.operating_unit || '').trim();
    if (!monthYear || !operatingUnit) return '';
    return `${monthYear}|${operatingUnit}`;
};

const isRecordWorkflowCompleted = (record) => {
    const key = getRecordWorkflowScopeKey(record);
    return key !== '' && completedWorkflowScopeKeySet.value.has(key);
};

const canEditRecord = (record) => {
    if (!record || !canEditChecklist.value) return false;
    return !isRecordWorkflowCompleted(record);
};

const canDeleteRecord = (record) => {
    if (!record || !canDeleteChecklist.value) return false;
    return !isRecordWorkflowCompleted(record);
};

const canViewRecord = (record) => {
    if (!record || !canViewChecklist.value) return false;
    return true;
};

const getEditDisabledReason = (record) => {
    if (!record) return 'Edit unavailable';
    if (!canEditChecklist.value) return 'You do not have edit permission';
    if (isRecordWorkflowCompleted(record)) return 'Disabled: monthly workflow already marked as completed';
    return 'Edit record';
};

const getDeleteDisabledReason = (record) => {
    if (!record) return 'Delete unavailable';
    if (!canDeleteChecklist.value) return 'You do not have delete permission';
    if (isRecordWorkflowCompleted(record)) return 'Disabled: monthly workflow already marked as completed';
    return 'Delete record';
};

const getViewDisabledReason = (record) => {
    if (!record) return 'View unavailable';
    if (!canViewChecklist.value) return 'You do not have view permission';
    return 'View details';
};

const canUserActStep = (stepIndex) => {
    if (displayUserRole.value === 'admin') return true;
    // User must have at least 'view' permission on Calving Checklist to perform any workflow step
    if (!hasCalvingChecklistPermission('view')) return false;
    // Only users explicitly assigned in the ACM can act - no role-based fallback
    const assignedUserIds = getAssignedUserIdsForStep(stepIndex);
    return assignedUserIds.includes(userId.value);
};

const canUploadStep = (workflow, stepIndex) => {
    if (!workflow) return false;
    if (workflow.is_completed) return false;

    const currentStep = workflow.endorsement_step || 0;

    if (!canUserActStep(stepIndex)) return false;

    if (displayUserRole.value === 'admin') {
        return stepIndex <= currentStep;
    }

    return stepIndex === currentStep || stepIndex === currentStep - 1;
};

const canDownloadPrevious = (workflow, stepIndex) => {
    if (!workflow) return false;
    if (stepIndex === 0) return false;
    
    const prevDoc = getStepDocument(workflow, stepIndex - 1);
    if (!prevDoc) return false;
    
    if (displayUserRole.value === 'admin') {
        const currentStep = workflow.endorsement_step || 0;
        return stepIndex <= currentStep;
    }
    
    return canUserActStep(stepIndex);
};

const canViewStep = (workflow, stepIndex) => {
    if (!workflow) return false;
    const doc = getStepDocument(workflow, stepIndex);
    if (!doc) return false;
    
    if (displayUserRole.value === 'admin') {
        return true;
    }
    
    return canUserActStep(stepIndex);
};

const handleDocFileUpload = (event) => {
    const file = event.target.files[0];
    if (file) {
        docUploadForm.value.signed_document = file;
    }
};

const reopenBatch = () => {
    if (!hasWorkflowScope.value || !selectedMonthYearParam.value) {
        alert('Please select Operating Unit, Month, and Year first.');
        return;
    }
    if (!confirm('Are you sure you want to reopen this month\'s workflow?')) return;

    router.post('/calving-checklist/mark-batch-reopen', {
        month: selectedMonthYearParam.value,
        unit: selectedUnit.value
    }, {
        preserveScroll: true,
        onSuccess: () => {
            fetchPendingCounts();
            closeWorkflow();
        }
    });
};

const submitDocUpload = (stepIndex) => {
    if (isSubmitting.value) return;
    if (typeof stepIndex !== 'number') return;
    if (!hasWorkflowScope.value) {
        alert('Please select Operating Unit, Month, and Year first.');
        return;
    }
    
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
    formData.append('step_index', stepIndex);
    formData.append('month_year', selectedMonthYearStorage.value);
    formData.append('operating_unit', selectedUnit.value);

    router.post('/calving-checklist/upload-batch-endorsement', formData, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            docUploadForm.value.signed_document = null;
            isSubmitting.value = false;
        },
        onError: () => {
            isSubmitting.value = false;
        }
    });
};

const submitEndorsementUpload = ({ stepIndex, file, name, date }) => {
    docUploadForm.value.name = name || currentUserName.value;
    docUploadForm.value.date = date || new Date().toISOString().split('T')[0];
    docUploadForm.value.signed_document = file;
    submitDocUpload(stepIndex);
};

const downloadWorkflowForm = () => {
    if (!hasWorkflowScope.value || !selectedMonthYearParam.value) {
        alert('Please select Operating Unit, Month, and Year first.');
        return;
    }
    window.open(`/calving-checklist/export-report?month=${selectedMonthYearParam.value}&unit=${selectedUnit.value}&layout=form`, '_blank');
};

const downloadStepDocument = (stepIndex) => {
    if (!hasWorkflowScope.value || !selectedMonthYearParam.value) {
        alert('Please select Operating Unit, Month, and Year first.');
        return;
    }
    window.open(`/calving-checklist/download-batch-endorsement/${stepIndex}?month=${selectedMonthYearParam.value}&unit=${selectedUnit.value}`, '_blank');
};

const downloadPreviousStepDocument = (stepIndex) => {
    if (stepIndex === 0) return;
    const prevIndex = stepIndex - 1;
    if (!hasWorkflowScope.value || !selectedMonthYearParam.value) {
        alert('Please select Operating Unit, Month, and Year first.');
        return;
    }
    window.open(`/calving-checklist/download-batch-endorsement/${prevIndex}?month=${selectedMonthYearParam.value}&unit=${selectedUnit.value}`, '_blank');
};

const markAsCompleted = () => {
    if (!hasWorkflowScope.value || !selectedMonthYearParam.value) {
        alert('Please select Operating Unit, Month, and Year first.');
        return;
    }
    if (!confirm('Are you sure you want to mark this month as completed?')) return;
    
    router.post('/calving-checklist/mark-batch-completed', {
        month: selectedMonthYearParam.value,
        unit: selectedUnit.value
    }, {
        preserveScroll: true
    });
};

const exportReport = () => {
    if (!hasWorkflowScope.value || !selectedMonthYearParam.value) {
        alert('Please select Operating Unit, Month, and Year first.');
        return;
    }
    window.open(`/calving-checklist/export-report?month=${selectedMonthYearParam.value}&unit=${selectedUnit.value}&layout=form&hide_endorsement=1`, '_blank');
};

const filteredRecords = computed(() => {
    let result = [...displayRecords.value];

    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(r =>
            (r.tag_no && r.tag_no.toLowerCase().includes(query)) ||
            (r.dam_tag_no && r.dam_tag_no.toLowerCase().includes(query))
        );
    }

    return result;
});

const totalPages = computed(() => Math.ceil(filteredRecords.value.length / itemsPerPage));

const paginatedRecords = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    return filteredRecords.value.slice(start, end);
});

const pageNumbers = computed(() => {
    const pages = [];
    const maxVisible = 5;

    if (totalPages.value <= maxVisible) {
        for (let i = 1; i <= totalPages.value; i++) {
            pages.push(i);
        }
    } else if (currentPage.value <= 3) {
        pages.push(1, 2, 3, '...', totalPages.value);
    } else if (currentPage.value >= totalPages.value - 2) {
        pages.push(1, '...', totalPages.value - 2, totalPages.value - 1, totalPages.value);
    } else {
        pages.push(1, '...', currentPage.value - 1, currentPage.value, currentPage.value + 1, '...', totalPages.value);
    }

    return pages;
});

const goToPage = (pageNum) => {
    if (pageNum >= 1 && pageNum <= totalPages.value) {
        currentPage.value = pageNum;
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

// Bulk action computed properties
const hasSelectedRecords = computed(() => selectedRecords.value.length > 0);
const allSelected = computed(() => {
    if (paginatedRecords.value.length === 0) return false;
    return paginatedRecords.value.every(record => selectedRecords.value.includes(record.id));
});

watch([searchQuery, selectedUnit, monthFilter, yearFilter], () => {
    currentPage.value = 1;
});

watch(filteredRecords, () => {
    const newTotal = totalPages.value || 1;
    if (currentPage.value > newTotal) {
        currentPage.value = newTotal;
    }
});

const applyFilters = () => {
    // Build query params
    const params = {
        unit: selectedUnit.value
    };

    if (monthFilter.value && yearFilter.value) {
        const monthIndex = months.indexOf(monthFilter.value);
        if (monthIndex >= 0) {
            params.month = `${yearFilter.value}-${String(monthIndex + 1).padStart(2, '0')}`;
        }
    }
    
    router.get('/calving-checklist', params, { preserveState: true });
    currentPage.value = 1;
};

const clearFilters = () => {
    searchQuery.value = '';
    selectedUnit.value = '';
    monthFilter.value = '';
    yearFilter.value = '';
    applyFilters();
    currentPage.value = 1;
};

const openWorkflow = () => {
    if (!hasWorkflowScope.value) return;
    if (!docUploadForm.value.name) {
        docUploadForm.value.name = currentUserName.value;
    }
    showWorkflowModal.value = true;
};

const closeWorkflow = () => {
    showWorkflowModal.value = false;
};

const viewRecord = (record) => {
    if (!canViewRecord(record)) return;
    selectedRecord.value = record;
    showRecordModal.value = true;
};

const closeRecordModal = () => {
    showRecordModal.value = false;
    selectedRecord.value = null;
};

const editRecord = (record) => {
    if (!canEditRecord(record)) return;
    router.visit(`/calving-checklist/${record.id}/edit`);
};

const deleteRecord = (record) => {
    if (!canDeleteRecord(record)) return;
    recordToDelete.value = record;
    showDeleteModal.value = true;
};

const confirmDeleteRecord = () => {
    if (!recordToDelete.value?.id) return;
    router.delete(`/calving-checklist/${recordToDelete.value.id}`, {
        onFinish: () => {
            showDeleteModal.value = false;
            recordToDelete.value = null;
        }
    });
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    
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
        return '-';
    }
    
    return date.toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric' });
};

const getTreatmentIcon = (hasTreatment) => {
    return hasTreatment 
        ? '<span class="text-gray-700 font-medium">Yes</span>'
        : '<span class="text-gray-700 font-medium">No</span>';
};

const goToCreate = () => {
    let monthValue;

    if (selectedMonthYearParam.value) {
        monthValue = selectedMonthYearParam.value;
    } else {
        // Default to current month when viewing all records
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        monthValue = `${year}-${month}`;
    }
    
    router.visit('/calving-checklist/create', {
        data: {
            month: monthValue,
            unit: selectedUnit.value
        },
        replace: true
    });
};
</script>

<template>
    <div class="w-full">
        <!-- Flash Messages -->
        <div v-if="page.props.flash && page.props.flash.success" class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm flex items-center justify-between">
            <div class="flex items-center gap-2">
                <CheckCircle class="w-4 h-4" />
                {{ page.props.flash.success }}
            </div>
            <button @click="page.props.flash ? page.props.flash.success = null : null"><Trash2 class="w-4 h-4 opacity-50 hover:opacity-100" /></button>
        </div>

        <div v-if="page.props.flash && page.props.flash.error" class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm flex items-center justify-between">
            <div class="flex items-center gap-2">
                <AlertTriangle class="w-4 h-4" />
                {{ page.props.flash.error }}
            </div>
            <button @click="page.props.flash ? page.props.flash.error = null : null"><Trash2 class="w-4 h-4 opacity-50 hover:opacity-100" /></button>
        </div>
        <div class="mb-8 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Calving Checklist Records</h1>
                <p class="text-sm text-gray-500 mt-1">Monthly calving checklist - {{ displayFilterPeriod }}</p>
            </div>
            <div class="flex items-center gap-3">
                <button 
                    v-if="canDeleteChecklist"
                    @click="showBulkActions = !showBulkActions" 
                    :class="showBulkActions ? 'bg-[#34554a] text-white' : 'border border-gray-200 text-gray-600 hover:bg-gray-50'"
                    class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors"
                >
                    <Filter class="w-4 h-4" />
                    Bulk Actions
                </button>
                <button 
                    @click="openWorkflow"
                    :disabled="!canExportMonthly"
                    class="flex items-center gap-2 px-4 py-2 border rounded-lg text-sm font-medium transition-colors"
                    :class="canExportMonthly ? 'border-gray-200 text-gray-600 hover:bg-gray-50' : 'border-gray-200 text-gray-400 bg-gray-50 cursor-not-allowed'"
                    title="Select Operating Unit, Month, and Year first"
                >
                    <FileSignature class="w-4 h-4" />
                    Workflow
                </button>
                <button
                    @click="exportReport"
                    :disabled="!canExportMonthly"
                    class="flex items-center gap-2 px-4 py-2 border rounded-lg text-sm font-medium transition-colors"
                    :class="canExportMonthly ? 'border-gray-200 text-gray-600 hover:bg-gray-50' : 'border-gray-200 text-gray-400 bg-gray-50 cursor-not-allowed'"
                    title="Select Operating Unit, Month, and Year first"
                >
                    <Download class="w-4 h-4" />
                    Export PDF
                </button>
                <button 
                    v-if="hasCalvingChecklistPermission('create')"
                    @click="goToCreate" 
                    :disabled="isMonthlyWorkflowCompleted"
                    :title="isMonthlyWorkflowCompleted ? 'Disabled: monthly workflow already marked as completed' : 'Add Record'"
                    class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium shadow-sm transition-colors"
                    :class="!isMonthlyWorkflowCompleted ? 'bg-[#34554a] text-white hover:bg-[#2a443b]' : 'bg-gray-300 text-gray-500 cursor-not-allowed'"
                >
                    <Plus class="w-4 h-4" />
                    Add Record
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <div class="bg-white p-6 rounded-xl border border-gray-200 flex items-center gap-5 shadow-sm">
                <div class="w-14 h-14 bg-[#34554a]/10 rounded-full flex items-center justify-center">
                    <ClipboardList class="w-7 h-7 text-[#34554a]" />
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-wide">Total Records</p>
                    <p class="text-3xl font-black text-gray-900">{{ (displayStats && displayStats.total) ? displayStats.total : 0 }}</p>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl border border-gray-200 flex items-center gap-5 shadow-sm">
                <div class="w-14 h-14 bg-blue-100 rounded-full flex items-center justify-center">
                    <span class="text-2xl font-bold text-blue-600">♂</span>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-wide">Male Calves</p>
                    <p class="text-3xl font-black text-gray-900">{{ (displayStats && displayStats.male_calves) ? displayStats.male_calves : 0 }}</p>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl border border-gray-200 flex items-center gap-5 shadow-sm">
                <div class="w-14 h-14 bg-pink-100 rounded-full flex items-center justify-center">
                    <span class="text-2xl font-bold text-pink-600">♀</span>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-wide">Female Calves</p>
                    <p class="text-3xl font-black text-gray-900">{{ (displayStats && displayStats.female_calves) ? displayStats.female_calves : 0 }}</p>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
            <div class="p-4 flex flex-wrap gap-3 items-center">
                <div class="relative flex-1 min-w-[200px]">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 w-4 h-4" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search by tag number..."
                        class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-200 outline-none text-sm focus:ring-2 focus:ring-[#34554a]"
                    >
                </div>
                <select
                    v-model="monthFilter"
                    @change="applyFilters"
                    class="px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]"
                >
                    <option value="">Month</option>
                    <option v-for="month in months" :key="month" :value="month">{{ month }}</option>
                </select>
                <select
                    v-model="yearFilter"
                    @change="applyFilters"
                    class="px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]"
                >
                    <option value="">Year</option>
                    <option v-for="year in years" :key="year" :value="year">{{ year }}</option>
                </select>
                <select
                    v-model="selectedUnit"
                    @change="applyFilters"
                    class="px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]"
                >
                    <option value="">Operational Unit</option>
                    <option v-for="unit in displayUnits" :key="unit" :value="unit">{{ unit }}</option>
                </select>
                <button
                    v-if="searchQuery || monthFilter || yearFilter || selectedUnit"
                    @click="clearFilters"
                    class="px-3 py-2 text-gray-500 text-sm hover:text-gray-700 font-medium flex items-center gap-1"
                >
                    <FilterX class="w-4 h-4" />
                    Clear
                </button>
            </div>
        </div>

        <!-- Bulk Action Toolbar -->
        <div v-if="canDeleteChecklist && showBulkActions && hasSelectedRecords" class="bg-red-50 border border-red-200 rounded-xl p-4 mb-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="text-sm font-semibold text-red-800">
                    {{ selectedRecords.length }} record(s) selected
                </span>
            </div>
            <div class="flex items-center gap-3">
                <button 
                    @click="clearSelection"
                    class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 transition-colors"
                >
                    Clear Selection
                </button>
                <button 
                    @click="bulkDelete"
                    class="flex items-center gap-2 px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition-colors"
                >
                    <Trash2 class="w-4 h-4" />
                    Delete Selected
                </button>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left whitespace-nowrap">
                    <thead>
                        <tr class="bg-[#34554a] text-white text-sm">
                            <th v-if="canDeleteChecklist && showBulkActions" class="p-4 font-semibold w-12 text-center">
                                <input 
                                    type="checkbox" 
                                    :checked="allSelected"
                                    @change="toggleSelectAll"
                                    class="w-4 h-4 rounded border-gray-300 text-[#34554a] focus:ring-[#34554a] cursor-pointer"
                                />
                            </th>
                            <th class="p-4 font-semibold w-12">No.</th>
                            <th class="p-4 font-semibold">Week</th>
                            <th class="p-4 font-semibold">Date</th>
                            <th class="p-4 font-semibold">Tag No. (Dam)</th>
                            <th class="p-4 font-semibold">Sex</th>
                            <th class="p-4 font-semibold">Colour</th>
                            <th class="p-4 font-semibold">Tag (Calf)</th>
                            <th class="p-4 font-semibold">Times of pregnancy</th>
                            <th class="p-4 font-semibold">Location</th>
                            <th class="p-4 font-semibold">Block</th>
                            <th class="p-4 font-semibold">Phase</th>
                            <th class="p-4 font-semibold">General Condition</th>
                            <th class="p-4 font-semibold text-center">Treatment (Iodine/Woundsarex)</th>
                            <th class="p-4 font-semibold text-center">Colostrum</th>
                            <th class="p-4 font-semibold text-center">Maminume</th>
                            <th class="p-4 font-semibold">Tagging Date</th>
                            <th class="p-4 font-semibold">LCC No.</th>
                            <th class="p-4 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y text-sm text-gray-700">
                        <tr v-for="(record, index) in paginatedRecords" :key="record.id" 
                            :class="[
                                'transition-colors border-l-4 cursor-pointer',
                                'border-l-transparent',
                                selectedRecords.includes(record.id) ? 'bg-blue-50' : ''
                            ]"
                            @dblclick="canViewRecord(record) && viewRecord(record)">
                            <td v-if="canDeleteChecklist && showBulkActions" class="p-4 text-center" @click.stop>
                                <input 
                                    type="checkbox" 
                                    :checked="selectedRecords.includes(record.id)"
                                    :disabled="!canDeleteRecord(record)"
                                    @change="toggleSelectRecord(record.id)"
                                    class="w-4 h-4 rounded border-gray-300 text-[#34554a] focus:ring-[#34554a] cursor-pointer disabled:cursor-not-allowed disabled:opacity-40"
                                />
                            </td>
                            <td class="p-4 font-medium text-gray-900 border-r">{{ (currentPage - 1) * itemsPerPage + index + 1 }}</td>
                            <td class="p-4 text-gray-600">{{ record.week || '-' }}</td>
                            <td class="p-4 text-gray-600">{{ formatDate(record.calving_date) }}</td>
                            <td class="p-4 font-medium text-gray-900 font-mono text-xs">{{ record.dam_tag_no || '-' }}</td>
                            <td class="p-4 text-gray-600">{{ record.sex || '-' }}</td>
                            <td class="p-4 text-gray-600">{{ record.colour || '-' }}</td>
                            <td class="p-4 font-medium text-gray-900">{{ record.tag_no || '-' }}</td>
                            <td class="p-4 text-gray-600">{{ record.times_of_pregnancy || '-' }}</td>
                            <td class="p-4 text-gray-600">{{ record.location || '-' }}</td>
                            <td class="p-4 text-gray-600">{{ record.location_block || '-' }}</td>
                            <td class="p-4 text-gray-600">{{ record.location_phase || '-' }}</td>
                            <td class="p-4">
                                <span class="text-xs font-medium">
                                    {{ record.general_condition || '-' }}
                                </span>
                            </td>
                            <td class="p-4 text-center">
                                <span class="text-xs font-medium text-gray-700">
                                    {{ (record.treatment_iodine || record.treatment_woundsarex) ? 'Yes' : 'No' }}
                                </span>
                            </td>
                            <td class="p-4 text-center" v-html="getTreatmentIcon(record.colostrum_feeding_24h)"></td>
                            <td class="p-4 text-center" v-html="getTreatmentIcon(record.mamumune)"></td>
                            <td class="p-4 text-gray-600">{{ formatDate(record.tagging_checklist_date) }}</td>
                            <td class="p-4 font-mono text-xs text-[#34554a] font-bold">{{ record.lcc_running_number || '-' }}</td>
                            <td class="p-4 text-right flex justify-end gap-2">
                                <button
                                    v-if="canEditChecklist"
                                    @click.stop="editRecord(record)"
                                    :disabled="!canEditRecord(record)"
                                    :title="getEditDisabledReason(record)"
                                    class="w-8 h-8 rounded flex items-center justify-center transition-colors"
                                    :class="canEditRecord(record) ? 'text-gray-400 hover:text-gray-600 hover:bg-gray-100' : 'text-gray-300 cursor-not-allowed'"
                                >
                                    <Edit class="w-4 h-4" />
                                </button>
                                <button
                                    v-if="canViewChecklist"
                                    @click.stop="viewRecord(record)"
                                    :disabled="!canViewRecord(record)"
                                    :title="getViewDisabledReason(record)"
                                    class="w-8 h-8 rounded flex items-center justify-center transition-colors"
                                    :class="canViewRecord(record) ? 'text-gray-400 hover:text-gray-600 hover:bg-gray-100' : 'text-gray-300 bg-gray-100 cursor-not-allowed'"
                                >
                                    <Eye class="w-4 h-4" />
                                </button>
                                <button 
                                    v-if="canDeleteChecklist"
                                    @click.stop="deleteRecord(record)"
                                    :disabled="!canDeleteRecord(record)"
                                    :title="getDeleteDisabledReason(record)"
                                    class="w-8 h-8 rounded flex items-center justify-center transition-colors"
                                    :class="canDeleteRecord(record) ? 'text-gray-400 hover:text-gray-600 hover:bg-gray-100' : 'text-gray-300 bg-gray-100 cursor-not-allowed'"
                                >
                                    <Trash2 class="w-4 h-4" />
                                </button>
                            </td>
                        </tr>
                        <tr v-if="filteredRecords.length === 0">
                            <td :colspan="showBulkActions ? 19 : 18" class="p-8 text-center text-gray-400 italic">
                                <ClipboardList class="w-8 h-8 mx-auto mb-2 opacity-50" />
                                <p class="text-sm">No calving checklist records found for {{ displayMonthYear }}</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

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
                        v-for="(pageNum, pageIdx) in pageNumbers"
                        :key="`calving-page-${pageIdx}-${pageNum}`"
                        @click="pageNum !== '...' && goToPage(pageNum)"
                        :class="[
                            pageNum === currentPage ? 'bg-[#34554a] text-white' : 'text-gray-600 hover:bg-white',
                            pageNum === '...' ? 'cursor-default' : ''
                        ]"
                        class="w-8 h-8 flex items-center justify-center rounded-lg text-sm font-bold transition-colors"
                    >
                        {{ pageNum }}
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
                    Showing <span class="font-semibold text-gray-800">{{ filteredRecords.length > 0 ? (currentPage - 1) * itemsPerPage + 1 : 0 }}-{{ Math.min(currentPage * itemsPerPage, filteredRecords.length) }}</span> of <span class="font-semibold text-gray-800">{{ filteredRecords.length }}</span> records
                </div>
            </div>
        </div>
    </div>

    <div v-if="showWorkflowModal && hasWorkflowScope" class="fixed inset-y-0 right-0 left-0 md:left-64 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-5 overflow-y-auto" @click.self="closeWorkflow">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-7xl mx-auto overflow-hidden border border-gray-100 max-h-[90vh] flex flex-col">
            <div class="flex justify-between items-center p-6 border-b border-gray-100 bg-gray-50 sticky top-0 flex-shrink-0">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">Monthly Endorsement Workflow</h3>
                    <p class="text-sm text-gray-500 mt-1">{{ monthFilter }} {{ yearFilter }} | {{ selectedUnit }}</p>
                </div>
                <button @click="closeWorkflow" class="text-gray-400 hover:text-gray-600 transition-colors w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-200">
                    <X class="w-5 h-5" />
                </button>
            </div>

            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-xs text-gray-500 font-medium">Current Step</p>
                        <p class="text-lg font-bold text-gray-900">{{ displayWorkflow?.endorsement_step || 0 }} / {{ displayDocWorkflowSteps.length }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            @click="downloadWorkflowForm"
                            class="flex items-center gap-2 px-3 py-1.5 text-sm text-[#34554a] border border-[#34554a] rounded-lg hover:bg-[#34554a]/10"
                        >
                            <FileSignature class="w-4 h-4" />
                            Download Form
                        </button>
                        <button
                            v-if="displayUserRole === 'admin' && displayWorkflow && displayWorkflow.endorsement_step === 4 && !displayWorkflow.is_completed"
                            @click="markAsCompleted"
                            class="flex items-center gap-2 px-3 py-1.5 text-sm text-white rounded-lg bg-[#34554a] hover:bg-[#2a443b]"
                        >
                            <CheckCircle class="w-4 h-4" />
                            Mark Completed
                        </button>
                        <button
                            v-if="displayUserRole === 'admin' && displayWorkflow && displayWorkflow.is_completed"
                            @click="reopenBatch"
                            class="flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-amber-700 bg-amber-100 rounded-lg hover:bg-amber-200"
                        >
                            <RotateCcw class="w-4 h-4" />
                            Reopen
                        </button>
                    </div>
                </div>

                <div class="max-w-xl mx-auto flex justify-center gap-2 flex-wrap p-4 bg-gray-50 rounded-xl">
                    <template v-for="(step, index) in displayDocWorkflowSteps" :key="index">
                        <div class="flex flex-col items-center min-w-[70px]">
                            <div
                                class="w-10 h-10 rounded-full flex items-center justify-center text-sm mb-1"
                                :class="getStepDocument(displayWorkflow, index) ? 'bg-[#34554a] text-white' : 'bg-gray-200 text-gray-400'"
                            >
                                <CheckCircle v-if="getStepDocument(displayWorkflow, index)" class="w-5 h-5" />
                                <span v-else>{{ index + 1 }}</span>
                            </div>
                            <span class="text-[10px] text-center" :class="getStepDocument(displayWorkflow, index) ? 'text-[#34554a] font-semibold' : 'text-gray-400'">{{ step.label }}</span>
                        </div>
                        <div v-if="index < displayDocWorkflowSteps.length - 1" class="pb-5 flex items-center" :class="getStepDocument(displayWorkflow, index) ? 'text-[#34554a]' : 'text-gray-300'">
                            <ChevronRight class="w-4 h-4" />
                        </div>
                    </template>
                </div>
            </div>

            <div class="overflow-y-auto flex-1 p-5">
                <WorkflowEndorsementCards
                    :steps="displayDocWorkflowSteps"
                    :get-step-document="(stepIdx) => getStepDocument(displayWorkflow, stepIdx)"
                    :can-upload-step="(stepIdx) => canUploadStep(displayWorkflow, stepIdx)"
                    :can-view-step="(stepIdx) => canViewStep(displayWorkflow, stepIdx)"
                    :can-download-previous="(stepIdx) => canDownloadPrevious(displayWorkflow, stepIdx)"
                    :is-submitting="isSubmitting"
                    :user-name="currentUserName"
                    grid-class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4"
                    @upload="submitEndorsementUpload"
                    @view="downloadStepDocument"
                    @previous="downloadPreviousStepDocument"
                />
            </div>

            <div class="border-t p-4 bg-gray-100 flex justify-end flex-shrink-0">
                <button @click="closeWorkflow" class="px-6 py-2.5 bg-gray-300 text-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-400 transition-colors">
                    Close
                </button>
            </div>
        </div>
    </div>

    <Teleport to="body">
        <div
            v-if="showRecordModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-5 overflow-y-auto"
            @click.self="closeRecordModal"
        >
            <div class="bg-white rounded-xl shadow-xl w-full max-w-6xl mx-auto overflow-hidden border border-gray-100 max-h-[90vh] flex flex-col" @click.stop>
                <div class="flex justify-between items-center p-6 border-b border-gray-100 bg-gray-50 sticky top-0">
                    <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                        <Baby class="w-5 h-5 text-[#34554a]" />
                        <span>Calving Checklist Details - {{ selectedRecord?.tag_no || '-' }}</span>
                    </h3>
                    <button
                        type="button"
                        @click="closeRecordModal"
                        class="text-gray-400 hover:text-gray-600 transition-colors w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-200"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div class="p-6 overflow-y-auto flex-1">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-5">
                            <div class="bg-gray-50 rounded-xl p-4">
                                <h4 class="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2">
                                    <Baby class="w-4 h-4 text-[#34554a]" />
                                    Calf Information
                                </h4>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between py-1.5 border-b border-gray-200"><span class="text-gray-500">Tag Number</span><span class="font-semibold text-gray-900">{{ selectedRecord?.tag_no || '-' }}</span></div>
                                    <div class="flex justify-between py-1.5 border-b border-gray-200"><span class="text-gray-500">LCC No.</span><span class="font-semibold text-gray-900">{{ selectedRecord?.lcc_running_number || '-' }}</span></div>
                                    <div class="flex justify-between py-1.5 border-b border-gray-200"><span class="text-gray-500">Week</span><span class="font-semibold text-gray-900">{{ selectedRecord?.week || '-' }}</span></div>
                                    <div class="flex justify-between py-1.5 border-b border-gray-200"><span class="text-gray-500">Date</span><span class="font-semibold text-gray-900">{{ formatDate(selectedRecord?.calving_date) }}</span></div>
                                    <div class="flex justify-between py-1.5 border-b border-gray-200"><span class="text-gray-500">Sex</span><span class="font-semibold text-gray-900">{{ selectedRecord?.sex || '-' }}</span></div>
                                    <div class="flex justify-between py-1.5 border-b border-gray-200"><span class="text-gray-500">Colour</span><span class="font-semibold text-gray-900">{{ selectedRecord?.colour || '-' }}</span></div>
                                    <div class="flex justify-between py-1.5"><span class="text-gray-500">Condition</span><span class="font-semibold text-gray-900">{{ selectedRecord?.general_condition || '-' }}</span></div>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-5">
                            <div class="bg-gray-50 rounded-xl p-4">
                                <h4 class="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2">
                                    <ClipboardList class="w-4 h-4 text-[#34554a]" />
                                    Breeder & Location
                                </h4>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between py-1.5 border-b border-gray-200"><span class="text-gray-500">Dam Tag No.</span><span class="font-semibold text-gray-900">{{ selectedRecord?.dam_tag_no || '-' }}</span></div>
                                    <div class="flex justify-between py-1.5 border-b border-gray-200"><span class="text-gray-500">Times of Pregnancy</span><span class="font-semibold text-gray-900">{{ selectedRecord?.times_of_pregnancy || '-' }}</span></div>
                                    <div class="flex justify-between py-1.5 border-b border-gray-200"><span class="text-gray-500">Operating Unit</span><span class="font-semibold text-gray-900">{{ selectedRecord?.operating_unit || '-' }}</span></div>
                                    <div class="flex justify-between py-1.5 border-b border-gray-200"><span class="text-gray-500">Location</span><span class="font-semibold text-gray-900">{{ selectedRecord?.location || '-' }}</span></div>
                                    <div class="flex justify-between py-1.5 border-b border-gray-200"><span class="text-gray-500">Block</span><span class="font-semibold text-gray-900">{{ selectedRecord?.location_block || '-' }}</span></div>
                                    <div class="flex justify-between py-1.5 border-b border-gray-200"><span class="text-gray-500">Phase</span><span class="font-semibold text-gray-900">{{ selectedRecord?.location_phase || '-' }}</span></div>
                                    <div class="flex justify-between py-1.5 border-b border-gray-200"><span class="text-gray-500">Tagging Date</span><span class="font-semibold text-gray-900">{{ formatDate(selectedRecord?.tagging_checklist_date) }}</span></div>
                                    <div class="flex justify-between py-1.5"><span class="text-gray-500">Remarks</span><span class="font-semibold text-gray-900">{{ selectedRecord?.remarks || '-' }}</span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>

    <DeleteConfirmationModal
        :show="showDeleteModal"
        title="Delete Calving Checklist Record"
        :message="recordToDelete ? `Are you sure you want to delete checklist record #${recordToDelete.id}? This action cannot be undone.` : 'Are you sure you want to delete this checklist record? This action cannot be undone.'"
        confirmText="Delete"
        cancelText="Cancel"
        @close="showDeleteModal = false; recordToDelete = null"
        @confirm="confirmDeleteRecord"
    />
</template>
