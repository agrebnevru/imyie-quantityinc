<?php

IncludeModuleLangFile(__FILE__);

class CIMYIEQuantityInc
{
    public static $PROPERTY_CODE_INT = "IMYIE_QINC_QUANTITY_INC";
    public static $PROPERTY_CODE_DATETIME = "IMYIE_QINC_QUANTITY_DATETIME";

    public static function OnAfterIBlockElementAddHandler(&$arFields)
    {
        $ELEMENT_ID = $arFields["ID"];
        $IBLOCK_ID = $arFields["IBLOCK_ID"];
        if (IntVal($ELEMENT_ID) > 0 && IntVal($IBLOCK_ID) > 0 && CModule::IncludeModule(
                'iblock'
            ) && CModule::IncludeModule('catalog')) {
            $arProduct = CCatalogProduct::GetByID($ELEMENT_ID);
            if (!$arProduct) {
                $arCatalog = CCatalog::GetByID($IBLOCK_ID);
                if (IntVal($arCatalog["OFFERS_IBLOCK_ID"]) > 0) {
                    self::_SetPropertyValue($ELEMENT_ID, $IBLOCK_ID);
                }
            }
        }
    }

    public static function OnBeforeIBlockElementUpdateHandler(&$arFields)
    {
        $ELEMENT_ID = $arFields["ID"];
        $IBLOCK_ID = $arFields["IBLOCK_ID"];
        if (IntVal($ELEMENT_ID) > 0 && IntVal($IBLOCK_ID) > 0 && CModule::IncludeModule(
                'iblock'
            ) && CModule::IncludeModule('catalog')) {
            static $PROP_CACHE = array();
            if (is_array($PROP_CACHE) && count($PROP_CACHE) < 1) {
                $resPr = CIBlockElement::GetProperty(
                    $IBLOCK_ID,
                    $ELEMENT_ID,
                    array(),
                    array("CODE" => self::$PROPERTY_CODE_INT)
                );
                if ($arProperty = $resPr->GetNext()) {
                    $PROP_CACHE[$arProperty["ID"]] = $arProperty["VALUE"];
                }
                $resPr = CIBlockElement::GetProperty(
                    $IBLOCK_ID,
                    $ELEMENT_ID,
                    array(),
                    array("CODE" => self::$PROPERTY_CODE_DATETIME)
                );
                if ($arProperty = $resPr->GetNext()) {
                    $PROP_CACHE[$arProperty["ID"]] = $arProperty["VALUE"];
                }
            }
            foreach ($arFields["PROPERTY_VALUES"] as $propID => $arValue) {
                if (isset($PROP_CACHE[$propID])) {
                    $key = key($arValue);
                    $arFields["PROPERTY_VALUES"][$propID][$key]["VALUE"] = $PROP_CACHE[$propID];
                }
            }
        }
    }

    public static function OnProductAddHandler($ID, $arFields)
    {
        $PRODUCT_ID = $ID;
        if (IntVal($PRODUCT_ID) > 0 && CModule::IncludeModule('iblock') && CModule::IncludeModule('catalog')) {
            $srElement = CIBlockElement::GetByID($PRODUCT_ID);
            if ($arElement = $srElement->GetNext()) {
                $ELEMENT_ID = $arElement["ID"];
                $IBLOCK_ID = $arElement["IBLOCK_ID"];
                if (IntVal($ELEMENT_ID) > 0 && IntVal($IBLOCK_ID) > 0) {
                    $arCatalog = CCatalog::GetByID($IBLOCK_ID);
                    if (IntVal($arCatalog["PRODUCT_IBLOCK_ID"]) > 0) {
                        // This iblock with offers
                        $dbRes = CIBlockElement::GetProperty(
                            $IBLOCK_ID,
                            $ELEMENT_ID,
                            array(),
                            array("ID" => $arCatalog["SKU_PROPERTY_ID"])
                        );
                        if ($arProp = $dbRes->Fetch()) {
                            self::_SetPropertyValue($arProp["VALUE"], $arCatalog["PRODUCT_IBLOCK_ID"]);
                        }
                    } else {
                        // This iblock with products
                        self::_SetPropertyValue($ELEMENT_ID, $IBLOCK_ID);
                    }
                }
            }
        }
    }

