<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, onMounted } from 'vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import { 
    Microscope, Eye, Heart, Wind, Dna, 
    Utensils, Printer, Save, X, CheckCircle,
    ChevronRight, FileText, Filter, Activity, AlertTriangle,
    Plus, Edit, Trash2, Settings
} from 'lucide-vue-next';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';

defineOptions({ 
    layout: (h, page) => h(AppLayout, { title: 'Post-Mortem Examination', parent: 'Mortality', parentUrl: '/mortality/records' }, () => page)
});

const page = usePage();
const openMenus = ref(['mortality']);

const userRole = computed(() => page.props.auth?.user?.role || '');
const userName = computed(() => page.props.auth?.user?.name || '');
const userRoleLabel = computed(() => page.props.auth?.user?.role || 'Sr. Assistant Livestock');

const mortalityPermissions = computed(() => {
    const perms = page.props.auth?.permissions?.['Mortality Records'];
    return Array.isArray(perms) ? perms : ['no-access'];
});

const hasEditPermission = computed(() => {
    if (String(userRole.value).toLowerCase() === 'admin') return true;
    const perms = mortalityPermissions.value;
    return perms.includes('full') || perms.includes('edit');
});

const showDetailModal = ref(false);
const selectedCase = ref(null);
const examDate = ref('');
const selectedLMC = ref('');

const customFields = computed(() => page.props.customFields || {});
const finalCauseOptions = computed(() => customFields.value.final_cause || []);

const heartOptions = computed(() => customFields.value.heart_options || []);
const tracheaOptions = computed(() => customFields.value.trachea_options || []);
const lungFloatingOptions = computed(() => customFields.value.lung_floating_options || []);
const diaphragmaOptions = computed(() => customFields.value.diaphragma_options || []);
const kidneyOptions = computed(() => customFields.value.kidney_options || []);
const urinaryBladderOptions = computed(() => customFields.value.urinary_bladder_options || []);
const rumenOptions = computed(() => customFields.value.rumen_options || []);
const reticulumOptions = computed(() => customFields.value.reticulum_options || []);
const omasumOptions = computed(() => customFields.value.omasum_options || []);
const abomasumOptions = computed(() => customFields.value.abomasum_options || []);
const smallIntestineOptions = computed(() => customFields.value.small_intestine_options || []);
const colonOptions = computed(() => customFields.value.colon_options || []);
const bladderOptions = computed(() => customFields.value.bladder_options || []);
const liverOptions = computed(() => customFields.value.liver_options || []);
const spleenOptions = computed(() => customFields.value.spleen_options || []);
const jointOptions = computed(() => customFields.value.joint_options || []);
const subcutaneousOptions = computed(() => customFields.value.subcutaneous_options || []);
const reproductiveOrganOptions = computed(() => customFields.value.reproductive_organ_options || []);

const showOptionsModal = ref(false);
const currentFieldType = ref('');
const currentFieldLabel = ref('');
const editingOption = ref(null);
const optionFormValue = ref('');
const isSaving = ref(false);

const currentFieldOptions = computed(() => {
    const fieldMap = {
        'final_cause': finalCauseOptions.value,
        'heart_options': heartOptions.value,
        'trachea_options': tracheaOptions.value,
        'lung_floating_options': lungFloatingOptions.value,
        'diaphragma_options': diaphragmaOptions.value,
        'kidney_options': kidneyOptions.value,
        'urinary_bladder_options': urinaryBladderOptions.value,
        'bladder_options': bladderOptions.value,
        'liver_options': liverOptions.value,
        'spleen_options': spleenOptions.value,
        'joint_options': jointOptions.value,
        'subcutaneous_options': subcutaneousOptions.value,
        'rumen_options': rumenOptions.value,
        'reticulum_options': reticulumOptions.value,
        'omasum_options': omasumOptions.value,
        'abomasum_options': abomasumOptions.value,
        'small_intestine_options': smallIntestineOptions.value,
        'colon_options': colonOptions.value,
        'reproductive_organ_options': reproductiveOrganOptions.value,
    };
    return fieldMap[currentFieldType.value] || [];
});

const openOptionsModal = (fieldType, label, option = null) => {
    currentFieldType.value = fieldType;
    currentFieldLabel.value = label;
    if (option) {
        editingOption.value = option;
        optionFormValue.value = option.value;
    } else {
        editingOption.value = null;
        optionFormValue.value = '';
    }
    showOptionsModal.value = true;
};

const closeOptionsModal = () => {
    showOptionsModal.value = false;
    currentFieldType.value = '';
    currentFieldLabel.value = '';
    editingOption.value = null;
    optionFormValue.value = '';
};

