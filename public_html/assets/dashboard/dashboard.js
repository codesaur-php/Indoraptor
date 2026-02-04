/**
 * ================================================================
 * 📌 Indoraptor Dashboard - JavaScript Utilities
 * ================================================================
 *
 * Энэ файл нь Dashboard UI-ийн нийтлэг функцуудыг нэгтгэсэн сан юм.
 *  Доорх функцууд нь: *
 *  ✔ AJAX Modal Loader
 *  ✔ Sidebar link activation
 *  ✔ Top Notification (NotifyTop)
 *  ✔ Button Spinner (spinNstop / growNstop)
 *  ✔ Scroll-To-Top Button
 *  ✔ copyContent() - текст copy хийх
 *  ✔ Dark mode auto-apply
 *
 * Indoraptor Dashboard бүхэн энэ файлыг залгаж ашиглана.
 *
 * Хөгжүүлэгч энэхүү файлыг өөрийн Dashboard-д дахин өргөтгөж 
 * өөрийн функцүүдийг ч нэмэх боломжтой.
 *
 * ---------------------------------------------------------------
 * ⚠️ Анхаарах зүйлс:
 * ---------------------------------------------------------------
 *  • Bootstrap modal механизм ашигладаг
 *  • <a data-bs-toggle="modal" data-bs-target="#static-modal"> 
 *      гэсэн линкүүд дээр AJAX ачаалалт ажиллана
 *  • Inline болон external <script> tag-уудыг response дотороос 
 *      автоматаар execution хийнэ
 *  • NotifyTop() нь системийн бүх popup notification-ийг орлодог
 *  • Button-ууд дээр .spinNstop() ашиглахад илүү амар
 * ================================================================
 */

/* 🌙 DARK MODE ИДЭВХЖҮҮЛЭХ */
if (localStorage.getItem('data-bs-theme') === 'dark') {
    document.body.setAttribute('data-bs-theme', 'dark');
}

/**
 * 📌 ajaxModal(link)
 * -- Modal-ийн агуулгыг AJAX-аар ачаалж харуулна
 *
 * @description
 *  data-bs-target="#static-modal" гэсэн modal руу HTML response 
 *  ачаалж, скриптуудыг сэргээж ажиллуулдаг ухаалаг loader.
 *
 * @param {HTMLElement} link - modal нээж буй <a> эсвэл <button>
 */
function ajaxModal(link)
{
    let url;
    if (link.hasAttribute('href')) {
        url = link.getAttribute('href');
    }
    if (!url || url.startsWith('javascript:;')) {
        return;
    }

    const modalId = link.getAttribute('data-bs-target');
    if (!modalId) return;
    const modalDiv = document.querySelector(modalId);
    if (!modalDiv) return;

    const method = link.getAttribute('method');
    const xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function () {
        if (this.readyState === XMLHttpRequest.DONE) {
            modalDiv.innerHTML = this.responseText;

            /* хуудсан дахь <script> tag-уудыг дотор нь ажиллуулна */
            const parser = new DOMParser();
            const responseDoc = parser.parseFromString(this.responseText, 'text/html');
            responseDoc.querySelectorAll('script').forEach(function (script) {
                if (script.src) {
                    /* External JS дахин залгах */
                    const newScript = document.createElement('script');
                    newScript.src = script.src;
                    document.body.appendChild(newScript);
                } else if (script.innerHTML.trim() !== '') {
                    try { eval(script.innerHTML); }
                    catch (e) { console.error('Modal script error:', e); }
                }
            });

            /* RESPONSE ERROR HANDLER */
            if (this.status !== 200) {
                const isModal = responseDoc.querySelector('div.modal-dialog');
                if (!isModal) {
                    modalDiv.innerHTML = `
                       <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-body">
                                    <div class="alert alert-danger shadow-sm mt-3">
                                        <i class="bi bi-bug-fill"></i>
                                        Error [${this.status}]: <strong>${this.statusText}</strong>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                </div>
                             </div>
                        </div>`;
                }
            }
        }
    };
    xhr.open(method || 'GET', url, true);
    xhr.send();
}

