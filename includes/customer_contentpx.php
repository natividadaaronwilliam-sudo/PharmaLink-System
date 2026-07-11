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

<!-- Popup shown right after an upload finishes reading the prescription -->
<div id="rxResultOverlay" style="display:none; position:fixed; inset:0; background:rgba(15,15,25,0.55); z-index:1000; align-items:center; justify-content:center;">
    <div id="rxResultModal" style="background:#fff; width:min(480px, 92vw); max-height:85vh; overflow-y:auto; border-radius:12px; padding:24px 24px 20px; box-shadow:0 20px 50px rgba(0,0,0,0.25);">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:6px;">
            <h3 style="margin:0; color:#2d2350;">Prescription Check</h3>
            <button id="rxResultClose" type="button" aria-label="Close" style="background:none; border:none; font-size:22px; line-height:1; cursor:pointer; color:#888;">&times;</button>
        </div>
        <p id="rxResultFilename" style="margin:0 0 14px; font-size:0.85em; color:#888;"></p>

        <div id="rxResultBody"></div>

        <button id="rxResultOkBtn" type="button" class="upload-btn" style="background:#574b90; margin-top:18px; width:100%;">Got it</button>
    </div>
</div>

<script>
(function () {
    const form = document.getElementById('prescriptionUploadForm');
    const fileInput = document.getElementById('prescriptionFile');
    const msg = document.getElementById('prescriptionUploadMsg');
    const tbody = document.getElementById('prescriptionHistoryBody');

    const rxOverlay = document.getElementById('rxResultOverlay');
    const rxFilename = document.getElementById('rxResultFilename');
    const rxBody = document.getElementById('rxResultBody');
    const rxClose = document.getElementById('rxResultClose');
    const rxOkBtn = document.getElementById('rxResultOkBtn');

    function closeRxModal() {
        rxOverlay.style.display = 'none';
    }
    rxClose.addEventListener('click', closeRxModal);
    rxOkBtn.addEventListener('click', closeRxModal);
    rxOverlay.addEventListener('click', function (e) {
        if (e.target === rxOverlay) closeRxModal();
    });

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str == null ? '' : str;
        return div.innerHTML;
    }

    // Builds the popup content based on the same fields upload_prescription.php returns.
    function showRxResultPopup(d) {
        rxFilename.textContent = d.filename || '';

        let html = '';

        if (d.ocr_status === 'unavailable') {
            html += `<p style="color:#94a3b8;">OCR is not installed on this server, so the prescription text could not be read automatically. It has been saved for manual pharmacist review.</p>`;
        } else if (d.ocr_status === 'skipped_pdf') {
            html += `<p style="color:#94a3b8;">This was uploaded as a PDF, so automatic text reading was skipped. A pharmacist will review it manually.</p>`;
        } else if (d.ocr_status === 'failed') {
            html += `<p style="color:#dc3545;">We couldn't clearly read the text on this prescription (try a clearer photo, better lighting, or straighten the image). A pharmacist will still review it manually.</p>`;
        } else {
            html += `<p style="margin:0 0 6px; font-weight:600; color:#333;">Text we read from your prescription:</p>`;
            html += `<div style="background:#f4f2fb; border-radius:8px; padding:10px 12px; font-size:0.9em; color:#444; white-space:pre-wrap; max-height:120px; overflow-y:auto; margin-bottom:16px;">${escapeHtml(d.extracted_text) || '—'}</div>`;

            const matches = Array.isArray(d.availability_summary) ? d.availability_summary : [];
            html += `<p style="margin:0 0 8px; font-weight:600; color:#333;">Stock check:</p>`;

            if (!matches.length) {
                html += `<p style="color:#94a3b8;">No medicine names on this prescription matched our current catalog. A pharmacist will review it manually.</p>`;
            } else {
                html += '<div>' + matches.map(m => {
                    const inStock = m.status === 'In Stock';
                    const color = inStock ? '#27ae60' : '#dc3545';
                    const icon = inStock ? '✔' : '✘';
                    return `<div style="display:flex; justify-content:space-between; align-items:center; padding:8px 10px; border-bottom:1px solid #eee;">
                        <span>${escapeHtml(m.name)}</span>
                        <span style="color:${color}; font-weight:600;">${icon} ${m.status}</span>
                    </div>`;
                }).join('') + '</div>';
            }
        }

        rxBody.innerHTML = html;
        rxOverlay.style.display = 'flex';
    }

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
                    if (d.success) {
                        form.reset();
                        loadHistory();
                        showRxResultPopup(d);
                    }
                })
                .catch(() => { msg.style.color = 'red'; msg.textContent = 'Upload error.'; });
        });
    }
    loadHistory();
})();
</script>