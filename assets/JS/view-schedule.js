// ─── Drag & Drop Edit Mode ────────────────────────────────────────────────────
let editMode = false;
let dragState = null;
let pendingDrop = null;

function toggleEditMode() {
    editMode = !editMode;
    const btn = document.getElementById('editModeBtn');
    const txt = document.getElementById('editModeBtnText');
    if (!btn) return;
    if (editMode) {
        txt.textContent = '\u0625\u0646\u0647\u0627\u0621 \u0627\u0644\u062a\u0639\u062f\u064a\u0644';
        btn.classList.add('active');
        sessionStorage.setItem('scheduleEditMode', '1');
        document.querySelectorAll('.class-card[data-id]').forEach(c => {
            c.setAttribute('draggable', 'true');
            c.style.cursor = 'grab';
        });
    } else {
        txt.textContent = '\u062a\u0639\u062f\u064a\u0644';
        btn.classList.remove('active');
        sessionStorage.removeItem('scheduleEditMode');
        document.querySelectorAll('.class-card[data-id]').forEach(c => {
            c.removeAttribute('draggable');
            c.style.cursor = '';
        });
        document.querySelectorAll('td.drag-over').forEach(td => td.classList.remove('drag-over'));
    }
}

function checkAdjacentConflict(draggedId, draggedTerm, targetDay, targetTime) {
    const t = parseInt(draggedTerm);
    const tDay = String(targetDay).trim();
    const tTime = String(targetTime).trim();
    return (conflictData || []).some(s =>
        String(s.id) !== String(draggedId) &&
        (parseInt(s.term) === t - 1 || parseInt(s.term) === t + 1) &&
        String(s.day_of_week).trim() === tDay &&
        String(s.time).trim().substring(0, 5) === tTime.substring(0, 5)
    );
}

function cancelDrop() {
    pendingDrop = null;
    document.getElementById('conflictModal').classList.add('hidden');
    document.getElementById('conflictConfirmBtn').style.display = '';
}

async function executeDrop({ id, swapId, newDay, newTime }) {
    document.getElementById('conflictModal').classList.add('hidden');
    pendingDrop = null;
    const body = new URLSearchParams({ id, new_day: newDay, new_time: newTime });
    if (swapId) body.append('swap_id', swapId);
    try {
        const res = await fetch('move_schedule.php', { method: 'POST', body });
        const json = await res.json();
        if (json.ok) {
            window.location.reload();
        } else if (json.error === 'teacher_conflict') {
            document.getElementById('conflictMsg').textContent = 'المدرس لديه حصة أخرى في هذا الوقت. لا يمكن نقل الحصة هنا.';
            document.getElementById('conflictConfirmBtn').style.display = 'none';
            document.getElementById('conflictModal').classList.remove('hidden');
        } else {
            alert('حدث خطأ: ' + (json.error || 'خطأ غير معروف'));
        }
    } catch {
        alert('فشل الاتصال بالخادم');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('editModeBtn') && sessionStorage.getItem('scheduleEditMode') === '1') {
        toggleEditMode();
    }
    applyZoom(currentZoom);

    document.addEventListener('dragstart', e => {
        if (!editMode) return;
        const card = e.target.closest('.class-card[data-id]');
        if (!card) return;
        dragState = { id: card.dataset.id, term: card.dataset.term, day: card.dataset.day, time: card.dataset.time };
        e.dataTransfer.effectAllowed = 'move';
        setTimeout(() => card.classList.add('opacity-50'), 0);
    });

    document.addEventListener('dragend', e => {
        const card = e.target.closest('.class-card[data-id]');
        if (card) card.classList.remove('opacity-50');
        document.querySelectorAll('td.drag-over').forEach(td => td.classList.remove('drag-over'));
    });

    document.addEventListener('dragover', e => {
        if (!editMode || !dragState) return;
        const td = e.target.closest('td[data-day]');
        if (td) e.preventDefault();
    });

    document.addEventListener('dragenter', e => {
        if (!editMode || !dragState) return;
        const td = e.target.closest('td[data-day]');
        if (td) td.classList.add('drag-over');
    });

    document.addEventListener('dragleave', e => {
        if (!editMode) return;
        const td = e.target.closest('td[data-day]');
        if (td && !td.contains(e.relatedTarget)) td.classList.remove('drag-over');
    });

    document.addEventListener('drop', e => {
        if (!editMode || !dragState) return;
        const td = e.target.closest('td[data-day]');
        if (!td) return;
        e.preventDefault();
        td.classList.remove('drag-over');

        const targetDay = td.dataset.day;
        const targetTime = td.dataset.time;

        if (dragState.day === targetDay && dragState.time === targetTime) { dragState = null; return; }

        const targetCard = td.querySelector('.class-card[data-id]');
        const swapId = targetCard ? targetCard.dataset.id : 0;
        const draggedId = dragState.id;
        const draggedTerm = dragState.term;
        const dropArgs = { id: draggedId, swapId, newDay: targetDay, newTime: targetTime };
        dragState = null;

        if (checkAdjacentConflict(draggedId, draggedTerm, targetDay, targetTime)) {
            const t = parseInt(draggedTerm);
            const adjTerms = [t - 1, t + 1].filter(x => x >= 3 && x <= 8);
            const termLabels = adjTerms.map(x => 'الفصل ' + x).join(' و ');
            document.getElementById('conflictMsg').textContent =
                'توجد حصة في نفس الوقت في ' + termLabels + '. هل تريد المتابعة على أي حال؟';
            pendingDrop = dropArgs;
            document.getElementById('conflictConfirmBtn').onclick = () => executeDrop(pendingDrop);
            document.getElementById('conflictModal').classList.remove('hidden');
        } else {
            executeDrop(dropArgs);
        }
    });
});
// ─────────────────────────────────────────────────────────────────────────────

