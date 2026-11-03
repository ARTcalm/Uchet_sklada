<?php
declare(strict_types=1);
require_once __DIR__ . '/../partials/layout_header.php';
?>
<h1 class="page-title">Пользователи</h1>

<div class="toolbar">
    <div class="spacer"></div>
    <button class="btn btn-primary" id="user-add">+ Добавить пользователя</button>
</div>

<div class="table-wrap" id="users-table"></div>

<!-- Диалог создания -->
<dialog id="user-dialog">
    <form id="user-form" novalidate>
        <div class="dialog-header">Новый пользователь</div>
        <div class="dialog-body">
            <div class="form-row">
                <label>Логин *</label>
                <input type="text" name="login" required maxlength="64" autocomplete="off">
            </div>
            <div class="form-row">
                <label>Пароль *</label>
                <input type="password" name="password" required minlength="4" autocomplete="new-password">
            </div>
            <div class="form-row">
                <label>Имя *</label>
                <input type="text" name="full_name" required maxlength="255">
            </div>
            <div class="form-row">
                <label>Роль *</label>
                <select name="role" required>
                    <option value="worker">worker</option>
                    <option value="manager">manager</option>
                    <option value="admin">admin</option>
                </select>
            </div>
            <div class="form-error" id="user-form-error"></div>
        </div>
        <div class="dialog-footer">
            <button type="button" class="btn btn-ghost" id="user-cancel">Отмена</button>
            <button type="submit" class="btn btn-primary">Создать</button>
        </div>
    </form>
</dialog>

<!-- Диалог редактирования -->
<dialog id="user-edit-dialog">
    <form id="user-edit-form" novalidate>
        <div class="dialog-header">Редактировать пользователя</div>
        <div class="dialog-body">
            <input type="hidden" name="id" value="">

            <div class="form-row">
                <label>Логин *</label>
                <input type="text" name="login" required maxlength="64" autocomplete="off">
            </div>
            <div class="form-row">
                <label>Новый пароль</label>
                <input type="password" name="password" minlength="4" autocomplete="new-password"
                       placeholder="Оставьте пустым, чтобы не менять">
            </div>
            <div class="form-row">
                <label>Имя *</label>
                <input type="text" name="full_name" required maxlength="255">
            </div>
            <div class="form-row">
                <label>Роль *</label>
                <select name="role" required>
                    <option value="worker">worker</option>
                    <option value="manager">manager</option>
                    <option value="admin">admin</option>
                </select>
            </div>
            <div class="form-row">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="checkbox" name="is_active" value="1" style="width:auto">
                    Активен
                </label>
            </div>
            <div class="form-error" id="user-edit-form-error"></div>
        </div>
        <div class="dialog-footer">
            <button type="button" class="btn btn-ghost" id="user-edit-cancel">Отмена</button>
            <button type="submit" class="btn btn-primary">Сохранить</button>
        </div>
    </form>
</dialog>

<script src="assets/pages/users.js"></script>
<?php require_once __DIR__ . '/../partials/layout_footer.php'; ?>