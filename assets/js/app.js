// app.js - Logika Interaktivitas & Sinkronisasi Database (AJAX + Pembatasan Role)

// State Aplikasi
let inventory = [];
let currentFilter = "Semua";
let currentSort = "id"; // 'id', 'name', 'stock-high', 'stock-low'
let searchQuery = "";
let deleteTargetId = null;
let isEditMode = false;

// DOM Elements
let searchBar, searchBarMobile, clearSearch, profileDropdownBtn, profileDropdown, sortBtn, sortDropdown;
let statTotalItems, statTotalUnits, statItemsInUse, statLowStock, lowStockWarningText;
let inventoryTableBody, emptyState, showingStartCount, showingEndCount, totalItemsCount;
let itemModal, modalTitle, modalSub, modalIcon, modalIconContainer, itemForm; // Tambah itemForm
let itemIdInput, itemNameInput, itemCategoryInput, itemStockInput, itemInUseInput, generateIdBtn;
let deleteDialog, deleteItemName, confirmDeleteBtn;

// Mendapatkan role user saat ini dari konteks global PHP
const currentUserRole = window.CurrentUser ? window.CurrentUser.role : 'karyawan';

// Mulai Inisialisasi saat Halaman Selesai Dimuat
document.addEventListener("DOMContentLoaded", () => {
    cacheDomElements();
    initializeEventListeners();
    // Hanya load inventory jika tabel inventaris ada di halaman ini
    if (inventoryTableBody) {
        loadInventory();
    }
});

// Cache DOM Elements untuk optimasi performa
function cacheDomElements() {
    searchBar = document.getElementById("searchBar");
    searchBarMobile = document.getElementById("searchBarMobile");
    clearSearch = document.getElementById("clearSearch");
    profileDropdownBtn = document.getElementById("profileDropdownBtn");
    profileDropdown = document.getElementById("profileDropdown");
    sortBtn = document.getElementById("sortBtn");
    sortDropdown = document.getElementById("sortDropdown");

    statTotalItems = document.getElementById("statTotalItems");
    statTotalUnits = document.getElementById("statTotalUnits");
    statItemsInUse = document.getElementById("statItemsInUse");
    statLowStock = document.getElementById("statLowStock");
    lowStockWarningText = document.getElementById("lowStockWarningText");

    inventoryTableBody = document.getElementById("inventoryTableBody");
    emptyState = document.getElementById("emptyState");
    showingStartCount = document.getElementById("showingStartCount");
    showingEndCount = document.getElementById("showingEndCount");
    totalItemsCount = document.getElementById("totalItemsCount");

    itemModal = document.getElementById("itemModal");
    itemForm = document.getElementById("itemForm") || (itemModal ? itemModal.querySelector("form") : null); // Cache form modal
    modalTitle = document.getElementById("modalTitle");
    modalSub = document.getElementById("modalSub");
    modalIcon = document.getElementById("modalIcon");
    modalIconContainer = document.getElementById("modalIconContainer");

    itemIdInput = document.getElementById("itemIdInput");
    itemNameInput = document.getElementById("itemNameInput");
    itemCategoryInput = document.getElementById("itemCategoryInput");
    itemStockInput = document.getElementById("itemStockInput");
    itemInUseInput = document.getElementById("itemInUseInput");
    generateIdBtn = document.getElementById("generateIdBtn");

    deleteDialog = document.getElementById("deleteDialog");
    deleteItemName = document.getElementById("deleteItemName");
    confirmDeleteBtn = document.getElementById("confirmDeleteBtn");
}

