#!/usr/bin/env python3
"""Buat preview visual: foto mock (gradient) di bawah frame transparan."""
import os
from PIL import Image, ImageDraw

FDIR = "/home/romo/Project-Photobooth/public/storage/frames"
OUT = "/tmp/opencode/preview"
os.makedirs(OUT, exist_ok=True)

KEYS = ["denim","lace-brown","pink-plaid","movie-ticket","music-player",
        "green-smile","pinky-promise","red-hearts","blue-growing","receipt"]

def gradient(w, h, seed=0):
    img = Image.new("RGB", (w, h))
    px = img.load()
    colors = [(255,99,132),(54,162,235),(255,206,86),(75,192,192),(153,102,255)]
    c = colors[seed % len(colors)]
    for y in range(h):
        for x in range(w):
            px[x,y] = (
                int(c[0]*(0.4+0.6*x/w)),
                int(c[1]*(0.4+0.6*y/h)),
                int(c[2]*(0.4+0.6*(x+y)/(w+h))),
            )
    return img

for key in KEYS:
    frame = Image.open(os.path.join(FDIR, key + "-transparent.png")).convert("RGBA")
    photo = gradient(frame.width, frame.height, hash(key) % 5).convert("RGBA")
    # composite: foto di bawah, frame transparan di atas
    result = Image.alpha_composite(photo, frame).convert("RGB")
    for name, box in [("kiri",(0,0,600,1800)),("kanan",(600,0,1200,1800)),("gabungan",(0,0,1200,1800))]:
        crop = result.crop(box)
        crop.save(os.path.join(OUT, f"{key}-{name}.png"))
    print(key, "OK")

# Buat kontak sheet (semua varian kiri) untuk gambaran cepat
thumbs = []
for key in KEYS:
    im = Image.open(os.path.join(OUT, f"{key}-kiri.png")).resize((100,300))
    thumbs.append((key, im))
sheet = Image.new("RGB", (100*len(thumbs), 320), "white")
d = ImageDraw.Draw(sheet)
for i,(key,im) in enumerate(thumbs):
    sheet.paste(im,(i*100,20))
    d.text((i*100+2,3), key[:13], fill="black")
sheet.save(os.path.join(OUT, "sheet-kiri.png"))
print("sheet saved")
