(function() {
    const restUrl = Wp_Erp_Standup_Tracker.restUrl;
    const nonce = Wp_Erp_Standup_Tracker.nonce;
    
    // UI Elements
    const listView = document.getElementById('st-list-view');
    const formView = document.getElementById('st-form-view');
    const datePicker = document.getElementById('st-date-picker');
    const monthFilter = document.getElementById('st-month-filter');
    const reportModal = document.getElementById('st-report-modal');
    
    if (!listView) return;

    // Switch Views
    document.getElementById('btn-show-add').addEventListener('click', () => {
        listView.classList.add('st-hidden');
        formView.classList.remove('st-hidden');
        loadForm(datePicker.value);
    });
    
    document.getElementById('btn-back-list').addEventListener('click', showList);
    document.getElementById('btn-cancel').addEventListener('click', showList);
    
    // Modal Toggle
    document.getElementById('btn-show-report').addEventListener('click', () => {
        reportModal.classList.remove('st-hidden');
    });

    const closeModal = () => reportModal.classList.add('st-hidden');
    reportModal.querySelector('.st-btn-close').addEventListener('click', closeModal);
    reportModal.querySelector('.btn-modal-close').addEventListener('click', closeModal);
    reportModal.querySelector('.st-modal-backdrop').addEventListener('click', closeModal);

    // Download CSV
    document.getElementById('btn-download-csv').addEventListener('click', async () => {
        const month = document.getElementById('st-report-month').value;
        const btn = document.getElementById('btn-download-csv');
        const originalText = btn.innerHTML;
        
        btn.disabled = true;
        btn.innerHTML = `<span class="dashicons dashicons-update st-spin st-btn-icon-right"></span> Generating...`;

        try {
            const res = await fetch(`${restUrl}/report?month=${month}`, {
                headers: { 'X-WP-Nonce': nonce }
            });
            const data = await res.json();
            
            if (data.stats && data.stats.length > 0) {
                downloadCSV(data, month);
                closeModal();
            } else {
                alert('No data found for the selected month.');
            }
        } catch(e) {
            console.error(e);
            alert('Failed to generate report.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    });

    // Download PDF
    document.getElementById('btn-download-pdf').addEventListener('click', async () => {
        const month = document.getElementById('st-report-month').value;
        const btn = document.getElementById('btn-download-pdf');
        const originalText = btn.innerHTML;
        
        btn.disabled = true;
        btn.innerHTML = `<span class="dashicons dashicons-update st-spin st-btn-icon-right"></span> Generating...`;

        try {
            const res = await fetch(`${restUrl}/report?month=${month}`, {
                headers: { 'X-WP-Nonce': nonce }
            });
            const data = await res.json();
            
            if (data.stats && data.stats.length > 0) {
                await generatePDF(data, month);
                closeModal();
            } else {
                alert('No data found for the selected month.');
            }
        } catch(e) {
            console.error(e);
            alert('Failed to generate PDF.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    });

    async function generatePDF(data, month) {
        const { jsPDF } = window.jspdf;

        // ── A4 layout constants ──────────────────────────────────────
        const doc        = new jsPDF({ unit: 'mm', format: 'a4', orientation: 'portrait' });
        const pageW      = doc.internal.pageSize.getWidth();   // 210
        const pageH      = doc.internal.pageSize.getHeight();  // 297
        const margin     = 14;
        const contentW   = pageW - margin * 2;                 // 182
        let   y          = margin;

        // ── Helper: set colour from hex ──────────────────────────────
        function hexRGB(hex) {
            const n = parseInt(hex.replace('#',''), 16);
            return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
        }

        // ── Fetch & rasterise SVG logo → PNG dataURL ─────────────────
        let logoPng = null;
        try {
            const svgRes  = await fetch('https://welabs.dev/wp-content/uploads/2025/11/welabs-logo.svg');
            const svgText = await svgRes.text();
            const blob    = new Blob([svgText], { type: 'image/svg+xml' });
            const objUrl  = URL.createObjectURL(blob);
            const img     = new Image();
            await new Promise((res, rej) => { img.onload = res; img.onerror = rej; img.src = objUrl; });
            const cvs = document.createElement('canvas');
            cvs.width = img.naturalWidth * 3; cvs.height = img.naturalHeight * 3;
            cvs.getContext('2d').drawImage(img, 0, 0, cvs.width, cvs.height);
            logoPng = cvs.toDataURL('image/png');
            URL.revokeObjectURL(objUrl);
        } catch (e) { /* logo missing — continue without */ }

        // ── Compute stats ─────────────────────────────────────────────
        let totalPct = 0;
        const rows = data.stats.map(s => {
            const attend = parseInt(s.attend);
            const absent = parseInt(s.absent);
            const total  = attend + absent;
            const pct    = total > 0 ? (attend / total * 100) : 0;
            totalPct += pct;
            return { name: s.name, attend: s.attend, absent: s.absent, leave: s.leave, pctStr: pct.toFixed(2) + '%' };
        });
        const globalAvg = rows.length > 0 ? (totalPct / rows.length).toFixed(2) + '%' : '0.00%';

        // ════════════════════════════════════════════════════════════════
        //  HEADER
        // ════════════════════════════════════════════════════════════════
        if (logoPng) {
            // Logo: natural aspect ratio, ~ 38 mm wide
            const logoW = 38, logoH = 38 * (34 / 156); // SVG viewBox 156×34
            doc.addImage(logoPng, 'PNG', margin, y, logoW, logoH);
        }

        // Title (right-aligned, vertically centred in header row)
        const [r1, g1, b1] = hexRGB('#111b3a');
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(14);
        doc.setTextColor(r1, g1, b1);
        doc.text('DAILY STANDUP REPORT', pageW - margin, y + 4, { align: 'right' });

        const [r2, g2, b2] = hexRGB('#646970');
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(10);
        doc.setTextColor(r2, g2, b2);
        doc.text(`Report for ${month}`, pageW - margin, y + 10, { align: 'right' });

        y += 16;

        // Divider
        doc.setDrawColor(r1, g1, b1);
        doc.setLineWidth(0.5);
        doc.line(margin, y, pageW - margin, y);
        y += 8;

        // ════════════════════════════════════════════════════════════════
        //  SUMMARY CARDS  (3 equal columns)
        // ════════════════════════════════════════════════════════════════
        const cardGap = 4;
        const cardW   = (contentW - cardGap * 2) / 3;
        const cardH   = 18;
        const cards   = [
            { label: 'TOTAL WORKING DAYS', value: String(data.total_working_days) },
            { label: 'TOTAL EMPLOYEES',    value: String(data.stats.length)        },
            { label: 'AVG. ATTENDANCE',    value: globalAvg                        },
        ];

        cards.forEach((card, i) => {
            const x = margin + i * (cardW + cardGap);

            // card background + border
            doc.setFillColor(248, 249, 250);
            doc.setDrawColor(233, 236, 239);
            doc.setLineWidth(0.3);
            doc.roundedRect(x, y, cardW, cardH, 2, 2, 'FD');

            // label
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(7);
            doc.setTextColor(r2, g2, b2);
            doc.text(card.label, x + cardW / 2, y + 6, { align: 'center' });

            // value
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(14);
            doc.setTextColor(r1, g1, b1);
            doc.text(card.value, x + cardW / 2, y + 14, { align: 'center' });
        });

        y += cardH + 8;

        // ════════════════════════════════════════════════════════════════
        //  ATTENDANCE TABLE
        // ════════════════════════════════════════════════════════════════
        const cols = [
            { label: 'Employee Name', w: contentW * 0.40, align: 'left'   },
            { label: 'Present',       w: contentW * 0.15, align: 'center' },
            { label: 'Absent',        w: contentW * 0.15, align: 'center' },
            { label: 'Leave',         w: contentW * 0.15, align: 'center' },
            { label: 'Attendance %',  w: contentW * 0.15, align: 'center' },
        ];
        const rowH   = 7.5;
        const cellPX = 2.5;   // horizontal cell padding

        // — Table header row —
        doc.setFillColor(r1, g1, b1);
        doc.rect(margin, y, contentW, rowH, 'F');

        doc.setFont('helvetica', 'bold');
        doc.setFontSize(8);
        doc.setTextColor(255, 255, 255);

        let cx = margin;
        cols.forEach(col => {
            const tx = col.align === 'center' ? cx + col.w / 2 : cx + cellPX;
            doc.text(col.label.toUpperCase(), tx, y + rowH / 2, { align: col.align, baseline: 'middle' });
            cx += col.w;
        });
        y += rowH;

        // — Data rows —
        const [dr, dg, db] = hexRGB('#dee2e6');
        rows.forEach((row, i) => {
            // new page guard
            if (y + rowH > pageH - margin) {
                doc.addPage();
                y = margin;
            }

            // alternating zebra
            if (i % 2 === 1) {
                doc.setFillColor(249, 249, 249);
                doc.rect(margin, y, contentW, rowH, 'F');
            }

            // row text
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(9);
            doc.setTextColor(50, 50, 50);

            const cellValues = [row.name, String(row.attend), String(row.absent), String(row.leave), row.pctStr];
            cx = margin;
            cols.forEach((col, ci) => {
                const tx = col.align === 'center' ? cx + col.w / 2 : cx + cellPX;
                doc.text(cellValues[ci], tx, y + rowH / 2, { align: col.align, baseline: 'middle' });
                cx += col.w;
            });

            // bottom border
            doc.setDrawColor(dr, dg, db);
            doc.setLineWidth(0.2);
            doc.line(margin, y + rowH, margin + contentW, y + rowH);

            y += rowH;
        });

        // ════════════════════════════════════════════════════════════════
        //  FOOTER
        // ════════════════════════════════════════════════════════════════
        y += 6;
        if (y + 10 > pageH - margin) { doc.addPage(); y = margin; }

        doc.setDrawColor(dr, dg, db);
        doc.setLineWidth(0.3);
        doc.line(margin, y, pageW - margin, y);
        y += 5;

        doc.setFont('helvetica', 'normal');
        doc.setFontSize(8);
        doc.setTextColor(173, 181, 189);
        doc.text(`Generated on ${new Date().toLocaleString()}`, pageW / 2, y, { align: 'center' });

        // ── Save ──────────────────────────────────────────────────────
        doc.save(`standup-report-${month}.pdf`);
    }

    function downloadCSV(data, month) {
        const headers = ['Name', 'Attend', 'Absent', 'Leave', 'Total Working Days', '% of Attend'];
        const rows = data.stats.map(s => {
            const attend = parseInt(s.attend);
            const absent = parseInt(s.absent);
            const total = attend + absent;
            const percentage = total > 0 ? ((attend / total) * 100).toFixed(2) + '%' : '0%';
            
            return [
                `"${s.name}"`,
                s.attend,
                s.absent,
                s.leave,
                data.total_working_days,
                percentage
            ];
        });

        const csvContent = [headers, ...rows].map(e => e.join(",")).join("\n");
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement("a");
        const url = URL.createObjectURL(blob);
        
        link.setAttribute("href", url);
        link.setAttribute("download", `standup-report-${month}.csv`);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    function showList() {
        formView.classList.add('st-hidden');
        listView.classList.remove('st-hidden');
        loadHistory(monthFilter.value);
    }

    monthFilter.addEventListener('change', (e) => loadHistory(e.target.value));

    // Load History
    async function loadHistory(month = '') {
        document.getElementById('st-history-loading').classList.remove('st-hidden');
        document.getElementById('st-history-table').classList.add('st-hidden');
        document.getElementById('st-history-empty').classList.add('st-hidden');
        
        try {
            const url = month ? `${restUrl}/history?month=${month}` : `${restUrl}/history`;
            const res = await fetch(url, {
                headers: { 'X-WP-Nonce': nonce }
            });
            const data = await res.json();
            
            document.getElementById('st-history-loading').classList.add('st-hidden');
            const tbody = document.querySelector('#st-history-table tbody');
            tbody.innerHTML = '';
            
            if(data && data.length > 0) {
                document.getElementById('st-history-table').classList.remove('st-hidden');
                data.forEach(row => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td><strong>${row.standup_date}</strong></td>
                        <td><span class="st-badge st-badge-present">${row.present_count}</span></td>
                        <td><span class="st-badge st-badge-absent">${row.absent_count}</span></td>
                        <td><span class="st-badge st-badge-leave">${row.leave_count}</span></td>
                        <td class="st-text-right">
                            <button class="button st-btn" onclick="editDate('${row.standup_date}')">Edit</button>
                            <button class="button st-btn st-btn-danger" onclick="deleteDate('${row.standup_date}')">Delete</button>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            } else {
                document.getElementById('st-history-empty').classList.remove('st-hidden');
            }
        } catch(e) {
            console.error(e);
            alert('Failed to load history.');
        }
    }

    window.editDate = function(date) {
        datePicker.value = date;
        listView.classList.add('st-hidden');
        formView.classList.remove('st-hidden');
        loadForm(date);
    };

    window.deleteDate = async function(date) {
        if(!confirm(`Are you sure you want to delete standup records for ${date}?`)) return;
        
        try {
            const res = await fetch(`${restUrl}/delete?date=${date}`, {
                method: 'DELETE',
                headers: { 'X-WP-Nonce': nonce }
            });
            const result = await res.json();
            
            if(result.success) {
                loadHistory();
            } else {
                alert(result.message || 'Failed to delete');
            }
        } catch(e) {
            console.error(e);
            alert('Error deleting data.');
        }
    };

    // Load Form Data
    datePicker.addEventListener('change', (e) => loadForm(e.target.value));

    async function loadForm(date) {
        document.getElementById('st-form-loading').classList.remove('st-hidden');
        document.getElementById('st-employee-table').classList.add('st-hidden');
        document.getElementById('st-form-empty').classList.add('st-hidden');
        document.getElementById('st-form-actions').classList.add('st-hidden');
        
        try {
            const res = await fetch(`${restUrl}/employees?date=${date}`, {
                headers: { 'X-WP-Nonce': nonce }
            });
            const data = await res.json();
            
            if(data.code && data.code === 'invalid_date') {
                alert(data.message);
                showList();
                return;
            }

            document.getElementById('st-form-loading').classList.add('st-hidden');
            const tbody = document.querySelector('#st-employee-table tbody');
            tbody.innerHTML = '';
            
            if(data && data.length > 0) {
                document.getElementById('st-employee-table').classList.remove('st-hidden');
                document.getElementById('st-form-actions').classList.remove('st-hidden');
                
                data.forEach(emp => {
                    const tr = document.createElement('tr');
                    tr.dataset.empId = emp.employee_id;
                    
                    const status = emp.standup_status || '';
                    
                    tr.innerHTML = `
                        <td><strong>${emp.name}</strong></td>
                        <td>
                            <div class="st-radio-group">
                                <label class="st-radio-label">
                                    <input type="radio" name="status_${emp.employee_id}" value="present" ${status==='present'?'checked':''}> Present
                                </label>
                                <label class="st-radio-label">
                                    <input type="radio" name="status_${emp.employee_id}" value="absent" ${status==='absent'?'checked':''}> Absent
                                </label>
                                <label class="st-radio-label">
                                    <input type="radio" name="status_${emp.employee_id}" value="leave" ${status==='leave'?'checked':''}> Leave
                                </label>
                            </div>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            } else {
                document.getElementById('st-form-empty').classList.remove('st-hidden');
            }
        } catch(e) {
            console.error(e);
            alert('Failed to load employees.');
        }
    }

    // Bulk Actions
    window.stMarkAll = function(status) {
        const trs = document.querySelectorAll('#st-employee-table tbody tr');
        trs.forEach(tr => {
            const empId = tr.dataset.empId;
            const radio = document.querySelector(`input[name="status_${empId}"][value="${status}"]`);
            if(radio) radio.checked = true;
        });
    }

    // Save
    document.getElementById('btn-save').addEventListener('click', async () => {
        const records = [];
        const trs = document.querySelectorAll('#st-employee-table tbody tr');
        
        trs.forEach(tr => {
            const empId = tr.dataset.empId;
            const checked = document.querySelector(`input[name="status_${empId}"]:checked`);
            if(checked) {
                records.push({
                    employee_id: empId,
                    status: checked.value
                });
            }
        });

        if(records.length === 0) {
            alert('Please select status for at least one employee.');
            return;
        }

        const date = datePicker.value;
        const spinner = document.getElementById('st-save-spinner');
        spinner.classList.remove('st-hidden');

        try {
            const res = await fetch(`${restUrl}/save`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce
                },
                body: JSON.stringify({ date, records })
            });
            const result = await res.json();
            
            spinner.classList.add('st-hidden');
            if(result.success) {
                showList();
            } else {
                alert(result.message || 'Failed to save');
            }
        } catch(e) {
            console.error(e);
            alert('Error saving data.');
            spinner.classList.add('st-hidden');
        }
    });

    // Init
    loadHistory(monthFilter.value);
})();
