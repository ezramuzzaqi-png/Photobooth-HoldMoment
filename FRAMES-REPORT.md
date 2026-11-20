# Laporan Frame Photobooth Baru (10 file, 20 desain strip)

## Ringkasan

- 10 file frame asli (1200×1800, dua desain berdampingan) → `public/storage/frames/<key>.png`
- 10 versi transparan (slot = alpha 0) → `public/storage/frames/<key>-transparent.png`
- Config slot (rasio + piksel per varian) → `public/frames-config/<key>-slots.json` (+ `all-slots.json`)
- Mask bentuk (oval/hati/blob/polaroid) → `public/frames-config/<key>-{left,right}-sN-mask.png`
- Preview visual (foto mock di bawah frame transparan) → `public/frames-config/previews/`
- Skrip deteksi & preview → `scripts/detect_slots.py`, `scripts/make_preview.py`

Koordinat RATIO disimpan terhadap **ukuran canvas varian**:
- `kiri` / `kanan` → 600×1800 (x 0..1)
- `gabungan` → 1200×1800 (x 0..1)

Rasio ini dibaca langsung oleh `strip-engine.blade.php` (tidak ada hardcode ukuran piksel untuk layout preview).

## Pemetaan file → tema

| File asli | Key | Tema | Bentuk slot |
|---|---|---|---|
| IMG_7012.PNG | denim | Denim | blob (rounded) |
| IMG_7007.PNG | lace-brown | Lace & Brown | kiri persegi, kanan oval |
| IMG_7011.PNG | pink-plaid | Pink Plaid | polaroid miring/bergerigi |
| IMG_7008.PNG | movie-ticket | Movie Ticket | persegi panjang |
| IMG_7009.PNG | music-player | Music Player | persegi |
| IMG_7005.PNG | green-smile | Green Smile | kiri persegi, kanan oval berenda |
| IMG_7004.PNG | pinky-promise | Pinky Promise | kiri persegi, kanan oval |
| IMG_7006.PNG | red-hearts | Red Hearts | kiri hati, kanan persegi |
| IMG_7003.PNG | blue-growing | Blue Growing | persegi |
| IMG_7010.PNG | receipt | Receipt | persegi panjang |

## Koordinat final bbox slot (piksel canvas varian)

Format: `sN = [x, y, w, h]` pada canvas 600×1800 (kiri/kanan) atau 1200×1800 (gabungan).

### Denim
- kiri: s1 [83,229,433,322], s2 [82,670,438,323], s3 [80,1111,437,322] (blob)
- kanan: s1 [83,229,434,322], s2 [83,670,434,322], s3 [83,1111,434,322] (blob)
- rasio s1 kiri: x=0.1383 y=0.1272 w=0.7217 h=0.1789 (≈2.02:1 saat digabung)

### Lace & Brown
- kiri: s1 [70,180,460,410], s2 [70,637,460,411], s3 [70,1095,460,410] (persegi, EST)
- kanan: s1 [43,190,509,402], s2 [43,621,509,401], s3 [43,1051,509,401] (oval)

### Pink Plaid
- kiri: s1 [91,229,418,309], s2 [86,637,403,298], s3 [80,1038,418,313] (polaroid)
- kanan: s1 [91,231,418,310], s2 [86,637,403,298], s3 [85,1033,413,318] (polaroid)

### Movie Ticket
- kiri: s1 [60,230,481,339], s2 [60,598,481,339], s3 [60,967,481,339] (persegi, EST)
- kanan: s1 [61,230,478,338], s2 [61,599,478,338], s3 [61,967,478,338] (persegi, EST)

### Music Player
- kiri: s1 [88,120,424,392], s2 [88,558,424,392], s3 [88,997,424,392] (persegi, EST)
- kanan: s1 [89,120,423,392], s2 [89,559,423,392], s3 [89,998,423,391] (persegi, EST)

### Green Smile
- kiri: s1 [66,245,459,398], s2 [66,707,459,400], s3 [66,1170,459,398] (persegi, EST)
- kanan: s1 [67,273,459,353], s2 [71,723,459,353], s3 [75,1174,459,353] (oval)

### Pinky Promise
- kiri: s1 [56,215,488,415], s2 [56,664,488,417], s3 [56,1113,488,415] (persegi, EST)
- kanan: s1 [71,216,481,416], s2 [71,654,481,416], s3 [71,1093,481,416] (oval)

