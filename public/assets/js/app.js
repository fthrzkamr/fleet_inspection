/**
 * FleetGuard Digital - Client Script Helper
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Inisialisasi preview upload foto instan
    initImageUploadPreviews();

    // 2. Inisialisasi Auto-dismiss Alert
    initAutoDismissAlerts();

    // 3. Inisialisasi tabel data ber-pagination tanpa reload halaman penuh
    initAjaxTable();

    // 4. Inisialisasi dropdown kendaraan/dll yang bisa dicari (banyak opsi)
    initSearchableSelects(document);
});

/**
 * Ubah <select data-searchable> (opsi banyak, misal daftar kendaraan) jadi
 * kotak input yang bisa diketik untuk mencari, tanpa library luar.
 * Select asli tetap ada (disembunyikan) supaya form tetap submit normal.
 */
function initSearchableSelects(root) {
    (root || document).querySelectorAll('select[data-searchable]').forEach(select => {
        if (select.dataset.searchableReady) return;
        select.dataset.searchableReady = '1';
        enhanceSearchableSelect(select);
    });
}

function enhanceSearchableSelect(select) {
    const options = Array.from(select.options).map(o => ({ value: o.value, label: o.textContent.trim() }));

    const wrapper = document.createElement('div');
    wrapper.className = 'relative';

    const input = document.createElement('input');
    input.type = 'text';
    input.autocomplete = 'off';
    input.placeholder = select.getAttribute('data-placeholder') || 'Ketik untuk cari...';
    input.className = select.className;

    const dropdown = document.createElement('div');
    dropdown.className = 'hidden absolute z-30 left-0 right-0 mt-1 max-h-60 overflow-y-auto bg-white border border-slate-300 rounded-lg shadow-lg';

    function currentLabel() {
        if (!select.value) return ''; // biar kotak kosong (pakai placeholder), bukan teks "-- Pilih --"
        const found = options.find(o => o.value === select.value);
        return found ? found.label : '';
    }

    function renderList(filterText) {
        const f = filterText.trim().toLowerCase();
        const list = f ? options.filter(o => o.label.toLowerCase().includes(f)) : options;
        dropdown.innerHTML = '';
        if (!list.length) {
            dropdown.innerHTML = '<div class="px-3 py-2 text-xs text-slate-400">Tidak ditemukan</div>';
            return;
        }
        list.forEach(o => {
            const item = document.createElement('div');
            item.className = 'px-3 py-2 text-xs text-slate-800 hover:bg-blue-50 cursor-pointer' + (o.value === select.value ? ' bg-blue-50 font-semibold' : '');
            item.textContent = o.label || '(Semua)';
            item.addEventListener('click', () => {
                select.value = o.value;
                input.value = o.label;
                dropdown.classList.add('hidden');
                select.dispatchEvent(new Event('change', { bubbles: true }));
            });
            dropdown.appendChild(item);
        });
    }

    input.value = currentLabel();

    input.addEventListener('focus', () => {
        renderList('');
        input.select();
        dropdown.classList.remove('hidden');
    });

    input.addEventListener('input', () => {
        renderList(input.value);
        dropdown.classList.remove('hidden');
    });

    // Kalau user mengetik lalu klik di luar (termasuk klik tombol Filter) atau langsung
    // submit (Enter) tanpa sempat klik salah satu saran, teks yang diketik dicocokkan dulu
    // ke opsi yang paling sesuai sebelum dianggap "batal". Tanpa ini, klik tombol Filter
    // akan mereset teksnya ke kosong duluan sebelum form sempat submit dengan nilainya.
    function commitTypedValue() {
        if (input.value.trim() === currentLabel()) return;
        const typed = input.value.trim().toLowerCase();
        if (typed === '') {
            select.value = '';
            input.value = '';
            return;
        }
        const match = options.find(o => o.label.toLowerCase().includes(typed));
        if (match) {
            select.value = match.value;
            input.value = match.label;
            select.dispatchEvent(new Event('change', { bubbles: true }));
        } else {
            input.value = currentLabel();
        }
    }

    document.addEventListener('click', (e) => {
        if (!wrapper.contains(e.target)) {
            dropdown.classList.add('hidden');
            commitTypedValue();
        }
    });

    select.classList.add('hidden');
    select.parentNode.insertBefore(wrapper, select);
    wrapper.appendChild(input);
    wrapper.appendChild(dropdown);
    wrapper.appendChild(select);

    // Supaya bisa disinkronkan ulang kalau value di-set lewat JS (mis. saat buka modal edit)
    select._syncSearchDisplay = () => { input.value = currentLabel(); };

    // Jaga-jaga untuk submit lewat tombol Enter tanpa klik apapun di luar (mis. langsung
    // Enter di keyboard), commitTypedValue tetap dijalankan sebelum form benar-benar submit.
    const form = select.closest('form');
    if (form) {
        form.addEventListener('submit', commitTypedValue);
    }
}

