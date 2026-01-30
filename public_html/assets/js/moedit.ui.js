/**
 * moedit UI Components v1
 *
 * Dialog, Modal болон UI холбоотой функцуудыг агуулна.
 * moedit.js файлын дараа ачаалах шаардлагатай.
 *
 * @requires moedit
 */

/* ============================================
   Notify Helper - Мэдэгдэл харуулах
   ============================================ */

/**
 * Мэдэгдэл харуулах helper
 * opts.notify -> NotifyTop -> alert дарааллаар fallback хийнэ
 * @param {string} type - Мэдэгдлийн төрөл (success, warning, danger, info, error)
 * @param {string} title - Гарчиг
 * @param {string} [message] - Дэлгэрэнгүй мэдээлэл (заавал биш)
 */
moedit.prototype._notify = function(type, title, message) {
  /* message байхгүй бол title-ийг message болгож, title-ийг хоослох */
  if (message === undefined) {
    message = title;
    title = '';
  }
  if (this.opts.notify) {
    this.opts.notify(type, title, message);
  } else if (typeof NotifyTop === 'function') {
    NotifyTop(type, title, message);
  } else {
    alert(message);
  }
};

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
        /* Зургийг clipboard-аас авч upload хийх */
        e.preventDefault();
        const file = items[i].getAsFile();
        if (file) {
          this._uploadAndInsertImage(file);
        }
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
    const result = this._cleanToVanillaHTML(html);
    const cleanedHtml = typeof result === 'object' ? result.html : result;
    const hasWordImages = typeof result === 'object' ? result.hasWordImages : false;

    /* Word-ын local зураг байсан бол мэдэгдэл өгөх */
    if (hasWordImages) {
      this._notify('warning',
        this._isMn ? 'Зураг орсонгүй!' : 'Images not included!',
        this._isMn ? 'Текст амжилттай орлоо. Гэхдээ Word доторх зураг хуулагдахгүй. Зургийг тусад нь хуулж оруулна уу.' : 'Text pasted successfully. However, images from Word cannot be copied. Please insert images separately.');
    }

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
   Clipboard-аас зураг upload хийх
   ============================================ */

/**
 * Clipboard-аас авсан зургийг upload хийж оруулах
 * @param {File} file - Зургийн файл
 */