/**
 * 📌 activateLink(href)
 * -- Sidebar-ийн идэвхтэй линк тодруулах
 * 
 * @param {string} href - Document link */
function activateLink(href)
{
    if (!href) return;

    document.querySelectorAll('.sidebar-menu a.nav-link').forEach(function (a) {
        const aLink = a.getAttribute('href');
        if (aLink && href.startsWith(aLink)) {
            a.classList.add('active');
        }
    });
}

/** 
 * 📣 NotifyTop(type, title, content)
 * -- Дээд notification popup
 *
 * @param {string} type - success, danger, warning, primary
 * @param {string} title - гарчиг
 * @param {string} content - доторх текст
 * @param {number} velocity - хөдөлж харагдах хурд
 * @param {number} delay - автоматаар хаагдах хугацаа
 */
function NotifyTop(type, title, content, velocity = 5, delay = 2500)
{
    const previous = document.querySelector('.notifyTop');
    if (previous?.parentNode) {
        previous.parentNode.removeChild(previous);
    }

    /* өнгө сонгох... */
    const bgColorHex =
        type === 'success' ? '#15cc1f' :
        type === 'warning' ? '#ffc107' :
        type === 'danger'  ? '#f32750' :
        type === 'primary' ? '#0d6efd' :
                             '#17a2b8';

    const section = document.createElement('section');
    section.classList.add('notifyTop');
    section.style.cssText =
        `z-index:11500;position:fixed;padding:1rem;color:#fff;
         background:${bgColorHex};right:0;left:0;top:0`;

    const h5 = document.createElement('h5');
    h5.style.cssText = 'margin:5px 0;font-weight:300;color:#fff;text-transform:uppercase;';
    h5.innerHTML = title;

    const closeX = document.createElement('a');
    closeX.innerHTML = 'x';
    closeX.style.cssText =
        'cursor:pointer;position:absolute;right:0;top:0;color:#fff;padding:10px 15px;font-size:16px';

    const contentDiv = document.createElement('div');
    contentDiv.innerHTML = content;

    section.append(h5, closeX, contentDiv);
    document.body.appendChild(section);

    /* анимэйшн … */
    const notifyHeight = section.offsetHeight;
    section.style.top = -notifyHeight + 'px';

    let top = -notifyHeight;
    let interv = setInterval(function () {
        top += 10;
        section.style.top = top + 'px';
        if (top > -10) clearInterval(interv);
    }, velocity);

    let close = function () {
        closeX.style.display = 'none';
        const interv2 = setInterval(function () {
            top -= 10;
            section.style.top = top + 'px';
            if (top < -notifyHeight) {
                section.remove();
                clearInterval(interv2);
            }
        }, velocity);
    };

    closeX.onclick = e => { e.preventDefault(); close(); };
    setTimeout(close, delay);
}

/**
 * 🔄 Button Spinner - spinNstop(), growNstop()
 * 
 * @description
 *  Button дээр loader spinner тавиад, disable болгох.
 *  Ajax дуусаад буцааж сэргээхэд ашиглана.
 * @param {HTMLElement} ele - Button element
 * @param {string} type - spinner төрөл (border эсвэл grow)
 * @param {bool} block - element-ийн дотоод агуулгыг блоклох эсэх
 */
function spinStop(ele, type, block)
{
    const isDisabled = ele.disabled;
    const hasDisabled = ele.classList.contains('disabled');
    const attrText = ele.getAttribute('data-innerHTML');
    if (isDisabled && hasDisabled && attrText) {
        ele.disabled = false;
        ele.classList.remove('disabled');
        ele.innerHTML = attrText;
        return;
    }

    const html = ele.innerHTML;
    ele.setAttribute('data-innerHTML', html);
    const lgStyle = ele.classList.contains('btn-lg') ? ' style="position:relative;top:-2px"' : '';
    let spanHtml = `<span class="spinner-${type} spinner-${type}-sm" role="status"${lgStyle}></span>`;
    if (!block) spanHtml += ' ' + html;

    ele.innerHTML = spanHtml;
    ele.disabled = true;
    ele.classList.add('disabled');
}
Element.prototype.spinNstop = function (block = true) {
    spinStop(this, 'border', block);
};
Element.prototype.growNstop = function (block = true) {
    spinStop(this, 'grow', block);
};

