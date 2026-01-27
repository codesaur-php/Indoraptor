/**
 * motable v2.5
 * ------------------------------------------------------------------
 * Энэ script нь ямар ч HTML <table>-ийг дэвшилтэт боломжтой болгож өгнө:
 *  ✔ Sticky header (толгой мөр гацдаг)
 *  ✔ Horizontal scroll илүү зөөлөн болгох
 *  ✔ Аль ч баганыг freeze/sticky position болгох
 *  ✔ Client-side search / filter
 *  ✔ Client-side sort (үсгийн болон тоон эрэмбэлэлт)
 *  ✔ Responsive scroll indicator + fade effect
 *  ✔ Монгол / Англи хэлний label-тэй
 *  ✔ lightweight ба external dependencyгүй.
 */

/**
 * motable(<table>, options) - үндсэн Constructor функц
 * -------------------------
 * - Хүснэгтийг динамикаар сайжруулж UI-г бүтээнэ
 * - Tools bar (info + search)
 * - Sticky header
 * - Scroll shadow
 * - Freeze column
 * - Sort
 * - Filter
 */
function motable(
    ele,
    opts = {
        label: {},
        style: {},
        /* freezeColumns: [0, 1, 2] */
    }
) {
    /* Table элементийг resolve хийх */
    const table = typeof ele === 'string' ? document.querySelector(ele) : ele;
    if (table?.tagName !== 'TABLE') throw new Error('motable must be an instance of the Table');

    /* Options-г default-той нэгтгэх */
    const options = this.getDefaults(opts);

    /* Tools bar үүсгэх (info + search) */
    const tools = document.createElement('div');
    tools.classList.add('motools');
    if (options.style.tools) tools.style.cssText = options.style.tools;

    /* Info text */
    const infoSpan = document.createElement('p');
    infoSpan.innerHTML = options.label.loading;
    if (options.style.info) infoSpan.style.cssText = options.style.info;

    /* Search input */
    const searchInput = document.createElement('input');
    searchInput.type = 'search';
    searchInput.disabled = true;  /* Хүснэгт хоосон үед идэвхгүй */
    searchInput.classList.add('mosearch');
    searchInput.placeholder = options.label.search;
    if (options.style.search) searchInput.style.cssText = options.style.search;

    /* 🔎 Хайлт хийх event */
    searchInput.addEventListener('input', function () {
        const rows = table.querySelector('tbody')?.getElementsByTagName('tr');
        const total = rows?.length ?? 0;
        let filtered = 0;
        const searchValue = this.value.toUpperCase();

        for (let i = 0; i < total; i++) {
            const rowContent = (rows[i].textContent || '').toUpperCase();
            const show = rowContent.indexOf(searchValue) > -1;
            rows[i].style.display = show ? '' : 'none';
            if (show) filtered++;
        }

        const infostr =
            filtered === total
                ? (total === 0 ? options.label.empty : options.label.total)
                : (filtered === 0 ? options.label.notfound : options.label.filtered);

        infoSpan.innerHTML = infostr
            .replace('{total}', total)
            .replace('{filtered}', filtered);
    }, false);

    /* Wrapper + Container үүсгэх */
    const container = document.createElement('div');
    container.classList.add('mocontainer');
    if (options.style.container) container.style = options.style.container;

    const wrapper = document.createElement('div');
    wrapper.classList.add('mowrapper');
    if (options.style.wrapper) wrapper.style.cssText += options.style.wrapper;

    this.info = infoSpan;
    this.search = searchInput;
    this.table = table;
    this.options = options;
    this.wrapper = wrapper;
    
    /* THEAD автоматаар үүсгэх хэсэг */
    if (!table.tHead && this.options.columns?.length) {
        const thead = document.createElement('thead');
        const tr = document.createElement('tr');
        this.options.columns.forEach(col => {
            const th = document.createElement('th');
            th.textContent = col.title ?? '';
            if (col.style) th.style.cssText = col.style;
            tr.appendChild(th);
        });
        thead.appendChild(tr);

        if (table.firstChild) {
            table.insertBefore(thead, table.firstChild);
        } else {
            table.appendChild(thead);
        }
    }

    /* Table-г wrapper рүү зөөж оруулах */
    table.classList.add('motable');
    table.parentNode.insertBefore(wrapper, table);

    wrapper.appendChild(tools);
    wrapper.appendChild(container);
    tools.appendChild(infoSpan);
    tools.appendChild(searchInput);
    container.appendChild(table);

    /* Sorting (толгой мөр дээр click) */
    if (table.tHead && table.tHead.rows[0]) {
        const isNumeric = (string) => /^[+-]?\d+(\.\d+)?$/.test(string);
        
        for (let i = 0; i < table.tHead.rows[0].cells.length; i++) {
            const column = table.tHead.rows[0].cells[i];
            column.addEventListener('click', function () {
                const tBody = table.querySelector('tbody');
                if (!tBody) return;
                const rows = Array.from(tBody.querySelectorAll('tr'));
                if (!rows.length) return;

                /* sort төлөв toggle хийх */
                if (!this.dataset.sort) this.dataset.sort = 'asc';
                else this.dataset.sort = this.dataset.sort === 'asc' ? 'desc' : 'asc';

                const ascending = this.dataset.sort === 'asc';

                let sorting = false;
                const sorted = rows.sort((a, b) => {
                    const atext = a.cells[i]?.textContent ?? '';
                    const btext = b.cells[i]?.textContent ?? '';
                    if (atext === btext) return 0;

                    sorting = true;

                    if (isNumeric(atext) && isNumeric(btext))
                        return ascending ? atext - btext : btext - atext;

                    return atext > btext ? (ascending ? 1 : -1) : (ascending ? -1 : 1);
                });

                tBody.innerHTML = '';
                tBody.append(...sorted);

                /* Бусад баганаас data-sort-ийг цэвэрлэнэ */
                for (let j = 0; j < table.tHead.rows[0].cells.length; j++) {
                    if (i !== j || !sorting)
                        delete table.tHead.rows[0].cells[j].dataset.sort;
                }
            }, false);
        }
    }

    /* Scroll indicator update */
    this.updateScrollable();
    wrapper.addEventListener('scroll', () => this.updateScrollable());

    /* Window resize үед frozen columns-г дахин тооцно */
    window.addEventListener('resize', () => {
        this.updateScrollable();
        if (this.options.freezeColumns?.length) {
            this.applyFreezeColumns();
        }
    });

    /* Body-г эхлүүлэх */
    this.setBody();
}

