/**
 * moedit UI Components
 *
 * Dialog, Modal болон UI холбоотой функцуудыг агуулна.
 * moedit.js файлын дараа ачаалах шаардлагатай.
 *
 * @requires moedit
 */

/* ============================================
   Link Dialog
   ============================================ */

moedit.prototype._insertLink = function() {
  /* Selection хадгалах */
  let savedRange = null;
  const selection = window.getSelection();
  const selectedText = selection.toString();
  if (selection.rangeCount > 0) {
    savedRange = selection.getRangeAt(0).cloneRange();
  }

  const config = this.opts.linkModal;
  const dialogId = 'moedit-link-dialog-' + Date.now();
  const dialog = document.createElement('div');
  dialog.id = dialogId;
  dialog.className = 'moedit-modal-overlay';
  dialog.innerHTML = `
    <div class="moedit-modal">
      <h5 class="moedit-modal-title"><i class="bi bi-link-45deg"></i> ${config.title}</h5>
      <div class="moedit-modal-field">
        <div class="moedit-modal-radio-group">
          <label class="moedit-modal-radio">
            <input type="radio" name="${dialogId}-type" value="url" checked> <i class="bi bi-globe"></i> URL
          </label>
          <label class="moedit-modal-radio">
            <input type="radio" name="${dialogId}-type" value="email"> <i class="bi bi-envelope"></i> Email
          </label>
        </div>
      </div>
      <div class="moedit-modal-field">
        <label class="moedit-modal-label" id="${dialogId}-label">${config.urlLabel}</label>
        <input type="text" class="moedit-modal-input" id="${dialogId}-url" placeholder="https://">
      </div>
      <div class="moedit-modal-field">
        <label class="moedit-modal-label">${config.textLabel} <span class="moedit-modal-hint">${config.textHint}</span></label>
        <input type="text" class="moedit-modal-input" id="${dialogId}-text" placeholder="">
      </div>
      <div class="moedit-modal-buttons">
        <button type="button" class="moedit-modal-btn moedit-modal-btn-secondary" id="${dialogId}-cancel">${config.cancelText}</button>
        <button type="button" class="moedit-modal-btn moedit-modal-btn-primary" id="${dialogId}-ok">${config.okText}</button>
      </div>
    </div>
  `;
  document.body.appendChild(dialog);

  const urlInput = document.getElementById(`${dialogId}-url`);
  const textInput = document.getElementById(`${dialogId}-text`);
  const labelEl = document.getElementById(`${dialogId}-label`);
  const typeRadios = document.querySelectorAll(`input[name="${dialogId}-type"]`);
  const okBtn = document.getElementById(`${dialogId}-ok`);
  const cancelBtn = document.getElementById(`${dialogId}-cancel`);

  /* Сонгосон текст байвал text input-д оруулах */
  if (selectedText) {
    textInput.value = selectedText;
  }

  urlInput.focus();

  /* Type солигдоход placeholder өөрчлөх */
  typeRadios.forEach(radio => {
    radio.addEventListener('change', () => {
      if (radio.value === 'email') {
        urlInput.placeholder = 'example@domain.com';
        labelEl.textContent = config.emailLabel;
      } else {
        urlInput.placeholder = 'https://';
        labelEl.textContent = config.urlLabel;
      }
      urlInput.value = '';
      urlInput.focus();
    });
  });

  const closeDialog = () => dialog.remove();

  cancelBtn.addEventListener('click', closeDialog);
  dialog.addEventListener('click', (e) => { if (e.target === dialog) closeDialog(); });

  const escHandler = (e) => {
    if (e.key === 'Escape') {
      closeDialog();
      document.removeEventListener('keydown', escHandler);
    }
  };
  document.addEventListener('keydown', escHandler);

  /* Enter дарахад OK */
  const enterHandler = (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      okBtn.click();
    }
  };
  urlInput.addEventListener('keydown', enterHandler);
  textInput.addEventListener('keydown', enterHandler);

  /* OK товч */
  okBtn.addEventListener('click', () => {
    const isEmail = document.querySelector(`input[name="${dialogId}-type"]:checked`).value === 'email';
    let url = urlInput.value.trim();
    let text = textInput.value.trim();

    if (!url) {
      urlInput.focus();
      return;
    }

    closeDialog();
    document.removeEventListener('keydown', escHandler);

    /* URL/Email форматлах */
    if (isEmail) {
      if (!url.startsWith('mailto:')) {
        url = 'mailto:' + url;
      }
      if (!text) text = url.replace('mailto:', '');
    } else {
      if (!url.startsWith('http://') && !url.startsWith('https://') && !url.startsWith('/')) {
        url = 'https://' + url;
      }
      if (!text) text = url;
    }

    /* Selection сэргээх */
    this._ensureVisualMode();
    this._focusEditor();
    if (savedRange) {
      const sel = window.getSelection();
      sel.removeAllRanges();
      sel.addRange(savedRange);
    }

    /* Link үүсгэх */
    const link = document.createElement('a');
    link.href = url;
    link.textContent = text;
    if (!isEmail) {
      link.target = '_blank';
      link.rel = 'noopener noreferrer';
    }

    const sel = window.getSelection();
    if (sel.rangeCount > 0) {
      const range = sel.getRangeAt(0);
      range.deleteContents();
      range.insertNode(link);
      range.setStartAfter(link);
      range.collapse(true);
      sel.removeAllRanges();
      sel.addRange(range);
    }

    this._emitChange();
  });
};

/* ============================================
   Image Upload
   ============================================ */

moedit.prototype._insertImage = function() {
  /* Selection хадгалах */
  let savedRange = null;
  const selection = window.getSelection();
  if (selection.rangeCount > 0) {
    savedRange = selection.getRangeAt(0).cloneRange();
  }

  /* Хэрэв uploadUrl эсвэл uploadImage тохируулсан бол file upload dialog харуулах */
  if (this.opts.uploadUrl || this.opts.uploadImage) {
    this._showImageUploadDialog(savedRange);
  } else {
    /* URL оруулах dialog харуулах */
    this._showImageUrlDialog(savedRange);
  }
};

/**
 * Зургийн URL оруулах dialog
 */