function syncSearchableSelect(select) {
    if (select && typeof select._syncSearchDisplay === 'function') {
        select._syncSearchDisplay();
    }
}

/**
 * Navigasi tabel data (pagination, ganti jumlah tampilan, cari/filter) tanpa
 * me-reload seluruh halaman. Ambil ulang isi #ajax-table-wrap via fetch,
 * lalu ganti isinya + update URL browser (agar refresh & tombol back tetap benar).
 */
function initAjaxTable() {
    const wrap = document.getElementById('ajax-table-wrap');
    if (!wrap) return;

    wrap.addEventListener('click', function(e) {
        const a = e.target.closest('a[href]');
        if (!a || a.hasAttribute('data-no-ajax')) return;
        const href = a.getAttribute('href');
        if (href && href.startsWith('?')) {
            e.preventDefault();
            loadAjaxTable(href, true);
        }
    });

    wrap.addEventListener('submit', function(e) {
        const form = e.target.closest('form');
        if (!form || (form.getAttribute('method') || 'get').toLowerCase() !== 'get') return;
        e.preventDefault();
        const qs = new URLSearchParams(new FormData(form)).toString();
        loadAjaxTable('?' + qs, true);
    });

    window.addEventListener('popstate', function() {
        loadAjaxTable(window.location.pathname + window.location.search, false);
    });
}

function ajaxNavigate(url) {
    loadAjaxTable(url, true);
}

