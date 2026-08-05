<?php
use \Bitrix\Main;
use \Bitrix\Main\Loader;
use \Bitrix\Main\Error;
use \Bitrix\Main\Type\DateTime;
use \Bitrix\Main\Localization\Loc;
use \Bitrix\Iblock;
use \Bitrix\Iblock\Component\ElementList;
use \Bitrix\Iblock\Component\Element;

\Bitrix\Main\Loader::includeModule('iblock');

class Fire_ComponentElementList extends ElementList {
	
	static $SectionCodes = [];
	
	static function addSectionToGetNext($Item) {
		if($Item['ID'] && isset($Item['CODE']))
			self::$SectionCodes[$Item['ID']] = ['IBLOCK_SECTION_ID'=>$Item['IBLOCK_SECTION_ID'], 'CODE'=>$Item['CODE']];
	}
	
	static function toGetNext($Item, $detail='', $section='', $list='') {
		if(!is_array($Item))
			return $Item;
		
		$section = $section? $section : $Item['SECTION_PAGE_URL'];
		if(mb_strpos($section, '#SECTION_CODE_PATH#')!==false && ($Code = self::$SectionCodes[$Item['ID']])) {
			$path = [];
			do {
				$path[] = $Code['CODE'];
				if(!$Code['IBLOCK_SECTION_ID']) {
					$section = str_replace('#SECTION_CODE_PATH#', implode('/', array_reverse($path)), $section);
				}
			} while($Code = self::$SectionCodes[$Code['IBLOCK_SECTION_ID']]);
		}
		if(mb_strpos($section, '#')!==false)
			$section = CIBlock::ReplaceSectionUrl($section, $Item, true, 'S');
		
		$Item['SECTION_PAGE_URL'] = $section;
		
		foreach($Item as $FName=>$arFValue) {
			if(array_key_exists($FName.'_TYPE', $Item))
				$Item[$FName] = FormatText($arFValue, $Item[$FName.'_TYPE']);
			elseif(is_array($arFValue))
				$Item[$FName] = htmlspecialcharsEx($arFValue);
			elseif(preg_match("/[;&<>\"]/", $arFValue))
				$Item[$FName] = htmlspecialcharsEx($arFValue);
			else
				$Item[$FName] = $arFValue;
			$Item['~'.$FName] = $arFValue;
		}
		
		return $Item;
	}
	
	protected function processLinkAction() {
		self::checkAddBasket($this->arParams);
		parent::processLinkAction();
	}
	
