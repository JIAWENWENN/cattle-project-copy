<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { Plus, Save, ArrowLeft, Check, AlertTriangle, X, Trash2, Search, ChevronDown } from 'lucide-vue-next';

defineOptions({
    layout: (h, page) => h(AppLayout, { title: 'Add Calving Checklist', parent: 'Calving Checklist', parentUrl: '/calving-checklist' }, () => page)
});

const props = defineProps({
    monthYear: String,
    operatingUnit: String,
    operatingUnits: {
        type: Array,
        default: () => []
    },
    operatingUnitsWithStructure: {
        type: Array,
        default: () => []
    },
    allBlocks: {
        type: Array,
        default: () => []
    },
    allPhases: {
        type: Array,
        default: () => []
    },
    cattle: {
        type: Array,
        default: () => []
    },
    calvingRecords: {
        type: Object,
        default: () => ({}) // keyed by calf tag_no: { lcc_running_number, dam_tag_no, sire_tag_no, ... }
    },
    customFields: {
        type: Object,
        default: () => ({})
    }
});

const page = usePage();

const toTitleCase = (value) => {
    if (!value) return '';
    return String(value)
        .toLowerCase()
        .split(' ')
        .filter(Boolean)
        .map(word => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
};

const matchLocationOption = (options, value) => {
    if (!value) return '';
    const normalized = String(value).trim().toLowerCase();
    const found = (options || []).find((option) => String(option).trim().toLowerCase() === normalized);
    return found || value;
};

// Convert "Sept/2024" format to "2024-09" for month input
const convertMonthYearToInputFormat = (monthYearStr) => {
    if (!monthYearStr) return '';
    
    // If already in YYYY-MM format, return as is
    if (monthYearStr.match(/^\d{4}-\d{2}$/)) {
        return monthYearStr;
    }
    
    // Convert "Sept/2024" to "2024-09"
    const monthMap = {
        'Jan': '01', 'Feb': '02', 'Mar': '03', 'Apr': '04', 'May': '05', 'Jun': '06',
        'Jul': '07', 'Aug': '08', 'Sept': '09', 'Oct': '10', 'Nov': '11', 'Dec': '12'
    };
    
    const parts = monthYearStr.split('/');
    if (parts.length === 2) {
        const month = monthMap[parts[0]];
        const year = parts[1];
        if (month && year) {
            return `${year}-${month}`;
        }
    }
    
    return monthYearStr;
};

// Convert "2024-09" back to "Sept/2024" for storage
const convertMonthYearToStorageFormat = (inputFormat) => {
    if (!inputFormat) return '';
    
    // If already in Mmm/YYYY format, return as is
    if (inputFormat.match(/^[A-Za-z]{3,4}\/\d{4}$/)) {
        return inputFormat;
    }
    
    // Convert "2024-09" to "Sept/2024"
    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sept', 'Oct', 'Nov', 'Dec'];
    const parts = inputFormat.split('-');
    if (parts.length === 2) {
        const year = parts[0];
        const monthIndex = parseInt(parts[1]) - 1;
        if (monthIndex >= 0 && monthIndex < 12) {
            return `${monthNames[monthIndex]}/${year}`;
        }
    }
    
    return inputFormat;
};

const displayMonthYear = computed(() => props.monthYear || 'Sept/2024');
// Operating unit comes from the selected cattle record, not a default list value
const displayOperatingUnit = computed(() => props.operatingUnit || '');

const form = useForm({
    month_year: convertMonthYearToStorageFormat(displayMonthYear.value),
    operating_unit: displayOperatingUnit.value,
    week: '',
    calving_date: '',
    tag_no: '',
    sex: 'MC',
    colour: '',
    dam_tag_no: '',
    dam_colour: '',
    sire_tag_no: '',
    sire_colour: '',
    times_of_pregnancy: '',
    location: '',
    location_block: '',
    location_phase: '',
    general_condition: 'Good',
    treatment_iodine: false,
    treatment_woundsarex: false,
    colostrum_feeding_24h: false,
    mamumune: false,
    tagging_checklist_date: '',
    lcc_running_number: '',
    remarks: ''
});

const weeks = [1, 2, 3, 4, 5];
const colours = computed(() => {
    const options = props.customFields?.coat_colour || [];
    if (options.length > 0) {
        return options.map(opt => opt.value);
    }
    return ['Red', 'Honey', 'Grey', 'Black', 'Brown', 'White', 'Spotted', 'Chestnut', 'Tan', 'Brindle', 'Stripe', 'Other'];
});
const treatmentIodineWoundsarex = ref(false);

watch(() => form.operating_unit, (unit) => {
    if (unit) {
        form.location = unit;
    }
}, { immediate: true });

const getWeekFromCalvingDate = (dateValue) => {
    if (!dateValue) return '';
    const date = new Date(dateValue);
    if (Number.isNaN(date.getTime())) return '';
    const day = date.getDate();
    return Math.min(5, Math.max(1, Math.ceil(day / 7)));
};

watch(() => form.calving_date, (value) => {
    form.week = getWeekFromCalvingDate(value);
}, { immediate: true });

watch(treatmentIodineWoundsarex, (value) => {
    form.treatment_iodine = value;
    form.treatment_woundsarex = value;
}, { immediate: true });

// Searchable dropdown state for cattle selection
const showCattleDropdown = ref(false);
const cattleSearchQuery = ref('');
const selectedCattleId = ref(null);
const hasAutoFilledFields = computed(() => Boolean(selectedCattleId.value));

const findCattleByTag = (tag) => {
    if (!tag) return null;
    const normalized = String(tag).trim().toLowerCase();
    return (props.cattle || []).find(
        (item) => String(item.tag_no || '').trim().toLowerCase() === normalized
    ) || null;
};

const resolveOperatingUnitFromCattle = (cattle) => {
    let unit = String(cattle?.operating_unit || '').trim();
    if (unit) {
        return unit;
    }

    const blockName = String(cattle?.location_block || '').trim();
    if (blockName && props.operatingUnitsWithStructure?.length) {
        const estate = props.operatingUnitsWithStructure.find((entry) =>
            (entry.blocks || []).some(
                (block) => String(block.name || '').trim().toLowerCase() === blockName.toLowerCase()
            )
        );
        if (estate?.name) {
            return estate.name;
        }
    }

    return blockName;
};

const mapGeneralCondition = (value) => {
    const raw = String(value || '').trim();
    if (!raw) return 'Good';
    const lower = raw.toLowerCase();
    if (lower === 'normal') return 'Good';
    if (['good', 'fair', 'poor'].includes(lower)) {
        return lower.charAt(0).toUpperCase() + lower.slice(1);
    }
    return raw;
};

const normalizeDateForInput = (value) => {
    if (!value) return '';
    const raw = String(value).trim();
    if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
        return raw;
    }
    if (/^\d{4}-\d{2}-\d{2}T/.test(raw)) {
        const dt = new Date(raw);
        if (!Number.isNaN(dt.getTime())) {
            return `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, '0')}-${String(dt.getDate()).padStart(2, '0')}`;
        }
    }
    const date = new Date(raw);
    if (!Number.isNaN(date.getTime())) {
        return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    }
    return '';
};

