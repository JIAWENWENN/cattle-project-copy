<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
    users: Array,
    existingPermissions: Array,
    workflowAssignment: Object,
    calvingWorkflowAssignment: Object,
    transferWorkflowAssignment: Object,
    workflowAssignments: Object,
});
// Define all main modules from sidebar
const modules = [
    { name: 'Dashboard', group: 'Dashboard' },
    { name: 'Cattle Directory', group: 'Cattle' },
    { name: 'Weekly Return', group: 'Analytics' },
    { name: 'Daily Operation DOML', group: 'Cattle' },
    { name: 'Performance Summary', group: 'Analytics' },
    { name: 'Feeding Record', group: 'Feeding' },
    { name: 'Treatment Record', group: 'Health' },
    { name: 'Veterinary Contact', group: 'Health' },
    { name: 'Calving Record', group: 'Calving' },
    { name: 'Calving Checklist', group: 'Calving' },
    { name: 'Mortality Records', group: 'Mortality' },
    { name: 'Pasture', group: 'Pasture' },
    { name: 'Transfer CTV', group: 'Transfer' },
    { name: 'Transfer Receival', group: 'Transfer' },
    { name: 'Transfer SIV', group: 'Transfer' },
    { name: 'Driver', group: 'Driver' },
    { name: 'Inventory Medication Stock', group: 'Inventory' },
    { name: 'Task', group: 'Task' },
    { name: 'Settings', group: 'Settings' },
];

const permissionLevels = [
    { value: 'no-access', label: 'No Access', class: 'bg-red-50 text-red-800 border-red-200' },
    { value: 'view', label: 'View', class: 'bg-gray-100 text-gray-800 border-gray-300' },
    { value: 'create', label: 'Create', class: 'bg-teal-50 text-teal-800 border-teal-200' },
    { value: 'edit', label: 'Edit', class: 'bg-blue-50 text-blue-800 border-blue-200' },
    { value: 'delete', label: 'Delete', class: 'bg-red-50 text-red-800 border-red-200' },
    { value: 'full', label: 'Full Access', class: 'bg-green-50 text-green-800 border-green-200' },
];

/** Modules that only expose a reduced permission set in the matrix. */
const modulePermissionOverrides = {
    'Dashboard': ['no-access', 'view'],
    'Weekly Return': ['no-access', 'view', 'full'],
};

const getAllowedPermissionValues = (moduleName) => {
    if (modulePermissionOverrides[moduleName]) {
        return modulePermissionOverrides[moduleName];
    }

    return permissionLevels.map((level) => level.value);
};

const normalizePermissionListForModule = (moduleName, value) => {
    const allowed = new Set(getAllowedPermissionValues(moduleName));
    const normalized = normalizePermissionList(value).filter((item) => allowed.has(item));
    if (normalized.includes('full')) return ['full'];
    return normalized.length ? normalized : ['no-access'];
};

const visiblePermissionLevels = computed(() => {
    const override = modulePermissionOverrides[selectedModule.value];
    let levels = permissionLevels;
    
    if (override) {
        levels = permissionLevels.filter((level) => override.includes(level.value));
    }

    return levels;
});

const searchQuery = ref('');
const permissions = ref({});
const selectedModule = ref('Cattle Directory');

const normalizePermissionList = (value) => {
    const allowed = ['no-access', 'view', 'create', 'edit', 'delete', 'full', 'approve'];
    let items = [];

    if (Array.isArray(value)) {
        items = value;
    } else if (typeof value === 'string') {
        const trimmed = value.trim();
        if (trimmed.startsWith('[') && trimmed.endsWith(']')) {
            try {
                const parsed = JSON.parse(trimmed);
                if (Array.isArray(parsed)) {
                    items = parsed;
                }
            } catch (_) {
                items = trimmed.split(',');
            }
        } else {
            items = trimmed.split(',');
        }
    }

    items = [...new Set(items.map((item) => String(item).trim()).filter((item) => allowed.includes(item)))];

    if (items.includes('full')) return ['full'];
    if (items.length > 1) items = items.filter((item) => item !== 'no-access');
    return items.length ? items : ['no-access'];
};

const toValidIdArray = (value, fallbackId = null) => {
    const source = Array.isArray(value) ? value : (fallbackId ? [fallbackId] : []);
    return [...new Set(
        source
            .map((v) => Number(v))
            .filter((v) => Number.isInteger(v) && v > 0)
    )];
};

const workflowAssignmentForm = useForm({
    prepared_by_user_ids: toValidIdArray(props.workflowAssignment?.prepared_by_user_ids, props.workflowAssignment?.prepared_by_user_id),
    checked_by_user_ids: toValidIdArray(props.workflowAssignment?.checked_by_user_ids, props.workflowAssignment?.checked_by_user_id),
    approved_by_user_ids: toValidIdArray(props.workflowAssignment?.approved_by_user_ids, props.workflowAssignment?.approved_by_user_id),
});