/* setBody(html) - tbody-г шинэчлэх */
motable.prototype.setBody = function (html) {
    let tBody = this.table.querySelector('tbody');

    if (!tBody) {
        tBody = document.createElement('tbody');
        this.table.appendChild(tBody);
    }

    if (this.options.style.tbody) tBody.style.cssText = this.options.style.tbody;
    if (html) tBody.innerHTML = html;

    /* Хоосон эсвэл мөртэй ч байсан setReady дуудах */
    this.setReady();
};

/* setReady() - хүснэгт бүрэн ачаалсны дараах ажилбар */
motable.prototype.setReady = function () {
    const tBody = this.table.querySelector('tbody');
    const total = tBody?.rows.length ?? 0;
    const filtered = tBody?.querySelectorAll('tr:not([style*="display: none"])')?.length ?? 0;
    const infostr =
        filtered === total
            ? (total === 0 ? this.options.label.empty : this.options.label.total)
            : (filtered === 0 ? this.options.label.notfound : this.options.label.filtered);
            
    this.info.innerHTML =
        infostr.replace('{total}', total).replace('{filtered}', filtered);

    /* Search input-г идэвхжүүлэх */
    if (this.search.disabled && total > 0) this.search.disabled = false;

    /* Freeze columns тохируулна */
    if (this.options.freezeColumns?.length) {
        requestAnimationFrame(() => this.applyFreezeColumns());
    }
};

/* error(msg) - info дээр алдаа харуулах */
motable.prototype.error = function (message) {
    this.info.innerHTML = `<span style="color:red">${message}<span>`;
};

/* updateScrollable() - scroll shadow toggle */
motable.prototype.updateScrollable = function () {
    if (!this.wrapper) return;

    const el = this.wrapper;
    const hasOverflow = el.scrollWidth > el.clientWidth + 1;
    const atEnd = el.scrollLeft >= (el.scrollWidth - el.clientWidth - 1);

    el.classList.toggle('scrollable', hasOverflow && !atEnd);
};