	static function checkAddBasket($arParams) {
		global $APPLICATION, $USER;
		$strError = '';
		$successfulAdd = true;
		if(isset($_REQUEST[$arParams["ACTION_VARIABLE"]]) && isset($_REQUEST[$arParams["PRODUCT_ID_VARIABLE"]]))
		{
			if(isset($_REQUEST[$arParams["ACTION_VARIABLE"]."BUY"]))
				$action = "BUY";
			elseif(isset($_REQUEST[$arParams["ACTION_VARIABLE"]."ADD2BASKET"]))
				$action = "ADD2BASKET";
			else
				$action = mb_strtoupper($_REQUEST[$arParams["ACTION_VARIABLE"]]);

			$productID = intval($_REQUEST[$arParams["PRODUCT_ID_VARIABLE"]]);
			if (($action == "ADD2BASKET" || $action == "BUY") && $productID > 0)
			{
				if (Loader::includeModule("sale") && Loader::includeModule("catalog"))
				{
					$addByAjax = isset($_REQUEST['ajax_basket']) && 'Y' == $_REQUEST['ajax_basket'];
					$QUANTITY = 0;
					$product_properties = array();
					$intProductIBlockID = intval(CIBlockElement::GetIBlockByID($productID));
					if (0 < $intProductIBlockID)
					{
						if ($arParams['ADD_PROPERTIES_TO_BASKET'] == 'Y')
						{
							if ($intProductIBlockID == $arParams["IBLOCK_ID"])
							{
								if (!empty($arParams["PRODUCT_PROPERTIES"]))
								{
									if (
										isset($_REQUEST[$arParams["PRODUCT_PROPS_VARIABLE"]])
										&& is_array($_REQUEST[$arParams["PRODUCT_PROPS_VARIABLE"]])
									)
									{
										$product_properties = CIBlockPriceTools::CheckProductProperties(
											$arParams["IBLOCK_ID"],
											$productID,
											$arParams["PRODUCT_PROPERTIES"],
											$_REQUEST[$arParams["PRODUCT_PROPS_VARIABLE"]],
											$arParams['PARTIAL_PRODUCT_PROPERTIES'] == 'Y'
										);
										if (!is_array($product_properties))
										{
											$strError = GetMessage("CATALOG_PARTIAL_BASKET_PROPERTIES_ERROR");
											$successfulAdd = false;
										}
									}
									else
									{
										$strError = GetMessage("CATALOG_EMPTY_BASKET_PROPERTIES_ERROR");
										$successfulAdd = false;
									}
								}
							}
							else
							{
								$skuAddProps = (isset($_REQUEST['basket_props']) && !empty($_REQUEST['basket_props']) ? $_REQUEST['basket_props'] : '');
								if (!empty($arParams["OFFERS_CART_PROPERTIES"]) || !empty($skuAddProps))
								{
									$product_properties = CIBlockPriceTools::GetOfferProperties(
										$productID,
										$arParams["IBLOCK_ID"],
										$arParams["OFFERS_CART_PROPERTIES"],
										$skuAddProps
									);
								}
							}
						}
						$QUANTITY = 1;
					}
					else
					{
						$strError = GetMessage('CATALOG_ELEMENT_NOT_FOUND');
						$successfulAdd = false;
					}

					if ($successfulAdd)
					{
						//Выберем торговое предложение (у него нужно XML_ID и свойство CML2_LINK - ID товара)
						$arFilter = array(
							"IBLOCK_ID" => $intProductIBlockID,
							"ID" => $productID,
						);
						$arSelect = array(
							"ID",
							"IBLOCK_ID",
							"NAME",
							"XML_ID",
							"PROPERTY_CML2_LINK",
						);
						$rsOffers = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
						if ($arOffer = $rsOffers->GetNext())
						{/*
							$product_properties[] = array(
								"NAME" => "aID",
								"CODE" => "aID",
								"VALUE" => $arOffer["XML_ID"],
								"SORT" => 200,
							);					*/
							$main_item_id = intval($arOffer["PROPERTY_CML2_LINK_VALUE"]);
							if ($main_item_id > 0)
							{
								$arFilter = array(
									"IBLOCK_ID" => $arParams["IBLOCK_ID"],
									"ID" => $main_item_id,
								);
								$arSelect = array(
									"ID",
									"IBLOCK_ID",
									"NAME",
									"XML_ID",
									"IBLOCK_SECTION_ID",
									"PROPERTY_pics"
								);
								$rsItems = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
								if ($arItem = $rsItems->GetNext())
								{/*
									if ($arItem["XML_ID"] != "")
									{
										$product_properties[] = array(
											"NAME" => "prodID",
											"CODE" => "prodID",
											"VALUE" => $arItem["XML_ID"],
											"SORT" => 200,
										);
										$product_properties[] = array(
											"NAME" => "Ссылка на stripmag",
											"CODE" => "stripmag",
											"VALUE" => "http://stripmag.ru/prod.php?id=".$arItem["XML_ID"],
											"SORT" => 200,
										);
									}*/
									if ($arItem["PROPERTY_PICS_VALUE"] != "")
									{
										if(is_array($arItem["PROPERTY_PICS_VALUE"]))
											$arItem["PROPERTY_PICS_VALUE"] = current($arItem["PROPERTY_PICS_VALUE"]);
										$product_properties[] = array(
											"NAME" => "Картинка",
											"CODE" => "PICTURE",
											"VALUE" => $arItem["PROPERTY_PICS_VALUE"],
											"SORT" => 200,
										);
									}
								}
							}
						}
						if(!Add2BasketByProductID($productID, $QUANTITY, array(), $product_properties))
						{
							if ($ex = $APPLICATION->GetException())
								$strError = $ex->GetString();
							else
								$strError = GetMessage("CATALOG_ERROR2BASKET");
							$successfulAdd = false;
						}
					}

					if ($addByAjax)
					{
						if ($successfulAdd)
						{
							$metrika_product = array();
							if (isset($arItem) && is_array($arItem) && $arPrice = CCatalogProduct::GetOptimalPrice($productID, 1, $USER->GetUserGroupArray(), "N"))
							{
								$metrika_product["id"] = $arItem["XML_ID"];
								$metrika_product["name"] = $arItem["NAME"];
								$metrika_product["price"] = (float)$arPrice["DISCOUNT_PRICE"];

								$db_props = CIBlockElement::GetProperty($arItem["IBLOCK_ID"], $arItem["ID"], array("sort" => "asc"), Array("CODE"=>"vendor"));
								if($arVendor = $db_props->Fetch())
								{
									$vendor_xml_id = trim($arVendor["VALUE"]);
									if ($vendor_xml_id != "")
									{
										$arVendorFilter = array(
											"IBLOCK_ID" => $arParams["VENDORS_IBLOCK_ID"],
											"XML_ID" => $vendor_xml_id,
											"ACTIVE" => "Y"
										);
										$rsVendor = CIBlockElement::GetList(array(), $arVendorFilter, false, array("nTopCount" => 1));
										if ($arVendor = $rsVendor->GetNext())
										{
											$metrika_product["brand"] = $arVendor["NAME"];
										}
									}
								}
								if ($arItem["IBLOCK_SECTION_ID"] > 0)
								{
									$section_path = "";
									$rsPath = CIBlockSection::GetNavChain($arItem["IBLOCK_ID"], $arItem["IBLOCK_SECTION_ID"]);
									$rsPath->SetUrlTemplates("", $arParams["SECTION_URL"]);
									$key = 0;
									while($arPath = $rsPath->GetNext())
									{
										$ipropValues = new \Bitrix\Iblock\InheritedProperty\SectionValues($arParams["IBLOCK_ID"], $arPath["ID"]);
										$arPath["IPROPERTY_VALUES"] = $ipropValues->getValues();
										
										if ($key > 0)
											$section_path .= "/";
										if ($arPath["IPROPERTY_VALUES"]["SECTION_PAGE_TITLE"] != "")
											$section_path .= $arPath["IPROPERTY_VALUES"]["SECTION_PAGE_TITLE"];
										else
											$section_path .= $arPath["NAME"];
										$key++;
									}
									if ($section_path != "")
										$metrika_product["category"] = $section_path;
								}
								if (is_array($product_properties))
								{
									$color = "";
									$size = "";
									foreach ($product_properties as $arProp)
									{
										if ($arProp["CODE"] == "color" && $arProp["VALUE"] != "")
											$color = $arProp['NAME'].' '.$arProp["VALUE"];
										else
										if ($arProp["CODE"] == "size" && $arProp["VALUE"] != "")
											$size = $arProp['NAME'].' '.$arProp["VALUE"];
									}
									if ($size != "" || $color != "")
									{
										$metrika_product["variant"] = "";
										if ($size != "")
											$metrika_product["variant"] = $size;
										if ($color != "")
										{
											if ($metrika_product["variant"] != "")
												$metrika_product["variant"] .= ", ";
											$metrika_product["variant"] .= $color;
										}
									}
								}
							}
							$addResult = array('STATUS' => 'OK', 'MESSAGE' => GetMessage('CATALOG_SUCCESSFUL_ADD_TO_BASKET'), 'ID' => $productID, 'METRIKA_PRODUCT' => $metrika_product);
						}
						else
						{
							$addResult = array('STATUS' => 'ERROR', 'MESSAGE' => $strError);
						}
						ob_end_clean();
						$APPLICATION->RestartBuffer();
						echo CUtil::PhpToJSObject($addResult);
						die();
					}
					else
					{
						if ($successfulAdd)
						{
							$pathRedirect = (
							$action == "BUY"
								? $arParams["BASKET_URL"]
								: $APPLICATION->GetCurPageParam("", array(
									$arParams["PRODUCT_ID_VARIABLE"],
									$arParams["ACTION_VARIABLE"],
									$arParams['PRODUCT_PROPS_VARIABLE']
								))
							);
							LocalRedirect($pathRedirect);
						}
					}
				}
			}
		}
		
		return $successfulAdd? false : ($strError? $strError : true);
	}
	
