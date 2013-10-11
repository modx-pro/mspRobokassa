<?php
/**
 * Loads system settings into build
 *
 * @package msprobokassa
 * @subpackage build
 */
$settings = array();

$tmp = array(
	'url' => array(
		'xtype' => 'textfield',
		'value' => 'https://merchant.roboxchange.com/Index.aspx',
	),
	'login' => array(
		'xtype' => 'textfield',
		'value' => 'test.dev',
	),
	'pass1' => array(
		'xtype' => 'text-password',
		'value' => '5hdwIOaLPJqz',
	),
	'pass2' => array(
		'xtype' => 'text-password',
		'value' => 'ahwzBVB32V4d',
	),
	'currency' => array(
		'xtype' => 'textfield',
		'value' => '',
	),
	'culture' => array(
		'xtype' => 'textfield',
		'value' => 'ru',
	),
	'success_id' => array(
		'xtype' => 'numberfield',
		'value' => 0,

	),
	'failure_id' => array(
		'xtype' => 'numberfield',
		'value' => 0,
	),

);

foreach ($tmp as $k => $v) {
	/* @var modSystemSetting $setting */
	$setting = $modx->newObject('modSystemSetting');
	$setting->fromArray(array_merge(
		array(
			'key' => 'ms2_payment_rbks_'.$k,
			'namespace' => 'minishop2',
			'area' => 'ms2_payment',
		), $v
	),'',true,true);

	$settings[] = $setting;
}

unset($tmp);
return $settings;