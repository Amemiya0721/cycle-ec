<?php
/**
 * 住所入力フィールド（マイページ・注文画面で共用）
 *
 * 呼び出し側で用意する変数:
 *   $addr   array<string,string>  現在の入力値（UserAddress::FIELDS のキー）
 *   $errors array<string,string>  フィールド名 => エラーメッセージ
 * 呼び出し側で h() を定義しておくこと。
 */

$field = static function (string $name) use ($addr): string {
    return (string) ($addr[$name] ?? '');
};
$invalid = static fn (string $name): string => isset($errors[$name]) ? ' is-invalid' : '';
$feedback = static function (string $name) use ($errors): void {
    if (isset($errors[$name])) {
        echo '<div class="invalid-feedback">' . h($errors[$name]) . '</div>';
    }
};
?>
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="recipient_name">受取人名 <span class="text-danger">*</span></label>
        <input type="text" class="form-control<?= $invalid('recipient_name') ?>" id="recipient_name" name="recipient_name"
               value="<?= h($field('recipient_name')) ?>" maxlength="50" autocomplete="name" required>
        <?php $feedback('recipient_name'); ?>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="phone_number">電話番号 <span class="text-danger">*</span></label>
        <input type="tel" class="form-control<?= $invalid('phone_number') ?>" id="phone_number" name="phone_number"
               value="<?= h($field('phone_number')) ?>" placeholder="09012345678" autocomplete="tel" required>
        <?php $feedback('phone_number'); ?>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="postal_code">郵便番号 <span class="text-danger">*</span></label>
        <input type="text" class="form-control<?= $invalid('postal_code') ?>" id="postal_code" name="postal_code"
               value="<?= h(UserAddress::formatPostalCode($field('postal_code'))) ?>" placeholder="123-4567"
               inputmode="numeric" autocomplete="postal-code" required>
        <?php $feedback('postal_code'); ?>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="prefecture">都道府県 <span class="text-danger">*</span></label>
        <select class="form-select<?= $invalid('prefecture') ?>" id="prefecture" name="prefecture" autocomplete="address-level1" required>
            <option value="">選択してください</option>
            <?php foreach (UserAddress::PREFECTURES as $pref): ?>
                <option value="<?= h($pref) ?>" <?= $field('prefecture') === $pref ? 'selected' : '' ?>><?= h($pref) ?></option>
            <?php endforeach; ?>
        </select>
        <?php $feedback('prefecture'); ?>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="city">市区町村 <span class="text-danger">*</span></label>
        <input type="text" class="form-control<?= $invalid('city') ?>" id="city" name="city"
               value="<?= h($field('city')) ?>" maxlength="50" autocomplete="address-level2" required>
        <?php $feedback('city'); ?>
    </div>
    <div class="col-12">
        <label class="form-label" for="address_line">番地など <span class="text-danger">*</span></label>
        <input type="text" class="form-control<?= $invalid('address_line') ?>" id="address_line" name="address_line"
               value="<?= h($field('address_line')) ?>" maxlength="100" autocomplete="address-line1" required>
        <?php $feedback('address_line'); ?>
    </div>
    <div class="col-12">
        <label class="form-label" for="building">建物名・部屋番号</label>
        <input type="text" class="form-control<?= $invalid('building') ?>" id="building" name="building"
               value="<?= h($field('building')) ?>" maxlength="100" autocomplete="address-line2">
        <?php $feedback('building'); ?>
    </div>
</div>