// Inisialisasi Events Listener
function initializeEventListeners() {
    const handleSearchInput = (e) => {
        searchQuery = e.target.value.toLowerCase().trim();
        
        if (e.target === searchBar && searchBarMobile) {
            searchBarMobile.value = e.target.value;
        } else if (searchBar) {
            searchBar.value = e.target.value;
        }

        if (clearSearch) {
            if (searchQuery.length > 0) {
                clearSearch.classList.remove("hidden");
            } else {
                clearSearch.classList.add("hidden");
            }
        }

        if (inventoryTableBody) renderTable();
    };

    // Menggunakan Optional Chaining (?.) agar tidak error jika elemen bernilai null
    searchBar?.addEventListener("input", handleSearchInput);
    searchBarMobile?.addEventListener("input", handleSearchInput);

    clearSearch?.addEventListener("click", () => {
        if (searchBar) searchBar.value = "";
        if (searchBarMobile) searchBarMobile.value = "";
        searchQuery = "";
        clearSearch.classList.add("hidden");
        if (inventoryTableBody) renderTable();
        searchBar?.focus();
    });

    document.addEventListener("keydown", (e) => {
        if (e.key === "/" && document.activeElement !== searchBar && document.activeElement !== searchBarMobile) {
            if (searchBar) {
                e.preventDefault();
                searchBar.focus();
                searchBar.select();
            }
        }
    });

    profileDropdownBtn?.addEventListener("click", (e) => {
        e.stopPropagation();
        profileDropdown?.classList.toggle("hidden");
    });

    sortBtn?.addEventListener("click", (e) => {
        e.stopPropagation();
        sortDropdown?.classList.toggle("hidden");
    });

    document.addEventListener("click", () => {
        profileDropdown?.classList.add("hidden");
        sortDropdown?.classList.add("hidden");
    });

    // Pasang handler submit untuk form tambah/edit modal barang
    itemForm?.addEventListener("submit", handleFormSubmit);
}

// Sinkronisasi data ke API PHP
async function loadInventory() {
    // KUNCI PERBAIKAN: Jika elemen tabel inventaris tidak ada di halaman ini, 
    // langsung batalkan fetch api.php agar tidak memicu toast error palsu di halaman lain.
    if (!inventoryTableBody) {
        return; 
    }

    try {
        // Deteksi path dinamis untuk api.php
        const apiPath = window.location.pathname.includes('/modules/') ? '../api.php' : 'api.php';
        const response = await fetch(apiPath);
        
        if (response.status === 401) {
            window.location.href = 'login.php';
            return;
        }
        if (!response.ok) {
            throw new Error("Respon API gagal: " + response.statusText);
        }
        
        inventory = await response.json();
        renderTable();
        updateMetrics();
    } catch (error) {
        console.error("Gagal memuat inventaris:", error);
        showToast("error", "Koneksi Database Gagal", "Gagal memuat data dari server database MySQL.");
    }
}

