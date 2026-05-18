function editRoom(id, name, examOnly, classOnly) {
    document.getElementById('editId').value = id;
    document.getElementById('editRoomName').value = name;
    var roomType = examOnly === 1 ? 'exam_only' : (classOnly === 1 ? 'class_only' : 'regular');
    document.getElementById('editRoomTypeRegular').checked = roomType === 'regular';
    document.getElementById('editRoomTypeExam').checked = roomType === 'exam_only';
    document.getElementById('editRoomTypeClass').checked = roomType === 'class_only';
    document.getElementById('editModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}

function showDeleteRoomModal(id) {
    document.getElementById('deleteRoomId').value = id;
    document.getElementById('deleteRoomModal').classList.remove('hidden');
}

function closeDeleteRoomModal() {
    document.getElementById('deleteRoomModal').classList.add('hidden');
}

function submitDeleteRoom() {
    document.getElementById('deleteRoomForm').submit();
}
