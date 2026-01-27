/**
 * mofiles v3.0
 * ------------------------------------------------------------------
 * Файл удирдах хүснэгт - Local storage approach + motable
 *
 * Шинэ архитектур:
 * - Файл сонгоход browser-д хадгална (File object)
 * - Хүснэгтэд preview харуулна (motable ашиглана)
 * - category, keyword, description засварлах боломжтой
 * - Form submit үед бүх файлууд нэг дор илгээгдэнэ
 *
 * @example
 * const files = new mofiles('#news_files', {
 *     files: [...],  // Одоо байгаа файлууд (update/view үед)
 *     readonly: false
 * });
 *
 * // Form submit үед:
 * const data = files.getFilesData();
 */

class mofiles {
    /* Static: CDN URLs */
    static CDN = {
        motableJs: 'https://cdn.jsdelivr.net/gh/codesaur-php/Indoraptor/public_html/assets/js/motable.js',
        motableCss: 'https://cdn.jsdelivr.net/gh/codesaur-php/Indoraptor/public_html/assets/css/motable.css'
    };

    /* Static: CSS injected flag */
    static _cssInjected = false;

    /* Static: Loading promises */
    static _loadingPromises = {};

    /* Static: Media modal instance */
    static _mediaModal = null;

    /* Static: Confirm modal instance */
    static _confirmModal = null;

    /* Static: Edit modal instance */
    static _editModal = null;

    /**
     * Constructor
     */
    constructor(tableSelector, opts = {}) {
        this._isMn = document.documentElement.lang === 'mn';
        this._isDark = this._detectDarkMode();

        this.opts = {
            files: [],
            readonly: false,
            darkMode: 'auto',
            maxFileSize: 8 * 1024 * 1024,
            allowedExtensions: ['jpg', 'jpeg', 'jpe', 'png', 'gif', 'ico', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'pps', 'ppsx', 'odt', 'mp3', 'm4a', 'ogg', 'wav', 'mp4', 'm4v', 'mov', 'wmv', 'avi', 'mpg', 'ogv', '3gp', '3g2', 'txt', 'xml', 'json', 'zip', 'rar'],
            onEmbed: null,
            onChange: null,
            countBadge: null,
            labels: {
                selectFiles: this._isMn ? 'Файл сонгох' : 'Select files',
                deleteConfirm: this._isMn ? 'Та {title} файлыг устгахдаа итгэлтэй байна уу?' : 'Are you sure to delete {title}?',
                error: this._isMn ? 'Алдаа' : 'Error',
                success: this._isMn ? 'Амжилттай' : 'Success',
                cancel: this._isMn ? 'Болих' : 'Cancel',
                delete: this._isMn ? 'Устгах' : 'Delete',
                close: this._isMn ? 'Хаах' : 'Close',
                save: this._isMn ? 'Хадгалах' : 'Save',
                edit: this._isMn ? 'Засах' : 'Edit',
                headerFile: this._isMn ? 'Файл' : 'File',
                headerProperties: this._isMn ? 'Шинж чанар' : 'Properties',
                headerActions: this._isMn ? 'Үйлдэл' : 'Actions',
                noFiles: this._isMn ? 'Файл байхгүй' : 'No files',
                saveWarning: this._isMn
                    ? 'Хадгалах товч дарснаар файлын өөрчлөлтүүд серверт илгээгдэнэ'
                    : 'File changes will be sent to server when you click Save',
                fileTooLarge: this._isMn ? 'Файл хэт том байна' : 'File is too large',
                invalidType: this._isMn ? 'Зөвшөөрөгдөөгүй файлын төрөл' : 'Invalid file type',
                embedHint: this._isMn
                    ? 'Агуулгад зураг оруулахдаа засварлагчийн <b>Толгой зураг</b> эсвэл <b>Зураг оруулах</b> товч ашиглавал илүү үр дүнтэй.'
                    : 'For images in content, use the editor\'s <b>Header Image</b> or <b>Insert Image</b> buttons for better results.',
                category: this._isMn ? 'Ангилал' : 'Category',
                keyword: this._isMn ? 'Түлхүүр үг' : 'Keyword',
                description: this._isMn ? 'Тайлбар' : 'Description',
                editFile: this._isMn ? 'Файлын мэдээлэл засах' : 'Edit file info',
                newFile: this._isMn ? 'Шинэ' : 'New'
            },
            ...opts
        };

        this._tableSelector = tableSelector;
        this._newFiles = [];
        this._existingFiles = [];
        this._deletedIds = [];
        this.table = null;

        this._injectCSS();
        this._init();
    }

    /**
     * Dark mode detection
     */
    _detectDarkMode() {
        if (this.opts?.darkMode === true) return true;
        if (this.opts?.darkMode === false) return false;
        if (document.documentElement.classList.contains('dark') ||
            document.body.classList.contains('dark') ||
            document.documentElement.getAttribute('data-bs-theme') === 'dark' ||
            document.documentElement.getAttribute('data-theme') === 'dark') {
            return true;
        }
        return window.matchMedia?.('(prefers-color-scheme: dark)').matches || false;
    }

