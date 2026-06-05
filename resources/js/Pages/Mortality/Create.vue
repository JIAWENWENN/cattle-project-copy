<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, onMounted } from 'vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import { 
    Skull, Save, ArrowLeft, AlertTriangle,
    Microscope, Eye, ChevronDown, Check,
    Pencil, Trash2, Plus, Settings
} from 'lucide-vue-next';

defineOptions({ 
    layout: (h, page) => h(AppLayout, { title: 'Record Mortality', parent: 'Mortality', parentUrl: '/mortality/records' }, () => page)
});

const page = usePage();

const customFields = computed(() => page.props.customFields || {});
const cattleList = computed(() => page.props.cattle || []);
const estates = computed(() => page.props.estates || []);
const blocks = computed(() => page.props.blocks || []);

const showCategoryModal = ref(false);
const editingCategory = ref(null);
const categoryFormValue = ref('');
const isSaving = ref(false);
const pmExamDate = ref('');

const categoryOptions = computed(() => customFields.value.category || []);
const preliminaryCauseOptions = computed(() => customFields.value.preliminary_cause || []);

const cattleSearch = ref('');
const showCattleDropdown = ref(false);
const selectedCattle = ref(null);

const filteredCattle = computed(() => {
    if (!cattleSearch.value) return cattleList.value;
    const search = cattleSearch.value.toLowerCase();
    return cattleList.value.filter(cattle => 
        cattle.tag_no.toLowerCase().includes(search) ||
        cattle.category.toLowerCase().includes(search) ||
        (cattle.breed && cattle.breed.toLowerCase().includes(search))
    );
});

const selectCattle = (cattle) => {
    selectedCattle.value = cattle;
    form.cattle_id = cattle.id;
    cattleSearch.value = cattle.tag_no;
    
    form.category = cattle.category || '';
    form.location = cattle.operating_unit || '';
    form.block = cattle.location_block || '';
    
    showCattleDropdown.value = false;
};

const clearCattleSelection = () => {
    selectedCattle.value = null;
    form.cattle_id = '';
    cattleSearch.value = '';
    form.category = '';
    form.location = '';
    form.block = '';
};

const onCattleSearchFocus = () => {
    if (!selectedCattle.value) {
        showCattleDropdown.value = true;
    }
};

const onCattleSearchBlur = () => {
    setTimeout(() => {
        showCattleDropdown.value = false;
    }, 250);
};

const selectedPreliminaryCauses = ref([]);

const form = useForm({
    cattle_id: '',
    lmc_no: '',
    category: '',
    location: '',
    block: '',
    death_date: '',
    reported_by: '',
    time_of_death: '',
    cause_of_death: '',
    treatment: '',
    additional_info: '',
    preliminary_cause: [],
    additional_notes: '',
    pm_examination_date: '',
    pm_external_skin: '',
    pm_external_eyes: '',
    pm_external_mouth: '',
    pm_external_nostrils: '',
    pm_external_ears: '',
    pm_external_limbs: '',
    pm_external_anus: '',
    pm_external_genital: '',
    pm_external_general: '',
    pm_subcutaneous: '',
    pm_heart: '',
    pm_trachea: '',
    pm_lung: '',
    pm_diaphragma: '',
    pm_kidney: '',
    pm_reproductive_organ: '',
    pm_joint: '',
    pm_bladder: '',
    pm_liver: '',
    pm_spleen: '',
    pm_stomachrumen: '',
    pm_stomachreticulum: '',
    pm_stomachabomasum: '',
    pm_intestine_small: '',
    pm_intestine_colon: '',
});

const userRole = computed(() => page.props.auth?.user?.role || '');
const userName = computed(() => page.props.auth?.user?.name || '');

const mortalityPermissions = computed(() => {
    const perms = page.props.auth?.permissions?.['Mortality Records'];
    return Array.isArray(perms) ? perms : ['no-access'];
});

const hasCreatePermission = computed(() => {
    if (String(userRole.value).toLowerCase() === 'admin') return true;
    const perms = mortalityPermissions.value;
    return perms.includes('full') || perms.includes('create');
});

const togglePreliminaryCause = (cause) => {
    const index = selectedPreliminaryCauses.value.indexOf(cause);
    if (index > -1) {
        selectedPreliminaryCauses.value.splice(index, 1);
    } else {
        selectedPreliminaryCauses.value.push(cause);
    }
};

