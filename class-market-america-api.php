<?php

class MarketAmericaApi {
    protected $endpoint = 'https://api.hasoffers.com/Api';
    protected $params = [
        'Format'             => 'json',
        'Target'             => 'Conversion',
        'Method'             => 'create',
        'Service'            => 'HasOffers',
        'Version'            => 2,
        'NetworkId'          => 'marktamerica',
        'NetworkToken'       => 'NETPYKNAYOswzsboApxaL6GPQRiY2s',
        'data[affiliate_id]' => 12
    ];
    protected $offerId = null;
    protected $advertiserId = null;
    protected $rid = null;
    protected $clickId = null;
    protected $commissionRate = 0;

    public function __construct($config = [], $params = []) {
        $this->offerId = $config['offerId'];
        $this->advertiserId = $config['advertiserId'];
        $this->rid = $config['rid'];
        $this->clickId = $config['clickId'];
        $this->commissionRate = $config['commissionRate'];
        $this->params = array_merge($this->params, $params);
    }

    public function getPayout($val) {
        return round($val * $this->commissionRate, 2);
    }

    public function trackConversion($data) {
        $reqData = $this->buildOrderData($data);
        $reqData['payout'] = $this->getPayout($reqData['sale_amount']);
        $reqData['revenue'] = $reqData['payout'];

        return $this->request($reqData);
    }

    public function trackRefund($data) {
        $reqData = $this->buildOrderData($data, ['is_adjustment' => 1]);
        $reqData['payout'] = $this->getPayout($reqData['sale_amount']);

        return $this->request($reqData);
    }

    private function buildUrl($data = []) {
        $params = $this->params;

        $reqData = [
            'advertiser_id'   => $this->advertiserId,
            'ad_id'           => $this->clickId,
            'offer_id'        => $this->offerId,
            'affiliate_info1' => $this->rid,
        ];

        $data = array_merge($reqData, $data);

        foreach ($data as $key => $value) {
            $params['data[' . $key . ']'] = $value;
        }

        return $this->endpoint . '?' . http_build_query($params);
    }

    private function request($data = []) {
        return file_get_contents($this->buildUrl($data));
    }

    private function buildOrderData($data, $defaults = []) {
        $reqData = $defaults;

        $checkKeys = [
            'date'        => function ($val) { return ['session_datetime' => date('Y-m-d', strtotime($val))]; },
            'orderId'     => function ($val) { return ['advertiser_info' => $val]; },
            'orderAmount' => function ($val) { return ['sale_amount' => round(floatval($val), 2)]; },
        ];

        foreach ($checkKeys as $key => $value) {
            if (isset($data[$key])) {
                $reqData += $checkKeys[$key]($data[$key]);
            } else {
                throw new Exception('[' . $key . '] is not set');
            }
        }

        return $reqData;
    }
}