// Menampilkan Data Barang di Tabel Klien
function renderTable() {
    if (!inventoryTableBody) return;

    const skeleton = document.getElementById("tableSkeleton");
    if (skeleton) skeleton.remove();

    let filteredItems = inventory.filter(item => {
        if (currentFilter === "Semua") return true;
        return item.category.toLowerCase() === currentFilter.toLowerCase();
    });

    if (searchQuery.length > 0) {
        filteredItems = filteredItems.filter(item => {
            return item.id.toLowerCase().includes(searchQuery) || 
                   item.name.toLowerCase().includes(searchQuery);
        });
    }

    filteredItems.sort((a, b) => {
        if (currentSort === "id") {
            return a.id.localeCompare(b.id, undefined, { numeric: true, sensitivity: 'base' });
        } else if (currentSort === "name") {
            return a.name.localeCompare(b.name);
        } else if (currentSort === "stock-high") {
            return b.stock - a.stock;
        } else if (currentSort === "stock-low") {
            return a.stock - b.stock;
        }
        return 0;
    });

    inventoryTableBody.innerHTML = "";

    if (filteredItems.length === 0) {
        emptyState?.classList.remove("hidden");
        updatePaginationInfo(0, 0, 0);
        return;
    }

    emptyState?.classList.add("hidden");

    filteredItems.forEach((item) => {
        const isOutOfStock = item.stock <= 0;
        const statusText = isOutOfStock ? "Habis" : "Tersedia";
        const statusBadgeClass = isOutOfStock 
            ? "bg-rose-50 text-rose-700 border-rose-100/80" 
            : "bg-emerald-50 text-emerald-700 border-emerald-100/80";

        let catBadgeClass = "bg-slate-50 text-slate-600 border-slate-100";
        if (item.category === "Elektronik") {
            catBadgeClass = "bg-teal-50 text-teal-700 border-teal-100/80";
        } else if (item.category === "Furnitur") {
            catBadgeClass = "bg-cyan-50 text-cyan-700 border-cyan-100/80";
        } else if (item.category === "ATK") {
            catBadgeClass = "bg-amber-50 text-amber-700 border-amber-100/80";
        }

        // Tampilkan tombol aksi sesuai role
        let actionButtons = "";
        if (currentUserRole === 'admin') {
            actionButtons = `
                <a href="locations.php?barang_id=${item.id}&action=checkout" class="p-1.5 text-slate-400 hover:text-amber-500 hover:bg-amber-50 rounded-lg transition-all inline-flex items-center justify-center" title="Checkout / Keluar dari Rak">
                    <i data-lucide="log-out" class="w-3.5 h-3.5"></i>
                </a>
                <button onclick="openEditModal('${item.id}')" class="p-1.5 text-slate-400 hover:text-brand-500 hover:bg-brand-50 rounded-lg transition-all" title="Edit Data Barang">
                    <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                </button>
                <button onclick="confirmDelete('${item.id}', '${item.name.replace(/'/g, "\\'")}')" class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-all" title="Hapus Barang">
                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                </button>
            `;
        } else {
            actionButtons = `
                <a href="locations.php?barang_id=${item.id}&action=checkout" class="p-1.5 text-slate-400 hover:text-amber-500 hover:bg-amber-50 rounded-lg transition-all inline-flex items-center justify-center" title="Checkout / Keluar dari Rak">
                    <i data-lucide="log-out" class="w-3.5 h-3.5"></i>
                </a>
                <button onclick="openEditModal('${item.id}')" class="p-1.5 text-slate-400 hover:text-cyan-500 hover:bg-cyan-50 rounded-lg transition-all flex items-center gap-1" title="Update Jumlah Stok / Dipakai">
                    <i data-lucide="edit-2" class="w-3.5 h-3.5"></i>
                    <span class="text-[10px] font-semibold pr-1">Update</span>
                </button>
            `;
        }

        const row = document.createElement("tr");
        row.className = "table-row-animate hover:bg-slate-50/50 group select-none animate-fade-in";
        row.innerHTML = `
            <td class="py-3.5 px-6 font-mono text-xs text-slate-500 font-semibold align-middle">${item.id}</td>
            <td class="py-3.5 px-6 align-middle">
                <div class="text-sm font-semibold text-slate-800 group-hover:text-brand-500 transition-colors leading-tight">${item.name}</div>
            </td>
            <td class="py-3.5 px-6 align-middle">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold border ${catBadgeClass} tracking-wide uppercase">
                    ${item.category}
                </span>
            </td>
            <td class="py-3.5 px-6 align-middle">
                <div class="flex items-center gap-1.5">
                    <span class="text-sm font-semibold text-slate-800">${item.stock}</span>
                    <span class="text-[10px] text-slate-400">unit</span>
                </div>
            </td>
            <td class="py-3.5 px-6 align-middle">
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold border ${statusBadgeClass}">
                    <span class="w-1.5 h-1.5 rounded-full ${isOutOfStock ? 'bg-rose-500' : 'bg-emerald-500'}"></span>
                    ${statusText}
                </span>
            </td>
            <td class="py-3.5 px-6 align-middle text-right">${actionButtons}</td>
        `;
        inventoryTableBody.appendChild(row);
    });

    if (window.lucide) lucide.createIcons();
    updatePaginationInfo(1, filteredItems.length, filteredItems.length);
}

