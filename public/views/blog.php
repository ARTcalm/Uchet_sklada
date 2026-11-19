<?php
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/markdown.php';

$pdo = db();

// 1. Получаем список всех статей для сайдбара
$stmtList = $pdo->query('SELECT id, title, slug FROM articles ORDER BY published_at DESC');
$articlesList = $stmtList->fetchAll(PDO::FETCH_ASSOC);

// 2. Определяем, какую статью показывать
// Если id нет в URL, берем ID первой статьи из списка
$articleId = isset($_GET['id']) ? (int)$_GET['id'] : ($articlesList[0]['id'] ?? 0);

// 3. Получаем данные выбранной статьи
$currentArticle = null;
if ($articleId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM articles WHERE id = ?');
    $stmt->execute([$articleId]);
    $currentArticle = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Конвертируем Markdown в HTML
$contentHtml = $currentArticle ? parse_markdown($currentArticle['content']) : '';
?>

<!DOCTYPE html>
<html lang="ru" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $currentArticle ? htmlspecialchars($currentArticle['seo_title']) : 'Блог о складе' ?></title>
    <meta name="description" content="<?= $currentArticle ? htmlspecialchars($currentArticle['seo_description']) : 'Полезные статьи об автоматизации склада' ?>">
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'); ?>
    <link rel="stylesheet" href="<?= base_url() ?>/assets/style.css">
    
    <!-- Микроразметка Schema.org для текущей статьи -->
    <?php if ($currentArticle): ?>
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Article",
      "headline": "<?= htmlspecialchars($currentArticle['title']) ?>",
      "datePublished": "<?= date('c', strtotime($currentArticle['published_at'])) ?>",
      "author": { "@type": "Person", "name": "Жогин А.А." }
    }
    </script>
    <?php endif; ?>

    <style>
        :root {
            --bg-base: #f8fafc; --text-base: #0f172a; --text-muted: #64748b;
            --bg-card: #ffffff; --border-color: #e2e8f0;
            --primary: #4f46e5; --primary-soft: #e0e7ff;
        }
        html[data-theme="dark"] {
            --bg-base: #0f172a; --text-base: #f8fafc; --text-muted: #94a3b8;
            --bg-card: #1e293b; --border-color: #334155;
            --primary: #6366f1; --primary-soft: #1e1b4b;
        }
        
        .blog-header-nav{
            margin-bottom:30px;
        }

        /* Стили для кнопки смены темы */
        .theme-btn {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            transition: all 0.2s;
            color: var(--text-base);
            box-shadow: 0 2px 5px rgba(0,0,0,0.02);
        }
        
        .theme-btn:hover {
            background: var(--primary-soft);
            border-color: var(--primary);
            transform: translateY(-2px);
        }
    </style>
</head>
<body>

<div class="blog-layout">
    <!-- Левый сайдбар -->
    <aside class="blog-sidebar">
        <div class="blog-header-nav" style="display: flex; justify-content: space-between; align-items: center;">
            <a href="?page=welcome" class="btn-back-home">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
                На главную
            </a>

            <!-- Кнопка смены темы -->
            <button type="button" class="theme-btn" id="theme-toggle" aria-label="Тема" title="Сменить тему">
                <span class="theme-icon">🌙</span>
            </button>
        </div>
        <div class="sidebar-title">Все статьи</div>
        <ul class="article-list">
            <?php foreach ($articlesList as $a): ?>
                <li>
                    <!-- При клике на ссылку происходит перезагрузка, но мы остаемся на ?page=blog -->
                    <a href="?page=blog&id=<?= $a['id'] ?>" 
                       class="article-link <?= ($a['id'] == $articleId) ? 'active' : '' ?>">
                        <?= htmlspecialchars($a['title']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </aside>

    <!-- Правая часть (контент статьи) -->
    <main class="blog-content">
        <?php if ($currentArticle): ?>
            <div class="article-body">
                <h1><?= htmlspecialchars($currentArticle['title']) ?></h1>
                <?= $contentHtml ?>
            </div>
        <?php else: ?>
            <p>В блоге пока нет статей. Зайдите позже или добавьте их через базу данных.</p>
        <?php endif; ?>
    </main>
</div>
<script>
    (function () {
        const btn  = document.getElementById('theme-toggle');
        const icon = btn ? btn.querySelector('.theme-icon') : null;
        
        // Проверяем сохраненную тему в localStorage
        try {
            const t = localStorage.getItem('theme');
            if (t === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
            } else if (t === 'light') {
                document.documentElement.removeAttribute('data-theme');
            }
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

        // Устанавливаем иконку при загрузке
        if (icon) icon.textContent = currentTheme() === 'dark' ? '☀️' : '🌙';
        
        // Обработчик клика
        btn && btn.addEventListener('click', () => {
            apply(currentTheme() === 'dark' ? 'light' : 'dark');
        });
    })();
</script>
</body>
</html>