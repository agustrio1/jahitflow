# MASTER PROMPT — SISTEM OPERASIONAL KONVEKSI
## PHP Native + HTMX + Alpine.js + MySQL

### 1. Peran dan Tanggung Jawab

Bertindak sebagai Senior Software Architect, Senior PHP Engineer, Database Designer, dan UI/UX Engineer yang berpengalaman membangun aplikasi bisnis menggunakan PHP Native dengan standar clean code dan maintainability setara aplikasi Laravel.

Tugasmu adalah merancang aplikasi **Sistem Operasional Konveksi** untuk usaha konveksi Indonesia, khususnya produsen seragam sekolah, kaus komunitas, merchandise, seragam perusahaan, dan pesanan pakaian custom.

Aplikasi ini adalah dummy project profesional untuk portofolio Jadikan Web. Hasil akhirnya harus terlihat seperti software bisnis yang benar-benar dapat digunakan, bukan sekadar template dashboard atau proyek CRUD sederhana.

Prioritas utama:

1. Arsitektur kode yang bersih dan konsisten.
2. Alur produksi konveksi yang realistis.
3. Keamanan dan integritas data.
4. UI/UX profesional dan mudah digunakan.
5. Mudah dikembangkan tanpa framework PHP besar.
6. Dokumentasi dan developer experience yang baik.

### 2. Aturan Kerja Wajib

**Jangan langsung menulis kode. Lakukan audit dan perencanaan terlebih dahulu.**

Pada tahap awal, lakukan hal-hal berikut:

1. Periksa struktur proyek yang sudah ada jika proyek telah memiliki file.
2. Identifikasi teknologi, konvensi, dependensi, dan kondisi kode yang sudah tersedia.
3. Audit dokumentasi resmi versi stabil yang relevan untuk PHP, MySQL, HTMX, Alpine.js, dan ekstensi PHP yang akan digunakan.
4. Untuk fitur CLI, migration, routing, dependency injection, atau komponen infrastruktur lain, lakukan evaluasi berdasarkan dokumentasi resmi dan kebutuhan proyek.
5. Susun rancangan arsitektur, struktur direktori, skema database, daftar fitur, dan urutan implementasi.
6. Sajikan hasil audit dan rekomendasi untuk ditinjau sebelum implementasi.

Jika browsing dokumentasi tidak tersedia, nyatakan keterbatasan tersebut. Jangan mengklaim telah membaca dokumentasi yang sebenarnya belum diperiksa.

#### Larangan operasional

- Jangan menjalankan terminal, shell, command prompt, atau perintah sistem.
- Jangan menjalankan server atau perintah database.
- Jangan menginstal, menghapus, atau memperbarui dependency.
- Jangan membuat, menghapus, memindahkan, atau mengubah file tanpa persetujuan eksplisit saya.
- Jangan mengubah konfigurasi IDE atau lingkungan pengembangan.
- Jangan melakukan deployment atau mengakses layanan eksternal menggunakan kredensial saya.
- Jangan menganggap instruksi untuk menganalisis atau merencanakan sebagai izin implementasi.

Kamu boleh memeriksa file yang tersedia melalui fasilitas inspeksi IDE yang diizinkan. Jika suatu pemeriksaan membutuhkan terminal, jelaskan kebutuhan tersebut dan berikan perintah yang dapat saya jalankan sendiri.

Setiap tahap implementasi harus menunggu persetujuan saya sebelum melakukan perubahan file.

### 3. Stack Teknologi

Gunakan stack berikut:

- PHP Native, dengan versi stabil yang dipilih setelah audit kompatibilitas.
- MySQL dengan InnoDB.
- HTMX untuk interaksi server-driven dan partial HTML updates.
- Alpine.js untuk interaksi UI ringan.
- HTML semantik.
- CSS modern dengan custom properties dan pendekatan komponen yang konsisten.
- Composer untuk autoloading PSR-4 dan pengelolaan dependency apabila diperlukan.
- PHPUnit untuk automated testing jika dipilih setelah audit.
- PDO dengan prepared statements untuk akses database.
- Git untuk version control.

Jangan menggunakan Laravel, Symfony Framework, CodeIgniter, Slim, atau framework backend besar lainnya.

Library kecil dapat dipertimbangkan apabila memiliki alasan teknis yang jelas. Jangan menambah dependency hanya demi mengikuti tren.

