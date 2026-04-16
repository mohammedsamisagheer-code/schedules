function editSubject(id, code, name, term, teacherId, priority, requiresSubjectId) {
    document.getElementById('editId').value = id;
    document.getElementById('editSubjectCode').value = code;
    document.getElementById('editSubjectName').value = name;
    document.getElementById('editTerm').value = term;
    document.getElementById('editTeacherId').value = teacherId;
    document.getElementById('editPriority').value = priority;
    document.getElementById('editRequiresSubject').value = requiresSubjectId || '';
    document.getElementById('editModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}
