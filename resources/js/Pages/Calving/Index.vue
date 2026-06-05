<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, watch, nextTick, toRaw } from 'vue';
import { usePage, router, useForm } from '@inertiajs/vue3';
import {
    Baby, Plus, Search, Filter, Calendar,
    ChevronRight, ChevronLeft, Eye, Edit, Trash2,
    CheckCircle, Clock, FilterX, Download,
    FileSignature, ClipboardCheck, Shield, Upload
} from 'lucide-vue-next';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import WorkflowEndorsementCards from '@/Components/WorkflowEndorsementCards.vue';

defineOptions({
    layout: (h, page) => h(AppLayout, { title: 'Calving Records', parent: 'Calving', parentUrl: '/calving' }, () => page)
});

const page = usePage();
const userRole = computed(() => page.props.auth?.user?.role || '');
const userName = computed(() => page.props.auth?.user?.name || '');
const userId = computed(() => Number(page.props.auth?.user?.id || 0));
const calvingPermissions = computed(() => {
    const perms = page.props.auth?.permissions?.['Calving Record'];
    return Array.isArray(perms) ? perms : ['no-access'];
});
const hasCalvingPermission = (action) => {
    if (String(userRole.value).toLowerCase() === 'admin') return true;
    const perms = calvingPermissions.value;
    return perms.includes('full') || perms.includes(action);
};
const canViewCalving = computed(() => hasCalvingPermission('view'));
const canCreateCalving = computed(() => hasCalvingPermission('create'));
const canEditCalving = computed(() => hasCalvingPermission('edit'));
const canDeleteCalving = computed(() => hasCalvingPermission('delete'));
const workflowAssignment = computed(() => page.props.workflowAssignment || null);
const searchQuery = ref('');
const isSubmitting = ref(false);

const calvingRecords = computed(() => page.props.calvingRecords || []);
const stats = computed(() => page.props.stats || {});
const monthYear = computed(() => page.props.monthYear || '');
const operatingUnit = computed(() => page.props.operatingUnit || '');
const availableMonths = computed(() => page.props.availableMonths || []);
const operatingUnits = computed(() => page.props.operatingUnits || []);
const myRecordsFromServer = computed(() => page.props.myRecords || false);

const monthOptions = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sept', 'Oct', 'Nov', 'Dec'];

const parseStorageMonthYear = (value) => {
    if (!value) return { month: '', year: '' };
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
            return { month: monthOptions[monthNum - 1], year };
        }
    }

    return { month: '', year: '' };
};

const initialScope = parseStorageMonthYear(monthYear.value);
const selectedMonth = ref(initialScope.month);
const selectedYear = ref(initialScope.year);
const selectedUnit = ref(operatingUnit.value || '');
const showMyRecords = ref(myRecordsFromServer.value);
const currentPage = ref(1);
const itemsPerPage = 10;

const years = computed(() => {
    const fromServer = (availableMonths.value || [])
        .map((m) => parseStorageMonthYear(m).year)
        .filter(Boolean);
    const currentYear = String(new Date().getFullYear());
    return [...new Set([currentYear, ...fromServer])].sort((a, b) => Number(b) - Number(a));
});

const selectedMonthYearParam = computed(() => {
    if (!selectedMonth.value || !selectedYear.value) return '';
    const monthIndex = monthOptions.indexOf(selectedMonth.value);
    if (monthIndex < 0) return '';
    return `${selectedYear.value}-${String(monthIndex + 1).padStart(2, '0')}`;
});

const displayFilterPeriod = computed(() => {
    if (!selectedMonth.value || !selectedYear.value) return 'All Records';
    return `${selectedMonth.value} ${selectedYear.value}`;
});

const canExportMonthly = computed(() => Boolean(selectedMonthYearParam.value && selectedUnit.value));

const showModal = ref(false);
const selectedRecord = ref(null);
const modalView = ref('details');
const showDeleteModal = ref(false);
const recordToDelete = ref(null);

// Bulk action states
const selectedRecords = ref([]);
const showBulkActions = ref(false);

// Document upload form (used inline in the grid)
const docUploadForm = ref({
    name: '',
    date: '',
    hasFile: false,  // Just track if file is selected, not the actual file
});

// Key to force file input reset
const fileInputKey = ref(0);

const workflowSteps = [
    { id: 'issued', label: 'Issued', roles: ['livestock'], role_name: 'Sr. Assistant Livestock' },
    { id: 'verified', label: 'Verified', roles: ['security'], role_name: 'Sr. Assistant Security' },
    { id: 'checked', label: 'Checked', roles: ['supervisor'], role_name: 'Supervisor Livestock' },
    { id: 'witnessed', label: 'Witnessed', roles: ['assistant_manager'], role_name: 'Estate Management' },
    { id: 'approved', label: 'Approved', roles: ['livestock manager'], role_name: 'Livestock Manager/OIC' }
];

const stepStatuses = ['pending', 'issued', 'verified', 'checked', 'witnessed', 'approved', 'completed'];

const normalizeRole = (role) => String(role || '')
    .toLowerCase()
    .replace(/[_-]+/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();

const canonicalRole = (role) => {
    const r = normalizeRole(role);
    if (['livestock manager', 'manager', 'livestock_manager', 'act livestock manager', 'act. livestock manager'].includes(r)) return 'livestock manager';
    if (['assistant manager', 'assistant_manager'].includes(r)) return 'assistant_manager';
    return r;
};

const roleMatches = (userRoleValue, stepRoleValue) => canonicalRole(userRoleValue) === canonicalRole(stepRoleValue);

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

const canUserActStep = (stepIndex) => {
    if (userRole.value === 'admin') return true;
    // User must have at least 'view' permission on Calving Record to perform any workflow step
    if (!hasCalvingPermission('view')) return false;
    // Only users explicitly assigned in the ACM can act — no role-based fallback
    const assignedUserIds = getAssignedUserIdsForStep(stepIndex);
    return assignedUserIds.includes(userId.value);
};

const getUserWorkflowStepIndex = () => {
    if (canonicalRole(userRole.value) === 'admin') return null;
    for (let i = 0; i < workflowSteps.length; i++) {
        if (canUserActStep(i)) return i;
    }
    return null;
};

const filteredRecords = computed(() => {
    let result = [...calvingRecords.value];

    if (selectedUnit.value) {
        result = result.filter(r => r.operating_unit === selectedUnit.value);
    }

    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(r =>
            (r.tag_no && r.tag_no.toLowerCase().includes(query)) ||
            (r.dam_tag_no && r.dam_tag_no.toLowerCase().includes(query))
        );
    }

    return result;
});

