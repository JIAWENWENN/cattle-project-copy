<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, watch } from 'vue';
import { usePage, router, useForm } from '@inertiajs/vue3';
import { CheckCircle, ChevronRight, Download, Eye, Lock, RotateCcw } from 'lucide-vue-next';
import WorkflowEndorsementCards from '@/Components/WorkflowEndorsementCards.vue';

defineOptions({
    layout: (h, page) => h(AppLayout, { title: 'Daily Operation Master List (DOML)', parent: 'Cattle', parentUrl: '/cattle' }, () => page)
});

const page = usePage();
const estates = computed(() => page.props.estates || []);
const selectedLadang = ref(page.props.selectedLadang || '');
const selectedMonth = ref(page.props.selectedMonth || new Date().getMonth() + 1);
const selectedYear = ref(page.props.selectedYear || new Date().getFullYear());
const domlNumber = computed(() => page.props.domlNumber || '-');
const daysInMonth = computed(() => page.props.daysInMonth || []);
const categories = computed(() => page.props.categories || {});
const dailyData = computed(() => page.props.dailyData || {});
const workflowDocuments = computed(() => page.props.workflowDocuments || {});
const workflowCurrentStep = computed(() => Number(page.props.workflowCurrentStep || 0));
const workflowIsCompleted = computed(() => Boolean(page.props.workflowIsCompleted || false));
const workflowCompletedAt = computed(() => page.props.workflowCompletedAt || null);
const domlWorkflowAssignments = computed(() => page.props.domlWorkflowAssignments || {});
const userRole = computed(() => page.props.auth?.user?.role || '');
const userName = computed(() => page.props.auth?.user?.name || '');
const userId = computed(() => Number(page.props.auth?.user?.id || 0));

const selectedWeek = ref(page.props.selectedWeek || '1');

watch(() => page.props.selectedWeek, (week) => {
    if (week !== undefined && week !== selectedWeek.value) {
        selectedWeek.value = week || '1';
    }
});

watch(() => page.props.selectedLadang, (ladang) => {
    if (ladang !== undefined && ladang !== selectedLadang.value) {
        selectedLadang.value = ladang || '';
    }
});

watch(() => page.props.selectedMonth, (month) => {
    if (month !== undefined && Number(month) !== Number(selectedMonth.value)) {
        selectedMonth.value = Number(month);
    }
});

watch(() => page.props.selectedYear, (year) => {
    if (year !== undefined && Number(year) !== Number(selectedYear.value)) {
        selectedYear.value = Number(year);
    }
});

const weeks = [
    { value: '1', label: 'Week 1 (Days 1-7)' },
    { value: '2', label: 'Week 2 (Days 8-14)' },
    { value: '3', label: 'Week 3 (Days 15-21)' },
    { value: '4', label: 'Week 4 (Days 22-End)' },
];

const showWeek1 = computed(() => selectedWeek.value === '1');
const showWeek2 = computed(() => selectedWeek.value === '2');
const showWeek3 = computed(() => selectedWeek.value === '3');
const showWeek4 = computed(() => selectedWeek.value === '4');

const months = [
    { value: 1, label: 'January' },
    { value: 2, label: 'February' },
    { value: 3, label: 'March' },
    { value: 4, label: 'April' },
    { value: 5, label: 'May' },
    { value: 6, label: 'June' },
    { value: 7, label: 'July' },
    { value: 8, label: 'August' },
    { value: 9, label: 'September' },
    { value: 10, label: 'October' },
    { value: 11, label: 'November' },
    { value: 12, label: 'December' },
];

const years = computed(() => {
    const currentYear = new Date().getFullYear();
    return Array.from({ length: currentYear - 2020 + 1 }, (_, i) => 2020 + i);
});

const canExport = computed(() => {
    return selectedLadang.value && selectedMonth.value && selectedYear.value && selectedWeek.value;
});

const canShowWorkflow = computed(() => {
    return Boolean(
        selectedLadang.value
        && selectedMonth.value
        && selectedYear.value
        && selectedWeek.value
    );
});

const canEditDoml = computed(() => {
    const permissions = page.props.auth?.permissions?.['Daily Operation DOML'] || [];
    const perms = Array.isArray(permissions) ? permissions : String(permissions).split(',');
    return perms.includes('full') || perms.includes('edit') || perms.includes('create') || String(page.props.auth?.user?.role || '').toLowerCase() === 'admin';
});