const selectedCattleLabel = computed(() => {
    if (!selectedCattleId.value) return '';
    const cattle = props.cattle.find((item) => item.id === selectedCattleId.value);
    if (!cattle) return form.tag_no || '';
    const parts = [cattle.tag_no, cattle.category, cattle.coat_colour].filter(Boolean);
    return parts.join(' - ');
});

// Filter cattle based on search query (health record pattern)
const filteredCattle = computed(() => {
    const query = cattleSearchQuery.value.trim().toLowerCase();
    if (!query) return props.cattle;
    return props.cattle.filter((item) =>
        (item.tag_no || '').toLowerCase().includes(query) ||
        String(item.id || '').toLowerCase().includes(query) ||
        (item.category || '').toLowerCase().includes(query) ||
        (item.coat_colour || '').toLowerCase().includes(query)
    );
});

const pickFirstNonEmpty = (...values) => {
    for (const value of values) {
        const trimmed = String(value ?? '').trim();
        if (trimmed) return trimmed;
    }
    return '';
};

// Handle cattle selection - auto-fill all fields from cattle record
const normalizeColour = (colour) => {
    if (!colour) return '';
    const trimmed = String(colour).trim();
    const available = colours.value;
    const exactMatch = available.find(c => c.toLowerCase() === trimmed.toLowerCase());
    if (exactMatch) return exactMatch;
    return trimmed;
};

const getCalvingRecordForTag = (tag) => {
    if (!tag) return null;
    const rec = props.calvingRecords?.[tag];
    if (!rec || typeof rec !== 'object') return null;
    return rec;
};

