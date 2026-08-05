<?
use Bitrix\Main,
	Bitrix\Main\Loader,
	Bitrix\Iblock\Component\Element,
	Bitrix\Main\Localization\Loc,
	Bitrix\Catalog;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

Loc::loadMessages(__FILE__);

if (!\Bitrix\Main\Loader::includeModule('iblock'))
{
	ShowError(Loc::getMessage('IBLOCK_MODULE_NOT_INSTALLED'));
	return;
}

class CatalogElementComponent extends Element
{
	public function __construct($component = null)
	{
		parent::__construct($component);
		$this->setExtendedMode(false);
	}

	/**
	 * Processing parameters unique to catalog.element component.
	 *
	 * @param array $params		Component parameters.
	 * @return array
	 */
	public function onPrepareComponentParams($params)
	{
		$params = parent::onPrepareComponentParams($params);
		
		$params['COMPATIBLE_MODE'] = (isset($params['COMPATIBLE_MODE']) && $params['COMPATIBLE_MODE'] === 'N' ? 'N' : 'Y');
		if ($params['COMPATIBLE_MODE'] === 'N')
		{
			$params['SET_VIEWED_IN_COMPONENT'] = 'N';
			$params['DISABLE_INIT_JS_IN_COMPONENT'] = 'Y';
			$params['OFFERS_LIMIT'] = 0;
		}

		$this->setCompatibleMode($params['COMPATIBLE_MODE'] === 'Y');

		$params['SET_VIEWED_IN_COMPONENT'] = isset($params['SET_VIEWED_IN_COMPONENT']) && $params['SET_VIEWED_IN_COMPONENT'] === 'Y' ? 'Y' : 'N';

		$params['DISABLE_INIT_JS_IN_COMPONENT'] = isset($params['DISABLE_INIT_JS_IN_COMPONENT']) && $params['DISABLE_INIT_JS_IN_COMPONENT'] === 'Y' ? 'Y' : 'N';
		/*if ($params['DISABLE_INIT_JS_IN_COMPONENT'] !== 'Y')
		{
			\CJSCore::Init(array('popup'));
		}*/
		
		//fda2000
		$params['VENDORS_IBLOCK_ID'] = (int)$params['VENDORS_IBLOCK_ID'];
		CModule::IncludeModule('fire.main');
		$params['SHOPS_IBLOCK_ID'] = Fire_Settings::getOption('SELFSHOP_TYPE')? Fire_Settings::getOption('SETTINGS_SHOP_IBLOCK') : NULL;
		if($params['SHOPS_IBLOCK_ID'])
			$params['OFFERS_FIELD_CODE'][]='IBLOCK_SECTION_ID';
		
		$params['DISPLAY_WISHLIST'] = (isset($params['DISPLAY_WISHLIST']) && $params['DISPLAY_WISHLIST'] == 'Y');
		$params['DISPLAY_COMPARE'] = (isset($params['DISPLAY_COMPARE']) && $params['DISPLAY_COMPARE'] == 'Y');
		
		$params['SECT_COUNT'] = !isset($params['SECT_COUNT']) || $params['SECT_COUNT']===''? 3 : (int)$params['SECT_COUNT'];
		
		$params['BUY_CLICK'] = isset($params['BUY_CLICK']) && $params['BUY_CLICK'] === 'Y' ? 'Y' : 'N';
		
		/*if($arParams["BASKET_URL"] === '')
			$arParams["BASKET_URL"] = "/personal/cart/";*/

		return $params;
	}