const saveOption = () => {
    if (!optionFormValue.value) return;
    isSaving.value = true;
    
    if (editingOption.value) {
        router.put(`/mortality/custom-fields/${editingOption.value.id}`, {
            value: optionFormValue.value
        }, {
            onSuccess: () => {
                isSaving.value = false;
                closeOptionsModal();
            },
            onError: () => {
                isSaving.value = false;
            }
        });
    } else {
        router.post('/mortality/custom-fields', {
            field_type: currentFieldType.value,
            value: optionFormValue.value
        }, {
            onSuccess: () => {
                isSaving.value = false;
                closeOptionsModal();
            },
            onError: () => {
                isSaving.value = false;
            }
        });
    }
};

const deleteOption = (option) => {
    if (!confirm('Are you sure you want to delete this option?')) return;
    
    router.delete(`/mortality/custom-fields/${option.id}`, {
        preserveScroll: true
    });
};

const form = useForm({
    examination_date: '',
    examination_time: '',
    external_skin: 'Normal',
    external_eyes: 'Normal',
    external_mouth: 'Normal',
    external_nostrils: 'Normal',
    external_ears: 'Normal',
    external_limbs: 'Normal',
    external_anus: 'Normal',
    external_genital: 'Normal',
    external_general: '',
    heart_findings: '',
    trachea_findings: '',
    lung_floating_test: '',
    lung_floating_test_details: '',
    diaphragma_test: '',
    diaphragma_test_details: '',
    kidney_findings: '',
    urinary_bladder_findings: '',
    bladder_findings: '',
    liver_findings: '',
    spleen_findings: '',
    joint_findings: '',
    subcutaneous_findings: '',
    reproductive_organ_findings: '',
    rumen_findings: '',
    reticulum_findings: '',
    omasum_findings: '',
    abomasum_findings: '',
    small_intestine_findings: '',
    colon_findings: '',
    preliminary_diagnosis: '',
    confirmed_cause_of_death: '',
    cause_of_death_category: '',
    additional_notes: '',
    recommendations: '',
});

const pmExamCases = computed(() => {
    const cases = page.props.cases || [];
    return cases.map(c => ({
        id: c.id,
        lmc_no: c.lmc_no || `LMC-${c.id}`,
        death_date: c.death_date,
        tag_no: c.cattle?.tag_no || '-',
        category: c.category || c.cattle?.category || '-',
        preliminary_cod: c.category || '-',
        cattle: c.cattle
    }));
});

const pendingPMCases = computed(() => {
    return pmExamCases.value.filter(c => !c.postmortem_examination);
});

const filteredCases = computed(() => {
    let cases = pendingPMCases.value;
    if (dateFrom.value || dateTo.value) {
        cases = cases.filter(record => {
            if (!record.death_date) return false;
            
            const recordDate = new Date(record.death_date);
            const fromDate = dateFrom.value ? new Date(dateFrom.value + 'T00:00:00') : null;
            const toDate = dateTo.value ? new Date(dateTo.value + 'T23:59:59') : null;
            
            if (fromDate && recordDate < fromDate) return false;
            if (toDate && recordDate > toDate) return false;
            return true;
        });
    }
    return cases;
});

onMounted(() => {
    examDate.value = new Date().toISOString().split('T')[0];
    form.examination_date = examDate.value;
});

const loadCase = () => {
    if (!hasEditPermission.value) {
        alert(`You don't have permission to perform PM examination.`);
        return;
    }

    if (!selectedLMC.value) {
        alert('Please select an LMC number first');
        return;
    }
    
    const foundCase = pendingPMCases.value.find(c => c.id === parseInt(selectedLMC.value) || c.id === selectedLMC.value);
    if (foundCase) {
        selectedCase.value = foundCase;
        form.lmc_number = foundCase.lmc_no;
    }
};

const viewDetail = (pmCase) => {
    selectedCase.value = pmCase;
    showDetailModal.value = true;
};

const closeDetailModal = () => {
    showDetailModal.value = false;
    selectedCase.value = null;
};

const submitExam = () => {
    if (!hasEditPermission.value) {
        alert(`You don't have permission to perform PM examination.`);
        return;
    }

    if (!selectedCase.value) {
        alert('Please select a case first');
        return;
    }

    form.examination_date = examDate.value;

    form.post(route('mortality.pm-exam.submit', { mortalityCaseId: selectedCase.value.id }), {
        onSuccess: () => {
            alert('Post-mortem examination completed successfully! Case is now ready for verification.');
            selectedCase.value = null;
            selectedLMC.value = '';
            form.reset();
        },
        onError: (errors) => {
            console.error(errors);
        }
    });
};

const saveDraft = () => {
    alert('Draft saved successfully!');
};

const applyDateFilter = () => {
};

const clearDateFilter = () => {
    dateFrom.value = '';
    dateTo.value = '';
};

const dateFrom = ref('');
const dateTo = ref('');

const showDeleteModal = ref(false);
const caseToDelete = ref(null);

const confirmDeleteCase = (pmCase) => {
    caseToDelete.value = pmCase;
    showDeleteModal.value = true;
};

