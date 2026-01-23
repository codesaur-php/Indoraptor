/**
 * mofiles v1
 * ------------------------------------------------------------------
 * Файл upload, харуулах, устгах боломжтой хүснэгт.
 * motable дээр суурилсан, Plupload + Fancybox + SweetAlert2 ашигладаг.
 *
 * @requires motable
 * @requires plupload
 * @requires Fancybox (optional)
 * @requires Swal (optional)
 *
 * @example
 * const files = new mofiles('#news_files', {
 *     uploadUrl: '/api/files/upload',
 *     deleteUrl: '/api/files/delete',
 *     modalUrl: '/api/files/modal',
 *     permissions: { update: true, delete: true },
 *     onUpload: (res) => console.log('Uploaded:', res),
 *     onDelete: (id) => console.log('Deleted:', id)
 * });
 */

class mofiles {
    /**
     * mofiles constructor
     * @param {string|HTMLElement} tableSelector - Table element эсвэл selector
     * @param {Object} opts - Тохиргоо
     */
    constructor(tableSelector, opts = {}) {
        /* Монгол хэл эсэхийг шалгах */
        this._isMn = document.documentElement.lang === 'mn';

        /* Options тохируулах */
        this.opts = {
            /* Upload URL */
            uploadUrl: null,
            /* Delete URL */
            deleteUrl: null,
            /* Modal URL (файлын мэдээлэл засах modal) */
            modalUrl: null,
            /* Эрхүүд */
            permissions: {
                update: false,
                delete: false
            },
            /* Max file size */
            maxFileSize: '8mb',
            /* Allowed file types */
            mimeTypes: [
                { title: 'Images', extensions: 'jpg,jpeg,jpe,png,gif,ico,webp' },
                { title: 'Documents', extensions: 'pdf,doc,docx,xls,xlsx,ppt,pptx,pps,ppsx,odt' },
                { title: 'Audio', extensions: 'mp3,m4a,ogg,wav' },
                { title: 'Video', extensions: 'mp4,m4v,mov,wmv,avi,mpg,ogv,3gp,3g2' },
                { title: 'Text files', extensions: 'txt,xml,json' },
                { title: 'Archives', extensions: 'zip,rar' }
            ],
            /* Callbacks */
            onUpload: null,
            onDelete: null,
            onChange: null,
            /* Upload container ID */
            containerId: 'mofiles-container',
            pickButtonId: 'mofiles-pick',
            uploadButtonId: 'mofiles-upload',
            fileListId: 'mofiles-list',
            /* Count badge element */
            countBadge: null,
            /* Readonly mode */
            readonly: false,
            /* Labels */
            labels: {
                selectFiles: this._isMn ? 'Файл сонгох' : 'Select files',
                uploadFiles: this._isMn ? 'Хуулах' : 'Upload',
                deleteConfirm: this._isMn ? 'Та {title} файлын бичлэгийг устгахдаа итгэлтэй байна уу?' : 'Are you sure to delete {title}?',
                deleteNote: this._isMn ? 'Бодит файл нь устахгүй бөгөөд оршсоор байх болно.' : 'The actual file will not be deleted.',
                uploadSuccess: this._isMn ? 'Файл амжилттай хуулагдлаа' : 'File uploaded successfully',
                deleteSuccess: this._isMn ? 'Файл амжилттай устгагдлаа' : 'File deleted successfully',
                error: this._isMn ? 'Алдаа' : 'Error',
                success: this._isMn ? 'Амжилттай' : 'Success',
                cancel: this._isMn ? 'Болих' : 'Cancel',
                delete: this._isMn ? 'Устгах' : 'Delete',
                sending: this._isMn ? 'илгээж байна' : 'sending'
            },
            ...opts
        };

        /* motable instance үүсгэх */
        this.table = new motable(tableSelector, opts.motableOpts || {});

        /* Uploader elements */
        this.fileListEl = document.getElementById(this.opts.fileListId);
        this.uploadBtn = document.getElementById(this.opts.uploadButtonId);
        this.pickBtn = document.getElementById(this.opts.pickButtonId);
        this.containerEl = document.getElementById(this.opts.containerId);

        /* Plupload uploader */
        this.uploader = null;

        /* Readonly биш бол uploader идэвхжүүлэх */
        if (!this.opts.readonly && this.opts.uploadUrl) {
            this._initUploader();
        }
    }