moedit.prototype._showImageUrlDialog = function(savedRange) {
  const config = this.opts.imageUploadModal;
  const dialogId = 'moedit-image-url-dialog-' + Date.now();
  const dialog = document.createElement('div');
  dialog.id = dialogId;
  dialog.className = 'moedit-modal-overlay';
  dialog.innerHTML = `
    <div class="moedit-modal">
      <h5 class="moedit-modal-title"><i class="bi bi-image"></i> ${config.title}</h5>
      <div class="moedit-modal-field">
        <label class="moedit-modal-label">Зургийн URL хаяг</label>
        <input type="url" class="moedit-modal-input" id="${dialogId}-url" placeholder="https://example.com/image.jpg">
      </div>
      <div class="moedit-modal-preview" id="${dialogId}-preview" style="display:none;">
        <img id="${dialogId}-preview-img" src="" style="max-width:100%; max-height:200px;">
      </div>
      <div class="moedit-modal-buttons">
        <button type="button" class="moedit-modal-btn moedit-modal-btn-secondary" id="${dialogId}-cancel">${config.cancelText}</button>
        <button type="button" class="moedit-modal-btn moedit-modal-btn-primary" id="${dialogId}-ok">
          <i class="bi bi-check-lg"></i> OK
        </button>
      </div>
    </div>
  `;
  document.body.appendChild(dialog);

  const urlInput = dialog.querySelector(`#${dialogId}-url`);
  const previewDiv = dialog.querySelector(`#${dialogId}-preview`);
  const previewImg = dialog.querySelector(`#${dialogId}-preview-img`);
  const okBtn = dialog.querySelector(`#${dialogId}-ok`);
  const cancelBtn = dialog.querySelector(`#${dialogId}-cancel`);

  const closeDialog = () => dialog.remove();

  /* URL оруулахад preview харуулах */
  let debounceTimer;
  urlInput.addEventListener('input', () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
      const url = urlInput.value.trim();
      if (url && (url.startsWith('http://') || url.startsWith('https://'))) {
        previewImg.src = url;
        previewImg.onload = () => { previewDiv.style.display = 'block'; };
        previewImg.onerror = () => { previewDiv.style.display = 'none'; };
      } else {
        previewDiv.style.display = 'none';
      }
    }, 500);
  });

  /* OK товч */
  okBtn.addEventListener('click', () => {
    const url = urlInput.value.trim();
    if (url) {
      this._insertImageByUrl(url, savedRange);
    }
    closeDialog();
  });

  /* Cancel товч */
  cancelBtn.addEventListener('click', closeDialog);

  /* Overlay дарахад хаах */
  dialog.addEventListener('click', (e) => { if (e.target === dialog) closeDialog(); });

  /* Escape товч */
  const escHandler = (e) => {
    if (e.key === 'Escape') {
      closeDialog();
      document.removeEventListener('keydown', escHandler);
    }
  };
  document.addEventListener('keydown', escHandler);

  /* Enter товч */
  urlInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      okBtn.click();
    }
  });

  /* Focus */
  setTimeout(() => urlInput.focus(), 100);
};

moedit.prototype._showImageUploadDialog = function(savedRange) {
  const config = this.opts.imageUploadModal;
  const dialogId = 'moedit-image-dialog-' + Date.now();
  const dialog = document.createElement('div');
  dialog.id = dialogId;
  dialog.className = 'moedit-modal-overlay';
  dialog.innerHTML = `
    <div class="moedit-modal">
      <h5 class="moedit-modal-title"><i class="bi bi-image"></i> ${config.title}</h5>
      <div class="moedit-modal-field">
        <div class="moedit-modal-file-input">
          <input type="text" class="moedit-modal-input moedit-modal-input-readonly" id="${dialogId}-filename" readonly placeholder="${config.placeholder}">
          <button type="button" class="moedit-modal-btn moedit-modal-btn-primary" id="${dialogId}-browse">
            <i class="bi bi-folder2-open"></i> ${config.browseText}
          </button>
        </div>
        <input type="file" id="${dialogId}-file" accept="image/*" style="display:none;">
      </div>
      <div class="moedit-modal-preview" id="${dialogId}-preview" style="display:none;">
        <img id="${dialogId}-preview-img" src="">
      </div>
      <div class="moedit-modal-buttons">
        <button type="button" class="moedit-modal-btn moedit-modal-btn-secondary" id="${dialogId}-cancel">${config.cancelText}</button>
        <button type="button" class="moedit-modal-btn moedit-modal-btn-primary moedit-modal-btn-disabled" id="${dialogId}-ok" disabled>
          <i class="bi bi-upload"></i> ${config.uploadText}
        </button>
      </div>
    </div>
  `;
  document.body.appendChild(dialog);

  const fileInput = document.getElementById(`${dialogId}-file`);
  const filenameInput = document.getElementById(`${dialogId}-filename`);
  const browseBtn = document.getElementById(`${dialogId}-browse`);
  const previewDiv = document.getElementById(`${dialogId}-preview`);
  const previewImg = document.getElementById(`${dialogId}-preview-img`);
  const okBtn = document.getElementById(`${dialogId}-ok`);
  const cancelBtn = document.getElementById(`${dialogId}-cancel`);

  let selectedFile = null;

  const closeDialog = () => dialog.remove();

  browseBtn.addEventListener('click', () => fileInput.click());

  fileInput.addEventListener('change', function() {
    if (this.files && this.files[0]) {
      selectedFile = this.files[0];
      filenameInput.value = selectedFile.name;

      const reader = new FileReader();
      reader.onload = function(e) {
        previewImg.src = e.target.result;
        previewDiv.style.display = 'block';
      };
      reader.readAsDataURL(selectedFile);

      okBtn.disabled = false;
      okBtn.classList.remove('moedit-modal-btn-disabled');
    }
  });

  cancelBtn.addEventListener('click', closeDialog);
  dialog.addEventListener('click', (e) => { if (e.target === dialog) closeDialog(); });

  const escHandler = (e) => {
    if (e.key === 'Escape') {
      closeDialog();
      document.removeEventListener('keydown', escHandler);
    }
  };
  document.addEventListener('keydown', escHandler);

  okBtn.addEventListener('click', () => {
    if (!selectedFile) return;

    okBtn.disabled = true;
    okBtn.innerHTML = `<i class="bi bi-hourglass-split"></i> ${config.uploadingText}`;

    /* uploadImage функц эсвэл uploadUrl ашиглах */
    const uploadPromise = this.opts.uploadImage
      ? this.opts.uploadImage(selectedFile)
      : fetch(this.opts.uploadUrl, {
          method: 'POST',
          body: (() => { const fd = new FormData(); fd.append('file', selectedFile); return fd; })()
        }).then(res => res.json()).then(data => data.path);

    Promise.resolve(uploadPromise)
      .then(path => {
        closeDialog();
        document.removeEventListener('keydown', escHandler);

        if (path) {
          this._insertImageByUrl(path, savedRange);
          if (this.opts.onUploadSuccess) {
            this.opts.onUploadSuccess({ path });
          }
        } else {
          throw new Error(config.errorMessage);
        }
      })
      .catch(err => {
        okBtn.disabled = false;
        okBtn.classList.remove('moedit-modal-btn-disabled');
        okBtn.innerHTML = `<i class="bi bi-upload"></i> ${config.uploadText}`;
        if (this.opts.onUploadError) {
          this.opts.onUploadError(err);
        } else if (this.opts.notify) {
          this.opts.notify('danger', err.message || config.errorMessage);
        } else {
          alert(err.message || config.errorMessage);
        }
      });
  });
};