/* applyFreezeColumns() - дурын багануудыг sticky болгох */
motable.prototype.applyFreezeColumns = function () {
    const freeze = this.options.freezeColumns;
    if (!freeze?.length) return;

    const table = this.table;
    const headRow = table.tHead?.rows[0];
    const body = table.tBodies[0];
    if (!headRow || !body) return;

    /* Өмнө байсан sticky class-уудыг цэвэрлэнэ */
    table.querySelectorAll('.freeze-col').forEach(cell => {
        cell.classList.remove('freeze-col', 'freeze-col-shadow');
        cell.style.left = '';
    });

    /* Unique + зөв индексүүдийг сонгох */
    const cols = [...new Set(freeze)]
        .filter(i => Number.isInteger(i) && i >= 0 && i < headRow.cells.length)
        .sort((a, b) => a - b);

    /* Багануудын өргөнийг тооцох */
    const firstRow = body.rows[0];
    if (!firstRow) return;

    const colWidths = [];
    for (let i = 0; i < headRow.cells.length; i++) {
        const cell = firstRow.cells[i] || headRow.cells[i];
        colWidths[i] = cell.getBoundingClientRect().width;
    }

    /* Freeze-дэх offset */
    let leftOffset = 0;

    cols.forEach((colIndex, idx) => {
        const th = headRow.cells[colIndex];
        if (!th) return;

        th.classList.add('freeze-col');
        if (idx === cols.length - 1) th.classList.add('freeze-col-shadow');
        th.style.left = leftOffset + 'px';

        Array.from(body.rows).forEach(row => {
            const td = row.cells[colIndex];
            if (td) {
                td.classList.add('freeze-col');
                if (idx === cols.length - 1) td.classList.add('freeze-col-shadow');
                td.style.left = leftOffset + 'px';
            }
        });

        leftOffset += colWidths[colIndex];
    });
};

/* getDefaults(options) - default утгууд */
motable.prototype.getDefaults = function (options) {
    if (!options) options = {};
    if (!options.label) options.label = {};

    /* Монгол хэл дээрх label-ууд */
    if (document.documentElement.lang === 'mn') {
        if (!options.label.loading) options.label.loading = 'Хүснэгтийг ачаалж байна <span class="threedots"></span>';
        if (!options.label.empty) options.label.empty = 'Хүснэгтэд мэдээлэл байхгүй';
        if (!options.label.total) options.label.total = 'Хүснэгтэд нийт {total} мөр бичлэг байна';
        if (!options.label.filtered) options.label.filtered = 'Нийт {total} бичлэгээс <strong>{filtered}</strong> мөр бичлэг харуулж байна';
        if (!options.label.search) options.label.search = 'Хүснэгтээс хайх утгаа оруулна уу ...';
        if (!options.label.notfound) options.label.notfound = '<span style="color:gray">Хайлтын утгад тохирох үр дүн олдсонгүй</span>';
    } else {
        /* English labels */
        if (!options.label.loading) options.label.loading = 'Loading table <span class="threedots"></span>';
        if (!options.label.empty) options.label.empty = 'There is no data in the table';
        if (!options.label.total) options.label.total = 'The table has a total of {total} rows of records';
        if (!options.label.filtered) options.label.filtered = 'Showing <strong>{filtered}</strong> out of {total} total rows';
        if (!options.label.search) options.label.search = 'Search within table ...';
        if (!options.label.notfound) options.label.notfound = '<span style="color:gray">No results were found matching your search criteria</span>';
    }

    /* Style defaults - CSS class ашиглаж байгаа тул inline style багасгав */
    if (!options.style) options.style = {};
    /* Доорх style-үүд нь хоосон байж болно, CSS class-аар удирдагдана */
    if (!options.style.tools) options.style.tools = '';
    if (!options.style.info) options.style.info = '';
    if (!options.style.search) options.style.search = '';
    if (!options.style.container) options.style.container = '';
    if (!options.style.table) options.style.table = '';
    if (!options.style.tbody) options.style.tbody = '';

    if (!options.freezeColumns) options.freezeColumns = [];

    return options;
};

/* Global export */
window.motable = motable;