const calvingWorkflowAssignmentForm = useForm({
    issued_by_user_ids: toValidIdArray(props.calvingWorkflowAssignment?.issued_by_user_ids, props.calvingWorkflowAssignment?.issued_by_user_id),
    verified_by_user_ids: toValidIdArray(props.calvingWorkflowAssignment?.verified_by_user_ids, props.calvingWorkflowAssignment?.verified_by_user_id),
    checked_by_user_ids: toValidIdArray(props.calvingWorkflowAssignment?.checked_by_user_ids, props.calvingWorkflowAssignment?.checked_by_user_id),
    witnessed_by_user_ids: toValidIdArray(props.calvingWorkflowAssignment?.witnessed_by_user_ids, props.calvingWorkflowAssignment?.witnessed_by_user_id),
    approved_by_user_ids: toValidIdArray(props.calvingWorkflowAssignment?.approved_by_user_ids, props.calvingWorkflowAssignment?.approved_by_user_id),
});

const transferWorkflowAssignmentForm = useForm({
    issued_by_user_ids: toValidIdArray(props.transferWorkflowAssignment?.issued_by_user_ids, props.transferWorkflowAssignment?.issued_by_user_id),
    approved_by_user_ids: toValidIdArray(props.transferWorkflowAssignment?.approved_by_user_ids, props.transferWorkflowAssignment?.approved_by_user_id),
    transported_by_user_ids: toValidIdArray(props.transferWorkflowAssignment?.transported_by_user_ids, props.transferWorkflowAssignment?.transported_by_user_id),
    witnessed_transit_by_user_ids: toValidIdArray(props.transferWorkflowAssignment?.witnessed_transit_by_user_ids, props.transferWorkflowAssignment?.witnessed_transit_by_user_id),
    verified_transit_by_user_ids: toValidIdArray(props.transferWorkflowAssignment?.verified_transit_by_user_ids, props.transferWorkflowAssignment?.verified_transit_by_user_id),
    witnessed_receive_by_user_ids: toValidIdArray(props.transferWorkflowAssignment?.witnessed_receive_by_user_ids, props.transferWorkflowAssignment?.witnessed_receive_by_user_id),
    received_by_user_ids: toValidIdArray(props.transferWorkflowAssignment?.received_by_user_ids, props.transferWorkflowAssignment?.received_by_user_id),
    completed_by_user_ids: toValidIdArray(props.transferWorkflowAssignment?.completed_by_user_ids, props.transferWorkflowAssignment?.completed_by_user_id),
});

const genericWorkflowDefinitions = {
    'Weekly Return': [
        { key: 'prepared_by_user_ids', label: 'Prepared By' },
        { key: 'verified_by_user_ids', label: 'Verified By' },
        { key: 'checked_by_user_ids', label: 'Checked By' },
        { key: 'approved_by_user_ids', label: 'Approved By' },
    ],
    'Daily Operation DOML': [
        { key: 'pengembala_user_ids', label: 'Workflow 1 (Pengembala)' },
        { key: 'pembantu_kanan_ternakan_user_ids', label: 'Workflow 2 (Pemb Kanan Ternakan)' },
        { key: 'pembantu_kanan_keselamatan_user_ids', label: 'Workflow 3 (Pemb Kanan Keselamatan)' },
        { key: 'wakil_ladang_user_ids', label: 'Workflow 4 (Wakil Ladang)' },
    ],
    'Mortality Records': [
        { key: 'issued_by_user_ids', label: 'Issued By' },
        { key: 'verified_by_user_ids', label: 'Verified By' },
        { key: 'checked_by_user_ids', label: 'Checked By' },
        { key: 'witnessed_by_user_ids', label: 'Witnessed By' },
        { key: 'approved_by_user_ids', label: 'Approved By' },
    ],
};

const transferWorkflowDefinitions = {
    'Transfer CTV': [
        { key: 'issued_by_user_ids', label: 'Issued By (Transferor Estate)' },
        { key: 'approved_by_user_ids', label: 'Approved By (Transferor Estate)' },
        { key: 'transported_by_user_ids', label: 'Transported By (Transferor Estate)' },
        { key: 'witnessed_transit_by_user_ids', label: 'Witness By (Transferor Estate)' },
        { key: 'verified_transit_by_user_ids', label: 'Verified By (Transferor Estate)' },
        { key: 'witnessed_receive_by_user_ids', label: 'Witness By (Receiving Estate)' },
        { key: 'received_by_user_ids', label: 'Received By (Receiving Estate)' },
        { key: 'completed_by_user_ids', label: 'Verified Completion By (Receiving Estate)' },
    ],
    'Transfer Receival': [
        { key: 'issued_by_user_ids', label: 'Prepared By' },
        { key: 'witnessed_transit_by_user_ids', label: 'Witness By' },
        { key: 'verified_transit_by_user_ids', label: 'Verified By' },
    ],
    'Transfer SIV': [
        { key: 'issued_by_user_ids', label: 'Requested By' },
        { key: 'approved_by_user_ids', label: 'Verified By' },
        { key: 'received_by_user_ids', label: 'Approved By' },
        { key: 'completed_by_user_ids', label: 'Received By' },
    ],
};

const genericWorkflowForms = ref(
    Object.fromEntries(
        Object.entries(genericWorkflowDefinitions).map(([module, fields]) => [
            module,
            Object.fromEntries(fields.map((field) => [
                field.key,
                toValidIdArray(props.workflowAssignments?.[module]?.[field.key]),
            ])),
        ])
    )
);