// Memperbarui Kartu Metrik
function updateMetrics() {
    if (statTotalItems) statTotalItems.innerText = inventory.length;

    const totalUnits = inventory.reduce((sum, item) => sum + Number(item.stock) + Number(item.dipakai), 0);
    if (statTotalUnits) statTotalUnits.innerText = `${totalUnits} unit`;

    const totalInUse = inventory.reduce((sum, item) => sum + Number(item.dipakai), 0);
    if (statItemsInUse) statItemsInUse.innerText = `${totalInUse} Unit`;

    const lowStockItems = inventory.filter(item => item.stock <= 5 && item.stock > 0);
    if (statLowStock) statLowStock.innerText = `${lowStockItems.length} Barang`;
    
    if (lowStockWarningText) {
        if (lowStockItems.length > 0) {
            lowStockWarningText.innerHTML = `Stok <span class="font-bold underline">${lowStockItems.length} barang</span> menipis (&le; 5 unit)`;
        } else {
            lowStockWarningText.innerText = "Stok barang di atas &le; 5 unit";
        }
    }
}

// Memperbarui pagination info
function updatePaginationInfo(start, end, total) {
    if (showingStartCount) showingStartCount.innerText = start;
    if (showingEndCount) showingEndCount.innerText = end;
    if (totalItemsCount) totalItemsCount.innerText = total;
}

// Aksi filter
function filterCategory(category) {
    currentFilter = category;

    const tabs = document.querySelectorAll(".filter-tab");
    tabs.forEach(tab => {
        tab.className = "filter-tab px-4 py-2 rounded-xl text-xs font-semibold transition-all duration-200 text-slate-500 hover:bg-slate-50 hover:text-slate-800";
    });

    const activeTab = document.getElementById(`filterBtn${category}`);
    if (activeTab) {
        activeTab.className = "filter-tab px-4 py-2 rounded-xl text-xs font-semibold transition-all duration-200 bg-brand-500 text-white shadow-soft shadow-brand-500/10";
    }

    renderTable();
}

// Aksi urutkan
function applySort(type) {
    currentSort = type;
    renderTable();
    
    if (sortBtn) {
        if (type !== 'id') {
            sortBtn.classList.add("border-brand-500", "text-brand-600", "bg-brand-50/30");
        } else {
            sortBtn.classList.remove("border-brand-500", "text-brand-600", "bg-brand-50/30");
        }
    }
}

function resetFilters() {
    if (searchBar) searchBar.value = "";
    if (searchBarMobile) searchBarMobile.value = "";
    clearSearch?.classList.add("hidden");
    searchQuery = "";
    currentSort = "id";
    filterCategory("Semua");
}

// Ekspor data
function exportToCSV() {
    try {
        let csvContent = "data:text/csv;charset=utf-8,";
        csvContent += "ID Barang,Nama Barang,Kategori,Stok (Unit),Dipakai (Unit),Status\r\n";

        inventory.forEach(item => {
            const status = item.stock <= 0 ? "Habis" : "Tersedia";
            const row = `"${item.id}","${item.name.replace(/"/g, '""')}","${item.category}",${item.stock},${item.dipakai},"${status}"`;
            csvContent += row + "\r\n";
        });

        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        
        const timestamp = new Date().toISOString().slice(0,10);
        link.setAttribute("download", `Data_Inventaris_Kantor_${timestamp}.csv`);
        document.body.appendChild(link);
        
        link.click();
        document.body.removeChild(link);
        
        showToast("success", "Ekspor Berhasil", "Data inventaris berhasil diunduh dalam format CSV.");
    } catch (error) {
        showToast("error", "Ekspor Gagal", "Terjadi kesalahan saat mengekspor data inventaris.");
    }
}

