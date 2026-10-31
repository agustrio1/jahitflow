# JahitFlow — Sistem Operasional Konveksi

Aplikasi web **native PHP** (tanpa framework) untuk mengelola operasional konveksi:
dari pesanan pelanggan, produksi, quality control, pembayaran, hingga pengiriman —
dengan dashboard yang memprioritaskan **pekerjaan yang perlu ditindaklanjuti**.

> Proyek portofolio dengan **data fiktif**. Tidak ada integrasi payment gateway;
> pembayaran dicatat manual. Seluruh angka & transaksi adalah dummy.

---

## Daftar Isi
- [Fitur & Modul](#fitur--modul)
- [Teknologi](#teknologi)
- [Prasyarat](#prasyarat)
- [Instalasi](#instalasi)
- [Konfigurasi Environment](#konfigurasi-environment)
- [Menjalankan Aplikasi](#menjalankan-aplikasi)
- [Perintah CLI](#perintah-cli)
- [Akun Demo](#akun-demo)
- [Peran & Hak Akses](#peran--hak-akses)
- [Pengujian](#pengujian)
- [Struktur Proyek](#struktur-proyek)
- [Aturan Bisnis yang ditegakkan Server](#aturan-bisnis-yang-ditegakkan-server)
- [Catatan Keamanan](#catatan-keamanan)

---

## Fitur & Modul

| Modul | Ringkasan |
|---|---|
| **Dashboard** | Cockpit operasional: KPI, pekerjaan terlambat / mendekati tenggat, menunggu QC, pengiriman belum selesai. Panel disaring sesuai peran. |
| **Pelanggan** | Data pelanggan B2B + status. |
| **Produk & Varian** | Kategori, produk, varian (warna/ukuran) + harga satuan, foto. |
| **Sales Order** | Pesanan multi-item; total dihitung ulang dari harga di DB; alur status `draft → offered → confirmed → cancelled`. |
| **Produksi** | Perintah produksi dari pesanan terkonfirmasi (gerbang DP); pipeline tahap berurutan. |
| **Bahan & Stok** | Bahan baku + ledger stok (penerimaan/pemakaian/penyesuaian) anti-saldo-negatif. |
| **Quality Control** | Pemeriksaan hasil produksi (lolos / perbaikan / gagal) sebagai log. |
| **Pembayaran** | Pencatatan uang muka / cicilan / pelunasan; tak boleh melebihi sisa tagihan. |
| **Pengiriman** | Batch kirim dengan status `disiapkan → dikirim → diterima` / `batal`. |
| **Laporan** | Analitik lintas modul + ekspor CSV. |

---

## Teknologi

- **PHP ≥ 8.4** (native, PSR-4 via Composer — bukan framework)
- **MySQL 8.4** (InnoDB), akses lewat **PDO prepared statements**
- **HTMX** + **Alpine.js** untuk interaktivitas tanpa build step
- Uang memakai **bcmath** (presisi desimal), bukan float
- PHPUnit `^11.5` untuk pengujian unit

---

## Prasyarat

Pastikan hal berikut sudah terpasang:

- PHP **8.4** atau lebih baru dengan ekstensi: `pdo_mysql`, `mbstring`, `fileinfo`, `bcmath`, `openssl`
- Composer
- MySQL 8.4 (atau MariaDB yang kompatibel)

Cek ekstensi:

```bash
php -m
```

---

## Instalasi

```bash
# 1. Ambil dependensi (autoloader PSR-4 + PHPUnit dev)
composer install

# 2. Buat database (contoh via mysql client)
#    CREATE DATABASE jahitflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# 3. Buat file .env di root proyek, lalu isi kredensial
#    (template lengkap ada di bagian "Konfigurasi Environment" di bawah)

# 4. Jalankan migrasi schema
php bin/console migrate

# 5. Isi data demo (akun, pelanggan, produk, pesanan, produksi, QC, pembayaran, pengiriman)
php bin/console db:seed
```

Seeder **Demo01…Demo09** dirancang memakai **prefiks angka** agar rantai
dependensi dijalankan dalam satu lintasan yang benar (users → pelanggan → produk
→ bahan → sales order → produksi → QC → pembayaran → pengiriman). Seed bersifat
**idempoten** — aman dijalankan berulang.

---

## Konfigurasi Environment

Loader `.env` minimalis (`App\Core\Env`) membaca file `.env` di **root proyek**.
Variabel environment sistem selalu **mengalahkan** isi `.env` (prinsip 12-factor).

| Key | Default | Keterangan |
|---|---|---|
| `APP_NAME` | `JahitFlow` | Nama aplikasi. |
| `APP_ENV` | `local` | `local` / `production`. |
| `APP_DEBUG` | `false` | `true` menampilkan stack trace; **wajib `false` di produksi**. |
| `APP_URL` | `http://localhost:8000` | URL dasar aplikasi. |
| `APP_KEY` | _(kosong)_ | Kunci aplikasi (isi acak). |
| `DB_HOST` | `127.0.0.1` | Host MySQL. |
| `DB_PORT` | `3306` | Port MySQL. |
| `DB_DATABASE` | `jahitflow` | Nama database. |
| `DB_USERNAME` | `root` | User MySQL. |
| `DB_PASSWORD` | _(kosong)_ | Password MySQL — **hanya dari environment, tidak pernah di-hardcode**. |
| `SESSION_SECURE` | `false` | `true` untuk cookie `Secure` (produksi HTTPS). |

Contoh isi `.env`:

```dotenv
APP_NAME=JahitFlow
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_KEY=

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=jahitflow
DB_USERNAME=root
DB_PASSWORD=

SESSION_SECURE=false
```

---

## Menjalankan Aplikasi

**Document root web server HARUS menunjuk ke folder `public/`** sehingga source,
config, `.env`, dan `storage` tidak terjangkau URL. Hanya `public/index.php` yang
boleh dieksekusi publik (front controller).

Server bawaan PHP (pengembangan lokal):

```bash
php -S localhost:8000 -t public
```

Buka <http://localhost:8000> lalu login dengan salah satu [akun demo](#akun-demo).

Untuk produksi, konfigurasikan Apache/Nginx agar `public/` menjadi root, dengan
rewrite semua permintaan ke `public/index.php`.

---

## Perintah CLI

Semua perintah lewat `php bin/console <command>`. Hanya command yang benar-benar
berfungsi yang terdaftar (`list` tidak menampilkan command palsu).

```bash
php bin/console list          # daftar command aktif
php bin/console help <command>
```

| Command | Fungsi |
|---|---|
| `migrate [--pretend]` | Jalankan migrasi yang belum diterapkan. `--pretend` = pratinjau tanpa perubahan. |
| `migrate:status` | Status penerapan migrasi. |
| `migrate:rollback` | Rollback migrasi terakhir. |
| `db:seed [--class=Nama]` | Jalankan **semua** seeder demo, atau satu via `--class=Demo01UserSeeder`. |
| `route:list` | Daftar seluruh route terdaftar. |
| `cache:clear` | Bersihkan cache aplikasi. |
| `test [arg...]` | Wrapper nyata ke PHPUnit (meneruskan output & exit code asli). |
| `make:controller` `make:model` `make:service` `make:middleware` `make:validator` `make:command` `make:migration` `make:seeder` | Generator kerangka kode. |

---

## Akun Demo

Dibuat oleh `Demo01UserSeeder`. **Password sama untuk semua akun** dan hanya
untuk lingkungan dummy:

```
Password123!
```

| Email | Nama | Peran |
|---|---|---|
| `admin@jahitflow.test` | Admin Konveksi | Administrator |
| `manager@jahitflow.test` | Manajer Produksi | Manajer |
| `operator@jahitflow.test` | Operator Jahit | Operator Produksi |
| `keuangan@jahitflow.test` | Staf Keuangan | Keuangan |

---

## Peran & Hak Akses

Empat peran operasional (keputusan Fase 0):

| Peran | Cakupan |
|---|---|
| **Administrator** | Akses penuh, termasuk manajemen pengguna & konfigurasi. |
| **Manajer** | Pesanan, produksi, produk, pelanggan, pengiriman, laporan, bahan. |
| **Operator Produksi** | Produksi (progres tahap), QC, bahan — sesuai kebutuhan lapangan. |
| **Keuangan** | Pembayaran & laporan keuangan; tidak mengubah pesanan/produksi. |

Setiap modul menegakkan otorisasi lewat **Policy** di server — bukan sekadar
menyembunyikan tombol di UI.

---

## Pengujian

```bash
composer test          # sama dengan: phpunit
# atau lewat CLI:
php bin/console test
php bin/console test --filter=PaymentCalculator
```

Suite berfokus pada **logika murni** (tanpa DB): kalkulasi pembayaran & stok
(bcmath), validator, mesin status/transisi, matriks otorisasi Policy, dan
perlindungan CSRF. Butuh `composer install` agar `vendor/` tersedia.

---

## Struktur Proyek

```
jahitflow/
├── app/
│   ├── Console/        # Kernel CLI + Commands (migrate, db:seed, test, make:*)
│   ├── Core/           # Framework mini: Router, Database, Env, Session, View,
│   │                    #   Container, Middleware, ExceptionHandler, helpers
│   ├── Http/           # Controllers
│   ├── Models/         # Entitas (User, SalesOrder, ProductionOrder, dll.)
│   ├── Policies/       # Otorisasi berbasis peran
│   ├── Repositories/   # Akses data (SQL prepared)
│   ├── Services/       # Logika bisnis (transaksi + audit)
│   ├── Support/        # Helper murni (PaymentCalculator, StockLedger, dll.)
│   └── Validators/     # Validasi input murni
├── bin/console         # Entri CLI
├── bootstrap/app.php   # Perakitan aplikasi + wiring container
├── config/             # app, database, session, auth, navigation
├── database/
│   ├── migrations/     # Skema tabel (berurutan waktu)
│   └── seeders/        # Demo01…Demo09 (data fiktif)
├── public/             # Front controller index.php + assets (doc root)
├── resources/views/    # Template PHP
├── routes/web.php      # Definisi route
├── storage/            # Log, session (di luar doc root)
└── tests/              # Unit test PHPUnit
```

Lapisan tiap fitur: **Controller → Service → Policy/Validator → Repository → Model**,
dengan helper murni di `Support`. Dependensi dirakit otomatis lewat container
refleksi (`bootstrap/app.php`).

---

## Aturan Bisnis yang ditegakkan Server

Dirancang mengikuti spesifikasi `DOC.MD`:

- **Nomor dokumen** (SO/PP/BYR/KRM) memakai tabel `sequences` + `SELECT … FOR UPDATE`
  — **bukan** `MAX(id)+1` — aman dari tabrakan nomor.
- **Uang & kuantitas** dihitung dengan **bcmath** (skala 2 untuk rupiah, 3 untuk
  kuantitas); total **SELALU** dihitung ulang dari harga di database, harga kiriman
  browser dibuang.
- **Gerbang DP**: produksi tidak dibuka sebelum uang muka memenuhi **30%** total.
- **Pagar integritas**: pembayaran tidak boleh melebihi sisa tagihan; pengiriman
  tidak boleh melebihi jumlah siap-kirim `min(pesanan, lolos QC) − terkirim`.
  Batas diverifikasi ulang **di dalam transaksi dengan baris pesanan terkunci**
  (`FOR UPDATE`) agar double-submit tak bisa menembusnya.
- **Stok** hanya berkurang lewat peristiwa pemakaian (arah `out`), dengan ledger
  saldo yang menolak nilai negatif.
- **Transisi status eksplisit** untuk pesanan, produksi, dan pengiriman — dijaga
  mesin status di server.
- **Audit trail** mencatat setiap aksi penting (siapa, kapan, berubah dari → ke).
- **Kejujuran laporan**: "nilai pesanan" (`grand_total`, nilai kontrak) tidak pernah
  dilabeli sebagai pendapatan diterima; pendapatan = `SUM(pembayaran)`.

---

## Catatan Keamanan

- Kredensial database **hanya** dari environment; tidak ada yang di-hardcode.
- PDO prepared statements di seluruh kueri (anti SQL injection); output di-escape.
- Perlindungan **CSRF** (token sesi + `hash_equals`) pada semua aksi menulis.
- **Throttling login**: 5 percobaan lalu kunci 15 menit (lihat `config/auth.php`).
- Password memakai `password_hash` (bcrypt).
- `APP_DEBUG=false` di produksi agar stack trace tidak bocor; `SESSION_SECURE=true`
  saat berjalan di atas HTTPS.

---

*Dibangun sebagai proyek portofolio native-PHP. Seluruh nama, pelanggan, dan
transaksi bersifat fiktif.*
