<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { 
    Save, ArrowLeft, X, Pencil, Trash2, Settings, Search, ChevronDown, Check
} from 'lucide-vue-next';

const page = usePage();
const saving = ref(false);

const props = defineProps({
    treatment: Object
});

const isEditing = computed(() => !!props.treatment);

defineOptions({
    layout: (h, page) => h(AppLayout, { 
        title: 'Treatment', 
        parent: 'Health', 
        parentUrl: '/health/treatment' 
    }, () => page)
});

const form = ref({
    date: new Date().toISOString().split('T')[0],
    cattle_id: null,
    tag_no: '',
    category: '',
    operating_unit: '',
    colour: '',
    symptoms: '',
    treatment_code: '',
    dosage: '',
    remarks: '',
    follow_up_required: false,
    follow_up_date: '',

});

// Searchable cattle dropdown state (must be defined before form population)
const showCattleDropdown = ref(false);
const cattleSearchQuery = ref('');

// Add cattle data from controller
const cattle = page.props.cattle || [];
const treatmentCodes = ref([...(page.props.treatmentCodes || [])]);
const estates = page.props.estates || [];

// Populate form if editing
if (props.treatment) {
    const tCattle = cattle.find(c => c.id == props.treatment.cattle_id);
    let unitName = props.treatment.operating_unit || '';
    if (tCattle) {
        let herdName = tCattle.herd || '';
        if (!herdName && tCattle.location_block) {
            const unit = estates.find(u => 
                (u.pasture_blocks || u.blocks || []).some(b => b.name === tCattle.location_block)
            );
            if (unit) {
                herdName = unit.name;
            } else {
                herdName = tCattle.location_block;
            }
        }
        unitName = herdName || unitName;
    }

    form.value = {
        date: props.treatment.date ? new Date(props.treatment.date).toISOString().split('T')[0] : new Date().toISOString().split('T')[0],
        cattle_id: props.treatment.cattle_id || null,
        tag_no: tCattle?.tag_no || props.treatment.tag_no || '',
        category: tCattle?.category || props.treatment.category || '',
        operating_unit: unitName,
        colour: tCattle?.coat_colour || props.treatment.colour || props.treatment.cattle?.coat_colour || '',
        symptoms: props.treatment.symptoms || '',
        treatment_code: props.treatment.treatment_code || '',
        dosage: props.treatment.dosage || '',
        remarks: props.treatment.remarks || '',
        follow_up_required: props.treatment.follow_up_required || false,
        follow_up_date: props.treatment.follow_up_date ? new Date(props.treatment.follow_up_date).toISOString().split('T')[0] : '',
    };
    
    // Set search query to match the tag_no
    if (form.value.tag_no) {
        cattleSearchQuery.value = form.value.tag_no;
    }
}

const operatingUnitOptions = computed(() => {
    const estateNames = estates
        .map((estate) => (estate?.name || '').trim())
        .filter(Boolean);

    const cattleUnits = cattle
        .map((item) => (item?.herd || '').trim())
        .filter(Boolean);

    const selectedUnits = [
        (form.value.operating_unit || '').trim(),
        (props.treatment?.operating_unit || '').trim(),
        (props.treatment?.cattle?.herd || '').trim(),
    ].filter(Boolean);

    return [...new Set([...estateNames, ...cattleUnits, ...selectedUnits])];
});

const filteredCattle = computed(() => {
    const query = cattleSearchQuery.value.trim().toLowerCase();
    if (!query) return cattle;
    return cattle.filter((item) =>
        (item.tag_no || '').toLowerCase().includes(query) ||
        String(item.id || '').toLowerCase().includes(query) ||
        (item.category || '').toLowerCase().includes(query) ||
        (item.coat_colour || '').toLowerCase().includes(query)
    );
});

const derivedFieldClass = 'w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-gray-50 text-sm outline-none text-gray-600 cursor-not-allowed';

// Function to handle cattle selection change
const onCattleChange = () => {
    const selectedCattle = cattle.find(c => c.id == form.value.cattle_id);
    if (selectedCattle) {
        form.value.tag_no = selectedCattle.tag_no || '';
        form.value.category = selectedCattle.category || '';
        form.value.colour = selectedCattle.coat_colour || '';
        
        let unitName = selectedCattle.herd || '';
        if (!unitName && selectedCattle.location_block) {
            const unit = estates.find(u => 
                (u.pasture_blocks || u.pastureBlocks || u.blocks || []).some(b => b.name === selectedCattle.location_block)
            );
            if (unit) {
                unitName = unit.name;
            } else {
                unitName = selectedCattle.location_block;
            }
        }
        form.value.operating_unit = unitName;
    } else {
        form.value.tag_no = '';
        form.value.category = '';
        form.value.colour = '';
        form.value.operating_unit = '';
    }
};

