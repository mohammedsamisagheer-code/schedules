function filterTerm(term) {
    if (term === 'all') {
        document.querySelectorAll('tr[data-term]').forEach(r => r.style.display = '');
        document.querySelectorAll('[data-date-col]').forEach(el => el.style.display = '');
    } else {
        document.querySelectorAll('tr[data-term]').forEach(r => {
            r.style.display = (r.dataset.term == term) ? '' : 'none';
        });
        const activeDates = (termDates[term] || []).map(String);
        document.querySelectorAll('[data-date-col]').forEach(el => {
            el.style.display = activeDates.includes(el.dataset.dateCol) ? '' : 'none';
        });
    }
}

async function exportExams() {
    fetch('log_action.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'action=تصدير جدول الاختبارات Excel' });
    const examtype   = document.getElementById('examTypeSelect')?.value ?? '';
    const raw        = examRawData;
    const tnames     = termNamesData;
    const arabicDays = {'6':'السبت','7':'الأحد','1':'الإثنين','2':'الثلاثاء','3':'الإربعاء','4':'الخميس','5':'الجمعة'};

    const selectedTerm = document.getElementById('termSelect')?.value ?? 'all';
    const allTerms = selectedTerm === 'all' ? [3,4,5,6,7,8] : [parseInt(selectedTerm)];

    const grid = {};
    for (const e of raw) {
        if (selectedTerm !== 'all' && String(e.term) !== String(selectedTerm)) continue;
        if (!grid[e.exam_date]) grid[e.exam_date] = {};
        grid[e.exam_date][e.term] = e;
    }
    const dates = Object.keys(grid).sort();

    const wb    = new ExcelJS.Workbook();
    const sheet = wb.addWorksheet('جدول الإمتحانات', { views: [{ rightToLeft: true }] });

    sheet.columns = [
        { width: 20 },
        ...dates.map(() => ({ width: 20 }))
    ];

    const styleCell = (cell, { text='', bg='1152D4', fontColor='FFFFFF', bold=false, size=10 }) => {
        cell.value     = text;
        cell.fill      = { type:'pattern', pattern:'solid', fgColor:{ argb:'FF'+bg } };
        cell.font      = { bold, color:{ argb:'FF'+fontColor }, name:'Cairo', size };
        cell.alignment = { vertical:'middle', horizontal:'center', wrapText:true, readingOrder:2 };
        cell.border    = {
            top:    { style:'thin', color:{ argb:'FFD1D5DB' } },
            bottom: { style:'thin', color:{ argb:'FFD1D5DB' } },
            left:   { style:'thin', color:{ argb:'FFD1D5DB' } },
            right:  { style:'thin', color:{ argb:'FFD1D5DB' } }
        };
    };

    const termFill = { 3:'DBEAFE', 4:'DCFCE7', 5:'DBEAFE', 6:'DCFCE7', 7:'DBEAFE', 8:'DCFCE7' };
    const termFont = { 3:'1E3A8A', 4:'14532D', 5:'1E3A8A', 6:'14532D', 7:'1E3A8A', 8:'14532D' };
    const termHead = { 3:'2563EB', 4:'16A34A', 5:'2563EB', 6:'16A34A', 7:'2563EB', 8:'16A34A' };

    const fmtDate = date => {
        const d = new Date(date);
        const jsDay = d.getUTCDay();
        const key   = jsDay === 6 ? '6' : (jsDay === 0 ? '7' : String(jsDay));
        const day   = arabicDays[key] || '';
        const str   = d.getUTCDate().toString().padStart(2,'0') + '/' +
                      (d.getUTCMonth()+1).toString().padStart(2,'0') + '/' +
                      d.getUTCFullYear();
        return { day, str };
    };

    const titleRowExam = sheet.addRow([]);
    titleRowExam.height = 30;
    sheet.mergeCells(1, 1, 1, 1 + dates.length);
    const titleCellExam = titleRowExam.getCell(1);
    titleCellExam.value = 'جدول الإمتحانات ' + examtype + ' ' + academicYear;
    titleCellExam.alignment = { vertical: 'middle', horizontal: 'center', readingOrder: 2 };
    titleCellExam.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF595959' } };
    titleCellExam.font = { bold: true, color: { argb: 'FFFFFFFF' }, name: 'Cairo', size: 13 };

    const hRow = sheet.addRow([]);
    hRow.height = 42;
    styleCell(hRow.getCell(1), { text:'الفصل', bg:'1152D4', fontColor:'FFFFFF', bold:true, size:11 });
    dates.forEach((date, i) => {
        const { day, str } = fmtDate(date);
        styleCell(hRow.getCell(2+i), { text: day + '\n' + str, bg:'1152D4', fontColor:'FFFFFF', bold:true });
    });

    allTerms.forEach(t => {
        const row = sheet.addRow([]);
        row.height = 52;
        styleCell(row.getCell(1), {
            text:      tnames[t] || ('الفصل ' + t),
            bg:        termHead[t],
            fontColor: 'FFFFFF',
            bold:      true,
            size:      11
        });
        dates.forEach((date, i) => {
            const entry = grid[date] && grid[date][t];
            const cell  = row.getCell(2+i);
            if (entry) {
                const txt = entry.subject_name + (entry.teacher_name ? '\n' + entry.teacher_name : '');
                styleCell(cell, { text: txt, bg: termFill[t], fontColor: termFont[t] });
            } else {
                styleCell(cell, { text:'', bg:'F9FAFB', fontColor:'E5E7EB' });
            }
        });
    });

    const buf  = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], { type:'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = 'جدول_الإمتحانات_' + examtype + '_' + academicYear + '.xlsx';
    a.click();
    URL.revokeObjectURL(url);
}
