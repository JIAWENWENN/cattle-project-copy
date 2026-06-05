<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { Save, X, Upload, Image as ImageIcon, Plus, Pencil, Trash2, Check, Settings } from 'lucide-vue-next';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    onClose: Function,
    customFields: Object,
    operatingUnits: {
        type: Array,
        default: () => []
    }
});

const selectedOperatingUnit = ref('');
const selectedOperatingUnitRecord = computed(() => {
    if (!selectedOperatingUnit.value) return null;
    return props.operatingUnits.find(u => u.id === selectedOperatingUnit.value) || null;
});
const filteredBlocks = computed(() => {
    return selectedOperatingUnitRecord.value?.pasture_blocks || selectedOperatingUnitRecord.value?.blocks || [];
});

const profilePreview = ref(null);
const showAddFieldModal = ref(false);
const addFieldType = ref('');
const addFieldLabel = ref('');
const newFieldValue = ref('');
const editingFieldId = ref(null);
const editingFieldValue = ref('');
const isManageMode = ref(false);

// Local copy of custom fields that we can update (deep copy)
const localCustomFields = ref(JSON.parse(JSON.stringify(props.customFields || {})));

// Watch for prop changes and update local copy
watch(() => props.customFields, (newVal) => {
    if (newVal) {
        localCustomFields.value = JSON.parse(JSON.stringify(newVal));
    }
}, { deep: true });

const form = useForm({
    // Basic Information
    tag_no: '',
    category: '',
    coat_colour: '',
    birth_date: '',
    gender: 'Male',

    // Receival & Condition
    receival_weight: '',
    general_condition: '',
    operating_unit: '',
    location_block: '',
    location_phase: '',

    // Dam (Mother) Genealogy
    dam_tag: '',
    dam_category: '',
    dam_colour: '',

    // Sire (Father) Genealogy
    sire_tag: '',
    sire_category: '',
    sire_coat_colour: '',

    // Milestones (Optional)
    weaning_weight: '',
    yearling_weight: '',

    // Additional
    status: 'Active',
    remarks: '',
    profile_picture: null
});

const selectedBlock = computed(() => {
    if (!form.location_block) return null;
    return filteredBlocks.value.find(block => block.name === form.location_block) || null;
});

const filteredPhases = computed(() => selectedBlock.value?.phases || []);

// Watch after form is initialized; otherwise opening the modal can throw a TDZ error.
watch(selectedOperatingUnit, () => {
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

const removeImage = () => {
    form.profile_picture = null;
    profilePreview.value = null;
};

// Open modal to add/manage field values
const openAddFieldModal = (fieldType, fieldLabel) => {
    addFieldType.value = fieldType;
    addFieldLabel.value = fieldLabel;
    newFieldValue.value = '';
    editingFieldId.value = null;
    editingFieldValue.value = '';
    isManageMode.value = false;
    showAddFieldModal.value = true;
};

// Add new field value
const addNewFieldValue = () => {
    if (!newFieldValue.value.trim()) return;

    router.post(route('custom-fields.store'), {
        field_type: addFieldType.value,
        value: newFieldValue.value.trim()
    }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            // Update local state from page props
            if (page.props.customFields) {
                localCustomFields.value = JSON.parse(JSON.stringify(page.props.customFields));
            }

            // Set as selected value
            form[addFieldType.value] = newFieldValue.value.trim();

            newFieldValue.value = '';
        },
        onError: (errors) => {
            alert(errors.value || 'Failed to add option');
        }
    });
};

// Start editing a field
const startEditField = (field) => {
    editingFieldId.value = field.id;
    editingFieldValue.value = field.value;
};

// Cancel editing
const cancelEditField = () => {
    editingFieldId.value = null;
    editingFieldValue.value = '';
};

// Save edited field
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
            editingFieldId.value = null;
            editingFieldValue.value = '';
        },
        onError: (errors) => {
            alert(errors.value || 'Failed to update option');
        }
    });
};

