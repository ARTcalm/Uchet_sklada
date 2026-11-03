<?php
declare(strict_types=1);
require_once __DIR__ . '/../partials/layout_header.php';
?>
<h1 class="page-title">Товары</h1>

<div class="toolbar">
    <input type="text" id="products-search" placeholder="Поиск по SKU или названию…" style="max-width:320px">
    <div class="spacer"></div>
    <button class="btn btn-primary" id="product-add" style="display:none">+ Добавить товар</button>
</div>

<div class="table-wrap" id="products-table"></div>
<div class="pagination" id="products-pager"></div>

<!-- Диалог добавления/редактирования -->
<dialog id="product-dialog">
    <form id="product-form" method="dialog" novalidate>
        <div class="dialog-header" id="product-dialog-title">Новый товар</div>
        <div class="dialog-body">
            <input type="hidden" id="product-id" value="">
            <div class="form-row">
                <label>SKU *</label>
                <input type="text" name="sku" required maxlength="64">
            </div>
            <div class="form-row">
                <label>Название *</label>
                <input type="text" name="name" required maxlength="255">
            </div>
            <div class="form-row">
                <label>Категория</label>
                <select name="category_id"></select>
            </div>
            <div class="form-row">
                <label>Единица измерения *</label>
                <input type="text" name="unit" required value="шт" maxlength="16">
            </div>
            <div class="form-row" style="display:flex; gap:12px">
                <div style="flex:1">
                    <label>Остаток</label>
                    <input type="number" name="quantity" min="0" step="1" value="0">
                </div>
                <div style="flex:1">
                    <label>Мин. остаток</label>
                    <input type="number" name="min_stock" min="0" step="1" value="0">
                </div>
            </div>
            <div class="form-row">
                <label>Цена</label>
                <input type="number" name="price" min="0" step="0.01" value="0">
            </div>
            <div class="form-error" id="product-form-error"></div>
        </div>
        <div class="dialog-footer">
            <button type="button" class="btn btn-ghost" id="product-cancel">Отмена</button>
            <button type="submit" class="btn btn-primary">Сохранить</button>
        </div>
    </form>
</dialog>

<script src="assets/pages/products.js"></script>
<?php require_once __DIR__ . '/../partials/layout_footer.php'; ?>