document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.site-header').forEach(function (header) {
        var toggle = header.querySelector('.header-menu-toggle');
        var panel = header.querySelector('.header-navigation');
        if (!toggle || !panel) {
            return;
        }
        var smallScreen = window.matchMedia('(max-width: 860px)');
        function setMenuOpen(open, restoreFocus) {
            header.classList.toggle('header-menu-open', open);
            toggle.setAttribute('aria-expanded', String(open));
            if (!open) {
                panel.querySelectorAll('.submenu-open > .submenu-toggle').forEach(function (button) {
                    button.click();
                });
            }
            if (restoreFocus) {
                toggle.focus();
            }
        }
        toggle.hidden = false;
        header.classList.add('header-menu-enhanced');
        toggle.addEventListener('click', function () {
            setMenuOpen(toggle.getAttribute('aria-expanded') !== 'true', false);
        });
        header.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && smallScreen.matches && header.classList.contains('header-menu-open')) {
                event.preventDefault();
                setMenuOpen(false, true);
            }
        });
        header.addEventListener('focusout', function (event) {
            if (event.relatedTarget && !header.contains(event.relatedTarget)) {
                setMenuOpen(false, false);
            }
        });
        panel.addEventListener('click', function (event) {
            if (smallScreen.matches && event.target.closest('a')) {
                setMenuOpen(false, false);
            }
        });
        document.addEventListener('click', function (event) {
            if (smallScreen.matches && !header.contains(event.target)) {
                setMenuOpen(false, false);
            }
        });
        smallScreen.addEventListener('change', function () {
            var panelHadFocus = panel.contains(document.activeElement);
            setMenuOpen(false, smallScreen.matches && panelHadFocus);
        });
    });

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
                if (event.pointerType === 'mouse' && window.matchMedia('(min-width: 861px)').matches) {
                    setOpen(true);
                }
            });
            item.addEventListener('pointerleave', function (event) {
                if (event.pointerType === 'mouse' && window.matchMedia('(min-width: 861px)').matches && !item.contains(document.activeElement)) {
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