const initializePermissions = () => {
    modules.forEach(module => {
        props.users.forEach(user => {
            const key = `${module.name}-${user.id}`;

            // First, check if permission exists in database
            const existingPerm = props.existingPermissions?.find(
                p => p.user_id === user.id && p.module === module.name
            );

            if (existingPerm) {
                // Use saved permission from database
                permissions.value[key] = normalizePermissionListForModule(module.name, existingPerm.permission);
            } else {
                if (module.name === 'Weekly Return') {
                    permissions.value[key] = ['no-access'];
                    return;
                }

                // Set default based on role if no saved permission
                if (user.role === 'Admin') {
                    permissions.value[key] = ['full'];
                } else if (user.role === 'Manager') {
                    permissions.value[key] = ['edit'];
                } else if (user.role === 'Supervisor') {
                    permissions.value[key] = ['view'];
                } else {
                    permissions.value[key] = ['no-access'];
                }
            }
        });
    });
};
initializePermissions();

const filteredModules = computed(() => {
    if (!searchQuery.value) return modules;
    const query = searchQuery.value.toLowerCase();
    return modules.filter(m => m.name.toLowerCase().includes(query));
});

const selectedModuleObj = computed(() => modules.find(m => m.name === selectedModule.value) || modules[0]);
const selectedGenericWorkflowFields = computed(() => {
    // Calving Checklist shares the same workflow assignment table as Calving Record.
    if (selectedModule.value === 'Calving Checklist' || selectedModule.value === 'Calving Record') {
        return [];
    }
    return genericWorkflowDefinitions[selectedModule.value] || [];
});
const usesCalvingWorkflowAssignment = computed(() =>
    selectedModule.value === 'Calving Record' || selectedModule.value === 'Calving Checklist'
);
const selectedTransferWorkflowFields = computed(() => transferWorkflowDefinitions[selectedModule.value] || []);
const usersByRole = computed(() => {
    const map = new Map();
    for (const user of props.users || []) {
        const roleKey = String(user.role || 'unknown');
        if (!map.has(roleKey)) {
            map.set(roleKey, []);
        }
        map.get(roleKey).push(user);
    }

    return Array.from(map.entries())
        .sort((a, b) => a[0].localeCompare(b[0]))
        .map(([role, users]) => ({
            role,
            users: users.sort((a, b) => String(a.name || '').localeCompare(String(b.name || ''))),
        }));
});

const getPermissionClass = (moduleKey, userId) => {
    const key = `${moduleKey}-${userId}`;
    const permValue = normalizePermissionListForModule(moduleKey, permissions.value[key] || ['no-access'])[0];
    const perm = permissionLevels.find(p => p.value === permValue);
    return perm ? perm.class : '';
};

const updatePermission = (moduleName, userId, newValue) => {
    const key = `${moduleName}-${userId}`;
    permissions.value[key] = normalizePermissionListForModule(moduleName, newValue);
    console.log(`Updated ${moduleName} for user ${userId} to ${newValue}`);
};

let permissionSaveTimer = null;
let workflowSaveTimer = null;
let calvingWorkflowSaveTimer = null;
let transferWorkflowSaveTimer = null;
let genericWorkflowSaveTimer = null;
const pendingPermissionChanges = ref({});

const flushPendingPermissions = (useKeepalive = false) => {
    const payload = Object.values(pendingPermissionChanges.value);
    if (payload.length === 0) return;

    if (permissionSaveTimer) {
        clearTimeout(permissionSaveTimer);
        permissionSaveTimer = null;
    }

    if (useKeepalive && typeof fetch === 'function') {
        const xsrfToken = getCookie('XSRF-TOKEN');
        fetch(route('permissions.save'), {
            method: 'POST',
            credentials: 'include',
            keepalive: true,
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
                ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
            },
            body: JSON.stringify({ permissions: payload }),
        });

        pendingPermissionChanges.value = {};
        return;
    }

    postWithCsrf(route('permissions.save'), {
        permissions: payload,
    }).then(() => {
        pendingPermissionChanges.value = {};
    }).catch((error) => {
        console.error('Flush permissions failed:', error);
    });
};

const getCookie = (name) => {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length !== 2) return '';
    return decodeURIComponent(parts.pop().split(';').shift() || '');
};

const getCsrfHeaders = () => {
    const headers = {
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
    };

    const xsrfToken = getCookie('XSRF-TOKEN');
    if (xsrfToken) {
        headers['X-XSRF-TOKEN'] = xsrfToken;
    }

    return headers;
};

const refreshCsrfCookie = () => window.axios.get('/sanctum/csrf-cookie', {
    withCredentials: true,
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
    },
});

const postWithCsrf = async (url, data, hasRetried = false) => {
    try {
        delete window.axios.defaults.headers.common['X-CSRF-TOKEN'];
        return await window.axios.post(url, data, {
            withCredentials: true,
            headers: getCsrfHeaders(),
        });
    } catch (error) {
        if (error?.response?.status === 419 && !hasRetried) {
            await refreshCsrfCookie();
            return postWithCsrf(url, data, true);
        }

        throw error;
    }
};

