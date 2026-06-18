<?php declare(strict_types = 1);
/**
 * Enterprise CMDB list view.
 *
 * @var CView $this
 * @var array $data
 */

require_once dirname(__DIR__) . '/lib/HostDataBuilder.php';

use Modules\EnterpriseCmdb\Lib\HostDataBuilder;

$hosts           = $data['hosts'] ?? [];
$groups          = $data['groups'] ?? [];
$search          = (string) ($data['search'] ?? '');
$groupid         = (int) ($data['groupid'] ?? 0);
$device_type     = (string) ($data['device_type'] ?? '');
$warranty_status = (string) ($data['warranty_status'] ?? '');
$iface_type      = (int) ($data['interface_type'] ?? 0);
$page            = max(1, (int) ($data['page'] ?? 1));
$page_size       = max(1, (int) ($data['page_size'] ?? 50));
$total_pages     = max(1, (int) ($data['total_pages'] ?? 1));
$total           = (int) ($data['total'] ?? 0);
$stats           = $data['stats'] ?? ['total' => 0, 'up' => 0, 'down' => 0, 'exp_warranty' => 0, 'exp_soon' => 0];
$type_counts     = $data['type_counts'] ?? [];
$os_counts       = $data['os_counts'] ?? [];

/** Never pass null to htmlspecialchars (PHP 8.1+ deprecation / 8.4 fatal). */
$h = static function (?string $value): string {
	return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
};

/**
 * Build list-page URL with optional filter overrides.
 * Fixes badge links: overrides must not be overwritten by current filter state.
 */
$list_url = static function (array $overrides = []) use ($search, $groupid, $device_type, $warranty_status, $iface_type): string {
	$url = (new CUrl('zabbix.php'))->setArgument('action', 'cmdb.list');
	$url->setArgument('search', array_key_exists('search', $overrides) ? $overrides['search'] : $search);
	$url->setArgument('groupid', array_key_exists('groupid', $overrides) ? $overrides['groupid'] : $groupid);
	$url->setArgument('device_type', array_key_exists('device_type', $overrides) ? $overrides['device_type'] : $device_type);
	$url->setArgument('warranty_status', array_key_exists('warranty_status', $overrides) ? $overrides['warranty_status'] : $warranty_status);
	$url->setArgument('interface_type', array_key_exists('interface_type', $overrides) ? $overrides['interface_type'] : $iface_type);
	if (array_key_exists('page', $overrides)) {
		$url->setArgument('page', $overrides['page']);
	}
	return $url->getUrl();
};

// ── Filter Form ──
$group_sel = (new CTag('select', true))
	->setAttribute('name', 'groupid')
	->setAttribute('style', 'height:30px;border:1px solid #c8d0e0;border-radius:3px;padding:4px 8px;font-size:13px;min-width:160px;');
$opt = (new CTag('option', true, '-- All Groups --'))->setAttribute('value', '0');
if (!$groupid) {
	$opt->setAttribute('selected', 'selected');
}
$group_sel->addItem($opt);
foreach ($groups as $g) {
	$o = (new CTag('option', true, $h($g['name'] ?? '')))->setAttribute('value', (string) ($g['groupid'] ?? '0'));
	if ($groupid == ($g['groupid'] ?? 0)) {
		$o->setAttribute('selected', 'selected');
	}
	$group_sel->addItem($o);
}

$type_sel = (new CTag('select', true))
	->setAttribute('name', 'device_type')
	->setAttribute('style', 'height:30px;border:1px solid #c8d0e0;border-radius:3px;padding:4px 8px;font-size:13px;min-width:130px;');
$opt = (new CTag('option', true, '-- All Types --'))->setAttribute('value', '');
if ($device_type === '') {
	$opt->setAttribute('selected', 'selected');
}
$type_sel->addItem($opt);
foreach (['Server','Linux','Windows','Router','Switch','Firewall','Hypervisor','Storage','Printer','UPS','Wireless','Unknown'] as $t) {
	$o = (new CTag('option', true, $t))->setAttribute('value', $t);
	if ($device_type === $t) {
		$o->setAttribute('selected', 'selected');
	}
	$type_sel->addItem($o);
}

