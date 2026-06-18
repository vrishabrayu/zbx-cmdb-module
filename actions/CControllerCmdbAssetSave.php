<?php

require_once __DIR__ . '/../lib/AssetStore.php';
use Modules\EnterpriseCmdb\Lib\AssetStore;

class CControllerCmdbAssetSave extends CController {

	protected function init(): void { $this->disableCsrfValidation(); }

	protected function checkInput(): bool {
		return $this->validateInput([
			'hostid'        => 'required|id',
			'device_type'   => 'string',
			'manufacturer'  => 'string',
			'model'         => 'string',
			'serial'        => 'string',
			'asset_tag'     => 'string',
			'purchase_date' => 'string',
			'warranty_end'  => 'string',
			'eol_date'      => 'string',
			'datacenter'    => 'string',
			'location'      => 'string',
			'rack'          => 'string',
			'rack_unit'     => 'string',
			'notes'         => 'string',
		]);
	}

	protected function checkPermissions(): bool {
		return $this->getUserType() >= USER_TYPE_ZABBIX_ADMIN;
	}

	protected function doAction(): void {
		$hostid = $this->getInput('hostid');
		$data = [
			'device_type'   => $this->getInput('device_type', ''),
			'manufacturer'  => $this->getInput('manufacturer', ''),
			'model'         => $this->getInput('model', ''),
			'serial'        => $this->getInput('serial', ''),
			'asset_tag'     => $this->getInput('asset_tag', ''),
			'purchase_date' => $this->getInput('purchase_date', ''),
			'warranty_end'  => $this->getInput('warranty_end', ''),
			'eol_date'      => $this->getInput('eol_date', ''),
			'datacenter'    => $this->getInput('datacenter', ''),
			'location'      => $this->getInput('location', ''),
			'rack'          => $this->getInput('rack', ''),
			'rack_unit'     => $this->getInput('rack_unit', ''),
			'notes'         => $this->getInput('notes', ''),
		];

		AssetStore::save($hostid, $data);

		// Redirect back to list
		$url = (new CUrl('zabbix.php'))->setArgument('action', 'cmdb.list');
		$this->setResponse(new CControllerResponseRedirect($url));
	}
}
