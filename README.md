# Digital Meeting & Activity Management System

## Project Overview

Agenda Management System merupakan modul administrasi berbasis web yang digunakan untuk mengelola proses pengajuan agenda rapat dan agenda kegiatan pada lingkungan internal instansi.

Proyek ini merupakan pengembangan dan modifikasi dari modul agenda yang telah ada sebelumnya. Sistem lama hanya mendukung pengajuan agenda rapat dengan tampilan antarmuka lama serta belum memiliki beberapa kebutuhan proses bisnis terbaru. Melalui pengembangan ini dilakukan penyempurnaan fitur, pembaruan antarmuka, serta penambahan berbagai validasi dan integrasi untuk meningkatkan efisiensi proses administrasi.

Modul ini dikembangkan menggunakan PHP, MySQL, JavaScript, HTML, CSS, dan Bootstrap. Proses autentikasi (Login, Logout, dan Manajemen Hak Akses) dikelola oleh sistem utama sehingga tidak termasuk dalam repository ini.

---

# Latar Belakang

Modul agenda sebelumnya telah digunakan untuk mengelola proses pengajuan rapat. Namun, seiring berkembangnya kebutuhan operasional, sistem tersebut memiliki beberapa keterbatasan, antara lain:

- Hanya mendukung pengajuan agenda rapat.
- Belum mendukung pengajuan agenda kegiatan.
- Belum memiliki validasi bentrok jadwal penggunaan ruangan.
- Belum terintegrasi dengan proses peminjaman Barang Milik Negara (BMN).
- Belum mendukung pengajuan konsumsi.
- Fitur dashboard yang masih butuh penyempurnaan dan perubahan.
- Menggunakan tampilan antarmuka lama yang kurang mendukung kebutuhan pengguna.

Berdasarkan kebutuhan tersebut, dilakukan pengembangan terhadap modul agenda dengan menambahkan fitur baru, memperbaiki proses bisnis, serta memperbarui tampilan antarmuka agar lebih efektif, efisien, dan mudah digunakan.

---

# Tujuan Pengembangan

Pengembangan modul ini bertujuan untuk:

- Mendigitalisasi proses pengajuan agenda rapat dan kegiatan.
- Mengurangi konflik penggunaan ruangan melalui validasi bentrok jadwal secara otomatis.
- Mengintegrasikan pengajuan fasilitas BMN ke dalam proses pengajuan agenda.
- Mendukung proses pengajuan konsumsi dalam satu alur kerja.
- Mempermudah monitoring agenda melalui dashboard rekapitulasi.
- Meningkatkan efisiensi administrasi dan dokumentasi kegiatan.

---

# Fitur Utama

Modul ini memiliki beberapa fitur utama sebagai berikut:

### Pengajuan Agenda
- Pengajuan agenda rapat.
- Pengajuan agenda kegiatan.
- Penentuan jenis meeting (Offline, Online, Hybrid, Hybrid Luar Kantor).
- Penjadwalan tanggal dan waktu kegiatan.
- Penentuan lokasi pelaksanaan.
- Penambahan pelaksana dan pendamping.
- Upload dokumen pendukung.

### Validasi Sistem
- Validasi data wajib (mandatory field).
- Validasi tanggal dan waktu.
- Validasi bentrok jadwal ruangan.
- Validasi agenda multi-hari.
- Validasi upload dokumen.
- Validasi jenis meeting.

### Integrasi
- Integrasi Zoom Meeting.
- Integrasi pengajuan Barang Milik Negara (BMN).
- Pengajuan konsumsi.

### Dashboard
- Monitoring agenda.
- Pencarian data.
- Filter berdasarkan bulan dan tahun.
- Pagination.
- Detail agenda.

### Manajemen Data
- Edit agenda.
- Pembatalan agenda (soft delete).
- Export data ke Microsoft Excel.

---

# Perubahan dari Sistem Sebelumnya

| Sistem Sebelumnya | Sistem Setelah Pengembangan |
|-------------------|-----------------------------|
| Hanya mendukung agenda rapat | Mendukung agenda rapat dan agenda kegiatan |
| Belum ada validasi bentrok jadwal | Validasi bentrok ruangan secara otomatis |
| Belum ada integrasi BMN | Terintegrasi dengan pengajuan BMN |
| Belum ada pengajuan konsumsi | Mendukung pengajuan konsumsi |
| Tampilan antarmuka lama | Antarmuka diperbarui agar lebih modern dan mudah digunakan |
| Dashboard terbatas | Dashboard monitoring lebih informatif |
| Export data terbatas | Export data ke Microsoft Excel |

---

# Teknologi yang Digunakan

| Teknologi | Keterangan |
|------------|------------|
| PHP | Backend Development |
| MySQL | Database Management |
| JavaScript | Client Side Programming |
| HTML5 | Struktur Halaman |
| CSS3 | Styling |
| Bootstrap | User Interface |
| XAMPP | Development Environment |

---

# Struktur Repository

```
agenda-management-system/
│
├── source-code/
│   ├── form_pengajuan_agenda.php
│   ├── rekap_agenda.php
│   └── ...
│
├── screenshots/
│   ├── dashboard.png
│   ├── form-pengajuan.png
│   ├── detail-agenda.png
│   └── export-excel.png
│
├── documentation/
│   ├── Dokument Teknis BMN.pdf
│   ├── Dokumen QA Agenda Manajement.xlsx
│
└── README.md
```

---

# Dokumentasi Quality Assurance

Repository ini juga dilengkapi dengan dokumentasi Quality Assurance sebagai bagian dari proses pengembangan sistem.

Dokumentasi yang tersedia meliputi:

- Project Overview
- Functional Requirements
- Test Scenario
- Test Case
- Bug Report
- Test Summary

Dokumentasi tersebut disusun untuk memastikan setiap kebutuhan sistem dapat ditelusuri mulai dari requirement hingga proses pengujian sehingga mendukung proses validasi dan verifikasi sistem secara menyeluruh.

---

# Screenshot Sistem

Repository ini menyertakan beberapa tampilan utama sistem, antara lain:

- Dashboard Monitoring Agenda
- Form Pengajuan Agenda

---

# Catatan

- Repository ini merupakan bagian dari portofolio pengembangan sistem administrasi internal.
- Source code yang dipublikasikan hanya mencakup bagian yang menjadi kontribusi pengembangan.
- Modul autentikasi, manajemen pengguna, konfigurasi server, dan beberapa komponen internal lainnya tidak disertakan karena merupakan bagian dari sistem utama serta mempertimbangkan aspek keamanan dan kerahasiaan data.

---

# Kontribusi Pengembangan

Pada proyek ini, kontribusi pengembangan meliputi:

- Pengembangan fitur pengajuan agenda kegiatan.
- Pengembangan validasi bentrok jadwal penggunaan ruangan.
- Pengembangan integrasi pengajuan BMN.
- Pengembangan fitur pengajuan konsumsi.
- Modernisasi antarmuka pengguna.
- Pengembangan dashboard monitoring agenda yang diatur sesuai hak akses pengguna.
- Pengembangan fitur export data,, filter, edit, dan hapus data.
- Penyusunan dokumentasi sistem bersama system analyst.
- Penyusunan dokumentasi Quality Assurance.

---

# Pengembang

**Pinka Ananda**

S1 Teknik Informatika – Universitas Lampung

Repository ini disusun sebagai bagian dari portofolio pengembangan sistem dan dokumentasi Quality Assurance.