$warranty_sel = (new CTag('select', true))
	->setAttribute('name', 'warranty_status')
	->setAttribute('style', 'height:30px;border:1px solid #c8d0e0;border-radius:3px;padding:4px 8px;font-size:13px;min-width:140px;');
foreach (['' => '-- Warranty --', 'valid' => 'Valid', 'expiring' => 'Expiring Soon', 'expired' => 'Expired', 'unknown' => 'Not Set'] as $v => $l) {
	$o = (new CTag('option', true, $l))->setAttribute('value', $v);
	if ($warranty_status === $v) {
		$o->setAttribute('selected', 'selected');
	}
	$warranty_sel->addItem($o);
}

$iface_sel = (new CTag('select', true))
	->setAttribute('name', 'interface_type')
	->setAttribute('style', 'height:30px;border:1px solid #c8d0e0;border-radius:3px;padding:4px 8px;font-size:13px;min-width:120px;');
foreach ([0 => 'All Interfaces', 1 => 'Agent', 2 => 'SNMP', 3 => 'IPMI', 4 => 'JMX'] as $v => $l) {
	$o = (new CTag('option', true, $l))->setAttribute('value', (string) $v);
	if ($iface_type == $v) {
		$o->setAttribute('selected', 'selected');
	}
	$iface_sel->addItem($o);
}

$filter_form = (new CTag('form', true))
	->setAttribute('method', 'get')
	->setAttribute('action', 'zabbix.php')
	->setAttribute('style', 'background:#f5f7fa;border:1px solid #dde3ee;border-radius:6px;padding:12px 16px;margin-bottom:16px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;')
	->addItem((new CTag('input', false))->setAttribute('type', 'hidden')->setAttribute('name', 'action')->setAttribute('value', 'cmdb.list'))
	->addItem((new CTag('input', false))->setAttribute('type', 'text')->setAttribute('name', 'search')->setAttribute('value', $h($search))->setAttribute('placeholder', 'Search host, IP...')->setAttribute('style', 'height:30px;border:1px solid #c8d0e0;border-radius:3px;padding:4px 10px;font-size:13px;min-width:180px;'))
	->addItem($group_sel)
	->addItem($type_sel)
	->addItem($warranty_sel)
	->addItem($iface_sel)
	->addItem((new CTag('button', true, 'Apply'))->setAttribute('type', 'submit')->setAttribute('style', 'height:30px;background:#1f4068;color:#fff;border:none;border-radius:3px;padding:0 16px;cursor:pointer;font-size:13px;'))
	->addItem((new CTag('a', true, 'Reset'))->setAttribute('href', 'zabbix.php?action=cmdb.list')->setAttribute('style', 'height:30px;line-height:30px;display:inline-block;background:#e0e0e0;color:#333;border-radius:3px;padding:0 12px;font-size:13px;text-decoration:none;'));

// ── Summary Cards ──
$cs = 'background:#fff;border:1px solid #d0d5e0;border-radius:6px;padding:12px 18px;min-width:100px;box-shadow:0 1px 3px rgba(0,0,0,.05);';
$summary = (new CDiv([
	(new CDiv([(new CDiv((string) ($stats['total'] ?? 0)))->setAttribute('style', 'font-size:22px;font-weight:bold;color:#1f4068'), (new CDiv('Total Assets'))->setAttribute('style', 'font-size:11px;color:#888;margin-top:2px;')]))->setAttribute('style', $cs),
	(new CDiv([(new CDiv((string) ($stats['up'] ?? 0)))->setAttribute('style', 'font-size:22px;font-weight:bold;color:#27ae60'), (new CDiv('Online'))->setAttribute('style', 'font-size:11px;color:#888;margin-top:2px;')]))->setAttribute('style', $cs),
	(new CDiv([(new CDiv((string) ($stats['down'] ?? 0)))->setAttribute('style', 'font-size:22px;font-weight:bold;color:#e74c3c'), (new CDiv('Offline'))->setAttribute('style', 'font-size:11px;color:#888;margin-top:2px;')]))->setAttribute('style', $cs),
	(new CDiv([(new CDiv((string) ($stats['exp_warranty'] ?? 0)))->setAttribute('style', 'font-size:22px;font-weight:bold;color:#e74c3c'), (new CDiv('Warranty Expired'))->setAttribute('style', 'font-size:11px;color:#888;margin-top:2px;')]))->setAttribute('style', $cs),
	(new CDiv([(new CDiv((string) ($stats['exp_soon'] ?? 0)))->setAttribute('style', 'font-size:22px;font-weight:bold;color:#e67e22'), (new CDiv('Expiring Soon'))->setAttribute('style', 'font-size:11px;color:#888;margin-top:2px;')]))->setAttribute('style', $cs),
]))->setAttribute('style', 'display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap;');

