<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class BankTransferSettings
{
    private const DEFAULTS = [
        'bank_name' => '',
        'branch_name' => '',
        'account_type' => '',
        'account_number' => '',
        'account_holder' => '',
    ];

    public static function get(): array
    {
        $stmt = Database::getConnection()->query(
            'SELECT bank_name, branch_name, account_type, account_number, account_holder
             FROM bank_transfer_settings
             WHERE settings_id = 1
             LIMIT 1'
        );
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);

        return $settings === false ? self::DEFAULTS : $settings;
    }

    public static function save(array $settings): void
    {
        $stmt = Database::getConnection()->prepare(
            'INSERT INTO bank_transfer_settings
                (settings_id, bank_name, branch_name, account_type, account_number, account_holder)
             VALUES
                (1, :bank_name, :branch_name, :account_type, :account_number, :account_holder)
             ON DUPLICATE KEY UPDATE
                bank_name = VALUES(bank_name),
                branch_name = VALUES(branch_name),
                account_type = VALUES(account_type),
                account_number = VALUES(account_number),
                account_holder = VALUES(account_holder)'
        );
        $stmt->execute([
            ':bank_name' => $settings['bank_name'],
            ':branch_name' => $settings['branch_name'],
            ':account_type' => $settings['account_type'],
            ':account_number' => $settings['account_number'],
            ':account_holder' => $settings['account_holder'],
        ]);
    }

    public static function isConfigured(array $settings): bool
    {
        foreach (array_keys(self::DEFAULTS) as $key) {
            if (trim((string) ($settings[$key] ?? '')) === '') {
                return false;
            }
        }
        return true;
    }
}
