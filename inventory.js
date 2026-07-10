// --- VARIABLE DECLARATIONS ---
    const modal = document.getElementById("addItemModal");
    const addBtn = document.getElementById("addItemBtn");
    const closeBtn = document.getElementById("closeModal");
    const form = document.getElementById("addItemForm");
    const filter = document.getElementById("categoryFilter");
    const tableBody = document.getElementById("inventoryBody");
    const totalItems = document.getElementById("total-items");
    const lowStock = document.getElementById("low-stock");
    const outStock = document.getElementById("out-stock");
    const expiringSoonCard = document.getElementById("expiring-soon");
    const drugSelect = document.getElementById("drug_master_id"); 

    const drugModal = document.getElementById("addDrugModal");
    const addDrugBtn = document.getElementById("addDrugMasterBtn");
    const closeDrugBtn = document.getElementById("closeDrugModal");
    const drugForm = document.getElementById("addDrugForm");

    const editModal = document.getElementById("editLotModal");
    const closeEditBtn = document.getElementById("closeEditModal");
    const editForm = document.getElementById("editLotForm");

    const drugMasterBody = document.getElementById("drugMasterBody");

    const editDrugMasterModal = document.getElementById("editDrugMasterModal");
    const closeEditDrugMasterBtn = document.getElementById("closeEditDrugMasterModal");
    const editDrugMasterForm = document.getElementById("editDrugMasterForm");

    const supplierSelect = document.getElementById("supplier"); // <-- make sure you have this element

    const lotStatusFilter = document.getElementById("lotStatusFilter");
    const inventorySearch = document.getElementById("inventorySearch");


let allSuppliers = [];
let allInventoryData = [];
let activeCardFilter = 'all';

  

    // --- MODAL CONTROLS ---
    addBtn.onclick = () => modal.style.display = "flex";
    closeBtn.onclick = () => modal.style.display = "none";
    
    addDrugBtn.onclick = () => drugModal.style.display = "flex";
    closeDrugBtn.onclick = () => drugModal.style.display = "none";
    
    closeEditBtn.onclick = () => editModal.style.display = "none";

    window.onclick = (e) => { 
        if (e.target == modal) modal.style.display = "none"; 
        if (e.target == drugModal) drugModal.style.display = "none"; 
        if (e.target == editModal) editModal.style.display = "none";
        if (e.target == editDrugMasterModal) editDrugMasterModal.style.display = "none"; 
    };


        

    // --- HELPER FUNCTIONS ---
    function capitalizeFirstLetter(string) {
        if (!string) return '';
        // Only capitalize the first letter and return the rest of the string
        return string.charAt(0).toUpperCase() + string.slice(1);
    }

    function isExpiringSoon(expirationDate) {
        if (!expirationDate) return false;
        const today = new Date();
        const expiry = new Date(expirationDate);
        const ninetyDays = 90 * 24 * 60 * 60 * 1000;
        return expiry.getTime() - today.getTime() <= ninetyDays && expiry.getTime() > today.getTime();
    }

    // Returns { label, color } for the expiration date column.
    // Red = already expired, Orange = expiring within 90 days, Green = still OK.
    function getExpiryStatus(expirationDate) {
        if (!expirationDate) return { label: "Unknown", color: "#6b7280" };

        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const expiry = new Date(expirationDate);
        const ninetyDays = 90 * 24 * 60 * 60 * 1000;

        if (expiry.getTime() <= today.getTime()) {
            return { label: "Expired", color: "#dc2626" }; // Red
        }
        if (expiry.getTime() - today.getTime() <= ninetyDays) {
            return { label: "Expiring Soon", color: "#f59e0b" }; // Orange
        }
        return { label: "OK", color: "#16a34a" }; // Green
    }

    function getStatus(item) {
        if (item.current_stock <= 0) return "Out of Stock";
        if (item.current_stock < item.minimum_stock) return "Low Stock"; 
        return "In Stock";
    }

    let allDrugsMasterData = [];
    // --- FETCHING DATA ---
// --- inventory.js ---