moedit.prototype._insertImageByUrl = function(url, savedRange) {
  this._ensureVisualMode();
  this._focusEditor();

  if (savedRange) {
    const sel = window.getSelection();
    sel.removeAllRanges();
    sel.addRange(savedRange);
  }

  const img = document.createElement('img');
  img.src = url;
  img.alt = '';
  img.style.maxWidth = '100%';
  img.style.height = 'auto';

  const selection = window.getSelection();
  if (selection.rangeCount > 0) {
    const range = selection.getRangeAt(0);
    range.deleteContents();
    range.insertNode(img);

    const newRange = document.createRange();
    newRange.setStartAfter(img);
    newRange.collapse(true);
    selection.removeAllRanges();
    selection.addRange(newRange);
  }

  this._emitChange();

  /* Notify if available */
  if (this.opts.notify) {
    this.opts.notify('success', this.opts.imageUploadModal.successMessage);
  }
};

/* ============================================
   Table Dialog
   ============================================ */

moedit.prototype._insertTable = function() {
  /* Selection хадгалах */
  let savedRange = null;
  const selection = window.getSelection();
  if (selection.rangeCount > 0) {
    savedRange = selection.getRangeAt(0).cloneRange();
  }

  const config = this.opts.tableModal;
  const dialogId = 'moedit-table-dialog-' + Date.now();
  const dialog = document.createElement('div');
  dialog.id = dialogId;
  dialog.className = 'moedit-modal-overlay';
  dialog.innerHTML = `
    <div class="moedit-modal moedit-modal-sm">
      <h5 class="moedit-modal-title"><i class="bi bi-table"></i> ${config.title}</h5>
      <div class="moedit-modal-field">
        <label class="moedit-modal-label">${config.rowsLabel}</label>
        <input type="number" class="moedit-modal-input" id="${dialogId}-rows" value="3" min="1" max="50">
      </div>
      <div class="moedit-modal-field">
        <label class="moedit-modal-label">${config.colsLabel}</label>
        <input type="number" class="moedit-modal-input" id="${dialogId}-cols" value="3" min="1" max="20">
      </div>
      <div class="moedit-modal-buttons">
        <button type="button" class="moedit-modal-btn moedit-modal-btn-secondary" id="${dialogId}-cancel">${config.cancelText}</button>
        <button type="button" class="moedit-modal-btn moedit-modal-btn-primary" id="${dialogId}-ok">${config.okText}</button>
      </div>
    </div>
  `;
  document.body.appendChild(dialog);

  const rowsInput = document.getElementById(`${dialogId}-rows`);
  const colsInput = document.getElementById(`${dialogId}-cols`);
  const okBtn = document.getElementById(`${dialogId}-ok`);
  const cancelBtn = document.getElementById(`${dialogId}-cancel`);

  /* Эхний input-д focus */
  rowsInput.focus();
  rowsInput.select();

  /* Dialog хаах функц */
  const closeDialog = () => dialog.remove();

  /* Cancel товч */
  cancelBtn.addEventListener('click', closeDialog);

  /* Background дээр дарахад хаах */
  dialog.addEventListener('click', (e) => {
    if (e.target === dialog) closeDialog();
  });

  /* ESC дарахад хаах */
  const escHandler = (e) => {
    if (e.key === 'Escape') {
      closeDialog();
      document.removeEventListener('keydown', escHandler);
    }
  };
  document.addEventListener('keydown', escHandler);

  /* Enter дарахад OK */
  const enterHandler = (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      okBtn.click();
    }
  };
  rowsInput.addEventListener('keydown', enterHandler);
  colsInput.addEventListener('keydown', enterHandler);

  /* OK товч */
  okBtn.addEventListener('click', () => {
    const rows = parseInt(rowsInput.value) || 3;
    const cols = parseInt(colsInput.value) || 3;

    closeDialog();
    document.removeEventListener('keydown', escHandler);

    if (rows > 0 && cols > 0) {
      const table = document.createElement('table');
      table.style.width = '100%';
      table.style.borderCollapse = 'collapse';
      table.style.marginBottom = '1rem';

      const tbody = document.createElement('tbody');

      for (let i = 0; i < rows; i++) {
        const tr = document.createElement('tr');
        for (let j = 0; j < cols; j++) {
          const cell = document.createElement(i === 0 ? 'th' : 'td');
          cell.style.border = '1px solid #dee2e6';
          cell.style.padding = '0.5rem';
          cell.innerHTML = '&nbsp;';
          tr.appendChild(cell);
        }
        tbody.appendChild(tr);
      }

      table.appendChild(tbody);

      /* Selection сэргээх */
      this._focusEditor();
      if (savedRange) {
        const sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(savedRange);
      }

      const sel = window.getSelection();
      if (sel.rangeCount > 0) {
        const range = sel.getRangeAt(0);
        range.deleteContents();
        range.insertNode(table);

        /* Cursor-ийг эхний cell-д байрлуулах */
        const firstCell = table.querySelector('th, td');
        if (firstCell) {
          const newRange = document.createRange();
          newRange.selectNodeContents(firstCell);
          newRange.collapse(true);
          sel.removeAllRanges();
          sel.addRange(newRange);
        }
      } else {
        document.execCommand("insertHTML", false, table.outerHTML);
      }

      this._emitChange();
    }
  });
};

/* ============================================
   Paste Text Dialog
   ============================================ */

