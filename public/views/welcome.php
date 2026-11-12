<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/helpers.php';
require_once __DIR__ . '/../../src/auth.php';

$isLogged = is_logged_in();
$btnLink  = $isLogged ? '?page=menu' : '?page=login';
$btnText  = $isLogged ? 'Перейти в склад' : 'Войти в склад';
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="yandex-verification" content="5e800c5e5667a52a" />
    <title>Склад — учёт товаров без Excel</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        /* --- Глобальные настройки страницы --- */
        html { scroll-behavior: smooth; }
        body { padding-top: 72px; }
        
        /* --- Топбар --- */
        .topbar {
            position: fixed;
            top: 0; left: 0; right: 0;
            height: 72px;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 40px;
            transition: background 0.3s;
        }
        html[data-theme="dark"] .topbar { background: rgba(30, 41, 59, 0.85); }
        section {scroll-margin-top: 90px;}
        .logo {
            display: flex; align-items: center; gap: 10px;
            font-weight: 700; font-size: 20px;
            color: var(--text); text-decoration: none; letter-spacing: -0.02em;
            z-index: 2;
        }
        .logo svg { width: 32px; height: 32px; color: var(--primary); }
        
        /* --- Навигация с анимацией --- */
        .nav-links {
            position: relative;
            display: flex;
            gap: 8px;
            align-items: center;
            background: var(--bg); /* Легкий фон для контейнера, чтобы кнопки выделялись */
            padding: 6px;
            border-radius: 40px;
            border: 1px solid var(--border);
            z-index: 1;
        }
        html[data-theme="dark"] .nav-links { background: #0f172a; }

        /* Подложка, которая будет ездить */
        .nav-indicator {
            position: absolute;
            top: 6px;
            bottom: 6px;
            left: 0;
            background: var(--primary);
            border-radius: 30px;
            transition: transform 0.3s cubic-bezier(0.25, 1, 0.5, 1), width 0.3s cubic-bezier(0.25, 1, 0.5, 1);
            z-index: -1;
            box-shadow: 0 4px 12px rgba(79,70,229,.3);
            pointer-events: none;
            opacity: 0; /* Скрыта, пока не загрузится JS */
        }

        .nav-links a {
            position: relative;
            color: var(--text-muted);
            font-weight: 500;
            font-size: 15px;
            text-decoration: none;
            padding: 8px 20px;
            border-radius: 30px;
            transition: color 0.3s ease;
            z-index: 2;
            white-space: nowrap;
        }
        
        .nav-links a:hover { color: var(--text); }
        .nav-links a.active { color: #ffffff !important; }
        
        .topbar-actions {
            display: flex; align-items: center; gap: 16px; z-index: 2;
        }
        .theme-btn {
            background: transparent; border: 1px solid var(--border);
            width: 40px; height: 40px; border-radius: 50%;
            cursor: pointer; display: flex; align-items: center; justify-content: center;
            font-size: 18px; transition: all 0.2s;
        }
        .theme-btn:hover { background: var(--primary-soft); border-color: var(--primary); }

        /* --- Контент лендинга --- */
        .landing-main { max-width: 1400px; margin: 0 auto; padding: 60px 15px; text-align: center; }
        .landing-title { font-size: 48px; font-weight: 700; letter-spacing: -0.03em; margin-bottom: 20px; line-height: 1.15; }
        .landing-subtitle { font-size: 18px; color: var(--text-muted); margin-bottom: 40px; max-width: 700px; margin-left: auto; margin-right: auto; }
        .landing-cta { display: flex; gap: 16px; justify-content: center; margin-bottom: 80px; }
        .btn-large { padding: 14px 32px; font-size: 16px; border-radius: var(--radius); }
        
        /* Преимущества */
        .features { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 24px; text-align: left; margin-bottom: 80px; }
        .feature-card { background: var(--bg-elevated); padding: 28px; border-radius: var(--radius); border: 1px solid var(--border); box-shadow: var(--shadow-sm); transition: transform 0.2s; }
        .feature-card:hover { transform: translateY(-4px); box-shadow: var(--shadow); }
        .feature-icon { font-size: 32px; margin-bottom: 16px; }
        .feature-title { font-size: 18px; font-weight: 600; margin-bottom: 8px; }
        .feature-text { font-size: 14px; color: var(--text-muted); line-height: 1.6; }

        /* Галерея интерфейса */
        .gallery { margin-bottom: 100px; }
        .gallery-tabs { display: flex; justify-content: center; gap: 10px; margin-bottom: 30px; flex-wrap: wrap; }
        .gallery-tab { padding: 10px 24px; border-radius: 30px; border: 1px solid var(--border); background: var(--bg-elevated); color: var(--text); cursor: pointer; font-size: 14px; font-weight: 500; transition: all 0.2s; }
        .gallery-tab.active { background: var(--primary); color: #fff; border-color: var(--primary); box-shadow: 0 4px 12px rgba(79,70,229,.3); }
        .gallery-tab:hover:not(.active) { border-color: var(--primary); color: var(--primary); }
        .gallery-window { position: relative; border-radius: var(--radius); overflow: hidden; box-shadow: var(--shadow-lg); border: 1px solid var(--border); background: var(--bg-elevated); display: flex; justify-content: center; align-items: center; min-height: 400px; }
        .gallery-img { width: 100%; height: auto; display: none; object-fit: contain; }
        .gallery-img.active { display: block; animation: fadeIn 0.4s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        /* Тарифы */
        .pricing { display: flex; justify-content: center; gap: 24px; margin-top: 40px; margin-bottom: 100px; flex-wrap: wrap; }
        .price-card { background: var(--bg-elevated); border: 1px solid var(--border); border-radius: var(--radius); padding: 36px 28px; width: 320px; text-align: center; box-shadow: var(--shadow); transition: transform 0.2s; }
        .price-card:hover { transform: translateY(-4px); }
        .price-card.popular { border-color: var(--primary); box-shadow: 0 10px 30px rgba(79,70,229,.15); transform: scale(1.05); z-index: 2; position: relative; }
        .price-card.popular:hover { transform: scale(1.05) translateY(-4px); }
        .price-title { font-size: 18px; font-weight: 600; margin-bottom: 12px; }
        .price-value { font-size: 42px; font-weight: 700; color: var(--primary); margin-bottom: 24px; letter-spacing: -0.02em; }
        .price-value span { font-size: 16px; color: var(--text-muted); font-weight: 400; letter-spacing: 0; }
        .price-list { list-style: none; padding: 0; margin: 0 0 28px 0; font-size: 14px; color: var(--text-muted); text-align: left; }
        .price-list li { margin-bottom: 12px; padding-left: 24px; position: relative; }
        .price-list li::before { content: '✓'; position: absolute; left: 0; color: var(--success); font-weight: bold; }

        /* Отзывы */
        .testimonials { margin-bottom: 100px; }
        .testimonial-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px; text-align: left; margin-top: 30px; }
        .testimonial { background: var(--bg-elevated); padding: 28px; border-radius: var(--radius); border: 1px solid var(--border); box-shadow: var(--shadow-sm); }
        .testimonial-text { font-size: 15px; font-style: italic; margin-bottom: 20px; color: var(--text); line-height: 1.6; }
        .testimonial-author { font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 12px; }
        .testimonial-avatar { width: 44px; height: 44px; border-radius: 50%; background: var(--primary-soft); color: var(--primary); display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 18px; }

        /* FAQ */
        .faq { max-width: 900px; margin: 0 auto 100px; text-align: left; }
        .faq-item { border-bottom: 1px solid var(--border); padding: 24px 0; }
        .faq-question { font-size: 17px; font-weight: 600; cursor: pointer; display: flex; justify-content: space-between; align-items: center; transition: color 0.2s; }
        .faq-question:hover { color: var(--primary); }
        .faq-answer { font-size: 15px; color: var(--text-muted); margin-top: 16px; line-height: 1.6; display: none; }
        .faq-item.open .faq-answer { display: block; animation: fadeIn 0.3s; }
        .faq-icon { font-size: 20px; font-weight: 400; }

        /* Футер */
        .landing-footer { border-top: 1px solid var(--border); padding: 40px 20px; text-align: center; color: var(--text-muted); font-size: 13px; background: var(--bg-elevated); }
        .footer-links { display: flex; justify-content: center; gap: 24px; margin-bottom: 20px; flex-wrap: wrap; }
        .footer-links a { color: var(--text-muted); text-decoration: none; transition: color 0.2s; }
        .footer-links a:hover { color: var(--primary); }

        @media (max-width: 900px) {
            .nav-links { display: none; }
        }
        @media (max-width: 768px) {
            .landing-title { font-size: 32px; }
            .landing-cta { flex-direction: column; }
            .price-card.popular { transform: none; }
            .price-card.popular:hover { transform: translateY(-4px); }
            .topbar { padding: 0 20px; }
        }
    </style>
    <!-- Yandex.Metrika counter -->
    <script type="text/javascript">
        (function(m,e,t,r,i,k,a){
            m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
            m[i].l=1*new Date();
            for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
            k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)
        })(window, document,'script','https://mc.yandex.ru/metrika/tag.js?id=114173620', 'ym');

        ym(114173620, 'init', {ssr:true, webvisor:true, clickmap:true, ecommerce:"dataLayer", referrer: document.referrer, url: location.href, accurateTrackBounce:true, trackLinks:true});
    </script>
    <noscript><div><img src="https://mc.yandex.ru/watch/114173620" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
    <!-- /Yandex.Metrika counter -->
</head>
<body>

<!-- Топбар -->
<header class="topbar">
    <a href="#" class="logo">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M21 8L12 3L3 8M21 8V16L12 21M21 8L12 13M12 13V21M12 13L3 8M3 8V16L12 21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M7 10.5L12 8L17 10.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" opacity="0.5"/>
        </svg>
        Склад
    </a>

    <nav class="nav-links" id="nav-links">
        <div class="nav-indicator" id="nav-indicator"></div>
        <a href="#hero" class="active">О программе</a>
        <a href="#industries">Для бизнеса</a>
        <a href="#gallery">Как работает</a>
        <a href="#pricing">Тарифы</a>
        <a href="#vs">Почему мы</a>
        <a href="#testimonials">Отзывы</a>
        <a href="#faq">FAQ</a>
        <a href="?page=register" class="btn btn-primary btn-sm" id="reg-btn">Регистрация</a>
    </nav>

    <div class="topbar-actions">
        <button type="button" class="theme-btn" id="theme-toggle" aria-label="Тема" title="Сменить тему">
            <span class="theme-icon">🌙</span>
        </button>
    </div>
</header>

<main class="landing-main">
    <section id="hero">
        <h1 class="landing-title">Учёт склада без хаоса и Excel</h1>
        <p class="landing-subtitle">
            Простая система для контроля остатков, прихода и расхода товаров. 
            Наведите порядок на складе за 15 минут. Никаких сложных настроек и скрытых платежей.
        </p>

        <div class="landing-cta">
            <a href="<?= $btnLink ?>" class="btn btn-primary btn-large">
                <?= $btnText ?>
            </a>
            <a href="#pricing" class="btn btn-ghost btn-large">Посмотреть тарифы</a>
        </div>
    </section>

    <section id="features">
        <div class="features">
            <div class="feature-card">
                <div class="feature-icon">📦</div>
                <div class="feature-title">Все товары под контролем</div>
                <div class="feature-text">Каталог с SKU, ценами и категориями. Удобный поиск и фильтрация. Остатки всегда актуальны.</div>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🔄</div>
                <div class="feature-title">Прозрачные операции</div>
                <div class="feature-text">Фиксируйте приход, расход и списание за пару кликов. Система сама пересчитает остатки.</div>
            </div>
            <div class="feature-card">
                <div class="feature-icon">📊</div>
                <div class="feature-title">Отчёты и уведомления</div>
                <div class="feature-text">Узнавайте о товарах ниже минимума заранее. Выгружайте остатки в CSV для бухгалтерии.</div>
            </div>
            <div class="feature-card">
                <div class="feature-icon">👥</div>
                <div class="feature-title">Роли и доступы</div>
                <div class="feature-text">Администратор, менеджер и кладовщик видят только то, что им положено. История действий ведётся автоматически.</div>
            </div>
        </div>
    </section>

    <section id="industries" style="margin-bottom: 80px;">
        <h2 style="font-size: 32px; margin-bottom: 16px;">Подходит для</h2>
        <p class="landing-subtitle">Система гибко настраивается под задачи разных сфер бизнеса.</p>

        <div class="features">
            <div class="feature-card">
                <div class="feature-icon">🛒</div>
                <div class="feature-title">Интернет-магазин</div>
                <div class="feature-text">Учёт остатков по SKU, приёмка и отгрузка. Отчёты по движению товаров для маркетплейсов.</div>
            </div>
            <div class="feature-card">
                <div class="feature-icon">📦</div>
                <div class="feature-title">Оптовая торговля</div>
                <div class="feature-text">Массовые приходы и отгрузки, контроль минимальных остатков, отчёты для закупщика.</div>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🏭</div>
                <div class="feature-title">Производство</div>
                <div class="feature-text">Учёт материалов и комплектующих, списание в производство, история операций по партиям.</div>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🏪</div>
                <div class="feature-title">Розница</div>
                <div class="feature-text">Простой приход/расход, печать ценников вручную, отчёты по выручке за день.</div>
            </div>
        </div>
    </section>

    <section id="gallery" class="gallery">
        <h2 style="font-size: 32px; margin-bottom: 16px;">Посмотрите, как это работает</h2>
        <p class="landing-subtitle">Интерфейс, в котором разберётся любой сотрудник с первого раза.</p>
        
        <div class="gallery-tabs">
            <button class="gallery-tab active" data-target="img-1">Главное меню</button>
            <button class="gallery-tab" data-target="img-2">Дашборд и графики</button>
            <button class="gallery-tab" data-target="img-3">Журнал операций</button>
        </div>

        <div class="gallery-window">
            <img src="assets/img/1.webp" alt="Главное меню системы" class="gallery-img active" id="img-1">
            <img src="assets/img/2.webp" alt="Дашборд с графиками" class="gallery-img" id="img-2">
            <img src="assets/img/3.webp" alt="Таблица операций" class="gallery-img" id="img-3">
        </div>
    </section>

    <section id="pricing">
        <h2 style="font-size: 32px; margin-bottom: 16px;">Простые тарифы</h2>
        <p class="landing-subtitle">Начните с бесплатного теста или выберите тариф под размер вашего бизнеса.</p>

        <div class="pricing">
            <div class="price-card">
                <div class="price-title">Старт</div>
                <div class="price-value">0 ₽<span>/мес</span></div>
                <ul class="price-list">
                    <li>До 50 товаров</li>
                    <li>1 пользователь</li>
                    <li>Базовые отчёты</li>
                </ul>
                <a href="?page=register&plan=start" class="btn btn-ghost" style="width: 100%">Попробовать</a>
            </div>
            <div class="price-card popular">
                <div class="price-title">Бизнес</div>
                <div class="price-value">990 ₽<span>/мес</span></div>
                <ul class="price-list">
                    <li>Без ограничений по товарам</li>
                    <li>До 5 пользователей</li>
                    <li>Все отчёты и экспорт</li>
                    <li>Уведомления о низком остатке</li>
                </ul>
                <a href="?page=register&plan=business" class="btn btn-primary" style="width: 100%">Выбрать тариф</a>
            </div>
            <div class="price-card">
                <div class="price-title">Корпоративный</div>
                <div class="price-value">2990 ₽<span>/мес</span></div>
                <ul class="price-list">
                    <li>Многопользовательский режим</li>
                    <li>Интеграция по API</li>
                    <li>Персональный менеджер</li>
                </ul>
                <a href="?page=register&plan=corporate" class="btn btn-ghost" style="width: 100%">Связаться</a>
            </div>
        </div>
    </section>

    <section id="vs" style="margin-bottom: 80px;">
        <h2 style="font-size: 32px; margin-bottom: 16px;">Почему проще, чем у других</h2>
        <p class="landing-subtitle">
            Если вы уже устали от внедрения и обучения в тяжёлых системах — вот чем «Склад» отличается.
        </p>

        <div class="features" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));">
            <div class="feature-card">
                <div class="feature-icon">⚡</div>
                <div class="feature-title">Без внедрения</div>
                <div class="feature-text">Регистрация и работа через браузер с первого дня. Не нужно ставить сервер и настраивать базу.</div>
            </div>
            <div class="feature-card">
                <div class="feature-icon">💸</div>
                <div class="feature-title">Дешевле</div>
                <div class="feature-text">От 0 ₽/мес на тарифе «Старт». Никаких лицензий и «коробок» за сотни тысяч.</div>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🔐</div>
                <div class="feature-title">Разграничение доступа</div>
                <div class="feature-text">Три роли: Администратор, Менеджер, Кладовщик. Кладовщик видит только приход и расход — не может изменить цены.</div>
            </div>
            <div class="feature-card">
                <div class="feature-icon">📜</div>
                <div class="feature-title">Журнал действий</div>
                <div class="feature-text">Каждое действие фиксируется. Видно, кто и когда изменил товар, цену или остаток.</div>
            </div>
        </div>
    </section>

    <section id="testimonials" class="testimonials">
        <h2 style="font-size: 32px;">Нам доверяют</h2>
        <div class="testimonial-grid">
            <div class="testimonial">
                <div class="testimonial-text">«Раньше вели склад в тетрадях и Excel. Постоянно были недостачи. С этой системой за месяц сэкономили 30 000 рублей только на закупках. Кладовщики разобрались за один день».</div>
                <div class="testimonial-author">
                    <div class="testimonial-avatar">А</div>
                    <div>Алексей,<br>владелец оптовой базы</div>
                </div>
            </div>
            <div class="testimonial">
                <div class="testimonial-text">«Нравится, что можно выдать кладовщику только нужные права. Он теперь видит только кнопки прихода и расхода, и не может случайно удалить товар или изменить цену».</div>
                <div class="testimonial-author">
                    <div class="testimonial-avatar">Е</div>
                    <div>Елена,<br>менеджер интернет-магазина</div>
                </div>
            </div>
            <div class="testimonial">
                <div class="testimonial-text">«Уведомления о том, что товар заканчивается — это огонь. Больше не бывает ситуаций, когда кофе заканчивается, а мы замечаем это только когда клиент спрашивает».</div>
                <div class="testimonial-author">
                    <div class="testimonial-avatar">Д</div>
                    <div>Дмитрий,<br>сеть кофеен</div>
                </div>
            </div>
        </div>
    </section>

    <section id="faq" class="faq">
        <h2 style="font-size: 32px; margin-bottom: 30px; text-align: center;">Частые вопросы</h2>
        
        <div class="faq-item">
            <div class="faq-question">Есть ли бесплатный период? <span class="faq-icon">+</span></div>
            <div class="faq-answer">Да, у нас есть тариф «Старт» с нулевой стоимостью. Вы можете пользоваться им бессрочно, если у вас до 50 товаров. Для тестирования расширенного функционала просто напишите в поддержку.</div>
        </div>
        <div class="faq-item">
            <div class="faq-question">Нужно ли что-то устанавливать на компьютер? <span class="faq-icon">+</span></div>
            <div class="faq-answer">Нет. Система работает в браузере. Вам достаточно открыть сайт, ввести логин и пароль. Работать можно с любого устройства: компьютера, планшета или телефона.</div>
        </div>
        <div class="faq-item">
            <div class="faq-question">Безопасно ли хранить данные у вас? <span class="faq-icon">+</span></div>
            <div class="faq-answer">Да. Мы используем защищенные каналы связи и шифрование паролей. Каждое действие фиксируется в журнале аудита, поэтому вы всегда знаете, кто и что изменил.</div>
        </div>
        <div class="faq-item">
            <div class="faq-question">Можно ли экспортировать данные? <span class="faq-icon">+</span></div>
            <div class="faq-answer">Конечно. В разделе «Отчёты» есть кнопка «Экспорт CSV». Выгрузить можно как текущие остатки, так и движения товаров за любой период.</div>
        </div>
        <div class="faq-item">
            <div class="faq-question">Можно ли перейти с 1С или МойСклад? <span class="faq-icon">+</span></div>
            <div class="faq-answer">Да. Достаточно выгрузить список товаров в CSV и загрузить в «Склад». Остатки и цены сохранятся. Миграция занимает 15–30 минут, поддержка помогает с переносом бесплатно.</div>
        </div>
    </section>
