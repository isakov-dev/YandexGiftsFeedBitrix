<?php

require_once "../YandexFeed.php";

switch ($_REQUEST['action']) {

    case 'get_yandex_gifts_feed':

        $feed = new YandexFeed(21, $_SERVER['DOCUMENT_ROOT'].'/backend/data/products.json',
            'Техника Чистоты', 'https://tc-clean.ru');
        $result = $feed->generateFeed();

        Header('Content-type: text/xml');
        print($result);

        break;

    default:
        http_response_code(400);

}