	public function __construct($component = null)
	{
		parent::__construct($component);
		$this->setExtendedMode(false)->setMultiIblockMode(false)->setPaginationMode(true);
		$this->setSeparateLoading(true);
	}
	
	public function onPrepareComponentParams($params)
	{
		$params['HIDE_NOT_AVAILABLE'] = $params['HIDE_NOT_AVAILABLE']==='N'? 'N' : 'Y';
		
		$params = parent::onPrepareComponentParams($params);
		$params['IBLOCK_TYPE'] = isset($params['IBLOCK_TYPE']) ? trim($params['IBLOCK_TYPE']) : '';

		if ((int)$params['SECTION_ID'] > 0 && (int)$params['SECTION_ID'].'' != $params['SECTION_ID'] && Loader::includeModule('iblock'))
		{
			$this->errorCollection->setError(new Error(Loc::getMessage('CATALOG_SECTION_NOT_FOUND'), self::ERROR_404));
			return $params;
		}

		$params['SECTION_ID_VARIABLE'] = (isset($params['SECTION_ID_VARIABLE']) ? trim($params['SECTION_ID_VARIABLE']) : '');
		if ($params['SECTION_ID_VARIABLE'] == '' || !preg_match(self::PARAM_TITLE_MASK, $params['SECTION_ID_VARIABLE']))
			$params['SECTION_ID_VARIABLE'] = 'SECTION_ID';

		$params['SHOW_ALL_WO_SECTION'] = isset($params['SHOW_ALL_WO_SECTION']) && $params['SHOW_ALL_WO_SECTION'] === 'Y';
		$params['USE_MAIN_ELEMENT_SECTION'] = isset($params['USE_MAIN_ELEMENT_SECTION']) && $params['USE_MAIN_ELEMENT_SECTION'] === 'Y';
		$params['SECTIONS_CHAIN_START_FROM'] = isset($params['SECTIONS_CHAIN_START_FROM']) ? (int)$params['SECTIONS_CHAIN_START_FROM'] : 0;

		// compatibility for bigData case with zero initial elements
		if ($params['PAGE_ELEMENT_COUNT'] <= 0 && !isset($params['PRODUCT_ROW_VARIANTS']))
		{
			$params['PAGE_ELEMENT_COUNT'] = 12;
		}

		$params['CUSTOM_CURRENT_PAGE'] = isset($params['CUSTOM_CURRENT_PAGE']) ? trim($params['CUSTOM_CURRENT_PAGE']) : '';

		$params['COMPATIBLE_MODE'] = (isset($params['COMPATIBLE_MODE']) && $params['COMPATIBLE_MODE'] === 'N' ? 'N' : 'Y');
		if ($params['COMPATIBLE_MODE'] === 'N')
		{
			$params['DISABLE_INIT_JS_IN_COMPONENT'] = 'Y';
			$params['OFFERS_LIMIT'] = 0;
		}

		$this->setCompatibleMode($params['COMPATIBLE_MODE'] === 'Y');

		$params['DISABLE_INIT_JS_IN_COMPONENT'] = isset($params['DISABLE_INIT_JS_IN_COMPONENT']) && $params['DISABLE_INIT_JS_IN_COMPONENT'] === 'Y' ? 'Y' : 'N';

		/*if ($params['DISABLE_INIT_JS_IN_COMPONENT'] !== 'Y')
		{
			CJSCore::Init(array('popup'));
		}*/
		
		//fda2000
		$params["IMAGE_WIDTH"] = intval($params["IMAGE_WIDTH"]);
		if($params["IMAGE_WIDTH"]<=0)
			$params["IMAGE_WIDTH"] = intval($params["IMG_WIDTH"]);
		if($params["IMAGE_WIDTH"] <= 0)
			$params["IMAGE_WIDTH"] = 195;
		$params["IMAGE_HEIGHT"] = intval($params["IMAGE_HEIGHT"]);
		if($params["IMAGE_HEIGHT"]<=0)
			$params["IMAGE_HEIGHT"] = intval($params["IMG_HEIGHT"]);
		if($params["IMAGE_HEIGHT"] <= 0)
			$params["IMAGE_HEIGHT"] = 268;
		
		$params["VENDORS_IBLOCK_ID"] = (int)$params["VENDORS_IBLOCK_ID"];
		$params["DISPLAY_WISHLIST"] = (isset($params['DISPLAY_WISHLIST']) && $params["DISPLAY_WISHLIST"] == "Y");
		
		if ($_REQUEST["count"] != "") {
			$_SESSION["fire"]["count"] = $_REQUEST["count"];
			$params["PAGE_ELEMENT_COUNT"] = $_REQUEST["count"];
		}
		else if ($_SESSION["fire"]["count"] != "") {
			$params["PAGE_ELEMENT_COUNT"] = $_SESSION["fire"]["count"];
		}

		
		$addSort = [];
		if (!empty($params["ELEMENT_SORT_FIELD"]) && preg_match('/^(asc|desc|nulls)(,asc|,desc|,nulls){0,1}$/i', $params["ELEMENT_SORT_ORDER"]))
			$addSort[$params["ELEMENT_SORT_FIELD"]] = $params["ELEMENT_SORT_ORDER"];
		if (!empty($params["ELEMENT_SORT_FIELD2"]) && preg_match('/^(asc|desc|nulls)(,asc|,desc|,nulls){0,1}$/i', $params["ELEMENT_SORT_ORDER2"]))
			$addSort[$params["ELEMENT_SORT_FIELD2"]] = $params["ELEMENT_SORT_ORDER2"];
		unset($params["ELEMENT_SORT_FIELD"], $params["ELEMENT_SORT_ORDER"]);
		
		if ($_REQUEST["sort"] != "") {
			if ($_REQUEST["sort"] == "base")
				unset($_SESSION["fire"]["sort"]);
			else
				$_SESSION["fire"]["sort"] = $_REQUEST["sort"];
		} else if ($_SESSION["fire"]["sort"] != "") {
			$_REQUEST["sort"] = $_SESSION["fire"]["sort"];
		}
		if ($_REQUEST["sort"] == "bestseller") {
			$params["ELEMENT_SORT_FIELD"] = "PROPERTY_bestseller";
			$params["ELEMENT_SORT_ORDER"] = "desc";
		} else if ($_REQUEST["sort"] == "new") {
			$params["ELEMENT_SORT_FIELD"] = "PROPERTY_new";
			$params["ELEMENT_SORT_ORDER"] = "desc";
		} else if ($_REQUEST["sort"] == "price_asc" ) {
			$params["ELEMENT_SORT_FIELD"] = "PROPERTY_price";
			$params["ELEMENT_SORT_ORDER"] = "asc";
		} else if ($_REQUEST["sort"] == "price_desc" ) {
			$params["ELEMENT_SORT_FIELD"] = "PROPERTY_price";
			$params["ELEMENT_SORT_ORDER"] = "desc";
		} else if ($_REQUEST["sort"] == "sale" ) {
			$params["ELEMENT_SORT_FIELD"] = "PROPERTY_sale";
			$params["ELEMENT_SORT_ORDER"] = "desc";
		}
		
		if(!isset($params['ELEMENT_SORT_FIELD']) or empty($params['ELEMENT_SORT_FIELD']))
			if($addSort)
				$params['CUSTOM_ELEMENT_SORT'] = $addSort + ['ID' => 'desc'];
			else
				$params['CUSTOM_ELEMENT_SORT'] = ['PROPERTY_new' => 'desc', 'PROPERTY_bestseller' => 'desc', 'ID' => 'desc'];
		else
			$params['CUSTOM_ELEMENT_SORT'] = [$params['ELEMENT_SORT_FIELD'] => $params['ELEMENT_SORT_ORDER']] + $addSort + ['ID' => 'desc'];

		return $params;
	}

