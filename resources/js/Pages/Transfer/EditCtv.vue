<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { computed, ref } from 'vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import { Save, ArrowLeft, Plus, Trash2 } from 'lucide-vue-next';

defineOptions({
    layout: (h, page) => h(AppLayout, { title: 'Edit CTV', parent: 'Transfer', parentUrl: '/transfer/ctv' }, () => page)
});

const page = usePage();
const doc = computed(() => page.props.document || {});
const cattleOptions = computed(() => page.props.cattle || []);

const form = useForm({
    from_location: doc.value.from_location || '',
    to_location: doc.value.to_location || '',
    date: doc.value.date ? String(doc.value.date).slice(0, 10) : new Date().toISOString().split('T')[0],
    time: doc.value.time ? String(doc.value.time).slice(0, 5) : new Date().toTimeString().slice(0, 5),
    livestock: (doc.value.livestock || []).map((item) => ({
        tag_no: item.tag_no || '',
        category: item.category || 'WB',
        colour: item.colour || '',
        weight: item.weight ?? null,
        condition_good: !!item.condition_good,
        condition_not_good: !!item.condition_not_good,
        remarks: item.remarks || '',
        purpose: item.purpose || ''
    }))
});

const selectedTagNos = computed(() => new Set(form.livestock.map((item) => item.tag_no).filter(Boolean)));
const cattleSearch = ref('');

const filteredOptionsForRow = (row) => {
    const query = cattleSearch.value.trim().toLowerCase();
    const activeTag = row.tag_no || '';
    const fromUnit = (form.from_location || '').trim();

    if (!fromUnit) return [];
    return cattleOptions.value.filter((c) => {
        const tagNo = c.tag_no || '';
        if (String(c.operating_unit || '') !== fromUnit) return false;
        if (selectedTagNos.value.has(tagNo) && tagNo !== activeTag) return false;
        if (!query) return true;
        return String(tagNo).toLowerCase().includes(query);
    });
};

if (!form.livestock.length) {
    form.livestock.push({ tag_no: '', category: 'WB', colour: '', weight: null, condition_good: true, condition_not_good: false, remarks: '', purpose: '' });
}

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

const addLivestock = () => {
    form.livestock.push({ tag_no: '', category: 'WB', colour: '', weight: null, condition_good: true, condition_not_good: false, remarks: '', purpose: '' });
};

const removeLivestock = (index) => {
    if (form.livestock.length > 1) form.livestock.splice(index, 1);
};

const syncLivestockFromTag = (item) => {
    const tagNo = item.tag_no?.trim();
    if (tagNo) {
        const alreadyExists = form.livestock.some((existing) => existing.tag_no === tagNo && existing !== item);
        if (alreadyExists) {
            alert(`Tag no. ${tagNo} is already added in this document`);
            item.tag_no = '';
            item.category = 'WB';
            item.colour = '';
            return;
        }
    }
    const selected = cattleOptions.value.find((c) => c.tag_no === item.tag_no);
    if (!selected) return;
    item.category = normalizeCategory(selected.category);
    item.colour = selected.colour || '';
};

const setCondition = (item, value) => {
    item.condition_good = value === 'good';
    item.condition_not_good = value === 'not_good';
};

const submitForm = () => {
    const tagNos = form.livestock.map((item) => item.tag_no?.trim()).filter(Boolean);
    const duplicates = tagNos.filter((tag, idx) => tagNos.indexOf(tag) !== idx);
    if (duplicates.length > 0) {
        alert(`Duplicate tag no. found: ${duplicates[0]}. Please remove duplicates before saving.`);
        return;
    }
    form.put(`/transfer/ctv/${doc.value.id}`, {
        onSuccess: () => alert('CTV updated successfully'),
        onError: (errors) => {
            const message = Object.values(errors || {}).flat().join('\n') || 'Failed to update CTV';
            alert(message);
        }
    });
};

const goBack = () => router.visit('/transfer/ctv');
</script>