const deleteCase = () => {
    if (!caseToDelete.value) return;
    
    router.delete(`/mortality/${caseToDelete.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
            caseToDelete.value = null;
            selectedCase.value = null;
            selectedLMC.value = '';
        },
        onError: (errors) => {
            console.error('Delete failed:', errors);
            alert('Failed to delete case. Please try again.');
        }
    });
};
</script>

<template>
    <div class="max-w-7xl mx-auto">
        <div v-if="!hasEditPermission" class="bg-red-50 border border-red-200 rounded-lg p-6 text-center">
            <AlertTriangle class="w-12 h-12 text-red-500 mx-auto mb-4" />
            <h2 class="text-lg font-bold text-red-700 mb-2">Access Denied</h2>
            <p class="text-red-600">You don't have permission to perform PM examinations.</p>
        </div>

        <div v-else>
            <div class="mb-8">
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Post-Mortem Examination</h1>
                <p class="text-sm text-gray-500 mt-1">Conduct detailed post-mortem examination for mortality cases.</p>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <Microscope class="w-5 h-5 text-[#34554a]" />
                    Select Case for PM Examination
                </h3>
                
                <div class="bg-gray-50 rounded-lg p-4 mb-4">
                    <div class="flex items-center gap-2 mb-3">
                        <Filter class="w-4 h-4 text-[#34554a]" />
                        <span class="text-sm font-bold text-gray-700">Filter Cases by Death Date</span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-600 uppercase tracking-wide mb-1.5">From Date</label>
                            <input 
                                v-model="dateFrom"
                                type="date" 
                                class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#34554a] focus:border-transparent outline-none bg-white"
                            >
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-600 uppercase tracking-wide mb-1.5">To Date</label>
                            <input 
                                v-model="dateTo"
                                type="date" 
                                class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#34554a] focus:border-transparent outline-none bg-white"
                            >
                        </div>
                        <div class="flex items-end">
                            <button 
                                v-if="dateFrom || dateTo"
                                @click="clearDateFilter"
                                type="button"
                                class="px-4 py-2.5 border border-gray-200 rounded-lg text-gray-600 font-medium hover:bg-gray-100 transition-colors text-sm flex items-center gap-2">
                                <X class="w-4 h-4" />
                                Clear Filter
                            </button>
                        </div>
                    </div>
                    <p v-if="dateFrom || dateTo" class="text-xs text-gray-500 mt-2">
                        Showing {{ filteredCases.length }} of {{ pendingPMCases.length }} cases
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-600 uppercase tracking-wide mb-1.5">LMC Number</label>
                        <select 
                            v-model="selectedLMC"
                            class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#34554a] focus:border-transparent outline-none bg-white"
                        >
                            <option value="">Select LMC Number</option>
                            <option v-for="pmCase in filteredCases" :key="pmCase.id" :value="pmCase.id">
                                {{ pmCase.lmc_no }} - {{ pmCase.tag_no }} ({{ pmCase.preliminary_cod }})
                            </option>
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button 
                            @click="loadCase"
                            class="flex-1 px-6 py-2.5 bg-[#34554a] text-white rounded-lg font-bold hover:bg-opacity-90 transition-all text-sm flex items-center justify-center gap-2">
                            <Eye class="w-4 h-4" />
                            Load Case
                        </button>
                        <button 
                            v-if="selectedCase"
                            @click="viewDetail(selectedCase)"
                            class="px-4 py-2.5 border border-gray-200 rounded-lg text-gray-600 font-medium hover:bg-gray-50 transition-colors text-sm flex items-center gap-2">
                            <FileText class="w-4 h-4" />
                            View Details
                        </button>
                        <button 
                            v-if="userRole === 'admin' && selectedCase"
                            @click="confirmDeleteCase(selectedCase)"
                            class="px-4 py-2.5 border border-red-200 text-red-600 rounded-lg font-medium hover:bg-red-50 transition-colors text-sm flex items-center gap-2">
                            <Trash2 class="w-4 h-4" />
                            Delete
                        </button>
                    </div>
                </div>
            </div>

            <form v-if="selectedCase" @submit.prevent="submitExam">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="p-5 border-b border-gray-100 bg-gray-50">
                        <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                            <Microscope class="w-5 h-5 text-[#34554a]" />
                            POST-MORTEM EXAMINATION FINDINGS
                        </h3>
                        <p class="text-sm text-gray-500 mt-1">Detailed examination of internal and external organs</p>
                        <div class="mt-3 flex items-center gap-3">
                            <code class="bg-[#34554a]/10 text-[#34554a] px-2 py-1 rounded font-bold text-xs">{{ selectedCase.lmc_no }}</code>
                            <span class="text-sm text-gray-600">- {{ selectedCase.tag_no }} ({{ selectedCase.category }})</span>
                        </div>
                    </div>
                    
                    <div class="p-6">
                        <h4 class="text-sm font-bold text-gray-800 mb-4 pb-2 border-b-2 border-[#34554a] flex items-center gap-2">
                            <Eye class="w-4 h-4 text-[#34554a]" />
                            External Findings
                        </h4>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                            <div>
                                <label class="block text-xs font-bold text-gray-600 uppercase tracking-wide mb-1.5">Skin</label>
                                <select v-model="form.external_skin" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#34554a] focus:border-transparent outline-none bg-white">
                                    <option value="Normal">Normal</option>
                                    <option value="Abnormal">Abnormal</option>
                                    <option value="Not Examined">Not Examined</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 uppercase tracking-wide mb-1.5">Eyes</label>
                                <select v-model="form.external_eyes" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#34554a] focus:border-transparent outline-none bg-white">
                                    <option value="Normal">Normal</option>
                                    <option value="Abnormal">Abnormal</option>
                                    <option value="Not Examined">Not Examined</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 uppercase tracking-wide mb-1.5">Mouth</label>
                                <select v-model="form.external_mouth" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#34554a] focus:border-transparent outline-none bg-white">
                                    <option value="Normal">Normal</option>
                                    <option value="Abnormal">Abnormal</option>
                                    <option value="Not Examined">Not Examined</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 uppercase tracking-wide mb-1.5">Nostrils</label>
                                <select v-model="form.external_nostrils" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#34554a] focus:border-transparent outline-none bg-white">
                                    <option value="Normal">Normal</option>
                                    <option value="Abnormal">Abnormal</option>
                                    <option value="Not Examined">Not Examined</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 uppercase tracking-wide mb-1.5">Ears</label>
                                <select v-model="form.external_ears" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#34554a] focus:border-transparent outline-none bg-white">
                                    <option value="Normal">Normal</option>
                                    <option value="Abnormal">Abnormal</option>
                                    <option value="Not Examined">Not Examined</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 uppercase tracking-wide mb-1.5">Limbs</label>
                                <select v-model="form.external_limbs" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#34554a] focus:border-transparent outline-none bg-white">
                                    <option value="Normal">Normal</option>
                                    <option value="Abnormal">Abnormal</option>
                                    <option value="Not Examined">Not Examined</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 uppercase tracking-wide mb-1.5">Anus</label>
                                <select v-model="form.external_anus" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#34554a] focus:border-transparent outline-none bg-white">
                                    <option value="Normal">Normal</option>
                                    <option value="Abnormal">Abnormal</option>
                                    <option value="Not Examined">Not Examined</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 uppercase tracking-wide mb-1.5">Genital</label>
                                <select v-model="form.external_genital" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#34554a] focus:border-transparent outline-none bg-white">
                                    <option value="Normal">Normal</option>
                                    <option value="Abnormal">Abnormal</option>
                                    <option value="Not Examined">Not Examined</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-6">
                            <label class="block text-xs font-bold text-gray-600 uppercase tracking-wide mb-1.5">External Findings Notes</label>
                            <textarea 
                                v-model="form.external_general"
                                rows="3" 
                                placeholder="Describe any abnormal external findings such as wounds, lesions, discharges, or other observations..."
                                class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#34554a] focus:border-transparent outline-none resize-none"
                            ></textarea>
                        </div>

                        <div class="flex justify-between items-center mb-4 pb-2 border-b-2 border-[#34554a]">
                            <h4 class="text-sm font-bold text-gray-800 flex items-center gap-2">
                                <Heart class="w-4 h-4 text-[#34554a]" />
                                Internal Organ Examination
                            </h4>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                            <div class="bg-white border border-gray-200 rounded-lg p-4">
                                <div class="flex justify-between items-center mb-3">
                                    <h5 class="font-bold text-gray-800 flex items-center gap-2">
                                        <Activity class="w-4 h-4 text-red-500" />
                                        Heart
                                    </h5>
                                    <button type="button" @click="openOptionsModal('heart_options', 'Heart')" class="text-gray-400 hover:text-[#34554a]">
                                        <Settings class="w-4 h-4" />
                                    </button>
                                </div>
                                <div class="space-y-2 mb-2">
                                    <label v-for="opt in heartOptions" :key="opt.id" class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" v-model="form.heart_findings" :value="opt.value" class="accent-[#34554a]">
                                        <span class="text-sm">{{ opt.value }}</span>
                                    </label>
                                </div>
                            </div>
                            <div class="bg-white border border-gray-200 rounded-lg p-4">
                                <div class="flex justify-between items-center mb-3">
                                    <h5 class="font-bold text-gray-800 flex items-center gap-2">
                                        <Wind class="w-4 h-4 text-blue-500" />
                                        Trachea
                                    </h5>
                                    <button type="button" @click="openOptionsModal('trachea_options', 'Trachea')" class="text-gray-400 hover:text-[#34554a]">
                                        <Settings class="w-4 h-4" />
                                    </button>
                                </div>
                                <div class="space-y-2 mb-2">
                                    <label v-for="opt in tracheaOptions" :key="opt.id" class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" v-model="form.trachea_findings" :value="opt.value" class="accent-[#34554a]">
                                        <span class="text-sm">{{ opt.value }}</span>
                                    </label>
                                </div>
                            </div>
                            <div class="bg-white border border-gray-200 rounded-lg p-4">
                                <div class="flex justify-between items-center mb-3">
                                    <h5 class="font-bold text-gray-800 flex items-center gap-2">
                                        <Wind class="w-4 h-4 text-pink-500" />
                                        Lung Floating Test
                                    </h5>
                                    <button type="button" @click="openOptionsModal('lung_floating_options', 'Lung Floating Test')" class="text-gray-400 hover:text-[#34554a]">
                                        <Settings class="w-4 h-4" />
                                    </button>
                                </div>
                                <div class="space-y-2">
                                    <label v-for="opt in lungFloatingOptions" :key="opt.id" class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" v-model="form.lung_floating_test" :value="opt.value" class="accent-[#34554a]">
                                        <span class="text-sm">{{ opt.value }}</span>
                                    </label>
                                </div>
                                <input 
                                    v-model="form.lung_floating_test_details"
                                    type="text" 
                                    placeholder="Details..."
                                    class="w-full mt-2 px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#34554a] focus:border-transparent outline-none"
                                >
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                            <div class="bg-white border border-gray-200 rounded-lg p-4">
                                <div class="flex justify-between items-center mb-3">
                                    <h5 class="font-bold text-gray-800 flex items-center gap-2">
                                        <Dna class="w-4 h-4 text-purple-500" />
                                        Diaphragma Test
                                    </h5>
                                    <button type="button" @click="openOptionsModal('diaphragma_options', 'Diaphragma Test')" class="text-gray-400 hover:text-[#34554a]">
                                        <Settings class="w-4 h-4" />
                                    </button>
                                </div>
                                <div class="space-y-2">
                                    <label v-for="opt in diaphragmaOptions" :key="opt.id" class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" v-model="form.diaphragma_test" :value="opt.value" class="accent-[#34554a]">
                                        <span class="text-sm">{{ opt.value }}</span>
                                    </label>
                                </div>
                                <input 
                                    v-model="form.diaphragma_test_details"
                                    type="text" 
                                    placeholder="Details..."
                                    class="w-full mt-2 px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#34554a] focus:border-transparent outline-none"
                                >
                            </div>
                            <div class="bg-white border border-gray-200 rounded-lg p-4">
                                <div class="flex justify-between items-center mb-3">
                                    <h5 class="font-bold text-gray-800 flex items-center gap-2">
                                        <Activity class="w-4 h-4 text-red-600" />
                                        Kidney
                                    </h5>
                                    <button type="button" @click="openOptionsModal('kidney_options', 'Kidney')" class="text-gray-400 hover:text-[#34554a]">
                                        <Settings class="w-4 h-4" />
                                    </button>
                                </div>
                                <div class="space-y-2">
                                    <label v-for="opt in kidneyOptions" :key="opt.id" class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" v-model="form.kidney_findings" :value="opt.value" class="accent-[#34554a]">
                                        <span class="text-sm">{{ opt.value }}</span>
                                    </label>
                                </div>
                            </div>
                            <div class="bg-white border border-gray-200 rounded-lg p-4">
                                <div class="flex justify-between items-center mb-3">
                                    <h5 class="font-bold text-gray-800 flex items-center gap-2">
                                        <Activity class="w-4 h-4 text-yellow-600" />
                                        Urinary Bladder
                                    </h5>
                                    <button type="button" @click="openOptionsModal('urinary_bladder_options', 'Urinary Bladder')" class="text-gray-400 hover:text-[#34554a]">
                                        <Settings class="w-4 h-4" />
                                    </button>
                                </div>
                                <div class="space-y-2">
                                    <label v-for="opt in urinaryBladderOptions" :key="opt.id" class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" v-model="form.urinary_bladder_findings" :value="opt.value" class="accent-[#34554a]">
                                        <span class="text-sm">{{ opt.value }}</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                            <div class="bg-white border border-gray-200 rounded-lg p-4">
                                <div class="flex justify-between items-center mb-3">
                                    <h5 class="font-bold text-gray-800 flex items-center gap-2">
                                        <Utensils class="w-4 h-4 text-green-500" />
                                        Rumen
                                    </h5>
                                    <button type="button" @click="openOptionsModal('rumen_options', 'Rumen')" class="text-gray-400 hover:text-[#34554a]">
                                        <Settings class="w-4 h-4" />
                                    </button>
                                </div>
                                <div class="space-y-2">
                                    <label v-for="opt in rumenOptions" :key="opt.id" class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" v-model="form.rugen_findings" :value="opt.value" class="accent-[#34554a]">
                                        <span class="text-sm">{{ opt.value }}</span>
                                    </label>
                                </div>
                            </div>
                            <div class="bg-white border border-gray-200 rounded-lg p-4">
                                <div class="flex justify-between items-center mb-3">
                                    <h5 class="font-bold text-gray-800 flex items-center gap-2">
                                        <Dna class="w-4 h-4 text-indigo-500" />
                                        Reticulum
                                    </h5>
                                    <button type="button" @click="openOptionsModal('reticulum_options', 'Reticulum')" class="text-gray-400 hover:text-[#34554a]">
                                        <Settings class="w-4 h-4" />
                                    </button>
                                </div>
                                <div class="space-y-2">
                                    <label v-for="opt in reticulumOptions" :key="opt.id" class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" v-model="form.reticulum_findings" :value="opt.value" class="accent-[#34554a]">
                                        <span class="text-sm">{{ opt.value }}</span>
                                    </label>
                                </div>
                            </div>
                            <div class="bg-white border border-gray-200 rounded-lg p-4">
                                <div class="flex justify-between items-center mb-3">
                                    <h5 class="font-bold text-gray-800 flex items-center gap-2">
                                        <Activity class="w-4 h-4 text-orange-500" />
                                        Omasum
                                    </h5>
                                    <button type="button" @click="openOptionsModal('omasum_options', 'Omasum')" class="text-gray-400 hover:text-[#34554a]">
                                        <Settings class="w-4 h-4" />
                                    </button>
                                </div>
                                <div class="space-y-2">
                                    <label v-for="opt in omasumOptions" :key="opt.id" class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" v-model="form.omasum_findings" :value="opt.value" class="accent-[#34554a]">
                                        <span class="text-sm">{{ opt.value }}</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                            <div class="bg-white border border-gray-200 rounded-lg p-4">
                                <div class="flex justify-between items-center mb-3">
                                    <h5 class="font-bold text-gray-800 flex items-center gap-2">
                                        <Activity class="w-4 h-4 text-teal-500" />
                                        Abomasum
                                    </h5>
                                    <button type="button" @click="openOptionsModal('abomasum_options', 'Abomasum')" class="text-gray-400 hover:text-[#34554a]">
                                        <Settings class="w-4 h-4" />
                                    </button>
                                </div>
                                <div class="space-y-2">
                                    <label v-for="opt in abomasumOptions" :key="opt.id" class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" v-model="form.abomasum_findings" :value="opt.value" class="accent-[#34554a]">
                                        <span class="text-sm">{{ opt.value }}</span>
                                    </label>
                                </div>
                            </div>
                            <div class="bg-white border border-gray-200 rounded-lg p-4">
                                <div class="flex justify-between items-center mb-3">
                                    <h5 class="font-bold text-gray-800 flex items-center gap-2">
                                        <Activity class="w-4 h-4 text-pink-600" />
                                        Small Intestine
                                    </h5>
                                    <button type="button" @click="openOptionsModal('small_intestine_options', 'Small Intestine')" class="text-gray-400 hover:text-[#34554a]">
                                        <Settings class="w-4 h-4" />
                                    </button>
                                </div>
                                <div class="space-y-2">
                                    <label v-for="opt in smallIntestineOptions" :key="opt.id" class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" v-model="form.small_intestine_findings" :value="opt.value" class="accent-[#34554a]">
                                        <span class="text-sm">{{ opt.value }}</span>
                                    </label>
                                </div>
                            </div>
                            <div class="bg-white border border-gray-200 rounded-lg p-4">
                                <div class="flex justify-between items-center mb-3">
                                    <h5 class="font-bold text-gray-800 flex items-center gap-2">
                                        <Activity class="w-4 h-4 text-cyan-500" />
                                        Colon
                                    </h5>
                                    <button type="button" @click="openOptionsModal('colon_options', 'Colon')" class="text-gray-400 hover:text-[#34554a]">
                                        <Settings class="w-4 h-4" />
                                    </button>
                                </div>
                                <div class="space-y-2">
                                    <label v-for="opt in colonOptions" :key="opt.id" class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" v-model="form.colon_findings" :value="opt.value" class="accent-[#34554a]">
                                        <span class="text-sm">{{ opt.value }}</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                            <div class="bg-white border border-gray-200 rounded-lg p-4">
                                <div class="flex justify-between items-center mb-3">
                                    <h5 class="font-bold text-gray-800 flex items-center gap-2">
                                        <Heart class="w-4 h-4 text-rose-500" />
                                        Liver
                                    </h5>
                                    <button type="button" @click="openOptionsModal('liver_options', 'Liver')" class="text-gray-400 hover:text-[#34554a]">
                                        <Settings class="w-4 h-4" />
                                    </button>
                                </div>
                                <div class="space-y-2">
                                    <label v-for="opt in liverOptions" :key="opt.id" class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" v-model="form.liver_findings" :value="opt.value" class="accent-[#34554a]">
                                        <span class="text-sm">{{ opt.value }}</span>
                                    </label>
                                </div>
                            </div>
                            <div class="bg-white border border-gray-200 rounded-lg p-4">
                                <div class="flex justify-between items-center mb-3">
                                    <h5 class="font-bold text-gray-800 flex items-center gap-2">
                                        <Dna class="w-4 h-4 text-slate-500" />
                                        Spleen
                                    </h5>
                                    <button type="button" @click="openOptionsModal('spleen_options', 'Spleen')" class="text-gray-400 hover:text-[#34554a]">
                                        <Settings class="w-4 h-4" />
                                    </button>
                                </div>
                                <div class="space-y-2">
                                    <label v-for="opt in spleenOptions" :key="opt.id" class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" v-model="form.spleen_findings" :value="opt.value" class="accent-[#34554a]">
                                        <span class="text-sm">{{ opt.value }}</span>
                                    </label>
                                </div>
                            </div>
                            <div class="bg-white border border-gray-200 rounded-lg p-4">
                                <div class="flex justify-between items-center mb-3">
                                    <h5 class="font-bold text-gray-800 flex items-center gap-2">
                                        <Activity class="w-4 h-4 text-amber-600" />
                                        Joints
                                    </h5>
                                    <button type="button" @click="openOptionsModal('joint_options', 'Joints')" class="text-gray-400 hover:text-[#34554a]">
                                        <Settings class="w-4 h-4" />
                                    </button>
                                </div>
                                <div class="space-y-2">
                                    <label v-for="opt in jointOptions" :key="opt.id" class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" v-model="form.joint_findings" :value="opt.value" class="accent-[#34554a]">
                                        <span class="text-sm">{{ opt.value }}</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                            <div class="bg-white border border-gray-200 rounded-lg p-4">
                                <div class="flex justify-between items-center mb-3">
                                    <h5 class="font-bold text-gray-800 flex items-center gap-2">
                                        <Activity class="w-4 h-4 text-gray-500" />
                                        Subcutaneous
                                    </h5>
                                    <button type="button" @click="openOptionsModal('subcutaneous_options', 'Subcutaneous')" class="text-gray-400 hover:text-[#34554a]">
                                        <Settings class="w-4 h-4" />
                                    </button>
                                </div>
                                <div class="space-y-2">
                                    <label v-for="opt in subcutaneousOptions" :key="opt.id" class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" v-model="form.subcutaneous_findings" :value="opt.value" class="accent-[#34554a]">
                                        <span class="text-sm">{{ opt.value }}</span>
                                    </label>
                                </div>
                            </div>
                            <div class="bg-white border border-gray-200 rounded-lg p-4">
                                <div class="flex justify-between items-center mb-3">
                                    <h5 class="font-bold text-gray-800 flex items-center gap-2">
                                        <Activity class="w-4 h-4 text-violet-500" />
                                        Reproductive Organs
                                    </h5>
                                    <button type="button" @click="openOptionsModal('reproductive_organ_options', 'Reproductive Organs')" class="text-gray-400 hover:text-[#34554a]">
                                        <Settings class="w-4 h-4" />
                                    </button>
                                </div>
                                <div class="space-y-2">
                                    <label v-for="opt in reproductiveOrganOptions" :key="opt.id" class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" v-model="form.reproductive_organ_findings" :value="opt.value" class="accent-[#34554a]">
                                        <span class="text-sm">{{ opt.value }}</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <h4 class="text-sm font-bold text-gray-800 mb-4 pb-2 border-b-2 border-[#34554a] flex items-center gap-2">
                            <FileText class="w-4 h-4 text-[#34554a]" />
                            Summary & Diagnosis
                        </h4>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label class="block text-xs font-bold text-gray-600 uppercase tracking-wide mb-1.5">Preliminary Diagnosis</label>
                                <select v-model="form.preliminary_diagnosis" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#34554a] focus:border-transparent outline-none bg-white">
                                    <option value="">Select Diagnosis</option>
                                    <option v-for="opt in customFields.preliminary_diagnosis" :key="opt.id" :value="opt.value">{{ opt.value }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 uppercase tracking-wide mb-1.5">Confirmed Cause of Death</label>
                                <select v-model="form.confirmed_cause_of_death" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#34554a] focus:border-transparent outline-none bg-white">
                                    <option value="">Select Cause</option>
                                    <option v-for="opt in finalCauseOptions" :key="opt.id" :value="opt.value">{{ opt.value }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-6">
                            <label class="block text-xs font-bold text-gray-600 uppercase tracking-wide mb-1.5">Additional Notes</label>
                            <textarea 
                                v-model="form.additional_notes"
                                rows="4" 
                                placeholder="Document any additional findings, observations, or relevant information..."
                                class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#34554a] focus:border-transparent outline-none resize-none"
                            ></textarea>
                        </div>

                        <div class="mb-6">
                            <label class="block text-xs font-bold text-gray-600 uppercase tracking-wide mb-1.5">Recommendations</label>
                            <textarea 
                                v-model="form.recommendations"
                                rows="3" 
                                placeholder="Provide recommendations for prevention, treatment protocols, or management changes..."
                                class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#34554a] focus:border-transparent outline-none resize-none"
                            ></textarea>
                        </div>

                        <div class="flex justify-end gap-4 pt-4 border-t border-gray-200">
                            <button 
                                type="button"
                                @click="saveDraft"
                                class="px-6 py-2.5 border border-gray-200 rounded-lg text-gray-600 font-medium hover:bg-gray-50 transition-colors flex items-center gap-2">
                                <Save class="w-4 h-4" />
                                Save Draft
                            </button>
                            <button 
                                type="submit"
                                :disabled="form.processing"
                                class="px-6 py-2.5 bg-[#34554a] text-white rounded-lg font-bold hover:bg-opacity-90 transition-all flex items-center gap-2 disabled:opacity-50">
                                <Printer class="w-4 h-4" />
                                {{ form.processing ? 'Submitting...' : 'Submit PM Examination' }}
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <Teleport to="body">
            <div 
                v-if="showDetailModal && selectedCase"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-5"
                @click.self="closeDetailModal">
                <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl mx-auto overflow-hidden max-h-[90vh]">
                    <div class="flex justify-between items-center p-4 border-b border-gray-100 bg-gray-50">
                        <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                            <Microscope class="w-5 h-5 text-[#34554a]" />
                            <span>PM Examination Details - {{ selectedCase.lmc_no }}</span>
                        </h3>
                        <button 
                            @click="closeDetailModal"
                            class="text-gray-400 hover:text-gray-600 transition-colors w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-200">
                            ✕
                        </button>
                    </div>
                    <div class="p-6 overflow-y-auto max-h-[calc(90vh-130px)]">
                        <div class="grid grid-cols-2 gap-6 mb-6">
                            <div class="bg-gray-50 rounded-lg p-4">
                                <h4 class="text-sm font-bold text-gray-800 mb-3">Case Information</h4>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">Tag Number</span>
                                        <span class="font-medium">{{ selectedCase.tag_no }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">Category</span>
                                        <span class="font-medium">{{ selectedCase.category }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">Death Date</span>
                                        <span class="font-medium">{{ selectedCase.death_date }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="p-4 bg-gray-50 border-t border-gray-200 flex justify-end">
                        <button 
                            @click="closeDetailModal"
                            class="px-6 py-2 bg-gray-200 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-300">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <Teleport to="body">
            <div 
                v-if="showOptionsModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-5"
                @click.self="closeOptionsModal">
                <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 overflow-hidden">
                    <div class="p-6 border-b border-gray-100">
                        <h3 class="text-lg font-bold text-gray-900">Manage Options - {{ currentFieldLabel }}</h3>
                    </div>
                    <div class="p-6">
                        <div class="mb-4">
                            <label class="block text-xs font-bold text-gray-600 uppercase tracking-wide mb-1.5">{{ editingOption ? 'Edit' : 'Add New' }} Option</label>
                            <input 
                                v-model="optionFormValue"
                                type="text" 
                                class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#34554a] focus:border-transparent outline-none"
                                :placeholder="`Enter ${currentFieldLabel} option`"
                                @keyup.enter="saveOption"
                            >
                        </div>

                        <div class="flex gap-2 mb-6">
                            <button
                                @click="saveOption"
                                :disabled="isSaving || !optionFormValue"
                                class="px-4 py-2 bg-[#34554a] text-white rounded-lg hover:bg-opacity-90 disabled:opacity-50 text-sm">
                                {{ isSaving ? '...' : (editingOption ? 'Update' : 'Add') }}
                            </button>
                            <button
                                v-if="editingOption"
                                type="button"
                                @click="editingOption = null; optionFormValue = ''"
                                class="px-3 py-2 border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50 text-sm">
                                Cancel
                            </button>
                        </div>

                        <div class="mb-4">
                            <h4 class="text-xs font-bold text-gray-600 uppercase tracking-wide mb-2">Existing Options</h4>
                            <div class="max-h-64 overflow-y-auto border border-gray-200 rounded-lg">
                                <div
                                    v-for="opt in currentFieldOptions"
                                    :key="opt.id"
                                    class="flex justify-between items-center p-3 border-b border-gray-100 last:border-0 hover:bg-gray-50">
                                    <span class="text-sm">{{ opt.value }}</span>
                                    <div class="flex gap-1">
                                        <button
                                            type="button"
                                            @click="editingOption = opt; optionFormValue = opt.value"
                                            class="p-1.5 text-gray-400 hover:text-[#34554a] rounded hover:bg-gray-100">
                                            <Edit class="w-4 h-4" />
                                        </button>
                                        <button
                                            type="button"
                                            @click="deleteOption(opt)"
                                            class="p-1.5 text-gray-400 hover:text-red-500 rounded hover:bg-gray-100">
                                            <Trash2 class="w-4 h-4" />
                                        </button>
                                    </div>
                                </div>
                                <div v-if="currentFieldOptions.length === 0" class="p-4 text-center text-gray-400 text-sm">
                                    No options defined
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button
                                type="button"
                                @click="closeOptionsModal"
                                class="px-4 py-2 border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50">
                                Close
                            </button>
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
