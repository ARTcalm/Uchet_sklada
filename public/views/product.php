<?php
declare(strict_types=1);
require_once __DIR__ . '/../partials/layout_header.php';

$productId = (int)($_GET['id'] ?? 0);
if ($productId <= 0) {
    echo '<h1 class="page-title">Товар не найден</h1>';
    require_once __DIR__ . '/../partials/layout_footer.php';
    exit;
}
?>
<div class="breadcrumbs">
    <a href="?page=products">← К списку товаров</a>
</div>

<h1 class="page-title" id="product-name">Загрузка…</h1>
<p class="page-subtitle" id="product-sku"></p>

<!-- Верхние карточки -->
<div class="cards">
    <div class="card">
        <div class="label">Остаток</div>
        <div class="value" id="p-qty">—</div>
        <div class="card-sub" id="p-qty-sub">—</div>
    </div>
    <div class="card">
        <div class="label">Мин. остаток</div>
        <div class="value" id="p-min">—</div>
        <div class="card-sub" id="p-min-sub">—</div>
    </div>
    <div class="card">
        <div class="label">Цена</div>
        <div class="value" id="p-price">—</div>
        <div class="card-sub" id="p-price-sub">—</div>
    </div>
    <div class="card">
        <div class="label">Стоимость остатка</div>
        <div class="value" id="p-value">—</div>
        <div class="card-sub">кол-во × цена</div>
    </div>
</div>

<!-- График 30 дней -->
<div class="panel">
    <div class="panel-header">
        <h2>Движение за 30 дней</h2>
        <div class="legend">
            <span class="legend-item"><i class="dot in"></i>Приход</span>
            <span class="legend-item"><i class="dot out"></i>Расход</span>
            <span class="legend-item"><i class="dot writeoff"></i>Списание</span>
        </div>
    </div>
    <div class="panel-body">
        <div id="product-chart" class="activity-chart" style="height:140px"></div>
    </div>
</div>

<div class="grid-2">
    <!-- Операции -->
    <div class="panel">
        <div class="panel-header">
            <h2>Последние операции</h2>
            <a href="?page=operations" class="panel-link">Все операции →</a>
        </div>
        <div class="panel-body" id="product-operations"></div>
    </div>

    <!-- Audit -->
    <div class="panel">
        <div class="panel-header">
            <h2>История изменений</h2>
            <a href="?page=audit" class="panel-link">Журнал действий →</a>
        </div>
        <div class="panel-body" id="product-audit"></div>
    </div>
</div>

<script>
    window.PRODUCT_ID = <?= $productId ?>;
</script>
<script src="assets/pages/product.js"></script>
<?php require_once __DIR__ . '/../partials/layout_footer.php'; ?>