    /**
     * CSS inject
     */
    _injectCSS() {
        if (mofiles._cssInjected) return;
        mofiles._cssInjected = true;

        const css = `
/*!
 * mofiles v3.0 styles
 */
.mofiles-wrapper {
    --mo-bg: #fff;
    --mo-text: #212529;
    --mo-border: #dee2e6;
    --mo-hover: rgba(0,0,0,.075);
    --mo-stripe: rgba(0,0,0,.05);
    --mo-primary: #0d6efd;
    --mo-success: #198754;
    --mo-warning: #ffc107;
    --mo-danger: #dc3545;
    --mo-info: #0dcaf0;
    --mo-secondary: #6c757d;
    font-family: system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;
    font-size: .875rem;
    color: var(--mo-text);
}
.mofiles-wrapper.mo-dark,
[data-bs-theme="dark"] .mofiles-wrapper {
    --mo-bg: #212529;
    --mo-text: #dee2e6;
    --mo-border: #495057;
    --mo-hover: rgba(255,255,255,.075);
    --mo-stripe: rgba(255,255,255,.05);
    --mo-primary: #6ea8fe;
    --mo-success: #75b798;
    --mo-warning: #ffda6a;
    --mo-danger: #ea868f;
    --mo-info: #6edff6;
    --mo-secondary: #a7acb1;
    --mo-dark: #424649;
}
.mofiles-wrapper.mo-dark .mo-btn-dark,
[data-bs-theme="dark"] .mofiles-wrapper .mo-btn-dark {
    background: var(--mo-dark);
    border-color: var(--mo-dark);
}

/* Buttons */
.mofiles-wrapper .mo-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .25rem;
    padding: .375rem .75rem;
    font-size: .875rem;
    font-weight: 400;
    line-height: 1.5;
    text-decoration: none;
    cursor: pointer;
    border: 1px solid transparent;
    border-radius: .375rem;
    transition: all .15s ease-in-out;
}
.mofiles-wrapper .mo-btn:disabled { opacity: .65; pointer-events: none; }
.mofiles-wrapper .mo-btn-sm { padding: .25rem .5rem; font-size: .8125rem; border-radius: .25rem; }
.mofiles-wrapper .mo-btn-xs { padding: .15rem .35rem; font-size: .75rem; border-radius: .2rem; }
.mofiles-wrapper .mo-btn-warning { background: var(--mo-warning); border-color: var(--mo-warning); color: #000; }
.mofiles-wrapper .mo-btn-warning:hover { filter: brightness(1.1); }
.mofiles-wrapper .mo-btn-secondary { background: var(--mo-secondary); border-color: var(--mo-secondary); color: #fff; }
.mofiles-wrapper .mo-btn-secondary:hover { filter: brightness(1.1); }
.mofiles-wrapper .mo-btn-danger { background: var(--mo-danger); border-color: var(--mo-danger); color: #fff; }
.mofiles-wrapper .mo-btn-danger:hover { filter: brightness(1.1); }
.mofiles-wrapper .mo-btn-primary { background: var(--mo-primary); border-color: var(--mo-primary); color: #fff; }
.mofiles-wrapper .mo-btn-primary:hover { filter: brightness(1.1); }
.mofiles-wrapper .mo-btn-info { background: var(--mo-info); border-color: var(--mo-info); color: #000; }
.mofiles-wrapper .mo-btn-info:hover { filter: brightness(1.1); }
.mofiles-wrapper .mo-btn-pink { background: #d63384; border-color: #d63384; color: #fff; }
.mofiles-wrapper .mo-btn-pink:hover { filter: brightness(1.1); }
.mofiles-wrapper .mo-btn-dark { background: #212529; border-color: #212529; color: #fff; }
.mofiles-wrapper .mo-btn-dark:hover { filter: brightness(1.3); }
.mofiles-wrapper .mo-shadow-sm { box-shadow: 0 .125rem .25rem rgba(0,0,0,.075); }
.mofiles-wrapper .mo-btn-group { display: inline-flex; gap: .25rem; }

/* Toolbar */
.mofiles-toolbar {
    margin-bottom: .75rem;
    display: flex;
    gap: .5rem;
    flex-wrap: wrap;
    align-items: center;
}
.mofiles-toolbar input[type="file"] { display: none; }

/* Warning */
.mofiles-warning {
    margin-bottom: .75rem;
    padding: .5rem .75rem;
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffc107;
    border-radius: .375rem;
    font-size: .8125rem;
    display: flex;
    align-items: center;
    gap: .5rem;
}
.mo-dark .mofiles-warning,
[data-bs-theme="dark"] .mofiles-warning {
    background: #332701;
    color: #ffda6a;
    border-color: #997404;
}

/* Embed hint */
.mofiles-embed-hint {
    margin-bottom: .75rem;
    padding: .5rem .75rem;
    background: #cff4fc;
    color: #055160;
    border: 1px solid #9eeaf9;
    border-radius: .375rem;
    font-size: .8125rem;
}
.mo-dark .mofiles-embed-hint,
[data-bs-theme="dark"] .mofiles-embed-hint {
    background: #032830;
    color: #6edff6;
    border-color: #087990;
}

/* Icons */
.mofiles-wrapper .mo-bi { display: inline-block; width: 1em; height: 1em; vertical-align: -.125em; fill: currentColor; }

/* New file badge */
.mofiles-new-badge {
    display: inline-block;
    padding: .15em .4em;
    font-size: .65em;
    font-weight: 600;
    background: var(--mo-success);
    color: #fff;
    border-radius: .25rem;
    margin-left: .35rem;
    vertical-align: middle;
}

/* File preview in table */
.mofiles-wrapper .mofiles-preview {
    max-height: 4rem;
    max-width: 6rem;
    cursor: pointer;
    border-radius: .25rem;
    object-fit: contain;
}
.mofiles-wrapper .mofiles-icon {
    font-size: 2rem;
    color: var(--mo-secondary);
}
.mofiles-wrapper .mofiles-filename {
    font-weight: 500;
    word-break: break-word;
}
.mofiles-wrapper .mofiles-meta {
    font-size: .75rem;
    color: var(--mo-secondary);
}
.mofiles-wrapper .mofiles-props {
    font-size: .8rem;
}
.mofiles-wrapper .mofiles-props span {
    display: inline-block;
    padding: .1rem .4rem;
    margin: .1rem;
    background: var(--mo-stripe);
    border-radius: .25rem;
}

/* Modal base */
.mo-modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 9999;
    background: rgba(0,0,0,.6);
    align-items: center;
    justify-content: center;
    padding: 1rem;
}
.mo-modal-overlay.mo-show { display: flex; }

/* Media modal */
.mo-media-modal .mo-modal-content {
    position: relative;
    max-width: 95vw;
    max-height: 95vh;
    background: #000;
    border-radius: .5rem;
    overflow: hidden;
}
.mo-media-modal .mo-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: .5rem .75rem;
    background: rgba(0,0,0,.85);
    gap: .5rem;
}
.mo-media-modal .mo-modal-caption {
    color: #fff;
    font-size: .875rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    flex: 1;
}
.mo-modal-close {
    width: 2rem;
    height: 2rem;
    background: rgba(255,255,255,.15);
    border: none;
    border-radius: 50%;
    color: #fff;
    font-size: 1.25rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}
.mo-modal-close:hover { background: rgba(255,255,255,.3); }
.mo-media-modal img,
.mo-media-modal video { max-width: 95vw; max-height: calc(95vh - 3rem); display: block; }

/* Confirm modal */
.mo-confirm-modal .mo-modal-content {
    background: var(--mo-bg);
    border-radius: .5rem;
    max-width: 24rem;
    width: 100%;
    box-shadow: 0 .5rem 1rem rgba(0,0,0,.3);
    overflow: hidden;
}
.mo-confirm-modal .mo-modal-body {
    padding: 1.5rem;
    text-align: center;
    color: var(--mo-text);
}
.mo-confirm-modal .mo-modal-footer {
    padding: 1rem;
    display: flex;
    gap: .5rem;
    justify-content: center;
    border-top: 1px solid var(--mo-border);
}

/* Edit modal */
.mo-edit-modal .mo-modal-content {
    background: var(--mo-bg);
    border-radius: .5rem;
    max-width: 28rem;
    width: 100%;
    box-shadow: 0 .5rem 1rem rgba(0,0,0,.3);
    overflow: hidden;
}
.mo-edit-modal .mo-modal-header {
    padding: .75rem 1rem;
    border-bottom: 1px solid var(--mo-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--mo-stripe);
}
.mo-edit-modal .mo-modal-title {
    font-weight: 600;
    color: var(--mo-text);
}
.mo-edit-modal .mo-modal-body {
    padding: 1rem;
}
.mo-edit-modal .mo-modal-footer {
    padding: .75rem 1rem;
    display: flex;
    gap: .5rem;
    justify-content: flex-end;
    border-top: 1px solid var(--mo-border);
}
.mo-edit-modal .mo-form-group {
    margin-bottom: .75rem;
}
.mo-edit-modal .mo-form-label {
    display: block;
    margin-bottom: .25rem;
    font-weight: 500;
    font-size: .8125rem;
    color: var(--mo-text);
}
.mo-edit-modal .mo-form-control {
    display: block;
    width: 100%;
    padding: .375rem .75rem;
    font-size: .875rem;
    line-height: 1.5;
    color: var(--mo-text);
    background-color: var(--mo-bg);
    border: 1px solid var(--mo-border);
    border-radius: .375rem;
    transition: border-color .15s ease-in-out;
}
.mo-edit-modal .mo-form-control:focus {
    outline: none;
    border-color: var(--mo-primary);
    box-shadow: 0 0 0 .2rem rgba(13,110,253,.25);
}
.mo-edit-modal textarea.mo-form-control {
    min-height: 4rem;
    resize: vertical;
}

/* Toast */
.mo-toast {
    position: fixed;
    top: 1rem;
    right: 1rem;
    z-index: 10001;
    min-width: 16rem;
    max-width: 24rem;
    background: var(--mo-bg);
    border-radius: .5rem;
    box-shadow: 0 .5rem 1rem rgba(0,0,0,.15);
    animation: mo-toast-in .3s ease-out;
}
@keyframes mo-toast-in {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}
.mo-toast-header {
    padding: .5rem 1rem;
    display: flex;
    align-items: center;
    gap: .5rem;
    font-weight: 600;
    font-size: .875rem;
    border-radius: .5rem .5rem 0 0;
}
.mo-toast-header.mo-success { background: var(--mo-success); color: #fff; }
.mo-toast-header.mo-danger { background: var(--mo-danger); color: #fff; }
.mo-toast-body { padding: .75rem 1rem; font-size: .875rem; color: var(--mo-text); }
.mo-toast-close {
    margin-left: auto;
    background: none;
    border: none;
    color: inherit;
    font-size: 1.25rem;
    cursor: pointer;
}
`;
        const style = document.createElement('style');
        style.id = 'mofiles-css';
        style.textContent = css;
        document.head.appendChild(style);
    }