	protected function processResultData()
	{
		if ($this->initSectionResult())
		{
			$this->initSectionProperties();
			parent::processResultData();
		}
	}

	protected function initSectionResult()
	{
		$success = true;
		$selectFields = array();

		if (!empty($this->arParams['SECTION_USER_FIELDS']) && is_array($this->arParams['SECTION_USER_FIELDS']))
		{
			foreach ($this->arParams['SECTION_USER_FIELDS'] as $field)
			{
				if (is_string($field) && preg_match('/^UF_/', $field))
				{
					$selectFields[] = $field;
				}
			}
		}

		if (preg_match('/^UF_/', $this->arParams['META_KEYWORDS']))
		{
			$selectFields[] = $this->arParams['META_KEYWORDS'];
		}

		if (preg_match('/^UF_/', $this->arParams['META_DESCRIPTION']))
		{
			$selectFields[] = $this->arParams['META_DESCRIPTION'];
		}

		if (preg_match('/^UF_/', $this->arParams['BROWSER_TITLE']))
		{
			$selectFields[] = $this->arParams['BROWSER_TITLE'];
		}

		$filterFields = array(
			'IBLOCK_ID' => $this->arParams['IBLOCK_ID'],
			'IBLOCK_ACTIVE' => 'Y',
			'ACTIVE' => 'Y',
			'GLOBAL_ACTIVE' => 'Y',
		);

		// Hidden tricky parameter USED to display linked
		// by default it is not set
		if ($this->arParams['BY_LINK'] === 'Y')
		{
			$sectionResult = array(
				'ID' => 0,
				'IBLOCK_ID' => $this->arParams['IBLOCK_ID'],
			);
		}
		elseif ($this->arParams['SECTION_ID'] > 0)
		{
			$filterFields['ID'] = $this->arParams['SECTION_ID'];
			$sectionIterator = CIBlockSection::GetList(array(), $filterFields, false, $selectFields);
			$sectionIterator->SetUrlTemplates('', $this->arParams['SECTION_URL']);
			$sectionResult = $sectionIterator->GetNext();
		}
		elseif ($this->arParams['SECTION_CODE'] <> '')
		{
			$filterFields['=CODE'] = $this->arParams['SECTION_CODE'];
			$sectionIterator = CIBlockSection::GetList(array(), $filterFields, false, $selectFields);
			$sectionIterator->SetUrlTemplates('', $this->arParams['SECTION_URL']);
			$sectionResult = $sectionIterator->GetNext();
		}
		elseif ($this->arParams['SECTION_CODE_PATH'] <> '')
		{
			$sectionId = CIBlockFindTools::GetSectionIDByCodePath($this->arParams['IBLOCK_ID'], $this->arParams['SECTION_CODE_PATH']);
			if ($sectionId)
			{
				$filterFields['ID'] = $sectionId;
				$sectionIterator = CIBlockSection::GetList(array(), $filterFields, false, $selectFields);
				$sectionIterator->SetUrlTemplates('', $this->arParams['SECTION_URL']);
				$sectionResult = $sectionIterator->GetNext();
			}
		}
		else	// Root section (no section filter)
		{
			$sectionResult = array(
				'ID' => 0,
				'IBLOCK_ID' => $this->arParams['IBLOCK_ID'],
			);
		}

		if (empty($sectionResult))
		{
			$success = false;
			$this->abortResultCache();
			$this->errorCollection->setError(new Error(Loc::getMessage('CATALOG_SECTION_NOT_FOUND'), self::ERROR_404));
		}
		else
		{
			$this->arResult = array_merge($this->arResult, $sectionResult);
			if ($this->arResult['ID'] > 0 && $this->arParams['ADD_SECTIONS_CHAIN'])
			{
				$this->arResult['PATH'] = array();
				$pathIterator = CIBlockSection::GetNavChain(
					$this->arResult['IBLOCK_ID'],
					$this->arResult['ID'],
					array(
						'ID', 'CODE', 'XML_ID', 'EXTERNAL_ID', 'IBLOCK_ID',
						'IBLOCK_SECTION_ID', 'SORT', 'NAME', 'ACTIVE',
						'DEPTH_LEVEL', 'SECTION_PAGE_URL'
					)
				);
				$pathIterator->SetUrlTemplates('', $this->arParams['SECTION_URL']);
				while ($path = $pathIterator->GetNext())
				{
					$ipropValues = new Iblock\InheritedProperty\SectionValues($this->arParams['IBLOCK_ID'], $path['ID']);
					$path['IPROPERTY_VALUES'] = $ipropValues->getValues();
					$this->arResult['PATH'][] = $path;
				}

				if ($this->arParams['SECTIONS_CHAIN_START_FROM'] > 0)
				{
					$this->arResult['PATH'] = array_slice($this->arResult['PATH'], $this->arParams['SECTIONS_CHAIN_START_FROM']);
				}
			}
		}

		return $success;
	}

