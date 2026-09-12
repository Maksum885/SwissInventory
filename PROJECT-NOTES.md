# SWIS Inventory Tracking

**Web read-only** (Laravel + PostgreSQL) untuk tracking item, box, dan slot rack pada
Smart Warehouse Integrated System (SWIS) — fasilitas pembelajaran mahasiswa Politeknik
Batam. Project **terpisah** dari aplikasi desktop WinForms (C#) di `../Mainform` —
keduanya cuma berbagi database PostgreSQL yang sama, tidak digabung.

Spesifikasi resmi ada di `SWIS-Inventory-Tracking-Dokumentasi.pdf` (dikirim user) dan
mockup desain di `swis-inventory-mockup.html` — layout/warna/font pada layout Blade
(`resources/views/layouts/app.blade.php`) mengikuti mockup itu secara sengaja (IBM Plex
Sans/Mono, sidebar gelap, Chart.js).

## Prinsip inti (dari dokumentasi resmi — JANGAN dilanggar)

- **Read-only sepenuhnya.** Tidak ada form input manual, tidak ada menu Master Data,
  tidak ada halaman login/role. Semua data dibaca dari database yang sudah ditulis oleh
  sistem AGV/ACR existing.
- 4 menu resmi saja: **Dashboard, Rack Monitoring, Riwayat Transaksi, Laporan**.
- Cakupan gudang: **8 rack (R1-R8) × 45 slot (9 kolom × 5 layer) = 360 slot**.
  Kode slot **FINAL (update 2026-09-09 sore, berdasarkan foto label resmi gudang
  yang dikirim user — "KETERANGAN LABEL")**: **`R{rack}C{kolom}{layer}`** — digit
  kolom & layer digabung LANGSUNG tanpa huruf pemisah, mis. `R1C11` = Rak1/Kolom1/
  Layer1, `R1C95` = Rak1/Kolom9/Layer5. **Kolom 1 di KANAN, Kolom 9 di KIRI** (tampak
  dari depan rak), **Layer 1 di BAWAH, Layer 5 di ATAS**.
  Riwayat revisi format kode (jangan bingung kalau nemu versi lama di riwayat chat):
  1. Draf PDF paling awal: `R{rack}-H{h}-V{v}`
  2. Revisi user (pagi): `R{rack}C{kolom}L{layer}`, kolom 1=kiri, kolom 9=kanan
  3. **FINAL (sore, dari foto label resmi)**: `R{rack}C{kolom}{layer}` (tanpa L),
     kolom 1=**kanan**, kolom 9=**kiri** — kebalikan dari revisi #2.
  Implementasi grid di `resources/views/rack/index.blade.php` pakai CSS `grid-column`/
  `grid-row` eksplisit per slot (bukan urutan dokumen dari query) supaya orientasi
  akurat — jangan render berdasar urutan data mentah, itu menghasilkan grid acak
  (bug yang sempat terjadi & sudah diperbaiki 2x). Rumus final (grid polos, TANPA
  baris header kolom/kolom label layer — itu sempat dicoba lalu di-revert atas
  permintaan user): `gridColumn = COLUMNS+1-kolom`, `gridRow = ROWS-layer+1`.

  **Visualisasi 3D (2026-09-09 malam)**: halaman Rack Monitoring sekarang menampilkan
  panel 3D (Three.js, CDN `cdnjs three.js r128`, WebGL global build tanpa OrbitControls
  — rotasi drag manual + auto-rotate diimplementasikan sendiri di script) PALING ATAS,
  sebelum grid 2D interaktif. Menampilkan 8 rack (2 baris x 4, meniru foto RAK1-4
  depan/RAK5-8 belakang dari referensi user) dengan warna live per slot (endpoint baru
  `GET /rack/all-slots`, lihat `RackMonitoringController::allSlots()`), plus panel
  legenda kode di sampingnya. Grid 2D di bawahnya (tab per rack, klik slot, pencarian)
  tetap versi sederhana seperti sebelum redesign "TAMPAK SAMPING" — user minta di-revert.

  **Update tampilan rak 3D (2026-09-09 malam, setelah user kirim foto rak asli)**:
  ditambah struktur rak — 4 tiang biru (`frameColor`) + palang merah tiap layer
  (`beamColor`) via `buildRackFrame()`, meniru foto fisik gudang. Box hanya muncul
  untuk slot **FULL** sebagai keranjang biru (`crateColor`, mesh `visible=false` by
  default lalu di-`true`-kan saat fetch `/rack/all-slots` menemukan status FULL) — slot
  EMPTY tidak render keranjang sama sekali (rak kelihatan kosong, bukan box abu-abu).

  **Update arah susunan (2026-09-09 malam, revisi ke-2 setelah user anotasi foto)**:
  8 rack TIDAK lagi disusun melebar ke samping (4 lebar x 2 baris) — sekarang memanjang
  ke belakang: pasangan (R1,R2) paling depan berhadapan lewat lorong tengah, (R3,R4) di
  belakangnya, (R5,R6) lebih belakang lagi, (R7,R8) paling belakang. Lihat perhitungan
  `pairIdx`/`sideIdx`/`originX`/`originZ` di script. Palang/beam dasar (level bawah,
  `l===0`) juga dihapus atas permintaan user — beam sekarang cuma di tiap layer 1-5.

  **Update urutan penempatan (2026-09-10)**: 3D pakai array terpisah `RACK_ORDER_3D`
  = `['R1','R5','R2','R6','R3','R7','R4','R8']` (BUKAN `RACKS` polos R1..R8). Hasil:
  kolom KANAN layar depan→belakang = R1,R2,R3,R4; kolom KIRI depan→belakang = R5,R6,R7,R8.
  Kamera juga dibuat lebih menghadap lurus (`rotY` 0.25→0.1) + zoom lebih dekat
  (`radius` 34→26) supaya urutan pasangan jauh tidak "melintir". Label DAN data box
  realtime otomatis ikut nama rack di `RACK_ORDER_3D` (mesh key & fetch sama-sama
  `R{n}C{kolom}{layer}`) — ganti isi array ini kalau mau atur ulang posisi rack.
  **Catatan jujur**: saya tidak bisa memverifikasi visual WebGL-nya sendiri (tidak ada
  browser/GPU di sisi saya) — sudah dicek sintaks JS valid (`node --check`) dan CDN
  Three.js reachable, tapi hasil render sebenarnya perlu dicek langsung oleh user.

  **Redesign layout Rack Monitoring (2026-09-10, sesuai mockup ke-2 user)**:
  - Grid 2D coklat/emas DIHAPUS.
  - Iso 3D dipindah jadi **kecil di kolom kanan** (`.rack3d-mini`, 230px) + panel
    Detail slot di bawahnya. Layout: `.rackmon-layout` (1fr / 300px).
  - **Update (2026-09-10 sore)**: "Tampak depan" rak terpilih yang tadinya CSS flat
    diganti jadi **scene 3D kedua** (`initRackFront3D`, canvas `#rackFront3d`, 440px) —
    rak tunggal dirender Three.js, tampak depan sedikit menyudut. Box FULL = keranjang
    biru 3D, klik via **raycasting** → `window.showSlotDetailByBin(storage_bin)`.
    Data slot rak aktif dikirim dari controller sbg `$slotsData` (`{col,layer,status,
    storage_bin,code}`). Jadi halaman ini punya **2 WebGL context**: besar (rak aktif)
    + kecil (iso semua rack). Pencarian & `?highlight=` menyorot box di scene besar
    lewat `window.rackFrontFocus(code)` (ubah warna mesh jadi emas + buka detail).
  - **Label kode di keranjang (2026-09-10)**: tiap keranjang FULL di scene besar dapat
    label kode (mis. `R1C15`) di 3 sisi (depan + kiri + kanan) via `addCrateLabels()` —
    canvas texture (`makeLabelTexture`) di `PlaneGeometry` child mesh. Raycasting tetap
    non-rekursif jadi label plane tidak mengganggu klik box.
  - **Graying**: kalau user sudah pilih rack lewat tab (`rackExplicit` = `$request->has('rack')`),
    rack selain yang aktif di-abu-abukan di 3D (`setRackGrayed()` — tiap rack punya
    array material sendiri di `rackObjects[rackName]`). Kalau baru masuk menu (`/rack`
    tanpa query) → semua rack full warna.
  - Detail slot hanya terisi saat `.crate` diklik (default: placeholder "Klik salah satu box").
  - Panel "Keterangan kode" lama dihapus dari layout ini.
- Satu box bisa berisi lebih dari satu jenis item.
- Satu slot rack menyimpan satu box pada satu waktu.

## Pemetaan konsep dokumen → data existing (lihat `app/Services/RackTrackingService.php`)

| Konsep dokumen | Sumber data existing |
|---|---|
| Item / SKU | `db_swis.public.warehouse_stock.component` (+ component_name, unit) |
| Box | `warehouse_stock.bin_name` **===** `db_agv.m_matrix_storage_bin.qr_id` (match langsung, terverifikasi) |
| Isi box (many-to-many) | Agregat `SUM(qty) GROUP BY (bin_name, component)` dari ledger `warehouse_stock`, qty > 0 — tidak ada tabel `BOX_ITEMS` literal, tapi ledger ini mengimplementasikan relasi yang sama |
| Rack | `m_matrix_storage_bin.side` — **hanya R1..R8** yang dihitung (lihat `RackTrackingService::RACKS`) |
| Slot H (1-9) | `m_matrix_storage_bin.column_no` (format "C1".."C9") |
| Slot V (1-5) | `m_matrix_storage_bin.stack` |
| Status slot | `m_matrix_storage_bin.status` (FULL/EMPTY) |

**Verifikasi struktur (2026-09-09, data lokal)**: `side` R1..R8 masing-masing punya
persis 45 baris (column_no C1-C9 × stack 1-5) = 360 total — **cocok persis** dengan
dokumen. Di luar 8 rack ini ada `RA` (5 slot staging) dan `CONVE1`/`CONVE2` (titik
conveyor, side='0') — **dikecualikan** dari Rack Monitoring karena bukan storage rack
sesuai alur fisik di dokumen (Inbound→Sortir→AGV→Conveyor+ACR→**Rack storage**→Outbound).

## Keterbatasan data yang perlu diketahui

1. **Riwayat Transaksi bukan log box↔rack asli.** Tabel yang idealnya jadi log ini
   (`db_agv.t_status_rack`, `t_status_rack_trial`, `t_task_order`, `t_status_agv`)
   **kosong** di snapshot dev (kemungkinan dump diambil saat robot idle, ATAU memang
   belum dipakai live — **perlu dikonfirmasi ke programmer**). Sebagai gantinya,
   Riwayat Transaksi memakai ledger `warehouse_stock` (remark IN/OUT) dan me-resolve
   rack/slot lewat posisi box **SAAT INI** di `m_matrix_storage_bin` — bukan posisi
   persis pada waktu transaksi terjadi. Kolom di UI dinamai "Lokasi Box (kini)" +
   footnote singkat (`resources/views/transaksi/index.blade.php`). Kalau nanti
   terkonfirmasi `t_status_rack` terisi di produksi, ganti sumber data di
   `RackTrackingService::transactionHistory()` ke tabel itu untuk akurasi penuh.
   **Update (2026-09-10)**: tabel riwayat sekarang juga menampilkan **Item** (SKU +
   nama) dan **Qty** (bertanda +/-, hijau/merah) per transaksi — ini data ASLI
   per-baris dari ledger `warehouse_stock`, akurat (beda dari kolom lokasi yang cuma
   perkiraan).
   **Update (2026-09-11)**: footnote/note kuning dihapus total (user minta) — kolom
   "Lokasi Box (kini)" dianggap cukup jelas dari namanya sendiri. Ditambah:
   - **Sorting**: klik header **Waktu** (toggle terkini/terlama) atau **Event**
     (kelompokkan MASUK dulu / KELUAR dulu) via query `?sort=waktu|event&dir=asc|desc`,
     lihat `TransaksiController::index()` + `RackTrackingService::transactionHistory()`
     (parameter `$sort`, `$dir`, default `waktu`/`desc`).
   - **Pagination**: 10 baris/halaman (bukan 30), pager custom Sebelumnya/Selanjutnya
     (BUKAN `$movements->links()` bawaan Laravel — itu butuh Tailwind yang tidak dipakai
     di app ini, hasilnya bakal polos tak berstyle). Kalau nanti bikin pager di halaman
     lain, ikuti pola custom ini (`.pager`, `.pager-btn`, dst di layout), jangan pakai
     `->links()` langsung.
   - Filter tanggal/rack/event sekarang punya label kecil di atasnya (`.filter-field`)
     biar lebih jelas fungsinya.
   - Polish visual: zebra-striping baris tabel, hover row jadi biru muda (`--accent-soft`).
2. **Utilisasi rack di Laporan adalah snapshot saat ini**, bukan time-series historis
   per periode (data historis okupansi tidak tersedia tanpa tabel log di atas).
3. Ada box dengan `qr_id` yang sama muncul di >1 slot rack sekaligus di data dev
   (mis. BOX013) — data quality issue di snapshot test, bukan bug kode. Kalau muncul
   lagi di data produksi, perlu ditelusuri ke programmer.
4. Data dev lokal jumlahnya sedikit (cuma 3 distinct item, 30 baris ledger) — cukup
   untuk verifikasi fungsional, tapi chart/tabel akan terlihat jauh lebih hidup begitu
   connect ke data produksi asli.

## Struktur menu & implementasi

| Menu | Route | Controller | Isi |
|---|---|---|---|
| 01 Dashboard | `/dashboard`, `/dashboard/data` (JSON) | `DashboardController` | Kartu ringkasan (klikable, link ke halaman terkait), chart utilisasi rack (bar), chart tren in/out 7 hari (line) |

**Update Dashboard final (2026-09-12)**: dijadikan versi final —
- **Auto-refresh 30 detik** (polling `GET /dashboard/data`, bukan websocket — alasan sama
  seperti Rack Monitoring: penulis data sesungguhnya di luar Laravel) — update angka
  kartu + data 2 chart di tempat tanpa reload (`refreshDashboard()` di
  `dashboard/index.blade.php`). **Indikator visual "Live" DIHAPUS** (user minta) —
  polling tetap jalan diam-diam di background, cuma tidak ada teks/titik berdenyut
  yang menampilkannya lagi. Kalau nanti perlu indikator lagi, tinggal render ulang;
  logic `refreshDashboard()` tidak berubah.
- **Panel AI Insight (placeholder "Fase 2" dari dokumen PDF awal) DIHAPUS** (2026-09-12,
  user tanya perlu/tidak) — membangun AI beneran (integrasi API, prompt, dst) itu scope
  besar di luar sekadar polish, jadi kotak placeholder kosong dilepas saja daripada
  nongkrong tidak berfungsi di dashboard "final". Bisa dibangun ulang nanti kalau
  benar-benar dibutuhkan — bukan dihapus karena dianggap tidak penting selamanya.
- **Kartu jadi tautan** ke halaman terkait: Total Item & Slot Terisi → Laporan, Box
  Aktif → Rack Monitoring, Box Masuk/Keluar Hari Ini → Riwayat Transaksi dengan
  `?dari=<hari ini>&sampai=<hari ini>` otomatis terisi (deep link, bukan cuma ke
  halaman kosong). Tiap kartu dapat ikon (pakai ulang SVG nav sidebar yang sama,
  bukan icon baru, biar konsisten & pasti valid).
- Kartu "Box Masuk/Keluar" nilainya diwarnai (hijau=masuk, merah=keluar, `.stat-in`/
  `.stat-out`) — konsisten dengan warna qty di Riwayat Transaksi.
- Panel chart dapat caption penjelas singkat di bawah judul (`.panel-caption`),
  konsisten dengan pola panel di halaman Laporan.
| 02 Rack Monitoring | `/rack`, `/rack/{rack}/slots` (JSON), `/rack/slot/{storageBin}` (JSON), `/rack/search` (JSON) | `RackMonitoringController` | Pencarian box/item → lokasi, tab 8 rack, grid 9×5, detail slot on-click (AJAX), isi box |
| 03 Riwayat Transaksi | `/transaksi` | `TransaksiController` | Log box masuk/keluar, filter tanggal/rack/event, pagination |
| 04 Laporan | `/laporan`, `/laporan/export/excel`, `/laporan/export/pdf` | `LaporanController` | Stok per item + rack tersebar, utilisasi rack, export CSV (label "Excel" — dibuka native oleh Excel, tanpa dependency berat) & PDF (`barryvdh/laravel-dompdf`) |

**Update Laporan (2026-09-11)**: kartu ringkasan sempat ditambah lalu **DIHAPUS LAGI**
(2026-09-12) — user mempertanyakan kenapa perlu, dan memang duplikat info Dashboard +
tidak diminta di spek PDF asli. Tabel "Utilisasi Rack" tetap dapat bar visual per baris
(`.occ-bar-fill`, warna hijau/emas/merah — low/mid/high) menggantikan angka polos.

**Update filter + export custom (2026-09-12)**: `RackTrackingService::stockPerItem()`
sekarang terima `$rackFilter, $search, $sortBy, $sortDir` — dipakai SAMA PERSIS oleh
tampilan layar (`LaporanController::index()`) dan kedua export (`exportExcel`,
`exportPdf`), lewat helper privat `readFilters()`. Jadi export Excel/PDF sekarang ikut
filter rack + pencarian + sortir yang sedang aktif (sebelumnya export selalu ambil
SEMUA data mentah, tidak peduli apa yang difilter di layar — ini yang dikomplain user).
Filter rack diterapkan di PHP (bukan SQL) karena butuh `$item->racks` yang baru
dihitung setelah query awal. Sorting SKU/Nama/Qty sekarang **server-side** (query
`?sort=sku|nama|qty&dir=asc|desc`, pola sama seperti Riwayat Transaksi) — bukan lagi
client-side JS, supaya ikut kebawa ke export. Kalau nanti SKU sudah ratusan/ribuan,
tambahkan pagination juga (belum ada).
Tidak ada field "jenis/kategori" di data (`warehouse_stock` cuma punya component/
component_name/unit) — kalau user minta filter "per jenis" lagi, klarifikasi dulu
maksudnya field mana, jangan mengarang kolom yang tidak ada.

**Update struktur halaman "selayaknya laporan" (2026-09-12)**: user bilang halaman masih
terasa kurang seperti "menu laporan pada umumnya". Dirombak jadi:
- Header meta di atas: judul "Laporan Inventory" + waktu generate (`now()->translatedFormat`)
  + tombol **Cetak** (`window.print()`) di samping Excel/PDF.
- Filter dibungkus panel tersendiri (`.report-panel`, class `no-print`) + **filter chip**
  yang bisa dihapus satu-satu (`.search-chip` dipakai ulang dari Rack Monitoring) atau
  "Hapus semua filter" sekaligus.
- Tiap tabel dibungkus `.report-panel` dengan header "Laporan A · Stok per Item" /
  "Laporan B · Utilisasi Rack" + caption penjelas (termasuk penjelasan kenapa Utilisasi
  Rack belum bisa per-periode — keterbatasan data, lihat bagian atas file ini).
- **CSS print** (`@media print`) — sidebar/topbar/filter form disembunyikan, panel jadi
  border tipis polos, siap dicetak langsung dari browser (Ctrl+P) selain lewat export
  Excel/PDF. `.th-sort` di-nonaktifkan (bukan disembunyikan — kalau di-`display:none`
  teks headernya ikut hilang karena teksnya ada DI DALAM elemen itu, bukan di luar).

Semua query terpusat di `app/Services/RackTrackingService.php` — jangan duplikasi logic
query di controller, tambah method baru di service kalau perlu data baru.

## Kredensial database (dev lokal)

Sama seperti sebelumnya — lihat riwayat project. Ringkas: PostgreSQL 18 lokal,
`db_swis` (user postgres) untuk warehouse_stock dkk, `postgres`/schema `db_agv` untuk
data rack/AGV (read-only, connection `pgsql_agv` di `config/database.php`).

## Status progress

- [x] Analisis dokumentasi resmi PDF + mockup HTML, verifikasi struktur 8×45=360 slot
      cocok dengan data asli
- [x] `RackTrackingService` — semua query terpusat (dashboard cards, rack utilization,
      trend, box contents, search, stock per item, transaction history)
- [x] 4 controller sesuai menu resmi, route lama (Stock In/Out manual, Monitor,
      Lokasi Rak versi awal) **dihapus** — digantikan struktur ini
- [x] Layout Blade mengikuti mockup (IBM Plex, sidebar gelap, Chart.js)
- [x] Dashboard — 4 kartu, bar chart rack, line chart tren 7 hari, panel AI placeholder
- [x] Rack Monitoring — 8 tab, grid 9×5, klik slot (AJAX), pencarian box/item (AJAX)
- [x] Riwayat Transaksi — filter tanggal/rack/event, pagination, catatan keterbatasan
- [x] Laporan — stok per item + utilisasi rack, export CSV & PDF (dompdf terinstall)
- [x] Semua route + JSON endpoint + export dites end-to-end dengan data nyata (curl)
- [ ] Konfirmasi ke programmer: apakah `t_status_rack`/`t_task_order` terisi saat sistem
      live — kalau ya, upgrade Riwayat Transaksi ke posisi historis akurat
- [ ] Setup akses ke database PC proyek asli — nanti saat testing tahap akhir

## Cara jalankan dev server

```
cd InventoryWeb
php artisan serve --port=8000
```
Buka http://127.0.0.1:8000/dashboard