const selectCattleItem = (item) => {
    form.value.cattle_id = item.id;
    onCattleChange();
    showCattleDropdown.value = false;
    cattleSearchQuery.value = '';
};

const clearCattleSelection = () => {
    form.value.cattle_id = null;
    form.value.tag_no = '';
    form.value.category = '';
    form.value.colour = '';
        form.value.operating_unit = '';
};

const saveTreatment = () => {
    // Validate required fields
    if (!form.value.cattle_id) {
        alert('Please select a cattle');
        return;
    }
    if (!form.value.date) {
        alert('Please select a date');
        return;
    }
    if (!form.value.symptoms) {
        alert('Please enter symptoms observed');
        return;
    }
    if (!form.value.dosage) {
        alert('Please enter dosage');
        return;
    }

    saving.value = true;
    
    // Prepare data for submission
    const data = {
        cattle_id: form.value.cattle_id,
        tag_no: form.value.tag_no,
        category: form.value.category,
        operating_unit: form.value.operating_unit,
        colour: form.value.colour,
        date: form.value.date,
        symptoms: form.value.symptoms,
        treatment: form.value.treatment_code || 'General Treatment',
        treatment_code: form.value.treatment_code,
        dosage: form.value.dosage,
        remarks: form.value.remarks,
        follow_up_required: form.value.follow_up_required,
        follow_up_date: form.value.follow_up_required ? form.value.follow_up_date : null,
    };

    if (isEditing.value) {
        router.put(`/health/treatment/${props.treatment.id}`, data, {
            onSuccess: () => {
                saving.value = false;
                router.visit('/health/treatment');
            },
            onError: (errors) => {
                saving.value = false;
                const errorMessages = Object.values(errors).join('\n');
                alert('Error updating treatment:\n' + errorMessages);
            }
        });
    } else {
        router.post('/health/treatment', data, {
            onSuccess: () => {
                saving.value = false;
                // Redirect happens automatically via Inertia after successful store
            },
            onError: (errors) => {
                saving.value = false;
                console.error('Validation errors:', errors);
                const errorMessages = Object.values(errors).flat().join('\n');
                alert('Error saving treatment:\n' + errorMessages);
            },
            onFinish: () => {
                saving.value = false;
            }
        });
    }
};

const cancel = () => {
    if (confirm('Are you sure you want to cancel? Any unsaved changes will be lost.')) {
        router.visit('/health/treatment');
    }
};

// Add new treatment code functionality
const showCodeManagerModal = ref(false);
const newCodeData = ref({
    code: ''
});
const editingCodeId = ref(null);
const editingCodeValue = ref('');
const codeActionLoading = ref(false);
const codeManagerFeedback = ref('');
const codeManagerError = ref(false);

const setCodeFeedback = (message, isError = false) => {
    codeManagerFeedback.value = message;
    codeManagerError.value = isError;
};

const resetNewCodeData = () => {
    newCodeData.value = { code: '' };
};

const cancelCodeEdit = () => {
    editingCodeId.value = null;
    editingCodeValue.value = '';
};

const startEditCode = (code) => {
    editingCodeId.value = code.id;
    editingCodeValue.value = code.code;
};

const addNewTreatmentCode = async () => {
    if (!newCodeData.value.code.trim() || codeActionLoading.value) return;
    
    try {
        codeActionLoading.value = true;
        setCodeFeedback('');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        if (!csrfToken) {
            setCodeFeedback('Session expired. Please refresh the page and try again.', true);
            return;
        }
        
        const response = await fetch('/health/treatment-codes', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                code: newCodeData.value.code,
                label: newCodeData.value.code,
                description: '',
                is_active: true
            })
        });

        if (response.ok) {
            const payload = await response.json();
            if (payload?.data) {
                treatmentCodes.value.push(payload.data);
                treatmentCodes.value.sort((a, b) => String(a.label || '').localeCompare(String(b.label || '')));
            }
            resetNewCodeData();
            setCodeFeedback('Treatment code added successfully.');
        } else {
            if (response.status === 422) {
                setCodeFeedback('This treatment code already exists. Use a different code.', true);
            } else {
                setCodeFeedback('Unable to add treatment code. Please try again.', true);
            }
        }
    } catch (error) {
        console.error('Error:', error);
        setCodeFeedback('Unable to add treatment code right now. Please try again.', true);
    } finally {
        codeActionLoading.value = false;
    }
};

