<script setup>
import { nextTick, ref, computed } from 'vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import { Plus, Pencil, Trash2, Loader2, Save, X } from 'lucide-vue-next';

const page = usePage();

const props = defineProps({
    operatingUnits: { type: Array, default: () => [] },
});

const pasturePermissions = computed(() => {
    const perms = page.props.auth?.permissions?.['Pasture'];
    return Array.isArray(perms) ? perms : ['no-access'];
});
const hasPasturePermission = (action) => {
    if (String(page.props.auth?.user?.role || '').toLowerCase() === 'admin') return true;
    const perms = pasturePermissions.value;
    return perms.includes('full') || perms.includes(action);
};
const canCreatePasture = computed(() => hasPasturePermission('create'));
const canEditPasture = computed(() => hasPasturePermission('edit'));
const canDeletePasture = computed(() => hasPasturePermission('delete'));

const showUnitModal = ref(false);
const unitModalMode = ref('add');
const editingUnitIndex = ref(null);

const showUnitDetailModal = ref(false);
const selectedUnitForDetail = ref(null);
const selectedUnitIndexForDetail = ref(null);

const showMapModal = ref(false);
const mapLatitude = ref('');
const mapLongitude = ref('');
const mapMarker = ref(null);
let mapInstance = null;
let currentMapType = 'satellite';

const showDeleteModal = ref(false);
const deletingUnit = ref(null);
const deletingUnitIndex = ref(null);

const unitForm = useForm({
    name: '',
    area: '',
    latitude: '',
    longitude: '',
    place_name: '',
});

const toTitleCase = (value) => {
    if (!value) return '';
    return String(value)
        .toLowerCase()
        .split(' ')
        .filter(Boolean)
        .map(word => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
};

const normalizeUnitName = (value) => String(value || '').trim().toLowerCase();

const operatingUnitNameExists = (name, excludeId = null) => {
    const normalized = normalizeUnitName(name);
    if (!normalized) return false;

    return props.operatingUnits.some((unit) => {
        if (excludeId && unit.id === excludeId) return false;
        return normalizeUnitName(unit.name) === normalized;
    });
};

const duplicateNameMessage = 'A record with this operating unit name already exists.';

const openAddUnitModal = () => {
    unitModalMode.value = 'add';
    unitForm.reset();
    mapLatitude.value = '';
    mapLongitude.value = '';
    showUnitModal.value = true;
};

const openEditUnitModal = (e, index) => {
    e.stopPropagation();
    unitModalMode.value = 'edit';
    editingUnitIndex.value = index;
    const unit = props.operatingUnits[index];
    unitForm.name = unit.name;
    unitForm.area = unit.area;
    unitForm.latitude = unit.latitude || '';
    unitForm.longitude = unit.longitude || '';
    unitForm.place_name = unit.place_name || '';
    mapLatitude.value = unit.latitude || '';
    mapLongitude.value = unit.longitude || '';
    showUnitModal.value = true;
};

const saveUnit = () => {
    const excludeId = unitModalMode.value === 'edit'
        ? props.operatingUnits[editingUnitIndex.value]?.id
        : null;

    if (operatingUnitNameExists(unitForm.name, excludeId)) {
        unitForm.setError('name', duplicateNameMessage);
        return;
    }

    unitForm.clearErrors('name');

    if (unitModalMode.value === 'add') {
        unitForm.post(route('pasture.operating-units.store'), {
            preserveScroll: true,
            onSuccess: () => {
                showUnitModal.value = false;
                unitForm.reset();
            },
        });
    } else {
        const unit = props.operatingUnits[editingUnitIndex.value];
        unitForm.put(route('pasture.operating-units.update', unit.id), {
            preserveScroll: true,
            onSuccess: () => {
                showUnitModal.value = false;
                unitForm.reset();
            },
        });
    }
};

const openDeleteModal = (e, index) => {
    e.stopPropagation();
    const unit = props.operatingUnits[index];
    deletingUnit.value = unit;
    deletingUnitIndex.value = index;
    showDeleteModal.value = true;
};

const confirmDeleteUnit = () => {
    if (!deletingUnit.value) return;
    router.delete(route('pasture.operating-units.destroy', deletingUnit.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
            deletingUnit.value = null;
            deletingUnitIndex.value = null;
        }
    });
};

const openUnitDetailModal = (unit, index) => {
    selectedUnitForDetail.value = unit;
    selectedUnitIndexForDetail.value = index;
    showUnitDetailModal.value = true;
};

