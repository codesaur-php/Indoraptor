/**
 * moedit UI Components
 *
 * Dialog, Modal болон UI холбоотой функцуудыг агуулна.
 * moedit.js файлын дараа ачаалах шаардлагатай.
 *
 * @requires moedit
 */

/* ============================================
   Paste Handler - Clean HTML автоматаар
   ============================================ */

/**
 * Paste event handler
 * Clipboard-аас буулгахад HTML-ийг автоматаар цэвэрлэнэ
 * @param {ClipboardEvent} e - Paste event
 */
moedit.prototype._handlePaste = function(e) {
  const clipboardData = e.clipboardData || window.clipboardData;
  if (!clipboardData) return;

  /* Зураг байгаа эсэхийг шалгах */
  const items = clipboardData.items;
  if (items) {
    for (let i = 0; i < items.length; i++) {
      if (items[i].type.indexOf('image') !== -1) {
        /* Зураг байвал default paste-г зөвшөөрөх (upload хийгдэнэ) */
        return;
      }
    }
  }

  /* HTML контент авах */
  const html = clipboardData.getData('text/html');
  const plainText = clipboardData.getData('text/plain');

  /* HTML байвал цэвэрлэж оруулах */
  if (html && html.trim()) {
    e.preventDefault();

    /* Clean HTML функц ашиглах */
    const cleanedHtml = this._cleanToVanillaHTML(html);

    /* Selection-д оруулах */
    const selection = window.getSelection();
    if (selection.rangeCount > 0) {
      const range = selection.getRangeAt(0);
      range.deleteContents();

      /* Цэвэрлэсэн HTML оруулах */
      const tempDiv = document.createElement('div');
      tempDiv.innerHTML = cleanedHtml;

      const fragment = document.createDocumentFragment();
      let lastNode = null;
      while (tempDiv.firstChild) {
        lastNode = fragment.appendChild(tempDiv.firstChild);
      }

      range.insertNode(fragment);

      /* Cursor-ийг төгсгөлд байрлуулах */
      if (lastNode) {
        const newRange = document.createRange();
        newRange.setStartAfter(lastNode);
        newRange.collapse(true);
        selection.removeAllRanges();
        selection.addRange(newRange);
      }

      /* onChange дуудах */
      if (typeof this.opts.onChange === 'function') {
        this.opts.onChange(this.getHTML());
      }
    }
    return;
  }

  /* Plain text байвал paragraph болгон оруулах */
  if (plainText && plainText.trim()) {
    e.preventDefault();

    /* Мөр мөрөөр салгаж paragraph болгох */
    const lines = plainText.split(/\r?\n/).filter(line => line.trim());
    let resultHtml = '';

    if (lines.length === 1) {
      /* Нэг мөр бол text node болгох */
      resultHtml = this._escapeHtml(lines[0]);
    } else {
      /* Олон мөр бол paragraph болгох */
      resultHtml = lines.map(line => `<p>${this._escapeHtml(line)}</p>`).join('');
    }

    /* Selection-д оруулах */
    const selection = window.getSelection();
    if (selection.rangeCount > 0) {
      const range = selection.getRangeAt(0);
      range.deleteContents();

      const tempDiv = document.createElement('div');
      tempDiv.innerHTML = resultHtml;

      const fragment = document.createDocumentFragment();
      let lastNode = null;
      while (tempDiv.firstChild) {
        lastNode = fragment.appendChild(tempDiv.firstChild);
      }

      range.insertNode(fragment);

      if (lastNode) {
        const newRange = document.createRange();
        newRange.setStartAfter(lastNode);
        newRange.collapse(true);
        selection.removeAllRanges();
        selection.addRange(newRange);
      }

      if (typeof this.opts.onChange === 'function') {
        this.opts.onChange(this.getHTML());
      }
    }
  }
};

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
  let base64Image = null;
  let isBusy = false;

  const closeDialog = () => dialog.remove();

  browseBtn.addEventListener('click', () => fileInput.click());

  fileInput.addEventListener('change', function() {
    if (this.files && this.files[0]) {
      selectedFile = this.files[0];
      filenameInput.value = selectedFile.name;

      const reader = new FileReader();
      reader.onload = function(e) {
        base64Image = e.target.result;
        previewImg.src = base64Image;
        previewDiv.style.display = 'block';
      };
      reader.readAsDataURL(selectedFile);

      okBtn.disabled = false;
      okBtn.classList.remove('moedit-modal-btn-disabled');
    }
  });

  cancelBtn.addEventListener('click', () => { if (!isBusy) closeDialog(); });
  dialog.addEventListener('click', (e) => { if (e.target === dialog && !isBusy) closeDialog(); });

  const escHandler = (e) => {
    if (e.key === 'Escape' && !isBusy) {
      closeDialog();
      document.removeEventListener('keydown', escHandler);
    }
  };
  document.addEventListener('keydown', escHandler);

  /* Upload товч */
  okBtn.addEventListener('click', () => {
    if (!selectedFile) return;

    /* Бүх товчнуудыг disable хийх */
    isBusy = true;
    okBtn.disabled = true;
    cancelBtn.disabled = true;
    browseBtn.disabled = true;
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
        /* Бүх товчнуудыг enable хийх */
        isBusy = false;
        okBtn.disabled = false;
        cancelBtn.disabled = false;
        browseBtn.disabled = false;
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
      /* Editor-д focus хийж, selection сэргээх */
      this._focusEditor();

      if (savedRange) {
        const sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(savedRange);
      }

      /* Цэвэр текст оруулах - ямар ч HTML tag үүсгэхгүй */
      document.execCommand('insertText', false, cleanText);
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
  let isBusy = false;

  const closeDialog = () => dialog.remove();

  cancelBtn.addEventListener('click', () => { if (!isBusy) closeDialog(); });
  dialog.addEventListener('click', (e) => { if (e.target === dialog && !isBusy) closeDialog(); });

  const escHandler = (e) => {
    if (e.key === 'Escape' && !isBusy) {
      closeDialog();
      document.removeEventListener('keydown', escHandler);
    }
  };
  document.addEventListener('keydown', escHandler);

  /* Shine товч дарахад API дуудах */
  shineBtn.addEventListener('click', async () => {
    /* Бүх товчнуудыг disable хийх */
    isBusy = true;
    shineBtn.disabled = true;
    cancelBtn.disabled = true;
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
        /* Амжилттай үед busy төлвийг цуцлах */
        isBusy = false;
        cancelBtn.disabled = false;

        if (this.opts.notify) {
          this.opts.notify('success', cfg.title, cfg.successMessage);
        }
      } else {
        throw new Error(data.message || cfg.errorMessage);
      }
    } catch (err) {
      errorEl.textContent = err.message || cfg.errorMessage;
      errorEl.style.display = 'block';
      /* Бүх товчнуудыг enable хийх */
      isBusy = false;
      shineBtn.disabled = false;
      cancelBtn.disabled = false;

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

/**
 * HTML-ийг vanilla HTML болгон цэвэрлэх (offline)
 * - БҮХ class, style устгана (зөвхөн шаардлагатай inline style нэмнэ)
 * - Шаардлагагүй wrapper tag-уудыг unwrap хийнэ
 * - Хамгийн энгийн, нүцгэн HTML болгоно
 * @private
 * @param {string} html - Цэвэрлэх HTML
 * @returns {string} Цэвэрлэгдсэн HTML
 */
moedit.prototype._cleanToVanillaHTML = function(html) {
  if (!html || !html.trim()) return '';

  /* Түр div үүсгэж HTML parse хийх */
  const tempDiv = document.createElement('div');
  tempDiv.innerHTML = html;

  /* HTML биш plain text эсэхийг шалгах */
  const hasHtmlTags = /<[a-z][\s\S]*>/i.test(html);
  if (!hasHtmlTags) {
    const lines = html.split(/\n+/).filter(line => line.trim());
    if (lines.length === 0) return '';
    return lines.map(line => `<p>${this._escapeHtml(line.trim())}</p>`).join('\n');
  }

  /* ============================================
     1. Facebook emoji зургийг жинхэнэ emoji болгох
     ============================================ */
  tempDiv.querySelectorAll('img').forEach(img => {
    const src = img.getAttribute('src') || '';
    const alt = img.getAttribute('alt') || '';
    /* Facebook emoji зураг эсэхийг шалгах */
    if ((src.includes('fbcdn.net') || src.includes('facebook.com')) &&
        src.includes('emoji') && alt) {
      /* Зургийг emoji текстээр солих */
      const textNode = document.createTextNode(alt);
      img.parentNode.replaceChild(textNode, img);
    }
  });

  /* ============================================
     2. Facebook tracking URL цэвэрлэх
     ============================================ */
  tempDiv.querySelectorAll('a').forEach(a => {
    let href = a.getAttribute('href') || '';

    /* Facebook redirect URL */
    if (href.includes('l.facebook.com/l.php') || href.includes('lm.facebook.com')) {
      try {
        const url = new URL(href);
        const realUrl = url.searchParams.get('u');
        if (realUrl) {
          href = decodeURIComponent(realUrl.split('?')[0]);
        }
      } catch (e) { /* URL parse алдаа */ }
    }

    /* Facebook tracking параметрүүд устгах */
    const fbTrackingParams = ['__cft__', '__tn__', '__eep__', 'fbclid', '__cft__[0]', '__xts__', 'ref', 'fref', 'hc_ref'];
    try {
      const url = new URL(href, 'https://example.com');
      let hasTracking = false;
      fbTrackingParams.forEach(param => {
        /* [0], [1] гэх мэт indexed параметрүүдийг устгах */
        Array.from(url.searchParams.keys()).forEach(key => {
          if (key === param || key.startsWith(param + '[')) {
            url.searchParams.delete(key);
            hasTracking = true;
          }
        });
      });
      if (hasTracking) {
        /* Query string хоосон болсон бол зөвхөн pathname буцаах */
        href = url.searchParams.toString() ? url.origin + url.pathname + '?' + url.searchParams.toString() : url.origin + url.pathname;
        /* Hashtag хадгалах */
        if (url.hash) href += url.hash;
      }
    } catch (e) { /* URL parse алдаа */ }

    a.setAttribute('href', href);
  });

  /* ============================================
     3. Nested div flatten хийх (зөвхөн нэг div child-тай div-үүдийг unwrap)
     ============================================ */
  const flattenNestedDivs = () => {
    let changed = true;
    while (changed) {
      changed = false;
      tempDiv.querySelectorAll('div').forEach(div => {
        /* Зөвхөн нэг element child байгаа бөгөөд тэр нь div бол */
        const children = Array.from(div.childNodes).filter(n =>
          n.nodeType === Node.ELEMENT_NODE ||
          (n.nodeType === Node.TEXT_NODE && n.textContent.trim())
        );
        if (children.length === 1 && children[0].nodeType === Node.ELEMENT_NODE && children[0].tagName === 'DIV') {
          /* Гадна div-ийг unwrap хийх */
          const parent = div.parentNode;
          if (parent) {
            while (div.firstChild) {
              parent.insertBefore(div.firstChild, div);
            }
            parent.removeChild(div);
            changed = true;
          }
        }
      });
    }
  };
  flattenNestedDivs();

  /* Бүрэн устгах tag-ууд */
  const removeTags = ['SCRIPT', 'STYLE', 'LINK', 'META', 'NOSCRIPT', 'IFRAME', 'OBJECT', 'EMBED', 'APPLET', 'FORM', 'INPUT', 'BUTTON', 'SELECT', 'TEXTAREA', 'LABEL'];

  /* Unwrap хийх tag-ууд (контентыг хадгалж, tag-ийг устгах) */
  const unwrapTags = ['SPAN', 'FONT', 'ASIDE', 'HEADER', 'FOOTER', 'NAV', 'MAIN', 'FIGURE', 'FIGCAPTION', 'CENTER', 'MARK', 'INS', 'DEL', 'S', 'SMALL', 'BIG', 'ABBR', 'ACRONYM', 'CITE', 'DFN', 'KBD', 'SAMP', 'VAR', 'TIME', 'ADDRESS', 'DETAILS', 'SUMMARY', 'DIALOG', 'MENU', 'MENUITEM'];

  /* Хадгалах tag-ууд (semantic, essential) */
  const keepTags = ['P', 'DIV', 'ARTICLE', 'SECTION', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'BR', 'HR', 'UL', 'OL', 'LI', 'TABLE', 'THEAD', 'TBODY', 'TFOOT', 'TR', 'TH', 'TD', 'CAPTION', 'COLGROUP', 'COL', 'A', 'IMG', 'STRONG', 'B', 'EM', 'I', 'U', 'BLOCKQUOTE', 'PRE', 'CODE', 'SUB', 'SUP', 'VIDEO', 'AUDIO', 'SOURCE', 'PICTURE'];

  /**
   * Бүх attribute-уудыг устгах (зөвхөн зөвшөөрөгдсөнийг хадгалах)
   */
  const cleanAttributes = (el) => {
    const allowedAttrs = {
      'A': ['href', 'target', 'rel'],
      'IMG': ['src', 'alt', 'width', 'height'],
      'VIDEO': ['src', 'controls', 'width', 'height'],
      'AUDIO': ['src', 'controls'],
      'SOURCE': ['src', 'type'],
      'TABLE': ['style'],
      'TH': ['style', 'colspan', 'rowspan'],
      'TD': ['style', 'colspan', 'rowspan'],
      'COL': ['span'],
      'COLGROUP': ['span'],
      'BLOCKQUOTE': ['style'],
      'PRE': ['style'],
      'CODE': ['style'],
      'OL': ['start', 'type', 'style'],
      'UL': ['style'],
      'LI': ['style']
    };

    const allowed = allowedAttrs[el.tagName] || [];
    const attrsToRemove = [];

    for (const attr of el.attributes) {
      if (!allowed.includes(attr.name)) {
        attrsToRemove.push(attr.name);
      }
    }
    attrsToRemove.forEach(attr => el.removeAttribute(attr));
  };

  /**
   * Element-ийг unwrap хийх (контентыг хадгалж tag-ийг устгах)
   */
  const unwrapElement = (el) => {
    const parent = el.parentNode;
    if (!parent) return;
    while (el.firstChild) {
      parent.insertBefore(el.firstChild, el);
    }
    parent.removeChild(el);
  };

  /**
   * Recursive цэвэрлэгч
   */
  const processNode = (node) => {
    if (node.nodeType === Node.TEXT_NODE) return;
    if (node.nodeType !== Node.ELEMENT_NODE) {
      node.remove();
      return;
    }

    const tagName = node.tagName;

    /* Word/Office namespace tag устгах */
    if (tagName.includes(':')) {
      unwrapElement(node);
      return;
    }

    /* Бүрэн устгах */
    if (removeTags.includes(tagName)) {
      node.remove();
      return;
    }

    /* Эхлээд хүүхдүүдийг процесслох */
    Array.from(node.childNodes).forEach(child => processNode(child));

    /* Unwrap хийх tag-ууд */
    if (unwrapTags.includes(tagName)) {
      unwrapElement(node);
      return;
    }

    /* Хадгалах tag биш бол unwrap */
    if (!keepTags.includes(tagName)) {
      unwrapElement(node);
      return;
    }

    /* Attribute цэвэрлэх */
    cleanAttributes(node);

    /* Тусгай tag-уудад minimal style нэмэх */
    switch (tagName) {
      case 'TABLE':
        node.setAttribute('style', 'width:100%;border-collapse:collapse;');
        break;
      case 'TH':
        node.setAttribute('style', 'border:1px solid #ddd;padding:8px;background:#f5f5f5;font-weight:bold;text-align:left;');
        break;
      case 'TD':
        node.setAttribute('style', 'border:1px solid #ddd;padding:8px;');
        break;
      case 'IMG':
        node.setAttribute('style', 'max-width:100%;height:auto;');
        if (!node.hasAttribute('alt')) node.setAttribute('alt', '');
        break;
      case 'A':
        if (!node.hasAttribute('target')) node.setAttribute('target', '_blank');
        if (!node.hasAttribute('rel')) node.setAttribute('rel', 'noopener noreferrer');
        break;
      case 'BLOCKQUOTE':
        node.setAttribute('style', 'border-left:3px solid #ccc;padding-left:15px;margin:10px 0;color:#666;');
        break;
      case 'PRE':
        node.setAttribute('style', 'background:#f5f5f5;padding:10px;overflow-x:auto;');
        break;
      case 'CODE':
        node.setAttribute('style', 'background:#f5f5f5;padding:2px 5px;');
        break;
      case 'UL':
      case 'OL':
        node.setAttribute('style', 'padding-left:20px;');
        break;
    }
  };

  /* Процесслох */
  Array.from(tempDiv.childNodes).forEach(child => processNode(child));

  /**
   * Хоосон элементүүдийг устгах
   */
  const removeEmpty = (el) => {
    Array.from(el.children).forEach(child => removeEmpty(child));

    const emptyable = ['P', 'SPAN', 'DIV', 'ARTICLE', 'SECTION', 'B', 'I', 'U', 'STRONG', 'EM', 'LI'];
    if (emptyable.includes(el.tagName) && !el.textContent.trim() && !el.querySelector('img, table, video, audio, br, hr')) {
      el.remove();
    }
  };
  removeEmpty(tempDiv);

  /**
   * DIV-ийг P болгох (зөвхөн inline content агуулсан div)
   */
  const convertDivToP = (el) => {
    Array.from(el.children).forEach(child => convertDivToP(child));

    if (el.tagName === 'DIV') {
      /* DIV дотор block element байгаа эсэхийг шалгах */
      const blockTags = ['P', 'DIV', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'UL', 'OL', 'TABLE', 'BLOCKQUOTE', 'PRE', 'HR', 'ARTICLE', 'SECTION'];
      const hasBlockChild = Array.from(el.children).some(child => blockTags.includes(child.tagName));

      /* Block child байхгүй бол P болгох */
      if (!hasBlockChild && el.textContent.trim()) {
        const p = document.createElement('p');
        while (el.firstChild) {
          p.appendChild(el.firstChild);
        }
        el.parentNode.replaceChild(p, el);
      }
    }
  };
  convertDivToP(tempDiv);

  /**
   * Inline text-ийг p tag-д оруулах
   */
  const wrapTextNodes = () => {
    const children = Array.from(tempDiv.childNodes);
    let inlineGroup = [];

    const flushGroup = () => {
      if (inlineGroup.length === 0) return;
      const hasContent = inlineGroup.some(n => n.nodeType === Node.TEXT_NODE ? n.textContent.trim() : true);
      if (hasContent) {
        const p = document.createElement('p');
        /* p tag-д style нэмэхгүй */
        inlineGroup.forEach(n => p.appendChild(n.cloneNode(true)));
        tempDiv.insertBefore(p, inlineGroup[0]);
      }
      inlineGroup.forEach(n => n.remove());
      inlineGroup = [];
    };

    const blockTags = ['P', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'UL', 'OL', 'TABLE', 'BLOCKQUOTE', 'PRE', 'HR'];

    children.forEach(node => {
      if (node.nodeType === Node.TEXT_NODE) {
        if (node.textContent.trim()) inlineGroup.push(node);
        else node.remove();
      } else if (node.nodeType === Node.ELEMENT_NODE) {
        if (blockTags.includes(node.tagName)) {
          flushGroup();
        } else {
          inlineGroup.push(node);
        }
      }
    });
    flushGroup();
  };
  wrapTextNodes();

  /**
   * Давхар BR-ийг p болгох
   */
  let result = tempDiv.innerHTML;
  result = result.replace(/<br\s*\/?>\s*<br\s*\/?>/gi, '</p><p>');
  result = result.replace(/(<p[^>]*>)\s*<br\s*\/?>/gi, '$1');
  result = result.replace(/<br\s*\/?>\s*(<\/p>)/gi, '$1');

  /* &nbsp; устгах */
  result = result.replace(/&nbsp;/gi, ' ');
  result = result.replace(/\u00A0/g, ' ');

  /* Хоосон tag устгах (space, tab агуулсан ч устгана, newline агуулсан бол үлдээнэ) */
  const emptyTags = ['span', 'font', 'b', 'i', 'u', 'strong', 'em', 'div', 'article', 'section', 'a'];
  let prevResult;
  do {
    prevResult = result;
    emptyTags.forEach(tag => {
      /* Хоосон эсвэл зөвхөн space/tab агуулсан tag устгах */
      result = result.replace(new RegExp(`<${tag}[^>]*>[ \\t]*<\\/${tag}>`, 'gi'), '');
    });
    /* P tag: хоосон эсвэл зөвхөн space/tab агуулсан бол устгах, newline агуулсан бол үлдээх */
    result = result.replace(/<p[^>]*>[ \t]*<\/p>/gi, '');
  } while (result !== prevResult);

  return result.trim();
};

/* ============================================
   AI OCR Dialog (Зураг сонгох → HTML)
   ============================================ */

moedit.prototype._ocr = async function() {
  /* Selection хадгалах */
  let savedRange = null;
  const selection = window.getSelection();
  if (selection.rangeCount > 0) {
    savedRange = selection.getRangeAt(0).cloneRange();
  }

  const cfg = this.opts.ocrModal;
  const shineUrl = this.opts.shineUrl;

  /* Modal үүсгэх */
  const dialogId = 'moedit-ocr-dialog-' + Date.now();
  const dialog = document.createElement('div');
  dialog.id = dialogId;
  dialog.className = 'moedit-modal-overlay';
  dialog.innerHTML = `
    <div class="moedit-modal">
      <h5 class="moedit-modal-title"><i class="bi bi-file-text text-info"></i> ${cfg.title}</h5>
      <p class="moedit-modal-desc">${cfg.description}</p>
      <div class="moedit-modal-field">
        <div class="moedit-modal-file-input">
          <input type="text" class="moedit-modal-input moedit-modal-input-readonly" id="${dialogId}-filename" readonly placeholder="Зураг сонгоно уу...">
          <button type="button" class="moedit-modal-btn moedit-modal-btn-primary" id="${dialogId}-browse">
            <i class="bi bi-folder2-open"></i> Сонгох
          </button>
        </div>
        <input type="file" id="${dialogId}-file" accept="image/*" style="display:none;">
      </div>
      <div class="ocr-preview" id="${dialogId}-preview" style="display:none; margin:10px 0; text-align:center;">
        <img id="${dialogId}-preview-img" src="" style="max-width:100%; max-height:200px; border:1px solid var(--mo-border); border-radius:var(--mo-radius);">
      </div>
      <div class="ocr-status" id="${dialogId}-status" style="display:none;">
        <div style="display:flex; align-items:center; gap:0.5rem;">
          <div class="spinner-border spinner-border-sm text-info" role="status"></div>
          <span>${cfg.processingText}</span>
        </div>
      </div>
      <div class="ocr-result" id="${dialogId}-result" style="display:none; max-height:300px; overflow:auto; border:1px solid var(--mo-border); border-radius:var(--mo-radius); padding:10px; margin-top:10px; background:var(--mo-bg);"></div>
      <div class="ocr-error" id="${dialogId}-error" style="display:none; color:#dc3545; margin-top:10px;"></div>
      <div class="moedit-modal-buttons">
        <button type="button" class="moedit-modal-btn moedit-modal-btn-secondary btn-cancel">${cfg.cancelText}</button>
        <button type="button" class="moedit-modal-btn moedit-modal-btn-info moedit-modal-btn-disabled btn-convert" disabled>
          <i class="bi bi-file-text"></i> ${cfg.confirmText}
        </button>
        <button type="button" class="moedit-modal-btn moedit-modal-btn-success btn-confirm" style="display:none;">
          <i class="bi bi-check-lg"></i> Оруулах
        </button>
      </div>
    </div>
  `;
  document.body.appendChild(dialog);

  const fileInput = dialog.querySelector(`#${dialogId}-file`);
  const filenameInput = dialog.querySelector(`#${dialogId}-filename`);
  const browseBtn = dialog.querySelector(`#${dialogId}-browse`);
  const previewEl = dialog.querySelector(`#${dialogId}-preview`);
  const previewImg = dialog.querySelector(`#${dialogId}-preview-img`);
  const statusEl = dialog.querySelector(`#${dialogId}-status`);
  const resultEl = dialog.querySelector(`#${dialogId}-result`);
  const errorEl = dialog.querySelector(`#${dialogId}-error`);
  const convertBtn = dialog.querySelector('.btn-convert');
  const confirmBtn = dialog.querySelector('.btn-confirm');
  const cancelBtn = dialog.querySelector('.btn-cancel');

  let selectedFile = null;
  let newHtml = null;
  let isBusy = false;

  const closeDialog = () => dialog.remove();

  cancelBtn.addEventListener('click', () => { if (!isBusy) closeDialog(); });
  dialog.addEventListener('click', (e) => { if (e.target === dialog && !isBusy) closeDialog(); });

  const escHandler = (e) => {
    if (e.key === 'Escape' && !isBusy) {
      closeDialog();
      document.removeEventListener('keydown', escHandler);
    }
  };
  document.addEventListener('keydown', escHandler);

  browseBtn.addEventListener('click', () => fileInput.click());

  /* Зураг файл сонгоход */
  fileInput.addEventListener('change', function() {
    if (!this.files || !this.files[0]) return;

    selectedFile = this.files[0];
    filenameInput.value = selectedFile.name;
    errorEl.style.display = 'none';
    resultEl.style.display = 'none';
    convertBtn.style.display = 'inline-flex';
    confirmBtn.style.display = 'none';

    /* Preview харуулах */
    const reader = new FileReader();
    reader.onload = (e) => {
      previewImg.src = e.target.result;
      previewEl.style.display = 'block';
    };
    reader.readAsDataURL(selectedFile);

    /* Convert товч идэвхжүүлэх */
    convertBtn.disabled = false;
    convertBtn.classList.remove('moedit-modal-btn-disabled');
  });

  /* Convert товч дарахад */
  convertBtn.addEventListener('click', async () => {
    if (!selectedFile) return;

    isBusy = true;
    convertBtn.disabled = true;
    cancelBtn.disabled = true;
    browseBtn.disabled = true;
    statusEl.style.display = 'block';
    errorEl.style.display = 'none';
    resultEl.style.display = 'none';

    try {
      /* Зургийг base64 болгох */
      const base64Image = await new Promise((resolve) => {
        const reader = new FileReader();
        reader.onload = (e) => resolve(e.target.result);
        reader.readAsDataURL(selectedFile);
      });

      /* OpenAI Vision API руу илгээх */
      const response = await fetch(shineUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          mode: 'vision',
          images: [base64Image]
        })
      });

      if (!response.ok) {
        throw new Error(`AI OCR алдаа: ${response.status} ${response.statusText}`);
      }

      const data = await response.json();

      if (data.status === 'success' && data.html) {
        newHtml = data.html;

        resultEl.innerHTML = newHtml;
        resultEl.style.display = 'block';
        previewEl.style.display = 'none';
        convertBtn.style.display = 'none';
        confirmBtn.style.display = 'inline-flex';
        isBusy = false;
        cancelBtn.disabled = false;

        if (this.opts.notify) {
          this.opts.notify('success', cfg.successMessage);
        }
      } else if (data.status === 'error') {
        throw new Error(data.message || 'AI OCR алдаа');
      } else {
        throw new Error('Хүлээгдээгүй хариу ирлээ');
      }
    } catch (err) {
      errorEl.textContent = err.message || cfg.errorMessage;
      errorEl.style.display = 'block';
      isBusy = false;
      convertBtn.disabled = false;
      cancelBtn.disabled = false;
      browseBtn.disabled = false;

      if (this.opts.notify) {
        this.opts.notify('danger', err.message || cfg.errorMessage);
      }
    } finally {
      statusEl.style.display = 'none';
    }
  });

  /* Confirm товч - HTML оруулах */
  confirmBtn.addEventListener('click', () => {
    if (newHtml) {
      this._ensureVisualMode();
      this._focusEditor();

      if (savedRange) {
        const sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(savedRange);
      }

      document.execCommand('insertHTML', false, newHtml);
      this._emitChange();
    }

    closeDialog();
    document.removeEventListener('keydown', escHandler);
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
  /* Selection хадгалах */
  let savedRange = null;
  const selection = window.getSelection();
  if (selection.rangeCount > 0) {
    savedRange = selection.getRangeAt(0).cloneRange();
  }

  const config = this.opts.youtubeModal;
  const dialogId = 'moedit-youtube-dialog-' + Date.now();
  const dialog = document.createElement('div');
  dialog.id = dialogId;
  dialog.className = 'moedit-modal-overlay';
  dialog.innerHTML = `
    <div class="moedit-modal">
      <h5 class="moedit-modal-title"><i class="bi bi-youtube"></i> ${config.title}</h5>
      <div class="moedit-modal-field">
        <label class="moedit-modal-label">${config.urlLabel}</label>
        <input type="text" class="moedit-modal-input" id="${dialogId}-url" placeholder="${config.placeholder}">
        <div class="moedit-modal-hint" style="margin-top: 6px; font-size: 12px; color: #888;">${config.hint}</div>
      </div>
      <div class="moedit-modal-preview" id="${dialogId}-preview" style="display: none; margin-top: 12px;">
        <div style="position: relative; width: 100%; padding-bottom: 56.25%; background: #000; border-radius: 6px; overflow: hidden;">
          <iframe id="${dialogId}-iframe" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none;" allowfullscreen></iframe>
        </div>
      </div>
      <div class="moedit-modal-error" id="${dialogId}-error" style="display: none; margin-top: 8px; color: #dc3545; font-size: 13px;"></div>
      <div class="moedit-modal-buttons">
        <button type="button" class="moedit-modal-btn moedit-modal-btn-secondary" id="${dialogId}-cancel">${config.cancelText}</button>
        <button type="button" class="moedit-modal-btn moedit-modal-btn-primary" id="${dialogId}-ok">${config.okText}</button>
      </div>
    </div>
  `;
  document.body.appendChild(dialog);

  const urlInput = document.getElementById(`${dialogId}-url`);
  const previewDiv = document.getElementById(`${dialogId}-preview`);
  const previewIframe = document.getElementById(`${dialogId}-iframe`);
  const errorDiv = document.getElementById(`${dialogId}-error`);
  const okBtn = document.getElementById(`${dialogId}-ok`);
  const cancelBtn = document.getElementById(`${dialogId}-cancel`);

  urlInput.focus();

  /* YouTube видео ID задлах */
  const extractYouTubeId = (url) => {
    const patterns = [
      /(?:youtube\.com\/watch\?v=|youtube\.com\/embed\/|youtu\.be\/|youtube\.com\/v\/|youtube\.com\/shorts\/)([a-zA-Z0-9_-]{11})/,
      /^([a-zA-Z0-9_-]{11})$/
    ];
    for (const pattern of patterns) {
      const match = url.match(pattern);
      if (match) return match[1];
    }
    return null;
  };

  /* URL өөрчлөгдөхөд preview харуулах */
  let debounceTimer;
  urlInput.addEventListener('input', () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
      const url = urlInput.value.trim();
      const videoId = extractYouTubeId(url);
      if (videoId) {
        previewIframe.src = 'https://www.youtube.com/embed/' + videoId;
        previewDiv.style.display = 'block';
        errorDiv.style.display = 'none';
      } else if (url) {
        previewDiv.style.display = 'none';
        errorDiv.textContent = config.invalidUrl;
        errorDiv.style.display = 'block';
      } else {
        previewDiv.style.display = 'none';
        errorDiv.style.display = 'none';
      }
    }, 300);
  });

  const closeDialog = () => {
    previewIframe.src = '';
    dialog.remove();
  };

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
  urlInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      okBtn.click();
    }
  });

  /* OK товч */
  okBtn.addEventListener('click', () => {
    const url = urlInput.value.trim();
    const videoId = extractYouTubeId(url);

    if (!videoId) {
      errorDiv.textContent = config.invalidUrl;
      errorDiv.style.display = 'block';
      urlInput.focus();
      return;
    }

    closeDialog();
    document.removeEventListener('keydown', escHandler);

    /* Selection сэргээх */
    this._ensureVisualMode();
    this._focusEditor();
    if (savedRange) {
      const sel = window.getSelection();
      sel.removeAllRanges();
      sel.addRange(savedRange);
    }

    /* Responsive wrapper div үүсгэх */
    const wrapper = document.createElement('div');
    wrapper.style.position = 'relative';
    wrapper.style.width = '100%';
    wrapper.style.maxWidth = '560px';
    wrapper.style.paddingBottom = '56.25%';
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

    const sel = window.getSelection();
    if (sel.rangeCount > 0) {
      const range = sel.getRangeAt(0);
      range.insertNode(wrapper);
      range.setStartAfter(wrapper);
      range.collapse(true);
      sel.removeAllRanges();
      sel.addRange(range);
    }

    this._emitChange();
  });
};

