<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    ArrowLeft, Calendar, Activity, Heart,
    FileText, TrendingUp, AlertCircle,
    DollarSign, Truck, Utensils, Pencil, X, Save,
    Upload, Image as ImageIcon, Trash2, Plus, Eye,
    CheckCircle, AlertTriangle, Clock, ChevronLeft, ChevronRight, Download, Settings, Check,
    ClipboardList
} from 'lucide-vue-next';

const props = defineProps({
    cattle: Object,
    healthRecords: Array,
    mortalityRecords: Array,
    customFields: Object,
    auth: Object,
    operatingUnits: {
        type: Array,
        default: () => []
    }
});

const page = usePage();

const cattlePermissions = computed(() => {
    const perms = page.props.auth?.permissions?.['Cattle Directory'];
    return Array.isArray(perms) ? perms : ['no-access'];
});

const canEditCattle = computed(() => {
    if (String(page.props.auth?.user?.role || '').toLowerCase() === 'admin') return true;
    const perms = cattlePermissions.value;
    return perms.includes('full') || perms.includes('edit');
});

const canDeleteCattle = computed(() => {
    if (String(page.props.auth?.user?.role || '').toLowerCase() === 'admin') return true;
    const perms = cattlePermissions.value;
    return perms.includes('full') || perms.includes('delete');
});

const selectedOperatingUnit = ref('');

const selectedOperatingUnitRecord = computed(() => {
    if (!selectedOperatingUnit.value) return null;
    return props.operatingUnits.find(u => u.id === selectedOperatingUnit.value) || null;
});

const showDetailModal = ref(false);
const selectedTreatment = ref(null);
const showMortalityDetailModal = ref(false);
const selectedMortalityRecord = ref(null);

const docWorkflowSteps = [
    { role: 'livestock', label: 'Prepared By', field: 'prepared', role_name: 'Sr. Assistant Livestock' },
    { role: 'supervisor', label: 'Checked By', field: 'checked', role_name: 'Supervisor Livestock' },
    { role: 'livestock manager', label: 'Approved By', field: 'approved', role_name: 'Livestock Manager/OIC' },
];

const activeTab = ref('overview');
const isEditing = ref(false);
const profilePreview = ref(null);
const currentHealthPage = ref(1);
const healthItemsPerPage = 10;
const currentMortalityPage = ref(1);
const mortalityItemsPerPage = 10;

// Local copy of custom fields
const localCustomFields = ref(JSON.parse(JSON.stringify(props.customFields || {})));
const showAddFieldModal = ref(false);
const addFieldType = ref('coat_colour');
const addFieldLabel = ref('Coat Colour');
const newFieldValue = ref('');
const editingFieldId = ref(null);
const editingFieldValue = ref('');

watch(() => props.customFields, (newVal) => {
    localCustomFields.value = JSON.parse(JSON.stringify(newVal || {}));
}, { deep: true });

// Form for editing
const form = useForm({
    tag_no: props.cattle.tag_no || '',
    category: props.cattle.category || '',
    coat_colour: props.cattle.coat_colour || '',
    birth_date: props.cattle.birth_date ? props.cattle.birth_date.split('T')[0] : '',
    gender: props.cattle.gender || 'Male',
    receival_weight: props.cattle.receival_weight || '',
    general_condition: props.cattle.general_condition || '',
    operating_unit: props.cattle.operating_unit || '',
    location_block: props.cattle.location_block || '',
    location_phase: props.cattle.location_phase || '',
    dam_tag: props.cattle.dam_tag || '',
    dam_category: props.cattle.dam_category || '',
    dam_colour: props.cattle.dam_colour || '',
    sire_tag: props.cattle.sire_tag || '',
    sire_category: props.cattle.sire_category || '',
    sire_coat_colour: props.cattle.sire_coat_colour || '',
    weaning_weight: props.cattle.weaning_weight || '',
    yearling_weight: props.cattle.yearling_weight || '',
    status: props.cattle.status || 'Active',
    remarks: props.cattle.remarks || '',
    profile_picture: null
});

const filteredBlocks = computed(() => {
    return selectedOperatingUnitRecord.value?.pasture_blocks || selectedOperatingUnitRecord.value?.blocks || [];
});

const selectedBlock = computed(() => {
    if (!form.location_block) return null;
    return filteredBlocks.value.find(block => block.name === form.location_block) || null;
});

const filteredPhases = computed(() => selectedBlock.value?.phases || []);

// Watch after form is initialized; otherwise the detail page can blank on mount.
watch(() => form.location_block, (newBlock) => {
    if (!newBlock || selectedOperatingUnit.value) return;

    const unit = props.operatingUnits.find(u =>
        (u.pasture_blocks || u.blocks || []).some((b) => b.name === newBlock)
    );

    if (unit) selectedOperatingUnit.value = unit.id;
}, { immediate: true });

watch(selectedOperatingUnit, (newUnit, oldUnit) => {
    if (oldUnit === undefined || newUnit === oldUnit) return;
    form.operating_unit = selectedOperatingUnitRecord.value?.name || '';
    form.location_block = '';
    form.location_phase = '';
});

watch(selectedOperatingUnitRecord, (unit) => {
    form.operating_unit = unit?.name || '';
});

watch(() => form.location_block, () => {
    if (!selectedBlock.value) {
        form.location_phase = '';
    }
});

const tabs = [
    { id: 'overview', label: 'Overview', icon: FileText },
    { id: 'health', label: 'Health', icon: Activity },
    { id: 'mortality', label: 'Mortality', icon: AlertTriangle },
    { id: 'genealogy', label: 'Genealogy', icon: Heart },
    { id: 'movement', label: 'Movement Log', icon: Truck },
];

// Get profile picture URL
const profilePictureUrl = computed(() => {
    if (profilePreview.value) return profilePreview.value;
    if (props.cattle.profile_picture) return `/storage/${props.cattle.profile_picture}`;
    return null;
});

// Get options for a field type
const getFieldOptions = (fieldType) => {
    return localCustomFields.value?.[fieldType] || [];
};

const openAddFieldModal = (fieldType, fieldLabel) => {
    addFieldType.value = fieldType;
    addFieldLabel.value = fieldLabel;
    newFieldValue.value = '';
    editingFieldId.value = null;
    editingFieldValue.value = '';
    showAddFieldModal.value = true;
};

