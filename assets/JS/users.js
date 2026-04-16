function openAddModal() {
    document.getElementById('modalTitle').textContent = 'إضافة مستخدم';
    document.getElementById('userForm').querySelector('[name=id]').value = '';
    document.getElementById('userForm').querySelectorAll('input, select').forEach(el => {
        if (el.name !== 'id') el.value = '';
    });
    document.getElementById('userRole').value = 'admin';
    document.getElementById('submitBtn').name = 'add_user';
    document.getElementById('submitBtn').textContent = 'إضافة';
    document.getElementById('userPassword').required = true;
    document.getElementById('passwordHint').classList.add('hidden');
    document.getElementById('passwordLabel').innerHTML = 'كلمة المرور <span class="text-red-500">*</span>';
    document.getElementById('userModal').classList.remove('hidden');
}

function openEditModal(user) {
    document.getElementById('modalTitle').textContent = 'تعديل مستخدم';
    document.getElementById('userId').value       = user.id;
    document.getElementById('userName').value     = user.name;
    document.getElementById('userTitle').value    = user.title || '';
    document.getElementById('userRole').value     = user.role;
    document.getElementById('userUsername').value = user.username;
    document.getElementById('userPassword').value = '';
    document.getElementById('userPassword').required = false;
    document.getElementById('passwordHint').classList.remove('hidden');
    document.getElementById('passwordLabel').innerHTML = 'كلمة المرور';
    document.getElementById('submitBtn').name = 'edit_user';
    document.getElementById('submitBtn').textContent = 'حفظ التعديلات';
    document.getElementById('userModal').classList.remove('hidden');
}

function openDeleteModal(id, name) {
    document.getElementById('deleteUserId').value = id;
    document.getElementById('deleteUserName').textContent = name;
    document.getElementById('deleteModal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('userModal').classList.add('hidden');
}
