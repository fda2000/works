<?php
if (IsModuleInstalled('fire.main')) {
	CModule::IncludeModule('fire.main');
	$kernelPath = is_dir($_SERVER['DOCUMENT_ROOT'] . $updater->kernelPath) ? $_SERVER['DOCUMENT_ROOT'] . $updater->kernelPath : $_SERVER['DOCUMENT_ROOT'] . '/bitrix';

	function recursiveCheckMD5($dir, $templ)
	{
		if (!is_dir($dir))
			return;
		$odir = opendir($dir);
		while (($file = readdir($odir)) !== FALSE) {
			if ($file == '.' || $file == '..')
				continue;

			if (is_dir($dir . DIRECTORY_SEPARATOR . $file))
				recursiveCheckMD5($dir . DIRECTORY_SEPARATOR . $file, $templ . DIRECTORY_SEPARATOR . $file);
			elseif (is_file($dir . DIRECTORY_SEPARATOR . $file . '.md5')) {
				if (
					is_file(($templ . DIRECTORY_SEPARATOR . $file)) &&
					is_file($dir . DIRECTORY_SEPARATOR . $file . '.new.md5') &&
					file_get_contents($dir . DIRECTORY_SEPARATOR . $file . '.new.md5') == md5(@file_get_contents($templ . DIRECTORY_SEPARATOR . $file))
				) {
					@unlink($templ . DIRECTORY_SEPARATOR . $file . '.new');
				} elseif (
					is_file(($templ . DIRECTORY_SEPARATOR . $file)) &&
					file_get_contents($dir . DIRECTORY_SEPARATOR . $file . '.md5') != md5(@file_get_contents($templ . DIRECTORY_SEPARATOR . $file)) &&
					md5(@file_get_contents($dir . DIRECTORY_SEPARATOR . $file)) != md5(@file_get_contents($templ . DIRECTORY_SEPARATOR . $file))
				)
					rename($dir . DIRECTORY_SEPARATOR . $file, $dir . DIRECTORY_SEPARATOR . $file . '.new');

				@unlink($dir . DIRECTORY_SEPARATOR . $file . '.md5');
				@unlink($dir . DIRECTORY_SEPARATOR . $file . '.new.md5');
			}
		}
		closedir($odir);
	}

	$prefix = [];
	$templates = ['.default', 'fire', 'fire_index', 'fire_pink', 'papa', 'underwear', 'opt'];
	foreach ($templates as $dir)
		if (is_dir($kernelPath . '/templates/' . $dir) || $dir == '.default') {
			recursiveCheckMD5(dirname(__FILE__) . '/install/templates/' . $dir, $kernelPath . '/templates/' . $dir);
			$updater->CopyFiles('install/templates/' . $dir, 'templates/' . $dir . '/');

			if ($dir == 'underwear')
				$prefix[] = 'fire';
			if ($dir == 'fire_pink')
				$prefix[] = 'pink';
			else
				$prefix[] = $dir;
		}

	$path = dirname(__FILE__) . '/install/components/fire';
	if (is_dir($path)) {//remove not exist templates
		if ($handle = @opendir($path)) {
			while (($file = readdir($handle)) !== false) {
				if ($file == '.' || $file == '..')
					continue;
				$path1 = $path . '/' . $file . '/templates';
				if ($handle1 = @opendir($path1)) {
					while (($file1 = readdir($handle1)) !== false) {
						if ($file1 == '.' || $file1 == '..')
							continue;
						if (is_dir($path1 . '/' . $file1) && $file1 != '.default' && array_search($file1, $prefix) === false && array_search(mb_substr($file1, 0, mb_strpos($file1, '_')), $prefix) === false && !is_dir($kernelPath . '/components/fire/' . $file . '/templates/' . $file1))
							\Bitrix\Main\IO\Directory::deleteDirectory($path1 . '/' . $file1);
					}
					@closedir($handle1);
				}
			}
			@closedir($handle);
		}
		$updater->CopyFiles('install/components', 'components/');
	}

	if (is_dir(dirname(__FILE__) . '/install/php_interface'))
		$updater->CopyFiles('install/php_interface', 'php_interface/');

	if (is_dir(dirname(__FILE__) . '/install/cron'))
		$updater->CopyFiles('install/cron', '/cron/');
	if (is_dir(dirname(__FILE__) . '/install/img'))
		$updater->CopyFiles('install/img', '/img/');

	//\Bitrix\Main\EventManager::getInstance()->registerEventHandler('main', 'OnAdminSaleOrderViewDraggable', 'fire.main', 'Fire_CustomEvents', 'OrderViewInit');

	BXClearCache(true, '/');

	/*
	IncludeModuleLangFile(__DIR__.'/updater.php');
	CAdminNotify::Add(array(
		'MESSAGE' => GetMessage('FIRE_NOTIFY_1_11_2'),
		'TAG' => 'FIRE_NOTIFY_1_11_2',
		'MODULE_ID' => 'fire.main',
		'ENABLE_CLOSE' => 'Y'
	));
	//*/
}
