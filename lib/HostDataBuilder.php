<?php
namespace Modules\EnterpriseCmdb\Lib;

/**
 * Builds normalized CMDB host rows for list view and CSV export.
 * Single source of truth — prevents controller/view field mismatches.
 */
class HostDataBuilder {

	/** Coerce nullable inventory/API values to display-safe strings. */
	public static function display(?string $value, string $default = '-'): string {
		if ($value === null || $value === '') {
			return $default;
		}
		return (string) $value;
	}

	/** htmlspecialchars-safe: never pass null to the view layer. */
	public static function esc(?string $value): string {
		return htmlspecialchars(self::display($value, ''), ENT_QUOTES, 'UTF-8');
	}

	/**
	 * Build one CMDB row from Zabbix host + items + asset store entry.
	 *
	 * @return array<string, mixed>|null null when row should be excluded by filters
	 */
	public static function buildRow(array $host, array $items, array $asset, array $filters = []): ?array {
		$inv = $host['inventory'] ?? [];

		// Interface type filter — skip host when no matching interface.
		$iface_type = (int) ($filters['interface_type'] ?? 0);
		if (!ItemFinder::hasInterfaceType($host, $iface_type)) {
			return null;
		}

		$sys_descr = ItemFinder::getValue($items, 'sys_descr');
		$os        = self::resolveOs($inv, $items, $sys_descr);
		$sys_name  = ItemFinder::getValue($items, 'sys_name', $host['host'] ?? '-');

		$dev_type = self::display(
			$asset['device_type'] ?? AssetStore::detectDeviceType($os, $sys_descr, $host['name'] ?? '')
		);

		$manufacturer = self::display(
			$inv['vendor'] ?? ($asset['manufacturer'] ?? AssetStore::detectManufacturer($sys_descr, $os)),
			'Unknown'
		);

		$serial = self::display(
			$inv['serialno_a'] ?? ItemFinder::getValue($items, 'serial') ?: ($asset['serial'] ?? null)
		);
		$model = self::display(
			$inv['model'] ?? $inv['hardware'] ?? ItemFinder::getValue($items, 'model') ?: ($asset['model'] ?? null)
		);

		$warranty_info = AssetStore::getWarrantyStatus($asset['warranty_end'] ?? null);

		// Post-build filters (device type, warranty status).
		if (!empty($filters['device_type']) && $dev_type !== $filters['device_type']) {
			return null;
		}
		if (!empty($filters['warranty_status']) && $warranty_info['status'] !== $filters['warranty_status']) {
			return null;
		}

		$location   = self::display($asset['location'] ?? ($inv['location'] ?? null));
		$rack       = self::display($asset['rack'] ?? null);
		$rack_unit  = self::display($asset['rack_unit'] ?? null);
		$datacenter = self::display($asset['datacenter'] ?? null);

		$cpu_usage = self::formatCpuUsage($items);
		$mem_usage = self::formatMemUsage($items);
		$uptime    = self::formatUptime($items);

		$group = '-';
		if (!empty($host['hostgroups'][0]['name'])) {
			$group = (string) $host['hostgroups'][0]['name'];
		} elseif (!empty($host['groups'][0]['name'])) {
			$group = (string) $host['groups'][0]['name'];
		}

		$row = [
			'hostid'        => (string) ($host['hostid'] ?? ''),
			'hostname'      => self::display($host['name'] ?? null, 'Unknown'),
			'host'          => self::display($host['host'] ?? null, '-'),
			'sys_name'      => self::display($sys_name),
			'ip'            => ItemFinder::getPrimaryIp($host),
			'group'         => $group,
			'os'            => $os,
			'device_type'   => $dev_type,
			'manufacturer'  => $manufacturer,
			'model'         => $model,
			'serial'        => $serial,
			'iface_type'    => ItemFinder::getPrimaryInterfaceLabel($host),
			'availability'  => ItemFinder::getAvailability($host['interfaces'] ?? []),
			'warranty'      => $warranty_info,
			'warranty_end'  => $asset['warranty_end'] ?? null,
			'location'      => $location,
			'rack'          => $rack,
			'rack_unit'     => $rack_unit,
			'datacenter'    => $datacenter,
			'purchase_date' => self::display($asset['purchase_date'] ?? ($inv['install_date'] ?? null)),
			'eol_date'      => self::display($asset['eol_date'] ?? null),
			'asset_tag'     => self::display($asset['asset_tag'] ?? ($inv['asset_tag'] ?? null)),
			'notes'         => (string) ($asset['notes'] ?? ($inv['notes'] ?? '')),
			'cpu_count'     => self::display(ItemFinder::getValue($items, 'cpu_count')),
			'cpu_usage'     => $cpu_usage,
			'mem_total'     => AssetStore::formatBytes(ItemFinder::getValue($items, 'mem_total')),
			'mem_usage'     => $mem_usage,
			'uptime'        => $uptime,
			'status'        => $host['status'] ?? 0,
			'maintenance'   => $host['maintenance_status'] ?? 0,
		];

		// Pre-formatted location for list view — avoids duplicating logic in the template.
		$row['location_display'] = self::formatLocationColumn($row);

		return $row;
	}

