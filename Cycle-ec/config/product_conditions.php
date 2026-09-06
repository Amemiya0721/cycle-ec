<?php

declare(strict_types=1);

return [
    'S' => [
        'label' => 'Sランク',
        'description' => '未使用・新品同等',
        'detail' => "使用感がほとんどない\n非常にきれいな状態",
    ],
    'A' => [
        'label' => 'Aランク',
        'description' => '使用感が少ない良品',
        'detail' => "小さな傷はあるが\n全体的にきれいな状態",
    ],
    'B' => [
        'label' => 'Bランク',
        'description' => '通常使用の中古品',
        'detail' => "使用に伴う傷・汚れが\nあるが使用に問題なし",
    ],
    'C' => [
        'label' => 'Cランク',
        'description' => '傷・使用感が目立つ',
        'detail' => "目立つ傷や汚れがあり\n使用感のある状態",
    ],
    'JUNK' => [
        'label' => 'JUNK',
        'description' => '動作保証なし・現状販売',
        'detail' => '返品・返金対象外',
    ],
];
