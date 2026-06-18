<?php declare(strict_types = 1);
/**
 * @var CView $this
 * @var array $data
 */

$hostid = (string) ($data['hostid'] ?? '');
$host   = $data['host'] ?? null;
$asset  = $data['asset'] ?? [];

/** Never pass null to htmlspecialchars (PHP 8.1+ deprecation / 8.4 fatal). */
$h = static function (?string $value): string {
	return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
};

$name = $host ? $h($host['name'] ?? '') : 'Host #' . $hostid;

$fs  = 'width:100%;padding:6px 10px;border:1px solid #ccc;border-radius:3px;font-size:13px;box-sizing:border-box;height:32px;';
$ls  = 'display:block;font-size:12px;font-weight:bold;color:#444;margin-bottom:4px;';
$rs  = 'margin-bottom:14px;';
$sec = 'background:#f8f9fc;border:1px solid #dde3ee;border-radius:6px;padding:16px 20px;margin-bottom:20px;';
$sh  = 'font-size:14px;font-weight:bold;color:#1f4068;margin:0 0 14px 0;border-bottom:2px solid #1f4068;padding-bottom:6px;';
$grid= 'display:grid;grid-template-columns:1fr 1fr;gap:14px;';

$device_types = ['Server','Linux','Windows','Router','Switch','Firewall','Hypervisor','Storage','Printer','UPS','Wireless','Unknown'];

$dev_sel = (new CTag('select', true))->setAttribute('name', 'device_type')->setAttribute('style', $fs);
foreach ($device_types as $t) {
	$o = (new CTag('option', true, $t))->setAttribute('value', $t);
	if (($asset['device_type'] ?? '') === $t) {
		$o->setAttribute('selected', 'selected');
	}
	$dev_sel->addItem($o);
}

$form = (new CTag('form', true))
	->setAttribute('method', 'post')
	->setAttribute('action', 'zabbix.php?action=cmdb.asset.save')
	->setAttribute('style', 'max-width:800px;')
	->addItem((new CTag('input', false))->setAttribute('type', 'hidden')->setAttribute('name', 'hostid')->setAttribute('value', $hostid));

if (!$host) {
	$form->addItem((new CDiv('Warning: host record was not found in Zabbix. Saving will still update CMDB asset metadata for host ID ' . $h($hostid) . '.'))
		->setAttribute('style', 'background:#fff3cd;border:1px solid #ffc107;border-radius:4px;padding:10px 14px;margin-bottom:16px;font-size:13px;color:#856404;'));
}

// Section: Device Identity — asset keys guaranteed by controller defaults.
$form->addItem((new CDiv([
	(new CTag('h3', true, '🖥 Device Identity'))->setAttribute('style', $sh),
	(new CDiv([
		(new CDiv([(new CTag('label', true, 'Device Type'))->setAttribute('style', $ls), $dev_sel]))->setAttribute('style', $rs),
		(new CDiv([(new CTag('label', true, 'Manufacturer'))->setAttribute('style', $ls), (new CTag('input', false))->setAttribute('type', 'text')->setAttribute('name', 'manufacturer')->setAttribute('value', $h($asset['manufacturer'] ?? ''))->setAttribute('placeholder', 'e.g. Cisco, Dell, HP')->setAttribute('style', $fs)]))->setAttribute('style', $rs),
		(new CDiv([(new CTag('label', true, 'Model'))->setAttribute('style', $ls), (new CTag('input', false))->setAttribute('type', 'text')->setAttribute('name', 'model')->setAttribute('value', $h($asset['model'] ?? ''))->setAttribute('placeholder', 'e.g. Catalyst 2960, PowerEdge R730')->setAttribute('style', $fs)]))->setAttribute('style', $rs),
		(new CDiv([(new CTag('label', true, 'Serial Number'))->setAttribute('style', $ls), (new CTag('input', false))->setAttribute('type', 'text')->setAttribute('name', 'serial')->setAttribute('value', $h($asset['serial'] ?? ''))->setAttribute('placeholder', 'e.g. FCZ2042X0PV')->setAttribute('style', $fs)]))->setAttribute('style', $rs),
		(new CDiv([(new CTag('label', true, 'Asset Tag'))->setAttribute('style', $ls), (new CTag('input', false))->setAttribute('type', 'text')->setAttribute('name', 'asset_tag')->setAttribute('value', $h($asset['asset_tag'] ?? ''))->setAttribute('placeholder', 'e.g. IT-2024-001')->setAttribute('style', $fs)]))->setAttribute('style', $rs),
	]))->setAttribute('style', $grid),
]))->setAttribute('style', $sec));

