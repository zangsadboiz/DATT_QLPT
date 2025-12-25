/**
 * Admin Custom JavaScript
 * Quản lý phòng trọ - Interactive Features
 */

(function() {
  'use strict';

  // ===== DOM Ready =====
  document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
  });

  function initializeApp() {
    setupAutoHideAlerts();
    setupConfirmDialogs();
    setupImagePreviews();
    setupFormValidation();
    setupTableFeatures();
    setupTooltips();
  }

  // ===== Auto-hide Alerts =====
  function setupAutoHideAlerts() {
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    alerts.forEach(alert => {
      setTimeout(() => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
      }, 5000);
    });
  }

  // ===== Confirmation Dialogs =====
  function setupConfirmDialogs() {
    // Delete confirmations
    document.querySelectorAll('[data-confirm-delete]').forEach(el => {
      el.addEventListener('click', function(e) {
        const message = this.getAttribute('data-confirm-delete') || 
                       'Bạn có chắc chắn muốn xóa mục này?';
        if (!confirm(message)) {
          e.preventDefault();
          return false;
        }
      });
    });

    // General confirmations
    document.querySelectorAll('[data-confirm]').forEach(el => {
      el.addEventListener('click', function(e) {
        const message = this.getAttribute('data-confirm') || 
                       'Bạn có chắc chắn muốn thực hiện hành động này?';
        if (!confirm(message)) {
          e.preventDefault();
          return false;
        }
      });
    });
  }

  // ===== Image Preview Before Upload =====
  function setupImagePreviews() {
    document.querySelectorAll('input[type="file"][accept*="image"]').forEach(input => {
      input.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;

        // Find or create preview element
        let previewId = input.getAttribute('data-preview');
        let preview = previewId ? document.getElementById(previewId) : null;

        if (!preview) {
          // Create preview if not exists
          preview = document.createElement('img');
          preview.className = 'img-preview mt-2';
          preview.style.maxWidth = '200px';
          preview.style.maxHeight = '200px';
          input.parentElement.appendChild(preview);
        }

        // Show preview
        const reader = new FileReader();
        reader.onload = function(e) {
          preview.src = e.target.result;
          preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
      });
    });
  }

  // ===== Form Validation =====
  function setupFormValidation() {
    // Bootstrap form validation
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
      form.addEventListener('submit', function(e) {
        if (!form.checkValidity()) {
          e.preventDefault();
          e.stopPropagation();
        }
        form.classList.add('was-validated');
      }, false);
    });

    // Date validation
    const checkInInputs = document.querySelectorAll('input[name="check_in"]');
    const checkOutInputs = document.querySelectorAll('input[name="check_out"]');

    checkInInputs.forEach((checkIn, index) => {
      const checkOut = checkOutInputs[index];
      if (!checkOut) return;

      checkIn.addEventListener('change', function() {
        checkOut.min = this.value;
        if (checkOut.value && checkOut.value <= this.value) {
          checkOut.value = '';
        }
      });

      checkOut.addEventListener('change', function() {
        if (checkIn.value && this.value <= checkIn.value) {
          alert('Ngày trả phòng phải sau ngày nhận phòng');
          this.value = '';
        }
      });
    });
  }

  // ===== Table Features =====
  function setupTableFeatures() {
    // Row click to view details
    document.querySelectorAll('[data-row-link]').forEach(row => {
      row.style.cursor = 'pointer';
      row.addEventListener('click', function(e) {
        // Don't trigger if clicking on buttons or links
        if (e.target.tagName === 'A' || e.target.tagName === 'BUTTON' || 
            e.target.closest('a') || e.target.closest('button')) {
          return;
        }
        const url = this.getAttribute('data-row-link');
        if (url) {
          window.location.href = url;
        }
      });
    });

    // Select all checkbox
    const selectAllCheckbox = document.querySelector('#selectAll');
    if (selectAllCheckbox) {
      selectAllCheckbox.addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.row-checkbox');
        checkboxes.forEach(cb => cb.checked = this.checked);
      });
    }

    // Individual checkboxes
    document.querySelectorAll('.row-checkbox').forEach(checkbox => {
      checkbox.addEventListener('change', function() {
        const allCheckboxes = document.querySelectorAll('.row-checkbox');
        const selectAll = document.querySelector('#selectAll');
        if (selectAll) {
          selectAll.checked = Array.from(allCheckboxes).every(cb => cb.checked);
        }
      });
    });
  }

  // ===== Tooltips =====
  function setupTooltips() {
    const tooltipTriggerList = [].slice.call(
      document.querySelectorAll('[data-bs-toggle="tooltip"]')
    );
    tooltipTriggerList.map(function(tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl);
    });
  }

  // ===== Loading Overlay =====
  window.showLoading = function(message = 'Đang xử lý...') {
    const overlay = document.createElement('div');
    overlay.className = 'spinner-overlay';
    overlay.id = 'loadingOverlay';
    overlay.innerHTML = `
      <div class="text-center">
        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
          <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-3 text-muted">${message}</p>
      </div>
    `;
    document.body.appendChild(overlay);
  };

  window.hideLoading = function() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
      overlay.remove();
    }
  };

  // ===== Toast Notifications =====
  window.showToast = function(message, type = 'success') {
    const toastContainer = document.getElementById('toastContainer') || createToastContainer();
    
    const toastId = 'toast-' + Date.now();
    const bgClass = {
      'success': 'bg-success',
      'error': 'bg-danger',
      'warning': 'bg-warning',
      'info': 'bg-info'
    }[type] || 'bg-success';

    const icon = {
      'success': 'check-circle',
      'error': 'exclamation-circle',
      'warning': 'exclamation-triangle',
      'info': 'info-circle'
    }[type] || 'check-circle';

    const toastHTML = `
      <div id="${toastId}" class="toast align-items-center text-white ${bgClass} border-0" role="alert">
        <div class="d-flex">
          <div class="toast-body">
            <i class="bi bi-${icon} me-2"></i>
            ${message}
          </div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
      </div>
    `;

    toastContainer.insertAdjacentHTML('beforeend', toastHTML);
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, { delay: 3000 });
    toast.show();

    toastElement.addEventListener('hidden.bs.toast', function() {
      toastElement.remove();
    });
  };

  function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
    container.style.zIndex = '9999';
    document.body.appendChild(container);
    return container;
  }

  // ===== AJAX Form Submit =====
  window.submitFormAjax = function(formId, successCallback) {
    const form = document.getElementById(formId);
    if (!form) return;

    form.addEventListener('submit', function(e) {
      e.preventDefault();
      
      if (!form.checkValidity()) {
        form.classList.add('was-validated');
        return;
      }

      showLoading('Đang gửi dữ liệu...');

      const formData = new FormData(form);
      const action = form.getAttribute('action') || window.location.href;

      fetch(action, {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        hideLoading();
        if (data.success) {
          showToast(data.message || 'Thành công!', 'success');
          if (successCallback) successCallback(data);
        } else {
          showToast(data.message || 'Có lỗi xảy ra!', 'error');
        }
      })
      .catch(error => {
        hideLoading();
        showToast('Lỗi kết nối server!', 'error');
        console.error('Error:', error);
      });
    });
  };

  // ===== Number Formatting =====
  window.formatNumber = function(num) {
    return new Intl.NumberFormat('vi-VN').format(num);
  };

  window.formatCurrency = function(num) {
    return new Intl.NumberFormat('vi-VN', {
      style: 'currency',
      currency: 'VND'
    }).format(num);
  };

  // ===== Date Formatting =====
  window.formatDate = function(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('vi-VN');
  };

  // ===== Debounce Function =====
  window.debounce = function(func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  };

  // ===== Search with Debounce =====
  const searchInputs = document.querySelectorAll('[data-search]');
  searchInputs.forEach(input => {
    input.addEventListener('input', debounce(function(e) {
      const searchTerm = e.target.value.toLowerCase();
      const targetTable = document.querySelector(input.getAttribute('data-search'));
      
      if (!targetTable) return;

      const rows = targetTable.querySelectorAll('tbody tr');
      rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
      });
    }, 300));
  });

  // ===== Back to Top Button =====
  const backToTopButton = document.querySelector('.back-to-top');
  if (backToTopButton) {
    window.addEventListener('scroll', function() {
      if (window.pageYOffset > 300) {
        backToTopButton.classList.add('active');
      } else {
        backToTopButton.classList.remove('active');
      }
    });
  }

  // ===== Console Info =====
  console.log('%cQuản lý phòng trọ - Admin Portal', 'color: #4154f1; font-size: 18px; font-weight: bold;');
  console.log('%cVersion: 2.0', 'color: #6c757d;');

})();

