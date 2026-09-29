<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';

/**
 * UserAddress
 * -----------------------------------------------------------
 * user_addresses（1ユーザー1住所）の取得・検証・保存。
 * マイページと注文画面（checkout.php）の両方から使う。
 */
final class UserAddress
{
    public const FIELDS = [
        'recipient_name', 'postal_code', 'prefecture',
        'city', 'address_line', 'building', 'phone_number',
    ];

    public const PREFECTURES = [
        '北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県',
        '茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県',
        '新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県', '岐阜県',
        '静岡県', '愛知県', '三重県', '滋賀県', '京都府', '大阪府', '兵庫県',
        '奈良県', '和歌山県', '鳥取県', '島根県', '岡山県', '広島県', '山口県',
        '徳島県', '香川県', '愛媛県', '高知県', '福岡県', '佐賀県', '長崎県',
        '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県',
    ];

    /** @return array<string, string>|null */
    public static function findByUserId(int $userId): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT address_id, user_id, postal_code, prefecture, city, address_line,
                    building, recipient_name, phone_number
             FROM user_addresses
             WHERE user_id = :user_id
             LIMIT 1'
        );
        $stmt->execute([':user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * フォーム入力を保存用に整形する（全角→半角、ハイフン除去、trim）。
     *
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public static function normalize(array $input): array
    {
        $get = static fn (string $key): string =>
            trim(mb_convert_kana((string) ($input[$key] ?? ''), 's'));

        return [
            'recipient_name' => $get('recipient_name'),
            'postal_code'    => preg_replace('/\D/', '', mb_convert_kana($get('postal_code'), 'n')) ?? '',
            'prefecture'     => $get('prefecture'),
            'city'           => $get('city'),
            'address_line'   => $get('address_line'),
            'building'       => $get('building'),
            'phone_number'   => preg_replace('/\D/', '', mb_convert_kana($get('phone_number'), 'n')) ?? '',
        ];
    }

    /**
     * @param array<string, string> $data normalize() 済みの値
     * @return array<string, string> フィールド名 => エラーメッセージ
     */
    public static function validate(array $data): array
    {
        $errors = [];

        if ($data['recipient_name'] === '') {
            $errors['recipient_name'] = '受取人名を入力してください。';
        } elseif (mb_strlen($data['recipient_name']) > 50) {
            $errors['recipient_name'] = '受取人名は50文字以内で入力してください。';
        }

        if (!preg_match('/^\d{7}$/', $data['postal_code'])) {
            $errors['postal_code'] = '郵便番号は7桁の数字で入力してください。';
        }

        if (!in_array($data['prefecture'], self::PREFECTURES, true)) {
            $errors['prefecture'] = '都道府県を選択してください。';
        }

        if ($data['city'] === '') {
            $errors['city'] = '市区町村を入力してください。';
        } elseif (mb_strlen($data['city']) > 50) {
            $errors['city'] = '市区町村は50文字以内で入力してください。';
        }

        if ($data['address_line'] === '') {
            $errors['address_line'] = '番地などを入力してください。';
        } elseif (mb_strlen($data['address_line']) > 100) {
            $errors['address_line'] = '番地などは100文字以内で入力してください。';
        }

        if (mb_strlen($data['building']) > 100) {
            $errors['building'] = '建物名は100文字以内で入力してください。';
        }

        if (!preg_match('/^\d{10,11}$/', $data['phone_number'])) {
            $errors['phone_number'] = '電話番号は10〜11桁の数字で入力してください。';
        }

        return $errors;
    }

    /**
     * 住所を登録 or 更新する（1ユーザー1件）。
     *
     * @param array<string, string> $data normalize() + validate() 済みの値
     */
    public static function save(int $userId, array $data): void
    {
        $pdo = Database::getConnection();

        $params = [
            ':user_id'        => $userId,
            ':postal_code'    => $data['postal_code'],
            ':prefecture'     => $data['prefecture'],
            ':city'           => $data['city'],
            ':address_line'   => $data['address_line'],
            ':building'       => $data['building'],
            ':recipient_name' => $data['recipient_name'],
            ':phone_number'   => $data['phone_number'],
        ];

        if (self::findByUserId($userId) !== null) {
            $stmt = $pdo->prepare(
                'UPDATE user_addresses
                 SET postal_code = :postal_code, prefecture = :prefecture, city = :city,
                     address_line = :address_line, building = :building,
                     recipient_name = :recipient_name, phone_number = :phone_number,
                     updated_at = NOW()
                 WHERE user_id = :user_id'
            );
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO user_addresses
                    (user_id, postal_code, prefecture, city, address_line, building,
                     recipient_name, phone_number, created_at, updated_at)
                 VALUES
                    (:user_id, :postal_code, :prefecture, :city, :address_line, :building,
                     :recipient_name, :phone_number, NOW(), NOW())'
            );
        }
        $stmt->execute($params);
    }

    /** 保存値 "1234567" → 表示用 "123-4567" */
    public static function formatPostalCode(string $postalCode): string
    {
        return preg_match('/^\d{7}$/', $postalCode)
            ? substr($postalCode, 0, 3) . '-' . substr($postalCode, 3)
            : $postalCode;
    }
}
