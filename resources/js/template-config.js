// Template config based system - each template defines its slots as ratios
// Satuan: semua koordinat dan dimensi dalam rasio 0-1 terhadap ukuran canvas 600x1800
// Setiap template memiliki 3 slot (vertikal: atas-tengah-bawah) untuk layout 1x3 single strip
// Saat canvas width <= 700, render sebagai 1 strip 3 foto (berganding vertikal)

// Format: { id, nama, kategori, frame, thumbnail, canvas: {width, height}, slots: [{x, y, w, h, shape?}] }
// - frame: path transparan slot (alpha 0 di area slot), dekorasi di luar slot tetap utuh
// - canvas: ukuran asli frame dipakai untuk merender hasil akhir
// - slots: semua rasio 0-1 terhadap canvas width/height
// - shape: opsional, untuk slot bukan persegi panjang (oval, hati, blob, polaroid)

// Daftar template dari hasil deteksi slot + estimasi untuk sisa template
// Setiap template wajib punya tepat 3 slot, urut atas-bawah, y_slot_1 ≠ 0

window.CFG_TEMPLATES = [
  {
    id: 1,
    nama: "Blue-Vibes",
    kategori: "vibes",
    frame: "frames/blue-vibes.png",
    thumbnail: "frames/blue-vibes.png",
    canvas: {width: 600, height: 1800},
    slots: [
      {x: 0.138333, y: 0.127222, w: 0.721667, h: 0.178889, shape: "blob"},
      {x: 0, y: 0.394, w: 1, h: 0.178, shape: "blob"},
      {x: 0, y: 0.660, w: 1, h: 0.178, shape: "blob"}
    ]
  },
  {
    id: 2,
    nama: "Cozy-Chapters",
    kategori: "chapters",
    frame: "frames/cozy-chapters.png",
    thumbnail: "frames/cozy-chapters.png",
    canvas: {width: 600, height: 1800},
    slots: [
      {x: 0.116667, y: 0.1, w: 0.766667, h: 0.227778, shape: "rect"},
      {x: 0, y: 0.353889, w: 1, h: 0.228333, shape: "rect"},
      {x: 0, y: 0.608333, w: 1, h: 0.227778, shape: "rect"}
    ]
  },
  {
    id: 3,
    nama: "Crimson-Fade",
    kategori: "fade",
    frame: "frames/crimson-fade.png",
    thumbnail: "frames/crimson-fade.png",
    canvas: {width: 600, height: 1800},
    slots: [
      {x: 0.151667, y: 0.127222, w: 0.696667, h: 0.171667, shape: "polaroid"},
      {x: 0, y: 0.353889, w: 1, h: 0.165556, shape: "polaroid"},
      {x: 0, y: 0.576667, w: 1, h: 0.173889, shape: "polaroid"}
    ]
  },
  {
    id: 4,
    nama: "Daisy-Dreams",
    kategori: "dreams",
    frame: "frames/daisy-dreams.png",
    thumbnail: "frames/daisy-dreams.png",
    canvas: {width: 600, height: 1800},
    slots: [
      {x: 0.1, y: 0.127778, w: 0.801667, h: 0.188333, shape: "rect"},
      {x: 0, y: 0.332222, w: 1, h: 0.188333, shape: "rect"},
      {x: 0, y: 0.537222, w: 1, h: 0.188333, shape: "rect"}
    ]
  },
  {
    id: 5,
    nama: "Denim-Dreams",
    kategori: "dreams",
    frame: "frames/denim-dreams.png",
    thumbnail: "frames/denim-dreams.png",
    canvas: {width: 600, height: 1800},
    slots: [
      {x: 0.138333, y: 0.127222, w: 0.721667, h: 0.178889, shape: "blob"},
      {x: 0, y: 0.394, w: 1, h: 0.178, shape: "blob"},
      {x: 0, y: 0.660, w: 1, h: 0.178, shape: "blob"}
    ]
  },
  {
    id: 6,
    nama: "Little-Green-Days",
    kategori: "green",
    frame: "frames/little-green-days.png",
    thumbnail: "frames/little-green-days.png",
    canvas: {width: 600, height: 1800},
    slots: [
      {x: 0.146667, y: 0.066667, w: 0.706667, h: 0.217778, shape: "rect"},
      {x: 0, y: 0.31, w: 1, h: 0.217778, shape: "rect"},
      {x: 0, y: 0.553889, w: 1, h: 0.217778, shape: "rect"}
    ]
  },
  {
    id: 7,
    nama: "Love-Letter",
    kategori: "letter",
    frame: "frames/love-letter.png",
    thumbnail: "frames/love-letter.png",
    canvas: {width: 600, height: 1800},
    slots: [
      {x: 0.093333, y: 0.119444, w: 0.813333, h: 0.230556, shape: "rect"},
      {x: 0, y: 0.368889, w: 1, h: 0.231667, shape: "rect"},
      {x: 0, y: 0.618333, w: 1, h: 0.230556, shape: "rect"}
    ]
  },
  {
    id: 8,
    nama: "Movie-Ticket",
    kategori: "receipt",
    frame: "frames/movie-ticket.png",
    thumbnail: "frames/movie-ticket.png",
    canvas: {width: 600, height: 1800},
    slots: [
      {x: 0, y: 0.128, w: 1, h: 0.211, shape: "rect"},
      {x: 0, y: 0.332, w: 1, h: 0.211, shape: "rect"},
      {x: 0, y: 0.535, w: 1, h: 0.211, shape: "rect"}
    ]
  },
  {
    id: 9,
    nama: "Picnic-Tomato",
    kategori: "tomato",
    frame: "frames/picnic-tomato.png",
    thumbnail: "frames/picnic-tomato.png",
    canvas: {width: 600, height: 1800},
    slots: [
      {x: 0.1, y: 0.127778, w: 0.801667, h: 0.188333, shape: "rect"},
      {x: 0, y: 0.332222, w: 1, h: 0.188333, shape: "rect"},
      {x: 0, y: 0.537222, w: 1, h: 0.188333, shape: "rect"}
    ]
  },
  {
    id: 10,
    nama: "Pinky-Promise",
    kategori: "promise",
    frame: "frames/pinky-promise.png",
    thumbnail: "frames/pinky-promise.png",
    canvas: {width: 600, height: 1800},
    slots: [
      {x: 0.093333, y: 0.119444, w: 0.813333, h: 0.230556, shape: "rect"},
      {x: 0, y: 0.368889, w: 1, h: 0.231667, shape: "rect"},
      {x: 0, y: 0.618333, w: 1, h: 0.230556, shape: "rect"}
    ]
  },
  {
    id: 11,
    nama: "Pop-Art-Comic",
    kategori: "pop-art",
    frame: "frames/pop-art-comic.png",
    thumbnail: "frames/pop-art-comic.png",
    canvas: {width: 600, height: 1800},
    slots: [
      {x: 0, y: 0.128, w: 1, h: 0.278, shape: "rect"},
      {x: 0, y: 0.319, w: 1, h: 0.278, shape: "rect"},
      {x: 0, y: 0.728, w: 1, h: 0.278, shape: "rect"}
    ]
  },
  {
    id: 12,
    nama: "Receipt-V2",
    kategori: "receipt",
    frame: "frames/receipt-v2.png",
    thumbnail: "frames/receipt-v2.png",
    canvas: {width: 600, height: 1800},
    slots: [
      {x: 0.075, y: 0.216667, w: 0.85, h: 0.183333, shape: "rect"},
      {x: 0, y: 0.415556, w: 1, h: 0.182778, shape: "rect"},
      {x: 0, y: 0.613889, w: 1, h: 0.183333, shape: "rect"}
    ]
  },
  {
    id: 13,
    nama: "Retro-Dekstop",
    kategori: "retro",
    frame: "frames/retro-dekstop.png",
    thumbnail: "frames/retro-dekstop.png",
    canvas: {width: 600, height: 1800},
    slots: [
      {x: 0.1, y: 0.1, w: 1, h: 0.3, shape: "rect"},
      {x: 0, y: 0.45, w: 1, h: 0.3, shape: "rect"},
      {x: 0, y: 0.8, w: 1, h: 0.3, shape: "rect"}
    ]
  },
  {
    id: 14,
    nama: "Spring-Bunny",
    kategori: "bunny",
    frame: "frames/spring-bunny.png",
    thumbnail: "frames/spring-bunny.png",
    canvas: {width: 600, height: 1800},
    slots: [
      {x: 0.1, y: 0.1, w: 1, h: 0.3, shape: "rect"},
      {x: 0, y: 0.45, w: 1, h: 0.3, shape: "rect"},
      {x: 0, y: 0.8, w: 1, h: 0.3, shape: "rect"}
    ]
  },
  {
    id: 15,
    nama: "The-One-That-Got-Away",
    kategori: "away",
    frame: "frames/the-one-that-got-away.png",
    thumbnail: "frames/the-one-that-got-away.png",
    canvas: {width: 600, height: 1800},
    slots: [
      {x: 0, y: 0.1, w: 1, h: 0.3, shape: "rect"},
      {x: 0, y: 0.45, w: 1, h: 0.3, shape: "rect"},
      {x: 0, y: 0.8, w: 1, h: 0.3, shape: "rect"}
    }
];