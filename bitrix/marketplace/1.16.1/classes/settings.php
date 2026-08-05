<?
class Fire_Settings {
	const MODULE_ID = 'fire.main';
	const PREFIX = 'FIRE_';

	static $Settings = array(
		'DESIGN' => array('type'=>'TEXT'),
		'IMPORT_PRICE_TYPE' => array('type'=>'TEXT', 'def'=>1),
		'IMPORT_NACENKA' => array('type'=>'ARRAY'),
		'IMPORT_PRICE_TYPE' => array('type'=>'TEXT'),
		'IMPORT_PRODUCT_RRC' => array('type'=>'CHECKBOX', 'def'=>'Y'),
		'IMPORT_IMG_WIDTH' => array('type'=>'TEXT'),
		'IMPORT_IMG_HEIGHT' => array('type'=>'TEXT'),
		'SELFSHOP_TYPE' => array('type'=>'TEXT'),
		'SHOP_IMG_STATUS_REG' => array('type'=>'TEXT'),
		'SHOP_IMG_STATUS' => array('type'=>'TEXT'),
		'P5S_API_KEY' => array('type'=>'TEXT'),
		'P5S_NO_ERROR_MAIL' => array('type'=>'CHECKBOX'),
		'P5S_PACK_DELIVERY' => array('type'=>'ARRAY'),
		'P5S_SHIPPING_MODEL' => array('type'=>'TEXT', 'def'=>'DS'),
		'MONITORING_EMAIL' => array('type'=>'TEXT'),
		'MONITORING_DISK' => array('type'=>'CHECKBOX'),
		'MONITORING_CATALOG' => array('type'=>'CHECKBOX'),
		'MONITORING_ORDERS' => array('type'=>'CHECKBOX'),
		'IMPORT_PRODUCT_DEACTIVATE' => array('type'=>'CHECKBOX'),
		'IMPORT_PRODUCT_SHIPPING24' => array('type'=>'CHECKBOX'),
		'IMPORT_PRODUCT_MINSTOCK' => array('type'=>'TEXT', 'def'=>0),
		'SETTINGS_SITE' => array('type'=>'TEXT', 'def'=>'s1'),
		'SETTINGS_PRODUCTS_IBLOCK' => array('type'=>'TEXT', 'def'=>5),
		'SETTINGS_OFFERS_IBLOCK' => array('type'=>'TEXT', 'def'=>6),
		'SETTINGS_SHOP_IBLOCK' => array('type'=>'TEXT', 'def'=>14),
		'SETTINGS_COLOR_HL' => array('type'=>'TEXT', 'def'=>3),
		'SETTINGS_VENDOR_IBLOCK' => array('type'=>'TEXT', 'def'=>4),
		'SETTINGS_PRODUCTS_IMPORT_PROFILE' => array('type'=>'TEXT', 'def'=>11),
		'SETTINGS_OFFERS_IMPORT_PROFILE' => array('type'=>'TEXT', 'def'=>4),
		'SETTINGS_VENDOR_IMPORT_PROFILE' => array('type'=>'TEXT', 'def'=>2),
		'SETTINGS_PRODUCTS_IMPORT_URL' => array('type'=>'TEXT', 'def'=>'...'),
		'SETTINGS_CATALOG_IMPORT_URL' => array('type'=>'TEXT', 'def'=>''),
		'SETTINGS_OFFERS_IMPORT_STOCK_URL' => array('type'=>'TEXT', 'def'=>'.../bitrix_stock.csv'),
		'SETTINGS_OFFERS_IMPORT_PRICE_URL' => array('type'=>'TEXT', 'def'=>'.../bitrix_stock_price.csv'),
		'SETTINGS_VENDOR_IMPORT_URL' => array('type'=>'TEXT', 'def'=>'.../bitrix_vendors.csv'),
		'SETTINGS_COLOR_IMPORT_URL' => array('type'=>'TEXT', 'def'=>'.../bitrix_colors.csv'),
		'SETTINGS_ORDER_EXPORT_URL' => array('type'=>'TEXT', 'def'=>'.../ds_order.php'),
		'SETTINGS_ORDER_STATUS_URL' => array('type'=>'TEXT', 'def'=>'.../ds_get_order_data.php'),
		'SETTINGS_ORDER_EXPORT_NODS_URL' => array('type'=>'TEXT', 'def'=>'.../order.php'),
		'SETTINGS_ORDER_STATUS_NODS_URL' => array('type'=>'TEXT', 'def'=>'')
	);
	