// Note: Ensure drugMasterFilter is defined globally or retrieved locally
const drugMasterFilter = document.getElementById("drugMasterFilter"); 


async function fetchDrugsMaster() {
    // 1. Get the status filter from the UI element. Defaults to 'active' if the filter element doesn't exist yet.
    const status = drugMasterFilter ? drugMasterFilter.value : 'active';
    
    try {
        // 2. Fetch the data, passing the filter status to the PHP endpoint.
        const res = await fetch(`get_drugs_master.php?status=${status}`);
        const drugs = await res.json();
        
        // ******************************************************
        // NEW LINE: Store the fetched data globally for filtering
        // This 'drugs' array contains items based on the 'status' filter ('active' or 'all').
        // ******************************************************
        allDrugsMasterData = drugs;
        
        // 3. Populate the Add Lot dropdown (ONLY use ACTIVE drugs)
        drugSelect.innerHTML = '<option value="">Search/Select Standard Drug...</option>';
        
        // Filter the fetched list down to only active drugs for the dropdown
        const activeDrugs = drugs.filter(drug => drug.is_active == 1); 
        
        activeDrugs.forEach(drug => {
            const option = document.createElement('option');
            option.textContent = `${drug.generic_name} (${drug.brand_name || 'Generic'}) ${drug.dosage} ${drug.form}`;
            option.value = drug.drug_id;
            drugSelect.appendChild(option);
        });

        // 4. Populate the Master List table using ALL fetched data
        loadDrugsMasterTable(drugs); 

    } catch (error) {
        console.error("Error fetching master drug list:", error);
        drugSelect.innerHTML = '<option value="">Error Loading Drugs</option>';
    }
}

async function fetchInventory(category = 'all') {
    const status = lotStatusFilter ? lotStatusFilter.value : 'active';

    try {
        const res = await fetch(`get_inventory_lots.php?status=${status}&exclude_expired=1`);
        const data = await res.json();
        allInventoryData = data;
        applyInventoryView(category);
    } catch (error) {
        console.error("Error fetching inventory lots:", error);
    }
}

function applyInventoryView(category = 'all') {
    let data = category === 'all' ? allInventoryData : allInventoryData.filter(i => i.category === category);

    if (activeCardFilter === 'low') {
        data = data.filter(i => getStatus(i) === 'Low Stock');
    } else if (activeCardFilter === 'out') {
        data = data.filter(i => getStatus(i) === 'Out of Stock');
    } else if (activeCardFilter === 'expiring') {
        data = data.filter(i => getExpiryStatus(i.expiration_date).label === 'Expiring Soon');
    }

    loadInventory(data, 'all');
}
    

    // Reactivate Drug Master Definition (Sets is_active back to 1)