// ===== Global Utility Functions =====

/**
 * Copy text to clipboard
 */
function copyToClipboard(text) {
  navigator.clipboard.writeText(text).then(function() {
    showToast('Đã sao chép vào clipboard!', 'success');
  }, function(err) {
    showToast('Không thể sao chép!', 'error');
  });
}

/**
 * Print element content
 */
function printElement(elementId) {
  const element = document.getElementById(elementId);
  if (!element) return;

  const printWindow = window.open('', '', 'height=600,width=800');
  printWindow.document.write('<html><head><title>Print</title>');
  printWindow.document.write('<link rel="stylesheet" href="/quanlyphongtro/admin/assets/vendor/bootstrap/css/bootstrap.min.css">');
  printWindow.document.write('<link rel="stylesheet" href="/quanlyphongtro/admin/assets/css/admin-custom.css">');
  printWindow.document.write('</head><body>');
  printWindow.document.write(element.innerHTML);
  printWindow.document.write('</body></html>');
  printWindow.document.close();
  printWindow.print();
}

/**
 * Export table to CSV
 */
function exportTableToCSV(tableId, filename = 'export.csv') {
  const table = document.getElementById(tableId);
  if (!table) return;

  let csv = [];
  const rows = table.querySelectorAll('tr');

  rows.forEach(row => {
    const cols = row.querySelectorAll('td, th');
    const csvRow = [];
    cols.forEach(col => {
      csvRow.push('"' + col.textContent.trim().replace(/"/g, '""') + '"');
    });
    csv.push(csvRow.join(','));
  });

  const csvContent = '\uFEFF' + csv.join('\n'); // \uFEFF for UTF-8 BOM
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const link = document.createElement('a');
  const url = URL.createObjectURL(blob);

  link.setAttribute('href', url);
  link.setAttribute('download', filename);
  link.style.visibility = 'hidden';
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);

  showToast('Đã xuất file CSV!', 'success');
}