	/**
	 * Build rows for many hosts; applies optional filters.
	 */
	public static function buildAll(array $hosts, array $itemsByHost, array $assets, array $filters = []): array {
		$rows = [];
		foreach ($hosts as $host) {
			$hid = $host['hostid'] ?? null;
			if ($hid === null || $hid === '') {
				continue;
			}
			$key = (string) $hid;
			$row = self::buildRow(
				$host,
				$itemsByHost[$key] ?? $itemsByHost[$hid] ?? [],
				$assets[$key] ?? $assets[$hid] ?? [],
				$filters
			);
			if ($row !== null) {
				$rows[] = $row;
			}
		}
		return $rows;
	}

	/** Format location column: datacenter / rack / room fallback chain. */
	public static function formatLocationColumn(array $row): string {
		$parts = [];
		if (($row['datacenter'] ?? '-') !== '-') {
			$parts[] = $row['datacenter'];
		}
		if (($row['rack'] ?? '-') !== '-') {
			$parts[] = 'Rack ' . $row['rack'];
		}
		if (!empty($parts)) {
			return implode(' / ', $parts);
		}
		return ($row['location'] ?? '-') !== '-' ? $row['location'] : '-';
	}

	private static function resolveOs(array $inv, array $items, string $sys_descr): string {
		$os = '-';
		if (!empty($inv['os'])) {
			$os = (string) $inv['os'];
		} elseif (!empty($inv['os_full'])) {
			$os = (string) $inv['os_full'];
		} else {
			$item_os = ItemFinder::getValue($items, 'os');
			if ($item_os !== '') {
				$os = $item_os;
			} elseif ($sys_descr !== '') {
				if (preg_match('/Version\s+([\d\.\(\)A-Za-z]+)/i', $sys_descr, $m)) {
					$os = $m[1];
				} else {
					$os = substr($sys_descr, 0, 40);
				}
			}
		}
		if (strlen($os) > 40) {
			$os = substr($os, 0, 37) . '...';
		}
		return $os;
	}

	private static function formatCpuUsage(array $items): string {
		$val = ItemFinder::getValue($items, 'cpu_usage');
		if ($val === '') {
			return '-';
		}
		$v = (float) $val;
		if (strpos(ItemFinder::getKey($items, 'cpu_usage'), 'idle') !== false) {
			$v = 100 - $v;
		}
		return round($v, 1) . '%';
	}

	private static function formatMemUsage(array $items): string {
		$val = ItemFinder::getValue($items, 'mem_usage');
		if ($val === '') {
			return '-';
		}
		$v = (float) $val;
		if (strpos(ItemFinder::getKey($items, 'mem_usage'), 'pavailable') !== false) {
			$v = 100 - $v;
		}
		return round($v, 1) . '%';
	}

	private static function formatUptime(array $items): string {
		$val = ItemFinder::getValue($items, 'uptime');
		if ($val === '') {
			return '-';
		}
		return ItemFinder::formatUptime($val, ItemFinder::getKey($items, 'uptime'));
	}
}