// MODAL ADD / EDIT CONTROL
function openAddModal() {
    if (currentUserRole !== 'admin') {
        showToast("error", "Akses Ditolak", "Hanya Admin yang dapat menambahkan barang baru.");
        return;
    }
    isEditMode = false;
    
    if (modalTitle) modalTitle.innerText = "Tambah Barang Baru";
    if (modalSub) modalSub.innerText = "Masukkan data inventaris baru secara lengkap.";
    modalIcon?.setAttribute("data-lucide", "package-plus");
    if (modalIconContainer) modalIconContainer.className = "w-10 h-10 rounded-xl bg-brand-50 text-brand-500 flex items-center justify-center";
    if (window.lucide) lucide.createIcons();

    // Enable semua input
    if (itemIdInput) {
        itemIdInput.value = "";
        itemIdInput.disabled = false;
        itemIdInput.classList.remove("opacity-60", "cursor-not-allowed");
    }
    generateIdBtn?.classList.remove("hidden");
    
    const helpText = document.getElementById("idHelpText");
    if (helpText) helpText.innerText = "ID barang harus unik, gunakan kombinasi huruf dan angka.";
    
    if (itemNameInput) {
        itemNameInput.value = "";
        itemNameInput.disabled = false;
        itemNameInput.classList.remove("opacity-60", "cursor-not-allowed");
    }

    if (itemCategoryInput) {
        itemCategoryInput.value = "Elektronik";
        itemCategoryInput.disabled = false;
        itemCategoryInput.classList.remove("opacity-60", "cursor-not-allowed");
    }

    if (itemStockInput) itemStockInput.value = "";
    if (itemInUseInput) itemInUseInput.value = "0";

    // Tampilkan & load dropdown lokasi
    const locSection = document.getElementById('initialLocationSection');
    if (locSection) locSection.classList.remove('hidden');
    populateInitialLocationDropdown();

    itemModal?.classList.remove("hidden");
    itemIdInput?.focus();
}

async function populateInitialLocationDropdown() {
    const select = document.getElementById('initialLocationInput');
    if (!select) return;
    try {
        select.innerHTML = '<option value="">⏳ Memuat lokasi rak...</option>';
        const res = await fetch(API_CONFIG.RAK);
        const json = await res.json();
        if (json.status === 'success' && json.data.length > 0) {
            select.innerHTML = '<option value="">-- Pilih Lokasi Penyimpanan --</option>' +
                json.data.map(r => `<option value="${r.id}">${r.kode_lengkap} (Terisi: ${r.terisi}/${r.kapasitas} unit)</option>`).join('');
        } else {
            select.innerHTML = '<option value="">⚠️ Tidak ada lokasi rak aktif</option>';
        }
    } catch (err) {
        select.innerHTML = '<option value="">❌ Gagal memuat lokasi</option>';
    }
}

