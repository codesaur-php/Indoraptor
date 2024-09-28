const mostyle = document.createElement('style');
mostyle.innerHTML = `@keyframes l1{to{clip-path:inset(0 -34% 0 0)}}.threedots{display:inline-block;width:.5rem;aspect-ratio:4;background:radial-gradient(circle closest-side,currentcolor 90%,#0000) 0/calc(100%/3) 100% space;clip-path:inset(0 100% 0 0);animation:l1 1s steps(4) infinite}`;
mostyle.innerHTML += `.motable thead th:after{content:'';float:right;margin-top:.2rem;margin-right:.5rem;border-width:0 .275rem .275rem;border-style:solid;border-color:#404040 transparent;visibility:hidden;-ms-user-select:none;-webkit-user-select:none;-moz-user-select:none;user-select:none}.motable thead th:hover:after{visibility:visible}.motable thead th[data-sort]:after{visibility:visible}.motable thead th[data-sort=desc]::after{border-bottom:none;border-width:.275rem .275rem 0}.motable thead th{cursor:pointer}`;
document.head.appendChild(mostyle);

function motable(
    ele,
    opts = {
        //columns: [
            //{
            //    title: 'name',
            //    style: 'width:8rem'
            //},
        //],
        label: {
            loading: '',
            empty: '',
            total: '',
            filtered: '',
            search: '',
            notfound: ''
        },
        style: {
            wrapper: '',
            tools: '',
            info: '',
            search: '',
            table: '',
            tbody: ''
        }
    }
) {
    let table = typeof ele === 'string' ? document.querySelector(ele) : ele;
    if (table?.tagName !== 'TABLE') throw new Error('motable must be an instance of the Table');

    let options = this.getDefaults(opts);

    let tools = document.createElement('div');
    tools.classList.add('motools');
    if (options.style.tools) tools.style.cssText = options.style.tools;

    let infoSpan = document.createElement('p');
    infoSpan.innerHTML = options.label.loading;
    if (options.style.info) infoSpan.style.cssText = options.style.info;

    let searchInput = document.createElement('input');
    searchInput.type = 'search';
    searchInput.disabled = true;
    searchInput.placeholder = options.label.search;
    if (options.style.search) searchInput.style.cssText = options.style.search;            
    searchInput.addEventListener('input', function () {
        let rows = table.querySelector('tbody')?.getElementsByTagName('tr');
        let filtered = 0;
        let total = rows?.length ?? 0;
        let searchValue = this.value.toUpperCase();
        for (let i = 0; i < total; i++) {
            let rowContent = (rows[i].textContent || '').toUpperCase();
            if (rowContent.indexOf(searchValue) > -1) {
                rows[i].style.display = '';
                filtered++;
            } else {
                rows[i].style.display = 'none';
            }
        }
        let infostr = filtered === total ?
            (total === 0 ? options.label.empty : options.label.total)
            : (filtered === 0 ? options.label.notfound : options.label.filtered);
        infoSpan.innerHTML = infostr.replace('{total}', total).replace('{filtered}', filtered);
    }, false);

    let container = document.createElement('div');
    container.classList.add('mocontainer');
    if (options.style.container) container.style = options.style.container;

    if (options.columns && options.columns.length) {
        let header = table.createTHead();
        let row = header.insertRow(0);
        options.columns.forEach(column => {
            let th = document.createElement('th');
            th.innerHTML = column.title ?? '';
            th.scope = 'col';
            if (column.style) {
                th.style = column.style;
            }
            if (column.sort) {
                th.dataset.sort = column.sort;
            }
            row.appendChild(th);
        });
    }

    let wrapper = document.createElement('div');
    wrapper.classList.add('mowrapper');
    if (options.style.wrapper) wrapper.style.cssText = options.style.wrapper;

    this.info = infoSpan;
    this.search = searchInput;
    this.table = table;
    this.options = options;

    table.classList.add('motable');
    table.parentNode.insertBefore(wrapper, table);
    if (options.style.table) table.style.cssText += options.style.table;

    wrapper.appendChild(tools);
    wrapper.appendChild(container);

    tools.appendChild(this.info);
    tools.appendChild(this.search);
    container.appendChild(this.table);

    if (table.tHead || table.tHead.rows[0]) {
        const isNumeric = (string) => /^[+-]?\d+(\.\d+)?$/.test(string);
        for (let i = 0; i < table.tHead.rows[0].cells.length; i++) {
            let column = table.tHead.rows[0].cells[i];
            column.addEventListener('click', function () {
                let tBody = table.querySelector('tbody');
                if (!tBody) return;
                let rows = Array.from(tBody.querySelectorAll('tr'));
                if (!rows.length) return;

                if (!this.dataset.sort) {
                    this.dataset.sort = 'asc';
                } else {
                    this.dataset.sort = this.dataset.sort === 'asc' ? 'desc' : 'asc';
                }
                let ascending = this.dataset.sort === 'asc';

                let sorting = false;
                let sorted = rows.sort((a, b) => {
                    let atext = a.cells[i].textContent ?? '';
                    let btext = b.cells[i].textContent ?? '';
                    if (atext === btext) return 0;

                    sorting = true;

                    if (isNumeric(atext) && isNumeric(btext))
                        return ascending ? parseFloat(atext) - parseFloat(btext) : parseFloat(btext) - parseFloat(atext);

                    return atext > btext ? ascending ? 1 : -1 : ascending ? -1 : 1;
                });
                tBody.innerHTML = '';
                tBody.append(...sorted);

                for (let j = 0; j < table.tHead.rows[0].cells.length; j++) {
                    if (i !== j || !sorting) delete table.tHead.rows[0].cells[j].dataset.sort;
                }
            }, false);
        }
    }

    this.setBody();
}