/* ⬆ Scroll-To-Top Button */
function initScrollToTop(options = {}) {
    /* Default options */
    const config = {
        right: options.right ?? '25%',
        bottom: options.bottom ?? '0px',
        bgColor: options.bgColor ?? '#7952b3',
        hoverColor: options.hoverColor ?? 'blue',
        sizeW: options.sizeW ?? '40px',
        sizeH: options.sizeH ?? '25px',
        threshold: options.threshold ?? 200
    };

    /* Avoid creating multiple buttons */
    if (document.getElementById('scrollToTopBtn')) return;

    /* Create arrow icon */
    const upArrow = document.createElement('i');
    upArrow.style.cssText =
        'border:solid black;border-width:0 2px 2px 0;border-color:white;display:inline-block;' +
        'padding:3.4px;margin-top:11px;transform:rotate(-135deg);-webkit-transform:rotate(-135deg)';

    /* Create button */
    const btnScroll = document.createElement('a');
    btnScroll.id = 'scrollToTopBtn';
    btnScroll.style.cssText =
        `display:inline-block;cursor:pointer;background-color:${config.bgColor};` +
        `width:${config.sizeW};height:${config.sizeH};text-align:center;` +
        `border-radius:6px 6px 0px 0px;position:fixed;right:${config.right};bottom:${config.bottom};` +
        `transition:background-color .3s, opacity .5s, visibility .5s;opacity:0.75;` +
        `visibility:hidden;z-index:10000`;

    btnScroll.appendChild(upArrow);
    document.body.appendChild(btnScroll);

    /* Scroll detection */
    window.addEventListener('scroll', function () {
        const windowpos = document.documentElement.scrollTop;
        if (windowpos > config.threshold) {
            btnScroll.style.opacity = '0.75';
            btnScroll.style.visibility = 'visible';
        } else {
            btnScroll.style.opacity = '0';
            btnScroll.style.visibility = 'hidden';
        }
    });

    /* Smooth scroll */
    btnScroll.addEventListener('click', function (e) {
        e.preventDefault();
        scroll({ top: 0, behavior: 'smooth' });
    });

    /* Hover states */
    btnScroll.addEventListener('mouseover', () => {
        btnScroll.style.backgroundColor = config.hoverColor;
    });
    btnScroll.addEventListener('mouseout', () => {
        btnScroll.style.backgroundColor = config.bgColor;
    });
}

/** 
 * 📋 copyContent(elementId)
 * @description
 *  DOM текстийг clipboard руу хуулна
 *  
 * @param {HTMLElement} elem - текстийг агуулсан element */
function copyContent(elem)
{
    const text = document.getElementById(elem);
    if (document.body.createTextRange) {
        const range = document.body.createTextRange();
        range.moveToElementText(text);
        range.select();
    } else if (window.getSelection) {
        const selection = window.getSelection();
        const range = document.createRange();
        range.selectNodeContents(text);
        selection.removeAllRanges();
        selection.addRange(range);
    }
    document.execCommand('copy');
}

/**
 * 🚀 DOMContentLoaded:
 * -- Sidebar activate
 * -- static-modal reset
 * -- AJAX modal binding */
document.addEventListener('DOMContentLoaded', function () {
    activateLink(window.location.pathname);

    const staticModal = document.getElementById('static-modal');
    const modalInitialContent = staticModal?.innerHTML;
    staticModal?.addEventListener('hidden.bs.modal', function () {
        this.innerHTML = modalInitialContent;
    });

    document.querySelectorAll('[data-bs-toggle="modal"][data-bs-target="#static-modal"]')
        .forEach(link => link.addEventListener('click', function (e) {
            e.preventDefault();
            ajaxModal(link);
        }));
        
    initScrollToTop();
});
