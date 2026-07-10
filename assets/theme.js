/**
 * FILE: assets/theme.js
 * DID NOT EXIST — referenced by admin.php and cashier.php's closing
 * <script src="assets/theme.js"> tag (a broken/missing src request on every
 * page load). Provides a small shared toast helper any page can call:
 *
 *   window.pharmaToast('Stock updated', 'success');
 */
(function () {
  function ensureContainer() {
    let el = document.getElementById('toast-container');
    if (!el) {
      el = document.createElement('div');
      el.id = 'toast-container';
      document.body.appendChild(el);
    }
    return el;
  }

  window.pharmaToast = function (message, type) {
    type = type || 'info';
    const container = ensureContainer();
    const toast = document.createElement('div');
    toast.className = 'toast ' + type;
    toast.textContent = message;
    container.appendChild(toast);
    setTimeout(() => toast.remove(), 3500);
  };
})();