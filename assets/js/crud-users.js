// ============================================================
// ELIPS LMS - crud-users.js
// CRUD pengguna dan penugasan instruktur ke kursus.
// ============================================================

(function (ELIPS) {
    'use strict';

    const { success, failure, todayString, isValidEmail, isValidRole, isValidUserStatus } = ELIPS._;

    function createUser(data) {
        data = data || {};
        const name = String(data.name || '').trim();
        const email = String(data.email || '').trim().toLowerCase();
        const phone = String(data.phone || '').trim();
        const role = data.role || 'peserta';
        const status = data.status || 'aktif';

        if (!name) return failure('Nama wajib diisi.');
        if (!isValidEmail(email)) return failure('Email tidak valid.');
        if (!isValidRole(role)) return failure('Role pengguna tidak valid.');
        if (!isValidUserStatus(status)) return failure('Status pengguna tidak valid.');

        const users = ELIPS.getUsers();
        if (users.some(function (u) { return String(u.email).toLowerCase() === email; })) {
            return failure('Email sudah digunakan oleh pengguna lain.');
        }

        const user = {
            id: ELIPS.nextId(users),
            name: name,
            email: email,
            phone: phone,
            role: role,
            status: status,
            createdAt: todayString()
        };
        if (data.password) user.password = data.password;

        users.push(user);
        ELIPS.setUsers(users);
        return success('Pengguna berhasil ditambahkan.', user);
    }

    function updateUser(id, data) {
        data = data || {};
        const users = ELIPS.getUsers();
        const user = users.find(function (u) { return u.id === Number(id); });
        if (!user) return failure('Pengguna tidak ditemukan.');

        const nextName = data.name !== undefined ? String(data.name).trim() : user.name;
        const nextEmail = data.email !== undefined ? String(data.email).trim().toLowerCase() : user.email;
        const nextRole = data.role !== undefined ? data.role : user.role;
        const nextStatus = data.status !== undefined ? data.status : user.status;

        if (!nextName) return failure('Nama wajib diisi.');
        if (!isValidEmail(nextEmail)) return failure('Email tidak valid.');
        if (!isValidRole(nextRole)) return failure('Role pengguna tidak valid.');
        if (!isValidUserStatus(nextStatus)) return failure('Status pengguna tidak valid.');
        if (users.some(function (u) { return u.id !== user.id && String(u.email).toLowerCase() === nextEmail; })) {
            return failure('Email sudah digunakan oleh pengguna lain.');
        }

        const previousRole = user.role;
        user.name = nextName;
        user.email = nextEmail;
        if (data.phone !== undefined) user.phone = String(data.phone).trim();
        user.role = nextRole;
        user.status = nextStatus;
        if (data.password) user.password = data.password;
        user.updatedAt = new Date().toISOString();

        ELIPS.setUsers(users);

        if (previousRole === 'instruktur' && user.role !== 'instruktur') {
            ELIPS._.releaseInstructorCourses(user.id, false);
        }
        if (user.role === 'instruktur') {
            ELIPS._.syncInstructorNameOnCourses(user);
            if (user.status !== 'aktif') ELIPS._.releaseInstructorCourses(user.id, true);
        }

        return success('Pengguna berhasil diperbarui.', user);
    }

    function deleteUser(id) {
        const user = ELIPS.getUserById(id);
        if (!user) return failure('Pengguna tidak ditemukan.');
        if (user.id === 1) return failure('Akun Administrator utama tidak dapat dihapus.');
        if (user.role === 'admin' && ELIPS.getUsersByRole('admin').length <= 1) {
            return failure('Minimal satu akun Admin harus tersedia.');
        }

        ELIPS.setUsers(ELIPS.getUsers().filter(function (u) { return u.id !== user.id; }));

        if (user.role === 'peserta') {
            ELIPS.setEnrollments(ELIPS.getEnrollments().filter(function (e) { return e.studentId !== user.id; }));
            ELIPS.setGrades(ELIPS.getGrades().filter(function (g) { return g.studentId !== user.id; }));
            ELIPS.setCertificates(ELIPS.getCertificates().filter(function (c) { return c.studentId !== user.id; }));
            ELIPS.setCompletedLessons(ELIPS.getCompletedLessons().filter(function (c) { return c.studentId !== user.id; }));
        }

        if (user.role === 'instruktur') {
            ELIPS._.releaseInstructorCourses(user.id, false);
        }

        return success('Pengguna dihapus.');
    }

    function assignInstructorToCourses(instructorId, courseIds) {
        const instructor = ELIPS.getUserById(instructorId);
        if (!instructor || instructor.role !== 'instruktur') return failure('Pilih akun instruktur yang valid.');
        if (instructor.status !== 'aktif') return failure('Instruktur harus berstatus aktif.');

        const selected = (courseIds || []).map(Number).filter(Boolean);
        const courses = ELIPS.getCourses();
        let changed = 0;

        courses.forEach(function (course) {
            if (selected.indexOf(course.id) !== -1) {
                if (course.instructorId !== instructor.id) {
                    course.instructorId = instructor.id;
                    course.instructorName = instructor.name;
                    changed++;
                } else if (course.instructorName !== instructor.name) {
                    course.instructorName = instructor.name;
                    changed++;
                }
            } else if (course.instructorId === instructor.id) {
                course.instructorId = null;
                course.instructorName = '';
                if (course.status === 'published') course.status = 'draft';
                changed++;
            }
        });

        if (changed > 0) {
            ELIPS.setCourses(courses);
            ELIPS._.syncAllContentOwners();
        }
        return success(changed + ' penugasan kursus diperbarui.', { changed: changed });
    }

    Object.assign(ELIPS, {
        createUser: createUser,
        updateUser: updateUser,
        deleteUser: deleteUser,
        assignInstructorToCourses: assignInstructorToCourses
    });
})(window.ELIPS);
