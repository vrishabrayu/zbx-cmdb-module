<?php
// ItemFinder v3.1 - safe item access for PHP 8+ (no null offset reads)
namespace Modules\EnterpriseCmdb\Lib;

use API;

class ItemFinder {

	private static array $keyMap = [
		'cpu_count'  => ['system.cpu.num', 'system.cpu.num[online]', 'system.hw.cpu.num'],
		'cpu_usage'  => ['system.cpu.util', 'system.cpu.util[,avg1]', 'system.cpu.util[,idle]', 'system.cpu.util[]', 'system.cpu.load[percpu,avg1]'],
		'mem_total'  => ['vm.memory.size[total]', 'vm.memory.total'],
		'mem_usage'  => ['vm.memory.utilization', 'vm.memory.util', 'vm.memory.size[pused]', 'vm.memory.size[pavailable]'],
		'uptime'     => ['system.uptime'],
		'sys_name'   => ['system.hostname', 'system.hostname[host]', 'agent.hostname', 'system.name'],
		'os'         => ['system.sw.os', 'system.sw.os[name]', 'system.sw.os[full]', 'system.uname', 'system.sw.os[short]'],
		'os_arch'    => ['system.sw.arch', 'system.hw.arch'],
		'sys_descr'  => ['system.descr'],
		'serial'     => ['system.hw.chassis[serial]', 'system.hw.serialnumber', 'vm.vmware.vm.info[serial]'],
		'model'      => ['system.hw.chassis[model]', 'system.hw.model', 'system.product.name'],
		'bios'       => ['system.hw.chassis[version]', 'system.bios.version'],
	];

	private static array $snmpSearchKeys = [
		'sys_name'  => 'sysName',
		'sys_descr' => 'sysDescr',
		'uptime'    => 'sysUpTime',
		'os'        => 'system.sw.os',
	];

	/** Empty item slot — avoids "array offset on null" when reading ['value']. */
	private static function emptyItem(): array {
		return ['value' => '', 'key' => '', 'type' => '', 'id' => ''];
	}

	/**
	 * Safe read of an item lastvalue; returns $default when category is missing or empty.
	 */
	public static function getValue(array $items, string $category, string $default = ''): string {
		$item = $items[$category] ?? null;
		if (!is_array($item)) {
			return $default;
		}
		$value = $item['value'] ?? '';
		return ($value === null || $value === '') ? $default : (string) $value;
	}

	/**
	 * Safe read of item metadata (e.g. key_) for a category.
	 */
	public static function getKey(array $items, string $category, string $default = ''): string {
		$item = $items[$category] ?? null;
		if (!is_array($item)) {
			return $default;
		}
		return (string) ($item['key'] ?? $default);
	}

	public static function batchGetItems(array $hostIds): array {
		if (empty($hostIds)) {
			return [];
		}

		// Pre-fill every host with empty item structures, not null.
		$result = [];
		foreach ($hostIds as $hid) {
			$result[$hid] = array_fill_keys(array_keys(self::$keyMap), self::emptyItem());
		}

		$allKeys  = array_merge(...array_values(self::$keyMap));
		$keyIndex = [];
		foreach (self::$keyMap as $cat => $keys) {
			foreach ($keys as $pri => $key) {
				$keyIndex[$key] = ['cat' => $cat, 'pri' => $pri];
			}
		}

		try {
			$items = API::Item()->get([
				'output'  => ['itemid', 'hostid', 'key_', 'lastvalue', 'value_type'],
				'hostids' => $hostIds,
				'filter'  => ['key_' => $allKeys, 'status' => 0],
			]);

			$snmpItems = [];
			foreach (self::$snmpSearchKeys as $cat => $prefix) {
				$found = API::Item()->get([
					'output'      => ['itemid', 'hostid', 'key_', 'lastvalue', 'value_type'],
					'hostids'     => $hostIds,
					'search'      => ['key_' => $prefix],
					'startSearch' => true,
					'filter'      => ['status' => 0],
				]);
				foreach ($found as $f) {
					$f['_snmp_cat'] = $cat;
					$snmpItems[]    = $f;
				}
			}

			$bestPri = [];

			foreach ($items as $item) {
				$hid = $item['hostid'];
				$key = $item['key_'];
				if (!isset($result[$hid]) || !isset($keyIndex[$key])) {
					continue;
				}
				$cat  = $keyIndex[$key]['cat'];
				$pri  = $keyIndex[$key]['pri'];
				$pkey = $hid . ':' . $cat;
				$val  = $item['lastvalue'] ?? '';
				if ($val !== '' && (!isset($bestPri[$pkey]) || $pri < $bestPri[$pkey])) {
					$result[$hid][$cat] = [
						'value' => (string) $val,
						'key'   => $key,
						'type'  => $item['value_type'],
						'id'    => $item['itemid'],
					];
					$bestPri[$pkey] = $pri;
				}
			}

			foreach ($snmpItems as $item) {
				$hid  = $item['hostid'];
				$cat  = $item['_snmp_cat'];
				$key  = $item['key_'];
				$pkey = $hid . ':' . $cat;
				$val  = $item['lastvalue'] ?? '';
				if ($val !== '' && !isset($bestPri[$pkey])) {
					$result[$hid][$cat] = [
						'value' => (string) $val,
						'key'   => $key,
						'type'  => $item['value_type'],
						'id'    => $item['itemid'],
					];
					$bestPri[$pkey] = 99;
				}
			}
		} catch (\Exception $e) {
			error_log('EnterpriseCmdb ItemFinder: ' . $e->getMessage());
		}

		return $result;
	}