const isDomlLocked = computed(() => (canShowWorkflow.value && workflowIsCompleted.value) || !canEditDoml.value);

const selectedWeekLabel = computed(() => {
    const match = weeks.find((w) => w.value === String(selectedWeek.value));
    return match?.label || `Week ${selectedWeek.value}`;
});

const ladangOptions = computed(() => {
    return estates.value.map(e => ({ value: e.name, label: e.name }));
});

const applyFilters = () => {
    router.get('/cattle/daily-operation', {
        ladang: selectedLadang.value,
        month: selectedMonth.value,
        year: selectedYear.value,
        week: selectedWeek.value,
    }, { preserveState: true });
};

watch(ladangOptions, (options) => {
    if (!selectedLadang.value && options.length > 0) {
        selectedLadang.value = options[0].value;
        applyFilters();
    }
}, { immediate: true });

const getCategoryRows = computed(() => {
    const rows = [];
    const cats = categories.value || {};
    const data = dailyData.value || {};
    
    if (typeof cats !== 'object' || cats === null) {
        return rows;
    }
    
    for (const [code, cat] of Object.entries(cats)) {
        const catData = data[code] || { previous: 0, daily: [], strayed: 0, notes: [] };
        const notesArr = catData.notes || [];
        rows.push({
            code,
            name: cat.name || code,
            name_my: cat.name_my || '',
            previous: catData.previous || 0,
            daily: catData.daily || [],
            strayed: catData.strayed || 0,
            notes: Array.isArray(notesArr) ? notesArr.join(', ') : '',
        });
    }
    
    if (data.total) {
        rows.push({
            code: 'total',
            name: 'Total',
            name_my: 'Jumlah',
            previous: data.total.previous || 0,
            daily: data.total.daily || [],
            strayed: data.total.strayed || 0,
            notes: '',
            isTotal: true,
        });
    }
    
    return rows;
});

const editableRows = ref([]);
const editableMap = computed(() => Object.fromEntries(editableRows.value.map((row) => [row.code, row])));
const totalRow = computed(() => {
    if (!editableRows.value.length) return null;
    const dayCount = (daysInMonth.value || []).length;
    const total = {
        code: 'total',
        name: 'Total',
        name_my: 'Jumlah',
        previous: 0,
        daily: Array.from({ length: dayCount }, () => 0),
        missing: 0,
        remark: '',
        isTotal: true,
    };

    for (const row of editableRows.value) {
        total.previous += Number(row.previous || 0);
        total.missing += Number(row.missing || 0);
        for (let i = 0; i < dayCount; i++) {
            total.daily[i] += Number(row.daily?.[i] || 0);
        }
    }

    return total;
});
const tableRows = computed(() => totalRow.value ? [...editableRows.value, totalRow.value] : [...editableRows.value]);

const buildEditableRows = () => {
    editableRows.value = getCategoryRows.value
        .filter((row) => !row.isTotal)
        .map((row) => ({
            code: row.code,
            name: row.name,
            name_my: row.name_my,
            previous: row.previous || 0,
            daily: Array.isArray(row.daily) ? [...row.daily] : [],
            missing: row.strayed || 0,
            remark: row.notes || '',
        }));
};

watch(getCategoryRows, buildEditableRows, { immediate: true });

const getOpeningBal = (row, startDay) => {
    if (!row) return 0;
    if (startDay === 0) return row.previous || 0;
    let total = row.previous || 0;
    const daily = row.daily || [];
    for (let i = 0; i < startDay; i++) {
        if (daily[i]) total += daily[i];
    }
    return total;
};

const week1Days = computed(() => {
    const arr = daysInMonth.value || [];
    return arr.slice(0, 7);
});
const week2Days = computed(() => {
    const arr = daysInMonth.value || [];
    return arr.slice(7, 14);
});
const week3Days = computed(() => {
    const arr = daysInMonth.value || [];
    return arr.slice(14, 21);
});
const week4Days = computed(() => {
    const arr = daysInMonth.value || [];
    return arr.slice(21);
});

const dutyPersonNames = computed(() => page.props.dutyPersonNames || []);

const dutyPersons = ref([
    { id: 1, name: '' },
    { id: 2, name: '' },
    { id: 3, name: '' },
    { id: 4, name: '' },
]);

