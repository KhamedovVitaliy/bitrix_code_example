<? define('STOP_STATISTICS', true);
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$GLOBALS['APPLICATION']->RestartBuffer();

if(empty($_GET)) exit();

CModule::IncludeModule("iblock");
//CModule::IncludeModule("catalog");

$iblock_id = CATALOG_IBLOCK_ID;
$section_code = $_GET['section'];

// Статус товара
switch ($_GET['status']) {
    case 'news':
        $status = 'Новинка';
        break;
    case 'ozhidaetsya':
        $status = 'Ожидается';
        break;
    case 'sale':
        $status = 'Распродажа';
        break;
    default:
        $status = '';
        break;
}

// Имя файла из названия раздела
$file_name = $_GET['file_name'];

// Увеличиваем лимиты, если выгрузка ВСЕГО каталога
if(empty($_GET['section']) && empty($_GET['status'])) ini_set('memory_limit', '1000M');

$arOrder = array("PROPERTY_DATA_SOZDANIYA" => "desc", "PROPERTY_DATA_IZMENENIYA" => "desc");
$arSelect = Array("IBLOCK_ID", "ID", "DETAIL_TEXT", "DETAIL_PAGE_URL", "DETAIL_PICTURE", "PREVIEW_PICTURE", "PRICE_2", "PROPERTY_*");
if(empty($_GET['section'])){
    $arFilter = Array(
        "IBLOCK_ID" => $iblock_id,
        "PROPERTY_STATUS_VALUE" => $status
    );
    if($status !== 'Ожидается') $arFilter = $arFilter + array("=AVAILABLE" => "Y");
}
elseif ($_GET['section'] == 'new_collection' || $_GET['section'] == 'shkolnaya_forma') {
    switch ($_GET['section']) {
        case 'new_collection':
            $section_id = CATALOG_NEW_COLLECTION_ID;
            break;
        case 'shkolnaya_forma':
            $section_id = CATALOG_SCHOOL_ID;
            break;
    }
    $arFilter = Array(
        "IBLOCK_ID" => $iblock_id,
        "=AVAILABLE" => "Y",
        "SECTION_ID" => $section_id,
        "INCLUDE_SUBSECTIONS" => "Y"
    );
}
else {
    $arFilter = Array(
        "IBLOCK_ID" => $iblock_id,
        "=AVAILABLE" => "Y",
        "SECTION_CODE" => $section_code
    );
}

// Получаем список товаров раздела
$resItems = CIBlockElement::GetList($arOrder, $arFilter, false, false, $arSelect);

$arProduct = array();
$arIds = array();

// Записываем все свойства в товар
while($obItem = $resItems->GetNextElement()){
    $arItem = $obItem->GetFields();

    $arItem["PROPERTIES"] = $obItem->GetProperties();

    $arItem["DISPLAY_PROPERTIES"] = array();
    foreach ($arItem["PROPERTIES"] as $pid=>$prop) {
        if (
            (is_array($prop["VALUE"]) && count($prop["VALUE"]) > 0)
            || (!is_array($prop["VALUE"]) && strlen((string)$prop["VALUE"]) > 0)
        ) {
            $arItem["DISPLAY_PROPERTIES"][$pid] = CIBlockFormatProperties::GetDisplayValue($arItem, $prop, "catalog_out");
        }
    }

    $arIds[] = $arItem['ID'];	//формируем массив ID элементов - он нам понадобится для красивой выборки ТП
    $arProduct[$arItem['ID']] = $arItem;	//формируем массив всех товаров, к нему будем прикреплять ТП
}
unset($obItem, $arItem);

// Записываем ТП в товар
$res = CCatalogSKU::getOffersList(
    $arIds,	// массив ID товаров
    $iblock_id,	// указываете ID инфоблока только в том случае, когда ВЕСЬ массив товаров из одного инфоблока и он известен
    array('ACTIVE' => 'Y', '>CATALOG_QUANTITY' => 0),	// дополнительный фильтр предложений. по умолчанию пуст.
    array('ID', 'IBLOCK_ID', 'DETAIL_PICTURE'),  // массив полей предложений. даже если пуст - вернет ID и IBLOCK_ID
    array("CODE"=> array('ROST', 'OSNOVNOY_TSVET', 'RAZMER_ROSS', 'MORE_PHOTO'))
);
foreach($res as $key => $arItem){
    $arProduct[$key]["OFFERS"] = $arItem;
}
unset($res);

function catalog_my_mb_ucfirst($str) {
    $fc = mb_strtoupper(mb_substr($str, 0, 1));
    return $fc.mb_substr($str, 1);
}

// Создаем массив цветов с кодом и названием
$res = CIBlockElement::GetList(Array(), Array("IBLOCK_ID"=>18, "ACTIVE"=>"Y"), false, Array(), Array("ID", "XML_ID", "NAME", "PROPERTY_NAME_OF_COLORS"));
while($obColors = $res->GetNextElement())
{
    $arFields = $obColors->GetFields();
    $colors_array[$arFields["XML_ID"]]["CODE"] = mb_strtoupper($arFields["NAME"]);
    $colors_array[$arFields["XML_ID"]]["NAME"] = catalog_my_mb_ucfirst(mb_strtolower($arFields["PROPERTY_NAME_OF_COLORS_VALUE"]));
}
unset($res);

