<?php
declare(strict_types=1);
require_once __DIR__ . '/../partials/layout_header.php';
?>
<h1 class="page-title">Профиль</h1>

<div class="grid-2">
    <!-- Данные -->
    <div class="panel">
        <div class="panel-header"><h2>Учётная запись</h2></div>
        <div class="panel-body">
            <div class="profile-field">
                <div class="profile-label">Email</div>
                <div class="profile-value"><?= e($user['email']) ?></div>
            </div>
            <div class="profile-field">
                <div class="profile-label">Имя</div>
                <div class="profile-value"><?= e($user['full_name']) ?></div>
            </div>
            <div class="profile-field">
                <div class="profile-label">Роль</div>
                <div class="profile-value">
                    <span class="badge <?= e($user['role']) ?>"><?= e($user['role']) ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Смена пароля -->
    <div class="panel">
        <div class="panel-header"><h2>Сменить пароль</h2></div>
        <div class="panel-body">
            <form id="password-form" novalidate>
                <div class="form-row">
                    <label for="pp-old">Старый пароль *</label>
                    <input type="password" id="pp-old" name="old_password" required autocomplete="current-password">
                </div>
                <div class="form-row">
                    <label for="pp-new">Новый пароль * (мин. 4 символа)</label>
                    <input type="password" id="pp-new" name="new_password" required minlength="4" autocomplete="new-password">
                </div>
                <div class="form-row">
                    <label for="pp-repeat">Повторите новый пароль *</label>
                    <input type="password" id="pp-repeat" name="repeat_password" required minlength="4" autocomplete="new-password">
                </div>
                <div class="form-error" id="password-form-error"></div>
                <button type="submit" class="btn btn-primary">Сменить пароль</button>
            </form>
        </div>
    </div>
</div>

<script src="assets/pages/profile.js"></script>
<?php require_once __DIR__ . '/../partials/layout_footer.php'; ?>