// Section: Lifecycle
$form->addItem((new CDiv([
	(new CTag('h3', true, '📅 Lifecycle & Warranty'))->setAttribute('style', $sh),
	(new CDiv([
		(new CDiv([(new CTag('label', true, 'Purchase Date'))->setAttribute('style', $ls), (new CTag('input', false))->setAttribute('type', 'date')->setAttribute('name', 'purchase_date')->setAttribute('value', $h($asset['purchase_date'] ?? ''))->setAttribute('style', $fs)]))->setAttribute('style', $rs),
		(new CDiv([(new CTag('label', true, 'Warranty End Date'))->setAttribute('style', $ls), (new CTag('input', false))->setAttribute('type', 'date')->setAttribute('name', 'warranty_end')->setAttribute('value', $h($asset['warranty_end'] ?? ''))->setAttribute('style', $fs)]))->setAttribute('style', $rs),
		(new CDiv([(new CTag('label', true, 'End of Life (EOL) Date'))->setAttribute('style', $ls), (new CTag('input', false))->setAttribute('type', 'date')->setAttribute('name', 'eol_date')->setAttribute('value', $h($asset['eol_date'] ?? ''))->setAttribute('style', $fs)]))->setAttribute('style', $rs),
	]))->setAttribute('style', $grid),
]))->setAttribute('style', $sec));

// Section: Location
$form->addItem((new CDiv([
	(new CTag('h3', true, '📍 Location & Rack'))->setAttribute('style', $sh),
	(new CDiv([
		(new CDiv([(new CTag('label', true, 'Datacenter'))->setAttribute('style', $ls), (new CTag('input', false))->setAttribute('type', 'text')->setAttribute('name', 'datacenter')->setAttribute('value', $h($asset['datacenter'] ?? ''))->setAttribute('placeholder', 'e.g. DC-Mumbai-1')->setAttribute('style', $fs)]))->setAttribute('style', $rs),
		(new CDiv([(new CTag('label', true, 'Location / Room'))->setAttribute('style', $ls), (new CTag('input', false))->setAttribute('type', 'text')->setAttribute('name', 'location')->setAttribute('value', $h($asset['location'] ?? ''))->setAttribute('placeholder', 'e.g. Server Room A')->setAttribute('style', $fs)]))->setAttribute('style', $rs),
		(new CDiv([(new CTag('label', true, 'Rack Name/ID'))->setAttribute('style', $ls), (new CTag('input', false))->setAttribute('type', 'text')->setAttribute('name', 'rack')->setAttribute('value', $h($asset['rack'] ?? ''))->setAttribute('placeholder', 'e.g. Rack-A01')->setAttribute('style', $fs)]))->setAttribute('style', $rs),
		(new CDiv([(new CTag('label', true, 'Rack Unit (U position)'))->setAttribute('style', $ls), (new CTag('input', false))->setAttribute('type', 'text')->setAttribute('name', 'rack_unit')->setAttribute('value', $h($asset['rack_unit'] ?? ''))->setAttribute('placeholder', 'e.g. U12')->setAttribute('style', $fs)]))->setAttribute('style', $rs),
	]))->setAttribute('style', $grid),
]))->setAttribute('style', $sec));

// Section: Notes
$form->addItem((new CDiv([
	(new CTag('h3', true, '📝 Notes'))->setAttribute('style', $sh),
	(new CTag('textarea', true, $h($asset['notes'] ?? '')))->setAttribute('name', 'notes')->setAttribute('style', 'width:100%;height:80px;border:1px solid #ccc;border-radius:3px;padding:8px;font-size:13px;resize:vertical;box-sizing:border-box;')->setAttribute('placeholder', 'Additional notes about this asset...'),
]))->setAttribute('style', $sec));

// Buttons
$form->addItem((new CDiv([
	(new CTag('button', true, '💾 Save Asset'))->setAttribute('type', 'submit')->setAttribute('style', 'padding:8px 20px;background:#1f4068;color:#fff;border:none;border-radius:3px;font-size:13px;cursor:pointer;font-weight:bold;'),
	(new CTag('a', true, 'Cancel'))->setAttribute('href', 'zabbix.php?action=cmdb.list')->setAttribute('style', 'padding:8px 16px;background:#e0e0e0;color:#333;border-radius:3px;font-size:13px;text-decoration:none;margin-left:10px;'),
]))->setAttribute('style', 'margin-top:4px;'));

(new CHtmlPage())
	->setTitle('Edit Asset: ' . $name)
	->addItem($form)
	->show();
