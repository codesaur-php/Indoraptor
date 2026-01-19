/**
 * moedit - Mongolian WYSIWYG Editor
 *
 * Контент засварлах rich text editor. Bootstrap 5 dark mode дэмждэг.
 *
 * @example
 * // Энгийн ашиглалт
 * const editor = new moedit(document.querySelector('.moedit'));
 *
 * @example
 * // Тохиргоотой
 * const editor = new moedit(document.querySelector('.moedit'), {
 *   uploadUrl: '/api/upload',
 *   onChange: (html) => console.log('Changed:', html),
 *   notify: (type, msg) => showToast(type, msg)
 * });
 *
 * @example
 * // Устгах
 * editor.destroy();
 */
class moedit {
  /**
   * Debounce utility function
   * @private
   * @param {Function} func - Дуудах функц
   * @param {number} wait - Хүлээх хугацаа (ms)
   * @returns {Function} Debounced функц
   */
  static _debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  }

  /**
   * moedit instance үүсгэх
   * @param {HTMLElement} root - Editor-ийн root element (.moedit class-тай)
   * @param {Object} [opts={}] - Тохиргоо
   * @param {Function} [opts.onChange] - HTML өөрчлөгдөх үед дуудагдах callback
   * @param {Function} [opts.prompt] - Prompt dialog функц (default: window.prompt)
   * @param {string} [opts.uploadUrl] - Зураг upload хийх URL
   * @param {Function} [opts.uploadImage] - Зураг upload хийх async функц (file) => url
   * @param {Function} [opts.onUploadSuccess] - Upload амжилттай болсны callback
   * @param {Function} [opts.onUploadError] - Upload алдааны callback
   * @param {Object} [opts.pasteTextModal] - Paste text modal тохиргоо
   * @param {Object} [opts.imageUploadModal] - Image upload modal тохиргоо
   * @param {Object} [opts.linkModal] - Link modal тохиргоо
   * @param {Object} [opts.tableModal] - Table modal тохиргоо
   * @param {Function} [opts.notify] - Notification функц (type, message)
   * @throws {Error} root element байхгүй бол
   */
  constructor(root, opts = {}) {
    if (!root) throw new Error("moedit: root element is required");

    /** @private */
    this._boundHandlers = {};

    /* Хэрэв textarea дамжуулсан бол бүтэц автоматаар үүсгэх */
    if (root.tagName === 'TEXTAREA') {
      this._targetTextarea = root;
      this.root = this._createWrapper(root, opts);
    } else {
      this.root = root;
      this._targetTextarea = null;
    }

    this.editor = this.root.querySelector(".moedit-editor");
    this.source = this.root.querySelector(".moedit-source");
    this.toolbar = this.root.querySelector(".moedit-toolbar");

    /* Toolbar байхгүй бол автоматаар үүсгэх */
    if (!this.toolbar) {
      this._createToolbar();
      this.toolbar = this.root.querySelector(".moedit-toolbar");
    }

    this.isSource = false;

    this.opts = {
      onChange: null,
      prompt: (label, def = "") => window.prompt(label, def),
      uploadUrl: null, /* Image upload URL - null бол URL prompt ашиглана */
      uploadImage: null, /* async function(file) -> url, зураг upload хийх функц */
      onUploadSuccess: null, /* Upload амжилттай болсны дараа дуудагдах callback */
      onUploadError: null, /* Upload алдаа гарсан үед дуудагдах callback */
      /* Paste text modal тохиргоо */
      pasteTextModal: {
        title: 'Цэвэр текст буулгах',
        description: 'Энд буулгасан бүх контент ЦЭВЭР ТЕКСТ болно. Ямар ч HTML tag, formatting үлдэхгүй - зөвхөн текст.',
        placeholder: 'Энд текстээ буулгана уу...',
        cancelText: 'Болих',
        okText: 'OK'
      },
      /* Image upload modal тохиргоо */
      imageUploadModal: {
        title: 'Зураг оруулах',
        placeholder: 'Зураг сонгоогүй байна...',
        browseText: 'Сонгох',
        cancelText: 'Болих',
        uploadText: 'Upload',
        uploadingText: 'Уншиж байна...',
        successMessage: 'Зураг амжилттай орлоо. Та хүсвэл source mode руу шилжүүлж img tag-ийг засах боломжтой.',
        errorMessage: 'Зураг upload хийхэд алдаа гарлаа'
      },
      /* Link modal тохиргоо */
      linkModal: {
        title: 'Холбоос оруулах',
        urlLabel: 'URL хаяг',
        emailLabel: 'Email хаяг',
        textLabel: 'Харуулах текст',
        textHint: '(хоосон бол URL/Email харуулна)',
        cancelText: 'Болих',
        okText: 'OK'
      },
      /* Table modal тохиргоо */
      tableModal: {
        title: 'Хүснэгт оруулах',
        rowsLabel: 'Мөрийн тоо',
        colsLabel: 'Баганы тоо',
        cancelText: 'Болих',
        okText: 'OK'
      },
      /* YouTube modal тохиргоо */
      youtubeModal: {
        title: 'YouTube видео оруулах',
        urlLabel: 'YouTube URL',
        placeholder: 'https://www.youtube.com/watch?v=...',
        hint: 'Жишээ: https://www.youtube.com/watch?v=dQw4w9WgXcQ эсвэл https://youtu.be/dQw4w9WgXcQ',
        invalidUrl: 'YouTube видео ID олдсонгүй. URL зөв эсэхийг шалгана уу.',
        cancelText: 'Болих',
        okText: 'Оруулах'
      },
      /* Facebook modal тохиргоо */
      facebookModal: {
        title: 'Facebook видео оруулах',
        urlLabel: 'Facebook видео URL',
        placeholder: 'https://www.facebook.com/...',
        hint: 'Facebook видео эсвэл reel-ийн URL хуулж буулгана уу',
        cancelText: 'Болих',
        okText: 'Оруулах'
      },
      /* Shine modal тохиргоо */
      shineModal: {
        title: 'AI Shine',
        description: 'Контентын бүтцийг шинжилж table, card, accordion зэрэг Bootstrap 5 компонент болгоно.',
        processingText: 'Боловсруулж байна...',
        successMessage: 'Контент амжилттай гоёжууллаа!',
        errorMessage: 'Алдаа гарлаа',
        emptyMessage: 'Контент хоосон байна',
        confirmText: 'Шинэчлэх',
        cancelText: 'Болих'
      },
      /* OCR modal тохиргоо */
      ocrModal: {
        title: 'AI OCR',
        description: 'Зураг дээрх текстийг уншиж HTML болгоод editor-д оруулна.',
        processingText: 'Зураг уншиж байна...',
        successMessage: 'Зургаас текст амжилттай задлагдлаа!',
        errorMessage: 'Алдаа гарлаа',
        noImageMessage: 'Зураг олдсонгүй. OCR ашиглахын тулд зураг оруулна уу.',
        confirmText: 'Хөрвүүлэх',
        cancelText: 'Болих'
      },
      /* PDF modal тохиргоо */
      pdfModal: {
        title: 'PDF → HTML',
        description: 'PDF файлыг AI ашиглан HTML болгож editor-д оруулна.',
        placeholder: 'PDF файл сонгоно уу...',
        browseText: 'Сонгох',
        processingText: 'PDF боловсруулж байна...',
        renderingText: 'Хуудас зурж байна...',
        successMessage: 'PDF амжилттай HTML болгогдлоо!',
        errorMessage: 'Алдаа гарлаа',
        confirmText: 'Оруулах',
        cancelText: 'Болих',
        pageText: 'хуудас'
      },
      /* Shine API URL */
      shineUrl: '/dashboard/ai/shine',
      /* OpenAI API key тохируулсан эсэх - false бол AI товчнууд disable */
      hasOpenAI: false,
      /* Notify function - optional */
      notify: null, /* function(type, title, message) */
      ...opts,
    };

    this.registry = {
      bold:        { type: "cmd", cmd: "bold", toggle: true },
      italic:      { type: "cmd", cmd: "italic", toggle: true },
      underline:   { type: "cmd", cmd: "underline", toggle: true },
      strike:      { type: "cmd", cmd: "strikeThrough", toggle: true },
      subscript:   { type: "cmd", cmd: "subscript", toggle: true },
      superscript: { type: "cmd", cmd: "superscript", toggle: true },

      fontSize:    { type: "fn", fn: (val) => this._setFontSize(val) },
      foreColor:   { type: "fn", fn: (val) => this._setForeColor(val) },

      justifyLeft:   { type: "cmd", cmd: "justifyLeft" },
      justifyCenter: { type: "cmd", cmd: "justifyCenter" },
      justifyRight:  { type: "cmd", cmd: "justifyRight" },
      justifyFull:   { type: "cmd", cmd: "justifyFull" },

      ul:        { type: "cmd", cmd: "insertUnorderedList" },
      ol:        { type: "cmd", cmd: "insertOrderedList" },
      indent:    { type: "fn", fn: () => this._indent() },
      outdent:   { type: "fn", fn: () => this._outdent() },

      link:      { type: "fn", fn: () => this._insertLink() },
      insertLink: { type: "fn", fn: () => this._insertLink() },
      unlink:    { type: "cmd", cmd: "unlink" },
      image:     { type: "fn", fn: () => this._insertImage() },
      table:     { type: "fn", fn: () => this._insertTable() },
      hr:        { type: "fn", fn: () => this._insertHR() },
      email:     { type: "fn", fn: () => this._insertEmail() },
      youtube:   { type: "fn", fn: () => this._insertYouTube() },
      facebook:  { type: "fn", fn: () => this._insertFacebook() },

      pasteText: { type: "fn", fn: () => this._pasteText() },
      cut:       { type: "cmd", cmd: "cut" },
      copy:      { type: "cmd", cmd: "copy" },
      paste:     { type: "fn", fn: () => this._paste() },

      undo:      { type: "cmd", cmd: "undo" },
      redo:      { type: "cmd", cmd: "redo" },

      removeFormat: { type: "cmd", cmd: "removeFormat" },

      print:      { type: "fn", fn: () => this._print() },
      source:     { type: "fn", fn: () => this.toggleSource() },
      fullscreen: { type: "fn", fn: () => this.toggleFullscreen() },
      shine:      { type: "fn", fn: () => this._shine() },
      ocr:        { type: "fn", fn: () => this._ocr() },
      pdf:        { type: "fn", fn: () => this._insertPdf() },
    };

    this._bind();
    this._syncToggleStates();

    /* Shine товчийг shineUrl байхгүй бол нуух */
    this._toggleShineButton();

    /* Shine товчны анхны төлвийг шинэчлэх */
    this._updateShineButtonState();

    /** @private */
    this._destroyed = false;
  }

  /**
   * AI товчнуудыг hasOpenAI тохиргооноос хамааран тохируулах
   * - hasOpenAI: false бол ocr, shine, pdf товчнуудыг disable болгоно
   * @private
   */
  _toggleShineButton() {
    const shineBtn = this.toolbar.querySelector('[data-action="shine"]');
    const pdfBtn = this.toolbar.querySelector('[data-action="pdf"]');
    const ocrBtn = this.toolbar.querySelector('[data-action="ocr"]');
    const hasOpenAI = this.opts.hasOpenAI;

    /* shineUrl хоосон бол бүх AI товчнуудыг нуух */
    const url = this.opts.shineUrl;
    const isConfigured = url && url.trim();

    if (!isConfigured) {
      if (shineBtn) shineBtn.style.display = 'none';
      if (pdfBtn) pdfBtn.style.display = 'none';
      if (ocrBtn) ocrBtn.style.display = 'none';
      return;
    }

    /* hasOpenAI false бол: ocr, shine, pdf товчнуудыг disable болгох */
    if (!hasOpenAI) {
      const disableBtn = (btn, title) => {
        if (!btn) return;
        btn.disabled = true;
        btn.style.opacity = '0.4';
        btn.style.cursor = 'not-allowed';
        btn.title = title + ' (OpenAI API key тохируулаагүй)';
      };

      disableBtn(ocrBtn, 'AI OCR');
      disableBtn(shineBtn, 'AI Shine');
      disableBtn(pdfBtn, 'PDF → HTML');
    }
  }

  /**
   * Shine товчийг контентын байдлаас хамааран enable/disable хийх
   * - Контент хоосон эсвэл зөвхөн whitespace бол disable
   * @private
   */
  _updateShineButtonState() {
    /* Товчийг үргэлж идэвхтэй байлгах - контент хоосон эсэхийг _shine функц шалгана */
  }

  /**
   * Editor instance-ийг устгах, event listener-үүдийг цэвэрлэх
   * @returns {void}
   */
  destroy() {
    if (this._destroyed) return;
    this._destroyed = true;

    /* Document level event listeners устгах */
    if (this._boundHandlers.selectionChange) {
      document.removeEventListener('selectionchange', this._boundHandlers.selectionChange);
    }

    /* Toolbar event listeners устгах */
    if (this._boundHandlers.toolbarMousedown) {
      this.toolbar.removeEventListener('mousedown', this._boundHandlers.toolbarMousedown);
    }
    if (this._boundHandlers.toolbarClick) {
      this.toolbar.removeEventListener('click', this._boundHandlers.toolbarClick);
    }

    /* Toolbar keyboard navigation устгах */
    if (this._boundHandlers.toolbarKeydown) {
      this.toolbar.removeEventListener('keydown', this._boundHandlers.toolbarKeydown);
    }

    /* Editor event listeners устгах */
    if (this._boundHandlers.editorInput) {
      this.editor.removeEventListener('input', this._boundHandlers.editorInput);
    }
    if (this._boundHandlers.editorPaste) {
      this.editor.removeEventListener('paste', this._boundHandlers.editorPaste);
    }
    if (this._boundHandlers.editorKeydown) {
      this.editor.removeEventListener('keydown', this._boundHandlers.editorKeydown);
    }
    if (this._boundHandlers.editorToolbarShortcut) {
      this.editor.removeEventListener('keydown', this._boundHandlers.editorToolbarShortcut);
    }

    /* Source event listeners устгах */
    if (this._boundHandlers.sourceInput) {
      this.source.removeEventListener('input', this._boundHandlers.sourceInput);
    }
    if (this._boundHandlers.sourceKeydown) {
      this.source.removeEventListener('keydown', this._boundHandlers.sourceKeydown);
    }

    /* References цэвэрлэх */
    this.root = null;
    this.editor = null;
    this.source = null;
    this.toolbar = null;
    this.opts = null;
    this.registry = null;
    this._boundHandlers = null;
  }

  /* ---------------- core ---------------- */

  /**
   * Toolbar action гүйцэтгэх
   * @param {string} action - Action нэр (bold, italic, image, гэх мэт)
   * @param {*} [value] - Action-д дамжуулах утга (fontSize, foreColor гэх мэт)
   * @returns {void}
   * @example
   * editor.exec('bold');
   * editor.exec('fontSize', '5');
   * editor.exec('foreColor', '#ff0000');
   */
  exec(action, value) {
    const item = this.registry[action];
    if (!item) return;

    /* Source товчны тохиолдолд _ensureVisualMode() дуудагдахгүй, учир нь toggleSource() өөрөө mode-ийг удирдана */
    if (action !== "source" && action !== "fullscreen") {
      this._ensureVisualMode();
      this._focusEditor();
    }

    if (item.type === "cmd") {
      document.execCommand(item.cmd, false, value ?? item.value ?? null);
    } else if (item.type === "fn") {
      item.fn(value);
    }

    this._syncToggleStates();
    this._emitChange();
  }

  /**
   * Fullscreen горим асаах/унтраах
   * @param {boolean} [force] - true: асаах, false: унтраах, undefined: toggle
   * @returns {void}
   * @example
   * editor.toggleFullscreen();      // Toggle
   * editor.toggleFullscreen(true);  // Асаах
   * editor.toggleFullscreen(false); // Унтраах
   */
  toggleFullscreen(force) {
    const next = typeof force === "boolean"
      ? force
      : !this.root.classList.contains("is-fullscreen");

    this.root.classList.toggle("is-fullscreen", next);

    /* Fullscreen товчны toggle state шинэчлэх */
    const fullscreenBtn = this.toolbar.querySelector('[data-action="fullscreen"]');
    if (fullscreenBtn) {
      fullscreenBtn.setAttribute("aria-pressed", next ? "true" : "false");
    }
  }

  /**
   * Source (HTML) горим асаах/унтраах
   * @param {boolean} [force] - true: source горим, false: visual горим, undefined: toggle
   * @returns {void}
   * @example
   * editor.toggleSource();       // Toggle
   * editor.toggleSource(true);   // Source горим руу
   * editor.toggleSource(false);  // Visual горим руу
   */
  toggleSource(force) {
    const next = typeof force === "boolean" ? force : !this.isSource;
    this.isSource = next;

    if (next) {
      /* Editor -> Source: cursor байрлалыг тооцоолох */
      const cursorOffset = this._getEditorCursorOffset();
      const textLength = this.editor.textContent.length;
      const cursorRatio = textLength > 0 ? cursorOffset / textLength : 0;

      /* HTML контентыг авах */
      const html = this.editor.innerHTML;
      const formattedHtml = this._formatHTML(html);

      /* Эхлээд textarea-г харуулах, дараа нь утга оноох (зарим browser-д шаардлагатай) */
      this.root.classList.add("is-source");
      this.source.value = formattedHtml;
      this.source.focus();

      /* Source textarea дээр cursor байрлуулах */
      const sourceLength = this.source.value.length;
      const sourcePos = Math.round(cursorRatio * sourceLength);
      this.source.setSelectionRange(sourcePos, sourcePos);

      /* Cursor байрлал руу scroll хийх */
      this._scrollTextareaToCursor();
    } else {
      /* Source -> Editor: cursor байрлалыг тооцоолох */
      const sourcePos = this.source.selectionStart;
      const sourceLength = this.source.value.length;
      const cursorRatio = sourceLength > 0 ? sourcePos / sourceLength : 0;

      /* Source утгыг editor-т оноох (хоосон бол хоосон string) */
      const sourceValue = this.source.value || '';
      this.editor.innerHTML = sourceValue;
      this.root.classList.remove("is-source");
      this._focusEditor();

      /* Editor дээр cursor байрлуулах */
      const textLength = this.editor.textContent.length;
      const targetOffset = Math.round(cursorRatio * textLength);
      this._setEditorCursorOffset(targetOffset);

      /* Cursor байрлал руу scroll хийх */
      this._scrollEditorToCursor();
    }

    /* Source товчны toggle state шинэчлэх */
    const sourceBtn = this.toolbar.querySelector('[data-action="source"]');
    if (sourceBtn) {
      sourceBtn.setAttribute("aria-pressed", next ? "true" : "false");
    }

    this._emitChange();
  }

  /**
   * Editor дотор cursor-ийн text offset авах
   * @private
   * @returns {number} Cursor-ийн текст offset
   */
  _getEditorCursorOffset() {
    const selection = window.getSelection();
    if (!selection.rangeCount) return 0;

    const range = selection.getRangeAt(0);
    const preCaretRange = range.cloneRange();
    preCaretRange.selectNodeContents(this.editor);
    preCaretRange.setEnd(range.startContainer, range.startOffset);

    /* Текст урт тооцоолох */
    return preCaretRange.toString().length;
  }

  /**
   * Editor дотор cursor-ийг text offset-ээр байрлуулах
   * @private
   * @param {number} targetOffset - Cursor байрлуулах текст offset
   * @returns {void}
   */
  _setEditorCursorOffset(targetOffset) {
    const walker = document.createTreeWalker(
      this.editor,
      NodeFilter.SHOW_TEXT,
      null,
      false
    );

    let currentOffset = 0;
    let node;

    while ((node = walker.nextNode())) {
      const nodeLength = node.textContent.length;

      if (currentOffset + nodeLength >= targetOffset) {
        /* Энэ node дотор cursor байрлуулах */
        const offsetInNode = targetOffset - currentOffset;
        const range = document.createRange();
        range.setStart(node, Math.min(offsetInNode, nodeLength));
        range.collapse(true);

        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);
        return;
      }

      currentOffset += nodeLength;
    }

    /* Хэрэв олдоогүй бол төгсгөлд байрлуулах */
    const range = document.createRange();
    range.selectNodeContents(this.editor);
    range.collapse(false);
    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(range);
  }

  /**
   * Textarea дээр cursor байрлал руу scroll хийх
   * @private
   */
  _scrollTextareaToCursor() {
    const textarea = this.source;
    const cursorPos = textarea.selectionStart;
    const text = textarea.value;

    /* Cursor хүртэлх мөрийн тоог тооцоолох */
    const textBeforeCursor = text.substring(0, cursorPos);
    const lines = textBeforeCursor.split('\n');
    const lineNumber = lines.length;

    /* Нэг мөрийн өндрийг тооцоолох */
    const lineHeight = parseInt(window.getComputedStyle(textarea).lineHeight) || 20;

    /* Scroll байрлал тооцоолох (дунд хэсэгт харуулах) */
    const scrollTop = (lineNumber * lineHeight) - (textarea.clientHeight / 2);

    textarea.scrollTop = Math.max(0, scrollTop);
  }

  /**
   * Editor дээр cursor байрлал руу scroll хийх
   * @private
   */
  _scrollEditorToCursor() {
    const selection = window.getSelection();
    if (!selection.rangeCount) return;

    const range = selection.getRangeAt(0);

    /* Түр element үүсгэж cursor байрлалд оруулах */
    const tempSpan = document.createElement('span');
    tempSpan.textContent = '\u200B'; /* Zero-width space */
    range.insertNode(tempSpan);

    /* Element рүү scroll хийх */
    tempSpan.scrollIntoView({ behavior: 'instant', block: 'center' });

    /* Түр element устгах */
    tempSpan.remove();

    /* Selection сэргээх */
    selection.removeAllRanges();
    selection.addRange(range);
  }

  /**
   * Editor-ийн HTML контент авах
   * @returns {string} HTML контент
   * @example
   * const html = editor.getHTML();
   * console.log(html); // "<p>Hello <strong>World</strong></p>"
   */
  getHTML() {
    return this.isSource ? this.source.value : this.editor.innerHTML;
  }

  /**
   * Editor-ийн HTML контент тохируулах
   * @param {string} [html=''] - HTML контент
   * @returns {void}
   * @example
   * editor.setHTML('<p>Hello World</p>');
   * editor.setHTML(''); // Цэвэрлэх
   */
  setHTML(html) {
    const v = html ?? "";
    this.editor.innerHTML = v;
    this.source.value = v;
    this._emitChange();
  }

  /* ---------------- internals ---------------- */

  /**
   * Textarea-аас editor wrapper бүтэц үүсгэх
   * @private
   * @param {HTMLTextAreaElement} textarea - Эх textarea element
   * @param {Object} opts - Тохиргоо
   * @returns {HTMLElement} Үүсгэсэн wrapper element
   */
  _createWrapper(textarea, opts) {
    /* Placeholder авах */
    const placeholder = opts.placeholder || textarea.placeholder || 'Агуулгаа энд бичнэ үү...';

    /* Анхны утга авах */
    const initialContent = textarea.value || '';

    /* Wrapper үүсгэх */
    const wrapper = document.createElement('div');
    wrapper.className = 'moedit';

    /* ID хуулах (хэрэв байвал) */
    if (textarea.id) {
      wrapper.id = textarea.id + '_wrapper';
    }

    wrapper.innerHTML = `
      <div class="moedit-body">
        <div class="moedit-editor" contenteditable="true" data-placeholder="${this._escapeAttr(placeholder)}">${initialContent}</div>
        <textarea class="moedit-source">${this._escapeHtml(initialContent)}</textarea>
      </div>
    `;

    /* Textarea-г нууж, wrapper-ийн дараа байрлуулах */
    textarea.style.display = 'none';
    textarea.parentNode.insertBefore(wrapper, textarea);

    return wrapper;
  }

  /**
   * HTML attribute escape хийх
   * @private
   * @param {string} text - Escape хийх текст
   * @returns {string} Escaped текст
   */
  _escapeAttr(text) {
    if (!text) return '';
    return text.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  /**
   * Toolbar автоматаар үүсгэх
   * @private
   */
  _createToolbar() {
    const toolbar = document.createElement('div');
    toolbar.className = 'moedit-toolbar';
    toolbar.innerHTML = `
      <div class="moedit-group">
        <button type="button" class="moedit-btn" data-action="bold" title="Bold (Ctrl+B)"><i class="bi bi-type-bold"></i></button>
        <button type="button" class="moedit-btn" data-action="italic" title="Italic (Ctrl+I)"><i class="bi bi-type-italic"></i></button>
        <button type="button" class="moedit-btn" data-action="underline" title="Underline (Ctrl+U)"><i class="bi bi-type-underline"></i></button>
        <button type="button" class="moedit-btn" data-action="strike" title="Strikethrough"><i class="bi bi-type-strikethrough"></i></button>
        <button type="button" class="moedit-btn" data-action="subscript" title="Subscript"><i class="bi bi-type"></i><sub>x</sub></button>
        <button type="button" class="moedit-btn" data-action="superscript" title="Superscript"><i class="bi bi-type"></i><sup>x</sup></button>
      </div>
      <div class="moedit-sep"></div>
      <div class="moedit-group">
        <select class="moedit-select" data-action="heading" title="Heading">
          <option value="p" selected>Paragraph</option>
          <option value="h1">H1 - Гарчиг</option>
          <option value="h2">H2 - Дэд гарчиг</option>
          <option value="h3">H3</option>
          <option value="h4">H4</option>
          <option value="h5">H5</option>
          <option value="h6">H6</option>
          <option value="pre">Preformatted</option>
          <option value="blockquote">Quote</option>
        </select>
        <select class="moedit-select" data-action="fontSize" title="Font Size">
          <option value="3" selected>Default</option>
          <option value="1">1 - Жижиг</option>
          <option value="2">2</option>
          <option value="3">3 - Хэвийн</option>
          <option value="4">4</option>
          <option value="5">5 - Том</option>
          <option value="6">6</option>
          <option value="7">7 - Хамгийн том</option>
        </select>
        <input type="color" class="moedit-color" data-action="foreColor" title="Font Color" value="#000000">
      </div>
      <div class="moedit-sep"></div>
      <div class="moedit-group">
        <button type="button" class="moedit-btn" data-action="justifyLeft" title="Align Left"><i class="bi bi-text-left"></i></button>
        <button type="button" class="moedit-btn" data-action="justifyCenter" title="Align Center"><i class="bi bi-text-center"></i></button>
        <button type="button" class="moedit-btn" data-action="justifyRight" title="Align Right"><i class="bi bi-text-right"></i></button>
        <button type="button" class="moedit-btn" data-action="justifyFull" title="Justify"><i class="bi bi-justify"></i></button>
        <button type="button" class="moedit-btn" data-action="outdent" title="Outdent"><i class="bi bi-text-indent-right"></i></button>
        <button type="button" class="moedit-btn" data-action="indent" title="Indent"><i class="bi bi-text-indent-left"></i></button>
      </div>
      <div class="moedit-sep"></div>
      <div class="moedit-group">
        <button type="button" class="moedit-btn" data-action="removeFormat" title="Remove Format"><i class="bi bi-eraser"></i></button>
      </div>
      <div class="moedit-sep"></div>
      <div class="moedit-group">
        <button type="button" class="moedit-btn" data-action="ul" title="Unordered List"><i class="bi bi-list-ul"></i></button>
        <button type="button" class="moedit-btn" data-action="ol" title="Ordered List"><i class="bi bi-list-ol"></i></button>
      </div>
      <div class="moedit-sep"></div>
      <div class="moedit-group">
        <button type="button" class="moedit-btn" data-action="pasteText" title="Paste Text"><i class="bi bi-clipboard-plus"></i></button>
        <button type="button" class="moedit-btn" data-action="image" title="Insert Image"><i class="bi bi-image"></i></button>
        <button type="button" class="moedit-btn" data-action="table" title="Insert Table"><i class="bi bi-table"></i></button>
        <button type="button" class="moedit-btn" data-action="insertLink" title="Insert Link / Email"><i class="bi bi-link-45deg"></i></button>
        <button type="button" class="moedit-btn" data-action="hr" title="Insert Horizontal Rule"><i class="bi bi-dash-lg"></i></button>
        <button type="button" class="moedit-btn" data-action="youtube" title="Insert YouTube Video"><i class="bi bi-youtube"></i></button>
        <button type="button" class="moedit-btn" data-action="facebook" title="Insert Facebook Video"><i class="bi bi-facebook"></i></button>
        <button type="button" class="moedit-btn text-info" data-action="ocr" title="AI OCR - Зургийг HTML болгох"><i class="bi bi-file-text"></i></button>
        <button type="button" class="moedit-btn text-danger" data-action="pdf" title="PDF → HTML"><i class="bi bi-file-earmark-pdf"></i></button>
        <button type="button" class="moedit-btn text-warning" data-action="shine" title="AI Shine - Bootstrap 5 гоёжуулах"><i class="bi bi-stars"></i></button>
      </div>
      <div class="moedit-sep"></div>
      <div class="moedit-group">
        <button type="button" class="moedit-btn" data-action="undo" title="Undo (Ctrl+Z)"><i class="bi bi-arrow-counterclockwise"></i></button>
        <button type="button" class="moedit-btn" data-action="redo" title="Redo (Ctrl+Y)"><i class="bi bi-arrow-clockwise"></i></button>
      </div>
      <div class="moedit-sep"></div>
      <div class="moedit-group">
        <button type="button" class="moedit-btn" data-action="print" title="Print"><i class="bi bi-printer"></i></button>
        <button type="button" class="moedit-btn" data-action="source" title="Source Code"><i class="bi bi-code-slash"></i></button>
        <button type="button" class="moedit-btn" data-action="fullscreen" title="Fullscreen"><i class="bi bi-arrows-fullscreen"></i></button>
      </div>
    `;

    /* Toolbar-ийг root element-ийн эхэнд нэмэх */
    this.root.insertBefore(toolbar, this.root.firstChild);
  }

  /**
   * Event listener-үүдийг холбох
   * @private
   */
  _bind() {
    /* Toolbar mousedown */
    this._boundHandlers.toolbarMousedown = (e) => {
      /* Зөвхөн button дээр preventDefault хийх, select дээр хийхгүй (dropdown нээгдэхийн тулд) */
      if (e.target.closest("button") && !e.target.closest("select")) e.preventDefault();
    };
    this.toolbar.addEventListener("mousedown", this._boundHandlers.toolbarMousedown);

    /* Toolbar click */
    this._boundHandlers.toolbarClick = (e) => {
      const btn = e.target.closest("button[data-action]");
      if (!btn) return;
      this.exec(btn.dataset.action);
    };
    this.toolbar.addEventListener("click", this._boundHandlers.toolbarClick);

    /* Toolbar keyboard navigation */
    this._setupToolbarKeyboardNav();

    const heading = this.toolbar.querySelector('[data-action="heading"]');
    if (heading) {
      heading.addEventListener("change", e => {
        document.execCommand("formatBlock", false, e.target.value);
        this._emitChange();
        /* Select-ийн утгыг шинэчлэх (cursor байрлалаас хамааран) */
        this._syncHeadingState();
      });
    }

    const fontSize = this.toolbar.querySelector('[data-action="fontSize"]');
    if (fontSize) {
      let savedRange = null;

      /* Select дээр focus очихоос өмнө selection хадгалах */
      fontSize.addEventListener("mousedown", () => {
        const selection = window.getSelection();
        if (selection.rangeCount > 0) {
          savedRange = selection.getRangeAt(0).cloneRange();
        }
      });

      fontSize.addEventListener("change", e => {
        const selectedValue = e.target.value;

        /* Selection сэргээх */
        if (savedRange) {
          this._focusEditor();
          const selection = window.getSelection();
          selection.removeAllRanges();
          selection.addRange(savedRange);
        }

        /* Default (3) сонгосон бол font size устгах, бусад format хэвээр үлдээх */
        if (selectedValue === "3") {
          this._removeFontSize();
        } else {
          this.exec("fontSize", selectedValue);
        }

        savedRange = null;
        /* Select-ийн утгыг шинэчлэх (cursor байрлалаас хамааран) */
        this._syncFontSizeState();
      });
    }

    const foreColor = this.toolbar.querySelector('[data-action="foreColor"]');
    if (foreColor) {
      let savedColorRange = null;

      /* Selection хадгалах helper */
      const saveSelection = () => {
        const selection = window.getSelection();
        if (selection.rangeCount > 0 && this.editor.contains(selection.anchorNode)) {
          savedColorRange = selection.getRangeAt(0).cloneRange();
        }
      };

      /* Color picker дээр focus очихоос өмнө selection хадгалах */
      foreColor.addEventListener("mousedown", saveSelection);
      foreColor.addEventListener("click", (e) => {
        if (!savedColorRange) saveSelection();
      });
      foreColor.addEventListener("focus", () => {
        if (!savedColorRange) saveSelection();
      });

      /* Color apply хийх функц */
      const applyColor = (color, isFinal = false) => {
        if (!color) return;

        /* Selection сэргээх */
        this._focusEditor();
        if (savedColorRange) {
          const selection = window.getSelection();
          selection.removeAllRanges();
          selection.addRange(savedColorRange);
        }

        /* Хар өнгө (#000000) сонгосон бол color tag устгах */
        const isBlack = color.toLowerCase() === '#000000' || color.toLowerCase() === 'rgb(0, 0, 0)';
        if (isBlack && isFinal) {
          this._removeFontColor();
        } else if (!isBlack) {
          document.execCommand("foreColor", false, color);
          this._emitChange();
        }
      };

      /* input event - color picker дээр сонгох үед (real-time preview) */
      foreColor.addEventListener("input", e => {
        applyColor(e.target.value, false);
      });

      /* change event - color picker хаах үед (final) */
      foreColor.addEventListener("change", e => {
        applyColor(e.target.value, true);
        savedColorRange = null;
        /* Picker-ийн утгыг шинэчлэх (cursor байрлалаас хамааран) */
        this._syncForeColorState();
      });
    }

    /* Editor input */
    this._boundHandlers.editorInput = () => !this.isSource && this._emitChange();
    this.editor.addEventListener("input", this._boundHandlers.editorInput);

    /* Source input */
    this._boundHandlers.sourceInput = () => this.isSource && this._emitChange();
    this.source.addEventListener("input", this._boundHandlers.sourceInput);

    /* Paste event listener - delegate to UI module if available */
    this._boundHandlers.editorPaste = async (e) => {
      if (typeof this._handlePaste === 'function') {
        this._handlePaste(e);
      }
    };
    this.editor.addEventListener("paste", this._boundHandlers.editorPaste);

    /* Keyboard shortcuts - Editor */
    this._boundHandlers.editorKeydown = (e) => {
      /* ESC товч - fullscreen mode-оос гарах */
      if (e.key === 'Escape' && this.root.classList.contains('is-fullscreen')) {
        e.preventDefault();
        this.toggleFullscreen(false);
        return;
      }

      if (e.ctrlKey || e.metaKey) {
        switch(e.key.toLowerCase()) {
          case 'b':
            e.preventDefault();
            this.exec('bold');
            break;
          case 'i':
            e.preventDefault();
            this.exec('italic');
            break;
          case 'u':
            e.preventDefault();
            this.exec('underline');
            break;
          case 'z':
            if (!e.shiftKey) {
              e.preventDefault();
              this.exec('undo');
            } else {
              e.preventDefault();
              this.exec('redo');
            }
            break;
          case 'y':
            e.preventDefault();
            this.exec('redo');
            break;
        }
      }
    };
    this.editor.addEventListener("keydown", this._boundHandlers.editorKeydown);

    /* Keyboard shortcuts - Source */
    this._boundHandlers.sourceKeydown = (e) => {
      /* ESC товч - fullscreen mode-оос гарах */
      if (e.key === 'Escape' && this.root.classList.contains('is-fullscreen')) {
        e.preventDefault();
        this.toggleFullscreen(false);
        return;
      }

      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
        e.preventDefault();
        this.toggleSource(false);
      }
    };
    this.source.addEventListener("keydown", this._boundHandlers.sourceKeydown);

    /* Selection change - debounced (100ms) */
    this._boundHandlers.selectionChange = moedit._debounce(() => {
      if (this.root && this.root.isConnected && !this.isSource) {
        this._syncToggleStates();
      }
    }, 100);
    document.addEventListener("selectionchange", this._boundHandlers.selectionChange);
  }

  _syncToggleStates() {
    /* Selection editor дотор байгаа эсэхийг шалгах */
    const selection = window.getSelection();
    const isInsideEditor = selection.rangeCount > 0 &&
      selection.anchorNode &&
      this.editor.contains(selection.anchorNode);

    for (const [key, cfg] of Object.entries(this.registry)) {
      if (!cfg.toggle) continue;
      const btn = this.toolbar.querySelector(`[data-action="${key}"]`);
      if (!btn) continue;

      let on = false;
      /* Зөвхөн editor дотор selection байвал queryCommandState ашиглах */
      if (isInsideEditor) {
        try { on = document.queryCommandState(cfg.cmd); } catch {}
      }
      btn.setAttribute("aria-pressed", on ? "true" : "false");
    }

    /* Heading select-ийн утгыг cursor байрлалаас хамааран шинэчлэх */
    this._syncHeadingState();

    /* FontSize select-ийн утгыг cursor байрлалаас хамааран шинэчлэх */
    this._syncFontSizeState();

    /* ForeColor picker-ийн утгыг cursor байрлалаас хамааран шинэчлэх */
    this._syncForeColorState();

    /* Justify buttons-ийн төлвийг cursor байрлалаас хамааран шинэчлэх */
    this._syncJustifyState();

    /* List buttons-ийн төлвийг cursor байрлалаас хамааран шинэчлэх */
    this._syncListState();
  }

  /**
   * FontSize select-ийн утгыг cursor байрлаж буй element-ээс хамааран шинэчлэх
   * @private
   */
  _syncFontSizeState() {
    const fontSizeSelect = this.toolbar.querySelector('[data-action="fontSize"]');
    if (!fontSizeSelect) return;

    const selection = window.getSelection();
    if (!selection.rangeCount) return;

    /* Cursor байрлаж буй node-ийг авах */
    let node = selection.anchorNode;
    if (!node) return;

    /* Editor дотор байгаа эсэхийг шалгах */
    if (!this.editor.contains(node)) return;

    /* Element node руу шилжих (text node бол parent авах) */
    if (node.nodeType === Node.TEXT_NODE) {
      node = node.parentNode;
    }

    /* Font size-тай element олох */
    let fontSize = null;

    while (node && node !== this.editor) {
      if (node.nodeType === Node.ELEMENT_NODE) {
        /* <font size="X"> tag шалгах */
        if (node.nodeName === 'FONT' && node.hasAttribute('size')) {
          fontSize = node.getAttribute('size');
          break;
        }
        /* style="font-size:..." шалгах */
        if (node.style && node.style.fontSize) {
          /* CSS font-size-г execCommand size руу хөрвүүлэх */
          fontSize = this._cssFontSizeToExecCommand(node.style.fontSize);
          if (fontSize) break;
        }
      }
      node = node.parentNode;
    }

    /* FontSize select-ийн утгыг шинэчлэх */
    if (fontSize) {
      const option = fontSizeSelect.querySelector(`option[value="${fontSize}"]`);
      if (option) {
        fontSizeSelect.value = fontSize;
      } else {
        fontSizeSelect.value = '3'; /* Default */
      }
    } else {
      fontSizeSelect.value = '3'; /* Default */
    }
  }

  /**
   * CSS font-size утгыг execCommand fontSize утга руу хөрвүүлэх
   * @private
   * @param {string} cssSize - CSS font-size утга (px, pt, em, etc.)
   * @returns {string|null} execCommand fontSize утга (1-7)
   */
  _cssFontSizeToExecCommand(cssSize) {
    if (!cssSize) return null;

    /* px утгыг тоо болгох */
    let pxValue = parseFloat(cssSize);

    /* em, rem утгыг px болгох (16px = 1em гэж тооцох) */
    if (cssSize.includes('em') || cssSize.includes('rem')) {
      pxValue = pxValue * 16;
    }
    /* pt утгыг px болгох (1pt = 1.333px) */
    else if (cssSize.includes('pt')) {
      pxValue = pxValue * 1.333;
    }

    /* execCommand fontSize mapping (browser-ийн standard) */
    /* 1=10px, 2=13px, 3=16px, 4=18px, 5=24px, 6=32px, 7=48px */
    if (pxValue <= 10) return '1';
    if (pxValue <= 13) return '2';
    if (pxValue <= 16) return '3';
    if (pxValue <= 18) return '4';
    if (pxValue <= 24) return '5';
    if (pxValue <= 32) return '6';
    return '7';
  }

  /**
   * ForeColor picker-ийн утгыг cursor байрлаж буй element-ээс хамааран шинэчлэх
   * @private
   */
  _syncForeColorState() {
    const foreColorPicker = this.toolbar.querySelector('[data-action="foreColor"]');
    if (!foreColorPicker) return;

    const selection = window.getSelection();
    if (!selection.rangeCount) return;

    /* Cursor байрлаж буй node-ийг авах */
    let node = selection.anchorNode;
    if (!node) return;

    /* Editor дотор байгаа эсэхийг шалгах */
    if (!this.editor.contains(node)) return;

    /* Element node руу шилжих (text node бол parent авах) */
    if (node.nodeType === Node.TEXT_NODE) {
      node = node.parentNode;
    }

    /* Color-тай element олох */
    let color = null;

    while (node && node !== this.editor) {
      if (node.nodeType === Node.ELEMENT_NODE) {
        /* <font color="X"> tag шалгах */
        if (node.nodeName === 'FONT' && node.hasAttribute('color')) {
          color = node.getAttribute('color');
          break;
        }
        /* style="color:..." шалгах */
        if (node.style && node.style.color) {
          color = node.style.color;
          break;
        }
      }
      node = node.parentNode;
    }

    /* ForeColor picker-ийн утгыг шинэчлэх */
    if (color) {
      /* Өнгийг hex format руу хөрвүүлэх */
      const hexColor = this._colorToHex(color);
      if (hexColor) {
        foreColorPicker.value = hexColor;
      }
    } else {
      /* Default хар өнгө */
      foreColorPicker.value = '#000000';
    }
  }

  /**
   * Өнгийг hex format руу хөрвүүлэх
   * @private
   * @param {string} color - Өнгө (hex, rgb, named color)
   * @returns {string|null} Hex өнгө (#RRGGBB)
   */
  _colorToHex(color) {
    if (!color) return null;

    /* Аль хэдийн hex format байвал шууд буцаах */
    if (color.startsWith('#')) {
      /* #RGB -> #RRGGBB */
      if (color.length === 4) {
        return '#' + color[1] + color[1] + color[2] + color[2] + color[3] + color[3];
      }
      return color.toUpperCase().substring(0, 7);
    }

    /* rgb(r, g, b) эсвэл rgba(r, g, b, a) format */
    const rgbMatch = color.match(/rgba?\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/i);
    if (rgbMatch) {
      const r = parseInt(rgbMatch[1]).toString(16).padStart(2, '0');
      const g = parseInt(rgbMatch[2]).toString(16).padStart(2, '0');
      const b = parseInt(rgbMatch[3]).toString(16).padStart(2, '0');
      return '#' + r + g + b;
    }

    /* Named colors - түгээмэл өнгөнүүд */
    const namedColors = {
      'black': '#000000', 'white': '#FFFFFF', 'red': '#FF0000',
      'green': '#008000', 'blue': '#0000FF', 'yellow': '#FFFF00',
      'cyan': '#00FFFF', 'magenta': '#FF00FF', 'gray': '#808080',
      'grey': '#808080', 'silver': '#C0C0C0', 'maroon': '#800000',
      'olive': '#808000', 'lime': '#00FF00', 'aqua': '#00FFFF',
      'teal': '#008080', 'navy': '#000080', 'fuchsia': '#FF00FF',
      'purple': '#800080', 'orange': '#FFA500', 'pink': '#FFC0CB'
    };

    const lowerColor = color.toLowerCase();
    if (namedColors[lowerColor]) {
      return namedColors[lowerColor];
    }

    return null;
  }

  /**
   * Justify buttons-ийн төлвийг cursor байрлаж буй element-ээс хамааран шинэчлэх
   * @private
   */
  _syncJustifyState() {
    const justifyActions = ['justifyLeft', 'justifyCenter', 'justifyRight', 'justifyFull'];
    const justifyBtns = {};

    /* Бүх justify товчнуудыг олох */
    for (const action of justifyActions) {
      const btn = this.toolbar.querySelector(`[data-action="${action}"]`);
      if (btn) {
        justifyBtns[action] = btn;
        /* Эхлээд бүгдийг false болгох */
        btn.setAttribute('aria-pressed', 'false');
      }
    }

    /* Хэрэв нэг ч товч байхгүй бол буцах */
    if (Object.keys(justifyBtns).length === 0) return;

    const selection = window.getSelection();
    if (!selection.rangeCount) return;

    /* Cursor байрлаж буй node-ийг авах */
    let node = selection.anchorNode;
    if (!node) return;

    /* Editor дотор байгаа эсэхийг шалгах */
    if (!this.editor.contains(node)) return;

    /* Element node руу шилжих (text node бол parent авах) */
    if (node.nodeType === Node.TEXT_NODE) {
      node = node.parentNode;
    }

    /* Block level element олох */
    const blockTags = ['P', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'DIV', 'BLOCKQUOTE', 'PRE', 'LI', 'TD', 'TH'];
    let blockElement = null;

    while (node && node !== this.editor) {
      if (node.nodeType === Node.ELEMENT_NODE && blockTags.includes(node.tagName)) {
        blockElement = node;
        break;
      }
      node = node.parentNode;
    }

    /* Text-align утгыг олох */
    let textAlign = 'left'; /* Default */

    if (blockElement) {
      /* Inline style шалгах */
      if (blockElement.style && blockElement.style.textAlign) {
        textAlign = blockElement.style.textAlign;
      }
      /* Computed style шалгах */
      else {
        const computedStyle = window.getComputedStyle(blockElement);
        textAlign = computedStyle.textAlign || 'left';
      }
    }

    /* Text-align утгыг normalize хийх */
    /* "start" -> "left", "end" -> "right", "justify" -> "full" */
    const alignMap = {
      'start': 'left',
      'end': 'right',
      'justify': 'full',
      'left': 'left',
      'center': 'center',
      'right': 'right'
    };

    const normalizedAlign = alignMap[textAlign] || 'left';

    /* Тохирох товчийг идэвхжүүлэх */
    const actionMap = {
      'left': 'justifyLeft',
      'center': 'justifyCenter',
      'right': 'justifyRight',
      'full': 'justifyFull'
    };

    const activeAction = actionMap[normalizedAlign];
    if (activeAction && justifyBtns[activeAction]) {
      justifyBtns[activeAction].setAttribute('aria-pressed', 'true');
    }
  }

  /**
   * List buttons-ийн (ul, ol) төлвийг cursor байрлаж буй element-ээс хамааран шинэчлэх
   * @private
   */
  _syncListState() {
    const ulBtn = this.toolbar.querySelector('[data-action="ul"]');
    const olBtn = this.toolbar.querySelector('[data-action="ol"]');

    /* Хэрэв нэг ч товч байхгүй бол буцах */
    if (!ulBtn && !olBtn) return;

    /* Эхлээд бүгдийг false болгох */
    if (ulBtn) ulBtn.setAttribute('aria-pressed', 'false');
    if (olBtn) olBtn.setAttribute('aria-pressed', 'false');

    const selection = window.getSelection();
    if (!selection.rangeCount) return;

    /* Cursor байрлаж буй node-ийг авах */
    let node = selection.anchorNode;
    if (!node) return;

    /* Editor дотор байгаа эсэхийг шалгах */
    if (!this.editor.contains(node)) return;

    /* Element node руу шилжих (text node бол parent авах) */
    if (node.nodeType === Node.TEXT_NODE) {
      node = node.parentNode;
    }

    /* UL эсвэл OL element олох */
    let listType = null;

    while (node && node !== this.editor) {
      if (node.nodeType === Node.ELEMENT_NODE) {
        if (node.tagName === 'UL') {
          listType = 'ul';
          break;
        }
        if (node.tagName === 'OL') {
          listType = 'ol';
          break;
        }
      }
      node = node.parentNode;
    }

    /* Тохирох товчийг идэвхжүүлэх */
    if (listType === 'ul' && ulBtn) {
      ulBtn.setAttribute('aria-pressed', 'true');
    } else if (listType === 'ol' && olBtn) {
      olBtn.setAttribute('aria-pressed', 'true');
    }
  }

  /**
   * Догол мөр нэмэх (margin-left ашиглан)
   * @private
   */
  _indent() {
    const blockElement = this._getBlockElement();
    if (!blockElement) return;

    /* Одоогийн margin-left авах */
    const currentMargin = parseInt(blockElement.style.marginLeft) || 0;
    const step = 40; /* 40px нэгж */

    /* Margin нэмэх */
    blockElement.style.marginLeft = (currentMargin + step) + 'px';
    this._emitChange();
  }

  /**
   * Догол мөр хасах (margin-left ашиглан)
   * @private
   */
  _outdent() {
    const blockElement = this._getBlockElement();
    if (!blockElement) return;

    /* Одоогийн margin-left авах */
    const currentMargin = parseInt(blockElement.style.marginLeft) || 0;
    const step = 40; /* 40px нэгж */

    /* Margin хасах (0-ээс бага болохгүй) */
    const newMargin = Math.max(0, currentMargin - step);
    if (newMargin > 0) {
      blockElement.style.marginLeft = newMargin + 'px';
    } else {
      blockElement.style.marginLeft = '';
    }
    this._emitChange();
  }

  /**
   * Cursor байрлаж буй block element авах
   * @private
   * @returns {HTMLElement|null}
   */
  _getBlockElement() {
    const selection = window.getSelection();
    if (!selection.rangeCount) return null;

    let node = selection.anchorNode;
    if (!node) return null;

    if (!this.editor.contains(node)) return null;

    if (node.nodeType === Node.TEXT_NODE) {
      node = node.parentNode;
    }

    const blockTags = ['P', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'DIV', 'BLOCKQUOTE', 'PRE', 'LI'];

    while (node && node !== this.editor) {
      if (node.nodeType === Node.ELEMENT_NODE && blockTags.includes(node.tagName)) {
        return node;
      }
      node = node.parentNode;
    }

    return null;
  }

  /**
   * Heading select-ийн утгыг cursor байрлаж буй block element-ээс хамааран шинэчлэх
   * @private
   */
  _syncHeadingState() {
    const headingSelect = this.toolbar.querySelector('[data-action="heading"]');
    if (!headingSelect) return;

    const selection = window.getSelection();
    if (!selection.rangeCount) return;

    /* Cursor байрлаж буй node-ийг авах */
    let node = selection.anchorNode;
    if (!node) return;

    /* Editor дотор байгаа эсэхийг шалгах */
    if (!this.editor.contains(node)) return;

    /* Element node руу шилжих (text node бол parent авах) */
    if (node.nodeType === Node.TEXT_NODE) {
      node = node.parentNode;
    }

    /* Block level parent element олох */
    const blockTags = ['P', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'DIV', 'BLOCKQUOTE', 'PRE'];
    let blockElement = null;

    while (node && node !== this.editor) {
      if (node.nodeType === Node.ELEMENT_NODE && blockTags.includes(node.tagName)) {
        blockElement = node;
        break;
      }
      node = node.parentNode;
    }

    /* Heading select-ийн утгыг шинэчлэх */
    if (blockElement) {
      const tagName = blockElement.tagName.toLowerCase();
      /* Select-д тухайн option байгаа эсэхийг шалгах */
      const option = headingSelect.querySelector(`option[value="${tagName}"]`);
      if (option) {
        headingSelect.value = tagName;
      } else {
        /* Тохирох option байхгүй бол p-д буцаах */
        headingSelect.value = 'p';
      }
    } else {
      /* Block element олдоогүй бол p */
      headingSelect.value = 'p';
    }
  }

  /* UI functions are in moedit.ui.js */

  _ensureVisualMode() {
    if (this.isSource) this.toggleSource(false);
  }

  _focusEditor() {
    this.editor.focus();
  }

  /**
   * Toolbar дээр keyboard navigation тохируулах
   * @private
   */
  _setupToolbarKeyboardNav() {
    /* Toolbar-д ARIA role нэмэх */
    this.toolbar.setAttribute('role', 'toolbar');
    this.toolbar.setAttribute('aria-label', 'Текст форматлах хэрэгслүүд');

    /* Бүх focusable элементүүдийг авах */
    const getFocusableElements = () => {
      return Array.from(this.toolbar.querySelectorAll(
        'button:not([disabled]), select:not([disabled]), input:not([disabled])'
      ));
    };

    /* Эхний элементээс бусдыг tabindex=-1 болгох (roving tabindex pattern) */
    const initTabIndex = () => {
      const elements = getFocusableElements();
      elements.forEach((el, index) => {
        el.setAttribute('tabindex', index === 0 ? '0' : '-1');
      });
    };

    /* Focus-ийг элемент рүү шилжүүлэх */
    const focusElement = (el) => {
      const elements = getFocusableElements();
      elements.forEach(e => e.setAttribute('tabindex', '-1'));
      el.setAttribute('tabindex', '0');
      el.focus();
    };

    /* Toolbar keydown handler */
    this._boundHandlers.toolbarKeydown = (e) => {
      const elements = getFocusableElements();
      const currentIndex = elements.indexOf(document.activeElement);

      if (currentIndex === -1) return;

      let nextIndex = currentIndex;
      let handled = false;

      switch (e.key) {
        case 'ArrowRight':
        case 'ArrowDown':
          /* Дараагийн элемент рүү */
          nextIndex = (currentIndex + 1) % elements.length;
          handled = true;
          break;

        case 'ArrowLeft':
        case 'ArrowUp':
          /* Өмнөх элемент рүү */
          nextIndex = (currentIndex - 1 + elements.length) % elements.length;
          handled = true;
          break;

        case 'Home':
          /* Эхний элемент рүү */
          nextIndex = 0;
          handled = true;
          break;

        case 'End':
          /* Сүүлийн элемент рүү */
          nextIndex = elements.length - 1;
          handled = true;
          break;

        case 'Escape':
          /* Editor рүү буцах */
          this._focusEditor();
          handled = true;
          break;

        case 'Enter':
        case ' ':
          /* Button дээр байвал идэвхжүүлэх (select-ийн хувьд default behavior) */
          if (document.activeElement.tagName === 'BUTTON') {
            e.preventDefault();
            document.activeElement.click();
          }
          break;
      }

      if (handled) {
        e.preventDefault();
        if (nextIndex !== currentIndex) {
          focusElement(elements[nextIndex]);
        }
      }
    };

    this.toolbar.addEventListener('keydown', this._boundHandlers.toolbarKeydown);

    /* Editor дээр Alt+F10 дарвал toolbar руу очих */
    this._boundHandlers.editorToolbarShortcut = (e) => {
      if (e.altKey && e.key === 'F10') {
        e.preventDefault();
        const elements = getFocusableElements();
        if (elements.length > 0) {
          focusElement(elements[0]);
        }
      }
    };
    this.editor.addEventListener('keydown', this._boundHandlers.editorToolbarShortcut);

    /* Табindex эхлүүлэх */
    initTabIndex();

    /* Group-үүдэд role нэмэх */
    this.toolbar.querySelectorAll('.moedit-group').forEach(group => {
      group.setAttribute('role', 'group');
    });

    /* Button-үүдэд tooltip нэмэх */
    const buttonLabels = {
      'bold': 'Тод (Ctrl+B)',
      'italic': 'Налуу (Ctrl+I)',
      'underline': 'Доогуур зураас (Ctrl+U)',
      'strike': 'Дундуур зураас',
      'subscript': 'Доод индекс',
      'superscript': 'Дээд индекс',
      'justifyLeft': 'Зүүн тийш',
      'justifyCenter': 'Төвд',
      'justifyRight': 'Баруун тийш',
      'justifyFull': 'Тэгшлэх',
      'removeFormat': 'Формат арилгах',
      'ul': 'Жагсаалт',
      'ol': 'Дугаарласан жагсаалт',
      'indent': 'Догол нэмэх',
      'outdent': 'Догол хасах',
      'pasteText': 'Текст буулгах',
      'image': 'Зураг оруулах',
      'table': 'Хүснэгт оруулах',
      'hr': 'Хэвтээ зураас',
      'youtube': 'YouTube видео',
      'facebook': 'Facebook видео',
      'insertLink': 'Холбоос оруулах',
      'link': 'Холбоос оруулах',
      'undo': 'Буцаах (Ctrl+Z)',
      'redo': 'Дахих (Ctrl+Y)',
      'print': 'Хэвлэх',
      'source': 'HTML код харах',
      'fullscreen': 'Бүтэн дэлгэц (ESC гарах)',
      'vanilla': 'Clean HTML - Цэвэр HTML болгох (offline)'
    };

    this.toolbar.querySelectorAll('button[data-action]').forEach(btn => {
      const action = btn.dataset.action;
      if (buttonLabels[action]) {
        btn.setAttribute('aria-label', buttonLabels[action]);
        btn.setAttribute('title', buttonLabels[action]);
      }
    });
  }

  _emitChange() {
    /* Shine товчний төлвийг шинэчлэх */
    this._updateShineButtonState();

    const html = this.getHTML();

    /* Target textarea-г sync хийх */
    if (this._targetTextarea) {
      this._targetTextarea.value = html;
    }

    if (typeof this.opts.onChange === "function") {
      this.opts.onChange(html);
    }
  }

  /**
   * HTML entity escape хийх (XSS-ээс хамгаалах)
   * @private
   * @param {string} text - Escape хийх текст
   * @returns {string} Escaped текст
   */
  _escapeHtml(text) {
    if (!text) return '';
    const map = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, char => map[char]);
  }

  _formatHTML(html) {
    if (!html || !html.trim()) return html || '';

    /* HTML-ийг indented format-тай болгох */
    let formatted = '';
    let indent = 0;
    const indentSize = 2;
    const indentChar = ' ';

    /* Self-closing tags */
    const selfClosingTags = /^(area|base|br|col|embed|hr|img|input|link|meta|param|source|track|wbr)$/i;

    /* HTML-ийг tag болон text-ээр хуваах */
    const regex = /(<(?:!--[\s\S]*?--|!\[CDATA\[[\s\S]*?\]\]|!DOCTYPE[^>]*|(?:[^>]+)>)|[^<]+)/g;
    const tokens = html.match(regex) || [];

    for (let i = 0; i < tokens.length; i++) {
      let token = tokens[i].trim();
      if (!token) continue;

      if (token.startsWith('<!--')) {
        /* HTML comment */
        formatted += indentChar.repeat(indent) + token + '\n';
      } else if (token.startsWith('</')) {
        /* Closing tag */
        indent = Math.max(0, indent - indentSize);
        formatted += indentChar.repeat(indent) + token + '\n';
      } else if (token.startsWith('<')) {
        /* Opening tag */
        const tagMatch = token.match(/^<(\w+)/i);
        const tagName = tagMatch ? tagMatch[1].toLowerCase() : '';
        const isSelfClosing = /\/>$/.test(token) || selfClosingTags.test(tagName);

        formatted += indentChar.repeat(indent) + token + '\n';

        if (!isSelfClosing && tagName && !['script', 'style'].includes(tagName)) {
          indent += indentSize;
        }
      } else {
        /* Text content */
        const text = token.replace(/\s+/g, ' ').trim();
        if (text) {
          formatted += indentChar.repeat(indent) + text + '\n';
        }
      }
    }

    return formatted.trim();
  }
}

/* ============================================
   Global helpers
   ============================================ */

/**
 * moedit class-ийг global scope-д нэмэх
 * @global
 * @type {moedit}
 */
window.moedit = moedit;

/**
 * Бүх moedit element-үүдийг эхлүүлэх
 * @global
 * @param {string} [selector='.moedit'] - CSS selector
 * @returns {moedit[]} moedit instance-үүдийн array
 * @example
 * // Бүх .moedit элементүүдийг эхлүүлэх
 * const editors = moeditInitAll();
 *
 * @example
 * // Custom selector
 * const editors = moeditInitAll('.my-editor');
 */
window.moeditInitAll = (selector = ".moedit") =>
  Array.from(document.querySelectorAll(selector))
    .map(el => new moedit(el));
