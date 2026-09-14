# Database yang Dipakai Sistem Ini

Dokumen ini menjelaskan **database & tabel mana saja** yang benar-benar dibaca oleh
aplikasi SWIS Inventory Tracking ini. Aplikasi ini **read-only sepenuhnya** — tidak
pernah `INSERT`/`UPDATE`/`DELETE` ke database manapun, cuma `SELECT`.

## Ringkasan: 2 koneksi PostgreSQL terpisah

Aplikasi ini terhubung ke **dua database/schema berbeda sekaligus**, keduanya harus
PostgreSQL yang sama dipakai oleh sistem AGV/WinForms yang sudah berjalan di lokasi
SWIS. Konfigurasinya ada di [`config/database.php`](config/database.php), nilai
aktual (host/port/nama db/user/password) diisi lewat file `.env` (lihat
[`.env.example`](.env.example) untuk daftar variabelnya).

| Koneksi (nama di kode) | Env prefix | Schema | Isi | Akses |
|---|---|---|---|---|
| `pgsql` (default) | `DB_*` | `public` (di database `db_swis`) | Data stok/ledger barang | Baca saja |
| `pgsql_agv` | `DB_AGV_*` | `db_agv` | Data status rak/slot dari sistem robot AGV | Baca saja (dipaksa di kode — lihat di bawah) |

Kalau di PC produksi kedua schema ini sebenarnya satu database Postgres yang sama,
isi `DB_*` dan `DB_AGV_*` dengan host/port yang sama, cuma `DB_AGV_SCHEMA=db_agv`
yang membedakan. Kalau terpisah server, sesuaikan `DB_AGV_HOST`/`DB_AGV_PORT` sendiri.

---

## Koneksi 1 — `pgsql` (schema `public`, database `db_swis`)

### Tabel `warehouse_stock` — **tabel paling penting, dipakai hampir semua fitur**

Model: [`app/Models/WarehouseStock.php`](app/Models/WarehouseStock.php)

Ini **ledger/jurnal transaksi**, BUKAN tabel saldo akhir — tiap baris adalah SATU
transaksi masuk atau keluar. Saldo stok saat ini dihitung dengan `SUM(qty) GROUP BY
component` dari seluruh baris.

| Kolom | Arti |
|---|---|
| `component` | Kode/SKU item (mis. `B13`) — disebut **"Part Code"** di tampilan |
| `component_name` | Nama item (mis. `AIR MINERAL`) |
| `unit` | Satuan (`pc`, `ml`, dst) |
| `qty` | Jumlah — **positif = barang masuk, negatif = barang keluar** |
| `bin_name` | Kode box (QR code) — ini yang menjadi penghubung ke tabel `m_matrix_storage_bin.qr_id` di koneksi AGV (lihat di bawah) |
| `remark` | `IN` atau `OUT` |
| `update_date` | Waktu transaksi |

Dipakai oleh: Dashboard (semua kartu ringkasan, chart), Rack Monitoring (pencarian &
isi box), Riwayat Transaksi (seluruh tabel), Laporan (ketiga tabnya: Stok per Item,
Ringkasan Mutasi).

### Tabel lain yang ADA modelnya tapi BELUM dipakai fitur manapun

Model-model ini sudah dibuat (untuk jaga-jaga fitur masa depan), tapi **tidak ada
satupun controller/halaman yang query ke sini saat ini**:

| Tabel | Model | Rencana kegunaan |
|---|---|---|
| `matrix_partcode` | [`MatrixPartcode.php`](app/Models/MatrixPartcode.php) | Master partcode/produk (`partcode`, `model_name`, `moq`, dst) |
| `bom_list` | [`BomList.php`](app/Models/BomList.php) | Bill of material — komponen penyusun tiap partcode |
| `bin_management` | [`BinManagement.php`](app/Models/BinManagement.php) | Master lokasi bin (beda dari status live robot di bawah) |

---

## Koneksi 2 — `pgsql_agv` (schema `db_agv`, READ-ONLY)

### Tabel `m_matrix_storage_bin` — status rak & slot dari sistem robot AGV

Model: [`app/Models/MatrixStorageBin.php`](app/Models/MatrixStorageBin.php)

**Read-only dipaksa di level kode** — method `save()` dan `delete()` di model ini
sengaja dibuat langsung `throw` error, supaya tidak ada bagian aplikasi web ini yang
bisa menulis ke data robot walau tidak sengaja.

| Kolom | Arti |
|---|---|
| `side` | Kode rack — **hanya `R1`..`R8`** yang dipakai (lihat `RackTrackingService::RACKS`). Nilai lain (`RA`, `CONVE1`, `CONVE2`) itu staging/conveyor, bukan rak penyimpanan, jadi dikecualikan |
| `column_no` | Kolom rak, format `"C1"`..`"C9"` |
| `stack` | Layer rak, `1`..`5` |
| `status` | `FULL` atau `EMPTY` |
| `qr_id` | Kode box yang sedang ada di slot ini — **cocok langsung** dengan `warehouse_stock.bin_name` |
| `storage_bin` | ID unik baris/slot (dipakai untuk endpoint detail slot) |
| `upd_date` | Waktu update terakhir status slot ini |

Cakupan gudang: 8 rack × 9 kolom × 5 layer = **360 slot total**, terverifikasi cocok
persis dengan dokumentasi resmi gudang.

Dipakai oleh: Dashboard (kartu Slots Filled, chart utilisasi), Rack Monitoring
(seluruhnya — grid, 3D, pencarian), Laporan (tab Rack Utilization), Riwayat Transaksi
(resolusi kolom "Lokasi Box" — lihat catatan keterbatasan di bawah).

### Tabel lain di schema ini (ADA tapi kosong/tidak dipakai)

`t_status_rack`, `t_task_order`, `t_status_agv` — ini yang **idealnya** jadi log
riwayat pergerakan box↔rack yang akurat (posisi box PADA WAKTU transaksi terjadi).
Di data pengembangan, tabel-tabel ini **kosong** — kemungkinan dump diambil saat robot
idle, atau memang belum dipakai live. **Perlu dikonfirmasi ke programmer sistem AGV**
apakah tabel ini terisi saat sistem berjalan normal di produksi.

Karena itu, kolom "Lokasi Box (kini)" di menu Riwayat Transaksi memakai pendekatan:
posisi box **SAAT INI** dari `m_matrix_storage_bin` (bukan posisi persis saat
transaksi terjadi dulu). Kalau nanti terkonfirmasi tabel di atas terisi, sumber data
ini sebaiknya diganti — lihat `RackTrackingService::transactionHistory()`.

---

## Semua query terpusat di satu tempat

Tidak ada controller yang query langsung ke model — semua logic query (join manual
antar dua koneksi, agregasi, dll) ada di satu file:

**[`app/Services/RackTrackingService.php`](app/Services/RackTrackingService.php)**

Kalau butuh data baru dari tabel yang sudah ada, atau mau mulai pakai salah satu
tabel yang belum aktif di atas, tambahkan method baru di sini — jangan taruh query
di controller.

## Variabel `.env` yang mengatur koneksi ini

Lihat [`.env.example`](.env.example) untuk daftar lengkap dengan komentar. Ringkas:

```
DB_CONNECTION=pgsql
DB_HOST=...
DB_PORT=...
DB_DATABASE=db_swis
DB_USERNAME=...
DB_PASSWORD=...

DB_AGV_HOST=...
DB_AGV_PORT=...
DB_AGV_DATABASE=...
DB_AGV_SCHEMA=db_agv
DB_AGV_USERNAME=...
DB_AGV_PASSWORD=...
```