function openEditModal(id) {
    isEditMode = true;
    const item = inventory.find(i => i.id === id);
    if (!item) return;

    if (modalTitle) modalTitle.innerText = currentUserRole === 'admin' ? "Edit Data Barang" : "Update Kuantitas Stok / Dipakai";
    if (modalSub) modalSub.innerText = `Mengedit detail data barang ${id}.`;
    modalIcon?.setAttribute("data-lucide", currentUserRole === 'admin' ? "edit-3" : "edit-2");
    if (modalIconContainer) modalIconContainer.className = currentUserRole === 'admin' 
        ? "w-10 h-10 rounded-xl bg-cyan-50 text-cyan-600 flex items-center justify-center"
        : "w-10 h-10 rounded-xl bg-teal-50 text-teal-650 flex items-center justify-center";
    if (window.lucide) lucide.createIcons();

    // Sembunyikan dropdown lokasi untuk edit mode
    const locSection = document.getElementById('initialLocationSection');
    if (locSection) locSection.classList.add('hidden');
    const select = document.getElementById('initialLocationInput');
    if (select) select.value = "";

    // Lock ID input
    if (itemIdInput) {
        itemIdInput.value = item.id;
        itemIdInput.disabled = true;
        itemIdInput.classList.add("opacity-60", "cursor-not-allowed");
    }
    generateIdBtn?.classList.add("hidden");
    
    const helpText = document.getElementById("idHelpText");
    if (helpText) helpText.innerText = "ID barang tidak dapat diubah setelah ditambahkan.";

    // Lock Nama dan Kategori jika role-nya Karyawan
    if (itemNameInput) itemNameInput.value = item.name;
    if (itemCategoryInput) itemCategoryInput.value = item.category;
    
    if (currentUserRole === 'karyawan') {
        itemNameInput?.classList.add("opacity-60", "cursor-not-allowed");
        if (itemNameInput) itemNameInput.disabled = true;
        itemCategoryInput?.classList.add("opacity-60", "cursor-not-allowed");
        if (itemCategoryInput) itemCategoryInput.disabled = true;
    } else {
        itemNameInput?.classList.remove("opacity-60", "cursor-not-allowed");
        if (itemNameInput) itemNameInput.disabled = false;
        itemCategoryInput?.classList.remove("opacity-60", "cursor-not-allowed");
        if (itemCategoryInput) itemCategoryInput.disabled = false;
    }

    if (itemStockInput) itemStockInput.value = item.stock;
    if (itemInUseInput) itemInUseInput.value = item.dipakai;

    itemModal?.classList.remove("hidden");
    
    if (currentUserRole === 'karyawan') {
        itemStockInput?.focus();
    } else {
        itemNameInput?.focus();
    }
}

function generateNewId() {
    let idExists = true;
    let nextId = "";
    
    while (idExists) {
        const randomNum = Math.floor(100 + Math.random() * 900);
        nextId = `INV-${randomNum}`;
        idExists = inventory.some(item => item.id === nextId);
    }
    
    if (itemIdInput) itemIdInput.value = nextId;
    showToast("info", "ID Acak Dibuat", `ID berhasil digenerate: ${nextId}`);
}

function closeModal() {
    itemModal?.classList.add("hidden");
}

// KIRIM DATA KE API VIA AJAX POST
async function handleFormSubmit(e) {
    e.preventDefault();

    const id = itemIdInput.value.trim().toUpperCase();
    const name = itemNameInput.value.trim();
    const category = itemCategoryInput.value;
    const stock = parseInt(itemStockInput.value);
    const inUse = parseInt(itemInUseInput.value);

    if (stock < 0 || inUse < 0) {
        showToast("error", "Input Tidak Valid", "Stok dan Jumlah Dipakai tidak boleh bernilai negatif.");
        return;
    }

    const initialLocationInput = document.getElementById('initialLocationInput');
    const initialLocationId = initialLocationInput ? initialLocationInput.value : '';

    if (!isEditMode && stock > 0 && !initialLocationId) {
        showToast("error", "Lokasi Wajib", "Silakan pilih lokasi penyimpanan awal untuk stok barang.");
        return;
    }

    const payload = {
        id: id,
        name: name,
        category: category,
        stock: stock,
        dipakai: inUse
    };

    try {
        const response = await fetch('api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });
        
        const result = await response.json();
        
        if (!response.ok) {
            throw new Error(result.error || "Terjadi kegagalan server.");
        }

        // Catat mutasi masuk jika menambahkan barang baru dengan stok awal > 0
        if (!isEditMode && stock > 0 && initialLocationId) {
            const mutasiPayload = {
                barang_id: id,
                tipe: 'masuk',
                jumlah: stock,
                lokasi_asal: null,
                lokasi_tuju: parseInt(initialLocationId),
                catatan: 'Inisialisasi stok awal saat input barang baru',
                user_id: 1
            };

            try {
                const mutasiResponse = await fetch(API_CONFIG.MUTASI, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(mutasiPayload)
                });
                
                if (!mutasiResponse.ok) {
                    const mutasiResult = await mutasiResponse.json();
                    showToast("warning", "Peringatan", "Barang disimpan, namun gagal mencatat penempatan lokasi rak: " + (mutasiResult.message || ""));
                }
            } catch (mutasiErr) {
                console.error("Gagal mengirim mutasi:", mutasiErr);
                showToast("warning", "Peringatan", "Gagal menghubungi backend Node.js untuk pencatatan lokasi.");
            }
        }

        await loadInventory();
        closeModal();
        
        if (isEditMode) {
            showToast("success", "Perubahan Disimpan", `Data barang ${id} berhasil diperbarui.`);
        } else {
            showToast("success", "Barang Ditambahkan", `Barang ${name} berhasil disimpan ke database MySQL.`);
        }
    } catch (error) {
        showToast("error", "Gagal Menyimpan", error.message || "Gagal mengirim data ke server database.");
    }
}

