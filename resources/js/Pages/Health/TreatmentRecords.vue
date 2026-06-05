<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, watch } from 'vue';
import { usePage, router, useForm } from '@inertiajs/vue3';
import {
    ClipboardList, Plus, Search, Filter, Calendar,
    ChevronRight, ChevronLeft, Eye, Edit, Trash2, Clock,
    AlertTriangle, CheckCircle, FilterX, FileText, Download, Upload,
    FileSignature, UserCheck, ClipboardCheck, Save, ChevronDown, Check
} from 'lucide-vue-next';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import WorkflowEndorsementCards from '@/Components/WorkflowEndorsementCards.vue';

defineOptions({
    layout: (h, page) => h(AppLayout, { title: 'Treatment Records', parent: 'Health', parentUrl: '/health' }, () => page)
});

const page = usePage();
const searchQuery = ref('');
const operatingUnitFilter = ref('');
const monthFilter = ref('');
const yearFilter = ref('');
const currentPage = ref(1);
const itemsPerPage = 10;
const showDetailModal = ref(false);
const showWorkflowModal = ref(false);
const monthlyWorkflow = ref(null);
const showEditModal = ref(false);
const selectedTreatment = ref(null);
const isSubmitting = ref(false);
const showDeleteModal = ref(false);
const treatmentToDelete = ref(null);
const editingTreatment = ref(null);
const selectedRecords = ref([]);
const showBulkActions = ref(false);
const showEditCattleDropdown = ref(false);
const editCattleSearchQuery = ref('');

const editForm = useForm({
    cattle_id: '',
    tag_no: '',
    category: '',
    operating_unit: '',
    colour: '',
    date: '',
    symptoms: '',
    treatment_code: '',
    dosage: '',
    remarks: '',
    follow_up_required: false,
    follow_up_date: '',
});

// User info
const userRole = computed(() => page.props.auth?.user?.role || '');
const userName = computed(() => page.props.auth?.user?.name || '');
const userId = computed(() => Number(page.props.auth?.user?.id || 0));
const workflowAssignment = computed(() => page.props.workflowAssignment || null);
const treatmentModulePermissions = computed(() => {
    const perms = page.props.auth?.permissions?.['Treatment Record'];
    if (!Array.isArray(perms)) return ['no-access'];
    return perms
        .map((perm) => String(perm || '').toLowerCase().trim())
        .filter(Boolean);
});
const hasTreatmentPermission = (action) => {
    if (canonicalRole(userRole.value) === 'admin') return true;
    const perms = treatmentModulePermissions.value;
    return perms.includes('full') || perms.includes(action);
};
const canViewTreatment = computed(() => hasTreatmentPermission('view'));
const canCreateTreatment = computed(() => hasTreatmentPermission('create'));
const canEditTreatment = computed(() => hasTreatmentPermission('edit') || hasTreatmentPermission('update'));
const canDeleteTreatment = computed(() => hasTreatmentPermission('delete'));

const monthlyWorkflowStatusMap = computed(() => {
    const rows = Array.isArray(page.props.monthlyWorkflows) ? page.props.monthlyWorkflows : [];
    const map = new Map();

    rows.forEach((workflow) => {
        const year = Number(workflow?.year);
        const month = Number(workflow?.month);
        const unit = String(workflow?.operating_unit || '').trim();
        if (!Number.isInteger(year) || !Number.isInteger(month) || !unit) return;
        const key = `${year}-${month}-${unit.toLowerCase()}`;
        const isCompleted = Boolean(workflow?.is_completed) || String(workflow?.status || '').toLowerCase() === 'completed';
        map.set(key, isCompleted);
    });

    return map;
});

// 3-Step Document workflow steps (for endorsement)
const docWorkflowSteps = [
    { role: 'livestock', label: 'Prepared By', field: 'prepared', role_name: 'Sr. Assistant Livestock' },
    { role: 'supervisor', label: 'Checked By', field: 'checked', role_name: 'Supervisor Livestock' },
    { role: 'livestock manager', label: 'Approved By', field: 'approved', role_name: 'Livestock Manager/OIC' },
];

const normalizeRole = (role) => String(role || '')
    .toLowerCase()
    .replace(/[_-]+/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();

const canonicalRole = (role) => {
    const r = normalizeRole(role);
    if (['livestock manager', 'manager', 'livestock_manager', 'act livestock manager', 'act. livestock manager'].includes(r)) return 'livestock manager';
    if (['supervisor', 'livestock supervisor'].includes(r)) return 'supervisor';
    if (['livestock', 'sr assistant livestock', 'sr. assistant livestock'].includes(r)) return 'livestock';
    if (['admin', 'administrator'].includes(r)) return 'admin';
    return r;
};

const roleMatches = (userRoleValue, stepRoleValue) => canonicalRole(userRoleValue) === canonicalRole(stepRoleValue);

const getAssignedUserIdsForStep = (stepIndex) => {
    const cfg = workflowAssignment.value;
    if (!cfg) return [];

    let ids = [];
    if (stepIndex === 0) {
        ids = Array.isArray(cfg.prepared_by_user_ids) ? cfg.prepared_by_user_ids : [];
        if (!ids.length && cfg.prepared_by_user_id) ids = [cfg.prepared_by_user_id];
    } else if (stepIndex === 1) {
        ids = Array.isArray(cfg.checked_by_user_ids) ? cfg.checked_by_user_ids : [];
        if (!ids.length && cfg.checked_by_user_id) ids = [cfg.checked_by_user_id];
    } else if (stepIndex === 2) {
        ids = Array.isArray(cfg.approved_by_user_ids) ? cfg.approved_by_user_ids : [];
        if (!ids.length && cfg.approved_by_user_id) ids = [cfg.approved_by_user_id];
    }

    return [...new Set((ids || []).map(v => Number(v)).filter(v => Number.isInteger(v) && v > 0))];
};

const canUserActStep = (stepIndex) => {
    if (canonicalRole(userRole.value) === 'admin') return true;
    const assignedUserIds = getAssignedUserIdsForStep(stepIndex);
    return assignedUserIds.length > 0 && assignedUserIds.includes(userId.value);
};

const getUserWorkflowStepIndex = () => {
    if (canonicalRole(userRole.value) === 'admin') return null;
    for (let i = 0; i < docWorkflowSteps.length; i++) {
        if (canUserActStep(i)) return i;
    }
    return null;
};

// Upload form
const docUploadForm = ref({
    name: '',
    date: '',
    signed_document: null,
});

// Get treatments from props (from controller)
const treatmentRecords = computed(() => {
    return page.props.treatments || [];
});
const cattle = computed(() => page.props.cattle || []);
const treatmentCodes = computed(() => page.props.treatmentCodes || []);

const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const years = computed(() => {
    const currentYear = new Date().getFullYear();
    const recordYears = treatmentRecords.value
        .map(r => (r.date ? new Date(r.date).getFullYear() : null))
        .filter(y => Number.isInteger(y));

    const allYears = [...new Set([currentYear, ...recordYears])].sort((a, b) => b - a);
    return allYears.map(String);
});

// Get unique operating units from estates prop and treatments
const operatingUnits = computed(() => {
    // Get estates from props
    const estateNames = (page.props.estates || []).map(e => e.name);
    
    // Also get unique operating units from treatments (in case there are legacy values)
    const treatmentUnits = treatmentRecords.value
        .map(r => r.operating_unit)
        .filter(u => u && u.trim() !== '');
    
    // Combine and deduplicate
    const allUnits = [...new Set([...estateNames, ...treatmentUnits])];
    return allUnits.sort();
});

const editOperatingUnitOptions = computed(() => {
    const fromEstates = (page.props.estates || [])
        .map(e => e?.name)
        .filter(Boolean);
    const fromTreatments = treatmentRecords.value
        .map(r => r?.operating_unit)
        .filter(v => v && String(v).trim() !== '');
    const current = editForm.operating_unit ? [editForm.operating_unit] : [];
    return [...new Set([...fromEstates, ...fromTreatments, ...current])].sort();
});

const filteredEditCattle = computed(() => {
    const query = editCattleSearchQuery.value.trim().toLowerCase();
    if (!query) return cattle.value;

    return cattle.value.filter((item) =>
        (item.tag_no || '').toLowerCase().includes(query) ||
        String(item.id || '').toLowerCase().includes(query) ||
        (item.category || '').toLowerCase().includes(query) ||
        (item.coat_colour || '').toLowerCase().includes(query)
    );
});

const stats = computed(() => ({
    totalTreatments: treatmentRecords.value.length,
    thisMonth: treatmentRecords.value.filter(r => {
        const recordDate = r.date ? new Date(r.date) : null;
        const now = new Date();
        return recordDate && recordDate.getMonth() === now.getMonth() && recordDate.getFullYear() === now.getFullYear();
    }).length,
    pendingApproval: treatmentRecords.value.filter(r => r.status !== 'completed').length,
    completed: treatmentRecords.value.filter(r => r.status === 'completed').length,
}));

const filteredRecords = computed(() => {
    let result = [...treatmentRecords.value];

    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(r =>
            (r.tag_no && r.tag_no.toLowerCase().includes(query)) ||
            (r.symptoms && r.symptoms.toLowerCase().includes(query)) ||
            (r.treatment && r.treatment.toLowerCase().includes(query))
        );
    }

    if (operatingUnitFilter.value) {
        result = result.filter(r => r.operating_unit === operatingUnitFilter.value);
    }

    if (monthFilter.value) {
        const monthIndex = months.indexOf(monthFilter.value);
        result = result.filter(r => {
            if (!r.date) return false;
            const recordDate = new Date(r.date);
            return recordDate.getMonth() === monthIndex;
        });
    }

    if (yearFilter.value) {
        result = result.filter(r => {
            if (!r.date) return false;
            const recordDate = new Date(r.date);
            return recordDate.getFullYear() === Number(yearFilter.value);
        });
    }

    return result;
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
    } else if (currentPage.value <= 3) {
        pages.push(1, 2, 3, '...', totalPages.value);
    } else if (currentPage.value >= totalPages.value - 2) {
        pages.push(1, '...', totalPages.value - 2, totalPages.value - 1, totalPages.value);
    } else {
        pages.push(1, '...', currentPage.value - 1, currentPage.value, currentPage.value + 1, '...', totalPages.value);
    }

    return pages;
});

