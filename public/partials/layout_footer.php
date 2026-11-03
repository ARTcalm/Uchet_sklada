<?php
declare(strict_types=1);
?>
    </main>

<script src="assets/app.js"></script>
<script>
    App.init(
        <?= json_encode($user, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>,
        <?= json_encode($csrf, JSON_HEX_TAG) ?>
    );

    // Выезжающее меню
    (function () {
        const toggle   = document.getElementById('menu-toggle');
        const drawer   = document.getElementById('drawer');
        const backdrop = document.getElementById('drawer-backdrop');
        const closeBtn = document.getElementById('drawer-close');

        const open  = () => {
            drawer.classList.add('open');
            backdrop.classList.add('open');
            document.body.classList.add('drawer-open');
        };
        const close = () => {
            drawer.classList.remove('open');
            backdrop.classList.remove('open');
            document.body.classList.remove('drawer-open');
        };

        toggle.addEventListener('click', () => {
            drawer.classList.contains('open') ? close() : open();
        });
        backdrop.addEventListener('click', close);
        closeBtn.addEventListener('click', close);
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
    })();

    // Тёмная тема
    (function () {
        const btn  = document.getElementById('theme-toggle');
        const icon = btn ? btn.querySelector('.theme-icon') : null;

        function currentTheme() {
            return document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
        }
        function apply(theme) {
            if (theme === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
                if (icon) icon.textContent = '☀️';
            } else {
                document.documentElement.removeAttribute('data-theme');
                if (icon) icon.textContent = '🌙';
            }
            try { localStorage.setItem('theme', theme); } catch (e) {}
        }

        if (icon) icon.textContent = currentTheme() === 'dark' ? '☀️' : '🌙';

        btn && btn.addEventListener('click', () => {
            apply(currentTheme() === 'dark' ? 'light' : 'dark');
        });
    })();

    // Уведомления
    (function () {
        const wrap     = document.getElementById('notif-wrap');
        const toggle   = document.getElementById('notif-toggle');
        const popover  = document.getElementById('notif-popover');
        const badge    = document.getElementById('notif-badge');
        const list     = document.getElementById('notif-list');
        const countLbl = document.getElementById('notif-count-label');
        if (!wrap || !toggle) return;

        let loaded = false;

        function renderList(items) {
            if (!items.length) {
                list.innerHTML = '<div class="notif-empty">Нет уведомлений 🎉</div>';
                return;
            }
            list.innerHTML = items.map(it => `
                <a class="notif-item severity-${it.severity || 'info'}" href="${it.link || '#'}">
                    <div class="notif-item-title">${App.esc(it.title)}</div>
                    ${it.subtitle ? `<div class="notif-item-sub">${App.esc(it.subtitle)}</div>` : ''}
                </a>
            `).join('');
        }

        async function loadNotifications() {
            try {
                const data = await App.apiGet('api/notifications.php');
                const count = data.count || 0;
                if (count > 0) {
                    badge.style.display = '';
                    badge.textContent = count > 99 ? '99+' : String(count);
                    countLbl.textContent = `(${count})`;
                } else {
                    badge.style.display = 'none';
                    countLbl.textContent = '';
                }
                renderList(data.items || []);
                loaded = true;
            } catch (e) {
                list.innerHTML = `<div class="notif-empty">${App.esc(e.message)}</div>`;
            }
        }

        function openPopover() {
            popover.classList.add('open');
            if (!loaded) loadNotifications();
        }
        function closePopover() {
            popover.classList.remove('open');
        }

        toggle.addEventListener('click', (e) => {
            e.stopPropagation();
            popover.classList.contains('open') ? closePopover() : openPopover();
        });
        document.addEventListener('click', (e) => {
            if (!wrap.contains(e.target)) closePopover();
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closePopover();
        });

        // Автозагрузка счётчика при старте
        loadNotifications();

        // Обновление раз в 60 секунд
        setInterval(loadNotifications, 60000);
    })();
</script>
</body>
</html>