const buildDutyPersons = () => {
    const names = Array.isArray(dutyPersonNames.value) ? dutyPersonNames.value : [];
    dutyPersons.value = Array.from({ length: Math.max(4, names.length) }, (_, index) => ({
        id: index + 1,
        name: String(names[index] || '').trim(),
    }));
};

watch(dutyPersonNames, buildDutyPersons, { immediate: true });

const workflowSteps = [
    { id: 1, label: 'First Step', role_name: 'Pengembala', role: 'livestock' },
    { id: 2, label: 'Second Step', role_name: 'Pemb Kanan Ternakan', role: 'security' },
    { id: 3, label: 'Third Step', role_name: 'Pemb Kanan Keselamatan', role: 'supervisor' },
    { id: 4, label: 'Last Step', role_name: 'Wakil Ladang', role: 'penyelia' },
];

const currentWorkflowStep = computed(() => workflowCurrentStep.value);
const allWorkflowStepsUploaded = computed(() => workflowSteps.every((_, index) => hasWorkflowDoc(index)));
const workflowUploading = ref(false);
const fileInputKey = ref(0);

const docUploadForm = ref({
    name: userName.value || '',
    date: new Date().toISOString().split('T')[0],
    hasFile: false,
});

const workflowForm = useForm({
    ladang: '',
    month: '',
    year: '',
    week: '',
    step_index: 0,
    name: '',
    date: '',
    signed_document: null,
});

const getStepDocument = (stepIndex) => {
    const docs = workflowDocuments.value || {};
    return docs[stepIndex] || docs[String(stepIndex)] || null;
};

const hasWorkflowDoc = (index) => !!getStepDocument(index);

const getAssignmentKeyByStep = (stepIndex) => ({
    0: 'pengembala_user_ids',
    1: 'pembantu_kanan_ternakan_user_ids',
    2: 'pembantu_kanan_keselamatan_user_ids',
    3: 'wakil_ladang_user_ids',
}[stepIndex] || null);

const isAdmin = computed(() => String(userRole.value || '').toLowerCase() === 'admin');

const canUserActStep = (stepIndex) => {
    if (isAdmin.value) return true;
    const key = getAssignmentKeyByStep(stepIndex);
    if (!key) return false;
    const ids = Array.isArray(domlWorkflowAssignments.value?.[key]) ? domlWorkflowAssignments.value[key] : [];
    return ids.map((v) => Number(v)).includes(userId.value);
};

const getUserWorkflowStepIndexes = () => {
    if (isAdmin.value) return [0, 1, 2, 3];
    const indexes = [];
    for (let i = 0; i < workflowSteps.length; i++) {
        if (canUserActStep(i)) indexes.push(i);
    }
    return indexes;
};

const canUploadStep = (stepIndex) => {
    if (workflowIsCompleted.value) return false;
    if (!userRole.value) return false;
    if (!canUserActStep(stepIndex)) return false;

    const currentStep = currentWorkflowStep.value;
    if (stepIndex === 3) return stepIndex <= currentStep;
    if (stepIndex === currentStep) return true;
    if (stepIndex < currentStep) {
        const nextStepDoc = getStepDocument(stepIndex + 1);
        return !nextStepDoc;
    }
    return false;
};

const canViewStep = (stepIndex) => {
    if (!getStepDocument(stepIndex)) return false;
    if (isAdmin.value) return true;
    return getUserWorkflowStepIndexes().includes(stepIndex);
};

const canDownloadPrevious = (stepIndex) => {
    if (stepIndex === 0) return false;
    if (isAdmin.value) return !!getStepDocument(stepIndex - 1);
    return getUserWorkflowStepIndexes().includes(stepIndex) && !!getStepDocument(stepIndex - 1);
};

const resetFileInput = () => {
    docUploadForm.value.hasFile = false;
    fileInputKey.value++;
};

const handleWorkflowFile = (event) => {
    const file = event.target.files?.[0] || null;
    docUploadForm.value.hasFile = !!file;
};

const uploadWorkflowDoc = (stepIndex) => {
    const fileInput = document.querySelector(`#doml-file-input-${stepIndex}`);
    const file = fileInput?.files?.[0] || null;
    if (!file || workflowUploading.value) return;
    if (!docUploadForm.value.name || !docUploadForm.value.date) {
        alert('Please fill in all fields');
        return;
    }

    workflowUploading.value = true;
    workflowForm.ladang = selectedLadang.value;
    workflowForm.month = selectedMonth.value;
    workflowForm.year = selectedYear.value;
    workflowForm.week = selectedWeek.value;
    workflowForm.step_index = stepIndex;
    workflowForm.name = docUploadForm.value.name;
    workflowForm.date = docUploadForm.value.date;
    workflowForm.signed_document = file;

    workflowForm.post('/cattle/daily-operation/workflow/upload', {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            workflowUploading.value = false;
            workflowForm.signed_document = null;
            resetFileInput();
        },
    });
};