// DIALOG CONFIRM DELETE CONTROL
function confirmDelete(id, name) {
    if (currentUserRole !== 'admin') {
        showToast("error", "Akses Ditolak", "Hanya Admin yang dapat menghapus barang.");
        return;
    }
    deleteTargetId = id;
    if (deleteItemName) deleteItemName.innerText = `"${id} - ${name}"`;
    if (confirmDeleteBtn) confirmDeleteBtn.onclick = executeDelete;
    deleteDialog?.classList.remove("hidden");
}

function closeDeleteDialog() {
    deleteDialog?.classList.add("hidden");
    deleteTargetId = null;
}

async function executeDelete() {
    if (!deleteTargetId) return;

    try {
        const response = await fetch(`api.php?id=${encodeURIComponent(deleteTargetId)}`, {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (!response.ok) {
            throw new Error(result.error || "Gagal menghapus data dari server.");
        }

        await loadInventory();
        closeDeleteDialog();
        showToast("success", "Barang Dihapus", "Barang berhasil dihapus dari database MySQL.");
    } catch (error) {
        showToast("error", "Gagal Menghapus", error.message || "Gagal menghapus data dari server database.");
    }
}

// SYSTEM FLOATING TOASTS
function showToast(type = "success", title = "", message = "") {
    const container = document.getElementById("toastContainer");
    if (!container) return; // Mencegah crash jika kontainer toast tidak ada di HTML
    
    let iconName = "check-circle";
    let iconColorClass = "text-emerald-500";
    let borderClass = "border-emerald-100";
    
    if (type === "error") {
        iconName = "alert-octagon";
        iconColorClass = "text-rose-500";
        borderClass = "border-rose-100";
    } else if (type === "info") {
        iconName = "info";
        iconColorClass = "text-blue-500";
        borderClass = "border-blue-100";
    }

    const toastId = "toast_" + Date.now();
    const toast = document.createElement("div");
    toast.id = toastId;
    toast.className = `animate-toast pointer-events-auto bg-white border ${borderClass} rounded-2xl p-4 shadow-modal flex gap-3 max-w-sm w-full transition-all duration-300`;
    toast.innerHTML = `
        <div class="${iconColorClass} shrink-0 mt-0.5">
            <i data-lucide="${iconName}" class="w-5 h-5"></i>
        </div>
        <div class="flex-1">
            <h4 class="text-xs font-bold text-slate-800 leading-none">${title}</h4>
            <p class="text-[11px] text-slate-400 mt-1 leading-normal">${message}</p>
        </div>
        <button onclick="dismissToast('${toastId}')" class="text-slate-300 hover:text-slate-500 shrink-0 self-start p-0.5 transition-colors">
            <i data-lucide="x" class="w-3.5 h-3.5"></i>
        </button>
    `;

    container.appendChild(toast);
    if (window.lucide) lucide.createIcons();

    setTimeout(() => {
        dismissToast(toastId);
    }, 4000);
}

function dismissToast(id) {
    const toast = document.getElementById(id);
    if (!toast) return;

    toast.style.opacity = "0";
    toast.style.transform = "translateY(15px) scale(0.95)";
    
    setTimeout(() => {
        toast.remove();
    }, 300);
}