const addNewFieldValue = () => {
    if (!newFieldValue.value.trim()) return;

    router.post(route('custom-fields.store'), {
        field_type: addFieldType.value,
        value: newFieldValue.value.trim()
    }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            if (page.props.customFields) {
                localCustomFields.value = JSON.parse(JSON.stringify(page.props.customFields));
            }
            form[addFieldType.value] = newFieldValue.value.trim();
            newFieldValue.value = '';
        }
    });
};

const startEditField = (field) => {
    editingFieldId.value = field.id;
    editingFieldValue.value = field.value;
};

const cancelEditField = () => {
    editingFieldId.value = null;
    editingFieldValue.value = '';
};

const saveEditField = (field) => {
    if (!editingFieldValue.value.trim()) return;

    router.put(route('custom-fields.update', field.id), {
        value: editingFieldValue.value.trim()
    }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            if (page.props.customFields) {
                localCustomFields.value = JSON.parse(JSON.stringify(page.props.customFields));
            }

            if (form[addFieldType.value] === field.value) {
                form[addFieldType.value] = editingFieldValue.value.trim();
            }

            editingFieldId.value = null;
            editingFieldValue.value = '';
        }
    });
};

const deleteField = (field) => {
    if (!confirm(`Are you sure you want to delete "${field.value}"?`)) return;

    router.delete(route('custom-fields.destroy', field.id), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            if (page.props.customFields) {
                localCustomFields.value = JSON.parse(JSON.stringify(page.props.customFields));
            }

            if (form[addFieldType.value] === field.value) {
                form[addFieldType.value] = '';
            }
        }
    });
};

const statusOptions = computed(() => {
    const options = getFieldOptions('status');
    if (options.length > 0) return options;

    return [
        { id: 'default-active', value: 'Active' },
        { id: 'default-sold', value: 'Sold' },
        { id: 'default-deceased', value: 'Deceased' },
        { id: 'default-missing', value: 'Missing' },
    ];
});

const isLccManaged = computed(() => {
    if (props.cattle?.calving_record_id) return true;
    return String(props.cattle?.remarks || '').startsWith('Auto-created from Calving Record #');
});

const lockedLccFields = new Set([
    'tag_no',
    'coat_colour',
    'birth_date',
    'gender',
    'dam_tag',
    'dam_colour',
    'sire_tag',
    'sire_coat_colour',
]);

const isLockedByLcc = (field) => isLccManaged.value && lockedLccFields.has(field);

// Handle image upload
const handleImageUpload = (event) => {
    const file = event.target.files[0];
    if (file) {
        form.profile_picture = file;
        const reader = new FileReader();
        reader.onload = (e) => {
            profilePreview.value = e.target.result;
        };
        reader.readAsDataURL(file);
    }
};

// Remove image
const removeImage = () => {
    form.profile_picture = null;
    profilePreview.value = null;
};

// Cancel editing
const cancelEdit = () => {
    isEditing.value = false;
    profilePreview.value = null;
    // Reset form to original values
    form.reset();
    Object.keys(form.data()).forEach(key => {
        if (key === 'profile_picture') {
            form[key] = null;
        } else if (key.includes('date') && props.cattle[key]) {
            form[key] = props.cattle[key].split('T')[0];
        } else {
            form[key] = props.cattle[key] || '';
        }
    });
};

// Submit form
const submitForm = () => {
    form.transform((data) => ({
        ...data,
        _method: 'PUT',
    })).post(route('cattle.update', props.cattle.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            isEditing.value = false;
            profilePreview.value = null;
        }
    });
};

// Format date for display
const formatDate = (dateStr) => {
    if (!dateStr) return 'N/A';
    return new Date(dateStr).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });
};

// Calculate age
const age = computed(() => {
    if (!props.cattle.birth_date) return 'N/A';
    const birth = new Date(props.cattle.birth_date);
    const now = new Date();
    const months = (now.getFullYear() - birth.getFullYear()) * 12 + (now.getMonth() - birth.getMonth());
    if (months < 12) return `${months} months`;
    const years = Math.floor(months / 12);
    const remainingMonths = months % 12;
    return remainingMonths > 0 ? `${years}y ${remainingMonths}m` : `${years} years`;
});

const displayOperatingUnit = computed(() => {
    if (props.cattle.operating_unit) return props.cattle.operating_unit;
    if (!props.cattle.location_block) return 'N/A';

    const unit = props.operatingUnits.find(u =>
        (u.pasture_blocks || u.blocks || []).some((b) => b.name === props.cattle.location_block)
    );

    return unit ? unit.name : 'N/A';
});

// Treatment modal actions
const viewRecord = (record) => {
    selectedTreatment.value = record;
    showDetailModal.value = true;
};

const closeModal = () => {
    showDetailModal.value = false;
    selectedTreatment.value = null;
};

const viewMortalityRecord = (record) => {
    selectedMortalityRecord.value = record;
    showMortalityDetailModal.value = true;
};

const closeMortalityModal = () => {
    showMortalityDetailModal.value = false;
    selectedMortalityRecord.value = null;
};

const formatStatusLabel = (status) => {
    if (!status) return '-';
    return status.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
};

// Detail fields for treatment modal
const treatmentDetailFields = [
    { key: 'treatment_no', label: 'Treatment No.' },
    { key: 'date', label: 'Date', type: 'date' },
    { key: 'symptoms', label: 'Symptoms' },
    { key: 'treatment', label: 'Treatment' },
    { key: 'treatment_code', label: 'Treatment Code' },
    { key: 'dosage', label: 'Dosage' },
    { key: 'medication', label: 'Medication' },
    { key: 'remarks', label: 'Remarks' },
    { key: 'follow_up_required', label: 'Follow Up Required', type: 'boolean' },
    { key: 'status', label: 'Status', type: 'status', always: true },
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
    return value;
};

const treatmentDetailItems = computed(() => {
    const record = selectedTreatment.value;
    if (!record) return [];

    return treatmentDetailFields
        .map((field) => {
            const rawValue = record[field.key];
            return {
                label: field.label,
                value: formatDetailValue(rawValue, field),
                rawValue,
                field,
            };
        })
        .filter((item) => hasFilledValue(item.rawValue, item.field));
});

