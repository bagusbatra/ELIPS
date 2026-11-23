<?php
// Direktori: partials/finance/payments.php
// Isi halaman Pembayaran (dipakai bersama oleh admin dan karyawan Keuangan).
// Logika: assets/js/pages/finance/payments.js
?>
        <main class="dashboard-main">
            <div class="dashboard-header">
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
                    <div>
                        <h1 class="dashboard-title">Pembayaran Siswa</h1>
                        <p class="dashboard-subtitle">Verifikasi bukti bayar, catat pembayaran sebagian, atau lunasi tagihan. Nomor siswa EC-xxxxxx terbit otomatis saat tagihan lunas.</p>
                    </div>
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <button class="btn btn-outline" type="button" id="finBankBtn" hidden><i class="bi bi-bank"></i> Rekening pembayaran</button>
                        <button class="btn btn-primary" type="button" id="finRefreshBtn"><i class="bi bi-arrow-clockwise"></i> Muat ulang</button>
                    </div>
                </div>
            </div>

            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-card-icon orange"><i class="bi bi-hourglass-split"></i></div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="finStatPending">0</div>
                        <div class="stat-card-label">Bukti Menunggu Verifikasi</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon blue"><i class="bi bi-receipt"></i></div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="finStatOpen">0</div>
                        <div class="stat-card-label">Tagihan Belum Lunas</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon purple"><i class="bi bi-cash-stack"></i></div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="finStatRemaining">Rp 0</div>
                        <div class="stat-card-label">Total Sisa Tagihan</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon green"><i class="bi bi-patch-check"></i></div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="finStatPaid">0</div>
                        <div class="stat-card-label">Tagihan Lunas</div>
                    </div>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header" style="flex-wrap: wrap; gap: 1rem;">
                    <h2 class="dashboard-section-title">Daftar Tagihan</h2>
                    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                        <select class="form-input" id="finFilterStatus" style="max-width: 220px;">
                            <option value="open">Belum lunas</option>
                            <option value="pending">Ada bukti menunggu verifikasi</option>
                            <option value="unpaid">Belum bayar</option>
                            <option value="partial">Dibayar sebagian</option>
                            <option value="paid">Lunas</option>
                            <option value="all">Semua</option>
                        </select>
                        <input type="text" id="finSearch" class="form-input" style="max-width: 240px;" placeholder="Cari nama, email, no. siswa...">
                    </div>
                </div>
                <div class="dashboard-section-body" style="padding: 0; overflow-x: auto;">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Siswa</th>
                                <th>Program / Kelas</th>
                                <th>Tagihan</th>
                                <th>Terbayar</th>
                                <th>Sisa</th>
                                <th>Status</th>
                                <th>No. Siswa</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="finTable"></tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Detail & verifikasi -->
    <div class="attendance-modal" id="finModal" style="display: none;">
        <div class="attendance-modal-card attendance-detail-card" style="max-width: 760px;">
            <div class="attendance-modal-header">
                <div>
                    <div class="attendance-modal-title" id="finModalTitle">Verifikasi Pembayaran</div>
                    <div class="attendance-modal-subtitle" id="finModalSubtitle"></div>
                </div>
                <button class="attendance-modal-close" type="button" id="finModalClose" aria-label="Tutup"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="attendance-modal-body">
                <p class="reg-inline-error" id="finError" role="alert" hidden></p>
                <div class="fin-summary" id="finSummary"></div>

                <h3 class="fin-h3">Riwayat pembayaran</h3>
                <div id="finPayments"></div>

                <div id="finRecordWrap">
                    <h3 class="fin-h3">Catat pembayaran langsung</h3>
                    <p class="form-hint">Untuk pembayaran tunai/offline atau pembayaran sebagian. Langsung terverifikasi.</p>
                    <div class="fin-form-grid">
                        <div class="form-group">
                            <label class="form-label" for="finAmount">Nominal (Rp)</label>
                            <input class="form-input" type="text" inputmode="numeric" id="finAmount" placeholder="contoh: 500000">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="finMethod">Metode</label>
                            <select class="form-input" id="finMethod"></select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="finDate">Tanggal bayar</label>
                            <input class="form-input" type="date" id="finDate">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="finNote">Catatan</label>
                            <input class="form-input" type="text" id="finNote" maxlength="500">
                        </div>
                    </div>
                    <div class="fin-actions">
                        <button class="btn btn-outline" type="button" id="finRecordBtn"><i class="bi bi-plus-circle"></i> Catat pembayaran</button>
                        <button class="btn btn-primary" type="button" id="finPaidBtn"><i class="bi bi-check2-circle"></i> Lunas Pembayaran</button>
                    </div>
                    <div class="form-hint">“Lunas Pembayaran” mencatat seluruh sisa tagihan sebagai terbayar dan menerbitkan nomor siswa.</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Setujui / tolak / batalkan -->
    <div class="attendance-modal" id="finActionModal" style="display: none;">
        <div class="attendance-modal-card" style="max-width: 460px;">
            <div class="attendance-modal-header">
                <div>
                    <div class="attendance-modal-title" id="finActionTitle"></div>
                    <div class="attendance-modal-subtitle" id="finActionSubtitle"></div>
                </div>
                <button class="attendance-modal-close" type="button" id="finActionClose" aria-label="Tutup"><i class="bi bi-x-lg"></i></button>
            </div>
            <form id="finActionForm">
                <div class="attendance-modal-body">
                    <p class="reg-inline-error" id="finActionError" role="alert" hidden></p>
                    <div class="form-group" id="finActionAmountGroup">
                        <label class="form-label" for="finActionAmount">Nominal yang disetujui (Rp)</label>
                        <input class="form-input" type="text" inputmode="numeric" id="finActionAmount">
                        <div class="form-hint">Ubah bila nominal di bukti berbeda dari yang diisi siswa.</div>
                    </div>
                    <div class="form-group" id="finActionReasonGroup">
                        <label class="form-label" for="finActionReason" id="finActionReasonLabel">Alasan</label>
                        <textarea class="form-input" id="finActionReason" rows="3" maxlength="500"></textarea>
                    </div>
                </div>
                <div class="attendance-modal-actions" style="padding: 0 1.5rem 1.5rem;">
                    <button class="btn btn-outline" type="button" id="finActionCancel">Kembali</button>
                    <button class="btn btn-primary" type="submit" id="finActionSubmit">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Rekening pembayaran (admin) -->
    <div class="attendance-modal" id="finBankModal" style="display: none;">
        <div class="attendance-modal-card" style="max-width: 460px;">
            <div class="attendance-modal-header">
                <div>
                    <div class="attendance-modal-title">Rekening Pembayaran</div>
                    <div class="attendance-modal-subtitle">Ditampilkan kepada siswa di halaman tagihan.</div>
                </div>
                <button class="attendance-modal-close" type="button" id="finBankClose" aria-label="Tutup"><i class="bi bi-x-lg"></i></button>
            </div>
            <form id="finBankForm">
                <div class="attendance-modal-body">
                    <p class="reg-inline-error" id="finBankError" role="alert" hidden></p>
                    <div class="form-group"><label class="form-label" for="finBankName">Nama bank</label><input class="form-input" id="finBankName" maxlength="80"></div>
                    <div class="form-group"><label class="form-label" for="finBankNumber">Nomor rekening</label><input class="form-input" id="finBankNumber" maxlength="40"></div>
                    <div class="form-group"><label class="form-label" for="finBankHolder">Atas nama</label><input class="form-input" id="finBankHolder" maxlength="100"></div>
                    <div class="form-group"><label class="form-label" for="finBankNote">Catatan untuk siswa</label><textarea class="form-input" id="finBankNote" rows="2" maxlength="300"></textarea></div>
                </div>
                <div class="attendance-modal-actions" style="padding: 0 1.5rem 1.5rem;">
                    <button class="btn btn-outline" type="button" id="finBankCancel">Batal</button>
                    <button class="btn btn-primary" type="submit">Simpan</button>
                </div>
            </form>
        </div>
    </div>