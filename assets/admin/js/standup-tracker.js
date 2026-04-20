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