	protected function initSectionProperties()
	{
		$arResult =& $this->arResult;

		$arResult['IPROPERTY_VALUES'] = array();
		if ($arResult['ID'] > 0)
		{
			$ipropValues = new Iblock\InheritedProperty\SectionValues($arResult['IBLOCK_ID'], $arResult['ID']);
			$arResult['IPROPERTY_VALUES'] = $ipropValues->getValues();
		}

		Iblock\Component\Tools::getFieldImageData(
			$arResult,
			array('PICTURE', 'DETAIL_PICTURE'),
			Iblock\Component\Tools::IPROPERTY_ENTITY_SECTION,
			'IPROPERTY_VALUES'
		);
	}

	protected function initCatalogInfo()
	{
		parent::initCatalogInfo();
		$useCatalogButtons = array();
		if (
			!empty($this->storage['CATALOGS'][$this->arParams['IBLOCK_ID']])
			&& is_array($this->storage['CATALOGS'][$this->arParams['IBLOCK_ID']])
		)
		{
			$catalogType = $this->storage['CATALOGS'][$this->arParams['IBLOCK_ID']]['CATALOG_TYPE'];
			if ($catalogType == CCatalogSku::TYPE_CATALOG || $catalogType == CCatalogSku::TYPE_FULL)
			{
				$useCatalogButtons['add_product'] = true;
			}

			if ($catalogType == CCatalogSku::TYPE_PRODUCT || $catalogType == CCatalogSku::TYPE_FULL)
			{
				$useCatalogButtons['add_sku'] = true;
			}
			unset($catalogType);
		}

		$this->storage['USE_CATALOG_BUTTONS'] = $useCatalogButtons;
	}

	protected function getCacheKeys()
	{
		return array(
			'ID',
			'NAV_CACHED_DATA',
			'NAV_STRING',
			$this->arParams['META_KEYWORDS'],
			$this->arParams['META_DESCRIPTION'],
			$this->arParams['BROWSER_TITLE'],
			'NAME',
			'PATH',
			'IBLOCK_SECTION_ID',
			'IPROPERTY_VALUES',
			'ITEMS_TIMESTAMP_X',
			'USE_CATALOG_BUTTONS'
		);
	}
	