</main>

<footer class="landing-footer">
    <div class="footer-links">
        <a href="#">Политика конфиденциальности</a>
        <a href="#">Договор оферты</a>
        <a href="mailto:info@sklad.local">info@sklad.local</a>
        <a href="tel:+78000000000">+7 (800) 000-00-00</a>
    </div>
    <div>&copy; <?= date('Y') ?> Система учёта склада. Все права защищены.</div>
</footer>

<script>
    // --- Тёмная тема ---
    (function () {
        const btn  = document.getElementById('theme-toggle');
        const icon = btn ? btn.querySelector('.theme-icon') : null;
        
        try {
            const t = localStorage.getItem('theme');
            if (t === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
        } catch (e) {}

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

    // --- Аккордеон FAQ ---
    document.querySelectorAll('.faq-item').forEach(item => {
        item.addEventListener('click', () => {
            item.classList.toggle('open');
            const icon = item.querySelector('.faq-icon');
            if (icon) icon.textContent = item.classList.contains('open') ? '−' : '+';
        });
    });

    // --- Галерея ---
    const tabs = document.querySelectorAll('.gallery-tab');
    const images = document.querySelectorAll('.gallery-img');

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            images.forEach(img => img.classList.remove('active'));
            tab.classList.add('active');
            const targetId = tab.getAttribute('data-target');
            document.getElementById(targetId).classList.add('active');
        });
    });

    // --- МАГИЧЕСКАЯ ПОДЛОЖКА (АБСОЛЮТНАЯ СИНХРОНИЗАЦИЯ) ---
    const navLinks = document.querySelectorAll('.nav-links a');
    const indicator = document.getElementById('nav-indicator');
    const navContainer = document.getElementById('nav-links');
    const sections = document.querySelectorAll('section');

    let isClicking = false; // Флаг, чтобы отключить скролл-спай во время клика
    let clickTimeout;

    function moveIndicator(element) {
        if (!element) return;
        
        // offsetLeft дает точную позицию внутри контейнера, минус padding контейнера (6px)
        const left = element.offsetLeft;
        const width = element.offsetWidth;
        
        indicator.style.opacity = '1';
        indicator.style.width = `${width}px`;
        indicator.style.transform = `translateX(${left}px)`;
    }

    // 1. Клик по меню
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            // Блокируем Scroll Spy на 800мс (время плавного скролла)
            isClicking = true;
            clearTimeout(clickTimeout);
            clickTimeout = setTimeout(() => { isClicking = false; }, 800);

            navLinks.forEach(l => l.classList.remove('active'));
            this.classList.add('active');
            
            // Форсируем перерисовку перед сменой transform, чтобы браузер не "клеил" анимации
            void indicator.offsetWidth; 
            
            moveIndicator(this);
        });
    });

    // 2. Инициализация
    window.addEventListener('load', () => {
        const active = document.querySelector('.nav-links a.active');
        if (active) moveIndicator(active);
        
        window.addEventListener('resize', () => {
            const currentActive = document.querySelector('.nav-links a.active');
            if (currentActive) moveIndicator(currentActive);
        });
    });

    // 3. Scroll Spy (работает только если не было клика)
    let lastActiveId = 'hero';

    window.addEventListener('scroll', () => {
        if (isClicking) return; // Если идет клик — игнорируем скролл

        let currentId = 'hero';
        
        sections.forEach(section => {
            const sectionTop = section.offsetTop;
            if (window.scrollY >= sectionTop - 200) {
                currentId = section.getAttribute('id');
            }
        });

        if (currentId !== lastActiveId) {
            lastActiveId = currentId;
            
            navLinks.forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === `#${currentId}`) {
                    link.classList.add('active');
                    moveIndicator(link);
                }
            });
        }
    });
</script>

</body>
</html>