const submitWorkflowEndorsementUpload = ({ stepIndex, file, name, date }) => {
    if (!file || workflowUploading.value) return;

    workflowUploading.value = true;
    workflowForm.ladang = selectedLadang.value;
    workflowForm.month = selectedMonth.value;
    workflowForm.year = selectedYear.value;
    workflowForm.week = selectedWeek.value;
    workflowForm.step_index = stepIndex;
    workflowForm.name = name || userName.value || '';
    workflowForm.date = date || new Date().toISOString().split('T')[0];
    workflowForm.signed_document = file;

    workflowForm.post('/cattle/daily-operation/workflow/upload', {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            workflowUploading.value = false;
            workflowForm.signed_document = null;
            resetFileInput();
        },
    });
};

const downloadWorkflowDoc = (stepIndex) => {
    const params = new URLSearchParams({
        ladang: selectedLadang.value || '',
        month: String(selectedMonth.value || ''),
        year: String(selectedYear.value || ''),
        week: String(selectedWeek.value || ''),
    });
    window.open(`/cattle/daily-operation/workflow/download/${stepIndex}?${params.toString()}`, '_blank');
};

const downloadPreviousWorkflowDoc = (stepIndex) => {
    if (stepIndex <= 0) return;
    downloadWorkflowDoc(stepIndex - 1);
};

const addDutyPerson = () => {
    const newId = dutyPersons.value.length > 0 
        ? Math.max(...dutyPersons.value.map(d => d.id)) + 1 
        : 1;
    dutyPersons.value.push({ id: newId, name: '' });
};

const removeDutyPerson = (id) => {
    if (dutyPersons.value.length > 1) {
        dutyPersons.value = dutyPersons.value.filter(d => d.id !== id);
    }
};

const exportDoml = () => {
    if (!canExport.value) {
        alert('Please select a specific estate and week before exporting.');
        return;
    }

    const params = new URLSearchParams({
        ladang: selectedLadang.value,
        month: selectedMonth.value,
        year: selectedYear.value,
        week: selectedWeek.value,
    });

    dutyPersons.value.forEach((person, index) => {
        const name = String(person.name || '').trim();
        if (name) {
            params.append(`duty_person_${index + 1}`, name);
        }
    });

    window.location.href = `/cattle/daily-operation/export?${params.toString()}`;
};

const saveDoml = () => {
    if (isDomlLocked.value) return;

    router.post('/cattle/daily-operation', {
        ladang: selectedLadang.value,
        month: selectedMonth.value,
        year: selectedYear.value,
        week: selectedWeek.value,
        duty_persons: dutyPersons.value.map((person) => String(person.name || '').trim()),
        entries: editableRows.value.map((row) => ({
            category_code: row.code,
            daily_values: row.daily.map((v) => Number(v || 0)),
            missing: Number(row.missing || 0),
            remark: row.remark || '',
        })),
    }, {
        preserveScroll: true,
    });
};

const workflowPayload = () => ({
    ladang: selectedLadang.value,
    month: selectedMonth.value,
    year: selectedYear.value,
    week: selectedWeek.value,
});

const markWorkflowCompleted = () => {
    if (!isAdmin.value || !allWorkflowStepsUploaded.value || workflowIsCompleted.value) return;

    router.post('/cattle/daily-operation/workflow/complete', workflowPayload(), {
        preserveScroll: true,
    });
};

const reopenWorkflow = () => {
    if (!isAdmin.value || !workflowIsCompleted.value) return;

    router.post('/cattle/daily-operation/workflow/reopen', workflowPayload(), {
        preserveScroll: true,
    });
};
</script>

