<?php
// Direktori: partials/academic/course-publish.php
// Isi halaman Publikasi Kursus (dipakai admin dan karyawan Operasional).
// Variabel dari halaman pemanggil: $cpBackUrl (tautan kembali ke Kelola Kursus).
// Logika: assets/js/pages/admin/course-publish.js | Gaya: assets/css/course-publish.css
?>
        <main class="dashboard-main cpb-main">
            <header class="cpb-head">
                <div>
                    <a class="cpb-crumb" href="<?= htmlspecialchars($cpBackUrl ?? '#', ENT_QUOTES) ?>"><i class="bi bi-arrow-left"></i> Kelola Kursus</a>
                    <h1 class="cpb-title" id="cpbTitle">Memuat...</h1>
                    <p class="cpb-sub" id="cpbSub">Susun modul dan judul materi. Isi tiap materi diisi oleh tentor.</p>
                </div>
                <div class="cpb-head-actions" id="cpbHeadActions"></div>
            </header>

            <div id="cpbError"></div>

            <section class="cpb-steps" id="cpbSteps" aria-label="Kesiapan publikasi"></section>

            <div class="cpb-grid">
                <section class="cpb-card cpb-structure">
                    <div class="cpb-card-head">
                        <div>
                            <h2 class="cpb-card-title">Struktur kursus</h2>
                            <p class="cpb-muted" id="cpbCount">-</p>
                        </div>
                        <div class="cpb-tools">
                            <button type="button" class="btn btn-ghost" id="cpbCopyBtn" hidden><i class="bi bi-files"></i> Salin dari kursus lain</button>
                            <button type="button" class="btn btn-ghost" id="cpbPasteBtn"><i class="bi bi-clipboard-plus"></i> Tempel daftar</button>
                            <button type="button" class="btn btn-primary" id="cpbAddModule"><i class="bi bi-plus-lg"></i> Modul</button>
                        </div>
                    </div>
                    <div class="cpb-hint">
                        <i class="bi bi-lightbulb"></i>
                        <span>Cukup isi <strong>judul</strong>. Tekan <kbd>Enter</kbd> pada judul materi untuk langsung menambah materi berikutnya. Tujuan, materi, penugasan, dan durasi diisi tentor.</span>
                    </div>
                    <div id="cpbTree" class="cpb-tree"><div class="cpb-skel"></div></div>
                </section>

                <aside class="cpb-card cpb-side">
                    <div class="cpb-card-head">
                        <div>
                            <h2 class="cpb-card-title">Angkatan</h2>
                            <p class="cpb-muted">Satu angkatan = satu tentor.</p>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary cpb-block" id="cpbNewAngkatan"><i class="bi bi-plus-circle"></i> Buat angkatan baru</button>
                    <p class="cpb-note" id="cpbAngkatanNote" hidden></p>
                    <div id="cpbAngkatan" class="cpb-angkatan"></div>
                </aside>
            </div>

            <div class="cpb-savebar" id="cpbSaveBar" hidden>
                <div>
                    <div class="cpb-savebar-title">Perubahan belum disimpan</div>
                    <div class="cpb-muted" id="cpbSaveText"></div>
                </div>
                <div class="cpb-tools">
                    <button type="button" class="btn btn-ghost" id="cpbDiscard">Batalkan</button>
                    <button type="button" class="btn btn-primary" id="cpbSave"><i class="bi bi-check2"></i> Simpan struktur</button>
                </div>
            </div>
        </main>

        <!-- Modal tempel daftar -->
        <div class="cpb-modal" id="cpbPasteModal" hidden>
            <div class="cpb-modal-box">
                <h3>Tempel daftar modul &amp; materi</h3>
                <p class="cpb-muted">Satu baris satu judul. Baris diawali <code>#</code> atau <code>Modul</code> menjadi modul, baris lainnya menjadi materi di bawahnya.</p>
                <textarea id="cpbPasteText" class="form-input cpb-paste" rows="10" placeholder="# Pengenalan Excel&#10;Mengenal antarmuka&#10;Rumus dasar&#10;# Pengolahan data&#10;Sort dan filter"></textarea>
                <p class="cpb-muted" id="cpbPastePreview"></p>
                <div class="cpb-modal-actions">
                    <button type="button" class="btn btn-ghost" data-close>Batal</button>
                    <button type="button" class="btn btn-primary" id="cpbPasteApply">Tambahkan ke struktur</button>
                </div>
            </div>
        </div>

        <!-- Modal salin struktur -->
        <div class="cpb-modal" id="cpbCopyModal" hidden>
            <div class="cpb-modal-box">
                <h3>Salin struktur dari kursus lain</h3>
                <p class="cpb-muted">Hanya judul modul dan materi yang disalin. Isi tiap materi tetap diisi tentor.</p>
                <select id="cpbCopySelect" class="form-input"></select>
                <div class="cpb-modal-actions">
                    <button type="button" class="btn btn-ghost" data-close>Batal</button>
                    <button type="button" class="btn btn-primary" id="cpbCopyApply">Salin struktur</button>
                </div>
            </div>
        </div>

        <!-- Modal angkatan baru -->
        <div class="cpb-modal" id="cpbAngModal" hidden>
            <div class="cpb-modal-box cpb-modal-wide">
                <h3>Buat angkatan baru</h3>
                <p class="cpb-muted">Memakai kurikulum yang sama, jadi isi dari tentor otomatis terbawa. Sesi dijadwalkan dari daftar materi.</p>
                <p class="cpb-error" id="cpbAngError" hidden></p>
                <div class="cpb-form">
                    <div class="cpb-field cpb-span2"><label for="angName">Nama angkatan</label><input id="angName" class="form-input" maxlength="150"></div>
                    <div class="cpb-field"><label for="angStart">Tanggal mulai</label><input id="angStart" type="date" class="form-input"></div>
                    <div class="cpb-field"><label for="angQuota">Kuota peserta</label><input id="angQuota" type="number" min="1" class="form-input"></div>
                    <div class="cpb-field cpb-span2"><label for="angTentor">Tentor</label><select id="angTentor" class="form-input"></select></div>
                    <div class="cpb-field cpb-span2">
                        <label>Hari belajar</label>
                        <div class="cpb-days" id="angDays"></div>
                    </div>
                    <div class="cpb-field"><label for="angFrom">Jam mulai</label><input id="angFrom" type="time" class="form-input"></div>
                    <div class="cpb-field"><label for="angTo">Jam selesai</label><input id="angTo" type="time" class="form-input"></div>
                    <div class="cpb-field"><label for="angJp">JP per sesi</label><input id="angJp" type="number" min="0" step="0.5" class="form-input"></div>
                    <div class="cpb-field"><label for="angStatus">Status</label>
                        <select id="angStatus" class="form-input"><option value="open">Open (terbuka untuk pendaftaran)</option><option value="draft">Draft</option></select>
                    </div>
                    <div class="cpb-field"><label for="angLocation">Lokasi (opsional)</label><input id="angLocation" class="form-input"></div>
                    <div class="cpb-field"><label for="angRoom">Ruang (opsional)</label><input id="angRoom" class="form-input"></div>
                </div>
                <div class="cpb-preview" id="angPreview"></div>
                <div class="cpb-modal-actions">
                    <button type="button" class="btn btn-ghost" data-close>Batal</button>
                    <button type="button" class="btn btn-primary" id="angSubmit"><i class="bi bi-check2"></i> Buat angkatan</button>
                </div>
            </div>
        </div>