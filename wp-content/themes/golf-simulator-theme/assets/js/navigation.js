document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.site-nav').forEach(function (nav, navIndex) {
        var entries = [];

        nav.querySelectorAll('li').forEach(function (item, itemIndex) {
            var submenu = Array.from(item.children).find(function (child) {
                return child.classList.contains('sub-menu');
            });
            if (!submenu) {
                return;
            }

            var link = Array.from(item.children).find(function (child) {
                return child.tagName === 'A';
            });
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'submenu-toggle';
            // The adjacent link provides the translated menu item label.
            button.setAttribute('aria-label', link ? link.textContent.trim() : 'Menu');
            submenu.id = submenu.id || 'site-submenu-' + navIndex + '-' + itemIndex;
            button.setAttribute('aria-controls', submenu.id);
            button.setAttribute('aria-expanded', 'false');
            item.insertBefore(button, submenu);

            function setOpen(open) {
                item.classList.toggle('submenu-open', open);
                button.setAttribute('aria-expanded', String(open));
                if (!open) {
                    entries.forEach(function (entry) {
                        if (submenu.contains(entry.item)) {
                            entry.setOpen(false);
                        }
                    });
                }
            }

            entries.push({ item: item, button: button, setOpen: setOpen });
            button.addEventListener('click', function () {
                var open = button.getAttribute('aria-expanded') !== 'true';
                entries.forEach(function (entry) {
                    if (entry.item !== item && entry.item.parentElement === item.parentElement) {
                        entry.setOpen(false);
                    }
                });
                setOpen(open);
            });
            item.addEventListener('pointerenter', function (event) {
                if (event.pointerType === 'mouse') {
                    setOpen(true);
                }
            });
            item.addEventListener('pointerleave', function (event) {
                if (event.pointerType === 'mouse' && !item.contains(document.activeElement)) {
                    setOpen(false);
                }
            });
            item.addEventListener('focusout', function (event) {
                if (!item.contains(event.relatedTarget)) {
                    setOpen(false);
                }
            });
            item.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && item.classList.contains('submenu-open')) {
                    event.stopPropagation();
                    setOpen(false);
                    button.focus();
                }
            });
        });

        nav.classList.add('navigation-enhanced');
        document.addEventListener('click', function (event) {
            if (!nav.contains(event.target)) {
                entries.forEach(function (entry) { entry.setOpen(false); });
            }
        });
    });
});