	public static function formatUptime(string $val, string $key): string {
		if ($val === '' || (float) $val <= 0) {
			return '-';
		}
		$s = (float) $val;
		if (stripos($key, 'sysUpTime') !== false) {
			$s = $s / 100;
		}
		$d = floor($s / 86400);
		$h = floor(((int) $s % 86400) / 3600);
		$m = floor(((int) $s % 3600) / 60);
		if ($d > 0) {
			return "{$d}d {$h}h {$m}m";
		}
		if ($h > 0) {
			return "{$h}h {$m}m";
		}
		return "{$m}m";
	}

	public static function getAvailability(array $interfaces): array {
		if (empty($interfaces)) {
			return ['status' => 'unknown', 'label' => 'Unknown', 'color' => '#888'];
		}
		$main = null;
		foreach ($interfaces as $i) {
			if (($i['main'] ?? 0) == 1) {
				$main = $i;
				break;
			}
		}
		if (!$main) {
			$main = $interfaces[0];
		}
		switch ($main['available'] ?? '0') {
			case '1':
				return ['status' => 'up', 'label' => 'Up', 'color' => '#27ae60'];
			case '2':
				return ['status' => 'down', 'label' => 'Down', 'color' => '#e74c3c'];
			default:
				return ['status' => 'unknown', 'label' => 'Unknown', 'color' => '#888888'];
		}
	}

	public static function getInterfaceTypeName(int $type): string {
		return [1 => 'Agent', 2 => 'SNMP', 3 => 'IPMI', 4 => 'JMX'][$type] ?? 'Unknown';
	}

	/**
	 * Resolve primary IP from host interfaces; safe when interfaces key is missing.
	 */
	public static function getPrimaryIp(array $host): string {
		$interfaces = $host['interfaces'] ?? [];
		if (empty($interfaces)) {
			return '-';
		}
		foreach ($interfaces as $i) {
			if (($i['main'] ?? 0) == 1 && !empty($i['ip'])) {
				return (string) $i['ip'];
			}
		}
		return (string) ($interfaces[0]['ip'] ?? '-');
	}

	/**
	 * Resolve main interface type label; safe when interfaces key is missing.
	 */
	public static function getPrimaryInterfaceLabel(array $host): string {
		$interfaces = $host['interfaces'] ?? [];
		if (empty($interfaces)) {
			return '-';
		}
		$label = '-';
		foreach ($interfaces as $i) {
			$label = self::getInterfaceTypeName((int) ($i['type'] ?? 0));
			if (($i['main'] ?? 0) == 1) {
				break;
			}
		}
		return $label;
	}

	/**
	 * True when host has an interface of the given Zabbix type (filter support).
	 */
	public static function hasInterfaceType(array $host, int $iface_type): bool {
		if (!$iface_type) {
			return true;
		}
		foreach ($host['interfaces'] ?? [] as $i) {
			if ((int) ($i['type'] ?? 0) === $iface_type) {
				return true;
			}
		}
		return false;
	}
}