const resolveGenealogyFromCattle = (cattle) => {
    const calvingRec = getCalvingRecordForTag(cattle?.tag_no);
    const damTag = pickFirstNonEmpty(cattle?.dam_tag, calvingRec?.dam_tag_no);
    const sireTag = pickFirstNonEmpty(cattle?.sire_tag, calvingRec?.sire_tag_no);

    const damRecord = findCattleByTag(damTag);
    const sireRecord = findCattleByTag(sireTag);

    return {
        damRecord,
        sireRecord,
        dam_tag_no: pickFirstNonEmpty(damTag, damRecord?.tag_no),
        dam_colour: pickFirstNonEmpty(cattle?.dam_colour, calvingRec?.dam_colour, damRecord?.coat_colour, damRecord?.dam_colour),
        sire_tag_no: pickFirstNonEmpty(sireTag, sireRecord?.tag_no),
        sire_colour: pickFirstNonEmpty(cattle?.sire_coat_colour, calvingRec?.sire_colour, sireRecord?.sire_coat_colour, sireRecord?.coat_colour),
    };
};

const applyCattleToForm = (cattle) => {
    if (!cattle) return;

    const genealogy = resolveGenealogyFromCattle(cattle);

    selectedCattleId.value = cattle.id;
    selectedDamId.value = genealogy.damRecord?.id || null;

    form.tag_no = cattle.tag_no || '';
    form.colour = normalizeColour(cattle.coat_colour);
    form.sex = cattle.gender === 'Female' ? 'FC' : 'MC';
    form.dam_tag_no = genealogy.dam_tag_no;
    form.dam_colour = genealogy.dam_colour;
    form.sire_tag_no = genealogy.sire_tag_no;
    form.sire_colour = genealogy.sire_colour;

    const resolvedUnit = resolveOperatingUnitFromCattle(cattle);
    form.operating_unit = resolvedUnit;
    form.location = resolvedUnit;
    form.location_block = matchLocationOption(props.allBlocks, cattle.location_block);
    form.location_phase = matchLocationOption(props.allPhases, cattle.location_phase);

    const calvingRec = getCalvingRecordForTag(cattle.tag_no);
    form.lcc_running_number = pickFirstNonEmpty(calvingRec?.lcc_running_number, cattle.lcc_running_number);
    form.general_condition = mapGeneralCondition(cattle.general_condition);

    if (cattle.birth_date && !form.calving_date) {
        form.calving_date = normalizeDateForInput(cattle.birth_date);
    }
};

const selectCattle = (cattle) => {
    applyCattleToForm(cattle);
    showCattleDropdown.value = false;
    cattleSearchQuery.value = '';
};

// Clear selected cattle
const clearCattleSelection = () => {
    selectedCattleId.value = null;
    selectedDamId.value = null;
    form.tag_no = '';
    form.colour = '';
    form.dam_tag_no = '';
    form.dam_colour = '';
    form.sire_tag_no = '';
    form.sire_colour = '';
    form.operating_unit = displayOperatingUnit.value;
    form.location = '';
    form.location_block = '';
    form.location_phase = '';
    form.lcc_running_number = '';
    form.sex = 'MC';
    form.general_condition = 'Good';
};

const selectedDamId = ref(null);

const submitForm = () => {
    // Convert month_year from YYYY-MM to Mmm/YYYY format before submitting
    form.month_year = convertMonthYearToStorageFormat(form.month_year);
    form.treatment_iodine = treatmentIodineWoundsarex.value;
    form.treatment_woundsarex = treatmentIodineWoundsarex.value;
    
    form.post('/calving-checklist', {
        onSuccess: () => {
            router.visit('/calving-checklist', { replace: true });
        },
        onError: (errors) => {
            console.error('Validation errors:', errors);
        }
    });
};

const goBack = () => {
    router.visit('/calving-checklist', { replace: true });
};

// Close dropdown when clicking outside
const closeDropdownOnClickOutside = (event) => {
    const dropdown = event.target.closest('.relative');
    if (!dropdown && showCattleDropdown.value) {
        showCattleDropdown.value = false;
    }
};

// Add click event listener when component mounts
if (typeof window !== 'undefined') {
    window.addEventListener('click', closeDropdownOnClickOutside);
}
</script>

