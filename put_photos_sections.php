<? require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?
// УДОБНЫЙ ВЫВОД
if (!function_exists('p')) {
    function p($obj, $name = "", $dump = false) {
        global $USER;
        if($USER->IsAdmin()) {
            echo "<br>";
            echo "<pre style='font-size:14px;border:#ccc;background:#efefef;padding:10px;'>";
            if($name) echo $name."<br>";
            ($dump) ? var_dump($obj) : print_r($obj);
            echo "</pre>";
            echo "<br><br>";
        }
    }
}

if (CModule::IncludeModule('iblock')) {

    // ПОЛУЧИТЬ СПИСОК РАЗДЕЛОВ СТАРОГО КАТАЛОГА
    $old_catalog_code_list = [];
    $arOrder = Array("CODE"=>"ASC");
    $arFilter = Array("IBLOCK_ID"=>OLD_CATALOG_ID);
    $arSelect = Array("ID", "NAME", "CODE", "PICTURE", "SORT");
    $resOld = CIBlockSection::GetList($arOrder, $arFilter, false, $arSelect);
    while($ob = $resOld->GetNext()){

        // ЗАМЕНА СТАРОГО СИМВОЛЬНОГО КОДА НА НОВЫЙ
        switch ($ob['CODE']) {
            // ОСНОВНЫЕ РАЗДЕЛЫ -> ИНФОБЛОКИ
            case "krepezh":
                $ob['CODE'] = "soedinitelnaya_furnitura_krepezh";
                break;
            case "kukhonnye_moyki_i_smesiteli":
                $ob['CODE'] = "moyki_i_smesiteli";
                break;
            case "navesnye_polki_i_aksessuary":
                $ob['CODE'] = "navesnye_polki_reylingi";
                break;

            /* ... */

            case "profil_alyuminievyy":
                $ob['CODE'] = "profil_alyuminievyy_dlya_fasadov";
                break;
        }
        $old_catalog_code_list[$ob['CODE']]['NAME'] = $ob['NAME'];
        $old_catalog_code_list[$ob['CODE']]['SORT'] = $ob['SORT'];
        $old_catalog_code_list[$ob['CODE']]['PICTURE'] = $ob['PICTURE'];
    }
    unset($ob);

    p("всего в old_catalog_code_list: ".count($old_catalog_code_list));

    // ОБНОВИТЬ ИНФОБЛОКИ НОВОГО КАТАЛОГА
    foreach (CATALOG_SECTION_ID_LIST as $iblock_id) {
        $ib_res = CIBlock::GetByID($iblock_id);
        if ($iblock_res = $ib_res->GetNext()) {
            $ib = new CIBlock;
            $arFields = Array(
                "ACTIVE" => $iblock_res['ACTIVE'],
                "NAME" => $iblock_res['NAME'],
                "CODE" => Cutil::translit($iblock_res['NAME'],"ru"),
                "SORT" => $old_catalog_code_list[$iblock_res['CODE']]['SORT'],
                "PICTURE" => CFile::MakeFileArray($old_catalog_code_list[$iblock_res['CODE']]['PICTURE']),
                "LIST_PAGE_URL" => "#SITE_DIR#catalog/#IBLOCK_CODE#/",
                "SECTION_PAGE_URL" => "#SITE_DIR#catalog/#IBLOCK_CODE#/#SECTION_CODE_PATH#/",
				"DETAIL_PAGE_URL" => "#SITE_DIR#catalog/#IBLOCK_CODE#/#ELEMENT_CODE#/",
                "IBLOCK_TYPE_ID" => $iblock_res['IBLOCK_TYPE_ID'],
            );
            unset($old_catalog_code_list[$iblock_res['CODE']]);
            $resIBlockUPD = $ib->Update($iblock_id, $arFields);
            p($resIBlockUPD, $iblock_res['NAME'], true);
        }
    }

    // ПОЛУЧИТЬ СПИСОК ИНФОБЛОКОВ НОВОГО КАТАЛОГА
    $arFilterBlocks = ['TYPE'=>'1c_catalog', 'SITE_ID'=>SITE_ID, 'ACTIVE'=>'Y'];
    $resBlocksList = CIBlock::GetList(['CODE' => 'ASC'], $arFilterBlocks, false);
    $blocksList = [];
    $resSectionBlockList = [];
    while ($arFieldsBlocksList = $resBlocksList->Fetch()) {
        $blocksList[$arFieldsBlocksList['CODE']]['NAME'] = $arFieldsBlocksList['NAME'];
        $blocksList[$arFieldsBlocksList['CODE']]['SORT'] = $arFieldsBlocksList['SORT'];
        $blocksList[$arFieldsBlocksList['CODE']]['PICTURE'] = $arFieldsBlocksList['PICTURE'];

        // ПОЛУЧИТЬ СПИСОК РАЗДЕЛОВ ИНФОБЛОКА НОВОГО КАТАЛОГА
        $arOrder = Array("CODE"=>"ASC");
        $arFilter = Array("IBLOCK_ID"=>$arFieldsBlocksList['ID']);
        $arSelect = Array("ID", "NAME", "CODE", "PICTURE", "SORT", "IBLOCK_SECTION_ID");
        $resSection = CIBlockSection::GetList($arOrder, $arFilter, false, $arSelect);
        while($ob = $resSection->GetNext()) {
            $resSectionBlockList[$ob['CODE']]['IBLOCK_CODE'] = $arFieldsBlocksList['CODE'];
            $resSectionBlockList[$ob['CODE']]['NAME'] = $ob['NAME'];
            $resSectionBlockList[$ob['CODE']]['SORT'] = $ob['SORT'];
            $resSectionBlockList[$ob['CODE']]['PICTURE'] = $ob['PICTURE'];

            // ОБНОВИТЬ РАЗДЕЛ ИНФОБЛОКА НОВОГО КАТАЛОГА
            if(!isset($old_catalog_code_list[$ob['CODE']])) continue;
            $bs = new CIBlockSection;
            $arPICTURE = CFile::MakeFileArray($old_catalog_code_list[$ob['CODE']]['PICTURE']);
            $arFields = Array(
                "ACTIVE" => "Y",
                "IBLOCK_SECTION_ID" => $ob['IBLOCK_SECTION_ID'],
                "IBLOCK_ID" => $arFieldsBlocksList['ID'],
                "NAME" => $ob['NAME'],
                "SORT" => $old_catalog_code_list[$ob['CODE']]['SORT'],
                "PICTURE" => $arPICTURE
            );
            unset($old_catalog_code_list[$ob['CODE']]);
            $resSectionUPD = $bs->Update($ob['ID'], $arFields);
            p($resSectionUPD, $ob['NAME'], true);
        }
    }
    unset($ob);

    p("осталось в old_catalog_code_list: ".count($old_catalog_code_list));
    p($old_catalog_code_list, "old_catalog_code_list");
    p($resSectionBlockList, "resSectionBlockList");
}
?>
<? require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
