if (localStorage.getItem('data-bs-theme') === 'dark') {
    document.body.setAttribute('data-bs-theme', 'dark');
}

function ajaxModal(link)
{
    let url;
    if (link.hasAttribute('href')) {
        url = link.getAttribute('href');
    }
    if (!url || url.startsWith('javascript:;')) {
        return;
    }
    let modalId = link.getAttribute('data-bs-target');
    if (!modalId) {
        return;
    }
    let modalDiv = document.querySelector(modalId);
    if (!modalDiv) {
        return;
    }
    let method;
    if (link.hasAttribute('method')) {
        method = link.getAttribute('method');
    }

    let xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function () {
        if (this.readyState === XMLHttpRequest.DONE) {
            modalDiv.innerHTML = this.responseText;
            let parser = new DOMParser();
            let responseDoc = parser.parseFromString(this.responseText, 'text/html');
            responseDoc.querySelectorAll('script[type="text/javascript"]').forEach(function (script) {
                eval(script.innerHTML);
            });
            if (this.status !== 200) {
                let isModal = responseDoc.querySelector('div.modal-dialog');
                if (!isModal) {
                    modalDiv.innerHTML =
                       `<div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-body">
                                    <div class="alert alert-danger shadow-sm fade mt-3 show" role="alert">
                                        <i class="bi bi-bug-fill"></i><span class="ps-1">Error [${this.status}]: <strong>${this.statusText}</strong></span>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button class="btn btn-secondary shadow-sm" data-bs-dismiss="modal" type="button">Close</button>
                                </div>
                             </div>
                        </div>`;
                }
            }
        }
    };
    xhr.open((!method || method === '') ? 'GET' : method, url, true);
    xhr.send();
}

function activateLink(href)
{
    if (!href) {
        return;
    }
    document.querySelectorAll('.sidebar-menu a.nav-link').forEach(function (a) {
        let aLink = a.getAttribute('href');
        if (aLink && href.startsWith(aLink)) {
            a.classList.add('active');
        }
    });
}

function NotifyTop(type, title, content, velocity = 5, delay = 2500)
{
    const previous = document.querySelector('.notifyTop');
    if (previous?.parentNode) {
        previous.parentNode.removeChild(previous);
    }

    let bgColorHex;
    switch (type) {
        case 'primary':
            bgColorHex = '#0d6efd';
            break;
        case 'success':
            bgColorHex = '#15cc1f';
            break;
        case 'warning':
            bgColorHex = '#ffc107';
            break;
        case 'danger':
            bgColorHex = '#f32750';
            break;
        default:
            bgColorHex = '#17a2b8';
            break;
    }

    let h5 = document.createElement('h5');
    h5.style.cssText = 'margin:5px 0 5px 0;text-transform:uppercase;font-weight:300;color:#fff;';
    h5.innerHTML = title;

    let closeX = document.createElement('a');
    closeX.style.cssText = 'cursor:pointer;position:absolute;right:0;top:0;color:#fff;padding:10px 15px;font-size:16px;text-decoration:none;';
    closeX.innerHTML = 'x';

    let contentDiv = document.createElement('div');
    contentDiv.innerHTML = content;

    let section = document.createElement('section');
    section.classList.add('notifyTop');
    section.style.cssText = `z-index:11500;position:fixed;padding:1rem;color:#fff;background:${bgColorHex};right:0;left:0;top:0`;
    section.appendChild(h5);
    section.appendChild(closeX);
    section.appendChild(contentDiv);
    document.body.appendChild(section);

    const notifyHeight = section.offsetHeight;
    section.style.top = (-notifyHeight) + 'px';

    let top = -notifyHeight;
    let interv = setInterval(function () {
        top += 10;
        section.style.top = top + 'px';
        if (top > -10) {
            clearInterval(interv);
        }
    }, velocity);

    let close = function () {
        closeX.style.display = 'none';
        let interv_ = setInterval(function () {
            top -= 10;
            section.style.top = top + 'px';
            if (top < -notifyHeight) {
                section.parentNode?.removeChild(section);
                clearInterval(interv_);
            }
        }, velocity);
    };

    closeX.addEventListener('click', function (e) {
        e.preventDefault();
        close();
    });

    setTimeout(function () {
        close();
    }, delay);
}

function spinStop(ele, type, block)
{
    let isDisabled = ele.disabled,
        hasDisabled = ele.classList.contains('disabled'),
        attrText = ele.getAttribute('data-innerHTML');
    if (isDisabled && hasDisabled && attrText) {
        ele.disabled = false;
        ele.classList.remove('disabled');
        ele.innerHTML = attrText;
        return;
    }

    const html = ele.innerHTML;
    ele.setAttribute('data-innerHTML', html);
    let lgStyle = ele.classList.contains('btn-lg') ? ' style="position:relative;top:-2px"' : '';
    let spanHtml = `<span class="spinner-${type} spinner-${type}-sm" role="status"${lgStyle}></span>`;
    if (!block) spanHtml += ' ' + html;

    ele.innerHTML = spanHtml;
    ele.disabled = true;
    ele.classList.add('disabled');
}

if (!String.prototype.format) {
    String.prototype.format = function () {
        var args = arguments;
        return this.replace(/{(\d+)}/g, function (match, number) {
            return typeof args[number] !== 'undefined' ? args[number] : match;
        });
    };
}

Element.prototype.spinNstop = function (block = true) {
    spinStop(this, 'border', block);
};
Element.prototype.growNstop = function (block = true) {
    spinStop(this, 'grow', block);
};

const upArrow = document.createElement('i');
upArrow.style.cssText = 'border:solid black;border-width:0 2px 2px 0;border-color:white;display:inline-block;padding:3.4px;margin-top:11px;transform:rotate(-135deg);-webkit-transform:rotate(-135deg)';
const btnScroll = document.createElement('a');
btnScroll.style.cssText = 'display:inline-block;cursor:pointer;background-color:#7952b3;width:40px;height:25px;text-align:center;-webkit-border-radius:6px 6px 0px 0px;border-radius:6px 6px 0px 0px;position:fixed;right:25%;bottom:0px;transition:background-color .3s, opacity .5s, visibility .5s;opacity:0.75;visibility:hidden;z-index:10000';
btnScroll.appendChild(upArrow);
document.body.appendChild(btnScroll);
window.addEventListener('scroll', function () {
    const windowpos = document.querySelector('html').scrollTop;
    if (windowpos > 200) {
        btnScroll.style.opacity = 0.75;
        btnScroll.style.visibility = 'visible';
    } else {
        btnScroll.style.opacity = 0;
        btnScroll.style.visibility = 'hidden';
    }
});
btnScroll.addEventListener('click', function (e) {
    e.preventDefault();
    scroll({top: 0, behavior: 'smooth'});
});
btnScroll.addEventListener('mouseover', function () {
    btnScroll.style.backgroundColor = 'blue';
});
btnScroll.addEventListener('mouseout', function () {
    btnScroll.style.backgroundColor = '#7952b3';
});

document.addEventListener('DOMContentLoaded', function () {
    activateLink(window.location.pathname);
    
    const staticModal = document.getElementById('static-modal');
    let modalInitialContent = staticModal?.innerHTML;
    staticModal?.addEventListener('hidden.bs.modal', function () {
        this.innerHTML = modalInitialContent;
    });

    document.querySelectorAll('[data-bs-toggle="modal"][data-bs-target="#static-modal"]').forEach(function (link) {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            ajaxModal(link);
        });
    });
});
