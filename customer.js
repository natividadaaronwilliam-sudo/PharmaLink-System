// FILE: customer.js (FINAL CLEAN & FIXED VERSION)

document.addEventListener('DOMContentLoaded', function() {

    // =======================================================
    // 1. GLOBAL VARIABLES & CONSTANTS
    // =======================================================
    const cart = {};
    
    // ⭐ FIXED: Tiyakin na defined ang CUSTOMER_ID mula sa PHP
    let selectedCustomer = typeof CUSTOMER_ID !== 'undefined' ? CUSTOMER_ID : null; 
    
    // --- Element Selectors ---
    const navItems = document.querySelectorAll('.nav-item');
    const sections = document.querySelectorAll('section');
    const productGrid = document.getElementById('productGrid');
    const cartPanel = document.getElementById('cart-panel');
    const cartItemsTableBody = document.getElementById('cart-items');
    const ordersContent = document.getElementById('orders'); 
    
    // Summary Spans
    const totalPriceSpan = document.getElementById('total-price');
    const subtotalDisplay = document.getElementById('subtotalDisplay');
    const checkoutBtn = document.getElementById('checkout-btn');
    const clearCartBtn = document.getElementById('clear-cart-btn');
    const emptyCartMessage = document.getElementById('emptyCartMessage');

    // Modals
    const receiptModal = document.getElementById('receiptModal');
    const receiptContent = document.getElementById('receiptContent');
    const closeReceiptModal = document.getElementById('closeReceiptModal');

    // ⭐ ORDER DETAILS MODAL ELEMENTS ⭐
    const orderDetailsModal = document.getElementById('orderDetailsModal');
    const orderDetailsContent = document.getElementById('orderDetailsContent');
    const closeOrderDetailsModal = document.getElementById('closeOrderDetailsModal'); 
    
    // Search & Filter
    const searchInput = document.getElementById('searchInput');
    const categoryFilter = document.getElementById('categoryFilter');

   // notif  
    const notificationBell = document.getElementById('customerNotificationBell') || document.querySelector('.notification');
    const notificationDropdown = document.getElementById('notification-dropdown');    
    
    
    // --- Initial Safety Check ---
    if (!productGrid || !cartItemsTableBody || !totalPriceSpan || !subtotalDisplay || !cartPanel || !ordersContent) {
        console.error("FATAL ERROR: One or more critical elements are missing.");
    }

    // --- Initial Event Listeners ---
    if (searchInput) {
        searchInput.addEventListener('input', applyProductFilters);
    }
    if (categoryFilter) {
        categoryFilter.addEventListener('change', applyProductFilters);
    }
    if (closeReceiptModal) {
        closeReceiptModal.addEventListener('click', () => {
            receiptModal.style.display = 'none';
        });
    }
    // ⭐ Close Listener para sa Order Details Modal
    if (closeOrderDetailsModal) {
        closeOrderDetailsModal.addEventListener('click', () => {
            if (orderDetailsModal) {
                orderDetailsModal.style.display = 'none';
            }
        });
    }


    // =======================================================
    // 2. NAVIGATION LOGIC & ORDER RELOAD
    // =======================================================
    navItems.forEach(item => {
        item.addEventListener('click', () => {
            navItems.forEach(i => i.classList.remove('active'));
            item.classList.add('active');
            const target = item.getAttribute('data-target');
            sections.forEach(sec => sec.classList.remove('active'));
            const targetSection = document.getElementById(target);

            if (targetSection) {
                targetSection.classList.add('active');
            }

            // Show/Hide Cart Panel based on the active section
            if (target === 'products') {
                cartPanel.style.display = 'block';
                updateCartPanel();
            } else {
                cartPanel.style.display = 'none';
            }
            
            // NEW: Load orders when the 'orders' tab is clicked
if (target === 'orders') {
    const type = item.getAttribute('data-order-type') || 'online';
    window.loadCustomerOrders(type);
}

        });
    });

    // Real-time refresh for My Orders while that tab is active.
    setInterval(() => {
        const ordersSection = document.getElementById('orders');
        if (ordersSection && ordersSection.classList.contains('active')) {
            const activeOrderTab = document.querySelector('.nav-item[data-target="orders"]');
            const type = activeOrderTab ? (activeOrderTab.getAttribute('data-order-type') || 'online') : 'online';
            window.loadCustomerOrders(type);
        }
    }, 20000);

    /**
     * Fetches and loads the customer's orders into the 'orders' section.
     */
/**
     * Fetches and loads the customer's orders into the 'orders' section, optionally with date filters.
     * Ginawang window.function para ma-access ng <script> tag sa PHP output.
     * @param {string} [startDate=''] - Start date filter (YYYY-MM-DD).
     * @param {string} [endDate=''] - End date filter (YYYY-MM-DD).
     */
window.loadCustomerOrders = function(type = 'online', startDate = '', endDate = '') {
    if (!ordersContent) return;

    ordersContent.innerHTML = '<p style="text-align:center; padding: 20px;">Loading your orders... ⌛</p>';

    // Decide which PHP file to fetch from
    let fetchUrl = (type === 'online')
        ? `includes/customer_contentorders.php?customer_id=${selectedCustomer}&type=online`
        : `includes/customer_contentorders.php?customer_id=${selectedCustomer}&type=walkin`;

    // Add date filters
    if (startDate) fetchUrl += `&start_date=${startDate}`;
    if (endDate) fetchUrl += `&end_date=${endDate}`;

    fetch(fetchUrl)
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.text();
        })
        .then(html => {
            ordersContent.innerHTML = html; // Render table
            setupOrderFilterListener();      // Keep your filters working
        })
        .catch(error => {
            console.error('Error loading orders:', error);
            ordersContent.innerHTML = '<p style="text-align:center; color:red; padding: 20px;">Failed to load orders. Please try again.</p>';
        });
};


    // =======================================================
    // ⭐ BAGONG FUNCTION: I-fetch at Ipakita ang Order Details
    // =======================================================

    // Ginawang global function (window.) para magamit ng 'onclick' sa PHP file.
    window.showOrderDetails = function(orderId) {
        if (!orderDetailsModal || !orderDetailsContent) return;

        orderDetailsContent.innerHTML = '<p style="text-align:center;">Loading order details... ⏳</p>';
        orderDetailsModal.style.display = 'flex'; // Ipakita ang modal habang naglo-load

        fetch(`get_order_details_.php?order_id=${orderId}`) 
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    displayOrderDetails(data); // Tumawag sa helper function
                } else {
                    orderDetailsContent.innerHTML = `<p style="text-align:center; color:red;">Error: ${data.message}</p>`;
                }
            })
            .catch(error => {
                console.error('Error fetching order details:', error);
                orderDetailsContent.innerHTML = `<p style="text-align:center; color:red;">Failed to load order details: ${error.message}</p>`;
            });
    }

    function loadContent(sectionId) {
        // Reuse the real nav-item click handler so this stays in sync with
        // however navigation actually works (section .active class), instead
        // of toggling a '.page' class that doesn't exist in this portal.
        const navLink = document.querySelector(`.nav-item[data-target="${sectionId}"]`);
        if (navLink) {
            navLink.click();
            return;
        }

        const targetSection = document.getElementById(sectionId);
        if (targetSection) {
            sections.forEach(sec => sec.classList.remove('active'));
            targetSection.classList.add('active');
            targetSection.scrollIntoView({ behavior: 'smooth' });
        } else {
            console.error('Target section not found with ID:', sectionId);
        }
    }
    // Exposed globally because it's called from inline onclick="" handlers
    // rendered by PHP (e.g. the Home page's "Shop Now" / "View Orders" buttons).
    window.loadContent = loadContent;

    // ⭐ BAGONG FUNCTION: I-render ang Order Details sa Modal