const queuePermissionsSave = () => {
    if (permissionSaveTimer) clearTimeout(permissionSaveTimer);
    permissionSaveTimer = setTimeout(async () => {
        const payload = Object.values(pendingPermissionChanges.value);
        if (payload.length === 0) return;
        try {
            await postWithCsrf(route('permissions.save'), {
                permissions: payload
            });
            pendingPermissionChanges.value = {};
        } catch (error) {
            console.error('Auto-save permissions failed:', error);
            const responseData = error?.response?.data || {};
            const validationErrors = responseData?.errors
                ? Object.values(responseData.errors).flat().join('\n')
                : '';
            const message = responseData?.message || 'Failed to save permission changes. Please try again.';
            alert(validationErrors ? `${message}\n${validationErrors}` : message);
        }
    }, 250);
};

const handlePageHide = () => {
    flushPendingPermissions(true);
};

onMounted(() => {
    window.addEventListener('beforeunload', handlePageHide);
    window.addEventListener('pagehide', handlePageHide);
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', handlePageHide);
    window.removeEventListener('pagehide', handlePageHide);
    flushPendingPermissions();
});

const queueWorkflowSave = () => {
    if (workflowSaveTimer) clearTimeout(workflowSaveTimer);
    workflowSaveTimer = setTimeout(async () => {
        try {
            await postWithCsrf(route('permissions.treatment-workflow-assignment.save'), {
                prepared_by_user_ids: workflowAssignmentForm.prepared_by_user_ids || [],
                checked_by_user_ids: workflowAssignmentForm.checked_by_user_ids || [],
                approved_by_user_ids: workflowAssignmentForm.approved_by_user_ids || [],
            });
        } catch (error) {
            console.error('Auto-save workflow failed:', error);
            alert('Failed to save workflow changes. Please try again.');
        }
    }, 250);
};

const queueCalvingWorkflowSave = () => {
    if (calvingWorkflowSaveTimer) clearTimeout(calvingWorkflowSaveTimer);
    calvingWorkflowSaveTimer = setTimeout(async () => {
        try {
            await postWithCsrf(route('permissions.calving-workflow-assignment.save'), {
                issued_by_user_ids: calvingWorkflowAssignmentForm.issued_by_user_ids || [],
                verified_by_user_ids: calvingWorkflowAssignmentForm.verified_by_user_ids || [],
                checked_by_user_ids: calvingWorkflowAssignmentForm.checked_by_user_ids || [],
                witnessed_by_user_ids: calvingWorkflowAssignmentForm.witnessed_by_user_ids || [],
                approved_by_user_ids: calvingWorkflowAssignmentForm.approved_by_user_ids || [],
            });
        } catch (error) {
            console.error('Auto-save calving workflow failed:', error);
            alert('Failed to save calving workflow changes. Please try again.');
        }
    }, 250);
};

const queueTransferWorkflowSave = () => {
    if (transferWorkflowSaveTimer) clearTimeout(transferWorkflowSaveTimer);
    transferWorkflowSaveTimer = setTimeout(async () => {
        try {
            await postWithCsrf(route('permissions.transfer-workflow-assignment.save'), {
                issued_by_user_ids: toValidIdArray(transferWorkflowAssignmentForm.issued_by_user_ids),
                approved_by_user_ids: toValidIdArray(transferWorkflowAssignmentForm.approved_by_user_ids),
                transported_by_user_ids: toValidIdArray(transferWorkflowAssignmentForm.transported_by_user_ids),
                witnessed_transit_by_user_ids: toValidIdArray(transferWorkflowAssignmentForm.witnessed_transit_by_user_ids),
                verified_transit_by_user_ids: toValidIdArray(transferWorkflowAssignmentForm.verified_transit_by_user_ids),
                witnessed_receive_by_user_ids: toValidIdArray(transferWorkflowAssignmentForm.witnessed_receive_by_user_ids),
                received_by_user_ids: toValidIdArray(transferWorkflowAssignmentForm.received_by_user_ids),
                completed_by_user_ids: toValidIdArray(transferWorkflowAssignmentForm.completed_by_user_ids),
            });
        } catch (error) {
            console.error('Auto-save transfer workflow failed:', error);
            const responseData = error?.response?.data || {};
            const validationErrors = responseData?.errors
                ? Object.values(responseData.errors).flat().join('\n')
                : '';
            const message = responseData?.message || 'Failed to save transfer workflow changes. Please try again.';
            alert(validationErrors ? `${message}\n${validationErrors}` : message);
        }
    }, 250);
};

const queueGenericWorkflowSave = (moduleName) => {
    if (genericWorkflowSaveTimer) clearTimeout(genericWorkflowSaveTimer);
    genericWorkflowSaveTimer = setTimeout(async () => {
        try {
            const assignments = {};
            for (const field of genericWorkflowDefinitions[moduleName] || []) {
                assignments[field.key] = toValidIdArray(genericWorkflowForms.value[moduleName]?.[field.key]);
            }

            await postWithCsrf(route('permissions.workflow-assignment.save'), {
                module: moduleName,
                assignments,
            });
        } catch (error) {
            console.error('Auto-save workflow failed:', error);
            const responseData = error?.response?.data || {};
            const message = responseData?.message || 'Failed to save workflow changes. Please try again.';
            alert(message);
        }
    }, 250);
};

const isPermissionChecked = (moduleName, userId, permissionValue) => {
    const key = `${moduleName}-${userId}`;
    return normalizePermissionList(permissions.value[key] || ['no-access']).includes(permissionValue);
};