const updateTreatmentCode = async (codeId) => {
    if (!editingCodeValue.value.trim() || codeActionLoading.value) return;
    
    try {
        codeActionLoading.value = true;
        setCodeFeedback('');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        if (!csrfToken) {
            setCodeFeedback('Session expired. Please refresh the page and try again.', true);
            return;
        }
        
        const response = await fetch(`/health/treatment-codes/${codeId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                code: editingCodeValue.value,
                label: editingCodeValue.value
            })
        });

        if (response.ok) {
            const payload = await response.json();
            if (payload?.data) {
                const index = treatmentCodes.value.findIndex(item => item.id === payload.data.id);
                if (index !== -1) {
                    treatmentCodes.value[index] = payload.data;
                }
                treatmentCodes.value.sort((a, b) => String(a.label || '').localeCompare(String(b.label || '')));
            }
            cancelCodeEdit();
            setCodeFeedback('Treatment code updated successfully.');
        } else {
            if (response.status === 422) {
                setCodeFeedback('This treatment code already exists. Use a different code.', true);
            } else {
                setCodeFeedback('Unable to update treatment code. Please try again.', true);
            }
        }
    } catch (error) {
        console.error('Error:', error);
        setCodeFeedback('Unable to update treatment code right now. Please try again.', true);
    } finally {
        codeActionLoading.value = false;
    }
};

const deleteTreatmentCode = async (codeId) => {
    if (!confirm('Are you sure you want to delete this treatment code?') || codeActionLoading.value) return;
    
    try {
        codeActionLoading.value = true;
        setCodeFeedback('');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        if (!csrfToken) {
            setCodeFeedback('Session expired. Please refresh the page and try again.', true);
            return;
        }
        
        const response = await fetch(`/health/treatment-codes/${codeId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        });

        if (response.ok) {
            treatmentCodes.value = treatmentCodes.value.filter(item => item.id !== codeId);
            setCodeFeedback('Treatment code deleted successfully.');
        } else {
            if (response.status === 422) {
                const error = await response.json();
                setCodeFeedback(error.message || 'This treatment code cannot be deleted because it is in use.', true);
            } else {
                setCodeFeedback('Unable to delete treatment code. Please try again.', true);
            }
        }
    } catch (error) {
        console.error('Error:', error);
        setCodeFeedback('Unable to delete treatment code right now. Please try again.', true);
    } finally {
        codeActionLoading.value = false;
    }
};