const openEditUnitFromDetail = () => {
    showUnitDetailModal.value = false;
    const i = selectedUnitIndexForDetail.value;
    if (i !== null && i !== undefined) openEditUnitModal({ stopPropagation: () => {} }, i);
};

const openGoogleMapsDirections = (unit) => {
    if (!unit.latitude || !unit.longitude) return;
    window.open(`https://www.google.com/maps/dir/?api=1&destination=${unit.latitude},${unit.longitude}`, '_blank');
};

const openStructurePage = (unit) => {
    if (!unit?.id) return;
    router.visit(route('pasture.show', unit.id));
};

const openAllHerdRecords = (unit) => {
    if (!unit?.id) return;
    router.visit(route('pasture.show', unit.id) + '?view=all');
};

const loadLeaflet = () => {
    return new Promise((resolve, reject) => {
        if (typeof L !== 'undefined') return resolve();

        if (!document.getElementById('leaflet-css')) {
            const link = document.createElement('link');
            link.id = 'leaflet-css';
            link.rel = 'stylesheet';
            link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
            document.head.appendChild(link);
        }

        const existingScript = document.getElementById('leaflet-js');
        if (existingScript) {
            existingScript.addEventListener('load', resolve);
            existingScript.addEventListener('error', reject);
            return;
        }

        const script = document.createElement('script');
        script.id = 'leaflet-js';
        script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
        script.async = true;
        script.onload = resolve;
        script.onerror = reject;
        document.head.appendChild(script);
    });
};

