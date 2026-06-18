<?php
namespace Modules\EnterpriseCmdb\Lib;

class AssetStore {

	private static string $file;

	private static function getFile(): string {
		if (!isset(self::$file)) {
			self::$file = dirname(__DIR__) . '/data/assets.json';
		}
		return self::$file;
	}

	public static function getAll(): array {
		$file = self::getFile();
		if (!file_exists($file)) return [];
		$data = json_decode(file_get_contents($file), true);
		return is_array($data) ? $data : [];
	}

	public static function get(string|int $hostid): array {
		$all = self::getAll();
		return $all[(string) $hostid] ?? [];
	}

	public static function save(string|int $hostid, array $data): bool {
		$all = self::getAll();
		$key = (string) $hostid;
		// Merge with existing record so partial saves do not drop prior fields.
		$existing = $all[$key] ?? [];
		$data['updated_at'] = date('Y-m-d H:i:s');
		$all[$key] = array_merge($existing, $data);
		return file_put_contents(self::getFile(), json_encode($all, JSON_PRETTY_PRINT)) !== false;
	}

	public static function delete(string|int $hostid): bool {
		$all = self::getAll();
		unset($all[(string) $hostid]);
		return file_put_contents(self::getFile(), json_encode($all, JSON_PRETTY_PRINT)) !== false;
	}

	// Device type detection based on SNMP sysDescr / OS / hostname patterns
	public static function detectDeviceType(string $os, string $sys_descr, string $hostname): string {
		$text = strtolower($os . ' ' . $sys_descr . ' ' . $hostname);

		$patterns = [
			'Firewall'  => ['firewall', 'asa', 'pix', 'fortigate', 'fortinet', 'palo alto', 'checkpoint', 'juniper srx', 'pfSense', 'sophos', 'watchguard'],
			'Router'    => ['router', 'cisco isr', 'cisco asr', 'juniper mx', 'mikrotik', 'routeros'],
			'Switch'    => ['switch', 'catalyst', 'nexus', 'procurve', 'aruba', 'juniper ex', 'dell powerconnect', 'brocade'],
			'Wireless'  => ['wireless', 'access point', 'wifi', 'wlan', 'aironet', 'unifi', 'ruckus', 'aruba ap'],
			'Printer'   => ['printer', 'print', 'laserjet', 'officejet', 'ricoh', 'xerox', 'canon', 'konica', 'epson'],
			'UPS'       => ['ups', 'uninterruptible', 'apc', 'eaton', 'liebert', 'power supply'],
			'Storage'   => ['storage', 'nas', 'san', 'netapp', 'emc', 'synology', 'qnap', 'pure storage'],
			'Hypervisor'=> ['vmware', 'esxi', 'vsphere', 'proxmox', 'hyper-v', 'xen'],
			'Linux'     => ['linux', 'ubuntu', 'centos', 'debian', 'rhel', 'fedora', 'suse', 'alpine'],
			'Windows'   => ['windows', 'microsoft windows', 'win server'],
			'Server'    => ['server', 'poweredge', 'proliant', 'blade'],
		];

		foreach ($patterns as $type => $keywords) {
			foreach ($keywords as $kw) {
				if (strpos($text, strtolower($kw)) !== false) {
					return $type;
				}
			}
		}

		return 'Unknown';
	}

	// Detect manufacturer from sysDescr / OS
	public static function detectManufacturer(string $sys_descr, string $os): string {
		$text = strtolower($sys_descr . ' ' . $os);

		$vendors = [
			'Cisco'      => ['cisco'],
			'Juniper'    => ['juniper'],
			'HP'         => ['hp ', 'hewlett', 'procurve', 'hpe'],
			'Dell'       => ['dell'],
			'Aruba'      => ['aruba'],
			'Fortinet'   => ['fortinet', 'fortigate'],
			'Palo Alto'  => ['palo alto'],
			'MikroTik'   => ['mikrotik', 'routeros'],
			'Ubiquiti'   => ['ubiquiti', 'unifi'],
			'VMware'     => ['vmware', 'esxi'],
			'Microsoft'  => ['microsoft', 'windows'],
			'Synology'   => ['synology'],
			'QNAP'       => ['qnap'],
			'NetApp'     => ['netapp'],
			'APC'        => ['apc', 'schneider'],
			'Eaton'      => ['eaton'],
			'Ruckus'     => ['ruckus'],
		];

		foreach ($vendors as $vendor => $keywords) {
			foreach ($keywords as $kw) {
				if (strpos($text, $kw) !== false) {
					return $vendor;
				}
			}
		}

		return 'Unknown';
	}

	// Calculate warranty status
	public static function getWarrantyStatus(?string $warranty_end): array {
		if (empty($warranty_end)) {
			return ['status' => 'unknown', 'label' => 'Not Set', 'color' => '#888888'];
		}

		$end = strtotime($warranty_end);
		$now = time();
		$days_left = (int)(($end - $now) / 86400);

		if ($days_left < 0) {
			return ['status' => 'expired', 'label' => 'Expired ' . abs($days_left) . 'd ago', 'color' => '#e74c3c'];
		} elseif ($days_left <= 90) {
			return ['status' => 'expiring', 'label' => 'Expiring in ' . $days_left . 'd', 'color' => '#e67e22'];
		} else {
			return ['status' => 'valid', 'label' => 'Valid (' . $days_left . 'd left)', 'color' => '#27ae60'];
		}
	}

	// Format bytes to human readable
	public static function formatBytes(string $bytes): string {
		if (!is_numeric($bytes) || $bytes == '') return '-';
		$b = (float)$bytes;
		foreach (['B', 'KB', 'MB', 'GB', 'TB'] as $unit) {
			if ($b < 1024) return round($b, 1) . ' ' . $unit;
			$b /= 1024;
		}
		return round($b, 1) . ' PB';
	}
}