const toggleCodeActive = async (code) => {
    if (codeActionLoading.value) return;
    try {
        codeActionLoading.value = true;
        setCodeFeedback('');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        if (!csrfToken) {
            setCodeFeedback('Session expired. Please refresh the page and try again.', true);
            return;
        }
        
        const response = await fetch(`/health/treatment-codes/${code.id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                is_active: !code.is_active
            })
        });

        if (response.ok) {
            const payload = await response.json();
            if (payload?.data) {
                const index = treatmentCodes.value.findIndex(item => item.id === payload.data.id);
                if (index !== -1) {
                    treatmentCodes.value[index] = payload.data;
                }
            }
        } else {
            setCodeFeedback('Unable to update treatment code status. Please try again.', true);
        }
    } catch (error) {
        console.error('Error:', error);
        setCodeFeedback('Unable to update treatment code status right now. Please try again.', true);
    } finally {
        codeActionLoading.value = false;
    }
};
</script>

<template>
    <div class="w-full max-w-6xl mx-auto">
        <div class="mb-8 flex justify-between items-center">
            <div class="flex items-center gap-4">
                <button @click="cancel" class="w-10 h-10 flex items-center justify-center rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors">
                    <ArrowLeft class="w-5 h-5 text-gray-600" />
                </button>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">{{ isEditing ? 'Edit treatment' : 'Record treatment' }}</h1>
                    <p class="text-sm text-gray-500 mt-1">{{ isEditing ? 'Update treatment record for cattle' : 'Add new treatment record for cattle' }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <button type="button" @click="cancel" class="px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
                <button type="button" @click="saveTreatment" :disabled="saving" class="flex items-center gap-2 bg-[#34554a] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#2a443b] shadow-sm transition-colors disabled:opacity-50">
                    <Save class="w-4 h-4" />
                    {{ saving ? 'Saving...' : 'Save record' }}
                </button>
            </div>
        </div>

        <form @submit.prevent>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
                <div class="px-6 py-4 bg-[#34554a] border-b border-gray-100">
                    <h3 class="text-sm font-bold text-white">Treatment information</h3>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Select cattle *</label>
                        <div class="relative">
                            <div
                                @click="showCattleDropdown = !showCattleDropdown"
                                class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a] cursor-pointer flex items-center justify-between"
                            >
                                <span :class="form.cattle_id ? 'text-gray-900' : 'text-gray-400'">
                                    {{ form.cattle_id ? `${form.tag_no} - ${form.category} - ${form.colour}` : 'Search and select cattle...' }}
                                </span>
                                <div class="flex items-center gap-2">
                                    <button
                                        v-if="form.cattle_id"
                                        @click.stop="clearCattleSelection"
                                        class="text-gray-400 hover:text-red-500"
                                        type="button"
                                    >
                                        <X class="w-4 h-4" />
                                    </button>
                                    <ChevronDown class="w-4 h-4 text-gray-400" />
                                </div>
                            </div>

                            <div
                                v-if="showCattleDropdown"
                                class="absolute z-20 left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-xl max-h-96 overflow-hidden"
                            >
                                <div class="p-3 border-b border-gray-100 bg-gray-50">
                                    <div class="relative">
                                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 w-4 h-4" />
                                        <input
                                            v-model="cattleSearchQuery"
                                            type="text"
                                            placeholder="Search by tag no, cattle ID, category, or colour..."
                                            class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-200 bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]"
                                            @click.stop
                                        />
                                    </div>
                                    <div class="text-xs text-gray-500 mt-2">{{ filteredCattle.length }} cattle found</div>
                                </div>

                                <div class="overflow-y-auto max-h-56">
                                    <div v-if="filteredCattle.length === 0" class="p-4 text-center text-gray-500 text-sm">
                                        No cattle found matching "{{ cattleSearchQuery }}"
                                    </div>
                                    <div
                                        v-for="item in filteredCattle"
                                        :key="item.id"
                                        @click.stop="selectCattleItem(item)"
                                        class="px-4 py-3 hover:bg-gray-50 cursor-pointer border-b border-gray-100 last:border-0"
                                        :class="{ 'bg-[#34554a]/10': form.cattle_id == item.id }"
                                    >
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <p class="font-medium text-gray-900">{{ item.tag_no }}</p>
                                                <p class="text-xs text-gray-500">ID: {{ item.id }} | {{ item.category }} | {{ item.coat_colour }}</p>
                                            </div>
                                            <div v-if="form.cattle_id == item.id" class="text-[#34554a]">
                                                <Check class="w-5 h-5" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Date *</label>
                        <input v-model="form.date" type="date" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Operating unit *</label>
                        <select v-model="form.operating_unit" disabled :class="derivedFieldClass">
                            <option value="">Select operating unit</option>
                            <option v-for="unit in operatingUnitOptions" :key="unit" :value="unit">
                                {{ unit }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Cattle id</label>
                        <input :value="form.cattle_id || 'Not selected'" type="text" readonly class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-gray-50 text-sm outline-none text-gray-600">
                    </div>
<div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Tag number</label>
                        <input v-model="form.tag_no" type="text" readonly :class="derivedFieldClass" placeholder="Enter tag number">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Category</label>
                        <input v-model="form.category" type="text" readonly :class="derivedFieldClass" placeholder="Enter category">
                    </div>
<div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Coat Colour</label>
                        <input v-model="form.colour" type="text" readonly :class="derivedFieldClass" placeholder="Enter coat colour">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Symptoms observed *</label>
                        <textarea v-model="form.symptoms" rows="2" placeholder="Describe the symptoms observed" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]"></textarea>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
                <div class="px-6 py-4 bg-[#34554a] border-b border-gray-100">
                    <h3 class="text-sm font-bold text-white">Treatment details</h3>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-gray-500 mb-1 flex items-center justify-between">
                            <span>Treatment code</span>
                            <button @click="showCodeManagerModal = true" type="button" class="text-xs px-2 py-1 bg-gray-100 text-gray-700 rounded hover:bg-gray-200 transition-colors flex items-center gap-1" v-if="page.props.auth?.user?.role === 'admin' || page.props.auth?.user?.role === 'manager'">
                                <Settings class="w-3 h-3" />
                                Manage
                            </button>
                        </label>
                        <select v-model="form.treatment_code" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]">
                            <option value="">Select treatment</option>
                            <option v-for="code in treatmentCodes.filter(c => c.is_active !== false)" :key="code.id" :value="code.code">{{ code.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Dosage *</label>
                        <input v-model="form.dosage" type="text" placeholder="e.g., OTC 12ml + Daxa 10ml" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Additional remarks</label>
                        <textarea v-model="form.remarks" rows="2" placeholder="Any additional notes" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]"></textarea>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
                <div class="px-6 py-4 bg-[#34554a] border-b border-gray-100">
                    <h3 class="text-sm font-bold text-white">Follow-up information</h3>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="flex items-center gap-3">
                        <input v-model="form.follow_up_required" type="checkbox" id="follow_up" class="rounded border-gray-300 text-[#34554a] focus:ring-[#34554a] cursor-pointer">
                        <label for="follow_up" class="text-sm text-gray-700 cursor-pointer">Follow-up required</label>
                    </div>
                    <div v-if="form.follow_up_required">
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Follow-up date</label>
                        <input v-model="form.follow_up_date" type="date" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]">
                    </div>
                </div>
            </div>

            <div v-if="showCodeManagerModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center p-4 z-50" @click.self="showCodeManagerModal = false; cancelCodeEdit()">
                <div class="bg-white rounded-xl shadow-xl max-w-lg w-full overflow-hidden flex flex-col max-h-[80vh]">
                    <div class="flex justify-between items-center p-5 border-b bg-gray-50">
                        <h3 class="text-lg font-bold text-gray-900">Manage treatment codes</h3>
                        <button @click="showCodeManagerModal = false; cancelCodeEdit()" class="text-gray-400 hover:text-gray-600 transition-colors">
                            <X class="w-5 h-5" />
                        </button>
                    </div>
                    <div class="p-5 overflow-y-auto">
                        <h4 class="text-xs font-bold text-gray-500 mb-3">Treatment codes</h4>
                        <div v-if="codeManagerFeedback" class="mb-4 px-3 py-2 rounded-lg text-sm" :class="codeManagerError ? 'bg-red-50 text-red-700 border border-red-100' : 'bg-green-50 text-green-700 border border-green-100'">
                            {{ codeManagerFeedback }}
                        </div>
                        <div class="space-y-2 mb-6">
                            <div v-if="treatmentCodes.length === 0" class="text-sm text-gray-500 text-center py-4">
                                No treatment codes yet. Add one below.
                            </div>
                            <div v-for="code in treatmentCodes" :key="code.id" class="flex items-center justify-between p-3 border rounded-lg hover:bg-gray-50 group transition-colors bg-white shadow-sm">
                                <div class="flex items-center gap-3 flex-1">
                                    <input type="checkbox" :checked="code.is_active !== false" @change="toggleCodeActive(code)" class="w-4 h-4 text-[#34554a] rounded focus:ring-[#34554a] cursor-pointer" title="Toggle active/inactive">
                                    <template v-if="editingCodeId === code.id">
                                        <input v-model="editingCodeValue" type="text" class="flex-1 px-2 py-1 border rounded text-sm focus:ring-1 focus:ring-[#34554a] outline-none" @keyup.enter="updateTreatmentCode(code.id)" @keyup.escape="cancelCodeEdit">
                                    </template>
                                    <template v-else>
                                        <span class="text-sm font-medium" :class="code.is_active === false ? 'text-gray-400 line-through' : 'text-gray-700'">
                                            {{ code.code }}
                                        </span>
                                    </template>
                                </div>
                                <div class="flex gap-2">
                                    <template v-if="editingCodeId === code.id">
                                        <button @click="updateTreatmentCode(code.id)" class="text-green-500 hover:text-green-700 transition-colors text-xs px-2 py-1 bg-green-50 rounded">
                                            Save
                                        </button>
                                        <button @click="cancelCodeEdit" class="text-gray-500 hover:text-gray-700 transition-colors text-xs px-2 py-1 bg-gray-100 rounded">
                                            Cancel
                                        </button>
                                    </template>
                                    <template v-else>
                                        <button @click="startEditCode(code)" class="text-blue-400 hover:text-blue-600 transition-colors">
                                            <Pencil class="w-4 h-4" />
                                        </button>
                                        <button @click="deleteTreatmentCode(code.id)" class="text-red-400 hover:text-red-600 transition-colors">
                                            <Trash2 class="w-4 h-4" />
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                            <h4 class="text-xs font-bold text-gray-800 mb-3">Add new code</h4>
                            <div class="flex gap-2">
                                <input v-model="newCodeData.code" type="text" class="flex-1 px-3 py-2 border rounded-lg text-sm focus:ring-1 focus:ring-[#34554a] outline-none shadow-sm" placeholder="Enter treatment code (e.g., OTC, VIT)" maxlength="20" @keyup.enter="addNewTreatmentCode">
                                <button @click="addNewTreatmentCode" :disabled="!newCodeData.code.trim() || codeActionLoading" class="px-4 py-2 bg-[#34554a] text-white rounded-lg text-sm font-bold hover:bg-opacity-90 shadow-md transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                                    {{ codeActionLoading ? 'Saving...' : 'Add' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</template>