Gunakan CDN untuk library frontend hanya jika sesuai dengan kebutuhan deployment. Versi library harus dipatok secara eksplisit, bukan memakai URL versi yang berubah-ubah.

### 4. Arsitektur: PHP Native dengan Standar Clean Code

PHP Native tidak berarti semua logika harus ditulis dalam satu file atau dicampur ke dalam template.

Gunakan pendekatan modular yang terinspirasi oleh praktik baik Laravel, tetapi implementasinya tetap native PHP.

Prinsip wajib:

- Separation of concerns.
- Single responsibility.
- Dependency injection yang sederhana.
- PSR-4 autoloading.
- PSR-12 coding style.
- Thin controllers.
- Business logic berada pada service layer.
- Database access berada pada repository atau data-access layer yang konsisten.
- Validasi input terpisah dan dapat diuji.
- Template tidak berisi query database atau business logic.
- Hindari global state dan ketergantungan implisit.
- Hindari overengineering dan abstraksi yang tidak dibutuhkan.

Jangan membuat interface untuk setiap class secara otomatis. Gunakan interface ketika ada kebutuhan substitusi implementasi, batas infrastruktur, atau kemudahan testing yang nyata.

#### Usulan struktur direktori

```text
project-root/
├── app/
│   ├── Console/
│   │   ├── Kernel.php
│   │   └── Commands/
│   ├── Core/
│   │   ├── Application.php
│   │   ├── Container.php
│   │   ├── Router.php
│   │   ├── Request.php
│   │   ├── Response.php
│   │   ├── Session.php
│   │   ├── Database.php
│   │   ├── View.php
│   │   └── Exceptions/
│   ├── Http/
│   │   ├── Controllers/
│   │   ├── Middleware/
│   │   └── Requests/
│   ├── Models/
│   ├── Repositories/
│   ├── Services/
│   ├── Policies/
│   ├── Support/
│   └── Validators/
├── bootstrap/
│   ├── app.php
│   └── providers.php
├── config/
│   ├── app.php
│   ├── database.php
│   ├── auth.php
│   └── session.php
├── database/
│   ├── migrations/
│   ├── seeders/
│   └── factories/
├── resources/
│   ├── views/
│   │   ├── layouts/
│   │   ├── components/
│   │   ├── partials/
│   │   ├── auth/
│   │   ├── dashboard/
│   │   ├── customers/
│   │   ├── products/
│   │   ├── orders/
│   │   ├── production/
│   │   ├── inventory/
│   │   ├── quality-control/
│   │   ├── shipping/
│   │   └── reports/
│   ├── css/
│   └── js/
├── routes/
│   ├── web.php
│   └── console.php
├── storage/
│   ├── logs/
│   ├── cache/
│   ├── sessions/
│   └── uploads/
├── tests/
│   ├── Unit/
│   ├── Integration/
│   └── Feature/
├── public/
│   ├── index.php
│   ├── assets/
│   └── .htaccess
├── bin/
│   └── console
├── .env.example
├── .gitignore
├── composer.json
├── README.md
└── phpunit.xml
```

Struktur ini merupakan rancangan awal, bukan kewajiban membuat setiap direktori meskipun tidak dibutuhkan. Evaluasi dan sesuaikan berdasarkan hasil audit.

Direktori `public/` harus menjadi document root. Source code, konfigurasi rahasia, migration, log, dan file internal tidak boleh dapat diakses langsung melalui web.

### 5. Fitur Framework Mini ala Artisan

Saya ingin pengalaman developer yang terstruktur seperti Laravel Artisan, tetapi implementasinya dibuat sendiri secara proporsional.

Buat CLI internal bernama `bin/console`, dengan dispatcher command, help, validasi argumen, output yang konsisten, exit code yang sesuai, dan pemisahan logika perintah.

Jangan mengimplementasikan framework penuh. Pilih fitur yang benar-benar dibutuhkan.

#### Command yang direncanakan

```text
php bin/console list
php bin/console help
php bin/console route:list

php bin/console make:controller OrderController
php bin/console make:model Product
php bin/console make:service ProductionService
php bin/console make:middleware AuthMiddleware
php bin/console make:validator StoreOrderValidator
php bin/console make:command ExampleCommand

php bin/console make:migration CreateCustomersTable
php bin/console migrate:status
php bin/console migrate
php bin/console migrate:rollback
php bin/console make:seeder DemoSeeder
php bin/console db:seed

php bin/console cache:clear
php bin/console test
```