	static $Classes = array();//array('Fire_Mailchimp');
	static $SettingsArr = array();

	public static function getImgStatuses(): array
	{
		$ret = [];
		$key = 'IMG_STATUS_';
		$i = 0;
		while ($name = getMessage($key . $i)) {
			$ret[$i++] = $name;
		}
		$ret[''] = getMessage($key);

		return $ret;
	}

	public static function addFilterImgStatus(array $filter, string $prop): array
	{
		global $USER;
		$shop_img_status = self::getOption($USER->IsAuthorized() ? 'SHOP_IMG_STATUS_REG' : 'SHOP_IMG_STATUS');
		if (is_numeric($shop_img_status)) {
			$filter['<=' . $prop] = $shop_img_status;
		}
		return $filter;
	}

	public static function addArrSettings($arr) {
		self::$SettingsArr = array_merge(self::$SettingsArr, $arr);
	}

	public static function getTab() {
		return [
			'TAB'=>'Общие настройки', 
			'DIV'=>'settings', 
			'TITLE' => 'Настройки модуля',
		];
	}
	
	public static function getArrSettings() {
		if(!self::$SettingsArr) {
			self::addArrSettings(self::$Settings);
			foreach(self::$Classes as $class)
				self::addArrSettings(call_user_func_array(array($class, "getArrSettings"), array()));
		}
		return self::$SettingsArr;
	}
	
	public static function getOptionHTML($ID, $Item) {
		$value = $Item['VALUE']? $Item['VALUE'] : self::getOption($ID);
		if($Item['ADD'])
			$Item['ADD'] = ' '.$Item['ADD'];
		$options = '';
		if(is_array($Item['VALUES']))
			foreach($Item['VALUES'] as $key=>$val)
				$options.= '<option value="'.htmlspecialcharsEx($key).'"'.($key==$value? ' selected' : '').'>'.htmlspecialcharsEx($val).'</option>';
		
		switch($Item['type']) {
			case 'TEXT':
				return '<input type="text" name="'.$ID.'"  value="'.htmlspecialcharsEx($value).'"'.$Item['ADD'].' />';
			case 'CHECKBOX':
				return '<input type="checkbox" name="'.$ID.'" value="Y"'.($value== "Y"? ' checked' : '').$Item['ADD'].' />';
			case 'TEXTAREA':
				return '<textarea name="'.$ID.'"'.$Item['ADD'].'>'.htmlspecialcharsEx($value).'</textarea>';
			case 'SELECT':
				return '<select name="'.$ID.'"'.$Item['ADD'].'>'.$options.'"</select>';
			case 'INFO':
				return $Item['VALUES'];
			case 'BUTTON':
				return '<input type="submit" name="'.$ID.'" value="'.$Item['VALUES'].'" />';
		}
		return false;
	}
	
	public static function getOptionsHTML($Items) {
		$ret = '';
		foreach($Items as $ID=>$Item) {
			$html = self::getOptionHTML($ID, $Item);
			if($html!==false)
				if($Item['type']=='INFO' && !$html)
					$ret.= '<tr class="heading"><td colspan="2">'.$Item['TITLE'].'</td></tr>';
				else
					$ret.= '<tr><td width="40%" class="adm-detail-content-cell-l" style="white-space:nowrap">'.$Item['TITLE'].($Item['TITLE']? ':' : '').'</td><td width="60%">'.$html.'</td></tr>';
		}
		return $ret;
	}
	
	public static function getSelfOptionsHTML() {
		return self::getOptionsHTML(self::$Settings);
	}
	