onMounted(() => {
    if (!hasCreatePermission.value) {
        alert(`You don't have permission to create mortality cases.`);
        return;
    }

    const now = new Date();
    form.death_date = now.toISOString().split('T')[0];
    form.reported_by = userName.value || '';
    pmExamDate.value = now.toISOString().split('T')[0];
    form.pm_examination_date = pmExamDate.value;
});

const submit = () => {
    if (!hasCreatePermission.value) {
        alert(`You don't have permission to create mortality cases.`);
        return;
    }

    form.preliminary_cause = selectedPreliminaryCauses.value;

    form.post(route('mortality.store'), {
        onSuccess: () => {
            form.reset();
            selectedPreliminaryCauses.value = [];
            clearCattleSelection();
            router.visit('/mortality/records');
        },
        onError: (errors) => {
            console.error(errors);
        }
    });
};

const goBack = () => {
    window.history.back();
};

const openCategoryModal = (category = null) => {
    if (category) {
        editingCategory.value = category;
        categoryFormValue.value = category.value;
    } else {
        editingCategory.value = null;
        categoryFormValue.value = '';
    }
    showCategoryModal.value = true;
};

const closeCategoryModal = () => {
    showCategoryModal.value = false;
    editingCategory.value = null;
    categoryFormValue.value = '';
};

const saveCategory = () => {
    if (isSaving.value || !categoryFormValue.value) return;
    isSaving.value = true;

    if (editingCategory.value) {
        router.put(route('mortality.custom-fields.update', { customField: editingCategory.value.id }), {
            value: categoryFormValue.value
        }, {
            preserveScroll: true,
            onSuccess: () => {
                isSaving.value = false;
                closeCategoryModal();
            },
            onError: () => {
                isSaving.value = false;
                alert('Failed to update category');
            }
        });
    } else {
        router.post(route('mortality.custom-fields.store'), {
            field_type: 'category',
            value: categoryFormValue.value
        }, {
            preserveScroll: true,
            onSuccess: () => {
                isSaving.value = false;
                closeCategoryModal();
            },
            onError: () => {
                isSaving.value = false;
                alert('Failed to add category');
            }
        });
    }
};

const deleteCategory = (category) => {
    if (confirm(`Are you sure you want to delete "${category.value}"?`)) {
        router.delete(route('mortality.custom-fields.destroy', { customField: category.id }), {
            preserveScroll: true,
            onError: () => {
                alert('Failed to delete category');
            }
        });
    }
};
</script>