// My tasks - treatments that need current user's action
const myTasks = computed(() => {
    if (!userRole.value) return [];

    if (userRole.value === 'admin') {
        return treatmentRecords.value.filter(r => r.status !== 'completed');
    }

    return treatmentRecords.value.filter(r => {
        if (r.status === 'completed') return false;
        const currentEndorsementStep = r.endorsement_step || 0;
        if (currentEndorsementStep >= docWorkflowSteps.length) return false;
        return canUserActStep(currentEndorsementStep);
    });
});

// Helper functions for endorsement workflow
const getStepDocument = (treatment, stepIndex) => {
    if (!treatment?.endorsement_documents) return null;
    const docs = treatment.endorsement_documents;
    return docs[stepIndex] || docs[String(stepIndex)] || null;
};

const canUploadStep = (treatment, stepIndex) => {
    if (!treatment) return false;
    if (treatment.status === 'completed') return false;

    const currentStep = treatment.endorsement_step || 0;
    // Admin can upload at any step while record is not completed
    if (!canUserActStep(stepIndex)) return false;

    // Step 2 (manager - last step): can upload/re-upload anytime until completed
    if (stepIndex === 2) {
        return stepIndex <= currentStep;
    }

    // Steps 0-1: can upload at current step, OR re-upload if next hasn't uploaded
    if (currentStep === stepIndex) return true;

    if (stepIndex < currentStep) {
        const nextStepDoc = getStepDocument(treatment, stepIndex + 1);
        return !nextStepDoc;
    }

    return false;
};

const canViewStep = (treatment, stepIndex) => {
    const stepDoc = getStepDocument(treatment, stepIndex);
    if (!stepDoc) return false;

    if (userRole.value === 'admin') return true;

    const userStepIndex = getUserWorkflowStepIndex();
    if (userStepIndex === null) return false;
    return stepIndex === userStepIndex;
};

const canDownloadPrevious = (treatment, stepIndex) => {
    const prevStepDoc = getStepDocument(treatment, stepIndex - 1);
    if (stepIndex === 0) return false;

    if (canonicalRole(userRole.value) === 'admin') {
        return !!prevStepDoc;
    }

    const userStepIndex = getUserWorkflowStepIndex();
    if (userStepIndex === null) return false;
    if (stepIndex !== userStepIndex) return false;
    return !!prevStepDoc;
};

const canDeleteStep = (treatment, stepIndex) => {
    const stepDoc = getStepDocument(treatment, stepIndex);
    if (!stepDoc) return false;
    if (!treatment || treatment.status === 'completed') return false;

    if (userRole.value === 'admin') return true;

    if (!canUserActStep(stepIndex)) return false;

    const nextStepDoc = getStepDocument(treatment, stepIndex + 1);
    if (stepIndex < 2 && nextStepDoc) return false;

    return true;
};

const allStepsUploaded = (treatment) => {
    if (!treatment) return false;
    for (let i = 0; i < 3; i++) {
        if (!getStepDocument(treatment, i)) return false;
    }
    return true;
};

const canMarkAsCompleted = (treatment) => {
    if (!treatment) return false;
    if (userRole.value !== 'admin') return false;
    if (treatment.status === 'completed') return false;
    return allStepsUploaded(treatment);
};

const canUploadMonthlyStep = (stepIndex) => {
    if (!monthlyWorkflow.value) return false;
    if (monthlyWorkflow.value.status === 'completed' || monthlyWorkflow.value.is_completed) return false;

    const currentStep = monthlyWorkflow.value.endorsement_step || 0;
    if (!canUserActStep(stepIndex)) return false;

    const nextDoc = getMonthlyStepDocument(stepIndex + 1);
    if (stepIndex === 2) {
        return stepIndex <= currentStep;
    }

    return stepIndex === currentStep || (stepIndex < currentStep && !nextDoc);
};

const canViewMonthlyStep = (stepIndex) => {
    if (!getMonthlyStepDocument(stepIndex)) return false;
    if (userRole.value === 'admin') return true;

    const userStepIndex = getUserWorkflowStepIndex();
    if (userStepIndex === null) return false;
    return stepIndex === userStepIndex || stepIndex === userStepIndex - 1;
};

const canDownloadMonthlyPrevious = (stepIndex) => {
    if (stepIndex === 0) return false;
    if (!getMonthlyStepDocument(stepIndex - 1)) return false;
    if (userRole.value === 'admin') return true;

    const userStepIndex = getUserWorkflowStepIndex();
    if (userStepIndex === null) return false;
    return stepIndex === userStepIndex;
};

const canDeleteMonthlyStep = (stepIndex) => {
    const stepDoc = getMonthlyStepDocument(stepIndex);
    if (!stepDoc || !monthlyWorkflow.value) return false;
    if (monthlyWorkflow.value.status === 'completed' || monthlyWorkflow.value.is_completed) return false;

    if (userRole.value === 'admin') return true;
    if (!canUserActStep(stepIndex)) return false;
    const nextDoc = getMonthlyStepDocument(stepIndex + 1);
    if (stepIndex < 2 && nextDoc) return false;
    return true;
};

const allMonthlyStepsUploaded = computed(() => {
    return !!(getMonthlyStepDocument(0) && getMonthlyStepDocument(1) && getMonthlyStepDocument(2));
});

const isBulkSelectable = (record) => canDeleteTreatment.value && record?.status !== 'completed';

const hasSelectedRecords = computed(() => selectedRecords.value.length > 0);

const allSelected = computed(() => {
    const selectableRecords = paginatedRecords.value.filter(isBulkSelectable);
    if (selectableRecords.length === 0) return false;
    return selectableRecords.every(record => selectedRecords.value.includes(record.id));
});

// Watch for changes in treatments and update selectedTreatment
watch(treatmentRecords, (newRecords) => {
    if (selectedTreatment.value) {
        const updated = newRecords.find(r => r.id === selectedTreatment.value.id);
        if (updated) {
            selectedTreatment.value = updated;
        }
    }

    const validIds = new Set(newRecords.map(record => record.id));
    selectedRecords.value = selectedRecords.value.filter(id => validIds.has(id));
}, { deep: true });

watch(showBulkActions, (visible) => {
    if (!visible) {
        selectedRecords.value = [];
    }
});

// Actions
const viewRecord = (record) => {
    selectedTreatment.value = record;
    docUploadForm.value = {
        name: userName.value,
        date: new Date().toISOString().split('T')[0],
        signed_document: null,
    };
    showDetailModal.value = true;
};

const closeModal = () => {
    showDetailModal.value = false;
    selectedTreatment.value = null;
    docUploadForm.value = {
        name: '',
        date: '',
        signed_document: null,
    };
};

const closeWorkflow = () => {
    showWorkflowModal.value = false;
    monthlyWorkflow.value = null;
    docUploadForm.value = {
        name: '',
        date: '',
        signed_document: null,
    };
};

