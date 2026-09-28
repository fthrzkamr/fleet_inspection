    </div>

    <footer class="mt-auto border-t border-slate-200 bg-white py-4 text-xs text-slate-500 no-print">
        <div class="px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-2.5">
            <div class="flex items-center gap-2 text-slate-600">
                <span class="font-bold text-slate-800"><?= APP_NAME ?></span> &copy; <?= date('Y') ?> &bull; Divisi Operasional & Manajemen Armada
            </div>
            <div class="flex items-center gap-3 text-slate-500 text-xs">
                <span>Versi 1.0.0</span>
                <span>&bull;</span>
                <a href="<?= BASE_URL ?>/public/scan.php" class="text-blue-700 hover:text-blue-900 font-semibold flex items-center gap-1">
                    <i class="fa-solid fa-qrcode"></i> Pindai Stiker QR
                </a>
            </div>
        </div>
    </footer>
</main>
</div>

<!-- Universal Enterprise Confirmation Modal -->
<div id="universal-confirm-modal" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs hidden items-center justify-center p-4 no-print">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-md w-full p-6 relative space-y-4">
        <div class="flex items-start gap-3.5">
            <div id="confirm-modal-icon-container" class="w-11 h-11 rounded-xl bg-rose-50 border border-rose-200 flex items-center justify-center text-lg text-rose-600 flex-shrink-0">
                <i id="confirm-modal-icon" class="fa-solid fa-trash-can"></i>
            </div>
            <div class="flex-1 pt-0.5">
                <h3 id="confirm-modal-title" class="text-sm font-bold text-slate-900 leading-tight">Konfirmasi Tindakan</h3>
                <p id="confirm-modal-message" class="text-xs text-slate-500 mt-1 leading-relaxed">Apakah Anda yakin ingin melanjutkan tindakan ini?</p>
            </div>
        </div>

        <div class="pt-2 flex items-center justify-end gap-2 border-t border-slate-100">
            <button type="button" id="confirm-modal-cancel-btn" onclick="closeConfirmModal()" class="btn-secondary text-xs py-2 px-3.5 font-semibold">
                Batal
            </button>
            <button type="button" id="confirm-modal-action-btn" class="text-xs py-2 px-4 font-bold shadow-xs rounded-lg transition-colors bg-rose-600 hover:bg-rose-700 text-white">
                Ya, Lanjutkan
            </button>
        </div>
    </div>
</div>

<!-- App JS Client Helper -->
<script src="<?= BASE_URL ?>/public/assets/js/app.js?v=<?= filemtime(BASE_PATH . '/public/assets/js/app.js') ?>"></script>

<?php if (isset($extraScripts)): ?>
    <?= $extraScripts ?>
<?php endif; ?>

</body>
</html>