<template>
    <div class="w-full max-w-6xl mx-auto">
        <!-- Flash Messages -->
        <div v-if="page.props.flash && page.props.flash.success" class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm flex items-center justify-between">
            <div class="flex items-center gap-2">
                <Check class="w-4 h-4" />
                {{ page.props.flash.success }}
            </div>
            <button @click="page.props.flash ? page.props.flash.success = null : null"><X class="w-4 h-4 opacity-50 hover:opacity-100" /></button>
        </div>

        <div v-if="page.props.flash && page.props.flash.error" class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm flex items-center justify-between">
            <div class="flex items-center gap-2">
                <AlertTriangle class="w-4 h-4" />
                {{ page.props.flash.error }}
            </div>
            <button @click="page.props.flash ? page.props.flash.error = null : null"><X class="w-4 h-4 opacity-50 hover:opacity-100" /></button>
        </div>

        <div class="mb-8 flex justify-between items-center">
            <div class="flex items-center gap-4">
                <button @click="goBack" class="w-10 h-10 flex items-center justify-center rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors">
                    <ArrowLeft class="w-5 h-5 text-gray-600" />
                </button>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Monthly calving record</h1>
                    <p class="text-sm text-gray-500 mt-1">Record calving details</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <button type="button" @click="goBack" class="px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
                <button type="submit" @click="submitForm" class="flex items-center gap-2 bg-[#34554a] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#2a443b] shadow-sm transition-colors disabled:opacity-50" :disabled="form.processing">
                    <Save class="w-4 h-4" />
                    {{ form.processing ? 'Saving...' : 'Save record' }}
                </button>
            </div>
        </div>

        <div v-if="Object.keys(form.errors).length > 0" class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
            <p class="font-bold flex items-center gap-2 mb-1">
                <AlertTriangle class="w-4 h-4" />
                Please correct the following errors:
            </p>
            <ul class="list-disc list-inside">
                <li v-for="(error, field) in form.errors" :key="field">{{ error }}</li>
            </ul>
        </div>

        <form @submit.prevent="submitForm">
            <!-- Form Scope -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
                <div class="px-6 py-4 bg-[#34554a] border-b border-gray-100">
                    <h3 class="text-sm font-bold text-white tracking-wide">Form scope</h3>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-1 gap-6">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Operating Unit *</label>
                        <input
                            type="text"
                            :value="form.operating_unit ? toTitleCase(form.operating_unit) : ''"
                            readonly
                            placeholder="Select calf below to load from cattle record"
                            class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-gray-50 text-sm cursor-not-allowed"
                        />
                    </div>
                </div>
            </div>

            <!-- Calving Details -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-6">
                <div class="px-6 py-4 bg-[#34554a] border-b border-gray-100">
                    <h3 class="text-sm font-bold text-white tracking-wide">Calf details</h3>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div class="relative md:col-span-4">
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Tag (Calf) *</label>
                        <div
                            @click="showCattleDropdown = !showCattleDropdown"
                            class="w-full px-4 py-2.5 border rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a] cursor-pointer flex items-center justify-between"
                            :class="form.errors.tag_no ? 'border-red-500' : 'border-gray-200'"
                        >
                            <span :class="selectedCattleId ? 'text-gray-900' : 'text-gray-400'">
                                {{ selectedCattleId ? selectedCattleLabel : 'Search and select cattle by tag no...' }}
                            </span>
                            <div class="flex items-center gap-2">
                                <button
                                    v-if="selectedCattleId"
                                    type="button"
                                    @click.stop="clearCattleSelection"
                                    class="text-gray-400 hover:text-red-500"
                                >
                                    <X class="w-4 h-4" />
                                </button>
                                <ChevronDown class="w-4 h-4 text-gray-400" />
                            </div>
                        </div>

                        <div
                            v-if="showCattleDropdown"
                            class="absolute z-[100] left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-xl max-h-96 overflow-hidden"
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
                                    v-for="cattleItem in filteredCattle"
                                    :key="cattleItem.id"
                                    @click.stop="selectCattle(cattleItem)"
                                    class="px-4 py-3 hover:bg-gray-50 cursor-pointer border-b border-gray-100 last:border-0"
                                    :class="{ 'bg-[#34554a]/10': selectedCattleId === cattleItem.id }"
                                >
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="font-medium text-gray-900">{{ cattleItem.tag_no }}</p>
                                            <p class="text-xs text-gray-500">ID: {{ cattleItem.id }} | {{ cattleItem.category || '-' }} | {{ cattleItem.coat_colour || '-' }}</p>
                                        </div>
                                        <div v-if="selectedCattleId === cattleItem.id" class="text-[#34554a]">
                                            <Check class="w-5 h-5" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <p v-if="form.errors.tag_no" class="text-xs text-red-500 mt-1">{{ form.errors.tag_no }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Week</label>
                        <select v-model="form.week" disabled class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-gray-50 text-sm outline-none cursor-not-allowed">
                            <option value="">Select Week</option>
                            <option v-for="w in weeks" :key="w" :value="w">Week {{ w }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Calving Date</label>
                        <input type="date" v-model="form.calving_date" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                    </div>
                    <div class="relative md:col-span-2">
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Tag No. (Dam) *</label>
                        <input
                            type="text"
                            v-model="form.dam_tag_no"
                            readonly
                            class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-gray-50 text-sm cursor-not-allowed"
                        />
                        <p v-if="form.errors.dam_tag_no" class="text-xs text-red-500 mt-1">{{ form.errors.dam_tag_no }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Tag No. (Sire)</label>
                        <input
                            type="text"
                            v-model="form.sire_tag_no"
                            readonly
                            class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-gray-50 text-sm cursor-not-allowed"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Dam Colour</label>
                        <input
                            type="text"
                            v-model="form.dam_colour"
                            readonly
                            class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-gray-50 text-sm cursor-not-allowed"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Sire Colour</label>
                        <input
                            type="text"
                            v-model="form.sire_colour"
                            readonly
                            class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-gray-50 text-sm cursor-not-allowed"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Sex *</label>
                        <select v-model="form.sex" disabled class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-gray-50 text-sm cursor-not-allowed">
                            <option value="MC">MC (Male Calf)</option>
                            <option value="FC">FC (Female Calf)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Colour *</label>
                        <select v-model="form.colour" disabled class="w-full px-4 py-2.5 border rounded-lg bg-gray-50 text-sm cursor-not-allowed" :class="form.errors.colour ? 'border-red-500' : 'border-gray-200'" required>
                            <option value="">Select Colour</option>
                            <option
                                v-if="form.colour && !colours.includes(form.colour)"
                                :value="form.colour"
                            >
                                {{ form.colour }}
                            </option>
                            <option v-for="c in colours" :key="c" :value="c">{{ c }}</option>
                        </select>
                        <p v-if="form.errors.colour" class="text-xs text-red-500 mt-1">{{ form.errors.colour }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Times of Pregnancy</label>
                        <input type="text" v-model="form.times_of_pregnancy" placeholder="e.g., 2" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Condition</label>
                        <select v-model="form.general_condition" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]">
                            <option value="Good">Good</option>
                            <option value="Fair">Fair</option>
                            <option value="Poor">Poor</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Location (aligned with Calving Record / Cattle Directory) -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
                <div class="px-6 py-4 bg-[#34554a] border-b border-gray-100">
                    <h3 class="text-sm font-bold text-white tracking-wide">Location</h3>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Operating Unit</label>
                        <input type="text" :value="form.operating_unit" readonly class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-gray-100 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Block</label>
                        <input
                            type="text"
                            :value="form.location_block"
                            readonly
                            class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-gray-50 text-sm cursor-not-allowed"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Phase</label>
                        <input
                            type="text"
                            :value="form.location_phase"
                            readonly
                            class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-gray-50 text-sm cursor-not-allowed"
                        />
                    </div>
                </div>
            </div>

            <!-- Treatments -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
                <div class="px-6 py-4 bg-[#34554a] border-b border-gray-100">
                    <h3 class="text-sm font-bold text-white tracking-wide">Treatments & care</h3>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <label class="flex items-center gap-3 p-4 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                        <input type="checkbox" v-model="treatmentIodineWoundsarex" class="w-5 h-5 text-[#34554a] border-gray-300 rounded focus:ring-[#34554a]" />
                        <div>
                            <p class="font-medium text-gray-900">Iodine/Woundsarex</p>
                        </div>
                    </label>
                    <label class="flex items-center gap-3 p-4 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                        <input type="checkbox" v-model="form.colostrum_feeding_24h" class="w-5 h-5 text-[#34554a] border-gray-300 rounded focus:ring-[#34554a]" />
                        <div>
                            <p class="font-medium text-gray-900">Colostrum (24H)</p>
                        </div>
                    </label>
                    <label class="flex items-center gap-3 p-4 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                        <input type="checkbox" v-model="form.mamumune" class="w-5 h-5 text-[#34554a] border-gray-300 rounded focus:ring-[#34554a]" />
                        <div>
                            <p class="font-medium text-gray-900">Maminume</p>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Documentation -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
                <div class="px-6 py-4 bg-[#34554a] border-b border-gray-100">
                    <h3 class="text-sm font-bold text-white tracking-wide">Tagging & documentation</h3>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Tagging/Checklist Date</label>
                        <input type="date" v-model="form.tagging_checklist_date" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">LCC Running No.</label>
                        <input type="text" v-model="form.lcc_running_number" :disabled="hasAutoFilledFields" placeholder="e.g., 3179" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-[#34554a]" :class="hasAutoFilledFields ? 'bg-gray-50 cursor-not-allowed' : 'bg-white'" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Remarks</label>
                        <textarea v-model="form.remarks" rows="2" placeholder="Additional notes..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]"></textarea>
                    </div>
                </div>
            </div>
        </form>
    </div>
</template>

