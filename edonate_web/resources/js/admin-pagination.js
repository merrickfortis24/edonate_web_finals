/*
 * Shared pagination renderer for admin tables that load their rows through
 * JSON endpoints. Server-rendered tables use the Blade component with the
 * same .admin-pagination markup and Bootstrap page-link styling.
 */
(function () {
    'use strict';

    function number(value, fallback) {
        var parsed = Number(value);
        return Number.isFinite(parsed) ? parsed : fallback;
    }

    function normalizeMeta(meta) {
        var source = meta && typeof meta === 'object' ? meta : {};
        var total = Math.max(0, Math.floor(number(source.total, 0)));
        var perPage = Math.max(1, Math.floor(number(source.per_page, 10)));
        var lastPage = Math.max(1, Math.floor(number(source.last_page, Math.ceil(total / perPage) || 1)));
        var currentPage = Math.min(lastPage, Math.max(1, Math.floor(number(source.current_page, 1))));
        var from = total > 0 ? Math.max(1, Math.floor(number(source.from, ((currentPage - 1) * perPage) + 1))) : 0;
        var to = total > 0 ? Math.min(total, Math.max(from, Math.floor(number(source.to, currentPage * perPage)))) : 0;

        return {
            current_page: currentPage,
            last_page: lastPage,
            per_page: perPage,
            total: total,
            from: from,
            to: to,
        };
    }

    function pageItems(currentPage, lastPage) {
        if (lastPage <= 7) {
            var allPages = [];
            for (var page = 1; page <= lastPage; page += 1) {
                allPages.push(page);
            }
            return allPages;
        }

        var pages = [1];
        var start = Math.max(2, currentPage - 1);
        var end = Math.min(lastPage - 1, currentPage + 1);

        if (start > 2) {
            pages.push('ellipsis-left');
        }

        for (var pageIndex = start; pageIndex <= end; pageIndex += 1) {
            pages.push(pageIndex);
        }

        if (end < lastPage - 1) {
            pages.push('ellipsis-right');
        }

        pages.push(lastPage);
        return pages;
    }

    function createItem(label, page, options, onPage) {
        var settings = options || {};
        var item = document.createElement('li');
        item.className = 'page-item';

        if (settings.disabled || settings.active || settings.ellipsis) {
            if (settings.disabled) item.classList.add('disabled');
            if (settings.active) item.classList.add('active');
        }

        if (settings.ellipsis) {
            var ellipsis = document.createElement('span');
            ellipsis.className = 'page-link';
            ellipsis.textContent = '…';
            ellipsis.setAttribute('aria-hidden', 'true');
            item.appendChild(ellipsis);
            return item;
        }

        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'page-link';
        button.textContent = label;
        button.disabled = Boolean(settings.disabled);
        button.setAttribute('aria-label', settings.ariaLabel || ('Page ' + label));

        if (settings.active) {
            button.setAttribute('aria-current', 'page');
        }

        if (!settings.disabled) {
            button.dataset.page = String(page);
        }

        if (!settings.disabled && typeof onPage === 'function') {
            button.addEventListener('click', function () {
                onPage(page);
            });
        }

        item.appendChild(button);
        return item;
    }

    function render(linksElement, meta, onPage, options) {
        var settings = options || {};
        var normalized = normalizeMeta(meta);
        var infoElement = settings.infoElement || null;

        if (infoElement) {
            infoElement.textContent = 'Showing ' + normalized.from + ' to ' + normalized.to + ' of ' + normalized.total + ' entries';
        }

        if (!linksElement) {
            return normalized;
        }

        linksElement.innerHTML = '';
        linksElement.classList.add('admin-pagination__links');

        if (normalized.total <= 0 || normalized.last_page <= 1) {
            return normalized;
        }

        var list = document.createElement('ul');
        list.className = 'pagination mb-0';

        list.appendChild(createItem('Previous', normalized.current_page - 1, {
            disabled: normalized.current_page <= 1,
            ariaLabel: 'Previous page',
        }, onPage));

        pageItems(normalized.current_page, normalized.last_page).forEach(function (item) {
            if (typeof item === 'string') {
                list.appendChild(createItem('…', null, { ellipsis: true }, onPage));
                return;
            }

            list.appendChild(createItem(String(item), item, {
                active: item === normalized.current_page,
                ariaLabel: 'Page ' + item,
            }, onPage));
        });

        list.appendChild(createItem('Next', normalized.current_page + 1, {
            disabled: normalized.current_page >= normalized.last_page,
            ariaLabel: 'Next page',
        }, onPage));

        linksElement.appendChild(list);
        return normalized;
    }

    window.eDonateAdminPagination = {
        normalizeMeta: normalizeMeta,
        render: render,
    };
}());