moedit.prototype._insertFacebook = function() {
  /* Selection хадгалах */
  let savedRange = null;
  const selection = window.getSelection();
  if (selection.rangeCount > 0) {
    savedRange = selection.getRangeAt(0).cloneRange();
  }

  const config = this.opts.facebookModal;
  const dialogId = 'moedit-facebook-dialog-' + Date.now();
  const dialog = document.createElement('div');
  dialog.id = dialogId;
  dialog.className = 'moedit-modal-overlay';
  dialog.innerHTML = `
    <div class="moedit-modal">
      <h5 class="moedit-modal-title"><i class="bi bi-facebook"></i> ${config.title}</h5>
      <div class="moedit-modal-field">
        <label class="moedit-modal-label">${config.urlLabel}</label>
        <input type="text" class="moedit-modal-input" id="${dialogId}-url" placeholder="${config.placeholder}">
        <div class="moedit-modal-hint" style="margin-top: 6px; font-size: 12px; color: #888;">${config.hint}</div>
      </div>
      <div class="moedit-modal-preview" id="${dialogId}-preview" style="display: none; margin-top: 12px;">
        <div style="position: relative; width: 100%; padding-bottom: 56.25%; background: #f0f2f5; border-radius: 6px; overflow: hidden;">
          <iframe id="${dialogId}-iframe" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none;" scrolling="no" allowfullscreen></iframe>
        </div>
      </div>
      <div class="moedit-modal-buttons">
        <button type="button" class="moedit-modal-btn moedit-modal-btn-secondary" id="${dialogId}-cancel">${config.cancelText}</button>
        <button type="button" class="moedit-modal-btn moedit-modal-btn-primary" id="${dialogId}-ok">${config.okText}</button>
      </div>
    </div>
  `;
  document.body.appendChild(dialog);

  const urlInput = document.getElementById(`${dialogId}-url`);
  const previewDiv = document.getElementById(`${dialogId}-preview`);
  const previewIframe = document.getElementById(`${dialogId}-iframe`);
  const okBtn = document.getElementById(`${dialogId}-ok`);
  const cancelBtn = document.getElementById(`${dialogId}-cancel`);

  urlInput.focus();

  /* Facebook URL зөв эсэхийг шалгах */
  const isValidFacebookUrl = (url) => {
    return url.includes('facebook.com') || url.includes('fb.watch');
  };

  /* URL өөрчлөгдөхөд preview харуулах */
  let debounceTimer;
  urlInput.addEventListener('input', () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
      const url = urlInput.value.trim();
      if (url && isValidFacebookUrl(url)) {
        previewIframe.src = 'https://www.facebook.com/plugins/video.php?href=' + encodeURIComponent(url) + '&show_text=0&width=500';
        previewDiv.style.display = 'block';
      } else {
        previewDiv.style.display = 'none';
      }
    }, 500);
  });

  const closeDialog = () => {
    previewIframe.src = '';
    dialog.remove();
  };

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
  urlInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      okBtn.click();
    }
  });

  /* OK товч */
  okBtn.addEventListener('click', () => {
    const url = urlInput.value.trim();

    if (!url) {
      urlInput.focus();
      return;
    }

    closeDialog();
    document.removeEventListener('keydown', escHandler);

    /* Selection сэргээх */
    this._ensureVisualMode();
    this._focusEditor();
    if (savedRange) {
      const sel = window.getSelection();
      sel.removeAllRanges();
      sel.addRange(savedRange);
    }

    /* Responsive wrapper div үүсгэх */
    const wrapper = document.createElement('div');
    wrapper.style.position = 'relative';
    wrapper.style.width = '100%';
    wrapper.style.maxWidth = '560px';
    wrapper.style.paddingBottom = '56.25%';
    wrapper.style.margin = '10px 0';
    wrapper.style.height = '0';
    wrapper.style.overflow = 'hidden';

    const iframe = document.createElement('iframe');
    iframe.src = 'https://www.facebook.com/plugins/video.php?href=' + encodeURIComponent(url) + '&show_text=0&width=560';
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

    const sel = window.getSelection();
    if (sel.rangeCount > 0) {
      const range = sel.getRangeAt(0);
      range.insertNode(wrapper);
      range.setStartAfter(wrapper);
      range.collapse(true);
      sel.removeAllRanges();
      sel.addRange(range);
    }

    this._emitChange();
  });
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