function displayOrderDetails(data) {
    let itemsHtml = data.items.map(item => {
        // ⭐ BAGONG LOGIC DITO: Gumawa ng mas kumpletong string.
        const brandName = item.brand_name ? item.brand_name.trim() : '';
        const genericInfo = (item.generic_name && item.dosage) 
            ? `${item.generic_name.trim()} (${item.dosage.trim()})` 
            : (item.generic_name ? item.generic_name.trim() : 'N/A');

        // Pagsamahin: (Brand Name) [Generic Name (Dosage)]
        let itemName;
        if (brandName && brandName !== genericInfo) {
            itemName = `${brandName} / ${genericInfo}`;
        } else {
            itemName = genericInfo;
        }

        const itemTotal = item.ordered_qty * item.price_per_unit;
        
        // ... (Ang natitirang HTML ay unchanged)
    
        return `
            <tr>
                <td style="padding: 4px 0; width: 60%;">${item.ordered_qty}x <strong>${itemName}</strong></td>
                <td style="padding: 4px 0; width: 40%; text-align: right;">₱${itemTotal.toFixed(2)}</td>
            </tr>
        `;
    }).join('');

        const calculatedTotal = data.items.reduce((sum, item) => sum + (item.ordered_qty * item.price_per_unit), 0);
        const statusColor = getStatusColor(data.status);

        orderDetailsContent.innerHTML = `
            <h4 style="color:#007bff; text-align: center; margin-bottom: 20px;">Order Details (#${data.order_id})</h4>
            <div style="font-size: 0.9em;">
                <p><strong>Customer:</strong> ${data.customer_name}</p>
                <p><strong>Status:</strong> <span style="font-weight: bold; color: ${statusColor};">${data.status}</span></p>
                <hr style="border-top: 1px dashed #ccc; margin: 15px 0;">
                
                <h5 style="margin-top: 0; font-weight: bold;">Ordered Items:</h5>
                <table style="width: 100%; border-collapse: collapse; font-size: 0.9em;">
                    <thead>
                        <tr style="border-bottom: 1px solid #ddd;">
                            <th style="padding: 5px 0; text-align: left;">Item (Quantity)</th>
                            <th style="padding: 5px 0; text-align: right;">Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${itemsHtml}
                    </tbody>
                </table>

                <hr style="border-top: 1px dashed #ccc; margin: 15px 0;">

                <p style="display: flex; justify-content: space-between; font-size: 1.1em; color: #333;">
                    **TOTAL AMOUNT:** <span style="font-weight: bold;">₱${calculatedTotal.toFixed(2)}</span>
                </p>
                
                <p style="font-size: 0.9em; color: #555; text-align: center; margin-top: 10px;">
                    *Payment and change will be finalized upon pickup.*
                </p>
            </div>
        `;
    }

    // Helper function para sa kulay ng status
    function getStatusColor(status) {
        status = status.toLowerCase();
        if (status.includes('pending')) return '#ffc107';
        if (status.includes('processing')) return '#17a2b8';
        if (status.includes('ready')) return '#28a745';
        if (status.includes('completed')) return '#0069d9';
        if (status.includes('canceled')) return '#dc3545';
        return '#6c757d';
    }
    
    // =======================================================
    // 3. CART MANIPULATION & STOCK LOGIC (UNCHANGED)
    // =======================================================

    function addToCart(button) {
        const lotId = button.getAttribute('data-lot-id');
        const name = button.getAttribute('data-name');
        const price = parseFloat(button.getAttribute('data-price'));
        const drugId = button.getAttribute('data-drug-id');
        const maxStock = parseInt(button.getAttribute('data-stock'));
        let currentInCart = cart[lotId] ? cart[lotId].qty : 0;

        if (currentInCart >= maxStock) {
             alert(`❌ You have reached the maximum available stock (${maxStock}) for ${name}.`);
             return;
        }
        if(cart[lotId]) {
            cart[lotId].qty += 1;
        } else {
            cart[lotId] = { name: name, price: price, qty: 1, drug_id: drugId, max_stock: maxStock, lot_id: lotId };
        }
        updateCartPanel();
    }

    window.updateCartQty = function(inputElement, delta = 0) {
        const lotId = inputElement.dataset.lotId;
        const item = cart[lotId];
        if (!item) { updateCartPanel(); return; }

        let newQty = (delta !== 0) ? item.qty + delta : parseInt(inputElement.value);
        if (isNaN(newQty) || newQty < 1) newQty = 1;
        if (newQty > item.max_stock) {
            alert("⚠️ Cannot exceed max stock of " + item.max_stock);
            newQty = item.max_stock;
        }
        if (newQty === 0) { removeItem(lotId); return; }

        item.qty = newQty;
        inputElement.value = newQty;
        updateCartPanel();
    };

    window.removeItem = function(lotId) {
        const addBtn = document.querySelector(`.add-btn[data-lot-id="${lotId}"]`);
        if (addBtn) { addBtn.disabled = false; addBtn.style.opacity = 1; }
        delete cart[lotId];
        updateCartPanel();
    }

    function clearCart() {
        if (Object.keys(cart).length === 0) { alert("Cart is already empty."); return; }
        for (let lotId in cart) {
            const addBtn = document.querySelector(`.add-btn[data-lot-id="${lotId}"]`);
            if (addBtn) { addBtn.disabled = false; addBtn.style.opacity = 1; }
            delete cart[lotId];
        }
        updateCartPanel();
        alert("Cart has been cleared.");
    }

    function updateCartPanel() {
        cartItemsTableBody.innerHTML = '';
        let totalItems = 0;
        let finalTotal = 0;

        if (Object.keys(cart).length === 0) {
            emptyCartMessage.style.display = 'block';
        } else {
            emptyCartMessage.style.display = 'none';
        }

        for (let lotId in cart) {
            const item = cart[lotId];
            const itemTotal = item.price * item.qty;
            finalTotal += itemTotal;
            totalItems += item.qty;

            const tr = document.createElement('tr');
            tr.innerHTML = `
                 <td style="font-size: 0.9em; padding: 8px 0;">
                    ${item.name}
                    <span style="color:#666; font-size:0.85em;">(₱${item.price.toFixed(2)})</span>
                </td>

                <td style="padding: 8px 0;">
                    <div style="display: flex; justify-content: center; align-items: center; gap: 5px;">
                        <button class="qty-control-btn qty-minus" onclick="updateCartQty(this.closest('tr').querySelector('.qty-input'), -1)">−</button>
                        <input type="number" class="qty-input" min="1" value="${item.qty}" data-lot-id="${lotId}" onchange="updateCartQty(this)" style="width: 45px; text-align: center; border: 1px solid #ccc; border-radius: 4px;">
                        <button class="qty-control-btn qty-plus" onclick="updateCartQty(this.closest('tr').querySelector('.qty-input'), 1)" ${item.qty >= item.max_stock ? 'disabled style="opacity:0.5;"' : ''}>+</button>
                    </div>
                </td>

                <td style="font-weight: bold; text-align: right; padding: 8px 0;">₱${itemTotal.toFixed(2)}</td>

                <td style="padding: 8px 0;">
                    <button class="cart-remove-btn" onclick="removeItem('${lotId}')" style="background: none; border: none; color: #e74c3c; cursor: pointer; padding: 0; font-size: 1.2em;">&times;</button>
                </td>
            `;

            cartItemsTableBody.appendChild(tr);

            const addBtn = document.querySelector(`.add-btn[data-lot-id="${lotId}"]`);
            if (addBtn) {
                addBtn.disabled = (item.qty >= item.max_stock);
                addBtn.style.opacity = (item.qty >= item.max_stock) ? 0.5 : 1;
            }
        }

        const subtotalValue = finalTotal;
        if (subtotalDisplay) subtotalDisplay.innerText = subtotalValue.toFixed(2);
        if (totalPriceSpan) totalPriceSpan.innerText = finalTotal.toFixed(2);

        if (checkoutBtn) {
            checkoutBtn.disabled = totalItems === 0;
            checkoutBtn.innerText = totalItems > 0 ? `Submit Order for Pickup (₱${finalTotal.toFixed(2)})` : 'Submit Order for Pickup';
        }

        return { finalTotal };
    }


    /// =======================================================
    // 4. ORDER SUBMISSION (FIXED for Timeout Errors)
    // =======================================================

    function submitOrder() {
        const { finalTotal } = updateCartPanel();
        const totalItems = Object.keys(cart).reduce((sum, key) => sum + cart[key].qty, 0);

        if (totalItems === 0) {
            alert('Your cart is empty!');
            return;
        }

        const orderData = {
            customer_id: selectedCustomer,
            total_amount: finalTotal,
            sc_pwd_applied: false,
            // Ginagamit ang GLOBAL_UNIQUE_TOKEN_FROM_PHP_SESSION
            order_token: typeof GLOBAL_UNIQUE_TOKEN_FROM_PHP_SESSION !== 'undefined' ? GLOBAL_UNIQUE_TOKEN_FROM_PHP_SESSION : 'no_token',
            items: Object.values(cart).map(item => ({
                lot_id: item.lot_id,
                drug_id: item.drug_id,
                name: item.name, 
                quantity: item.qty,
                price_per_unit: item.price
            }))
        };

        if (checkoutBtn) {
            checkoutBtn.disabled = true;
            checkoutBtn.innerText = 'Processing...';
        }

        fetch('process_customer_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(orderData),
        })
        .then(response => {
            // Re-enable button early to prevent it from staying disabled if .json() fails
            if (checkoutBtn) checkoutBtn.disabled = false;
            
            if (response.status === 409) {
                 return response.json(); 
            }
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // SUCCESS PATH
                showReceiptModal(data.order_id, finalTotal, orderData.items);
                loadCustomerOrders(); // Force reload ng orders tab
                
                // Clear the cart
                for (let lotId in cart) delete cart[lotId];
                updateCartPanel();

            } else {
                // FAILURE PATH
                alert(`❌ Order submission failed: ${data.message}`);
                console.error(data);
            }
        })
        .catch(error => {
            // CRITICAL FIX: a network error/timeout here does NOT mean the
            // order was saved. The previous version of this code assumed
            // success, deleted every item from `cart`, and showed a fake
            // receipt — that's why items appeared to vanish right after
            // checkout even though nothing had actually been ordered.
            //
            // The cart is now left untouched on failure. The customer can
            // safely click "Submit Order for Pickup" again: process_customer_order.php
            // is idempotent per order_token, so even if the first request
            // actually did reach the server, retrying will NOT create a
            // duplicate order or double-deduct stock — it just returns the
            // original order.
            console.error('Network Error or Timeout during checkout:', error);
            alert('⚠️ Could not reach the server to place your order. Your cart has been kept — please check your connection and try again.');
        })
        .finally(() => {
            if (checkoutBtn) {
                checkoutBtn.disabled = false;
                checkoutBtn.innerText = 'Submit Order for Pickup';
            }
            updateCartPanel();
        });
    }

    function showReceiptModal(orderId, finalTotal, items) {
        
        if (!receiptContent || !receiptModal) {
            console.error("receiptModal or receiptContent element is missing.");
            return;
        }

        receiptContent.innerHTML = `
            <h5 style="color:#007bff; text-align: center;">🧾 Order Confirmation</h5>
            <p style="text-align: center; margin-bottom: 20px;">
                <strong>Order ID:</strong> #${orderId}<br>
                <strong>Date:</strong> ${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString()}
            </p>
            <hr style="border-top: 1px dashed #ccc;">
            
            <h5 style="margin-top: 15px; font-weight: bold;">Order Details:</h5>
            
            <table style="width: 100%; border-collapse: collapse; font-size: 0.9em; margin-bottom: 15px;">
                <thead>
                    <tr style="border-bottom: 2px solid #ddd;">
                        <th style="padding: 5px 0; text-align: left;">Item</th>
                        <th style="padding: 5px 0; text-align: right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                ${items.map(item => {
                    const itemName = item.name; 
                    const itemTotalPrice = item.quantity * item.price_per_unit;
                    return `
                        <tr>
                            <td style="padding: 2px 0; width: 70%;">${item.quantity}x ${itemName}</td>
                            <td style="padding: 2px 0; width: 30%; text-align: right;">₱${itemTotalPrice.toFixed(2)}</td>
                        </tr>
                    `;
                }).join('')}
                </tbody>
            </table>
            
            <div style="margin-top: 20px; border-top: 1px dashed #ccc; padding-top: 10px;">
                <p style="display: flex; justify-content: space-between; font-size: 1.1em; color: #333;">
                    Subtotal: <span>₱${finalTotal.toFixed(2)}</span>
                </p>
                <h5 style="display: flex; justify-content: space-between; font-size: 1.3em; color:#28a745; margin: 5px 0 0;">
                    TOTAL AMOUNT: <span>₱${finalTotal.toFixed(2)}</span>
                </h5>
            </div>
            
            <hr style="border-top: 1px dashed #ccc; margin-top: 20px;">
            
            <p style="margin-top: 15px; font-weight: bold; color: orange; text-align: center;">Current Status: Pending ⏱️</p>
            <p style="font-size: 0.9em; text-align: center;">We will notify you when your order is **'Ready for Pickup'**.</p>
        `;
        
        receiptModal.style.display = 'flex';
    }


    // =======================================================
    // ⭐ BAGONG FUNCTION: Setup Filter Listener
    // =======================================================
    function setupOrderFilterListener() {
        const applyBtn = document.getElementById('apply_order_filter_btn');
        const startDateInput = document.getElementById('order_start_date');
        const endDateInput = document.getElementById('order_end_date');

        if (applyBtn && startDateInput && endDateInput) {
            applyBtn.addEventListener('click', function() {
                const start = startDateInput.value;
                const end = endDateInput.value;
                
                // Tawagin ang na-update na loadCustomerOrders function
                window.loadCustomerOrders('online', start, end);
            });
        }
    }

    // =======================================================
    // 5. EVENT LISTENERS & FILTERS (UNCHANGED)
    // =======================================================
    function applyProductFilters() {
        if (!searchInput || !categoryFilter || !productGrid) return;
        const search = searchInput.value.toLowerCase().trim();
        const selectedCategory = categoryFilter.value;
        const products = productGrid.querySelectorAll('.product');

        products.forEach(product => {
            const productCategory = product.getAttribute('data-category') || '';
            const productNameSearch = product.getAttribute('data-name-search') ? product.getAttribute('data-name-search').toLowerCase() : '';

            const matchesCategory = selectedCategory === '' || productCategory === selectedCategory;
            const matchesSearch = search === '' || productNameSearch.includes(search);

            if (matchesSearch && matchesCategory) {
                product.style.display = 'block';
            } else {
                product.style.display = 'none';
            }
        });
    }

    if (productGrid) {
        productGrid.addEventListener('click', function(e) {
            const addButton = e.target.closest('.add-btn');
            if (addButton) {
                e.preventDefault();
                addToCart(addButton);
            }
        });
    }

    if (checkoutBtn) checkoutBtn.addEventListener('click', submitOrder);
    if (clearCartBtn) clearCartBtn.addEventListener('click', clearCart);

    // Initial Load
    updateCartPanel();

    // FILE: customer.js (Sa dulo ng DOMContentLoaded block)

