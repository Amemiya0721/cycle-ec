<?php
declare(strict_types=1);

/**
 * category_slugs.php
 * -----------------------------------------------------------
 * 現行ER図（CATEGORIES テーブル）には slug カラムが存在しないため、
 * URL上の英語スラッグ（例: road-bike）とDB上のカテゴリ名（例: ロードバイク）を
 * コード側の対応表で橋渡しする。
 *
 * ・products.php?category=road-bike        → 下記マップ経由で名前解決
 * ・products.php?category=ロードバイク       → マップに無ければ名前で直接検索（Category::resolveCategoryId参照）
 * ・products.php?category=3                → 数値の場合は category_id として直接扱う
 *
 * 将来的にCATEGORIESへslugカラムを追加する場合は、
 * このファイルを廃止しDB側の値を正とすればよい（Category.php側の1関数を差し替えるだけで済む設計）。
 */
return [
    'road-bike'   => 'ロードバイク',
    'wheel'       => 'ホイール',
    'component'   => 'コンポーネント',
    'frame'       => 'フレーム / フォーク',
    'saddle'      => 'サドル / シートポスト',
    'handlebar'   => 'ハンドル / ステム',
    'accessory'   => 'サイクルコンピュータ',
];
