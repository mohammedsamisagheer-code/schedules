function editSchedule(id, subjectId, dayOfWeek, time, roomId) {
    document.getElementById('editId').value = id;
    document.getElementById('editSubjectId').value = subjectId;
    document.getElementById('editDayOfWeek').value = dayOfWeek;
    document.getElementById('editTime').value = time;
    document.getElementById('editRoomId').value = roomId;
    document.getElementById('editModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}

function showDeleteClassModal(id) {
    document.getElementById('deleteClassId').value = id;
    document.getElementById('deleteClassModal').classList.remove('hidden');
}

function closeDeleteClassModal() {
    document.getElementById('deleteClassModal').classList.add('hidden');
}

function submitDeleteClass() {
    document.getElementById('deleteClassForm').submit();
}

function getSubjectTerm(subjectId) {
    return subjectTerms[subjectId] || '';
}

document.addEventListener('DOMContentLoaded', function() {
    const addSubjectSelect = document.querySelector('select[name="subject_id"]');
    if (addSubjectSelect) {
        addSubjectSelect.addEventListener('change', function() {
            const selectedTerm = getSubjectTerm(this.value);
            document.getElementById('selectedSubjectTerm').value = selectedTerm;
            updateScheduleDisplay();
        });
    }

    const editSubjectSelect = document.querySelector('select[name="subject_id"][id*="edit"]');
    if (editSubjectSelect) {
        editSubjectSelect.addEventListener('change', function() {
            const selectedTerm = getSubjectTerm(this.value);
            document.getElementById('selectedSubjectTerm').value = selectedTerm;
            updateScheduleDisplay();
        });
    }
});

function updateScheduleDisplay() {
    const selectedTerm    = document.getElementById('selectedSubjectTerm').value;
    const selectedSubject = document.querySelector('select[name="subject_id"]').value;

    if (selectedTerm && selectedSubject) {
        const url = new URL(window.location);
        url.searchParams.set('selected_term', selectedTerm);
        url.searchParams.set('selected_subject', selectedSubject);
        url.searchParams.set('teacher_id', currentTeacherId);
        window.location.href = url.toString();
    }
}

const myScheduleDays = ['السبت', 'الأحد', 'الإثنين', 'الثلاثاء', 'الإربعاء', 'الخميس'];
const myScheduleTimeSlots = [
    { key: '09:00', label: '09:00 - 11:00' },
    { key: '11:00', label: '11:00 - 13:00' },
    { key: '13:00', label: '13:00 - 15:00' },
];

async function exportToExcel() {
    fetch('log_action.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'action=تصدير جدول المدرس Excel' });
    const workbook = new ExcelJS.Workbook();
    const sheet = workbook.addWorksheet('جدول ' + teacherName, { views: [{ rightToLeft: true }] });

    sheet.mergeCells(1, 1, 1, 7);
    const titleCell = sheet.getCell('A1');
    titleCell.value = 'الجدول الدراسي - ' + teacherName;
    titleCell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF1152D4' } };
    titleCell.font = { bold: true, size: 14, color: { argb: 'FFFFFFFF' } };
    titleCell.alignment = { horizontal: 'center', vertical: 'middle' };
    sheet.getRow(1).height = 30;

    const headerRow = sheet.addRow(['الوقت', ...myScheduleDays]);
    headerRow.eachCell((cell, colNum) => {
        cell.font = { bold: true, size: 11 };
        cell.alignment = colNum === 1
            ? { horizontal: 'center', vertical: 'middle', readingOrder: 2 }
            : { horizontal: 'center', vertical: 'middle' };
        cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF3F4F6' } };
        cell.border = { top: { style: 'thin' }, left: { style: 'thin' }, bottom: { style: 'thin' }, right: { style: 'thin' } };
    });
    sheet.getRow(2).height = 22;

    const lookup = {};
    teacherScheduleData.forEach(s => {
        lookup[s.day + '_' + s.time] = s;
    });

    myScheduleTimeSlots.forEach(slot => {
        const rowData = [slot.label];
        myScheduleDays.forEach(day => {
            const entry = lookup[day + '_' + slot.key];
            rowData.push(entry ? entry.subject + '\n' + entry.room + ' | فصل ' + entry.term : '');
        });
        const row = sheet.addRow(rowData);
        row.height = 50;
        row.eachCell((cell, colNum) => {
            cell.alignment = colNum === 1
                ? { horizontal: 'center', vertical: 'middle', wrapText: true, readingOrder: 2 }
                : { horizontal: 'center', vertical: 'middle', wrapText: true };
            cell.border = { top: { style: 'thin' }, left: { style: 'thin' }, bottom: { style: 'thin' }, right: { style: 'thin' } };
            if (colNum === 1) {
                cell.font = { bold: true };
                cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF9FAFB' } };
            } else if (cell.value) {
                cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFE8EEFB' } };
            }
        });
    });

    sheet.getColumn(1).width = 18;
    for (let i = 2; i <= 7; i++) sheet.getColumn(i).width = 28;

    const buffer = await workbook.xlsx.writeBuffer();
    const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'جدول_' + teacherName + '.xlsx';
    a.click();
    URL.revokeObjectURL(url);
}