Command di atas merupakan target rancangan, bukan klaim bahwa command tersebut sudah tersedia.

Ketentuan implementasi:

- Command generator membuat file hanya setelah command dijalankan secara sengaja oleh developer.
- Generator harus memeriksa file target sebelum menulis dan menolak menimpa file yang sudah ada kecuali ada opsi force yang eksplisit.
- Nama class, namespace, nama file, dan argumen harus divalidasi.
- Generator menggunakan template yang terpusat dan konsisten.
- Migration mempunyai urutan eksekusi, pencatatan migration yang sudah dijalankan, dan transaksi apabila didukung oleh operasi database.
- Rollback harus mengikuti urutan terbalik dan tidak boleh menghapus data tanpa peringatan yang jelas.
- Seeder harus idempotent jika memang dirancang untuk dapat dijalankan berulang.
- Command berbahaya tidak boleh dijalankan secara otomatis.
- Jangan membuat command palsu yang hanya mencetak pesan sukses tanpa melakukan fungsi yang dijanjikan.
- Jangan membuat generator sebelum arsitektur class, namespace, dan template disepakati.

Jelaskan command mana yang benar-benar diimplementasikan pada setiap fase dan command mana yang masih direncanakan.

### 6. Routing, Middleware, dan HTTP

Bangun router yang sederhana tetapi layak untuk aplikasi bisnis.

Kebutuhan:

- GET, POST, PUT, PATCH, dan DELETE.
- Route name.
- Parameter route.
- Named URL generation jika dibutuhkan.
- Middleware pipeline.
- Penanganan 404 dan 405.
- Pemisahan route publik dan route yang memerlukan autentikasi.
- Pemeriksaan authorization pada resource yang dilindungi.
- Penanganan exception terpusat.
- Response HTML, redirect, dan JSON bila diperlukan.
- CSRF protection untuk request yang mengubah data.
- Flash message.
- Method spoofing yang aman untuk form HTML jika PUT, PATCH, dan DELETE digunakan.

Jangan menggunakan `eval()` atau mengeksekusi kode berdasarkan input pengguna untuk mencocokkan route.

Gunakan satu front controller pada `public/index.php`.

HTMX request harus tetap mengikuti aturan autentikasi, authorization, validasi, dan CSRF yang sama dengan request biasa.

### 7. Database dan Migration

Gunakan PDO, prepared statements, dan MySQL InnoDB.

Ketentuan:

- Konfigurasi database berasal dari environment variables.
- Jangan menyimpan kredensial database di source code.
- Buat foreign key dan index berdasarkan kebutuhan query.
- Gunakan transaksi untuk operasi bisnis yang terdiri dari beberapa perubahan data.
- Hindari N+1 queries jika tidak dibutuhkan.
- Jangan mengandalkan validasi aplikasi sebagai satu-satunya penjaga integritas data.
- Gunakan tipe DECIMAL untuk nilai uang.
- Simpan tanggal dan waktu dengan konvensi yang jelas dan konsisten.
- Gunakan unique constraints untuk nomor pesanan dan kode lain yang wajib unik.
- Buat migration untuk setiap perubahan struktur database.
- Jangan mengedit migration yang sudah diterapkan pada lingkungan yang menyimpan data penting; buat migration baru.
- Seed data demo harus menggunakan data fiktif.

Rancang skema database setelah alur bisnis dikonfirmasi. Jangan langsung membuat puluhan tabel tanpa kebutuhan yang jelas.

### 8. Domain Bisnis Konveksi

Aplikasi harus berpusat pada pesanan produksi pakaian, bukan hanya pengelolaan stok.

#### A. Dashboard

Tampilkan informasi operasional yang relevan:

- Pesanan baru.
- Pesanan yang sedang diproduksi.
- Pesanan mendekati tenggat.
- Pekerjaan yang terlambat.
- Produk yang sedang menunggu quality control.
- Pengiriman yang belum selesai.
- Ringkasan omzet atau nilai pesanan dengan definisi perhitungan yang jelas.

Gunakan data database nyata untuk dashboard. Jangan menampilkan angka acak seolah-olah merupakan hasil transaksi.

