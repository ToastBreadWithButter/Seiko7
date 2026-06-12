# Sistem Penjualan Online

Website penjualan online sederhana berbasis PHP + MySQL yang siap dijalankan di localhost XAMPP dan mudah dipindahkan ke hosting.

## Fitur User

- Etalase barang dengan harga, deskripsi, gambar, stok, dan badge promo
- Pencarian barang
- Filter kategori
- Urutkan harga termurah atau termahal
- Keranjang belanja
- Ubah jumlah barang di keranjang
- Checkout dan total pembayaran
- Stok otomatis berkurang saat order dibuat

## Fitur Admin

- Login melalui `/login`
- Tambah, edit, dan hapus barang
- Statistik total produk, total stok, total order, total revenue
- Riwayat order terbaru
- Daftar stok hampir habis
- Atur threshold stok hampir habis
- Pencarian barang di dashboard admin

## Setup

1. Import file `schema.sql` ke MySQL
2. Pastikan database bernama `penjualan_online`
3. Sesuaikan `app/config.php` jika username atau password MySQL berbeda
4. Simpan project di folder XAMPP `htdocs`
5. Buka `http://localhost/nama-folder-project/`

## Login Admin Demo

- Username: `admin`
- Password: `admin123`

## Catatan

- Trigger MySQL akan memvalidasi stok dan mengurangi stok otomatis saat checkout berhasil
- Produk contoh memakai SVG lokal supaya ringan dan tidak bergantung pada upload gambar

