#!/usr/bin/env python3
"""
Regenerasi frame transparan dengan benar:
- Area slot Sudah transparan (alpha 0) di file asli, mengikuti bentuk (oval/hati/blob/polaroid).
- Kita HANYA memperluas area transparan yang sudah ada ~2 px (MaxFilter) untuk
  menghilangkan halo anti-aliasing, TANPA mengubah bentuk menjadi persegi.
- Dekorasi (bintang, klip, pita, bunga) yang menimpa slot tetap dipertahankan.
"""
import os
from PIL import Image, ImageFilter

SRC_DIR = "/home/romo/Downloads/drive-download-20261120T005519Z-1-001"
FRAME_DIR = "/home/romo/Project-Photobooth/public/storage/frames"

FILES = [
    ("IMG_7012.PNG", "denim"), ("IMG_7007.PNG", "lace-brown"),
    ("IMG_7011.PNG", "pink-plaid"), ("IMG_7008.PNG", "movie-ticket"),
    ("IMG_7009.PNG", "music-player"), ("IMG_7005.PNG", "green-smile"),
    ("IMG_7004.PNG", "pinky-promise"), ("IMG_7006.PNG", "red-hearts"),
    ("IMG_7003.PNG", "blue-growing"), ("IMG_7010.PNG", "receipt"),
]

for src_name, key in FILES:
    src = os.path.join(SRC_DIR, src_name)
    img = Image.open(src).convert("RGBA")
    alpha = img.getchannel("A")

    # Area lubang = piksel yang sudah transparan (alpha < 30)
    hole = alpha.point(lambda a: 255 if a < 30 else 0)
    # Perluas 2 px (MaxFilter(5) = radius 2) agar halo tepi hilang
    dilated = hole.filter(ImageFilter.MaxFilter(5))

    # Alpha baru: 0 di area yang diperluas, alpha asli di tempat lain
    black = Image.new("L", img.size, 0)
    new_alpha = Image.composite(black, alpha, dilated)
    img.putalpha(new_alpha)

    dst = os.path.join(FRAME_DIR, key + "-transparent.png")
    img.save(dst)
    print(key, "->", dst)