moedit.prototype._uploadAndInsertImage = function(file) {
  /* uploadUrl эсвэл uploadImage тохируулаагүй бол анхааруулга өгөх */
  if (!this.opts.uploadUrl && !this.opts.uploadImage) {
    this._notify('warning',
      this._isMn ? 'Зураг upload хийх боломжгүй' : 'Cannot upload image',
      this._isMn ? 'Зургийн upload тохиргоо хийгдээгүй байна.' : 'Image upload is not configured.');
    return;
  }

  const config = this.opts.imageUploadModal;

  /* Upload хийж байгаа мэдэгдэл харуулах */
  this._notify('info', config.uploadingText);

  /* Selection хадгалах */
  let savedRange = null;
  const selection = window.getSelection();
  if (selection.rangeCount > 0) {
    savedRange = selection.getRangeAt(0).cloneRange();
  }

  /* uploadImage функц эсвэл uploadUrl ашиглах */
  const uploadPromise = this.opts.uploadImage
    ? this.opts.uploadImage(file)
    : fetch(this.opts.uploadUrl, {
        method: 'POST',
        body: (() => { const fd = new FormData(); fd.append('file', file); return fd; })()
      }).then(res => res.json()).then(data => data.path);

  Promise.resolve(uploadPromise)
    .then(path => {
      if (path) {
        this._insertImageByUrl(path, savedRange);
        if (this.opts.onUploadSuccess) {
          this.opts.onUploadSuccess({ path });
        }
        this._notify('success', this._isMn ? 'Зураг амжилттай оруулагдлаа' : 'Image uploaded successfully');
      } else {
        throw new Error(config.errorMessage);
      }
    })
    .catch(err => {
      if (this.opts.onUploadError) {
        this.opts.onUploadError(err);
      } else {
        this._notify('danger', err.message || config.errorMessage);
      }
    });
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
      <h5 class="moedit-modal-title"><i class="mi-link-45deg"></i> ${config.title}</h5>
      <div class="moedit-modal-field">
        <div class="moedit-modal-radio-group">
          <label class="moedit-modal-radio">
            <input type="radio" name="${dialogId}-type" value="url" checked> <i class="mi-globe"></i> URL
          </label>
          <label class="moedit-modal-radio">
            <input type="radio" name="${dialogId}-type" value="email"> <i class="mi-envelope"></i> Email
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
      <h5 class="moedit-modal-title"><i class="mi-camera"></i> ${config.title}</h5>
      <div class="moedit-modal-field">
        <label class="moedit-modal-label">${this._isMn ? 'Зургийн URL хаяг' : 'Image URL'}</label>
        <input type="url" class="moedit-modal-input" id="${dialogId}-url" placeholder="https://example.com/image.jpg">
      </div>
      <div class="moedit-modal-preview" id="${dialogId}-preview" style="display:none;">
        <img id="${dialogId}-preview-img" src="" style="max-width:100%; max-height:200px;">
      </div>
      <div class="moedit-modal-buttons">
        <button type="button" class="moedit-modal-btn moedit-modal-btn-secondary" id="${dialogId}-cancel">${config.cancelText}</button>
        <button type="button" class="moedit-modal-btn moedit-modal-btn-primary" id="${dialogId}-ok">
          <i class="mi-check-lg"></i> OK
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
      <h5 class="moedit-modal-title"><i class="mi-camera"></i> ${config.title}</h5>
      <div class="moedit-modal-tabs">
        <button type="button" class="moedit-modal-tab moedit-modal-tab-active" id="${dialogId}-tab-upload">
          <i class="mi-pc-display"></i> ${config.tabUpload}
        </button>
        <button type="button" class="moedit-modal-tab" id="${dialogId}-tab-url">
          <i class="mi-link-45deg"></i> ${config.tabUrl}
        </button>
      </div>
      <div class="moedit-modal-tab-content" id="${dialogId}-content-upload">
        <div class="moedit-modal-field">
          <div class="moedit-modal-file-input">
            <input type="text" class="moedit-modal-input moedit-modal-input-readonly" id="${dialogId}-filename" readonly placeholder="${config.placeholder}">
            <button type="button" class="moedit-modal-btn moedit-modal-btn-primary" id="${dialogId}-browse">
              <i class="mi-folder2-open"></i> ${config.browseText}
            </button>
          </div>
          <input type="file" id="${dialogId}-file" accept="image/*" style="display:none;">
        </div>
        <div class="moedit-modal-preview" id="${dialogId}-preview-upload" style="display:none;">
          <img id="${dialogId}-preview-img-upload" src="">
        </div>
      </div>
      <div class="moedit-modal-tab-content" id="${dialogId}-content-url" style="display:none;">
        <div class="moedit-modal-field">
          <label class="moedit-modal-label">${config.urlLabel}</label>
          <input type="url" class="moedit-modal-input" id="${dialogId}-url" placeholder="${config.urlPlaceholder}">
        </div>
        <div class="moedit-modal-preview" id="${dialogId}-preview-url" style="display:none;">
          <img id="${dialogId}-preview-img-url" src="">
        </div>
      </div>
      <div class="moedit-modal-buttons">
        <button type="button" class="moedit-modal-btn moedit-modal-btn-secondary" id="${dialogId}-cancel">${config.cancelText}</button>
        <button type="button" class="moedit-modal-btn moedit-modal-btn-primary moedit-modal-btn-disabled" id="${dialogId}-ok" disabled>
          <i class="mi-check-lg"></i> OK
        </button>
      </div>
    </div>
  `;
  document.body.appendChild(dialog);

  /* Tab elements */
  const tabUpload = document.getElementById(`${dialogId}-tab-upload`);
  const tabUrl = document.getElementById(`${dialogId}-tab-url`);
  const contentUpload = document.getElementById(`${dialogId}-content-upload`);
  const contentUrl = document.getElementById(`${dialogId}-content-url`);

  /* Upload tab elements */
  const fileInput = document.getElementById(`${dialogId}-file`);
  const filenameInput = document.getElementById(`${dialogId}-filename`);
  const browseBtn = document.getElementById(`${dialogId}-browse`);
  const previewDivUpload = document.getElementById(`${dialogId}-preview-upload`);
  const previewImgUpload = document.getElementById(`${dialogId}-preview-img-upload`);

  /* URL tab elements */
  const urlInput = document.getElementById(`${dialogId}-url`);
  const previewDivUrl = document.getElementById(`${dialogId}-preview-url`);
  const previewImgUrl = document.getElementById(`${dialogId}-preview-img-url`);

  /* Common elements */
  const okBtn = document.getElementById(`${dialogId}-ok`);
  const cancelBtn = document.getElementById(`${dialogId}-cancel`);

  let selectedFile = null;
  let activeTab = 'upload'; /* 'upload' or 'url' */
  let isBusy = false;
  let urlPreviewTimeout = null;

  const closeDialog = () => dialog.remove();

  /* Tab switching */
  const switchTab = (tab) => {
    activeTab = tab;
    if (tab === 'upload') {
      tabUpload.classList.add('moedit-modal-tab-active');
      tabUrl.classList.remove('moedit-modal-tab-active');
      contentUpload.style.display = 'block';
      contentUrl.style.display = 'none';
      /* Update OK button state based on file selection */
      okBtn.disabled = !selectedFile;
      okBtn.classList.toggle('moedit-modal-btn-disabled', !selectedFile);
      okBtn.innerHTML = `<i class="mi-upload"></i> ${config.uploadText}`;
    } else {
      tabUrl.classList.add('moedit-modal-tab-active');
      tabUpload.classList.remove('moedit-modal-tab-active');
      contentUrl.style.display = 'block';
      contentUpload.style.display = 'none';
      /* Update OK button state based on URL input */
      const hasUrl = urlInput.value.trim().length > 0;
      okBtn.disabled = !hasUrl;
      okBtn.classList.toggle('moedit-modal-btn-disabled', !hasUrl);
      okBtn.innerHTML = `<i class="mi-check-lg"></i> OK`;
      urlInput.focus();
    }
  };

  tabUpload.addEventListener('click', () => switchTab('upload'));
  tabUrl.addEventListener('click', () => switchTab('url'));

  /* File upload handling */
  browseBtn.addEventListener('click', () => fileInput.click());

  fileInput.addEventListener('change', function() {
    if (this.files && this.files[0]) {
      selectedFile = this.files[0];
      filenameInput.value = selectedFile.name;

      const reader = new FileReader();
      reader.onload = function(e) {
        previewImgUpload.src = e.target.result;
        previewDivUpload.style.display = 'block';
      };
      reader.readAsDataURL(selectedFile);

      okBtn.disabled = false;
      okBtn.classList.remove('moedit-modal-btn-disabled');
    }
  });

  /* URL input handling with preview */
  urlInput.addEventListener('input', () => {
    clearTimeout(urlPreviewTimeout);
    const url = urlInput.value.trim();
    const hasUrl = url.length > 0;
    okBtn.disabled = !hasUrl;
    okBtn.classList.toggle('moedit-modal-btn-disabled', !hasUrl);

    if (hasUrl) {
      urlPreviewTimeout = setTimeout(() => {
        previewImgUrl.onload = () => {
          previewDivUrl.style.display = 'block';
        };
        previewImgUrl.onerror = () => {
          previewDivUrl.style.display = 'none';
        };
        previewImgUrl.src = url;
      }, 500);
    } else {
      previewDivUrl.style.display = 'none';
    }
  });

  /* Enter key for URL input */
  urlInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !okBtn.disabled && !isBusy) {
      okBtn.click();
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

  /* OK button click */
  okBtn.addEventListener('click', () => {
    if (activeTab === 'url') {
      /* URL tab - шууд зураг оруулах */
      const url = urlInput.value.trim();
      if (!url) return;
      closeDialog();
      document.removeEventListener('keydown', escHandler);
      this._insertImageByUrl(url, savedRange);
    } else {
      /* Upload tab - файл upload хийх */
      if (!selectedFile) return;

      isBusy = true;
      okBtn.disabled = true;
      cancelBtn.disabled = true;
      browseBtn.disabled = true;
      tabUpload.disabled = true;
      tabUrl.disabled = true;
      okBtn.innerHTML = `<i class="mi-hourglass-split"></i> ${config.uploadingText}`;

      const uploadPromise = this.opts.uploadImage
        ? this.opts.uploadImage(selectedFile)
        : fetch(this.opts.uploadUrl, {
            method: 'POST',
            body: (() => { const fd = new FormData(); fd.append('file', selectedFile); fd.append('optimize', this._optimizeImages ? '1' : '0'); return fd; })()
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
          isBusy = false;
          okBtn.disabled = false;
          cancelBtn.disabled = false;
          browseBtn.disabled = false;
          tabUpload.disabled = false;
          tabUrl.disabled = false;
          okBtn.classList.remove('moedit-modal-btn-disabled');
          okBtn.innerHTML = `<i class="mi-upload"></i> ${config.uploadText}`;
          if (this.opts.onUploadError) {
            this.opts.onUploadError(err);
          } else {
            this._notify('danger', err.message || config.errorMessage);
          }
        });
    }
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
  this._notify('success', this.opts.imageUploadModal.successMessage);
};

/* ============================================
   Video Upload
   ============================================ */

moedit.prototype._insertVideo = function() {
  let savedRange = null;
  const selection = window.getSelection();
  if (selection.rangeCount > 0) {
    savedRange = selection.getRangeAt(0).cloneRange();
  }

  if (this.opts.uploadUrl || this.opts.uploadVideo) {
    this._showVideoUploadDialog(savedRange);
  } else {
    this._showMediaUrlDialog('video', savedRange);
  }
};

moedit.prototype._showVideoUploadDialog = function(savedRange) {
  const config = this.opts.videoModal;
  const dialogId = 'moedit-video-dialog-' + Date.now();
  const dialog = document.createElement('div');
  dialog.id = dialogId;
  dialog.className = 'moedit-modal-overlay';

  dialog.innerHTML = `
    <div class="moedit-modal">
      <h5 class="moedit-modal-title"><i class="mi-camera-video"></i> ${config.title}</h5>
      <div class="moedit-modal-tabs">
        <button type="button" class="moedit-modal-tab moedit-modal-tab-active" id="${dialogId}-tab-upload">
          <i class="mi-pc-display"></i> ${config.tabUpload}
        </button>
        <button type="button" class="moedit-modal-tab" id="${dialogId}-tab-url">
          <i class="mi-link-45deg"></i> ${config.tabUrl}
        </button>
      </div>
      <div class="moedit-modal-tab-content" id="${dialogId}-content-upload">
        <div class="moedit-modal-field">
          <div class="moedit-modal-file-input">
            <input type="text" class="moedit-modal-input moedit-modal-input-readonly" id="${dialogId}-filename" readonly placeholder="${config.placeholder}">
            <button type="button" class="moedit-modal-btn moedit-modal-btn-primary" id="${dialogId}-browse">
              <i class="mi-folder2-open"></i> ${config.browseText}
            </button>
          </div>
          <input type="file" id="${dialogId}-file" accept="video/*" style="display:none;">
        </div>
        <div class="moedit-modal-preview" id="${dialogId}-preview-upload" style="display:none;">
          <video id="${dialogId}-preview-video-upload" src="" controls style="max-width:100%; max-height:200px;"></video>
        </div>
      </div>
      <div class="moedit-modal-tab-content" id="${dialogId}-content-url" style="display:none;">
        <div class="moedit-modal-field">
          <label class="moedit-modal-label">${config.urlLabel}</label>
          <input type="url" class="moedit-modal-input" id="${dialogId}-url" placeholder="${config.urlPlaceholder}">
        </div>
        <div class="moedit-modal-preview" id="${dialogId}-preview-url" style="display:none;">
          <video id="${dialogId}-preview-video-url" src="" controls style="max-width:100%; max-height:200px;"></video>
        </div>
      </div>
      <div class="moedit-modal-buttons">
        <button type="button" class="moedit-modal-btn moedit-modal-btn-secondary" id="${dialogId}-cancel">${config.cancelText}</button>
        <button type="button" class="moedit-modal-btn moedit-modal-btn-primary moedit-modal-btn-disabled" id="${dialogId}-ok" disabled>
          <i class="mi-check-lg"></i> OK
        </button>
      </div>
    </div>
  `;
  document.body.appendChild(dialog);

  const tabUpload = document.getElementById(`${dialogId}-tab-upload`);
  const tabUrl = document.getElementById(`${dialogId}-tab-url`);
  const contentUpload = document.getElementById(`${dialogId}-content-upload`);
  const contentUrl = document.getElementById(`${dialogId}-content-url`);

  const fileInput = document.getElementById(`${dialogId}-file`);
  const filenameInput = document.getElementById(`${dialogId}-filename`);
  const browseBtn = document.getElementById(`${dialogId}-browse`);
  const previewDivUpload = document.getElementById(`${dialogId}-preview-upload`);
  const previewVideoUpload = document.getElementById(`${dialogId}-preview-video-upload`);

  const urlInput = document.getElementById(`${dialogId}-url`);
  const previewDivUrl = document.getElementById(`${dialogId}-preview-url`);
  const previewVideoUrl = document.getElementById(`${dialogId}-preview-video-url`);

  const okBtn = document.getElementById(`${dialogId}-ok`);
  const cancelBtn = document.getElementById(`${dialogId}-cancel`);

  let selectedFile = null;
  let activeTab = 'upload';
  let isBusy = false;
  let urlPreviewTimeout = null;

  const closeDialog = () => dialog.remove();

  const switchTab = (tab) => {
    activeTab = tab;
    if (tab === 'upload') {
      tabUpload.classList.add('moedit-modal-tab-active');
      tabUrl.classList.remove('moedit-modal-tab-active');
      contentUpload.style.display = 'block';
      contentUrl.style.display = 'none';
      okBtn.disabled = !selectedFile;
      okBtn.classList.toggle('moedit-modal-btn-disabled', !selectedFile);
      okBtn.innerHTML = `<i class="mi-upload"></i> ${config.uploadText}`;
    } else {
      tabUrl.classList.add('moedit-modal-tab-active');
      tabUpload.classList.remove('moedit-modal-tab-active');
      contentUrl.style.display = 'block';
      contentUpload.style.display = 'none';
      const hasUrl = urlInput.value.trim().length > 0;
      okBtn.disabled = !hasUrl;
      okBtn.classList.toggle('moedit-modal-btn-disabled', !hasUrl);
      okBtn.innerHTML = `<i class="mi-check-lg"></i> OK`;
      urlInput.focus();
    }
  };

  tabUpload.addEventListener('click', () => switchTab('upload'));
  tabUrl.addEventListener('click', () => switchTab('url'));

  browseBtn.addEventListener('click', () => fileInput.click());

  fileInput.addEventListener('change', function() {
    if (this.files && this.files[0]) {
      selectedFile = this.files[0];
      filenameInput.value = selectedFile.name;

      const url = URL.createObjectURL(selectedFile);
      previewVideoUpload.src = url;
      previewDivUpload.style.display = 'block';

      okBtn.disabled = false;
      okBtn.classList.remove('moedit-modal-btn-disabled');
    }
  });

  urlInput.addEventListener('input', () => {
    clearTimeout(urlPreviewTimeout);
    const url = urlInput.value.trim();
    const hasUrl = url.length > 0;
    okBtn.disabled = !hasUrl;
    okBtn.classList.toggle('moedit-modal-btn-disabled', !hasUrl);

    if (hasUrl) {
      urlPreviewTimeout = setTimeout(() => {
        previewVideoUrl.src = url;
        previewDivUrl.style.display = 'block';
      }, 500);
    } else {
      previewDivUrl.style.display = 'none';
    }
  });

  urlInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !okBtn.disabled && !isBusy) {
      okBtn.click();
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

  okBtn.addEventListener('click', () => {
    if (activeTab === 'url') {
      const url = urlInput.value.trim();
      if (!url) return;
      closeDialog();
      document.removeEventListener('keydown', escHandler);
      this._insertMediaByUrl('video', url, savedRange);
    } else {
      if (!selectedFile) return;

      isBusy = true;
      okBtn.disabled = true;
      cancelBtn.disabled = true;
      browseBtn.disabled = true;
      tabUpload.disabled = true;
      tabUrl.disabled = true;
      okBtn.innerHTML = `<i class="mi-hourglass-split"></i> ${config.uploadingText}`;

      const uploadPromise = this.opts.uploadVideo
        ? this.opts.uploadVideo(selectedFile)
        : fetch(this.opts.uploadUrl, {
            method: 'POST',
            body: (() => { const fd = new FormData(); fd.append('file', selectedFile); fd.append('optimize', this._optimizeImages ? '1' : '0'); return fd; })()
          }).then(res => res.json()).then(data => data.path);

      Promise.resolve(uploadPromise)
        .then(path => {
          closeDialog();
          document.removeEventListener('keydown', escHandler);

          if (path) {
            this._insertMediaByUrl('video', path, savedRange);
            if (this.opts.onUploadSuccess) {
              this.opts.onUploadSuccess({ path, type: 'video' });
            }
          } else {
            throw new Error(config.errorMessage);
          }
        })
        .catch(err => {
          isBusy = false;
          okBtn.disabled = false;
          cancelBtn.disabled = false;
          browseBtn.disabled = false;
          tabUpload.disabled = false;
          tabUrl.disabled = false;
          okBtn.classList.remove('moedit-modal-btn-disabled');
          okBtn.innerHTML = `<i class="mi-upload"></i> ${config.uploadText}`;
          if (this.opts.onUploadError) {
            this.opts.onUploadError(err);
          } else {
            this._notify('danger', err.message || config.errorMessage);
          }
        });
    }
  });
};

/* ============================================
   Audio Upload
   ============================================ */

moedit.prototype._insertAudio = function() {
  let savedRange = null;
  const selection = window.getSelection();
  if (selection.rangeCount > 0) {
    savedRange = selection.getRangeAt(0).cloneRange();
  }

  if (this.opts.uploadUrl || this.opts.uploadAudio) {
    this._showAudioUploadDialog(savedRange);
  } else {
    this._showMediaUrlDialog('audio', savedRange);
  }
};

moedit.prototype._showAudioUploadDialog = function(savedRange) {
  const config = this.opts.audioModal;
  const dialogId = 'moedit-audio-dialog-' + Date.now();
  const dialog = document.createElement('div');
  dialog.id = dialogId;
  dialog.className = 'moedit-modal-overlay';

  dialog.innerHTML = `
    <div class="moedit-modal">
      <h5 class="moedit-modal-title"><i class="mi-music-note-beamed"></i> ${config.title}</h5>
      <div class="moedit-modal-tabs">
        <button type="button" class="moedit-modal-tab moedit-modal-tab-active" id="${dialogId}-tab-upload">
          <i class="mi-pc-display"></i> ${config.tabUpload}
        </button>
        <button type="button" class="moedit-modal-tab" id="${dialogId}-tab-url">
          <i class="mi-link-45deg"></i> ${config.tabUrl}
        </button>
      </div>
      <div class="moedit-modal-tab-content" id="${dialogId}-content-upload">
        <div class="moedit-modal-field">
          <div class="moedit-modal-file-input">
            <input type="text" class="moedit-modal-input moedit-modal-input-readonly" id="${dialogId}-filename" readonly placeholder="${config.placeholder}">
            <button type="button" class="moedit-modal-btn moedit-modal-btn-primary" id="${dialogId}-browse">
              <i class="mi-folder2-open"></i> ${config.browseText}
            </button>
          </div>
          <input type="file" id="${dialogId}-file" accept="audio/*" style="display:none;">
        </div>
        <div class="moedit-modal-preview" id="${dialogId}-preview-upload" style="display:none;">
          <audio id="${dialogId}-preview-audio-upload" src="" controls style="max-width:100%;"></audio>
        </div>
      </div>
      <div class="moedit-modal-tab-content" id="${dialogId}-content-url" style="display:none;">
        <div class="moedit-modal-field">
          <label class="moedit-modal-label">${config.urlLabel}</label>
          <input type="url" class="moedit-modal-input" id="${dialogId}-url" placeholder="${config.urlPlaceholder}">
        </div>
        <div class="moedit-modal-preview" id="${dialogId}-preview-url" style="display:none;">
          <audio id="${dialogId}-preview-audio-url" src="" controls style="max-width:100%;"></audio>
        </div>
      </div>
      <div class="moedit-modal-buttons">
        <button type="button" class="moedit-modal-btn moedit-modal-btn-secondary" id="${dialogId}-cancel">${config.cancelText}</button>
        <button type="button" class="moedit-modal-btn moedit-modal-btn-primary moedit-modal-btn-disabled" id="${dialogId}-ok" disabled>
          <i class="mi-check-lg"></i> OK
        </button>
      </div>
    </div>
  `;
  document.body.appendChild(dialog);

  const tabUpload = document.getElementById(`${dialogId}-tab-upload`);
  const tabUrl = document.getElementById(`${dialogId}-tab-url`);
  const contentUpload = document.getElementById(`${dialogId}-content-upload`);
  const contentUrl = document.getElementById(`${dialogId}-content-url`);

  const fileInput = document.getElementById(`${dialogId}-file`);
  const filenameInput = document.getElementById(`${dialogId}-filename`);
  const browseBtn = document.getElementById(`${dialogId}-browse`);
  const previewDivUpload = document.getElementById(`${dialogId}-preview-upload`);
  const previewAudioUpload = document.getElementById(`${dialogId}-preview-audio-upload`);

  const urlInput = document.getElementById(`${dialogId}-url`);
  const previewDivUrl = document.getElementById(`${dialogId}-preview-url`);
  const previewAudioUrl = document.getElementById(`${dialogId}-preview-audio-url`);

  const okBtn = document.getElementById(`${dialogId}-ok`);
  const cancelBtn = document.getElementById(`${dialogId}-cancel`);

  let selectedFile = null;
  let activeTab = 'upload';
  let isBusy = false;
  let urlPreviewTimeout = null;

  const closeDialog = () => dialog.remove();

  const switchTab = (tab) => {
    activeTab = tab;
    if (tab === 'upload') {
      tabUpload.classList.add('moedit-modal-tab-active');
      tabUrl.classList.remove('moedit-modal-tab-active');
      contentUpload.style.display = 'block';
      contentUrl.style.display = 'none';
      okBtn.disabled = !selectedFile;
      okBtn.classList.toggle('moedit-modal-btn-disabled', !selectedFile);
      okBtn.innerHTML = `<i class="mi-upload"></i> ${config.uploadText}`;
    } else {
      tabUrl.classList.add('moedit-modal-tab-active');
      tabUpload.classList.remove('moedit-modal-tab-active');
      contentUrl.style.display = 'block';
      contentUpload.style.display = 'none';
      const hasUrl = urlInput.value.trim().length > 0;
      okBtn.disabled = !hasUrl;
      okBtn.classList.toggle('moedit-modal-btn-disabled', !hasUrl);
      okBtn.innerHTML = `<i class="mi-check-lg"></i> OK`;
      urlInput.focus();
    }
  };

  tabUpload.addEventListener('click', () => switchTab('upload'));
  tabUrl.addEventListener('click', () => switchTab('url'));

  browseBtn.addEventListener('click', () => fileInput.click());

  fileInput.addEventListener('change', function() {
    if (this.files && this.files[0]) {
      selectedFile = this.files[0];
      filenameInput.value = selectedFile.name;

      const url = URL.createObjectURL(selectedFile);
      previewAudioUpload.src = url;
      previewDivUpload.style.display = 'block';

      okBtn.disabled = false;
      okBtn.classList.remove('moedit-modal-btn-disabled');
    }
  });

  urlInput.addEventListener('input', () => {
    clearTimeout(urlPreviewTimeout);
    const url = urlInput.value.trim();
    const hasUrl = url.length > 0;
    okBtn.disabled = !hasUrl;
    okBtn.classList.toggle('moedit-modal-btn-disabled', !hasUrl);

    if (hasUrl) {
      urlPreviewTimeout = setTimeout(() => {
        previewAudioUrl.src = url;
        previewDivUrl.style.display = 'block';
      }, 500);
    } else {
      previewDivUrl.style.display = 'none';
    }
  });

  urlInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !okBtn.disabled && !isBusy) {
      okBtn.click();
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

  okBtn.addEventListener('click', () => {
    if (activeTab === 'url') {
      const url = urlInput.value.trim();
      if (!url) return;
      closeDialog();
      document.removeEventListener('keydown', escHandler);
      this._insertMediaByUrl('audio', url, savedRange);
    } else {
      if (!selectedFile) return;

      isBusy = true;
      okBtn.disabled = true;
      cancelBtn.disabled = true;
      browseBtn.disabled = true;
      tabUpload.disabled = true;
      tabUrl.disabled = true;
      okBtn.innerHTML = `<i class="mi-hourglass-split"></i> ${config.uploadingText}`;

      const uploadPromise = this.opts.uploadAudio
        ? this.opts.uploadAudio(selectedFile)
        : fetch(this.opts.uploadUrl, {
            method: 'POST',
            body: (() => { const fd = new FormData(); fd.append('file', selectedFile); fd.append('optimize', this._optimizeImages ? '1' : '0'); return fd; })()
          }).then(res => res.json()).then(data => data.path);

      Promise.resolve(uploadPromise)
        .then(path => {
          closeDialog();
          document.removeEventListener('keydown', escHandler);

          if (path) {
            this._insertMediaByUrl('audio', path, savedRange);
            if (this.opts.onUploadSuccess) {
              this.opts.onUploadSuccess({ path, type: 'audio' });
            }
          } else {
            throw new Error(config.errorMessage);
          }
        })
        .catch(err => {
          isBusy = false;
          okBtn.disabled = false;
          cancelBtn.disabled = false;
          browseBtn.disabled = false;
          tabUpload.disabled = false;
          tabUrl.disabled = false;
          okBtn.classList.remove('moedit-modal-btn-disabled');
          okBtn.innerHTML = `<i class="mi-upload"></i> ${config.uploadText}`;
          if (this.opts.onUploadError) {
            this.opts.onUploadError(err);
          } else {
            this._notify('danger', err.message || config.errorMessage);
          }
        });
    }
  });
};

/* ============================================
   Media URL Dialog (for video/audio without upload)
   ============================================ */

moedit.prototype._showMediaUrlDialog = function(type, savedRange) {
  const config = type === 'video' ? this.opts.videoModal : this.opts.audioModal;
  const icon = type === 'video' ? 'mi-camera-video' : 'mi-music-note-beamed';
  const dialogId = `moedit-${type}-url-dialog-` + Date.now();
  const dialog = document.createElement('div');
  dialog.id = dialogId;
  dialog.className = 'moedit-modal-overlay';

  dialog.innerHTML = `
    <div class="moedit-modal">
      <h5 class="moedit-modal-title"><i class="${icon}"></i> ${config.title}</h5>
      <div class="moedit-modal-field">
        <label class="moedit-modal-label">${config.urlLabel}</label>
        <input type="url" class="moedit-modal-input" id="${dialogId}-url" placeholder="${config.urlPlaceholder}">
      </div>
      <div class="moedit-modal-preview" id="${dialogId}-preview" style="display:none;">
        ${type === 'video'
          ? `<video id="${dialogId}-preview-media" src="" controls style="max-width:100%; max-height:200px;"></video>`
          : `<audio id="${dialogId}-preview-media" src="" controls style="max-width:100%;"></audio>`
        }
      </div>
      <div class="moedit-modal-buttons">
        <button type="button" class="moedit-modal-btn moedit-modal-btn-secondary" id="${dialogId}-cancel">${config.cancelText}</button>
        <button type="button" class="moedit-modal-btn moedit-modal-btn-primary moedit-modal-btn-disabled" id="${dialogId}-ok" disabled>
          <i class="mi-check-lg"></i> OK
        </button>
      </div>
    </div>
  `;
  document.body.appendChild(dialog);

  const urlInput = document.getElementById(`${dialogId}-url`);
  const previewDiv = document.getElementById(`${dialogId}-preview`);
  const previewMedia = document.getElementById(`${dialogId}-preview-media`);
  const okBtn = document.getElementById(`${dialogId}-ok`);
  const cancelBtn = document.getElementById(`${dialogId}-cancel`);

  let urlPreviewTimeout = null;

  const closeDialog = () => dialog.remove();

  urlInput.addEventListener('input', () => {
    clearTimeout(urlPreviewTimeout);
    const url = urlInput.value.trim();
    const hasUrl = url.length > 0;
    okBtn.disabled = !hasUrl;
    okBtn.classList.toggle('moedit-modal-btn-disabled', !hasUrl);

    if (hasUrl) {
      urlPreviewTimeout = setTimeout(() => {
        previewMedia.src = url;
        previewDiv.style.display = 'block';
      }, 500);
    } else {
      previewDiv.style.display = 'none';
    }
  });

  urlInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !okBtn.disabled) {
      okBtn.click();
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
    const url = urlInput.value.trim();
    if (!url) return;
    closeDialog();
    document.removeEventListener('keydown', escHandler);
    this._insertMediaByUrl(type, url, savedRange);
  });

  setTimeout(() => urlInput.focus(), 100);
};

/* ============================================
   Insert Media (Video/Audio) by URL
   ============================================ */

moedit.prototype._insertMediaByUrl = function(type, url, savedRange) {
  this._ensureVisualMode();
  this._focusEditor();

  if (savedRange) {
    const sel = window.getSelection();
    sel.removeAllRanges();
    sel.addRange(savedRange);
  }

  const config = type === 'video' ? this.opts.videoModal : this.opts.audioModal;

  /* Block элемент учир өмнө нь P tag нэмж, курсорыг тэнд байрлуулна */
  const fragment = document.createDocumentFragment();

  /* Өмнөх P */
  const pBefore = document.createElement('p');
  pBefore.innerHTML = '<br>';

  /* Media wrapper div - contenteditable="false" тул Enter дарахад style өвлөхгүй */
  const wrapper = document.createElement('div');
  wrapper.style.margin = '10px 0';
  wrapper.setAttribute('contenteditable', 'false');

  /* Media элемент */
  let media;
  if (type === 'video') {
    media = document.createElement('video');
    media.src = url;
    media.controls = true;
    media.style.maxWidth = '100%';
  } else {
    media = document.createElement('audio');
    media.src = url;
    media.controls = true;
    media.style.maxWidth = '100%';
  }

  wrapper.appendChild(media);

  /* Дараах P */
  const pAfter = document.createElement('p');
  pAfter.innerHTML = '<br>';

  fragment.appendChild(pBefore);
  fragment.appendChild(wrapper);
  fragment.appendChild(pAfter);

  const selection = window.getSelection();
  if (selection.rangeCount > 0) {
    const range = selection.getRangeAt(0);
    range.deleteContents();
    range.insertNode(fragment);

    /* Cursor-ийг дараах P-д байрлуулах */
    const newRange = document.createRange();
    newRange.setStart(pAfter, 0);
    newRange.collapse(true);
    selection.removeAllRanges();
    selection.addRange(newRange);
  }

  this._emitChange();
  this._notify('success', config.successMessage);
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
      <h5 class="moedit-modal-title"><i class="mi-table"></i> ${config.title}</h5>
      <div class="moedit-modal-field">
        <label class="moedit-modal-label">${config.typeLabel}</label>
        <select class="moedit-modal-input" id="${dialogId}-type">
          <option value="vanilla">${config.typeVanilla}</option>
          <option value="bootstrap" selected>${config.typeBootstrap}</option>
        </select>
      </div>
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

  const typeSelect = document.getElementById(`${dialogId}-type`);
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
    const tableType = typeSelect.value;
    const rows = parseInt(rowsInput.value) || 3;
    const cols = parseInt(colsInput.value) || 3;

    closeDialog();
    document.removeEventListener('keydown', escHandler);

    if (rows > 0 && cols > 0) {
      const table = document.createElement('table');

      if (tableType === 'bootstrap') {
        /* Bootstrap Table */
        table.className = 'table table-bordered table-striped table-hover';

        const thead = document.createElement('thead');
        const headerRow = document.createElement('tr');
        for (let j = 0; j < cols; j++) {
          const th = document.createElement('th');
          th.setAttribute('scope', 'col');
          th.innerHTML = '&nbsp;';
          headerRow.appendChild(th);
        }
        thead.appendChild(headerRow);
        table.appendChild(thead);

        const tbody = document.createElement('tbody');
        for (let i = 1; i < rows; i++) {
          const tr = document.createElement('tr');
          for (let j = 0; j < cols; j++) {
            const td = document.createElement('td');
            td.innerHTML = '&nbsp;';
            tr.appendChild(td);
          }
          tbody.appendChild(tr);
        }
        table.appendChild(tbody);
      } else {
        /* Vanilla Table */
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
      }

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
   Toggle Mark (Highlight)
   ============================================ */

moedit.prototype._toggleMark = function() {
  const selection = window.getSelection();
  if (!selection.rangeCount) return;

  const range = selection.getRangeAt(0);

  /* Cursor байрлаж буй mark element-ийг олох */
  let node = selection.anchorNode;
  if (node && node.nodeType === Node.TEXT_NODE) node = node.parentNode;
  let existingMark = null;
  while (node && node !== this.editor) {
    if (node.nodeName === 'MARK') { existingMark = node; break; }
    node = node.parentNode;
  }

  if (existingMark) {
    /* Mark-ийг арилгах (unwrap) */
    const parent = existingMark.parentNode;
    while (existingMark.firstChild) {
      parent.insertBefore(existingMark.firstChild, existingMark);
    }
    parent.removeChild(existingMark);
    parent.normalize();
  } else if (!range.collapsed) {
    /* Сонгосон текстийг mark-аар ороох */
    const mark = document.createElement('mark');
    try {
      range.surroundContents(mark);
    } catch {
      /* Хэрэв surroundContents ажиллахгүй бол (partial selection) extractContents ашиглах */
      mark.appendChild(range.extractContents());
      range.insertNode(mark);
    }
    /* Cursor-ийг mark-ийн ард байрлуулах */
    const newRange = document.createRange();
    newRange.setStartAfter(mark);
    newRange.collapse(true);
    selection.removeAllRanges();
    selection.addRange(newRange);
  }

  this._emitChange();
};

/* ============================================
   Insert Accordion (details/summary)
   ============================================ */

moedit.prototype._insertAccordion = function() {
  const isMn = this._isMn;

  /* Selection хадгалах */
  let savedRange = null;
  const selection = window.getSelection();
  if (selection.rangeCount > 0) {
    savedRange = selection.getRangeAt(0).cloneRange();
  }

  const dialogId = 'moedit-accordion-dialog-' + Date.now();
  const dialog = document.createElement('div');
  dialog.id = dialogId;
  dialog.className = 'moedit-modal-overlay';
  dialog.innerHTML = `
    <div class="moedit-modal moedit-modal-sm">
      <h5 class="moedit-modal-title"><i class="mi-chevron-bar-expand"></i> ${isMn ? 'Accordion оруулах' : 'Insert Accordion'}</h5>
      <div class="moedit-modal-field">
        <label class="moedit-modal-label">${isMn ? 'Тоо' : 'Count'}</label>
        <input type="number" class="moedit-modal-input" id="${dialogId}-count" value="3" min="1" max="20">
      </div>
      <div class="moedit-modal-field">
        <label class="moedit-modal-label">${isMn ? 'Горим' : 'Mode'}</label>
        <select class="moedit-modal-input" id="${dialogId}-mode">
          <option value="independent">${isMn ? 'Чөлөөт (олон нээгдэх)' : 'Independent (multiple open)'}</option>
          <option value="exclusive" selected>${isMn ? 'Зөвхөн нэг нээгдэх' : 'Only one open at a time'}</option>
        </select>
      </div>
      <div class="moedit-modal-buttons">
        <button type="button" class="moedit-modal-btn moedit-modal-btn-secondary" id="${dialogId}-cancel">${isMn ? 'Болих' : 'Cancel'}</button>
        <button type="button" class="moedit-modal-btn moedit-modal-btn-primary" id="${dialogId}-ok">OK</button>
      </div>
    </div>
  `;
  document.body.appendChild(dialog);

  const countInput = document.getElementById(`${dialogId}-count`);
  const modeSelect = document.getElementById(`${dialogId}-mode`);
  const okBtn = document.getElementById(`${dialogId}-ok`);
  const cancelBtn = document.getElementById(`${dialogId}-cancel`);

  countInput.focus();
  countInput.select();

  const closeDialog = () => dialog.remove();

  cancelBtn.addEventListener('click', closeDialog);

  dialog.addEventListener('click', (e) => {
    if (e.target === dialog) closeDialog();
  });

  const escHandler = (e) => {
    if (e.key === 'Escape') {
      closeDialog();
      document.removeEventListener('keydown', escHandler);
    }
  };
  document.addEventListener('keydown', escHandler);

  const enterHandler = (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      okBtn.click();
    }
  };
  countInput.addEventListener('keydown', enterHandler);

  okBtn.addEventListener('click', () => {
    const count = parseInt(countInput.value) || 3;
    const mode = modeSelect.value;

    closeDialog();
    document.removeEventListener('keydown', escHandler);

    if (count > 0) {
      const groupName = mode === 'exclusive' ? 'accordion-' + Date.now() : '';

      const wrapper = document.createDocumentFragment();
      for (let i = 0; i < count; i++) {
        const details = document.createElement('details');
        if (groupName) details.setAttribute('name', groupName);
        if (i === 0) details.setAttribute('open', '');

        const summary = document.createElement('summary');
        summary.textContent = (isMn ? 'Гарчиг ' : 'Title ') + (i + 1);
        details.appendChild(summary);

        const content = document.createElement('p');
        content.innerHTML = '&nbsp;';
        details.appendChild(content);

        wrapper.appendChild(details);
      }

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
        range.insertNode(wrapper);

        /* Cursor-ийг эхний summary дээр байрлуулах */
        const firstSummary = sel.anchorNode.parentElement.querySelector
          ? sel.anchorNode.parentElement.querySelector('details summary')
          : null;
        if (firstSummary) {
          const newRange = document.createRange();
          newRange.selectNodeContents(firstSummary);
          newRange.collapse(false);
          sel.removeAllRanges();
          sel.addRange(newRange);
        }
      }

      this._emitChange();
    }
  });
};

/* ============================================
   AI Config Notice Dialog
   shineUrl тохируулаагүй үед тайлбарлах dialog харуулна
   ============================================ */

moedit.prototype._showAiConfigNotice = function(title, feature) {
  const dialogId = 'moedit-ai-config-notice-' + Date.now();
  const dialog = document.createElement('div');
  dialog.id = dialogId;
  dialog.className = 'moedit-modal-overlay';

  const featureIcons = {
    'shine': 'bi-stars text-warning',
    'ocr': 'bi-file-text text-info',
    'pdf': 'bi-file-earmark-pdf text-danger'
  };
  const iconClass = featureIcons[feature] || 'bi-gear';

  dialog.innerHTML = `
    <div class="moedit-modal">
      <h5 class="moedit-modal-title"><i class="bi ${iconClass}"></i> ${title}</h5>
      <div style="padding: 15px 0;">
        <div style="text-align: center; margin-bottom: 15px;">
          <i class="mi-info-circle" style="font-size: 48px; color: var(--mo-primary, #0d6efd);"></i>
        </div>
        <p style="margin-bottom: 12px; color: var(--mo-text, #333);">
          <strong>${this._isMn ? 'AI функц идэвхжүүлээгүй байна.' : 'AI function is not enabled.'}</strong>
        </p>
        <p style="margin-bottom: 12px; color: var(--mo-text-muted, #666); font-size: 14px;">
          ${this._isMn ? 'Энэ функцийг ашиглахын тулд системийн администратор дараах тохиргоог хийсэн байх шаардлагатай:' : 'To use this function, the system administrator must configure the following:'}
        </p>
        <ul style="margin-bottom: 15px; padding-left: 20px; color: var(--mo-text-muted, #666); font-size: 13px;">
          <li style="margin-bottom: 6px;"><code style="background: var(--mo-bg-muted, #f5f5f5); padding: 2px 6px; border-radius: 3px;">shineUrl</code> - ${this._isMn ? 'AI endpoint URL тохируулах' : 'Set AI endpoint URL'}</li>
          <li style="margin-bottom: 6px;"><code style="background: var(--mo-bg-muted, #f5f5f5); padding: 2px 6px; border-radius: 3px;">INDO_OPENAI_API_KEY</code> - ${this._isMn ? 'Backend дээр OpenAI API key тохируулах' : 'Set OpenAI API key on backend'}</li>
        </ul>
        <p style="margin: 0; color: var(--mo-text-muted, #666); font-size: 13px; font-style: italic;">
          ${this._isMn ? 'Тохиргоо хийгдсэн бол энэ функц автоматаар идэвхжинэ.' : 'Once configured, this function will be enabled automatically.'}
        </p>
      </div>
      <div class="moedit-modal-buttons">
        <button type="button" class="moedit-modal-btn moedit-modal-btn-primary btn-close-notice">
          <i class="mi-check-lg"></i> ${this._isMn ? 'Ойлголоо' : 'Got it'}
        </button>
      </div>
    </div>
  `;
  document.body.appendChild(dialog);

  const closeBtn = dialog.querySelector('.btn-close-notice');
  const closeDialog = () => dialog.remove();

  closeBtn.addEventListener('click', closeDialog);
  dialog.addEventListener('click', (e) => { if (e.target === dialog) closeDialog(); });

  const escHandler = (e) => {
    if (e.key === 'Escape') {
      closeDialog();
      document.removeEventListener('keydown', escHandler);
    }
  };
  document.addEventListener('keydown', escHandler);
};

/* ============================================
   AI Shine Dialog
   ============================================ */

moedit.prototype._shine = async function() {
  const html = this.getHTML();
  const cfg = this.opts.shineModal;
  const shineUrl = this.opts.shineUrl;

  /* shineUrl тохируулаагүй бол тайлбарлах dialog харуулах */
  if (!shineUrl || !shineUrl.trim()) {
    this._showAiConfigNotice(cfg.title, 'shine');
    return;
  }

  /* Хоосон контент шалгах */
  if (!html || !html.trim()) {
    this._notify('warning', cfg.title, this._isMn ? 'AI Shine ашиглахын тулд эхлээд контент бичнэ үү.' : 'Please write some content first to use AI Shine.');
    return;
  }

  /* Modal үүсгэх */
  const dialogId = 'moedit-shine-dialog-' + Date.now();
  const dialog = document.createElement('div');
  dialog.id = dialogId;
  dialog.className = 'moedit-modal-overlay';
  const defaultPrompt = cfg.defaultPrompt || '';
  const resetLabel = this._isMn ? 'Анхны утга' : 'Reset';
  dialog.innerHTML = `
    <div class="moedit-modal moedit-modal-lg">
      <h5 class="moedit-modal-title"><i class="mi-stars"></i> ${cfg.title}</h5>
      <p class="moedit-modal-desc">${cfg.description}</p>
      <div style="background: linear-gradient(135deg, #7952b3 0%, #563d7c 100%); color: white; padding: 10px 14px; border-radius: 6px; margin-bottom: 12px; font-size: 13px;">
        <i class="mi-bootstrap" style="margin-right: 6px;"></i>
        <strong>${this._isMn ? 'Анхааруулга:' : 'Note:'}</strong> ${this._isMn ? 'Энэ функцээр сайжруулсан контент нь зөвхөн <strong>Bootstrap</strong> сан ашиглаж буй HTML хуудас дотор гоё харагдана.' : 'Content beautified with this function will only look good on HTML pages using the <strong>Bootstrap</strong> library.'}
      </div>
      <div class="moedit-modal-field shine-prompt-field">
        <label class="moedit-modal-label">
          <i class="mi-chat-square-text"></i> ${cfg.promptLabel}
          <button type="button" class="btn-reset-prompt" style="float:right; background:none; border:none; color:var(--mo-primary, #0d6efd); cursor:pointer; font-size:12px; padding:0;">
            <i class="mi-arrow-counterclockwise"></i> ${resetLabel}
          </button>
        </label>
        <textarea class="moedit-modal-textarea shine-prompt" rows="6" style="font-size:13px; font-family:monospace;">${defaultPrompt}</textarea>
      </div>
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
          <i class="mi-stars"></i> ${cfg.title}
        </button>
        <button type="button" class="moedit-modal-btn moedit-modal-btn-success btn-confirm" style="display:none;">
          <i class="mi-check-lg"></i> ${cfg.confirmText}
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
  const promptTextarea = dialog.querySelector('.shine-prompt');
  const promptField = dialog.querySelector('.shine-prompt-field');
  const resetPromptBtn = dialog.querySelector('.btn-reset-prompt');

  let newHtml = null;
  let isBusy = false;

  /* Анхны утга сэргээх товч */
  resetPromptBtn.addEventListener('click', () => {
    promptTextarea.value = defaultPrompt;
  });

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
    promptField.style.display = 'none'; /* Prompt талбарыг нуух */

    /* Хэрэглэгчийн оруулсан prompt авах */
    const customPrompt = promptTextarea.value.trim();

    try {
      const response = await fetch(this.opts.shineUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ html: html, prompt: customPrompt })
      });

      /* HTTP алдаа шалгах */
      if (!response.ok) {
        throw new Error(this._isMn ? `Сервер алдаа: ${response.status} ${response.statusText}` : `Server error: ${response.status} ${response.statusText}`);
      }

      /* JSON эсэхийг шалгах */
      const contentType = response.headers.get('content-type');
      if (!contentType || !contentType.includes('application/json')) {
        throw new Error(this._isMn ? 'Shine API endpoint тохируулаагүй байна' : 'Shine API endpoint is not configured');
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

        this._notify('success', cfg.title, cfg.successMessage);
      } else {
        throw new Error(data.message || cfg.errorMessage);
      }
    } catch (err) {
      errorEl.textContent = err.message || cfg.errorMessage;
      errorEl.style.display = 'block';
      promptField.style.display = 'block'; /* Prompt талбарыг дахин харуулах */
      /* Бүх товчнуудыг enable хийх */
      isBusy = false;
      shineBtn.disabled = false;
      cancelBtn.disabled = false;

      this._notify('error', cfg.title, err.message || cfg.errorMessage);
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
 * @returns {{html: string, hasWordImages: boolean}} Цэвэрлэгдсэн HTML болон Word зураг байсан эсэх
 */
moedit.prototype._cleanToVanillaHTML = function(html) {
  if (!html || !html.trim()) return { html: '', hasWordImages: false };

  /* Word-ын local file зураг байгаа эсэх (regex-ээр урьдчилан шалгах) */
  let hasWordImages = /src\s*=\s*["']?file:\/\/|msohtmlclip/i.test(html);

  /* file:/// URL-тай img tag-уудыг DOM parse-аас өмнө устгах (browser error гаргахгүйн тулд) */
  if (hasWordImages) {
    html = html.replace(/<img[^>]*src\s*=\s*["']?file:\/\/[^>]*>/gi, '');
    html = html.replace(/<img[^>]*msohtmlclip[^>]*>/gi, '');
    html = html.replace(/<v:imagedata[^>]*>/gi, '');
  }

  /* Түр div үүсгэж HTML parse хийх */
  const tempDiv = document.createElement('div');
  tempDiv.innerHTML = html;

  /* HTML биш plain text эсэхийг шалгах */
  const hasHtmlTags = /<[a-z][\s\S]*>/i.test(html);
  if (!hasHtmlTags) {
    const lines = html.split(/\n+/).filter(line => line.trim());
    if (lines.length === 0) return { html: '', hasWordImages };
    return { html: lines.map(line => `<p>${this._escapeHtml(line.trim())}</p>`).join('\n'), hasWordImages };
  }

  /* ============================================
     1. Word local file зургийг устгах (file:///)
     ============================================ */
  tempDiv.querySelectorAll('img').forEach(img => {
    const src = (img.getAttribute('src') || '').toLowerCase();
    /* Word-оос хуулсан local file зураг эсэхийг шалгах */
    if (src.startsWith('file:///') || src.startsWith('file://') || src.includes('msohtmlclip')) {
      hasWordImages = true;
      img.remove();
      return;
    }
  });

  /* Word-ын v:imagedata tag устгах */
  tempDiv.querySelectorAll('v\\:imagedata, [src*="file://"], [src*="msohtmlclip"]').forEach(el => {
    hasWordImages = true;
    el.remove();
  });

  /* ============================================
     2. Facebook emoji зургийг жинхэнэ emoji болгох
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
     3. Facebook tracking URL цэвэрлэх
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
     4. Nested div flatten хийх (зөвхөн нэг div child-тай div-үүдийг unwrap)
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

    /* Тусгай tag-уудад minimal style нэмэх (Vanilla Table стиль) */
    switch (tagName) {
      case 'TABLE':
        node.setAttribute('style', 'width:100%;border-collapse:collapse;margin-bottom:1rem;');
        break;
      case 'TH':
      case 'TD':
        node.setAttribute('style', 'border:1px solid #dee2e6;padding:0.5rem;');
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

  return { html: result.trim(), hasWordImages };
};

/* ============================================
   AI OCR Dialog (Зураг сонгох → HTML)
   ============================================ */

moedit.prototype._ocr = async function() {
  const cfg = this.opts.ocrModal;
  const shineUrl = this.opts.shineUrl;

  /* shineUrl тохируулаагүй бол тайлбарлах dialog харуулах */
  if (!shineUrl || !shineUrl.trim()) {
    this._showAiConfigNotice(cfg.title, 'ocr');
    return;
  }

  /* Selection хадгалах */
  let savedRange = null;
  const selection = window.getSelection();
  if (selection.rangeCount > 0) {
    savedRange = selection.getRangeAt(0).cloneRange();
  }

  /* Modal үүсгэх */
  const dialogId = 'moedit-ocr-dialog-' + Date.now();
  const dialog = document.createElement('div');
  dialog.id = dialogId;
  dialog.className = 'moedit-modal-overlay';
  const defaultPrompt = cfg.defaultPrompt || '';
  const resetLabel = this._isMn ? 'Анхны утга' : 'Reset';
  const selectImageText = this._isMn ? 'Зураг сонгоно уу...' : 'Select image...';
  const browseText = this._isMn ? 'Сонгох' : 'Browse';
  const insertText = this._isMn ? 'Оруулах' : 'Insert';
  dialog.innerHTML = `
    <div class="moedit-modal">
      <h5 class="moedit-modal-title"><i class="mi-file-text text-info"></i> ${cfg.title}</h5>
      <p class="moedit-modal-desc">${cfg.description}</p>
      <div class="moedit-modal-field">
        <div class="moedit-modal-file-input">
          <input type="text" class="moedit-modal-input moedit-modal-input-readonly" id="${dialogId}-filename" readonly placeholder="${selectImageText}">
          <button type="button" class="moedit-modal-btn moedit-modal-btn-primary" id="${dialogId}-browse">
            <i class="mi-folder2-open"></i> ${browseText}
          </button>
        </div>
        <input type="file" id="${dialogId}-file" accept="image/*" style="display:none;">
      </div>
      <div class="ocr-preview" id="${dialogId}-preview" style="display:none; margin:10px 0; text-align:center;">
        <img id="${dialogId}-preview-img" src="" style="max-width:100%; max-height:200px; border:1px solid var(--mo-border); border-radius:var(--mo-radius);">
      </div>
      <div class="moedit-modal-field ocr-prompt-field" style="display:none;">
        <label class="moedit-modal-label">
          <i class="mi-chat-square-text"></i> ${cfg.promptLabel}
          <button type="button" class="btn-reset-prompt" style="float:right; background:none; border:none; color:var(--mo-primary, #0d6efd); cursor:pointer; font-size:12px; padding:0;">
            <i class="mi-arrow-counterclockwise"></i> ${resetLabel}
          </button>
        </label>
        <textarea class="moedit-modal-textarea ocr-prompt" rows="5" style="font-size:13px; font-family:monospace;">${defaultPrompt}</textarea>
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
          <i class="mi-file-text"></i> ${cfg.confirmText}
        </button>
        <button type="button" class="moedit-modal-btn moedit-modal-btn-success btn-confirm" style="display:none;">
          <i class="mi-check-lg"></i> ${insertText}
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
  const promptField = dialog.querySelector('.ocr-prompt-field');
  const promptTextarea = dialog.querySelector('.ocr-prompt');
  const resetPromptBtn = dialog.querySelector('.btn-reset-prompt');
  const statusEl = dialog.querySelector(`#${dialogId}-status`);
  const resultEl = dialog.querySelector(`#${dialogId}-result`);
  const errorEl = dialog.querySelector(`#${dialogId}-error`);
  const convertBtn = dialog.querySelector('.btn-convert');
  const confirmBtn = dialog.querySelector('.btn-confirm');
  const cancelBtn = dialog.querySelector('.btn-cancel');

  /* Анхны утга сэргээх товч */
  resetPromptBtn.addEventListener('click', () => {
    promptTextarea.value = defaultPrompt;
  });

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
    promptField.style.display = 'block'; /* Prompt талбарыг харуулах */

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
    promptField.style.display = 'none'; /* Prompt талбарыг нуух */

    /* Хэрэглэгчийн оруулсан prompt авах */
    const customPrompt = promptTextarea.value.trim();

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
          images: [base64Image],
          prompt: customPrompt
        })
      });

      if (!response.ok) {
        throw new Error(this._isMn ? `AI OCR алдаа: ${response.status} ${response.statusText}` : `AI OCR error: ${response.status} ${response.statusText}`);
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

        this._notify('success', cfg.successMessage);
      } else if (data.status === 'error') {
        throw new Error(data.message || cfg.errorMessage);
      } else {
        throw new Error(this._isMn ? 'Хүлээгдээгүй хариу ирлээ' : 'Unexpected response');
      }
    } catch (err) {
      errorEl.textContent = err.message || cfg.errorMessage;
      errorEl.style.display = 'block';
      promptField.style.display = 'block'; /* Prompt талбарыг дахин харуулах */
      isBusy = false;
      convertBtn.disabled = false;
      cancelBtn.disabled = false;
      browseBtn.disabled = false;

      this._notify('danger', err.message || cfg.errorMessage);
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
  if (!printWindow) {
    this._notify?.('error', this._isMn ? 'Popup хориглогдсон байна' : 'Popup blocked');
    return;
  }

  /* Толгой зураг авах */
  let headerImageHtml = '';
  const headerArea = this.headerImageArea || this.root.querySelector('.moedit-header-image-area');
  const headerPreview = this.headerImagePreview || this.root.querySelector('.moedit-header-image-preview');
  /* headerImageArea харагдаж байгаа бөгөөд зураг ачаалагдсан үед л хэвлэх */
  if (headerArea && headerArea.style.display !== 'none' && headerPreview && headerPreview.naturalWidth > 0) {
    const imgSrc = headerPreview.src;
    if (imgSrc && !imgSrc.startsWith('data:,') && !imgSrc.endsWith('/')) {
      headerImageHtml = `<img src="${imgSrc}" style="max-width:100%;height:auto;margin-bottom:1rem;display:block;">`;
    }
  }

  /* Контент авах */
  const content = this.getHTML();

  printWindow.document.write(`
    <!DOCTYPE html>
    <html>
      <head>
        <meta charset="UTF-8">
        <title>${this._isMn ? 'Хэвлэх' : 'Print'}</title>
        <style>
          body { font-family: system-ui, -apple-system, sans-serif; line-height: 1.6; padding: 2rem; max-width: 800px; margin: 0 auto; }
          img { max-width: 100%; height: auto; }
          table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
          td, th { padding: 0.5rem; border: 1px solid #ddd; }
          blockquote { margin: 1rem 0; padding: 0.75rem 1rem; border-left: 4px solid #0d6efd; background: #f8f9fa; }
          pre { background: #f8f9fa; padding: 1rem; overflow-x: auto; }
          @media print { body { padding: 0; } }
        </style>
      </head>
      <body>
        ${headerImageHtml}
        ${content}
      </body>
    </html>
  `);
  printWindow.document.close();
  printWindow.focus();
  setTimeout(() => {
    printWindow.print();
    printWindow.close();
  }, 250);
};

/**
 * Файл хавсаргах - file picker нээх
 */
moedit.prototype._insertAttachment = function() {
  const fileInput = document.createElement('input');
  fileInput.type = 'file';
  fileInput.multiple = true;
  fileInput.style.display = 'none';
  document.body.appendChild(fileInput);

  fileInput.addEventListener('change', () => {
    if (fileInput.files && fileInput.files.length > 0) {
      const now = new Date();
      const dateStr = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
      for (let i = 0; i < fileInput.files.length; i++) {
        const file = fileInput.files[i];
        const mimeType = file.type || '';
        const type = mimeType.split('/')[0];
        this._attachments.push({
          _localId: 'att_' + Date.now() + '_' + i,
          _file: file,
          _isExisting: false,
          name: file.name,
          size: file.size,
          type: ['image', 'video', 'audio'].includes(type) ? type : 'document',
          mime_content_type: mimeType,
          description: '',
          date: dateStr
        });
      }
      this._renderAttachments();
      this._emitAttachmentChange();
      this._notify?.('success', this._isMn ? 'Файл хавсаргагдлаа' : 'File attached');
    }
    fileInput.remove();
  });

  fileInput.addEventListener('cancel', () => fileInput.remove());
  fileInput.click();
};

/**
 * Хавсралт файлуудын хүснэгт зурах
 */
moedit.prototype._renderAttachments = function() {
  if (!this._attachmentsArea || !this._attachmentsTbody) return;
  const isMn = this._isMn;
  const isReadonly = this.opts.readonly;

  if (this._attachments.length === 0) {
    this._attachmentsArea.style.display = 'none';
    return;
  }
  this._attachmentsArea.style.display = '';

  this._attachmentsTbody.innerHTML = '';
  this._attachments.forEach((file, idx) => {
    const tr = document.createElement('tr');

    /* File */
    const fileName = file.path ? file.path.split('/').pop() : (file.name || '');
    let sizeStr = '';
    if (file.size != null) {
      if (file.size < 1024) sizeStr = file.size + ' B';
      else if (file.size < 1048576) sizeStr = (file.size / 1024).toFixed(1) + ' KB';
      else sizeStr = (file.size / 1048576).toFixed(2) + ' MB';
    }
    const tdFile = document.createElement('td');
    const displayName = file._isExisting ? fileName : file.name;
    if (file.path || file._file) {
      const link = document.createElement('a');
      link.href = file.path || (file._file ? URL.createObjectURL(file._file) : '#');
      link.target = '_blank';
      link.className = 'moedit-attach-link';
      link.textContent = displayName;
      tdFile.appendChild(link);
    } else {
      tdFile.textContent = displayName;
    }
    if (!isReadonly) {
      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.className = 'moedit-attach-remove';
      removeBtn.title = isMn ? 'Устгах' : 'Remove';
      removeBtn.innerHTML = '<i class="mi-trash"></i>';
      removeBtn.addEventListener('click', () => {
        if (file._isExisting && file.id) {
          this._deletedAttachmentIds.push(file.id);
        }
        this._attachments.splice(idx, 1);
        this._renderAttachments();
        this._emitAttachmentChange();
      });
      tdFile.appendChild(removeBtn);
    }
    tr.appendChild(tdFile);

    /* Properties */
    const tdProps = document.createElement('td');
    tdProps.innerHTML = `<span class="moedit-attach-type">${file.type || ''}</span><span class="moedit-attach-size">${sizeStr}</span>`;
    tr.appendChild(tdProps);

    /* Description */
    const tdDesc = document.createElement('td');
    if (isReadonly) {
      tdDesc.textContent = file.description || '';
    } else {
      const descInput = document.createElement('input');
      descInput.type = 'text';
      descInput.className = 'moedit-attach-desc';
      descInput.value = file.description || '';
      descInput.placeholder = isMn ? 'Тайлбар...' : 'Description...';
      descInput.addEventListener('change', () => {
        file.description = descInput.value;
        this._emitAttachmentChange();
      });
      tdDesc.appendChild(descInput);
    }
    tr.appendChild(tdDesc);

    /* Date */
    const tdDate = document.createElement('td');
    tdDate.textContent = file.date || file.created_at || '';
    tr.appendChild(tdDate);

    this._attachmentsTbody.appendChild(tr);
  });
};

/**
 * Attachment change callback дуудах
 */
moedit.prototype._emitAttachmentChange = function() {
  if (typeof this.opts.onAttachmentChange === 'function') {
    this.opts.onAttachmentChange(this._attachments);
  }
};

/**
 * Preview - контентыг popup цонхонд rendered харуулах
 */
moedit.prototype._preview = function() {
  const previewWindow = window.open('', '_blank');
  if (!previewWindow) {
    this._notify?.('error', this._isMn ? 'Popup хориглогдсон байна' : 'Popup blocked');
    return;
  }

  /* Title авах */
  let titleHtml = '';
  if (this.opts.titleSelector) {
    const titleEl = document.querySelector(this.opts.titleSelector);
    if (titleEl) {
      const titleVal = (titleEl.value || titleEl.textContent || '').trim();
      if (titleVal) {
        titleHtml = `<h2>${titleVal}</h2>`;
      }
    }
  }

  /* Толгой зураг авах */
  let headerImageHtml = '';
  const headerArea = this.headerImageArea || this.root.querySelector('.moedit-header-image');
  const headerPreview = this.headerImagePreview || this.root.querySelector('.moedit-header-image-preview');
  if (headerArea && headerArea.style.display !== 'none' && headerPreview && headerPreview.naturalWidth > 0) {
    const imgSrc = headerPreview.src;
    if (imgSrc && !imgSrc.startsWith('data:,') && !imgSrc.endsWith('/')) {
      headerImageHtml = `<img class="img-fluid mt-1" src="${imgSrc}">`;
    }
  }

  /* Контент авах */
  const content = this.getHTML();

  /* Хавсралт файлууд */
  let filesHtml = '';
  if (this._attachments && this._attachments.length > 0) {
    const isMn = this._isMn;
    let rows = '';
    this._attachments.forEach((file, idx) => {
      const fileName = file.path ? file.path.split('/').pop() : (file.name || '');
      let icon = '<i class="bi bi-file-earmark"></i>';
      if (file.type === 'image') icon = '<i class="bi bi-file-image text-success"></i>';
      else if (file.type === 'video') icon = '<i class="bi bi-file-play text-danger"></i>';
      else if (file.type === 'audio') icon = '<i class="bi bi-file-music text-info"></i>';
      else if (file.mime_content_type) {
        const mime = file.mime_content_type;
        if (mime === 'application/pdf') icon = '<i class="bi bi-file-pdf text-danger"></i>';
        else if (mime.startsWith('application/vnd.ms-excel') || mime.startsWith('application/vnd.openxmlformats-officedocument.spreadsheet')) icon = '<i class="bi bi-file-excel text-success"></i>';
        else if (mime.startsWith('application/msword') || mime.startsWith('application/vnd.openxmlformats-officedocument.wordprocessing')) icon = '<i class="bi bi-file-word text-primary"></i>';
        else if (mime.startsWith('application/zip') || mime.startsWith('application/x-rar') || mime.startsWith('application/x-7z')) icon = '<i class="bi bi-file-zip text-warning"></i>';
      }
      let sizeStr = '';
      if (file.size != null) {
        if (file.size < 1024) sizeStr = file.size + ' B';
        else if (file.size < 1048576) sizeStr = (file.size / 1024).toFixed(1) + ' KB';
        else sizeStr = (file.size / 1048576).toFixed(2) + ' MB';
      }
      const descHtml = file.description ? `<small class="text-muted d-block">${file.description}</small>` : '';
      const linkHref = file.path || (file._file ? URL.createObjectURL(file._file) : '#');
      rows += `<tr>
        <td>${idx + 1}</td>
        <td><a href="${linkHref}" target="_blank" class="text-decoration-none">${icon} ${file._isExisting ? fileName : file.name}</a>${descHtml}</td>
        <td>${sizeStr}</td>
        <td><span class="badge bg-secondary">${file.type || ''}</span></td>
      </tr>`;
    });
    filesHtml = `
      <div class="col-12 mt-5">
        <hr>
        <h5><i class="bi bi-paperclip"></i> ${isMn ? 'Хавсралт файлууд' : 'Attachments'}</h5>
        <table class="table table-striped table-hover">
          <thead><tr>
            <th style="width:50px">#</th>
            <th>${isMn ? 'Файл' : 'File'}</th>
            <th style="width:120px">${isMn ? 'Хэмжээ' : 'Size'}</th>
            <th style="width:100px">${isMn ? 'Төрөл' : 'Type'}</th>
          </tr></thead>
          <tbody>${rows}</tbody>
        </table>
      </div>`;
  }

  previewWindow.document.write(`<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>${this._isMn ? 'Урьдчилан харах' : 'Preview'}</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    img { max-width: 100%; height: auto; }
    table { width: 100%; }
    blockquote { margin: 1rem 0; padding: 0.75rem 1rem; border-left: 4px solid #0d6efd; background: #f8f9fa; }
    pre { background: #f8f9fa; padding: 1rem; overflow-x: auto; }
  </style>
</head>
<body>
  <main class="container" style="width:50rem;margin-top:.75rem;margin-bottom:.75rem;">
    ${titleHtml}
    ${headerImageHtml}
    <div class="col-12 mt-1">${content}</div>
    ${filesHtml}
  </main>
</body>
</html>`);
  previewWindow.document.close();
  previewWindow.focus();
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
  const email = this.opts.prompt(this._isMn ? "Email хаяг оруулна уу" : "Enter email address", "example@domain.com");
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
      <h5 class="moedit-modal-title"><i class="mi-youtube"></i> ${config.title}</h5>
      <div class="moedit-modal-field">
        <label class="moedit-modal-label">${config.urlLabel}</label>
        <textarea class="moedit-modal-input" id="${dialogId}-url" rows="3" placeholder="${config.placeholder}" style="resize:vertical;min-height:80px;"></textarea>
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

  /**
   * YouTube видео ID задлах
   * Дэмжих форматууд:
   * 1. <iframe src="..."></iframe> - embed код
   * 2. https://www.youtube.com/watch?v=VIDEO_ID
   * 3. https://youtu.be/VIDEO_ID
   * 4. https://www.youtube.com/embed/VIDEO_ID
   * 5. https://www.youtube.com/shorts/VIDEO_ID
   * 6. VIDEO_ID шууд (11 тэмдэгт)
   */
  const extractYouTubeId = (input) => {
    input = input.trim();

    /* iframe tag-аас src гаргах */
    const iframeMatch = input.match(/<iframe[^>]+src=["']([^"']+)["']/i);
    if (iframeMatch) {
      input = iframeMatch[1];
    }

    const patterns = [
      /(?:youtube\.com\/watch\?v=|youtube\.com\/embed\/|youtu\.be\/|youtube\.com\/v\/|youtube\.com\/shorts\/)([a-zA-Z0-9_-]{11})/,
      /^([a-zA-Z0-9_-]{11})$/
    ];
    for (const pattern of patterns) {
      const match = input.match(pattern);
      if (match) return match[1];
    }
    return null;
  };

  /* URL өөрчлөгдөхөд preview харуулах */
  let debounceTimer;
  urlInput.addEventListener('input', () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
      const input = urlInput.value.trim();
      const videoId = extractYouTubeId(input);
      if (videoId) {
        previewIframe.src = 'https://www.youtube.com/embed/' + videoId;
        previewDiv.style.display = 'block';
        errorDiv.style.display = 'none';
      } else if (input) {
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

  /* OK товч */
  okBtn.addEventListener('click', () => {
    const input = urlInput.value.trim();
    const videoId = extractYouTubeId(input);

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

    /* Block элемент учир P tag нэмэх */
    const fragment = document.createDocumentFragment();

    const pBefore = document.createElement('p');
    pBefore.innerHTML = '<br>';

    /* Responsive wrapper div үүсгэх */
    const wrapper = document.createElement('div');
    wrapper.style.maxWidth = '560px';
    wrapper.style.margin = '10px 0';
    wrapper.setAttribute('contenteditable', 'false');

    const iframe = document.createElement('iframe');
    iframe.src = 'https://www.youtube.com/embed/' + videoId;
    iframe.width = '560';
    iframe.height = '315';
    iframe.style.border = 'none';
    iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
    iframe.allowFullscreen = true;
    iframe.setAttribute('loading', 'lazy');
    iframe.setAttribute('referrerpolicy', 'no-referrer-when-downgrade');

    wrapper.appendChild(iframe);

    const pAfter = document.createElement('p');
    pAfter.innerHTML = '<br>';

    fragment.appendChild(pBefore);
    fragment.appendChild(wrapper);
    fragment.appendChild(pAfter);

    const sel = window.getSelection();
    if (sel.rangeCount > 0) {
      const range = sel.getRangeAt(0);
      range.deleteContents();
      range.insertNode(fragment);

      const newRange = document.createRange();
      newRange.setStart(pAfter, 0);
      newRange.collapse(true);
      sel.removeAllRanges();
      sel.addRange(newRange);
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
      <h5 class="moedit-modal-title"><i class="mi-facebook"></i> ${config.title}</h5>
      <div class="moedit-modal-field">
        <label class="moedit-modal-label">${config.urlLabel}</label>
        <textarea class="moedit-modal-input" id="${dialogId}-url" rows="3" placeholder="${config.placeholder}" style="resize:vertical;min-height:80px;"></textarea>
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

  /**
   * Facebook URL задлах
   * Дэмжих форматууд:
   * 1. <iframe src="..."></iframe> - embed код
   * 2. https://www.facebook.com/... - видео URL
   * 3. https://fb.watch/... - short URL
   */
  const extractFacebookUrl = (input) => {
    input = input.trim();

    /* iframe tag-аас src гаргах */
    const iframeMatch = input.match(/<iframe[^>]+src=["']([^"']+)["']/i);
    if (iframeMatch) {
      /* Facebook plugins URL-аас href параметр гаргах */
      const hrefMatch = iframeMatch[1].match(/[?&]href=([^&]+)/);
      if (hrefMatch) {
        return decodeURIComponent(hrefMatch[1]);
      }
      return iframeMatch[1];
    }

    /* Facebook URL шалгах */
    if (input.includes('facebook.com') || input.includes('fb.watch')) {
      return input;
    }

    return null;
  };

  /* URL өөрчлөгдөхөд preview харуулах */
  let debounceTimer;
  urlInput.addEventListener('input', () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
      const fbUrl = extractFacebookUrl(urlInput.value);
      if (fbUrl) {
        previewIframe.src = 'https://www.facebook.com/plugins/video.php?href=' + encodeURIComponent(fbUrl) + '&show_text=0&width=500';
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

  /* OK товч */
  okBtn.addEventListener('click', () => {
    const fbUrl = extractFacebookUrl(urlInput.value);

    if (!fbUrl) {
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

    /* Block элемент учир P tag нэмэх */
    const fragment = document.createDocumentFragment();

    const pBefore = document.createElement('p');
    pBefore.innerHTML = '<br>';

    /* Responsive wrapper div үүсгэх */
    const wrapper = document.createElement('div');
    wrapper.style.maxWidth = '560px';
    wrapper.style.margin = '10px 0';
    wrapper.setAttribute('contenteditable', 'false');

    const iframe = document.createElement('iframe');
    iframe.src = 'https://www.facebook.com/plugins/video.php?href=' + encodeURIComponent(fbUrl) + '&show_text=0&width=560';
    iframe.width = '560';
    iframe.height = '315';
    iframe.style.border = 'none';
    iframe.scrolling = 'no';
    iframe.allow = 'encrypted-media';
    iframe.setAttribute('loading', 'lazy');
    iframe.setAttribute('referrerpolicy', 'no-referrer-when-downgrade');

    wrapper.appendChild(iframe);

    const pAfter = document.createElement('p');
    pAfter.innerHTML = '<br>';

    fragment.appendChild(pBefore);
    fragment.appendChild(wrapper);
    fragment.appendChild(pAfter);

    const sel = window.getSelection();
    if (sel.rangeCount > 0) {
      const range = sel.getRangeAt(0);
      range.deleteContents();
      range.insertNode(fragment);

      const newRange = document.createRange();
      newRange.setStart(pAfter, 0);
      newRange.collapse(true);
      sel.removeAllRanges();
      sel.addRange(newRange);
    }

    this._emitChange();
  });
};

/* ============================================
   Twitter/X Embed
   ============================================ */

moedit.prototype._insertTwitter = function() {
  /* Selection хадгалах */
  let savedRange = null;
  const selection = window.getSelection();
  if (selection.rangeCount > 0) {
    savedRange = selection.getRangeAt(0).cloneRange();
  }

  const config = this.opts.twitterModal;
  const dialogId = 'moedit-twitter-dialog-' + Date.now();
  const dialog = document.createElement('div');
  dialog.id = dialogId;
  dialog.className = 'moedit-modal-overlay';
  dialog.innerHTML = `
    <div class="moedit-modal">
      <h5 class="moedit-modal-title"><i class="mi-twitter-x"></i> ${config.title}</h5>
      <div class="moedit-modal-field">
        <label class="moedit-modal-label">${config.urlLabel}</label>
        <textarea class="moedit-modal-input" id="${dialogId}-url" rows="3" placeholder="${config.placeholder}" style="resize:vertical;min-height:80px;"></textarea>
        <div class="moedit-modal-hint" style="margin-top: 6px; font-size: 12px; color: #888;">${config.hint}</div>
      </div>
      <div class="moedit-modal-preview" id="${dialogId}-preview" style="display: none; margin-top: 12px;">
        <div style="position: relative; width: 100%; max-width: 550px; height: 300px; background: #f7f9fa; border-radius: 6px; overflow: hidden;">
          <iframe id="${dialogId}-iframe" style="width: 100%; height: 100%; border: none;"></iframe>
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

  /**
   * Twitter/X tweet ID задлах
   * Дэмжих форматууд:
   * 1. https://twitter.com/username/status/1234567890
   * 2. https://x.com/username/status/1234567890
   * 3. https://twitter.com/username/status/1234567890?s=20
   * 4. Tweet ID шууд (цифр)
   */
  const extractTweetId = (input) => {
    input = input.trim();

    const patterns = [
      /(?:twitter\.com|x\.com)\/\w+\/status\/(\d+)/,
      /^(\d{10,})$/
    ];
    for (const pattern of patterns) {
      const match = input.match(pattern);
      if (match) return match[1];
    }
    return null;
  };

  /* URL өөрчлөгдөхөд preview харуулах */
  let debounceTimer;
  urlInput.addEventListener('input', () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
      const input = urlInput.value.trim();
      const tweetId = extractTweetId(input);
      if (tweetId) {
        previewIframe.src = 'https://platform.twitter.com/embed/Tweet.html?id=' + tweetId;
        previewDiv.style.display = 'block';
        errorDiv.style.display = 'none';
      } else if (input) {
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

  /* OK товч */
  okBtn.addEventListener('click', () => {
    const input = urlInput.value.trim();
    const tweetId = extractTweetId(input);

    if (!tweetId) {
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

    /* Block элемент учир P tag нэмэх */
    const fragment = document.createDocumentFragment();

    const pBefore = document.createElement('p');
    pBefore.innerHTML = '<br>';

    /* Responsive wrapper div үүсгэх */
    const wrapper = document.createElement('div');
    wrapper.style.maxWidth = '550px';
    wrapper.style.margin = '10px 0';
    wrapper.setAttribute('contenteditable', 'false');

    const iframe = document.createElement('iframe');
    iframe.src = 'https://platform.twitter.com/embed/Tweet.html?id=' + tweetId;
    iframe.width = '550';
    iframe.height = '300';
    iframe.style.border = 'none';
    iframe.setAttribute('loading', 'lazy');
    iframe.setAttribute('referrerpolicy', 'no-referrer-when-downgrade');

    wrapper.appendChild(iframe);

    const pAfter = document.createElement('p');
    pAfter.innerHTML = '<br>';

    fragment.appendChild(pBefore);
    fragment.appendChild(wrapper);
    fragment.appendChild(pAfter);

    const sel = window.getSelection();
    if (sel.rangeCount > 0) {
      const range = sel.getRangeAt(0);
      range.deleteContents();
      range.insertNode(fragment);

      const newRange = document.createRange();
      newRange.setStart(pAfter, 0);
      newRange.collapse(true);
      sel.removeAllRanges();
      sel.addRange(newRange);
    }

    this._emitChange();
  });
};

/* ============================================
   Google Maps Embed
   ============================================ */

moedit.prototype._insertMap = function() {
  /* Selection хадгалах */
  let savedRange = null;
  const selection = window.getSelection();
  if (selection.rangeCount > 0) {
    savedRange = selection.getRangeAt(0).cloneRange();
  }

  const config = this.opts.mapModal;
  const dialogId = 'moedit-map-dialog-' + Date.now();
  const dialog = document.createElement('div');
  dialog.id = dialogId;
  dialog.className = 'moedit-modal-overlay';
  dialog.innerHTML = `
    <div class="moedit-modal">
      <h5 class="moedit-modal-title"><i class="mi-geo-alt"></i> ${config.title}</h5>
      <div class="moedit-modal-field">
        <label class="moedit-modal-label">${config.urlLabel}</label>
        <textarea class="moedit-modal-input" id="${dialogId}-url" rows="3" placeholder="${config.placeholder}" style="resize:vertical;min-height:80px;"></textarea>
        <div class="moedit-modal-hint" style="margin-top: 6px; font-size: 12px; color: #888;">${config.hint}</div>
      </div>
      <div class="moedit-modal-preview" id="${dialogId}-preview" style="display: none; margin-top: 12px;">
        <div style="position: relative; width: 100%; height: 200px; background: #f0f2f5; border-radius: 6px; overflow: hidden;">
          <iframe id="${dialogId}-iframe" style="width: 100%; height: 100%; border: none;"></iframe>
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

  /**
   * Google Maps embed URL гаргаж авах
   * Дэмжих форматууд:
   * 1. <iframe src="..."></iframe> - embed код шууд
   * 2. https://www.google.com/maps/embed?pb=... - embed URL
   * 3. https://www.google.com/maps/place/... - place URL
   * 4. https://www.google.com/maps?q=... - search URL
   * 5. https://maps.google.com/... - maps URL
   * 6. https://goo.gl/maps/... - short URL
   */
  const extractMapEmbedUrl = (input) => {
    input = input.trim();

    /* iframe tag-аас src гаргах */
    const iframeMatch = input.match(/<iframe[^>]+src=["']([^"']+)["']/i);
    if (iframeMatch) {
      return iframeMatch[1];
    }

    /* Embed URL шууд байвал */
    if (input.includes('google.com/maps/embed')) {
      return input;
    }

    /* Place URL → embed болгох */
    const placeMatch = input.match(/google\.com\/maps\/place\/([^\/\?]+)/);
    if (placeMatch) {
      const place = placeMatch[1];
      return `https://www.google.com/maps/embed/v1/place?key=&q=${encodeURIComponent(place.replace(/\+/g, ' '))}`;
    }

    /* Search URL → embed болгох */
    const searchMatch = input.match(/google\.com\/maps\?q=([^&]+)/);
    if (searchMatch) {
      return `https://www.google.com/maps/embed?pb=!1m14!1m12!1m3!1d10000!2d0!3d0!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!5e0!3m2!1sen!2s!4v1!5m2!1sen!2s&q=${searchMatch[1]}`;
    }

    /* @координат байвал */
    const coordMatch = input.match(/@(-?\d+\.?\d*),(-?\d+\.?\d*)/);
    if (coordMatch) {
      const lat = coordMatch[1];
      const lng = coordMatch[2];
      return `https://www.google.com/maps/embed?pb=!1m14!1m12!1m3!1d5000!2d${lng}!3d${lat}!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!5e0!3m2!1sen!2s!4v1`;
    }

    /* Google Maps домэйн байвал ерөнхий embed үүсгэх */
    if (input.includes('google.com/maps') || input.includes('maps.google.com') || input.includes('goo.gl/maps')) {
      /* URL-ээс pb= параметр хайх */
      const pbMatch = input.match(/[?&]pb=([^&]+)/);
      if (pbMatch) {
        return `https://www.google.com/maps/embed?pb=${pbMatch[1]}`;
      }
    }

    return null;
  };

  /* URL өөрчлөгдөхөд preview харуулах */
  let debounceTimer;
  urlInput.addEventListener('input', () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
      const embedUrl = extractMapEmbedUrl(urlInput.value);
      if (embedUrl) {
        previewIframe.src = embedUrl;
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

  /* OK товч */
  okBtn.addEventListener('click', () => {
    const input = urlInput.value.trim();

    if (!input) {
      urlInput.focus();
      return;
    }

    const embedUrl = extractMapEmbedUrl(input);
    if (!embedUrl) {
      this._notify('danger', config.invalidUrl);
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

    /* Block элемент учир P tag нэмэх */
    const fragment = document.createDocumentFragment();

    const pBefore = document.createElement('p');
    pBefore.innerHTML = '<br>';

    /* Responsive wrapper div үүсгэх */
    const wrapper = document.createElement('div');
    wrapper.style.position = 'relative';
    wrapper.style.width = '100%';
    wrapper.style.maxWidth = '600px';
    wrapper.style.height = '450px';
    wrapper.style.margin = '10px 0';
    wrapper.style.borderRadius = '8px';
    wrapper.style.overflow = 'hidden';
    wrapper.setAttribute('contenteditable', 'false');

    const iframe = document.createElement('iframe');
    iframe.src = embedUrl;
    iframe.style.width = '100%';
    iframe.style.height = '100%';
    iframe.style.border = 'none';
    iframe.setAttribute('allowfullscreen', '');
    iframe.setAttribute('loading', 'lazy');
    iframe.setAttribute('referrerpolicy', 'no-referrer-when-downgrade');

    wrapper.appendChild(iframe);

    const pAfter = document.createElement('p');
    pAfter.innerHTML = '<br>';

    fragment.appendChild(pBefore);
    fragment.appendChild(wrapper);
    fragment.appendChild(pAfter);

    const sel = window.getSelection();
    if (sel.rangeCount > 0) {
      const range = sel.getRangeAt(0);
      range.deleteContents();
      range.insertNode(fragment);

      const newRange = document.createRange();
      newRange.setStart(pAfter, 0);
      newRange.collapse(true);
      sel.removeAllRanges();
      sel.addRange(newRange);
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
   PDF to HTML Dialog
   ============================================ */

/**
 * PDF.js санг динамикаар ачаалах
 * @returns {Promise<Object>} pdfjsLib объект
 */
moedit.prototype._loadPdfJs = function() {
  const isMn = this._isMn;
  return new Promise((resolve, reject) => {
    /* Аль хэдийн ачаалагдсан бол шууд буцаах */
    if (window.pdfjsLib) {
      resolve(window.pdfjsLib);
      return;
    }

    const pdfJsVersion = '4.10.38';
    const cdnBase = `https://cdnjs.cloudflare.com/ajax/libs/pdf.js/${pdfJsVersion}`;

    /* ES module script үүсгэх */
    const script = document.createElement('script');
    script.type = 'module';
    script.textContent = `
      import * as pdfjsLib from '${cdnBase}/pdf.min.mjs';
      pdfjsLib.GlobalWorkerOptions.workerSrc = '${cdnBase}/pdf.worker.min.mjs';
      window.pdfjsLib = pdfjsLib;
      window.dispatchEvent(new Event('pdfjsLoaded'));
    `;

    /* Ачаалалт дууссаныг хүлээх */
    const onLoaded = () => {
      window.removeEventListener('pdfjsLoaded', onLoaded);
      if (window.pdfjsLib) {
        resolve(window.pdfjsLib);
      } else {
        reject(new Error(isMn ? 'PDF.js ачаалж чадсангүй' : 'Failed to load PDF.js'));
      }
    };

    window.addEventListener('pdfjsLoaded', onLoaded);

    /* Timeout тохируулах */
    setTimeout(() => {
      window.removeEventListener('pdfjsLoaded', onLoaded);
      if (!window.pdfjsLib) {
        reject(new Error(isMn ? 'PDF.js ачаалах хугацаа хэтэрлээ' : 'PDF.js loading timeout'));
      }
    }, 15000);

    document.head.appendChild(script);
  });
};

moedit.prototype._insertPdf = async function() {
  const config = this.opts.pdfModal;
  const shineUrl = this.opts.shineUrl;

  /* shineUrl тохируулаагүй бол тайлбарлах dialog харуулах */
  if (!shineUrl || !shineUrl.trim()) {
    this._showAiConfigNotice(config.title, 'pdf');
    return;
  }

  /* Selection хадгалах */
  let savedRange = null;
  const selection = window.getSelection();
  if (selection.rangeCount > 0) {
    savedRange = selection.getRangeAt(0).cloneRange();
  }

  /* PDF.js сан динамикаар ачаалах */
  let pdfjsLib;
  try {
    pdfjsLib = await this._loadPdfJs();
  } catch (err) {
    this._notify('danger', config.title, err.message);
    return;
  }

  /* Modal үүсгэх */
  const dialogId = 'moedit-pdf-dialog-' + Date.now();
  const dialog = document.createElement('div');
  dialog.id = dialogId;
  dialog.className = 'moedit-modal-overlay';
  const defaultPrompt = config.defaultPrompt || '';
  const resetLabel = this._isMn ? 'Анхны утга' : 'Reset';
  const selectPagesText = this._isMn ? 'Хуудас сонгох:' : 'Select pages:';
  const selectAllText = this._isMn ? 'Бүгдийг' : 'Select All';
  const clearText = this._isMn ? 'Арилгах' : 'Clear';
  const loadingPagesText = this._isMn ? 'Хуудсуудыг ачаалж байна...' : 'Loading pages...';
  dialog.innerHTML = `
    <div class="moedit-modal moedit-modal-lg">
      <h5 class="moedit-modal-title"><i class="mi-file-earmark-pdf text-danger"></i> ${config.title}</h5>
      <p class="moedit-modal-desc">${config.description}</p>
      <div class="moedit-modal-field">
        <div class="moedit-modal-file-input">
          <input type="text" class="moedit-modal-input moedit-modal-input-readonly" id="${dialogId}-filename" readonly placeholder="${config.placeholder}">
          <button type="button" class="moedit-modal-btn moedit-modal-btn-primary" id="${dialogId}-browse">
            <i class="mi-folder2-open"></i> ${config.browseText}
          </button>
        </div>
        <input type="file" id="${dialogId}-file" accept=".pdf,application/pdf" style="display:none;">
      </div>
      <div class="pdf-info" id="${dialogId}-info" style="display:none; margin:10px 0; padding:10px; background:var(--mo-bg); border:1px solid var(--mo-border); border-radius:var(--mo-radius);">
        <small class="text-muted"></small>
      </div>
      <div class="pdf-pages-container" id="${dialogId}-pages-container" style="display:none; margin:10px 0;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
          <small class="text-muted">${selectPagesText}</small>
          <div>
            <button type="button" class="moedit-modal-btn moedit-modal-btn-sm moedit-modal-btn-secondary" id="${dialogId}-select-all">${selectAllText}</button>
            <button type="button" class="moedit-modal-btn moedit-modal-btn-sm moedit-modal-btn-secondary" id="${dialogId}-select-none">${clearText}</button>
          </div>
        </div>
        <div class="pdf-pages-grid" id="${dialogId}-pages" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(120px, 1fr)); gap:10px; max-height:300px; overflow-y:auto; padding:5px;"></div>
        <div class="pdf-pages-loading" id="${dialogId}-pages-loading" style="display:none; text-align:center; padding:20px;">
          <div class="spinner-border spinner-border-sm text-danger" role="status"></div>
          <span class="ms-2">${loadingPagesText}</span>
        </div>
      </div>
      <div class="moedit-modal-field pdf-prompt-field" style="display:none;">
        <label class="moedit-modal-label">
          <i class="mi-chat-square-text"></i> ${config.promptLabel}
          <button type="button" class="btn-reset-prompt" style="float:right; background:none; border:none; color:var(--mo-primary, #0d6efd); cursor:pointer; font-size:12px; padding:0;">
            <i class="mi-arrow-counterclockwise"></i> ${resetLabel}
          </button>
        </label>
        <textarea class="moedit-modal-textarea pdf-prompt" rows="5" style="font-size:13px; font-family:monospace;">${defaultPrompt}</textarea>
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
          <i class="mi-file-earmark-pdf"></i> ${config.title}
        </button>
        <button type="button" class="moedit-modal-btn moedit-modal-btn-success btn-confirm" style="display:none;">
          <i class="mi-check-lg"></i> ${config.confirmText}
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
  const promptField = dialog.querySelector('.pdf-prompt-field');
  const promptTextarea = dialog.querySelector('.pdf-prompt');
  const resetPromptBtn = dialog.querySelector('.btn-reset-prompt');
  const statusEl = dialog.querySelector(`#${dialogId}-status`);
  const statusTextEl = dialog.querySelector(`#${dialogId}-status-text`);
  const progressEl = dialog.querySelector(`#${dialogId}-progress`);
  const progressBar = progressEl.querySelector('.progress-bar');
  const resultEl = dialog.querySelector(`#${dialogId}-result`);
  const errorEl = dialog.querySelector(`#${dialogId}-error`);
  const convertBtn = dialog.querySelector('.btn-convert');
  const confirmBtn = dialog.querySelector('.btn-confirm');
  const cancelBtn = dialog.querySelector('.btn-cancel');

  let selectedFile = null;
  let newHtml = null;
  let isBusy = false;
  let pdfDoc = null;  /* Ачаалсан PDF document */
  let selectedPages = new Set();  /* Сонгосон хуудсууд */

  /* Анхны утга сэргээх товч */
  resetPromptBtn.addEventListener('click', () => {
    promptTextarea.value = defaultPrompt;
  });

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
        const pageAltText = this._isMn ? `Хуудас ${pageNum}` : `Page ${pageNum}`;
        pageCard.innerHTML = `
          <img src="${thumbnail}" alt="${pageAltText}" style="width:100%; display:block;">
          <div class="pdf-page-overlay" style="position:absolute; top:0; left:0; right:0; bottom:0; background:rgba(220,53,69,0.3); display:none;"></div>
          <div class="pdf-page-check" style="position:absolute; top:5px; right:5px; width:20px; height:20px; background:#dc3545; border-radius:50%; display:none; align-items:center; justify-content:center;">
            <i class="mi-check" style="color:white; font-size:14px;"></i>
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
      errorEl.textContent = (this._isMn ? 'PDF ачаалахад алдаа: ' : 'Error loading PDF: ') + err.message;
      errorEl.style.display = 'block';
    } finally {
      pagesLoadingEl.style.display = 'none';
    }
  };

  /**
   * Convert товчны төлөв шинэчлэх
   */
  const updateConvertButton = () => {
    const pageText = this._isMn ? 'хуудас' : (selectedPages.size === 1 ? 'page' : 'pages');
    if (selectedPages.size > 0) {
      convertBtn.disabled = false;
      convertBtn.classList.remove('moedit-modal-btn-disabled');
      convertBtn.innerHTML = `<i class="mi-file-earmark-pdf"></i> ${config.title} (${selectedPages.size} ${pageText})`;
    } else {
      convertBtn.disabled = true;
      convertBtn.classList.add('moedit-modal-btn-disabled');
      convertBtn.innerHTML = `<i class="mi-file-earmark-pdf"></i> ${config.title}`;
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
    promptField.style.display = 'block'; /* Prompt талбарыг харуулах */

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
   * AI OCR ашиглан текст задлах (OpenAI Vision)
   * Зөвхөн сонгосон хуудсуудыг боловсруулна
   * @param {string} customPrompt - Хэрэглэгчийн оруулсан prompt
   */
  const extractTextWithAiOcr = async (customPrompt) => {
    if (!pdfDoc) {
      throw new Error(this._isMn ? 'PDF ачаалаагүй байна' : 'PDF not loaded');
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
      const pageLabel = this._isMn ? 'хуудас' : 'page';
      statusTextEl.textContent = this._isMn
        ? `AI OCR боловсруулж байна... (${i + 1}/${totalSelected} - хуудас ${pageNum})`
        : `Processing AI OCR... (${i + 1}/${totalSelected} - page ${pageNum})`;

      const page = await pdfDoc.getPage(pageNum);
      const canvas = await renderPageToCanvas(page, 2);
      const base64Image = canvas.toDataURL('image/png');

      const response = await fetch(shineUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          mode: 'vision',
          images: [base64Image],
          prompt: customPrompt
        })
      });

      if (!response.ok) {
        throw new Error(this._isMn ? `AI OCR алдаа: ${response.status} ${response.statusText}` : `AI OCR error: ${response.status} ${response.statusText}`);
      }

      const data = await response.json();

      if (data.status === 'success' && data.html) {
        if (totalSelected > 1) {
          const pageComment = this._isMn ? `Хуудас ${pageNum}` : `Page ${pageNum}`;
          htmlParts.push(`<!-- ${pageComment} -->\n${data.html}`);
        } else {
          htmlParts.push(data.html);
        }
      } else if (data.status === 'error') {
        throw new Error(data.message || config.errorMessage);
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

    isBusy = true;
    convertBtn.disabled = true;
    cancelBtn.disabled = true;
    browseBtn.disabled = true;
    statusEl.style.display = 'block';
    progressBar.style.width = '0%';
    errorEl.style.display = 'none';
    resultEl.style.display = 'none';
    promptField.style.display = 'none'; /* Prompt талбарыг нуух */

    /* Хэрэглэгчийн оруулсан prompt авах */
    const customPrompt = promptTextarea.value.trim();

    statusTextEl.textContent = this._isMn ? 'AI OCR ашиглан боловсруулж байна...' : 'Processing with AI OCR...';
    pagesContainerEl.style.display = 'none';  /* Хуудасны сонголтыг нуух */

    try {
      /* AI OCR - OpenAI Vision (зөвхөн сонгосон хуудсуудыг) */
      const result = await extractTextWithAiOcr(customPrompt);
      const pageCount = result.pages;
      newHtml = result.html;

      if (!newHtml || !newHtml.trim()) {
        throw new Error(this._isMn ? 'AI OCR текст олдсонгүй.' : 'AI OCR found no text.');
      }

      infoEl.querySelector('small').textContent += ` • ${pageCount} ${config.pageText} (AI OCR)`;

      resultEl.innerHTML = newHtml;
      resultEl.style.display = 'block';
      convertBtn.style.display = 'none';
      confirmBtn.style.display = 'inline-block';
      isBusy = false;
      cancelBtn.disabled = false;

      this._notify('success', config.title, config.successMessage);
    } catch (err) {
      errorEl.textContent = err.message || config.errorMessage;
      errorEl.style.display = 'block';
      pagesContainerEl.style.display = 'block';  /* Хуудасны сонголтыг дахин харуулах */
      promptField.style.display = 'block';       /* Prompt талбарыг дахин харуулах */
      isBusy = false;
      convertBtn.disabled = false;
      cancelBtn.disabled = false;
      browseBtn.disabled = false;
      updateConvertButton();

      this._notify('danger', config.title, err.message || config.errorMessage);
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

/* ============================================
   Header Image - Толгой зураг сонгох
   ============================================ */

/**
 * Толгой зураг сонгох dialog эсвэл file picker нээх
 * Сонгосон зураг editor-ийн дээр preview болж харагдана
 */
moedit.prototype._selectHeaderImage = function() {
  /* File input үүсгэх */
  const fileInput = document.createElement('input');
  fileInput.type = 'file';
  fileInput.accept = 'image/*';
  fileInput.style.display = 'none';
  document.body.appendChild(fileInput);

  fileInput.addEventListener('change', () => {
    if (fileInput.files && fileInput.files[0]) {
      const file = fileInput.files[0];

      /* FileReader ашиглан preview үүсгэх */
      const reader = new FileReader();
      reader.onload = (e) => {
        const preview = e.target.result;

        /* Header image preview харуулах */
        if (this.headerImageArea && this.headerImagePreview) {
          this.headerImagePreview.src = preview;
          this.headerImageArea.style.display = 'block';
        }

        /* File хадгалах */
        this._headerImageFile = file;

        /* Callback дуудах */
        if (typeof this.opts.onHeaderImageChange === 'function') {
          this.opts.onHeaderImageChange(file, preview);
        }

        this._notify('success', this._isMn ? 'Толгой зураг сонгогдлоо' : 'Header image selected');
      };
      reader.readAsDataURL(file);
    }

    /* Цэвэрлэх */
    fileInput.remove();
  });

  /* Cancel хийхэд цэвэрлэх */
  fileInput.addEventListener('cancel', () => {
    fileInput.remove();
  });

  /* File picker нээх */
  fileInput.click();
};