	public static function GetSettings() {
		$arSettings = array();
		foreach(self::$Settings as $code=>$value){
			$type = $value['type'];
			$value = self::getOption($code);
			if ($type == 'TEXT')
				$value = htmlspecialcharsbx($value);
			
			$arSettings[$code] = $value;

		}
		return $arSettings;
	}
	public static function SetSettings($arFields) {
		if(!isset($_POST['SAVE']))
			return;
		$clear_comp = false;
		foreach(self::getArrSettings() as $code=>$value) {
			$value = $value['type'];
			$val = NULL;
			if ($value == 'CHECKBOX' && !isset($arFields[$code]))
				$val = '';
			elseif (isset($arFields[$code]))
			{
				if ($code == 'IMPORT_NACENKA') {
					$val = array();
					if(is_array($arr=$arFields[$code]))
						foreach($arr['price'] as $key=>$price) {
							if((string)(float)$price!==$price)
								continue;
							$price = (float)$price;
							$v = $arr['discount'][$key];
							if ($v < 0)
							{
								CAdminMessage::ShowMessage("Наценка/скидка не может быть отрицательной");
								continue 2;
							}
							if($arr['sign'][$key]<0)
								$v = -$v;
							if ($v < -100)
							{
								CAdminMessage::ShowMessage("Скидка не может быть больше 100%");
								continue 2;
							}
							$val[(string)$price] = $v;
						}
					ksort($val, SORT_NUMERIC);
				} elseif($code=='SHOP_IMG_STATUS' || $code=='SHOP_IMG_STATUS_REG') {
					$val = $arFields[$code];
					if(self::getOption($code) !== $val)
						$clear_comp = true;
				} elseif(mb_substr($code, -4, 4)=='_URL') {
					$val = $arFields[$code];
					if($val && mb_strpos($val, 'http://')!==0 && mb_strpos($val, 'https://')!==0) {
						CAdminMessage::ShowMessage("В поле фида должен быть URL!");
						continue;
					}
				} else
					$val = $arFields[$code];
			}
			if ($value == 'ARRAY') {
				$val = serialize($val);
			}
			
			self::SetSetting($code, $val);
		}
		
		if($clear_comp)
			BXClearCache(true, '/'.self::getOption('SETTINGS_SITE').'/fire/');
	}
	
	public static function SetSetting($name, $val) {
		$Settings = self::getArrSettings();
		if(!$value=$Settings[$name])
			return false;
		if($value['type']=='INFO')
			return false;
		return self::setModuleSetting($name, $val);
	}
	
	public static function setModuleSetting($name, $val) {
		return COption::SetOptionString(self::MODULE_ID, $name, $val, SITE_ID);
	}
	
	public static function getOption($name) {
		if(mb_strpos($name, self::PREFIX)===0)
			$name = mb_substr($name, mb_strlen(self::PREFIX));
		$Settings = self::getArrSettings();
		if(!$value=$Settings[$name])
			return false;
		$ret = self::getModuleSetting($name, isset($value['def'])? $value['def'] : '');
		if($value['type']=='ARRAY') {
			$ret = unserialize($ret);
			return $ret? $ret : array();
		}
		return $ret;
	}
	
	public static function getModuleSetting($name, $def='') {
		return COption::GetOptionString(self::MODULE_ID, $name, $def, SITE_ID);
	}
	
	public static function getSiteType() {
		if(defined('FIRE_SITE_TYPE'))
			return FIRE_SITE_TYPE;
		
		$type = NULL;
		if(defined('SITE_TEMPLATE_ID')) {
			if(mb_strpos(SITE_TEMPLATE_ID, 'underwear')!==false)
				$type = 'underwear';
			if(mb_strpos(SITE_TEMPLATE_ID, 'papa')!==false)
				$type = 'papa';
			if(mb_strpos(SITE_TEMPLATE_ID, 'fire')!==false)
				$type = 'fire';
			if(mb_strpos(SITE_TEMPLATE_ID, 'opt')!==false)
				$type = 'opt';
		}/*
		if(!$type) {
			$site = CSite::GetByID(self::getOption('SETTINGS_SITE'));
			$site = $site->Fetch();
			if(mb_strpos($site['NAME'], 'underwear')!==false)
				$type = 'underwear';
			if(mb_strpos($site['NAME'], 'papa')!==false)
				$type = 'papa';
			if(mb_strpos($site['NAME'], 'fire')!==false)
				$type = 'fire';
			if(mb_strpos($site['NAME'], 'opt')!==false)
				$type = 'opt';
		}*/
		
		if(!$type)
			$type = 'fire';
		
		define('FIRE_SITE_TYPE', $type);
		return $type;
	}
}
?>