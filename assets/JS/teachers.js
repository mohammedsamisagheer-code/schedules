function editTeacher(id, name, title) {
    document.getElementById('editId').value = id;
    document.getElementById('editTeacherName').value = name;
    document.getElementById('editTitle').value = title;
    document.getElementById('editModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}
