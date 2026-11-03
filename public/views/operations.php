<?php
declare(strict_types=1);
require_once __DIR__ . '/../partials/layout_header.php';
$role = $user['role'];
?>
<h1 class="page-title">Операции</h1>

<div class="table-wrap" style="padding:16px; margin-bottom:20px">
    <form id="operation-form" novalidate>
        <div style="display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end">
            <div style="flex:0 0 160px">
                <label for="op-type" style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Тип</label>
                <select id="op-type" name="type" required>
                    <option value="in">Приход</option>
                    <option value="out">Расход</option>
                    <option value="writeoff">Списание</option>
                </select>
            </div>
            <div style="flex:1 1 260px">
                <label for="op-product" style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Товар</label>
                <select id="op-product" name="product_id" required></select>
            </div>
            <div style="flex:0 0 120px">
                <label for="op-qty" style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Количество</label>
                <input type="number" id="op-qty" name="qty" min="1" step="1" value="1" required>
            </div>
            <div style="flex:2 1 240px">
                <label for="op-comment" style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Комментарий</label>
                <input type="text" id="op-comment" name="comment" maxlength="255" placeholder="необязательно">
            </div>
            <div>
                <button type="submit" class="btn btn-primary">Провести</button>
            </div>
        </div>
        <div class="form-error" id="operation-form-error"></div>
    </form>
</div>

<!-- Фильтры -->
<div class="toolbar" style="gap:10px; flex-wrap:wrap">
    <select id="f-type" style="max-width:140px" aria-label="Тип операции">
        <option value="">Все типы</option>
        <option value="in">Приход</option>
        <option value="out">Расход</option>
        <option value="writeoff">Списание</option>
    </select>
    <select id="f-product" style="max-width:260px" aria-label="Товар">
        <option value="">Все товары</option>
    </select>
    <?php if ($role !== 'worker'): ?>
        <select id="f-user" style="max-width:200px" aria-label="Пользователь">
            <option value="">Все пользователи</option>
        </select>
    <?php endif; ?>
    <input type="date" id="f-from" style="max-width:160px" title="С" aria-label="Дата с">
    <input type="date" id="f-to"   style="max-width:160px" title="По" aria-label="Дата по">
    <div class="spacer"></div>
    <button class="btn btn-ghost" id="f-reset">Сбросить</button>
</div>

<div class="table-wrap" id="operations-table"></div>
<div class="pagination" id="operations-pager"></div>

<script>
    window.OPERATIONS_ROLE = <?= json_encode($role) ?>;
</script>
<script src="assets/pages/operations.js"></script>
<?php require_once __DIR__ . '/../partials/layout_footer.php'; ?>