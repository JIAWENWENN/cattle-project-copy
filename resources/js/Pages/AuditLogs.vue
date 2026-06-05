<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import { Search, FilterX } from 'lucide-vue-next';

const props = defineProps({
    logs: Object,
    models: Array,
    filters: Object,
});

const filterForm = reactive({
    search: props.filters?.search || '',
    event: props.filters?.event || '',
    model: props.filters?.model || '',
    date_from: props.filters?.date_from || '',
    date_to: props.filters?.date_to || '',
});

const eventBadge = (event) => {
    const value = String(event || '').toLowerCase();
    if (value === 'created') return 'bg-green-100 text-green-700';
    if (value === 'updated') return 'bg-blue-100 text-blue-700';
    if (value === 'deleted') return 'bg-red-100 text-red-700';
    if (value === 'viewed') return 'bg-amber-100 text-amber-700';
    return 'bg-gray-100 text-gray-700';
};

const applyFilters = () => {
    router.get('/audit-logs', filterForm, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const clearFilters = () => {
    filterForm.search = '';
    filterForm.event = '';
    filterForm.model = '';
    filterForm.date_from = '';
    filterForm.date_to = '';
    applyFilters();
};

const fmtDateTime = (value) => {
    if (!value) return '-';
    return new Date(value).toLocaleString('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const looksLikeIsoDateTime = (value) =>
    typeof value === 'string' &&
    /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?Z$/.test(value);

const fmtChangeValue = (key, value) => {
    if (value === null || value === undefined || value === '') return '-';

    if (
        value instanceof Date ||
        (typeof key === 'string' && key.endsWith('_at')) ||
        looksLikeIsoDateTime(value)
    ) {
        const parsed = value instanceof Date ? value : new Date(value);
        if (!Number.isNaN(parsed.getTime())) {
            return fmtDateTime(parsed);
        }
    }

    if (typeof value === 'object') {
        try {
            return JSON.stringify(value);
        } catch {
            return '[object]';
        }
    }

    return String(value);
};

const jsonToChanges = (log) => {
    const event = String(log.event || '').toLowerCase();
    const oldValues = log.old_values || {};
    const newValues = log.new_values || {};

    if (event === 'created') {
        const fields = Object.keys(newValues);
        return fields.length ? `Created fields: ${fields.join(', ')}` : 'Created record';
    }

    if (event === 'viewed') {
        return 'Opened page';
    }

    if (event === 'deleted') {
        const fields = Object.keys(oldValues);
        return fields.length ? `Deleted fields: ${fields.join(', ')}` : 'Deleted record';
    }

    const keys = Object.keys(newValues);
    if (!keys.length) return '-';

    const preview = keys.slice(0, 3).map((key) => {
        const before = fmtChangeValue(key, oldValues[key]);
        const after = fmtChangeValue(key, newValues[key]);
        return `${key}: ${before} -> ${after}`;
    });

    const suffix = keys.length > 3 ? ` (+${keys.length - 3} more)` : '';
    return preview.join(' | ') + suffix;
};

const rows = computed(() => props.logs?.data || []);
</script>

<template>
    <Head title="Audit Logs" />

    <AppLayout title="Audit Logs" parent="Settings" parentUrl="#">
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Audit Logs</h1>
            <p class="text-sm text-gray-500 mt-1">Track create, update, and delete changes across modules.</p>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-4 mb-6">
            <div class="flex flex-wrap gap-3 items-center">
                <div class="relative flex-1 min-w-[220px]">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 w-4 h-4" />
                    <input
                        v-model="filterForm.search"
                        type="text"
                        placeholder="Search user, route, model, record id..."
                        class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-200 outline-none text-sm focus:ring-2 focus:ring-[#34554a]"
                        @keyup.enter="applyFilters"
                    >
                </div>
                <select
                    v-model="filterForm.event"
                    class="px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]"
                >
                    <option value="">All Events</option>
                    <option value="created">Created</option>
                    <option value="updated">Updated</option>
                    <option value="deleted">Deleted</option>
                    <option value="viewed">Viewed</option>
                </select>
                <select
                    v-model="filterForm.model"
                    class="px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]"
                >
                    <option value="">All Models</option>
                    <option v-for="model in models" :key="model" :value="model">{{ model }}</option>
                </select>
                <input
                    v-model="filterForm.date_from"
                    type="date"
                    class="px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]"
                >
                <input
                    v-model="filterForm.date_to"
                    type="date"
                    class="px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]"
                >
                <button
                    @click="applyFilters"
                    class="px-4 py-2 bg-[#34554a] text-white rounded-lg text-sm font-medium hover:bg-[#2a443b] transition-colors"
                >
                    Apply
                </button>
                <button
                    @click="clearFilters"
                    class="px-3 py-2 text-gray-500 text-sm hover:text-gray-700 font-medium flex items-center gap-1"
                >
                    <FilterX class="w-4 h-4" />
                    Clear
                </button>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="bg-[#34554a] text-white">
                            <th class="p-3 font-semibold">Date/Time</th>
                            <th class="p-3 font-semibold">User</th>
                            <th class="p-3 font-semibold">Event</th>
                            <th class="p-3 font-semibold">Model</th>
                            <th class="p-3 font-semibold">Record</th>
                            <th class="p-3 font-semibold">Route</th>
                            <th class="p-3 font-semibold">Changes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-if="rows.length === 0">
                            <td colspan="7" class="p-8 text-center text-gray-500">No audit logs found.</td>
                        </tr>
                        <tr v-for="log in rows" :key="log.id" class="hover:bg-gray-50">
                            <td class="p-3 whitespace-nowrap">{{ fmtDateTime(log.created_at) }}</td>
                            <td class="p-3 min-w-[220px]">
                                <div class="font-medium text-gray-900">{{ log.user_name || 'System' }}</div>
                                <div class="text-xs text-gray-500">{{ log.user_email || '-' }}</div>
                                <div class="text-xs text-gray-400">ID: {{ log.user_id || '-' }}</div>
                            </td>
                            <td class="p-3">
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="eventBadge(log.event)">
                                    {{ log.event }}
                                </span>
                            </td>
                            <td class="p-3">{{ log.auditable_type }}</td>
                            <td class="p-3">{{ log.auditable_id || '-' }}</td>
                            <td class="p-3">
                                <div class="text-xs text-gray-700">{{ log.method || '-' }} {{ log.route_name || '-' }}</div>
                                <div class="text-xs text-gray-500 break-all">{{ log.url || '-' }}</div>
                            </td>
                            <td class="p-3 min-w-[380px]">
                                <div class="text-xs text-gray-700 break-all">{{ jsonToChanges(log) }}</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="logs?.links" class="p-4 border-t border-gray-100 bg-gray-50 flex flex-wrap gap-2">
                <button
                    v-for="(link, idx) in logs.links"
                    :key="idx"
                    :disabled="!link.url"
                    @click="link.url && router.visit(link.url, { preserveScroll: true, preserveState: true })"
                    class="px-3 py-1.5 rounded border text-sm"
                    :class="link.active ? 'bg-[#34554a] text-white border-[#34554a]' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50 disabled:opacity-50'"
                    v-html="link.label"
                />
            </div>
        </div>
    </AppLayout>
</template>