moedit.prototype._pasteText = function() {
  /* Selection хадгалах */
  let savedRange = null;
  this._ensureVisualMode();
  const selection = window.getSelection();
  if (selection.rangeCount > 0) {
    savedRange = selection.getRangeAt(0).cloneRange();
  }

  const config = this.opts.pasteTextModal;
  const dialogId = 'moedit-paste-dialog-' + Date.now();
  const dialog = document.createElement('div');
  dialog.id = dialogId;
  dialog.className = 'moedit-modal-overlay';
  dialog.innerHTML = `
    <div class="moedit-modal">
      <h5 class="moedit-modal-title"><i class="bi bi-clipboard-plus"></i> ${config.title}</h5>
      <p class="moedit-modal-desc">${config.description}</p>
      <textarea class="moedit-modal-textarea" id="${dialogId}-textarea" rows="10" placeholder="${config.placeholder}"></textarea>
      <div class="moedit-modal-buttons">
        <button type="button" class="moedit-modal-btn moedit-modal-btn-secondary" id="${dialogId}-cancel">${config.cancelText}</button>
        <button type="button" class="moedit-modal-btn moedit-modal-btn-primary" id="${dialogId}-ok"><i class="bi bi-check-lg"></i> ${config.okText}</button>
      </div>
    </div>
  `;
  document.body.appendChild(dialog);

  const textarea = document.getElementById(`${dialogId}-textarea`);
  const okBtn = document.getElementById(`${dialogId}-ok`);
  const cancelBtn = document.getElementById(`${dialogId}-cancel`);

  /* Modal нээгдсэний дараа textarea-д focus */
  setTimeout(() => textarea.focus(), 100);

  const closeDialog = () => dialog.remove();

  /* Textarea дээр paste хийхэд текстийг цэвэрлэх */
  textarea.addEventListener('paste', (e) => {
    e.preventDefault();
    const clipboardData = e.clipboardData || window.clipboardData;

    /* Plain text авах (HTML tag-гүй цэвэр текст) */
    let pastedText = clipboardData.getData('text/plain') || clipboardData.getData('text');

    /* Текстийг цэвэрлэх */
    pastedText = pastedText
      .replace(/\r\n/g, '\n')           /* Windows line break -> Unix */
      .replace(/\r/g, '\n')             /* Old Mac line break -> Unix */
      .replace(/\t/g, '    ')           /* Tab -> 4 spaces */
      .replace(/\u00A0/g, ' ')          /* Non-breaking space -> normal space */
      .replace(/[\u200B-\u200D\uFEFF]/g, '') /* Zero-width characters устгах */
      .trim();

    /* Cursor байрлалд оруулах */
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const text = textarea.value;
    textarea.value = text.substring(0, start) + pastedText + text.substring(end);
    textarea.selectionStart = textarea.selectionEnd = start + pastedText.length;
  });

  /* Cancel товч */
  cancelBtn.addEventListener('click', closeDialog);

  /* Background дээр дарахад хаах */
  dialog.addEventListener('click', (e) => {
    if (e.target === dialog) closeDialog();
  });

  /* ESC дарахад хаах */
  const escHandler = (e) => {
    if (e.key === 'Escape') {
      closeDialog();
      document.removeEventListener('keydown', escHandler);
    }
  };
  document.addEventListener('keydown', escHandler);

  /* OK товч */
  okBtn.addEventListener('click', () => {
    const cleanText = textarea.value.trim();

    if (cleanText) {
      /* Текстийг HTML болгох (line break -> <br> эсвэл <p>) */
      const paragraphs = cleanText.split(/\n\n+/);
      let html = '';

      if (paragraphs.length > 1) {
        /* Олон paragraph байвал <p> tag ашиглах */
        html = paragraphs.map(p => {
          /* XSS-ээс хамгаалах: HTML escape хийх */
          const escapedP = this._escapeHtml(p);
          const lines = escapedP.split(/\n/).join('<br>');
          return `<p style="margin-bottom:1rem">${lines}</p>`;
        }).join('');
      } else {
        /* Ганц paragraph байвал <br> ашиглах */
        /* XSS-ээс хамгаалах: HTML escape хийх */
        html = this._escapeHtml(cleanText).split(/\n/).join('<br>');
      }

      /* Editor-д focus хийж, selection сэргээх */
      this._focusEditor();

      if (savedRange) {
        const sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(savedRange);
      }

      /* HTML оруулах */
      document.execCommand('insertHTML', false, html);
      this._emitChange();
    }

    closeDialog();
    document.removeEventListener('keydown', escHandler);
  });
};

/* ============================================
   AI Shine Dialog
   ============================================ */

moedit.prototype._shine = async function() {
  const html = this.getHTML();
  const cfg = this.opts.shineModal;

  /* Хоосон контент шалгах */
  if (!html || !html.trim()) {
    const emptyMsg = 'AI Shine ашиглахын тулд эхлээд контент бичнэ үү.';
    if (typeof NotifyTop === 'function') {
      NotifyTop('warning', cfg.title, emptyMsg);
    } else if (this.opts.notify) {
      this.opts.notify('warning', emptyMsg);
    } else {
      alert(emptyMsg);
    }
    return;
  }

  /* Modal үүсгэх */
  const dialogId = 'moedit-shine-dialog-' + Date.now();
  const dialog = document.createElement('div');
  dialog.id = dialogId;
  dialog.className = 'moedit-modal-overlay';
  dialog.innerHTML = `
    <div class="moedit-modal moedit-modal-lg">
      <h5 class="moedit-modal-title"><i class="bi bi-stars"></i> ${cfg.title}</h5>
      <p class="moedit-modal-desc">${cfg.description}</p>
      <div class="shine-status" style="display:none;">
        <div style="display:flex; align-items:center; gap:0.5rem;">
          <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
          <span>${cfg.processingText}</span>
        </div>
      </div>
      <div class="shine-preview" style="display:none; max-height:300px; overflow:auto; border:1px solid var(--mo-border); border-radius:var(--mo-radius); padding:10px; margin-top:10px; background:var(--mo-bg);"></div>
      <div class="shine-error" style="display:none; color:#dc3545; margin-top:10px; word-wrap:break-word; overflow-wrap:break-word; max-width:100%;"></div>
      <div class="moedit-modal-buttons">
        <button type="button" class="moedit-modal-btn moedit-modal-btn-secondary btn-cancel">${cfg.cancelText}</button>
        <button type="button" class="moedit-modal-btn moedit-modal-btn-primary btn-shine">
          <i class="bi bi-stars"></i> ${cfg.title}
        </button>
        <button type="button" class="moedit-modal-btn moedit-modal-btn-success btn-confirm" style="display:none;">
          <i class="bi bi-check-lg"></i> ${cfg.confirmText}
        </button>
      </div>
    </div>
  `;
  document.body.appendChild(dialog);

  const statusEl = dialog.querySelector('.shine-status');
  const previewEl = dialog.querySelector('.shine-preview');
  const errorEl = dialog.querySelector('.shine-error');
  const shineBtn = dialog.querySelector('.btn-shine');
  const confirmBtn = dialog.querySelector('.btn-confirm');
  const cancelBtn = dialog.querySelector('.btn-cancel');

  let newHtml = null;

  const closeDialog = () => dialog.remove();

  cancelBtn.addEventListener('click', closeDialog);
  dialog.addEventListener('click', (e) => { if (e.target === dialog) closeDialog(); });

  const escHandler = (e) => {
    if (e.key === 'Escape') {
      closeDialog();
      document.removeEventListener('keydown', escHandler);
    }
  };
  document.addEventListener('keydown', escHandler);

  /* Shine товч дарахад API дуудах */
  shineBtn.addEventListener('click', async () => {
    shineBtn.disabled = true;
    statusEl.style.display = 'block';
    errorEl.style.display = 'none';
    previewEl.style.display = 'none';

    try {
      const response = await fetch(this.opts.shineUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ html: html })
      });

      /* HTTP алдаа шалгах */
      if (!response.ok) {
        throw new Error(`Сервер алдаа: ${response.status} ${response.statusText}`);
      }

      /* JSON эсэхийг шалгах */
      const contentType = response.headers.get('content-type');
      if (!contentType || !contentType.includes('application/json')) {
        throw new Error('Shine API endpoint тохируулаагүй байна');
      }

      const data = await response.json();

      if (data.status === 'success' && data.html) {
        newHtml = data.html;
        previewEl.innerHTML = newHtml;
        previewEl.style.display = 'block';
        shineBtn.style.display = 'none';
        confirmBtn.style.display = 'inline-block';

        if (this.opts.notify) {
          this.opts.notify('success', cfg.title, cfg.successMessage);
        }
      } else {
        throw new Error(data.message || cfg.errorMessage);
      }
    } catch (err) {
      errorEl.textContent = err.message || cfg.errorMessage;
      errorEl.style.display = 'block';
      shineBtn.disabled = false;

      if (this.opts.notify) {
        this.opts.notify('error', cfg.title, err.message || cfg.errorMessage);
      }
    } finally {
      statusEl.style.display = 'none';
    }
  });

  /* Баталгаажуулах товч - контентыг шинэчлэх */
  confirmBtn.addEventListener('click', () => {
    if (newHtml) {
      this.setHTML(newHtml);
    }
    closeDialog();
  });
};