const getPlaceName = async (lat, lng) => {
    try {
        const r = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=14&addressdetails=1`);
        const d = await r.json();
        return d?.display_name || `${parseFloat(lat).toFixed(5)}, ${parseFloat(lng).toFixed(5)}`;
    } catch {
        return `${parseFloat(lat).toFixed(5)}, ${parseFloat(lng).toFixed(5)}`;
    }
};

const updateTileLayer = () => {
    if (!mapInstance) return;
    mapInstance.eachLayer((layer) => {
        if (layer instanceof L.TileLayer) mapInstance.removeLayer(layer);
    });

    let tileUrl = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}';
    let attribution = 'Satellite &copy; Esri';
    if (currentMapType === 'street') {
        tileUrl = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
        attribution = '&copy; OpenStreetMap contributors';
    }
    L.tileLayer(tileUrl, { attribution }).addTo(mapInstance);
};

const updateMarker = async (lat, lng) => {
    if (mapMarker.value) mapMarker.value.setLatLng([lat, lng]);
    else mapMarker.value = L.marker([lat, lng]).addTo(mapInstance);

    mapMarker.value.bindPopup('Loading...').openPopup();
    const placeName = await getPlaceName(lat, lng);
    mapMarker.value.setPopupContent(`
        <div class="text-center min-w-[200px]">
            <strong class="block text-sm text-[#34554a] mb-1">Estate Location</strong>
            <span class="text-xs text-gray-600">${placeName}</span>
            <div class="text-xs text-gray-500 mt-1">${parseFloat(lat).toFixed(7)}, ${parseFloat(lng).toFixed(7)}</div>
        </div>
    `);
};

const initLeafletMap = () => {
    const el = document.getElementById('estate-map');
    if (!el) return;
    const lat = mapLatitude.value ? parseFloat(mapLatitude.value) : 5.9788;
    const lng = mapLongitude.value ? parseFloat(mapLongitude.value) : 116.0753;

    if (mapInstance) mapInstance.remove();
    mapInstance = L.map('estate-map').setView([lat, lng], 12);
    updateTileLayer();

    if (mapLatitude.value && mapLongitude.value) updateMarker(lat, lng);

    mapInstance.on('click', async (e) => {
        const { lat, lng } = e.latlng;
        mapLatitude.value = lat.toFixed(7);
        mapLongitude.value = lng.toFixed(7);
        updateMarker(lat, lng);
        const placeName = await getPlaceName(lat, lng);
        const info = document.getElementById('selected-place-name');
        if (info) {
            info.textContent = placeName;
            info.classList.remove('hidden');
        }
    });
};

const openMapPicker = async () => {
    mapLatitude.value = unitForm.latitude || '';
    mapLongitude.value = unitForm.longitude || '';
    currentMapType = 'satellite';
    showMapModal.value = true;
    await nextTick();
    try {
        await loadLeaflet();
        setTimeout(() => initLeafletMap(), 100);
    } catch {
        alert('Failed to load map. Please check your internet connection.');
    }
};

const switchMapType = (type) => {
    currentMapType = type;
    updateTileLayer();
};

const clearMapLocation = () => {
    mapLatitude.value = '';
    mapLongitude.value = '';
    const info = document.getElementById('selected-place-name');
    if (info) {
        info.textContent = '';
        info.classList.add('hidden');
    }
    if (mapMarker.value) {
        mapMarker.value.remove();
        mapMarker.value = null;
    }
};

const saveMapLocation = async () => {
    unitForm.latitude = mapLatitude.value;
    unitForm.longitude = mapLongitude.value;
    if (mapLatitude.value && mapLongitude.value) {
        unitForm.place_name = await getPlaceName(mapLatitude.value, mapLongitude.value);
    }
    showMapModal.value = false;
};

const getStaticMapUrl = (lat, lng, width = 200, height = 100) => {
    if (!lat || !lng) return null;
    const zoom = width > 300 ? 14 : 12;
    return `https://static-maps.yandex.ru/1.x/?lang=en-US&ll=${lng},${lat}&z=${zoom}&size=${width},${height}&l=sat`;
};
</script>

<template>
    <Head title="All Pastures" />
    <AppLayout title="All Pastures" parent="Data Management">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="mb-8 flex justify-between items-start">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">All Pastures</h1>
                    <p class="text-sm text-gray-500 mt-1">Manage operating units, blocks, and phases.</p>
                </div>
                <div class="flex items-center gap-2">
                    <Link href="/herd-cards/grazing-detail" class="flex items-center gap-2 px-4 py-2 border border-gray-200 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50">
                        View Grazing Details
                    </Link>
                    <button v-if="canCreatePasture" type="button" @click="openAddUnitModal" class="flex items-center gap-2 px-4 py-2 bg-[#34554a] text-white rounded-lg text-sm font-medium hover:bg-[#2a443b]">
                        <Plus class="w-4 h-4" />
                        Add Operating Unit
                    </button>
                </div>
            </div>

            <section class="mb-8">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div v-for="(unit, index) in operatingUnits" :key="unit.id" class="relative cursor-pointer bg-white p-5 rounded-xl border-2 border-transparent shadow-sm hover:shadow-md transition-all text-center group" @click="openStructurePage(unit)">
                        <div class="text-sm font-bold mb-1 text-gray-800 group-hover:text-[#34554a]">{{ toTitleCase(unit.name) }}</div>
                        <div class="text-xs text-gray-500 font-medium">{{ unit.area }} Ha</div>
                        <div v-if="unit.latitude && unit.longitude" class="flex items-center gap-1 mt-1 text-xs text-gray-500 justify-center">
                            <span>{{ unit.place_name ? unit.place_name.substring(0, 20) + (unit.place_name.length > 20 ? '...' : '') : 'Located' }}</span>
                        </div>

                        <div v-if="unit.latitude && unit.longitude" class="mt-2">
                            <a :href="`https://www.google.com/maps/dir/?api=1&destination=${unit.latitude},${unit.longitude}`" target="_blank" class="block group" title="Click to get directions" @click.stop>
                                <img :src="getStaticMapUrl(unit.latitude, unit.longitude, 120, 60)" :alt="unit.name + ' location'" class="w-full h-12 object-cover rounded border border-gray-200 group-hover:ring-2 group-hover:ring-[#34554a] transition-all cursor-pointer" @error="$event.target.style.display='none'" />
                                <div class="text-[9px] text-gray-400 mt-0.5 text-center">{{ parseFloat(unit.latitude).toFixed(4) }}, {{ parseFloat(unit.longitude).toFixed(4) }}</div>
                            </a>
                        </div>

                        <div class="absolute top-2 right-2 flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button @click="openUnitDetailModal(unit, index)" class="text-[10px] bg-gray-100 hover:bg-gray-200 text-gray-600 p-1.5 rounded-full shadow-sm" title="View Details">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                            <button v-if="canEditPasture" @click="openEditUnitModal($event, index)" class="text-[10px] bg-gray-100 hover:bg-gray-200 text-gray-600 p-1.5 rounded-full shadow-sm" title="Edit">
                                <Pencil class="w-3 h-3" />
                            </button>
                            <button v-if="canDeletePasture" @click="openDeleteModal($event, index)" class="text-[10px] bg-gray-100 hover:bg-gray-200 text-gray-500 p-1.5 rounded-full shadow-sm" title="Delete">
                                <Trash2 class="w-3 h-3" />
                            </button>
                        </div>

                        <div class="mt-4 pt-3 border-t border-gray-100 text-left" @click.stop>
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-semibold text-gray-500">Blocks</span>
                                <span class="font-semibold text-[#34554a]">{{ (unit.blocks || []).length }}</span>
                            </div>
                            <p class="mt-2 text-[11px] text-gray-500">Click the card to manage block and phase.</p>
                        </div>
                    </div>

                    <div @click="openAddUnitModal" class="cursor-pointer bg-white p-5 rounded-xl border-2 border-dashed border-gray-300 hover:border-[#34554a] hover:bg-gray-50 transition-all text-center flex flex-col justify-center items-center h-full min-h-[80px] group">
                        <div class="w-10 h-10 rounded-full bg-gray-50 text-gray-400 group-hover:bg-[#34554a] group-hover:text-white flex items-center justify-center mb-2 transition-all"><Plus class="w-5 h-5" /></div>
                        <div class="text-xs font-medium text-gray-500 group-hover:text-[#34554a] font-semibold">Add Operating Unit</div>
                    </div>
                </div>
            </section>

            <div v-if="showUnitModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click.self="showUnitModal = false">
                <div class="bg-white rounded-xl p-6 w-full max-w-lg shadow-2xl">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-900">{{ unitModalMode === 'add' ? 'Add New Operating Unit' : 'Edit Operating Unit' }}</h3>
                        <button @click="showUnitModal = false" class="text-gray-400 hover:text-gray-600"><X class="w-5 h-5" /></button>
                    </div>
                    <form @submit.prevent="saveUnit" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Operating Unit Name</label>
                            <input
                                v-model="unitForm.name"
                                type="text"
                                required
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-[#34554a] focus:border-transparent"
                                :class="unitForm.errors.name ? 'border-red-400' : 'border-gray-300'"
                                @input="unitForm.clearErrors('name')"
                            />
                            <p v-if="unitForm.errors.name" class="mt-1 text-sm text-red-600">{{ unitForm.errors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Area (Hectares)</label>
                            <input v-model="unitForm.area" type="number" step="0.01" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#34554a] focus:border-transparent" />
                            <p v-if="unitForm.errors.area" class="mt-1 text-sm text-gray-600">{{ unitForm.errors.area }}</p>
                        </div>
                        <div class="border-t border-gray-200 pt-4">
                            <h4 class="text-sm font-semibold text-gray-700 mb-3">Location Information</h4>
                            <div class="grid grid-cols-2 gap-3 mb-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Latitude</label>
                                    <input v-model="unitForm.latitude" type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#34554a] focus:border-transparent" />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Longitude</label>
                                    <input v-model="unitForm.longitude" type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#34554a] focus:border-transparent" />
                                </div>
                            </div>
                            <button type="button" @click="openMapPicker" class="w-full px-4 py-2 bg-[#34554a] text-white rounded-lg hover:bg-[#2a443b] transition-colors">Pick Location on Map</button>
                            <div v-if="unitForm.latitude && unitForm.longitude" class="mt-2">
                                <div class="text-xs text-gray-600 text-center font-medium mb-1">Location Set</div>
                                <div class="text-[10px] text-gray-500 text-center">{{ unitForm.latitude }}, {{ unitForm.longitude }}</div>
                            </div>
                        </div>
                        <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                            <button type="button" @click="showUnitModal = false" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
                            <button type="submit" :disabled="unitForm.processing" class="px-4 py-2 bg-[#34554a] text-white rounded-lg hover:bg-[#2a443b] disabled:opacity-50 flex items-center gap-2">
                                <Loader2 v-if="unitForm.processing" class="w-4 h-4 animate-spin" />
                                <Save v-else class="w-4 h-4" />
                                {{ unitModalMode === 'add' ? 'Add Operating Unit' : 'Save Changes' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div v-if="showUnitDetailModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click.self="showUnitDetailModal = false">
                <div class="bg-white rounded-xl p-6 w-full max-w-lg shadow-2xl">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-900">Operating Unit Details</h3>
                        <button @click="showUnitDetailModal = false" class="text-gray-400 hover:text-gray-600"><X class="w-5 h-5" /></button>
                    </div>
                    <div v-if="selectedUnitForDetail" class="space-y-4">
                        <div class="bg-gray-50 rounded-lg p-4">
                            <h4 class="text-xl font-bold text-gray-900">{{ toTitleCase(selectedUnitForDetail.name) }}</h4>
                            <p class="text-sm text-gray-500 mt-1">{{ selectedUnitForDetail.area }} Hectares</p>
                        </div>
                        <div class="border border-gray-200 rounded-lg overflow-hidden">
                            <div class="bg-gray-50 px-4 py-2 border-b border-gray-200"><span class="text-xs font-semibold text-gray-700">Location Information</span></div>
                            <div class="p-4 space-y-3">
                                <div v-if="selectedUnitForDetail.place_name" class="text-sm text-gray-800">{{ selectedUnitForDetail.place_name }}</div>
                                <div class="text-sm text-gray-800 font-mono">{{ selectedUnitForDetail.latitude ? parseFloat(selectedUnitForDetail.latitude).toFixed(7) : '-' }}, {{ selectedUnitForDetail.longitude ? parseFloat(selectedUnitForDetail.longitude).toFixed(7) : '-' }}</div>
                            </div>
                        </div>
                        <div v-if="selectedUnitForDetail.latitude && selectedUnitForDetail.longitude" class="border border-gray-200 rounded-lg overflow-hidden">
                            <img :src="getStaticMapUrl(selectedUnitForDetail.latitude, selectedUnitForDetail.longitude, 450, 200)" :alt="selectedUnitForDetail.name + ' location'" class="w-full h-40 object-cover" @error="$event.target.style.display='none'" />
                        </div>
                        <div class="flex flex-col gap-2 pt-2">
                            <button @click="openAllHerdRecords(selectedUnitForDetail)" class="w-full px-4 py-2.5 border border-[#34554a] text-[#34554a] rounded-lg hover:bg-[#34554a]/5">View All Records</button>
                            <button @click="openStructurePage(selectedUnitForDetail)" class="w-full px-4 py-2.5 bg-[#34554a] text-white rounded-lg hover:bg-[#2a443b]">Manage Block / Phase</button>
                            <div class="flex gap-3">
                                <button @click="openGoogleMapsDirections(selectedUnitForDetail)" :disabled="!selectedUnitForDetail.latitude || !selectedUnitForDetail.longitude" class="flex-1 px-4 py-2.5 bg-[#34554a] text-white rounded-lg hover:bg-[#2a443b] disabled:opacity-50">Open in Google Maps</button>
                                <button @click="openEditUnitFromDetail" class="flex-1 px-4 py-2.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">Edit</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="showMapModal" class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50" @click.self="showMapModal = false">
                <div class="bg-white rounded-xl p-4 w-full max-w-4xl shadow-2xl">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-900">Select Operating Unit Location</h3>
                        <button @click="showMapModal = false" class="text-gray-400 hover:text-gray-600"><X class="w-5 h-5" /></button>
                    </div>
                    <div class="flex items-center gap-2 mb-4">
                        <span class="text-xs text-gray-500 font-medium">Map View:</span>
                        <button type="button" @click="switchMapType('satellite')" :class="['px-3 py-1.5 text-xs font-medium rounded-lg transition-colors', currentMapType === 'satellite' ? 'bg-[#34554a] text-white' : 'bg-gray-200 text-gray-700']">Satellite</button>
                        <button type="button" @click="switchMapType('street')" :class="['px-3 py-1.5 text-xs font-medium rounded-lg transition-colors', currentMapType === 'street' ? 'bg-[#34554a] text-white' : 'bg-gray-200 text-gray-700']">Street Map</button>
                    </div>
                    <div id="selected-place-name" class="hidden mb-3 p-3 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-700"></div>
                    <div class="mb-3 grid grid-cols-1 md:grid-cols-2 gap-3">
                        <input v-model="mapLatitude" type="text" placeholder="Latitude" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#34554a] text-sm" />
                        <input v-model="mapLongitude" type="text" placeholder="Longitude" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#34554a] text-sm" />
                    </div>
                    <div id="estate-map" class="w-full h-96 rounded-lg border border-gray-300 mb-4"></div>
                    <div class="flex justify-end gap-3">
                        <button type="button" @click="clearMapLocation" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Clear</button>
                        <button type="button" @click="showMapModal = false" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
                        <button type="button" @click="saveMapLocation" :disabled="!mapLatitude || !mapLongitude" class="px-4 py-2 bg-[#34554a] text-white rounded-lg hover:bg-[#2a443b] disabled:opacity-50">Save Location</button>
                    </div>
                </div>
            </div>

            <DeleteConfirmationModal
                :show="showDeleteModal"
                title="Delete Operating Unit"
                message="Are you sure you want to delete"
                :itemName="deletingUnit?.name || ''"
                @confirm="confirmDeleteUnit"
                @close="showDeleteModal = false; deletingUnit = null; deletingUnitIndex = null"
            />
        </div>
    </AppLayout>
</template>
