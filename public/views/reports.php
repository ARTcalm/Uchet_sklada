<?php
declare(strict_types=1);
require_once __DIR__ . '/../partials/layout_header.php';
?>
<h1 class="page-title">Отчёты</h1>

<div class="toolbar" style="margin-bottom:16px">
    <button type="button" class="btn tab-btn active" data-tab="stock">Остатки</button>
    <button type="button" class="btn tab-btn" data-tab="movement">Движение</button>
</div>

<!-- Вкладка: остатки -->
<div class="report-tab" data-tab="stock">
    <div class="toolbar">
        <select id="stock-category" style="max-width:240px">
            <option value="">Все категории</option>
        </select>
        <div class="spacer"></div>
        <button class="btn btn-ghost" id="stock-export">Экспорт CSV</button>
    </div>
    <div class="table-wrap" id="stock-table"></div>
</div>

<!-- Вкладка: движение -->
<div class="report-tab" data-tab="movement" style="display:none">
    <div class="toolbar" style="gap:12px">
        <div>
            <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:4px">С</label>
            <input type="date" id="move-from">
        </div>
        <div>
            <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:4px">По</label>
            <input type="date" id="move-to">
        </div>
        <div style="flex:1 1 260px">
            <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:4px">Товар</label>
            <select id="move-product">
                <option value="">Все товары</option>
            </select>
        </div>
        <div style="align-self:flex-end">
            <button class="btn btn-primary" id="move-run">Построить</button>
            <button class="btn btn-ghost" id="move-export">Экспорт CSV</button>
        </div>
    </div>
    <div class="table-wrap" id="movement-table"></div>
</div>

<script src="assets/pages/reports.js"></script>
<?php require_once __DIR__ . '/../partials/layout_footer.php'; ?>