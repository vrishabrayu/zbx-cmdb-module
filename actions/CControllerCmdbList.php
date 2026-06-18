<?php

require_once __DIR__ . '/../lib/ItemFinder.php';
require_once __DIR__ . '/../lib/AssetStore.php';
require_once __DIR__ . '/../lib/HostDataBuilder.php';

use Modules\EnterpriseCmdb\Lib\ItemFinder;
use Modules\EnterpriseCmdb\Lib\AssetStore;
use Modules\EnterpriseCmdb\Lib\HostDataBuilder;

class CControllerCmdbList extends CController {

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
			'page'            => 'int32',
		]);
	}

	protected function checkPermissions(): bool {
		return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
	}

	protected function doAction(): void {
		$search          = (string) $this->getInput('search', '');
		$groupid         = (int) $this->getInput('groupid', 0);
		$device_type     = (string) $this->getInput('device_type', '');
		$warranty_status = (string) $this->getInput('warranty_status', '');
		$iface_type      = (int) $this->getInput('interface_type', 0);
		$page            = max(1, (int) $this->getInput('page', 1));
		$page_size       = 50;

		$groups = API::HostGroup()->get([
			'output'     => ['groupid', 'name'],
			'with_hosts' => true,
			'sortfield'  => 'name',
		]);
		if (!is_array($groups)) {
			$groups = [];
		}

		$host_params = [
			'output'           => ['hostid', 'host', 'name', 'status', 'maintenance_status'],
			'selectHostGroups' => ['groupid', 'name'],
			'selectInterfaces' => ['interfaceid', 'ip', 'dns', 'type', 'main', 'available'],
			'selectInventory'  => [
				'os', 'os_full', 'type', 'hardware', 'serialno_a', 'model', 'location',
				'location_lat', 'location_lon', 'vendor', 'contract_number', 'install_date',
				'asset_tag', 'notes',
			],
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

		// Build all rows once; interface filter only at build time.
		$iface_filter = ['interface_type' => $iface_type];
		$all_rows     = HostDataBuilder::buildAll($hosts, $items, $assets, $iface_filter);

		// Table/stats use device + warranty filters; charts/badges use full row set.
		$table_filters = [
			'device_type'     => $device_type,
			'warranty_status' => $warranty_status,
		];
		$filtered_rows = HostDataBuilder::applyFilters($all_rows, $table_filters);

		$total = count($filtered_rows);
		$up    = count(array_filter($filtered_rows, fn($r) => ($r['availability']['status'] ?? '') === 'up'));
		$down  = count(array_filter($filtered_rows, fn($r) => ($r['availability']['status'] ?? '') === 'down'));
		$exp_warranty = count(array_filter($filtered_rows, fn($r) => ($r['warranty']['status'] ?? '') === 'expired'));
		$exp_soon     = count(array_filter($filtered_rows, fn($r) => ($r['warranty']['status'] ?? '') === 'expiring'));

		$type_counts = HostDataBuilder::countByField($all_rows, 'device_type');
		$os_counts   = HostDataBuilder::buildOsCounts($all_rows);

		$total_pages = max(1, (int) ceil($total / $page_size));
		$page        = min($page, $total_pages);
		$paged_data  = array_slice($filtered_rows, ($page - 1) * $page_size, $page_size);

		$this->setResponse(new CControllerResponseData([
			'hosts'           => $paged_data,
			'all_hosts'       => $filtered_rows,
			'groups'          => $groups,
			'search'          => $search,
			'groupid'         => $groupid,
			'device_type'     => $device_type,
			'warranty_status' => $warranty_status,
			'interface_type'  => $iface_type,
			'page'            => $page,
			'page_size'       => $page_size,
			'total'           => $total,
			'total_pages'     => $total_pages,
			'stats'           => compact('total', 'up', 'down', 'exp_warranty', 'exp_soon'),
			'type_counts'     => $type_counts,
			'os_counts'       => $os_counts,
		]));
	}
}
