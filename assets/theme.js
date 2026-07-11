/* ==========================================================================
   PHARMALINK — shared interaction layer
   Adds the same "premium" micro-interactions to every portal without
   touching any existing page logic: button ripple feedback, a global
   toast helper, and auto-dismiss for any .message/.toast the app already
   creates. Safe to include everywhere — it only ever ADDS behaviour.
   ========================================================================== */
(function () {
  "use strict";

  /* ---- 1. Ripple feedback on every clickable button ---- */
  function attachRipple(el) {
    if (el.dataset.phRippleBound) return;
    el.dataset.phRippleBound = "1";
    el.classList.add("ph-ripple-host");
    el.addEventListener("click", function (e) {
      const rect = el.getBoundingClientRect();
      const size = Math.max(rect.width, rect.height);
      const span = document.createElement("span");
      span.className = "ph-ripple";
      span.style.width = span.style.height = size + "px";
      span.style.left = (e.clientX - rect.left - size / 2) + "px";
      span.style.top = (e.clientY - rect.top - size / 2) + "px";
      el.appendChild(span);
      setTimeout(() => span.remove(), 600);
    });
  }

  function bindAllButtons(root) {
    (root || document)
      .querySelectorAll("button, .add-btn, .btn-primary, .nav-item, .pos-mode-btn")
      .forEach(attachRipple);
  }

  document.addEventListener("DOMContentLoaded", function () {
    bindAllButtons(document);
  });

  // Re-bind when the app injects new DOM (common in these SPA-style pages)
  const observer = new MutationObserver((mutations) => {
    mutations.forEach((m) => {
      m.addedNodes.forEach((node) => {
        if (node.nodeType === 1) bindAllButtons(node.parentElement || document);
      });
    });
  });
  document.addEventListener("DOMContentLoaded", function () {
    observer.observe(document.body, { childList: true, subtree: true });
  });

  /* ---- 2. Global toast helper: window.phToast('Saved!', 'success') ---- */
  window.phToast = function (text, type) {
    const el = document.createElement("div");
    el.className = "toast " + (type || "success");
    el.textContent = text;
    document.body.appendChild(el);
    setTimeout(() => {
      el.style.opacity = "0";
      el.style.transform = "translateY(10px)";
      el.style.transition = "opacity .3s, transform .3s";
      setTimeout(() => el.remove(), 300);
    }, 2600);
  };

  /* ---- 3. Auto-dismiss any legacy .message toast the app builds itself --- */
  const msgObserver = new MutationObserver((mutations) => {
    mutations.forEach((m) => {
      m.addedNodes.forEach((node) => {
        if (node.nodeType === 1 && node.classList && node.classList.contains("message")) {
          setTimeout(() => {
            node.style.opacity = "0";
            setTimeout(() => node.remove(), 300);
          }, 2600);
        }
      });
    });
  });
  document.addEventListener("DOMContentLoaded", function () {
    msgObserver.observe(document.body, { childList: true });
  });

  /* ---- 4. Staff (Admin/Cashier) notification bell ----
     Purely additive: only runs if #staffNotificationBell exists on the
     page (Admin & Cashier portals). Polls get_notifications_staff.php
     for low-stock / expiring-soon alerts. */
  document.addEventListener('DOMContentLoaded', function () {
    var bell = document.getElementById('staffNotificationBell');
    var dropdown = document.getElementById('staff-notification-dropdown');
    if (!bell || !dropdown) return;

    function iconFor(type) {
      if (type === 'low_stock') return 'fa-box-open';
      if (type === 'out_of_stock') return 'fa-circle-xmark';
      return 'fa-triangle-exclamation';
    }

    function render(notifications) {
      if (!notifications || notifications.length === 0) {
        dropdown.innerHTML = '<div class="staff-notif-empty">No new alerts. You are all caught up!</div>';
        return;
      }
      dropdown.innerHTML = notifications.map(function (n) {
        return '<div class="staff-notif-item ' + n.type + '">' +
               '<i class="fas ' + iconFor(n.type) + '"></i>' +
               '<span>' + n.message + '</span></div>';
      }).join('');
    }

    function refresh() {
      fetch('get_notifications_staff.php')
        .then(function (res) { return res.json(); })
        .then(function (data) {
          render(data.notifications);
          bell.classList.toggle('has-unread', (data.count || 0) > 0);
          var badge = bell.querySelector('.notification-badge');
          if (data.count > 0) {
            if (!badge) {
              badge = document.createElement('span');
              badge.className = 'notification-badge';
              bell.appendChild(badge);
            }
            badge.textContent = data.count > 9 ? '9+' : data.count;
          } else if (badge) {
            badge.remove();
          }
        })
        .catch(function (err) { console.error('Notification fetch error:', err); });
    }

    bell.addEventListener('click', function (e) {
      e.stopPropagation();
      dropdown.classList.toggle('open');
      if (dropdown.classList.contains('open')) refresh();
    });

    document.addEventListener('click', function (e) {
      if (!bell.contains(e.target)) dropdown.classList.remove('open');
    });

    refresh();
    setInterval(refresh, 30000);
  });
})();
