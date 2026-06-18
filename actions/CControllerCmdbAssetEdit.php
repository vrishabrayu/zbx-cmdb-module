<?php

require_once __DIR__ . '/../lib/AssetStore.php';
use Modules\EnterpriseCmdb\Lib\AssetStore;

class CControllerCmdbAssetEdit extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		return $this->validateInput(['hostid' => 'required|id']);
	}

	protected function checkPermissions(): bool {
		return $this->getUserType() >= USER_TYPE_ZABBIX_ADMIN;
	}

	protected function doAction(): void {
		$hostid = (string) $this->getInput('hostid');
		$hosts = API::Host()->get([
			'output'  => ['hostid', 'host', 'name'],
			'hostids' => [$hostid],
		]);
		$host = !empty($hosts) ? $hosts[0] : null;

		// Merge stored asset with empty defaults so every form field key exists.
		$asset = array_merge([
			'device_type'   => '',
			'manufacturer'  => '',
			'model'         => '',
			'serial'        => '',
			'asset_tag'     => '',
			'purchase_date' => '',
			'warranty_end'  => '',
			'eol_date'      => '',
			'datacenter'    => '',
			'location'      => '',
			'rack'          => '',
			'rack_unit'     => '',
			'notes'         => '',
		], AssetStore::get($hostid));

		$this->setResponse(new CControllerResponseData([
			'hostid' => $hostid,
			'host'   => $host,
			'asset'  => $asset,
		]));
	}
}