// =======================================================
// ⭐ 6. NOTIFICATION POLLING LOGIC ⭐
// =======================================================

// Counter para sa notification badge
let notificationCount = 0;

function updateNotificationBadge(count) {
    if (!notificationBell) return;

    notificationCount = count || 0;
    notificationBell.classList.toggle('has-unread', notificationCount > 0);

    let badge = notificationBell.querySelector('.notification-badge');
    if (notificationCount > 0) {
        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'notification-badge';
            notificationBell.appendChild(badge);
        }
        badge.textContent = notificationCount > 9 ? '9+' : notificationCount;
    } else if (badge) {
        badge.remove();
    }
}

function renderNotificationDropdown(notifications) {
    if (!notificationDropdown || !notificationDropdown.classList.contains('open')) return;

    if (!notifications || notifications.length === 0) {
        notificationDropdown.innerHTML = '<div class="staff-notif-empty">No new alerts. You are all caught up!</div>';
        return;
    }

    notificationDropdown.innerHTML = notifications.map(notif => `
        <div class="staff-notif-item order_update"
             data-order-id="${notif.order_id}"
             role="button"
             tabindex="0"
             style="cursor:pointer;">
            <i class="fas fa-receipt"></i>
            <span>${notif.message}<br><small style="color:#9ca3af;">${notif.date}</small></span>
        </div>
    `).join('');

    notificationDropdown.querySelectorAll('.staff-notif-item[data-order-id]').forEach(item => {
        item.addEventListener('click', (e) => {
            e.stopPropagation();
            const orderId = item.dataset.orderId;
            window.markNotificationRead(orderId);
            window.showOrderDetails(orderId);
            notificationDropdown.classList.remove('open');
        });

        item.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                item.click();
            }
        });
    });
}