    /**
     * Plupload uploader идэвхжүүлэх
     * @private
     */
    _initUploader() {
        if (!window.plupload) {
            console.warn('mofiles: plupload not found');
            return;
        }

        if (!this.containerEl || !this.pickBtn) {
            console.warn('mofiles: upload container or pick button not found');
            return;
        }

        const self = this;

        this.uploader = new plupload.Uploader({
            runtimes: 'html5,flash,silverlight,html4',
            browse_button: this.opts.pickButtonId,
            container: this.containerEl,
            url: this.opts.uploadUrl,
            filters: {
                max_file_size: this.opts.maxFileSize,
                mime_types: this.opts.mimeTypes
            },
            flash_swf_url: 'https://cdn.jsdelivr.net/gh/moxiecode/plupload/js/Moxie.swf',
            silverlight_xap_url: 'https://cdn.jsdelivr.net/gh/moxiecode/plupload/js/Moxie.xap',
            init: {
                PostInit: function () {
                    if (self.fileListEl) self.fileListEl.innerHTML = '';
                    if (self.uploadBtn) {
                        self.uploadBtn.onclick = function () {
                            self.uploader.start();
                            return false;
                        };
                    }
                },
                FilesAdded: function (up, files) {
                    if (self.fileListEl) {
                        plupload.each(files, function (file) {
                            self.fileListEl.innerHTML += `<div id="${file.id}">${file.name} (${plupload.formatSize(file.size)}) <b></b> <em></em></div>`;
                        });
                    }

                    if (self.uploadBtn && self.uploadBtn.disabled) {
                        self.uploadBtn.removeAttribute('disabled');
                        self.uploadBtn.classList.remove('btn-secondary');
                        self.uploadBtn.classList.add('btn-info');
                    }
                },
                UploadProgress: function (up, file) {
                    const el = document.getElementById(file.id);
                    if (el) {
                        el.getElementsByTagName('b')[0].innerHTML = `<i class="bi bi-upload"></i> ${self.opts.labels.sending} ${file.percent}%`;
                    }
                    if (self.uploadBtn) self.uploadBtn.setAttribute('disabled', '');
                },
                FileUploaded: function (up, file, response) {
                    try {
                        const res = JSON.parse(response.response);
                        if (!res.file) {
                            throw new Error('Invalid response!');
                        }

                        const currentFile = document.getElementById(file.id);
                        if (currentFile) {
                            currentFile.getElementsByTagName('b')[0].innerHTML = '<i class="bi bi-check"></i> success';
                            currentFile.classList.add('text-success');
                        }

                        self.append(res);

                        if (typeof self.opts.onUpload === 'function') {
                            self.opts.onUpload(res);
                        }

                        self._notify('success', self.opts.labels.success, self.opts.labels.uploadSuccess);
                    } catch (err) {
                        let errMsg = 'Unknown error!';
                        if (err instanceof SyntaxError) {
                            errMsg = 'Invalid request!';
                        } else if (err.message) {
                            errMsg = err.message;
                        }

                        const currentFile = document.getElementById(file.id);
                        if (currentFile) {
                            currentFile.getElementsByTagName('b')[0].innerHTML = '<i class="bi bi-x"></i> error';
                            currentFile.getElementsByTagName('em')[0].innerHTML = errMsg;
                            currentFile.classList.add('text-danger');
                        }
                        self._notify('danger', self.opts.labels.error, errMsg);
                    }

                    self._checkUploadComplete();
                },
                Error: function (up, err) {
                    if (err.file && err.file.id) {
                        const currentFile = document.getElementById(err.file.id);
                        if (currentFile) {
                            currentFile.getElementsByTagName('b')[0].innerHTML = '<i class="bi bi-x"></i> failed';
                            currentFile.getElementsByTagName('em')[0].innerHTML = err.message;
                            currentFile.classList.add('text-danger');
                        }
                    }
                    self._notify('danger', self.opts.labels.error, err.message);
                }
            }
        });

        this.uploader.init();
    }

    /**
     * Upload дууссан эсэхийг шалгах
     * @private
     */
    _checkUploadComplete() {
        if (!this.fileListEl || !this.uploadBtn) return;

        const list = this.fileListEl.children;
        let processedCount = 0;

        for (let i = 0; i < list.length; i++) {
            if (list[i].getElementsByTagName('b')[0].innerHTML !== '') {
                processedCount++;
            }
        }

        if (processedCount === list.length) {
            this.uploadBtn.classList.remove('btn-info');
            this.uploadBtn.classList.add('btn-secondary');
            this.uploadBtn.setAttribute('disabled', '');
        } else {
            this.uploadBtn.removeAttribute('disabled');
        }
    }