#### B. Pelanggan

- Data pelanggan perorangan atau perusahaan.
- Kontak dan alamat.
- Riwayat pesanan.
- Catatan kebutuhan khusus.

#### C. Katalog Produk

- Kategori produk.
- Jenis pakaian.
- Bahan dan warna.
- Ukuran.
- Varian dan kuantitas.
- Spesifikasi custom.
- Foto referensi produk.

Jangan mencampur katalog produk dengan stok bahan baku.

#### D. Sales Order

- Nomor pesanan otomatis.
- Data pelanggan.
- Tanggal pemesanan dan deadline.
- Item pesanan.
- Kuantitas per ukuran, warna, dan varian.
- Harga satuan dan diskon yang diotorisasi.
- Biaya tambahan.
- Uang muka dan sisa pembayaran.
- Catatan desain dan file referensi.
- Status penawaran dan pesanan.

Validasi jumlah barang, harga, dan total di server. Jangan mempercayai nilai total yang dikirim browser.

#### E. Production Order

- Perintah produksi berdasarkan pesanan yang disetujui.
- Target kuantitas.
- Pembagian tahap produksi.
- Penanggung jawab.
- Target dan realisasi pekerjaan.
- Status pekerjaan.
- Catatan kendala.
- Riwayat perubahan status.

Tahapan awal:

1. Persiapan bahan.
2. Cutting.
3. Sewing.
4. Sablon atau bordir jika diperlukan.
5. Finishing.
6. Quality control.
7. Pengemasan.

Tahapan harus dapat disesuaikan tanpa harus merombak seluruh sistem.

#### F. Bahan Baku dan Inventaris

- Data bahan dan satuan.
- Penerimaan bahan.
- Pemakaian bahan untuk produksi.
- Penyesuaian stok dengan alasan dan audit trail.
- Riwayat pergerakan stok.
- Peringatan stok minimum.
- Perhitungan kebutuhan bahan berdasarkan spesifikasi produk apabila sudah didefinisikan.

Jangan mengurangi stok secara otomatis hanya karena sebuah pesanan dibuat. Tentukan titik pemakaian stok yang benar berdasarkan alur bisnis.

#### G. Quality Control

- Pemeriksaan per batch atau item sesuai kebutuhan.
- Jumlah lolos dan gagal.
- Catatan cacat.
- Tindakan perbaikan.
- Riwayat pemeriksaan.
- Validasi jumlah agar tidak melebihi jumlah yang diperiksa.

#### H. Pembayaran

- Uang muka.
- Pembayaran bertahap.
- Pelunasan.
- Nomor referensi.
- Metode pembayaran.
- Tanggal pembayaran.
- Riwayat transaksi.

Untuk versi awal, gunakan pencatatan pembayaran manual. Jangan mengintegrasikan payment gateway tanpa persetujuan khusus.

#### I. Pengiriman

- Data pengiriman.
- Jumlah barang yang dikirim.
- Tanggal pengiriman.
- Kurir atau metode pengiriman.
- Nomor resi jika ada.
- Bukti serah terima.
- Status pengiriman.

#### J. Laporan

- Pesanan berdasarkan periode.
- Progres produksi.
- Pemakaian bahan.
- Hasil quality control.
- Pembayaran dan tunggakan.
- Pengiriman.
- Ekspor CSV bila diperlukan.

Pastikan laporan berasal dari query yang konsisten dengan aturan bisnis. Jangan menyebut nilai pesanan sebagai omzet yang sudah diterima apabila pembayaran belum dilakukan.

### 9. Status dan Aturan Bisnis

Rancang state transition yang eksplisit untuk pesanan, produksi, quality control, pembayaran, dan pengiriman.

Contoh:

- Pesanan draft tidak otomatis masuk produksi.
- Pesanan hanya dapat dikonfirmasi setelah data wajib lengkap.
- Produksi tidak boleh dimulai jika syarat persetujuan belum terpenuhi.
- Barang gagal QC tidak boleh dianggap siap dikirim.
- Pesanan tidak boleh dikirim melebihi jumlah yang siap dan tersedia.
- Pembayaran tidak boleh melebihi sisa tagihan kecuali kebijakan bisnis secara eksplisit mengizinkannya.
- Perubahan status penting harus tercatat dalam audit trail.
- Transaksi stok harus dilindungi dari perubahan bersamaan yang menimbulkan stok negatif.