// ─── Schedule Zoom ────────────────────────────────────────────────────────────
const ZOOM_STEP = 10;
const ZOOM_MIN = 50;
const ZOOM_MAX = 200;

let currentZoom = (function () {
    const stored = localStorage.getItem('viewScheduleZoom');
    if (stored) return parseInt(stored);
    return window.innerWidth < 768 ? 75 : 100;
})();

function applyZoom(zoom) {
    currentZoom = Math.min(ZOOM_MAX, Math.max(ZOOM_MIN, zoom));
    const wrapper = document.getElementById('scheduleZoomWrapper');
    if (wrapper) wrapper.style.zoom = currentZoom / 100;
    const label = document.getElementById('zoomLevel');
    if (label) label.textContent = currentZoom + '%';
    localStorage.setItem('viewScheduleZoom', currentZoom);
}

function zoomIn() { applyZoom(currentZoom + ZOOM_STEP); }
function zoomOut() { applyZoom(currentZoom - ZOOM_STEP); }
// ─────────────────────────────────────────────────────────────────────────────

async function exportToExcel() {
    fetch('log_action.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'action=تصدير الجدول العام Excel' });
    const colorMap = {
        blue: { fill: 'DBEAFE', border: '3B82F6', font: '1E3A8A' },
        green: { fill: 'DCFCE7', border: '16A34A', font: '14532D' },
        purple: { fill: 'F3E8FF', border: 'A855F7', font: '581C87' },
        orange: { fill: 'FFEDD5', border: 'F97316', font: '7C2D12' },
        red: { fill: 'FEE2E2', border: 'EF4444', font: '7F1D1D' },
        pink: { fill: 'FCE7F3', border: 'EC4899', font: '831843' },
        indigo: { fill: 'E0E7FF', border: '6366F1', font: '312E81' },
        yellow: { fill: 'FEF9C3', border: 'CA8A04', font: '713F12' },
        teal: { fill: 'CCFBF1', border: '14B8A6', font: '134E4A' },
        cyan: { fill: 'CFFAFE', border: '06B6D4', font: '164E63' },
        gray: { fill: 'F3F4F6', border: '6B7280', font: '374151' },
    };

    const lookup = {};
    for (const e of scheduleData) {
        if (!lookup[e.term]) lookup[e.term] = {};
        if (!lookup[e.term][e.day]) lookup[e.term][e.day] = {};
        if (!lookup[e.term][e.day][e.time]) lookup[e.term][e.day][e.time] = [];
        lookup[e.term][e.day][e.time].push(e);
    }

    const slotKeys = timeSlotKeys.map(k => k.substring(0, 5));

    function styleCell(cell, opts) {
        const { text, bg, borderColor, fontColor, bold } = opts;
        cell.value = text || '';
        cell.alignment = { vertical: 'middle', horizontal: 'center', wrapText: true, readingOrder: 2 };
        cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF' + (bg || 'FFFFFF') } };
        cell.font = { bold: !!bold, color: { argb: 'FF' + (fontColor || '000000') }, name: 'Cairo', size: 10 };
        cell.border = {
            top: { style: 'thin', color: { argb: 'FFE5E7EB' } },
            bottom: { style: 'thin', color: { argb: 'FFE5E7EB' } },
            left: { style: borderColor ? 'medium' : 'thin', color: { argb: 'FF' + (borderColor || 'E5E7EB') } },
            right: { style: 'thin', color: { argb: 'FFE5E7EB' } },
        };
    }

    const workbook = new ExcelJS.Workbook();
    workbook.creator = 'iT Schedule';

    if (selectedTerm === 'all') {
        const sheet = workbook.addWorksheet('الجدول العام', { views: [{ rightToLeft: true }] });
        sheet.columns = [
            { width: 10 }, { width: 16 },
            { width: 24 }, { width: 24 }, { width: 24 },
            { width: 24 }, { width: 24 }, { width: 24 },
        ];

        const titleRowAll = sheet.addRow([]);
        titleRowAll.height = 30;
        sheet.mergeCells(1, 1, 1, days.length + 2);
        const titleCellAll = titleRowAll.getCell(1);
        titleCellAll.value = 'جدول المحاضرات - ' + academicYear;
        titleCellAll.alignment = { vertical: 'middle', horizontal: 'center', readingOrder: 2 };
        titleCellAll.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF595959' } };
        titleCellAll.font = { bold: true, color: { argb: 'FFFFFFFF' }, name: 'Cairo', size: 13 };

        const hRow = sheet.addRow([]);
        hRow.height = 30;
        ['الفصل', 'الوقت', ...days].forEach((h, i) => {
            styleCell(hRow.getCell(i + 1), { text: h, bg: '1152D4', fontColor: 'FFFFFF', bold: true });
        });

        for (const term of availableTerms) {
            const termLabel = termNames[term] || ('الفصل ' + term);
            const blockStartRow = sheet.rowCount + 1;

            slotKeys.forEach((slotKey, si) => {
                const row = sheet.addRow([]);
                row.height = 58;
                styleCell(row.getCell(2), { text: timeSlotLabels[si], bg: 'F0F4FF', fontColor: '374151', bold: true });
                days.forEach((day, di) => {
                    const cell = row.getCell(di + 3);
                    const entries = (lookup[term] && lookup[term][day] && lookup[term][day][slotKey]) || [];
                    if (entries.length > 0) {
                        const e = entries[0];
                        const c = colorMap[e.color] || colorMap.gray;
                        styleCell(cell, { text: e.subject + '\n' + e.teacher + '\n' + e.room, bg: c.fill, borderColor: c.border, fontColor: c.font });
                    } else {
                        styleCell(cell, { text: '', bg: 'F9FAFB' });
                    }
                });
            });

            const blockEndRow = sheet.rowCount;
            const sepRow = sheet.getRow(blockEndRow);
            for (let col = 1; col <= 8; col++) {
                const cell = sepRow.getCell(col);
                cell.border = Object.assign({}, cell.border || {}, {
                    bottom: { style: 'medium', color: { argb: 'FF000000' } }
                });
            }

            if (blockEndRow >= blockStartRow) {
                if (blockEndRow > blockStartRow) {
                    sheet.mergeCells(blockStartRow, 1, blockEndRow, 1);
                }
                const termCell = sheet.getCell(blockStartRow, 1);
                termCell.value = termLabel;
                termCell.alignment = { vertical: 'middle', horizontal: 'center', wrapText: true, textRotation: 90, readingOrder: 2 };
                termCell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF1152D4' } };
                termCell.font = { bold: true, color: { argb: 'FFFFFFFF' }, name: 'Cairo', size: 11 };
                termCell.border = {
                    top: { style: 'medium', color: { argb: 'FF1152D4' } },
                    bottom: { style: 'medium', color: { argb: 'FF1152D4' } },
                    left: { style: 'thin', color: { argb: 'FFE5E7EB' } },
                    right: { style: 'thin', color: { argb: 'FFE5E7EB' } },
                };
            }
        }

    } else {
        const term = parseInt(selectedTerm);
        const sheetName = (termNames[term] || ('الفصل ' + term)).substring(0, 31);
        const sheet = workbook.addWorksheet(sheetName, { views: [{ rightToLeft: true }] });
        sheet.columns = [
            { width: 16 },
            { width: 24 }, { width: 24 }, { width: 24 },
            { width: 24 }, { width: 24 }, { width: 24 },
        ];

        const titleRowSingle = sheet.addRow([]);
        titleRowSingle.height = 30;
        sheet.mergeCells(1, 1, 1, days.length + 1);
        const titleCellSingle = titleRowSingle.getCell(1);
        titleCellSingle.value = 'جدول محاضرات ' + sheetName + ' لفصل ' + academicYear;
        titleCellSingle.alignment = { vertical: 'middle', horizontal: 'center', readingOrder: 2 };
        titleCellSingle.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF595959' } };
        titleCellSingle.font = { bold: true, color: { argb: 'FFFFFFFF' }, name: 'Cairo', size: 13 };

        const hRow = sheet.addRow([]);
        hRow.height = 30;
        ['الوقت', ...days].forEach((h, i) => {
            styleCell(hRow.getCell(i + 1), { text: h, bg: '1152D4', fontColor: 'FFFFFF', bold: true });
        });

        slotKeys.forEach((slotKey, si) => {
            const row = sheet.addRow([]);
            row.height = 58;
            styleCell(row.getCell(1), { text: timeSlotLabels[si], bg: 'F0F4FF', fontColor: '374151', bold: true });
            days.forEach((day, di) => {
                const cell = row.getCell(di + 2);
                const entries = (lookup[term] && lookup[term][day] && lookup[term][day][slotKey]) || [];
                if (entries.length > 0) {
                    const e = entries[0];
                    const c = colorMap[e.color] || colorMap.gray;
                    styleCell(cell, { text: e.subject + '\n' + e.teacher + '\n' + e.room, bg: c.fill, borderColor: c.border, fontColor: c.font });
                } else {
                    styleCell(cell, { text: '', bg: 'F9FAFB' });
                }
            });
        });
    }


    const buffer = await workbook.xlsx.writeBuffer();
    const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'الجدول_الدراسي_' + academicYear + '.xlsx';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}