<template>
    <div class="w-full max-w-6xl mx-auto">
        <div class="mb-8 flex justify-between items-center">
            <div class="flex items-center gap-4">
                <button @click="goBack" class="w-10 h-10 flex items-center justify-center rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors">
                    <ArrowLeft class="w-5 h-5 text-gray-600" />
                </button>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Edit cattle transfer voucher (CTV)</h1>
                    <p class="text-sm text-gray-500 mt-1">Same visual style as calving module</p>
                </div>
            </div>
            <button type="button" @click="submitForm" class="flex items-center gap-2 bg-[#34554a] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#2a443b] shadow-sm transition-colors disabled:opacity-50" :disabled="form.processing">
                <Save class="w-4 h-4" />
                {{ form.processing ? 'Saving...' : 'Update record' }}
            </button>
        </div>

        <form @submit.prevent="submitForm">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
                <div class="px-6 py-4 bg-[#34554a] border-b border-gray-100">
                    <h3 class="text-sm font-bold text-white">Document scope</h3>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Transfer from *</label>
                        <select v-model="form.from_location" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" required>
                            <option value="">Select source</option>
                            <option v-for="estate in $page.props.estates" :key="estate.id" :value="estate.name">{{ estate.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Transfer to *</label>
                        <select v-model="form.to_location" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" required>
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
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
                <div class="px-6 py-4 bg-[#34554a] border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-white">Livestock details</h3>
                    <div class="flex items-center gap-2">
                        <input v-model="cattleSearch" placeholder="Search cattle ID..." class="px-3 py-1.5 rounded bg-white text-xs text-gray-700 outline-none border border-white/40 min-w-[180px]" />
                        <button type="button" @click="addLivestock" class="inline-flex items-center gap-1 px-3 py-1.5 rounded bg-white/15 text-white text-xs font-semibold hover:bg-white/25">
                            <Plus class="w-3.5 h-3.5" />
                            Add row
                        </button>
                    </div>
                </div>
                <div class="p-6 overflow-x-auto">
                    <table class="w-full text-left whitespace-nowrap min-w-[1000px]">
                        <thead>
                            <tr class="bg-[#34554a] text-white text-sm">
                                <th class="p-3 font-semibold">No.</th>
                                <th class="p-3 font-semibold">Tag no.</th>
                                <th class="p-3 font-semibold">Category</th>
                                <th class="p-3 font-semibold">Colour</th>
                                <th class="p-3 font-semibold">Weight (kg)</th>
                                <th class="p-3 font-semibold">Condition</th>
                                <th class="p-3 font-semibold">Purpose</th>
                                <th class="p-3 font-semibold">Remarks</th>
                                <th class="p-3 font-semibold text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y text-sm text-gray-700">
                            <tr v-for="(item, index) in form.livestock" :key="index" class="hover:bg-gray-50">
                                <td class="p-3 font-medium">{{ index + 1 }}</td>
                                <td class="p-3">
                                    <select v-model="item.tag_no" @change="syncLivestockFromTag(item)" class="w-full px-3 py-2 border border-gray-200 rounded-lg outline-none focus:ring-2 focus:ring-[#34554a]" required>
                                        <option value="">Select tag no.</option>
                                        <option v-if="!form.from_location" value="" disabled>Select from location first</option>
                                        <option v-else-if="filteredOptionsForRow(item).length === 0" value="" disabled>No record found</option>
                                        <option v-for="c in filteredOptionsForRow(item)" :key="c.id" :value="c.tag_no">{{ c.tag_no }}</option>
                                    </select>
                                </td>
                                <td class="p-3">
                                    <select v-model="item.category" class="w-full px-3 py-2 border border-gray-200 rounded-lg outline-none focus:ring-2 focus:ring-[#34554a]" :disabled="!!item.tag_no" :class="{ 'bg-gray-100 cursor-not-allowed': !!item.tag_no }">
                                        <option v-for="type in categoryOptionsForItem(item)" :key="type" :value="type">{{ type }}</option>
                                    </select>
                                </td>
                                <td class="p-3"><input v-model="item.colour" class="w-full px-3 py-2 border border-gray-200 rounded-lg outline-none focus:ring-2 focus:ring-[#34554a]" :disabled="!!item.tag_no" :class="{ 'bg-gray-100 cursor-not-allowed': !!item.tag_no }"></td>
                                <td class="p-3"><input v-model="item.weight" type="number" class="w-full px-3 py-2 border border-gray-200 rounded-lg outline-none focus:ring-2 focus:ring-[#34554a]"></td>
                                <td class="p-3">
                                    <div class="flex items-center gap-3">
                                        <button type="button" @click="setCondition(item, 'good')" :class="item.condition_good ? 'bg-[#34554a] text-white border-[#34554a]' : 'bg-white text-gray-600 border-gray-300'" class="px-2 py-1 rounded border text-xs font-semibold transition-colors">Good</button>
                                        <button type="button" @click="setCondition(item, 'not_good')" :class="item.condition_not_good ? 'bg-[#34554a] text-white border-[#34554a]' : 'bg-white text-gray-600 border-gray-300'" class="px-2 py-1 rounded border text-xs font-semibold transition-colors">Not good</button>
                                    </div>
                                </td>
                                <td class="p-3"><input v-model="item.purpose" class="w-full px-3 py-2 border border-gray-200 rounded-lg outline-none focus:ring-2 focus:ring-[#34554a]"></td>
                                <td class="p-3"><input v-model="item.remarks" class="w-full px-3 py-2 border border-gray-200 rounded-lg outline-none focus:ring-2 focus:ring-[#34554a]"></td>
                                <td class="p-3 text-center">
                                    <button type="button" @click="removeLivestock(index)" class="w-8 h-8 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded flex items-center justify-center transition-colors">
                                        <Trash2 class="w-4 h-4" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </form>
    </div>
</template>