async function reactivateDrugMaster(drugId) {
    if (!confirm("Are you sure you want to REACTIVATE this drug definition?")) {
        return;
    }

    try {
        // You'll need to create a simple 'reactivate_drug_master.php' endpoint
        // that executes: UPDATE drugs_master SET is_active = 1 WHERE drug_id = ?
        const res = await fetch(`reactivate_drug_master.php?id=${drugId}`, {
            method: "POST" 
        });
        
        const result = await res.json();

        if (result.success) {
            alert("Drug definition reactivated successfully.");
            fetchDrugsMaster(); // Refresh table 
        } else {
            alert("Error reactivating drug: " + (result.message || "Unknown error."));
        }
    } catch (error) {
        console.error("Fetch error:", error);
        alert("A network error occurred.");
    }
}

    // --- LOADING DATA TO TABLE ---
 function loadInventory(data, category) {
    tableBody.innerHTML = '';

    const filtered = category === 'all' ? data : data.filter(i => i.category === category);

    let low = 0, out = 0, expiring = 0;

    filtered.forEach(item => {
        const status = getStatus(item);
        if (status === "Low Stock") low++;
        if (status === "Out of Stock") out++;

        const expiryStatus = getExpiryStatus(item.expiration_date);
        if (expiryStatus.label === "Expiring Soon") expiring++;

        const expColor = expiryStatus.color;
        
        const color = status === "Out of Stock" ? "#b91c1c" :
                        status === "Low Stock" ? "#f59e0b" : "#16a34a";

        const displayCategory = capitalizeFirstLetter(item.category);
        
        // --- NEW LOGIC: DETERMINE ACTION BUTTON ---
        // item.is_active is fetched from the database (1 or 0)
        const isActiveLot = item.is_active == 1; 

        let lotActionButton;

        if (isActiveLot) {
            // Show DEACTIVATE (Archive) button for active lots
            lotActionButton = `
                <button 
                    onclick="deactivateLot(${item.lot_inventory_id})" 
                    style="background:#b91c1c; padding:5px 8px; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    <i class="fa fa-archive"></i>
                </button>`;
        } else {
            // Show REACTIVATE (Unarchive) button for archived lots
            lotActionButton = `
                <button 
                    onclick="reactivateLot(${item.lot_inventory_id})" 
                    style="background:#2563eb; padding:5px 8px; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    <i class="fa fa-undo"></i>
                </button>`;
        }
        // ------------------------------------------

        const row = `
            <tr style="text-align:center; border-bottom:1px solid #eee;">
                <td>${item.generic_name}</td>
                <td>${item.brand_name || '-'}</td>
                <td>${item.dosage}</td>
                <td>${item.form}</td>
                <td>${displayCategory}</td> <td style="font-weight: 600;">${item.lot_number}</td>
                <td style="color: ${expColor}; font-weight: 600;">${item.expiration_date}</td>
                <td>${item.current_stock}</td>
                <td>${item.minimum_stock}</td>
                <td>₱${parseFloat(item.price).toFixed(2)}</td> 
                <td>
                    ${allSuppliers.find(s => s.supplier_id == item.supplier)?.supplier_name || 'Unknown'}
                </td>
                <td style="color:${color}; font-weight:600;">${status}</td>
                <td>
                    <button 
                        onclick="editLot(${item.lot_inventory_id})" 
                        style="background:#f59e0b; padding:5px 8px; color: white; border: none; border-radius: 4px; cursor: pointer;">
                        <i class="fa fa-edit"></i>
                    </button>
                    ${lotActionButton} 
                </td>
            </tr>`;
        tableBody.innerHTML += row;
    });

    if (totalItems) totalItems.textContent = allInventoryData.length;
    if (lowStock) lowStock.textContent = allInventoryData.filter(i => getStatus(i) === 'Low Stock').length;
    if (outStock) outStock.textContent = allInventoryData.filter(i => getStatus(i) === 'Out of Stock').length;
    if (expiringSoonCard) expiringSoonCard.textContent = allInventoryData.filter(i => getExpiryStatus(i.expiration_date).label === 'Expiring Soon').length;
}
    
    // --- ACTION FUNCTIONS ---
    async function deleteLot(lotId) {
        if (!confirm("Are you sure you want to delete this stock lot? This action cannot be undone.")) {
            return;
        }

        try {
            // Requires delete_lot.php endpoint
            const res = await fetch(`delete_lot.php?id=${lotId}`, {
                method: "DELETE"
            });
            
            const result = await res.json();

            if (result.success) {
                alert("Stock lot deleted successfully.");
                fetchInventory(); // Refresh the table
            } else {
                alert("Error deleting lot: " + (result.message || "Unknown error. Check PHP console."));
            }
        } catch (error) {
            console.error("Fetch error:", error);
            alert("A network error occurred while deleting the lot.");
        }
    }

    async function editLot(lotId) {
    try {
        // 1. Fetch the data for the specific lotId
        const res = await fetch(`get_lot_details.php?id=${lotId}`);
        const result = await res.json();

        if (result.success) {
            const lot = result.lot;
            
            // 2. Populate the Edit Modal fields
            
            // Hidden ID field
            document.getElementById('edit_lot_inventory_id').value = lot.lot_inventory_id;
            
            // Display Drug Name (Read-only)
            const drugName = `${lot.generic_name} ${lot.dosage} ${lot.form}`;
            document.getElementById('editDrugName').textContent = drugName;
            document.getElementById('edit_drug_label').textContent = drugName;

            // Lot-specific fields
            document.getElementById('edit_lot_number').value = lot.lot_number;
            document.getElementById('edit_expiration_date').value = lot.expiration_date;
            document.getElementById('edit_current_stock').value = lot.current_stock;
            document.getElementById('edit_price').value = lot.price.toFixed(2);
            document.getElementById('edit_supplier').value = lot.supplier;

            // 3. Display the modal
            editModal.style.display = "flex";

        } else {
            alert("Error loading lot details: " + result.message);
        }
    } catch (error) {
        console.error("Fetch error:", error);
        alert("A network error occurred while fetching lot details.");
    }
}

