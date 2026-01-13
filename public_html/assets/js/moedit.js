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
    };

    this._bind();
    this._syncToggleStates();

    /** @private */
    this._destroyed = false;
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

    /* Paste event listener - Word/Excel content-ийг цэвэрлэх */
    this._boundHandlers.editorPaste = async (e) => {
      e.preventDefault();
      const clipboardData = e.clipboardData || window.clipboardData;

      /* Clipboard-аас зураг олох - бүх image type-ийг шалгах */
      const items = Array.from(clipboardData.items || []);
      let imageItems = items.filter(item => item.type.startsWith('image/'));

      /* Image item олдоогүй бол files шалгах */
      if (imageItems.length === 0 && clipboardData.files && clipboardData.files.length > 0) {
        for (const file of clipboardData.files) {
          if (file && file.type.startsWith('image/')) {
            imageItems.push({ getAsFile: () => file, type: file.type });
          }
        }
      }

      const htmlData = clipboardData.getData('text/html');
      const plainText = clipboardData.getData('text/plain');

      /* HTML дотор local file path эсвэл base64 зурагтай img байгаа эсэхийг шалгах */
      const localImgRegex = /src\s*=\s*["']?(file:\/\/\/|file:\/\/|[A-Za-z]:[\\\/])[^"'\s>]*/i;
      const base64ImgRegex = /src\s*=\s*["']?data:image\/[^"'\s>]+/i;
      const hasLocalImage = htmlData && localImgRegex.test(htmlData);
      const hasBase64Image = htmlData && base64ImgRegex.test(htmlData);


      /* Base64 зургийг File болгон хөрвүүлэх helper функц */
      const base64ToFile = (dataUrl, filename = 'pasted-image.png') => {
        const arr = dataUrl.split(',');
        const mimeMatch = arr[0].match(/:(.*?);/);
        const mime = mimeMatch ? mimeMatch[1] : 'image/png';
        const bstr = atob(arr[1]);
        let n = bstr.length;
        const u8arr = new Uint8Array(n);
        while(n--) {
          u8arr[n] = bstr.charCodeAt(n);
        }
        const ext = mime.split('/')[1] || 'png';
        return new File([u8arr], `${filename}.${ext}`, { type: mime });
      };

      /* Word-оос зурагтай content paste хийж байна - олон зургийг дэмжих */
      if ((hasLocalImage || hasBase64Image) && this.opts.uploadImage) {

        /* Local file path-уудыг placeholder-ээр солих (browser warning-аас зайлсхийх) */
        let safeHtml = htmlData
          .replace(/(<img[^>]*src\s*=\s*["']?)(file:\/\/\/[^"'\s>]*)(["']?)/gi, '$1#LOCAL_FILE_PLACEHOLDER#$3')
          .replace(/(<img[^>]*src\s*=\s*["']?)([A-Za-z]:[\\\/][^"'\s>]*)(["']?)/gi, '$1#LOCAL_FILE_PLACEHOLDER#$3');

        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = safeHtml;
        const images = tempDiv.querySelectorAll('img');

        let uploadedCount = 0;
        let clipboardImageIndex = 0;

        /* Эхлээд бүх clipboard зургуудыг upload хийх */
        const uploadedUrls = [];
        for (const imageItem of imageItems) {
          const file = imageItem.getAsFile();
          if (file) {
            try {
              const uploadedUrl = await this.opts.uploadImage(file);
              if (uploadedUrl) {
                uploadedUrls.push(uploadedUrl);
              }
            } catch (err) {
              /* Upload алдаа - үргэлжлүүлнэ */
            }
          }
        }

        /* Хэрэв clipboard-д зураг байхгүй бол Clipboard API ашиглах */
        if (uploadedUrls.length === 0 && hasLocalImage) {
          try {
            const clipboardApiItems = await navigator.clipboard.read();

            for (const clipItem of clipboardApiItems) {
              const imageType = clipItem.types.find(type => type.startsWith('image/'));
              if (imageType) {
                const blob = await clipItem.getType(imageType);
                const file = new File([blob], `pasted-image-${uploadedUrls.length + 1}.png`, { type: imageType });
                const uploadedUrl = await this.opts.uploadImage(file);
                if (uploadedUrl) {
                  uploadedUrls.push(uploadedUrl);
                }
              }
            }
          } catch (clipErr) {
            /* Clipboard API алдаа - үргэлжлүүлнэ */
          }
        }

        for (const img of images) {
          const src = img.getAttribute('src') || '';
          const isLocalPlaceholder = src === '#LOCAL_FILE_PLACEHOLDER#';
          const isBase64 = src.startsWith('data:image/');

          if (isLocalPlaceholder) {
            /* Local file placeholder-ийг upload хийсэн URL-ээр солих */
            if (clipboardImageIndex < uploadedUrls.length) {
              img.setAttribute('src', uploadedUrls[clipboardImageIndex]);
              img.style.maxWidth = '100%';
              img.style.height = 'auto';
              img.removeAttribute('width');
              img.removeAttribute('height');
              clipboardImageIndex++;
              uploadedCount++;
            } else {
              /* Upload хийсэн зураг байхгүй бол img-ийг устгах */
              img.parentNode?.removeChild(img);

              /* Хэрэглэгчид мэдэгдэл харуулах */
              if (typeof NotifyTop === 'function') {
                NotifyTop('warning', 'Анхааруулга', 'Word дээрх зургийг хуулж чадсангүй. Зургийг тусад нь хуулж (зөвхөн зургийг сонгоод Ctrl+C) дараа нь paste хийнэ үү.');
              } else {
                alert('Word дээрх зургийг хуулж чадсангүй. Зургийг тусад нь хуулж (зөвхөн зургийг сонгоод Ctrl+C) дараа нь paste хийнэ үү.');
              }
            }
          } else if (isBase64) {
            /* Base64 зургийг File болгож upload хийх */
            try {
              const file = base64ToFile(src, `pasted-image-${uploadedCount + 1}`);
              const uploadedUrl = await this.opts.uploadImage(file);
              if (uploadedUrl) {
                img.setAttribute('src', uploadedUrl);
                img.style.maxWidth = '100%';
                img.style.height = 'auto';
                img.removeAttribute('width');
                img.removeAttribute('height');
                uploadedCount++;
              } else {
                img.parentNode?.removeChild(img);
              }
            } catch (err) {
              img.parentNode?.removeChild(img);
            }
          }
        }

        /* HTML-ийг цэвэрлэж editor-д оруулах */
        const cleanedDiv = this._cleanNode(tempDiv);
        this._insertCleanedContent(cleanedDiv ? cleanedDiv.innerHTML : '');
        return;
      }

      /* Word-оос paste хийсэн бол imageItem байхгүй байж болно - Clipboard API ашиглах */
      if (hasLocalImage && imageItems.length === 0 && this.opts.uploadImage) {
        try {
          const clipboardItems = await navigator.clipboard.read();
          const uploadedUrls = [];

          for (const clipItem of clipboardItems) {
            const imageType = clipItem.types.find(type => type.startsWith('image/'));
            if (imageType) {
              const blob = await clipItem.getType(imageType);
              const file = new File([blob], `pasted-image-${uploadedUrls.length + 1}.png`, { type: imageType });
              const uploadedUrl = await this.opts.uploadImage(file);
              if (uploadedUrl) {
                uploadedUrls.push(uploadedUrl);
              }
            }
          }

          if (uploadedUrls.length > 0) {
            /* Local file path-уудыг placeholder-ээр солих */
            let safeHtml2 = htmlData
              .replace(/(<img[^>]*src\s*=\s*["']?)(file:\/\/\/[^"'\s>]*)(["']?)/gi, '$1#LOCAL_FILE_PLACEHOLDER#$3')
              .replace(/(<img[^>]*src\s*=\s*["']?)([A-Za-z]:[\\\/][^"'\s>]*)(["']?)/gi, '$1#LOCAL_FILE_PLACEHOLDER#$3');

            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = safeHtml2;
            const images = tempDiv.querySelectorAll('img');
            let urlIndex = 0;

            images.forEach(img => {
              const src = img.getAttribute('src') || '';
              const isLocalPlaceholder = src === '#LOCAL_FILE_PLACEHOLDER#';
              if (isLocalPlaceholder && urlIndex < uploadedUrls.length) {
                img.setAttribute('src', uploadedUrls[urlIndex]);
                img.style.maxWidth = '100%';
                img.style.height = 'auto';
                img.removeAttribute('width');
                img.removeAttribute('height');
                urlIndex++;
              } else if (isLocalPlaceholder) {
                img.parentNode?.removeChild(img);
              }
            });

            const cleanedDiv = this._cleanNode(tempDiv);
            this._insertCleanedContent(cleanedDiv ? cleanedDiv.innerHTML : '');
            return;
          }
        } catch (clipErr) {
          /* Clipboard API алдаа - үргэлжлүүлнэ */
        }
      }

      /* Зөвхөн зураг paste хийж байвал (screenshot гэх мэт) */
      if (imageItems.length > 0 && this.opts.uploadImage && !htmlData) {
        for (const imageItem of imageItems) {
          const file = imageItem.getAsFile();
          if (file) {
            try {
              const uploadedUrl = await this.opts.uploadImage(file);
              if (uploadedUrl) {
                const img = document.createElement('img');
                img.src = uploadedUrl;
                img.style.maxWidth = '100%';
                img.style.height = 'auto';

                const selection = window.getSelection();
                if (selection.rangeCount > 0) {
                  const range = selection.getRangeAt(0);
                  range.deleteContents();
                  range.insertNode(img);
                  range.setStartAfter(img);
                  range.collapse(true);
                  selection.removeAllRanges();
                  selection.addRange(range);
                }
              }
            } catch (err) {
              /* Image upload алдаа - үргэлжлүүлнэ */
            }
          }
        }
        this._emitChange();
        return;
      }

      /* HTML эсвэл plain text paste хийх */
      let pastedData = htmlData || plainText;

      /* _cleanPastedContent нь placeholder ашиглан local file болон base64 зургуудыг устгана */
      if (pastedData) {
        const cleaned = await this._cleanPastedContent(pastedData);
        this._insertCleanedContent(cleaned);
      }
    };
    this.editor.addEventListener("paste", this._boundHandlers.editorPaste);

    /* Helper: Цэвэрлэсэн content-ийг editor-д оруулах */
    this._insertCleanedContent = (html) => {
      const selection = window.getSelection();
      if (selection.rangeCount > 0) {
        const range = selection.getRangeAt(0);
        range.deleteContents();
        
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = html;
        
        const fragment = document.createDocumentFragment();
        const nodes = [];
        while (tempDiv.firstChild) {
          const node = tempDiv.firstChild;
          nodes.push(node);
          fragment.appendChild(node);
        }
        
        if (nodes.length > 0) {
          range.insertNode(fragment);
          
          const lastNode = nodes[nodes.length - 1];
          if (lastNode && lastNode.parentNode) {
            range.setStartAfter(lastNode);
            range.collapse(true);
            selection.removeAllRanges();
            selection.addRange(range);
          } else {
            range.collapse(false);
          }
        }
      }
      this._emitChange();
    };

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

  _insertLink() {
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
  }

  _insertImage() {
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
      /* URL prompt ашиглах */
      const url = this.opts.prompt("Image URL оруул", "https://");
      if (url && url.trim()) {
        this._insertImageByUrl(url.trim(), savedRange);
      }
    }
  }

  _showImageUploadDialog(savedRange) {
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
  }

  _insertImageByUrl(url, savedRange) {
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
  }

  _insertTable() {
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
    const closeDialog = () => {
      dialog.remove();
    };

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
  }

  _setFontSize(size) {
    if (size) {
      document.execCommand("fontSize", false, size);
    }
  }

  /* Font size-ийг устгах, бусад форматыг хэвээр үлдээх */
  _removeFontSize() {
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
  }

  _setForeColor(color) {
    if (color) {
      document.execCommand("foreColor", false, color);
    }
  }

  /**
   * Font color устгах, бусад форматыг хэвээр үлдээх
   * @private
   */
  _removeFontColor() {
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
  }

  _insertHR() {
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
  }

  _insertEmail() {
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
  }

  _insertYouTube() {
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
  }

  _insertFacebook() {
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
  }

  _paste() {
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
  }

  _pasteText() {
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
  }

  async _cleanPastedContent(html) {
    if (!html) return '';

    /* 1. Local file path болон base64 зурагтай img tag-уудыг placeholder-ээр солих (browser warning-аас зайлсхийх) */
    let cleanedHtml = html
      .replace(/(<img[^>]*src\s*=\s*["']?)(file:\/\/\/[^"'\s>]*)(["']?)/gi, '$1#LOCAL_FILE_PLACEHOLDER#$3')
      .replace(/(<img[^>]*src\s*=\s*["']?)([A-Za-z]:[\\\/][^"'\s>]*)(["']?)/gi, '$1#LOCAL_FILE_PLACEHOLDER#$3')
      .replace(/(<img[^>]*src\s*=\s*["']?)(data:image\/[^"'\s>]*)(["']?)/gi, '$1#BASE64_PLACEHOLDER#$3');

    /* Temporary div ашиглан HTML-ийг parse хийх */
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = cleanedHtml;

    /* Word/Excel-ийн нэмэлт tag-уудыг цэвэрлэх (_cleanNode нь placeholder-тай img-уудыг устгана) */
    const cleanedDiv = this._cleanNode(tempDiv);
    return cleanedDiv ? cleanedDiv.innerHTML : '';
  }

  /* HTML node-ийг цэвэрлэх helper функц */
  _cleanNode(tempDiv) {
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
              if (cleaned.nodeType === Node.DOCUMENT_FRAGMENT_NODE) {
                fragment.appendChild(cleaned);
              } else {
                fragment.appendChild(cleaned);
              }
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
  }

  _print() {
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
      <!DOCTYPE html>
      <html>
        <head>
          <title>Print</title>
          <style>
            body { font-family: Arial, sans-serif; padding: 20px; }
            img { max-width: 100%; height: auto; }
            table { border-collapse: collapse; width: 100%; }
            table, th, td { border: 1px solid #ddd; }
            th, td { padding: 8px; text-align: left; }
          </style>
        </head>
        <body>
          ${this.getHTML()}
        </body>
      </html>
    `);
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => {
      printWindow.print();
      printWindow.close();
    }, 250);
  }

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
