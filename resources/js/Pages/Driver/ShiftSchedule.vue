<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { 
    Calendar, Clock, ChevronLeft, ChevronRight,
    Truck, User, Plus, MapPin, Edit, Trash2
} from 'lucide-vue-next';

const props = defineProps({
    deliveries: {
        type: Array,
        default: () => []
    }
});

const currentDate = ref(new Date());
const selectedDate = ref(new Date());
const showDetailsModal = ref(false);
const selectedDelivery = ref(null);

const currentMonth = computed(() => {
    return currentDate.value.toLocaleString('default', { month: 'long', year: 'numeric' });
});

const daysInMonth = computed(() => {
    const year = currentDate.value.getFullYear();
    const month = currentDate.value.getMonth();
    const days = new Date(year, month + 1, 0).getDate();
    const firstDay = new Date(year, month, 1).getDay();
    
    return { days, firstDay };
});

const scheduleData = computed(() => {
    const data = {};
    const year = currentDate.value.getFullYear();
    const month = currentDate.value.getMonth();
    
    props.deliveries.forEach(d => {
        const dDate = new Date(d.date);
        // Normalize dates to handle potential timezone issues if needed, but YYYY-MM-DD is usually safe with new Date(dateString)
        if (dDate.getFullYear() === year && dDate.getMonth() === month) {
            const day = dDate.getDate();
            if (!data[day]) data[day] = [];
            data[day].push({
                id: d.id,
                driver: d.driver?.name || d.driver || 'Unknown',
                origin: d.origin,
                destination: d.destination,
                time: d.time,
                cargo_type: d.cargo_type
            });
        }
    });
    return data;
});

const getScheduleForDay = (day) => {
    return scheduleData.value[day] || [];
};

const prevMonth = () => {
    currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() - 1, 1);
};

const nextMonth = () => {
    currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() + 1, 1);
};

const getDayClass = (day) => {
    const schedules = getScheduleForDay(day);
    if (schedules.length === 0) return '';
    
    if (schedules.length === 1) return 'bg-green-50';
    if (schedules.length === 2) return 'bg-yellow-50';
    return 'bg-red-50';
};

const todayShifts = computed(() => {
    const today = new Date();
    const year = today.getFullYear();
    const month = today.getMonth();
    const day = today.getDate();
    
    return props.deliveries.filter(d => {
        const dDate = new Date(d.date);
        return dDate.getFullYear() === year && 
               dDate.getMonth() === month && 
               dDate.getDate() === day;
    });
});

const selectedDateDeliveries = computed(() => {
    if (!selectedDate.value) return [];
    const year = selectedDate.value.getFullYear();
    const month = selectedDate.value.getMonth();
    const day = selectedDate.value.getDate();
    
    return props.deliveries.filter(d => {
        const dDate = new Date(d.date);
        return dDate.getFullYear() === year && 
               dDate.getMonth() === month && 
               dDate.getDate() === day;
    });
});

const selectDate = (day) => {
    selectedDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth(), day);
};

const openDetailsModal = (deliveryId) => {
    // We need to find the delivery record in the original props.deliveries
    // since scheduleData only contains some fields
    selectedDelivery.value = props.deliveries.find(d => d.id === deliveryId || d.db_id === deliveryId);
    showDetailsModal.value = true;
};

const closeModals = () => {
    showDetailsModal.value = false;
    selectedDelivery.value = null;
};


</script>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #e2e8f0;
    border-radius: 10px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #cbd5e1;
}
</style>

