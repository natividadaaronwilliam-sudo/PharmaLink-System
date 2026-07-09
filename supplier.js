class PharmacySupplierManager {
    constructor(containerSelector, buttonSelector) {
        this.supplierContainer = document.querySelector(containerSelector);
        this.addButton = document.querySelector(buttonSelector);
        this.searchField = document.getElementById('supplier-search');
        this.statusFilter = document.getElementById('supplier-status-filter'); // <-- NEW: was never wired up before
        this.allSuppliers = []; // Store the full list so we can filter client-side
        this.init();
    }

    init() {
        this.setupAddSupplierButton();
        this.setupSearchInput();
        this.setupStatusFilter(); // <-- NEW
        this.fetchSuppliers();
    }

  setupAddSupplierButton() {
    // Use the stored addButton element
    if (this.addButton) this.addButton.addEventListener('click', () => this.showAddSupplierModal());
  }

    showAddSupplierModal() {
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Add Supplier</h2>
                    <span class="close">&times;</span>
                </div>
                <form id="add-supplier-form">
                    <div class="form-group">
                        <label>Supplier Name</label>
                        <input type="text" id="supplier-name" required>
                    </div>
                    <div class="form-group">
                        <label>Contact Number</label>
                        <input type="text" id="supplier-contact" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" id="supplier-email" required>
                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <textarea id="supplier-address" rows="3" required></textarea>
                    </div>
                    <button type="submit" class="btn-primary">Save Supplier</button>
                </form>
            </div>
        `;

        document.body.appendChild(modal);
        modal.style.display = 'flex';

        modal.querySelector('.close').addEventListener('click', () => modal.remove());
        modal.addEventListener('click', (e) => { if (e.target === modal) modal.remove(); });

        modal.querySelector('form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleAddSupplier(modal);
        });
    }

    handleAddSupplier(modal) {
        const supplierData = {
            name: document.getElementById('supplier-name').value.trim(),
            contact: document.getElementById('supplier-contact').value.trim(),
            email: document.getElementById('supplier-email').value.trim(),
            address: document.getElementById('supplier-address').value.trim(),
        };

        fetch('add_supplier.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(supplierData)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                this.showSuccess('Supplier added successfully!');
                modal.remove();
                this.fetchSuppliers();
            } else {
                this.showError(data.message || 'Failed to add supplier.');
            }
        })
        .catch(err => {
            console.error(err);
            this.showError('Error adding supplier.');
        });
    }

    showSuccess(msg) { this.showMessage(msg, 'success'); }
    showError(msg) { this.showMessage(msg, 'error'); }

    showEditSupplierModal(supplier) {
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Edit Supplier</h2>
                    <span class="close">&times;</span>
                </div>
                <form id="edit-supplier-form">
                    <div class="form-group">
                        <label>Supplier Name</label>
                        <input type="text" id="edit-supplier-name" value="${supplier.supplier_name}" required>
                    </div>
                    <div class="form-group">
                        <label>Contact Number</label>
                        <input type="text" id="edit-supplier-contact" value="${supplier.contact_number || ''}" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" id="edit-supplier-email" value="${supplier.email || ''}" required>
                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <textarea id="edit-supplier-address" rows="3" required>${supplier.address || ''}</textarea>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select id="edit-supplier-status">
                            <option value="Active" ${supplier.status === 'Active' ? 'selected' : ''}>Active</option>
                            <option value="Inactive" ${supplier.status === 'Inactive' ? 'selected' : ''}>Inactive</option>
                        </select>
                    </div>
                    <div class="form-group" id="inactive-reason-group" style="${supplier.status === 'Inactive' ? '' : 'display:none;'}">
                        <label>Why Inactive?</label>
                        <input type="text" id="edit-inactive-reason" value="${supplier.inactive_reason || ''}" placeholder="e.g. Contract ended, poor delivery record">
                        <small style="color:#6b7280;">Required when setting supplier to Inactive</small>
                    </div>
                    <button type="submit" class="btn-primary">Save Changes</button>
                </form>
            </div>
        `;

        document.body.appendChild(modal);
        modal.style.display = 'flex';

        modal.querySelector('.close').addEventListener('click', () => modal.remove());
        modal.addEventListener('click', (e) => { if (e.target === modal) modal.remove(); });

        modal.querySelector('form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleEditSupplier(modal, supplier.supplier_id);
        });

        const statusSel = modal.querySelector('#edit-supplier-status');
        const reasonGroup = modal.querySelector('#inactive-reason-group');
        statusSel?.addEventListener('change', () => {
            if (reasonGroup) reasonGroup.style.display = statusSel.value === 'Inactive' ? '' : 'none';
        });
    }

    handleEditSupplier(modal, supplierId) {
        const supplierData = {
            supplier_id: supplierId,
            name: document.getElementById('edit-supplier-name').value.trim(),
            contact: document.getElementById('edit-supplier-contact').value.trim(),
            email: document.getElementById('edit-supplier-email').value.trim(),
            address: document.getElementById('edit-supplier-address').value.trim(),
            status: document.getElementById('edit-supplier-status').value,
            inactive_reason: document.getElementById('edit-inactive-reason')?.value.trim() || '',
        };

        fetch('update_supplier.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(supplierData)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                this.showSuccess('Supplier updated successfully!');
                modal.remove();
                this.fetchSuppliers();
            } else {
                this.showError(data.message || 'Failed to update supplier.');
            }
        })
        .catch(err => {
            console.error(err);
            this.showError('Error updating supplier.');
        });
    }

    showMessage(message, type) {
        const msgDiv = document.createElement('div');
        msgDiv.className = `message ${type}`;
        msgDiv.textContent = message;
        document.body.appendChild(msgDiv);
        setTimeout(() => msgDiv.remove(), 4000);
    }

    setupSearchInput() {
        if (this.searchField) {
            this.searchField.addEventListener('keyup', () => this.filterSuppliers());
        }
    }

    // NEW: wire up the Status dropdown (All / Active / Inactive)
    setupStatusFilter() {
        if (this.statusFilter) {
            this.statusFilter.addEventListener('change', () => this.filterSuppliers());
        }
    }

    // Fetch suppliers from backend and store the full list for filtering
    fetchSuppliers() {
        fetch('get_suppliers.php')
            .then(res => res.json())
            .then(data => {
                this.allSuppliers = data;
                this.filterSuppliers();
            })
            .catch(err => {
                console.error(err);
                this.showError('Failed to load suppliers.');
            });
    }

    // Filters the stored data based on both the search box AND the status dropdown
    filterSuppliers() {
        const searchTerm = this.searchField ? this.searchField.value.toLowerCase().trim() : '';
        const statusValue = this.statusFilter ? this.statusFilter.value : 'all';

        const filtered = this.allSuppliers.filter(supplier => {
            const matchesName = supplier.supplier_name.toLowerCase().includes(searchTerm);

            const supplierStatus = (supplier.status || '').toLowerCase();
            const matchesStatus = statusValue === 'all' || supplierStatus === statusValue;

            return matchesName && matchesStatus;
        });

        this.displaySuppliers(filtered);
    }

    // Display suppliers and their medicines
    displaySuppliers(suppliers) {
    if (!this.supplierContainer) return;
    this.supplierContainer.innerHTML = '';

    if (!suppliers || suppliers.length === 0) {
        this.supplierContainer.innerHTML = `<p class="empty-message">No suppliers found.</p>`;
        return;
    }

    // Since displaySuppliers is called often (on every keystroke), 
    // fetching lots inside it is inefficient. 
    // We assume the lots data is handled or fetched separately 
    // upon initial module load for efficiency. 

    // Assuming we still need lot data for the cards, we must ensure 
    // it's available. To keep your original logic working with minimal change,
    // we'll revert to fetching lots here, but be aware this is less efficient.
    // **A better solution is to fetch lots once in init() and store them.**

    // --- Using your original, but less efficient, lots fetching logic: ---
    fetch('get_inventory_lots.php')
        .then(res => res.json())
        .then(lots => {
            suppliers.forEach(supplier => {
                const card = document.createElement('div');
                card.className = 'supplier-card';
                card.innerHTML = `
                    <div class="supplier-header">
                        <h3>${supplier.supplier_name}</h3>
                        <span class="status ${supplier.status === 'Active' ? 'active' : 'inactive'}">${supplier.status}</span>
                    </div>
                    ${supplier.status === 'Inactive' && supplier.inactive_reason ? `<p style="color:#b91c1c; font-size:13px;"><strong>Why inactive:</strong> ${supplier.inactive_reason}</p>` : ''}
                    <p><strong>Contact:</strong> ${supplier.contact_number || 'N/A'}</p>
                    <p><strong>Email:</strong> ${supplier.email || 'N/A'}</p>
                    <p><strong>Address:</strong> ${supplier.address || 'N/A'}</p>
                    <p><strong>Medicines Supplied:</strong></p>
                    <ul>
                        ${
                            lots.filter(lot => lot.supplier == supplier.supplier_id).length > 0
                            ? lots.filter(lot => lot.supplier == supplier.supplier_id)
                                .map(lot => `<li>${lot.generic_name} (${lot.brand_name}, ${lot.dosage} ${lot.form})</li>`)
                                .join('')
                            : '<li><em>No medicines listed</em></li>'
                        }
                    </ul>
                    <button type="button" class="btn-edit-supplier" style="margin-top:8px; padding:6px 12px; background:#1e90ff; color:#fff; border:none; border-radius:4px; cursor:pointer;">Edit</button>
                `;
                const editBtn = card.querySelector('.btn-edit-supplier');
                editBtn.addEventListener('click', () => this.showEditSupplierModal(supplier));
                this.supplierContainer.appendChild(card);
            });
        })
        .catch(err => {
            console.error(err);
            this.showError('Failed to load medicines.');
        });
    // ----------------------------------------------------------------------
}
}

/**
 * 💡 The wrapper function called by the admin page's tab handler.
 * This ensures the script only runs AFTER the HTML elements exist.
 */
function initializeSupplierModule() {
    // Prevent re-initialization if the user clicks the tab multiple times
    if (window.supplierManagerInstance) {
        window.supplierManagerInstance.fetchSuppliers(); // Just refresh data
        return;
    }
    
    // Instantiate the class, passing the required selectors
    window.supplierManagerInstance = new PharmacySupplierManager('.supplier-list', '.btn-add-supplier');
}