<template>
    <div class="w-full max-w-6xl mx-auto">
        <div v-if="!hasCreatePermission" class="bg-red-50 border border-red-200 rounded-lg p-6 text-center">
            <AlertTriangle class="w-12 h-12 text-red-500 mx-auto mb-4" />
            <h2 class="text-lg font-bold text-red-700 mb-2">Access Denied</h2>
            <p class="text-red-600">You don't have permission to create mortality cases.</p>
        </div>

        <div v-else>
            <div class="mb-8 flex justify-between items-center">
                <div class="flex items-center gap-4">
                    <button @click="goBack" class="w-10 h-10 flex items-center justify-center rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors">
                        <ArrowLeft class="w-5 h-5 text-gray-600" />
                    </button>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Livestock Mortality Report</h1>
                        <p class="text-sm text-gray-500 mt-1">Report new mortality case and initiate investigation</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <button type="button" @click="goBack" class="px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="button" @click="submit" class="flex items-center gap-2 bg-[#34554a] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#2a443b] shadow-sm transition-colors disabled:opacity-50" :disabled="form.processing">
                        <Save class="w-4 h-4" />
                        {{ form.processing ? 'Saving...' : 'Save record' }}
                    </button>
                </div>
            </div>

            <form @submit.prevent>
                <!-- Basic Information -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
                    <div class="px-6 py-4 bg-[#34554a] border-b border-gray-100">
                        <h3 class="text-sm font-bold text-white">Basic Information</h3>
                    </div>
                    <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Date *</label>
                            <input type="date" v-model="form.death_date" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" required />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">LMC No.</label>
                            <input type="text" v-model="form.lmc_no" placeholder="e.g., LMC-2026-0001" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1 flex items-center justify-between">
                                <span>Tag No. *</span>
                            </label>
                            <div class="relative">
                                <input type="text" v-model="cattleSearch" :placeholder="selectedCattle ? '' : 'Search cattle...'" :readonly="!!selectedCattle" @focus="onCattleSearchFocus" @blur="onCattleSearchBlur" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" :class="{ 'bg-gray-100 cursor-not-allowed': selectedCattle }" />
                                <button v-if="selectedCattle" type="button" @click="clearCattleSelection" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                    ✕
                                </button>
                                <div v-if="showCattleDropdown && filteredCattle.length > 0" class="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                                    <div v-for="cattle in filteredCattle" :key="cattle.id" @mousedown.prevent="selectCattle(cattle)" class="px-4 py-3 hover:bg-[#34554a]/10 cursor-pointer text-sm border-b border-gray-100 last:border-0">
                                        <div class="font-medium text-gray-900">{{ cattle.tag_no }}</div>
                                        <div class="text-xs text-gray-500">{{ cattle.category }} - {{ cattle.breed || 'N/A' }} {{ cattle.location_block ? ' - ' + cattle.location_block : '' }}</div>
                                    </div>
                                </div>
                                <div v-if="showCattleDropdown && cattleSearch && filteredCattle.length === 0" class="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg p-4 text-sm text-gray-500 text-center">
                                    No cattle found matching "{{ cattleSearch }}"
                                </div>
                            </div>
                            <input type="hidden" v-model="form.cattle_id" required />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Category *</label>
                            <input type="text" v-model="form.category" readonly class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-gray-100 text-sm cursor-not-allowed" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Location (Estate)</label>
                            <input type="text" v-model="form.location" readonly class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-gray-100 text-sm cursor-not-allowed" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Block</label>
                            <input type="text" v-model="form.block" readonly class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-gray-100 text-sm cursor-not-allowed" />
                        </div>
                    </div>
                </div>

                <!-- Case Details -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
                    <div class="px-6 py-4 bg-[#34554a] border-b border-gray-100">
                        <h3 class="text-sm font-bold text-white">Case Details</h3>
                    </div>
                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Reported By *</label>
                            <input type="text" v-model="form.reported_by" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" required />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Time of Death (Approx)</label>
                            <input type="time" v-model="form.time_of_death" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Cause of Death</label>
                            <textarea v-model="form.cause_of_death" rows="3" placeholder="Describe the suspected cause of death..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a] resize-none"></textarea>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Treatment</label>
                            <textarea v-model="form.treatment" rows="3" placeholder="Describe any treatments administered before death..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a] resize-none"></textarea>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Additional Info</label>
                            <textarea v-model="form.additional_info" rows="3" placeholder="Any additional relevant information..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a] resize-none"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Post-Mortem Examination (Optional) -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
                    <div class="px-6 py-4 bg-[#34554a] border-b border-gray-100">
                        <h3 class="text-sm font-bold text-white">Post-Mortem Examination</h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Examination Date</label>
                                <input type="date" v-model="form.pm_examination_date" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                            </div>
                        </div>

                        <h4 class="text-sm font-bold text-gray-800 mb-4 pb-2 border-b-2 border-[#34554a]">External Findings</h4>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Skin</label>
                                <input type="text" v-model="form.pm_external_skin" placeholder="Findings..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Eyes</label>
                                <input type="text" v-model="form.pm_external_eyes" placeholder="Findings..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Mouth</label>
                                <input type="text" v-model="form.pm_external_mouth" placeholder="Findings..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Nostrils</label>
                                <input type="text" v-model="form.pm_external_nostrils" placeholder="Findings..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Ears</label>
                                <input type="text" v-model="form.pm_external_ears" placeholder="Findings..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Limbs</label>
                                <input type="text" v-model="form.pm_external_limbs" placeholder="Findings..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Anus</label>
                                <input type="text" v-model="form.pm_external_anus" placeholder="Findings..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Genital</label>
                                <input type="text" v-model="form.pm_external_genital" placeholder="Findings..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-semibold text-gray-500 mb-1">General</label>
                                <input type="text" v-model="form.pm_external_general" placeholder="General external observations..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                            </div>
                        </div>

                        <h4 class="text-sm font-bold text-gray-800 mb-4 pb-2 border-b-2 border-[#34554a]">Organ Findings</h4>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Subcutaneous</label>
                                <input type="text" v-model="form.pm_subcutaneous" placeholder="Findings..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Heart</label>
                                <input type="text" v-model="form.pm_heart" placeholder="Findings..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Trachea</label>
                                <input type="text" v-model="form.pm_trachea" placeholder="Findings..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Lung</label>
                                <input type="text" v-model="form.pm_lung" placeholder="Findings..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Diaphragma</label>
                                <input type="text" v-model="form.pm_diaphragma" placeholder="Findings..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Kidney</label>
                                <input type="text" v-model="form.pm_kidney" placeholder="Findings..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Reproductive Organ</label>
                                <input type="text" v-model="form.pm_reproductive_organ" placeholder="Findings..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Joint</label>
                                <input type="text" v-model="form.pm_joint" placeholder="Findings..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Bladder</label>
                                <input type="text" v-model="form.pm_bladder" placeholder="Findings..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Liver</label>
                                <input type="text" v-model="form.pm_liver" placeholder="Findings..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Spleen</label>
                                <input type="text" v-model="form.pm_spleen" placeholder="Findings..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                            </div>
                        </div>

                        <h4 class="text-sm font-bold text-gray-800 mb-4 pb-2 border-b-2 border-[#34554a]">Stomach</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Rumen</label>
                                <input type="text" v-model="form.pm_stomachrumen" placeholder="Findings..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Reticulum</label>
                                <input type="text" v-model="form.pm_stomachreticulum" placeholder="Findings..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Abomasum</label>
                                <input type="text" v-model="form.pm_stomachabomasum" placeholder="Findings..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                            </div>
                        </div>

                        <h4 class="text-sm font-bold text-gray-800 mb-4 pb-2 border-b-2 border-[#34554a]">Intestine</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Small Intestine</label>
                                <input type="text" v-model="form.pm_intestine_small" placeholder="Findings..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Colon</label>
                                <input type="text" v-model="form.pm_intestine_colon" placeholder="Findings..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="form.hasErrors" class="mt-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                    <p class="text-sm text-red-600 font-medium">Form has errors:</p>
                    <pre class="text-xs text-red-500 mt-1">{{ JSON.stringify(form.errors, null, 2) }}</pre>
                </div>
                <div v-if="form.recentlySuccessful" class="mt-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                    <p class="text-sm text-green-600 font-medium">Record saved successfully!</p>
                </div>
            </form>
        </div>

        <!-- Category Management Modal -->
        <Teleport to="body">
            <div v-if="showCategoryModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click.self="closeCategoryModal">
                <div class="bg-white rounded-xl p-6 w-full max-w-md shadow-2xl max-h-[80vh] flex flex-col">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">
                        Manage Category options
                    </h3>

                    <div class="flex gap-2 mb-4">
                        <input v-model="categoryFormValue" @keyup.enter="saveCategory" type="text" placeholder="Add new category..." class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#34554a] focus:border-transparent text-sm" />
                        <button @click="saveCategory" type="button" :disabled="!categoryFormValue.trim()" class="px-4 py-2 bg-[#34554a] text-white rounded-lg hover:bg-opacity-90 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
                            <Plus class="w-4 h-4" />
                            Add
                        </button>
                    </div>

                    <div class="flex-1 overflow-y-auto border border-gray-200 rounded-lg">
                        <div v-if="categoryOptions.length === 0" class="p-4 text-center text-gray-500 text-sm">
                            No options yet. Add one above.
                        </div>
                        <div v-else class="divide-y divide-gray-100">
                            <div v-for="cat in categoryOptions" :key="cat.id" class="flex items-center gap-2 p-3 hover:bg-gray-50">
                                <template v-if="editingCategory?.id === cat.id">
                                    <input v-model="categoryFormValue" @keyup.enter="saveCategory" @keyup.escape="closeCategoryModal" type="text" class="flex-1 px-3 py-1.5 border border-green-300 rounded-md focus:ring-2 focus:ring-green-500 focus:border-transparent text-sm" />
                                    <button @click="saveCategory" type="button" class="p-1.5 text-green-600 hover:bg-green-50 rounded-md">
                                        <Check class="w-4 h-4" />
                                    </button>
                                </template>
                                <template v-else>
                                    <span class="flex-1 text-sm text-gray-700">{{ cat.value }}</span>
                                    <button @click="openCategoryModal(cat)" type="button" class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-md transition-colors">
                                        <Pencil class="w-4 h-4" />
                                    </button>
                                    <button @click="deleteCategory(cat)" type="button" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-md transition-colors">
                                        <Trash2 class="w-4 h-4" />
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end mt-4">
                        <button @click="closeCategoryModal" type="button" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 font-medium text-sm">
                            Done
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
