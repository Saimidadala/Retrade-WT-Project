// Retrade JavaScript Functions

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
        alerts.forEach(function(alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);

    // Image preview for file uploads
    const imageInputs = document.querySelectorAll('input[type="file"][accept*="image"]');
    imageInputs.forEach(function(input) {
        input.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    let preview = document.getElementById('imagePreview');
                    if (!preview) {
                        preview = document.createElement('img');
                        preview.id = 'imagePreview';
                        preview.className = 'image-preview mt-2';
                        input.parentNode.appendChild(preview);
                    }
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });
    });

    // Confirm dialogs for dangerous actions
    const dangerousButtons = document.querySelectorAll('.btn-danger, .delete-btn');
    dangerousButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            const action = this.dataset.action || 'delete this item';
            if (!confirm(`Are you sure you want to ${action}? This action cannot be undone.`)) {
                e.preventDefault();
            }
        });
    });

    // Form validation
    const forms = document.querySelectorAll('.needs-validation');
    forms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
                form.classList.add('was-validated');
                return;
            }
            // Mark validated first
            form.classList.add('was-validated');

            // Apply loading state AFTER submit is allowed to proceed.
            // Using a micro-delay ensures the browser does not cancel submission
            // due to the submitter being disabled synchronously.
            const submitter = form.querySelector('button[type="submit"], input[type="submit"]');
            if (submitter) {
                const originalText = submitter.innerHTML;
                submitter.dataset.originalText = originalText;
                setTimeout(() => {
                    if (submitter.tagName.toLowerCase() === 'button') {
                        submitter.innerHTML = '<span class="loading"></span> Processing...';
                    }
                    submitter.disabled = true;
                }, 0);
                // Fallback to re-enable if still on the page after 6s (e.g., validation via AJAX)
                setTimeout(() => {
                    if (!submitter.disabled) return;
                    submitter.disabled = false;
                    if (submitter.tagName.toLowerCase() === 'button' && submitter.dataset.originalText) {
                        submitter.innerHTML = submitter.dataset.originalText;
                    }
                }, 6000);
            }
        });
    });

    // Search functionality
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const productCards = document.querySelectorAll('.product-card');
            
            productCards.forEach(function(card) {
                const title = card.querySelector('.card-title').textContent.toLowerCase();
                const description = card.querySelector('.card-text').textContent.toLowerCase();
                
                if (title.includes(searchTerm) || description.includes(searchTerm)) {
                    card.closest('.col-md-4').style.display = 'block';
                } else {
                    card.closest('.col-md-4').style.display = 'none';
                }
            });
        });
    }

    // Debounced auto-submit for the server-side search on home filters
    const serverSearch = document.getElementById('search');
    if (serverSearch) {
        let debounceTimer;
        serverSearch.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                const form = serverSearch.closest('form');
                if (form) form.submit();
            }, 450);
        });
    }

    // Copy link button on product details
    const copyBtn = document.getElementById('copyProductLink');
    if (copyBtn) {
        copyBtn.addEventListener('click', async function() {
            try {
                await navigator.clipboard.writeText(window.location.href);
                this.innerHTML = '<i class="fas fa-check"></i> Copied!';
                setTimeout(() => (this.innerHTML = '<i class="fas fa-link"></i> Copy Link'), 1500);
            } catch (e) {
                alert('Unable to copy link');
            }
        });
    }

    // Skeleton removal for card images
    document.querySelectorAll('.card-img-top[loading="lazy"]').forEach(img => {
        const wrapper = img.closest('.position-relative');
        if (wrapper) wrapper.classList.add('skeleton');
        if (img.complete) {
            if (wrapper) wrapper.classList.remove('skeleton');
            return;
        }
        img.addEventListener('load', () => {
            if (wrapper) wrapper.classList.remove('skeleton');
        });
        img.addEventListener('error', () => {
            if (wrapper) wrapper.classList.remove('skeleton');
        });
    });

    // Live price preview for seller forms
    const priceInput = document.getElementById('price');
    const pricePreview = document.getElementById('pricePreview');
    if (priceInput && pricePreview) {
        const fmt = new Intl.NumberFormat('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const updatePreview = () => {
            const val = parseFloat(priceInput.value);
            if (isNaN(val)) { pricePreview.textContent = ''; return; }
            pricePreview.textContent = 'Preview: ₹' + fmt.format(val);
        };
        priceInput.addEventListener('input', updatePreview);
        updatePreview();
    }

    // Price filter
    const priceFilter = document.getElementById('priceFilter');
    if (priceFilter) {
        priceFilter.addEventListener('change', function() {
            const maxPrice = parseFloat(this.value) || Infinity;
            const productCards = document.querySelectorAll('.product-card');
            
            productCards.forEach(function(card) {
                const priceText = card.querySelector('.product-price').textContent;
                const price = parseFloat(priceText.replace(/[^\d.]/g, ''));
                
                if (price <= maxPrice) {
                    card.closest('.col-md-4').style.display = 'block';
                } else {
                    card.closest('.col-md-4').style.display = 'none';
                }
            });
        });
    }

    // Loading states for buttons are now handled in the form submit handler above
});

// Utility functions
function formatPrice(price) {
    return '₹' + parseFloat(price).toLocaleString('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    const container = document.querySelector('.container');
    if (container) {
        container.insertBefore(alertDiv, container.firstChild);
    }
}

function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// AJAX helper function
function makeRequest(url, method = 'GET', data = null) {
    return fetch(url, {
        method: method,
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: data ? JSON.stringify(data) : null
    })
    .then(response => response.json())
    .catch(error => {
        console.error('Request failed:', error);
        showAlert('An error occurred. Please try again.', 'danger');
    });
}
