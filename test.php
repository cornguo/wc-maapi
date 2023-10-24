<?php
require 'class-market-america-api.php';

$s = new MarketAmericaApi(['offerId' => 'offID', 'advertiserId' => 'advID', 'clickId' => 'CLICKID', 'rid' => 'RID', 'commissionRate' => '0.1']);
$ret = $s->trackConversion([
    'date'        => '2023-10-15',
    'orderId'     => '123123',
    'orderAmount' => 100
]);

var_dump(json_decode($ret));
