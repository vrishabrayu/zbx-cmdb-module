# Enterprise CMDB for Zabbix

An enterprise-grade CMDB module for Zabbix 7.x that goes far beyond the basic X-Mars CMDB with full asset lifecycle management, device classification, warranty tracking, rack mapping and CSV export.

## Features

### 🔍 Auto-Discovery
- **Device type detection** — automatically classifies hosts as Server, Router, Switch, Firewall, Hypervisor, Storage, Printer, UPS, Wireless, etc.
- **Manufacturer detection** — Cisco, Juniper, Dell, HP, Fortinet, MikroTik, VMware, and more
- **OS detection** — from Zabbix inventory or live item data (system.sw.os, system.uname, sysDescr)
- **Serial number & model** — from inventory or SNMP items

### 📊 Dashboard
- Summary cards: Total Assets, Online, Offline, Warranty Expired, Expiring Soon
- Device type breakdown badges with one-click filter
- Paginated host table with 18 columns

### 🛡 Warranty & Lifecycle Tracking
- Purchase date, Warranty end date, EOL date per asset
- Color-coded warranty status: ✅ Valid / ⚠️ Expiring Soon / ❌ Expired
- Filter by warranty status

### 📍 Location & Rack Mapping
- Datacenter, Location/Room, Rack Name, Rack Unit position
- Displayed inline in the asset table

### ✏️ Asset Editor
- Edit device type, manufacturer, model, serial, asset tag
- Set purchase date, warranty end, EOL date
- Set datacenter, location, rack, rack unit
- Add free-text notes

### ⬇️ Export
- Full CSV export with all 26 fields
- Includes auto-detected and manually entered data

## Compatibility

- ✅ Zabbix 7.0.x
- ✅ Zabbix 7.4.x
- ✅ Zabbix 8.0.x

## Installation

```bash
# Download on Zabbix server (Linux)
wget https://github.com/vrishabrayu/zbx-cmdb-module/archive/refs/heads/main.zip -O cmdb.zip
unzip -o cmdb.zip

# Docker install
sudo docker cp zbx-cmdb-module-main zabbix-web:/usr/share/zabbix/modules/zabbix_enterprise_cmdb
sudo docker exec -u root zabbix-web chown -R zabbix:zabbix /usr/share/zabbix/modules/zabbix_enterprise_cmdb/data
sudo docker exec -u root zabbix-web chmod 775 /usr/share/zabbix/modules/zabbix_enterprise_cmdb/data
```

## Enable in Zabbix

1. Go to **Administration → General → Modules**
2. Click **Scan Directory**
3. Enable **Enterprise CMDB**
4. Navigate to **Inventory → Enterprise CMDB**

## Data Storage

Asset data (warranty, rack, location, etc.) is stored in `data/assets.json` inside the module folder. The `data/` folder must be writable by the PHP process user (`zabbix` in Docker).

## License

MIT