// Delete a field
const deleteField = (field) => {
    if (!confirm(`Are you sure you want to delete "${field.value}"?`)) return;

    router.delete(route('custom-fields.destroy', field.id), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            if (page.props.customFields) {
                localCustomFields.value = JSON.parse(JSON.stringify(page.props.customFields));
            }
            // Clear selection if deleted item was selected
            if (form[addFieldType.value] === field.value) {
                form[addFieldType.value] = '';
            }
        }
    });
};

// Get options for a field type (returns array of objects with id and value)
const getFieldOptions = (fieldType) => {
    return localCustomFields.value?.[fieldType] || [];
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

watch(statusOptions, (options) => {
    if (!options.some(opt => opt.value === form.status)) {
        form.status = options[0]?.value || '';
    }
}, { immediate: true });

const submit = () => {
    form.post(route('cattle.store'), {
        onSuccess: () => {
            form.reset();
            profilePreview.value = null;
            props.onClose();
        }
    });
};
</script>

<template>
    <form @submit.prevent="submit" class="space-y-6 p-6 max-h-[80vh] overflow-y-auto">

        <!-- VALIDATION ERRORS -->
        <div v-if="Object.keys(form.errors).length > 0" class="bg-red-50 border border-red-200 rounded-lg p-4">
            <h4 class="text-red-800 font-medium text-sm mb-2">Please fix the following errors:</h4>
            <ul class="list-disc list-inside text-red-600 text-sm space-y-1">
                <li v-for="(error, field) in form.errors" :key="field">{{ error }}</li>
            </ul>
        </div>

        <!-- PROFILE PICTURE UPLOAD -->
        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
            <h3 class="text-sm font-semibold text-gray-900 mb-3">Cattle Profile Picture</h3>
            <div class="flex items-start gap-4">
                <div class="w-32 h-32 rounded-lg border-2 border-dashed border-gray-300 flex items-center justify-center bg-white overflow-hidden">
                    <img v-if="profilePreview" :src="profilePreview" alt="Preview" class="w-full h-full object-cover" />
                    <ImageIcon v-else class="w-12 h-12 text-gray-300" />
                </div>

                <div class="flex-1">
                    <input
                        type="file"
                        @change="handleImageUpload"
                        accept="image/*"
                        class="hidden"
                        id="profile-upload"
                    />
                    <label
                        for="profile-upload"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 cursor-pointer">
                        <Upload class="w-4 h-4" />
                        Upload Picture
                    </label>
                    <button
                        v-if="profilePreview"
                        @click="removeImage"
                        type="button"
                        class="ml-2 px-4 py-2 bg-red-50 text-red-600 rounded-lg text-sm font-medium hover:bg-red-100">
                        Remove
                    </button>
                    <p class="text-xs text-gray-500 mt-2">JPG, PNG (max 2MB)</p>
                </div>
            </div>
        </div>

        <!-- BASIC INFORMATION -->
        <div>
            <h3 class="text-sm font-semibold text-gray-900 mb-4 pb-2 border-b">Basic Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="flex flex-col space-y-1">
                    <label class="text-sm font-medium text-gray-700">Tag No <span class="text-red-500">*</span></label>
                    <input
                        v-model="form.tag_no"
                        type="text"
                        required
                        class="px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 text-sm"
                        placeholder="Enter tag number">
                </div>

                <!-- Category with Add New -->
                <div class="flex flex-col space-y-1">
                    <label class="text-sm font-medium text-gray-700">Category <span class="text-red-500">*</span></label>
                    <div class="flex gap-2">
                        <select
                            v-model="form.category"
                            required
                            class="flex-1 px-3 py-2 border border-gray-300 rounded-md bg-white text-sm focus:ring-green-500 focus:border-green-500">
                            <option value="" disabled>Select...</option>
                            <option v-for="opt in getFieldOptions('category')" :key="opt.id" :value="opt.value">{{ opt.value }}</option>
                        </select>
                        <button
                            type="button"
                            @click="openAddFieldModal('category', 'Category')"
                            class="px-3 py-2 bg-green-50 text-green-600 rounded-md hover:bg-green-100 transition-colors"
                            title="Manage Categories">
                            <Settings class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                <!-- Coat Colour with Add New -->
                <div class="flex flex-col space-y-1">
                    <label class="text-sm font-medium text-gray-700">Coat Colour</label>
                    <div class="flex gap-2">
                        <select
                            v-model="form.coat_colour"
                            class="flex-1 px-3 py-2 border border-gray-300 rounded-md bg-white text-sm focus:ring-green-500 focus:border-green-500">
                            <option value="">Select...</option>
                            <option v-for="opt in getFieldOptions('coat_colour')" :key="opt.id" :value="opt.value">{{ opt.value }}</option>
                        </select>
                        <button
                            type="button"
                            @click="openAddFieldModal('coat_colour', 'Coat Colour')"
                            class="px-3 py-2 bg-green-50 text-green-600 rounded-md hover:bg-green-100 transition-colors"
                            title="Manage Colours">
                            <Settings class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                <div class="flex flex-col space-y-1">
                    <label class="text-sm font-medium text-gray-700">Birth Date</label>
                    <input
                        v-model="form.birth_date"
                        type="date"
                        class="px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 text-sm">
                </div>

                <div class="flex flex-col space-y-1">
                    <label class="text-sm font-medium text-gray-700">Gender <span class="text-red-500">*</span></label>
                    <select
                        v-model="form.gender"
                        required
                        class="px-3 py-2 border border-gray-300 rounded-md bg-white text-sm focus:ring-green-500 focus:border-green-500">
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- RECEIVAL & CONDITION -->
        <div>
            <h3 class="text-sm font-semibold text-gray-900 mb-4 pb-2 border-b">Receival & Condition</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="flex flex-col space-y-1">
                    <label class="text-sm font-medium text-gray-700">Receival Weight (kg)</label>
                    <input
                        v-model="form.receival_weight"
                        type="number"
                        min="0"
                        step="0.01"
                        class="px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 text-sm"
                        placeholder="0.00">
                </div>

                <!-- General Condition with Add New -->
                <div class="flex flex-col space-y-1">
                    <label class="text-sm font-medium text-gray-700">General Condition</label>
                    <div class="flex gap-2">
                        <select
                            v-model="form.general_condition"
                            class="flex-1 px-3 py-2 border border-gray-300 rounded-md bg-white text-sm focus:ring-green-500 focus:border-green-500">
                            <option value="">Select...</option>
                            <option v-for="opt in getFieldOptions('general_condition')" :key="opt.id" :value="opt.value">{{ opt.value }}</option>
                        </select>
                        <button
                            type="button"
                            @click="openAddFieldModal('general_condition', 'Condition')"
                            class="px-3 py-2 bg-green-50 text-green-600 rounded-md hover:bg-green-100 transition-colors"
                            title="Manage Conditions">
                            <Settings class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                <!-- Operating Unit -->
                <div class="flex flex-col space-y-1">
                    <label class="text-sm font-medium text-gray-700">Operating Unit</label>
                    <select
                        v-model="selectedOperatingUnit"
                        class="px-3 py-2 border border-gray-300 rounded-md bg-white text-sm focus:ring-green-500 focus:border-green-500">
                        <option value="">Select Operating Unit...</option>
                        <option v-for="unit in operatingUnits" :key="unit.id" :value="unit.id">
                            {{ unit.name }}
                        </option>
                    </select>
                </div>

                <!-- Location Block -->
                <div class="flex flex-col space-y-1">
                    <label class="text-sm font-medium text-gray-700">Location Block</label>
                    <select
                        v-model="form.location_block"
                        :disabled="!selectedOperatingUnit"
                        class="px-3 py-2 border border-gray-300 rounded-md bg-white text-sm focus:ring-green-500 focus:border-green-500 disabled:bg-gray-100">
                        <option value="">Select Block...</option>
                        <option v-for="block in filteredBlocks" :key="block.id" :value="block.name">
                            {{ block.name }}
                        </option>
                    </select>
                </div>

                <!-- Location Phase -->
                <div class="flex flex-col space-y-1">
                    <label class="text-sm font-medium text-gray-700">Location Phase</label>
                    <select
                        v-model="form.location_phase"
                        :disabled="!form.location_block"
                        class="px-3 py-2 border border-gray-300 rounded-md bg-white text-sm focus:ring-green-500 focus:border-green-500 disabled:bg-gray-100">
                        <option value="">Select Phase...</option>
                        <option v-for="phase in filteredPhases" :key="phase.id" :value="phase.name">
                            {{ phase.name }}
                        </option>
                    </select>
                </div>
            </div>
        </div>

        <!-- GENEALOGY -->
        <div>
            <h3 class="text-sm font-semibold text-gray-900 mb-4 pb-2 border-b">Genealogy</h3>

            <!-- Dam (Mother) -->
            <div class="mb-4">
                <h4 class="text-sm font-medium text-pink-600 mb-3">Dam (Mother)</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="flex flex-col space-y-1">
                        <label class="text-sm font-medium text-gray-700">Dam Tag</label>
                        <input
                            v-model="form.dam_tag"
                            type="text"
                            class="px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 text-sm"
                            placeholder="Mother's tag">
                    </div>

                    <div class="flex flex-col space-y-1">
                        <label class="text-sm font-medium text-gray-700">Dam Category</label>
                        <select
                            v-model="form.dam_category"
                            class="px-3 py-2 border border-gray-300 rounded-md bg-white text-sm focus:ring-green-500 focus:border-green-500">
                            <option value="">Select...</option>
                            <option v-for="opt in getFieldOptions('category')" :key="opt.id" :value="opt.value">{{ opt.value }}</option>
                        </select>
                    </div>

                    <div class="flex flex-col space-y-1">
                        <label class="text-sm font-medium text-gray-700">Dam Colour</label>
                        <select
                            v-model="form.dam_colour"
                            class="px-3 py-2 border border-gray-300 rounded-md bg-white text-sm focus:ring-green-500 focus:border-green-500">
                            <option value="">Select...</option>
                            <option v-for="opt in getFieldOptions('coat_colour')" :key="opt.id" :value="opt.value">{{ opt.value }}</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Sire (Father) -->
            <div>
                <h4 class="text-sm font-medium text-blue-600 mb-3">Sire (Father)</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="flex flex-col space-y-1">
                        <label class="text-sm font-medium text-gray-700">Sire Tag</label>
                        <input
                            v-model="form.sire_tag"
                            type="text"
                            class="px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 text-sm"
                            placeholder="Father's tag">
                    </div>

                    <div class="flex flex-col space-y-1">
                        <label class="text-sm font-medium text-gray-700">Sire Category</label>
                        <select
                            v-model="form.sire_category"
                            class="px-3 py-2 border border-gray-300 rounded-md bg-white text-sm focus:ring-green-500 focus:border-green-500">
                            <option value="">Select...</option>
                            <option v-for="opt in getFieldOptions('category')" :key="opt.id" :value="opt.value">{{ opt.value }}</option>
                        </select>
                    </div>

                    <div class="flex flex-col space-y-1">
                        <label class="text-sm font-medium text-gray-700">Sire Coat Colour</label>
                        <select
                            v-model="form.sire_coat_colour"
                            class="px-3 py-2 border border-gray-300 rounded-md bg-white text-sm focus:ring-green-500 focus:border-green-500">
                            <option value="">Select...</option>
                            <option v-for="opt in getFieldOptions('coat_colour')" :key="opt.id" :value="opt.value">{{ opt.value }}</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- ADDITIONAL INFO -->
        <div>
            <h3 class="text-sm font-semibold text-gray-900 mb-4 pb-2 border-b">Additional Information</h3>
            <div class="space-y-4">
                <div class="flex flex-col space-y-1">
                    <label class="text-sm font-medium text-gray-700">Status</label>
                    <select
                        v-model="form.status"
                        class="px-3 py-2 border border-gray-300 rounded-md bg-white text-sm focus:ring-green-500 focus:border-green-500">
                        <option v-for="opt in statusOptions" :key="opt.id" :value="opt.value">{{ opt.value }}</option>
                    </select>
                </div>

                <div class="flex flex-col space-y-1">
                    <label class="text-sm font-medium text-gray-700">Remarks</label>
                    <textarea
                        v-model="form.remarks"
                        rows="3"
                        class="px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 text-sm resize-none"
                        placeholder="Additional notes..."></textarea>
                </div>
            </div>
        </div>

        <!-- FOOTER ACTIONS -->
        <div class="sticky bottom-0 bg-white border-t pt-4 flex justify-end gap-3">
            <button
                type="button"
                @click="onClose"
                class="px-5 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 font-medium text-sm flex items-center gap-2">
                <X class="w-4 h-4" />
                Cancel
            </button>
            <button
                type="submit"
                :disabled="form.processing"
                class="px-5 py-2.5 bg-[#34554a] text-white rounded-lg hover:bg-opacity-90 font-medium text-sm flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                <Save class="w-4 h-4" />
                {{ form.processing ? 'Saving...' : 'Register Cattle' }}
            </button>
        </div>

        <!-- Manage Field Options Modal -->
        <div
            v-if="showAddFieldModal"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
            @click.self="showAddFieldModal = false">
            <div class="bg-white rounded-xl p-6 w-full max-w-md shadow-2xl max-h-[80vh] flex flex-col">
                <h3 class="text-lg font-bold text-gray-900 mb-4">
                    Manage {{ addFieldLabel }} Options
                </h3>

                <!-- Add New Option -->
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
                        class="px-4 py-2 bg-[#34554a] text-white rounded-lg hover:bg-opacity-90 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
                        <Plus class="w-4 h-4" />
                        Add
                    </button>
                </div>

                <!-- Existing Options List -->
                <div class="flex-1 overflow-y-auto border border-gray-200 rounded-lg">
                    <div v-if="getFieldOptions(addFieldType).length === 0" class="p-4 text-center text-gray-500 text-sm">
                        No options yet. Add one above.
                    </div>
                    <div v-else class="divide-y divide-gray-100">
                        <div
                            v-for="field in getFieldOptions(addFieldType)"
                            :key="field.id"
                            class="flex items-center gap-2 p-3 hover:bg-gray-50">
                            <!-- Edit Mode -->
                            <template v-if="editingFieldId === field.id">
                                <input
                                    v-model="editingFieldValue"
                                    @keyup.enter="saveEditField(field)"
                                    @keyup.escape="cancelEditField"
                                    type="text"
                                    class="flex-1 px-3 py-1.5 border border-green-300 rounded-md focus:ring-2 focus:ring-green-500 focus:border-transparent text-sm"
                                    autofocus
                                />
                                <button
                                    @click="saveEditField(field)"
                                    type="button"
                                    class="p-1.5 text-green-600 hover:bg-green-50 rounded-md"
                                    title="Save">
                                    <Check class="w-4 h-4" />
                                </button>
                                <button
                                    @click="cancelEditField"
                                    type="button"
                                    class="p-1.5 text-gray-500 hover:bg-gray-100 rounded-md"
                                    title="Cancel">
                                    <X class="w-4 h-4" />
                                </button>
                            </template>

                            <!-- Display Mode -->
                            <template v-else>
                                <span class="flex-1 text-sm text-gray-700">{{ field.value }}</span>
                                <button
                                    @click="startEditField(field)"
                                    type="button"
                                    class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-md transition-colors"
                                    title="Edit">
                                    <Pencil class="w-4 h-4" />
                                </button>
                                <button
                                    @click="deleteField(field)"
                                    type="button"
                                    class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-md transition-colors"
                                    title="Delete">
                                    <Trash2 class="w-4 h-4" />
                                </button>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Close Button -->
                <div class="flex justify-end mt-4">
                    <button
                        @click="showAddFieldModal = false"
                        type="button"
                        class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 font-medium text-sm">
                        Done
                    </button>
                </div>
            </div>
        </div>
    </form>
</template>
