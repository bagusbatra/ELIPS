<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$page = [
    'page_script' => 'instructor/manage-course',
    'roles' => ['instruktur'],
    'title' => 'Kelola Kursus - ELIPS LMS',
    'area' => 'instructor',
    'scripts' => ['@crud', 'crud-academic', 'crud-tasks', 'ui'],
    'sidebar' => 'instructor',
];
layout('app', $page);
?>
        <main class="dashboard-main">
            <div class="dashboard-header">
                <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
                    <div>
                        <h1 class="dashboard-title">Kelola Kursus</h1>
                        <p class="dashboard-subtitle">Kelola modul dan materi pada kursus yang ditugaskan Admin kepada Anda.</p>
                    </div>
                    <a href="<?= url('instructor/students.php') ?>" class="btn btn-outline"><i class="bi bi-people"></i> Lihat Peserta</a>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header">
                    <h2 class="dashboard-section-title">Kursus yang Ditugaskan</h2>
                    <span class="badge badge-primary" id="courseCountBadge">0 Kursus</span>
                </div>
                <div class="dashboard-section-body" style="padding: 0; overflow-x: auto;">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Kursus</th>
                                <th>Modul / Pelajaran</th>
                                <th>Peserta</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="instructorCoursesBody"></tbody>
                    </table>
                </div>
            </div>

            <div class="dashboard-section" id="structureSection">
                <div class="dashboard-section-header" style="flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <h2 class="dashboard-section-title">Struktur Kursus</h2>
                        <span style="color: var(--text-secondary); font-size: 0.875rem;" id="structureCourseLabel">Pilih kursus untuk melihat strukturnya</span>
                    </div>
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <select id="structureCourseSelect" class="form-input" style="max-width: 280px;" onchange="selectCourseForStructure(this.value)"></select>
                        <button class="btn btn-primary" onclick="openModuleModal()"><i class="bi bi-folder-plus"></i> Tambah Modul</button>
                    </div>
                </div>
                <div class="dashboard-section-body">
                    <div style="padding: 1rem; background: #eff6ff; border-radius: var(--radius); color: #1e40af; font-size: 0.875rem; margin-bottom: 1rem;">
                        <i class="bi bi-info-circle"></i> Anda dapat menambah, mengubah, dan menghapus modul/materi pada kursus yang ditugaskan. Publikasi kursus dilakukan oleh Admin.
                    </div>
                    <div class="modules-list" id="instructorModules"></div>
                </div>
            </div>

            <div class="dashboard-section" id="classMaterialSection">
                <div class="dashboard-section-header" style="flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <h2 class="dashboard-section-title">Materi Tambahan &amp; Akses Kelas</h2>
                        <span style="color: var(--text-secondary); font-size: 0.875rem;" id="classMaterialLabel">Pilih kelas untuk mengelola materi tambahan</span>
                    </div>
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <select id="classMaterialClassSelect" class="form-input" style="max-width: 300px;" onchange="selectMaterialClass(this.value)"></select>
                        <button class="btn btn-primary" onclick="openMaterialModal()"><i class="bi bi-journal-plus"></i> Tambah Materi</button>
                    </div>
                </div>
                <div class="dashboard-section-body">
                    <div style="padding: 1rem; background: #eff6ff; border-radius: var(--radius); color: #1e40af; font-size: 0.875rem; margin-bottom: 1rem;">
                        <i class="bi bi-info-circle"></i> Materi tambahan tidak mengubah versi kurikulum. Atur mode akses kelas dan tambahkan bahan pendukung untuk peserta.
                    </div>
                    <div id="classMaterialSettingsPanel" style="display: none; padding: 1rem; border: 1px solid var(--border); border-radius: var(--radius); margin-bottom: 1rem;"></div>
                    <div id="classMaterialList"></div>
                </div>
            </div>

            <div class="dashboard-section" id="taskSection">
                <div class="dashboard-section-header" style="flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <h2 class="dashboard-section-title">Tugas &amp; Ujian</h2>
                        <span style="color: var(--text-secondary); font-size: 0.875rem;" id="taskClassLabel">Pilih kelas untuk mengelola tugas dan ujian</span>
                    </div>
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <select id="taskClassSelect" class="form-input" style="max-width: 300px;" onchange="selectTaskClass(this.value)"></select>
                        <button class="btn btn-primary" onclick="openAssignmentTaskModal()"><i class="bi bi-clipboard-plus"></i> Tambah Tugas</button>
                        <button class="btn btn-outline" onclick="openExamTaskModal()"><i class="bi bi-journal-check"></i> Tambah Ujian</button>
                    </div>
                </div>
                <div class="dashboard-section-body">
                    <div style="padding: 1rem; background: #eff6ff; border-radius: var(--radius); color: #1e40af; font-size: 0.875rem; margin-bottom: 1rem;">
                        <i class="bi bi-info-circle"></i> Tugas dapat berupa unggah file, jawaban teks, praktik kelas, atau referensi eksternal. Nilai dan revisi pengumpulan tercatat pada audit trail.
                    </div>
                    <div id="taskPanel"></div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal Modul -->
    <div id="moduleModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;">
        <div style="background: white; border-radius: var(--radius-lg); max-width: 520px; width: 100%; max-height: 90vh; overflow-y: auto;">
            <div style="padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between;">
                <h3 style="font-size: 1.25rem; font-weight: 600;" id="moduleModalTitle">Tambah Modul</h3>
                <button onclick="closeModuleModal()" aria-label="Tutup" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-secondary);">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <div style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label" for="moduleCourse">Kursus *</label>
                    <select id="moduleCourse" class="form-input"></select>
                    <small class="form-hint" id="moduleCourseHint">Pilih kursus yang Anda ampu.</small>
                </div>
                <div class="form-group">
                    <label class="form-label" for="moduleTitle">Judul Modul *</label>
                    <input type="text" id="moduleTitle" class="form-input" placeholder="Contoh: Modul 1: Pengantar Manajemen">
                </div>
                <div class="form-group">
                    <label class="form-label" for="moduleDesc">Deskripsi (Opsional)</label>
                    <textarea id="moduleDesc" class="form-input" rows="3" placeholder="Deskripsi singkat modul..."></textarea>
                </div>
            </div>
            <div style="padding: 1.5rem; border-top: 1px solid var(--border); display: flex; gap: 0.75rem; justify-content: flex-end;">
                <button class="btn btn-ghost" onclick="closeModuleModal()">Batal</button>
                <button class="btn btn-primary" id="moduleSaveBtn" onclick="saveModule()">Simpan Modul</button>
            </div>
        </div>
    </div>

    <!-- Modal Pelajaran -->
    <div id="lessonModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;">
        <div style="background: white; border-radius: var(--radius-lg); max-width: 620px; width: 100%; max-height: 90vh; overflow-y: auto;">
            <div style="padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between;">
                <h3 style="font-size: 1.25rem; font-weight: 600;" id="lessonModalTitle">Tambah Pelajaran / Materi</h3>
                <button onclick="closeLessonModal()" aria-label="Tutup" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-secondary);">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <div style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label" for="lessonCourse">Kursus *</label>
                    <select id="lessonCourse" class="form-input" onchange="refreshLessonModules()"></select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="lessonModule">Pilih Modul *</label>
                    <select id="lessonModule" class="form-input"></select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="lessonTitle">Judul Pelajaran *</label>
                    <input type="text" id="lessonTitle" class="form-input" placeholder="Contoh: 1.1 Konsep Dasar Manajemen">
                </div>
                <div class="form-group">
                    <label class="form-label" for="contentType">Tipe Konten</label>
                    <select class="form-input" id="contentType" onchange="toggleContentFields()">
                        <option value="text">Teks (Rich Text)</option>
                        <option value="video">Video (Embed URL)</option>
                        <option value="file">File (Nama/Link File)</option>
                        <option value="link">Link Eksternal</option>
                    </select>
                </div>
                <div id="contentField" class="form-group">
                    <label class="form-label" id="contentLabel" for="lessonContent">Konten Teks</label>
                    <textarea class="form-input" id="lessonContent" rows="6" placeholder="Tulis materi pelajaran di sini..."></textarea>
                </div>
            </div>
            <div style="padding: 1.5rem; border-top: 1px solid var(--border); display: flex; gap: 0.75rem; justify-content: flex-end;">
                <button class="btn btn-ghost" onclick="closeLessonModal()">Batal</button>
                <button class="btn btn-primary" id="lessonSaveBtn" onclick="saveLesson()">Simpan Pelajaran</button>
            </div>
        </div>
    </div>

    <!-- Modal Materi Tambahan Kelas -->
    <div id="materialModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;">
        <div style="background: white; border-radius: var(--radius-lg); max-width: 640px; width: 100%; max-height: 90vh; overflow-y: auto;">
            <div style="padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between;">
                <h3 style="font-size: 1.25rem; font-weight: 600;" id="materialModalTitle">Tambah Materi Tambahan</h3>
                <button onclick="closeMaterialModal()" aria-label="Tutup" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-secondary);">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <div style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
                <input type="hidden" id="materialId">
                <div class="form-group">
                    <label class="form-label" for="materialTitle">Judul Materi *</label>
                    <input type="text" id="materialTitle" class="form-input" placeholder="Contoh: Ringkasan Pertemuan 1">
                </div>
                <div class="form-group">
                    <label class="form-label" for="materialDescription">Deskripsi</label>
                    <textarea id="materialDescription" class="form-input" rows="2" placeholder="Penjelasan singkat materi tambahan"></textarea>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label" for="materialType">Jenis Materi *</label>
                        <select id="materialType" class="form-input" onchange="refreshMaterialFields()">
                            <option value="text">Teks</option>
                            <option value="document">Dokumen</option>
                            <option value="video">Video</option>
                            <option value="link">Link</option>
                            <option value="file">File</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="materialAccessLevel">Status Materi</label>
                        <select id="materialAccessLevel" class="form-input">
                            <option value="required">Wajib</option>
                            <option value="optional">Opsional</option>
                            <option value="reference">Referensi</option>
                        </select>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label" for="materialLesson">Terkait Pelajaran Kurikulum</label>
                        <select id="materialLesson" class="form-input"></select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="materialSession">Terkait Sesi Kelas</label>
                        <select id="materialSession" class="form-input"></select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="materialContent" id="materialContentLabel">Konten Teks</label>
                    <textarea id="materialContent" class="form-input" rows="5" placeholder="Tulis materi atau tempel URL"></textarea>
                </div>
                <div id="materialFileFields" style="display: none; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label" for="materialFileName">Nama File</label>
                        <input type="text" id="materialFileName" class="form-input" placeholder="modul-1.pdf">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="materialMimeType">MIME Type</label>
                        <input type="text" id="materialMimeType" class="form-input" placeholder="application/pdf">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="materialFileSize">Ukuran File (byte)</label>
                        <input type="number" id="materialFileSize" class="form-input" min="0" placeholder="0">
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label" for="materialAvailableFrom">Tersedia Mulai</label>
                        <input type="date" id="materialAvailableFrom" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="materialOrder">Urutan</label>
                        <input type="number" id="materialOrder" class="form-input" min="1" placeholder="Otomatis">
                    </div>
                </div>
            </div>
            <div style="padding: 1.5rem; border-top: 1px solid var(--border); display: flex; gap: 0.75rem; justify-content: flex-end;">
                <button class="btn btn-ghost" onclick="closeMaterialModal()">Batal</button>
                <button class="btn btn-primary" onclick="saveMaterial()">Simpan Materi</button>
            </div>
        </div>
    </div>

    <!-- Modal Status Materi -->
    <div id="materialStatusModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;">
        <div style="background: white; border-radius: var(--radius-lg); max-width: 480px; width: 100%;">
            <div style="padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between;">
                <h3 style="font-size: 1.25rem; font-weight: 600;" id="materialStatusTitle">Status Materi</h3>
                <button onclick="closeMaterialStatusModal()" aria-label="Tutup" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-secondary);">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <div style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
                <input type="hidden" id="materialStatusId">
                <input type="hidden" id="materialStatusValue">
                <p style="font-size: 0.875rem; color: var(--text-secondary);" id="materialStatusText"></p>
                <div class="form-group">
                    <label class="form-label" for="materialStatusReason">Alasan *</label>
                    <textarea id="materialStatusReason" class="form-input" rows="3" placeholder="Contoh: materi sedang direvisi"></textarea>
                </div>
            </div>
            <div style="padding: 1.5rem; border-top: 1px solid var(--border); display: flex; gap: 0.75rem; justify-content: flex-end;">
                <button class="btn btn-ghost" onclick="closeMaterialStatusModal()">Batal</button>
                <button class="btn btn-primary" onclick="saveMaterialStatus()">Simpan Status</button>
            </div>
        </div>
    </div>

    <!-- Modal Tugas -->
    <div id="assignmentTaskModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;">
        <div style="background: white; border-radius: var(--radius-lg); max-width: 760px; width: 100%; max-height: 90vh; overflow-y: auto;">
            <div style="padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between;">
                <h3 style="font-size: 1.25rem; font-weight: 600;" id="assignmentTaskModalTitle">Tambah Tugas</h3>
                <button onclick="closeAssignmentTaskModal()" aria-label="Tutup" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-secondary);"><i class="bi bi-x"></i></button>
            </div>
            <div style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
                <input type="hidden" id="assignmentTaskId">
                <div class="form-group">
                    <label class="form-label" for="assignmentTaskTitle">Judul Tugas *</label>
                    <input type="text" id="assignmentTaskTitle" class="form-input" placeholder="Contoh: Tugas 1: Analisis Fungsi Manajemen">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label" for="assignmentTaskType">Jenis Tugas</label>
                        <select id="assignmentTaskType" class="form-input">
                            <option value="text_submission">Jawaban Teks</option>
                            <option value="file_upload">Unggah File</option>
                            <option value="in_class_practice">Praktik Kelas</option>
                            <option value="external_reference">Referensi Eksternal</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="assignmentTaskStatus">Status</label>
                        <select id="assignmentTaskStatus" class="form-input">
                            <option value="draft">Draft</option>
                            <option value="published">Published</option>
                            <option value="closed">Closed</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label" for="assignmentTaskModule">Modul</label>
                        <select id="assignmentTaskModule" class="form-input" onchange="refreshAssignmentTaskLessons()"></select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="assignmentTaskLesson">Pelajaran</label>
                        <select id="assignmentTaskLesson" class="form-input"></select>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label" for="assignmentTaskOpenAt">Dibuka Pada</label>
                        <input type="datetime-local" id="assignmentTaskOpenAt" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="assignmentTaskDueAt">Tenggat</label>
                        <input type="datetime-local" id="assignmentTaskDueAt" class="form-input">
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label" for="assignmentTaskMaxScore">Nilai Maksimum</label>
                        <input type="number" id="assignmentTaskMaxScore" class="form-input" min="1" step="0.01" value="100">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="assignmentTaskWeight">Bobot (%)</label>
                        <input type="number" id="assignmentTaskWeight" class="form-input" min="0" step="0.01" value="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="assignmentTaskMaxFileSize">Maks. File (byte)</label>
                        <input type="number" id="assignmentTaskMaxFileSize" class="form-input" min="0" placeholder="Opsional">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="assignmentTaskAllowedTypes">Tipe File Diizinkan</label>
                    <input type="text" id="assignmentTaskAllowedTypes" class="form-input" placeholder="pdf,doc,docx">
                    <small class="form-hint">Pisahkan dengan koma. Kosongkan untuk semua tipe.</small>
                </div>
                <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem;">
                    <input type="checkbox" id="assignmentTaskRequireFile" style="width: auto;"> Wajib mengunggah file
                </label>
                <div class="form-group">
                    <label class="form-label" for="assignmentTaskInstructions">Instruksi</label>
                    <textarea id="assignmentTaskInstructions" class="form-input" rows="4" placeholder="Petunjuk pengerjaan tugas..."></textarea>
                </div>
                <div class="form-group">
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 0.5rem;">
                        <label class="form-label" style="margin: 0;">Rubrik Penilaian</label>
                        <button type="button" class="btn btn-ghost" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;" onclick="addAssignmentRubricRow()"><i class="bi bi-plus-lg"></i> Kriteria</button>
                    </div>
                    <div id="assignmentTaskRubrics" style="display: flex; flex-direction: column; gap: 0.5rem;"></div>
                </div>
            </div>
            <div style="padding: 1.5rem; border-top: 1px solid var(--border); display: flex; gap: 0.75rem; justify-content: flex-end;">
                <button class="btn btn-ghost" onclick="closeAssignmentTaskModal()">Batal</button>
                <button class="btn btn-primary" onclick="saveAssignmentTask()">Simpan Tugas</button>
            </div>
        </div>
    </div>

    <!-- Modal Pengumpulan Tugas -->
    <div id="assignmentSubmissionsModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;">
        <div style="background: white; border-radius: var(--radius-lg); max-width: 900px; width: 100%; max-height: 90vh; overflow-y: auto;">
            <div style="padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between;">
                <h3 style="font-size: 1.25rem; font-weight: 600;" id="assignmentSubmissionsTitle">Pengumpulan Tugas</h3>
                <button onclick="closeAssignmentSubmissionsModal()" aria-label="Tutup" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-secondary);"><i class="bi bi-x"></i></button>
            </div>
            <div style="padding: 1.5rem;" id="assignmentSubmissionsBody"></div>
        </div>
    </div>

    <!-- Modal Ujian -->
    <div id="examTaskModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;">
        <div style="background: white; border-radius: var(--radius-lg); max-width: 720px; width: 100%; max-height: 90vh; overflow-y: auto;">
            <div style="padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between;">
                <h3 style="font-size: 1.25rem; font-weight: 600;" id="examTaskModalTitle">Tambah Ujian</h3>
                <button onclick="closeExamTaskModal()" aria-label="Tutup" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-secondary);"><i class="bi bi-x"></i></button>
            </div>
            <div style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
                <input type="hidden" id="examTaskId">
                <div class="form-group">
                    <label class="form-label" for="examTaskTitle">Judul Ujian *</label>
                    <input type="text" id="examTaskTitle" class="form-input" placeholder="Contoh: Ujian Tengah Semester">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label" for="examTaskType">Jenis Ujian</label>
                        <select id="examTaskType" class="form-input">
                            <option value="written">Tertulis</option>
                            <option value="practical">Praktik</option>
                            <option value="oral">Lisan</option>
                            <option value="project">Proyek</option>
                            <option value="other">Lainnya</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="examTaskStatus">Status</label>
                        <select id="examTaskStatus" class="form-input">
                            <option value="draft">Draft</option>
                            <option value="published">Published</option>
                            <option value="closed">Closed</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label" for="examTaskDate">Tanggal</label>
                        <input type="date" id="examTaskDate" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="examTaskStartTime">Waktu Mulai</label>
                        <input type="time" id="examTaskStartTime" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="examTaskDuration">Durasi (menit)</label>
                        <input type="number" id="examTaskDuration" class="form-input" min="0" placeholder="90">
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label" for="examTaskMaxScore">Nilai Maksimum</label>
                        <input type="number" id="examTaskMaxScore" class="form-input" min="1" step="0.01" value="100">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="examTaskWeight">Bobot (%)</label>
                        <input type="number" id="examTaskWeight" class="form-input" min="0" step="0.01" value="0">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="examTaskDescription">Deskripsi</label>
                    <textarea id="examTaskDescription" class="form-input" rows="2" placeholder="Ringkasan ujian..."></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label" for="examTaskInstructions">Instruksi</label>
                    <textarea id="examTaskInstructions" class="form-input" rows="3" placeholder="Petunjuk pengerjaan ujian..."></textarea>
                </div>
            </div>
            <div style="padding: 1.5rem; border-top: 1px solid var(--border); display: flex; gap: 0.75rem; justify-content: flex-end;">
                <button class="btn btn-ghost" onclick="closeExamTaskModal()">Batal</button>
                <button class="btn btn-primary" onclick="saveExamTask()">Simpan Ujian</button>
            </div>
        </div>
    </div>

    <!-- Modal Hasil Ujian -->
    <div id="examResultsModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;">
        <div style="background: white; border-radius: var(--radius-lg); max-width: 960px; width: 100%; max-height: 90vh; overflow-y: auto;">
            <div style="padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between;">
                <h3 style="font-size: 1.25rem; font-weight: 600;" id="examResultsTitle">Hasil Ujian</h3>
                <button onclick="closeExamResultsModal()" aria-label="Tutup" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-secondary);"><i class="bi bi-x"></i></button>
            </div>
            <div style="padding: 1.5rem; overflow-x: auto;" id="examResultsBody"></div>
        </div>
<?php end_layout(); ?>
