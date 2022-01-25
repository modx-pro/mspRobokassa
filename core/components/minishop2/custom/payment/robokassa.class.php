<?php

if (!class_exists('msPaymentInterface')) {
    require_once dirname(dirname(dirname(__FILE__))) . '/model/minishop2/mspaymenthandler.class.php';
}

class Robokassa extends msPaymentHandler implements msPaymentInterface
{
    public $config;
    /** @var modX */
    public $modx;

    const LOG_NAME = '[miniShop2:Robokassa]';

    function __construct(xPDOObject $object, $config = [])
    {
        $this->modx = &$object->xpdo;

        $siteUrl = $this->modx->getOption('site_url');
        $assetsUrl = $this->modx->getOption(
            'minishop2.assets_url', $config,
            $this->modx->getOption('assets_url') . 'components/minishop2/'
        );
        $paymentUrl = $siteUrl . substr($assetsUrl, 1) . 'payment/robokassa.php';

        $this->config = array_merge([
            'paymentUrl' => $paymentUrl,
            'checkoutUrl' => $this->modx->getOption('ms2_payment_rbks_url', null, 'https://merchant.roboxchange.com/Index.aspx', true),
            'login' => $this->modx->getOption('ms2_payment_rbks_login'),
            'pass1' => $this->modx->getOption('ms2_payment_rbks_pass1'),
            'pass2' => $this->modx->getOption('ms2_payment_rbks_pass2'),
            'currency' => $this->modx->getOption('ms2_payment_rbks_currency', '', true),
            'culture' => $this->modx->getOption('ms2_payment_rbks_culture', 'ru', true),
            'json_response' => false,
            'fiskal' => $this->modx->getOption('ms2_payment_rbks_fiskal', 'ru', false),
            'debug' => $this->modx->getOption('ms2_payment_rbks_debug', 'ru', false),
            'tax' => $this->modx->getOption('ms2_payment_rbks_tax', 'ru', 'none'),
        ], $config);
    }


    /* @inheritdoc} */
    public function send(msOrder $order)
    {
        $link = $this->getPaymentLink($order);

        return $this->success('', ['redirect' => $link]);
    }

    /**
     * Метод получения ссылки на оплату
     * @param msOrder $order
     * @return string
     */
    public function getPaymentLink(msOrder $order)
    {
        $id = $order->get('id');
        $sum = $this->formatSum($order->get('cost'));
        $hashData = $this->getRequestHashData($order);

        $request = [
            'url' => $this->config['checkoutUrl'],
            'MrchLogin' => $this->config['login'],
            'OutSum' => $sum,
            'InvId' => $id,
            'Desc' => 'Payment #' . $id,
            'SignatureValue' => $this->getHash($hashData), //TODO: upper false
            'IncCurrLabel' => $this->config['currency'],
            'Culture' => $this->config['culture']
        ];

        if ($this->config['fiskal']) {
            $receipt = $this->modx->toJSON($this->getReceipt($order));
            $request['Receipt'] = $receipt;
        }

        if ($this->config['debug']) {
            $this->log('Request Link:', $request);
            $this->log('HashData:', $hashData);
        }

        return $this->config['checkoutUrl'] . '?' . http_build_query($request);
    }


    /* @inheritdoc} */
    public function receive(msOrder $order, $params = [])
    {
        $id = $order->get('id');
        $crc = strtoupper($_REQUEST['SignatureValue']);
        // Production
        $sum1 = $this->formatSum($order->get('cost')); //TODO: old decimal 6
        $crc1 = $this->getHash([
            $sum1,
            $id,
            $this->config['pass2']
        ]);
        // Test
        $sum2 = $this->formatSum($order->get('cost'));
        $crc2 = $this->getHash([
            $sum2,
            $id,
            $this->config['pass2']
        ]);

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


    /**
     * @param $text
     * @param array $request
     */
    public function paymentError($text, $request = [])
    {
        $this->modx->log(
            modX::LOG_LEVEL_ERROR,
            self::LOG_NAME . ' ' . $text . ', request: ' . print_r($request, true)
        );
        header("HTTP/1.0 400 Bad Request");

        die('ERR: ' . $text);
    }

    /**
     * Генерация подписи
     * @param array $hashData
     * @param bool $upper
     * @return string
     */
    private function getHash(array $hashData, $upper = true)
    {
        $hash = md5(implode(':', $hashData));

        if (!$upper) {
            return $hash;
        }

        return strtoupper($hash);
    }

    private function formatSum($sum, $decimal = 2)
    {
        return number_format($sum, $decimal, '.', '');
    }

    /**
     * Отдает данные для хэширования запроса
     * @param msOrder $order
     * @return array
     */
    private function getRequestHashData(msOrder $order)
    {
        $data = [
            $this->config['login'],
            $this->formatSum($order->get('cost')), //TODO: old decimal 6
            $order->get('id')
        ];

        if ($this->config['fiskal']) {
            $receipt = $this->modx->toJSON($this->getReceipt($order));
            $data[] = $receipt;
        }

        $data[] = $this->config['pass1'];

        return $data;
    }

    /**
     * Передача товаров для фискализации
     * @param msOrder $order
     * @return array
     */
    private function getReceipt(msOrder $order)
    {
        /** @var msProduct[] $products */
        $products = $order->getMany('Products');
        $out = [
            'items' => []
        ];

        if (!$products) {
            return $out;
        }

        foreach ($products as $product) {
            $out['items'][] = [
                'name' => $product->get('name'),
                'quantity' => $product->get('count'),
                'sum' => $product->get('cost'),
                'tax' => $this->config['tax']
            ];
        }

        if ($order->delivery_cost) {
            $out['items'][] = [
                'name' => 'Доставка',
                'quantity' => 1,
                'sum' => $order->delivery_cost,
                'tax' => $this->config['tax']
            ];
        }

        return $out;
    }

    private function log($text, $data)
    {
        $this->modx->log(
            modX::LOG_LEVEL_ERROR,
            self::LOG_NAME . ' ' . $text . print_r($data, true)
        );
    }
}