/* ============================================
   PDF to HTML Dialog
   ============================================ */

moedit.prototype._insertPdf = async function() {
  /* Selection хадгалах */
  let savedRange = null;
  const selection = window.getSelection();
  if (selection.rangeCount > 0) {
    savedRange = selection.getRangeAt(0).cloneRange();
  }

  const config = this.opts.pdfModal;

  /* PDF.js сан ачаалагдсан эсэхийг шалгах */
  const pdfjsLib = window.pdfjsLib;
  if (!pdfjsLib) {
    const msg = 'PDF.js сан ачаалаагүй байна. HTML head-д дараах script-үүдийг нэмнэ үү:\n' +
      '<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.0.379/pdf.min.mjs" type="module"></script>';
    if (this.opts.notify) {
      this.opts.notify('warning', config.title, msg);
    } else {
      alert(msg);
    }
    return;
  }

  /* AI OCR боломжтой эсэх (shineUrl тохируулсан бол) */
  const shineUrl = this.opts.shineUrl;
  const hasAiOcrSupport = shineUrl && shineUrl.trim() && shineUrl.trim();

  /* PDF → HTML зөвхөн AI OCR ашиглана */
  if (!hasAiOcrSupport) {
    const msg = 'AI OCR тохируулаагүй байна. PDF → HTML ашиглахын тулд shineUrl тохируулна уу.';
    if (this.opts.notify) {
      this.opts.notify('warning', config.title, msg);
    } else {
      alert(msg);
    }
    return;
  }

  /* Modal үүсгэх */
  const dialogId = 'moedit-pdf-dialog-' + Date.now();
  const dialog = document.createElement('div');
  dialog.id = dialogId;
  dialog.className = 'moedit-modal-overlay';
  dialog.innerHTML = `
    <div class="moedit-modal moedit-modal-lg">
      <h5 class="moedit-modal-title"><i class="bi bi-file-earmark-pdf text-danger"></i> ${config.title}</h5>
      <p class="moedit-modal-desc">${config.description}</p>
      <div class="moedit-modal-field">
        <div class="moedit-modal-file-input">
          <input type="text" class="moedit-modal-input moedit-modal-input-readonly" id="${dialogId}-filename" readonly placeholder="${config.placeholder}">
          <button type="button" class="moedit-modal-btn moedit-modal-btn-primary" id="${dialogId}-browse">
            <i class="bi bi-folder2-open"></i> ${config.browseText}
          </button>
        </div>
        <input type="file" id="${dialogId}-file" accept=".pdf,application/pdf" style="display:none;">
      </div>
      <div class="pdf-info" id="${dialogId}-info" style="display:none; margin:10px 0; padding:10px; background:var(--mo-bg); border:1px solid var(--mo-border); border-radius:var(--mo-radius);">
        <small class="text-muted"></small>
      </div>
      <div class="pdf-pages-container" id="${dialogId}-pages-container" style="display:none; margin:10px 0;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
          <small class="text-muted">Хуудас сонгох:</small>
          <div>
            <button type="button" class="moedit-modal-btn moedit-modal-btn-sm moedit-modal-btn-secondary" id="${dialogId}-select-all">Бүгдийг</button>
            <button type="button" class="moedit-modal-btn moedit-modal-btn-sm moedit-modal-btn-secondary" id="${dialogId}-select-none">Арилгах</button>
          </div>
        </div>
        <div class="pdf-pages-grid" id="${dialogId}-pages" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(120px, 1fr)); gap:10px; max-height:300px; overflow-y:auto; padding:5px;"></div>
        <div class="pdf-pages-loading" id="${dialogId}-pages-loading" style="display:none; text-align:center; padding:20px;">
          <div class="spinner-border spinner-border-sm text-danger" role="status"></div>
          <span class="ms-2">Хуудсуудыг ачаалж байна...</span>
        </div>
      </div>
      <div class="pdf-status" id="${dialogId}-status" style="display:none;">
        <div style="display:flex; align-items:center; gap:0.5rem;">
          <div class="spinner-border spinner-border-sm text-danger" role="status"></div>
          <span id="${dialogId}-status-text">${config.processingText}</span>
        </div>
        <div class="progress mt-2" style="height:4px; display:none;" id="${dialogId}-progress">
          <div class="progress-bar bg-danger" role="progressbar" style="width:0%"></div>
        </div>
      </div>
      <div class="pdf-result" id="${dialogId}-result" style="display:none; max-height:300px; overflow:auto; border:1px solid var(--mo-border); border-radius:var(--mo-radius); padding:10px; margin-top:10px; background:var(--mo-bg);"></div>
      <div class="pdf-error" id="${dialogId}-error" style="display:none; color:#dc3545; margin-top:10px;"></div>
      <div class="moedit-modal-buttons">
        <button type="button" class="moedit-modal-btn moedit-modal-btn-secondary btn-cancel">${config.cancelText}</button>
        <button type="button" class="moedit-modal-btn moedit-modal-btn-danger moedit-modal-btn-disabled btn-convert" disabled>
          <i class="bi bi-file-earmark-pdf"></i> ${config.title}
        </button>
        <button type="button" class="moedit-modal-btn moedit-modal-btn-success btn-confirm" style="display:none;">
          <i class="bi bi-check-lg"></i> ${config.confirmText}
        </button>
      </div>
    </div>
  `;
  document.body.appendChild(dialog);

  const fileInput = dialog.querySelector(`#${dialogId}-file`);
  const filenameInput = dialog.querySelector(`#${dialogId}-filename`);
  const browseBtn = dialog.querySelector(`#${dialogId}-browse`);
  const infoEl = dialog.querySelector(`#${dialogId}-info`);
  const pagesContainerEl = dialog.querySelector(`#${dialogId}-pages-container`);
  const pagesGridEl = dialog.querySelector(`#${dialogId}-pages`);
  const pagesLoadingEl = dialog.querySelector(`#${dialogId}-pages-loading`);
  const selectAllBtn = dialog.querySelector(`#${dialogId}-select-all`);
  const selectNoneBtn = dialog.querySelector(`#${dialogId}-select-none`);
  const statusEl = dialog.querySelector(`#${dialogId}-status`);
  const statusTextEl = dialog.querySelector(`#${dialogId}-status-text`);
  const progressEl = dialog.querySelector(`#${dialogId}-progress`);
  const progressBar = progressEl.querySelector('.progress-bar');
  const resultEl = dialog.querySelector(`#${dialogId}-result`);
  const errorEl = dialog.querySelector(`#${dialogId}-error`);
  const convertBtn = dialog.querySelector('.btn-convert');
  const confirmBtn = dialog.querySelector('.btn-confirm');
  const cancelBtn = dialog.querySelector('.btn-cancel');
  /* PDF → HTML зөвхөн AI OCR ашиглана */
  const getSelectedOcrMethod = () => 'ai';

  let selectedFile = null;
  let newHtml = null;
  let isBusy = false;
  let pdfDoc = null;  /* Ачаалсан PDF document */
  let selectedPages = new Set();  /* Сонгосон хуудсууд */

  const closeDialog = () => dialog.remove();

  cancelBtn.addEventListener('click', () => { if (!isBusy) closeDialog(); });
  dialog.addEventListener('click', (e) => { if (e.target === dialog && !isBusy) closeDialog(); });

  const escHandler = (e) => {
    if (e.key === 'Escape' && !isBusy) {
      closeDialog();
      document.removeEventListener('keydown', escHandler);
    }
  };
  document.addEventListener('keydown', escHandler);

  browseBtn.addEventListener('click', () => fileInput.click());

  /**
   * Хуудасны thumbnail үүсгэх
   */
  const renderPageThumbnail = async (page, pageNum) => {
    const scale = 0.3;  /* Жижиг thumbnail */
    const viewport = page.getViewport({ scale });
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    canvas.width = viewport.width;
    canvas.height = viewport.height;

    await page.render({
      canvasContext: ctx,
      viewport: viewport
    }).promise;

    return canvas.toDataURL('image/jpeg', 0.7);
  };

  /**
   * Хуудсуудын preview харуулах
   */
  const loadPdfPages = async (file) => {
    pagesContainerEl.style.display = 'block';
    pagesLoadingEl.style.display = 'block';
    pagesGridEl.innerHTML = '';
    selectedPages.clear();

    try {
      const arrayBuffer = await file.arrayBuffer();
      const loadingTask = pdfjsLib.getDocument({
        data: arrayBuffer,
        cMapUrl: 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.0.379/cmaps/',
        cMapPacked: true
      });

      pdfDoc = await loadingTask.promise;
      const totalPages = pdfDoc.numPages;

      for (let pageNum = 1; pageNum <= totalPages; pageNum++) {
        const page = await pdfDoc.getPage(pageNum);
        const thumbnail = await renderPageThumbnail(page, pageNum);

        /* Хуудасны card үүсгэх */
        const pageCard = document.createElement('div');
        pageCard.className = 'pdf-page-card';
        pageCard.dataset.page = pageNum;
        pageCard.style.cssText = 'position:relative; border:2px solid var(--mo-border); border-radius:var(--mo-radius); overflow:hidden; cursor:pointer; transition:all 0.2s;';
        pageCard.innerHTML = `
          <img src="${thumbnail}" alt="Хуудас ${pageNum}" style="width:100%; display:block;">
          <div class="pdf-page-overlay" style="position:absolute; top:0; left:0; right:0; bottom:0; background:rgba(220,53,69,0.3); display:none;"></div>
          <div class="pdf-page-check" style="position:absolute; top:5px; right:5px; width:20px; height:20px; background:#dc3545; border-radius:50%; display:none; align-items:center; justify-content:center;">
            <i class="bi bi-check" style="color:white; font-size:14px;"></i>
          </div>
          <div style="position:absolute; bottom:0; left:0; right:0; background:rgba(0,0,0,0.7); color:white; text-align:center; padding:2px; font-size:11px;">
            ${pageNum}
          </div>
        `;

        /* Click event - сонгох/сонголт цуцлах */
        pageCard.addEventListener('click', () => {
          const isSelected = selectedPages.has(pageNum);
          if (isSelected) {
            selectedPages.delete(pageNum);
            pageCard.querySelector('.pdf-page-overlay').style.display = 'none';
            pageCard.querySelector('.pdf-page-check').style.display = 'none';
            pageCard.style.borderColor = 'var(--mo-border)';
          } else {
            selectedPages.add(pageNum);
            pageCard.querySelector('.pdf-page-overlay').style.display = 'block';
            pageCard.querySelector('.pdf-page-check').style.display = 'flex';
            pageCard.style.borderColor = '#dc3545';
          }
          updateConvertButton();
        });

        pagesGridEl.appendChild(pageCard);

        /* Бүх хуудсыг анхнаасаа сонгох */
        selectedPages.add(pageNum);
        pageCard.querySelector('.pdf-page-overlay').style.display = 'block';
        pageCard.querySelector('.pdf-page-check').style.display = 'flex';
        pageCard.style.borderColor = '#dc3545';
      }

      updateConvertButton();
    } catch (err) {
      errorEl.textContent = 'PDF ачаалахад алдаа: ' + err.message;
      errorEl.style.display = 'block';
    } finally {
      pagesLoadingEl.style.display = 'none';
    }
  };

  /**
   * Convert товчны төлөв шинэчлэх
   */
  const updateConvertButton = () => {
    if (selectedPages.size > 0) {
      convertBtn.disabled = false;
      convertBtn.classList.remove('moedit-modal-btn-disabled');
      convertBtn.innerHTML = `<i class="bi bi-file-earmark-pdf"></i> ${config.title} (${selectedPages.size} хуудас)`;
    } else {
      convertBtn.disabled = true;
      convertBtn.classList.add('moedit-modal-btn-disabled');
      convertBtn.innerHTML = `<i class="bi bi-file-earmark-pdf"></i> ${config.title}`;
    }
  };

  /**
   * Бүх хуудсыг сонгох/цуцлах
   */
  const selectAllPages = (select) => {
    const cards = pagesGridEl.querySelectorAll('.pdf-page-card');
    cards.forEach(card => {
      const pageNum = parseInt(card.dataset.page);
      if (select) {
        selectedPages.add(pageNum);
        card.querySelector('.pdf-page-overlay').style.display = 'block';
        card.querySelector('.pdf-page-check').style.display = 'flex';
        card.style.borderColor = '#dc3545';
      } else {
        selectedPages.delete(pageNum);
        card.querySelector('.pdf-page-overlay').style.display = 'none';
        card.querySelector('.pdf-page-check').style.display = 'none';
        card.style.borderColor = 'var(--mo-border)';
      }
    });
    updateConvertButton();
  };

  selectAllBtn.addEventListener('click', () => selectAllPages(true));
  selectNoneBtn.addEventListener('click', () => selectAllPages(false));

  /* PDF файл сонгоход */
  fileInput.addEventListener('change', async function() {
    if (!this.files || !this.files[0]) return;

    selectedFile = this.files[0];
    filenameInput.value = selectedFile.name;
    errorEl.style.display = 'none';
    resultEl.style.display = 'none';
    convertBtn.style.display = 'inline-block';
    confirmBtn.style.display = 'none';

    /* Файлын хэмжээг харуулах */
    const sizeKB = (selectedFile.size / 1024).toFixed(1);
    const sizeMB = (selectedFile.size / (1024 * 1024)).toFixed(2);
    const sizeText = selectedFile.size > 1024 * 1024 ? `${sizeMB} MB` : `${sizeKB} KB`;
    infoEl.querySelector('small').textContent = `${selectedFile.name} (${sizeText})`;
    infoEl.style.display = 'block';

    /* Хуудсуудыг preview харуулах */
    await loadPdfPages(selectedFile);
  });

  /**
   * PDF.js ашиглан PDF-ийг текст болгох (Frontend)
   * Монгол Кирилл болон бусад Unicode текстийг зөв задална
   */
  const extractTextWithPdfJs = async (file) => {
    const arrayBuffer = await file.arrayBuffer();

    /* PDF.js-р PDF ачаалах */
    const loadingTask = pdfjsLib.getDocument({
      data: arrayBuffer,
      /* CMap (Character Map) - Кирилл, Монгол үсгийг зөв хөрвүүлэхэд шаардлагатай */
      cMapUrl: 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.0.379/cmaps/',
      cMapPacked: true,
      /* Standard fonts - суулгасан фонт байхгүй үед ашиглана */
      standardFontDataUrl: 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.0.379/standard_fonts/'
    });

    const pdf = await loadingTask.promise;
    const totalPages = pdf.numPages;
    const pageTexts = [];

    progressEl.style.display = 'block';

    for (let pageNum = 1; pageNum <= totalPages; pageNum++) {
      /* Progress шинэчлэх */
      const progress = Math.round((pageNum / totalPages) * 100);
      progressBar.style.width = progress + '%';
      statusTextEl.textContent = `${config.renderingText || 'Хуудас уншиж байна...'} (${pageNum}/${totalPages})`;

      const page = await pdf.getPage(pageNum);
      const textContent = await page.getTextContent();

      /* Текст items-ийг нэгтгэх */
      let pageText = '';
      let lastY = null;
      let lastX = null;

      for (const item of textContent.items) {
        if (item.str === undefined) continue;

        const currentY = item.transform[5];
        const currentX = item.transform[4];

        /* Шинэ мөр эсвэл параграф илрүүлэх */
        if (lastY !== null) {
          const yDiff = Math.abs(currentY - lastY);
          /* Y координат өөрчлөгдвөл шинэ мөр */
          if (yDiff > 5) {
            /* Их зай байвал параграф, бага бол шинэ мөр */
            pageText += yDiff > 15 ? '\n\n' : '\n';
          } else if (lastX !== null && currentX - lastX > 20) {
            /* X координат их өөрчлөгдвөл tab (хүснэгт) */
            pageText += '\t';
          } else if (item.str && !item.str.startsWith(' ') && pageText && !pageText.endsWith(' ') && !pageText.endsWith('\n')) {
            /* Үгс хооронд зай нэмэх */
            pageText += ' ';
          }
        }

        pageText += item.str;
        lastY = currentY;
        lastX = currentX + (item.width || 0);
      }

      if (pageText.trim()) {
        pageTexts.push({
          pageNum: pageNum,
          text: pageText.trim()
        });
      }
    }

    return {
      pages: totalPages,
      content: pageTexts
    };
  };

  /**
   * Текстийг HTML болгох
   */
  const textToHtml = (text) => {
    /* Windows мөр төгсгөлийг нэгтгэх */
    text = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n');

    /* Хоосон мөрүүдээр параграф болгох */
    const paragraphs = text.split(/\n{2,}/);
    let html = '';

    for (const para of paragraphs) {
      const trimmed = para.trim();
      if (!trimmed) continue;

      /* Tab байвал хүснэгт гэж үзэх */
      if (trimmed.includes('\t')) {
        html += tabsToTable(trimmed);
      } else {
        /* Нэг мөрөн дотор newline-уудыг <br> болгох */
        const escaped = escapeHtml(trimmed);
        const withBr = escaped.replace(/\n/g, '<br>');
        html += `<p>${withBr}</p>\n`;
      }
    }

    return html;
  };

  /**
   * Tab-аар тусгаарлагдсан текстийг хүснэгт болгох
   */
  const tabsToTable = (text) => {
    const lines = text.split('\n').filter(l => l.trim());
    if (lines.length === 0) return '';

    let html = '<div class="table-responsive">\n';
    html += '<table class="table table-striped table-hover table-bordered">\n';

    lines.forEach((line, idx) => {
      const cells = line.split(/\t+/).map(c => c.trim());

      if (idx === 0 && lines.length > 1) {
        /* Эхний мөрийг header болгох */
        html += '<thead><tr>\n';
        cells.forEach(cell => {
          html += `<th>${escapeHtml(cell)}</th>\n`;
        });
        html += '</tr></thead>\n<tbody>\n';
      } else {
        html += '<tr>\n';
        cells.forEach(cell => {
          html += `<td>${escapeHtml(cell)}</td>\n`;
        });
        html += '</tr>\n';
      }
    });

    if (lines.length > 1) {
      html += '</tbody>\n';
    }
    html += '</table>\n</div>\n';

    return html;
  };

  /**
   * HTML escape
   */
  const escapeHtml = (text) => {
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return text.replace(/[&<>"']/g, c => map[c]);
  };

  /**
   * PDF хуудсыг canvas болгох helper функц
   */
  const renderPageToCanvas = async (page, scale = 2) => {
    const viewport = page.getViewport({ scale });
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    canvas.width = viewport.width;
    canvas.height = viewport.height;

    await page.render({
      canvasContext: ctx,
      viewport: viewport
    }).promise;

    return canvas;
  };

  /**
   * Tesseract.js ашиглан OCR хийх (Үнэгүй, browser дээр)
   * Монгол Кирилл дэмждэг
   */
  const extractTextWithTesseract = async (file) => {
    const arrayBuffer = await file.arrayBuffer();

    const loadingTask = pdfjsLib.getDocument({
      data: arrayBuffer,
      cMapUrl: 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.0.379/cmaps/',
      cMapPacked: true
    });

    const pdf = await loadingTask.promise;
    const totalPages = pdf.numPages;
    const pageTexts = [];

    progressEl.style.display = 'block';

    /* Tesseract worker үүсгэх - ЗӨВХӨН Орос/Кирилл */
    statusTextEl.textContent = 'Tesseract OCR ачаалж байна...';

    const worker = await Tesseract.createWorker('rus', 1, {
      logger: m => {
        if (m.status === 'recognizing text') {
          const pageProgress = Math.round(m.progress * 100);
          statusTextEl.textContent = `OCR боловсруулж байна... ${pageProgress}%`;
        }
      },
      errorHandler: err => console.error('Tesseract error:', err)
    });

    /* Кирилл таних тохиргоо - Зөвхөн Кирилл үсэг зөвшөөрөх */
    const cyrillicChars = 'АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯабвгдеёжзийклмнопрстуфхцчшщъыьэюя' +
                          'ӨҮөү' +  /* Монгол тусгай үсгүүд */
                          '0123456789' +
                          '.,;:!?()-–—«»""\'\'/ \n';

    await worker.setParameters({
      tessedit_pageseg_mode: Tesseract.PSM.AUTO,
      preserve_interword_spaces: '1',
      tessedit_char_whitelist: cyrillicChars,  /* Зөвхөн эдгээр тэмдэгт зөвшөөрөх */
    });

    try {
      for (let pageNum = 1; pageNum <= totalPages; pageNum++) {
        const overallProgress = Math.round(((pageNum - 1) / totalPages) * 100);
        progressBar.style.width = overallProgress + '%';
        statusTextEl.textContent = `Хуудас ${pageNum}/${totalPages} боловсруулж байна...`;

        const page = await pdf.getPage(pageNum);
        /* 3x scale - илүү тод зураг, OCR илүү сайн ажиллана */
        const canvas = await renderPageToCanvas(page, 3);

        /* Зургийг grayscale болгох - OCR илүү сайн ажиллана */
        const ctx = canvas.getContext('2d');
        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        const data = imageData.data;
        for (let i = 0; i < data.length; i += 4) {
          const gray = data[i] * 0.299 + data[i + 1] * 0.587 + data[i + 2] * 0.114;
          /* Contrast нэмэх */
          const contrast = 1.2;
          const adjusted = ((gray - 128) * contrast) + 128;
          const final = Math.max(0, Math.min(255, adjusted));
          data[i] = data[i + 1] = data[i + 2] = final;
        }
        ctx.putImageData(imageData, 0, 0);

        /* Tesseract OCR */
        const { data: { text } } = await worker.recognize(canvas);

        if (text && text.trim()) {
          /* Монгол Ү, Ө засвар - Орос У, О-г Монгол үгсэд солих */
          const correctedText = fixMongolianCyrillic(text.trim());
          pageTexts.push({
            pageNum: pageNum,
            text: correctedText
          });
        }
      }
    } finally {
      await worker.terminate();
    }

    return {
      pages: totalPages,
      content: pageTexts
    };
  };

  /**
   * Монгол Кирилл Ү, Ө засвар
   * Орос У → Монгол Ү, Орос О → Монгол Ө (түгээмэл үгсэд)
   */
  const fixMongolianCyrillic = (text) => {
    /* Ү агуулсан түгээмэл Монгол үгс/үе (У → Ү) */
    const uToY = [
      /* Үйл үг төгсгөлүүд */
      [/улэх/gi, 'үлэх'], [/улээ/gi, 'үлээ'], [/улсэн/gi, 'үлсэн'],
      [/у|лж/gi, 'үлж'], [/улдэг/gi, 'үлдэг'], [/улна/gi, 'үлнэ'],
      /* Нэр үг төгсгөлүүд */
      [/у|уд/gi, 'үүд'], [/уулэн/gi, 'үүлэн'],
      /* Түгээмэл үгс */
      [/\bу|г\b/gi, 'үг'], [/\bуйл\b/gi, 'үйл'], [/уйлдэл/gi, 'үйлдэл'],
      [/\bун[эе]н/gi, 'үнэн'], [/\bунэ\b/gi, 'үнэ'], [/унэлгээ/gi, 'үнэлгээ'],
      [/\bуз[эе]/gi, 'үзэ'], [/узуулэ/gi, 'үзүүлэ'], [/узэл/gi, 'үзэл'],
      [/\bур\b/gi, 'үр'], [/урэ/gi, 'үрэ'], [/ур дун/gi, 'үр дүн'],
      [/\bус[эе]г/gi, 'үсэг'], [/\bудэс/gi, 'үдэс'], [/\bуед/gi, 'үед'],
      [/хуртэл/gi, 'хүртэл'], [/хурээ/gi, 'хүрээ'], [/хундэтг/gi, 'хүндэтг'],
      [/\bхун\b/gi, 'хүн'], [/хумуус/gi, 'хүмүүс'], [/хучин/gi, 'хүчин'],
      [/\bбут[эе]/gi, 'бүтэ'], [/бурэн/gi, 'бүрэн'], [/бугд/gi, 'бүгд'],
      [/\bбур/gi, 'бүр'], [/бутээ/gi, 'бүтээ'],
      [/тухай/gi, 'түхай'], [/тувшин/gi, 'түвшин'], [/турул/gi, 'түрүүл'],
      [/тургэн/gi, 'түргэн'],
      [/нухцэл/gi, 'нөхцөл'], [/нуур/gi, 'нүүр'],
      [/суул/gi, 'сүүл'], [/сулжээ/gi, 'сүлжээ'],
      [/\bзуй\b/gi, 'зүй'], [/зуйл/gi, 'зүйл'],
      [/оноодор/gi, 'өнөөдөр'], [/онгорсон/gi, 'өнгөрсөн'],
      [/дуурэн/gi, 'дүүрэн'], [/\bдун\b/gi, 'дүн'],
      [/гуйцэт/gi, 'гүйцэт'], [/гунзгий/gi, 'гүнзгий'],
      [/муний/gi, 'мүний'], [/мунгу/gi, 'мөнгө'],
      [/шуу\b/gi, 'шүү'], [/шууд/gi, 'шүүд'],
      [/эруул/gi, 'эрүүл'],
    ];

    /* Ө агуулсан түгээмэл Монгол үгс (О → Ө) */
    const oToO = [
      /* Түгээмэл үгс */
      [/\bор\b/gi, 'өр'], [/оргоо/gi, 'өргөө'], [/оргон/gi, 'өргөн'],
      [/оргож/gi, 'өргөж'], [/оргох/gi, 'өргөх'],
      [/\bомно/gi, 'өмнө'], [/\bондор/gi, 'өндөр'], [/\bонго/gi, 'өнгө'],
      [/оноо\b/gi, 'өнөө'], [/онгорс/gi, 'өнгөрс'],
      [/\bоор\b/gi, 'өөр'], [/оорчло/gi, 'өөрчлө'], [/\bоортоо/gi, 'өөртөө'],
      [/орсолд/gi, 'өрсөлд'], [/осгох/gi, 'өсгөх'], [/осолт/gi, 'өсөлт'],
      [/отгон/gi, 'өтгөн'], [/одор/gi, 'өдөр'], [/одоо/gi, 'өдөө'],
      [/\bогох/gi, 'өгөх'], [/\bогсон/gi, 'өгсөн'], [/\bогно/gi, 'өгнө'],
      [/голбор/gi, 'гөлбөр'],
      [/толбор/gi, 'төлбөр'], [/толов/gi, 'төлөв'], [/торол/gi, 'төрөл'],
      [/\bтов\b/gi, 'төв'], [/товлор/gi, 'төвлөр'],
      [/болон\b/gi, 'болон'], /* энийг хэвээр үлдээх */
      [/\bмонго/gi, 'мөнгө'], [/монгол/gi, 'Монгол'], /* Монгол хэвээр */
      [/хоног/gi, 'хоног'], /* хэвээр */
      [/нохцол/gi, 'нөхцөл'], [/ноло/gi, 'нөлө'], [/ногоо/gi, 'ногоо'],
      [/соним/gi, 'сөним'], [/\bсо\b/gi, 'сө'],
      [/хоорон/gi, 'хооронд'], /* хэвээр */
      [/хогжил/gi, 'хөгжил'], [/хотолбор/gi, 'хөтөлбөр'],
      [/зохион/gi, 'зохион'], /* хэвээр */
      [/зовшоор/gi, 'зөвшөөр'], [/зовлол/gi, 'зөвлөл'], [/зовлогоо/gi, 'зөвлөгөө'],
    ];

    let result = text;

    /* У → Ү солих */
    for (const [pattern, replacement] of uToY) {
      result = result.replace(pattern, replacement);
    }

    /* О → Ө солих */
    for (const [pattern, replacement] of oToO) {
      result = result.replace(pattern, replacement);
    }

    return result;
  };

  /**
   * AI OCR ашиглан текст задлах (OpenAI Vision)
   * Зөвхөн сонгосон хуудсуудыг боловсруулна
   */
  const extractTextWithAiOcr = async () => {
    if (!pdfDoc) {
      throw new Error('PDF ачаалаагүй байна');
    }

    /* Сонгосон хуудсуудыг эрэмбэлэх */
    const pagesToProcess = Array.from(selectedPages).sort((a, b) => a - b);
    const totalSelected = pagesToProcess.length;
    const htmlParts = [];

    progressEl.style.display = 'block';

    for (let i = 0; i < totalSelected; i++) {
      const pageNum = pagesToProcess[i];
      const progress = Math.round(((i + 1) / totalSelected) * 100);
      progressBar.style.width = progress + '%';
      statusTextEl.textContent = `AI OCR боловсруулж байна... (${i + 1}/${totalSelected} - хуудас ${pageNum})`;

      const page = await pdfDoc.getPage(pageNum);
      const canvas = await renderPageToCanvas(page, 2);
      const base64Image = canvas.toDataURL('image/png');

      const response = await fetch(shineUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          mode: 'vision',
          images: [base64Image]
        })
      });

      if (!response.ok) {
        throw new Error(`AI OCR алдаа: ${response.status} ${response.statusText}`);
      }

      const data = await response.json();

      if (data.status === 'success' && data.html) {
        if (totalSelected > 1) {
          htmlParts.push(`<!-- Хуудас ${pageNum} -->\n${data.html}`);
        } else {
          htmlParts.push(data.html);
        }
      } else if (data.status === 'error') {
        throw new Error(data.message || 'AI OCR алдаа');
      }
    }

    return {
      pages: totalSelected,
      html: htmlParts.join('\n\n<hr class="my-4">\n\n')
    };
  };

  /* Convert товч дарахад */
  convertBtn.addEventListener('click', async () => {
    if (!selectedFile) return;

    const ocrMethod = getSelectedOcrMethod();

    isBusy = true;
    convertBtn.disabled = true;
    cancelBtn.disabled = true;
    browseBtn.disabled = true;
    statusEl.style.display = 'block';
    progressBar.style.width = '0%';
    errorEl.style.display = 'none';
    resultEl.style.display = 'none';

    statusTextEl.textContent = 'AI OCR ашиглан боловсруулж байна...';
    pagesContainerEl.style.display = 'none';  /* Хуудасны сонголтыг нуух */

    try {
      /* AI OCR - OpenAI Vision (зөвхөн сонгосон хуудсуудыг) */
      const result = await extractTextWithAiOcr();
      const pageCount = result.pages;
      newHtml = result.html;

      if (!newHtml || !newHtml.trim()) {
        throw new Error('AI OCR текст олдсонгүй.');
      }

      infoEl.querySelector('small').textContent += ` • ${pageCount} ${config.pageText} (AI OCR)`;

      resultEl.innerHTML = newHtml;
      resultEl.style.display = 'block';
      convertBtn.style.display = 'none';
      confirmBtn.style.display = 'inline-block';
      isBusy = false;
      cancelBtn.disabled = false;

      if (this.opts.notify) {
        this.opts.notify('success', config.title, config.successMessage);
      }
    } catch (err) {
      errorEl.textContent = err.message || config.errorMessage;
      errorEl.style.display = 'block';
      pagesContainerEl.style.display = 'block';  /* Хуудасны сонголтыг дахин харуулах */
      isBusy = false;
      convertBtn.disabled = false;
      cancelBtn.disabled = false;
      browseBtn.disabled = false;
      updateConvertButton();

      if (this.opts.notify) {
        this.opts.notify('danger', config.title, err.message || config.errorMessage);
      }
    } finally {
      statusEl.style.display = 'none';
      progressEl.style.display = 'none';
    }
  });

  /* Confirm товч - HTML оруулах */
  confirmBtn.addEventListener('click', () => {
    if (newHtml) {
      this._ensureVisualMode();
      this._focusEditor();

      if (savedRange) {
        const sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(savedRange);
      }

      document.execCommand('insertHTML', false, newHtml);
      this._emitChange();
    }
    closeDialog();
  });
};
