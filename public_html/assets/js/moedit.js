class moedit {
  constructor(root, opts = {}) {
    if (!root) throw new Error("moedit: root element is required");

    this.root = root;
    this.editor = root.querySelector(".moedit-editor");
    this.source = root.querySelector(".moedit-source");
    this.toolbar = root.querySelector(".moedit-toolbar");

    this.isSource = false;

    this.opts = {
      onChange: null,
      prompt: (label, def = "") => window.prompt(label, def),
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
      unlink:    { type: "cmd", cmd: "unlink" },
      image:     { type: "fn", fn: () => this._insertImage() },
      table:     { type: "fn", fn: () => this._insertTable() },
      hr:        { type: "fn", fn: () => this._insertHR() },
      email:     { type: "fn", fn: () => this._insertEmail() },
      youtube:   { type: "fn", fn: () => this._insertYouTube() },
      facebook:  { type: "fn", fn: () => this._insertFacebook() },

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
  }

  /* ---------------- core ---------------- */

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

  toggleSource(force) {
    const next = typeof force === "boolean" ? force : !this.isSource;
    this.isSource = next;

    if (next) {
      const html = this.editor.innerHTML;
      this.source.value = this._formatHTML(html);
      this.root.classList.add("is-source");
      this.source.focus();
    } else {
      this.editor.innerHTML = this.source.value;
      this.root.classList.remove("is-source");
      this._focusEditor();
    }

    /* Source товчны toggle state шинэчлэх */
    const sourceBtn = this.toolbar.querySelector('[data-action="source"]');
    if (sourceBtn) {
      sourceBtn.setAttribute("aria-pressed", next ? "true" : "false");
    }

    this._emitChange();
  }

  getHTML() {
    return this.isSource ? this.source.value : this.editor.innerHTML;
  }

  setHTML(html) {
    const v = html ?? "";
    this.editor.innerHTML = v;
    this.source.value = v;
    this._emitChange();
  }

  /* ---------------- internals ---------------- */

  _bind() {
    this.toolbar.addEventListener("mousedown", e => {
      /* Зөвхөн button дээр preventDefault хийх, select дээр хийхгүй (dropdown нээгдэхийн тулд) */
      if (e.target.closest("button") && !e.target.closest("select")) e.preventDefault();
    });

    this.toolbar.addEventListener("click", e => {
      const btn = e.target.closest("button[data-action]");
      if (!btn) return;
      this.exec(btn.dataset.action);
    });

    const heading = this.toolbar.querySelector('[data-action="heading"]');
    if (heading) {
      heading.addEventListener("change", e => {
        document.execCommand("formatBlock", false, e.target.value);
        e.target.value = "P";
        this._emitChange();
      });
    }

    const fontSize = this.toolbar.querySelector('[data-action="fontSize"]');
    console.log('[moedit] fontSize element:', fontSize);
    if (fontSize) {
      let savedRange = null;

      /* Select дээр focus очихоос өмнө selection хадгалах */
      fontSize.addEventListener("mousedown", () => {
        const selection = window.getSelection();
        console.log('[moedit fontSize] mousedown, selection rangeCount:', selection.rangeCount);
        if (selection.rangeCount > 0) {
          savedRange = selection.getRangeAt(0).cloneRange();
          console.log('[moedit fontSize] savedRange:', savedRange.toString());
        }
      });

      fontSize.addEventListener("change", e => {
        const selectedValue = e.target.value;
        console.log('[moedit fontSize] change, value:', selectedValue, 'savedRange:', savedRange ? savedRange.toString() : 'null');

        /* Selection сэргээх */
        if (savedRange) {
          this._focusEditor();
          const selection = window.getSelection();
          selection.removeAllRanges();
          selection.addRange(savedRange);
          console.log('[moedit fontSize] selection restored');
        }

        /* Default (3) сонгосон бол font size хэрэглэхгүй, format устгах */
        if (selectedValue === "3") {
          document.execCommand("removeFormat", false, null);
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

      /* Color picker дээр focus очихоос өмнө selection хадгалах */
      foreColor.addEventListener("mousedown", () => {
        const selection = window.getSelection();
        if (selection.rangeCount > 0) {
          savedColorRange = selection.getRangeAt(0).cloneRange();
        }
      });

      foreColor.addEventListener("change", e => {
        /* Selection сэргээх */
        if (savedColorRange) {
          this._focusEditor();
          const selection = window.getSelection();
          selection.removeAllRanges();
          selection.addRange(savedColorRange);
        }

        this.exec("foreColor", e.target.value);
        savedColorRange = null;
      });
    }

    this.editor.addEventListener("input", () => !this.isSource && this._emitChange());
    this.source.addEventListener("input", () => this.isSource && this._emitChange());

    /* Paste event listener - Word/Excel content-ийг цэвэрлэх */
    this.editor.addEventListener("paste", async (e) => {
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

      console.log('[moedit paste] hasLocalImage:', hasLocalImage, 'hasBase64Image:', hasBase64Image);
      if (hasLocalImage) {
        /* Local file path олдвол HTML-ийн эхний хэсгийг харуулах */
        const imgMatch = htmlData.match(/<img[^>]*src\s*=\s*["']?([^"'\s>]+)["']?[^>]*>/i);
        if (imgMatch) {
          console.log('[moedit paste] Олдсон img src:', imgMatch[1].substring(0, 100) + '...');
        }
      }

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
        console.log('[moedit paste] Local/Base64 зураг илэрлээ. Clipboard images:', imageItems.length);

        /* Local file path-уудыг placeholder-ээр солих (browser warning-аас зайлсхийх) */
        let safeHtml = htmlData
          .replace(/(<img[^>]*src\s*=\s*["']?)(file:\/\/\/[^"'\s>]*)(["']?)/gi, '$1#LOCAL_FILE_PLACEHOLDER#$3')
          .replace(/(<img[^>]*src\s*=\s*["']?)([A-Za-z]:[\\\/][^"'\s>]*)(["']?)/gi, '$1#LOCAL_FILE_PLACEHOLDER#$3');

        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = safeHtml;
        const images = tempDiv.querySelectorAll('img');
        console.log('[moedit paste] HTML дахь нийт img:', images.length);

        let uploadedCount = 0;
        let clipboardImageIndex = 0;

        /* Эхлээд бүх clipboard зургуудыг upload хийх */
        const uploadedUrls = [];
        for (const imageItem of imageItems) {
          const file = imageItem.getAsFile();
          if (file) {
            console.log('[moedit paste] Uploading clipboard image:', file.name, file.type, file.size);
            try {
              const uploadedUrl = await this.opts.uploadImage(file);
              if (uploadedUrl) {
                console.log('[moedit paste] Upload амжилттай:', uploadedUrl);
                uploadedUrls.push(uploadedUrl);
              }
            } catch (err) {
              console.error('[moedit paste] Image upload failed:', err);
            }
          }
        }
        console.log('[moedit paste] Upload хийсэн зургууд:', uploadedUrls.length);

        /* Хэрэв clipboard-д зураг байхгүй бол Clipboard API ашиглах */
        if (uploadedUrls.length === 0 && hasLocalImage) {
          console.log('[moedit paste] Clipboard items байхгүй, Clipboard API оролдож байна...');
          try {
            const clipboardApiItems = await navigator.clipboard.read();
            console.log('[moedit paste] Clipboard API items:', clipboardApiItems.length);

            for (const clipItem of clipboardApiItems) {
              console.log('[moedit paste] Clipboard item types:', clipItem.types);
              const imageType = clipItem.types.find(type => type.startsWith('image/'));
              if (imageType) {
                console.log('[moedit paste] Clipboard API-с зураг олдлоо:', imageType);
                const blob = await clipItem.getType(imageType);
                const file = new File([blob], `pasted-image-${uploadedUrls.length + 1}.png`, { type: imageType });
                const uploadedUrl = await this.opts.uploadImage(file);
                if (uploadedUrl) {
                  console.log('[moedit paste] Clipboard API upload амжилттай:', uploadedUrl);
                  uploadedUrls.push(uploadedUrl);
                }
              }
            }
          } catch (clipErr) {
            console.warn('[moedit paste] Clipboard API алдаа:', clipErr.message);
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
              img.className = 'img-fluid rounded';
              img.style.maxWidth = '100%';
              img.style.height = 'auto';
              img.removeAttribute('width');
              img.removeAttribute('height');
              clipboardImageIndex++;
              uploadedCount++;
            } else {
              /* Upload хийсэн зураг байхгүй бол img-ийг устгах */
              console.warn('[moedit paste] Зураг upload хийх боломжгүй, устгаж байна');
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
                img.className = 'img-fluid rounded';
                img.style.maxWidth = '100%';
                img.style.height = 'auto';
                img.removeAttribute('width');
                img.removeAttribute('height');
                uploadedCount++;
              } else {
                img.parentNode?.removeChild(img);
              }
            } catch (err) {
              console.error('Base64 image upload failed:', err);
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
        console.log('[moedit paste] Clipboard items байхгүй, Clipboard API ашиглаж байна...');
        try {
          const clipboardItems = await navigator.clipboard.read();
          console.log('[moedit paste] Clipboard API items:', clipboardItems.length);
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
                img.className = 'img-fluid rounded';
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
          console.warn('Clipboard API error:', clipErr.message);
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
                img.className = 'img-fluid rounded';
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
              console.error('Image upload failed:', err);
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
    });
    
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

    /* Keyboard shortcuts */
    this.editor.addEventListener("keydown", e => {
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
    });

    this.source.addEventListener("keydown", e => {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
        e.preventDefault();
        this.toggleSource(false);
      }
    });

    document.addEventListener("selectionchange", () => {
      if (this.root.isConnected && !this.isSource) this._syncToggleStates();
    });
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
    const selection = window.getSelection();
    let selectedText = '';
    if (selection.rangeCount > 0) {
      selectedText = selection.toString();
    }
    const url = this.opts.prompt("Link URL оруул", selectedText || "https://");
    if (url && url.trim()) {
      if (selectedText) {
        document.execCommand("createLink", false, url.trim());
      } else {
        const link = document.createElement('a');
        link.href = url.trim();
        link.textContent = url.trim();
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        const range = selection.getRangeAt(0);
        range.deleteContents();
        range.insertNode(link);
        this._emitChange();
      }
    }
  }

  _insertImage() {
    const url = this.opts.prompt("Image URL оруул", "https://");
    if (url && url.trim()) {
      const img = document.createElement('img');
      img.src = url.trim();
      img.alt = '';
      img.style.maxWidth = '100%';
      img.style.height = 'auto';
      const selection = window.getSelection();
      if (selection.rangeCount > 0) {
        const range = selection.getRangeAt(0);
        range.insertNode(img);
        this._emitChange();
      } else {
        document.execCommand("insertImage", false, url.trim());
      }
    }
  }

  _insertTable() {
    const rows = parseInt(this.opts.prompt("Мөрний тоо", "3")) || 3;
    const cols = parseInt(this.opts.prompt("Баганы тоо", "3")) || 3;
    
    if (rows > 0 && cols > 0) {
      const table = document.createElement('table');
      table.className = 'table table-bordered table-striped';
      
      const tbody = document.createElement('tbody');
      
      for (let i = 0; i < rows; i++) {
        const tr = document.createElement('tr');
        for (let j = 0; j < cols; j++) {
          const cell = document.createElement(i === 0 ? 'th' : 'td');
          cell.innerHTML = '&nbsp;';
          tr.appendChild(cell);
        }
        tbody.appendChild(tr);
      }
      
      table.appendChild(tbody);
      
      const selection = window.getSelection();
      if (selection.rangeCount > 0) {
        const range = selection.getRangeAt(0);
        range.deleteContents();
        range.insertNode(table);
        
        /* Cursor-ийг эхний cell-д байрлуулах */
        const firstCell = table.querySelector('th, td');
        if (firstCell) {
          const newRange = document.createRange();
          newRange.selectNodeContents(firstCell);
          newRange.collapse(true);
          selection.removeAllRanges();
          selection.addRange(newRange);
        }
      } else {
        document.execCommand("insertHTML", false, table.outerHTML);
      }
      
      this._emitChange();
    }
  }

  _setFontSize(size) {
    if (size) {
      document.execCommand("fontSize", false, size);
    }
  }

  _setForeColor(color) {
    if (color) {
      document.execCommand("foreColor", false, color);
    }
  }

  _insertHR() {
    document.execCommand("insertHorizontalRule", false, null);
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
        wrapper.className = 'ratio ratio-16x9';
        wrapper.style.maxWidth = '560px';
        wrapper.style.margin = '10px 0';

        const iframe = document.createElement('iframe');
        iframe.src = 'https://www.youtube.com/embed/' + videoId;
        iframe.width = '560';
        iframe.height = '315';
        iframe.frameBorder = '0';
        iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
        iframe.allowFullscreen = true;
        iframe.style.border = 'none';

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
      wrapper.className = 'ratio ratio-16x9';
      wrapper.style.maxWidth = '560px';
      wrapper.style.margin = '10px 0';

      const iframe = document.createElement('iframe');
      iframe.src = 'https://www.facebook.com/plugins/video.php?href=' + encodeURIComponent(url.trim()) + '&show_text=0&width=560';
      iframe.width = '560';
      iframe.height = '315';
      iframe.frameBorder = '0';
      iframe.scrolling = 'no';
      iframe.allow = 'encrypted-media';
      iframe.style.border = 'none';

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
          if (!newElement.hasAttribute('class')) {
            newElement.className = 'img-fluid rounded';
          }
        }

        if (tagName === 'table' && !newElement.hasAttribute('class')) {
          newElement.className = 'table table-bordered table-striped';
        }

        if (tagName === 'p' && !newElement.hasAttribute('class')) {
          newElement.className = 'mb-3';
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

  _emitChange() {
    if (typeof this.opts.onChange === "function") {
      this.opts.onChange(this.getHTML());
    }
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

/* global helper */
window.moedit = moedit;
window.moeditInitAll = (selector = ".moedit") =>
  Array.from(document.querySelectorAll(selector))
    .map(el => new moedit(el));