	/**
	 * Fill additional keys for component cache.
	 *
	 * @param array &$resultCacheKeys		Cached result keys.
	 * @return void
	 */
	protected function initAdditionalCacheKeys(&$resultCacheKeys)
	{
		parent::initAdditionalCacheKeys($resultCacheKeys);

		if (
			$this->useCatalog
			&& !empty($this->storage['CATALOGS'][$this->arParams['IBLOCK_ID']])
			&& is_array($this->storage['CATALOGS'][$this->arParams['IBLOCK_ID']])
		)
		{
			$element =& $this->elements[0];

			// catalog hit stats
			$productTitle = !empty($element['IPROPERTY_VALUES']['ELEMENT_PAGE_TITLE'])
				? $element['IPROPERTY_VALUES']['ELEMENT_PAGE_TITLE']
				: $element['NAME'];

			$categoryId = '';
			$categoryPath = array();

			if (isset($element['SECTION']['ID']))
			{
				$categoryId = $element['SECTION']['ID'];
			}

			if (isset($element['SECTION']['PATH']))
			{
				foreach ($element['SECTION']['PATH'] as $cat)
				{
					$categoryPath[$cat['ID']] = $cat['NAME'];
				}
			}

			$this->arResult['CATEGORY_PATH'] = implode('/', $categoryPath);

			$counterData = array(
				'product_id' => $element['ID'],
				'iblock_id' => $this->arParams['IBLOCK_ID'],
				'product_title' => $productTitle,
				'category_id' => $categoryId,
				'category' => $categoryPath
			);

			if (empty($element['OFFERS']))
			{
				$priceProductId = $element['ID'];
			}
			else
			{
				$offer = reset($element['OFFERS']);
				$priceProductId = $offer['ID'];
				unset($offer);
			}

			// price for anonymous
			if ($this->useDiscountCache)
			{
				if ($this->storage['USE_SALE_DISCOUNTS'])
				{
					$priceTypes = array();
					$priceIterator = Catalog\GroupAccessTable::getList(array(
						'select' => array('CATALOG_GROUP_ID'),
						'filter' => array('GROUP_ID' => 2, '=ACCESS' => Catalog\GroupAccessTable::ACCESS_BUY),
						'order' => array('CATALOG_GROUP_ID' => 'ASC')
					));
					while ($priceType = $priceIterator->fetch())
					{
						$priceTypeId = (int)$priceType['CATALOG_GROUP_ID'];
						$priceTypes[$priceTypeId] = $priceTypeId;
						unset($priceTypeId);
					}
					Catalog\Discount\DiscountManager::preloadPriceData(array($priceProductId), $priceTypes);
					Catalog\Product\Price::loadRoundRules($priceTypes);
				}
			}
			$optimalPrice = \CCatalogProduct::GetOptimalPrice($priceProductId, 1, array(2), 'N', array(), $this->getSiteId(), array());
			$counterData['price'] = $optimalPrice['RESULT_PRICE']['DISCOUNT_PRICE'];
			$counterData['currency'] = $optimalPrice['RESULT_PRICE']['CURRENCY'];

			// make sure it is in utf8
			$counterData = Main\Text\Encoding::convertEncoding($counterData, SITE_CHARSET, 'UTF-8');

			// pack value and protocol version
			$rcmLogCookieName = Main\Config\Option::get('main', 'cookie_name', 'BITRIX_SM') . '_' . Main\Analytics\Catalog::getCookieLogName();

			$this->arResult['counterData'] = array(
				'item' => base64_encode(json_encode($counterData)),
				'user_id' => new Main\Text\JsExpression(
					'function(){return BX.message("USER_ID") ? BX.message("USER_ID") : 0;}'
				),
				'recommendation' => new Main\Text\JsExpression(
					'function() {
							var rcmId = "";
							var cookieValue = BX.getCookie("' . $rcmLogCookieName . '");
							var productId = ' . $element["ID"] . ';
							var cItems = [];
							var cItem;

							if (cookieValue)
							{
								cItems = cookieValue.split(".");
							}

							var i = cItems.length;
							while (i--)
							{
								cItem = cItems[i].split("-");
								if (cItem[0] == productId)
								{
									rcmId = cItem[1];
									break;
								}
							}

							return rcmId;
						}'
				),
				'v' => '2'
			);
			$resultCacheKeys[] = 'counterData';

			if ($this->arParams['SET_VIEWED_IN_COMPONENT'] === 'Y')
			{
				$viewedProduct = array(
					'PRODUCT_ID' => $element['ID'],
					'OFFER_ID' => $element['ID']
				);

				if (!empty($element['OFFERS']))
				{
					$viewedProduct['OFFER_ID'] = $element['OFFER_ID_SELECTED'] > 0
						? $element['OFFER_ID_SELECTED']
						: $element['OFFERS'][0]['ID'];
				}

				$this->arResult['VIEWED_PRODUCT'] = $viewedProduct;
				$resultCacheKeys[] = 'VIEWED_PRODUCT';
				unset($viewedProduct);
			}
			unset($element);
		}
		
