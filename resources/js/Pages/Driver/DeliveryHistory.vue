<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { 
    MapPin, Calendar, Clock, Truck, User, 
    Search, Filter, Download, Eye, FileText,
    Plus, Save, Edit, Trash2, ChevronLeft, ChevronRight, X, Settings2
} from 'lucide-vue-next';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';

const page = usePage();

const props = defineProps({
    deliveries: {
        type: Array,
        default: () => []
    },
    drivers: {
        type: Array,
        default: () => []
    },
    isDriver: {
        type: Boolean,
        default: false
    },
    driverStats: {
        type: Object,
        default: null
    },
    currentDriverId: {
        type: Number,
        default: null
    },
    currentDriverName: {
        type: String,
        default: null
    },
    selectedDriverId: {
        type: Number,
        default: null
    }
});

const driverPermissions = computed(() => {
    const perms = page.props.auth?.permissions?.['Driver'];
    return Array.isArray(perms) ? perms : ['no-access'];
});
const hasDriverPermission = (action) => {
    if (String(page.props.auth?.user?.role || '').toLowerCase() === 'admin') return true;
    const perms = driverPermissions.value;
    return perms.includes('full') || perms.includes(action);
};
const canCreateDriver = computed(() => hasDriverPermission('create'));
const canEditDriver = computed(() => hasDriverPermission('edit'));
const canDeleteDriver = computed(() => hasDriverPermission('delete'));

const searchQuery = ref('');
const statusFilter = ref('');
const showAddModal = ref(false);
const showDetailsModal = ref(false);
const selectedDelivery = ref(null);
const isEditing = ref(false);
const driverFilter = ref(props.selectedDriverId || '');
const currentPage = ref(1);
const itemsPerPage = 10;
const bulkMode = ref(false);
const selectedIds = ref([]);
const showDeleteModal = ref(false);
const showBulkDeleteModal = ref(false);
const deliveryToDelete = ref(null);

const deliveryForm = useForm({
    date: new Date().toISOString().split('T')[0],
    time: new Date().toLocaleTimeString('en-US', { hour12: true, hour: '2-digit', minute: '2-digit' }),
    user_id: props.isDriver ? props.currentDriverId : '',
    vehicle: '',
    origin: '',
    destination: '',
    cargo_type: '',
    cargo_weight: '',
    status: 'pending',
    delivery_notes: '',
    customer: ''
});

const filteredDeliveries = computed(() => {
    return props.deliveries.filter(delivery => {
        const matchesSearch = !searchQuery.value || 
            delivery.id.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            delivery.driver.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            delivery.destination.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            delivery.customer.toLowerCase().includes(searchQuery.value.toLowerCase());
        
        const matchesStatus = !statusFilter.value || delivery.status === statusFilter.value;
        
        return matchesSearch && matchesStatus;
    });
});

const totalPages = computed(() => {
    return Math.ceil(filteredDeliveries.value.length / itemsPerPage);
});

const paginatedDeliveries = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    return filteredDeliveries.value.slice(start, end);
});

const pageNumbers = computed(() => {
    const pages = [];
    const maxVisible = 5;

    if (totalPages.value <= maxVisible) {
        for (let i = 1; i <= totalPages.value; i++) {
            pages.push(i);
        }
    } else if (currentPage.value <= 3) {
        pages.push(1, 2, 3, '...', totalPages.value);
    } else if (currentPage.value >= totalPages.value - 2) {
        pages.push(1, '...', totalPages.value - 2, totalPages.value - 1, totalPages.value);
    } else {
        pages.push(1, '...', currentPage.value - 1, currentPage.value, currentPage.value + 1, '...', totalPages.value);
    }

    return pages;
});

const getStatusClass = (status) => {
    const classes = {
        'delivered': 'bg-green-100 text-green-700 border-green-200',
        'in_transit': 'bg-blue-100 text-blue-700 border-blue-200',
        'pending': 'bg-yellow-100 text-yellow-700 border-yellow-200',
        'cancelled': 'bg-red-100 text-red-700 border-red-200'
    };
    return classes[status] || 'bg-gray-100 text-gray-700 border-gray-200';
};

const formatStatus = (status) => {
    const normalized = String(status || '').replace(/_/g, ' ').toLowerCase();
    return normalized.charAt(0).toUpperCase() + normalized.slice(1);
};

const openAddModal = () => {
    isEditing.value = false;
    deliveryForm.reset();
    if (props.isDriver) {
        deliveryForm.user_id = props.currentDriverId;
    }
    showAddModal.value = true;
};

const openEditModal = (delivery) => {
    isEditing.value = true;
    selectedDelivery.value = delivery;
    
    deliveryForm.date = delivery.date;
    deliveryForm.time = delivery.time;
    deliveryForm.user_id = delivery.user_id;
    deliveryForm.vehicle = delivery.vehicle;
    deliveryForm.origin = delivery.origin;
    deliveryForm.destination = delivery.destination;
    deliveryForm.cargo_type = delivery.cargo_type;
    deliveryForm.cargo_weight = delivery.cargo_weight;
    deliveryForm.status = delivery.status;
    deliveryForm.delivery_notes = delivery.delivery_notes;
    deliveryForm.customer = delivery.customer;
    
    showAddModal.value = true;
};

const handleDriverFilterChange = () => {
    const url = new URL(window.location.href);
    if (driverFilter.value) {
        url.searchParams.set('driver_id', driverFilter.value);
    } else {
        url.searchParams.delete('driver_id');
    }
    window.location.href = url.toString();
};

const openDetailsModal = (delivery) => {
    selectedDelivery.value = delivery;
    showDetailsModal.value = true;
};

const closeModals = () => {
    showAddModal.value = false;
    showDetailsModal.value = false;
    selectedDelivery.value = null;
};

const toggleBulkMode = () => {
    bulkMode.value = !bulkMode.value;
    if (!bulkMode.value) {
        selectedIds.value = [];
    }
};

const allSelected = computed(() => {
    if (paginatedDeliveries.value.length === 0) return false;
    return paginatedDeliveries.value.every((delivery) => selectedIds.value.includes(delivery.db_id));
});

