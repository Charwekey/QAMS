/**
 * QAMS – Main JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {

    // ── Sidebar toggle (mobile) ──────────────────────────
    const menuToggle = document.querySelector('.menu-toggle');
    const sidebar = document.getElementById('sidebar');

    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 1024 &&
                sidebar.classList.contains('open') &&
                !sidebar.contains(e.target) &&
                !menuToggle.contains(e.target)) {
                sidebar.classList.remove('open');
            }
        });
    }

    // ── Auto-dismiss flash messages ──────────────────────
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(function() { alert.remove(); }, 400);
        }, 5000);
    });

    // ── File upload label update ─────────────────────────
    const fileInputs = document.querySelectorAll('.file-upload input[type="file"]');
    fileInputs.forEach(function(input) {
        input.addEventListener('change', function() {
            const wrapper = this.closest('.file-upload');
            const label = wrapper.querySelector('p');
            if (this.files.length > 0) {
                label.textContent = this.files[0].name;
                wrapper.style.borderColor = '#22c55e';
                wrapper.style.background = '#dcfce7';
            }
        });
    });

    // ── Form validation ─────────────────────────────────
    const forms = document.querySelectorAll('form[data-validate]');
    forms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            let valid = true;
            const required = form.querySelectorAll('[required]');
            required.forEach(function(field) {
                if (!field.value.trim()) {
                    valid = false;
                    field.style.borderColor = '#ef4444';
                    field.addEventListener('input', function() {
                        this.style.borderColor = '';
                    }, { once: true });
                }
            });
            if (!valid) {
                e.preventDefault();
                showToast('Please fill in all required fields.', 'error');
            }
        });
    });

    // ── Confirm actions ─────────────────────────────────
    document.addEventListener('click', function(e) {
        const confirmBtn = e.target.closest('[data-confirm]');
        if (confirmBtn) {
            const msg = confirmBtn.getAttribute('data-confirm');
            if (!confirm(msg)) {
                e.preventDefault();
            }
        }
    });

    // ── Modal handling ──────────────────────────────────
    document.addEventListener('click', function(e) {
        // Open modal
        const trigger = e.target.closest('[data-modal]');
        if (trigger) {
            const modal = document.getElementById(trigger.getAttribute('data-modal'));
            if (modal) modal.classList.add('active');
        }
        // Close modal
        if (e.target.classList.contains('modal-overlay') || e.target.classList.contains('modal-close')) {
            const overlay = e.target.closest('.modal-overlay') || e.target;
            overlay.classList.remove('active');
        }
    });
});

// ── Toast notification ──────────────────────────────────
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = 'alert alert-' + type;
    toast.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;min-width:300px;max-width:450px;animation:slideDown 0.3s ease;';
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(function() {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(-10px)';
        setTimeout(function() { toast.remove(); }, 400);
    }, 4000);
}