// 3. DEACTIVATE DRUG MASTER DEFINITION
async function deactivateDrugMaster(drugId) {
    if (!confirm("Are you sure you want to DEACTIVATE this drug definition? Existing stock lots will remain linked, but you won't be able to add new stock of this type.")) {
        return;
    }

    try {
        // CALL THE NEW DEACTIVATE ENDPOINT
        const res = await fetch(`deactivate_drug_master.php?id=${drugId}`, {
            method: "POST" // Using POST for security, although GET is also common
        });
        
        const result = await res.json();

        if (result.success) {
            alert("Drug definition deactivated (archived) successfully.");
            fetchDrugsMaster(); // Refresh Master List table (it will disappear)
            fetchInventory(); // Refresh lot list (no change, but good practice)
        } else {
            alert("Error deactivating drug: " + (result.message || "Unknown error."));
        }
    } catch (error) {
        console.error("Fetch error:", error);
        alert("A network error occurred while deactivating the drug.");
    }
}

// --- ACTION FUNCTIONS (Deactivate Lot) ---
async function deactivateLot(lotId) { // Changed name to deactivateLot
    if (!confirm("Are you sure you want to ARCHIVE this stock lot? It will be set to 0 stock and removed from active inventory.")) {
        return;
    }

    try {
        // Target the archive_lot.php (or rename it to deactivate_lot.php)
        const res = await fetch(`deactivate_lot.php?id=${lotId}`, { 
            method: "POST" 
        });
        
        const result = await res.json();

        if (result.success) {
            alert("Stock lot archived successfully and stock set to zero.");
fetchInventory("all");
        } else {
            alert("Error deactivating lot: " + (result.message || "Unknown error."));
        }
    } catch (error) {
        console.error("Fetch error:", error);
        alert("A network error occurred while deactivating the lot.");
    }
}

// --- ACTION FUNCTIONS (Reactivate Lot) ---
async function reactivateLot(lotId) {
    if (!confirm("Are you sure you want to REACTIVATE this stock lot? Its status will be changed to 'Active', but you MUST manually set the stock quantity again.")) {
        return;
    }

    try {
        // Target the new reactivate_lot.php endpoint
        const res = await fetch(`reactivate_lot.php?id=${lotId}`, {
            method: "POST" 
        });
        
        const result = await res.json();

        if (result.success) {
            alert(result.message); // This will display the "Stock lot reactivated..." message
            // Refresh the table to show the lot again (if the filter is set to 'all' or no filter)
fetchInventory("all");
            // OPTIONAL: Open the edit modal automatically so the user can update the stock
            editLot(lotId); 
        } else {
            alert("Error reactivating lot: " + (result.message || "Unknown error."));
        }
    } catch (error) {
        console.error("Fetch error:", error);
        alert("A network error occurred.");
    }
}

