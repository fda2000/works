<?
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

if($arParams['PRODUCT_IBLOCK_ID']) {
	$codes = [];
	$res = CIBlockProperty::GetList([], ['IBLOCK_ID'=>$arParams['PRODUCT_IBLOCK_ID']]);
	while($arr = $res->Fetch())
		$codes[$arr['CODE']] = $arr['ID'];
	
	if(!$codes['vendor'])
		return;
	
	$getList = [
			'select' => ['VENDOR_ID'=>'VENDOR.VALUE_NUM'],
			'filter' => [
				'=IBLOCK_ID'=> $arParams['PRODUCT_IBLOCK_ID'],
				'=ACTIVE'=> 'Y',
				/*'ELEMENT.SECTION_ACTIVE'=> 'Y',
				'ELEMENT.ACTIVE_DATE'=>'Y',
				'ELEMENT.INCLUDE_SUBSECTIONS'=>'Y',*/
				//'ELEMENT.IBLOCK.CHECK_PERMISSIONS'=>'Y',
				//'ELEMENT.IBLOCK.MIN_PERMISSION'=>'R',
				//'=IBLOCK.LID'=>SITE_ID,
				'=VENDOR.IBLOCK_PROPERTY_ID' => $codes['vendor']
			],
		'group' => ['VENDOR_ID'],
		'runtime' => [
			'VENDOR' => [
				'data_type' => 'Bitrix\Iblock\ElementProperty',
				'reference' => array('=this.ID' => 'ref.IBLOCK_ELEMENT_ID')
			]
		]
	];
	
	if($arParams['HIDE_NOT_AVAILABLE']!=='N' && $arParams['HIDE_NOT_AVAILABLE']!=='L') {
		$getList['filter']['=PRODUCT.AVAILABLE'] = 'Y';
		$getList['runtime']['PRODUCT'] = [
			'data_type' => 'Bitrix\Catalog\ProductTable',
			'reference' => array('=this.ID' => 'ref.ID')
		];
	}
	
	if($codes['img_status']) {
		CModule::IncludeModule('fire.main');
		$count = count($getList['filter']);
		$getList['filter'] = Fire_Settings::addFilterImgStatus($getList['filter'], 'STATUS.VALUE_NUM');
		if (count($getList['filter']) > $count) {
			$getList['filter']['=STATUS.IBLOCK_PROPERTY_ID'] = $codes['img_status'];
			$getList['runtime']['STATUS'] = [
				'data_type' => 'Bitrix\Iblock\ElementProperty',
				'reference' => array('=this.ID' => 'ref.IBLOCK_ELEMENT_ID')
			];
		}
	}
	
	$iterator = Bitrix\Iblock\ElementTable::getList($getList);
	while($row=$iterator->fetch())
		$ids[(int)$row['VENDOR_ID']] = true;
	
	foreach($arResult["ITEMS"] as $key=>$val)
		if(!$ids[(int)$val['EXTERNAL_ID']])
			unset($arResult["ITEMS"][$key]);
}
?>