const toggleSelectAll = () => {
    const pageIds = paginatedDeliveries.value.map((delivery) => delivery.db_id);

    if (allSelected.value) {
        selectedIds.value = selectedIds.value.filter((id) => !pageIds.includes(id));
        return;
    }

    selectedIds.value = [...new Set([...selectedIds.value, ...pageIds])];
};

const toggleSelectDelivery = (id) => {
    const index = selectedIds.value.indexOf(id);
    if (index >= 0) {
        selectedIds.value.splice(index, 1);
        return;
    }

    selectedIds.value.push(id);
};

const promptDelete = (delivery) => {
    deliveryToDelete.value = delivery;
    showDeleteModal.value = true;
};

const confirmDelete = () => {
    if (!deliveryToDelete.value) return;

    router.delete(route('driver.delivery-history.destroy', deliveryToDelete.value.db_id), {
        preserveScroll: true,
        onFinish: () => {
            showDeleteModal.value = false;
            deliveryToDelete.value = null;
        }
    });
};

const promptBulkDelete = () => {
    if (selectedIds.value.length === 0) return;
    showBulkDeleteModal.value = true;
};

const confirmBulkDelete = () => {
    if (selectedIds.value.length === 0) return;

    router.post(route('driver.delivery-history.bulk-delete'), {
        ids: selectedIds.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            selectedIds.value = [];
            bulkMode.value = false;
        },
        onFinish: () => {
            showBulkDeleteModal.value = false;
        }
    });
};

const submitForm = () => {
    if (isEditing.value) {
        deliveryForm.put(route('driver.delivery-history.update', selectedDelivery.value.db_id), {
            onSuccess: () => {
                closeModals();
            }
        });
    } else {
        deliveryForm.post(route('driver.delivery-history.store'), {
            onSuccess: () => {
                closeModals();
            }
        });
    }
};

const stats = computed(() => {
    // Calculate from CURRENT filtered list to make it dynamic
    return {
        total: filteredDeliveries.value.length,
        delivered: filteredDeliveries.value.filter(d => d.status === 'delivered').length,
        inTransit: filteredDeliveries.value.filter(d => d.status === 'in_transit').length,
        pending: filteredDeliveries.value.filter(d => d.status === 'pending').length,
        cancelled: filteredDeliveries.value.filter(d => d.status === 'cancelled').length
    };
});

const goToPage = (page) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
    }
};

const previousPage = () => {
    if (currentPage.value > 1) {
        currentPage.value--;
    }
};

const nextPage = () => {
    if (currentPage.value < totalPages.value) {
        currentPage.value++;
    }
};

watch([searchQuery, statusFilter], () => {
    currentPage.value = 1;
});

watch(totalPages, (newTotal) => {
    if (newTotal === 0) {
        currentPage.value = 1;
        return;
    }

    if (currentPage.value > newTotal) {
        currentPage.value = newTotal;
    }
});

watch(filteredDeliveries, () => {
    const validIds = new Set(filteredDeliveries.value.map((delivery) => delivery.db_id));
    selectedIds.value = selectedIds.value.filter((id) => validIds.has(id));
});
</script>