	protected function getFilter()
	{
		$filterFields = parent::getFilter();

		if ($this->getAction() === 'bigDataLoad')
		{
			return $filterFields;
		}

		$filterFields['INCLUDE_SUBSECTIONS'] = $this->arParams['INCLUDE_SUBSECTIONS'] === 'N' ? 'N' : 'Y';

		if ($this->arParams['INCLUDE_SUBSECTIONS'] === 'A')
		{
			$filterFields['SECTION_GLOBAL_ACTIVE'] = 'Y';
		}

		if ($this->arParams['BY_LINK'] !== 'Y')
		{
			if ($this->arResult['ID'])
			{
				$filterFields['SECTION_ID'] = $this->arResult['ID'];
			}
			elseif (!$this->arParams['SHOW_ALL_WO_SECTION'])
			{
				$filterFields['SECTION_ID'] = 0;
			}
			else
			{
				unset($filterFields['INCLUDE_SUBSECTIONS']);
				unset($filterFields['SECTION_GLOBAL_ACTIVE']);
			}
		}
		//fda2000 img_status
		global $USER;
		CModule::IncludeModule('fire.main');
		$filterFields = Fire_Settings::addFilterImgStatus($filterFields, 'PROPERTY_img_status');
		//
		$filterFields['SECTION_GLOBAL_ACTIVE'] = 'Y';
		
		$globalFilter = [];
		if (!empty($this->globalFilter))
			$globalFilter = $this->convertFilter($this->globalFilter);
		foreach($globalFilter as $key=>$val);
			unset($filterFields[$key]);
		
		return $filterFields;
	}

	protected function makeOutputResult()
	{
		parent::makeOutputResult();
		$this->arResult['USE_CATALOG_BUTTONS'] = $this->storage['USE_CATALOG_BUTTONS'];
	}

	protected function initialLoadAction()
	{
		parent::initialLoadAction();

		if (!$this->hasErrors())
		{
			$this->initAdminIconsPanel();
			$this->setTemplateCachedData($this->arResult['NAV_CACHED_DATA']);
			$this->initMetaData();
		}
	}

	protected function initAdminIconsPanel()
	{
		global $APPLICATION, $INTRANET_TOOLBAR, $USER;

		if (!$USER->IsAuthorized())
		{
			return;
		}

		$arResult =& $this->arResult;

		if (
			$APPLICATION->GetShowIncludeAreas()
			|| (is_object($INTRANET_TOOLBAR) && $this->arParams['INTRANET_TOOLBAR'] !== 'N')
			|| $this->arParams['SET_TITLE']
			|| isset($arResult[$this->arParams['BROWSER_TITLE']])
		)
		{
			if (Loader::includeModule('iblock'))
			{
				$urlDeleteSectionButton = '';

				if ($arResult['IBLOCK_SECTION_ID'] > 0)
				{
					$sectionIterator = CIBlockSection::GetList(
						array(),
						array('=ID' => $arResult['IBLOCK_SECTION_ID']),
						false,
						array('SECTION_PAGE_URL')
					);
					$sectionIterator->SetUrlTemplates('', $this->arParams['SECTION_URL']);
					$section = $sectionIterator->GetNext();
					$urlDeleteSectionButton = $section['SECTION_PAGE_URL'];
				}

				if (empty($urlDeleteSectionButton))
				{
					$urlTemplate = CIBlock::GetArrayByID($this->arParams['IBLOCK_ID'], 'LIST_PAGE_URL');
					$iblock = CIBlock::GetArrayByID($this->arParams['IBLOCK_ID']);
					$iblock['IBLOCK_CODE'] = $iblock['CODE'];
					$urlDeleteSectionButton = CIBlock::ReplaceDetailUrl($urlTemplate, $iblock, true, false);
				}

				$returnUrl = array(
					'add_section' => (
					$this->arParams['SECTION_URL'] <> ''? $this->arParams['SECTION_URL'] : CIBlock::GetArrayByID($this->arParams['IBLOCK_ID'], 'SECTION_PAGE_URL')
					),
					'delete_section' => $urlDeleteSectionButton,
				);
				$buttonParams = array(
					'RETURN_URL' => $returnUrl,
					'CATALOG' => true
				);

				if (isset($arResult['USE_CATALOG_BUTTONS']))
				{
					$buttonParams['USE_CATALOG_BUTTONS'] = $arResult['USE_CATALOG_BUTTONS'];
				}

				$buttons = CIBlock::GetPanelButtons(
					$this->arParams['IBLOCK_ID'],
					0,
					$arResult['ID'],
					$buttonParams
				);
				unset($buttonParams);

				if ($APPLICATION->GetShowIncludeAreas())
				{
					$this->addIncludeAreaIcons(CIBlock::GetComponentMenu($APPLICATION->GetPublicShowMode(), $buttons));
				}

				if (
					is_array($buttons['intranet'])
					&& is_object($INTRANET_TOOLBAR)
					&& $this->arParams['INTRANET_TOOLBAR'] !== 'N'
				)
				{
					Main\Page\Asset::getInstance()->addJs('/bitrix/js/main/utils.js');

					foreach ($buttons['intranet'] as $button)
					{
						$INTRANET_TOOLBAR->AddButton($button);
					}
				}

				if ($this->arParams['SET_TITLE'] || isset($arResult[$this->arParams['BROWSER_TITLE']]))
				{
					$this->storage['TITLE_OPTIONS'] = array(
						'ADMIN_EDIT_LINK' => $buttons['submenu']['edit_section']['ACTION'],
						'PUBLIC_EDIT_LINK' => $buttons['edit']['edit_section']['ACTION'],
						'COMPONENT_NAME' => $this->getName(),
					);
				}
			}
		}
	}
	