$prod = array(); // массив отображаемых свойств товара
foreach($arProduct as $key => $sku){	//arProduct - массив товара с его свойствами и ТП

    if (empty($sku['OFFERS'])) continue;

    // Собираем все фотографии из ТП в кучу
    foreach ($sku['OFFERS'] as $offer) {
        if($offer['PROPERTIES']['MORE_PHOTO']['VALUE'] || $offer['DETAIL_PICTURE']) {
            // Основное фото ТП
            $arProduct[$key]['ALL_PICTURES'][] = array(
                'ID' => $offer['DETAIL_PICTURE'],
                'PATH' => CFile::GetPath($offer['DETAIL_PICTURE'])
            );

            // Дополнительная галерея фото
            foreach($offer['PROPERTIES']['MORE_PHOTO']['VALUE'] as $key2 => $picId) {
                $arProduct[$key]['ALL_PICTURES'][] = array(
                    'ID' => $picId,
                    'PATH' => CFile::GetPath($picId)
                );
            }
        }
    }

    // Обрезаем ненужный текст в описании товара
    if($sku["DETAIL_TEXT"])
    {
        $full_text = str_replace(PHP_EOL, ' ', strip_tags($sku["DETAIL_TEXT"]));
        if (!$cut_text = substr(stristr($full_text, "BRAND"), 5)) {
            $cut_text = $full_text;
        }

        $pos1 = strpos($cut_text, ".");
        $pos2 = strpos($cut_text, ",");
        if ($pos2) {
            if ($pos1 < $pos2) {
                $name = stristr($cut_text, ".", true);
            } else {
                $name = stristr($cut_text, ",", true);
            }
        }
        else $name = stristr($cut_text, ".", true);
    }

    // Заполняем массив необходимыми свойствами
    $prod[$key]['ART']          = $sku['DISPLAY_PROPERTIES']['CML2_ARTICLE']["VALUE"];
    $prod[$key]['STATUS']       = $sku['DISPLAY_PROPERTIES']['STATUS']["VALUE"];
    $prod[$key]['NAME']         = $name;
    $prod[$key]['DESCRIPTION']  = $full_text;
    $prod[$key]['LINK']         = SITE_SERVER_PROTOCOL.$_SERVER['SERVER_NAME'].$sku["DETAIL_PAGE_URL"];

    $photo = array();
    foreach($arProduct[$key]['ALL_PICTURES'] as $ph) {
        $photo[$ph['ID']] = SITE_SERVER_PROTOCOL.$_SERVER['SERVER_NAME'].$ph['PATH'];
    }

    $prod[$key]['PHOTOS']       = implode("; ", $photo);
    $prod[$key]['PRICE']        = intval($sku['PRICE_2']);

    $rost = array();
    $color = array();
    $size = array();
    $params_key = array();
    $params_key2 = array();

    foreach($sku['OFFERS'] as $row){	//row - массив ТП с его свойствами
        $rost[$row['PROPERTIES']['ROST']['VALUE']] = $row['PROPERTIES']['ROST']['VALUE'];

        $colorKey = $row['PROPERTIES']['OSNOVNOY_TSVET']['VALUE'];
        $color[$colorKey] = $colors_array[$colorKey]["NAME"];

        $size[$row['PROPERTIES']['RAZMER_ROSS']['VALUE']] = $row['PROPERTIES']['RAZMER_ROSS']['VALUE'];

        $params_key[] = $row['PROPERTIES']['RAZMER_ROSS']['VALUE'].'-'.$row['PROPERTIES']['ROST']['VALUE'].'-'.$colors_array[$colorKey]["NAME"].'-'.$colorKey;
        $params_key2[] = $row['PROPERTIES']['RAZMER_ROSS']['VALUE'].'-'.$row['PROPERTIES']['ROST']['VALUE'].'/'.$colors_array[$colorKey]["NAME"].'-'.$colorKey;
    }

    $prod[$key]['ROST']         = implode("; ", $rost);
    $prod[$key]['ROST_MODELI']  = $sku['DISPLAY_PROPERTIES']['ROST_MODELI_NA_FOTO']["VALUE"];
    $prod[$key]['COLOR']        = implode("; ", $color);
    ksort($size);
    $prod[$key]['SIZE']         = implode("; ", $size);
    $prod[$key]['PARAMS']       = implode("; ", $params_key);
    $prod[$key]['PARAMS2']       = implode("; ", $params_key2);
}

//открываем файл на запись. Если его нет - он будет создан
$fp = fopen($_SERVER['DOCUMENT_ROOT'].'/catalog/csv/catalog_'.$file_name.'.csv', 'w');

// форматируем в UTF-8
fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));

// вставляем строку заголовков
fputcsv($fp, array('артикул', 'статус', 'наименование','описание','ссылка на товар', 'ссылка на фото', 'опт цена', 'рост', 'рост модели на фото', 'цвет', 'размер', 'связка', 'связка_2'), ";");

// вставляем строки свойств товара
foreach ($prod as $fields) {
    fputcsv($fp, $fields, ";");
}

//закрываем дескриптор и очищаем буфер
fclose($fp);
ob_end_clean();

// получаем файл и отдаем пользователю
$file = $_SERVER['DOCUMENT_ROOT']."/catalog/csv/catalog_".$file_name.".csv";
if (file_exists($file)) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream; charset=UTF-8', true);
    header('Content-Disposition: attachment; filename="'.basename($file).'"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file));
    readfile($file);
}
exit;