// ── Pie Charts (SVG, no JavaScript required) ──
$build_pie_panel = static function (string $title, array $counts) use ($h): CDiv {
	if (empty($counts)) {
		return (new CDiv([
			(new CDiv($title))->setAttribute('style', 'font-size:13px;font-weight:bold;color:#1f4068;margin-bottom:10px;'),
			(new CDiv('No data to display yet.'))->setAttribute('style', 'font-size:12px;color:#888;'),
		]))->setAttribute('style', 'flex:1;min-width:280px;background:#fff;border:1px solid #d0d5e0;border-radius:6px;padding:16px 20px;');
	}

	$pie = HostDataBuilder::buildSvgPie($counts);

	return (new CDiv([
		(new CDiv([
			(new CDiv($title))->setAttribute('style', 'font-size:13px;font-weight:bold;color:#1f4068;margin-bottom:10px;'),
			new CTag('div', true, $pie['svg']),
		]))->setAttribute('style', 'flex-shrink:0;'),
		(new CDiv(new CTag('div', true, $pie['legend'])))->setAttribute('style', 'font-size:12px;'),
	]))->setAttribute('style', 'flex:1;min-width:280px;background:#fff;border:1px solid #d0d5e0;border-radius:6px;padding:16px 20px;display:flex;align-items:center;gap:24px;flex-wrap:wrap;');
};

$charts_row = (new CDiv([
	$build_pie_panel('Assets by Device Type', $type_counts),
	$build_pie_panel('Assets by Operating System', $os_counts),
]))->setAttribute('style', 'display:flex;gap:16px;margin-bottom:16px;flex-wrap:wrap;');

// ── Device Type Badges — toggle filter on click; URL uses override not stale state ──
$badge_items = [];
foreach ($type_counts as $t => $c) {
	$is_active = ($device_type === $t);
	$badge_items[] = (new CTag('a', true, $h($t) . ' (' . $c . ')'))
		->setAttribute('href', $list_url(['device_type' => $is_active ? '' : $t, 'page' => 1]))
		->setAttribute('style', 'display:inline-block;background:' . ($is_active ? '#1f4068' : '#f0f4ff') . ';color:' . ($is_active ? '#fff' : '#1f4068') . ';border:1px solid #c8d4f0;border-radius:4px;padding:4px 12px;font-size:12px;text-decoration:none;');
}
$badges = (new CDiv($badge_items))->setAttribute('style', 'display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;');

// ── Toolbar ──
$export_url = (new CUrl('zabbix.php'))
	->setArgument('action', 'cmdb.export')
	->setArgument('search', $search)
	->setArgument('groupid', $groupid)
	->setArgument('device_type', $device_type)
	->setArgument('warranty_status', $warranty_status)
	->setArgument('interface_type', $iface_type)
	->getUrl();
$toolbar = (new CDiv([
	(new CDiv('Showing ' . count($hosts) . ' of ' . $total . ' assets'))->setAttribute('style', 'font-size:13px;color:#666;'),
	(new CTag('a', true, 'Export CSV'))->setAttribute('href', $export_url)->setAttribute('style', 'padding:5px 14px;background:#27ae60;color:#fff;border-radius:3px;font-size:12px;text-decoration:none;font-weight:bold;'),
]))->setAttribute('style', 'display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;');

// ── Table ──
$table = (new CTableInfo())
	->setHeader(['#', 'Host Name', 'System Name', 'IP', 'Group', 'Type', 'Model', 'Serial', 'OS / Version', 'Interface', 'Status', 'Warranty', 'Location', 'Actions']);