	protected function initMetaData()
	{
		global $APPLICATION;

		if($this->SECTION_TITLE)
			unset($this->arResult['IPROPERTY_VALUES']);
		
		if ($this->arParams['SET_TITLE'])
		{
			if(!$this->SECTION_TITLE && $this->arResult['IPROPERTY_VALUES']['SECTION_PAGE_TITLE'] != '')
				$APPLICATION->SetTitle($this->arResult['IPROPERTY_VALUES']['SECTION_PAGE_TITLE'], $this->storage['TITLE_OPTIONS']);
			elseif(isset($this->arResult['NAME']))
				$APPLICATION->SetTitle($this->arResult['NAME'], $this->storage['TITLE_OPTIONS']);
			elseif($this->SECTION_TITLE)
				$APPLICATION->SetTitle($this->SECTION_TITLE);
		}

		if ($this->arParams['SET_BROWSER_TITLE'] === 'Y')
		{
			$browserTitle = Main\Type\Collection::firstNotEmpty(
				$this->arResult, $this->arParams['BROWSER_TITLE'],
				$this->arResult['IPROPERTY_VALUES'], 'SECTION_META_TITLE'
			);
			if (is_array($browserTitle))
			{
				$APPLICATION->SetPageProperty('title', implode(' ', $browserTitle), $this->storage['TITLE_OPTIONS']);
			}
			elseif ($browserTitle != '')
			{
				$APPLICATION->SetPageProperty('title', $browserTitle, $this->storage['TITLE_OPTIONS']);
			}
		}

		if ($this->arParams['SET_META_KEYWORDS'] === 'Y')
		{
			$metaKeywords = Main\Type\Collection::firstNotEmpty(
				$this->arResult, $this->arParams['META_KEYWORDS'],
				$this->arResult['IPROPERTY_VALUES'], 'SECTION_META_KEYWORDS'
			);
			if (is_array($metaKeywords))
			{
				$APPLICATION->SetPageProperty('keywords', implode(' ', $metaKeywords), $this->storage['TITLE_OPTIONS']);
			}
			elseif ($metaKeywords != '')
			{
				$APPLICATION->SetPageProperty('keywords', $metaKeywords, $this->storage['TITLE_OPTIONS']);
			}
		}

		if ($this->arParams['SET_META_DESCRIPTION'] === 'Y')
		{
			$metaDescription = Main\Type\Collection::firstNotEmpty(
				$this->arResult, $this->arParams['META_DESCRIPTION'],
				$this->arResult['IPROPERTY_VALUES'], 'SECTION_META_DESCRIPTION'
			);
			if (is_array($metaDescription))
			{
				$APPLICATION->SetPageProperty('description', implode(' ', $metaDescription), $this->storage['TITLE_OPTIONS']);
			}
			elseif ($metaDescription != '')
			{
				$APPLICATION->SetPageProperty('description', $metaDescription, $this->storage['TITLE_OPTIONS']);
			}
		}

		if ($this->arParams['ADD_SECTIONS_CHAIN'] && $this->SECTION_TITLE)
			$APPLICATION->AddChainItem($this->SECTION_TITLE, $this->arParams['IBLOCK_URL']);
		
		if ($this->arParams['ADD_SECTIONS_CHAIN'] && is_array($this->arResult['PATH']))
		{
			foreach ($this->arResult['PATH'] as $key=>$path)
			{
				if($this->SECTION_TITLE) {
					unset($this->arResult['PATH'][$key]['IPROPERTY_VALUES']);
					unset($path['IPROPERTY_VALUES']);
				}
				if ($path['IPROPERTY_VALUES']['SECTION_PAGE_TITLE'] != '')
				{
					$APPLICATION->AddChainItem($path['IPROPERTY_VALUES']['SECTION_PAGE_TITLE'], $path['~SECTION_PAGE_URL']);
				}
				else
				{
					$APPLICATION->AddChainItem($path['NAME'], $path['~SECTION_PAGE_URL']);
				}
			}
		}

		if ($this->arParams['SET_LAST_MODIFIED'] && $this->arResult['ITEMS_TIMESTAMP_X'])
		{
			Main\Context::getCurrent()->getResponse()->setLastModified($this->arResult['ITEMS_TIMESTAMP_X']);
		}
	}

	protected function getElementList($iblockId, $products)
	{
		$elementIterator = parent::getElementList($iblockId, $products);

		if (
			!empty($elementIterator)
			&& $this->arParams['BY_LINK'] !== 'Y'
			&& !$this->arParams['SHOW_ALL_WO_SECTION']
			&& !$this->arParams['USE_MAIN_ELEMENT_SECTION']
		)
		{
			$elementIterator->SetSectionContext($this->arResult);
		}
		//fda2000 404 for not exist pages
		$page = (int)$elementIterator->PAGEN;
		if($elementIterator->NavPageCount && $page && $page>$elementIterator->NavPageCount) {
			$this->errorCollection->setError(new Error(Loc::getMessage('CATALOG_SECTION_NOT_FOUND'), self::ERROR_404));
			return false;
		}
		//
		return $elementIterator;
	}

	protected function processElement(array &$element)
	{
		if ($this->arResult['ID'])
		{
			$element['IBLOCK_SECTION_ID'] = $this->arResult['ID'];
		}

		parent::processElement($element);
		$this->checkLastModified($element);
	}

	protected function checkLastModified($element)
	{
		if ($this->arParams['SET_LAST_MODIFIED'])
		{
			$time = DateTime::createFromUserTime($element['TIMESTAMP_X']);
			if (
				!isset($this->arResult['ITEMS_TIMESTAMP_X'])
				|| $time->getTimestamp() > $this->arResult['ITEMS_TIMESTAMP_X']->getTimestamp()
			)
			{
				$this->arResult['ITEMS_TIMESTAMP_X'] = $time;
			}
		}
	}