const togglePermission = (moduleName, userId, permissionValue, checked) => {
    const key = `${moduleName}-${userId}`;
    let current = normalizePermissionList(permissions.value[key] || ['no-access']);

    if (permissionValue === 'no-access') {
        // Ticking No Access clears everything else
        current = checked ? ['no-access'] : ['no-access'];
    } else if (permissionValue === 'full') {
        current = checked ? ['full'] : ['no-access'];
    } else if (checked) {
        current = current.filter((item) => item !== 'no-access' && item !== 'full');
        if (!current.includes(permissionValue)) {
            current.push(permissionValue);
        }
    } else {
        current = current.filter((item) => item !== permissionValue && item !== 'full');
        
        if (current.length === 0) {
            current = ['no-access'];
        }
    }

    updatePermission(moduleName, userId, current);

    // Use the freshly updated value (not stale pre-update value)
    pendingPermissionChanges.value[key] = {
        user_id: Number(userId),
        module: moduleName,
        permission: normalizePermissionListForModule(moduleName, permissions.value[key] || ['no-access']),
    };

    queuePermissionsSave();
};

const isWorkflowUserChecked = (stepKey, userId) => {
    const ids = workflowAssignmentForm[stepKey] || [];
    return Array.isArray(ids) && ids.includes(Number(userId));
};

const toggleWorkflowUser = (stepKey, userId, checked) => {
    const id = Number(userId);
    const current = Array.isArray(workflowAssignmentForm[stepKey]) ? [...workflowAssignmentForm[stepKey]] : [];
    const exists = current.includes(id);
    if (checked && !exists) current.push(id);
    if (!checked && exists) {
        const idx = current.indexOf(id);
        current.splice(idx, 1);
    }
    workflowAssignmentForm[stepKey] = current;
    queueWorkflowSave();
};

const isCalvingWorkflowUserChecked = (stepKey, userId) => {
    const ids = calvingWorkflowAssignmentForm[stepKey] || [];
    return Array.isArray(ids) && ids.includes(Number(userId));
};

const toggleCalvingWorkflowUser = (stepKey, userId, checked) => {
    const id = Number(userId);
    const current = Array.isArray(calvingWorkflowAssignmentForm[stepKey]) ? [...calvingWorkflowAssignmentForm[stepKey]] : [];
    const exists = current.includes(id);
    if (checked && !exists) current.push(id);
    if (!checked && exists) {
        const idx = current.indexOf(id);
        current.splice(idx, 1);
    }
    calvingWorkflowAssignmentForm[stepKey] = current;
    queueCalvingWorkflowSave();
};

const isTransferWorkflowUserChecked = (stepKey, userId) => {
    const ids = transferWorkflowAssignmentForm[stepKey] || [];
    return Array.isArray(ids) && ids.includes(Number(userId));
};

const toggleTransferWorkflowUser = (stepKey, userId, checked) => {
    const id = Number(userId);
    const current = Array.isArray(transferWorkflowAssignmentForm[stepKey]) ? [...transferWorkflowAssignmentForm[stepKey]] : [];
    const exists = current.includes(id);
    if (checked && !exists) current.push(id);
    if (!checked && exists) {
        const idx = current.indexOf(id);
        current.splice(idx, 1);
    }
    transferWorkflowAssignmentForm[stepKey] = toValidIdArray(current);
    queueTransferWorkflowSave();
};

const isGenericWorkflowUserChecked = (moduleName, stepKey, userId) => {
    const ids = genericWorkflowForms.value[moduleName]?.[stepKey] || [];
    return Array.isArray(ids) && ids.includes(Number(userId));
};

const toggleGenericWorkflowUser = (moduleName, stepKey, userId, checked) => {
    const id = Number(userId);
    const current = Array.isArray(genericWorkflowForms.value[moduleName]?.[stepKey])
        ? [...genericWorkflowForms.value[moduleName][stepKey]]
        : [];
    const exists = current.includes(id);
    if (checked && !exists) current.push(id);
    if (!checked && exists) {
        const idx = current.indexOf(id);
        current.splice(idx, 1);
    }

    genericWorkflowForms.value[moduleName] = {
        ...(genericWorkflowForms.value[moduleName] || {}),
        [stepKey]: toValidIdArray(current),
    };
    queueGenericWorkflowSave(moduleName);
};

</script>