<template>
    <Head title="Delivery History" />

    <AppLayout title="Delivery History" parent="Driver" parentUrl="/driver">
        <!-- Page Header -->
        <div class="mb-8 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Delivery History</h1>
                <p class="text-sm text-gray-500 mt-1">
                    <template v-if="isDriver">
                        Your delivery records - {{ currentDriverName }}
                    </template>
                    <template v-else>
                        Track and manage all delivery records.
                    </template>
                </p>
            </div>
            <div class="flex items-center gap-3">
                <button
                    @click="toggleBulkMode"
                    :class="bulkMode ? 'bg-[#34554a] text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50'"
                    class="px-4 py-2.5 rounded-lg font-medium flex items-center gap-2 shadow-sm transition-all"
                >
                    <Settings2 class="w-4 h-4" />
                    Bulk Action
                </button>
                <button v-if="canCreateDriver"
                    @click="openAddModal"
                    class="px-5 py-2.5 bg-[#34554a] text-white rounded-lg font-medium hover:bg-[#2c463d] flex items-center gap-2 shadow-sm transition-all"
                >
                    <Plus class="w-4 h-4" />
                    Add Delivery
                </button>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 font-medium">{{ isDriver ? 'My Total' : 'Total Deliveries' }}</p>
                        <p class="text-3xl font-bold text-gray-900 mt-1">{{ stats.total }}</p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center">
                        <svg class="w-8 h-8" viewBox="0 0 128 128" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <g>
                                <path d="M57.99 53.73s-.53-1.53-1.68-2.06c-1.14-.53-3.05-.61-3.05-.61l-3.2 37.85l2.6 18.68s.76 2.21 2.75 2.29c1.98.08 18.47 0 19.92 0c1.45 0 1.91-1.91 1.98-2.75c.08-.84.05-2.25.05-2.25l7.04-.03s.41 3-.27 4.89c-.59 1.64-1.12 2.95-1.11 3.51c.01.8 1.07 1.6 2.29 1.53c1.22-.08 3.05-1.68 4.27-2.67c1.22-.99 20.78-.76 20.78-.76s.94 1.74 1.99 2.79c.63.63 1.65.94 2.57.94c.92 0 2.06-1.22 1.83-2.06c-.23-.84-1.27-2.77-1.45-4.65c-.08-.85.02-3.22.44-3.61c.1-.09 1.62-.04 3.04-.19c2.42-.25 4.84-3.38 5.14-5.82c.31-2.44.08-10.15.08-10.15L57.99 53.73z" fill="#b3afaf"></path>
                                <path d="M52.02 96.11h72.03s.17 2.02-.18 3.32a5.612 5.612 0 0 1-.93 1.91l-70.51-.01l-.41-5.22z" fill="#9a9a9a"></path>
                                <path d="M26.75 92.84L11.5 97.56l-7.62-2.63s.27-14.34.36-15.79c.09-1.45.45-2.72 1.82-3.63c1.36-.91 5.17-3.18 5.17-3.18s11.71-18.79 12.71-20.15c1-1.36 2.23-3.02 5.14-3.29s20.87-.05 22.5.04c1.63.09 2.85.83 2.85 4.37s-.09 30.56-.18 31.92c-.09 1.36-6.9 6.35-6.9 6.35l-20.6 1.27z" fill="#e0e0e0"></path>
                                <path d="M48.95 110.03s1.46.04 2.47-.1s2.53-1.15 2.6-3.54c.04-1.42.23-21.17.23-21.17l-27.2.06l-8.8 9.63l-14.37.02s-.06 5.65-.02 7.16c.04 1.52 7.86 4.83 7.86 4.83l37.23 3.11z" fill="#dc0d28"></path>
                                <path d="M3.86 102.09h16.38s3.51-7 13.6-6.88c14.82.18 15.12 14.81 15.12 14.81h-38.6c-2.17 0-5.27-.72-6.06-3.46c-.67-2.3-.44-4.47-.44-4.47z" fill="#b10a1b"></path>
                                <path d="M13.89 76.26s0-1.63-1.07-1.69s-2.25.61-3.32 1.52c-1.13.96-1.69 1.83-1.91 2.59c-.34 1.13-.06 2.14 1.69 2.19c2.14.07 36.12-.15 37.69-.28c1.97-.17 3.15-1.01 3.09-2.48c-.05-1.41-1.07-1.86-5.29-1.91s-30.88.06-30.88.06z" fill="#ffffff"></path>
                                <path d="M4.2 81.03s10.21-.32 10.43-.07c.31.37.37 5.19.26 5.92c-.11.73-1.07 1.24-2.25 1.29s-8.61.17-8.61.17l.17-7.31z" fill="#fdedc5"></path>
                                <path d="M46.24 74.51c1.58 0 2.14-1.8 2.14-3.04V55.78c0-1.24-.79-1.91-3.26-2.03c-2.48-.11-13.78.02-15.02.06c-1.91.06-3.21.9-4.95 3.32c-1.13 1.57-8.1 13.11-8.66 14.06c-.56.96-1.24 3.49 1.69 3.54c3.03.06 28.06-.22 28.06-.22z" fill="#5f6369"></path>
                                <path d="M19.63 71.87h25.2c.51 0 .96-.56.96-1.24s.04-11.3.06-11.93c.06-1.74-1.24-2.14-2.36-2.08c-1.13.06-12.51.02-13.18.05c-1.29.06-2.57 1.79-3.69 3.5c-1.35 2.07-6.99 11.7-6.99 11.7z" fill="#afe3fb"></path>
                                <path d="M20.02 73.73c.11 2.53 4.16 4.61 5.18 4.67c1.01.06 1.63-1.91 1.74-4.56S25.99 69 25.14 69c-.84 0-5.21 2.59-5.12 4.73z" fill="#5f6369"></path>
                                <path d="M20.9 111.62c.08 5 4.06 12.19 12.72 12.24c8.66.05 13.16-6.59 12.88-13.4c-.28-6.92-5.65-11.81-13.38-11.59c-7.23.22-12.33 5.84-12.22 12.75z" fill="#4e433d"></path>
                                <path d="M27.09 111.33c.04 2.63 2.13 6.43 6.69 6.46s6.76-3.27 6.62-6.86c-.15-3.65-3.06-6.37-6.88-6.31c-3.8.05-6.48 3.06-6.43 6.71z" fill="#c8c8c8"></path>
                                <path d="M86.9 111.62c.08 5 4.06 12.19 12.72 12.24c8.66.05 13.16-6.59 12.88-13.4c-.28-6.92-5.65-11.81-13.38-11.59c-7.23.22-12.33 5.84-12.22 12.75z" fill="#4e433d"></path>
                                <path d="M93.09 111.33c.04 2.63 2.13 6.43 6.69 6.46c4.55.03 6.76-3.27 6.62-6.86c-.15-3.65-3.06-6.37-6.88-6.31c-3.8.05-6.48 3.06-6.43 6.71z" fill="#c8c8c8"></path>
                                <path d="M56.87 28.62c0-2.62 1.15-3.67 3.25-3.56c2.1.1 59.65-.21 61.53-.21s2.62 1.99 2.62 3.14c0 1.15-.36 59.49-.28 60.62c.07 1.13-.24 2.7-2.23 2.91s-61.01.1-62.27 0s-2.83-1.15-2.83-2.62c0-1.48.21-58.92.21-60.28z" fill="#d16e1d"></path>
                                <path d="M62.11 30.92c0 1.36-.42 52.31-.42 53.57c0 1.26.63 2.73 2.31 2.73c1.68 0 53.25.1 54.3 0c1.05-.1 1.68-.73 1.78-2.41c.1-1.68.1-52.83.1-54.51c0-1.68-1.68-1.78-3.46-1.78s-52.2.1-52.94.21c-.73.09-1.67.83-1.67 2.19z" fill="#f5b03a"></path>
                                <path d="M74.45 89c-.06-18.45-.19-61.48.02-62.65l1.48.27l1.49.18c-.14 1.65-.07 38.09.01 62.19l-3 .01z" fill="#d16e1d"></path>
                                <path fill="#d16e1d" d="M89.75 26.52h3V89.1h-3z"></path>
                                <path fill="#d16e1d" d="M104.89 26.57h3v62.27h-3z"></path>
                            </g>
                        </svg>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 font-medium">Delivered</p>
                        <p class="text-3xl font-bold text-green-600 mt-1">{{ stats.delivered }}</p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center">
                        <svg class="w-7 h-7" viewBox="0 0 50 50" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path fill="#020750" d="M8.1875 0C6.421875 0 5 1.421875 5 3.1875L5 10.90625L5.8125 10.90625C7.03125 10.90625 8.117188 11.476563 8.90625 12.34375C9.675781 8.886719 14.632813 7.1875 24 7.1875C34.558594 7.1875 40.292969 8.992188 41.09375 12.5C41.878906 11.605469 43.03125 11.03125 44.3125 11.03125L45 11.03125L45 3.1875C45 1.421875 43.507813 0 41.6875 0 Z M 24 9.1875C19.160156 9.1875 11.011719 9.710938 10.8125 13.15625L10.09375 18.6875L9.53125 22.78125L9.125 26L8.1875 26C7.855469 26 7.5625 26.039063 7.25 26.09375C7.085938 26.125 6.90625 26.171875 6.75 26.21875C6.625 26.253906 6.496094 26.300781 6.375 26.34375C6.164063 26.421875 5.976563 26.492188 5.78125 26.59375C5.734375 26.617188 5.703125 26.664063 5.65625 26.6875C5.433594 26.816406 5.203125 26.9375 5 27.09375C4.722656 27.3125 4.480469 27.578125 4.25 27.84375C4.207031 27.894531 4.164063 27.917969 4.125 27.96875C3.988281 28.144531 3.863281 28.335938 3.75 28.53125C3.664063 28.675781 3.570313 28.84375 3.5 29C3.453125 29.101563 3.417969 29.207031 3.375 29.3125C3.28125 29.558594 3.183594 29.796875 3.125 30.0625C3.109375 30.125 3.105469 30.1875 3.09375 30.25C3.039063 30.554688 3 30.863281 3 31.1875L3 38L28.0625 38C28.03125 38.328125 28 38.664063 28 39C28 39.335938 28.03125 39.671875 28.0625 40L3 40L3 45L29.78125 45C31.746094 48.011719 35.144531 50 39 50C39.210938 50 39.417969 49.980469 39.625 49.96875C39.746094 49.984375 39.875 50 40 50L44 50C45.652344 50 47 48.652344 47 47L46.53125 47C48.664063 44.992188 50 42.152344 50 39C50 36.089844 48.855469 33.4375 47 31.46875L47 31.1875C47 30.863281 46.960938 30.554688 46.90625 30.25C46.894531 30.1875 46.886719 30.125 46.875 30.0625C46.816406 29.796875 46.75 29.558594 46.65625 29.3125C46.617188 29.207031 46.546875 29.101563 46.5 29C46.429688 28.84375 46.367188 28.675781 46.28125 28.53125C46.167969 28.339844 46.015625 28.144531 45.875 27.96875C45.835938 27.914063 45.789063 27.890625 45.75 27.84375C45.519531 27.574219 45.28125 27.308594 45 27.09375C44.796875 26.9375 44.570313 26.816406 44.34375 26.6875C44.296875 26.664063 44.265625 26.617188 44.21875 26.59375C44.023438 26.488281 43.835938 26.421875 43.625 26.34375C43.503906 26.300781 43.378906 26.253906 43.25 26.21875C43.09375 26.171875 42.914063 26.125 42.75 26.09375C42.4375 26.035156 42.140625 26 41.8125 26L40.875 26L40.4375 22.59375L40.03125 19.46875L39.1875 13.25C38.957031 9.402344 26.496094 9.1875 24 9.1875 Z M 24 11.1875C32.429688 11.1875 36.839844 12.683594 37.21875 13.4375L38.21875 21.125C36.738281 21.375 32.34375 22 25 22C17.65625 22 13.261719 21.378906 11.78125 21.125L12.8125 13.34375C12.835938 12.914063 14.894531 11.1875 24 11.1875 Z M 4.1875 12.90625C2.992188 12.90625 1.90625 13.992188 1.90625 15.1875L1.90625 20.6875C1.90625 21.957031 2.972656 23.09375 4.1875 23.09375L5.6875 23.09375C6.957031 23.09375 8.09375 22.03125 8.09375 20.8125L8.09375 15.3125C8.09375 14.042969 7.027344 12.90625 5.8125 12.90625 Z M 44.3125 13.03125C43.054688 13.03125 42.03125 14.054688 42.03125 15.3125L42.03125 20.8125C42.03125 22.074219 43.054688 23.09375 44.3125 23.09375L45.8125 23.09375C47.007813 23.09375 48.09375 22.007813 48.09375 20.8125L48.09375 15.3125C48.09375 14.054688 47.070313 13.03125 45.8125 13.03125 Z M 19.59375 27L30.40625 27C31.09375 27 31.644531 27.40625 31.875 28C31.949219 28.1875 32 28.378906 32 28.59375L32 30.53125C30.566406 31.714844 29.472656 33.242188 28.78125 35L19.59375 35C18.695313 35 18 34.304688 18 33.40625L18 28.59375C18 28.378906 18.054688 28.1875 18.125 28C18.355469 27.40625 18.90625 27 19.59375 27 Z M 39 30C43.980469 30 48 34.019531 48 39C48 43.980469 43.980469 48 39 48C34.019531 48 30 43.980469 30 39C30 34.019531 34.019531 30 39 30 Z M 8.6875 31L13.3125 31C13.710938 31 14 31.289063 14 31.6875L14 34.3125C14 34.710938 13.710938 35 13.3125 35L8.6875 35C8.289063 35 8 34.710938 8 34.3125L8 31.6875C8 31.289063 8.289063 31 8.6875 31 Z M 44 35.34375L38.3125 41.03125L34.75 37.46875L33.3125 38.875L37.625 43.15625L38.3125 43.875L39.03125 43.15625L45.4375 36.78125 Z M 3 47C3 48.652344 4.347656 50 6 50L10 50C11.652344 50 13 48.652344 13 47Z"></path>
                        </svg>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 font-medium">In Transit</p>
                        <p class="text-3xl font-bold text-blue-600 mt-1">{{ stats.inTransit }}</p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center">
                        <svg class="w-7 h-7" viewBox="0 0 14 14" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path fill="#ffc107" d="M12 9.75H8.5v-5c0-.275.225-.5.5-.5h2c.15 0 .3.075.4.2l1.5 1.925c.075.1.1.2.1.3V8.75c0 .55-.45 1-1 1z"></path>
                            <g fill="#9575cd">
                                <path d="M3 9.75h5.5V4c0-.55-.45-1-1-1H2v5.75c0 .55.45 1 1 1z"></path>
                                <path d="M1 3h2.5v.5H1zm0 1.25h2.5v.5H1zM1 5.5h2.5V6H1zm0 1.25h2.5v.5H1z"></path>
                            </g>
                            <path fill="#7e57c2" d="M2 3.5h4V4H2zm0 1.25h3v.5H2zM2 6h2v.5H2zm0 1.25h1v.5H2z"></path>
                            <g fill="#37474f" transform="matrix(.25 0 0 .25 1 .75)">
                                <circle cx="39" cy="36" r="5"></circle>
                                <circle cx="16" cy="36" r="5"></circle>
                            </g>
                            <g fill="#78909c" transform="matrix(.25 0 0 .25 1 .75)">
                                <circle cx="39" cy="36" r="2.5"></circle>
                                <circle cx="16" cy="36" r="2.5"></circle>
                            </g>
                            <path fill="#455a64" d="M12 7.25h-.9c-.075 0-.125-.025-.175-.075l-.35-.35c-.05-.05-.1-.075-.175-.075h-.9c-.15 0-.25-.1-.25-.25V5c0-.15.1-.25.25-.25h1.375c.075 0 .15.025.2.1L12.2 6.2c.025.05.05.1.05.15V7c0 .15-.1.25-.25.25z"></path>
                        </svg>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 font-medium">Pending</p>
                        <p class="text-3xl font-bold text-yellow-600 mt-1">{{ stats.pending }}</p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-yellow-100 flex items-center justify-center">
                        <Clock class="w-6 h-6 text-yellow-600" />
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 font-medium">Cancelled</p>
                        <p class="text-3xl font-bold text-red-600 mt-1">{{ stats.cancelled }}</p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-red-100 flex items-center justify-center">
                        <svg class="w-8 h-8" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path style="fill:#FF6465;" d="M256.002,503.671c136.785,0,247.671-110.886,247.671-247.672S392.786,8.329,256.002,8.329S8.33,119.215,8.33,256.001S119.216,503.671,256.002,503.671z"></path>
                            <path style="opacity:0.1;enable-background:new;" d="M74.962,256.001c0-125.485,93.327-229.158,214.355-245.434c-10.899-1.466-22.016-2.238-33.316-2.238C119.216,8.329,8.33,119.215,8.33,256.001s110.886,247.672,247.671,247.672c11.3,0,22.417-0.772,33.316-2.238C168.289,485.159,74.962,381.486,74.962,256.001z"></path>
                            <path style="fill:#FFFFFF;" d="M311.525,256.001l65.206-65.206c4.74-4.74,4.74-12.425,0-17.163l-38.36-38.36c-4.74-4.74-12.425-4.74-17.164,0l-65.206,65.206l-65.206-65.206c-4.74-4.74-12.425-4.74-17.163,0l-38.36,38.36c-4.74,4.74-4.74,12.425,0,17.163l65.206,65.206l-65.206,65.206c-4.74,4.74-4.74,12.425,0,17.164l38.36,38.36c4.74,4.74,12.425,4.74,17.163,0l65.206-65.206l65.206,65.206c4.74,4.74,12.425,4.74,17.164,0l38.36-38.36c4.74-4.74,4.74-12.425,0-17.164L311.525,256.001z"></path>
                            <path d="M388.614,182.213c0-5.467-2.129-10.607-5.995-14.471l-38.36-38.36c-3.865-3.865-9.004-5.994-14.471-5.994s-10.605,2.129-14.471,5.994l-59.316,59.316l-59.316-59.316c-3.865-3.865-9.004-5.994-14.471-5.994c-5.467,0-10.606,2.129-14.471,5.994l-38.36,38.36c-7.979,7.979-7.979,20.962,0,28.943l59.316,59.316l-59.316,59.316c-7.979,7.979-7.979,20.962,0,28.943l38.36,38.36c3.865,3.865,9.004,5.993,14.471,5.993c5.467,0,10.606-2.129,14.471-5.993l59.316-59.316l59.316,59.316c3.865,3.865,9.004,5.993,14.471,5.993s10.605-2.129,14.471-5.993l38.36-38.36c3.866-3.865,5.995-9.004,5.995-14.471c0-5.467-2.129-10.607-5.995-14.471l-59.315-59.316l59.315-59.315C386.485,192.818,388.614,187.68,388.614,182.213z M370.84,184.905l-65.204,65.206c-3.253,3.253-3.253,8.527,0,11.778l65.204,65.207c0.971,0.971,1.115,2.103,1.115,2.692c0,0.589-0.144,1.721-1.115,2.692l-38.36,38.36c-0.971,0.971-2.103,1.115-2.692,1.115c-0.589,0-1.722-0.144-2.692-1.115l-65.206-65.206c-1.626-1.626-3.758-2.44-5.889-2.44c-2.131,0-4.263,0.813-5.889,2.44l-65.206,65.206c-0.971,0.971-2.103,1.115-2.692,1.115c-0.59,0-1.722-0.144-2.693-1.115l-38.36-38.36c-1.484-1.485-1.484-3.9,0-5.385l65.206-65.206c3.253-3.253,3.253-8.527,0-11.778l-65.206-65.206c-1.484-1.485-1.484-3.9,0-5.385l38.359-38.36c0.971-0.971,2.104-1.115,2.693-1.115s1.722,0.144,2.692,1.115l65.206,65.206c3.253,3.253,8.527,3.253,11.778,0l65.206-65.206c0.971-0.971,2.103-1.115,2.692-1.115c0.589,0,1.722,0.144,2.692,1.115l38.36,38.36c0.971,0.971,1.115,2.103,1.115,2.692S371.811,183.934,370.84,184.905z"></path>
                            <path d="M423.9,73.756c-3.229,3.276-3.191,8.55,0.086,11.778c46.016,45.349,71.358,105.89,71.358,170.466c0,63.931-24.896,124.035-70.102,169.241s-105.31,70.102-169.241,70.102c-35.385,0-69.471-7.555-101.311-22.455c-4.166-1.95-9.124-0.153-11.074,4.013c-1.95,4.166-0.153,9.124,4.013,11.074C181.695,503.917,218.156,512,255.999,512c68.381,0,132.668-26.629,181.019-74.982c48.352-48.352,74.98-112.64,74.98-181.019c0-69.072-27.106-133.825-76.323-182.331C432.401,70.44,427.128,70.478,423.9,73.756z"></path>
                            <path d="M116.34,470.563c1.405,0.916,2.982,1.354,4.542,1.354c2.72,0,5.387-1.332,6.984-3.78c2.513-3.852,1.427-9.013-2.426-11.526c-68.115-44.424-108.78-119.419-108.78-200.611c0-63.931,24.896-124.035,70.102-169.24c45.206-45.206,105.31-70.102,169.241-70.102c52.234,0,101.864,16.528,143.525,47.796c3.679,2.761,8.9,2.017,11.66-1.662c2.761-3.679,2.017-8.9-1.662-11.661C364.958,17.681,311.87,0,256.002,0c-68.38,0-132.668,26.629-181.019,74.98C26.63,123.333,0.001,187.62,0.001,255.999C0.001,342.841,43.493,423.051,116.34,470.563z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
            <div class="flex flex-col md:flex-row gap-4">
                <div class="flex-1 relative">
                    <input 
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search by ID, driver, destination, or customer..."
                        class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#34554a] focus:border-transparent outline-none"
                    >
                    <Search class="w-4 h-4 text-gray-400 absolute left-3 top-3" />
                </div>
                <select 
                    v-model="statusFilter"
                    class="px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#34554a] focus:border-transparent outline-none bg-white min-w-[150px]"
                >
                    <option value="">All Status</option>
                    <option value="delivered">Delivered</option>
                    <option value="in_transit">In Transit</option>
                    <option value="pending">Pending</option>
                    <option value="cancelled">Cancelled</option>
                </select>
                <!-- Driver Filter for Admins -->
                <select 
                    v-if="!isDriver"
                    v-model="driverFilter"
                    @change="handleDriverFilterChange"
                    class="px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#34554a] focus:border-transparent outline-none bg-white min-w-[150px]"
                >
                    <option value="">All Drivers</option>
                    <option v-for="driver in drivers" :key="driver.id" :value="driver.id">
                        {{ driver.name }}
                    </option>
                </select>
            </div>
        </div>

        <div v-if="bulkMode" class="mb-4 flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3">
            <span class="text-sm font-semibold text-[#34554a]">{{ selectedIds.length }} selected</span>
            <button
                @click="promptBulkDelete"
                :disabled="selectedIds.length === 0"
                class="flex items-center gap-2 rounded-lg bg-red-600 px-3 py-2 text-sm font-medium text-white transition-colors hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
            >
                <Trash2 class="w-4 h-4" />
                Delete Selected
            </button>
            <button
                @click="toggleBulkMode"
                class="ml-auto flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-50"
            >
                <X class="w-4 h-4" />
                Cancel
            </button>
        </div>

        <!-- Delivery Table -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left whitespace-nowrap">
                    <thead>
                        <tr class="bg-[#34554a] text-white text-sm">
                            <th v-if="bulkMode" class="px-6 py-4 font-semibold w-12 text-center">
                                <input
                                    type="checkbox"
                                    :checked="allSelected"
                                    @change="toggleSelectAll"
                                    class="w-4 h-4 rounded border-gray-300 text-[#34554a] focus:ring-[#34554a] cursor-pointer"
                                >
                            </th>
                            <th class="px-6 py-4 font-semibold">Delivery ID</th>
                            <th class="px-6 py-4 font-semibold">Date & Time</th>
                            <th class="px-6 py-4 font-semibold">Driver</th>
                            <th class="px-6 py-4 font-semibold">Route</th>
                            <th class="px-6 py-4 font-semibold">Cargo</th>
                            <th class="px-6 py-4 font-semibold">Status</th>
                            <th class="px-6 py-4 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm text-gray-700">
                        <tr 
                            v-for="delivery in paginatedDeliveries" 
                            :key="delivery.db_id"
                            :class="selectedIds.includes(delivery.db_id) ? 'bg-blue-50 transition-colors group' : 'hover:bg-gray-50 transition-colors group'"
                        >
                            <td v-if="bulkMode" class="px-6 py-4 text-center">
                                <input
                                    type="checkbox"
                                    :checked="selectedIds.includes(delivery.db_id)"
                                    @change="toggleSelectDelivery(delivery.db_id)"
                                    class="w-4 h-4 rounded border-gray-300 text-[#34554a] focus:ring-[#34554a] cursor-pointer"
                                >
                            </td>
                            <td class="px-6 py-4">
                                <p class="font-medium text-gray-900">{{ delivery.id }}</p>
                                <p class="text-sm text-gray-500">{{ delivery.customer }}</p>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <Calendar class="w-4 h-4 text-gray-400" />
                                    <div>
                                        <p class="font-medium text-gray-900">{{ delivery.date }}</p>
                                        <p class="text-sm text-gray-500 flex items-center gap-1">
                                            <Clock class="w-3 h-3" /> {{ delivery.time }}
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <p class="font-medium text-gray-900">{{ delivery.driver }}</p>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-gray-900 line-clamp-1 max-w-[150px]">{{ delivery.origin }}</p>
                                <p class="text-sm text-gray-500 flex items-center gap-1">
                                    <MapPin class="w-3 h-3" /> to {{ delivery.destination }}
                                </p>
                            </td>
                            <td class="px-6 py-4">
                                <p class="font-medium text-gray-900">{{ delivery.cargo_type }}</p>
                                <p class="text-sm text-gray-500">{{ delivery.cargo_weight }}</p>
                            </td>
                            <td class="px-6 py-4">
                                <span :class="['px-2.5 py-0.5 rounded-full text-xs font-medium border', getStatusClass(delivery.status)]">
                                    {{ formatStatus(delivery.status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <button 
                                        @click="openDetailsModal(delivery)"
                                        class="w-8 h-8 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded flex items-center justify-center transition-colors"
                                        title="View Details"
                                    >
                                        <Eye class="w-4 h-4" />
                                    </button>
                                    <button 
                                        @click="openEditModal(delivery)"
                                        class="w-8 h-8 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded flex items-center justify-center transition-colors"
                                        title="Edit Record"
                                    >
                                        <Edit class="w-4 h-4" />
                                    </button>
                                    <button 
                                        @click="promptDelete(delivery)"
                                        class="w-8 h-8 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded flex items-center justify-center transition-colors"
                                        title="Delete Record"
                                    >
                                        <Trash2 class="w-4 h-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col md:flex-row justify-between items-center p-4 border-t border-gray-100 bg-gray-50">
                <div class="flex items-center gap-2 mb-4 md:mb-0">
                    <button
                        @click="previousPage"
                        :disabled="currentPage === 1"
                        :class="currentPage === 1 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-white'"
                        class="w-8 h-8 flex items-center justify-center border border-gray-200 rounded-lg text-gray-500"
                    >
                        <ChevronLeft class="w-4 h-4" />
                    </button>

                    <button
                        v-for="page in pageNumbers"
                        :key="page"
                        @click="page !== '...' && goToPage(page)"
                        :class="[
                            page === currentPage ? 'bg-[#34554a] text-white' : 'text-gray-600 hover:bg-white',
                            page === '...' ? 'cursor-default' : ''
                        ]"
                        class="w-8 h-8 flex items-center justify-center rounded-lg text-sm font-bold transition-colors"
                    >
                        {{ page }}
                    </button>

                    <button
                        @click="nextPage"
                        :disabled="currentPage === totalPages || totalPages === 0"
                        :class="currentPage === totalPages || totalPages === 0 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-white'"
                        class="w-8 h-8 flex items-center justify-center border border-gray-200 rounded-lg text-gray-500"
                    >
                        <ChevronRight class="w-4 h-4" />
                    </button>
                </div>

                <div class="text-sm text-gray-500">
                    Showing <span class="font-semibold text-gray-800">{{ filteredDeliveries.length > 0 ? (currentPage - 1) * itemsPerPage + 1 : 0 }}-{{ Math.min(currentPage * itemsPerPage, filteredDeliveries.length) }}</span> of <span class="font-semibold text-gray-800">{{ filteredDeliveries.length }}</span> records
                </div>
            </div>
            
            <!-- Empty State -->
            <div v-if="filteredDeliveries.length === 0" class="p-12 text-center">
                <MapPin class="w-12 h-12 text-gray-300 mx-auto mb-4" />
                <h3 class="text-lg font-medium text-gray-900 mb-2">No Deliveries Found</h3>
                <p class="text-gray-500">Add a new delivery record to get started</p>
            </div>
        </div>

        <!-- Add/Edit Modal -->
        <Teleport to="body">
            <div 
                v-if="showAddModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
                @click.self="closeModals"
            >
                <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden">
                    <div class="flex justify-between items-center p-5 border-b border-gray-100 bg-gray-50">
                        <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                            <Truck class="w-5 h-5 text-[#34554a]" />
                            {{ isEditing ? 'Edit Delivery Record' : 'Add New Delivery' }}
                        </h3>
                        <button 
                            @click="closeModals"
                            class="text-gray-400 hover:text-gray-600 w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-200 transition-colors"
                        >
                            ✕
                        </button>
                    </div>
                    <form @submit.prevent="submitForm" class="flex flex-col max-h-[90vh]">
                        <div class="px-6 py-4 overflow-y-auto flex-1 custom-scrollbar">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Driver</label>
                                    <select 
                                        v-model="deliveryForm.user_id"
                                        required
                                        :disabled="isDriver"
                                        class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#34554a] focus:border-transparent outline-none bg-white disabled:bg-gray-50 disabled:text-gray-500"
                                    >
                                        <option value="" disabled>Select a driver</option>
                                        <option v-for="driver in drivers" :key="driver.id" :value="driver.id">
                                            {{ driver.name }}
                                        </option>
                                    </select>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Customer Name</label>
                                    <input 
                                        v-model="deliveryForm.customer"
                                        type="text"
                                        required
                                        placeholder="Enter customer or company name"
                                        class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#34554a] focus:border-transparent outline-none"
                                    >
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Date</label>
                                    <input 
                                        v-model="deliveryForm.date"
                                        type="date"
                                        required
                                        class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#34554a] focus:border-transparent outline-none"
                                    >
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Time</label>
                                    <input 
                                        v-model="deliveryForm.time"
                                        type="text"
                                        placeholder="08:30 AM"
                                        required
                                        class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#34554a] focus:border-transparent outline-none"
                                    >
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Vehicle</label>
                                    <input 
                                        v-model="deliveryForm.vehicle"
                                        type="text"
                                        required
                                        placeholder="e.g. Truck 001 - Volvo FH16"
                                        class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#34554a] focus:border-transparent outline-none"
                                    >
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Origin</label>
                                    <input 
                                        v-model="deliveryForm.origin"
                                        type="text"
                                        required
                                        placeholder="Enter starting location"
                                        class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#34554a] focus:border-transparent outline-none"
                                    >
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Destination</label>
                                    <input 
                                        v-model="deliveryForm.destination"
                                        type="text"
                                        required
                                        placeholder="Enter end destination"
                                        class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#34554a] focus:border-transparent outline-none"
                                    >
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Cargo Type</label>
                                    <input 
                                        v-model="deliveryForm.cargo_type"
                                        type="text"
                                        required
                                        placeholder="e.g. Fresh Fruit Bunch (FFB)"
                                        class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#34554a] focus:border-transparent outline-none"
                                    >
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Cargo Weight</label>
                                    <input 
                                        v-model="deliveryForm.cargo_weight"
                                        type="text"
                                        required
                                        placeholder="e.g. 15.2 MT"
                                        class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#34554a] focus:border-transparent outline-none"
                                    >
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Status</label>
                                    <select 
                                        v-model="deliveryForm.status"
                                        required
                                        class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#34554a] focus:border-transparent outline-none bg-white"
                                    >
                                        <option value="pending">Pending</option>
                                        <option value="in_transit">In Transit</option>
                                        <option value="delivered">Delivered</option>
                                        <option value="cancelled">Cancelled</option>
                                    </select>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Delivery Notes</label>
                                    <textarea 
                                        v-model="deliveryForm.delivery_notes"
                                        rows="3"
                                        placeholder="Add any specific notes about the delivery"
                                        class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#34554a] focus:border-transparent outline-none resize-none"
                                    ></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="px-6 py-4 flex justify-end gap-3 border-t border-gray-100 bg-gray-50">
                            <button 
                                type="button"
                                @click="closeModals"
                                class="px-5 py-2.5 border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50 font-medium"
                            >
                                Cancel
                            </button>
                            <button 
                                type="submit"
                                :disabled="deliveryForm.processing"
                                class="px-5 py-2.5 bg-[#34554a] text-white rounded-lg font-medium hover:bg-[#2c463d] flex items-center gap-2 disabled:opacity-50"
                            >
                                <Save class="w-4 h-4" />
                                {{ isEditing ? 'Update Record' : 'Save Delivery' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Delivery Details Modal -->
        <Teleport to="body">
            <div 
                v-if="showDetailsModal && selectedDelivery"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
                @click.self="closeModals"
            >
                <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden">
                    <div class="flex justify-between items-center p-5 border-b border-gray-100 bg-gray-50">
                        <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                            <Truck class="w-5 h-5 text-[#34554a]" />
                            Delivery Details - {{ selectedDelivery.id }}
                        </h3>
                        <button 
                            @click="closeModals"
                            class="text-gray-400 hover:text-gray-600 w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-200 transition-colors"
                        >
                            ✕
                        </button>
                    </div>
                    <div class="flex flex-col max-h-[90vh]">
                        <div class="p-6 overflow-y-auto flex-1 custom-scrollbar">
                            <!-- Status -->
                            <div class="mb-6">
                                <span :class="['px-4 py-2 rounded-full text-sm font-medium border', getStatusClass(selectedDelivery.status)]">
                                    {{ formatStatus(selectedDelivery.status) }}
                                </span>
                            </div>

                            <div class="grid grid-cols-2 gap-4 mb-6">
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <p class="text-xs text-gray-500 mb-1">Driver</p>
                                    <p class="font-medium text-gray-900 flex items-center gap-2">
                                        <User class="w-4 h-4 text-gray-400" /> {{ selectedDelivery.driver }}
                                    </p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <p class="text-xs text-gray-500 mb-1">Vehicle</p>
                                    <p class="font-medium text-gray-900 flex items-center gap-2">
                                        <Truck class="w-4 h-4 text-gray-400" /> {{ selectedDelivery.vehicle }}
                                    </p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <p class="text-xs text-gray-500 mb-1">Date & Time</p>
                                    <p class="font-medium text-gray-900 flex items-center gap-2">
                                        <Calendar class="w-4 h-4 text-gray-400" /> {{ selectedDelivery.date }} at {{ selectedDelivery.time }}
                                    </p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <p class="text-xs text-gray-500 mb-1">Customer</p>
                                    <p class="font-medium text-gray-900">{{ selectedDelivery.customer }}</p>
                                </div>
                            </div>

                            <!-- Route -->
                            <div class="bg-white border border-gray-200 rounded-lg p-4 mb-6 relative">
                                <p class="text-xs text-gray-500 mb-3">Route</p>
                                <div class="flex flex-col gap-4">
                                    <div class="relative pl-6">
                                        <div class="absolute left-1.5 top-2 bottom-2 w-0.5 bg-gray-200"></div>
                                        <div class="absolute left-0 top-1.5 w-3 h-3 rounded-full border-2 border-[#34554a] bg-white"></div>
                                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Origin</p>
                                        <p class="text-sm font-medium text-gray-900">{{ selectedDelivery.origin }}</p>
                                    </div>
                                    <div class="relative pl-6">
                                        <div class="absolute left-0 top-1.5 w-3 h-3 rounded-full bg-[#34554a]"></div>
                                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Destination</p>
                                        <p class="text-sm font-medium text-gray-900">{{ selectedDelivery.destination }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Cargo Info -->
                            <div class="grid grid-cols-2 gap-4 mb-6">
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <p class="text-xs text-gray-500 mb-1">Cargo Type</p>
                                    <p class="font-medium text-gray-900">{{ selectedDelivery.cargo_type }}</p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <p class="text-xs text-gray-500 mb-1">Cargo Weight</p>
                                    <p class="font-medium text-gray-900">{{ selectedDelivery.cargo_weight }}</p>
                                </div>
                            </div>

                            <!-- Notes -->
                            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                <p class="text-xs text-yellow-600 mb-1 font-medium">Delivery Notes</p>
                                <p class="text-sm text-gray-900">{{ selectedDelivery.delivery_notes || 'No notes available.' }}</p>
                            </div>
                        </div>

                        <div class="px-6 py-4 flex justify-end gap-3 border-t border-gray-100 bg-gray-50">
                            <button 
                                @click="openEditModal(selectedDelivery)"
                                class="px-5 py-2.5 border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50 font-medium flex items-center gap-2"
                            >
                                <Edit class="w-4 h-4" />
                                Edit Record
                            </button>
                            <button 
                                @click="closeModals"
                                class="px-5 py-2.5 bg-[#34554a] text-white rounded-lg font-medium hover:bg-[#2c463d]"
                            >
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <DeleteConfirmationModal
            :show="showDeleteModal"
            item-name="delivery record"
            @close="showDeleteModal = false; deliveryToDelete = null"
            @confirm="confirmDelete"
        />

        <DeleteConfirmationModal
            :show="showBulkDeleteModal"
            item-name="selected delivery records"
            @close="showBulkDeleteModal = false"
            @confirm="confirmBulkDelete"
        />
    </AppLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: #f9fafb;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}
</style>