/* ============================================
   AI OCR Dialog (Зураг → HTML)
   ============================================ */

moedit.prototype._ocr = async function() {
  const html = this.getHTML();
  const cfg = this.opts.ocrModal;

  /* HTML-ээс зураг шалгах */
  const tempDiv = document.createElement('div');
  tempDiv.innerHTML = html;
  const images = tempDiv.querySelectorAll('img');

  if (images.length === 0) {
    const noImgMsg = cfg.noImageMessage;
    if (typeof NotifyTop === 'function') {
      NotifyTop('warning', cfg.title, noImgMsg);
    } else if (this.opts.notify) {
      this.opts.notify('warning', noImgMsg);
    } else {
      alert(noImgMsg);
    }
    return;
  }

  /* Modal үүсгэх */
  const dialogId = 'moedit-ocr-dialog-' + Date.now();
  const dialog = document.createElement('div');
  dialog.id = dialogId;
  dialog.className = 'moedit-modal-overlay';
  dialog.innerHTML = `
    <div class="moedit-modal moedit-modal-lg">
      <h5 class="moedit-modal-title"><i class="bi bi-image-alt text-info"></i> ${cfg.title}</h5>
      <p class="moedit-modal-desc">${cfg.description}</p>
      <div class="ocr-image-preview" style="margin:10px 0; padding:10px; background:var(--mo-bg); border:1px solid var(--mo-border); border-radius:var(--mo-radius); max-height:150px; overflow:auto;">
        <small class="text-muted">Олдсон зураг: ${images.length}</small>
        <div style="display:flex; gap:5px; flex-wrap:wrap; margin-top:5px;">
          ${Array.from(images).map(img => `<img src="${img.src}" style="max-height:80px; max-width:120px; object-fit:contain; border:1px solid var(--mo-border); border-radius:4px;">`).join('')}
        </div>
      </div>
      <div class="ocr-status" style="display:none;">
        <div style="display:flex; align-items:center; gap:0.5rem;">
          <div class="spinner-border spinner-border-sm text-info" role="status"></div>
          <span>${cfg.processingText}</span>
        </div>
      </div>
      <div class="ocr-preview" style="display:none; max-height:300px; overflow:auto; border:1px solid var(--mo-border); border-radius:var(--mo-radius); padding:10px; margin-top:10px; background:var(--mo-bg);"></div>
      <div class="ocr-error" style="display:none; color:#dc3545; margin-top:10px; word-wrap:break-word; overflow-wrap:break-word; max-width:100%;"></div>
      <div class="moedit-modal-buttons">
        <button type="button" class="moedit-modal-btn moedit-modal-btn-secondary btn-cancel">${cfg.cancelText}</button>
        <button type="button" class="moedit-modal-btn moedit-modal-btn-info btn-ocr">
          <i class="bi bi-image-alt"></i> ${cfg.title}
        </button>
        <button type="button" class="moedit-modal-btn moedit-modal-btn-success btn-confirm" style="display:none;">
          <i class="bi bi-check-lg"></i> ${cfg.confirmText}
        </button>
      </div>
    </div>
  `;
  document.body.appendChild(dialog);

  const statusEl = dialog.querySelector('.ocr-status');
  const previewEl = dialog.querySelector('.ocr-preview');
  const errorEl = dialog.querySelector('.ocr-error');
  const ocrBtn = dialog.querySelector('.btn-ocr');
  const confirmBtn = dialog.querySelector('.btn-confirm');
  const cancelBtn = dialog.querySelector('.btn-cancel');

  let newHtml = null;

  const closeDialog = () => dialog.remove();

  cancelBtn.addEventListener('click', closeDialog);
  dialog.addEventListener('click', (e) => { if (e.target === dialog) closeDialog(); });

  const escHandler = (e) => {
    if (e.key === 'Escape') {
      closeDialog();
      document.removeEventListener('keydown', escHandler);
    }
  };
  document.addEventListener('keydown', escHandler);

  /* OCR товч дарахад API дуудах */
  ocrBtn.addEventListener('click', async () => {
    ocrBtn.disabled = true;
    statusEl.style.display = 'block';
    errorEl.style.display = 'none';
    previewEl.style.display = 'none';

    try {
      const response = await fetch(this.opts.shineUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ html: html, mode: 'vision' })
      });

      /* HTTP алдаа шалгах */
      if (!response.ok) {
        throw new Error(`Сервер алдаа: ${response.status} ${response.statusText}`);
      }

      /* JSON эсэхийг шалгах */
      const contentType = response.headers.get('content-type');
      if (!contentType || !contentType.includes('application/json')) {
        throw new Error('OCR API endpoint тохируулаагүй байна');
      }

      const data = await response.json();

      if (data.status === 'success' && data.html) {
        newHtml = data.html;
        previewEl.innerHTML = newHtml;
        previewEl.style.display = 'block';
        ocrBtn.style.display = 'none';
        confirmBtn.style.display = 'inline-block';

        if (this.opts.notify) {
          this.opts.notify('success', cfg.title, cfg.successMessage);
        }
      } else {
        throw new Error(data.message || cfg.errorMessage);
      }
    } catch (err) {
      errorEl.textContent = err.message || cfg.errorMessage;
      errorEl.style.display = 'block';
      ocrBtn.disabled = false;

      if (this.opts.notify) {
        this.opts.notify('error', cfg.title, err.message || cfg.errorMessage);
      }
    } finally {
      statusEl.style.display = 'none';
    }
  });

  /* Баталгаажуулах товч - контентыг шинэчлэх */
  confirmBtn.addEventListener('click', () => {
    if (newHtml) {
      this.setHTML(newHtml);
    }
    closeDialog();
  });
};