<template>
    <Head title="Shift Schedule" />

    <AppLayout title="Shift Schedule" parent="Driver" parentUrl="/driver">
        <!-- Page Header -->
        <div class="mb-8 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Shift Schedule</h1>
                <p class="text-sm text-gray-500 mt-1">View driver shifts and schedules based on delivery history.</p>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <!-- Calendar Section -->
            <div class="lg:col-span-3 bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <!-- Calendar Header -->
                <div class="flex items-center justify-between p-5 border-b border-gray-100 bg-gray-50">
                    <button 
                        @click="prevMonth"
                        class="p-2 hover:bg-gray-200 rounded-lg transition-colors"
                    >
                        <ChevronLeft class="w-5 h-5 text-gray-600" />
                    </button>
                    <h2 class="text-lg font-bold text-gray-900">{{ currentMonth }}</h2>
                    <button 
                        @click="nextMonth"
                        class="p-2 hover:bg-gray-200 rounded-lg transition-colors"
                    >
                        <ChevronRight class="w-5 h-5 text-gray-600" />
                    </button>
                </div>

                <!-- Calendar Grid -->
                <div class="p-5">
                    <!-- Weekday Headers -->
                    <div class="grid grid-cols-7 gap-2 mb-2">
                        <div v-for="day in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']" :key="day" class="text-center text-xs font-bold text-gray-500 uppercase py-2">
                            {{ day }}
                        </div>
                    </div>

                    <!-- Calendar Days -->
                    <div class="grid grid-cols-7 gap-2">
                        <!-- Empty cells for days before month starts -->
                        <div 
                            v-for="n in daysInMonth.firstDay" 
                            :key="'empty-' + n"
                            class="h-40 bg-gray-50 rounded-lg"
                        ></div>

                        <!-- Calendar days -->
                        <div 
                            v-for="day in daysInMonth.days" 
                            :key="day"
                            :class="[
                                'h-40 rounded-lg border p-2 hover:border-[#34554a] transition-colors cursor-pointer', 
                                getDayClass(day),
                                selectedDate && day === selectedDate.getDate() ? 'border-[#34554a] ring-1 ring-[#34554a]' : 'border-gray-100'
                            ]"
                            @click="selectDate(day)"
                        >
                            <div class="flex justify-between items-start mb-2">
                                <span :class="['text-sm font-medium', selectedDate && day === selectedDate.getDate() ? 'text-[#34554a] font-bold' : 'text-gray-900']">{{ day }}</span>
                            </div>
                            <div class="space-y-1 overflow-y-auto max-h-32 custom-scrollbar pr-0.5">
                                <div 
                                    v-for="schedule in getScheduleForDay(day)" 
                                    :key="schedule.id"
                                    class="text-[9px] p-1 rounded bg-white border border-gray-100 shadow-sm hover:border-[#34554a]/30 transition-colors leading-tight"
                                    @click.stop="openDetailsModal(schedule.id)"
                                >
                                    <p class="font-bold text-[#34554a] truncate">{{ schedule.driver }}</p>
                                    <p class="text-gray-500 truncate flex items-center gap-0.5 mt-0.5">
                                        <MapPin class="w-2 h-2" /> {{ schedule.origin }} → {{ schedule.destination }}
                                    </p>
                                    <p class="text-gray-400 font-medium mt-0.5">{{ schedule.time }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Sidebar -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden flex flex-col h-full min-h-[500px]">
                <div class="p-4 border-b border-gray-100 bg-gray-50">
                    <h3 class="text-sm font-bold text-gray-900 flex items-center gap-2">
                        <Calendar class="w-4 h-4 text-[#34554a]" />
                        {{ selectedDate ? selectedDate.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' }) : 'Select a date' }}
                    </h3>
                </div>
                <div class="p-4 flex-1 overflow-y-auto custom-scrollbar">
                    <div v-if="selectedDateDeliveries.length" class="space-y-4">
                        <div 
                            v-for="delivery in selectedDateDeliveries" 
                            :key="delivery.id"
                            class="p-4 rounded-xl border border-gray-100 hover:border-[#34554a]/30 hover:shadow-md transition-all cursor-pointer bg-white group"
                            @click="openDetailsModal(delivery.db_id || delivery.id)"
                        >
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    <div class="w-1.5 h-1.5 rounded-full bg-[#34554a]"></div>
                                    <span class="text-[9px] font-bold uppercase tracking-wider text-gray-400">
                                        {{ delivery.time }}
                                    </span>
                                </div>
                                <button class="text-gray-300 group-hover:text-[#34554a] transition-colors">
                                    <Eye class="w-3.5 h-3.5" />
                                </button>
                            </div>
                            <p class="font-bold text-gray-900 text-[13px] mb-1 group-hover:text-[#34554a] transition-colors line-clamp-1">{{ delivery.driver?.name || delivery.driver }}</p>
                            <div class="space-y-1">
                                <p class="text-[10px] text-gray-500 flex items-center gap-1.5">
                                    <MapPin class="w-2.5 h-2.5 text-gray-300" />
                                    <span class="font-medium">{{ delivery.origin }}</span>
                                </p>
                                <p class="text-[10px] text-gray-500 flex items-center gap-1.5 ml-1">
                                    <span class="text-gray-300 text-[8px]">↓</span>
                                    <span class="font-bold text-[#34554a]">{{ delivery.destination }}</span>
                                </p>
                            </div>
                            <div class="mt-2.5 pt-2.5 border-t border-gray-50 flex justify-between items-center text-[9px]">
                                <span class="px-1.5 py-0.5 rounded-full bg-gray-100 text-gray-600 font-medium">
                                    {{ delivery.cargo_type }}
                                </span>
                                <span class="font-bold text-gray-300 uppercase tracking-widest text-[8px]">
                                    {{ delivery.id }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-12 px-6">
                        <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4 border border-gray-100">
                            <Truck class="w-8 h-8 text-gray-200" />
                        </div>
                        <p class="text-sm font-bold text-gray-900">No deliveries</p>
                        <p class="text-xs text-gray-500 mt-1">No deliveries scheduled for this date.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Today's Deliveries Section (Moved below grid) -->
        <div class="mt-8 bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="p-5 bg-[#34554a]">
                <h3 class="text-lg font-semibold text-white">Today's Deliveries</h3>
            </div>
            <div class="divide-y divide-gray-100">
                <div 
                    v-for="delivery in todayShifts" 
                    :key="delivery.id"
                    class="p-4 flex items-center justify-between hover:bg-gray-50"
                >
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-full bg-[#34554a]/10 flex items-center justify-center text-[#34554a]">
                            <Truck class="w-5 h-5" />
                        </div>
                        <div>
                            <p class="font-medium text-gray-900">{{ delivery.driver?.name || delivery.driver }}</p>
                            <div class="flex items-center gap-3 text-sm text-gray-500">
                                <span class="flex items-center gap-1">
                                    <Clock class="w-3 h-3" /> {{ delivery.time }}
                                </span>
                                <span class="flex items-center gap-1">
                                    <MapPin class="w-3 h-3" /> {{ delivery.origin }} to {{ delivery.destination }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="px-3 py-1 rounded-full text-xs font-medium border bg-blue-50 text-blue-700 border-blue-100">
                            {{ delivery.cargo_type }}
                        </span>
                        <button @click="openDetailsModal(delivery.id)" class="text-gray-400 hover:text-[#34554a] transition-colors">
                            <Eye class="w-4 h-4" />
                        </button>
                    </div>
                </div>
                <div v-if="todayShifts.length === 0" class="p-8 text-center text-gray-500 font-medium">
                    No deliveries scheduled for today
                </div>
            </div>
        </div>

        <!-- Details Modal -->
        <Teleport to="body">
            <div 
                v-if="showDetailsModal && selectedDelivery"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
                @click.self="closeModals"
            >
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden border-t-8 border-[#34554a]">
                    <div class="flex justify-between items-center p-6 border-b border-gray-100">
                        <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                            <Eye class="w-5 h-5 text-[#34554a]" />
                            Delivery Details
                        </h3>
                        <button 
                            @click="closeModals"
                            class="text-gray-400 hover:text-gray-600 p-2 hover:bg-gray-100 rounded-full transition-colors"
                        >
                            <Plus class="w-6 h-6 rotate-45" />
                        </button>
                    </div>
                    
                    <div class="p-6 space-y-6">
                        <div class="grid grid-cols-2 gap-6">
                            <div class="space-y-1">
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Delivery No.</p>
                                <p class="text-base font-bold text-gray-900">{{ selectedDelivery.id }}</p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Date & Time</p>
                                <p class="text-base font-bold text-gray-900">{{ selectedDelivery.date }} @ {{ selectedDelivery.time }}</p>
                            </div>
                        </div>

                        <div class="p-4 bg-gray-50 rounded-2xl border border-gray-100">
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3">Route Information</p>
                            <div class="space-y-4">
                                <div class="flex items-start gap-4">
                                    <div class="w-8 h-8 rounded-full bg-white flex items-center justify-center shadow-sm text-[#34554a] border border-gray-100">
                                        <MapPin class="w-4 h-4" />
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-bold text-gray-400 uppercase">Origin</p>
                                        <p class="text-sm font-bold text-gray-900">{{ selectedDelivery.origin }}</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-4">
                                    <div class="w-8 h-8 rounded-full bg-[#34554a] flex items-center justify-center shadow-md text-white">
                                        <MapPin class="w-4 h-4" />
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-bold text-gray-400 uppercase">Destination</p>
                                        <p class="text-sm font-bold text-gray-900">{{ selectedDelivery.destination }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-6">
                            <div class="space-y-1">
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Driver</p>
                                <p class="text-sm font-bold text-gray-900">{{ selectedDelivery.driver?.name || selectedDelivery.driver }}</p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Cargo Type</p>
                                <p class="text-sm font-bold text-gray-900">{{ selectedDelivery.cargo_type }}</p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Weight</p>
                                <p class="text-sm font-bold text-gray-900">{{ selectedDelivery.cargo_weight }}</p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Customer</p>
                                <p class="text-sm font-bold text-gray-900">{{ selectedDelivery.customer }}</p>
                            </div>
                        </div>

                        <div v-if="selectedDelivery.delivery_notes" class="space-y-1">
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Notes</p>
                            <p class="text-sm text-gray-600 bg-gray-50 p-4 rounded-xl border border-gray-100 italic">{{ selectedDelivery.delivery_notes }}</p>
                        </div>
                    </div>

                    <div class="p-6 bg-gray-50 flex justify-end">
                        <button 
                            @click="closeModals"
                            class="px-8 py-3 bg-[#34554a] text-white rounded-xl font-bold text-xs uppercase tracking-widest hover:bg-[#2c463d] transition-all shadow-lg shadow-[#34554a]/20"
                        >
                            Close Details
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