motable.prototype.setBody = function (html) {
    let tBody = this.table.querySelector('tbody');
    if (!tBody) {
        tBody = document.createElement('tbody');
        this.table.appendChild(tBody);
    }
    if (this.options.style.tbody) tBody.style.cssText = this.options.style.tbody;
    if (html) tBody.innerHTML = html;
    if (tBody.querySelector('tr')) this.setReady();
};

motable.prototype.setReady = function () {
    let tBody = this.table.querySelector('tbody');
    let total = tBody?.getElementsByTagName('tr')?.length ?? 0;
    let filtered = tBody?.querySelectorAll('tr:not([style*="display: none"])')?.length ?? 0;
    let infostr = filtered === total ?
        (total === 0 ? this.options.label.empty : this.options.label.total)
        : (filtered === 0 ? this.options.label.notfound : this.options.label.filtered);
    this.info.innerHTML = infostr.replace('{total}', total).replace('{filtered}', filtered);

    if (this.search.disabled && total > 0) {
        this.search.disabled = false;
    }
};

motable.prototype.error = function (message) {
    this.info.innerHTML = `<span style="color:red">${message}<span>`;
};

motable.prototype.getDefaults = function (options) {
    if (!options.label) options.label = {};            
    if (document.documentElement.lang === 'mn') {
        if (!options.label.loading) options.label.loading = 'Хүснэгтийг ачаалж байна <span class="threedots"></span>';
        if (!options.label.empty) options.label.empty = 'Хүснэгтэд мэдээлэл байхгүй';
        if (!options.label.total) options.label.total = 'Хүснэгтэд нийт {total} мөр бичлэг байна';
        if (!options.label.filtered) options.label.filtered = 'Нийт {total} бичлэгээс <strong>{filtered}</strong> мөр бичлэг харуулж байна';
        if (!options.label.search) options.label.search = 'Хүснэгтээс хайх утгаа оруулна уу ...';
        if (!options.label.notfound) options.label.notfound = '<span style="color:gray">Хайлтын утгад тохирох үр дүн олдсонгүй</span>';
    } else {
        if (!options.label.loading) options.label.loading = 'Loading table <span class="threedots"></span>';
        if (!options.label.empty) options.label.empty = 'There is no data in the table';
        if (!options.label.total) options.label.total = 'The table has a total of {total} rows of records';
        if (!options.label.filtered) options.label.filtered = 'Showing <strong>{filtered}</strong> out of {total} total rows';
        if (!options.label.search) options.label.search = 'Search within table ...';
        if (!options.label.notfound) options.label.notfound = '<span style="color:gray">No results were found matching your search criteria</span>';
    }

    if (!options.style) options.style = {};
    if (!options.style.tools) options.style.tools = 'display:flex;flex-wrap:wrap;margin:0 0 .375rem;';
    if (!options.style.info) options.style.info = 'flex-basis:65%;margin:auto 0;padding-right:1rem;';
    if (!options.style.search) options.style.search = 'flex-basis:35%;margin:auto 0;display:block;width:100%;padding:.275rem;border:1px solid;border-radius:.2rem;';
    if (!options.style.container) options.style.container = 'overflow-x:auto;-webkit-overflow-scrolling:touch';
    if (!options.style.table) options.style.table = 'margin-bottom:0;';
    if (!options.style.tbody) options.style.tbody = 'border-top:0.1rem solid currentcolor';

    return options;
};
