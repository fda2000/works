<?
$module_id = "fire.main";
IncludeModuleLangFile(__FILE__);
include_once($GLOBALS["DOCUMENT_ROOT"]."/bitrix/modules/".$module_id."/include.php");

$classes = ['Fire_Settings', 'Fire_Actions'];

if($_POST) {
	foreach($classes as $class)
		call_user_func_array([$class, 'SetSettings'], [$_POST]);
	
	if ($_FILES["POPUP_FILE"]["name"] != "" and $_FILES["POPUP_FILE"]["tmp_name"] != "")
	{
		$path_arr = pathinfo($_FILES["POPUP_FILE"]["name"]);
		if (in_array($path_arr["extension"], array("jpg","jpeg","gif","png")))
		{
			if (move_uploaded_file($_FILES["POPUP_FILE"]["tmp_name"], $_SERVER["DOCUMENT_ROOT"]."/img/".$path_arr["basename"]))
				COption::SetOptionString($module_id, "POPUP_PICTURE", "/img/".$path_arr["basename"], SITE_ID);
		}
		else
			CAdminMessage::ShowMessage("Неверный формат файла");
	}
}

$iblocks = $sites = $imports = $highloads = $PackDelivery  = array();
if(CModule::IncludeModule("iblock") && CModule::IncludeModule("catalog") && CModule::IncludeModule('highloadblock') && CModule::IncludeModule('sale')) {
	$res = CIBlock::GetList(
		Array("NAME"=>"ASC")
	);
	while($ar_res = $res->Fetch())
		$iblocks[$ar_res['ID']] = $ar_res['NAME'];
	
	$res = CCatalogImport::GetList(
		Array("NAME"=>"ASC")
	);
	while($ar_res = $res->Fetch())
		$imports[$ar_res['ID']] = $ar_res['NAME'];
	
	$res = CSite::GetList($by="name", $order="asc");
	while($ar_res = $res->Fetch())
		$sites[$ar_res['ID']] = $ar_res['NAME'];
	
	$res = \Bitrix\Highloadblock\HighloadBlockTable::getList(array(
		'select' => array('*'),
		'order' => array('NAME' => 'ASC')
	));
	while($item = $res->fetch())
		$highloads[$item['ID']] = $item['NAME'];
	
	$res = CSaleDelivery::GetList(
		array(
			"SORT" => "ASC",
			"NAME" => "ASC"
		),
		array(
			"ACTIVE" => "Y",
			'!XML_ID'=>array('RUSPOST', 'RUSPOST_FREE', 'COURIER_MOSCOW', 'COURIER_MOSCOW_FREE', 'SELF_PICKUP')//Почта России, Курьер по Москве, Самовывоз
		),
		false,
		false,
		array('ID', 'NAME')
	);
	while ($dev = $res->Fetch())
		$PackDelivery[$dev['ID']] = $dev['NAME'];
}

$settings = Fire_Settings::GetSettings();

$templates = array();
$res = CSite::GetTemplateList($settings['SETTINGS_SITE']);
while($arTemplate = $res->Fetch())
	$templates[$arTemplate['ID']] = $arTemplate['TEMPLATE'];

$aTabs = [];
$MOD_RIGHT = $APPLICATION->GetGroupRight($module_id);
if($MOD_RIGHT>="W" && true):
	$aTabs = [];
	foreach($classes as $class)
		if($tab=call_user_func_array([$class, 'getTab'], [])) {
			$tab['CLASS'] = $class;
			$aTabs[] = $tab;
		}
	/*$aTabs = array(
		0 => array(
			'TAB'=>'Общие настройки', 
			'DIV'=>'edit1', 
			'TITLE' => 'Настройки модуля',
		)
	);*/
	$tab = new CAdminTabControl('Fire_Settings_tab', $aTabs);
	$tab->Begin();
?>
<style>
.fire-option .long {
	width: 100%;
	box-sizing: border-box;
}
.fire-option textarea {
	width: 100%;
	box-sizing: border-box;
	height: 100px;
}
</style>
	<form method="post" enctype="multipart/form-data" class="fire-option">
