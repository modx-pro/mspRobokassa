<?php

if (!class_exists('msPaymentInterface')) {
    require_once dirname(dirname(dirname(__FILE__))) . '/model/minishop2/mspaymenthandler.class.php';
}

class Robokassa extends msPaymentHandler implements msPaymentInterface
{
    public $config;
    public $modx;

    function __construct(xPDOObject $object, $config = array())
    {
        $this->modx = &$object->xpdo;

        $siteUrl = $this->modx->getOption('site_url');
        $assetsUrl = $this->modx->getOption('minishop2.assets_url', $config, $this->modx->getOption('assets_url') . 'components/minishop2/');
        $paymentUrl = $siteUrl . substr($assetsUrl, 1) . 'payment/robokassa.php';

        $this->config = array_merge(array(
            'paymentUrl' => $paymentUrl
        , 'checkoutUrl' => $this->modx->getOption('ms2_payment_rbks_url', null, 'https://merchant.roboxchange.com/Index.aspx', true)
        , 'login' => $this->modx->getOption('ms2_payment_rbks_login')
        , 'pass1' => $this->modx->getOption('ms2_payment_rbks_pass1')
        , 'pass2' => $this->modx->getOption('ms2_payment_rbks_pass2')
        , 'currency' => $this->modx->getOption('ms2_payment_rbks_currency', '', true)
        , 'culture' => $this->modx->getOption('ms2_payment_rbks_culture', 'ru', true)
        , 'json_response' => false
        ), $config);
    }


    /* @inheritdoc} */
    public function send(msOrder $order)
    {
        $link = $this->getPaymentLink($order);

        return $this->success('', array('redirect' => $link));
    }


    public function getPaymentLink(msOrder $order)
    {
        $id = $order->get('id');
        $sum = number_format($order->get('cost'), 2, '.', '');
        $request = [
            'url' => $this->config['checkoutUrl'],
            'MrchLogin' => $this->config['login'],
            'OutSum' => $sum,
            'InvId' => $id,
            'Desc' => 'Payment #' . $id,
            'SignatureValue' => md5($this->config['login'] . ':' . $sum . ':' . $id . ':' . $this->config['pass1']),
            'IncCurrLabel' => $this->config['currency'],
            'Culture' => $this->config['culture']
        ];

        $link = $this->config['checkoutUrl'] . '?' . http_build_query($request);
        return $link;
    }


    /* @inheritdoc} */
    public function receive(msOrder $order, $params = [])
    {
        $id = $order->get('id');
        $crc = strtoupper($_REQUEST['SignatureValue']);
        // Production
        $sum1 = number_format($order->get('cost'), 6, '.', '');
        $crc1 = strtoupper(md5($sum1 . ':' . $id . ':' . $this->config['pass2']));
        // Test
        $sum2 = number_format($order->get('cost'), 2, '.', '');
        $crc2 = strtoupper(md5($sum2 . ':' . $id . ':' . $this->config['pass2']));

        if ($crc == $crc1 || $crc == $crc2) {
            /* @var miniShop2 $miniShop2 */
            $miniShop2 = $this->modx->getService('miniShop2');
            @$this->modx->context->key = 'mgr';
            $miniShop2->changeOrderStatus($order->get('id'), 2);
            exit('OK');
        } else {
            $this->paymentError('Err: wrong signature.', $params);
        }
    }


    public function paymentError($text, $request = [])
    {
        $this->modx->log(modX::LOG_LEVEL_ERROR, '[miniShop2:Robokassa] ' . $text . ', request: ' . print_r($request, 1));
        header("HTTP/1.0 400 Bad Request");

        die('ERR: ' . $text);
    }
}