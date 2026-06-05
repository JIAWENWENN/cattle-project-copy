<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { computed, ref } from 'vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import { Save, ArrowLeft, Plus, Trash2, Search, ChevronDown, Check, X } from 'lucide-vue-next';

defineOptions({
    layout: (h, page) => h(AppLayout, { title: 'Create SIV', parent: 'Transfer', parentUrl: '/transfer/siv' }, () => page)
});

const page = usePage();
const cattleOptions = computed(() => page.props.cattle || []);

const showTagDropdownFor = ref(null);
const tagSearchQuery = ref('');

const form = useForm({
    type: 'SIV',
    from_location: '',
    to_location: '',
    date: new Date().toISOString().split('T')[0],
    time: new Date().toTimeString().slice(0, 5),
    vehicle_no: '',
    driver_name: '',
    driver_tel: '',
    driver_ic: '',
    address: '',
    customer_name: '',
    siv_no: '',
    receipt_no: '',
    livestock: [
        {
            row_key: Date.now(),
            tag_no: '',
            category: 'WB',
            colour: '',
            weight: null,
            unit_cost: null,
            value: 0,
            remarks: '',
        }
    ]
});

const selectedTagNos = computed(() => new Set(form.livestock.map((item) => item.tag_no).filter(Boolean)));
const activeRow = computed(() => form.livestock.find((row) => row.row_key === showTagDropdownFor.value) || null);

const filteredCattleOptions = computed(() => {
    const query = tagSearchQuery.value.trim().toLowerCase();
    const fromUnit = (form.from_location || '').trim();
    const activeTag = activeRow.value?.tag_no || '';

    return cattleOptions.value.filter((c) => {
        const tagNo = c.tag_no || '';
        if (!fromUnit) return false; // restrict by selected "from" location
        if (String(c.operating_unit || '') !== fromUnit) return false;
        if (selectedTagNos.value.has(tagNo) && tagNo !== activeTag) return false;
        if (!query) return true;
        return (
            String(tagNo).toLowerCase().includes(query) ||
            String(c.id || '').toLowerCase().includes(query) ||
            String(c.category || '').toLowerCase().includes(query) ||
            String(c.colour || c.coat_colour || '').toLowerCase().includes(query)
        );
    });
});

const livestockTypes = ['WB', 'BC', 'Bull', 'Steer', 'Heifer'];

const normalizeCategory = (value) => {
    const raw = String(value || '').trim();
    if (!raw) return 'WB';

    const upper = raw.toUpperCase();
    if (livestockTypes.includes(raw)) return raw;
    if (livestockTypes.includes(upper)) return upper;

    const lower = raw.toLowerCase();
    if (lower.includes('bull')) return 'Bull';
    if (lower.includes('steer')) return 'Steer';
    if (lower.includes('heifer')) return 'Heifer';
    if (lower === 'bc') return 'BC';
    if (lower === 'wb') return 'WB';

    return raw;
};

const categoryOptionsForItem = (item) => {
    const set = new Set(livestockTypes);
    if (item?.category) set.add(item.category);
    return Array.from(set);
};

const calculateValue = (item) => {
    const weight = Number(item.weight) || 0;
    const unitCost = Number(item.unit_cost) || 0;
    item.value = Number((weight * unitCost).toFixed(2));
};

const addLivestock = () => {
    form.livestock.push({
        row_key: Date.now() + Math.random(),
        tag_no: '',
        category: 'WB',
        colour: '',
        weight: null,
        unit_cost: null,
        value: 0,
        remarks: '',
    });
};

const removeLivestock = (index) => {
    if (form.livestock.length > 1) {
        const row = form.livestock[index];
        if (showTagDropdownFor.value === row.row_key) {
            showTagDropdownFor.value = null;
            tagSearchQuery.value = '';
        }
        form.livestock.splice(index, 1);
    }
};

const openTagDropdown = (row) => {
    showTagDropdownFor.value = row.row_key;
    tagSearchQuery.value = '';
};

const selectTagForRow = (row, cattle) => {
    const tagNo = cattle.tag_no || '';
    const alreadyExists = form.livestock.some((item, idx) => item.tag_no === tagNo && item.row_key !== row.row_key);
    if (alreadyExists) {
        alert(`Tag no. ${tagNo} is already added in this document`);
        return;
    }
    row.tag_no = tagNo;
    syncLivestockFromTag(row);
    showTagDropdownFor.value = null;
    tagSearchQuery.value = '';
};

const clearTagForRow = (row) => {
    row.tag_no = '';
    row.category = 'WB';
    row.colour = '';
};

const syncLivestockFromTag = (item) => {
    const tagNo = item.tag_no?.trim();
    if (tagNo) {
        const alreadyExists = form.livestock.some((existing) => existing.tag_no === tagNo && existing.row_key !== item.row_key);
        if (alreadyExists) {
            alert(`Tag no. ${tagNo} is already added in this document`);
            item.tag_no = '';
            item.category = 'WB';
            item.colour = '';
            item.weight = null;
            item.unit_cost = null;
            item.value = 0;
            return;
        }
    }
    const selected = cattleOptions.value.find((c) => c.tag_no === item.tag_no);
    if (!selected) return;
    item.category = normalizeCategory(selected.category);
    item.colour = selected.colour || selected.coat_colour || '';
};

