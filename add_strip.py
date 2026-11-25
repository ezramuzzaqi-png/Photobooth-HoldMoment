#!/usr/bin/env python3
# Tambah template baru (2x2 = 4 lubang, atau 2x3 = 6 lubang) dari satu PNG transparan.
# Jalankan dari root proyek Laravel:
#   python3 add_strip.py Frame.png "Nama Template"            -> simulasi
#   python3 add_strip.py Frame.png "Nama Template" --apply    -> tulis ke database
import sys, json, shutil, subprocess, tempfile
from pathlib import Path
import numpy as np
from PIL import Image
from scipy import ndimage as ndi

args = [a for a in sys.argv[1:] if not a.startswith("--")]
APPLY, SKIPDB = "--apply" in sys.argv, "--skip-db" in sys.argv
assert len(args) == 2, 'Pakai: python3 add_strip.py Frame.png "Nama Template" [--apply]'
src, name = Path(args[0]), args[1]
slug = src.stem.lower().replace(" ", "-").replace("_", "-")
DST, SRCDIR = Path("storage/app/public/frames"), Path("public/source-frames")
assert DST.is_dir(), "Jalankan dari root proyek Laravel."
THUMB_W = 400

a = np.array(Image.open(src).convert("RGBA")); H, W = a.shape[:2]
if a[..., 3].min() != 0:
    sys.exit("[GAGAL] Frame belum transparan (tidak ada piksel alpha=0). Lubang harus transparan.")

# bersihkan sisa hijau chroma di tepi lubang
r, g, b, al = [a[..., i].astype(int) for i in range(4)]
near = ndi.binary_dilation(al < 128, iterations=3)
pure = (al >= 128) & (g >= 200) & (r <= 110) & (b <= 110)
fringe = (al >= 128) & near & (g >= 170) & (g - np.maximum(r, b) >= 45)
clear = (pure & near) | fringe
a[clear, 3] = 0
rest = pure & ~clear
if rest.any():
    valid = (a[..., 3] >= 128) & ~rest & ~((a[..., 1] >= 200) & (a[..., 0] <= 110) & (a[..., 2] <= 110))
    idx = ndi.distance_transform_edt(~valid, return_distances=False, return_indices=True)
    a[rest, :3] = a[idx[0][rest], idx[1][rest], :3]

# deteksi lubang
t = a[..., 3] < 128
lab, n = ndi.label(t); sz = ndi.sum(t, lab, range(1, n + 1))
boxes = []
for i in range(1, n + 1):
    if sz[i - 1] > W * H * 0.01:
        ys, xs = np.where(lab == i)
        boxes.append([int(xs.min()), int(ys.min()), int(xs.max() - xs.min() + 1), int(ys.max() - ys.min() + 1)])
L = sorted([b for b in boxes if b[0] + b[2] / 2 < W / 2], key=lambda b: b[1])
R = sorted([b for b in boxes if b[0] + b[2] / 2 >= W / 2], key=lambda b: b[1])
if len(L) != len(R) or len(boxes) not in (4, 6):
    sys.exit(f"[GAGAL] Terdeteksi {len(boxes)} lubang ({len(L)} kiri, {len(R)} kanan). Harus 4 (2x2) atau 6 (2x3), dua kolom sama banyak.")
layout = "2x2" if len(boxes) == 4 else "2x3"
order = [b for i in range(len(L)) for b in (L[i], R[i])]
slots = [{"x": round(x / W, 6), "y": round(y / H, 6), "w": round(w / W, 6), "h": round(h / H, 6), "shape": "rect"} for x, y, w, h in order]
print(f"Frame {W}x{H}: {len(boxes)} lubang -> layout {layout}")
for i, (x, y, w, h) in enumerate(order, 1): print(f"  slot {i}: x={x} y={y} w={w} h={h}")

# simpan file
out = Image.fromarray(a)
bak = DST / "_backup"; bak.mkdir(exist_ok=True)
for fn in (f"{slug}.png", f"{slug}-transparent.png", f"{slug}-thumbnail.png"):
    if (DST / fn).exists(): shutil.copy2(DST / fn, bak / fn)
out.save(DST / f"{slug}.png"); out.save(DST / f"{slug}-transparent.png")
th = out.copy(); th.thumbnail((THUMB_W, 10000), Image.LANCZOS); th.save(DST / f"{slug}-thumbnail.png", optimize=True)
if SRCDIR.is_dir() and src.resolve().parent != SRCDIR.resolve():
    shutil.copyfile(src, SRCDIR / src.name)
print(f"[OK] file frame + thumbnail disimpan sebagai {slug}")

payload = Path(tempfile.mkstemp(suffix=".json")[1])
payload.write_text(json.dumps({"name": name, "slug": slug, "layout": layout, "slots": slots}))
php = ("$p=json_decode(file_get_contents('%s'),true);$apply=%s;"
       "$q=DB::table('templates')->where('frame_image','frames/'.$p['slug'].'.png');"
       "$d=['name'=>$p['name'],'layout_type'=>$p['layout'],'frame_image'=>'frames/'.$p['slug'].'.png',"
       "'slots'=>json_encode($p['slots']),'updated_at'=>now()];"
       "echo ($q->exists()?'[UPDATE] template sudah ada':'[BARU] template baru').': '.$p['name'].' ('.$p['layout'].')'.PHP_EOL;"
       "if($apply){ if($q->exists()){$q->update($d);} else {DB::table('templates')->insert($d+['created_at'=>now()]);} echo 'Tersimpan.'.PHP_EOL; }"
       ) % (payload, "true" if APPLY else "false")
if not SKIPDB:
    print("\n--- Database (" + ("APPLY" if APPLY else "simulasi") + ") ---")
    subprocess.run(["php", "artisan", "tinker", "--execute", php])
    print("\nLalu jalankan: php artisan optimize:clear")
    if not APPLY: print("Kalau sudah benar, ulangi dengan --apply di akhir perintah.")
