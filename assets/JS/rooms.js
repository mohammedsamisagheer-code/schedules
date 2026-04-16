function editRoom(id, name) {
    document.getElementById('editId').value = id;
    document.getElementById('editRoomName').value = name;
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