const totalWeight = computed(() => form.livestock.reduce((sum, item) => sum + (Number(item.weight) || 0), 0));
const totalValue = computed(() => form.livestock.reduce((sum, item) => sum + (Number(item.value) || 0), 0).toFixed(2));

const submitForm = () => {
    const tagNos = form.livestock.map(item => item.tag_no?.trim()).filter(Boolean);
    const duplicates = tagNos.filter((tag, idx) => tagNos.indexOf(tag) !== idx);
    if (duplicates.length > 0) {
        alert(`Duplicate tag no. found: ${duplicates[0]}. Please remove duplicates before saving.`);
        return;
    }
    form.livestock = form.livestock.map((item) => ({
        ...item,
        value: Number(item.value) || 0,
    }));

    form.post('/transfer', {
        onSuccess: () => {
            alert('SIV saved successfully');
        },
        onError: (errors) => {
            const message = Object.values(errors || {}).flat().join('\n') || 'Failed to save SIV';
            alert(message);
        }
    });
};

const goBack = () => {
    router.visit('/transfer/siv');
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
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Create sales issue voucher (SIV)</h1>
                    <p class="text-sm text-gray-500 mt-1">Use the same workflow styling as CTV module</p>
                </div>
            </div>
            <button type="button" @click="submitForm" class="flex items-center gap-2 bg-[#34554a] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#2a443b] shadow-sm transition-colors disabled:opacity-50" :disabled="form.processing">
                <Save class="w-4 h-4" />
                {{ form.processing ? 'Saving...' : 'Save record' }}
            </button>
        </div>

        <form @submit.prevent="submitForm">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-visible mb-6">
                <div class="px-6 py-4 bg-[#34554a] border-b border-gray-100">
                    <h3 class="text-sm font-bold text-white">Document scope</h3>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-3 xl:grid-cols-4 gap-6">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">SIV No.</label>
                        <input v-model="form.siv_no" type="text" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" placeholder="Enter SIV number">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Receipt No.</label>
                        <input v-model="form.receipt_no" type="text" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" placeholder="Enter receipt number">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Customer *</label>
                        <input v-model="form.customer_name" type="text" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" required>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Address</label>
                        <input v-model="form.address" type="text" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" placeholder="Enter address">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Transfer from *</label>
                        <select v-model="form.from_location" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" required>
                            <option value="">Select source</option>
                            <option v-for="estate in $page.props.estates" :key="estate.id" :value="estate.name">{{ estate.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Transfer to</label>
                        <select v-model="form.to_location" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]">
                            <option value="">Select destination</option>
                            <option v-for="estate in $page.props.estates" :key="estate.id" :value="estate.name">{{ estate.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Date *</label>
                        <input v-model="form.date" type="date" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" required>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Time</label>
                        <input v-model="form.time" type="time" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Vehicle no.</label>
                        <input v-model="form.vehicle_no" type="text" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Driver name</label>
                        <input v-model="form.driver_name" type="text" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Driver IC no.</label>
                        <input v-model="form.driver_ic" type="text" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" placeholder="Enter IC number">
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
                <div class="px-6 py-4 bg-[#34554a] border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-white">Livestock details</h3>
                    <button type="button" @click="addLivestock" class="inline-flex items-center gap-1 px-3 py-1.5 rounded bg-white/15 text-white text-xs font-semibold hover:bg-white/25">
                        <Plus class="w-3.5 h-3.5" />
                        Add row
                    </button>
                </div>
                <div class="p-6 min-h-[360px] overflow-x-auto overflow-y-visible">
                    <table class="w-full text-left whitespace-nowrap min-w-[1200px]">
                        <thead>
                            <tr class="bg-[#34554a] text-white text-sm">
                                <th class="p-3 font-semibold">No.</th>
                                <th class="p-3 font-semibold">Cattle ID</th>
                                <th class="p-3 font-semibold">Category</th>
                                <th class="p-3 font-semibold">Colour</th>
                                <th class="p-3 font-semibold">Weight (kg)</th>
                                <th class="p-3 font-semibold">Unit Cost (RM)</th>
                                <th class="p-3 font-semibold">Value (RM)</th>
                                <th class="p-3 font-semibold">Remarks</th>
                                <th class="p-3 font-semibold text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y text-sm text-gray-700">
                            <tr v-for="(item, index) in form.livestock" :key="item.row_key" class="hover:bg-gray-50">
                                <td class="p-3 font-medium">{{ index + 1 }}</td>
                                <td class="p-3">
                                    <div class="relative">
                                        <div
                                            @click="openTagDropdown(item)"
                                            class="w-full px-3 py-2 border border-gray-200 rounded-lg outline-none focus:ring-2 focus:ring-[#34554a] bg-white cursor-pointer flex items-center justify-between"
                                        >
                                            <span :class="item.tag_no ? 'text-gray-900' : 'text-gray-400'" class="text-sm">
                                                {{ item.tag_no || 'Search and select cattle ID...' }}
                                            </span>
                                            <div class="flex items-center gap-1">
                                                <button
                                                    v-if="item.tag_no"
                                                    @click.stop="clearTagForRow(item)"
                                                    type="button"
                                                    class="text-gray-400 hover:text-red-500"
                                                >
                                                    <X class="w-4 h-4" />
                                                </button>
                                                <ChevronDown class="w-4 h-4 text-gray-400" />
                                            </div>
                                        </div>

                                        <div
                                            v-if="showTagDropdownFor === item.row_key"
                                            class="absolute z-20 left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-xl max-h-96 overflow-hidden"
                                        >
                                            <div class="p-3 border-b border-gray-100 bg-gray-50">
                                                <div class="relative">
                                                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 w-4 h-4" />
                                                    <input
                                                        v-model="tagSearchQuery"
                                                        type="text"
                                                        placeholder="Search by cattle ID, tag no, category, or colour..."
                                                        class="w-full pl-10 pr-3 py-2 rounded-lg border border-gray-200 bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]"
                                                        @click.stop
                                                    >
                                                </div>
                                                <div class="text-xs text-gray-500 mt-2">{{ filteredCattleOptions.length }} cattle found</div>
                                            </div>

                                            <div class="overflow-y-auto max-h-56">
                                                <div v-if="filteredCattleOptions.length === 0" class="p-4 text-center text-gray-500 text-sm">
                                                    <span v-if="!form.from_location">Select from location first</span>
                                                    <span v-else-if="!tagSearchQuery.trim()">No record found</span>
                                                    <span v-else>No cattle found matching "{{ tagSearchQuery }}"</span>
                                                </div>
                                                <div
                                                    v-for="c in filteredCattleOptions"
                                                    :key="c.id"
                                                    @click.stop="selectTagForRow(item, c)"
                                                    class="px-4 py-3 hover:bg-gray-50 cursor-pointer border-b border-gray-100 last:border-0"
                                                    :class="{ 'bg-[#34554a]/10': item.tag_no === c.tag_no }"
                                                >
                                                    <div class="flex items-center justify-between">
                                                        <div>
                                                            <p class="font-medium text-gray-900">{{ c.tag_no }}</p>
                                                            <p class="text-xs text-gray-500">ID: {{ c.id }} | {{ c.category || '-' }} | {{ c.colour || c.coat_colour || '-' }}</p>
                                                        </div>
                                                        <div v-if="item.tag_no === c.tag_no" class="text-[#34554a]">
                                                            <Check class="w-5 h-5" />
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <input v-model="item.tag_no" type="hidden" required>
                                </td>
                                <td class="p-3">
                                    <select v-model="item.category" class="w-full px-3 py-2 border border-gray-200 rounded-lg outline-none focus:ring-2 focus:ring-[#34554a]" :disabled="!!item.tag_no" :class="{'bg-gray-100 cursor-not-allowed': !!item.tag_no}">
                                        <option v-for="type in categoryOptionsForItem(item)" :key="type" :value="type">{{ type }}</option>
                                    </select>
                                </td>
                                <td class="p-3"><input v-model="item.colour" class="w-full px-3 py-2 border border-gray-200 rounded-lg outline-none focus:ring-2 focus:ring-[#34554a]" :disabled="!!item.tag_no" :class="{'bg-gray-100 cursor-not-allowed': !!item.tag_no}"></td>
                                <td class="p-3"><input v-model="item.weight" @input="calculateValue(item)" type="number" step="0.01" min="0" class="w-full px-3 py-2 border border-gray-200 rounded-lg outline-none focus:ring-2 focus:ring-[#34554a]"></td>
                                <td class="p-3"><input v-model="item.unit_cost" @input="calculateValue(item)" type="number" step="0.01" min="0" class="w-full px-3 py-2 border border-gray-200 rounded-lg outline-none focus:ring-2 focus:ring-[#34554a]"></td>
                                <td class="p-3"><input :value="Number(item.value || 0).toFixed(2)" type="text" readonly class="w-full px-3 py-2 border border-gray-200 rounded-lg bg-gray-50 text-gray-700"></td>
                                <td class="p-3"><input v-model="item.remarks" class="w-full px-3 py-2 border border-gray-200 rounded-lg outline-none focus:ring-2 focus:ring-[#34554a]"></td>
                                <td class="p-3 text-center">
                                    <button type="button" @click="removeLivestock(index)" class="w-8 h-8 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded flex items-center justify-center transition-colors">
                                        <Trash2 class="w-4 h-4" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="bg-gray-50 text-sm font-semibold text-gray-700">
                                <td colspan="4" class="p-3 text-right">Total</td>
                                <td class="p-3">{{ totalWeight }}</td>
                                <td class="p-3"></td>
                                <td class="p-3">{{ totalValue }}</td>
                                <td colspan="2" class="p-3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </form>
    </div>
</template>