watch(calvingRecords, (records) => {
    if (!selectedRecord.value?.id) return;
    const updated = records.find(r => r.id === selectedRecord.value.id);
    if (updated) {
        selectedRecord.value = { ...updated };
    }
}, { deep: true });

const pendingRecords = computed(() => {
    return filteredRecords.value.filter(record => {
        return canUploadStepForRecord(record, getCurrentStepIndex(record));
    });
});

// Check if a file is selected in the form
const hasValidFile = computed(() => {
    return docUploadForm.value.hasFile;
});

// Bulk action computed properties
const hasSelectedRecords = computed(() => selectedRecords.value.length > 0);
const allSelected = computed(() => {
    const selectableRecords = paginatedRecords.value.filter(record => canDeleteRecord(record));
    if (selectableRecords.length === 0) return false;
    return selectableRecords.every(record => selectedRecords.value.includes(record.id));
});

const totalPages = computed(() => {
    return Math.ceil(filteredRecords.value.length / itemsPerPage);
});

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
    } else {
        if (currentPage.value <= 3) {
            pages.push(1, 2, 3, '...', totalPages.value);
        } else if (currentPage.value >= totalPages.value - 2) {
            pages.push(1, '...', totalPages.value - 2, totalPages.value - 1, totalPages.value);
        } else {
            pages.push(1, '...', currentPage.value - 1, currentPage.value, currentPage.value + 1, '...', totalPages.value);
        }
    }

    return pages;
});

const getCurrentStepIndex = (record) => {
    // Use endorsement_step if available, otherwise derive from status
    if (record.endorsement_step !== undefined && record.endorsement_step !== null) {
        return record.endorsement_step;
    }
    const status = record.status || 'pending';
    const index = stepStatuses.indexOf(status);
    return index >= 0 ? index : 0;
};

const getStatusStepIndex = (status) => {
    // If completed, return 6 (higher than all steps) to show all as green
    if (status === 'completed') return 6;
    const index = stepStatuses.indexOf(status);
    return index >= 0 ? index : 0;
};

/** Step is done if document uploaded, or record fully completed by admin. */
const isWorkflowStepComplete = (record, stepIndex) => {
    if (!record) return false;
    if (record.status === 'completed') return true;
    return !!getStepDocumentForRecord(record, stepIndex);
};

// Get uploaded document for a specific step
const getStepDocumentForRecord = (record, stepIndex) => {
    if (!record?.endorsement_documents) return null;
    const docs = record.endorsement_documents;
    return docs[stepIndex] || docs[String(stepIndex)] || null;
};

// Check if user can upload at this step
const canUploadStepForRecord = (record, stepIndex) => {
    if (!record) return false;

    // Block all uploads if record is approved, rejected, or completed
    if (record.status === 'approved' || record.status === 'rejected' || record.status === 'completed') return false;

    const currentStep = record.endorsement_step ?? 0;

    if (!canUserActStep(stepIndex)) return false;

    // Step 4 (manager - last person): can upload/re-upload anytime until record is approved
    if (stepIndex === 4) {
        return stepIndex <= currentStep;
    }

    // Steps 0-3: can upload at current step, OR re-upload if next person hasn't uploaded yet
    if (currentStep === stepIndex) return true;

    if (stepIndex < currentStep) {
        const nextStepDoc = getStepDocumentForRecord(record, stepIndex + 1);
        return !nextStepDoc;
    }

    return false;
};

// Check if user can view their OWN uploaded document (only in their own step column)
const canViewStepForRecord = (record, stepIndex) => {
    const stepDoc = getStepDocumentForRecord(record, stepIndex);
    if (!stepDoc) return false;

    if (userRole.value === 'admin') return true;

    // User can ONLY view their own step's document
    const userStepIndex = getUserWorkflowStepIndex();
    return stepIndex === userStepIndex;
};

// Check if user can download previous step's document (only shown in their OWN step column)
const canDownloadPreviousForRecord = (record, stepIndex) => {
    // Must be user's own step column
    const userStepIndex = getUserWorkflowStepIndex();
    if (stepIndex !== userStepIndex && userRole.value !== 'admin') return false;

    // First step has no previous
    if (stepIndex === 0) return false;

    // Check if previous step has document
    const prevStepDoc = getStepDocumentForRecord(record, stepIndex - 1);
    return !!prevStepDoc;
};

// Download own uploaded document
const downloadStepDocumentForRecord = (record, stepIndex) => {
    window.open(`/calving/${record.id}/download-endorsement-document/${stepIndex}`, '_blank');
};

// Download previous step's document
const downloadPreviousStepDocumentForRecord = (record, stepIndex) => {
    if (stepIndex > 0) {
        window.open(`/calving/${record.id}/download-endorsement-document/${stepIndex - 1}`, '_blank');
    }
};

// Handle file upload in the grid - just track if file is selected
const handleDocFileUpload = (event) => {
    const input = event.target;
    const files = input.files;
    if (files && files.length > 0) {
        const file = files[0];
        console.log('File selected:', { name: file.name, size: file.size, type: file.type });
        docUploadForm.value.hasFile = true;
    } else {
        docUploadForm.value.hasFile = false;
    }
};

// Reset the file input element
const resetFileInput = () => {
    docUploadForm.value.hasFile = false;
    fileInputKey.value++;
};