const healthRecords = computed(() => props.healthRecords || []);
const mortalityRecords = computed(() => props.mortalityRecords || []);

const totalHealthPages = computed(() => Math.ceil(healthRecords.value.length / healthItemsPerPage));

const paginatedHealthRecords = computed(() => {
    const start = (currentHealthPage.value - 1) * healthItemsPerPage;
    return healthRecords.value.slice(start, start + healthItemsPerPage);
});

const healthPageNumbers = computed(() => {
    const pages = [];
    const maxVisible = 5;

    if (totalHealthPages.value <= maxVisible) {
        for (let i = 1; i <= totalHealthPages.value; i++) {
            pages.push(i);
        }
    } else if (currentHealthPage.value <= 3) {
        pages.push(1, 2, 3, '...', totalHealthPages.value);
    } else if (currentHealthPage.value >= totalHealthPages.value - 2) {
        pages.push(1, '...', totalHealthPages.value - 2, totalHealthPages.value - 1, totalHealthPages.value);
    } else {
        pages.push(1, '...', currentHealthPage.value - 1, currentHealthPage.value, currentHealthPage.value + 1, '...', totalHealthPages.value);
    }

    return pages;
});

const totalMortalityPages = computed(() => Math.ceil(mortalityRecords.value.length / mortalityItemsPerPage));

const paginatedMortalityRecords = computed(() => {
    const start = (currentMortalityPage.value - 1) * mortalityItemsPerPage;
    return mortalityRecords.value.slice(start, start + mortalityItemsPerPage);
});

const mortalityPageNumbers = computed(() => {
    const pages = [];
    const maxVisible = 5;

    if (totalMortalityPages.value <= maxVisible) {
        for (let i = 1; i <= totalMortalityPages.value; i++) {
            pages.push(i);
        }
    } else if (currentMortalityPage.value <= 3) {
        pages.push(1, 2, 3, '...', totalMortalityPages.value);
    } else if (currentMortalityPage.value >= totalMortalityPages.value - 2) {
        pages.push(1, '...', totalMortalityPages.value - 2, totalMortalityPages.value - 1, totalMortalityPages.value);
    } else {
        pages.push(1, '...', currentMortalityPage.value - 1, currentMortalityPage.value, currentMortalityPage.value + 1, '...', totalMortalityPages.value);
    }

    return pages;
});

const goToHealthPage = (page) => {
    if (page >= 1 && page <= totalHealthPages.value) {
        currentHealthPage.value = page;
    }
};

const previousHealthPage = () => {
    if (currentHealthPage.value > 1) {
        currentHealthPage.value--;
    }
};

const nextHealthPage = () => {
    if (currentHealthPage.value < totalHealthPages.value) {
        currentHealthPage.value++;
    }
};

const goToMortalityPage = (page) => {
    if (page >= 1 && page <= totalMortalityPages.value) {
        currentMortalityPage.value = page;
    }
};

const previousMortalityPage = () => {
    if (currentMortalityPage.value > 1) {
        currentMortalityPage.value--;
    }
};

const nextMortalityPage = () => {
    if (currentMortalityPage.value < totalMortalityPages.value) {
        currentMortalityPage.value++;
    }
};

watch(healthRecords, () => {
    currentHealthPage.value = 1;
}, { deep: true });

watch(totalHealthPages, (newTotal) => {
    if (newTotal === 0) {
        currentHealthPage.value = 1;
        return;
    }

    if (currentHealthPage.value > newTotal) {
        currentHealthPage.value = newTotal;
    }
});

watch(mortalityRecords, () => {
    currentMortalityPage.value = 1;
}, { deep: true });

watch(totalMortalityPages, (newTotal) => {
    if (newTotal === 0) {
        currentMortalityPage.value = 1;
        return;
    }

    if (currentMortalityPage.value > newTotal) {
        currentMortalityPage.value = newTotal;
    }
});
</script>

