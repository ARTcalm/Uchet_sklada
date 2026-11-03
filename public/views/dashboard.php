<?php
declare(strict_types=1);
require_once __DIR__ . '/../partials/layout_header.php';
?>
<h1 class="page-title">Дашборд</h1>

<!-- ВЕРХНИЙ РЯД -->
<div class="cards">
    <div class="card">
        <div class="label">Товаров всего</div>
        <div class="value" id="c-total">—</div>
        <div class="card-sub" id="c-total-sub">—</div>
    </div>
    <div class="card">
        <div class="label">Стоимость склада</div>
        <div class="value" id="c-value">—</div>
        <div class="card-sub">по текущим ценам</div>
    </div>
    <div class="card warn" id="c-low-card">
        <div class="label">Ниже мин. остатка</div>
        <div class="value" id="c-low">—</div>
        <div class="card-sub" id="c-low-sub">—</div>
    </div>
    <div class="card">
        <div class="label">Операций сегодня</div>
        <div class="value" id="c-ops">—</div>
        <div class="card-sub" id="c-ops-sub">—</div>
    </div>
</div>

<!-- АКТИВНОСТЬ 7 ДНЕЙ -->
<div class="panel">
    <div class="panel-header">
        <h2>Активность за 7 дней</h2>
        <div class="legend">
            <span class="legend-item"><i class="dot in"></i>Приход</span>
            <span class="legend-item"><i class="dot out"></i>Расход</span>
            <span class="legend-item"><i class="dot writeoff"></i>Списание</span>
        </div>
    </div>
    <div class="panel-body">
        <div id="activity-chart" class="activity-chart"></div>
        <div class="activity-summary" id="activity-summary"></div>
    </div>
</div>

<!-- ТРЕБУЕТ ВНИМАНИЯ + ТОП ПО СТОИМОСТИ -->
<div class="grid-2">
    <div class="panel">
        <div class="panel-header">
            <h2>⚠️ Требуют внимания</h2>
            <a href="?page=reports" class="panel-link">Все отчёты →</a>
        </div>
        <div class="panel-body" id="low-stock-table"></div>
    </div>
    <div class="panel">
        <div class="panel-header">
            <h2>💰 Самые ценные позиции</h2>
        </div>
        <div class="panel-body" id="top-value-table"></div>
    </div>
</div>

<!-- ПОСЛЕДНИЕ ОПЕРАЦИИ -->
<div class="panel">
    <div class="panel-header">
        <h2>Последние операции</h2>
        <a href="?page=operations" class="panel-link">Все операции →</a>
    </div>
    <div class="panel-body" id="recent-table"></div>
</div>

<script src="assets/pages/dashboard.js"></script>
<?php require_once __DIR__ . '/../partials/layout_footer.php'; ?>