### Red Hearts
- kiri: s1 [64,305,464,350], s2 [64,738,464,350], s3 [66,1172,463,360] (hati)
- kanan: s1 [71,223,457,418], s2 [71,691,457,418], s3 [71,1158,457,418] (persegi, EST)

### Blue Growing
- kiri: s1 [62,177,487,398], s2 [62,608,487,398], s3 [62,1038,487,398] (persegi, EST)
- kanan: s1 [56,392,488,398], s2 [56,822,488,398], s3 [56,1252,488,398] (persegi, EST)

### Receipt
- kiri: s1 [45,390,510,330], s2 [45,747,510,330], s3 [45,1105,510,330] (persegi, EST; hampir putih → dibenahi dengan analisis tepi)
- kanan: s1 [46,390,508,330], s2 [46,748,508,329], s3 [46,1105,508,330] (persegi, EST)

## Hal yang perlu dicek manual

1. **Receipt kiri** — slot hampir sama putihnya dengan background (ff_n=0). Koordinat pakai EST + analisis tepi. Pastikan foto tidak menutupi teks "Receipt" / barcode / tulisan "Please don't scan this".
2. **Lace & Brown kiri s2, s3** — flood fill gagal (ff_n=0) karena dekorasi daisy/pita di tengah slot; pakai EST. Cek apakah bunga/pita tetap tampil di atas foto (preview menunjukkan sudah benar).
3. **Pinky Promise kiri** — klip pink & bunga menutup sebagian slot; EST dipakai. Pastikan klip tidak ikut tertutup foto.
4. **Blue Growing kiri** — teks "POSITIVE VIBES ONLY" memotong area transparan; EST dipakai agar foto tetap penuh di balik teks.
5. **Green Smile kiri s3** — tulisan "Smile more!" memotong area transparan; EST dipakai.
6. **Movie Ticket** — slot dekat tepi tiket; pastikan foto tidak menutupi perforasi tiket.
7. Semua slot berbentuk (oval/hati/blob/polaroid) sudah punya mask dan tampak halus di preview; tetap cek di browser nyata untuk anti-aliasing tepi.

## Cara menguji

1. Buka `/camera`, isi biodata, pilih format **1x3 (3 foto)** untuk varian Kiri/Kanan, atau **2x3** untuk Gabungan. Jepret 3 / 6 foto.
2. Lanjut ke `/desain`, pilih desain + varian (Kiri / Kanan / Gabungan). Opsi cetak akan muncul: Satu strip, Dua strip sama, atau Gabungan asli.
3. Tambahkan `?debug=1` ke URL `/desain?debug=1` atau `/camera?debug=1` untuk menampilkan kotak merah di bounding box slot (default mati).
4. Periksa preview: foto pas di slot, dekorasi di atas foto, bentuk oval/hati/miring benar, tanpa halo putih.
5. Simpan → halaman hasil: unduh PNG/JPG, cetak, bagikan. Hasil disimpan resolusi asli (600×1800 atau 1200×1800).

## Catatan perbaikan

- Versi transparan awal keliru menghapus **kotak persegi penuh** pada slot non-rect (hati/oval/blob/polaroid), sehingga bentuknya rusak. Sudah diperbaiki: sekarang **hanya area transparan asli yang dilebarkan ~2 px** mengikuti kontur bentuk (lihat `scripts/fix_transparent.py`). Preview sudah diregenerasi dan diverifikasi: hati, oval, blob, dan polaroid miring mengikuti bentuk dengan benar, dekorasi (pita, bunga, klip, bintang) tetap tampil di atas foto, tanpa halo putih.
- File `scripts/detect_slots.py` sudah disesuaikan agar tidak mengulang bug tersebut jika dijalankan ulang.

## Asumsi

- File frame yang dilampirkan ternyata **sudah transparan di area slot** (alpha 0), bukan putih seperti yang disebut di permintaan. Deteksi dilakukan dengan flood fill pada piksel transparan (alpha<30) — lebih akurat daripada piksel putih. Versi "transparan" dibuat dengan membersihkan tepi lubang 2 px (menghilangkan halo anti-aliasing).
- Satu file = satu template DB dengan `layout_type = 2x3`; varian Kiri/Kanan/Gabung disimpan di dalam field `slots.variants`.
- Format kamera `1x3` ditambahkan untuk mengambil 3 foto (satu strip). `2x3` tetap untuk 6 foto (gabungan).
- Template lama (tanpa `slots.variants`) tetap memakai jalur kode lama — tidak diubah perilakunya.