		//fda2000
		$resultCacheKeys[] = 'VENDOR';
		$resultCacheKeys[] = 'XML_ID';
		$resultCacheKeys[] = 'MIN_PRICE';
	}

	/**
	 * Save compatible viewed product in catalog.element only.
	 *
	 * @return void
	 */
	protected function saveViewedProduct()
	{
		if ($this->isEnableCompatible())
		{
			if ((string)Main\Config\Option::get('sale', 'product_viewed_save') === 'Y')
			{
				if (
					!isset($_SESSION['VIEWED_ENABLE'])
					&& isset($_SESSION['VIEWED_PRODUCT'])
					&& $_SESSION['VIEWED_PRODUCT'] != $this->arResult['ID']
					&& Loader::includeModule('sale')
				)
				{
					$_SESSION['VIEWED_ENABLE'] = 'Y';
					$fields = array(
						'PRODUCT_ID' => (int)$_SESSION['VIEWED_PRODUCT'],
						'MODULE' => 'catalog',
						'LID' => $this->getSiteId()
					);
					\CSaleViewedProduct::Add($fields);
				}

				if (
					isset($_SESSION['VIEWED_ENABLE'])
					&& $_SESSION['VIEWED_ENABLE'] === 'Y'
					&& $_SESSION['VIEWED_PRODUCT'] != $this->arResult['ID']
					&& Loader::includeModule('sale')
				)
				{
					$fields = array(
						'PRODUCT_ID' => $this->arResult['ID'],
						'MODULE' => 'catalog',
						'LID' => $this->getSiteId(),
						'IBLOCK_ID' => $this->arResult['IBLOCK_ID']
					);
					\CSaleViewedProduct::Add($fields);
				}

				$_SESSION['VIEWED_PRODUCT'] = $this->arResult['ID'];
			}

			if ($this->arParams['SET_VIEWED_IN_COMPONENT'] === 'Y' && !empty($this->arResult['VIEWED_PRODUCT']))
			{
				if (Loader::includeModule('catalog') && Loader::includeModule('sale'))
				{
					Catalog\CatalogViewedProductTable::refresh(
						$this->arResult['VIEWED_PRODUCT']['OFFER_ID'],
						\CSaleBasket::GetBasketUserID(),
						$this->getSiteId(),
						$this->arResult['VIEWED_PRODUCT']['PRODUCT_ID']
					);
				}
			}
		}
	}

	/**
	 * Save bigdata analytics for catalog.element only.
	 *
	 * @return void
	 */
	protected function sendCounters()
	{
		parent::sendCounters();
		if (isset($this->arResult['counterData']) && Main\Analytics\Catalog::isOn())
		{
			Main\Analytics\Counter::sendData('ct', $this->arResult['counterData']);
		}
	}
	
	//fda2000
	protected function getFilter() {
		$filterFields = parent::getFilter();
		if($this->arParams['SHOW_DEACTIVATED']!== 'Y') {
			$filterFields['ACTIVE'] = 'Y';
			$filterFields['SECTION_GLOBAL_ACTIVE'] = 'Y';
		}
		
		global $USER;
		CModule::IncludeModule('fire.main');
		$filterFields = Fire_Settings::addFilterImgStatus($filterFields, 'PROPERTY_img_status');
		
		$globalFilter = [];
		if (!empty($this->globalFilter))
			$globalFilter = $this->convertFilter($this->globalFilter);
		foreach($globalFilter as $key=>$val);
			unset($filterFields[$key]);
		
		return $filterFields;
	}
	
	protected function loadData() {
		if(!$this->isCacheDisabled()) {
			ob_start();
			$isCache = $this->startResultCache(false, $this->getAdditionalCacheId(), $this->getComponentCachePath());
			ob_end_clean();
			if(!$isCache && $this->arResult['ID']) {
				CModule::IncludeModule('fire.main');
				$arr = unserialize(Fire_Settings::getModuleSetting('ClearCacheElements'));
				$arr = is_array($arr)? $arr : array();
				if($arr[$this->arResult['ID']]) {
					$this->clearResultCache($this->getAdditionalCacheId(), $this->getComponentCachePath());
					unset($arr[$this->arResult['ID']]);
					Fire_Settings::setModuleSetting('ClearCacheElements', serialize($arr));
				}
			}
			$this->abortResultCache();
		}
		
		parent::loadData();
	}
	
	protected function initUrlTemplates() {
		parent::initUrlTemplates();
		$this->storage['URLS']['~COMPARE_URL_TEMPLATE'] = "?ADD_TO_COMPARE_LIST=#ID#";
		$this->storage['URLS']['COMPARE_URL_TEMPLATE'] = htmlspecialcharsbx($this->storage['URLS']['~COMPARE_URL_TEMPLATE']);
		$this->storage['URLS']['~WISHLIST_URL_TEMPLATE'] = "?ADD_TO_WISHLIST_LIST=#ID#";
		$this->storage['URLS']['WISHLIST_URL_TEMPLATE'] = htmlspecialcharsbx($this->storage['URLS']['~WISHLIST_URL_TEMPLATE']);
	}
	
	protected function prepareData() {
		if(isset($this->arResult['SECT_ITEMS']))
			return parent::prepareData();
		parent::prepareData();
		
		if ($this->arParams['DISPLAY_WISHLIST']) {
			$this->arResult['~WISHLIST_URL'] = str_replace('#ID#', $this->arResult["ID"], $this->storage['URLS']['~WISHLIST_URL_TEMPLATE']);
			$this->arResult['WISHLIST_URL'] = str_replace('#ID#', $this->arResult["ID"], $this->storage['URLS']['WISHLIST_URL_TEMPLATE']);
		}
		
		global $APPLICATION;
		if($this->arParams['SET_TITLE']) {
			if ($this->arResult['IPROPERTY_VALUES']['ELEMENT_PAGE_TITLE'] != '')
				$APPLICATION->SetTitle($this->arResult['IPROPERTY_VALUES']['ELEMENT_PAGE_TITLE']);
			else
				$APPLICATION->SetTitle($this->arResult['NAME']);
		}
		
		if (mb_strlen($this->arResult['DETAIL_TEXT']) > 200) {
			$this->arResult['SHORT_DETAIL_TEXT'] = mb_substr($this->arResult['DETAIL_TEXT'], 0, 200);
			$pos = mb_strrpos($this->arResult['SHORT_DETAIL_TEXT'], ' ');
			if ($pos!== false)
				$this->arResult['SHORT_DETAIL_TEXT'] = mb_substr($this->arResult['SHORT_DETAIL_TEXT'], 0, $pos);
		}
		else
			$this->arResult['SHORT_DETAIL_TEXT'] = $this->arResult['DETAIL_TEXT'];
		
		unset($this->arResult['VENDOR']);
		$vendor_xml_id = trim($this->arResult["PROPERTIES"]["vendor"]["VALUE"]);
		if ($vendor_xml_id != "")
		{
			$arVendorFilter = array(
				"IBLOCK_ID" => $this->arParams["VENDORS_IBLOCK_ID"],
				"XML_ID" => $vendor_xml_id,
				"ACTIVE" => "Y",
			);
			$rsVendor = CIBlockElement::GetList(array(), $arVendorFilter, false, array("nTopCount" => 1), array("*","PROPERTY_country"));
			if ($this->arResult['VENDOR'] = $rsVendor->GetNext())
			{
				$this->arResult['VENDOR']['PREVIEW_PICTURE'] = CFile::GetFileArray($this->arResult['VENDOR']['PREVIEW_PICTURE']);
			}
		}
		$this->arResult['BIG_PICTURES'] = array();
		$this->arResult['MIDDLE_PICTURES'] = array();
		$i = 1;
		$this->arResult['IMAGES'] = Fire_Images::getResizeCatalogItem($this->arResult, array('width'=>$this->arParams['IMAGE_WIDTH'], 'height'=>$this->arParams['IMAGE_HEIGHT']));
		foreach($this->arResult['IMAGES'] as $file) {//Old props
			$this->arResult['MIDDLE_PICTURES'][] = $file['src'];
			$this->arResult['BIG_PICTURES'][] = $file['SRC'];
		}
		//fda2000 Vendor XML_ID correct
		if(is_array($this->arResult['DISPLAY_PROPERTIES']['vendor']) && is_array($this->arResult['VENDOR']))
			$this->arResult['DISPLAY_PROPERTIES']['vendor']['DISPLAY_VALUE'] = '<a href="'.$this->arResult['VENDOR']['DETAIL_PAGE_URL'].'">'.$this->arResult['VENDOR']['NAME'].'</a>';
		//
		
		// Товары раздела
		$this->arResult['SECT_ITEMS'] = $this->arResult['SECT_ELEMENTS'] = [];
		if($this->arResult['ID'] && $this->arResult['SECTION']['ID'] > 0 && $this->arParams['SECT_COUNT']>0) {
//*
			$arCatalog = $this->storage['CATALOGS'][$this->arParams['IBLOCK_ID']];
			$arFilter = $this->getFilter() + array(
				"IBLOCK_ID" => $this->arParams["IBLOCK_ID"],
				"INCLUDE_SUBSECTIONS" => "Y",
				"SECTION_ID" => $this->arResult["SECTION"]["ID"],
				"!ID" => $this->arResult["ID"]
			);
			if($this->arParams['HIDE_NOT_AVAILABLE_OFFERS']!=='N' && $this->arParams['HIDE_NOT_AVAILABLE_OFFERS']!=='L')
				$arFilter['CATALOG_AVAILABLE'] = 'Y';
			/*if($this->arParams['HIDE_NOT_AVAILABLE_OFFERS']=='Y') {
				$arSubFilter['CATALOG_AVAILABLE'] = 'Y'; // а это условие обеспечит выборку только тех предложений, которые можно купить
				$arSubFilter["IBLOCK_ID"] = $arCatalog['IBLOCK_ID'];
				//$arSubFilter["ACTIVE_DATE"] = "Y";
				//$arSubFilter["ACTIVE"] = "Y";
				$arFilter["=ID"] = CIBlockElement::SubQuery("PROPERTY_".$arCatalog["SKU_PROPERTY_ID"], $arSubFilter);
			}*/
			
			//fda2000 img_status
			//global $USER;
			//CModule::IncludeModule('fire.main');
			//if($shop_img_status = Fire_Settings::getOption($USER->IsAuthorized()? 'SHOP_IMG_STATUS_REG' : 'SHOP_IMG_STATUS'))
			//	$arFilter['<=PROPERTY_img_status'] = $shop_img_status;
			//
			// list of the element fields that will be used in selection
			$arSelect = array(
				"ID",
				"IBLOCK_ID",
				"CODE",
				"XML_ID",
				"NAME",
				"ACTIVE",
				"SORT",
				"IBLOCK_SECTION_ID",
				"DETAIL_PAGE_URL",
				"CATALOG_QUANTITY"
			);
			
			$arSort = array(
				"RAND" => "ASC",
				"ID" => "DESC",
			);
			$arNavParams = array("nTopCount"=>$this->arParams["SECT_COUNT"]);
			$intKey = 0;
			$this->arResult["SECT_ITEMS"] = array();
			$arSectElementLink = array();
			$this->arResult["SECT_ELEMENTS"] = array();
			
			//$s_time = microtime(true);
/*
			$rsSectionElements = CIBlockElement::GetList($arSort, $arFilter, false, $arNavParams, array_merge($arSelect,['PROPERTY_pics', 'PROPERTY_pics', 'PROPERTY_vendor', 'CATALOG_GROUP_1']));
			$rsSectionElements->SetUrlTemplates($this->arParams["DETAIL_URL"]);
			$rsSectionElements->SetSectionContext($this->arResult["SECTION"]);
			while($arItem = $rsSectionElements->GetNext()) {
				var_dump($arItem);
			}
			var_dump(microtime(true)-$s_time);$s_time = microtime(true);
			*/

			$rsSectionElements = CIBlockElement::GetList($arSort, $arFilter, false, $arNavParams, $arSelect);
			$rsSectionElements->SetUrlTemplates($this->arParams["DETAIL_URL"]);
			$rsSectionElements->SetSectionContext($this->arResult["SECTION"]);
			
			//fda2000
			while($arItem = $rsSectionElements->GetNext()) {
				$arItem["PROPERTIES"] = array();
				$arItem["DISPLAY_PROPERTIES"] = array();
				$this->arResult["SECT_ITEMS"][$intKey] = $arItem;
				$this->arResult["SECT_ELEMENTS"][$intKey] = $arItem["ID"];
				$arSectElementLink[$arItem['ID']] = &$this->arResult["SECT_ITEMS"][$intKey];
				$intKey++;
			}
			//
			
			if (!empty($this->arResult["SECT_ELEMENTS"]))
			{
				$offersFilter = array(
					'IBLOCK_ID' => $this->arParams['IBLOCK_ID'],
					'HIDE_NOT_AVAILABLE' => $this->arParams['HIDE_NOT_AVAILABLE_OFFERS']
				);
				$offersFilter['SHOW_PRICE_COUNT'] = $this->arParams['SHOW_PRICE_COUNT'];
				$arOffers = CIBlockPriceTools::GetOffersArray(
					$offersFilter,
					$this->arResult["SECT_ELEMENTS"],
					array(
						"sort" => "asc",
					),
					array(),
					array(),
					0,
					$this->arResult["CAT_PRICES"],
					$this->arParams['PRICE_VAT_INCLUDE'],
					$arConvertParams
				);
				if(!empty($arOffers))
				{
					foreach($arOffers as $arOffer)
					{
						if (isset($arSectElementLink[$arOffer["LINK_ELEMENT_ID"]]))
						{
							if ($arOffer["CAN_BUY"] and empty($arSectElementLink[$arOffer["LINK_ELEMENT_ID"]]["MIN_PRICE"]))
							{
								$arSectElementLink[$arOffer["LINK_ELEMENT_ID"]]["MIN_PRICE"] = $arOffer["MIN_PRICE"];
							}
						}
					}
					unset($arOffer);
				}
				
				//fda2000 props
				$arPropFilter = array(
					'ID' => $this->arResult["SECT_ELEMENTS"],
					'IBLOCK_ID' => $this->arParams['IBLOCK_ID']
				);
				$this->arParams["LIST_PROPERTY_CODE"] = is_array($this->arParams["LIST_PROPERTY_CODE"])? $this->arParams["LIST_PROPERTY_CODE"] : [];
				CIBlockElement::GetPropertyValuesArray($arSectElementLink, $this->arParams["IBLOCK_ID"], $arPropFilter, ['CODE'=>array_merge(['pics'], $this->arParams["LIST_PROPERTY_CODE"])]);
				foreach ($this->arResult["SECT_ITEMS"] as &$arItem) {
					foreach($this->arParams["LIST_PROPERTY_CODE"] as $pid) {
						if (!isset($arItem["PROPERTIES"][$pid]))
							continue;
						$prop = &$arItem["PROPERTIES"][$pid];
						$boolArr = is_array($prop["VALUE"]);
						if(
							($boolArr && !empty($prop["VALUE"]))
							|| (!$boolArr && mb_strlen($prop["VALUE"]) > 0)
						)
						{
							$arItem["DISPLAY_PROPERTIES"][$pid] = CIBlockFormatProperties::GetDisplayValue($arItem, $prop, "catalog_out");
						}
					}
					$arItem["PREVIEW_PICTURE"] = array("SRC" => "");
					if($arItem["PROPERTIES"]['pics'] && $arItem["PROPERTIES"]['pics']['VALUE']) {
						$arItem_tmp = $arItem;
						$i = 0;
						if($arItem_tmp["PROPERTIES"]["pics"]['VALUE'])
							foreach($arItem_tmp["PROPERTIES"]["pics"]['VALUE'] as $key=>$val)
								if($i++>1)
									unset($arItem_tmp["PROPERTIES"]["pics"]['VALUE'][$key]);
						$arItem["IMAGES"] = Fire_Images::getResizeCatalogItem($arItem_tmp, array('width'=>$this->arParams["SECT_IMAGE_WIDTH"], 'height'=>$this->arParams["SECT_IMAGE_HEIGHT"]));
						$arItem["PREVIEW_PICTURE"]["SRC"] = $arItem["IMAGES"][0]['src'];
						
						if($this->arParams['DISPLAY_COMPARE']) {
							$arItem['~COMPARE_URL'] = str_replace('#ID#', $arItem["ID"], $this->storage['URLS']['~COMPARE_URL_TEMPLATE']);
							$arItem['COMPARE_URL'] = str_replace('#ID#', $arItem["ID"], $this->storage['URLS']['COMPARE_URL_TEMPLATE']);
						}
						if($this->arParams['DISPLAY_WISHLIST']) {
							$arItem['~WISHLIST_URL'] = str_replace('#ID#', $arItem["ID"], $this->storage['URLS']['~WISHLIST_URL_TEMPLATE']);
							$arItem['WISHLIST_URL'] = str_replace('#ID#', $arItem["ID"], $this->storage['URLS']['WISHLIST_URL_TEMPLATE']);
						}
					}
				}
				if(isset($arItem))
					unset($arItem);
				//
			}
//var_dump('src');var_dump(microtime(true)-$s_time);$s_time = microtime(true);
//*/
			//var_dump($this->iblockProducts);
			//var_dump($this->storage);
/*
			$params = [
				'order'=>['RAND' => 'ASC'],
				'filter' => $this->getFilter(),
				'navigation' => ['nTopCount'=>$this->arParams['SECT_COUNT']],
				'select' => [
					"ID",
					"IBLOCK_ID",
					"CODE",
					"XML_ID",
					"NAME",
					"ACTIVE",
					"SORT",
					"IBLOCK_SECTION_ID",
					"DETAIL_PAGE_URL",
				]
			];
			$elementIterator = \CIBlockElement::GetList(
				$params['order'],
				$params['filter'],
				false,
				$params['navigation'],
				$params['select']
			);
			$elementIterator->SetUrlTemplates($this->arParams['DETAIL_URL']);
			
			if (!empty($elementIterator)) {
				$items = [];
				while ($item = $elementIterator->GetNext()) {
					$this->processElement($item);
					$item['OFFERS'] = [];
					$items[$item['ID']] = $item;
				}
				
				$this->arParams["LIST_PROPERTY_CODE"] = is_array($this->arParams["LIST_PROPERTY_CODE"])? $this->arParams["LIST_PROPERTY_CODE"] : [];
				\CIBlockElement::GetPropertyValuesArray($items, $this->arParams['IBLOCK_ID'], ['ID' => array_keys($items)], ['CODE' => array_merge(['pics'], $this->arParams["LIST_PROPERTY_CODE"])]);
				
				//$this->elements = $items;
				//$this->iblockProducts = [$this->arParams['IBLOCK_ID']=>array_keys($items)];
				//var_dump($this->getIblockOffers($this->arParams['IBLOCK_ID']));die;
				$catalog = $this->storage['CATALOGS'][$this->arParams['IBLOCK_ID']];//var_dump($catalog);
				$productProperty = 'PROPERTY_'.$catalog['SKU_PROPERTY_ID'];
				$iterator = \CIBlockElement::GetList(
					[],
					[$productProperty => array_keys($items), 'IBLOCK_ID'=>$catalog['IBLOCK_ID'], 'HIDE_NOT_AVAILABLE' => "Y"],
					false,
					false,
					['ID', $productProperty]
				);
				$offers = [];
				while($row = $iterator->GetNext()) {
					$offers[$row['ID']] = $row;
					$items[$row[$productProperty.'_VALUE']]['OFFERS'][] = $row;
				}
				
				$this->loadPrices(array_keys($offers));
				$this->calculateItemPrices($offers);
				//var_dump($offers);die;
				
				foreach($items as $item) {
					foreach($item["PROPERTIES"] as $pid=>$prop)
						$item['DISPLAY_PROPERTIES'][$pid] = \CIBlockFormatProperties::GetDisplayValue($item, $prop, 'catalog_out');
					$item["PREVIEW_PICTURE"] = array("SRC" => "");
					if($item["PROPERTIES"]['pics'] && $item["PROPERTIES"]['pics']['VALUE']) {
						$arItem_tmp = $item;
						$i = 0;
						if($arItem_tmp["PROPERTIES"]["pics"]['VALUE'])
							foreach($arItem_tmp["PROPERTIES"]["pics"]['VALUE'] as $key=>$val)
								if($i++>1)
									unset($arItem_tmp["PROPERTIES"]["pics"]['VALUE'][$key]);
						$item["IMAGES"] = Fire_Images::getResizeCatalogItem($arItem_tmp, array('width'=>$this->arParams["SECT_IMAGE_WIDTH"], 'height'=>$this->arParams["SECT_IMAGE_HEIGHT"]));
						$item["PREVIEW_PICTURE"]["SRC"] = $item["IMAGES"][0]['src'];
					}
					$this->arResult['SECT_ITEMS'][] = $item;
					$this->arResult['SECT_ELEMENTS'][] = $item['ID'];
				}
				
			}
				//var_dump($this->arResult['SECT_ITEMS']);
			var_dump('d7');var_dump(microtime(true)-$s_time);$s_time = microtime(true);
			//die;
			//*/
/*
			$this->elements = [];
			$this->iblockProducts = [$this->arParams['IBLOCK_ID']=>false];
			$this->sortFields = ['RAND' => 'ASC'];
			$this->navParams = ['nTopCount'=>$this->arParams['SECT_COUNT']];
			$this->selectFields = [
				"ID",
				"IBLOCK_ID",
				"CODE",
				"XML_ID",
				"NAME",
				"ACTIVE",
				"SORT",
				"IBLOCK_SECTION_ID",
				"DETAIL_PAGE_URL",
//				"CATALOG_QUANTITY"
			];
			$this->arParams["LIST_PROPERTY_CODE"] = is_array($this->arParams["LIST_PROPERTY_CODE"])? $this->arParams["LIST_PROPERTY_CODE"] : [];
			$this->storage['IBLOCK_PARAMS'][$this->arParams['IBLOCK_ID']]['PROPERTY_CODE'] = array_merge(['pics'], $this->arParams["LIST_PROPERTY_CODE"]);
			$this->storage['IBLOCK_PARAMS'][$this->arParams['IBLOCK_ID']]['OFFERS_PROPERTY_CODE'] = [];
			$this->setSeparateLoading(true);
			var_dump(999);
			$this->elements = [];
			$this->initElementList();
			//$this->processResultData();
			var_dump(count($this->elements));
			foreach($this->elements as $item) {
				$item["PREVIEW_PICTURE"] = array("SRC" => "");
				if($item["PROPERTIES"]['pics'] && $item["PROPERTIES"]['pics']['VALUE']) {
					$arItem_tmp = $item;
					$i = 0;
					if($arItem_tmp["PROPERTIES"]["pics"]['VALUE'])
						foreach($arItem_tmp["PROPERTIES"]["pics"]['VALUE'] as $key=>$val)
							if($i++>1)
								unset($arItem_tmp["PROPERTIES"]["pics"]['VALUE'][$key]);
					$item["IMAGES"] = Fire_Images::getResizeCatalogItem($arItem_tmp, array('width'=>$this->arParams["SECT_IMAGE_WIDTH"], 'height'=>$this->arParams["SECT_IMAGE_HEIGHT"]));
					$item["PREVIEW_PICTURE"]["SRC"] = $item["IMAGES"][0]['src'];
				}
				
				$this->arResult['SECT_ITEMS'][] = $item;
				$this->arResult['SECT_ELEMENTS'][] = $item['ID'];
			}
			var_dump('class');var_dump(microtime(true)-$s_time);$s_time = microtime(true);
			//$this->arResult['SECT_ITEMS'] = array_values($this->elements);
			//$this->arResult['SECT_ELEMENTS'] = array_keys($this->elements);
			//
			//var_dump($this->arResult);
			//*/
			
			//var_dump($this->arResult['SECT_ITEMS']);
			//die;
		}
		
		//Наличие в магазинах fda2000
		if($this->arParams["SHOPS_IBLOCK_ID"]) {
			$shops = $this->arResult["SHOPS"] = array();
			foreach($this->arResult["OFFERS"] as $key=>$arOffer)
				if($arOffer['IBLOCK_SECTION_ID']) {
					$db = CIBlockElement::GetElementGroups($arOffer['ID'], true, array('ID'));
					while($ar_group = $db->Fetch()) {
						$shops[$ar_group["ID"]] = $ar_group["ID"];
						$this->arResult["OFFERS"][$key]['SHOPS'][$ar_group["ID"]] = $ar_group["ID"];
					}
				}
			if($shops) {
				$rsItems = CIBlockElement::GetList(array(), array("IBLOCK_ID" => $this->arParams["SHOPS_IBLOCK_ID"], 'PROPERTY_OFFER_GROUP'=>$shops), array('NAME', 'DETAIL_PAGE_URL', 'IBLOCK_ID', 'CODE', 'ID', 'PROPERTY_OFFER_GROUP'));
				while ($arItem = $rsItems->GetNext())
					$this->arResult["SHOPS"][$arItem['PROPERTY_OFFER_GROUP_VALUE']] = $arItem;
			}
			foreach($this->arResult["OFFERS"] as $key1=>$arOffer)
				if(is_array($arOffer['SHOPS']))
					foreach($arOffer['SHOPS'] as $key=>$val)
						$this->arResult["OFFERS"][$key1]['SHOPS'][$key] = $this->arResult["SHOPS"][$val];
		}
	}
	
	protected function processLinkAction() {
		global $APPLICATION, $USER;
		Fire_ComponentElementList::checkAddBasket($this->arParams);
		if('BUYCLICK'==mb_strtoupper($this->request->get($this->arParams['ACTION_VARIABLE']))) {
			$APPLICATION->RestartBuffer();
			$error = [];
			$productID = (int)$this->request->get($this->arParams['PRODUCT_ID_VARIABLE']);
			$product_properties = array();
			$intProductIBlockID = (int)CIBlockElement::GetIBlockByID($productID);
			if (0 < $intProductIBlockID)
			{
				if ($this->arParams['ADD_PROPERTIES_TO_BASKET'] == 'Y')
				{
					if ($intProductIBlockID == $this->arParams["IBLOCK_ID"])
					{
						if (!empty($this->$arParams["PRODUCT_PROPERTIES"]))
						{
							if (
								//isset($this->request->get($this->arParams['PRODUCT_PROPS_VARIABLE'])) && 
								is_array($this->request->get($this->arParams['PRODUCT_PROPS_VARIABLE']))
							)
							{
								$product_properties = CIBlockPriceTools::CheckProductProperties(
									$this->arParams["IBLOCK_ID"],
									$productID,
									$this->arParams["PRODUCT_PROPERTIES"],
									$this->request->get($this->arParams['PRODUCT_PROPS_VARIABLE']),
									$this->arParams['PARTIAL_PRODUCT_PROPERTIES'] == 'Y'
								);
								if (!is_array($product_properties))
								{
									$error[] = GetMessage("CATALOG_PARTIAL_BASKET_PROPERTIES_ERROR");
								}
							}
							else
							{
								$error[] = GetMessage("CATALOG_EMPTY_BASKET_PROPERTIES_ERROR");
							}
						}
					}
					else
					{
						$skuAddProps = (/*isset($this->request->get('basket_props')) && */!empty($this->request->get('basket_props')) ? $this->request->get('basket_props') : '');
						if (!empty($this->arParams["OFFERS_CART_PROPERTIES"]) || !empty($skuAddProps))
						{
							$product_properties = CIBlockPriceTools::GetOfferProperties(
								$productID,
								$this->arParams["IBLOCK_ID"],
								$this->arParams["OFFERS_CART_PROPERTIES"],
								$skuAddProps
							);
						}
					}
				}
			}
			else
			{
				$error[] = GetMessage('CATALOG_ELEMENT_NOT_FOUND');
			}
			
			$Name = $this->request->get('buy_click_name');
			$Phone = $this->request->get('buy_click_phone');
			$Email = $this->request->get('buy_click_email');
			$Comments = $this->request->get('buy_click_comments');
			if(mb_strlen($Name)<3)
				$error[] = GetMessage('SBB_BUY_CLICK_WRONG_NAME');
			if(mb_strlen($Phone)<11)
				$error[] = GetMessage('SBB_BUY_CLICK_WRONG_PHONE');
			if($Email && !check_email($Email, true))
				$error[] = GetMessage('SBB_BUY_CLICK_WRONG_EMAIL');
			
			Loader::IncludeModule('sale');
			
			if(!$error) {
				function getPropertyByCode($propertyCollection, $code) {
					foreach ($propertyCollection as $property)
					{
						if($property->getField('CODE') == $code)
							return $property;
					}
				}
				
				Bitrix\Sale\Compatible\DiscountCompatibility::stopUsageCompatible();
				
				$UserID = $USER->GetID() ? $USER->GetID() : \CSaleUser::GetAnonymousUserID();
				$siteId = \Bitrix\Main\Context::getCurrent()->getSite();
				$order = Bitrix\Sale\Order::create($siteId, $UserID);
				$personType = \Bitrix\Sale\PersonType::getList(
					[
						'select'=>['ID'],
						'filter'=>['ACTIVE'=>'Y'],
						'order'=>['SORT'=>'ASC'],
						'limit' => 1
					]
				)->fetch();
				$order->setPersonTypeId($personType['ID']);
				$basket = Bitrix\Sale\Basket::create($siteId);
				
				$del = array('aID', 'prodID', 'stripmag', 'PICTURE');
				$props = array();
				foreach ($product_properties as $key=>$prop)
					if(array_search($prop['CODE'], $del)!==false)
						unset($product_properties[$key]);
				
				$fields = [
					'PRODUCT_ID' => $productID,
					'QUANTITY' => 1,
					'MODULE' => 'catalog',
					'PRODUCT_PROVIDER_CLASS' => \Bitrix\Catalog\Product\Basket::getDefaultProviderName(),
					'PROPS' => $product_properties,
				];
				$r = \Bitrix\Catalog\Product\Basket::addProductToBasketWithPermissions($basket, $fields, array('SITE_ID'=>$siteId, 'USER_ID'=>$UserID));
				if(!$r->isSuccess()) 
					$error+= $r->getErrorMessages();
				else {
					$order->setBasket($basket);
					
					Bitrix\Sale\DiscountCouponsManager::init();
					$order->doFinalAction(true);
					
					$propertyCollection = $order->getPropertyCollection();
					$emailProperty = getPropertyByCode($propertyCollection, 'NAME');
					if($emailProperty)
						$emailProperty->setValue($Name);
					$phoneProperty = getPropertyByCode($propertyCollection, 'PHONE');
					if($phoneProperty)
						$phoneProperty->setValue($Phone);
					$emailProperty = getPropertyByCode($propertyCollection, 'EMAIL');
					if($emailProperty)
						$emailProperty->setValue($Email);
					
					$order->setField('COMMENTS', GetMessage('SBB_BUY_CLICK_ORDER_DESCRIPTION'));
					$order->setField('USER_DESCRIPTION', $Comments);
					
					$orderId = false;
					$r = $order->save();
					if(!$r->isSuccess())
						$error+= $r->getErrorMessages();
					else
						$orderId = $order->GetId();
					
					if($orderId) {
						Bitrix\Sale\Compatible\DiscountCompatibility::revertUsageCompatible();
						echo "<script>window.location='?BUY_CLICK_ORDER_ID=$orderId'</script>";
						die;
					}
				}
				Bitrix\Sale\Compatible\DiscountCompatibility::revertUsageCompatible();
				$error[] = GetMessage('SBB_BUY_CLICK_ERROR_ORDER_CREATE');
			}
			echo implode('<br>', $error);
			die();
		}
		return parent::processLinkAction();
	}
	/*
	protected function modifyDisplayProperties($iblock, &$iblockElements) {
		if(!$this->arResult['ID'])
			return parent::modifyDisplayProperties($iblock, $iblockElements);
		
		if (!empty($iblockElements))
		{
			$iblockParams = $this->storage['IBLOCK_PARAMS'][$iblock];
			$propertyCodes = $iblockParams['PROPERTY_CODE'];
			$productProperties = $iblockParams['CART_PROPERTIES'];
			$getPropertyCodes = !empty($propertyCodes);
			$getProductProperties = $this->arParams['ADD_PROPERTIES_TO_BASKET'] === 'Y' && !empty($productProperties);
			$getIblockProperties = $getPropertyCodes || $getProductProperties;

			if ($getIblockProperties || ($this->useCatalog && $this->useDiscountCache))
			{
				$propFilter = array(
					'ID' => array_keys($iblockElements),
					'IBLOCK_ID' => $iblock
				);
				\CIBlockElement::GetPropertyValuesArray($iblockElements, $iblock, $propFilter, ['CODE' => $propertyCodes]);

				if ($getPropertyCodes)
				{
					$propertyList = $this->getPropertyList($iblock, $propertyCodes);
				}

				foreach ($iblockElements as &$element)
				{
					if ($this->useCatalog && $this->useDiscountCache)
					{
						if ($this->storage['USE_SALE_DISCOUNTS'])
							Catalog\Discount\DiscountManager::setProductPropertiesCache($element['ID'], $element["PROPERTIES"]);
						else
							\CCatalogDiscount::SetProductPropertiesCache($element['ID'], $element['PROPERTIES']);
					}

					if ($getIblockProperties)
					{
						if (!empty($propertyList))
						{
							foreach ($propertyList as $pid)
							{
								if (!isset($element['PROPERTIES'][$pid]))
									continue;

								$prop =& $element['PROPERTIES'][$pid];
								$isArr = is_array($prop['VALUE']);
								if (
									($isArr && !empty($prop['VALUE']))
									|| (!$isArr && (string)$prop['VALUE'] !== '')
								)
								{
									$element['DISPLAY_PROPERTIES'][$pid] = \CIBlockFormatProperties::GetDisplayValue($element, $prop, 'catalog_out');
								}
								unset($prop);
							}
							unset($pid);
						}

						if ($getProductProperties)
						{
							$element['PRODUCT_PROPERTIES'] = \CIBlockPriceTools::GetProductProperties(
								$iblock,
								$element['ID'],
								$productProperties,
								$element['PROPERTIES']
							);

							if (!empty($element['PRODUCT_PROPERTIES']))
							{
								$element['PRODUCT_PROPERTIES_FILL'] = \CIBlockPriceTools::getFillProductProperties($element['PRODUCT_PROPERTIES']);
							}
						}
					}
				}
				unset($element);
			}
		}
	}
	
	protected function getSort() {
		if(!$this->arResult['ID'])
			return parent::getSort();
		return $this->sortFields;
	}
	
	protected function getIblockElements($elementIterator) {
		if(!$this->arResult['ID'])
			return parent::getIblockElements($elementIterator);
		
		$iblockElements = array();

		if (!empty($elementIterator))
		{
			while ($element = $elementIterator->GetNext())
			{
				$this->processElement($element);
				$iblockElements[$element['ID']] = $element;
			}
		}

		return $iblockElements;
	}
	*/
}