    /**
     * Notify helper
     * @private
     */
    _notify(type, title, message) {
        if (typeof NotifyTop === 'function') {
            NotifyTop(type, title, message);
        } else if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: type === 'danger' ? 'error' : type, title, text: message });
        } else {
            alert(message);
        }
    }

    /**
     * HTML escape
     * @private
     */
    _escape(s) {
        if (!s) return '';
        const lookup = { '&': '&amp;', '"': '&quot;', "'": '&apos;', '<': '&lt;', '>': '&gt;' };
        return String(s).replace(/[&"'<>]/g, c => lookup[c]);
    }

    /**
     * Файлын нэр авах
     * @private
     */
    _basename(url) {
        if (!url) return '';
        return url.split(/.*[\/|\\]/)[1] || url;
    }

    /**
     * Байт хэмжээг форматлах
     * @private
     */
    _formatSize(bytes) {
        const thresh = 1024;
        if (Math.abs(bytes) < thresh) return bytes + 'b';

        const units = ['kb', 'mb', 'gb', 'tb'];
        let u = -1;
        const r = 10;

        do {
            bytes /= thresh;
            ++u;
        } while (Math.round(Math.abs(bytes) * r) / r >= thresh && u < units.length - 1);

        return bytes.toFixed(1) + units[u];
    }

    /**
     * Файл нэмэх
     * @param {Object} record - Файлын мэдээлэл
     */
    append(record) {
        const self = this;
        const fileName = this._escape(this._basename(record.path));
        let fileIcon = '';
        let fileLinkAttr = `target="_blank" onclick="return confirm('Open file: ${fileName}?')"`;
        let fileAction = '';

        /* Файлын төрлөөр icon тодорхойлох */
        switch (record.type) {
            case 'image':
                fileIcon = `<img src="${record.path}" style="max-height:7.5rem;height:100%">`;
                if (record.mime_content_type !== 'image/gif') {
                    const caption = record.description ? this._escape(record.description) : fileName;
                    fileLinkAttr = `data-fancybox="mofiles" data-caption="${caption}"`;
                }
                break;
            case 'video':
                fileIcon = `<video style="max-height:15rem;height:100%;max-width:20rem;width:100%" controls><source src="${record.path}"></video>`;
                break;
            case 'audio':
                fileIcon = `<audio controls><source src="${record.path}" type="${record.mime_content_type}"></audio>`;
                break;
            default:
                fileIcon = '<i class="bi bi-file-earmark" style="font-size:2rem"></i>';
                break;
        }

        /* Action товчнууд */
        if (this.opts.modalUrl) {
            if (record.type === 'image' || record.type === 'video' || record.type === 'audio') {
                fileAction += ` <a class="btn btn-sm btn-dark ajax-modal" data-bs-target="#static-modal" data-bs-toggle="modal" href="${this.opts.modalUrl}?modal=${record.type}-tag&id=${record.id}"><i class="bi bi-code"></i></a>`;
            }
            fileAction = `<a class="btn btn-sm btn-info ajax-modal" data-bs-target="#static-modal" data-bs-toggle="modal" href="${this.opts.modalUrl}?modal=location&id=${record.id}"><i class="bi bi-link"></i></a>${fileAction}`;

            if (this.opts.permissions.update) {
                fileAction += ` <a class="btn btn-sm btn-primary shadow-sm ajax-modal" data-bs-target="#static-modal" data-bs-toggle="modal" href="${this.opts.modalUrl}?modal=files-update&id=${record.id}"><i class="bi bi-pencil-square"></i></a>`;
            }
        }

        if (this.opts.permissions.delete && !this.opts.readonly) {
            fileAction += ` <button class="mofiles-delete btn btn-sm btn-danger shadow-sm" data-id="${record.id}" type="button"><i class="bi bi-trash"></i></button>`;
        }

        /* Row үүсгэх */
        const row = document.createElement('tr');
        row.id = `file_${record.id}`;
        row.style.fontSize = '.875rem';

        /* Cell 1: Файл */
        const cell1 = document.createElement('td');
        cell1.innerHTML = `<input name="files[]" value="${record.id}" type="hidden"><a href="${record.path}" ${fileLinkAttr}>${fileIcon} <span style="display:block">${fileName}</span></a> ${fileAction}`;
        row.appendChild(cell1);

        /* Cell 2: Properties */
        const cell2 = document.createElement('td');
        cell2.innerHTML = `<p style="max-width:11.25rem;word-wrap:break-all"><u>${record.mime_content_type || ''}</u></p>${this._formatSize(record.size || 0)}`;
        row.appendChild(cell2);

        /* Cell 3: Description */
        const cell3 = document.createElement('td');
        cell3.innerHTML = record.description || '';
        row.appendChild(cell3);

        /* Cell 4: Category */
        const cell4 = document.createElement('td');
        cell4.innerHTML = record.category || '';
        row.appendChild(cell4);

        /* Cell 5: Keyword */
        const cell5 = document.createElement('td');
        cell5.innerHTML = record.keyword || '';
        row.appendChild(cell5);

        /* Table-д нэмэх */
        let tBody = this.table.table.querySelector('tbody');
        if (!tBody) {
            tBody = document.createElement('tbody');
            this.table.table.appendChild(tBody);
        }
        tBody.appendChild(row);

        this.table.setReady();

        /* Fancybox bind */
        if (record.type === 'image' && record.mime_content_type !== 'image/gif' && typeof Fancybox !== 'undefined') {
            Fancybox.close();
            Fancybox.bind('[data-fancybox="mofiles"]', { groupAll: true });
        }

        /* Ajax modal event listeners */
        row.querySelectorAll('.ajax-modal').forEach(a => {
            a.addEventListener('click', function (e) {
                e.preventDefault();
                if (typeof ajaxModal === 'function') ajaxModal(a);
            });
        });

        /* Delete button event listener */
        const deleteBtn = row.querySelector('.mofiles-delete');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this._confirmDelete(record.id, fileName, row);
            });
        }

        /* Update count badge */
        this._updateCount();

        /* onChange callback */
        if (typeof this.opts.onChange === 'function') {
            this.opts.onChange('append', record);
        }
    }

    /**
     * Олон файл нэмэх
     * @param {Array} records - Файлуудын жагсаалт
     */
    load(records) {
        if (!Array.isArray(records)) return;
        records.forEach(record => this.append(record));
    }

    /**
     * Устгах баталгаажуулалт
     * @private
     */
    _confirmDelete(id, title, row) {
        const self = this;
        const question = this.opts.labels.deleteConfirm.replace('{title}', title);
        const note = this.opts.labels.deleteNote;

        let imgSrc = '';
        const img = row.querySelector('img');
        if (img) imgSrc = img.src;

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                imageUrl: imgSrc || undefined,
                imageHeight: imgSrc ? 64 : undefined,
                html: `<p class="text-danger mb-3">${question}<br/><br/>${note}</p>`,
                showCancelButton: true,
                cancelButtonText: this.opts.labels.cancel,
                confirmButtonText: `<i class="bi bi-trash"></i> ${this.opts.labels.delete}`,
                confirmButtonColor: '#df4759',
                showLoaderOnConfirm: true,
                allowOutsideClick: () => !Swal.isLoading(),
                preConfirm: () => this._doDelete(id, title, row)
            });
        } else if (confirm(question)) {
            this._doDelete(id, title, row);
        }
    }

    /**
     * Файл устгах
     * @private
     */
    _doDelete(id, title, row) {
        const self = this;

        if (!this.opts.deleteUrl) {
            row.remove();
            this.table.setReady();
            this._updateCount();
            if (typeof this.opts.onDelete === 'function') {
                this.opts.onDelete(id);
            }
            return Promise.resolve();
        }

        return fetch(this.opts.deleteUrl, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, title })
        })
            .then(res => res.json())
            .then(response => {
                if (response.status !== 'success') {
                    throw new Error(response.message || 'Invalid response!');
                }

                if (typeof Swal !== 'undefined') Swal.close();

                self._notify('success', self.opts.labels.success, response.message || self.opts.labels.deleteSuccess);

                row.remove();
                self.table.setReady();
                self._updateCount();

                if (typeof self.opts.onDelete === 'function') {
                    self.opts.onDelete(id);
                }

                if (typeof self.opts.onChange === 'function') {
                    self.opts.onChange('delete', { id });
                }
            })
            .catch(error => {
                if (typeof Swal !== 'undefined') {
                    Swal.showValidationMessage(error.message);
                } else {
                    self._notify('danger', self.opts.labels.error, error.message);
                }
            });
    }

    /**
     * Count badge шинэчлэх
     * @private
     */
    _updateCount() {
        if (!this.opts.countBadge) return;

        const badge = typeof this.opts.countBadge === 'string'
            ? document.querySelector(this.opts.countBadge)
            : this.opts.countBadge;

        if (!badge) return;

        const count = this.table.table.querySelectorAll('tbody tr').length;
        badge.textContent = count;
        badge.className = count > 0 ? 'badge bg-primary' : 'badge bg-secondary';
    }

    /**
     * Файлуудын тоо авах
     * @returns {number}
     */
    getCount() {
        return this.table.table.querySelectorAll('tbody tr').length;
    }

    /**
     * Файлуудын ID-уудыг авах
     * @returns {Array}
     */
    getFileIds() {
        const ids = [];
        this.table.table.querySelectorAll('tbody tr input[name="files[]"]').forEach(input => {
            ids.push(input.value);
        });
        return ids;
    }
}

/* Global export */
window.mofiles = mofiles;