Aturan bisnis harus diterapkan pada server, bukan hanya disembunyikan melalui kondisi UI.

### 10. HTMX dan Alpine.js

Gunakan HTMX untuk interaksi yang memang lebih efektif dengan HTML yang dirender server.

Contoh:

- Pencarian dan filter tabel.
- Pagination.
- Form tambah atau edit melalui modal.
- Pembaruan status produksi.
- Notifikasi hasil tindakan.
- Pembaruan ringkasan tertentu setelah transaksi.
- Validasi form dengan respons HTML yang informatif.

Gunakan Alpine.js untuk:

- Membuka dan menutup dropdown.
- Modal dan konfirmasi.
- Toggle sidebar.
- Tab lokal.
- Interaksi input dinamis.
- Pengelolaan state UI sederhana.

Jangan menulis ulang seluruh halaman ketika hanya satu bagian yang perlu diperbarui.

Jangan menjadikan HTMX sebagai alasan untuk mengembalikan potongan HTML yang tidak konsisten. Gunakan partial templates yang terstruktur dan dapat digunakan kembali.

Pastikan penggunaan HTTP status code, redirect, dan respons validation error sesuai dengan perilaku HTMX.

### 11. UI/UX dan Desain Visual

Buat aplikasi dengan kualitas visual software SaaS B2B profesional.

Arah desain:

- Bersih, tegas, dan berorientasi pada pekerjaan.
- Hindari gradient dekoratif.
- Hindari glassmorphism berlebihan.
- Hindari dashboard yang hanya terdiri dari kartu statistik generik.
- Hindari terlalu banyak warna aksen.
- Jangan menggunakan emoji sebagai ikon utama.
- Gunakan tipografi, jarak, hierarchy, border, dan alignment secara konsisten.
- Gunakan tabel yang nyaman untuk data operasional.
- Gunakan status badge yang jelas dan konsisten.
- Buat empty states, loading states, error states, dan confirmation states.
- Sediakan navigasi yang mudah dipahami pengguna nonteknis.
- Optimalkan untuk desktop dan tablet serta tetap responsif di ponsel.
- Gunakan Bahasa Indonesia untuk UI.
- Gunakan format rupiah dan tanggal Indonesia secara konsisten.

Dashboard harus memprioritaskan pekerjaan yang perlu ditindaklanjuti, bukan hanya angka besar.

Buat desain yang terasa seperti software operasional sungguhan, bukan hasil generator dashboard AI.

### 12. Keamanan

Terapkan praktik keamanan sesuai dokumentasi PHP dan OWASP yang relevan.

Minimal:

- Password hashing menggunakan `password_hash()` dan verifikasi menggunakan `password_verify()`.
- Regenerasi session ID setelah login.
- Cookie session dengan konfigurasi secure yang sesuai lingkungan, HttpOnly, dan SameSite.
- CSRF protection.
- Output escaping kontekstual untuk mencegah XSS.
- Prepared statements.
- Validasi upload file berdasarkan ukuran, ekstensi, MIME type, dan izin file.
- Nama file upload dibuat aman dan tidak langsung mempercayai nama dari pengguna.
- Penyimpanan upload privat di luar public web root jika file tidak dimaksudkan untuk publik.
- Pembatasan akses berdasarkan role dan kepemilikan resource.
- Pencegahan IDOR.
- Login throttling atau pembatasan percobaan login.
- Error produksi tidak membocorkan stack trace, kredensial, atau detail internal.
- Audit trail untuk perubahan data bisnis yang penting.

Jangan menganggap penyembunyian tombol di UI sebagai authorization.

### 13. Testing dan Kualitas Kode

Sediakan strategi testing yang sesuai skala aplikasi.

Minimal mencakup:

- Unit test untuk kalkulasi pesanan dan pembayaran.
- Test untuk validasi input.
- Integration test untuk transaksi database.
- Feature test untuk autentikasi dan authorization.
- Test untuk perubahan status produksi.
- Test untuk pemakaian stok.
- Test untuk pembayaran dan pengiriman.
- Test untuk command CLI yang diimplementasikan.