    /**
     * Initialize
     */
    async _init() {
        const tableEl = typeof this._tableSelector === 'string'
            ? document.querySelector(this._tableSelector)
            : this._tableSelector;

        if (!tableEl) {
            console.error('mofiles: Table element not found');
            return;
        }

        this._tableEl = tableEl;

        // Wrapper үүсгэх
        this._wrapper = document.createElement('div');
        this._wrapper.className = 'mofiles-wrapper' + (this._isDark ? ' mo-dark' : '');
        tableEl.parentNode.insertBefore(this._wrapper, tableEl);
        this._wrapper.appendChild(tableEl);

        // motable ачаалах
        await this._loadMotable();

        // UI бэлдэх
        if (!this.opts.readonly) {
            this._buildToolbar();
        }

        this._createModals();
        this._createCountBadge();
        this._setupDarkModeListener();

        // motable эхлүүлэх
        this._initTable();

        // Одоо байгаа файлуудыг ачаалах
        if (this.opts.files && this.opts.files.length > 0) {
            this._existingFiles = [...this.opts.files];
            this._existingFiles.forEach(file => this._appendRow(file, false));
        }

        this._updateCount();
    }

    /**
     * Load motable
     */
    async _loadMotable() {
        if (window.motable) return;

        // CSS
        if (!document.querySelector('link[href*="motable"]')) {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = mofiles.CDN.motableCss;
            document.head.appendChild(link);
        }

        // JS
        if (!mofiles._loadingPromises.motable) {
            mofiles._loadingPromises.motable = new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = mofiles.CDN.motableJs;
                script.onload = resolve;
                script.onerror = reject;
                document.head.appendChild(script);
            });
        }
        await mofiles._loadingPromises.motable;
    }

    /**
     * Init motable
     */
    _initTable() {
        // Table structure
        this._tableEl.innerHTML = '';

        const thead = document.createElement('thead');
        const tr = document.createElement('tr');

        // Анхны байдал: File, Properties, Description, Category, Keyword, Date
        const headers = [
            { text: this.opts.labels.headerFile, style: 'min-width:200px' },
            { text: this.opts.labels.headerProperties, style: 'min-width:100px' },
            { text: this.opts.labels.description, style: 'min-width:120px' },
            { text: this.opts.labels.category, style: 'width:100px' },
            { text: this.opts.labels.keyword, style: 'width:100px' },
            { text: this._isMn ? 'Огноо' : 'Date', style: 'width:100px' }
        ];

        headers.forEach(h => {
            const th = document.createElement('th');
            th.textContent = h.text;
            if (h.style) th.style.cssText = h.style;
            tr.appendChild(th);
        });
        thead.appendChild(tr);
        this._tableEl.appendChild(thead);

        const tbody = document.createElement('tbody');
        tbody.setAttribute('data-empty', this.opts.labels.noFiles);
        this._tableEl.appendChild(tbody);
        this._tbody = tbody;

        // motable эхлүүлэх
        if (window.motable) {
            this.table = new motable(this._tableEl, {
                sortable: !this.opts.readonly,
                darkMode: this._isDark ? true : 'auto'
            });
        }
    }

    /**
     * Build toolbar
     */
    _buildToolbar() {
        const toolbar = document.createElement('div');
        toolbar.className = 'mofiles-toolbar';

        // File input
        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.multiple = true;
        fileInput.id = 'mofiles-input-' + Math.random().toString(36).substr(2, 9);
        fileInput.accept = this.opts.allowedExtensions.map(e => '.' + e).join(',');
        this._fileInput = fileInput;
        fileInput.onchange = (e) => this._handleFileSelect(e);

        // Select button
        const selectBtn = document.createElement('button');
        selectBtn.type = 'button';
        selectBtn.className = 'mo-btn mo-btn-sm mo-btn-warning mo-shadow-sm';
        selectBtn.innerHTML = this._icon('upload') + ' ' + this.opts.labels.selectFiles;
        selectBtn.onclick = () => fileInput.click();

        toolbar.appendChild(fileInput);
        toolbar.appendChild(selectBtn);
        this._wrapper.insertBefore(toolbar, this._tableEl);

        // Warning
        const warning = document.createElement('div');
        warning.className = 'mofiles-warning';
        warning.innerHTML = this._icon('exclamation-triangle') + ' ' + this.opts.labels.saveWarning;
        this._wrapper.insertBefore(warning, this._tableEl);

        // Embed hint
        if (typeof this.opts.onEmbed === 'function') {
            const hint = document.createElement('div');
            hint.className = 'mofiles-embed-hint';
            hint.innerHTML = this._icon('info-circle') + ' ' + this.opts.labels.embedHint;
            this._wrapper.insertBefore(hint, warning);
        }
    }

    /**
     * Handle file select
     */
    _handleFileSelect(e) {
        const files = e.target.files;
        if (!files || files.length === 0) return;

        for (let i = 0; i < files.length; i++) {
            const file = files[i];

            // Validate
            if (file.size > this.opts.maxFileSize) {
                this._notify('danger', this.opts.labels.error,
                    `${file.name}: ${this.opts.labels.fileTooLarge}`);
                continue;
            }

            const ext = file.name.split('.').pop().toLowerCase();
            if (!this.opts.allowedExtensions.includes(ext)) {
                this._notify('danger', this.opts.labels.error,
                    `${file.name}: ${this.opts.labels.invalidType}`);
                continue;
            }

            const fileData = {
                _localId: 'new_' + Date.now() + '_' + i,
                _file: file,
                name: file.name,
                size: file.size,
                type: this._getFileType(file.type),
                mime_content_type: file.type,
                category: '',
                keyword: '',
                description: ''
            };

            this._newFiles.push(fileData);
            this._appendRow(fileData, true);
        }

        e.target.value = '';
        this._updateCount();
        this.opts.onChange?.('add');
    }

    /**
     * Append row
     */
    _appendRow(file, isNew) {
        const row = document.createElement('tr');
        const id = file.id || file._localId;
        row.dataset.id = id;
        row.style.fontSize = '.875rem';

        const name = file.name || this._basename(file.path || '');

        // === File Cell (preview + name + action buttons) ===
        const fileCell = document.createElement('td');

        // Preview link/container
        const previewLink = document.createElement('a');
        previewLink.href = file.path || '#';
        previewLink.style.cssText = 'display:block;cursor:pointer';

        // Preview element
        let previewEl;
        const mediaSrc = isNew && file._file ? URL.createObjectURL(file._file) : file.path;

        if (file.type === 'image' && mediaSrc) {
            previewEl = document.createElement('img');
            previewEl.src = mediaSrc;
            previewEl.style.cssText = 'max-height:7.5rem;max-width:20rem;height:100%';
            previewLink.onclick = (e) => { e.preventDefault(); this._showMedia('image', mediaSrc, name); };
        } else if (file.type === 'video' && mediaSrc) {
            previewEl = document.createElement('video');
            previewEl.src = mediaSrc;
            previewEl.style.cssText = 'max-height:15rem;height:100%;max-width:20rem;width:100%';
            previewEl.controls = true;
            previewEl.muted = true;
            previewEl.preload = 'metadata';
            previewLink.onclick = (e) => { e.preventDefault(); this._showMedia('video', mediaSrc, name); };
        } else if (file.type === 'audio' && mediaSrc) {
            previewEl = document.createElement('audio');
            previewEl.src = mediaSrc;
            previewEl.controls = true;
            previewEl.preload = 'metadata';
            previewEl.style.cssText = 'max-width:20rem;width:100%';
            previewLink.onclick = (e) => e.preventDefault();
        } else {
            previewEl = document.createElement('span');
            previewEl.innerHTML = this._icon(this._getFileIcon(file.mime_content_type));
            previewEl.style.fontSize = '2rem';
        }
        previewLink.appendChild(previewEl);

        // Filename
        const filenameSpan = document.createElement('span');
        filenameSpan.style.display = 'block';
        filenameSpan.textContent = name;
        if (isNew) {
            filenameSpan.innerHTML += `<span class="mofiles-new-badge">${this.opts.labels.newFile}</span>`;
        }
        previewLink.appendChild(filenameSpan);

        fileCell.appendChild(previewLink);

        // Action buttons (анхны байдал: file cell дотор)
        if (!this.opts.readonly) {
            const actionsDiv = document.createElement('div');
            actionsDiv.style.cssText = 'margin-top:.5rem';

            // Edit button (info color, link icon like original)
            const editBtn = document.createElement('button');
            editBtn.type = 'button';
            editBtn.className = 'mo-btn mo-btn-sm mo-btn-info mo-shadow-sm';
            editBtn.innerHTML = this._icon('pencil');
            editBtn.title = this.opts.labels.edit;
            editBtn.style.marginRight = '.25rem';
            editBtn.onclick = () => this._showEditModal(file, isNew, row);
            actionsDiv.appendChild(editBtn);

            // Embed button (dark color, code icon like original - зураг бөгөөд server дээр байгаа бол)
            if (typeof this.opts.onEmbed === 'function' && file.type === 'image' && !isNew && file.path) {
                const embedBtn = document.createElement('button');
                embedBtn.type = 'button';
                embedBtn.className = 'mo-btn mo-btn-sm mo-btn-dark mo-shadow-sm';
                embedBtn.innerHTML = this._icon('code');
                embedBtn.title = this._isMn ? 'Агуулгад нэмэх' : 'Embed';
                embedBtn.style.marginRight = '.25rem';
                embedBtn.onclick = () => {
                    const html = `<img src="${file.path}" alt="${this._escape(name)}" style="max-width:100%;height:auto;">`;
                    this.opts.onEmbed(html, file);
                    embedBtn.classList.replace('mo-btn-dark', 'mo-btn-secondary');
                };
                actionsDiv.appendChild(embedBtn);
            }

            // Delete button (danger color)
            const deleteBtn = document.createElement('button');
            deleteBtn.type = 'button';
            deleteBtn.className = 'mo-btn mo-btn-sm mo-btn-danger mo-shadow-sm';
            deleteBtn.innerHTML = this._icon('trash');
            deleteBtn.title = this.opts.labels.delete;
            deleteBtn.onclick = () => this._confirmDelete(file, row, isNew);
            actionsDiv.appendChild(deleteBtn);

            fileCell.appendChild(actionsDiv);
        }
        row.appendChild(fileCell);

        // === Properties Cell (MIME type + size) ===
        const propsCell = document.createElement('td');
        propsCell.innerHTML = `
            <p style="max-width:11.25rem;word-wrap:break-all">
                <u>${file.mime_content_type || ''}</u>
            </p>
            ${this._formatSize(file.size)}
        `;
        row.appendChild(propsCell);

        // === Description Cell ===
        const descCell = document.createElement('td');
        descCell.className = 'mofiles-desc-cell';
        descCell.textContent = file.description || '';
        row.appendChild(descCell);

        // === Category Cell ===
        const catCell = document.createElement('td');
        catCell.className = 'mofiles-cat-cell';
        catCell.textContent = file.category || '';
        row.appendChild(catCell);

        // === Keyword Cell ===
        const kwCell = document.createElement('td');
        kwCell.className = 'mofiles-kw-cell';
        kwCell.textContent = file.keyword || '';
        row.appendChild(kwCell);

        // === Date Cell ===
        const dateCell = document.createElement('td');
        dateCell.textContent = file.created_at || (isNew ? (this._isMn ? 'Шинэ' : 'New') : '');
        row.appendChild(dateCell);

        this._tbody.appendChild(row);
    }

    /**
     * Update row cells after edit
     */
    _updateRowCells(row, file) {
        // Description cell (3rd column, index 2)
        const descCell = row.querySelector('.mofiles-desc-cell');
        if (descCell) descCell.textContent = file.description || '';

        // Category cell (4th column, index 3)
        const catCell = row.querySelector('.mofiles-cat-cell');
        if (catCell) catCell.textContent = file.category || '';

        // Keyword cell (5th column, index 4)
        const kwCell = row.querySelector('.mofiles-kw-cell');
        if (kwCell) kwCell.textContent = file.keyword || '';
    }

    /**
     * Show edit modal
     */
    _showEditModal(file, isNew, row) {
        const modal = mofiles._editModal;
        if (!modal) return;

        modal.querySelector('.mo-modal-title').textContent = this.opts.labels.editFile;
        modal.querySelector('#mo-edit-category').value = file.category || '';
        modal.querySelector('#mo-edit-keyword').value = file.keyword || '';
        modal.querySelector('#mo-edit-description').value = file.description || '';

        modal.querySelector('.mo-edit-save').onclick = () => {
            file.category = modal.querySelector('#mo-edit-category').value.trim();
            file.keyword = modal.querySelector('#mo-edit-keyword').value.trim();
            file.description = modal.querySelector('#mo-edit-description').value.trim();

            this._updateRowCells(row, file);
            this._closeModal(modal);
            this.opts.onChange?.('edit');
        };

        modal.classList.add('mo-show');
        document.body.style.overflow = 'hidden';
        modal.querySelector('#mo-edit-category').focus();
    }

    /**
     * Confirm delete
     */
    _confirmDelete(file, row, isNew) {
        const name = file.name || this._basename(file.path || '');
        const modal = mofiles._confirmModal;
        if (!modal) return;

        modal.querySelector('.mo-modal-body').textContent =
            this.opts.labels.deleteConfirm.replace('{title}', name);

        modal.querySelector('.mo-confirm-ok').onclick = () => {
            if (isNew) {
                const idx = this._newFiles.findIndex(f => f._localId === file._localId);
                if (idx > -1) this._newFiles.splice(idx, 1);
            } else {
                if (file.id) this._deletedIds.push(file.id);
                const idx = this._existingFiles.findIndex(f => f.id === file.id);
                if (idx > -1) this._existingFiles.splice(idx, 1);
            }
            row.remove();
            this._updateCount();
            this._closeModal(modal);
            this.opts.onChange?.('delete');
        };

        modal.classList.add('mo-show');
        document.body.style.overflow = 'hidden';
    }

    // ==================== Public Methods ====================

    getFilesData() {
        return {
            newFiles: this._newFiles.map(f => ({
                file: f._file,
                category: f.category,
                keyword: f.keyword,
                description: f.description
            })),
            existing: this._existingFiles.map(f => ({
                id: f.id,
                category: f.category,
                keyword: f.keyword,
                description: f.description
            })),
            deleted: this._deletedIds
        };
    }

    getNewFiles() {
        return this._newFiles.map(f => f._file);
    }

    getDeletedIds() {
        return this._deletedIds;
    }

    getCount() {
        return this._newFiles.length + this._existingFiles.length;
    }

    clear() {
        this._newFiles = [];
        this._existingFiles = [];
        this._deletedIds = [];
        this._tbody.innerHTML = '';
        this._updateCount();
    }

    // ==================== Helper Methods ====================

    _basename(url) {
        if (!url) return '';
        const name = url.split(/.*[\/|\\]/)[1] || url;
        try { return decodeURIComponent(name); } catch { return name; }
    }

    _escape(s) {
        if (!s) return '';
        const lookup = { '&': '&amp;', '"': '&quot;', "'": '&apos;', '<': '&lt;', '>': '&gt;' };
        return String(s).replace(/[&"'<>]/g, c => lookup[c]);
    }

    _formatSize(bytes) {
        if (!bytes) return '0b';
        const thresh = 1024;
        if (Math.abs(bytes) < thresh) return bytes + 'b';
        const units = ['kb', 'mb', 'gb'];
        let u = -1;
        do { bytes /= thresh; ++u; } while (Math.round(Math.abs(bytes) * 10) / 10 >= thresh && u < units.length - 1);
        return bytes.toFixed(1) + units[u];
    }

    _getFileType(mimeType) {
        if (!mimeType) return 'unknown';
        const type = mimeType.split('/')[0];
        return ['image', 'video', 'audio'].includes(type) ? type : 'document';
    }

    _getFileIcon(mimeType) {
        if (!mimeType) return 'file-earmark';
        if (mimeType.startsWith('video/')) return 'file-play';
        if (mimeType.startsWith('audio/')) return 'file-music';
        if (mimeType.includes('pdf')) return 'file-pdf';
        if (mimeType.includes('json')) return 'file-code';
        if (mimeType.includes('xml')) return 'file-code';
        if (mimeType.includes('text')) return 'file-text';
        if (mimeType.includes('zip') || mimeType.includes('rar')) return 'file-zip';
        if (mimeType.includes('word') || mimeType.includes('document')) return 'file-word';
        if (mimeType.includes('sheet') || mimeType.includes('excel')) return 'file-excel';
        if (mimeType.includes('presentation') || mimeType.includes('powerpoint')) return 'file-ppt';
        return 'file-earmark';
    }

    _icon(name) {
        const icons = {
            'upload': '<svg class="mo-bi" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5"/><path d="M7.646 1.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1-.708.708L8.5 2.707V11.5a.5.5 0 0 1-1 0V2.707L5.354 4.854a.5.5 0 1 1-.708-.708z"/></svg>',
            'trash': '<svg class="mo-bi" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/></svg>',
            'pencil': '<svg class="mo-bi" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325"/></svg>',
            'plus-lg': '<svg class="mo-bi" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M8 2a.5.5 0 0 1 .5.5v5h5a.5.5 0 0 1 0 1h-5v5a.5.5 0 0 1-1 0v-5h-5a.5.5 0 0 1 0-1h5v-5A.5.5 0 0 1 8 2"/></svg>',
            'code': '<svg class="mo-bi" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M5.854 4.854a.5.5 0 1 0-.708-.708l-3.5 3.5a.5.5 0 0 0 0 .708l3.5 3.5a.5.5 0 0 0 .708-.708L2.707 8zm4.292 0a.5.5 0 0 1 .708-.708l3.5 3.5a.5.5 0 0 1 0 .708l-3.5 3.5a.5.5 0 0 1-.708-.708L13.293 8z"/></svg>',
            'file-earmark': '<svg class="mo-bi" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M14 4.5V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2h5.5zm-3 0A1.5 1.5 0 0 1 9.5 3V1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V4.5z"/></svg>',
            'file-pdf': '<svg class="mo-bi" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M14 14V4.5L9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2M9.5 3A1.5 1.5 0 0 0 11 4.5h2V14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h5.5z"/></svg>',
            'file-text': '<svg class="mo-bi" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M5 4a.5.5 0 0 0 0 1h6a.5.5 0 0 0 0-1zm-.5 2.5A.5.5 0 0 1 5 6h6a.5.5 0 0 1 0 1H5a.5.5 0 0 1-.5-.5M5 8a.5.5 0 0 0 0 1h6a.5.5 0 0 0 0-1zm0 2a.5.5 0 0 0 0 1h3a.5.5 0 0 0 0-1z"/><path d="M2 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2zm10-1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1"/></svg>',
            'file-code': '<svg class="mo-bi" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M14 4.5V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2h5.5zm-3 0A1.5 1.5 0 0 1 9.5 3V1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V4.5zM8.646 6.646a.5.5 0 0 1 .708 0l2 2a.5.5 0 0 1 0 .708l-2 2a.5.5 0 0 1-.708-.708L10.293 9 8.646 7.354a.5.5 0 0 1 0-.708m-1.292 0a.5.5 0 0 0-.708 0l-2 2a.5.5 0 0 0 0 .708l2 2a.5.5 0 0 0 .708-.708L5.707 9l1.647-1.646a.5.5 0 0 0 0-.708"/></svg>',
            'file-zip': '<svg class="mo-bi" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M6.5 7.5a1 1 0 0 1 1-1h1a1 1 0 0 1 1 1v.938l.4 1.599a1 1 0 0 1-.416 1.074l-.93.62a1 1 0 0 1-1.109 0l-.93-.62a1 1 0 0 1-.415-1.074l.4-1.599zm2 0h-1v.938a1 1 0 0 1-.03.243l-.4 1.598.93.62.93-.62-.4-1.598a1 1 0 0 1-.03-.243z"/><path d="M2 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2zm5.5-1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1H9v1H8v1h1v1H8v1h1v1H7.5V5h-1V4h1V3h-1V2h1z"/></svg>',
            'file-word': '<svg class="mo-bi" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M14 4.5V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2h5.5zm-3 0A1.5 1.5 0 0 1 9.5 3V1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V4.5z"/></svg>',
            'file-excel': '<svg class="mo-bi" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M14 4.5V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2h5.5zm-3 0A1.5 1.5 0 0 1 9.5 3V1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V4.5z"/></svg>',
            'file-ppt': '<svg class="mo-bi" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M14 4.5V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2h5.5zm-3 0A1.5 1.5 0 0 1 9.5 3V1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V4.5z"/></svg>',
            'file-play': '<svg class="mo-bi" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M6 6.883v4.234a.5.5 0 0 0 .757.429l3.528-2.117a.5.5 0 0 0 0-.858L6.757 6.454a.5.5 0 0 0-.757.43z"/><path d="M14 14V4.5L9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2M9.5 3A1.5 1.5 0 0 0 11 4.5h2V14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h5.5z"/></svg>',
            'file-music': '<svg class="mo-bi" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M10.304 3.13a1 1 0 0 1 1.196.98v1.8l-2.5.5v5.09c0 .495-.301.883-.662 1.123C7.974 12.866 7.499 13 7 13s-.974-.134-1.338-.377C5.302 12.383 5 11.995 5 11.5s.301-.883.662-1.123C6.026 10.134 6.501 10 7 10c.356 0 .7.068 1 .196V4.41a1 1 0 0 1 .804-.98z"/><path d="M14 14V4.5L9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2M9.5 3A1.5 1.5 0 0 0 11 4.5h2V14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h5.5z"/></svg>',
            'info-circle': '<svg class="mo-bi" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/><path d="m8.93 6.588-2.29.287-.082.38.45.083c.294.07.352.176.288.469l-.738 3.468c-.194.897.105 1.319.808 1.319.545 0 1.178-.252 1.465-.598l.088-.416c-.2.176-.492.246-.686.246-.275 0-.375-.193-.304-.533zM9 4.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0"/></svg>',
            'exclamation-triangle': '<svg class="mo-bi" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M7.938 2.016A.13.13 0 0 1 8.002 2a.13.13 0 0 1 .063.016.15.15 0 0 1 .054.057l6.857 11.667c.036.06.035.124.002.183a.2.2 0 0 1-.054.06.1.1 0 0 1-.066.017H1.146a.1.1 0 0 1-.066-.017.2.2 0 0 1-.054-.06.18.18 0 0 1 .002-.183L7.884 2.073a.15.15 0 0 1 .054-.057m1.044-.45a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767z"/><path d="M7.002 12a1 1 0 1 1 2 0 1 1 0 0 1-2 0M7.1 5.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0z"/></svg>'
        };
        return icons[name] || icons['file-earmark'];
    }

    // ==================== Modals ====================

    _createModals() {
        this._createMediaModal();
        this._createConfirmModal();
        this._createEditModal();
    }

    _createMediaModal() {
        if (mofiles._mediaModal) return;
        const modal = document.createElement('div');
        modal.className = 'mo-modal-overlay mo-media-modal';
        modal.innerHTML = `
            <div class="mo-modal-content">
                <div class="mo-modal-header">
                    <div class="mo-modal-caption"></div>
                    <button class="mo-modal-close" type="button">&times;</button>
                </div>
                <div class="mo-modal-body"></div>
            </div>
        `;
        document.body.appendChild(modal);
        mofiles._mediaModal = modal;
        modal.querySelector('.mo-modal-close').onclick = () => this._closeModal(modal);
        modal.onclick = (e) => { if (e.target === modal) this._closeModal(modal); };
    }

    _createConfirmModal() {
        if (mofiles._confirmModal) return;
        const modal = document.createElement('div');
        modal.className = 'mo-modal-overlay mo-confirm-modal mofiles-wrapper' + (this._isDark ? ' mo-dark' : '');
        modal.innerHTML = `
            <div class="mo-modal-content">
                <div class="mo-modal-body"></div>
                <div class="mo-modal-footer">
                    <button class="mo-btn mo-btn-secondary mo-confirm-cancel" type="button">${this.opts.labels.cancel}</button>
                    <button class="mo-btn mo-btn-danger mo-confirm-ok" type="button">${this._icon('trash')} ${this.opts.labels.delete}</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        mofiles._confirmModal = modal;
        modal.querySelector('.mo-confirm-cancel').onclick = () => this._closeModal(modal);
        modal.onclick = (e) => { if (e.target === modal) this._closeModal(modal); };
    }

    _createEditModal() {
        if (mofiles._editModal) return;
        const modal = document.createElement('div');
        modal.className = 'mo-modal-overlay mo-edit-modal mofiles-wrapper' + (this._isDark ? ' mo-dark' : '');
        modal.innerHTML = `
            <div class="mo-modal-content">
                <div class="mo-modal-header">
                    <div class="mo-modal-title"></div>
                    <button class="mo-modal-close" type="button">&times;</button>
                </div>
                <div class="mo-modal-body">
                    <div class="mo-form-group">
                        <label class="mo-form-label" for="mo-edit-category">${this.opts.labels.category}</label>
                        <input type="text" class="mo-form-control" id="mo-edit-category" maxlength="24">
                    </div>
                    <div class="mo-form-group">
                        <label class="mo-form-label" for="mo-edit-keyword">${this.opts.labels.keyword}</label>
                        <input type="text" class="mo-form-control" id="mo-edit-keyword" maxlength="32">
                    </div>
                    <div class="mo-form-group">
                        <label class="mo-form-label" for="mo-edit-description">${this.opts.labels.description}</label>
                        <textarea class="mo-form-control" id="mo-edit-description" maxlength="255"></textarea>
                    </div>
                </div>
                <div class="mo-modal-footer">
                    <button class="mo-btn mo-btn-secondary mo-edit-cancel" type="button">${this.opts.labels.cancel}</button>
                    <button class="mo-btn mo-btn-primary mo-edit-save" type="button">${this.opts.labels.save}</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        mofiles._editModal = modal;
        modal.querySelector('.mo-modal-close').onclick = () => this._closeModal(modal);
        modal.querySelector('.mo-edit-cancel').onclick = () => this._closeModal(modal);
        modal.onclick = (e) => { if (e.target === modal) this._closeModal(modal); };
    }

    _showMedia(type, src, caption = '') {
        const modal = mofiles._mediaModal;
        if (!modal) return;
        const body = modal.querySelector('.mo-modal-body');
        body.innerHTML = '';
        modal.querySelector('.mo-modal-caption').textContent = caption;
        if (type === 'image') {
            const img = document.createElement('img');
            img.src = src;
            body.appendChild(img);
        } else if (type === 'video') {
            const video = document.createElement('video');
            video.src = src;
            video.controls = true;
            video.autoplay = true;
            body.appendChild(video);
        }
        modal.classList.add('mo-show');
        document.body.style.overflow = 'hidden';
    }

    _closeModal(modal) {
        modal.classList.remove('mo-show');
        document.body.style.overflow = '';
        const video = modal.querySelector('video');
        if (video) video.pause();
    }

    // ==================== Notification ====================

    _notify(type, title, message) {
        if (typeof NotifyTop === 'function') {
            NotifyTop(type, title, message);
            return;
        }
        document.querySelector('.mo-toast')?.remove();
        const toast = document.createElement('div');
        toast.className = 'mo-toast mofiles-wrapper' + (this._isDark ? ' mo-dark' : '');
        toast.innerHTML = `
            <div class="mo-toast-header mo-${type}">
                <span>${this._escape(title)}</span>
                <button class="mo-toast-close" type="button">&times;</button>
            </div>
            <div class="mo-toast-body">${this._escape(message)}</div>
        `;
        document.body.appendChild(toast);
        toast.querySelector('.mo-toast-close').onclick = () => toast.remove();
        setTimeout(() => toast.remove(), 4000);
    }

    // ==================== Count Badge ====================

    _createCountBadge() {
        if (!this.opts.countBadge) return;
        const container = typeof this.opts.countBadge === 'string'
            ? document.querySelector(this.opts.countBadge)
            : this.opts.countBadge;
        if (!container) return;
        this._badgeEl = document.createElement('span');
        this._badgeEl.className = 'badge bg-secondary ms-1';
        this._badgeEl.textContent = '0';
        container.appendChild(this._badgeEl);
    }

    _updateCount() {
        if (!this._badgeEl) return;
        const count = this.getCount();
        this._badgeEl.textContent = count;
        this._badgeEl.classList.remove('bg-secondary', 'bg-primary');
        this._badgeEl.classList.add(count > 0 ? 'bg-primary' : 'bg-secondary');
    }

    // ==================== Dark Mode ====================

    _setupDarkModeListener() {
        if (this.opts.darkMode !== 'auto') return;
        const observer = new MutationObserver(() => {
            const isDark = this._detectDarkMode();
            if (isDark !== this._isDark) {
                this._isDark = isDark;
                this._wrapper?.classList.toggle('mo-dark', isDark);
            }
        });
        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class', 'data-bs-theme', 'data-theme'] });
        observer.observe(document.body, { attributes: true, attributeFilter: ['class'] });
    }

    setDarkMode(isDark) {
        this._isDark = isDark;
        this._wrapper?.classList.toggle('mo-dark', isDark);
    }

    destroy() {
        this._wrapper?.remove();
    }
}

window.mofiles = mofiles;
