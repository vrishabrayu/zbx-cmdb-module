<?php
namespace Modules\EnterpriseCmdb;

use Zabbix\Core\CModule;
use APP;
use CMenuItem;

class Module extends CModule {

	public function init(): void {
		// Ensure data directory and asset store exist
		$data_dir = __DIR__ . '/data';
		if (!is_dir($data_dir)) {
			mkdir($data_dir, 0755, true);
		}
		$asset_file = $data_dir . '/assets.json';
		if (!file_exists($asset_file)) {
			file_put_contents($asset_file, json_encode([]));
		}

		APP::Component()->get('menu.main')
			->findOrAdd(_('Inventory'))
				->getSubmenu()
				->add((new CMenuItem(_('Enterprise CMDB')))
					->setAction('cmdb.list')
				);
	}
}
