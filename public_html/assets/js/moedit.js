/**
 * moedit - Mongolian WYSIWYG Editor v1
 *
 * Контент засварлах rich text editor.
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

    /* Language detection - stored as instance property for UI methods */
    /** @private */
    this._isMn = document.documentElement.lang === 'mn';
    const isMn = this._isMn;

    /* toolbarPosition default утга - _createWrapper дуудахаас ӨМНӨ тохируулах */
    if (!opts.toolbarPosition) opts.toolbarPosition = 'right';

    /* onHeaderImageChange заасан бол headerImage автоматаар идэвхжүүлэх */
    if (opts.onHeaderImageChange && !opts.headerImage) opts.headerImage = true;

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
      this._createToolbar(isMn);
      this.toolbar = this.root.querySelector(".moedit-toolbar");
    }

    this.isSource = false;

    this.opts = {
      onChange: null,
      prompt: (label, def = "") => window.prompt(label, def),
      readonly: false,
      uploadUrl: null,
      uploadImage: null,
      uploadVideo: null,
      uploadAudio: null,
      onUploadSuccess: null,
      onUploadError: null,
      /* Image upload modal */
      imageUploadModal: {
        title: isMn ? 'Зураг оруулах' : 'Insert Image',
        placeholder: isMn ? 'Зураг сонгоогүй байна...' : 'No image selected...',
        browseText: isMn ? 'Сонгох' : 'Browse',
        cancelText: isMn ? 'Болих' : 'Cancel',
        uploadText: 'Upload',
        uploadingText: isMn ? 'Уншиж байна...' : 'Uploading...',
        successMessage: isMn ? 'Зураг амжилттай орлоо.' : 'Image uploaded successfully.',
        errorMessage: isMn ? 'Зураг upload хийхэд алдаа гарлаа' : 'Error uploading image',
        tabUpload: isMn ? 'Компьютерээс' : 'From Computer',
        tabUrl: isMn ? 'URL хаягаар' : 'From URL',
        urlLabel: isMn ? 'Зургийн URL хаяг' : 'Image URL',
        urlPlaceholder: 'https://example.com/image.jpg'
      },
      /* Video modal */
      videoModal: {
        title: isMn ? 'Видео оруулах' : 'Insert Video',
        placeholder: isMn ? 'Видео сонгоогүй байна...' : 'No video selected...',
        browseText: isMn ? 'Сонгох' : 'Browse',
        cancelText: isMn ? 'Болих' : 'Cancel',
        uploadText: 'Upload',
        uploadingText: isMn ? 'Уншиж байна...' : 'Uploading...',
        successMessage: isMn ? 'Видео амжилттай орлоо.' : 'Video inserted successfully.',
        errorMessage: isMn ? 'Видео upload хийхэд алдаа гарлаа' : 'Error uploading video',
        tabUpload: isMn ? 'Компьютерээс' : 'From Computer',
        tabUrl: isMn ? 'URL хаягаар' : 'From URL',
        urlLabel: isMn ? 'Видеоны URL хаяг' : 'Video URL',
        urlPlaceholder: 'https://example.com/video.mp4'
      },
      /* Audio modal */
      audioModal: {
        title: isMn ? 'Аудио оруулах' : 'Insert Audio',
        placeholder: isMn ? 'Аудио сонгоогүй байна...' : 'No audio selected...',
        browseText: isMn ? 'Сонгох' : 'Browse',
        cancelText: isMn ? 'Болих' : 'Cancel',
        uploadText: 'Upload',
        uploadingText: isMn ? 'Уншиж байна...' : 'Uploading...',
        successMessage: isMn ? 'Аудио амжилттай орлоо.' : 'Audio inserted successfully.',
        errorMessage: isMn ? 'Аудио upload хийхэд алдаа гарлаа' : 'Error uploading audio',
        tabUpload: isMn ? 'Компьютерээс' : 'From Computer',
        tabUrl: isMn ? 'URL хаягаар' : 'From URL',
        urlLabel: isMn ? 'Аудионы URL хаяг' : 'Audio URL',
        urlPlaceholder: 'https://example.com/audio.mp3'
      },
      /* Link modal */
      linkModal: {
        title: isMn ? 'Холбоос оруулах' : 'Insert Link',
        urlLabel: isMn ? 'URL хаяг' : 'URL',
        emailLabel: isMn ? 'Email хаяг' : 'Email',
        textLabel: isMn ? 'Харуулах текст' : 'Display text',
        textHint: isMn ? '(хоосон бол URL/Email харуулна)' : '(if empty, URL/Email will be shown)',
        cancelText: isMn ? 'Болих' : 'Cancel',
        okText: 'OK'
      },
      /* Table modal */
      tableModal: {
        title: isMn ? 'Хүснэгт оруулах' : 'Insert Table',
        rowsLabel: isMn ? 'Мөрийн тоо' : 'Rows',
        colsLabel: isMn ? 'Баганы тоо' : 'Columns',
        typeLabel: isMn ? 'Хүснэгтийн төрөл' : 'Table type',
        typeVanilla: 'Vanilla Table',
        typeBootstrap: 'Bootstrap Table',
        cancelText: isMn ? 'Болих' : 'Cancel',
        okText: 'OK'
      },
      /* YouTube modal */
      youtubeModal: {
        title: isMn ? 'YouTube видео оруулах' : 'Insert YouTube Video',
        urlLabel: isMn ? 'YouTube URL эсвэл Embed код' : 'YouTube URL or Embed code',
        placeholder: 'https://www.youtube.com/watch?v=... эсвэл <iframe>...</iframe>',
        hint: isMn ? 'YouTube дээр Share → Embed дарж кодыг хуулна уу, эсвэл видеоны URL хуулна уу' : 'Click Share → Embed on YouTube and copy the code, or paste the video URL',
        invalidUrl: isMn ? 'YouTube видео ID олдсонгүй. URL эсвэл embed код зөв эсэхийг шалгана уу.' : 'YouTube video ID not found. Please check the URL or embed code.',
        cancelText: isMn ? 'Болих' : 'Cancel',
        okText: isMn ? 'Оруулах' : 'Insert'
      },
      /* Facebook modal */
      facebookModal: {
        title: isMn ? 'Facebook видео оруулах' : 'Insert Facebook Video',
        urlLabel: isMn ? 'Facebook URL эсвэл Embed код' : 'Facebook URL or Embed code',
        placeholder: 'https://www.facebook.com/... эсвэл <iframe>...</iframe>',
        hint: isMn ? 'Facebook видео дээр ... → Embed дарж кодыг хуулна уу, эсвэл видеоны URL хуулна уу' : 'Click ... → Embed on Facebook video and copy the code, or paste the video URL',
        cancelText: isMn ? 'Болих' : 'Cancel',
        okText: isMn ? 'Оруулах' : 'Insert'
      },
      /* Twitter/X modal */
      twitterModal: {
        title: isMn ? 'Twitter/X пост оруулах' : 'Insert Twitter/X Post',
        urlLabel: isMn ? 'Twitter/X URL' : 'Twitter/X URL',
        placeholder: 'https://x.com/username/status/...',
        hint: isMn ? 'Tweet/пост дээр Share → Copy link дарж URL хуулна уу' : 'Click Share → Copy link on the tweet/post',
        invalidUrl: isMn ? 'Twitter/X пост олдсонгүй. URL зөв эсэхийг шалгана уу.' : 'Twitter/X post not found. Please check the URL.',
        cancelText: isMn ? 'Болих' : 'Cancel',
        okText: isMn ? 'Оруулах' : 'Insert'
      },
      /* Google Maps modal */
      mapModal: {
        title: isMn ? 'Google Maps оруулах' : 'Insert Google Maps',
        urlLabel: isMn ? 'Google Maps URL эсвэл Embed код' : 'Google Maps URL or Embed code',
        placeholder: 'https://www.google.com/maps/... эсвэл <iframe>...</iframe>',
        hint: isMn ? 'Google Maps дээр Share → Embed a map дарж кодыг хуулна уу' : 'On Google Maps click Share → Embed a map and copy the code',
        invalidUrl: isMn ? 'Google Maps URL буруу байна. Share → Embed a map ашиглана уу.' : 'Invalid Google Maps URL. Please use Share → Embed a map.',
        cancelText: isMn ? 'Болих' : 'Cancel',
        okText: isMn ? 'Оруулах' : 'Insert'
      },
      /* Shine modal */
      shineModal: {
        title: 'AI Shine',
        description: isMn ? 'Контентын бүтцийг шинжилж table, card, accordion зэрэг Bootstrap 5 компонент болгоно.' : 'Analyzes content structure and converts to Bootstrap 5 components like table, card, accordion.',
        processingText: isMn ? 'Боловсруулж байна...' : 'Processing...',
        successMessage: isMn ? 'Контент амжилттай гоёжууллаа!' : 'Content beautified successfully!',
        errorMessage: isMn ? 'Алдаа гарлаа' : 'An error occurred',
        emptyMessage: isMn ? 'Контент хоосон байна' : 'Content is empty',
        confirmText: isMn ? 'Шинэчлэх' : 'Apply',
        cancelText: isMn ? 'Болих' : 'Cancel',
        promptLabel: isMn ? 'AI-д өгөх заавар' : 'Instructions for AI',
        defaultPrompt: isMn ? `Доорх HTML контентыг Bootstrap 5 компонентууд ашиглан илүү гоё, мэргэжлийн түвшинд харагдуулах болгож өгнө үү.

Заавар:
1. Контентын бүтэц, агуулгыг шинжилж, тохирох Bootstrap 5 компонент болго:
   - Жагсаалт мэдээлэл → card эсвэл list-group
   - Харьцуулалт, олон багана мэдээлэл → table (table-striped table-hover table-bordered)
   - Асуулт-хариулт, FAQ → accordion
   - Алхам алхмаар заавар → list-group эсвэл card
   - Онцлох мэдээлэл → alert эсвэл callout
   - Холбоотой зүйлсийн жагсаалт → row/col grid
2. img → class="img-fluid rounded"
3. Текст агуулгыг ӨӨРЧЛӨХГҮЙ, зөвхөн HTML бүтцийг сайжруул
4. Хэт их биш, зөвхөн тохирох хэсэгт компонент ашигла
5. Хэрэв контент энгийн текст бол хүний нүдээр уншихад эвтэйхэн болгон форматлах
6. <div class="container">...</div> wrapper БҮҮ ашигла - вэб хуудас өөрөө container-тэй` : `Transform the HTML content below into a more professional look using Bootstrap 5 components.

Instructions:
1. Analyze content structure and convert to appropriate Bootstrap components:
   - List information → card or list-group
   - Comparisons, multi-column data → table (table-striped table-hover table-bordered)
   - Q&A, FAQ → accordion
   - Step-by-step instructions → list-group or card
   - Highlighted information → alert or callout
   - Related items list → row/col grid
2. img → class="img-fluid rounded"
3. DO NOT change text content, only improve HTML structure
4. Use components sparingly, only where appropriate
5. If content is plain text, format it for easy reading
6. DO NOT use <div class="container">...</div> wrapper - the page already has a container`
      },
      /* OCR modal */
      ocrModal: {
        title: 'AI OCR',
        description: isMn ? 'Зураг дээрх текстийг уншиж HTML болгоод editor-д оруулна.' : 'Reads text from image and converts to HTML.',
        processingText: isMn ? 'Зураг уншиж байна...' : 'Reading image...',
        successMessage: isMn ? 'Зургаас текст амжилттай задлагдлаа!' : 'Text extracted successfully!',
        errorMessage: isMn ? 'Алдаа гарлаа' : 'An error occurred',
        noImageMessage: isMn ? 'Зураг олдсонгүй. OCR ашиглахын тулд зураг оруулна уу.' : 'No image found. Please insert an image to use OCR.',
        confirmText: isMn ? 'Хөрвүүлэх' : 'Convert',
        cancelText: isMn ? 'Болих' : 'Cancel',
        promptLabel: isMn ? 'AI-д өгөх заавар' : 'Instructions for AI',
        defaultPrompt: isMn ? `Хавсаргасан ЗУРАГ дээрх текстийг уншаад HTML болго.

Заавар:
1. Зөвхөн ЗУРАГ дээрх текстийг унш
2. Хүснэгт байвал <table> ашигла (inline style-тай)
3. Жагсаалт байвал <ul> эсвэл <ol>
4. Гарчиг байвал <h1>-<h6> ашигла
5. Параграф <p> tag ашигла` : `Read the text from the attached IMAGE and convert to HTML.

Instructions:
1. Read only the text from the IMAGE
2. Use <table> for tables (with inline styles)
3. Use <ul> or <ol> for lists
4. Use <h1>-<h6> for headings
5. Use <p> tag for paragraphs`
      },
      /* PDF modal */
      pdfModal: {
        title: 'PDF → HTML',
        description: isMn ? 'PDF файлыг AI ашиглан HTML болгож editor-д оруулна.' : 'Converts PDF to HTML using AI.',
        placeholder: isMn ? 'PDF файл сонгоно уу...' : 'Select PDF file...',
        browseText: isMn ? 'Сонгох' : 'Browse',
        processingText: isMn ? 'PDF боловсруулж байна...' : 'Processing PDF...',
        renderingText: isMn ? 'Хуудас зурж байна...' : 'Rendering page...',
        successMessage: isMn ? 'PDF амжилттай HTML болгогдлоо!' : 'PDF converted successfully!',
        errorMessage: isMn ? 'Алдаа гарлаа' : 'An error occurred',
        confirmText: isMn ? 'Оруулах' : 'Insert',
        cancelText: isMn ? 'Болих' : 'Cancel',
        pageText: isMn ? 'хуудас' : 'page',
        promptLabel: isMn ? 'AI-д өгөх заавар' : 'Instructions for AI',
        defaultPrompt: isMn ? `Хавсаргасан PDF ЗУРАГ дээрх текстийг уншаад HTML болго.

Заавар:
1. Зөвхөн ЗУРАГ дээрх текстийг унш
2. Хүснэгт байвал <table> ашигла (inline style-тай)
3. Жагсаалт байвал <ul> эсвэл <ol>
4. Гарчиг байвал <h1>-<h6> ашигла
5. Параграф <p> tag ашигла` : `Read the text from the attached PDF IMAGE and convert to HTML.

Instructions:
1. Read only the text from the IMAGE
2. Use <table> for tables (with inline styles)
3. Use <ul> or <ol> for lists
4. Use <h1>-<h6> for headings
5. Use <p> tag for paragraphs`
      },
      /* Shine API URL */
      shineUrl: null,
      /* Notify function - optional */
      notify: null,
      /* Header image */
      headerImage: false,
      /* Header image modal */
      headerImageModal: {
        title: isMn ? 'Толгой зураг' : 'Header Image',
        placeholder: isMn ? 'Зураг сонгоогүй байна...' : 'No image selected...',
        browseText: isMn ? 'Сонгох' : 'Browse',
        removeText: isMn ? 'Устгах' : 'Remove',
        changeText: isMn ? 'Солих' : 'Change'
      },
      /* Header image callback - зураг сонгогдох/устгагдах үед дуудагдана */
      onHeaderImageChange: null, /* function(file, preview) - file: File object эсвэл null */
      /* Preview options */
      titleSelector: null, /* Title input-ийн CSS selector, жнь: '#news_title' */
      /* Attachment options */
      attachments: [], /* Анхны файлууд [{id, path, name, size, type, mime_content_type, description, date}] */
      onAttachmentChange: null, /* function(attachments) - файл нэмэх/устгах/засах үед дуудагдана */
      /* Toolbar position - 'top' эсвэл 'right' (right бол desktop-д баруун талд, mobile-д дээр) */
      toolbarPosition: 'right',
      ...opts,
    };

    this.registry = {
      bold:        { type: "cmd", cmd: "bold", toggle: true },
      italic:      { type: "cmd", cmd: "italic", toggle: true },
      underline:   { type: "cmd", cmd: "underline", toggle: true },
      strike:      { type: "cmd", cmd: "strikeThrough", toggle: true },
      mark:        { type: "fn", fn: () => this._toggleMark(), toggle: "mark" },
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
      video:     { type: "fn", fn: () => this._insertVideo() },
      audio:     { type: "fn", fn: () => this._insertAudio() },
      table:     { type: "fn", fn: () => this._insertTable() },
      accordion: { type: "fn", fn: () => this._insertAccordion() },
      hr:        { type: "fn", fn: () => this._insertHR() },
      email:     { type: "fn", fn: () => this._insertEmail() },
      youtube:   { type: "fn", fn: () => this._insertYouTube() },
      facebook:  { type: "fn", fn: () => this._insertFacebook() },
      twitter:   { type: "fn", fn: () => this._insertTwitter() },
      map:       { type: "fn", fn: () => this._insertMap() },
      headerImage: { type: "fn", fn: () => this._selectHeaderImage() },

      cut:       { type: "cmd", cmd: "cut" },
      copy:      { type: "cmd", cmd: "copy" },
      paste:     { type: "fn", fn: () => this._paste() },

      undo:      { type: "cmd", cmd: "undo" },
      redo:      { type: "cmd", cmd: "redo" },

      removeFormat: { type: "cmd", cmd: "removeFormat" },

      attachment: { type: "fn", fn: () => this._insertAttachment() },
      preview:    { type: "fn", fn: () => this._preview() },
      print:      { type: "fn", fn: () => this._print() },
      source:     { type: "fn", fn: () => this.toggleSource() },
      fullscreen: { type: "fn", fn: () => this.toggleFullscreen() },
      shine:      { type: "fn", fn: () => this._shine() },
      ocr:        { type: "fn", fn: () => this._ocr() },
      pdf:        { type: "fn", fn: () => this._insertPdf() },
    };

    this._bind();
    this._syncToggleStates();

    /* ENTER дарахад <div> биш <p> үүсгэх */
    document.execCommand('defaultParagraphSeparator', false, 'p');

    /* Shine товчийг shineUrl байхгүй бол нуух */
    this._toggleShineButton();

    /* Readonly горим идэвхжүүлэх */
    this._applyReadonly();

    /* Header image идэвхжүүлэх */
    this._initHeaderImage();

    /** @private */
    this._destroyed = false;
  }

  /**
   * Header image функцийг идэвхжүүлэх
   * @private
   */
  _initHeaderImage() {
    if (!this.opts.headerImage) return;

    /* Toolbar дахь headerImage товч болон separator харуулах */
    const headerImageGroup = this.toolbar.querySelector('.moedit-group-header-image');
    const headerImageSep = this.toolbar.querySelector('.moedit-sep-header-image');
    if (headerImageGroup) headerImageGroup.style.display = '';
    if (headerImageSep) headerImageSep.style.display = '';

    /* Header image preview area */
    this.headerImageArea = this.root.querySelector('.moedit-header-image');
    this.headerImagePreview = this.root.querySelector('.moedit-header-image-preview');

    if (this.headerImageArea) {
      /* Change товч */
      const changeBtn = this.headerImageArea.querySelector('.moedit-header-image-change');
      /* Remove товч */
      const removeBtn = this.headerImageArea.querySelector('.moedit-header-image-remove');

      /* Readonly горимд товчуудыг нуух */
      if (this.opts.readonly) {
        if (changeBtn) changeBtn.style.display = 'none';
        if (removeBtn) removeBtn.style.display = 'none';
      } else {
        if (changeBtn) {
          changeBtn.addEventListener('click', () => this._selectHeaderImage());
        }
        if (removeBtn) {
          removeBtn.addEventListener('click', () => this._removeHeaderImage());
        }
      }

      /* headerImage нь URL string бол анхны зургийг харуулах */
      if (typeof this.opts.headerImage === 'string' && this.opts.headerImage) {
        this.headerImagePreview.src = this.opts.headerImage;
        this.headerImageArea.style.display = 'block';

        /* Зураг алдаатай (404) үед overlay-г байнга харуулах */
        if (!this.opts.readonly) {
          const overlay = this.headerImageArea.querySelector('.moedit-header-image-overlay');
          this.headerImagePreview.addEventListener('error', () => {
            if (overlay) overlay.style.opacity = '1';
            this.headerImageArea.classList.add('moedit-header-image-error');
          });
        }
      }
    }

    /** @private Зураг optimize хийх эсэх */
    this._optimizeImages = true;

    /** @private Одоогийн header image file */
    this._headerImageFile = null;

    /** @private Хавсралт файлууд */
    this._attachments = [];
    this._deletedAttachmentIds = [];
    this._attachmentsArea = this.root.querySelector('.moedit-attachments');
    this._attachmentsTbody = this.root.querySelector('.moedit-attachments tbody');

    /* Анхны файлуудыг ачаалах */
    if (this.opts.attachments && this.opts.attachments.length > 0) {
      this.opts.attachments.forEach(f => {
        this._attachments.push({ ...f, _isExisting: true });
      });
      this._renderAttachments();
    }
  }

  /**
   * Header image устгах
   * @private
   */
  _removeHeaderImage() {
    if (this.headerImageArea) {
      this.headerImageArea.style.display = 'none';
      if (this.headerImagePreview) {
        this.headerImagePreview.src = '';
      }
    }
    this._headerImageFile = null;

    /* Callback дуудах */
    if (typeof this.opts.onHeaderImageChange === 'function') {
      this.opts.onHeaderImageChange(null, null);
    }
  }

  /**
   * Header image-ийн file object авах
   * @returns {File|null} Header image file эсвэл null
   */
  getHeaderImageFile() {
    return this._headerImageFile || null;
  }

  /**
   * Header image preview URL авах
   * @returns {string|null} Preview URL эсвэл null
   */
  getHeaderImagePreview() {
    return this.headerImagePreview?.src || null;
  }

  /**
   * Хавсралт файлуудын мэдээлэл авах
   * @returns {{newFiles: Array, existing: Array, deleted: Array}}
   */
  getAttachments() {
    return {
      newFiles: this._attachments.filter(f => !f._isExisting).map(f => ({
        file: f._file,
        name: f.name,
        description: f.description || ''
      })),
      existing: this._attachments.filter(f => f._isExisting).map(f => ({
        id: f.id,
        description: f.description || ''
      })),
      deleted: this._deletedAttachmentIds
    };
  }

  /**
   * Зураг optimize хийх эсэх
   * @returns {boolean}
   */
  getOptimizeImages() {
    return this._optimizeImages;
  }

  /**
   * AI товчнуудын тохиргоо
   * - shineUrl тохируулаагүй бол товчнуудыг дарахад тайлбар dialog гарна
   * - shineUrl тохируулсан бол хэвийн ажиллана (backend-ээс INDO_OPENAI_API_KEY шалгана)
   * @private
   */
  _toggleShineButton() {
    /* AI товчнууд үргэлж харагдана, shineUrl байхгүй бол dialog тайлбарлана */
  }

  /**
   * Readonly горим идэвхжүүлэх
   * - Editor засварлах боломжгүй болно
   * - Toolbar дээр зөвхөн Print, Source, Fullscreen товчнууд харагдана
   * @private
   */
  _applyReadonly() {
    if (!this.opts.readonly) return;

    /* Editor-ийг унших горимд оруулах */
    this.editor.contentEditable = 'false';
    this.editor.style.cursor = 'default';

    /* Source textarea-г readonly болгох */
    if (this.source) {
      this.source.readOnly = true;
    }

    /* Readonly mode дээр харагдах товчнуудын жагсаалт */
    const allowedActions = ['print', 'source', 'fullscreen'];

    /* Toolbar дахь бүх элементүүдийг шүүх */
    const groups = this.toolbar.querySelectorAll('.moedit-group');
    const separators = this.toolbar.querySelectorAll('.moedit-sep');

    groups.forEach(group => {
      /* Group дотор зөвшөөрөгдсөн товч байгаа эсэхийг шалгах */
      const buttons = group.querySelectorAll('button[data-action]');
      let hasAllowedButton = false;

      buttons.forEach(btn => {
        const action = btn.dataset.action;
        if (allowedActions.includes(action)) {
          hasAllowedButton = true;
        } else {
          btn.style.display = 'none';
        }
      });

      /* Select, color input зэргийг нуух */
      group.querySelectorAll('select, input[type="color"]').forEach(el => {
        el.style.display = 'none';
      });

      /* Хэрэв group-д зөвшөөрөгдсөн товч байхгүй бол бүхлээр нуух */
      if (!hasAllowedButton) {
        group.style.display = 'none';
      }
    });

    /* Separator-үүдийг нуух (сүүлийн group-ийн өмнөхөөс бусад) */
    separators.forEach(sep => {
      sep.style.display = 'none';
    });

    /* Root element-д readonly class нэмэх */
    this.root.classList.add('moedit-readonly');
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
    if (this._boundHandlers.documentKeydown) {
      document.removeEventListener('keydown', this._boundHandlers.documentKeydown);
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

  /**
   * HTML-г cursor-ийн байрлалд оруулах эсвэл төгсгөлд нэмэх
   * @param {string} html - Оруулах HTML
   * @returns {void}
   * @example
   * editor.insertHtml('<img src="image.jpg" alt="Image">');
   */
  insertHtml(html) {
    if (!html) return;

    if (this.isSource) {
      /* Source mode: textarea-д cursor байрлалд оруулах */
      const start = this.source.selectionStart;
      const end = this.source.selectionEnd;
      const value = this.source.value;
      this.source.value = value.slice(0, start) + html + value.slice(end);
      this.source.selectionStart = this.source.selectionEnd = start + html.length;
      this.editor.innerHTML = this.source.value;
    } else {
      /* Editor mode */
      this.editor.focus();
      const sel = window.getSelection();

      /* Block элемент эсэхийг шалгах */
      const isBlockContent = /^<(video|audio|iframe|figure|table|div|blockquote)/i.test(html.trim());

      /* Editor хоосон эсвэл зөвхөн <br> эсвэл хоосон <p> байгаа эсэх */
      const isEmpty = !this.editor.textContent.trim() &&
        !this.editor.querySelector('img, video, audio, iframe, table');

      /* Selection editor дотор байгаа эсэхийг шалгах */
      let isInsideEditor = false;
      if (sel.rangeCount > 0) {
        const range = sel.getRangeAt(0);
        isInsideEditor = this.editor.contains(range.commonAncestorContainer);
      }

      if (isInsideEditor && !isEmpty) {
        /* Selection editor дотор байвал тэнд оруулах */
        const range = sel.getRangeAt(0);
        range.deleteContents();
        const temp = document.createElement('div');
        temp.innerHTML = html;
        const frag = document.createDocumentFragment();
        let lastNode;
        while (temp.firstChild) {
          lastNode = frag.appendChild(temp.firstChild);
        }
        range.insertNode(frag);
        if (lastNode) {
          range.setStartAfter(lastNode);
          range.collapse(true);
          sel.removeAllRanges();
          sel.addRange(range);
        }
      } else {
        /* Editor хоосон эсвэл selection гадна байвал */
        if (isBlockContent) {
          /* Block элемент бол өмнө/хойно paragraph нэмж cursor байрлах зай гаргах */
          this.editor.innerHTML = '<p><br></p>' + html + '<p><br></p>';
        } else {
          this.editor.innerHTML = html;
        }
      }

      /* Cursor-г зөв байрлалд тавих */
      const lastP = this.editor.querySelector('p:last-of-type');
      if (lastP) {
        const range = document.createRange();
        range.selectNodeContents(lastP);
        range.collapse(true);
        sel.removeAllRanges();
        sel.addRange(range);
      }

      this.source.value = this.editor.innerHTML;
    }
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
    /* Language detection */
    const isMn = document.documentElement.lang === 'mn';

    /* Placeholder авах */
    const defaultPlaceholder = isMn ? 'Агуулгаа энд бичнэ үү...' : 'Write your content here...';
    const placeholder = opts.placeholder || textarea.placeholder || defaultPlaceholder;

    /* Анхны утга авах */
    const initialContent = textarea.value || '';

    /* Wrapper үүсгэх */
    const wrapper = document.createElement('div');
    wrapper.className = opts.toolbarPosition === 'right' ? 'moedit moedit-toolbar-right' : 'moedit';

    /* ID хуулах (хэрэв байвал) */
    if (textarea.id) {
      wrapper.id = textarea.id + '_wrapper';
    }

    /* Header image preview (headerImage option идэвхтэй үед л харагдана) */
    const headerImageHtml = opts.headerImage ? `
      <div class="moedit-header-image" style="display:none;">
        <img class="moedit-header-image-preview" src="" alt="">
        <div class="moedit-header-image-overlay">
          <button type="button" class="moedit-header-image-change" title="${isMn ? 'Солих' : 'Change'}"><i class="mi-pencil"></i></button>
          <button type="button" class="moedit-header-image-remove" title="${isMn ? 'Устгах' : 'Remove'}"><i class="mi-trash"></i></button>
        </div>
      </div>
    ` : '';

    /* Right toolbar бол content wrapper нэмэх */
    const isRightToolbar = opts.toolbarPosition === 'right';

    const attachHtml = `<div class="moedit-attachments" style="display:none;"><table><thead><tr><th>${isMn ? 'Файл' : 'File'}</th><th>${isMn ? 'Шинж' : 'Properties'}</th><th>${isMn ? 'Тайлбар' : 'Description'}</th><th>${isMn ? 'Огноо' : 'Date'}</th></tr></thead><tbody></tbody></table></div>`;

    wrapper.innerHTML = isRightToolbar ? `
      <div class="moedit-content">
        ${headerImageHtml}
        <div class="moedit-body">
          <div class="moedit-editor" contenteditable="true" data-placeholder="${this._escapeAttr(placeholder)}">${initialContent}</div>
          <textarea class="moedit-source">${this._escapeHtml(initialContent)}</textarea>
        </div>
        ${attachHtml}
      </div>
    ` : `
      ${headerImageHtml}
      <div class="moedit-body">
        <div class="moedit-editor" contenteditable="true" data-placeholder="${this._escapeAttr(placeholder)}">${initialContent}</div>
        <textarea class="moedit-source">${this._escapeHtml(initialContent)}</textarea>
      </div>
      ${attachHtml}
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
   * @param {boolean} isMn - Mongolian language flag
   */
  _createToolbar(isMn) {
    const toolbar = document.createElement('div');
    toolbar.className = 'moedit-toolbar';
    toolbar.innerHTML = `
      <div class="moedit-group">
        <label class="moedit-optimize" title="${isMn ? 'Идэвхжүүлсэн үед upload хийгдэж буй зургийг автоматаар шахаж, хэмжээг нь багасгана. Толгой зураг, контент дотор оруулсан зураг, хавсаргасан зураг бүгдэд хамаарна. Зургийн чанар алдагдахгүйгээр файлын хэмжээг 50-80% хүртэл бууруулна.' : 'When enabled, uploaded images are automatically compressed and resized. Applies to header image, content images, and attached images. Reduces file size by 50-80% without noticeable quality loss.'}">
          <input type="checkbox" checked> ${isMn ? 'Зураг optimize хийх' : 'Optimize images'}
        </label>
      </div>
      <div class="moedit-sep"></div>
      <div class="moedit-group moedit-group-header-image" style="display:none;">
        <button type="button" class="moedit-btn mo-primary" data-action="headerImage" title="${isMn ? 'Толгой зураг' : 'Header Image'}"><i class="mi-photo"></i></button>
      </div>
      <div class="moedit-sep moedit-sep-header-image" style="display:none;"></div>
      <div class="moedit-group">
        <button type="button" class="moedit-btn mo-danger" data-action="image" title="${isMn ? 'Зураг оруулах' : 'Insert Image'}"><i class="mi-image"></i></button>
        <button type="button" class="moedit-btn mo-info" data-action="video" title="${isMn ? 'Видео оруулах' : 'Insert Video'}"><i class="mi-camera-video"></i></button>
        <button type="button" class="moedit-btn mo-success" data-action="audio" title="${isMn ? 'Аудио оруулах' : 'Insert Audio'}"><i class="mi-music-note-beamed"></i></button>
        <button type="button" class="moedit-btn mo-warning" data-action="attachment" title="${isMn ? 'Файл хавсаргах' : 'Attach File'}"><i class="mi-paperclip"></i></button>
        <button type="button" class="moedit-btn" data-action="table" title="${isMn ? 'Хүснэгт оруулах' : 'Insert Table'}"><i class="mi-table"></i></button>
        <button type="button" class="moedit-btn" data-action="accordion" title="${isMn ? 'Accordion оруулах' : 'Insert Accordion'}"><i class="mi-chevron-bar-expand"></i></button>
        <button type="button" class="moedit-btn" data-action="insertLink" title="${isMn ? 'Холбоос / Имэйл оруулах' : 'Insert Link / Email'}"><i class="mi-link-45deg"></i></button>
        <button type="button" class="moedit-btn" data-action="hr" title="${isMn ? 'Хэвтээ зураас оруулах' : 'Insert Horizontal Rule'}"><i class="mi-dash-lg"></i></button>
        <button type="button" class="moedit-btn" data-action="youtube" title="${isMn ? 'YouTube видео оруулах' : 'Insert YouTube Video'}"><i class="mi-youtube"></i></button>
        <button type="button" class="moedit-btn" data-action="facebook" title="${isMn ? 'Facebook видео оруулах' : 'Insert Facebook Video'}"><i class="mi-facebook"></i></button>
        <button type="button" class="moedit-btn" data-action="twitter" title="${isMn ? 'Twitter/X пост оруулах' : 'Insert Twitter/X Post'}"><i class="mi-twitter-x"></i></button>
        <button type="button" class="moedit-btn" data-action="map" title="${isMn ? 'Google Maps оруулах' : 'Insert Google Maps'}"><i class="mi-geo-alt"></i></button>
        <button type="button" class="moedit-btn mo-info" data-action="ocr" title="${isMn ? 'AI OCR - Зургийг HTML болгох' : 'AI OCR - Convert Image to HTML'}"><i class="mi-file-text"></i></button>
        <button type="button" class="moedit-btn mo-danger" data-action="pdf" title="PDF → HTML"><i class="mi-file-earmark-pdf"></i></button>
        <button type="button" class="moedit-btn mo-warning" data-action="shine" title="${isMn ? 'AI Shine - Bootstrap 5 гоёжуулах' : 'AI Shine - Beautify with Bootstrap 5'}"><i class="mi-stars"></i></button>
      </div>
      <div class="moedit-sep"></div>
      <div class="moedit-group">
        <button type="button" class="moedit-btn mo-primary" data-action="fullscreen" title="${isMn ? 'Бүтэн дэлгэц' : 'Fullscreen'}"><i class="mi-arrows-fullscreen"></i></button>
        <button type="button" class="moedit-btn mo-success" data-action="source" title="${isMn ? 'Эх код' : 'Source Code'}"><i class="mi-code-slash"></i></button>
        <button type="button" class="moedit-btn" data-action="print" title="${isMn ? 'Хэвлэх' : 'Print'}"><i class="mi-printer"></i></button>
        <button type="button" class="moedit-btn mo-info" data-action="preview" title="${isMn ? 'Урьдчилан харах' : 'Preview'}"><i class="mi-eye"></i></button>
      </div>
      <div class="moedit-sep"></div>
      <div class="moedit-group">
        <button type="button" class="moedit-btn" data-action="bold" title="${isMn ? 'Тод (Ctrl+B)' : 'Bold (Ctrl+B)'}"><i class="mi-type-bold"></i></button>
        <button type="button" class="moedit-btn" data-action="italic" title="${isMn ? 'Налуу (Ctrl+I)' : 'Italic (Ctrl+I)'}"><i class="mi-type-italic"></i></button>
        <button type="button" class="moedit-btn" data-action="underline" title="${isMn ? 'Доогуур зураас (Ctrl+U)' : 'Underline (Ctrl+U)'}"><i class="mi-type-underline"></i></button>
        <button type="button" class="moedit-btn" data-action="strike" title="${isMn ? 'Дундуур зураас' : 'Strikethrough'}"><i class="mi-type-strikethrough"></i></button>
        <button type="button" class="moedit-btn" data-action="mark" title="${isMn ? 'Тодруулга (Highlight)' : 'Highlight (Mark)'}"><i class="mi-highlighter"></i></button>
        <button type="button" class="moedit-btn" data-action="subscript" title="${isMn ? 'Доод индекс' : 'Subscript'}"><i class="mi-type"></i><sub>x</sub></button>
        <button type="button" class="moedit-btn" data-action="superscript" title="${isMn ? 'Дээд индекс' : 'Superscript'}"><i class="mi-type"></i><sup>x</sup></button>
      </div>
      <div class="moedit-sep"></div>
      <div class="moedit-group moedit-group-selects">
        <select class="moedit-select" data-action="heading" title="${isMn ? 'Гарчиг' : 'Heading'}">
          <option value="p" selected>${isMn ? 'Параграф' : 'Paragraph'}</option>
          <option value="h1">${isMn ? 'H1 - Гарчиг' : 'H1 - Heading'}</option>
          <option value="h2">${isMn ? 'H2 - Дэд гарчиг' : 'H2 - Subheading'}</option>
          <option value="h3">H3</option>
          <option value="h4">H4</option>
          <option value="h5">H5</option>
          <option value="h6">H6</option>
          <option value="pre">${isMn ? 'Форматтай' : 'Preformatted'}</option>
          <option value="blockquote">${isMn ? 'Иш татах' : 'Quote'}</option>
        </select>
        <select class="moedit-select" data-action="fontSize" title="${isMn ? 'Үсгийн хэмжээ' : 'Font Size'}">
          <option value="3" selected>${isMn ? 'Хэвийн' : 'Default'}</option>
          <option value="1">${isMn ? '1 - Жижиг' : '1 - Small'}</option>
          <option value="2">2</option>
          <option value="3">${isMn ? '3 - Хэвийн' : '3 - Normal'}</option>
          <option value="4">4</option>
          <option value="5">${isMn ? '5 - Том' : '5 - Large'}</option>
          <option value="6">6</option>
          <option value="7">${isMn ? '7 - Хамгийн том' : '7 - Largest'}</option>
        </select>
        <input type="color" class="moedit-color" data-action="foreColor" title="${isMn ? 'Үсгийн өнгө' : 'Font Color'}" value="#000000">
        <div class="moedit-list-select" data-action="heading">
          <div class="moedit-list-label">${isMn ? 'Параграф' : 'Paragraph'}</div>
          <button type="button" class="moedit-btn is-active is-default" data-value="p" title="${isMn ? 'Параграф' : 'Paragraph'}">P</button>
          <button type="button" class="moedit-btn" data-value="h1" title="${isMn ? 'H1 - Гарчиг' : 'H1 - Heading'}">H1</button>
          <button type="button" class="moedit-btn" data-value="h2" title="${isMn ? 'H2 - Дэд гарчиг' : 'H2 - Subheading'}">H2</button>
          <button type="button" class="moedit-btn" data-value="h3" title="H3">H3</button>
          <button type="button" class="moedit-btn" data-value="h4" title="H4">H4</button>
          <button type="button" class="moedit-btn" data-value="h5" title="H5">H5</button>
          <button type="button" class="moedit-btn" data-value="h6" title="H6">H6</button>
          <button type="button" class="moedit-btn" data-value="pre" title="${isMn ? 'Форматтай' : 'Preformatted'}"><i class="mi-code"></i></button>
          <button type="button" class="moedit-btn" data-value="blockquote" title="${isMn ? 'Иш татах' : 'Quote'}"><i class="mi-quote"></i></button>
        </div>
        <div class="moedit-list-select" data-action="fontSize">
          <div class="moedit-list-label">${isMn ? 'Үсгийн хэмжээ' : 'Font Size'}</div>
          <button type="button" class="moedit-btn" data-value="1" title="${isMn ? '1 - Жижиг' : '1 - Small'}">1</button>
          <button type="button" class="moedit-btn" data-value="2" title="2">2</button>
          <button type="button" class="moedit-btn is-active is-default" data-value="3" title="${isMn ? '3 - Хэвийн' : '3 - Normal'}">3</button>
          <button type="button" class="moedit-btn" data-value="4" title="4">4</button>
          <button type="button" class="moedit-btn" data-value="5" title="${isMn ? '5 - Том' : '5 - Large'}">5</button>
          <button type="button" class="moedit-btn" data-value="6" title="6">6</button>
          <button type="button" class="moedit-btn" data-value="7" title="${isMn ? '7 - Хамгийн том' : '7 - Largest'}">7</button>
        </div>
      </div>
      <div class="moedit-sep"></div>
      <div class="moedit-group">
        <button type="button" class="moedit-btn" data-action="justifyLeft" title="${isMn ? 'Зүүн тийш' : 'Align Left'}"><i class="mi-text-left"></i></button>
        <button type="button" class="moedit-btn" data-action="justifyCenter" title="${isMn ? 'Голлуулах' : 'Align Center'}"><i class="mi-text-center"></i></button>
        <button type="button" class="moedit-btn" data-action="justifyRight" title="${isMn ? 'Баруун тийш' : 'Align Right'}"><i class="mi-text-right"></i></button>
        <button type="button" class="moedit-btn" data-action="justifyFull" title="${isMn ? 'Тэгшлэх' : 'Justify'}"><i class="mi-justify"></i></button>
        <button type="button" class="moedit-btn" data-action="outdent" title="${isMn ? 'Догол мөр хасах' : 'Outdent'}"><i class="mi-text-indent-right"></i></button>
        <button type="button" class="moedit-btn" data-action="indent" title="${isMn ? 'Догол мөр нэмэх' : 'Indent'}"><i class="mi-text-indent-left"></i></button>
      </div>
      <div class="moedit-sep"></div>
      <div class="moedit-group">
        <button type="button" class="moedit-btn" data-action="removeFormat" title="${isMn ? 'Формат арилгах' : 'Remove Format'}"><i class="mi-eraser"></i></button>
      </div>
      <div class="moedit-sep"></div>
      <div class="moedit-group">
        <button type="button" class="moedit-btn" data-action="ul" title="${isMn ? 'Тэмдэгттэй жагсаалт' : 'Unordered List'}"><i class="mi-list-ul"></i></button>
        <button type="button" class="moedit-btn" data-action="ol" title="${isMn ? 'Дугаартай жагсаалт' : 'Ordered List'}"><i class="mi-list-ol"></i></button>
      </div>
      <div class="moedit-sep"></div>
      <div class="moedit-group">
        <button type="button" class="moedit-btn" data-action="undo" title="${isMn ? 'Буцаах (Ctrl+Z)' : 'Undo (Ctrl+Z)'}"><i class="mi-arrow-counterclockwise"></i></button>
        <button type="button" class="moedit-btn" data-action="redo" title="${isMn ? 'Дахих (Ctrl+Y)' : 'Redo (Ctrl+Y)'}"><i class="mi-arrow-clockwise"></i></button>
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
    /* Document түвшний ESC handler - fullscreen mode-оос гарахад (readonly горимд ч ажиллана) */
    this._boundHandlers.documentKeydown = (e) => {
      if (e.key === 'Escape' && this.root.classList.contains('is-fullscreen')) {
        e.preventDefault();
        this.toggleFullscreen(false);
      }
    };
    document.addEventListener('keydown', this._boundHandlers.documentKeydown);

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

    /* Optimize checkbox */
    const optimizeCheckbox = this.toolbar.querySelector('.moedit-optimize input[type="checkbox"]');
    if (optimizeCheckbox) {
      optimizeCheckbox.addEventListener('change', () => { this._optimizeImages = optimizeCheckbox.checked; });
    }

    /* Toolbar keyboard navigation */
    this._setupToolbarKeyboardNav();

    const heading = this.toolbar.querySelector('select[data-action="heading"]');
    if (heading) {
      heading.addEventListener("change", e => {
        document.execCommand("formatBlock", false, e.target.value);
        this._emitChange();
        /* Select-ийн утгыг шинэчлэх (cursor байрлалаас хамааран) */
        this._syncHeadingState();
        /* List select sync хийх */
        this._syncListSelect('heading', e.target.value);
      });
    }

    const fontSize = this.toolbar.querySelector('select[data-action="fontSize"]');
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
        /* List select sync хийх */
        this._syncListSelect('fontSize', selectedValue);
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

    /* List select handlers (right toolbar mode) */
    const headingListSelect = this.toolbar.querySelector('.moedit-list-select[data-action="heading"]');
    if (headingListSelect) {
      headingListSelect.addEventListener('click', (e) => {
        const btn = e.target.closest('button[data-value]');
        if (!btn) return;
        const value = btn.dataset.value;
        document.execCommand("formatBlock", false, value);
        this._emitChange();
        /* Active state шинэчлэх */
        headingListSelect.querySelectorAll('.moedit-btn').forEach(b => b.classList.remove('is-active'));
        btn.classList.add('is-active');
        headingListSelect.querySelector('.moedit-list-label').textContent = btn.title || value.toUpperCase();
        /* Dropdown-г sync хийх */
        if (heading) heading.value = value;
      });
    }

    const fontSizeListSelect = this.toolbar.querySelector('.moedit-list-select[data-action="fontSize"]');
    if (fontSizeListSelect) {
      fontSizeListSelect.addEventListener('click', (e) => {
        const btn = e.target.closest('button[data-value]');
        if (!btn) return;
        const value = btn.dataset.value;
        if (value === "3") {
          this._removeFontSize();
        } else {
          this.exec("fontSize", value);
        }
        /* Active state шинэчлэх */
        fontSizeListSelect.querySelectorAll('.moedit-btn').forEach(b => b.classList.remove('is-active'));
        btn.classList.add('is-active');
        fontSizeListSelect.querySelector('.moedit-list-label').textContent = btn.title || `Size ${value}`;
        /* Dropdown-г sync хийх */
        if (fontSize) fontSize.value = value;
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

      /* Backspace товч - хоосон мөр устгах (санамсаргүй TAB дарсан бол) */
      if (e.key === 'Backspace' && !e.ctrlKey && !e.metaKey) {
        const selection = window.getSelection();
        if (selection.rangeCount > 0) {
          const range = selection.getRangeAt(0);
          const cell = range.startContainer.nodeType === Node.TEXT_NODE
            ? range.startContainer.parentElement.closest('td, th')
            : range.startContainer.closest?.('td, th');

          if (cell) {
            const row = cell.closest('tr');
            const table = cell.closest('table');

            if (row && table) {
              const allRows = table.querySelectorAll('tr');

              /* Хамгийн багадаа 2 мөр байх ёстой (header + 1 row) */
              if (allRows.length > 1) {
                /* Мөр хоосон эсэхийг шалгах */
                const cells = row.querySelectorAll('td, th');
                const isRowEmpty = Array.from(cells).every(c => {
                  const text = c.textContent.trim();
                  return text === '' || text === '\u00A0'; /* &nbsp; */
                });

                /* Эхний cell дээр cursor байгаа эсэх */
                const firstCell = cells[0];
                const isInFirstCell = cell === firstCell;

                /* Cursor эхэнд байгаа эсэх */
                const isAtStart = range.startOffset === 0;

                if (isRowEmpty && isInFirstCell && isAtStart) {
                  e.preventDefault();

                  /* Өмнөх мөрийн сүүлийн cell-д cursor шилжүүлэх */
                  const rowIndex = Array.from(allRows).indexOf(row);
                  if (rowIndex > 0) {
                    const prevRow = allRows[rowIndex - 1];
                    const prevCells = prevRow.querySelectorAll('td, th');
                    const lastPrevCell = prevCells[prevCells.length - 1];

                    if (lastPrevCell) {
                      const newRange = document.createRange();
                      newRange.selectNodeContents(lastPrevCell);
                      newRange.collapse(false); /* Төгсгөлд */
                      selection.removeAllRanges();
                      selection.addRange(newRange);
                    }
                  }

                  /* Мөр устгах */
                  row.remove();
                  this._emitChange();
                  return;
                }
              }
            }
          }
        }
      }

      /* TAB товч - хүснэгтийн сүүлийн cell-д байвал шинэ мөр нэмэх */
      if (e.key === 'Tab' && !e.ctrlKey && !e.metaKey) {
        const selection = window.getSelection();
        if (selection.rangeCount > 0) {
          const range = selection.getRangeAt(0);
          const cell = range.startContainer.nodeType === Node.TEXT_NODE
            ? range.startContainer.parentElement.closest('td, th')
            : range.startContainer.closest?.('td, th');

          if (cell) {
            const row = cell.closest('tr');
            const table = cell.closest('table');

            if (row && table) {
              const allRows = table.querySelectorAll('tr');
              const lastRow = allRows[allRows.length - 1];
              const cellsInRow = row.querySelectorAll('td, th');
              const lastCell = cellsInRow[cellsInRow.length - 1];

              /* Сүүлийн мөрийн сүүлийн cell дээр TAB дарвал шинэ мөр нэмэх */
              if (row === lastRow && cell === lastCell && !e.shiftKey) {
                e.preventDefault();

                const newRow = document.createElement('tr');
                const colCount = cellsInRow.length;
                const isBootstrap = table.classList.contains('table');

                for (let i = 0; i < colCount; i++) {
                  const newCell = document.createElement('td');
                  if (!isBootstrap) {
                    /* Vanilla table - inline style хуулах */
                    const refCell = row.querySelector('td') || row.querySelector('th');
                    if (refCell && refCell.style.cssText) {
                      newCell.style.cssText = refCell.style.cssText;
                    }
                  }
                  newCell.innerHTML = '&nbsp;';
                  newRow.appendChild(newCell);
                }

                /* tbody-д нэмэх, байхгүй бол table-д шууд */
                const tbody = table.querySelector('tbody') || table;
                tbody.appendChild(newRow);

                /* Шинэ мөрийн эхний cell-д cursor шилжүүлэх */
                const firstNewCell = newRow.querySelector('td');
                if (firstNewCell) {
                  const newRange = document.createRange();
                  newRange.selectNodeContents(firstNewCell);
                  newRange.collapse(true);
                  selection.removeAllRanges();
                  selection.addRange(newRange);
                }

                this._emitChange();
                return;
              }
            }
          }
        }
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
      if (isInsideEditor) {
        if (typeof cfg.toggle === 'string') {
          /* Custom tag toggle (mark гэх мэт) - ancestor element шалгах */
          let node = selection.anchorNode;
          if (node && node.nodeType === Node.TEXT_NODE) node = node.parentNode;
          while (node && node !== this.editor) {
            if (node.nodeName.toLowerCase() === cfg.toggle) { on = true; break; }
            node = node.parentNode;
          }
        } else {
          /* Зөвхөн editor дотор selection байвал queryCommandState ашиглах */
          try { on = document.queryCommandState(cfg.cmd); } catch {}
        }
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
    /* List select-ийг бас sync хийх */
    this._syncListSelect('fontSize', fontSizeSelect.value);
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
    /* List select-ийг бас sync хийх */
    this._syncListSelect('heading', headingSelect.value);
  }

  /**
   * List select-ийн active state шинэчлэх (right toolbar mode)
   * @param {string} action - heading эсвэл fontSize
   * @param {string} value - сонгосон утга
   * @private
   */
  _syncListSelect(action, value) {
    const listSelect = this.toolbar.querySelector(`.moedit-list-select[data-action="${action}"]`);
    if (!listSelect) return;

    /* Бүх товчны active state арилгах */
    listSelect.querySelectorAll('.moedit-btn').forEach(btn => btn.classList.remove('is-active'));

    /* Тохирох товч олж active болгох */
    const activeBtn = listSelect.querySelector(`button[data-value="${value}"]`);
    if (activeBtn) {
      activeBtn.classList.add('is-active');
      const label = listSelect.querySelector('.moedit-list-label');
      if (label) {
        label.textContent = activeBtn.title || value.toUpperCase();
      }
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
    this.toolbar.setAttribute('aria-label', this._isMn ? 'Текст форматлах хэрэгслүүд' : 'Text formatting tools');

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

    /* Button-үүдэд aria-label нэмэх (title аль хэдийн _createToolbar-д тохируулагдсан) */
    const buttonLabels = this._isMn ? {
      'bold': 'Тод (Ctrl+B)',
      'italic': 'Налуу (Ctrl+I)',
      'underline': 'Доогуур зураас (Ctrl+U)',
      'strike': 'Дундуур зураас',
      'subscript': 'Доод индекс',
      'superscript': 'Дээд индекс',
      'justifyLeft': 'Зүүн тийш',
      'justifyCenter': 'Голлуулах',
      'justifyRight': 'Баруун тийш',
      'justifyFull': 'Тэгшлэх',
      'removeFormat': 'Формат арилгах',
      'ul': 'Тэмдэгттэй жагсаалт',
      'ol': 'Дугаартай жагсаалт',
      'indent': 'Догол мөр нэмэх',
      'outdent': 'Догол мөр хасах',
      'image': 'Зураг оруулах',
      'table': 'Хүснэгт оруулах',
      'hr': 'Хэвтээ зураас оруулах',
      'youtube': 'YouTube видео оруулах',
      'facebook': 'Facebook видео оруулах',
      'insertLink': 'Холбоос / Имэйл оруулах',
      'link': 'Холбоос / Имэйл оруулах',
      'undo': 'Буцаах (Ctrl+Z)',
      'redo': 'Дахих (Ctrl+Y)',
      'print': 'Хэвлэх',
      'source': 'Эх код',
      'fullscreen': 'Бүтэн дэлгэц'
    } : {
      'bold': 'Bold (Ctrl+B)',
      'italic': 'Italic (Ctrl+I)',
      'underline': 'Underline (Ctrl+U)',
      'strike': 'Strikethrough',
      'subscript': 'Subscript',
      'superscript': 'Superscript',
      'justifyLeft': 'Align Left',
      'justifyCenter': 'Align Center',
      'justifyRight': 'Align Right',
      'justifyFull': 'Justify',
      'removeFormat': 'Remove Format',
      'ul': 'Unordered List',
      'ol': 'Ordered List',
      'indent': 'Indent',
      'outdent': 'Outdent',
      'image': 'Insert Image',
      'table': 'Insert Table',
      'hr': 'Insert Horizontal Rule',
      'youtube': 'Insert YouTube Video',
      'facebook': 'Insert Facebook Video',
      'insertLink': 'Insert Link / Email',
      'link': 'Insert Link / Email',
      'undo': 'Undo (Ctrl+Z)',
      'redo': 'Redo (Ctrl+Y)',
      'print': 'Print',
      'source': 'Source Code',
      'fullscreen': 'Fullscreen'
    };

    this.toolbar.querySelectorAll('button[data-action]').forEach(btn => {
      const action = btn.dataset.action;
      if (buttonLabels[action]) {
        btn.setAttribute('aria-label', buttonLabels[action]);
      }
    });
  }

  _emitChange() {
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
