</div><!-- /.content -->
    </div><!-- /.main -->

    <!-- Order confirmation receipt modal (customer.js: showReceiptModal) -->
    <div id="receiptModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:1000;">
        <div style="background:#fff; border-radius:10px; padding:24px; width:360px; max-width:90vw; max-height:85vh; overflow-y:auto; position:relative;">
            <button id="closeReceiptModal" style="position:absolute; top:10px; right:14px; background:none; border:none; font-size:1.4em; cursor:pointer; color:#888;">&times;</button>
            <div id="receiptContent"></div>
        </div>
    </div>

    <!-- Order details modal (customer.js: showOrderDetails / displayOrderDetails) -->
    <div id="orderDetailsModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:1000;">
        <div style="background:#fff; border-radius:10px; padding:24px; width:400px; max-width:90vw; max-height:85vh; overflow-y:auto; position:relative;">
            <button id="closeOrderDetailsModal" style="position:absolute; top:10px; right:14px; background:none; border:none; font-size:1.4em; cursor:pointer; color:#888;">&times;</button>
            <div id="orderDetailsContent"></div>
        </div>
    </div>
</body>
</html>