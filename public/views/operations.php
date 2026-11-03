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
                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Тип</label>
                <select name="type" required>
                    <option value="in">Приход</option>
                    <option value="out">Расход</option>
                    <option value="writeoff">Списание</option>
                </select>
            </div>
            <div style="flex:1 1 260px">
                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Товар</label>
                <select name="product_id" required></select>
            </div>
            <div style="flex:0 0 120px">
                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Количество</label>
                <input type="number" name="qty" min="1" step="1" value="1" required>
            </div>
            <div style="flex:2 1 240px">
                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Комментарий</label>
                <input type="text" name="comment" maxlength="255" placeholder="необязательно">
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
    <select id="f-type" style="max-width:140px">
        <option value="">Все типы</option>
        <option value="in">Приход</option>
        <option value="out">Расход</option>
        <option value="writeoff">Списание</option>
    </select>
    <select id="f-product" style="max-width:260px">
        <option value="">Все товары</option>
    </select>
    <?php if ($role !== 'worker'): ?>
        <select id="f-user" style="max-width:200px">
            <option value="">Все пользователи</option>
        </select>
    <?php endif; ?>
    <input type="date" id="f-from" style="max-width:160px" title="С">
    <input type="date" id="f-to"   style="max-width:160px" title="По">
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