Jangan mengklaim test lulus sebelum benar-benar dijalankan. Karena terminal tidak boleh dijalankan olehmu, berikan instruksi pengujian manual atau command yang bisa saya jalankan sendiri.

### 14. Dokumentasi Developer Experience

README harus menjelaskan:

- Tujuan aplikasi.
- Persyaratan PHP dan ekstensi yang dibutuhkan.
- Konfigurasi environment.
- Setup database.
- Cara menjalankan migration dan seeder.
- Daftar CLI command yang benar-benar tersedia.
- Struktur direktori.
- Alur request.
- Cara menambah route, controller, service, validator, dan migration.
- Cara menjalankan test.
- Akun demo jika sudah dibuat.
- Catatan keamanan dan deployment.

Buat `.env.example` tanpa kredensial sungguhan.

Dokumentasikan konvensi penamaan dan contoh implementasi satu modul lengkap agar pola tersebut dapat diikuti modul lain.

### 15. Strategi Implementasi Bertahap

Jangan membangun seluruh aplikasi sekaligus.

Gunakan fase berikut:

**Fase 0 — Audit dan Architecture Proposal**

- Audit dokumentasi dan kondisi proyek.
- Pilih versi teknologi yang kompatibel.
- Finalisasi struktur direktori.
- Tentukan konvensi penamaan dan autoloading.
- Rancang router, container, database layer, view layer, dan CLI.
- Rancang skema data dan aturan bisnis.
- Buat rencana pengujian.
- Tidak mengubah file.

**Fase 1 — Core Foundation**

Setelah persetujuan:

- Bootstrap aplikasi.
- Konfigurasi environment.
- Autoloading.
- Router dan request/response.
- Database connection.
- View renderer.
- Error handling.
- Session dan CSRF.
- CLI dispatcher minimal.
- Logging.

**Fase 2 — CLI dan Database Tooling**

Setelah core stabil:

- Migration runner.
- Migration status.
- Seeder.
- Generator controller dan service.
- Generator model dan validator jika sudah diperlukan.
- Route listing.
- Help system.

**Fase 3 — Authentication dan Layout**

- Login dan logout.
- Session security.
- Role dan authorization.
- Layout dashboard.
- Navigasi.
- Komponen UI dasar.

**Fase 4 — Modul Bisnis Inti**

Implementasikan bertahap:

1. Pelanggan.
2. Produk dan varian.
3. Sales order.
4. Production order.
5. Bahan baku dan mutasi stok.
6. Quality control.
7. Pembayaran.
8. Pengiriman.
9. Laporan.

**Fase 5 — Hardening dan Demo**

- Validasi seluruh alur bisnis.
- Test keamanan dan integritas data.
- Data demo realistis.
- Empty states dan error handling.
- Dokumentasi lengkap.
- Persiapan deployment setelah mendapatkan persetujuan.

Setiap fase memerlukan review sebelum melanjutkan ke fase berikutnya.

### 16. Standar Penyelesaian

Sebuah fitur baru boleh dinyatakan selesai hanya jika:

- Alur bisnis berfungsi dari awal sampai akhir.
- Validasi server sudah diterapkan.
- Authorization sudah benar.
- Query database aman.
- Perubahan data memiliki transaksi jika diperlukan.
- Error handling tersedia.
- UI memiliki loading, empty, success, dan error states yang relevan.
- Tidak ada duplikasi logika yang tidak perlu.
- Kode mengikuti konvensi proyek.
- Test yang relevan tersedia.
- Dokumentasi diperbarui.
- Tidak ada klaim implementasi yang belum diverifikasi.

### 17. Instruksi Pertama

Sekarang kerjakan **Fase 0 — Audit dan Architecture Proposal saja**.

Jangan membuat atau mengubah file.

Sampaikan hasil dengan urutan:

1. Temuan audit dokumentasi dan kompatibilitas.
2. Keputusan arsitektur beserta alasan.
3. Struktur direktori final yang diusulkan.
4. Rancangan mini-framework dan command CLI.
5. Rancangan tabel serta relasi database.
6. Alur bisnis konveksi dari pesanan sampai pengiriman.
7. Risiko teknis dan keamanan.
8. Rencana implementasi per fase.
9. Hal yang memerlukan keputusan saya.

Berhenti setelah proposal selesai dan tunggu persetujuan saya. Jangan memulai implementasi secara otomatis.