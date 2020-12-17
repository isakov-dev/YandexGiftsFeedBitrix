<?php

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule("iblock");

class YandexFeed {

    private $iBlockId;
    private $dataPath;
    private $shopName;
    private $shopSite;

    public function __construct($iBlockId, $dataPath, $shopName, $shopSite) {

        $this->iBlockId = $iBlockId;
        $this->dataPath = $dataPath;
        $this->shopName = $shopName;
        $this->shopSite = $shopSite;

    }

    public function generateFeed() {

        $feed = null;
        $data = $this->readData();

        if ($data) {

            $productsIds = array();
            $giftsIds = array();

            foreach ($data as $item) {

                if (isset($item->product))
                    $productsIds[] = $item->product;

                if (isset($item->gift))
                    $giftsIds[] = $item->gift;

            }

            $products = $this->getProductsInfo($productsIds);
            $categories = $this->getCategoriesInfo($products);
            $gifts = $this->getGiftsInfo($giftsIds);

            $feed = $this->makeXML($products, $categories, $gifts, $data);

        }

        return $feed;

    }

    private function readData() {

        if (file_exists($this->dataPath)) {

            $data = json_decode(file_get_contents($this->dataPath));

            if (!empty($data)) return $data;
            else return false;

        } else return false;

    }

    private function getProductsInfo($productsIds) {

        $products = array();

        $arSelect = Array("ID", "NAME", "IBLOCK_SECTION_ID", "DETAIL_PAGE_URL", "DETAIL_PICTURE",
            "PROPERTY_PROIZVODITEL");
        $arFilter = Array("IBLOCK_ID" => IntVal($this->iBlockId), "ACTIVE" => "Y", "ID" => $productsIds);
        $res = CIBlockElement::GetList(Array(), $arFilter, false, false, $arSelect);

        while($ob = $res->GetNextElement()) {

            $arFields = $ob->GetFields();

            if ($arFields['DETAIL_PICTURE'] && $arFields['IBLOCK_SECTION_ID']
                && $arFields['PROPERTY_PROIZVODITEL_VALUE']) {

                $priceInfo = CPrice::GetBasePrice($arFields['ID']);
                if ($priceInfo['PRICE']) {

                    $products[$arFields['ID']] = array(
                        'id' => $arFields['ID'],
                        'name' => $arFields['NAME'],
                        'section' => $arFields['IBLOCK_SECTION_ID'],
                        'url' => $arFields['DETAIL_PAGE_URL'],
                        'picture' => CFile::GetPath($arFields['DETAIL_PICTURE']),
                        'vendor' => $arFields['PROPERTY_PROIZVODITEL_VALUE'],
                        'price' => $priceInfo['PRICE'],
                        'available' => intval($priceInfo['PRODUCT_QUANTITY']) > 0,
                    );

                }

            }

        }

        return $products;

    }

    private function getCategoriesInfo($products) {

        $sectionIds = array();
        $sections = array();

        foreach ($products as $product) {
            $sectionIds[] = $product['section'];
        }

        $sectionIds = array_unique($sectionIds);

        foreach ($sectionIds as $id) {

            $list = CIBlockSection::GetNavChain(false, $id, array(), true);
            foreach ($list as $arSection) {
                $sections[$arSection['ID']] = array(
                    'id' => $arSection['ID'],
                    'name' => $arSection['NAME'],
                    'parent_id' => $arSection['IBLOCK_SECTION_ID'],
                );
            }

        }

        return $sections;

    }

    private function getGiftsInfo($giftsIds) {

        $gifts = array();

        $arSelect = Array("ID", "NAME", "PREVIEW_PICTURE", "DETAIL_PICTURE");
        $arFilter = Array("IBLOCK_ID" => IntVal($this->iBlockId), "ACTIVE" => "Y", "ID" => $giftsIds);
        $res = CIBlockElement::GetList(Array(), $arFilter, false, false, $arSelect);

        while($ob = $res->GetNextElement()) {

            $arFields = $ob->GetFields();

            if ($arFields['DETAIL_PICTURE'] || $arFields['PREVIEW_PICTURE']) {

                $gifts[$arFields['ID']] = array(
                    'id' => $arFields['ID'],
                    'name' => $arFields['NAME'],
                    'picture' => ($arFields['DETAIL_PICTURE'] ? CFile::GetPath($arFields['DETAIL_PICTURE'])
                        : CFile::GetPath($arFields['PREVIEW_PICTURE'])),
                );

            }

        }
        
        return $gifts;

    }

    private function makeXML($productsData, $categoriesData, $giftsData = false, $promosData = false) {

        $objDateTime = new DateTime('NOW');

        $xml = new SimpleXMLElement('<yml_catalog/>');
        $xml->addAttribute('date', $objDateTime->format('c'));

        $shop = $xml->addChild('shop');
        $shop->addChild('name', $this->shopName);
        $shop->addChild('company', $this->shopName);
        $shop->addChild('url', $this->shopSite);

        $currencies = $shop->addChild('currencies');
        $currency = $currencies->addChild('currency');
        $currency->addAttribute('id', 'RUR');
        $currency->addAttribute('rate', '1');

        $categories = $shop->addChild('categories');

        foreach ($categoriesData as $categoryData) {

            $category = $categories->addChild('category', $categoryData['name']);
            $category->addAttribute('id', $categoryData['id']);

            if ($categoryData['parent_id'])
                $category->addAttribute('parentId', $categoryData['parent_id']);

        }

        $offers = $shop->addChild('offers');

        foreach ($productsData as $productData) {

            $offer = $offers->addChild('offer');
            $offer->addAttribute('id', $productData['id']);
            $offer->addAttribute('type', 'vendor.model');
            $offer->addAttribute('available', ($productData['available'] ? 'true' : 'false'));
            $offer->addChild('url', $this->shopSite.$productData['url']);
            $offer->addChild('price', $productData['price']);
            $offer->addChild('currencyId', 'RUR');
            $offer->addChild('categoryId', $productData['section']);
            $offer->addChild('picture', $this->shopSite.$productData['picture']);
            $offer->addChild('vendor', $productData['vendor']);
            $offer->addChild('model', $productData['name']);

        }

        if ($giftsData) {

            $gifts = $shop->addChild('gifts');

            foreach ($giftsData as $giftData) {

                $gift = $gifts->addChild('gift');
                $gift->addAttribute('id', $giftData['id']);
                $gift->addChild('name', $giftData['name']);
                $gift->addChild('picture', $this->shopSite.$giftData['picture']);

            }

            $promos = $shop->addChild('promos');

            foreach ($promosData as $index => $promoData) {

                if (isset($promoData->product) && isset($promoData->gift)) {

                    $promo = $promos->addChild('promo');
                    $promo->addAttribute('id', 'promo'.$index);
                    $promo->addAttribute('type', 'gift with purchase');
                    $promo->addChild('url', $this->shopSite.$productsData[$promoData->product]['url']);

                    $purchase = $promo->addChild('purchase');
                    $purchase->addChild('product')->addAttribute('offer-id',  $promoData->product);

                    $promoGifts = $promo->addChild('promo-gifts');
                    $promoGifts->addChild('promo-gift')->addAttribute('gift-id', $promoData->gift);

                }

            }

        }

        return $xml->asXML();

    }

}