<template>
    <Head title="Access Control Matrix" />

    <AppLayout
        title="Access Control Matrix"
        parent="Settings"
        parentUrl="#"
    >
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Access Control Matrix</h1>
            <p class="text-sm text-gray-500 mt-1">Manage access levels for all system modules and user roles.</p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex items-center gap-5 hover:shadow-md transition-shadow">
                <div class="w-14 h-14 rounded-full flex items-center justify-center bg-purple-50">
                    <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" version="1.1" fill="#000000" class="w-7 h-7">
                        <path style="fill:#333333;stroke:none;" d="m 50,46 -22,-22 22,-22 22,22 -22,22"></path>
                        <path style="fill:#333333;stroke:none;" d="m 48,48 -22,-22 -22,22 22,22 22,-22"></path>
                        <path style="fill:#333333;stroke:none;" d="m 52,48 22,-22 22,22 -22,22 -22,-22"></path>
                        <path style="fill:#EBEBDA;stroke:#333333;stroke-width:2;" d="m 50,55 22,22 -22,22 -22,-22 22,-22"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500 font-medium mb-1">Total Modules</p>
                </div>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex items-center gap-5 hover:shadow-md transition-shadow">
                <div class="w-14 h-14 rounded-full flex items-center justify-center bg-green-50 text-green-600">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-7 h-7">
                        <circle cx="9.00098" cy="6" r="4" fill="#1C274C"></circle>
                        <ellipse cx="9.00098" cy="17.001" rx="7" ry="4" fill="#1C274C"></ellipse>
                        <path d="M20.9996 17.0005C20.9996 18.6573 18.9641 20.0004 16.4788 20.0004C17.211 19.2001 17.7145 18.1955 17.7145 17.0018C17.7145 15.8068 17.2098 14.8013 16.4762 14.0005C18.9615 14.0005 20.9996 15.3436 20.9996 17.0005Z" fill="#1C274C"></path>
                        <path d="M17.9996 6.00073C17.9996 7.65759 16.6565 9.00073 14.9996 9.00073C14.6383 9.00073 14.292 8.93687 13.9712 8.81981C14.4443 7.98772 14.7145 7.02522 14.7145 5.99962C14.7145 4.97477 14.4447 4.01294 13.9722 3.18127C14.2927 3.06446 14.6387 3.00073 14.9996 3.00073C16.6565 3.00073 17.9996 4.34388 17.9996 6.00073Z" fill="#1C274C"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500 font-medium mb-1">Total Users</p>
                    <p class="text-3xl font-extrabold text-gray-900">{{ users.length }}</p>
                </div>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex items-center gap-5 hover:shadow-md transition-shadow">
                <div class="w-14 h-14 rounded-full flex items-center justify-center bg-amber-50 text-amber-600">
                    <svg viewBox="0 0 1024 1024" class="w-7 h-7" version="1.1" xmlns="http://www.w3.org/2000/svg" fill="#000000">
                        <path d="M512 32C376.8 32 267.2 141.6 267.2 276.8v262.4h489.6V276.8C756.8 141.6 647.2 32 512 32z m157.6 420H354.4V276.8c0-87.2 70.4-157.6 157.6-157.6 87.2 0 157.6 70.4 157.6 157.6v175.2z" fill="#FFFFFF"></path>
                        <path d="M756.8 359.2c-26.4-21.6-56-39.2-87.2-52.8v98.4H354.4V306.4c-31.2 13.6-60.8 32-87.2 52.8v132.8h489.6V359.2z" fill="#111318"></path>
                        <path d="M756.8 547.2H267.2c-4.8 0-8-3.2-8-8V276.8C259.2 137.6 372.8 24 512 24s252.8 113.6 252.8 252.8v262.4c0 4.8-3.2 8-8 8z m-481.6-16h473.6V276.8C748.8 146.4 642.4 40 512 40S275.2 146.4 275.2 276.8v254.4z m394.4-71.2H354.4c-4.8 0-8-3.2-8-8V276.8c0-91.2 74.4-165.6 165.6-165.6s165.6 74.4 165.6 165.6v175.2c0 4-4 8-8 8z m-307.2-16h299.2V276.8c0-82.4-67.2-149.6-149.6-149.6s-149.6 67.2-149.6 149.6v167.2z" fill="#6A576D"></path>
                        <path d="M512 648.8m-343.2 0a343.2 343.2 0 1 0 686.4 0 343.2 343.2 0 1 0-686.4 0Z" fill="#BB7D9B"></path>
                        <path d="M577.6 609.6c0-36-29.6-65.6-65.6-65.6-36 0-65.6 29.6-65.6 65.6 0 24 13.6 44.8 32.8 56v80.8c0 18.4 14.4 32.8 32.8 32.8s32.8-14.4 32.8-32.8V665.6c19.2-11.2 32.8-32 32.8-56z" fill="#616465"></path>
                        <path d="M504 677.6c-19.2-11.2-32.8-32-32.8-56 0-36 29.6-65.6 65.6-65.6 5.6 0 11.2 0.8 16 2.4-11.2-8.8-25.6-14.4-40.8-14.4-36 0-65.6 29.6-65.6 65.6 0 24 13.6 44.8 32.8 56v80.8c0 17.6 14.4 32 32 32.8-4.8-5.6-7.2-12.8-7.2-20V677.6z" fill="#111318"></path>
                        <path d="M512 787.2c-22.4 0-40.8-18.4-40.8-40.8v-76.8c-20-13.6-32.8-36.8-32.8-60.8 0-40.8 32.8-73.6 73.6-73.6s73.6 32.8 73.6 73.6c0 24-12.8 47.2-32.8 60.8v76.8c0 22.4-18.4 40.8-40.8 40.8zM512 552c-32 0-57.6 25.6-57.6 57.6 0 20.8 10.4 39.2 28.8 49.6 2.4 1.6 4 4 4 7.2v80.8c0 13.6 11.2 24.8 24.8 24.8s24.8-11.2 24.8-24.8V665.6c0-3.2 1.6-5.6 4-7.2 17.6-10.4 28.8-28.8 28.8-49.6 0-31.2-25.6-56.8-57.6-56.8z" fill="#6A576D"></path>
                        <path d="M512 1000c-193.6 0-351.2-157.6-351.2-351.2S318.4 297.6 512 297.6s351.2 157.6 351.2 351.2S705.6 1000 512 1000z m0-685.6c-184.8 0-335.2 150.4-335.2 335.2S327.2 984 512 984s335.2-150.4 335.2-335.2S696.8 314.4 512 314.4z" fill="#6A576D"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500 font-medium mb-1">Access Levels</p>
                    <p class="text-3xl font-extrabold text-gray-900">{{ permissionLevels.length }}</p>
                </div>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm mb-6">
            <div class="relative w-full max-w-md">
                <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Search modules..."
                    class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-200 focus:ring-2 focus:ring-[#34554a] focus:border-transparent text-sm"
                />
            </div>
        </div>

        <!-- Module Selector -->
        <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm mb-6">
            <p class="text-sm font-semibold text-gray-700 mb-3">Choose Module</p>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="module in filteredModules"
                    :key="module.name"
                    @click="selectedModule = module.name"
                    class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition-colors"
                    :class="selectedModule === module.name ? 'bg-[#34554a] text-white border-[#34554a]' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50'"
                >
                    {{ module.name }}
                </button>
            </div>
        </div>

        <!-- Selected Module Matrix (X: Users, Y: Permissions) -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 w-max min-w-full">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
                <h3 class="text-base font-bold text-gray-900">{{ selectedModuleObj.name }} Permission Matrix</h3>
                <p class="text-xs text-gray-500 mt-1">Y-axis: roles/users, X-axis: permission levels. Tick one permission per user.</p>
            </div>
            <div>
                <table class="text-left min-w-max">
                    <thead>
                    <tr class="bg-[#34554a] text-white">
                        <th class="py-4 px-4 font-semibold text-sm sticky left-0 bg-[#34554a] z-10 min-w-[160px] border-r border-[#4a6b60]">
                            Role
                        </th>
                        <th class="py-4 px-4 font-semibold text-sm sticky left-[160px] bg-[#34554a] z-10 min-w-[220px] border-r border-[#4a6b60]">
                            User
                        </th>
                        <th v-for="level in visiblePermissionLevels" :key="`x-${level.value}`" class="py-4 px-6 font-semibold text-xs min-w-[140px] text-center border-l border-[#4a6b60]">
                            {{ level.label }}
                        </th>
                        <template v-if="selectedModule === 'Treatment Record'">
                            <th class="py-4 px-4 font-semibold text-xs min-w-[120px] text-center border-l border-[#4a6b60]">Prepared By</th>
                            <th class="py-4 px-4 font-semibold text-xs min-w-[120px] text-center border-l border-[#4a6b60]">Checked By</th>
                            <th class="py-4 px-4 font-semibold text-xs min-w-[120px] text-center border-l border-[#4a6b60]">Approved By</th>
                        </template>
                        <template v-if="usesCalvingWorkflowAssignment">
                            <th class="py-4 px-4 font-semibold text-xs min-w-[120px] text-center border-l border-[#4a6b60]">Issued By</th>
                            <th class="py-4 px-4 font-semibold text-xs min-w-[120px] text-center border-l border-[#4a6b60]">Verified By</th>
                            <th class="py-4 px-4 font-semibold text-xs min-w-[120px] text-center border-l border-[#4a6b60]">Witnessed By</th>
                            <th class="py-4 px-4 font-semibold text-xs min-w-[120px] text-center border-l border-[#4a6b60]">Approved By</th>
                        </template>
                        <template v-if="selectedTransferWorkflowFields.length">
                            <th
                                v-for="field in selectedTransferWorkflowFields"
                                :key="`transfer-workflow-head-${field.key}`"
                                class="py-4 px-4 font-semibold text-xs min-w-[150px] text-center border-l border-[#4a6b60]"
                            >
                                {{ field.label }}
                            </th>
                        </template>
                        <template v-if="selectedGenericWorkflowFields.length">
                            <th
                                v-for="field in selectedGenericWorkflowFields"
                                :key="`workflow-head-${field.key}`"
                                class="py-4 px-4 font-semibold text-xs min-w-[130px] text-center border-l border-[#4a6b60]"
                            >
                                {{ field.label }}
                            </th>
                        </template>
                    </tr>
                    </thead>
                    <tbody class="text-gray-700 text-sm">
                    <template v-for="group in usersByRole" :key="`group-${group.role}`">
                        <tr v-for="(user, idx) in group.users" :key="`row-${group.role}-${user.id}`" class="border-b border-gray-100">
                            <td class="py-3 px-4 font-medium text-gray-900 sticky left-0 bg-white z-[1] border-r border-gray-100">
                                <span v-if="idx === 0" class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700 border border-gray-200">
                                    {{ group.role }}
                                </span>
                            </td>
                            <td class="py-3 px-4 sticky left-[160px] bg-white z-[1] border-r border-gray-100">
                                <div class="font-medium text-gray-900">{{ user.name }}</div>
                                <div class="text-xs text-gray-500">{{ user.email }}</div>
                            </td>
                            <td
                                v-for="level in visiblePermissionLevels"
                                :key="`cell-${group.role}-${user.id}-${level.value}`"
                                class="py-3 px-6 text-center border-l border-gray-100"
                            >
                                <input
                                    type="checkbox"
                                    :checked="isPermissionChecked(selectedModuleObj.name, user.id, level.value)"
                                    @change="togglePermission(selectedModuleObj.name, user.id, level.value, $event.target.checked)"
                                    class="w-4 h-4 text-[#34554a] border-gray-300 rounded focus:ring-[#34554a]"
                                >
                            </td>
                            <template v-if="selectedModule === 'Treatment Record'">
                                <td class="py-3 px-4 text-center border-l border-gray-100">
                                    <input
                                        type="checkbox"
                                        :checked="isWorkflowUserChecked('prepared_by_user_ids', user.id)"
                                        @change="toggleWorkflowUser('prepared_by_user_ids', user.id, $event.target.checked)"
                                        class="w-4 h-4 text-[#34554a] border-gray-300 rounded focus:ring-[#34554a]"
                                    >
                                </td>
                                <td class="py-3 px-4 text-center border-l border-gray-100">
                                    <input
                                        type="checkbox"
                                        :checked="isWorkflowUserChecked('checked_by_user_ids', user.id)"
                                        @change="toggleWorkflowUser('checked_by_user_ids', user.id, $event.target.checked)"
                                        class="w-4 h-4 text-[#34554a] border-gray-300 rounded focus:ring-[#34554a]"
                                    >
                                </td>
                                <td class="py-3 px-4 text-center border-l border-gray-100">
                                    <input
                                        type="checkbox"
                                        :checked="isWorkflowUserChecked('approved_by_user_ids', user.id)"
                                        @change="toggleWorkflowUser('approved_by_user_ids', user.id, $event.target.checked)"
                                        class="w-4 h-4 text-[#34554a] border-gray-300 rounded focus:ring-[#34554a]"
                                    >
                                </td>
                            </template>
                            <template v-if="usesCalvingWorkflowAssignment">
                                <td class="py-3 px-4 text-center border-l border-gray-100">
                                    <input
                                        type="checkbox"
                                        :checked="isCalvingWorkflowUserChecked('issued_by_user_ids', user.id)"
                                        @change="toggleCalvingWorkflowUser('issued_by_user_ids', user.id, $event.target.checked)"
                                        class="w-4 h-4 text-[#34554a] border-gray-300 rounded focus:ring-[#34554a]"
                                    >
                                </td>
                                <td class="py-3 px-4 text-center border-l border-gray-100">
                                    <input
                                        type="checkbox"
                                        :checked="isCalvingWorkflowUserChecked('verified_by_user_ids', user.id)"
                                        @change="toggleCalvingWorkflowUser('verified_by_user_ids', user.id, $event.target.checked)"
                                        class="w-4 h-4 text-[#34554a] border-gray-300 rounded focus:ring-[#34554a]"
                                    >
                                </td>
                                <td class="py-3 px-4 text-center border-l border-gray-100">
                                    <input
                                        type="checkbox"
                                        :checked="isCalvingWorkflowUserChecked('witnessed_by_user_ids', user.id)"
                                        @change="toggleCalvingWorkflowUser('witnessed_by_user_ids', user.id, $event.target.checked)"
                                        class="w-4 h-4 text-[#34554a] border-gray-300 rounded focus:ring-[#34554a]"
                                    >
                                </td>
                                <td class="py-3 px-4 text-center border-l border-gray-100">
                                    <input
                                        type="checkbox"
                                        :checked="isCalvingWorkflowUserChecked('approved_by_user_ids', user.id)"
                                        @change="toggleCalvingWorkflowUser('approved_by_user_ids', user.id, $event.target.checked)"
                                        class="w-4 h-4 text-[#34554a] border-gray-300 rounded focus:ring-[#34554a]"
                                    >
                                </td>
                            </template>
                            <template v-if="selectedTransferWorkflowFields.length">
                                <td
                                    v-for="field in selectedTransferWorkflowFields"
                                    :key="`transfer-workflow-${selectedModule}-${user.id}-${field.key}`"
                                    class="py-3 px-4 text-center border-l border-gray-100"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="isTransferWorkflowUserChecked(field.key, user.id)"
                                        @change="toggleTransferWorkflowUser(field.key, user.id, $event.target.checked)"
                                        class="w-4 h-4 text-[#34554a] border-gray-300 rounded focus:ring-[#34554a]"
                                    >
                                </td>
                            </template>
                            <template v-if="selectedGenericWorkflowFields.length">
                                <td
                                    v-for="field in selectedGenericWorkflowFields"
                                    :key="`generic-workflow-${selectedModule}-${user.id}-${field.key}`"
                                    class="py-3 px-4 text-center border-l border-gray-100"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="isGenericWorkflowUserChecked(selectedModule, field.key, user.id)"
                                        @change="toggleGenericWorkflowUser(selectedModule, field.key, user.id, $event.target.checked)"
                                        class="w-4 h-4 text-[#34554a] border-gray-300 rounded focus:ring-[#34554a]"
                                    >
                                </td>
                            </template>
                        </tr>
                    </template>
                    </tbody>
                </table>
            </div>
        </div>

    </AppLayout>
</template>
