<?php

require_once __DIR__ . '/../lib/ItemFinder.php';
require_once __DIR__ . '/../lib/AssetStore.php';
require_once __DIR__ . '/../lib/HostDataBuilder.php';

use Modules\EnterpriseCmdb\Lib\ItemFinder;
use Modules\EnterpriseCmdb\Lib\AssetStore;
use Modules\EnterpriseCmdb\Lib\HostDataBuilder;

class CControllerCmdbExport extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		return $this->validateInput([
			'search'          => 'string',
			'groupid'         => 'id',
			'device_type'     => 'string',
			'warranty_status' => 'string',
			'interface_type'  => 'id',
		]);
	}

	protected function checkPermissions(): bool {
		return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
	}

	protected function doAction(): void {
		// Honor the same filters as the list page when passed on the export URL.
		$search          = (string) $this->getInput('search', '');
		$groupid         = (int) $this->getInput('groupid', 0);
		$device_type     = (string) $this->getInput('device_type', '');
		$warranty_status = (string) $this->getInput('warranty_status', '');
		$iface_type      = (int) $this->getInput('interface_type', 0);

		$host_params = [
			'output'           => ['hostid', 'host', 'name', 'status'],
			'selectHostGroups' => ['name'],
			'selectInterfaces' => ['ip', 'type', 'main', 'available'],
			'selectInventory'  => ['os', 'os_full', 'serialno_a', 'model', 'hardware', 'vendor', 'location', 'install_date', 'asset_tag', 'notes'],
			'monitored_hosts'  => true,
			'sortfield'        => 'name',
		];
		if ($groupid) {
			$host_params['groupids'] = [$groupid];
		}
		if ($search !== '') {
			$host_params['search'] = ['host' => $search, 'name' => $search];
			$host_params['searchByAny'] = true;
			$host_params['searchWildcardsEnabled'] = true;
		}

		$hosts = API::Host()->get($host_params);
		if (!is_array($hosts)) {
			$hosts = [];
		}

		$hostIds = array_column($hosts, 'hostid');
		$items   = ItemFinder::batchGetItems($hostIds);
		$assets  = AssetStore::getAll();

		$filters = [
			'device_type'     => $device_type,
			'warranty_status' => $warranty_status,
			'interface_type'  => $iface_type,
		];
		$rows = HostDataBuilder::buildAll($hosts, $items, $assets, $filters);

		$filename = 'cmdb_export_' . date('Ymd_His') . '.csv';
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="' . $filename . '"');

		$out = fopen('php://output', 'w');
		fputcsv($out, [
			'Host Name', 'System Name', 'IP Address', 'Group', 'OS',
			'Device Type', 'Manufacturer', 'Model', 'Serial Number', 'Asset Tag',
			'Interface Type', 'CPU Count', 'CPU Usage', 'Memory Total', 'Memory Usage',
			'Uptime', 'Status', 'Warranty End', 'Warranty Status',
			'Datacenter', 'Location', 'Rack', 'Rack Unit',
			'Purchase Date', 'EOL Date', 'Notes',
		]);

		foreach ($rows as $row) {
			fputcsv($out, [
				$row['hostname'],
				$row['sys_name'],
				$row['ip'],
				$row['group'],
				$row['os'],
				$row['device_type'],
				$row['manufacturer'],
				$row['model'],
				$row['serial'],
				$row['asset_tag'],
				$row['iface_type'],
				$row['cpu_count'],
				$row['cpu_usage'],
				$row['mem_total'],
				$row['mem_usage'],
				$row['uptime'],
				$row['availability']['label'] ?? 'Unknown',
				$row['warranty_end'] ?? '-',
				$row['warranty']['label'] ?? 'Not Set',
				$row['datacenter'],
				$row['location'],
				$row['rack'],
				$row['rack_unit'],
				$row['purchase_date'],
				$row['eol_date'],
				$row['notes'],
			]);
		}

		fclose($out);
		exit;
	}
}