function loadAjaxTable(url, pushState) {
    const wrap = document.getElementById('ajax-table-wrap');
    if (!wrap) return;

    const sep = url.includes('?') ? '&' : '?';
    wrap.style.opacity = '0.5';

    fetch(url + sep + 'ajax=1', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(res => {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.text();
        })
        .then(html => {
            wrap.innerHTML = html;
            wrap.style.opacity = '1';
            initSearchableSelects(wrap);
            if (pushState) {
                history.pushState({ ajaxTable: true }, '', url);
            }
        })
        .catch(() => {
            // Fallback: kalau AJAX gagal, tetap navigasi biasa supaya tidak "macet"
            window.location.href = url;
        });
}

/**
 * Handle live thumbnail preview saat memilih file atau foto kamera
 */
function initImageUploadPreviews() {
    document.querySelectorAll('input[type="file"][data-preview-target]').forEach(input => {
        input.addEventListener('change', function(e) {
            const targetId = this.getAttribute('data-preview-target');
            const targetImg = document.getElementById(targetId);
            const container = document.getElementById(targetId + '_container');
            
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(evt) {
                    if (targetImg) {
                        targetImg.src = evt.target.result;
                    }
                    if (container) {
                        container.classList.remove('hidden');
                    }
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    });
}

/**
 * Hapus alert "berhasil" otomatis setelah 5 detik jika tidak ditutup manual.
 * Alert "gagal/perhatian" & "informasi" sengaja TIDAK auto-hilang, supaya sempat
 * dibaca/disalin (mis. daftar baris gagal saat import) — user tutup manual lewat tombol X.
 */
function initAutoDismissAlerts() {
    setTimeout(() => {
        document.querySelectorAll('.alert-box[data-type="success"]').forEach(el => {
            el.style.opacity = '0';
            el.style.transition = 'opacity 0.5s ease';
            setTimeout(() => el.remove(), 500);
        });
    }, 5000);
}

/**
 * Modal helper: Buka & Tutup modal
 */
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.classList.add('overflow-hidden');
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.classList.remove('overflow-hidden');
    }
}

/**
 * Universal Confirmation Modal Controller
 */
let confirmCallback = null;

function showConfirmModal(opts) {
    const options = Object.assign({
        title: 'Konfirmasi Tindakan',
        message: 'Apakah Anda yakin ingin melanjutkan tindakan ini?',
        confirmText: 'Ya, Lanjutkan',
        confirmType: 'danger', // 'danger' | 'warning' | 'primary'
        icon: null,
        onConfirm: null
    }, opts);

    const modal = document.getElementById('universal-confirm-modal');
    if (!modal) {
        // Fallback jika modal tidak tersedia
        if (options.onConfirm) options.onConfirm();
        return;
    }

    document.getElementById('confirm-modal-title').textContent = options.title;
    document.getElementById('confirm-modal-message').textContent = options.message;

    const actionBtn = document.getElementById('confirm-modal-action-btn');
    actionBtn.textContent = options.confirmText;

    const iconContainer = document.getElementById('confirm-modal-icon-container');
    const iconEl = document.getElementById('confirm-modal-icon');

    // Base button classes
    actionBtn.className = 'text-xs py-2 px-4 font-bold shadow-xs rounded-lg transition-colors ';
    iconContainer.className = 'w-11 h-11 rounded-xl flex items-center justify-center text-lg flex-shrink-0 ';

    if (options.confirmType === 'danger') {
        actionBtn.className += 'bg-rose-600 hover:bg-rose-700 text-white';
        iconContainer.className += 'bg-rose-50 border border-rose-200 text-rose-600';
        iconEl.className = options.icon || 'fa-solid fa-trash-can';
    } else if (options.confirmType === 'warning') {
        actionBtn.className += 'bg-amber-600 hover:bg-amber-700 text-white';
        iconContainer.className += 'bg-amber-50 border border-amber-200 text-amber-600';
        iconEl.className = options.icon || 'fa-solid fa-triangle-exclamation';
    } else {
        actionBtn.className += 'bg-blue-700 hover:bg-blue-800 text-white';
        iconContainer.className += 'bg-blue-50 border border-blue-200 text-blue-700';
        iconEl.className = options.icon || 'fa-solid fa-circle-question';
    }

    confirmCallback = options.onConfirm;

    actionBtn.onclick = function() {
        const cb = confirmCallback;
        closeConfirmModal();
        if (typeof cb === 'function') {
            cb();
        }
    };

    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.classList.add('overflow-hidden');
}

function closeConfirmModal() {
    const modal = document.getElementById('universal-confirm-modal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.classList.remove('overflow-hidden');
    }
    confirmCallback = null;
}

/**
 * Helper Konfirmasi Navigasi Link (Ganti browser confirm link)
 */
function confirmNav(url, title, message, confirmText = 'Ya, Hapus', type = 'danger') {
    showConfirmModal({
        title: title,
        message: message,
        confirmText: confirmText,
        confirmType: type,
        onConfirm: () => {
            window.location.href = url;
        }
    });
}

/**
 * Helper Konfirmasi Submit Form (Ganti browser confirm form)
 */
function confirmFormSubmit(e, form, options = {}) {
    e.preventDefault();
    showConfirmModal({
        title: options.title || 'Konfirmasi Tindakan',
        message: options.message || 'Apakah Anda yakin ingin melanjutkan proses ini?',
        confirmText: options.confirmText || 'Ya, Lanjutkan',
        confirmType: options.confirmType || 'warning',
        onConfirm: () => {
            form.submit();
        }
    });
    return false;
}

/**
 * Salin teks ke clipboard dengan notifikasi toast
 */
function copyToClipboard(text, successMsg = 'Teks berhasil disalin!') {
    navigator.clipboard.writeText(text).then(() => {
        showToast(successMsg, 'success');
    }).catch(err => {
        showToast('Gagal menyalin teks.', 'error');
    });
}

/**
 * Toast Notification Popup
 */
function showToast(message, type = 'info') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'fixed bottom-5 right-5 z-50 flex flex-col gap-2 pointer-events-none';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    const colors = {
        success: 'bg-emerald-600 text-white border-emerald-500',
        error: 'bg-rose-600 text-white border-rose-500',
        warning: 'bg-amber-600 text-white border-amber-500',
        info: 'bg-blue-600 text-white border-blue-500'
    };

    toast.className = `pointer-events-auto p-4 rounded-xl shadow-xl border text-sm font-medium flex items-center gap-3 transition-all duration-300 transform translate-y-4 opacity-0 ${colors[type] || colors.info}`;
    toast.innerHTML = `<span>${message}</span>`;

    container.appendChild(toast);

    requestAnimationFrame(() => {
        toast.classList.remove('translate-y-4', 'opacity-0');
    });

    setTimeout(() => {
        toast.classList.add('translate-y-4', 'opacity-0');
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

/**
 * Konfirmasi Hapus Cabang - submit POST form setelah konfirmasi modal
 */
function confirmDeleteCabang(id, nama) {
    showConfirmModal({
        title: 'Hapus Data Cabang',
        message: 'Apakah Anda yakin ingin menghapus cabang "' + nama + '"? Tindakan ini tidak dapat dibatalkan.',
        confirmText: 'Ya, Hapus Cabang',
        confirmType: 'danger',
        icon: 'fa-solid fa-trash-can',
        onConfirm: function() {
            var form = document.getElementById('delete-form-' + id);
            if (form) {
                form.submit();
            }
        }
    });
}
