function editTeacher(id, name, title, nationalId) {
    document.getElementById('editId').value = id;
    document.getElementById('editTeacherName').value = name;
    document.getElementById('editTitle').value = title;
    document.getElementById('editNationalId').value = nationalId || '';
    document.getElementById('editModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}