const handleDocFileUpload = (event) => {
    const file = event.target.files[0];
    if (file) {
        const maxBytes = 20 * 1024 * 1024; // 20MB (matches backend max:20480 KB)
        if (file.size > maxBytes) {
            alert('File too large. Maximum allowed size is 20MB.');
            event.target.value = '';
            docUploadForm.value.signed_document = null;
            return;
        }
        docUploadForm.value.signed_document = file;
    }
};

const submitDocUpload = (stepIndex) => {
    if (isSubmitting.value) return;
    const scope = getMonthlyScopePayload();
    if (!scope) {
        alert('Please select Operating Unit, Month, and Year first.');
        return;
    }
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
    formData.append('year', String(scope.year));
    formData.append('month', String(scope.month));
    formData.append('operating_unit', scope.operating_unit);

    router.post('/health/treatment/monthly-workflow/upload', formData, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            docUploadForm.value.signed_document = null;
            isSubmitting.value = false;
            refreshMonthlyWorkflow();
        },
        onError: (errors) => {
            console.error('Upload failed:', errors);
            const firstMessage = errors?.error
                || errors?.signed_document
                || errors?.step_index
                || errors?.name
                || errors?.date
                || Object.values(errors || {})[0]
                || 'Failed to upload document. Please try again.';
            alert(Array.isArray(firstMessage) ? firstMessage[0] : firstMessage);
            isSubmitting.value = false;
        }
    });
};

const submitMonthlyEndorsementUpload = ({ stepIndex, file, name, date }) => {
    docUploadForm.value.name = name || userName.value;
    docUploadForm.value.date = date || new Date().toISOString().split('T')[0];
    docUploadForm.value.signed_document = file;
    submitDocUpload(stepIndex);
};

const downloadStepDocument = (stepIndex) => {
    const scope = getMonthlyScopePayload();
    if (!scope) return;
    const params = new URLSearchParams({
        year: String(scope.year),
        month: String(scope.month),
        operating_unit: scope.operating_unit,
    });
    window.open(`/health/treatment/monthly-workflow/download/${stepIndex}?${params.toString()}`, '_blank');
};

const downloadPreviousStepDocument = (stepIndex) => {
    if (stepIndex > 0) {
        downloadStepDocument(stepIndex - 1);
    }
};

const downloadEndorsementForm = () => {
    exportRecords();
};

const markAsCompleted = () => {
    const scope = getMonthlyScopePayload();
    if (!scope) return;
    if (!confirm('Mark this monthly workflow as completed? This will lock uploads and records for this period.')) {
        return;
    }

    router.post('/health/treatment/monthly-workflow/mark-completed', scope, {
        preserveScroll: true,
        onSuccess: () => {
            refreshMonthlyWorkflow();
        },
        onError: (errors) => {
            console.error('Mark as completed failed:', errors);
            alert('Failed to mark treatment as completed. Please try again.');
        }
    });
};

const deleteEndorsementStep = (stepIndex) => {
    const scope = getMonthlyScopePayload();
    if (!scope) return;
    if (!confirm('Delete this endorsement document?')) return;

    const params = new URLSearchParams({
        year: String(scope.year),
        month: String(scope.month),
        operating_unit: scope.operating_unit,
    });

    router.delete(`/health/treatment/monthly-workflow/${stepIndex}?${params.toString()}`, {
        preserveScroll: true,
        onSuccess: () => {
            refreshMonthlyWorkflow();
        },
        onError: (errors) => {
            console.error('Delete endorsement failed:', errors);
            alert('Failed to delete endorsement. Please try again.');
        }
    });
};

const reopenMonthlyWorkflow = () => {
    const scope = getMonthlyScopePayload();
    if (!scope) return;
    if (!confirm('Reopen this monthly workflow?')) return;

    router.post('/health/treatment/monthly-workflow/reopen', scope, {
        preserveScroll: true,
        onSuccess: () => {
            refreshMonthlyWorkflow();
        },
        onError: (errors) => {
            console.error('Reopen monthly workflow failed:', errors);
            alert('Failed to reopen monthly workflow.');
        }
    });
};

const openEditModal = (record) => {
    editingTreatment.value = record;
    showEditCattleDropdown.value = false;
    editCattleSearchQuery.value = '';
    const matchedCattle = cattle.value.find(c => c.id == record.cattle_id || c.tag_no === record.tag_no);
    
    editForm.cattle_id = matchedCattle ? matchedCattle.id : (record.cattle_id || '');
    editForm.tag_no = matchedCattle?.tag_no || record.cattle?.tag_no || record.tag_no || '';
    editForm.category = matchedCattle?.category || record.cattle?.category || record.category || '';
    
    let unitName = record.operating_unit || '';
    if (matchedCattle) {
        let herdName = matchedCattle.herd || '';
        if (!herdName && matchedCattle.location_block) {
            const unit = page.props.estates?.find(u => 
                (u.pasture_blocks || u.pastureBlocks || u.blocks || []).some(b => b.name === matchedCattle.location_block)
            );
            if (unit) {
                herdName = unit.name;
            } else {
                herdName = matchedCattle.location_block;
            }
        }
        unitName = herdName || unitName;
    }
    editForm.operating_unit = unitName;
    
    editForm.colour = matchedCattle?.coat_colour || record.cattle?.coat_colour || record.colour || '';
    editForm.date = record.date ? new Date(record.date).toISOString().split('T')[0] : '';
    editForm.symptoms = record.symptoms || '';
    editForm.treatment_code = record.treatment_code || '';
    editForm.dosage = record.dosage || '';
    editForm.remarks = record.remarks || '';
    editForm.follow_up_required = !!record.follow_up_required;
    editForm.follow_up_date = record.follow_up_date ? new Date(record.follow_up_date).toISOString().split('T')[0] : '';
    
    if (matchedCattle) {
        onEditCattleChange();
    }
    editForm.clearErrors();
    showEditModal.value = true;
};

const closeEditModal = () => {
    showEditModal.value = false;
    showEditCattleDropdown.value = false;
    editCattleSearchQuery.value = '';
    editingTreatment.value = null;
    editForm.reset();
    editForm.clearErrors();
};

const submitEditForm = () => {
    if (!editingTreatment.value) return;

    if (!editForm.cattle_id) {
        alert('Please select a cattle');
        return;
    }
    if (!editForm.date) {
        alert('Please select a date');
        return;
    }
    if (!editForm.symptoms) {
        alert('Please enter symptoms observed');
        return;
    }
    if (!editForm.dosage) {
        alert('Please enter dosage');
        return;
    }

    editForm.transform((data) => ({
        ...data,
        follow_up_date: data.follow_up_required ? data.follow_up_date : null,
        _method: 'PUT',
    })).post(`/health/treatment/${editingTreatment.value.id}`, {
        preserveScroll: true,
        onSuccess: () => closeEditModal(),
    });
};

const onEditCattleChange = () => {
    const selectedCattle = cattle.value.find(c => c.id == editForm.cattle_id);
    if (selectedCattle) {
        editForm.tag_no = selectedCattle.tag_no || '';
        editForm.category = selectedCattle.category || '';
        editForm.colour = selectedCattle.coat_colour || '';
        
        let unitName = editForm.operating_unit || '';
        let herdName = selectedCattle.herd || '';
        if (!herdName && selectedCattle.location_block) {
            const unit = page.props.estates?.find(u => 
                (u.pasture_blocks || u.pastureBlocks || u.blocks || []).some(b => b.name === selectedCattle.location_block)
            );
            if (unit) {
                herdName = unit.name;
            } else {
                herdName = selectedCattle.location_block;
            }
        }
        editForm.operating_unit = herdName || unitName;
    } else {
        editForm.tag_no = '';
        editForm.category = '';
        editForm.colour = '';
        editForm.operating_unit = '';
    }
};

const selectEditCattleItem = (item) => {
    editForm.cattle_id = item.id;
    onEditCattleChange();
    showEditCattleDropdown.value = false;
    editCattleSearchQuery.value = '';
};