	protected function initElementList()
	{
		parent::initElementList();

		// compatibility for old components
		if ($this->isEnableCompatible() && empty($this->arResult['NAV_RESULT']))
		{
			$this->initNavString(\CIBlockElement::GetList(
				array(),
				array_merge($this->globalFilter, $this->filterFields + array('IBLOCK_ID' => $this->arParams['IBLOCK_ID'])),
				false,
				array('nTopCount' => 1),
				array('ID')
			));
			$this->arResult['NAV_RESULT']->NavNum = Main\Security\Random::getString(6);
		}

		$this->storage['sections'] = array();

		if (!empty($this->elements) && is_array($this->elements))
		{
			foreach ($this->elements as &$element)
			{
				$this->modifyItemPath($element);
				//fda2000 old mod
				if (mb_strlen($element["DETAIL_TEXT"]) > 200) {
					$element["DETAIL_TEXT"] = mb_substr($element["DETAIL_TEXT"], 0, 200);
					$pos = mb_strrpos($element["DETAIL_TEXT"], " ");
					if ($pos !== false)
						$element["DETAIL_TEXT"] = mb_substr($element["DETAIL_TEXT"], 0, $pos);
				}
				
				$element["PICTURES"] = [];
				$i = 0;
				$arItem_tmp = $element;
				if($arItem_tmp["PROPERTIES"]["pics"]['VALUE'])
					foreach($arItem_tmp["PROPERTIES"]["pics"]['VALUE'] as $key=>$val)
						if($i++>1)
							unset($arItem_tmp["PROPERTIES"]["pics"]['VALUE'][$key]);
				$element["IMAGES"] = Fire_Images::getResizeCatalogItem($arItem_tmp, array('width'=>$this->arParams["IMAGE_WIDTH"], 'height'=>$this->arParams["IMAGE_HEIGHT"]));
				foreach($element["IMAGES"] as $file) {//Old props
					$element["PICTURES"][] = $file['src'];
				}
				//
			}
		}
	}
	
	protected function transferItems(array &$items) {
		$this->storage['URLS']['~COMPARE_URL_TEMPLATE'] = "?ADD_TO_COMPARE_LIST=#ID#";
		$this->storage['URLS']['COMPARE_URL_TEMPLATE'] = htmlspecialcharsbx($this->storage['URLS']['~COMPARE_URL_TEMPLATE']);
		$this->storage['URLS']['~WISHLIST_URL_TEMPLATE'] = "?ADD_TO_WISHLIST_LIST=#ID#";
		$this->storage['URLS']['WISHLIST_URL_TEMPLATE'] = htmlspecialcharsbx($this->storage['URLS']['~WISHLIST_URL_TEMPLATE']);
		
		parent::transferItems($items);
		
		foreach ($items as &$element)
			if ($this->arParams['DISPLAY_WISHLIST']) {
				$element['~WISHLIST_URL'] = str_replace('#ID#', $element["ID"], $this->storage['URLS']['~WISHLIST_URL_TEMPLATE']);
				$element['WISHLIST_URL'] = str_replace('#ID#', $element["ID"], $this->storage['URLS']['WISHLIST_URL_TEMPLATE']);
			}
	}

	protected function modifyItemPath(&$element)
	{
		$sections =& $this->storage['sections'];

		if ($this->arParams['BY_LINK'] === 'Y')
		{
			if (!isset($sections[$element['IBLOCK_SECTION_ID']]))
			{
				$sections[$element['IBLOCK_SECTION_ID']] = array();
				$pathIterator = CIBlockSection::GetNavChain(
					$element['IBLOCK_ID'],
					$element['IBLOCK_SECTION_ID'],
					array(
						'ID', 'CODE', 'XML_ID', 'EXTERNAL_ID', 'IBLOCK_ID',
						'IBLOCK_SECTION_ID', 'SORT', 'NAME', 'ACTIVE',
						'DEPTH_LEVEL', 'SECTION_PAGE_URL'
					)
				);
				$pathIterator->SetUrlTemplates('', $this->arParams['SECTION_URL']);
				while ($path = $pathIterator->GetNext())
				{
					$sections[$element['IBLOCK_SECTION_ID']][] = $path;
				}
			}

			$element['SECTION']['PATH'] = $sections[$element['IBLOCK_SECTION_ID']];
		}
		else
		{
			$element['SECTION']['PATH'] = array();
		}
	}
	
	protected function chooseOffer($offers, $iblockId) {
		parent::chooseOffer($offers, $iblockId);
		//fda2000 discount price
		foreach($offers as $arOffer)
			if($arOffer['CAN_BUY'])
				if(!$this->elementLinks[$arOffer["LINK_ELEMENT_ID"]]['MIN_PRICE'] || !$this->elementLinks[$arOffer["LINK_ELEMENT_ID"]]['MIN_PRICE']['DISCOUNT_VALUE'] || $arOffer['MIN_PRICE']['DISCOUNT_VALUE']<$this->elementLinks[$arOffer["LINK_ELEMENT_ID"]]['MIN_PRICE']['DISCOUNT_VALUE'])
					$this->elementLinks[$arOffer["LINK_ELEMENT_ID"]]['MIN_PRICE'] = (isset($arOffer['RATIO_PRICE']) ? $arOffer['RATIO_PRICE'] : $arOffer['MIN_PRICE']);
		
		//fda2000 title
		if($this->SECTION_TITLE)
			unset($this->arResult['IPROPERTY_VALUES']);
		global $APPLICATION;
		if($this->arParams['SET_TITLE']) {
			if ($this->arResult['IPROPERTY_VALUES']['SECTION_PAGE_TITLE'] != '')
				$APPLICATION->SetTitle($this->arResult['IPROPERTY_VALUES']['SECTION_PAGE_TITLE']);
			elseif(isset($this->arResult['NAME']))
				$APPLICATION->SetTitle($this->arResult['NAME']);
			elseif($this->SECTION_TITLE)
				$APPLICATION->SetTitle($this->SECTION_TITLE);
		}
	}
	
	protected function getSeparateList(array $params) {
		$iterator = parent::getSeparateList($params);
		$iterator->nPageWindow = 3;
		return $iterator;
	}
}
?>