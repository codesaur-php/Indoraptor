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

    this.root = root;

    /** @private */
    this._boundHandlers = {};
    this.editor = root.querySelector(".moedit-editor");
    this.source = root.querySelector(".moedit-source");
    this.toolbar = root.querySelector(".moedit-toolbar");

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
        title: 'Текст буулгах',
        description: 'Доорх талбарт текстээ буулгана уу (Ctrl+V). Word, Excel-ээс хуулсан текст автоматаар цэвэрлэгдэнэ.',
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
      /* Shine modal тохиргоо */
      shineModal: {
        title: 'AI Shine',
        description: 'HTML контентыг Bootstrap 5 компонентуудаар гоёжуулна. Зургийг хөндөхгүй.',
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
        description: 'Зураг дээрх текстийг уншиж HTML болгоно. Зургийг устгаад оронд нь текст контент орно.',
        processingText: 'Зураг уншиж байна...',
        successMessage: 'Зургаас текст амжилттай задлагдлаа!',
        errorMessage: 'Алдаа гарлаа',
        noImageMessage: 'Зураг олдсонгүй. OCR ашиглахын тулд зураг оруулна уу.',
        confirmText: 'Хөрвүүлэх',
        cancelText: 'Болих'
      },
      /* Shine API URL */
      shineUrl: '/dashboard/ai/shine',
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
      indent:    { type: "cmd", cmd: "indent" },
      outdent:   { type: "cmd", cmd: "outdent" },

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

      print:     { type: "fn", fn: () => this._print() },
      source:     { type: "fn", fn: () => this.toggleSource() },
      fullscreen: { type: "fn", fn: () => this.toggleFullscreen() },
      shine:      { type: "fn", fn: () => this._shine() },
      ocr:        { type: "fn", fn: () => this._ocr() },
    };

    this._bind();
    this._syncToggleStates();

    /* Shine товчийг shineUrl байхгүй бол нуух */
    this._toggleShineButton();

    /* OCR товчийг shineUrl байхгүй бол нуух */
    this._toggleOcrButton();

    /* Shine болон OCR товчны анхны төлвийг шинэчлэх */
    this._updateShineButtonState();
    this._updateOcrButtonState();

    /** @private */
    this._destroyed = false;
  }

  /**
   * Shine товчийг shineUrl-ээс хамааран харуулах/нуух
   * @private
   */
  _toggleShineButton() {
    const shineBtn = this.toolbar.querySelector('[data-action="shine"]');
    if (!shineBtn) return;

    /* shineUrl хоосон, null, undefined эсвэл default утгатай бол нуух */
    const url = this.opts.shineUrl;
    const isConfigured = url && url.trim() && url !== '/dashboard/ai/shine';

    if (!isConfigured) {
      /* Товч болон түүний өмнөх separator-ийг нуух */
      const group = shineBtn.closest('.moedit-group');
      if (group) {
        const prevSep = group.previousElementSibling;
        if (prevSep && prevSep.classList.contains('moedit-sep')) {
          prevSep.style.display = 'none';
        }
        group.style.display = 'none';
      } else {
        shineBtn.style.display = 'none';
      }
    }
  }

  /**
   * Shine товчийг контентын байдлаас хамааран enable/disable хийх
   * - Контент хоосон эсвэл зөвхөн whitespace бол disable
   * @private
   */
  _updateShineButtonState() {
    const shineBtn = this.toolbar.querySelector('[data-action="shine"]');
    if (!shineBtn) return;

    /* shineUrl тохируулаагүй бол товч аль хэдийн нуугдсан байна */
    const url = this.opts.shineUrl;
    const isConfigured = url && url.trim() && url !== '/dashboard/ai/shine';
    if (!isConfigured) return;

    const html = this.getHTML();

    /* HTML tag-үүдийг арилгаж цэвэр текст авах */
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = html;
    const textContent = tempDiv.textContent || tempDiv.innerText || '';
    const hasText = !!textContent.trim();

    /* Зураг байгаа эсэхийг шалгах */
    const hasImages = tempDiv.querySelectorAll('img').length > 0;

    /* Текст эсвэл зураг байвал хоосон биш */
    const isEmpty = !hasText && !hasImages;

    /* Контент хоосон бол disable */
    shineBtn.disabled = isEmpty;
    shineBtn.title = isEmpty
      ? 'AI Shine: Эхлээд контент бичнэ үү'
      : 'AI Shine - Контентыг Bootstrap 5-аар гоёжуулах';

    /* Disabled үед бүрэн саарал, ойлгомжтой харагдах */
    if (isEmpty) {
      shineBtn.style.opacity = '0.4';
      shineBtn.style.filter = 'grayscale(100%)';
      shineBtn.style.cursor = 'not-allowed';
    } else {
      shineBtn.style.opacity = '1';
      shineBtn.style.filter = 'none';
      shineBtn.style.cursor = 'pointer';
    }
  }

  /**
   * OCR товчийг shineUrl-ээс хамааран харуулах/нуух
   * @private
   */
  _toggleOcrButton() {
    const ocrBtn = this.toolbar.querySelector('[data-action="ocr"]');
    if (!ocrBtn) return;

    /* shineUrl хоосон, null, undefined эсвэл default утгатай бол нуух */
    const url = this.opts.shineUrl;
    const isConfigured = url && url.trim() && url !== '/dashboard/ai/shine';

    if (!isConfigured) {
      ocrBtn.style.display = 'none';
    }
  }

  /**
   * OCR товчийг контентын байдлаас хамааран enable/disable хийх
   * - Зураг байвал enable, үгүй бол disable
   * @private
   */
  _updateOcrButtonState() {
    const ocrBtn = this.toolbar.querySelector('[data-action="ocr"]');
    if (!ocrBtn) return;

    /* shineUrl тохируулаагүй бол товч аль хэдийн нуугдсан байна */
    const url = this.opts.shineUrl;
    const isConfigured = url && url.trim() && url !== '/dashboard/ai/shine';
    if (!isConfigured) return;

    const html = this.getHTML();

    /* HTML-ээс зураг шалгах */
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = html;
    const hasImages = tempDiv.querySelectorAll('img').length > 0;

    /* Зураг байвал enable, үгүй бол disable */
    ocrBtn.disabled = !hasImages;
    ocrBtn.title = hasImages
      ? 'AI OCR - Зураг дээрх текстийг HTML болгох'
      : 'AI OCR: Эхлээд зураг оруулна уу';

    /* Disabled үед бүрэн саарал, ойлгомжтой харагдах */
    if (!hasImages) {
      ocrBtn.style.opacity = '0.4';
      ocrBtn.style.filter = 'grayscale(100%)';
      ocrBtn.style.cursor = 'not-allowed';
    } else {
      ocrBtn.style.opacity = '1';
      ocrBtn.style.filter = 'none';
      ocrBtn.style.cursor = 'pointer';
    }
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

      const html = this.editor.innerHTML;
      this.source.value = this._formatHTML(html);
      this.root.classList.add("is-source");
      this.source.focus();

      /* Source textarea дээр cursor байрлуулах */
      const sourceLength = this.source.value.length;
      const sourcePos = Math.round(cursorRatio * sourceLength);
      this.source.setSelectionRange(sourcePos, sourcePos);
    } else {
      /* Source -> Editor: cursor байрлалыг тооцоолох */
      const sourcePos = this.source.selectionStart;
      const sourceLength = this.source.value.length;
      const cursorRatio = sourceLength > 0 ? sourcePos / sourceLength : 0;

      this.editor.innerHTML = this.source.value;
      this.root.classList.remove("is-source");
      this._focusEditor();

      /* Editor дээр cursor байрлуулах */
      const textLength = this.editor.textContent.length;
      const targetOffset = Math.round(cursorRatio * textLength);
      this._setEditorCursorOffset(targetOffset);
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
        e.target.value = "P";
        this._emitChange();
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
        /* Сонгосон утгыг харуулсан хэвээр үлдээнэ (reset хийхгүй) */
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
        /* Хар өнгө сонгосон бол picker-ийг reset хийх */
        if (e.target.value.toLowerCase() === '#000000') {
          e.target.value = '#000000';
        }
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
    for (const [key, cfg] of Object.entries(this.registry)) {
      if (!cfg.toggle) continue;
      const btn = this.toolbar.querySelector(`[data-action="${key}"]`);
      if (!btn) continue;

      let on = false;
      try { on = document.queryCommandState(cfg.cmd); } catch {}
      btn.setAttribute("aria-pressed", on ? "true" : "false");
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
      'fullscreen': 'Бүтэн дэлгэц'
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
    /* Shine болон OCR товчний төлвийг шинэчлэх */
    this._updateShineButtonState();
    this._updateOcrButtonState();

    if (typeof this.opts.onChange === "function") {
      this.opts.onChange(this.getHTML());
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
    if (!html || !html.trim()) return html;

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