<template>
    <Head :title="`Cattle ${cattle.tag_no}`" />

    <AppLayout
        :title="`Cattle ${cattle.tag_no}`"
        parent="Cattle Directory"
        parentUrl="/cattle"
    >
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-4">
                <Link :href="route('cattle.index')"
                      class="p-2 hover:bg-gray-100 rounded-lg">
                    <ArrowLeft class="w-5 h-5" />
                </Link>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">
                        Cattle {{ cattle.tag_no }}
                    </h1>
                    <p class="text-sm text-gray-500">
                        {{ cattle.category }} &bull; {{ age }}
                    </p>
                </div>
            </div>
            <button
                v-if="canEditCattle && !isEditing"
                @click="isEditing = true"
                class="flex items-center gap-2 px-4 py-2 bg-[#34554a] text-white rounded-lg hover:bg-opacity-90 transition-colors">
                <Pencil class="w-4 h-4" />
                Edit Profile
            </button>
            <div v-if="canEditCattle && isEditing" class="flex gap-2">
                <button
                    @click="cancelEdit"
                    class="flex items-center gap-2 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                    <X class="w-4 h-4" />
                    Cancel
                </button>
                <button
                    @click="submitForm"
                    :disabled="form.processing"
                    class="flex items-center gap-2 px-4 py-2 bg-[#34554a] text-white rounded-lg hover:bg-opacity-90 disabled:opacity-50">
                    <Save class="w-4 h-4" />
                    {{ form.processing ? 'Saving...' : 'Save Changes' }}
                </button>
            </div>
        </div>

        <!-- Profile Card -->
        <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
            <div class="flex flex-col md:flex-row gap-6">
                <!-- Profile Picture -->
                <div class="flex-shrink-0">
                    <div class="w-40 h-40 rounded-xl border-2 border-dashed border-gray-300 flex items-center justify-center bg-gray-50 overflow-hidden">
                        <img v-if="profilePictureUrl" :src="profilePictureUrl" alt="Cattle" class="w-full h-full object-cover" />
                        <ImageIcon v-else class="w-16 h-16 text-gray-300" />
                    </div>
                    <div v-if="isEditing" class="mt-3 flex gap-2">
                        <input type="file" @change="handleImageUpload" accept="image/*" class="hidden" id="profile-upload" />
                        <label for="profile-upload"
                               class="flex-1 flex items-center justify-center gap-1 px-3 py-1.5 bg-gray-100 text-gray-700 rounded-lg text-xs font-medium cursor-pointer hover:bg-gray-200">
                            <Upload class="w-3 h-3" />
                            Upload
                        </label>
                        <button v-if="profilePictureUrl" @click="removeImage" type="button"
                                class="px-3 py-1.5 bg-red-50 text-red-600 rounded-lg text-xs font-medium hover:bg-red-100">
                            <Trash2 class="w-3 h-3" />
                        </button>
                    </div>
                </div>

                <!-- Quick Info -->
                <div class="flex-1 grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="bg-gray-50 rounded-lg p-4">
                        <p class="text-xs text-gray-500 font-medium">Tag Number</p>
                        <p class="text-lg font-bold text-gray-900">{{ cattle.tag_no }}</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <p class="text-xs text-gray-500 font-medium">Status</p>
                        <span :class="{
                            'bg-green-100 text-green-700': cattle.status === 'Active',
                            'bg-yellow-100 text-yellow-700': cattle.status === 'Sick',
                            'bg-red-100 text-red-700': cattle.status === 'Deceased',
                            'bg-blue-100 text-blue-700': cattle.status === 'Sold'
                        }" class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-bold uppercase mt-1">
                            {{ cattle.status }}
                        </span>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <p class="text-xs text-gray-500 font-medium">Weight</p>
                        <p class="text-lg font-bold text-gray-900">{{ cattle.receival_weight || 'N/A' }} <span class="text-sm font-normal">kg</span></p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <p class="text-xs text-gray-500 font-medium">Condition</p>
                        <p class="text-lg font-bold text-gray-900">{{ cattle.general_condition || 'N/A' }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Validation Errors -->
        <div v-if="Object.keys(form.errors).length > 0" class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
            <ul class="list-disc list-inside text-red-600 text-sm space-y-1">
                <li v-for="(error, field) in form.errors" :key="field">{{ error }}</li>
            </ul>
        </div>

        <!-- Tab Navigation -->
        <div class="bg-white rounded-lg shadow-sm mb-6">
            <div class="flex overflow-x-auto">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    @click="activeTab = tab.id"
                    :class="[
                        'flex items-center gap-2 px-6 py-4 font-medium text-sm whitespace-nowrap border-b-2 transition-colors',
                        activeTab === tab.id
                            ? 'border-[#34554a] text-[#34554a]'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                    ]">
                    <component :is="tab.icon" class="w-4 h-4" />
                    {{ tab.label }}
                </button>
            </div>
        </div>

        <!-- Tab Content -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <!-- Overview Tab -->
            <div v-if="activeTab === 'overview'">
                <!-- View Mode -->
                <div v-if="!isEditing">
                    <h3 class="text-lg font-semibold mb-4">Basic Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="text-xs font-medium text-gray-500 uppercase tracking-wide">Tag Number</label>
                            <p class="text-base text-gray-900 mt-1">{{ cattle.tag_no }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-500 uppercase tracking-wide">Category</label>
                            <p class="text-base text-gray-900 mt-1">{{ cattle.category }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-500 uppercase tracking-wide">Coat Colour</label>
                            <p class="text-base text-gray-900 mt-1">{{ cattle.coat_colour || 'N/A' }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-500 uppercase tracking-wide">Gender</label>
                            <p class="text-base text-gray-900 mt-1">{{ cattle.gender }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-500 uppercase tracking-wide">Birth Date</label>
                            <p class="text-base text-gray-900 mt-1">{{ formatDate(cattle.birth_date) }}</p>
                        </div>
                    </div>

                    <h3 class="text-lg font-semibold mb-4 mt-8">Receival & Condition</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="text-xs font-medium text-gray-500 uppercase tracking-wide">Receival Weight</label>
                            <p class="text-base text-gray-900 mt-1">{{ cattle.receival_weight ? `${cattle.receival_weight} kg` : 'N/A' }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-500 uppercase tracking-wide">General Condition</label>
                            <p class="text-base text-gray-900 mt-1">{{ cattle.general_condition || 'N/A' }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-500 uppercase tracking-wide">Operating Unit</label>
                            <p class="text-base text-gray-900 mt-1">{{ displayOperatingUnit }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-500 uppercase tracking-wide">Location Block</label>
                            <p class="text-base text-gray-900 mt-1">{{ cattle.location_block || 'N/A' }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-500 uppercase tracking-wide">Location Phase</label>
                            <p class="text-base text-gray-900 mt-1">{{ cattle.location_phase || 'N/A' }}</p>
                        </div>
                    </div>

                    <h3 class="text-lg font-semibold mb-4 mt-8">Additional Information</h3>
                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <label class="text-xs font-medium text-gray-500 uppercase tracking-wide">Status</label>
                            <p class="text-base text-gray-900 mt-1">{{ cattle.status }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-500 uppercase tracking-wide">Remarks</label>
                            <p class="text-base text-gray-900 mt-1">{{ cattle.remarks || 'No remarks' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Edit Mode -->
                <div v-else>
                    <form @submit.prevent="submitForm" class="space-y-8">
<div v-if="isLccManaged" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
    This profile is linked to LCC (Calving). Some LCC fields (Tag No, Coat Colour, Birth Date, Gender, etc.) are read-only here and must be updated from the Calving module.
</div>

                        <!-- Basic Information -->
                        <div>
                            <h3 class="text-lg font-semibold mb-4">Basic Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="text-sm font-medium text-gray-700">Tag No *</label>
                                    <input v-model="form.tag_no" type="text" required
                                           :disabled="isLockedByLcc('tag_no')"
                                           class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-green-500 focus:border-green-500" />
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-gray-700">Category *</label>
                                    <select v-model="form.category" required
                                            :disabled="isLockedByLcc('category')"
                                            class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-green-500 focus:border-green-500">
                                        <option value="">Select...</option>
                                        <option v-for="opt in getFieldOptions('category')" :key="opt.id" :value="opt.value">{{ opt.value }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-gray-700">Coat Colour</label>
                                    <div class="mt-1 flex gap-2">
                                        <select v-model="form.coat_colour"
                                                :disabled="isLockedByLcc('coat_colour')"
                                                class="flex-1 px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-green-500 focus:border-green-500 disabled:bg-gray-100">
                                            <option value="">Select...</option>
                                            <option v-for="opt in getFieldOptions('coat_colour')" :key="opt.id" :value="opt.value">{{ opt.value }}</option>
                                        </select>
                                        <button
                                            type="button"
                                            @click="openAddFieldModal('coat_colour', 'Coat Colour')"
                                            class="px-3 py-2 bg-green-50 text-green-600 rounded-md hover:bg-green-100 transition-colors"
                                            title="Manage Coat Colour">
                                            <Settings class="w-4 h-4" />
                                        </button>
                                    </div>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-gray-700">Gender *</label>
                                    <select v-model="form.gender" required
                                            :disabled="isLockedByLcc('gender')"
                                            class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-green-500 focus:border-green-500">
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-gray-700">Birth Date</label>
                                    <input v-model="form.birth_date" type="date"
                                           :disabled="isLockedByLcc('birth_date')"
                                           class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-green-500 focus:border-green-500" />
                                </div>
                            </div>
                        </div>

                        <!-- Receival & Condition -->
                        <div>
                            <h3 class="text-lg font-semibold mb-4">Receival & Condition</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="text-sm font-medium text-gray-700">Receival Weight (kg)</label>
                                    <input v-model="form.receival_weight" type="number" step="0.01"
                                           class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-green-500 focus:border-green-500" />
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-gray-700">General Condition</label>
                                    <select v-model="form.general_condition"
                                            :disabled="isLockedByLcc('general_condition')"
                                            class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-green-500 focus:border-green-500">
                                        <option value="">Select...</option>
                                        <option v-for="opt in getFieldOptions('general_condition')" :key="opt.id" :value="opt.value">{{ opt.value }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-gray-700">Operating Unit</label>
                                    <select v-model="selectedOperatingUnit"
                                            class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-green-500 focus:border-green-500">
                                        <option value="">Select Operating Unit...</option>
                                        <option v-for="unit in operatingUnits" :key="unit.id" :value="unit.id">
                                            {{ unit.name }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-gray-700">Location Block</label>
                                    <select v-model="form.location_block"
                                            :disabled="isLockedByLcc('location_block') || !selectedOperatingUnit"
                                            class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-green-500 focus:border-green-500 disabled:bg-gray-100">
                                        <option value="">Select Block...</option>
                                        <option v-for="block in filteredBlocks" :key="block.id" :value="block.name">
                                            {{ block.name }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-gray-700">Location Phase</label>
                                    <select v-model="form.location_phase"
                                            :disabled="isLockedByLcc('location_phase') || !form.location_block"
                                            class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-green-500 focus:border-green-500 disabled:bg-gray-100">
                                        <option value="">Select Phase...</option>
                                        <option v-for="phase in filteredPhases" :key="phase.id" :value="phase.name">
                                            {{ phase.name }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Additional -->
                        <div>
                            <h3 class="text-lg font-semibold mb-4">Additional Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="text-sm font-medium text-gray-700">Status</label>
                                    <select v-model="form.status"
                                            class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-green-500 focus:border-green-500">
                                        <option v-for="opt in statusOptions" :key="opt.id" :value="opt.value">{{ opt.value }}</option>
                                    </select>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="text-sm font-medium text-gray-700">Remarks</label>
                                    <textarea v-model="form.remarks" rows="3"
                                              class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-green-500 focus:border-green-500"></textarea>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Genealogy Tab -->
            <div v-if="activeTab === 'genealogy'">
                <h3 class="text-lg font-semibold mb-4">Genealogy Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Dam -->
                    <div class="border rounded-lg p-4">
                        <h4 class="font-medium mb-3 text-pink-600">Dam (Mother)</h4>
                        <div v-if="!isEditing" class="space-y-2">
                            <div>
                                <label class="text-sm text-gray-500">Tag</label>
                                <p class="font-medium">{{ cattle.dam_tag || 'N/A' }}</p>
                            </div>
                            <div>
                                <label class="text-sm text-gray-500">Category</label>
                                <p class="font-medium">{{ cattle.dam_category || 'N/A' }}</p>
                            </div>
                            <div>
                                <label class="text-sm text-gray-500">Colour</label>
                                <p class="font-medium">{{ cattle.dam_colour || 'N/A' }}</p>
                            </div>
                        </div>
                        <div v-else class="space-y-3">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Dam Tag</label>
                                <input v-model="form.dam_tag" type="text"
                                       :disabled="isLockedByLcc('dam_tag')"
                                       class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md text-sm" />
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-700">Dam Category</label>
                                <select v-model="form.dam_category"
                                        class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                                    <option value="">Select...</option>
                                    <option v-for="opt in getFieldOptions('category')" :key="opt.id" :value="opt.value">{{ opt.value }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-700">Dam Colour</label>
                                <select v-model="form.dam_colour"
                                        :disabled="isLockedByLcc('dam_colour')"
                                        class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                                    <option value="">Select...</option>
                                    <option v-for="opt in getFieldOptions('coat_colour')" :key="opt.id" :value="opt.value">{{ opt.value }}</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Sire -->
                    <div class="border rounded-lg p-4">
                        <h4 class="font-medium mb-3 text-blue-600">Sire (Father)</h4>
                        <div v-if="!isEditing" class="space-y-2">
                            <div>
                                <label class="text-sm text-gray-500">Tag</label>
                                <p class="font-medium">{{ cattle.sire_tag || 'N/A' }}</p>
                            </div>
                            <div>
                                <label class="text-sm text-gray-500">Category</label>
                                <p class="font-medium">{{ cattle.sire_category || 'N/A' }}</p>
                            </div>
                            <div>
                                <label class="text-sm text-gray-500">Coat Colour</label>
                                <p class="font-medium">{{ cattle.sire_coat_colour || 'N/A' }}</p>
                            </div>
                        </div>
                        <div v-else class="space-y-3">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Sire Tag</label>
                                <input v-model="form.sire_tag" type="text"
                                       :disabled="isLockedByLcc('sire_tag')"
                                       class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md text-sm" />
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-700">Sire Category</label>
                                <select v-model="form.sire_category"
                                        class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                                    <option value="">Select...</option>
                                    <option v-for="opt in getFieldOptions('category')" :key="opt.id" :value="opt.value">{{ opt.value }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-700">Sire Coat Colour</label>
                                <select v-model="form.sire_coat_colour"
                                        :disabled="isLockedByLcc('sire_coat_colour')"
                                        class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                                    <option value="">Select...</option>
                                    <option v-for="opt in getFieldOptions('coat_colour')" :key="opt.id" :value="opt.value">{{ opt.value }}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Health Tab -->
            <div v-if="activeTab === 'health'">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-semibold text-gray-900">Health & Medical Records</h3>
                </div>
                
                <div v-if="healthRecords.length > 0" class="overflow-x-auto rounded-lg border border-gray-200">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-100">
                                <th class="px-4 py-3 text-xs font-bold text-gray-500 w-12">No.</th>
                                <th class="px-4 py-3 text-xs font-bold text-gray-500">Date</th>
                                <th class="px-4 py-3 text-xs font-bold text-gray-500">Symptoms</th>
                                <th class="px-4 py-3 text-xs font-bold text-gray-500">Treatment</th>
                                <th class="px-4 py-3 text-xs font-bold text-gray-500">Dosage</th>
                                <th class="px-4 py-3 text-xs font-bold text-gray-500">Medication</th>
                                <th class="px-4 py-3 text-xs font-bold text-gray-500">Remarks</th>
                                <th class="px-4 py-3 text-xs font-bold text-gray-500">Follow up</th>
                                <th class="px-4 py-3 text-xs font-bold text-gray-500 w-20">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-sm">
                            <tr v-for="(record, index) in paginatedHealthRecords" :key="record.id" class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-4 font-medium text-gray-900">{{ (currentHealthPage - 1) * healthItemsPerPage + index + 1 }}</td>
                                <td class="px-4 py-4 text-gray-600">{{ formatDate(record.date) }}</td>
                                <td class="px-4 py-4 text-gray-600 max-w-xs truncate">{{ record.symptoms || '-' }}</td>
                                <td class="px-4 py-4 text-gray-600 max-w-xs truncate">{{ record.treatment || '-' }}</td>
                                <td class="px-4 py-4 text-gray-600 max-w-xs truncate">{{ record.dosage || '-' }}</td>
                                <td class="px-4 py-4 text-gray-600 max-w-xs truncate">{{ record.medication || '-' }}</td>
                                <td class="px-4 py-4 text-gray-600 max-w-xs truncate">{{ record.remarks || '-' }}</td>
                                <td class="px-4 py-4 text-gray-600">
                                    <span v-if="record.follow_up_required === true">Yes</span>
                                    <span v-else>-</span>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <button @click="viewRecord(record)" class="w-8 h-8 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded inline-flex items-center justify-center transition-colors">
                                        <Eye class="w-4 h-4" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="healthRecords.length > 0" class="mt-6 flex flex-col md:flex-row justify-between items-center p-4 border border-gray-200 rounded-xl bg-gray-50">
                    <div class="flex items-center gap-2 mb-4 md:mb-0">
                        <button
                            @click="previousHealthPage"
                            :disabled="currentHealthPage === 1"
                            :class="currentHealthPage === 1 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-white'"
                            class="w-8 h-8 flex items-center justify-center border border-gray-200 rounded-lg text-gray-500"
                        >
                            <ChevronLeft class="w-4 h-4" />
                        </button>
                        <button
                            v-for="page in healthPageNumbers"
                            :key="page"
                            @click="page !== '...' && goToHealthPage(page)"
                            :class="[
                                page === currentHealthPage ? 'bg-[#34554a] text-white' : 'text-gray-600 hover:bg-white',
                                page === '...' ? 'cursor-default' : ''
                            ]"
                            class="w-8 h-8 flex items-center justify-center rounded-lg text-sm font-bold transition-colors"
                        >
                            {{ page }}
                        </button>
                        <button
                            @click="nextHealthPage"
                            :disabled="currentHealthPage === totalHealthPages || totalHealthPages === 0"
                            :class="currentHealthPage === totalHealthPages || totalHealthPages === 0 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-white'"
                            class="w-8 h-8 flex items-center justify-center border border-gray-200 rounded-lg text-gray-500"
                        >
                            <ChevronRight class="w-4 h-4" />
                        </button>
                    </div>
                    <div class="text-sm text-gray-500">
                        Showing <span class="font-semibold text-gray-800">{{ healthRecords.length > 0 ? (currentHealthPage - 1) * healthItemsPerPage + 1 : 0 }}-{{ Math.min(currentHealthPage * healthItemsPerPage, healthRecords.length) }}</span> of <span class="font-semibold text-gray-800">{{ healthRecords.length }}</span> records
                    </div>
                </div>
                <div v-else class="text-center py-12 bg-gray-50 rounded-xl border-2 border-dashed border-gray-200">
                    <Activity class="w-12 h-12 text-gray-300 mx-auto mb-3" />
                    <p class="text-gray-500 font-medium">No health records found for this cattle.</p>
                </div>
            </div>

            <div v-if="activeTab === 'mortality'">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-semibold text-gray-900">Mortality Records</h3>
                </div>

                <div v-if="mortalityRecords.length > 0" class="overflow-x-auto rounded-lg border border-gray-200">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-100">
                                <th class="px-4 py-3 text-xs font-bold text-gray-500 w-12">No.</th>
                                <th class="px-4 py-3 text-xs font-bold text-gray-500">Date</th>
                                <th class="px-4 py-3 text-xs font-bold text-gray-500">LMC No.</th>
                                <th class="px-4 py-3 text-xs font-bold text-gray-500">Category</th>
                                <th class="px-4 py-3 text-xs font-bold text-gray-500">Cause of Death</th>
                                <th class="px-4 py-3 text-xs font-bold text-gray-500">Treatment</th>
                                <th class="px-4 py-3 text-xs font-bold text-gray-500">Notes</th>
                                <th class="px-4 py-3 text-xs font-bold text-gray-500 w-20">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-sm">
                            <tr v-for="(record, index) in paginatedMortalityRecords" :key="record.id" class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-4 font-medium text-gray-900">{{ (currentMortalityPage - 1) * mortalityItemsPerPage + index + 1 }}</td>
                                <td class="px-4 py-4 text-gray-600">{{ formatDate(record.date) }}</td>
                                <td class="px-4 py-4 text-gray-600">{{ record.reference_no || '-' }}</td>
                                <td class="px-4 py-4 text-gray-600">{{ record.category || '-' }}</td>
                                <td class="px-4 py-4 text-gray-600 max-w-xs truncate">{{ record.description || '-' }}</td>
                                <td class="px-4 py-4 text-gray-600 max-w-xs truncate">{{ record.treatment || '-' }}</td>
                                <td class="px-4 py-4 text-gray-600 max-w-xs truncate">{{ record.notes || '-' }}</td>
                                <td class="px-4 py-4 text-center">
                                    <button @click="viewMortalityRecord(record)" class="w-8 h-8 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded inline-flex items-center justify-center transition-colors">
                                        <Eye class="w-4 h-4" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="mortalityRecords.length > 0" class="mt-6 flex flex-col md:flex-row justify-between items-center p-4 border border-gray-200 rounded-xl bg-gray-50">
                    <div class="flex items-center gap-2 mb-4 md:mb-0">
                        <button
                            @click="previousMortalityPage"
                            :disabled="currentMortalityPage === 1"
                            :class="currentMortalityPage === 1 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-white'"
                            class="w-8 h-8 flex items-center justify-center border border-gray-200 rounded-lg text-gray-500"
                        >
                            <ChevronLeft class="w-4 h-4" />
                        </button>
                        <button
                            v-for="page in mortalityPageNumbers"
                            :key="`mortality-page-${page}`"
                            @click="page !== '...' && goToMortalityPage(page)"
                            :class="[
                                page === currentMortalityPage ? 'bg-[#34554a] text-white' : 'text-gray-600 hover:bg-white',
                                page === '...' ? 'cursor-default' : ''
                            ]"
                            class="w-8 h-8 flex items-center justify-center rounded-lg text-sm font-bold transition-colors"
                        >
                            {{ page }}
                        </button>
                        <button
                            @click="nextMortalityPage"
                            :disabled="currentMortalityPage === totalMortalityPages || totalMortalityPages === 0"
                            :class="currentMortalityPage === totalMortalityPages || totalMortalityPages === 0 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-white'"
                            class="w-8 h-8 flex items-center justify-center border border-gray-200 rounded-lg text-gray-500"
                        >
                            <ChevronRight class="w-4 h-4" />
                        </button>
                    </div>
                    <div class="text-sm text-gray-500">
                        Showing <span class="font-semibold text-gray-800">{{ mortalityRecords.length > 0 ? (currentMortalityPage - 1) * mortalityItemsPerPage + 1 : 0 }}-{{ Math.min(currentMortalityPage * mortalityItemsPerPage, mortalityRecords.length) }}</span> of <span class="font-semibold text-gray-800">{{ mortalityRecords.length }}</span> records
                    </div>
                </div>
                <div v-else class="text-center py-12 bg-gray-50 rounded-xl border-2 border-dashed border-gray-200">
                    <AlertTriangle class="w-12 h-12 text-gray-300 mx-auto mb-3" />
                    <p class="text-gray-500 font-medium">No mortality records found for this cattle.</p>
                </div>
            </div>

            <!-- Movement Tab -->
            <div v-if="activeTab === 'movement'">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-semibold text-gray-900">Movement History</h3>
                </div>

                <div v-if="cattle.movements && cattle.movements.length > 0" class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-100">
                                <th class="px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider">From</th>
                                <th class="px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider">To</th>
                                <th class="px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Reason</th>
                                <th class="px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider">By</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="move in cattle.movements" :key="move.id" class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-4 text-sm text-gray-900">{{ formatDate(move.document?.date) }}</td>
                                <td class="px-4 py-4 text-sm text-gray-600">{{ move.document?.from_location }}</td>
                                <td class="px-4 py-4 text-sm text-gray-600">{{ move.document?.to_location }}</td>
                                <td class="px-4 py-4 text-sm text-gray-600">
                                    <span class="px-2 py-0.5 bg-gray-100 rounded text-[10px] font-bold uppercase">{{ move.document?.type }}</span>
                                    {{ move.purpose || move.remarks }}
                                </td>
                                <td class="px-4 py-4 text-sm text-gray-600">{{ move.document?.driver_name || 'N/A' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="text-center py-12 bg-gray-50 rounded-xl border-2 border-dashed border-gray-200">
                    <Truck class="w-12 h-12 text-gray-300 mx-auto mb-3" />
                    <p class="text-gray-500 font-medium">No movement history recorded.</p>
                </div>
            </div>
        </div>

        <!-- Detail Modal -->
        <div v-if="showDetailModal && selectedTreatment" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <!-- Backdrop -->
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="closeModal"></div>
            
            <!-- Modal Content -->
            <div class="relative bg-white rounded-xl shadow-xl w-full max-w-4xl mx-auto overflow-hidden border border-gray-100 max-h-[90vh] flex flex-col">
                <!-- Modal Header -->
                <div class="flex justify-between items-center p-6 border-b border-gray-100 bg-gray-50 sticky top-0 flex-shrink-0">
                    <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                        <ClipboardList class="w-5 h-5 text-[#34554a]" />
                        <span>Treatment Details - {{ selectedTreatment.treatment_no || ('TRT-' + selectedTreatment.id) }}</span>
                    </h3>
                    <button @click="closeModal" class="text-gray-400 hover:text-gray-600 transition-colors w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-200">
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
                                Treatment Information
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 text-sm">
                                <div v-for="(item, index) in treatmentDetailItems" :key="index" class="flex justify-between py-1.5 border-b border-gray-200 gap-3">
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

        <!-- Mortality Detail Modal -->
        <div v-if="showMortalityDetailModal && selectedMortalityRecord" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="closeMortalityModal"></div>

            <div class="relative bg-white rounded-xl shadow-xl w-full max-w-4xl mx-auto overflow-hidden border border-gray-100 max-h-[90vh] flex flex-col">
                <div class="flex justify-between items-center p-6 border-b border-gray-100 bg-gray-50 sticky top-0 flex-shrink-0">
                    <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                        <AlertTriangle class="w-5 h-5 text-[#34554a]" />
                        <span>Mortality Details - {{ selectedMortalityRecord.reference_no || ('LMC-' + (selectedMortalityRecord.source_id || selectedMortalityRecord.id)) }}</span>
                    </h3>
                    <button @click="closeMortalityModal" class="text-gray-400 hover:text-gray-600 transition-colors w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-6 overflow-y-auto flex-1">
                    <div class="bg-gray-50 rounded-xl p-4">
                        <h4 class="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2">
                            <AlertTriangle class="w-4 h-4 text-[#34554a]" />
                            Mortality Information
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 text-sm">
                            <div class="flex justify-between py-1.5 border-b border-gray-200 gap-3">
                                <span class="text-gray-500">Date</span>
                                <span class="font-semibold text-gray-900 text-right">{{ formatDate(selectedMortalityRecord.date) }}</span>
                            </div>
                            <div class="flex justify-between py-1.5 border-b border-gray-200 gap-3">
                                <span class="text-gray-500">LMC No.</span>
                                <span class="font-semibold text-gray-900 text-right">{{ selectedMortalityRecord.reference_no || '-' }}</span>
                            </div>
                            <div class="flex justify-between py-1.5 border-b border-gray-200 gap-3">
                                <span class="text-gray-500">Category</span>
                                <span class="font-semibold text-gray-900 text-right">{{ selectedMortalityRecord.category || '-' }}</span>
                            </div>
                            <div class="flex justify-between py-1.5 border-b border-gray-200 gap-3">
                                <span class="text-gray-500">Operating Unit</span>
                                <span class="font-semibold text-gray-900 text-right">{{ selectedMortalityRecord.operating_unit || '-' }}</span>
                            </div>
                            <div class="flex justify-between py-1.5 border-b border-gray-200 gap-3 md:col-span-2">
                                <span class="text-gray-500">Cause of Death</span>
                                <span class="font-semibold text-gray-900 text-right break-all">{{ selectedMortalityRecord.description || '-' }}</span>
                            </div>
                            <div class="flex justify-between py-1.5 border-b border-gray-200 gap-3 md:col-span-2">
                                <span class="text-gray-500">Treatment</span>
                                <span class="font-semibold text-gray-900 text-right break-all">{{ selectedMortalityRecord.treatment || '-' }}</span>
                            </div>
                            <div class="flex justify-between py-1.5 border-b border-gray-200 gap-3 md:col-span-2">
                                <span class="text-gray-500">Notes</span>
                                <span class="font-semibold text-gray-900 text-right break-all">{{ selectedMortalityRecord.notes || '-' }}</span>
                            </div>
                            <div class="flex justify-between py-1.5 border-b border-gray-200 gap-3">
                                <span class="text-gray-500">Status</span>
                                <span class="font-semibold text-gray-900 text-right">{{ formatStatusLabel(selectedMortalityRecord.status) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="border-t p-4 bg-gray-50 flex justify-end flex-shrink-0">
                    <button @click="closeMortalityModal" class="px-6 py-2.5 bg-gray-300 text-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-400 transition-colors">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>

    <div
        v-if="showAddFieldModal"
        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
        @click.self="showAddFieldModal = false">
        <div class="bg-white rounded-xl p-6 w-full max-w-md shadow-2xl max-h-[80vh] flex flex-col">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Manage {{ addFieldLabel }} Options</h3>

            <div class="flex gap-2 mb-4">
                <input
                    v-model="newFieldValue"
                    @keyup.enter="addNewFieldValue"
                    type="text"
                    :placeholder="`Add new ${addFieldLabel.toLowerCase()}...`"
                    class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent text-sm"
                />
                <button
                    @click="addNewFieldValue"
                    type="button"
                    :disabled="!newFieldValue.trim()"
                    class="px-4 py-2 bg-[#34554a] text-white rounded-lg hover:bg-opacity-90 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                >
                    <Plus class="w-4 h-4" />
                    Add
                </button>
            </div>

            <div class="flex-1 overflow-y-auto border border-gray-200 rounded-lg">
                <div v-if="getFieldOptions(addFieldType).length === 0" class="p-4 text-center text-gray-500 text-sm">
                    No options yet. Add one above.
                </div>
                <div v-else class="divide-y divide-gray-100">
                    <div v-for="field in getFieldOptions(addFieldType)" :key="field.id" class="flex items-center gap-2 p-3 hover:bg-gray-50">
                        <template v-if="editingFieldId === field.id">
                            <input
                                v-model="editingFieldValue"
                                @keyup.enter="saveEditField(field)"
                                @keyup.escape="cancelEditField"
                                type="text"
                                class="flex-1 px-3 py-1.5 border border-green-300 rounded-md focus:ring-2 focus:ring-green-500 focus:border-transparent text-sm"
                            />
                            <button @click="saveEditField(field)" type="button" class="p-1.5 text-green-600 hover:bg-green-50 rounded-md" title="Save">
                                <Check class="w-4 h-4" />
                            </button>
                            <button @click="cancelEditField" type="button" class="p-1.5 text-gray-500 hover:bg-gray-100 rounded-md" title="Cancel">
                                <X class="w-4 h-4" />
                            </button>
                        </template>

                        <template v-else>
                            <span class="flex-1 text-sm text-gray-700">{{ field.value }}</span>
                            <button v-if="canEditCattle" @click="startEditField(field)" type="button" class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-md transition-colors" title="Edit">
                                <Pencil class="w-4 h-4" />
                            </button>
                            <button v-if="canDeleteCattle" @click="deleteField(field)" type="button" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-md transition-colors" title="Delete">
                                <Trash2 class="w-4 h-4" />
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <div class="flex justify-end mt-4">
                <button
                    @click="showAddFieldModal = false"
                    type="button"
                    class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 font-medium text-sm"
                >
                    Done
                </button>
            </div>
        </div>
    </div>
</template>
