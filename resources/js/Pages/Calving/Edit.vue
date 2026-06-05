<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { Save, ArrowLeft, Settings, Plus, Pencil, Trash2, Check, X } from 'lucide-vue-next';

const props = defineProps({
    customFields: Object,
    operatingUnits: {
        type: Array,
        default: () => [],
    },
    operatingUnitsWithStructure: {
        type: Array,
        default: () => [],
    },
    allBlocks: {
        type: Array,
        default: () => [],
    },
    allPhases: {
        type: Array,
        default: () => [],
    },
    estates: {
        type: Array,
        default: () => [],
    },
    locationBlocks: {
        type: Array,
        default: () => [],
    },
    locationPhases: {
        type: Array,
        default: () => [],
    },
    calvingRecord: {
        type: Object,
        required: true
    }
});

defineOptions({
    layout: (h, page) => h(AppLayout, { title: 'Edit Monthly Calving Record', parent: 'Calving', parentUrl: '/calving' }, () => page)
});

const page = usePage();
const userRole = computed(() => String(page.props.auth?.user?.role || '').toLowerCase());
const canManageColours = computed(() => ['admin', 'manager', 'livestock manager'].includes(userRole.value));

const toTitleCase = (value) => {
    if (!value) return '';
    return String(value)
        .toLowerCase()
        .split(' ')
        .filter(Boolean)
        .map(word => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
};

const availableBlocks = computed(() => {
    return props.allBlocks || [];
});

const availablePhases = computed(() => {
    return props.allPhases || [];
});

const showColourManagerModal = ref(false);
const addFieldType = ref('calving_colour');
const addFieldLabel = ref('Coat colour');
const newFieldValue = ref('');
const editingFieldId = ref(null);
const editingFieldValue = ref('');
const localCustomFields = ref(JSON.parse(JSON.stringify(props.customFields || {})));

watch(() => props.customFields, (newVal) => {
    if (newVal) {
        localCustomFields.value = JSON.parse(JSON.stringify(newVal));
    }
}, { deep: true });

const normalizeDate = (value) => {
    if (!value) return '';
    return String(value).split('T')[0];
};

const form = useForm({
    tag_no: props.calvingRecord.tag_no || '',
    lcc_running_number: props.calvingRecord.lcc_running_number || '',
    cattle_no_request_form: props.calvingRecord.cattle_no_request_form || '',
    calving_date: normalizeDate(props.calvingRecord.calving_date),
    operating_unit: props.calvingRecord.operating_unit || 'SKLIC BREEDLOT',
    sex: props.calvingRecord.sex || 'MC',
    colour: props.calvingRecord.colour || 'Merah (Red)',
    general_condition: props.calvingRecord.general_condition || 'Normal',
    dam_tag_no: props.calvingRecord.dam_tag_no || '',
    dam_colour: props.calvingRecord.dam_colour || '',
    sire_tag_no: props.calvingRecord.sire_tag_no || '',
    sire_colour: props.calvingRecord.sire_colour || '',
    worker_name: props.calvingRecord.worker_name || '',
    location_block: props.calvingRecord.location_block || '',
    location_phase: props.calvingRecord.location_phase || '',
    remarks: props.calvingRecord.remarks || ''
});

const defaultColours = [
    { id: 'default-grey', value: 'Kelabu (Grey)' },
    { id: 'default-honey', value: 'Madu (Honey)' },
    { id: 'default-red', value: 'Merah (Red)' },
    { id: 'default-black', value: 'Hitam (Black)' },
    { id: 'default-stripe', value: 'Berjalur (Stripe)' },
];

const getFieldOptions = (fieldType) => {
    return localCustomFields.value?.[fieldType] || [];
};

const calfColourOptions = computed(() => {
    const opts = getFieldOptions('calving_colour');
    if (opts.length > 0) return opts;
    return defaultColours;
});

const damColourOptions = computed(() => {
    const opts = getFieldOptions('calving_dam_colour');
    if (opts.length > 0) return opts;
    return defaultColours;
});

const sireColourOptions = computed(() => {
    const opts = getFieldOptions('calving_sire_colour');
    if (opts.length > 0) return opts;
    return defaultColours;
});

watch(calfColourOptions, (options) => {
    if (!options.some(opt => opt.value === form.colour)) {
        form.colour = options[0]?.value || '';
    }
}, { immediate: true });

watch(damColourOptions, (options) => {
    if (!options.some(opt => opt.value === form.dam_colour)) {
        form.dam_colour = '';
    }
}, { immediate: true });

watch(sireColourOptions, (options) => {
    if (!options.some(opt => opt.value === form.sire_colour)) {
        form.sire_colour = '';
    }
}, { immediate: true });

const openColourManager = (fieldType = 'calving_colour', label = 'Coat colour') => {
    addFieldType.value = fieldType;
    addFieldLabel.value = label;
    newFieldValue.value = '';
    editingFieldId.value = null;
    editingFieldValue.value = '';
    showColourManagerModal.value = true;
};

const addNewFieldValue = () => {
    if (!newFieldValue.value.trim()) return;

    router.post(route('custom-fields.store'), {
        field_type: addFieldType.value,
        value: newFieldValue.value.trim()
    }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: (pageData) => {
            if (pageData.props.customFields) {
                localCustomFields.value = JSON.parse(JSON.stringify(pageData.props.customFields));
            }
            newFieldValue.value = '';
        },
        onError: (errors) => {
            alert(errors.value || 'Failed to add option');
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

    router.post(route('custom-fields.update', field.id), {
        _method: 'PUT',
        value: editingFieldValue.value.trim()
    }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: (pageData) => {
            if (pageData.props.customFields) {
                localCustomFields.value = JSON.parse(JSON.stringify(pageData.props.customFields));
            }
            editingFieldId.value = null;
            editingFieldValue.value = '';
        },
        onError: (errors) => {
            alert(errors.value || 'Failed to update option');
        }
    });
};

const deleteField = (field) => {
    if (!confirm(`Are you sure you want to delete "${field.value}"?`)) return;

    router.post(route('custom-fields.destroy', field.id), {
        _method: 'DELETE',
        preserveState: true,
        preserveScroll: true,
        onSuccess: (pageData) => {
            if (pageData.props.customFields) {
                localCustomFields.value = JSON.parse(JSON.stringify(pageData.props.customFields));
            }
            if (form.colour === field.value) form.colour = calfColourOptions.value[0]?.value || '';
            if (form.dam_colour === field.value) form.dam_colour = '';
            if (form.sire_colour === field.value) form.sire_colour = '';
        }
    });
};

const submitForm = () => {
    console.log('=== FORM SUBMISSION STARTED ===');
    console.log('Form data being submitted:');
    console.log(JSON.stringify({
        tag_no: form.tag_no,
        lcc_running_number: form.lcc_running_number,
        cattle_no_request_form: form.cattle_no_request_form,
        calving_date: form.calving_date,
        operating_unit: form.operating_unit,
        sex: form.sex,
        colour: form.colour,
        general_condition: form.general_condition,
        dam_tag_no: form.dam_tag_no,
        dam_colour: form.dam_colour,
        sire_tag_no: form.sire_tag_no,
        sire_colour: form.sire_colour,
        worker_name: form.worker_name,
        location_block: form.location_block,
        location_phase: form.location_phase,
        remarks: form.remarks
    }, null, 2));
    
    if (!form.tag_no) {
        alert('Please enter an Identification Tag');
        document.querySelector('input[placeholder*="e.g., 5327"]')?.focus();
        return;
    }
    
    if (!form.calving_date) {
        alert('Please enter a Date of Birth');
        document.querySelector('input[type="date"]')?.focus();
        return;
    }
    
    if (!form.colour) {
        alert('Please select a Coat Colour');
        return;
    }
    
    form.put(`/calving/${props.calvingRecord.id}`, {
        onStart: () => {
            console.log('Request started');
        },
        onProgress: (progress) => {
            console.log('Upload progress:', progress);
        },
        onSuccess: (page) => {
            console.log('=== SUCCESS ===');
            console.log('Page props:', JSON.stringify(page.props, null, 2));
            console.log('Flash messages:', page.props.flash);
            
            if (page.props.flash?.success) {
                alert(page.props.flash.success);
            } else {
                alert('Record updated successfully!');
            }
            // Force full page reload to get fresh data
            window.location.href = '/calving';
        },
        onError: (errors) => {
            console.error('=== VALIDATION/ERROR ===');
            console.error('Errors:', JSON.stringify(errors, null, 2));
            const errorMessage = Object.entries(errors)
                .map(([key, value]) => `${key}: ${value}`)
                .join('\n');
            alert('Validation Errors:\n' + errorMessage);
        },
        onFinish: () => {
            console.log('Request finished');
            console.log('=== FORM SUBMISSION ENDED ===');
        }
    });
};

const goBack = () => {
    window.history.back();
};
</script>

<template>
    <div class="w-full max-w-6xl mx-auto">
        <div class="mb-8 flex justify-between items-center">
            <div class="flex items-center gap-4">
                <button @click="goBack" class="w-10 h-10 flex items-center justify-center rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors">
                    <ArrowLeft class="w-5 h-5 text-gray-600" />
                </button>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Edit monthly calving record</h1>
                    <p class="text-sm text-gray-500 mt-1">Update calving details</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <button type="button" @click="goBack" class="px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
                <button type="button" @click="submitForm" class="flex items-center gap-2 bg-[#34554a] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#2a443b] shadow-sm transition-colors disabled:opacity-50" :disabled="form.processing">
                    <Save class="w-4 h-4" />
                    {{ form.processing ? 'Saving...' : 'Save changes' }}
                </button>
            </div>
        </div>

        <form @submit.prevent>
            <!-- Form Scope -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
                <div class="px-6 py-4 bg-[#34554a] border-b border-gray-100">
                    <h3 class="text-sm font-bold text-white">Form scope</h3>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Operating unit *</label>
                        <select v-model="form.operating_unit" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]">
                            <option value="">Select operating unit</option>
                            <option v-for="estate in props.operatingUnitsWithStructure" :key="estate.id" :value="estate.name">{{ toTitleCase(estate.name) }}</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Calf Identification -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
                <div class="px-6 py-4 bg-[#34554a] border-b border-gray-100">
                    <h3 class="text-sm font-bold text-white">Calf identification</h3>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Identification tag *</label>
                        <input type="text" v-model="form.tag_no" placeholder="e.g., 5327" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" required />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">LCC no.</label>
                        <input type="text" v-model="form.lcc_running_number" placeholder="e.g., 3179" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Cattle no. request form</label>
                        <input type="text" v-model="form.cattle_no_request_form" placeholder="e.g., CFR-001" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Date of birth *</label>
                        <input type="date" v-model="form.calving_date" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" required />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Gender *</label>
                        <select v-model="form.sex" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]">
                            <option value="MC">Male</option>
                            <option value="FC">Female</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1 flex items-center justify-between">
                            <span>Coat colour *</span>
                            <button
                                v-if="canManageColours"
                                type="button"
                                @click="openColourManager('calving_colour', 'Coat colour')"
                                class="text-xs px-2 py-1 bg-gray-100 text-gray-700 rounded hover:bg-gray-200 transition-colors flex items-center gap-1"
                            >
                                <Settings class="w-3 h-3" />
                                Manage
                            </button>
                        </label>
                        <select v-model="form.colour" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" required>
                            <option value="">Select colour</option>
                            <option v-for="c in calfColourOptions" :key="c.id" :value="c.value">{{ c.value }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Cattle condition</label>
                        <select v-model="form.general_condition" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]">
                            <option value="Normal">Normal</option>
                            <option value="Abnormal">Abnormal</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Breeder's Details -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
                <div class="px-6 py-4 bg-[#34554a] border-b border-gray-100">
                    <h3 class="text-sm font-bold text-white">Breeder's details</h3>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Dam's identification tag</label>
                        <input type="text" v-model="form.dam_tag_no" placeholder="e.g., BF 23 3077" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1 flex items-center justify-between">
                            <span>Dam coat colour</span>
                            <button
                                v-if="canManageColours"
                                type="button"
                                @click="openColourManager('calving_dam_colour', 'Dam coat colour')"
                                class="text-xs px-2 py-1 bg-gray-100 text-gray-700 rounded hover:bg-gray-200 transition-colors flex items-center gap-1"
                            >
                                <Settings class="w-3 h-3" />
                                Manage
                            </button>
                        </label>
                        <select v-model="form.dam_colour" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]">
                            <option value="">Select colour</option>
                            <option v-for="c in damColourOptions" :key="`dam-${c.id}`" :value="c.value">{{ c.value }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Sire identification tag</label>
                        <input type="text" v-model="form.sire_tag_no" placeholder="e.g., BS 23 4001" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1 flex items-center justify-between">
                            <span>Sire coat colour</span>
                            <button
                                v-if="canManageColours"
                                type="button"
                                @click="openColourManager('calving_sire_colour', 'Sire coat colour')"
                                class="text-xs px-2 py-1 bg-gray-100 text-gray-700 rounded hover:bg-gray-200 transition-colors flex items-center gap-1"
                            >
                                <Settings class="w-3 h-3" />
                                Manage
                            </button>
                        </label>
                        <select v-model="form.sire_colour" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]">
                            <option value="">Select colour</option>
                            <option v-for="c in sireColourOptions" :key="`sire-${c.id}`" :value="c.value">{{ c.value }}</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Other Details -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
                <div class="px-6 py-4 bg-[#34554a] border-b border-gray-100">
                    <h3 class="text-sm font-bold text-white">Other details</h3>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Worker's name</label>
                        <input type="text" v-model="form.worker_name" placeholder="e.g., John bin Peter" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Remarks</label>
                        <textarea v-model="form.remarks" rows="2" placeholder="Additional notes..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]"></textarea>
                    </div>
                </div>
            </div>

            <!-- Location -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
                <div class="px-6 py-4 bg-[#34554a] border-b border-gray-100">
                    <h3 class="text-sm font-bold text-white">Location</h3>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Operating Unit</label>
                        <input type="text" :value="form.operating_unit" readonly class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-gray-100 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Block</label>
                        <select v-model="form.location_block" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]">
                            <option value="">Select block</option>
                            <option v-for="block in availableBlocks" :key="block" :value="block">{{ toTitleCase(block) }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Phase</label>
                        <select v-model="form.location_phase" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]">
                            <option value="">Select phase</option>
                            <option v-for="phase in availablePhases" :key="phase" :value="phase">{{ toTitleCase(phase) }}</option>
                        </select>
                    </div>
                </div>
            </div>

            <div
                v-if="showColourManagerModal"
                class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
                @click.self="showColourManagerModal = false"
            >
                <div class="bg-white rounded-xl p-6 w-full max-w-md shadow-2xl max-h-[80vh] flex flex-col">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">
                        Manage {{ addFieldLabel }} options
                    </h3>

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
                            <div
                                v-for="field in getFieldOptions(addFieldType)"
                                :key="field.id"
                                class="flex items-center gap-2 p-3 hover:bg-gray-50"
                            >
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
                                    <button @click="startEditField(field)" type="button" class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-md transition-colors" title="Edit">
                                        <Pencil class="w-4 h-4" />
                                    </button>
                                    <button @click="deleteField(field)" type="button" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-md transition-colors" title="Delete">
                                        <Trash2 class="w-4 h-4" />
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end mt-4">
                        <button @click="showColourManagerModal = false" type="button" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 font-medium text-sm">
                            Done
                        </button>
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
</template>
