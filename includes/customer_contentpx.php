<div class="prescription-header">
    <h2>Upload Prescription</h2>
    <p class="subtitle">Submit your prescription image for pharmacy review.</p>
</div>

<div class="upload-box">
    <i class="fas fa-cloud-upload-alt" style="font-size:40px; color:#574b90; margin-bottom:10px;"></i>
    <p>Drag &amp; drop or choose a prescription image (JPG, PNG, PDF)</p>
    <form id="prescriptionUploadForm" enctype="multipart/form-data">
        <input type="file" id="prescriptionFile" name="prescription_file" accept="image/*,.pdf" required>
        <label for="prescriptionFile" class="upload-btn">Choose File</label>
        <button type="submit" class="upload-btn" style="background:#574b90; margin-left:8px;">Upload</button>
    </form>
    <p id="prescriptionUploadMsg" style="margin-top:12px; font-weight:600;"></p>
</div>

<div class="pres-history">
    <h3>Upload History</h3>
    <table>
        <thead>
            <tr><th>File</th><th>Date</th><th>OCR Text</th><th>Availability</th></tr>
        </thead>
        <tbody id="prescriptionHistoryBody">
            <tr><td colspan="4" style="text-align:center;color:#888;">Loading...</td></tr>
        </tbody>
    </table>
</div>

<script>
(function () {
    const form = document.getElementById('prescriptionUploadForm');
    const fileInput = document.getElementById('prescriptionFile');
    const msg = document.getElementById('prescriptionUploadMsg');
    const tbody = document.getElementById('prescriptionHistoryBody');

    function renderAvailability(p) {
        if (p.ocr_status === 'unavailable') {
            return '<span style="color:#94a3b8;">OCR not installed on server</span>';
        }
        if (p.ocr_status === 'skipped_pdf') {
            return '<span style="color:#94a3b8;">OCR skipped (PDF)</span>';
        }
        if (p.ocr_status === 'failed') {
            return '<span style="color:#dc3545;">Could not read text</span>';
        }
        if (p.ocr_status === 'pending' || !p.ocr_status) {
            return '<span style="color:#94a3b8;">Processing…</span>';
        }
        const matches = Array.isArray(p.availability_summary) ? p.availability_summary : [];
        if (!matches.length) {
            return '<span style="color:#94a3b8;">No matching medicine found</span>';
        }
        return matches.map(m => {
            const color = m.status === 'In Stock' ? '#27ae60' : '#dc3545';
            return `<div><strong>${m.name}</strong>: <span style="color:${color}; font-weight:600;">${m.status}</span></div>`;
        }).join('');
    }

    function loadHistory() {
        fetch('get_prescriptions.php')
            .then(r => r.json())
            .then(data => {
                if (!data.success || !data.prescriptions.length) {
                    tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:#888;">No uploads yet.</td></tr>';
                    return;
                }
                tbody.innerHTML = data.prescriptions.map(p => `
                    <tr>
                        <td>${p.filename}</td>
                        <td>${new Date(p.created_at).toLocaleString()}</td>
                        <td>${p.extracted_text ? p.extracted_text.substring(0, 80) : '—'}</td>
                        <td>${renderAvailability(p)}</td>
                    </tr>`).join('');
            })
            .catch(() => {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:red;">Failed to load history.</td></tr>';
            });
    }

    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!fileInput.files.length) return;
            const fd = new FormData();
            fd.append('prescription_file', fileInput.files[0]);
            msg.style.color = '#555';
            msg.textContent = 'Uploading and reading prescription...';
            fetch('upload_prescription.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    msg.style.color = d.success ? 'green' : 'red';
                    msg.textContent = d.message || (d.success ? 'Uploaded!' : 'Upload failed.');
                    if (d.success) { form.reset(); loadHistory(); }
                })
                .catch(() => { msg.style.color = 'red'; msg.textContent = 'Upload error.'; });
        });
    }
    loadHistory();
})();
</script>