/* ============================================
   Print Function
   ============================================ */

moedit.prototype._print = function() {
  const printWindow = window.open('', '_blank');
  printWindow.document.write(`
    <!DOCTYPE html>
    <html>
      <head>
        <title>Print</title>
        <style>
          body { font-family: Arial, sans-serif; padding: 20px; }
          table { border-collapse: collapse; width: 100%; margin-bottom: 1rem; }
          td, th { border: 1px solid #dee2e6; padding: 0.5rem; }
          img { max-width: 100%; height: auto; }
          @media print {
            body { padding: 0; }
          }
        </style>
      </head>
      <body>${this.getHTML()}</body>
    </html>
  `);
  printWindow.document.close();
  printWindow.focus();
  setTimeout(() => {
    printWindow.print();
    printWindow.close();
  }, 250);
};

/* ============================================
   Font & Color Functions
   ============================================ */

moedit.prototype._setFontSize = function(size) {
  if (size) {
    document.execCommand("fontSize", false, size);
  }
};

moedit.prototype._removeFontSize = function() {
  const selection = window.getSelection();
  if (!selection.rangeCount) return;

  const range = selection.getRangeAt(0);

  /* Cursor байрлал (текст сонгоогүй) - font tag-аас гарах */
  if (range.collapsed) {
    let node = range.startContainer;
    let fontElement = null;

    while (node && node !== this.editor) {
      if (node.nodeName === 'FONT') {
        fontElement = node;
        break;
      }
      if (node.nodeType === Node.ELEMENT_NODE && node.style && node.style.fontSize) {
        fontElement = node;
        break;
      }
      node = node.parentNode;
    }

    if (fontElement) {
      const span = document.createElement('span');
      span.innerHTML = '\u200B';

      if (fontElement.nextSibling) {
        fontElement.parentNode.insertBefore(span, fontElement.nextSibling);
      } else {
        fontElement.parentNode.appendChild(span);
      }

      const newRange = document.createRange();
      newRange.setStart(span, 1);
      newRange.collapse(true);
      selection.removeAllRanges();
      selection.addRange(newRange);
    }

    this._emitChange();
    return;
  }

  /* Текст сонгосон үед */

  /* Эхлээд сонголтын эргэн тойрон дахь font элементүүдийг олох */
  let startNode = range.startContainer;
  let endNode = range.endContainer;

  /* Parent font элементүүдийг олох */
  const findParentFont = (node) => {
    while (node && node !== this.editor) {
      if (node.nodeName === 'FONT') return node;
      if (node.nodeType === Node.ELEMENT_NODE && node.style && node.style.fontSize) return node;
      node = node.parentNode;
    }
    return null;
  };

  const startFont = findParentFont(startNode);
  const endFont = findParentFont(endNode);

  /* Хэрэв font tag дотор байвал - tag-ийг задлах */
  if (startFont || endFont) {
    /* Сонгосон текстийг авах */
    const selectedText = range.toString();

    /* Сонголтыг устгах */
    range.deleteContents();

    /* Хэрэв бүтэн font tag сонгогдсон бол tag-ийг устгах */
    if (startFont && startFont === endFont) {
      /* Font tag хоосон болсон эсэхийг шалгах */
      if (!startFont.textContent.trim()) {
        /* Font tag-ийг устгах */
        const parent = startFont.parentNode;

        /* Шинэ текст node үүсгэх */
        const textNode = document.createTextNode(selectedText);
        parent.insertBefore(textNode, startFont);
        parent.removeChild(startFont);

        /* Cursor байрлуулах */
        const newRange = document.createRange();
        newRange.setStartAfter(textNode);
        newRange.collapse(true);
        selection.removeAllRanges();
        selection.addRange(newRange);
      } else {
        /* Font tag дотор хэсэгчлэн сонгосон - шинэ текст оруулах */
        const textNode = document.createTextNode(selectedText);
        range.insertNode(textNode);

        /* Текст node-ийг font tag-аас гаргах */
        const fontParent = findParentFont(textNode);
        if (fontParent) {
          /* Font tag-ийг хуваах */
          const afterFont = fontParent.cloneNode(false);
          let moveNode = textNode.nextSibling;
          while (moveNode) {
            const next = moveNode.nextSibling;
            afterFont.appendChild(moveNode);
            moveNode = next;
          }

          /* Текст node-ийг font-ийн дараа оруулах */
          fontParent.parentNode.insertBefore(textNode, fontParent.nextSibling);

          /* Хэрэв afterFont контенттай бол оруулах */
          if (afterFont.textContent) {
            fontParent.parentNode.insertBefore(afterFont, textNode.nextSibling);
          }

          /* Хоосон font tag устгах */
          if (!fontParent.textContent.trim()) {
            fontParent.parentNode.removeChild(fontParent);
          }
        }

        /* Cursor байрлуулах */
        const newRange = document.createRange();
        newRange.setStartAfter(textNode);
        newRange.collapse(true);
        selection.removeAllRanges();
        selection.addRange(newRange);
      }
    } else {
      /* Өөр өөр font tag-уудаас сонгосон - энгийн текст оруулах */
      const textNode = document.createTextNode(selectedText);
      range.insertNode(textNode);

      const newRange = document.createRange();
      newRange.selectNodeContents(textNode);
      selection.removeAllRanges();
      selection.addRange(newRange);
    }

    this._emitChange();
    return;
  }

  /* Font tag-гүй контент - fragment ашиглах */
  const fragment = range.cloneContents();
  const tempDiv = document.createElement('div');
  tempDiv.appendChild(fragment);

  const fontTags = tempDiv.querySelectorAll('font');
  fontTags.forEach(font => {
    const parent = font.parentNode;
    while (font.firstChild) {
      parent.insertBefore(font.firstChild, font);
    }
    parent.removeChild(font);
  });

  const styledElements = tempDiv.querySelectorAll('[style*="font-size"]');
  styledElements.forEach(el => {
    el.style.fontSize = '';
    if (!el.getAttribute('style')?.trim()) {
      el.removeAttribute('style');
    }
  });

  range.deleteContents();

  const newFragment = document.createDocumentFragment();
  while (tempDiv.firstChild) {
    newFragment.appendChild(tempDiv.firstChild);
  }

  range.insertNode(newFragment);
  selection.removeAllRanges();
  selection.addRange(range);

  this._emitChange();
};