<?	$tab->BeginNextTab();?>
	<? if(array_search('fire_pink', $templates)!==false) {?>
		<tr class="heading">
			<td colspan="2">Дизайн</td>
		</tr>
		<tr>
			<td width="40%" class="adm-detail-content-cell-l" style="white-space:nowrap">Вариант дизайна:</td>
			<td width="60%">
				<select name="DESIGN">
					<option value="old" <?if ($settings["DESIGN"] == "old"):?> selected<?endif?>>Стандартный</option>
					<option value="new" <?if ($settings["DESIGN"] == "new"):?> selected<?endif?>>Розовый</option>
				</select>
			</td>
		</tr>
	<? }?>
		<tr class="heading">
            <td colspan="2">Импорт каталога</td>
        </tr>
		<tr>
			<td width="40%" class="adm-detail-content-cell-l" style="white-space:nowrap">Какую цену использовать:</td>
			<td width="60%">
				<select name="IMPORT_PRICE_TYPE">
					<option value="1" <?if ($settings["IMPORT_PRICE_TYPE"] == "1"):?> selected<?endif?>>Розничную</option>
					<option value="2" <?if ($settings["IMPORT_PRICE_TYPE"] == "2"):?> selected<?endif?>>Оптовую</option>
				</select>
			</td>
        </tr>
		<tr>
						<td width="100%" class="adm-detail-content-cell-l" style="white-space:nowrap" colspan="2">
				<table style="margin:0 auto;text-align:center;">
					<tr>
						<th>цена от</th>
						<th>+/-</th>
						<th>процентов</th>
					</tr>
				<? if(is_array($settings['IMPORT_NACENKA'])) foreach($settings['IMPORT_NACENKA'] as $price=>$discount) {?>
					<tr>
						<td>
							<input type="text" name="IMPORT_NACENKA[price][]"  value="<?=$price?>" />
						</td>
						<td>
							<select name="IMPORT_NACENKA[sign][]">
								<option value="1"<?=$discount>=0? ' selected' : ''?>>Наценка</option>
								<option value="-1"<?=$discount<0? ' selected' : ''?>>Скидка</option>
							</select>
						</td>
						<td>
							<input type="text" name="IMPORT_NACENKA[discount][]"  value="<?=abs($discount)?>" />
						</td>
					</tr>
				<? }?>
					<tr>
						<td>
							<input type="text" name="IMPORT_NACENKA[price][]"  value="" />
						</td>
						<td>
							<select name="IMPORT_NACENKA[sign][]">
								<option value="1">Наценка</option>
								<option value="-1">Скидка</option>
							</select>
						</td>
						<td>
							<input type="text" name="IMPORT_NACENKA[discount][]"  value="" />
						</td>
					</tr>
				</table>
			</td>
		</tr>
		<tr>
			<td width="40%" class="adm-detail-content-cell-l" style="white-space:nowrap">Новые товары при импорте снимать с продажи:</td>
			<td width="60%">
				<input type="checkbox" name="IMPORT_PRODUCT_DEACTIVATE" value="Y"<?=$settings["IMPORT_PRODUCT_DEACTIVATE"]? 'checked="checked"' : '' ?> />
			</td>
        </tr>
		<tr>
			<td width="40%" class="adm-detail-content-cell-l" style="white-space:nowrap">Следовать РРЦ:</td>
			<td width="60%">
				<input type="checkbox" name="IMPORT_PRODUCT_RRC" value="1"<?=$settings["IMPORT_PRODUCT_RRC"]? 'checked="checked"' : '' ?> />
			</td>
        </tr>
		<tr>
			<td width="40%" class="adm-detail-content-cell-l" style="white-space:nowrap">Снимать с продажи товары, дата отгрузки которых больше чем через 24 часа:</td>
			<td width="60%">
				<input type="checkbox" name="IMPORT_PRODUCT_SHIPPING24" value="1"<?=$settings["IMPORT_PRODUCT_SHIPPING24"]? 'checked="checked"' : '' ?> />
			</td>
		</tr>
		<tr>
			<td width="40%" class="adm-detail-content-cell-l" style="white-space:nowrap">Снимать с продажи товары, складской остаток которых менее:</td>
			<td width="60%">
				<input type="text" name="IMPORT_PRODUCT_MINSTOCK" value="<?=$settings['IMPORT_PRODUCT_MINSTOCK']?>" />
			</td>
		</tr>
		<tr class="heading">
            <td colspan="2">Магазин</td>
        </tr>
		<tr>
			<td width="40%" class="adm-detail-content-cell-l">Какие товары показывать незарегистрированным пользователям?</td>
			<td width="60%">
				<select name="SHOP_IMG_STATUS">
				<?php foreach (Fire_Settings::getImgStatuses() as $id => $name) {?>
					<option value="<?=$id?>" <?if ($settings["SHOP_IMG_STATUS"] === (string)$id):?> selected<?endif?>><?=$name?></option>
				<?php }?>
				</select>
			</td>
		</tr>
		<tr>
			<td width="40%" class="adm-detail-content-cell-l">Какие товары показывать зарегистрированным пользователям?</td>
			<td width="60%">
				<select name="SHOP_IMG_STATUS_REG">
                <?php foreach (Fire_Settings::getImgStatuses() as $id => $name) {?>
					<option value="<?=$id?>" <?if ($settings["SHOP_IMG_STATUS_REG"] === (string)$id):?> selected<?endif?>><?=$name?></option>
                <?php }?>
				</select>
			</td>
		</tr>
		<tr>
			<td width="40%" class="adm-detail-content-cell-l" style="white-space:nowrap">Собственные магазины:</td>
			<td width="60%">
				<label><input type="radio" name="SELFSHOP_TYPE" value="" <?=!$settings['SELFSHOP_TYPE']? ' checked="checked"' : ''?> /> Не использовать</label><br />
				<label><input type="radio" name="SELFSHOP_TYPE" value="ONLYSHOW" <?=$settings['SELFSHOP_TYPE']=='ONLYSHOW'? ' checked="checked"' : ''?> /> Только отображать в карточке товара</label><br />
				<label><input type="radio" name="SELFSHOP_TYPE" value="INANYSHOP" <?=$settings['SELFSHOP_TYPE']=='INANYSHOP'? ' checked="checked"' : ''?> /> Доставка заказа со своими товарами в любой из своих магазинов</label><br />
				<label><input type="radio" name="SELFSHOP_TYPE" value="INSHOP" <?=$settings['SELFSHOP_TYPE']=='INSHOP'? ' checked="checked"' : ''?> /> Доставка заказа со своими товарами только в магазины, в которых товар в наличии</label>
			</td>
		</tr>
		<tr class="heading">
            <td colspan="2">Обмен заказами с P5S</td>
        </tr>
		<tr>
			<td width="40%" class="adm-detail-content-cell-l" style="white-space:nowrap">Ключ для API:</td>
			<td width="60%">
				<input type="text" name="P5S_API_KEY"  value="<?=$settings['P5S_API_KEY']?>" />
			</td>
		</tr>
		<tr>
			<td width="40%" class="adm-detail-content-cell-l" style="white-space:nowrap">Не отправлять оповещения о заказах с ошибками:</td>
			<td width="60%">
				<input type="checkbox" name="P5S_NO_ERROR_MAIL" value="Y" <?=$settings['P5S_NO_ERROR_MAIL']? ' checked="checked"' : ''?> />
			</td>
		</tr>
		<tr>
			<td width="40%" class="adm-detail-content-cell-l">Упаковка в коробку (берётся дополнительная плата):</td>
			<td width="60%">
			<? foreach($PackDelivery as $ID=>$name) {?>
				<input type="checkbox" name="P5S_PACK_DELIVERY[<?=$ID?>]" value="Y"<?if ($settings["P5S_PACK_DELIVERY"][$ID] == "Y"):?> checked<?endif?>> <?=$name?><br />
			<? }?>
			</td>
		</tr>
		<tr>
			<td width="40%" class="adm-detail-content-cell-l">E-commerce модель работы ИМ:</td>
			<td width="60%">
				<select name="P5S_SHIPPING_MODEL">
					<option value="DS"<?=$settings["P5S_SHIPPING_MODEL"]=='DS'? ' selected="selected"' : ''?>>Drop Shipping</option>
					<option value="SELF"<?=$settings["P5S_SHIPPING_MODEL"]=='SELF'? ' selected="selected"' : ''?>>Самостоятельная логистика</option>
				</select>
			</td>
		</tr>
		<tr class="heading">
			<td colspan="2">Мониторинг</td>
		</tr>
		<tr>
			<td width="40%" class="adm-detail-content-cell-l" style="white-space:nowrap">E-mail для уведомлений:</td>
			<td width="60%">
				<input type="text" name="MONITORING_EMAIL"  value="<?=$settings['MONITORING_EMAIL']?>" />
			</td>
        </tr>		
		<tr>
			<td width="40%" class="adm-detail-content-cell-l" style="white-space:nowrap">Контролировать доступное дисковое пространство:</td>
			<td width="60%">
				<input type="checkbox" name="MONITORING_DISK" value="Y"<?if ($settings["MONITORING_DISK"] == "Y"):?> checked<?endif?>>
			</td>
        </tr>		
		<tr>
			<td width="40%" class="adm-detail-content-cell-l" style="white-space:nowrap">Контролировать обновление товарной номенклатуры и остатков:</td>
			<td width="60%">
				<input type="checkbox" name="MONITORING_CATALOG" value="Y"<?if ($settings["MONITORING_CATALOG"] == "Y"):?> checked<?endif?>>
			</td>
        </tr>
		<tr>
			<td width="40%" class="adm-detail-content-cell-l" style="white-space:nowrap">Контролировать обмен статусами заказов:</td>
			<td width="60%">
				<input type="checkbox" name="MONITORING_ORDERS" value="Y"<?if ($settings["MONITORING_ORDERS"] == "Y"):?> checked<?endif?>>
			</td>
        </tr>
		<tr class="heading">
            <td colspan="2">Настройки модуля <br /><span style="color:red">НЕ МЕНЯЙТЕ ЭТИ НАСТРОЙКИ ЕСЛИ НЕ ПОНИМАЕТЕ НА ЧТО ОНИ ВЛИЯЮТ!</span></td>
        </tr>
		<tr>
			<td width="40%" class="adm-detail-content-cell-l" style="white-space:nowrap">Сайт:</td>
			<td width="60%">
				<select name="SETTINGS_SITE">
					<option>[Не выбрано]</option>
				<? foreach($sites as $id=>$val) {?>
					<option value="<?=$id?>"<?=$id==$settings["SETTINGS_SITE"]? ' selected="selected"' : ''?>><?=$val?></option>
				<? }?>
				</select>
			</td>
		</tr>
		<tr>
			<td width="40%" class="adm-detail-content-cell-l" style="white-space:nowrap">Каталог товаров:</td>
			<td width="60%">
				<select name="SETTINGS_PRODUCTS_IBLOCK">
					<option>[Не выбрано]</option>
				<? foreach($iblocks as $id=>$val) {?>
					<option value="<?=$id?>"<?=$id==$settings["SETTINGS_PRODUCTS_IBLOCK"]? ' selected="selected"' : ''?>><?=$val?></option>
				<? }?>
				</select>
			</td>
		</tr>
		<tr>
			<td width="40%" class="adm-detail-content-cell-l" style="white-space:nowrap">Каталог товарных предложений:</td>
			<td width="60%">
				<select name="SETTINGS_OFFERS_IBLOCK">
					<option>[Не выбрано]</option>
				<? foreach($iblocks as $id=>$val) {?>
					<option value="<?=$id?>"<?=$id==$settings["SETTINGS_OFFERS_IBLOCK"]? ' selected="selected"' : ''?>><?=$val?></option>
				<? }?>
				</select>
			</td>
		</tr>
		<tr>
			<td width="40%" class="adm-detail-content-cell-l" style="white-space:nowrap">Магазины:</td>
			<td width="60%">
				<select name="SETTINGS_SHOP_IBLOCK">
					<option>[Не выбрано]</option>
				<? foreach($iblocks as $id=>$val) {?>
					<option value="<?=$id?>"<?=$id==$settings["SETTINGS_SHOP_IBLOCK"]? ' selected="selected"' : ''?>><?=$val?></option>
				<? }?>
				</select>
			</td>
		</tr>
		<tr>
			<td width="40%" class="adm-detail-content-cell-l" style="white-space:nowrap">Производители:</td>
			<td width="60%">
				<select name="SETTINGS_VENDOR_IBLOCK">
					<option>[Не выбрано]</option>
				<? foreach($iblocks as $id=>$val) {?>
					<option value="<?=$id?>"<?=$id==$settings["SETTINGS_VENDOR_IBLOCK"]? ' selected="selected"' : ''?>><?=$val?></option>
				<? }?>
				</select>
			</td>
		</tr>
		<tr>
			<td width="40%" class="adm-detail-content-cell-l" style="white-space:nowrap">Цвета:</td>
			<td width="60%">
				<select name="SETTINGS_COLOR_HL">
					<option>[Не выбрано]</option>
				<? foreach($highloads as $id=>$val) {?>
					<option value="<?=$id?>"<?=$id==$settings["SETTINGS_COLOR_HL"]? ' selected="selected"' : ''?>><?=$val?></option>
				<? }?>
				</select>
			</td>
		</tr>
		<tr>
			<td width="40%" class="adm-detail-content-cell-l" style="white-space:nowrap">Профиль импорта товаров:</td>
			<td width="60%">
				<select name="SETTINGS_PRODUCTS_IMPORT_PROFILE">
					<option>[Не выбрано]</option>
				<? foreach($imports as $id=>$val) {?>
					<option value="<?=$id?>"<?=$id==$settings["SETTINGS_PRODUCTS_IMPORT_PROFILE"]? ' selected="selected"' : ''?>><?=$val?></option>
				<? }?>
				</select>
			</td>
		</tr>
		<tr>
			<td width="40%" class="adm-detail-content-cell-l" style="white-space:nowrap">Профиль импорта торговых предложений:</td>
			<td width="60%">
				<select name="SETTINGS_OFFERS_IMPORT_PROFILE">
					<option>[Не выбрано]</option>
				<? foreach($imports as $id=>$val) {?>
					<option value="<?=$id?>"<?=$id==$settings["SETTINGS_OFFERS_IMPORT_PROFILE"]? ' selected="selected"' : ''?>><?=$val?></option>
				<? }?>
				</select>
			</td>
		</tr>
		<tr>
			<td width="40%" class="adm-detail-content-cell-l" style="white-space:nowrap">Профиль импорта производителей:</td>
			<td width="60%">
				<select name="SETTINGS_VENDOR_IMPORT_PROFILE">
					<option>[Не выбрано]</option>
				<? foreach($imports as $id=>$val) {?>
					<option value="<?=$id?>"<?=$id==$settings["SETTINGS_VENDOR_IMPORT_PROFILE"]? ' selected="selected"' : ''?>><?=$val?></option>
				<? }?>
				</select>
			</td>
		</tr>
		<tr>
			<td width="40%" class="adm-detail-content-cell-l" style="white-space:nowrap">Полная выгрузка:</td>
			<td width="60%">
				<input type="text" name="SETTINGS_PRODUCTS_IMPORT_URL"  value="<?=$settings['SETTINGS_PRODUCTS_IMPORT_URL']?>" class="long" />
			</td>
		</tr>
		<tr>
			<td width="40%" class="adm-detail-content-cell-l" style="white-space:nowrap">Выгрузка структуры каталога:</td>
			<td width="60%">
				<input type="text" name="SETTINGS_CATALOG_IMPORT_URL"  value="<?=$settings['SETTINGS_CATALOG_IMPORT_URL']?>" class="long" />
			</td>
		</tr>
		<tr>
			<td width="40%" class="adm-detail-content-cell-l" style="white-space:nowrap">Выгрузка товарных предложений:</td>
			<td width="60%">
				<input type="text" name="SETTINGS_OFFERS_IMPORT_STOCK_URL"  value="<?=$settings['SETTINGS_OFFERS_IMPORT_STOCK_URL']?>" class="long" />
			</td>
		</tr>
		<tr>
			<td width="40%" class="adm-detail-content-cell-l" style="white-space:nowrap">Выгрузка товарных предложений (краткая):</td>
			<td width="60%">
				<input type="text" name="SETTINGS_OFFERS_IMPORT_PRICE_URL"  value="<?=$settings['SETTINGS_OFFERS_IMPORT_PRICE_URL']?>" class="long" />
			</td>
		</tr>
		<tr>
			<td width="40%" class="adm-detail-content-cell-l" style="white-space:nowrap">Справочник производителей:</td>
			<td width="60%">
				<input type="text" name="SETTINGS_VENDOR_IMPORT_URL"  value="<?=$settings['SETTINGS_VENDOR_IMPORT_URL']?>" class="long" />
			</td>
		</tr>
		<tr>
			<td width="40%" class="adm-detail-content-cell-l" style="white-space:nowrap">Справочник цветов:</td>
			<td width="60%">
				<input type="text" name="SETTINGS_COLOR_IMPORT_URL"  value="<?=$settings['SETTINGS_COLOR_IMPORT_URL']?>" class="long" />
			</td>
		</tr>
		<tr>
			<td width="40%" class="adm-detail-content-cell-l" style="white-space:nowrap">Адрес DS API для размещения заказов:</td>
			<td width="60%">
				<input type="text" name="SETTINGS_ORDER_EXPORT_URL"  value="<?=$settings['SETTINGS_ORDER_EXPORT_URL']?>" class="long" />
			</td>
		</tr>
		<tr>
			<td width="40%" class="adm-detail-content-cell-l" style="white-space:nowrap">Адрес DS API для синхронизации статусов заказов:</td>
			<td width="60%">
				<input type="text" name="SETTINGS_ORDER_STATUS_URL"  value="<?=$settings['SETTINGS_ORDER_STATUS_URL']?>" class="long" />
			</td>
		</tr>
		<tr>
			<td width="40%" class="adm-detail-content-cell-l" style="white-space:nowrap">Адрес API для размещения оптовых заказов:</td>
			<td width="60%">
				<input type="text" name="SETTINGS_ORDER_EXPORT_NODS_URL"  value="<?=$settings['SETTINGS_ORDER_EXPORT_NODS_URL']?>" class="long" />
			</td>
		</tr>
		<tr>
			<td width="40%" class="adm-detail-content-cell-l" style="white-space:nowrap">Адрес API для синхронизации статусов оптовых заказов:</td>
			<td width="60%">
				<input type="text" name="SETTINGS_ORDER_STATUS_NODS_URL"  value="<?=$settings['SETTINGS_ORDER_STATUS_NODS_URL']?>" class="long" />
			</td>
		</tr>
<? foreach($aTabs as $val)
	if($val['CLASS']!='Fire_Settings') {
		$tab->BeginNextTab();
		echo call_user_func_array([$val['CLASS'], 'getSelfOptionsHTML'], []);
	}
?>
<?	$tab->EndTab();?>
<?	$tab->Buttons();?>
	<input type="submit" name="SAVE" class="adm-btn-save"  value="Сохранить" title="Сохранить изменения" />
	<input type="button" onclick="window.document.location = '?lang=<?=LANGUAGE_ID ?>'" value="Отменить" title="Не сохранять изменения" />
</form>
<?$tab->End();?>
<script>
if(window.location.hash) {
	setTimeout(Fire_Settings_tab.SelectTab(window.location.hash.substr(1)), 1000);
}
BX.bindDelegate(
	document,
	'click',
	{className: 'adm-detail-tab'},
	function() {
		var SelectedTab = BX.findChild(document, {className: 'adm-detail-content', attr:{'style':'display: block;'}}, true );
		var form = BX.findChild(document, {className: 'fire-option'}, true);
		if(SelectedTab && SelectedTab.id && form)
			form.action = '#'+SelectedTab.id;
	}
);
</script>
<?endif;?>