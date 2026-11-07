import Vue from 'vue';

function escapeHtml(text) {
  if (!text) return '';
  return String(text)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function formatProductText(text) {
  if (!text) return '';
  let safe = escapeHtml(text);

  // Bold "Product Name: ..." and "SKU: ..."
  safe = safe.replace(/(Product Name:\s*[^,.\n]+)/gi, '<strong style="color: #0f172a;">$1</strong>');
  safe = safe.replace(/(SKU:\s*[^,.\n]+)/gi, '<span style="background: #e2e8f0; color: #334155; padding: 2px 6px; border-radius: 4px; font-weight: 600; font-family: monospace; font-size: 12px;">$1</span>');

  // Highlight stock info (Required: X, Available: Y)
  safe = safe.replace(/(Required:\s*[\d.]+,?\s*Available[^:.]*:\s*[\d.]+)/gi, '<span style="color: #e11d48; font-weight: 600;">$1</span>');

  // Highlight status
  safe = safe.replace(/(Current status:\s*[^)]+)/gi, '<em style="color: #d97706;">$1</em>');

  return safe;
}

export function parseApiError(error, defaultMsg = 'The requested operation could not be completed.') {
  const data = (error && error.response && error.response.data) || (error && error.data) || (error && typeof error === 'object' ? error : null);

  let title = (data && data.title) || null;
  let rawErrors = [];

  if (typeof error === 'string') {
    rawErrors.push(error);
  } else if (data) {
    if (data.message && data.message !== 'The given data was invalid.' && data.message !== 'Server Error') {
      const splitLines = String(data.message).split('\n').map(s => s.trim()).filter(Boolean);
      rawErrors.push(...splitLines);
    }

    if (data.errors) {
      if (Array.isArray(data.errors)) {
        data.errors.forEach(e => {
          if (typeof e === 'string') rawErrors.push(e.trim());
          else if (e && e.message) rawErrors.push(String(e.message).trim());
        });
      } else if (typeof data.errors === 'object') {
        Object.values(data.errors).forEach(errList => {
          if (Array.isArray(errList)) {
            errList.forEach(e => { if (typeof e === 'string') rawErrors.push(e.trim()); });
          } else if (typeof errList === 'string') {
            rawErrors.push(errList.trim());
          }
        });
      }
    }
  } else if (error && error.message) {
    rawErrors.push(error.message);
  }

  if (rawErrors.length === 0) {
    rawErrors.push(defaultMsg);
  }

  // De-duplicate errors
  const uniqueErrors = Array.from(new Set(rawErrors.filter(Boolean)));

  // Build clean HTML and Plain text
  let html = '';
  if (uniqueErrors.length === 1) {
    const single = uniqueErrors[0];
    html = `
      <div style="text-align: left; font-size: 13.5px; line-height: 1.6; color: #1e293b; padding: 4px 2px;">
        <div style="background: #fff1f2; border: 1px solid #fecdd3; border-left: 4px solid #f43f5e; padding: 12px 14px; border-radius: 8px;">
          ${formatProductText(single)}
        </div>
      </div>
    `;
  } else {
    html = `
      <div style="text-align: left; font-size: 13.5px; line-height: 1.6; max-height: 380px; overflow-y: auto; padding: 4px 2px;">
        <div style="font-weight: 600; color: #0f172a; margin-bottom: 10px; display: flex; align-items: center; gap: 6px;">
          <span>The following issues need attention:</span>
        </div>
        <div style="display: flex; flex-direction: column; gap: 8px;">
          ${uniqueErrors.map(err => `
            <div style="background: #fff1f2; border: 1px solid #fecdd3; border-left: 4px solid #f43f5e; padding: 10px 14px; border-radius: 8px; color: #334155;">
              ${formatProductText(err)}
            </div>
          `).join('')}
        </div>
      </div>
    `;
  }

  return {
    title: title || (uniqueErrors.length > 1 ? 'Multiple Issues Detected' : 'Operation Failed'),
    errors: uniqueErrors,
    plainText: uniqueErrors.join('\n'),
    html,
  };
}

export function showApiError(error, customTitle = null) {
  const formatted = parseApiError(error);
  const displayTitle = customTitle || formatted.title;

  // 1. Show SweetAlert modal popup
  const swalInstance = (Vue && Vue.swal) || window.$swal || (window.Swal);
  if (swalInstance) {
    const fire = swalInstance.fire ? swalInstance.fire.bind(swalInstance) : swalInstance;
    fire({
      icon: 'error',
      title: displayTitle,
      html: formatted.html,
      confirmButtonText: 'Dismiss',
      confirmButtonColor: '#e11d48',
      width: formatted.errors.length > 1 ? '620px' : '500px',
      customClass: {
        popup: 'swal2-api-error-popup',
      },
    });
  }

  // 2. Also trigger toast notification if available
  try {
    const root = window.__appVueInstance;
    if (root && root.$bvToast) {
      root.$bvToast.toast(formatted.plainText, {
        title: displayTitle,
        variant: 'danger',
        solid: true,
        autoHideDelay: 10000,
      });
    }
  } catch (e) {}

  return formatted;
}

export default {
  parseApiError,
  showApiError,
};