moedit.prototype._setForeColor = function(color) {
  if (color) {
    document.execCommand("foreColor", false, color);
  }
};

moedit.prototype._removeFontColor = function() {
  const selection = window.getSelection();
  if (!selection.rangeCount) return;

  const range = selection.getRangeAt(0);

  /* Cursor байрлал (текст сонгоогүй) - font/span tag-аас гарах */
  if (range.collapsed) {
    let node = range.startContainer;
    let colorElement = null;

    /* Color тохируулсан element олох */
    while (node && node !== this.editor) {
      if (node.nodeName === 'FONT' && node.hasAttribute('color')) {
        colorElement = node;
        break;
      }
      if (node.nodeType === Node.ELEMENT_NODE && node.style && node.style.color) {
        colorElement = node;
        break;
        }
      node = node.parentNode;
    }

    if (colorElement) {
      /* Element-ийн дараа cursor байрлуулах */
      const span = document.createElement('span');
      span.innerHTML = '\u200B'; /* Zero-width space */

      if (colorElement.nextSibling) {
        colorElement.parentNode.insertBefore(span, colorElement.nextSibling);
      } else {
        colorElement.parentNode.appendChild(span);
      }

      const newRange = document.createRange();
      newRange.setStart(span, 1);
      newRange.collapse(true);
      selection.removeAllRanges();
      selection.addRange(newRange);
    }

    this._emitChange();
    return;
  }

  /* Текст сонгосон үед */
  const fragment = range.cloneContents();
  const tempDiv = document.createElement('div');
  tempDiv.appendChild(fragment);

  /* Font color attribute устгах */
  const fontTags = tempDiv.querySelectorAll('font[color]');
  fontTags.forEach(font => {
    font.removeAttribute('color');
    /* Хэрэв бусад attribute байхгүй бол font tag-ийг устгах */
    if (!font.hasAttribute('size') && !font.hasAttribute('face')) {
      const parent = font.parentNode;
      while (font.firstChild) {
        parent.insertBefore(font.firstChild, font);
      }
      parent.removeChild(font);
    }
  });

  /* style="color:..." устгах */
  const styledElements = tempDiv.querySelectorAll('[style*="color"]');
  styledElements.forEach(el => {
    el.style.color = '';
    if (!el.getAttribute('style')?.trim()) {
      el.removeAttribute('style');
    }
  });

  range.deleteContents();

  const newFragment = document.createDocumentFragment();
  while (tempDiv.firstChild) {
    newFragment.appendChild(tempDiv.firstChild);
  }

  range.insertNode(newFragment);
  selection.removeAllRanges();
  selection.addRange(range);

  this._emitChange();
};

/* ============================================
   Insert Functions
   ============================================ */

moedit.prototype._insertHR = function() {
  const hr = document.createElement('hr');
  const selection = window.getSelection();
  if (selection.rangeCount > 0) {
    const range = selection.getRangeAt(0);
    range.deleteContents();
    range.insertNode(hr);

    /* Cursor-ийг HR-ийн дараа байрлуулах */
    const newRange = document.createRange();
    newRange.setStartAfter(hr);
    newRange.collapse(true);
    selection.removeAllRanges();
    selection.addRange(newRange);
  }
  this._emitChange();
};

moedit.prototype._insertEmail = function() {
  const email = this.opts.prompt("Email хаяг оруул", "example@domain.com");
  if (email && email.trim()) {
    const link = document.createElement('a');
    link.href = 'mailto:' + email.trim();
    link.textContent = email.trim();
    const selection = window.getSelection();
    if (selection.rangeCount > 0) {
      const range = selection.getRangeAt(0);
      range.deleteContents();
      range.insertNode(link);
      this._emitChange();
    }
  }
};

moedit.prototype._insertYouTube = function() {
  const url = this.opts.prompt("YouTube video URL оруул", "https://www.youtube.com/watch?v=");
  if (url && url.trim()) {
    let videoId = '';
    const match = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\n?#]+)/);
    if (match) {
      videoId = match[1];
    } else {
      videoId = url.trim().split('/').pop().split('?')[0];
    }
    if (videoId) {
      /* Responsive wrapper div үүсгэх */
      const wrapper = document.createElement('div');
      wrapper.style.position = 'relative';
      wrapper.style.width = '100%';
      wrapper.style.maxWidth = '560px';
      wrapper.style.paddingBottom = '56.25%'; /* 16:9 ratio */
      wrapper.style.margin = '10px 0';
      wrapper.style.height = '0';
      wrapper.style.overflow = 'hidden';

      const iframe = document.createElement('iframe');
      iframe.src = 'https://www.youtube.com/embed/' + videoId;
      iframe.style.position = 'absolute';
      iframe.style.top = '0';
      iframe.style.left = '0';
      iframe.style.width = '100%';
      iframe.style.height = '100%';
      iframe.style.border = 'none';
      iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
      iframe.allowFullscreen = true;
      iframe.setAttribute('sandbox', 'allow-scripts allow-same-origin allow-presentation');
      iframe.setAttribute('loading', 'lazy');
      iframe.setAttribute('referrerpolicy', 'no-referrer-when-downgrade');

      wrapper.appendChild(iframe);

      const selection = window.getSelection();
      if (selection.rangeCount > 0) {
        const range = selection.getRangeAt(0);
        range.insertNode(wrapper);
        this._emitChange();
      }
    }
  }
};

moedit.prototype._insertFacebook = function() {
  const url = this.opts.prompt("Facebook video URL оруул", "https://www.facebook.com/");
  if (url && url.trim()) {
    /* Responsive wrapper div үүсгэх */
    const wrapper = document.createElement('div');
    wrapper.style.position = 'relative';
    wrapper.style.width = '100%';
    wrapper.style.maxWidth = '560px';
    wrapper.style.paddingBottom = '56.25%'; /* 16:9 ratio */
    wrapper.style.margin = '10px 0';
    wrapper.style.height = '0';
    wrapper.style.overflow = 'hidden';

    const iframe = document.createElement('iframe');
    iframe.src = 'https://www.facebook.com/plugins/video.php?href=' + encodeURIComponent(url.trim()) + '&show_text=0&width=560';
    iframe.style.position = 'absolute';
    iframe.style.top = '0';
    iframe.style.left = '0';
    iframe.style.width = '100%';
    iframe.style.height = '100%';
    iframe.style.border = 'none';
    iframe.scrolling = 'no';
    iframe.allow = 'encrypted-media';
    iframe.setAttribute('sandbox', 'allow-scripts allow-same-origin allow-presentation');
    iframe.setAttribute('loading', 'lazy');
    iframe.setAttribute('referrerpolicy', 'no-referrer-when-downgrade');

    wrapper.appendChild(iframe);

    const selection = window.getSelection();
    if (selection.rangeCount > 0) {
      const range = selection.getRangeAt(0);
      range.insertNode(wrapper);
      this._emitChange();
    }
  }
};

