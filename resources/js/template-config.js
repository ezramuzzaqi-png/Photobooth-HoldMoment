// Template config based system - each template defines its slots as ratios
// Satuan: semua koordinat dan dimensi dalam rasio 0-1 terhadap ukuran canvas
// Setiap template memiliki 6 slot (3 kiri + 3 kanan) untuk perilaku duplikasi 3 foto ke 2 strip

// Format: { id, nama, kategori, frame, thumbnail, canvas: {width, height}, slots: [{x, y, w, h, shape?}] }
// - frame: path transparan slot (alpha 0 di area slot), dekorasi di luar slot tetap utuh
// - canvas: ukuran asli frame (dipakai untuk merender hasil akhir, TIDAK ADA hardcode 1181x1748 di kode)
// - slots: semua rasio 0-1 terhadap canvas width/height
// - shape: opsional, untuk slot bukan persegi panjang (oval, hati, blob, polaroid)

// Template akan diisi oleh pipeline di Langkah 3
// Untuk saat ini, contoh dengan struktur kosong

export const TEMPLATES = [];