function checkNotifications() {
    if (selectedCustomer === null || !notificationBell || !notificationDropdown) return;

    fetch('get_notifications.php')
        .then(response => {
            if (!response.ok) throw new Error('Failed to fetch notifications.');
            return response.json();
        })
        .then(data => {
            updateNotificationBadge(data.count || 0);
            renderNotificationDropdown(data.notifications || []);
        })
        .catch(error => {
            console.error('Notification Polling Error:', error);
        });
}

window.markNotificationRead = function(orderId) {
    fetch(`includes/mark_read.php?order_id=${orderId}`, { method: 'POST' })
        .then(response => response.json())
        .then(data => {
            if (data && data.success) {
                notificationCount = Math.max(0, notificationCount - 1);
                updateNotificationBadge(notificationCount);
                checkNotifications();
            }
        })
        .catch(error => {
            console.error('Failed to mark as read:', error);
        });
};

if (notificationBell && notificationDropdown) {
    notificationDropdown.addEventListener('click', (e) => e.stopPropagation());

    notificationBell.addEventListener('click', (e) => {
        e.stopPropagation();
        notificationDropdown.classList.toggle('open');
        if (notificationDropdown.classList.contains('open')) {
            notificationDropdown.innerHTML = '<div class="staff-notif-empty">Loading...</div>';
            checkNotifications();
        }
    });

    document.addEventListener('click', (e) => {
        if (!notificationBell.contains(e.target) && !notificationDropdown.contains(e.target)) {
            notificationDropdown.classList.remove('open');
        }
    });

    setInterval(checkNotifications, 30000);
    checkNotifications();
}

// =======================================================
// LIVE CLOCK ("Server Date & Time" widget, same as Admin/Cashier)
// =======================================================
const liveClockEl = document.getElementById('live-clock');
if (liveClockEl) {
    const tickClock = () => {
        const now = new Date();
        liveClockEl.textContent = now.toLocaleString('en-PH', {
            weekday: 'long', year: 'numeric', month: 'short', day: 'numeric',
            hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true
        });
    };
    tickClock();
    setInterval(tickClock, 1000);
}
});