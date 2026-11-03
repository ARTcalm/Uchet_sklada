<?php
declare(strict_types=1);
require_once __DIR__ . '/../partials/layout_header.php';
?>
<h1 class="page-title">Журнал действий</h1>

<div class="toolbar">
    <select id="audit-action" style="max-width:180px" aria-label="Действие">
        <option value="">Все действия</option>
    </select>
    <select id="audit-entity" style="max-width:180px" aria-label="Сущность">
        <option value="">Все сущности</option>
    </select>
    <input type="date" id="audit-from" style="max-width:160px" title="С" aria-label="Дата с">
    <input type="date" id="audit-to" style="max-width:160px" title="По" aria-label="Дата по">
    <div class="spacer"></div>
    <button class="btn btn-ghost" id="audit-refresh">Применить</button>
</div>

<div class="table-wrap" id="audit-table"></div>
<div class="pagination" id="audit-pager"></div>

<script src="assets/pages/audit.js"></script>
<?php require_once __DIR__ . '/../partials/layout_footer.php'; ?>