const clearEditCattleSelection = () => {
    editForm.cattle_id = '';
    editForm.tag_no = '';
    editForm.category = '';
    editForm.colour = '';
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

const promptDelete = (record) => {
    treatmentToDelete.value = record;
    showDeleteModal.value = true;
};

const canEditRecord = (record) => {
    if (!canEditTreatment.value) return false;
    return !isRecordLockedByMonthlyWorkflow(record);
};

const canDeleteRecord = (record) => {
    return canDeleteTreatment.value && !isRecordLockedByMonthlyWorkflow(record);
};

const isRecordLockedByMonthlyWorkflow = (record) => {
    if (!record?.date || !record?.operating_unit) return false;
    const date = new Date(record.date);
    if (Number.isNaN(date.getTime())) return false;
    const key = `${date.getFullYear()}-${date.getMonth() + 1}-${String(record.operating_unit).trim().toLowerCase()}`;
    return monthlyWorkflowStatusMap.value.get(key) === true;
};

const getEditActionTooltip = (record) => {
    if (canEditRecord(record)) return 'Edit';
    return 'Status marked as completed, cannot make changes.';
};

const getDeleteActionTooltip = (record) => {
    if (canDeleteRecord(record)) return 'Delete';
    return 'Status marked as completed, cannot make changes.';
};

const confirmDelete = () => {
    if (!treatmentToDelete.value) return;

    router.delete(`/health/treatment/${treatmentToDelete.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
            treatmentToDelete.value = null;
        },
        onError: () => {
            showDeleteModal.value = false;
            treatmentToDelete.value = null;
        }
    });
};

const toggleSelectAll = () => {
    const selectableIds = paginatedRecords.value.filter(isBulkSelectable).map(record => record.id);

    if (allSelected.value) {
        selectedRecords.value = selectedRecords.value.filter(id => !selectableIds.includes(id));
        return;
    }

    selectableIds.forEach(id => {
        if (!selectedRecords.value.includes(id)) {
            selectedRecords.value.push(id);
        }
    });
};

const toggleSelectRecord = (record) => {
    if (!isBulkSelectable(record)) return;

    const index = selectedRecords.value.indexOf(record.id);
    if (index > -1) {
        selectedRecords.value.splice(index, 1);
        return;
    }

    selectedRecords.value.push(record.id);
};

const isRecordSelected = (recordId) => selectedRecords.value.includes(recordId);

const clearSelection = () => {
    selectedRecords.value = [];
};

const bulkDelete = () => {
    if (selectedRecords.value.length === 0) return;

    const message = `Are you sure you want to delete ${selectedRecords.value.length} treatment record(s)? This action cannot be undone.`;
    if (!confirm(message)) return;

    router.post('/health/treatment/bulk-delete', {
        ids: selectedRecords.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            selectedRecords.value = [];
            showBulkActions.value = false;
        },
        onError: (errors) => {
            alert(errors?.error || 'Failed to delete selected records. Please try again.');
        },
    });
};

const clearFilters = () => {
    searchQuery.value = '';
operatingUnitFilter.value = '';
    monthFilter.value = '';
    yearFilter.value = '';
    currentPage.value = 1;
};

watch([searchQuery, operatingUnitFilter, monthFilter, yearFilter], () => {
    currentPage.value = 1;
});

watch(totalPages, (newTotal) => {
    if (newTotal === 0) {
        currentPage.value = 1;
        return;
    }
    if (currentPage.value > newTotal) {
        currentPage.value = newTotal;
    }
});

const exportRecords = () => {
    const monthIndex = months.indexOf(monthFilter.value);
    if (!operatingUnitFilter.value || !yearFilter.value || monthIndex < 0) {
        alert('Please select Operating Unit, Month, and Year before exporting.');
        return;
    }

    const params = new URLSearchParams({
        year: yearFilter.value,
        month: String(monthIndex + 1),
        operating_unit: operatingUnitFilter.value,
    });

    window.open(`/health/treatment/export/report?${params.toString()}`, '_blank');
};

const canExportMonthly = computed(() => {
    const monthIndex = months.indexOf(monthFilter.value);
    return Boolean(operatingUnitFilter.value && yearFilter.value && monthIndex >= 0);
});

const getMonthlyScopePayload = () => {
    const monthIndex = months.indexOf(monthFilter.value);
    if (!operatingUnitFilter.value || !yearFilter.value || monthIndex < 0) {
        return null;
    }

    return {
        year: Number(yearFilter.value),
        month: monthIndex + 1,
        operating_unit: operatingUnitFilter.value,
    };
};

const openMonthlyWorkflow = async () => {
    const scope = getMonthlyScopePayload();
    if (!scope) {
        alert('Please select Operating Unit, Month, and Year before opening workflow.');
        return;
    }

    try {
    const params = new URLSearchParams({
        year: String(scope.year),
        month: String(scope.month),
        operating_unit: scope.operating_unit,
        t: Date.now(),
    });
        const response = await fetch(`/health/treatment/monthly-workflow?${params.toString()}`);
        if (!response.ok) {
            throw new Error(`Failed with status ${response.status}`);
        }
        const data = await response.json();
        monthlyWorkflow.value = data.workflow || null;
        docUploadForm.value = {
            name: userName.value,
            date: new Date().toISOString().split('T')[0],
            signed_document: null,
        };
        showWorkflowModal.value = true;
    } catch (error) {
        console.error('Failed to load monthly workflow:', error);
        alert('Failed to load monthly workflow.');
    }
};

const refreshMonthlyWorkflow = async () => {
    if (!showWorkflowModal.value) return;
    const scope = getMonthlyScopePayload();
    if (!scope) return;

    const params = new URLSearchParams({
        year: String(scope.year),
        month: String(scope.month),
        operating_unit: scope.operating_unit,
        t: Date.now(),
    });
    const response = await fetch(`/health/treatment/monthly-workflow?${params.toString()}`);
    if (response.ok) {
        const data = await response.json();
        monthlyWorkflow.value = data.workflow || monthlyWorkflow.value;
    }
};

const getMonthlyStepDocument = (stepIndex) => {
    const docs = monthlyWorkflow.value?.endorsement_documents || {};
    return docs[stepIndex] || docs[String(stepIndex)] || null;
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric' });
};

const formatStatusLabel = (status) => {
    if (!status) return 'Pending';
    const normalized = String(status).replace(/_/g, ' ').toLowerCase();
    return normalized.charAt(0).toUpperCase() + normalized.slice(1);
};

const detailFieldConfig = [
    { key: 'treatment_no', label: 'Treatment No.' },
    { key: 'tag_no', label: 'Tag Number' },
    { key: 'cattle_id', label: 'Cattle ID' },
    { key: 'category', label: 'Category' },
    { key: 'operating_unit', label: 'Operating Unit' },
    { key: 'colour', label: 'Coat Colour' },
    { key: 'date', label: 'Date', type: 'date' },
    { key: 'week', label: 'Week' },
    { key: 'symptoms', label: 'Symptoms' },
    { key: 'treatment', label: 'Treatment' },
    { key: 'treatment_code', label: 'Treatment Code' },
    { key: 'dosage', label: 'Dosage' },
    { key: 'remarks', label: 'Remarks' },
    { key: 'follow_up_required', label: 'Follow Up Required', type: 'boolean' },
    { key: 'follow_up_date', label: 'Follow Up Date', type: 'date' },
    { key: 'follow_up_done', label: 'Follow Up Done', type: 'boolean' },
    { key: 'status', label: 'Status', type: 'status', always: true },
    { key: 'rejection_reason', label: 'Rejection Reason' },
];

const hasFilledValue = (value, field) => {
    if (field?.always) return true;
    if (field?.type === 'boolean') return value === true;
    if (value === null || value === undefined) return false;
    if (typeof value === 'string') return value.trim() !== '';
    return true;
};

const formatDetailValue = (value, field) => {
    if (field?.type === 'boolean') return value ? 'Yes' : 'No';
    if (field?.type === 'status') return formatStatusLabel(value);
    if (field?.type === 'date') return formatDate(value);
    if (field?.type === 'datetime') {
        if (!value) return '-';
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return value;
        return date.toLocaleString('en-GB', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    }
    return value;
};

const detailFields = computed(() => {
    const record = selectedTreatment.value;
    if (!record) return [];

    return detailFieldConfig
        .map((field) => {
            const rawValue = typeof field.get === 'function' ? field.get(record) : record[field.key];
            return {
                label: field.label,
                value: formatDetailValue(rawValue, field),
                rawValue,
                field,
            };
        })
        .filter((item) => hasFilledValue(item.rawValue, item.field));
});
</script>

<template>
    <div class="w-full">
        <div class="mb-8 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Treatment Records</h1>
                <p class="text-sm text-gray-500 mt-1">Monthly treatment records for all cattle with approval workflow.</p>
            </div>
            <div class="flex items-center gap-3">
                <button
                    v-if="canDeleteTreatment"
                    @click="showBulkActions = !showBulkActions"
                    :class="showBulkActions ? 'bg-[#34554a] text-white' : 'border border-gray-200 text-gray-600 hover:bg-gray-50'"
                    class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors"
                >
                    <Filter class="w-4 h-4" />
                    Bulk Actions
                </button>
<button
                    @click="openMonthlyWorkflow"
                    :disabled="!canExportMonthly"
                    class="flex items-center gap-2 px-4 py-2 border rounded-lg text-sm font-medium transition-colors"
                    :class="canExportMonthly ? 'border-gray-200 text-gray-600 hover:bg-gray-50' : 'border-gray-200 text-gray-400 bg-gray-50 cursor-not-allowed'"
                    :title="canExportMonthly ? 'View and manage monthly endorsement workflow' : 'Select Operating Unit, Month, and Year first'"
                >
                    <FileSignature class="w-4 h-4" />
                    Workflow
                </button>
                <button
                    @click="exportRecords"
                    :disabled="!canExportMonthly"
                    class="flex items-center gap-2 px-4 py-2 border rounded-lg text-sm font-medium transition-colors"
                    :class="canExportMonthly ? 'border-gray-200 text-gray-600 hover:bg-gray-50' : 'border-gray-200 text-gray-400 bg-gray-50 cursor-not-allowed'"
                    :title="canExportMonthly ? 'Export treatment records for selected month and unit as PDF' : 'Select Operating Unit, Month, and Year first'"
                >
                    <Download class="w-4 h-4" />
                    Export PDF
                </button>
                <button v-if="canCreateTreatment" @click="$inertia.visit('/health/treatment/create')" class="flex items-center gap-2 bg-[#34554a] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#2a443b] shadow-sm transition-colors">
                    <Plus class="w-4 h-4" />
                    Record Treatment
                </button>
            </div>
        </div>

        <div v-if="showBulkActions && hasSelectedRecords" class="bg-red-50 border border-red-200 rounded-xl p-4 mb-4 flex items-center justify-between">
            <div class="text-sm font-semibold text-red-800">
                {{ selectedRecords.length }} record(s) selected
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

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-xl border border-gray-200 flex items-center gap-5 shadow-sm">
                <div class="w-14 h-14 bg-[#34554a]/10 rounded-full flex items-center justify-center">
                    <svg class="w-10 h-10" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <circle cx="256" cy="256" r="256" fill="#21D0C3"></circle>
                        <path d="M270.251,267.525v25.828h-16.555v-25.828c2.742,0.06,5.5,0.094,8.279,0.094 C264.753,267.619,267.51,267.585,270.251,267.525z" fill="#666666"></path>
                        <path d="M261.975,37.398c63.846,0,115.609,51.763,115.609,115.61c0,63.846-51.763,115.606-115.609,115.606 c-63.847,0-115.608-51.76-115.608-115.606C146.367,89.161,198.127,37.398,261.975,37.398z" fill="#666666"></path>
                        <path d="M261.975,52.378c55.574,0,100.629,45.054,100.629,100.629S317.55,253.636,261.975,253.636 s-100.629-45.054-100.629-100.629S206.4,52.378,261.975,52.378z" fill="#21D0C3"></path>
                        <circle cx="261.978" cy="153.011" r="63.65" fill="#FEFEFE"></circle>
                        <path d="M249.297,467.739h25.349c3.526,0,6.41-2.958,6.41-6.569V306.041c0-3.612-2.901-6.57-6.41-6.57 h-25.348c-3.507,0-6.407,2.975-6.407,6.57V461.17C242.89,464.764,245.774,467.739,249.297,467.739z" fill="#666666"></path>
                        <path d="M281.055,311.209v-11.287c0-3.608-2.888-6.569-6.408-6.569h-25.35c-3.518,0-6.408,2.963-6.408,6.569 v11.287H281.055z" fill="#FEFEFE"></path>
                        <path d="M281.055,311.209v-11.285c0-3.456-2.646-6.315-5.964-6.553v17.838L281.055,311.209L281.055,311.209z M248.851,293.371c-3.314,0.239-5.962,3.098-5.962,6.553v11.285h5.962V293.371z" fill="#ECF0F1"></path>
                        <path d="M164.885,436.925l-66.769,3.199L54.67,359.648l109.757-6.034 c16.41-22.686,27.606-44.539,78.463-42.683l-0.04,10.661l55.179-0.587c19.001-0.903,21.827,30.366,1.24,31.342l-2.83,0.246 l-0.454,2.576l7.753-0.566c15.791-1.412,18.127,28.596,0.162,30.2l-13.492,1.867l-0.316,2.196l8.678-1.121 c14.533-1.316,14.87,28.326-0.519,30.085l-10.369,1.711l0.028,1.596l4.243-0.045c14.429-0.599,12.524,25.904-1.076,27.352 c-16.527,1.766-18.24,1.658-35.184,2.864C231.906,452.147,175.334,457.707,164.885,436.925L164.885,436.925z" fill="#FED298"></path>
                        <path d="M164.288,437.085l-86.13,3.038c-1.804-1.744-3.581-3.515-5.334-5.311l93.418-2.419 c2.46-0.371,3.823,1.423,4.816,2.322c11.301,26.383,104.827,10.463,125.635,10.805l0.569,0.002 c-1.667,1.582-3.736,2.659-6.185,2.918l-0.056,0.007C264,451.034,180.618,463.534,164.288,437.085z" fill="#F0B97D"></path>
                        <path d="M146.271,349.155l1.892,94.311l-22.108,0.102l-1.887-95.096L146.271,349.155z" fill="#FFFFFF"></path>
                        <path d="M132.963,345.1l1.407,101.826l-47.943,0.838c-35.358-31.29-61.99-72.206-75.812-118.641 L132.963,345.1L132.963,345.1z" fill="#0F7986"></path>
                        <path d="M121.353,436.771c0.034,3.251-2.511,5.909-5.677,5.942c-3.168,0.032-5.762-2.579-5.786-5.826 c-0.033-3.247,2.509-5.91,5.675-5.943C118.734,430.915,121.326,433.522,121.353,436.771z" fill="#FAD24D"></path>
                        <path d="M105.717,437.283c0.034,3.251-2.511,5.909-5.677,5.942c-3.168,0.032-5.762-2.579-5.786-5.826 c-0.033-3.247,2.509-5.91,5.675-5.943C103.098,431.426,105.69,434.034,105.717,437.283z" fill="#FAD24D"></path>
                        <path d="M252.417,251.967c-50.419-3.071-90.39-46.199-90.39-98.96s39.972-95.89,90.39-98.96 c-5.311,1.302-10.452,3.049-15.391,5.197c-37.74,13.384-64.863,50.304-64.863,93.763s27.123,80.379,64.863,93.763 C241.965,248.917,247.107,250.664,252.417,251.967z" fill="#ECF0F1"></path>
                        <path d="M276.788,245.082c44.615-2.856,79.985-42.984,79.985-92.076s-35.37-89.22-79.985-92.076 c1.029,0.266,2.051,0.551,3.066,0.854c40.99,6.622,72.436,44.934,72.436,91.222s-31.446,84.6-72.436,91.222 C278.839,244.532,277.817,244.817,276.788,245.082z" fill="#ECF0F1"></path>
                        <path d="M250.701,111.492h22.547v30.241h30.241v22.547h-30.241v30.241h-22.547V164.28H220.46v-22.547h30.241 L250.701,111.492L250.701,111.492z" fill="#FF5B62"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-wide">Total Treatments</p>
                    <p class="text-3xl font-black text-gray-900">{{ stats.totalTreatments }}</p>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl border border-gray-200 flex items-center gap-5 shadow-sm">
                <div class="w-14 h-14 bg-blue-100 rounded-full flex items-center justify-center">
                    <svg class="w-10 h-10" viewBox="0 0 120 120" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M24.9,100.7H95c3.9,0,7-3.3,7-7.3V31.1c0-4.1-3.2-7.3-7-7.3H24.9c-3.9,0-7,3.3-7,7.3v62.2 C17.9,97.4,21,100.7,24.9,100.7z" fill="#F4F4F4"></path>
                        <path d="M102.1,30.8v15.1H17.9V30.8c0-3.9,3.1-7,6.7-7h70.7C99.1,23.7,102.1,26.9,102.1,30.8z" fill="#7A9BFF"></path>
                        <path d="M38.9,31.2c-2,0-3.6,1.6-3.6,3.6s1.6,3.6,3.6,3.6s3.6-1.6,3.6-3.6S40.9,31.2,38.9,31.2z" fill="#6588E0"></path>
                        <path d="M81.1,31.2c-2,0-3.6,1.6-3.6,3.6s1.6,3.6,3.6,3.6c2,0,3.6-1.6,3.6-3.6S83.1,31.2,81.1,31.2z" fill="#6588E0"></path>
                        <path d="M39.9,19.3h-2c-0.6,0-1.1,0.5-1.1,1.1v13.9c0,0.6,0.5,1.1,1.1,1.1h2c0.6,0,1.1-0.5,1.1-1.1V20.5 C41.1,19.8,40.5,19.3,39.9,19.3z" fill="#FCF0DE"></path>
                        <path d="M82.1,19.3h-2c-0.6,0-1.1,0.5-1.1,1.1v13.9c0,0.6,0.5,1.1,1.1,1.1h2c0.6,0,1.1-0.5,1.1-1.1V20.5 C83.2,19.8,82.7,19.3,82.1,19.3z" fill="#FCF0DE"></path>
                        <circle cx="60" cy="74.7" r="16.4" fill="#83E598"></circle>
                        <path d="M58.1,82.1L58.1,82.1c-0.8,0-1.3-0.3-1.8-0.8l-6.6-7.4c-0.8-1-0.8-2.5,0.2-3.3c1-0.8,2.5-0.8,3.3,0.2l5,5.6 l8.6-8.3c0.9-0.9,2.4-0.8,3.3,0.1s0.8,2.4-0.1,3.3L59.8,81.5C59.3,81.9,58.8,82.1,58.1,82.1z" fill="#FFF2F2"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-wide">This Month</p>
                    <p class="text-3xl font-black text-gray-900">{{ stats.thisMonth }}</p>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl border border-gray-200 flex items-center gap-5 shadow-sm">
                <div class="w-14 h-14 bg-amber-100 rounded-full flex items-center justify-center">
                    <svg class="w-7 h-7" viewBox="0 0 1024 1024" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M182.99 146.2h585.14v402.29h73.14V73.06H109.84v877.71H512v-73.14H182.99z" fill="#000000" />
                        <path d="M256.13 219.34h438.86v73.14H256.13zM256.13 365.63h365.71v73.14H256.13zM256.13 511.91h219.43v73.14H256.13zM731.55 585.06c-100.99 0-182.86 81.87-182.86 182.86s81.87 182.86 182.86 182.86c100.99 0 182.86-81.87 182.86-182.86s-81.86-182.86-182.86-182.86z m0 292.57c-60.5 0-109.71-49.22-109.71-109.71 0-60.5 49.22-109.71 109.71-109.71 60.5 0 109.71 49.22 109.71 109.71 0.01 60.49-49.21 109.71-109.71 109.71z" fill="#000000" />
                        <path d="M758.99 692.08h-54.86v87.27l69.39 68.76 38.61-38.96-53.14-52.66z" fill="#ff2d2d" />
                    </svg>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-wide">Pending Approval</p>
                    <p class="text-3xl font-black text-gray-900">{{ stats.pendingApproval }}</p>
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
                    <p class="text-3xl font-black text-gray-900">{{ stats.completed }}</p>
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
                        placeholder="Search by tag, symptoms, treatment..."
                        class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-200 outline-none text-sm focus:ring-2 focus:ring-[#34554a]"
                    >
                </div>
                <select
                    v-model="operatingUnitFilter"
                    class="px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]"
                >
                    <option value="">All Operating Units</option>
                    <option v-for="unit in operatingUnits" :key="unit" :value="unit">{{ unit }}</option>
                </select>
                <select
                    v-model="monthFilter"
                    class="px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]"
                >
                    <option value="">All Months</option>
                    <option v-for="month in months" :key="month" :value="month">{{ month }}</option>
                </select>
                <select
                    v-model="yearFilter"
                    class="px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]"
                >
                    <option value="">All Years</option>
                    <option v-for="year in years" :key="year" :value="year">{{ year }}</option>
                </select>
                <button
                    v-if="searchQuery || operatingUnitFilter || monthFilter || yearFilter"
                    @click="clearFilters"
                    class="px-3 py-2 text-gray-500 text-sm hover:text-gray-700 font-medium flex items-center gap-1"
                >
                    <FilterX class="w-4 h-4" />
                    Clear
                </button>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left whitespace-nowrap">
                    <thead>
                        <tr class="bg-[#34554a] text-white text-sm">
                            <th v-if="showBulkActions" class="p-4 font-semibold w-12 text-center">
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
                            <th class="p-4 font-semibold">Category</th>
                            <th class="p-4 font-semibold">Operating Unit</th>
                            <th class="p-4 font-semibold">Symptoms</th>
                            <th class="p-4 font-semibold">Treatment</th>
                            <th class="p-4 font-semibold">Remarks</th>
                            <th class="p-4 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y text-sm text-gray-700">
                        <tr v-for="(record, index) in paginatedRecords" :key="record.id"
                            :class="[
                                'hover:bg-gray-50 transition-colors',
                                isRecordSelected(record.id) ? 'bg-blue-50' : ''
                            ]">
                            <td v-if="showBulkActions" class="p-4 text-center">
                                <input
                                    type="checkbox"
                                    :checked="isRecordSelected(record.id)"
                                    :disabled="!isBulkSelectable(record)"
                                    @change="toggleSelectRecord(record)"
                                    class="w-4 h-4 rounded border-gray-300 text-[#34554a] focus:ring-[#34554a] cursor-pointer disabled:cursor-not-allowed disabled:opacity-40"
                                >
                            </td>
                            <td class="p-4 font-medium text-gray-900">{{ (currentPage - 1) * itemsPerPage + index + 1 }}</td>
                            <td class="p-4 text-gray-600">{{ formatDate(record.date) }}</td>
                            <td class="p-4 font-medium text-gray-900">{{ record.tag_no || '-' }}</td>
                            <td class="p-4">
                                <span class="px-2.5 py-1 rounded-full text-xs font-medium"
                                    :class="record.category === 'Anak' ? 'bg-blue-100 text-blue-700' : record.category?.includes('W/') ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-700'">
                                    {{ record.category || '-' }}
                                </span>
                            </td>
                            <td class="p-4 text-gray-600">{{ record.operating_unit || '-' }}</td>
                            <td class="p-4 text-gray-600 max-w-xs truncate">{{ record.symptoms || '-' }}</td>
                            <td class="p-4 text-gray-600 max-w-xs truncate">{{ record.treatment || '-' }}</td>
<td class="p-4 text-gray-600 max-w-xs truncate">{{ record.remarks || '-' }}</td>
                            <td class="p-4 text-right flex justify-end gap-2">
                                <button @click="viewRecord(record)" class="w-8 h-8 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded flex items-center justify-center transition-colors" title="View Details">
                                    <Eye class="w-4 h-4" />
                                </button>
                                <span
                                    v-if="canEditTreatment"
                                    class="inline-flex"
                                    :title="getEditActionTooltip(record)"
                                >
                                    <button
                                        @click="router.visit(`/health/treatment/${record.id}/edit`)"
                                        :disabled="!canEditRecord(record)"
                                        class="w-8 h-8 rounded flex items-center justify-center transition-colors"
                                        :class="canEditRecord(record)
                                            ? 'text-gray-400 hover:text-gray-600 hover:bg-gray-100'
                                            : 'text-gray-300 bg-gray-100 cursor-not-allowed'"
                                    >
                                        <Edit class="w-4 h-4" />
                                    </button>
                                </span>
                                <span
                                    v-if="canDeleteTreatment"
                                    class="inline-flex"
                                    :title="getDeleteActionTooltip(record)"
                                >
                                    <button
                                        @click="promptDelete(record)"
                                        :disabled="!canDeleteRecord(record)"
                                        class="w-8 h-8 rounded flex items-center justify-center transition-colors"
                                        :class="canDeleteRecord(record)
                                            ? 'text-gray-400 hover:text-gray-600 hover:bg-gray-100'
                                            : 'text-gray-300 bg-gray-100 cursor-not-allowed'"
                                    >
                                        <Trash2 class="w-4 h-4" />
                                    </button>
                                </span>
                            </td>
                        </tr>
                        <tr v-if="filteredRecords.length === 0">
                            <td :colspan="showBulkActions ? 10 : 9" class="p-8 text-center text-gray-400 italic">
                                <ClipboardList class="w-8 h-8 mx-auto mb-2 opacity-50" />
                                <p class="text-sm">No treatment records found</p>
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

        <!-- Edit Treatment Modal -->
        <div v-if="showEditModal && editingTreatment" class="fixed inset-y-0 right-0 left-0 md:left-64 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-5 overflow-y-auto" @click.self="closeEditModal">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-7xl mx-auto overflow-hidden border border-gray-100 max-h-[90vh] flex flex-col">
                <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-[#34554a] text-white">
                    <h3 class="text-lg font-bold">Edit Treatment Record</h3>
                    <button @click="closeEditModal" class="text-white opacity-70 hover:opacity-100">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-6 overflow-y-auto space-y-6">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="p-4 border-b border-gray-100">
                            <h3 class="text-sm font-bold text-gray-900 flex items-center gap-2">
                                <ClipboardList class="w-4 h-4 text-[#34554a]" />
                                Treatment Information
                            </h3>
                        </div>
                        <div class="p-6 space-y-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Select Cattle *</label>
                                <div class="relative">
                                    <div
                                        @click="showEditCattleDropdown = !showEditCattleDropdown"
                                        class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white outline-none text-sm focus:ring-2 focus:ring-[#34554a] cursor-pointer flex items-center justify-between"
                                    >
                                        <span :class="editForm.cattle_id ? 'text-gray-900' : 'text-gray-400'">
                                            {{ editForm.cattle_id ? `${editForm.tag_no} - ${editForm.category} - ${editForm.colour}` : 'Search and select cattle...' }}
                                        </span>
                                        <div class="flex items-center gap-2">
                                            <button
                                                v-if="editForm.cattle_id"
                                                @click.stop="clearEditCattleSelection"
                                                class="text-gray-400 hover:text-red-500"
                                                type="button"
                                            >
                                                <Trash2 class="w-4 h-4" />
                                            </button>
                                            <ChevronDown class="w-4 h-4 text-gray-400" />
                                        </div>
                                    </div>

                                    <div
                                        v-if="showEditCattleDropdown"
                                        class="absolute z-20 left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-xl max-h-96 overflow-hidden"
                                    >
                                        <div class="p-3 border-b border-gray-100 bg-gray-50">
                                            <div class="relative">
                                                <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 w-4 h-4" />
                                                <input
                                                    v-model="editCattleSearchQuery"
                                                    type="text"
                                                    placeholder="Search by tag no, cattle ID, category, or colour..."
                                                    class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-200 bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]"
                                                    @click.stop
                                                />
                                            </div>
                                            <div class="text-xs text-gray-500 mt-2">{{ filteredEditCattle.length }} cattle found</div>
                                        </div>

                                        <div class="overflow-y-auto max-h-56">
                                            <div v-if="filteredEditCattle.length === 0" class="p-4 text-center text-gray-500 text-sm">
                                                No cattle found matching "{{ editCattleSearchQuery }}"
                                            </div>
                                            <div
                                                v-for="item in filteredEditCattle"
                                                :key="item.id"
                                                @click.stop="selectEditCattleItem(item)"
                                                class="px-4 py-3 hover:bg-gray-50 cursor-pointer border-b border-gray-100 last:border-0"
                                                :class="{ 'bg-[#34554a]/10': editForm.cattle_id == item.id }"
                                            >
                                                <div class="flex items-center justify-between">
                                                    <div>
                                                        <p class="font-medium text-gray-900">{{ item.tag_no }}</p>
                                                        <p class="text-xs text-gray-500">ID: {{ item.id }} | {{ item.category }} | {{ item.coat_colour }}</p>
                                                    </div>
                                                    <div v-if="editForm.cattle_id == item.id" class="text-[#34554a]">
                                                        <Check class="w-5 h-5" />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <p v-if="editForm.errors.cattle_id" class="text-xs text-red-600 mt-1">{{ editForm.errors.cattle_id }}</p>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">Date *</label>
                                    <input
                                        v-model="editForm.date"
                                        type="date"
                                        class="w-full px-3 py-2 rounded-lg border border-gray-200 outline-none text-sm focus:ring-2 focus:ring-[#34554a]"
                                    >
                                    <p v-if="editForm.errors.date" class="text-xs text-red-600 mt-1">{{ editForm.errors.date }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">Cattle ID</label>
                                    <input
                                        :value="editForm.cattle_id || 'Not selected'"
                                        type="text"
                                        readonly
                                        class="w-full px-3 py-2 rounded-lg border border-gray-100 bg-gray-50 text-sm focus:ring-0 text-gray-600"
                                    >
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">Tag Number</label>
                                    <input
                                        v-model="editForm.tag_no"
                                        type="text"
                                        readonly
                                        class="w-full px-3 py-2 rounded-lg border border-gray-100 bg-gray-50 text-sm focus:ring-0"
                                        placeholder="Auto-filled when cattle selected"
                                    >
                                    <p v-if="editForm.errors.tag_no" class="text-xs text-red-600 mt-1">{{ editForm.errors.tag_no }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">Category</label>
                                    <input
                                        v-model="editForm.category"
                                        type="text"
                                        readonly
                                        class="w-full px-3 py-2 rounded-lg border border-gray-100 bg-gray-50 text-sm focus:ring-0"
                                        placeholder="Auto-filled when cattle selected"
                                    >
                                    <p v-if="editForm.errors.category" class="text-xs text-red-600 mt-1">{{ editForm.errors.category }}</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">Colour/Marking</label>
                                    <input
                                        v-model="editForm.colour"
                                        type="text"
                                        readonly
                                        class="w-full px-3 py-2 rounded-lg border border-gray-100 bg-gray-50 text-sm focus:ring-0"
                                        placeholder="Auto-filled when cattle selected"
                                    >
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">Operating Unit *</label>
                                    <select
                                        v-model="editForm.operating_unit"
                                        class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white outline-none text-sm focus:ring-2 focus:ring-[#34554a]"
                                    >
                                        <option value="">Select Operating Unit...</option>
                                        <option v-for="unit in editOperatingUnitOptions" :key="unit" :value="unit">
                                            {{ unit }}
                                        </option>
                                    </select>
                                    <p v-if="editForm.errors.operating_unit" class="text-xs text-red-600 mt-1">{{ editForm.errors.operating_unit }}</p>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Symptoms Observed *</label>
                                <textarea 
                                    v-model="editForm.symptoms"
                                    rows="2"
                                    placeholder="Describe the symptoms observed..."
                                    class="w-full px-3 py-2 rounded-lg border border-gray-200 outline-none text-sm focus:ring-2 focus:ring-[#34554a]"
                                ></textarea>
                                <p v-if="editForm.errors.symptoms" class="text-xs text-red-600 mt-1">{{ editForm.errors.symptoms }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="p-4 border-b border-gray-100">
                            <h3 class="text-sm font-bold text-gray-900 flex items-center gap-2">
                                <FileText class="w-4 h-4 text-[#34554a]" />
                                Treatment Details
                            </h3>
                        </div>
                        <div class="p-6 space-y-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Treatment Code</label>
                                <select
                                    v-model="editForm.treatment_code"
                                    class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white outline-none text-sm focus:ring-2 focus:ring-[#34554a]"
                                >
                                    <option value="">Select Treatment</option>
                                    <option v-for="code in treatmentCodes.filter(c => c.is_active !== false)" :key="code.id" :value="code.code">{{ code.label }}</option>
                                </select>
                                <p v-if="editForm.errors.treatment_code" class="text-xs text-red-600 mt-1">{{ editForm.errors.treatment_code }}</p>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">Dosage *</label>
                                    <input 
                                        v-model="editForm.dosage"
                                        type="text" 
                                        placeholder="e.g., OTC 12ml + Daxa 10ml"
                                        class="w-full px-3 py-2 rounded-lg border border-gray-200 outline-none text-sm focus:ring-2 focus:ring-[#34554a]"
                                    >
                                    <p v-if="editForm.errors.dosage" class="text-xs text-red-600 mt-1">{{ editForm.errors.dosage }}</p>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Additional Remarks</label>
                                <textarea 
                                    v-model="editForm.remarks"
                                    rows="2"
                                    placeholder="Any additional notes..."
                                    class="w-full px-3 py-2 rounded-lg border border-gray-200 outline-none text-sm focus:ring-2 focus:ring-[#34554a]"
                                ></textarea>
                                <p v-if="editForm.errors.remarks" class="text-xs text-red-600 mt-1">{{ editForm.errors.remarks }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="p-4 border-b border-gray-100">
                            <h3 class="text-sm font-bold text-gray-900 flex items-center gap-2">
                                <Calendar class="w-4 h-4 text-[#34554a]" />
                                Follow-up Information
                            </h3>
                        </div>
                        <div class="p-6 space-y-4">
                            <div class="flex items-center gap-3">
                                <input 
                                    v-model="editForm.follow_up_required"
                                    type="checkbox" 
                                    id="edit-follow-up"
                                    class="rounded border-gray-300 text-[#34554a] focus:ring-[#34554a] cursor-pointer"
                                >
                                <label for="edit-follow-up" class="text-sm text-gray-700 cursor-pointer">Follow-up required</label>
                            </div>
                            <div v-if="editForm.follow_up_required">
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Follow-up Date</label>
                                <input 
                                    v-model="editForm.follow_up_date"
                                    type="date" 
                                    class="w-full px-3 py-2 rounded-lg border border-gray-200 outline-none text-sm focus:ring-2 focus:ring-[#34554a]"
                                >
                                <p v-if="editForm.errors.follow_up_date" class="text-xs text-red-600 mt-1">{{ editForm.errors.follow_up_date }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-6 border-t border-gray-100 flex justify-end gap-3 bg-gray-50">
                    <button @click="closeEditModal" class="px-4 py-2 border border-gray-200 rounded-lg text-sm font-bold text-gray-600 hover:bg-white transition-all">Cancel</button>
                    <button @click="submitEditForm" :disabled="editForm.processing" class="flex items-center gap-2 bg-[#34554a] text-white px-6 py-2 rounded-lg text-sm font-bold hover:bg-[#2a443b] transition-all disabled:opacity-50 shadow-md">
                        <Save class="w-4 h-4" />
                        {{ editForm.processing ? 'Saving...' : 'Save Changes' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Detail Modal with Endorsement Workflow -->
        <div v-if="showDetailModal && selectedTreatment" class="fixed inset-y-0 right-0 left-0 md:left-64 z-40 flex items-center justify-center bg-black/50 backdrop-blur-sm p-5 overflow-y-auto" @click.self="closeModal">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-7xl mx-auto overflow-hidden border border-gray-100 max-h-[90vh] flex flex-col">
                <div class="flex justify-between items-center p-6 border-b border-gray-100 bg-gray-50 sticky top-0 flex-shrink-0">
                    <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                        <ClipboardList class="w-5 h-5 text-[#34554a]" />
                        <span>Treatment Details - {{ selectedTreatment.treatment_no || ('TRT-' + selectedTreatment.id) }}</span>
                    </h3>
                    <button type="button" @click="closeModal" class="text-gray-400 hover:text-gray-600 transition-colors w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-6 overflow-y-auto flex-1">
                    <div class="space-y-5">
                        <div class="bg-gray-50 rounded-xl p-4">
                            <h4 class="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2">
                                <ClipboardList class="w-4 h-4 text-[#34554a]" />
                                Filled Treatment Columns
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 text-sm">
                                <div v-for="(item, index) in detailFields" :key="index" class="flex justify-between py-1.5 border-b border-gray-200 gap-3">
                                    <span class="text-gray-500">{{ item.label }}</span>
                                    <span class="font-semibold text-gray-900 text-right break-all">{{ item.value }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="border-t p-4 bg-gray-50 flex justify-end flex-shrink-0">
                    <button @click="closeModal" class="px-6 py-2.5 bg-gray-300 text-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-400 transition-colors">
                        Close
                    </button>
                </div>
            </div>
        </div>
        <div v-if="showWorkflowModal && monthlyWorkflow" class="fixed inset-y-0 right-0 left-0 md:left-64 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-5 overflow-y-auto" @click.self="closeWorkflow">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-7xl mx-auto overflow-hidden border border-gray-100 max-h-[90vh] flex flex-col">
                <div class="flex justify-between items-center p-6 border-b border-gray-100 bg-gray-50 sticky top-0 flex-shrink-0">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Monthly Endorsement Workflow</h3>
                        <p class="text-sm text-gray-500 mt-1">{{ monthFilter }} {{ yearFilter }} | {{ operatingUnitFilter }}</p>
                    </div>
                    <button @click="closeWorkflow" class="text-gray-400 hover:text-gray-600 transition-colors w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <p class="text-xs text-gray-500 font-medium">Current Step</p>
                            <p class="text-lg font-bold text-gray-900">{{ monthlyWorkflow.endorsement_step || 0 }} / {{ docWorkflowSteps.length }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button @click="downloadEndorsementForm" class="flex items-center gap-2 px-3 py-1.5 text-sm text-[#34554a] border border-[#34554a] rounded-lg hover:bg-[#34554a]/10">
                                <FileText class="w-4 h-4" />
                                Download Form
                            </button>
                            <button
                                v-if="userRole === 'admin' && !(monthlyWorkflow.is_completed || monthlyWorkflow.status === 'completed')"
                                :disabled="!allMonthlyStepsUploaded"
                                @click="markAsCompleted"
                                class="flex items-center gap-2 px-3 py-1.5 text-sm text-white rounded-lg"
                                :class="allMonthlyStepsUploaded ? 'bg-[#34554a] hover:bg-[#2a443b]' : 'bg-gray-300 cursor-not-allowed'"
                            >
                                <CheckCircle class="w-4 h-4" />
                                Mark Completed
                            </button>
                            <button
                                v-if="userRole === 'admin' && (monthlyWorkflow.is_completed || monthlyWorkflow.status === 'completed')"
                                @click="reopenMonthlyWorkflow"
                                class="flex items-center gap-2 px-3 py-1.5 text-sm text-amber-700 border border-amber-500 rounded-lg hover:bg-amber-50"
                            >
                                <Clock class="w-4 h-4" />
                                Reopen
                            </button>
                        </div>
                    </div>

                    <div class="max-w-xl mx-auto flex justify-center gap-2 flex-wrap p-4 bg-gray-50 rounded-xl">
                        <template v-for="(step, index) in docWorkflowSteps" :key="index">
                            <div class="flex flex-col items-center min-w-[70px]">
                                <div 
                                    class="w-10 h-10 rounded-full flex items-center justify-center text-sm mb-1"
                                    :class="getMonthlyStepDocument(index) || (monthlyWorkflow.endorsement_step || 0) >= index + 1
                                        ? 'bg-[#34554a] text-white'
                                        : 'bg-gray-200 text-gray-400'"
                                >
                                    <CheckCircle v-if="getMonthlyStepDocument(index) || (monthlyWorkflow.endorsement_step || 0) >= index + 1" class="w-5 h-5" />
                                    <span v-else>{{ index + 1 }}</span>
                                </div>
                                <span 
                                    class="text-[10px] text-center"
                                    :class="getMonthlyStepDocument(index) || (monthlyWorkflow.endorsement_step || 0) >= index + 1
                                        ? 'text-[#34554a] font-semibold'
                                        : 'text-gray-400'"
                                >{{ step.label }}</span>
                            </div>
                            <div 
                                v-if="index < docWorkflowSteps.length - 1"
                                class="pb-5 flex items-center"
                                :class="getMonthlyStepDocument(index) || (monthlyWorkflow.endorsement_step || 0) >= index + 1 ? 'text-[#34554a]' : 'text-gray-300'"
                            >
                                <ChevronRight class="w-4 h-4" />
                            </div>
                        </template>
                    </div>
                </div>

                <div class="overflow-y-auto flex-1 p-5">
                    <WorkflowEndorsementCards
                        :steps="docWorkflowSteps"
                        :get-step-document="getMonthlyStepDocument"
                        :can-upload-step="canUploadMonthlyStep"
                        :can-view-step="canViewMonthlyStep"
                        :can-download-previous="canDownloadMonthlyPrevious"
                        :can-delete-step="canDeleteMonthlyStep"
                        :is-submitting="isSubmitting"
                        :user-name="userName"
                        grid-class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4"
                        @upload="submitMonthlyEndorsementUpload"
                        @view="downloadStepDocument"
                        @previous="downloadPreviousStepDocument"
                        @delete="deleteEndorsementStep"
                    />
                </div>

                <div class="border-t p-4 bg-gray-100 flex justify-end flex-shrink-0">
                    <button @click="closeWorkflow" class="px-6 py-2.5 bg-gray-300 text-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-400 transition-colors">
                        Close
                    </button>
                </div>
            </div>
        </div>
        <DeleteConfirmationModal
            :show="showDeleteModal"
            title="Delete Treatment Record"
            :message="treatmentToDelete ? `Are you sure you want to delete treatment record for ${treatmentToDelete.tag_no || 'cattle'}? This action cannot be undone.` : 'Are you sure you want to delete this treatment record? This action cannot be undone.'"
            confirmText="Delete"
            cancelText="Cancel"
            @close="showDeleteModal = false; treatmentToDelete = null"
            @confirm="confirmDelete"
        />
    </div>
</template>