<template>
    <div class="w-full">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">DAILY OPERATION MASTER LIST (DOML)</h1>
            <p class="text-sm text-gray-500 mt-1">NO. DOML: {{ domlNumber }}</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
            <div class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1">Estate</label>
                    <select
                        v-model="selectedLadang"
                        @change="applyFilters"
                        class="px-4 py-2 border border-gray-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-[#34554a]"
                    >
                        <option v-for="opt in ladangOptions" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1">Month</label>
                    <select
                        v-model="selectedMonth"
                        @change="applyFilters"
                        class="px-4 py-2 border border-gray-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-[#34554a]"
                    >
                        <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1">Year</label>
                    <select
                        v-model="selectedYear"
                        @change="applyFilters"
                        class="px-4 py-2 border border-gray-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-[#34554a]"
                    >
                        <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1">Week</label>
                    <select
                        v-model="selectedWeek"
                        @change="applyFilters"
                        class="px-4 py-2 border border-gray-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-[#34554a]"
                    >
                        <option v-for="w in weeks" :key="w.value" :value="w.value">{{ w.label }}</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button
                        @click="saveDoml"
                        :disabled="isDomlLocked"
                        class="px-4 py-2 rounded-lg text-sm font-medium mr-2"
                        :class="isDomlLocked ? 'bg-gray-300 text-gray-500 cursor-not-allowed' : 'bg-blue-600 text-white hover:bg-blue-700'"
                    >
                        Save
                    </button>
                    <div class="relative group">
                        <button
                            @click="exportDoml"
                            :disabled="!canExport"
                            class="px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-2"
                            :class="canExport ? 'bg-green-600 text-white hover:bg-green-700' : 'bg-gray-300 text-gray-500 cursor-not-allowed'"
                        >
                            Export
                        </button>
                        <div v-if="!canExport" class="absolute bottom-full left-0 mb-2 px-3 py-2 bg-gray-800 text-white text-xs rounded-lg opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none z-10">
                            Select Estate, Month, Year and a specific Week to export
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="doml-content">
            <div v-if="canShowWorkflow && workflowIsCompleted" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 flex items-center gap-2">
                <Lock class="w-4 h-4 flex-shrink-0" />
                <span>Completed DOML workflow{{ workflowCompletedAt ? ` on ${workflowCompletedAt}` : '' }}. Reopen the workflow to edit this record.</span>
            </div>
            <div v-else-if="!canEditDoml" class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 flex items-center gap-2">
                <Lock class="w-4 h-4 flex-shrink-0" />
                <span>You do not have permission to edit this record. View-only mode active.</span>
            </div>

            <div v-if="daysInMonth.length === 0" class="text-center py-8 text-gray-500">
                No data available. Please select a valid month and year.
            </div>
            <div v-else>

        <!-- Week 1: Days 1-7 -->
        <div v-if="showWeek1" class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="bg-[#34554a] text-white">
                            <th rowspan="2" class="p-3 font-semibold text-left border-r border-gray-600">Category</th>
                            <th rowspan="2" class="p-3 font-semibold text-center border-r border-gray-600">Opening Bal</th>
                            <th :colspan="week1Days.length" class="p-3 font-semibold text-center">Week 1 - Days</th>
                            <th rowspan="2" class="p-3 font-semibold text-center border-l border-gray-600">Missing</th>
                            <th rowspan="2" class="p-3 font-semibold text-left">Remark / Doc Reference</th>
                        </tr>
                        <tr class="bg-[#34554a] text-white">
                            <th v-for="(day, idx) in week1Days" :key="idx" class="p-2 font-semibold text-center">
                                {{ day.day }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="row in tableRows" :key="row.code"
                            :class="row.isTotal ? 'bg-gray-50 font-bold' : 'hover:bg-gray-50'">
                            <td class="p-3 border-r border-gray-200">
                                <div>{{ row.name }}</div>
                                <div class="text-xs text-gray-500 italic">{{ row.name_my }}</div>
                            </td>
                            <td class="p-3 text-center border-r border-gray-200">{{ getOpeningBal(row, 0) }}</td>
                            <td v-for="(val, idx) in row.daily.slice(0, 7)" :key="idx" class="p-1 text-center">
                                <input
                                    v-if="!row.isTotal"
                                    v-model.number="editableMap[row.code].daily[idx]"
                                    type="number"
                                    :disabled="isDomlLocked"
                                    class="w-14 border border-gray-200 rounded px-1 py-1 text-xs text-center"
                                    :class="isDomlLocked ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : ''"
                                />
                                <span v-else>{{ val }}</span>
                            </td>
                            <td class="p-1 text-center border-l border-gray-200">
                                <input
                                    v-if="!row.isTotal"
                                    v-model.number="editableMap[row.code].missing"
                                    type="number"
                                    :disabled="isDomlLocked"
                                    class="w-14 border border-gray-200 rounded px-1 py-1 text-xs text-center"
                                    :class="isDomlLocked ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : ''"
                                />
                                <span v-else>{{ row.missing }}</span>
                            </td>
                            <td class="p-1 text-xs">
                                <input
                                    v-if="!row.isTotal"
                                    v-model="editableMap[row.code].remark"
                                    type="text"
                                    :disabled="isDomlLocked"
                                    class="w-full border border-gray-200 rounded px-2 py-1 text-xs"
                                    :class="isDomlLocked ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : ''"
                                />
                                <span v-else>{{ row.remark }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="showWeek2" class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="bg-[#34554a] text-white">
                            <th rowspan="2" class="p-3 font-semibold text-left border-r border-gray-600">Category</th>
                            <th rowspan="2" class="p-3 font-semibold text-center border-r border-gray-600">Opening Bal</th>
                            <th :colspan="week2Days.length" class="p-3 font-semibold text-center">Week 2 - Days</th>
                            <th rowspan="2" class="p-3 font-semibold text-center border-l border-gray-600">Missing</th>
                            <th rowspan="2" class="p-3 font-semibold text-left">Remark / Doc Reference</th>
                        </tr>
                        <tr class="bg-[#34554a] text-white">
                            <th v-for="(day, idx) in week2Days" :key="idx" class="p-2 font-semibold text-center">
                                {{ day.day }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="row in tableRows" :key="row.code + '-2'"
                            :class="row.isTotal ? 'bg-gray-50 font-bold' : 'hover:bg-gray-50'">
                            <td class="p-3 border-r border-gray-200">
                                <div>{{ row.name }}</div>
                                <div class="text-xs text-gray-500 italic">{{ row.name_my }}</div>
                            </td>
                            <td class="p-3 text-center border-r border-gray-200">{{ getOpeningBal(row, 7) }}</td>
                            <td v-for="(val, idx) in row.daily.slice(7, 14)" :key="idx" class="p-1 text-center">
                                <input
                                    v-if="!row.isTotal"
                                    v-model.number="editableMap[row.code].daily[idx + 7]"
                                    type="number"
                                    :disabled="isDomlLocked"
                                    class="w-14 border border-gray-200 rounded px-1 py-1 text-xs text-center"
                                    :class="isDomlLocked ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : ''"
                                />
                                <span v-else>{{ val }}</span>
                            </td>
                            <td class="p-1 text-center border-l border-gray-200">
                                <input
                                    v-if="!row.isTotal"
                                    v-model.number="editableMap[row.code].missing"
                                    type="number"
                                    :disabled="isDomlLocked"
                                    class="w-14 border border-gray-200 rounded px-1 py-1 text-xs text-center"
                                    :class="isDomlLocked ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : ''"
                                />
                                <span v-else>{{ row.missing }}</span>
                            </td>
                            <td class="p-1 text-xs">
                                <input
                                    v-if="!row.isTotal"
                                    v-model="editableMap[row.code].remark"
                                    type="text"
                                    :disabled="isDomlLocked"
                                    class="w-full border border-gray-200 rounded px-2 py-1 text-xs"
                                    :class="isDomlLocked ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : ''"
                                />
                                <span v-else>{{ row.remark }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="showWeek3" class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="bg-[#34554a] text-white">
                            <th rowspan="2" class="p-3 font-semibold text-left border-r border-gray-600">Category</th>
                            <th rowspan="2" class="p-3 font-semibold text-center border-r border-gray-600">Opening Bal</th>
                            <th :colspan="week3Days.length" class="p-3 font-semibold text-center">Week 3 - Days</th>
                            <th rowspan="2" class="p-3 font-semibold text-center border-l border-gray-600">Missing</th>
                            <th rowspan="2" class="p-3 font-semibold text-left">Remark / Doc Reference</th>
                        </tr>
                        <tr class="bg-[#34554a] text-white">
                            <th v-for="(day, idx) in week3Days" :key="idx" class="p-2 font-semibold text-center">
                                {{ day.day }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="row in tableRows" :key="row.code + '-3'"
                            :class="row.isTotal ? 'bg-gray-50 font-bold' : 'hover:bg-gray-50'">
                            <td class="p-3 border-r border-gray-200">
                                <div>{{ row.name }}</div>
                                <div class="text-xs text-gray-500 italic">{{ row.name_my }}</div>
                            </td>
                            <td class="p-3 text-center border-r border-gray-200">{{ getOpeningBal(row, 14) }}</td>
                            <td v-for="(val, idx) in row.daily.slice(14, 21)" :key="idx" class="p-1 text-center">
                                <input
                                    v-if="!row.isTotal"
                                    v-model.number="editableMap[row.code].daily[idx + 14]"
                                    type="number"
                                    :disabled="isDomlLocked"
                                    class="w-14 border border-gray-200 rounded px-1 py-1 text-xs text-center"
                                    :class="isDomlLocked ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : ''"
                                />
                                <span v-else>{{ val }}</span>
                            </td>
                            <td class="p-1 text-center border-l border-gray-200">
                                <input
                                    v-if="!row.isTotal"
                                    v-model.number="editableMap[row.code].missing"
                                    type="number"
                                    :disabled="isDomlLocked"
                                    class="w-14 border border-gray-200 rounded px-1 py-1 text-xs text-center"
                                    :class="isDomlLocked ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : ''"
                                />
                                <span v-else>{{ row.missing }}</span>
                            </td>
                            <td class="p-1 text-xs">
                                <input
                                    v-if="!row.isTotal"
                                    v-model="editableMap[row.code].remark"
                                    type="text"
                                    :disabled="isDomlLocked"
                                    class="w-full border border-gray-200 rounded px-2 py-1 text-xs"
                                    :class="isDomlLocked ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : ''"
                                />
                                <span v-else>{{ row.remark }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="showWeek4" class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="bg-[#34554a] text-white">
                            <th rowspan="2" class="p-3 font-semibold text-left border-r border-gray-600">Category</th>
                            <th rowspan="2" class="p-3 font-semibold text-center border-r border-gray-600">Opening Bal</th>
                            <th :colspan="week4Days.length" class="p-3 font-semibold text-center">Week 4 - Days</th>
                            <th rowspan="2" class="p-3 font-semibold text-center border-l border-gray-600">Missing</th>
                            <th rowspan="2" class="p-3 font-semibold text-left">Remark / Doc Reference</th>
                        </tr>
                        <tr class="bg-[#34554a] text-white">
                            <th v-for="(day, idx) in week4Days" :key="idx" class="p-2 font-semibold text-center">
                                {{ day.day }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="row in tableRows" :key="row.code + '-4'"
                            :class="row.isTotal ? 'bg-gray-50 font-bold' : 'hover:bg-gray-50'">
                            <td class="p-3 border-r border-gray-200">
                                <div>{{ row.name }}</div>
                                <div class="text-xs text-gray-500 italic">{{ row.name_my }}</div>
                            </td>
                            <td class="p-3 text-center border-r border-gray-200">{{ getOpeningBal(row, 21) }}</td>
                            <td v-for="(val, idx) in row.daily.slice(21)" :key="idx" class="p-1 text-center">
                                <input
                                    v-if="!row.isTotal"
                                    v-model.number="editableMap[row.code].daily[idx + 21]"
                                    type="number"
                                    :disabled="isDomlLocked"
                                    class="w-14 border border-gray-200 rounded px-1 py-1 text-xs text-center"
                                    :class="isDomlLocked ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : ''"
                                />
                                <span v-else>{{ val }}</span>
                            </td>
                            <td class="p-1 text-center border-l border-gray-200">
                                <input
                                    v-if="!row.isTotal"
                                    v-model.number="editableMap[row.code].missing"
                                    type="number"
                                    :disabled="isDomlLocked"
                                    class="w-14 border border-gray-200 rounded px-1 py-1 text-xs text-center"
                                    :class="isDomlLocked ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : ''"
                                />
                                <span v-else>{{ row.missing }}</span>
                            </td>
                            <td class="p-1 text-xs">
                                <input
                                    v-if="!row.isTotal"
                                    v-model="editableMap[row.code].remark"
                                    type="text"
                                    :disabled="isDomlLocked"
                                    class="w-full border border-gray-200 rounded px-2 py-1 text-xs"
                                    :class="isDomlLocked ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : ''"
                                />
                                <span v-else>{{ row.remark }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-semibold text-lg">Duty Person</h3>
                <button
                    @click="addDutyPerson"
                    :disabled="isDomlLocked"
                    class="px-3 py-1 text-white text-xs rounded-lg"
                    :class="isDomlLocked ? 'bg-gray-400 cursor-not-allowed' : 'bg-[#34554a] hover:bg-[#2a443b]'"
                >
                    + Add
                </button>
            </div>
            <div class="space-y-3">
                <div v-for="(person, index) in dutyPersons" :key="person.id" class="flex items-center gap-3">
                    <span class="text-sm font-medium text-gray-500 w-8">{{ index + 1 }}.</span>
                    <input
                        v-model="person.name"
                        type="text"
                        placeholder="Enter name"
                        :disabled="isDomlLocked"
                        class="flex-1 px-3 py-2 border border-gray-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-[#34554a]"
                        :class="isDomlLocked ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : ''"
                    />
                    <button
                        @click="removeDutyPerson(person.id)"
                        class="text-sm"
                        :disabled="dutyPersons.length <= 1 || isDomlLocked"
                        :class="(dutyPersons.length <= 1 || isDomlLocked) ? 'text-gray-300 cursor-not-allowed' : 'text-red-500 hover:text-red-700'"
                    >
                        ×
                    </button>
                </div>
            </div>
        </div>

        <div v-if="canShowWorkflow" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mt-6">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <div>
                    <h3 class="font-semibold text-lg">Workflow — {{ selectedWeekLabel }}</h3>
                    <p class="text-sm text-gray-500">
                        {{ selectedLadang }} · {{ selectedMonth }}/{{ selectedYear }} ·
                        {{ workflowIsCompleted ? 'Completed' : `Step ${Math.min(currentWorkflowStep + 1, workflowSteps.length)} / ${workflowSteps.length}` }}
                    </p>
                </div>
                <div v-if="isAdmin" class="flex items-center gap-2">
                    <button
                        v-if="!workflowIsCompleted"
                        @click="markWorkflowCompleted"
                        :disabled="!allWorkflowStepsUploaded"
                        class="px-3 py-2 rounded-lg text-xs font-medium flex items-center gap-2"
                        :class="allWorkflowStepsUploaded ? 'bg-emerald-600 text-white hover:bg-emerald-700' : 'bg-gray-200 text-gray-500 cursor-not-allowed'"
                    >
                        <CheckCircle class="w-4 h-4" />
                        Mark as Complete
                    </button>
                    <button
                        v-else
                        @click="reopenWorkflow"
                        class="px-3 py-2 rounded-lg text-xs font-medium bg-amber-600 text-white hover:bg-amber-700 flex items-center gap-2"
                    >
                        <RotateCcw class="w-4 h-4" />
                        Reopen
                    </button>
                </div>
            </div>

            <div class="max-w-xl mx-auto flex justify-center gap-2 flex-wrap p-4 bg-gray-50 rounded-xl border border-gray-200 mb-5">
                <template v-for="(step, index) in workflowSteps" :key="step.id">
                    <div class="flex flex-col items-center min-w-[70px]">
                        <div
                            class="w-10 h-10 rounded-full flex items-center justify-center text-sm mb-1"
                            :class="currentWorkflowStep >= index + 1 ? 'bg-[#34554a] text-white' : 'bg-gray-200 text-gray-400'"
                        >
                            <CheckCircle v-if="currentWorkflowStep >= index + 1" class="w-5 h-5" />
                            <span v-else>{{ index + 1 }}</span>
                        </div>
                        <span
                            class="text-[10px] text-center"
                            :class="currentWorkflowStep >= index + 1 ? 'text-[#34554a] font-semibold' : 'text-gray-400'"
                        >
                            {{ step.label }}
                        </span>
                    </div>
                    <div v-if="index < workflowSteps.length - 1" class="pb-5 flex items-center" :class="currentWorkflowStep >= index + 1 ? 'text-[#34554a]' : 'text-gray-300'">
                        <ChevronRight class="w-4 h-4" />
                    </div>
                </template>
            </div>

            <WorkflowEndorsementCards
                :steps="workflowSteps"
                :get-step-document="getStepDocument"
                :can-upload-step="canUploadStep"
                :can-view-step="canViewStep"
                :can-download-previous="canDownloadPrevious"
                :is-submitting="workflowUploading"
                :user-name="userName"
                grid-class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4"
                @upload="submitWorkflowEndorsementUpload"
                @view="downloadWorkflowDoc"
                @previous="downloadPreviousWorkflowDoc"
            />
        </div>
            </div>
        </div>
    </div>
</template>