    public static function OnBeforeProductUpdateHandler($ID, &$arFields)
    {
        $PRODUCT_ID = $ID;
        if (IntVal($PRODUCT_ID) > 0 && CModule::IncludeModule('iblock') && CModule::IncludeModule('catalog')) {
            $srElement = CIBlockElement::GetByID($PRODUCT_ID);
            if ($arElement = $srElement->GetNext()) {
                $ELEMENT_ID = $arElement["ID"];
                $IBLOCK_ID = $arElement["IBLOCK_ID"];
                if (IntVal($ELEMENT_ID) > 0 && IntVal($IBLOCK_ID) > 0) {
                    $arProductOld = CCatalogProduct::GetByID($ELEMENT_ID);
                    if ($arProductOld["QUANTITY"] < $arFields["QUANTITY"]) {
                        $arCatalog = CCatalog::GetByID($IBLOCK_ID);
                        if (IntVal($arCatalog["PRODUCT_IBLOCK_ID"]) > 0) {
                            // This iblock with offers
                            $dbRes = CIBlockElement::GetProperty(
                                $IBLOCK_ID,
                                $ELEMENT_ID,
                                array(),
                                array("ID" => $arCatalog["SKU_PROPERTY_ID"])
                            );
                            if ($arProp = $dbRes->Fetch()) {
                                self::_SetPropertyValue($arProp["VALUE"], $arCatalog["PRODUCT_IBLOCK_ID"]);
                            }
                        } else {
                            // This iblock with products
                            self::_SetPropertyValue($ELEMENT_ID, $IBLOCK_ID);
                        }
                    }
                }
            }
        }
    }

    public static function _SetPropertyValue($ELEMENT_ID, $IBLOCK_ID): void
    {
        $time = time();
        $arIDs = self::CheckProperties($IBLOCK_ID);
        CIBlockElement::SetPropertyValues($ELEMENT_ID, $IBLOCK_ID, $time, $arIDs["PROPERTY_CODE_INT"]);
        CIBlockElement::SetPropertyValues(
            $ELEMENT_ID,
            $IBLOCK_ID,
            ConvertTimeStamp($time, "FULL"),
            $arIDs["PROPERTY_CODE_DATETIME"]
        );
    }

    public static function CheckProperties($IBLOCK_ID, $PROPERTY_CODE_INT = "", $PROPERTY_CODE_DATETIME = ""): array
    {
        $return = array();

        if ($PROPERTY_CODE_INT == "") {
            $PROPERTY_CODE_INT = self::$PROPERTY_CODE_INT;
        }
        if ($PROPERTY_CODE_DATETIME == "") {
            $PROPERTY_CODE_DATETIME = self::$PROPERTY_CODE_DATETIME;
        }

        // int property
        $dbProperties = CIBlockProperty::GetList(
            array("sort" => "asc", "name" => "asc"),
            array("IBLOCK_ID" => $IBLOCK_ID, "CODE" => $PROPERTY_CODE_INT)
        );
        if (!$arFields = $dbProperties->GetNext()) {
            // add property
            $arFields = array(
                "IBLOCK_ID" => $IBLOCK_ID,
                "NAME" => GetMessage("IMYIE_QINC_PROP_NAME_INT"),
                "ACTIVE" => "Y",
                "SORT" => "100000",
                "CODE" => $PROPERTY_CODE_INT,
                "PROPERTY_TYPE" => "N",
                "MULTIPLE" => "N",
            );

            $ibp = new CIBlockProperty();
            $PropID = $ibp->Add($arFields);
            if (IntVal($PropID)) {
                $return["PROPERTY_CODE_INT"] = $PropID;
            }
        } else {
            $return["PROPERTY_CODE_INT"] = $arFields["ID"];
        }
        // date_time property
        $dbProperties = CIBlockProperty::GetList(
            array("sort" => "asc", "name" => "asc"),
            array("IBLOCK_ID" => $IBLOCK_ID, "CODE" => $PROPERTY_CODE_DATETIME)
        );
        if (!$arFields = $dbProperties->GetNext()) {
            // add property
            $arFields = array(
                "IBLOCK_ID" => $IBLOCK_ID,
                "NAME" => GetMessage("IMYIE_QINC_PROP_NAME_DATETIME"),
                "ACTIVE" => "Y",
                "SORT" => "100001",
                "CODE" => $PROPERTY_CODE_DATETIME,
                "PROPERTY_TYPE" => "S",
                "USER_TYPE" => "DateTime",
                "MULTIPLE" => "N",
            );

            $ibp = new CIBlockProperty();
            $PropID = $ibp->Add($arFields);
            if (IntVal($PropID)) {
                $return["PROPERTY_CODE_DATETIME"] = $PropID;
            }
        } else {
            $return["PROPERTY_CODE_DATETIME"] = $arFields["ID"];
        }

        return $return;
    }
}