$type_icons = [
	'Server' => '🖥', 'Linux' => '🐧', 'Windows' => '🪟', 'Router' => '🔀',
	'Switch' => '🔌', 'Firewall' => '🛡', 'Hypervisor' => '☁', 'Storage' => '💾',
	'Printer' => '🖨', 'UPS' => '🔋', 'Wireless' => '📡', 'Unknown' => '❓',
];

$i = (($page - 1) * $page_size) + 1;
foreach ($hosts as $hrow) {
	$avail = $hrow['availability'] ?? ['label' => 'Unknown', 'color' => '#888'];
	$warr  = $hrow['warranty'] ?? ['label' => 'Not Set', 'color' => '#888'];

	$avail_badge = (new CSpan($h($avail['label'] ?? 'Unknown')))
		->setAttribute('style', 'color:' . ($avail['color'] ?? '#888') . ';font-weight:bold;font-size:12px;');
	$warranty_badge = (new CSpan($h($warr['label'] ?? 'Not Set')))
		->setAttribute('style', 'color:' . ($warr['color'] ?? '#888') . ';font-size:11px;font-weight:bold;');

	$dev_type = (string) ($hrow['device_type'] ?? 'Unknown');
	$icon     = $type_icons[$dev_type] ?? '❓';
	$edit_url = (new CUrl('zabbix.php'))->setArgument('action', 'cmdb.asset.edit')->setArgument('hostid', $hrow['hostid'] ?? '0')->getUrl();
	$location_text = (string) ($hrow['location_display'] ?? '-');

	$table->addRow(new CRow([
		$i++,
		(new CTag('a', true, $h($hrow['hostname'] ?? '')))->setAttribute('href', 'zabbix.php?action=latest.view&hostids[]=' . ($hrow['hostid'] ?? ''))->setAttribute('style', 'font-weight:bold;color:#1f4068;'),
		$h($hrow['sys_name'] ?? ''),
		$h($hrow['ip'] ?? ''),
		$h($hrow['group'] ?? ''),
		$icon . ' ' . $h($dev_type),
		$h($hrow['model'] ?? ''),
		$h($hrow['serial'] ?? ''),
		$h($hrow['os'] ?? ''),
		$h($hrow['iface_type'] ?? ''),
		$avail_badge,
		$warranty_badge,
		$h($location_text),
		(new CTag('a', true, 'Edit'))->setAttribute('href', $edit_url)->setAttribute('style', 'color:#1f83c6;font-size:12px;text-decoration:none;'),
	]));
}

if ($total === 0) {
	$table->addRow(new CRow([
		(new CCol('No assets match the current filters.'))->setAttribute('colspan', 14),
	]));
}

// ── Pagination ──
$pag_items = [];
if ($page > 1) {
	$pag_items[] = (new CTag('a', true, 'Prev'))
		->setAttribute('href', $list_url(['page' => $page - 1]))
		->setAttribute('style', 'padding:4px 10px;border:1px solid #c8d0e0;border-radius:3px;font-size:12px;text-decoration:none;color:#333;background:#fff;');
}
$pag_items[] = (new CDiv('Page ' . $page . ' of ' . $total_pages))->setAttribute('style', 'font-size:12px;color:#666;padding:4px 8px;');
if ($page < $total_pages) {
	$pag_items[] = (new CTag('a', true, 'Next'))
		->setAttribute('href', $list_url(['page' => $page + 1]))
		->setAttribute('style', 'padding:4px 10px;border:1px solid #c8d0e0;border-radius:3px;font-size:12px;text-decoration:none;color:#333;background:#fff;');
}
$pagination = (new CDiv($pag_items))->setAttribute('style', 'display:flex;gap:8px;align-items:center;justify-content:flex-end;margin-top:12px;');

(new CHtmlPage())
	->setTitle('Enterprise CMDB')
	->addItem($filter_form)
	->addItem($summary)
	->addItem($charts_row)
	->addItem($badges)
	->addItem($toolbar)
	->addItem($table)
	->addItem($pagination)
	->show();