// 4. OPEN EDIT DRUG MASTER MODAL (Populates data)
    async function openEditDrugModal(drugId) {
        try {
            const res = await fetch(`get_drug_master_details.php?id=${drugId}`);
            const result = await res.json();

            if (result.success) {
                const drug = result.drug;

                // Populate fields
                document.getElementById('edit_master_drug_id').value = drug.drug_id;
                document.getElementById('currentMasterDrugName').textContent = `${drug.generic_name} ${drug.dosage}`;
                document.getElementById('edit_generic_name').value = drug.generic_name;
                document.getElementById('edit_brand_name').value = drug.brand_name;
                document.getElementById('edit_dosage').value = drug.dosage;
                document.getElementById('edit_form').value = drug.form;
                document.getElementById('edit_category').value = drug.category; // Sets the correct option
                document.getElementById('edit_minimum_stock').value = drug.minimum_stock;

                editDrugMasterModal.style.display = "flex";
            } else {
                alert("Error loading drug definition: " + result.message);
            }
        } catch (error) {
            console.error("Fetch error:", error);
            alert("A network error occurred while fetching drug details.");
        }
    }

function loadDrugsMasterTable(data) {
    // 1. Clear the table body
    drugMasterBody.innerHTML = '';
    
    // 2. Iterate over the filtered data (which now includes 'is_active' based on your PHP fix)
    data.forEach(drug => {
        
        // --- Status Logic ---
        // Check if the drug is active (remember PHP casts BOOLEAN to 1 or 0, so check against 1)
        const isActive = drug.is_active == 1;
        const statusText = isActive ? 'Active' : 'Archived';
        const statusColor = isActive ? '#16a34a' : '#9ca3af'; // Green for Active, Gray for Archived
        
        // --- Display Variables ---
        const displayCategory = capitalizeFirstLetter(drug.category || 'N/A');
        const minStockDisplay = drug.minimum_stock ?? '0'; 
        
        // --- Dynamic Button Rendering ---
        let actionButton;
        if (isActive) {
            // Button for active drugs: DEACTIVATE
            actionButton = `
                <button 
                    onclick="deactivateDrugMaster(${drug.drug_id})" 
                    style="background:#b91c1c; padding:5px 8px; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    <i class="fa fa-archive"></i> 
                </button>`;
        } else {
            // Button for archived drugs: REACTIVATE
            actionButton = `
                <button 
                    onclick="reactivateDrugMaster(${drug.drug_id})" 
                    style="background:#2563eb; padding:5px 8px; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    <i class="fa fa-undo"></i> 
                </button>`;
        }

        // --- Create the Row HTML ---
        const row = `
            <tr style="text-align:center; border-bottom:1px solid #eee;">
                <td>${drug.generic_name}</td>
                <td>${drug.brand_name || '-'}</td>
                <td>${drug.dosage}</td>
                <td>${drug.form}</td>
                <td>${displayCategory}</td>
                <td>${minStockDisplay}</td>
                <td style="color:${statusColor}; font-weight: 600;">${statusText}</td>
                <td>
                    <button 
                        onclick="openEditDrugModal(${drug.drug_id})" 
                        style="background:#f59e0b; padding:5px 8px; color: white; border: none; border-radius: 4px; cursor: pointer;">
                        <i class="fa fa-edit"></i> 
                    </button>
                    ${actionButton}
                </td>
            </tr>`;
        
        drugMasterBody.innerHTML += row;
    });
}


    // --- FORM SUBMISSIONS ---
    drugForm.addEventListener("submit", async (e) => {
        e.preventDefault();

        const newDrug = {
            generic_name: drugForm.new_generic_name.value,
            brand_name: drugForm.new_brand_name.value,
            dosage: drugForm.new_dosage.value,
            form: drugForm.new_form.value,
            category: drugForm.new_category.value,
            minimum_stock: parseInt(drugForm.new_minimum_stock.value)
        };

        const res = await fetch("add_drug_master.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(newDrug)
        });

        const result = await res.json();
        if (result.success) {
            alert("New drug definition created successfully!");
            drugModal.style.display = "none";
            drugForm.reset();
            fetchDrugsMaster(); 
        } else {
            alert("Error adding drug: " + result.message);
        }
    });

    form.addEventListener("submit", async (e) => {
        e.preventDefault();

        const newLot = {
            drug_master_id: form.drug_master_id.value,
            lot_number: form.lot_number.value,
            expiration_date: form.expiration_date.value,
            current_stock: parseInt(form.current_stock.value),
            price: parseFloat(form.price.value),
            supplier: form.supplier.value
        };

        const res = await fetch("add_lot.php", { 
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(newLot)
        });

        const result = await res.json();
        if (result.success) {
            alert("New stock lot added successfully!");
            modal.style.display = "none";
            form.reset();
            fetchInventory(); 
        } else {
            alert("Error adding item: " + result.message);
        }
    });

    // NEW SUBMIT HANDLER: Handle Edit Drug Master Submit
    editDrugMasterForm.addEventListener("submit", async (e) => {
        e.preventDefault();

        const updatedDrug = {
            drug_id: document.getElementById('edit_master_drug_id').value,
            generic_name: document.getElementById('edit_generic_name').value,
            brand_name: document.getElementById('edit_brand_name').value,
            dosage: document.getElementById('edit_dosage').value,
            form: document.getElementById('edit_form').value,
            category: document.getElementById('edit_category').value,
            minimum_stock: parseInt(document.getElementById('edit_minimum_stock').value)
        };

        const res = await fetch("update_drug_master.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(updatedDrug)
        });

        const result = await res.json();
        if (result.success) {
            alert("Drug definition updated successfully!");
            editDrugMasterModal.style.display = "none";
            fetchDrugsMaster(); // Refresh both dropdown and table
        } else {
            alert("Error updating drug: " + result.message);
        }
    });

    editForm.addEventListener("submit", async (e) => {
    e.preventDefault();

    // Data to be sent to update_lot.php
    const updatedLot = {
        lot_inventory_id: document.getElementById('edit_lot_inventory_id').value,
        lot_number: document.getElementById('edit_lot_number').value,
        expiration_date: document.getElementById('edit_expiration_date').value,
        current_stock: parseInt(document.getElementById('edit_current_stock').value),
        price: parseFloat(document.getElementById('edit_price').value),
        supplier: document.getElementById('edit_supplier').value
    };

    // Use the update_lot.php endpoint
    const res = await fetch("update_lot.php", {
        method: "POST", // Using POST since pure PUT can be tricky with some hosting
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(updatedLot)
    });

    const result = await res.json();
    if (result.success) {
        alert("Stock lot updated successfully!");
        editModal.style.display = "none";
        fetchInventory(); // Refresh table
    } else {
        alert("Error updating lot: " + result.message);
    }
});
// --- INITIALIZATION ---
document.addEventListener("DOMContentLoaded", () => {
    
    // 1. Load Suppliers
    fetch('get_suppliers.php')
     .then(res => res.json())
     .then(data => {
        allSuppliers = data; // store globally
        
        // Get dropdowns
        const addSupplierSelect = document.getElementById("supplier");
        const editSupplierSelect = document.getElementById("edit_supplier"); 
        
        // Clear and set default option for both
        if (addSupplierSelect) {
            addSupplierSelect.innerHTML = '<option value="">Select Supplier...</option>';
        }
        if (editSupplierSelect) {
            editSupplierSelect.innerHTML = '<option value="">Select Supplier...</option>';
        }

        // Only show ACTIVE suppliers in the Add Lot dropdown, with a green dot indicator
        const activeSuppliers = data.filter(s => (s.status || 'Active') === 'Active');

        activeSuppliers.forEach(supplier => {
            const option = document.createElement('option');
            option.value = supplier.supplier_id;
            option.textContent = '🟢 ' + supplier.supplier_name;

            // Populate the ADD Lot dropdown (active suppliers only)
            if (addSupplierSelect) {
                addSupplierSelect.appendChild(option);
            }
        });

        // Edit Lot dropdown keeps ALL suppliers (so existing lots with an
        // inactive supplier still display correctly), but active ones get the dot
        data.forEach(supplier => {
            const editOption = document.createElement('option');
            editOption.value = supplier.supplier_id;
            const isActive = (supplier.status || 'Active') === 'Active';
            editOption.textContent = (isActive ? '🟢 ' : '') + supplier.supplier_name + (isActive ? '' : ' (Inactive)');

            if (editSupplierSelect) {
                editSupplierSelect.appendChild(editOption);
            }
        });
     })
     .catch(err => console.error('Failed to load suppliers:', err));
    
    // 2. Attach Event Listeners
    // Inventory Category Filter
    if (filter) {
        filter.addEventListener("change", e => fetchInventory(e.target.value));
    }

    document.querySelectorAll('.inv-summary-card').forEach(card => {
        card.addEventListener('click', () => {
            activeCardFilter = card.dataset.filter || 'all';
            document.querySelectorAll('.inv-summary-card').forEach(c => c.style.outline = '');
            card.style.outline = '2px solid #2563eb';
            applyInventoryView(filter ? filter.value : 'all');
        });
    });

    window.applyInventoryCardFilter = function (filter) {
        activeCardFilter = filter || 'all';
        document.querySelectorAll('.inv-summary-card').forEach(c => {
            c.style.outline = (c.dataset.filter === activeCardFilter) ? '2px solid #2563eb' : '';
        });
        const cat = document.getElementById('categoryFilter');
        if (allInventoryData.length) {
            applyInventoryView(cat ? cat.value : 'all');
        } else {
            fetchInventory(cat ? cat.value : 'all');
        }
    };
    
    // Drug Master Archive Filter (The one that was not working before)
    if (drugMasterFilter) {
        drugMasterFilter.addEventListener("change", fetchDrugsMaster);
    }

    // NEW EVENT LISTENER: Inventory Lot Status Filter
    if (lotStatusFilter) {
        lotStatusFilter.addEventListener("change", () => {
            // Re-fetch inventory using the current category filter AND new status filter
fetchInventory("all");
        });
    }

    // 3. Initial Data Loads
    // Note: fetchDrugsMaster must run first as it populates the lot creation dropdown.
    fetchDrugsMaster(); 
    fetchInventory();
   // --- INVENTORY SEARCH (Current Inventory / Lots table) ---
    const inventorySearch = document.getElementById("inventorySearch");
    if (inventorySearch) {
        inventorySearch.addEventListener("input", () => {
            const query = inventorySearch.value.toLowerCase();

            const filteredInventory = allInventoryData.filter(item => {
                return (
                    item.generic_name.toLowerCase().includes(query) ||
                    (item.brand_name && item.brand_name.toLowerCase().includes(query)) ||
                    item.dosage.toLowerCase().includes(query) ||
                    item.form.toLowerCase().includes(query) ||
                    item.category.toLowerCase().includes(query)
                );
            });
            loadInventory(filteredInventory, "all");
        });
    }

    // --- MASTER LIST SEARCH (Standard Drug Definitions table) ---
    const drugMasterSearch = document.getElementById("drugMasterSearch");
    if (drugMasterSearch) {
        drugMasterSearch.addEventListener("input", () => {
            const query = drugMasterSearch.value.toLowerCase();

            const filteredDrugs = allDrugsMasterData.filter(drug => {
                return (
                    drug.generic_name.toLowerCase().includes(query) ||
                    (drug.brand_name && drug.brand_name.toLowerCase().includes(query)) ||
                    drug.dosage.toLowerCase().includes(query) ||
                    drug.form.toLowerCase().includes(query) ||
                    drug.category.toLowerCase().includes(query)
                );
            });
            loadDrugsMasterTable(filteredDrugs);
        });
    }

    // --- REAL-TIME REFRESH ---
    // The inventory section is populated via fetch() calls to real DB-backed
    // endpoints already, but only ever loaded once. Poll it periodically so
    // stock counts stay live (e.g. after a cashier sale elsewhere lowers
    // quantity). Skipped while the section is hidden or the user is actively
    // typing in a search box, so we never fight with what they're doing.
    setInterval(() => {
        const inventorySection = document.getElementById('inventory');
        const isVisible = inventorySection && inventorySection.style.display !== 'none';
        if (!isVisible) return;

        const active = document.activeElement;
        const isTyping = active && (active.id === 'inventorySearch' || active.id === 'drugMasterSearch');
        if (isTyping) return;

        fetchInventory(filter ? filter.value : 'all');
        fetchDrugsMaster();
    }, 20000);
});