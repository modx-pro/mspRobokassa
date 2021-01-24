<?php
/**
 * Loads system settings into build
 * @var modX $modx
 * @package msprobokassa
 * @subpackage build
 */
$settings = [];

$tmp = [
    'url' => [
        'xtype' => 'textfield',
        'value' => 'https://merchant.roboxchange.com/Index.aspx',
    ],
    'login' => [
        'xtype' => 'textfield',
        'value' => 'test.dev',
    ],
    'pass1' => [
        'xtype' => 'text-password',
        'value' => '5hdwIOaLPJqz',
    ],
    'pass2' => [
        'xtype' => 'text-password',
        'value' => 'ahwzBVB32V4d',
    ],
    'currency' => [
        'xtype' => 'textfield',
        'value' => '',
    ],
    'culture' => [
        'xtype' => 'textfield',
        'value' => 'ru',
    ],
    'success_id' => [
        'xtype' => 'numberfield',
        'value' => 0,
    ],
    'failure_id' => [
        'xtype' => 'numberfield',
        'value' => 0,
    ],
];

foreach ($tmp as $k => $v) {
    /* @var modSystemSetting $setting */
    $setting = $modx->newObject('modSystemSetting');
    $setting->fromArray(array_merge(
        array(
            'key' => 'ms2_payment_rbks_' . $k,
            'namespace' => 'minishop2',
            'area' => 'ms2_payment',
        ), $v
    ), '', true, true);

    $settings[] = $setting;
}

unset($tmp);
return $settings;