// Submit document upload for a record at a specific step
const submitDocUploadForRecord = async (record, stepIndex) => {
    if (isSubmitting.value) return;
    
    // Get file directly from the DOM input element to avoid Vue proxy issues
    const fileInput = document.querySelector(`#file-input-${stepIndex}`);
    if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
        alert('Please select a PDF file to upload');
        return;
    }
    
    const file = fileInput.files[0];
    
    if (!docUploadForm.value.name || !docUploadForm.value.date) {
        alert('Please fill in all fields');
        return;
    }

    if (!record?.id) {
        alert('Error: No record ID found');
        return;
    }

    console.log('Submitting upload:', {
        recordId: record.id,
        stepIndex: stepIndex,
        fileName: file.name,
        fileSize: file.size,
        fileType: file.type,
        name: docUploadForm.value.name,
        date: docUploadForm.value.date
    });

    isSubmitting.value = true;

    const formData = new FormData();
    formData.append('signed_document', file);
    formData.append('name', docUploadForm.value.name);
    formData.append('date', docUploadForm.value.date);

    router.post(`/calving/${record.id}/upload-endorsement/${stepIndex}`, formData, {
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
    if (!selectedRecord.value?.id) {
        alert('Error: No record ID found');
        return;
    }

    isSubmitting.value = true;

    const formData = new FormData();
    formData.append('signed_document', file);
    formData.append('name', name || userName.value || '');
    formData.append('date', date || new Date().toISOString().split('T')[0]);

    router.post(`/calving/${selectedRecord.value.id}/upload-endorsement/${stepIndex}`, formData, {
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

// Legacy functions for backward compatibility
const getStepField = (stepIndex) => {
    const fields = ['issued', 'verified', 'checked', 'witnessed', 'approved'];
    return fields[stepIndex] || '';
};

const getStepName = (record, stepIndex) => {
    // First check endorsement_documents
    const stepDoc = getStepDocumentForRecord(record, stepIndex);
    if (stepDoc?.name) return stepDoc.name;
    
    // Fallback to legacy fields
    if (stepIndex === 0) return record.issued_by_name || null;
    if (stepIndex === 1) return record.verified_by_name || null;
    if (stepIndex === 2) return record.checked_by_name || null;
    if (stepIndex === 3) return record.witnessed_by_name || null;
    if (stepIndex === 4) return record.approved_by_name || null;
    return null;
};

const getStepDate = (record, stepIndex) => {
    // First check endorsement_documents
    const stepDoc = getStepDocumentForRecord(record, stepIndex);
    if (stepDoc?.date) return stepDoc.date;
    
    // Fallback to legacy fields
    if (stepIndex === 0) return record.issued_by_date || null;
    if (stepIndex === 1) return record.verified_by_date || null;
    if (stepIndex === 2) return record.checked_by_date || null;
    if (stepIndex === 3) return record.witnessed_by_date || null;
    if (stepIndex === 4) return record.approved_by_date || null;
    return null;
};

const hasStepDocument = (record, stepIndex) => {
    if (!record) return false;
    // Check endorsement_documents first
    if (getStepDocumentForRecord(record, stepIndex)) return true;
    // Fallback to legacy signature fields
    const field = getStepField(stepIndex);
    const signatureField = field + '_by_signature';
    return record[signatureField] === true;
};

const goToPage = (page) => {
    if (page >= 1 && page <= totalPages.value) {
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

const applyFilters = () => {
    currentPage.value = 1;
    const params = {
        unit: selectedUnit.value,
    };
    if (selectedMonthYearParam.value) {
        params.month = selectedMonthYearParam.value;
    }
    router.get('/calving', params, { preserveState: true });
};

const clearFilters = () => {
    searchQuery.value = '';
    selectedMonth.value = '';
    selectedYear.value = '';
    selectedUnit.value = '';
    currentPage.value = 1;
    router.get('/calving', {}, { preserveState: true });
};

const exportReport = () => {
    if (!canExportMonthly.value) {
        alert('Please select Month, Year, and Operational Unit above to enable export.');
        return;
    }
    const params = new URLSearchParams({
        month: selectedMonthYearParam.value,
        unit: selectedUnit.value,
    });
    window.open(`/calving/export?${params.toString()}`, '_blank');
};

const viewRecord = (record) => {
    selectedRecord.value = record;
    modalView.value = 'details';
    showModal.value = true;
};

const viewWorkflow = (record) => {
    selectedRecord.value = record;
    modalView.value = 'workflow';
    // Initialize upload form when opening modal - ensure clean state
    docUploadForm.value = {
        name: userName.value,
        date: new Date().toISOString().split('T')[0],
        hasFile: false,
    };
    // Reset file input key to force re-render
    fileInputKey.value++;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    selectedRecord.value = null;
    modalView.value = 'details';
    docUploadForm.value = {
        name: '',
        date: '',
        hasFile: false,
    };
};

const editRecord = (record) => {
    if (!canEditRecord(record)) return;
    router.visit(`/calving/${record.id}/edit`);
};

const goToCreate = () => {
    if (!canCreateCalving.value) return;

    const params = {};
    if (selectedMonthYearParam.value) params.month = selectedMonthYearParam.value;
    if (selectedUnit.value) params.unit = selectedUnit.value;

    router.visit('/calving/create', { data: params });
};

const deleteRecord = (record) => {
    if (!canDeleteRecord(record)) return;

    recordToDelete.value = record;
    showDeleteModal.value = true;
};

const confirmDeleteRecord = () => {
    const recordId = recordToDelete.value?.id;
    if (!recordId) return;

    router.post(route('calving.delete', recordId), {}, {
        preserveScroll: true,
        onFinish: () => {
            showDeleteModal.value = false;
            recordToDelete.value = null;
        },
        onError: () => {
            alert('Failed to delete calving record. Please try again.');
        },
    });
};

// Bulk action methods
const toggleSelectAll = () => {
    const selectableRecords = paginatedRecords.value.filter(record => canDeleteRecord(record));

    if (allSelected.value) {
        // Deselect all on current page
        selectableRecords.forEach(record => {
            const index = selectedRecords.value.indexOf(record.id);
            if (index > -1) {
                selectedRecords.value.splice(index, 1);
            }
        });
    } else {
        // Select all on current page
        selectableRecords.forEach(record => {
            if (!selectedRecords.value.includes(record.id)) {
                selectedRecords.value.push(record.id);
            }
        });
    }
};

const toggleSelectRecord = (record) => {
    if (!canDeleteRecord(record)) return;
    const recordId = record.id;
    const index = selectedRecords.value.indexOf(recordId);
    if (index > -1) {
        selectedRecords.value.splice(index, 1);
    } else {
        selectedRecords.value.push(recordId);
    }
};

const isRecordSelected = (recordId) => {
    return selectedRecords.value.includes(recordId);
};

const clearSelection = () => {
    selectedRecords.value = [];
    showBulkActions.value = false;
};

const bulkDelete = () => {
    if (!canDeleteCalving.value) return;
    if (selectedRecords.value.length === 0) return;
    
    const message = `Are you sure you want to delete ${selectedRecords.value.length} record(s)? This action cannot be undone.`;
    if (confirm(message)) {
        router.post('/calving/bulk-delete', {
            ids: selectedRecords.value
        }, {
            onSuccess: () => {
                selectedRecords.value = [];
                showBulkActions.value = false;
            },
            onError: (errors) => {
                alert('Failed to delete records. Please try again.');
            }
        });
    }
};

const downloadLCC = (record) => {
    router.visit(`/calving/${record.id}/lcc`);
};

const getStatusColor = (status) => {
    switch (status) {
        case 'completed': return 'bg-[#34554a] text-white border-[#34554a]';
        case 'approved': return 'bg-green-100 text-green-700 border-green-200';
        case 'issued': return 'bg-blue-100 text-blue-700 border-blue-200';
        case 'verified': return 'bg-indigo-100 text-indigo-700 border-indigo-200';
        case 'checked': return 'bg-cyan-100 text-cyan-700 border-cyan-200';
        case 'witnessed': return 'bg-purple-100 text-purple-700 border-purple-200';
        case 'rejected': return 'bg-red-100 text-red-700 border-red-200';
        default: return 'bg-amber-100 text-amber-700 border-amber-200';
    }
};

const formatStatusLabel = (status) => {
    if (!status) return '-';
    const normalized = String(status).replace(/_/g, ' ').toLowerCase();
    if (normalized === 'pending') return '-';
    return normalized.charAt(0).toUpperCase() + normalized.slice(1);
};

const getSexColor = (sex) => {
    return sex === 'MC' ? 'bg-blue-100 text-blue-700' : 'bg-pink-100 text-pink-700';
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

const getWorkflowDownloadStep = (record) => {
    const step = getCurrentStepIndex(record);
    return Math.max(0, Math.min(step, workflowSteps.length - 1));
};

const downloadEndorsement = (record, stepIndex) => {
    window.open(`/calving/${record.id}/download-endorsement/${stepIndex}`, '_blank');
};

const getStepStatus = (record, stepIndex) => {
    const currentStep = record.endorsement_step ?? 0;
    const hasDoc = getStepDocumentForRecord(record, stepIndex) || hasStepDocument(record, stepIndex);

    if (hasDoc) return 'completed';
    if (stepIndex === currentStep) return 'pending';
    if (stepIndex < currentStep && !hasDoc) return 'pending';
    return 'upcoming';
};

// Check if all 5 endorsement steps have been uploaded
const allStepsUploaded = (record) => {
    if (!record?.endorsement_documents) return false;
    const docs = record.endorsement_documents;
    for (let i = 0; i < 5; i++) {
        if (!docs[i] && !docs[String(i)]) return false;
    }
    return true;
};

// Check if current user can mark the record as completed
const canMarkAsCompleted = (record) => {
    // Only admin can mark as completed
    if (userRole.value !== 'admin') return false;
    // Record must not already be completed
    if (record?.status === 'completed') return false;
    // All 5 steps must be uploaded
    return allStepsUploaded(record);
};

const getMarkCompletedDisabledReason = (record) => {
    if (!record) return 'Mark completed unavailable';
    if (record.status === 'completed') return 'Already completed';
    if (!allStepsUploaded(record)) return 'All 5 endorsement steps must be uploaded first';
    return 'Mark this record as completed';
};

// Check if record is completed
const isRecordCompleted = (record) => {
    return record?.status === 'completed';
};

const canReopenRecord = (record) => {
    if (userRole.value !== 'admin') return false;
    return record?.status === 'completed';
};

const canEditRecord = (record) => {
    if (!record || !canEditCalving.value) return false;
    return record.status !== 'completed';
};

const canDeleteRecord = (record) => {
    if (!record || !canDeleteCalving.value) return false;
    return record.status !== 'completed';
};

const getEditDisabledReason = (record) => {
    if (!record) return 'Edit unavailable';
    if (!canEditCalving.value) return 'You do not have edit permission';
    if (record.status === 'completed') return 'Disabled: record already marked as completed';
    return 'Edit record';
};

const getDeleteDisabledReason = (record) => {
    if (!record) return 'Delete unavailable';
    if (!canDeleteCalving.value) return 'You do not have delete permission';
    if (record.status === 'completed') return 'Disabled: record already marked as completed';
    return 'Delete record';
};

const getReopenState = (record) => {
    const docs = record?.endorsement_documents || {};
    const has0 = !!(docs[0] || docs['0']);
    const has1 = !!(docs[1] || docs['1']);
    const has2 = !!(docs[2] || docs['2']);
    const has3 = !!(docs[3] || docs['3']);
    const has4 = !!(docs[4] || docs['4']);

    if (has0 && !has1) return { status: 'issued', step: 1 };
    if (has0 && has1 && !has2) return { status: 'verified', step: 2 };
    if (has0 && has1 && has2 && !has3) return { status: 'checked', step: 3 };
    if (has0 && has1 && has2 && has3 && !has4) return { status: 'witnessed', step: 4 };
    if (has0 && has1 && has2 && has3 && has4) return { status: 'witnessed', step: 4 };
    return { status: 'pending', step: 0 };
};

// Mark record as completed (admin only)
const markAsCompleted = async (record) => {
    if (!canMarkAsCompleted(record)) return;
    
    if (!confirm('Are you sure you want to mark this record as completed? This will lock all uploads and cannot be undone.')) {
        return;
    }

    isSubmitting.value = true;

    router.post(`/calving/${record.id}/mark-completed`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            if (selectedRecord.value?.id === record.id) {
                selectedRecord.value = {
                    ...selectedRecord.value,
                    status: 'completed',
                    endorsement_step: 5,
                };
            }
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

const reopenRecord = async (record) => {
    if (!canReopenRecord(record)) return;

    if (!confirm('Reopen this record? It will unlock the workflow for upload again.')) {
        return;
    }

    isSubmitting.value = true;

    router.post(`/calving/${record.id}/reopen`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            if (selectedRecord.value?.id === record.id) {
                const reopened = getReopenState(selectedRecord.value);
                selectedRecord.value = {
                    ...selectedRecord.value,
                    status: reopened.status,
                    endorsement_step: reopened.step,
                };
            }
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
</script>

<template>
    <div class="w-full">
        <div class="mb-8 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Calving Records</h1>
                <p class="text-sm text-gray-500 mt-1">Monthly calving records - {{ displayFilterPeriod }}</p>
            </div>
            <div class="flex items-center gap-3">
                <button 
                    v-if="canDeleteCalving"
                    @click="showBulkActions = !showBulkActions" 
                    :class="showBulkActions ? 'bg-[#34554a] text-white' : 'border border-gray-200 text-gray-600 hover:bg-gray-50'"
                    class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors"
                >
                    <Filter class="w-4 h-4" />
                    Bulk Actions
                </button>
                <button
                    @click="exportReport"
                    :disabled="!canExportMonthly"
                    class="flex items-center gap-2 px-4 py-2 border rounded-lg text-sm font-medium transition-colors"
                    :class="canExportMonthly ? 'border-gray-200 text-gray-600 hover:bg-gray-50' : 'border-gray-200 text-gray-400 bg-gray-50 cursor-not-allowed'"
                    :title="canExportMonthly ? 'Export calving records for selected month and unit as CSV' : 'Select Month, Year, and Operational Unit above to enable export'"
                >
                    <Download class="w-4 h-4" />
                    Export
                </button>
                <button v-if="canCreateCalving" @click="goToCreate" class="flex items-center gap-2 bg-[#34554a] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#2a443b] shadow-sm transition-colors">
                    <Plus class="w-4 h-4" />
                    Add Record
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-xl border border-gray-200 flex items-center gap-5 shadow-sm">
                <div class="w-14 h-14 bg-[#34554a]/10 rounded-full flex items-center justify-center">
                    <svg class="w-7 h-7" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <g>
                            <g>
                                <path style="fill:#B2A860;" d="M199.02,131.384l-6.081,91.208c-41.334,4.373-83.215-25.962-105.966-63.471c-0.957-1.571-1.913-3.143-2.801-4.715c-6.832-12.229-11.615-25.073-13.664-37.576c-2.46-14.757-1.435-30.266,2.528-45.091c8.062-30.267,28.421-57.8,57.185-70.85c7.925-3.621,12.366,4.441,7.72,12.708c-0.273,0.478-0.478,0.887-0.752,1.366c-10.931,20.359-19.335,56.433-3.006,81.438c1.093,1.64,2.186,3.212,3.348,4.646c4.099,5.261,8.676,9.565,13.459,13.118C169.232,127.763,190.344,130.085,199.02,131.384z"></path>
                                <path style="fill:#B2A860;" d="M441.492,116.831c-2.392,14.143-8.13,28.695-16.466,42.291c-12.708,20.906-31.359,39.626-52.539,51.241c-16.738,9.292-35.117,14.142-53.427,12.23l-6.08-91.208c6.627-0.957,20.701-2.597,35.185-9.497c4.304-2.05,8.677-4.509,12.844-7.72h0.068c6.081-4.509,11.888-10.316,16.739-17.764c16.67-25.552,7.515-62.718-3.757-82.804c-4.646-8.267-0.205-16.33,7.72-12.708c2.869,1.299,5.67,2.733,8.335,4.373c14.689,8.54,26.713,21.042,35.595,35.527C439.647,63.541,445.727,91.279,441.492,116.831z"></path>
                            </g>
                            <path style="fill:#FEFEFE;" d="M510.359,219.04c0,0-59.986,59.918-111.841,18.516v0.819c-0.068,10.59-1.844,21.726-5.397,32.589c-6.217,19.267-18.105,37.508-36.347,50.148l-15.577,61.898c19.266,5.739,33.272,23.571,33.272,44.681c0,25.689-20.838,46.595-46.595,46.595H184.126c-25.757,0-46.595-20.907-46.595-46.595c0-21.042,13.937-38.874,33.136-44.613c0.068-0.068,0.068-0.068,0.137-0.068l-8.062-32.18l-7.516-29.719c-28.831-19.95-41.744-53.975-41.744-83.557c-32.452,25.893-68.048,12.161-90.184-1.776C10.044,227.443,1.641,219.04,1.641,219.04l60.122-42.223l25.21-17.695l64.017-44.955l8.677-6.081h192.665l8.676,6.081h0.068l63.948,44.955L510.359,219.04z"></path>
                            <path style="fill:#413D3C;" d="M251.559,108.086v208.242c0,19.062-15.441,34.502-34.571,34.502h-54.247l-7.516-29.719c-28.831-19.95-41.744-53.975-41.744-83.557c-32.452,25.893-68.048,12.161-90.184-1.776C10.044,227.443,1.641,219.04,1.641,219.04l60.122-42.223l25.21-17.695l64.017-44.955l8.677-6.081H251.559z"></path>
                            <path style="fill:#413D3C;" d="M374.469,427.691c0,25.689-20.838,46.595-46.595,46.595H184.126c-25.757,0-46.595-20.907-46.595-46.595c0-21.11,14.006-38.943,33.272-44.681h170.393C360.463,388.749,374.469,406.581,374.469,427.691z"></path>
                            <g>
                                <g>
                                    <path style="fill:#45402B;" d="M346.921,248.26h23.82l-7.702,10.449c0.993,2.219,1.566,4.644,1.566,7.21c0,9.766-7.904,17.694-17.684,17.694c-9.751,0-17.664-7.928-17.664-17.694C329.257,256.173,337.169,248.26,346.921,248.26z"></path>
                                </g>
                                <g>
                                    <path style="fill:#838384;" d="M182.733,265.919c0,9.766-7.904,17.694-17.664,17.694c-9.771,0-17.684-7.928-17.684-17.694c0-2.567,0.582-4.991,1.576-7.21l-7.712-10.449h23.82C174.83,248.26,182.733,256.173,182.733,265.919z"></path>
                                </g>
                            </g>
                            <path style="fill:#838384;" d="M312.637,432.1c0,11.036-8.947,19.974-19.984,19.974c-11.026,0-19.964-8.938-19.964-19.974c0-11.032,8.938-19.974,19.964-19.974C303.69,412.126,312.637,421.068,312.637,432.1z"></path>
                            <path style="fill:#838384;" d="M239.3,432.1c0,11.036-8.938,19.974-19.974,19.974c-11.037,0-19.974-8.938-19.974-19.974c0-11.032,8.937-19.974,19.974-19.974C230.362,412.126,239.3,421.068,239.3,432.1z"></path>
                        </g>
                    </svg>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-wide">Total Calves</p>
                    <p class="text-3xl font-black text-gray-900">{{ stats.total || 0 }}</p>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl border border-gray-200 flex items-center gap-5 shadow-sm">
                <div class="w-14 h-14 bg-blue-100 rounded-full flex items-center justify-center">
                    <span class="text-2xl font-bold text-blue-600">♂</span>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-wide">Male Calves</p>
                    <p class="text-3xl font-black text-gray-900">{{ stats.male_calves || 0 }}</p>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl border border-gray-200 flex items-center gap-5 shadow-sm">
                <div class="w-14 h-14 bg-pink-100 rounded-full flex items-center justify-center">
                    <span class="text-2xl font-bold text-pink-600">♀</span>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-wide">Female Calves</p>
                    <p class="text-3xl font-black text-gray-900">{{ stats.female_calves || 0 }}</p>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl border border-gray-200 flex items-center gap-5 shadow-sm">
                <div class="w-14 h-14 bg-[#34554a]/10 rounded-full flex items-center justify-center">
                    <svg class="w-7 h-7" viewBox="0 0 1024 1024" xmlns="http://www.w3.org/2000/svg" fill="#34554a" stroke="#34554a" aria-hidden="true">
                        <path fill="#34554a" d="M512 64a448 448 0 1 1 0 896 448 448 0 0 1 0-896zm-55.808 536.384-99.52-99.584a38.4 38.4 0 1 0-54.336 54.336l126.72 126.72a38.272 38.272 0 0 0 54.336 0l262.4-262.464a38.4 38.4 0 1 0-54.272-54.336L456.192 600.384z"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-wide">Completed</p>
                    <p class="text-3xl font-black text-gray-900">{{ stats.completed ?? stats.approved ?? 0 }}</p>
                </div>
            </div>
        </div>

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
                    <option value="">All Operational Units</option>
                    <option v-for="unit in operatingUnits" :key="unit" :value="unit">{{ unit }}</option>
                </select>
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

        <!-- Bulk Action Toolbar -->
        <div v-if="canDeleteCalving && showBulkActions && hasSelectedRecords" class="bg-red-50 border border-red-200 rounded-xl p-4 mb-4 flex items-center justify-between">
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

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left whitespace-nowrap">
                    <thead>
                        <tr class="bg-[#34554a] text-white text-sm">
                            <th v-if="canDeleteCalving && showBulkActions" class="p-4 font-semibold w-12 text-center">
                                <input 
                                    type="checkbox" 
                                    :checked="allSelected"
                                    @change="toggleSelectAll"
                                    class="w-4 h-4 rounded border-gray-300 text-[#34554a] focus:ring-[#34554a] cursor-pointer"
                                >
                            </th>
                            <th class="p-4 font-semibold w-12">No.</th>
                            <th class="p-4 font-semibold">Date</th>
                            <th class="p-4 font-semibold">Tag No.</th>
                            <th class="p-4 font-semibold">LCC No.</th>
                            <th class="p-4 font-semibold">Sex</th>
                            <th class="p-4 font-semibold">Colour</th>
                            <th class="p-4 font-semibold">Dam Tag</th>
                            <th class="p-4 font-semibold">Condition</th>
                            <th class="p-4 font-semibold">Location</th>
                            <th class="p-4 font-semibold">Status</th>
                            <th class="p-4 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y text-sm text-gray-700">
                        <tr v-for="(record, index) in paginatedRecords" :key="record.id" 
                            :class="[
                                'transition-colors cursor-pointer',
                                isRecordSelected(record.id) ? 'bg-blue-50' : ''
                            ]"
                            @dblclick="viewRecord(record)">
                            <td v-if="canDeleteCalving && showBulkActions" class="p-4 text-center" @click.stop>
                                <input 
                                    type="checkbox" 
                                    :checked="isRecordSelected(record.id)"
                                    :disabled="!canDeleteRecord(record)"
                                    @change="toggleSelectRecord(record)"
                                    class="w-4 h-4 rounded border-gray-300 text-[#34554a] focus:ring-[#34554a] cursor-pointer disabled:cursor-not-allowed disabled:opacity-40"
                                >
                            </td>
                            <td class="p-4 font-medium text-gray-900">{{ (currentPage - 1) * itemsPerPage + index + 1 }}</td>
                            <td class="p-4 text-gray-600">{{ formatDate(record.calving_date) }}</td>
                            <td class="p-4 font-medium text-gray-900">{{ record.tag_no || '-' }}</td>
                            <td class="p-4 font-mono text-xs text-[#34554a] font-bold">{{ record.lcc_running_number || '-' }}</td>
                            <td class="p-4 text-gray-600">{{ record.sex || '-' }}</td>
                            <td class="p-4 text-gray-600">{{ record.colour || '-' }}</td>
                            <td class="p-4 text-gray-600 font-mono text-xs">{{ record.dam_tag_no || '-' }}</td>
                            <td class="p-4 text-gray-600">{{ record.general_condition || '-' }}</td>
                            <td class="p-4 text-gray-600">{{ record.location_block || '-' }} {{ record.location_phase ? '/ ' + record.location_phase : '' }}</td>
                            <td class="p-4">
                                <span v-if="record.status === 'completed'" class="px-2.5 py-1 rounded-full text-xs font-medium bg-[#34554a] text-white">
                                    Completed
                                </span>
                                <span v-else class="text-gray-600">
                                    {{ formatStatusLabel(record.status) }}
                                </span>
                            </td>
                            <td class="p-4 text-right flex justify-end gap-2">
                                <button
                                    v-if="canEditCalving"
                                    @click.stop="editRecord(record)"
                                    :disabled="!canEditRecord(record)"
                                    :title="getEditDisabledReason(record)"
                                    class="w-8 h-8 rounded flex items-center justify-center transition-colors"
                                    :class="canEditRecord(record) ? 'text-gray-400 hover:text-gray-600 hover:bg-gray-100' : 'text-gray-300 cursor-not-allowed'"
                                >
                                    <Edit class="w-4 h-4" />
                                </button>
                                <button v-if="canViewCalving" @click.stop="viewRecord(record)" class="w-8 h-8 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded flex items-center justify-center transition-colors" title="View Details">
                                    <Eye class="w-4 h-4" />
                                </button>
                                <button v-if="canViewCalving" @click.stop="viewWorkflow(record)" class="w-8 h-8 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded flex items-center justify-center transition-colors" title="Workflow">
                                    <FileSignature class="w-4 h-4" />
                                </button>
                                <button 
                                    v-if="canDeleteCalving"
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
                            <td :colspan="showBulkActions ? 12 : 11" class="p-8 text-center text-gray-400 italic">
                                <Baby class="w-8 h-8 mx-auto mb-2 opacity-50" />
                                <p class="text-sm">No calving records found</p>
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
                    Showing <span class="font-semibold text-gray-800">{{ filteredRecords.length > 0 ? (currentPage - 1) * itemsPerPage + 1 : 0 }}-{{ Math.min(currentPage * itemsPerPage, filteredRecords.length) }}</span> of <span class="font-semibold text-gray-800">{{ filteredRecords.length }}</span> records
                </div>
            </div>
        </div>

        <Teleport to="body">
            <div 
                v-if="showModal" 
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-5 overflow-y-auto"
                @click.self="closeModal">
                <div class="bg-white rounded-xl shadow-xl w-full max-w-7xl mx-auto overflow-hidden border border-gray-100 max-h-[90vh] flex flex-col" @click.stop>
                    <div class="flex justify-between items-center p-6 border-b border-gray-100 bg-gray-50 sticky top-0">
                        <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                            <Baby class="w-5 h-5 text-[#34554a]" />
                            <span>{{ modalView === 'workflow' ? 'Calving Record Workflow - ' : 'Calving Record Details - ' }}{{ selectedRecord?.tag_no || '-' }}</span>
                        </h3>
                        <button 
                            type="button"
                            @click="closeModal" 
                            class="text-gray-400 hover:text-gray-600 transition-colors w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-200">
                            ✕
                        </button>
                    </div>
                    <div class="p-6 overflow-y-auto flex-1">
                        <div v-if="modalView === 'workflow'" class="px-6 py-4 bg-gray-50 border-b border-gray-200 mb-6">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <p class="text-xs text-gray-500 font-medium">Current Step</p>
                                    <p class="text-lg font-bold text-gray-900">{{ Math.min(selectedRecord?.endorsement_step ?? 0, workflowSteps.length) }} / {{ workflowSteps.length }}</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button
                                        @click="downloadEndorsement(selectedRecord, getWorkflowDownloadStep(selectedRecord))"
                                        class="flex items-center gap-2 px-3 py-1.5 text-sm text-[#34554a] border border-[#34554a] rounded-lg hover:bg-[#34554a]/10"
                                    >
                                        <FileSignature class="w-4 h-4" />
                                        Download Form
                                    </button>
                                    <button
                                        v-if="userRole === 'admin' && selectedRecord?.status !== 'completed'"
                                        :disabled="!canMarkAsCompleted(selectedRecord) || isSubmitting"
                                        @click="markAsCompleted(selectedRecord)"
                                        :title="getMarkCompletedDisabledReason(selectedRecord)"
                                        class="flex items-center gap-2 px-3 py-1.5 text-sm text-white rounded-lg disabled:opacity-50 disabled:cursor-not-allowed"
                                        :class="canMarkAsCompleted(selectedRecord) && !isSubmitting ? 'bg-[#34554a] hover:bg-[#2a443b]' : 'bg-gray-300'"
                                    >
                                        <CheckCircle class="w-4 h-4" />
                                        {{ isSubmitting ? 'Processing...' : 'Mark Completed' }}
                                    </button>
                                    <button
                                        v-if="canReopenRecord(selectedRecord)"
                                        @click="reopenRecord(selectedRecord)"
                                        :disabled="isSubmitting"
                                        class="flex items-center gap-2 px-3 py-1.5 text-sm text-amber-700 border border-amber-500 rounded-lg hover:bg-amber-50 disabled:opacity-50"
                                    >
                                        <Clock class="w-4 h-4" />
                                        Reopen
                                    </button>
                                </div>
                            </div>

                            <div class="max-w-xl mx-auto flex justify-center gap-2 flex-wrap p-4 bg-gray-50 rounded-xl">
                                <template v-for="(step, index) in workflowSteps" :key="step.id">
                                    <div class="flex flex-col items-center min-w-[70px]">
                                        <div
                                            class="w-10 h-10 rounded-full flex items-center justify-center text-sm mb-1"
                                            :class="isWorkflowStepComplete(selectedRecord, index)
                                                ? 'bg-[#34554a] text-white'
                                                : 'bg-gray-200 text-gray-400'"
                                        >
                                            <CheckCircle v-if="isWorkflowStepComplete(selectedRecord, index)" class="w-5 h-5" />
                                            <span v-else>{{ index + 1 }}</span>
                                        </div>
                                        <span
                                            class="text-[10px] text-center"
                                            :class="isWorkflowStepComplete(selectedRecord, index)
                                                ? 'text-[#34554a] font-semibold'
                                                : 'text-gray-400'"
                                        >{{ step.label }}</span>
                                    </div>
                                    <div v-if="index < workflowSteps.length - 1" class="pb-5 flex items-center" :class="isWorkflowStepComplete(selectedRecord, index) ? 'text-[#34554a]' : 'text-gray-300'">
                                        <ChevronRight class="w-4 h-4" />
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div v-if="modalView === 'details'" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-5">
                                <div class="bg-gray-50 rounded-xl p-4">
                                    <h4 class="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2">
                                        <Baby class="w-4 h-4 text-[#34554a]" />
                                        Calf Information
                                    </h4>
                                    <div class="space-y-2 text-sm">
                                        <div class="flex justify-between py-1.5 border-b border-gray-200">
                                            <span class="text-gray-500">Tag Number</span>
                                            <span class="font-semibold text-gray-900">{{ selectedRecord?.tag_no || '-' }}</span>
                                        </div>
                                        <div class="flex justify-between py-1.5 border-b border-gray-200">
                                            <span class="text-gray-500">LCC No.</span>
                                            <span class="font-semibold text-gray-900">{{ selectedRecord?.lcc_running_number || '-' }}</span>
                                        </div>
                                        <div class="flex justify-between py-1.5 border-b border-gray-200">
                                            <span class="text-gray-500">Cattle No. Request Form</span>
                                            <span class="font-semibold text-gray-900">{{ selectedRecord?.cattle_no_request_form || '-' }}</span>
                                        </div>
                                        <div class="flex justify-between py-1.5 border-b border-gray-200">
                                            <span class="text-gray-500">Date of Birth</span>
                                            <span class="font-semibold text-gray-900">{{ formatDate(selectedRecord?.calving_date) }}</span>
                                        </div>
                                        <div class="flex justify-between py-1.5 border-b border-gray-200">
                                            <span class="text-gray-500">Gender</span>
                                            <span class="font-semibold text-gray-900">{{ selectedRecord?.sex || '-' }}</span>
                                        </div>
                                        <div class="flex justify-between py-1.5 border-b border-gray-200">
                                            <span class="text-gray-500">Coat Colour</span>
                                            <span class="font-semibold text-gray-900">{{ selectedRecord?.colour || '-' }}</span>
                                        </div>
                                        <div class="flex justify-between py-1.5 border-b border-gray-200">
                                            <span class="text-gray-500">Condition</span>
                                            <span class="font-semibold text-gray-900">{{ selectedRecord?.general_condition || '-' }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-gray-50 rounded-xl p-4">
                                    <h4 class="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2">
                                        <Calendar class="w-4 h-4 text-[#34554a]" />
                                        Breeder's Details
                                    </h4>
                                    <div class="space-y-2 text-sm">
                                        <div class="flex justify-between py-1.5 border-b border-gray-200">
                                            <span class="text-gray-500">Dam Tag No.</span>
                                            <span class="font-semibold text-gray-900">{{ selectedRecord?.dam_tag_no || '-' }}</span>
                                        </div>
                                        <div class="flex justify-between py-1.5 border-b border-gray-200">
                                            <span class="text-gray-500">Dam Colour</span>
                                            <span class="font-semibold text-gray-900">{{ selectedRecord?.dam_colour || '-' }}</span>
                                        </div>
                                        <div class="flex justify-between py-1.5 border-b border-gray-200">
                                            <span class="text-gray-500">Sire Tag No.</span>
                                            <span class="font-semibold text-gray-900">{{ selectedRecord?.sire_tag_no || '-' }}</span>
                                        </div>
                                        <div class="flex justify-between py-1.5">
                                            <span class="text-gray-500">Sire Colour</span>
                                            <span class="font-semibold text-gray-900">{{ selectedRecord?.sire_colour || '-' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="space-y-5">
                                <div class="bg-gray-50 rounded-xl p-4">
                                    <h4 class="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2">
                                        <Edit class="w-4 h-4 text-[#34554a]" />
                                        Other Details
                                    </h4>
                                    <div class="space-y-2 text-sm">
                                        <div class="flex justify-between py-1.5 border-b border-gray-200">
                                            <span class="text-gray-500">Worker's Name</span>
                                            <span class="font-semibold text-gray-900">{{ selectedRecord?.worker_name || '-' }}</span>
                                        </div>
                                        <div class="flex justify-between py-1.5 border-b border-gray-200">
                                            <span class="text-gray-500">Operating Unit</span>
                                            <span class="font-semibold text-gray-900">{{ selectedRecord?.operating_unit || '-' }}</span>
                                        </div>
                                        <div class="flex justify-between py-1.5 border-b border-gray-200">
                                            <span class="text-gray-500">Block</span>
                                            <span class="font-semibold text-gray-900">{{ selectedRecord?.location_block || '-' }}</span>
                                        </div>
                                        <div class="flex justify-between py-1.5 border-b border-gray-200">
                                            <span class="text-gray-500">Phase</span>
                                            <span class="font-semibold text-gray-900">{{ selectedRecord?.location_phase || '-' }}</span>
                                        </div>
                                        <div class="flex justify-between py-1.5">
                                            <span class="text-gray-500">Remarks</span>
                                            <span class="font-semibold text-gray-900">{{ selectedRecord?.remarks || '-' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Document Endorsement - Full Width Section -->
                        <div v-if="modalView === 'workflow'" class="mt-6">
                            <WorkflowEndorsementCards
                                :steps="workflowSteps"
                                :get-step-document="(index) => getStepDocumentForRecord(selectedRecord, index)"
                                :can-upload-step="(index) => canUploadStepForRecord(selectedRecord, index)"
                                :can-view-step="(index) => canViewStepForRecord(selectedRecord, index)"
                                :can-download-previous="(index) => canDownloadPreviousForRecord(selectedRecord, index)"
                                :is-submitting="isSubmitting"
                                :user-name="userName"
                                grid-class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4"
                                @upload="submitEndorsementUpload"
                                @view="(index) => downloadStepDocumentForRecord(selectedRecord, index)"
                                @previous="(index) => downloadPreviousStepDocumentForRecord(selectedRecord, index)"
                            />

                            <!-- Completion Status Section -->
                            <div
                                v-if="!isRecordCompleted(selectedRecord)"
                                class="mt-4 p-4 rounded-lg border-2"
                                :class="{
                                'bg-amber-50 border-amber-500': allStepsUploaded(selectedRecord),
                                'bg-gray-50 border-gray-200': !allStepsUploaded(selectedRecord)
                            }"
                            >
                                <!-- All steps uploaded, awaiting admin mark completed -->
                                <div v-if="allStepsUploaded(selectedRecord)" class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-amber-500 rounded-full flex items-center justify-center">
                                        <Clock class="w-5 h-5 text-white" />
                                    </div>
                                    <div>
                                        <p class="font-bold text-amber-800">Ready for completion</p>
                                        <p class="text-sm text-amber-700">
                                            All 5 endorsement steps are uploaded.
                                            <span v-if="userRole === 'admin'"> Use <strong>Mark Completed</strong> above to finalize this record.</span>
                                            <span v-else> Waiting for admin to mark as completed.</span>
                                        </p>
                                    </div>
                                </div>
                                
                                <!-- Not all steps uploaded yet -->
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
                    </div>
                </div>
            </div>
        </Teleport>
        <DeleteConfirmationModal
            :show="showDeleteModal"
            title="Delete Calving Record"
            message="Are you sure you want to delete this calving record"
            :item-name="recordToDelete?.tag_no ? 'Tag ' + recordToDelete.tag_no : 'this record'"
            @close="showDeleteModal = false; recordToDelete = null"
            @confirm="confirmDeleteRecord"
        />
    </div>
</template>