moedit.prototype._paste = function() {
  navigator.clipboard.readText().then(text => {
    const selection = window.getSelection();
    if (selection.rangeCount > 0) {
      const range = selection.getRangeAt(0);
      range.deleteContents();
      const textNode = document.createTextNode(text);
      range.insertNode(textNode);
      range.setStartAfter(textNode);
      range.collapse(true);
      selection.removeAllRanges();
      selection.addRange(range);
      this._emitChange();
    }
  }).catch(() => {
    /* Fallback: standard paste command */
    document.execCommand("paste", false, null);
    this._emitChange();
  });
};

/* ============================================
   Clean Pasted Content
   ============================================ */

moedit.prototype._cleanPastedContent = async function(html) {
  if (!html) return '';

  /* 1. Local file path болон base64 зурагтай img tag-уудыг placeholder-ээр солих */
  let cleanedHtml = html
    .replace(/(<img[^>]*src\s*=\s*["']?)(file:\/\/\/[^"'\s>]*)(["']?)/gi, '$1#LOCAL_FILE_PLACEHOLDER#$3')
    .replace(/(<img[^>]*src\s*=\s*["']?)([A-Za-z]:[\\\/][^"'\s>]*)(["']?)/gi, '$1#LOCAL_FILE_PLACEHOLDER#$3')
    .replace(/(<img[^>]*src\s*=\s*["']?)(data:image\/[^"'\s>]*)(["']?)/gi, '$1#BASE64_PLACEHOLDER#$3');

  /* Temporary div ашиглан HTML-ийг parse хийх */
  const tempDiv = document.createElement('div');
  tempDiv.innerHTML = cleanedHtml;

  /* Word/Excel-ийн нэмэлт tag-уудыг цэвэрлэх */
  const cleanedDiv = this._cleanNode(tempDiv);
  return cleanedDiv ? cleanedDiv.innerHTML : '';
};

moedit.prototype._cleanNode = function(tempDiv) {
  const cleanNode = (node) => {
    if (node.nodeType === Node.TEXT_NODE) {
      return node.cloneNode(true);
    }

    if (node.nodeType === Node.ELEMENT_NODE) {
      const tagName = node.tagName.toLowerCase();

      /* Word/Excel-ийн нэмэлт tag-уудыг устгах */
      if (['meta', 'link', 'style', 'script', 'xml', 'o:p', 'v:shapetype', 'v:shape'].includes(tagName)) {
        return null;
      }

      /* Зөвхөн зөвшөөрөгдсөн tag-уудыг үлдээх */
      const allowedTags = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'sub', 'sup',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td',
        'a', 'img', 'blockquote', 'pre', 'code',
        'div', 'span', 'hr'
      ];

      if (!allowedTags.includes(tagName)) {
        /* Tag-ийг устгах, гэхдээ content-ийг үлдээх */
        const fragment = document.createDocumentFragment();
        Array.from(node.childNodes).forEach(child => {
          const cleaned = cleanNode(child);
          if (cleaned) {
            fragment.appendChild(cleaned);
          }
        });
        return fragment;
      }

      /* Шинэ element үүсгэх */
      const newElement = document.createElement(tagName);

      /* Зөвхөн зөвшөөрөгдсөн attribute-уудыг үлдээх */
      const allowedAttrs = {
        'a': ['href', 'target', 'rel', 'title'],
        'img': ['src', 'alt', 'title', 'width', 'height'],
        'table': ['border', 'cellpadding', 'cellspacing'],
        'td': ['colspan', 'rowspan'],
        'th': ['colspan', 'rowspan'],
        'col': ['span'],
        'colgroup': ['span']
      };

      const attrs = allowedAttrs[tagName] || [];
      attrs.forEach(attr => {
        if (node.hasAttribute(attr)) {
          newElement.setAttribute(attr, node.getAttribute(attr));
        }
      });

      /* Special cases */
      if (tagName === 'a' && !newElement.hasAttribute('target')) {
        newElement.setAttribute('target', '_blank');
        newElement.setAttribute('rel', 'noopener noreferrer');
      }

      if (tagName === 'img') {
        /* Local file path, base64 эсвэл placeholder-тай зурагийг устгах */
        const imgSrc = (node.getAttribute('src') || '').trim();
        const isLocalPath = imgSrc.startsWith('file:') || /^[A-Za-z]:[\\\/]/i.test(imgSrc);
        const isPlaceholder = imgSrc === '#LOCAL_FILE_PLACEHOLDER#' || imgSrc === '#BASE64_PLACEHOLDER#';
        const isBase64 = imgSrc.startsWith('data:image/');
        if (isLocalPath || isPlaceholder || isBase64 || !imgSrc) {
          return null;
        }
        newElement.style.maxWidth = '100%';
        newElement.style.height = 'auto';
      }

      if (tagName === 'table') {
        newElement.style.width = '100%';
        newElement.style.borderCollapse = 'collapse';
        newElement.style.marginBottom = '1rem';
      }

      if (tagName === 'td' || tagName === 'th') {
        newElement.style.border = '1px solid #dee2e6';
        newElement.style.padding = '0.5rem';
      }

      if (tagName === 'p') {
        newElement.style.marginBottom = '1rem';
      }

      /* Child node-уудыг цэвэрлэх */
      Array.from(node.childNodes).forEach(child => {
        const cleaned = cleanNode(child);
        if (cleaned) {
          if (cleaned.nodeType === Node.DOCUMENT_FRAGMENT_NODE) {
            while (cleaned.firstChild) {
              newElement.appendChild(cleaned.firstChild);
            }
          } else {
            newElement.appendChild(cleaned);
          }
        }
      });

      return newElement;
    }

    return null;
  };

  /* Root node-уудыг цэвэрлэх */
  const resultDiv = document.createElement('div');
  Array.from(tempDiv.childNodes).forEach(child => {
    const cleaned = cleanNode(child);
    if (cleaned) {
      if (cleaned.nodeType === Node.DOCUMENT_FRAGMENT_NODE) {
        while (cleaned.firstChild) {
          resultDiv.appendChild(cleaned.firstChild);
        }
      } else {
        resultDiv.appendChild(cleaned);
      }
    }
  });

  return resultDiv;
};
