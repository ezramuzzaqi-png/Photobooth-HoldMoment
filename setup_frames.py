#!/usr/bin/env python3
# Jalankan dari root proyek Laravel:
#   python3 setup_frames.py           -> simulasi (tidak menulis DB)
#   python3 setup_frames.py --apply   -> tulis slot ke database
import sys, json, shutil, subprocess
from pathlib import Path
import numpy as np
from PIL import Image
from scipy import ndimage as ndi

APPLY = "--apply" in sys.argv
SKIPDB = "--skip-db" in sys.argv
SLUGS = ["blue-vibes","cozy-chapters","crimson-fade","daisy-dreams","denim-dreams",
         "little-green-days","love-letter","pinky-promise","the-one-that-got-away",
         "receipt-v2","picnic-tomato","retro-dekstop","spring-bunny","pop-art-comic"]
CLEAN = {"picnic-tomato","retro-dekstop","spring-bunny"}   # punya sisa hijau chroma
THUMB_W = 400
SRC, DST = Path("public/source-frames"), Path("storage/app/public/frames")
assert SRC.is_dir() and DST.is_dir(), "Jalankan dari root proyek Laravel."
BAK = DST / "_backup"; BAK.mkdir(exist_ok=True)

def clean(a):
    r,g,b,al = [a[...,i].astype(int) for i in range(4)]
    near = ndi.binary_dilation(al < 128, iterations=3)
    pure = (al >= 128) & (g >= 200) & (r <= 110) & (b <= 110)
    fringe = (al >= 128) & near & (g >= 170) & (g - np.maximum(r, b) >= 45)
    clear = (pure & near) | fringe
    a = a.copy(); a[clear, 3] = 0
    rest = pure & ~clear
    if rest.any():
        valid = (a[...,3] >= 128) & ~rest & ~((a[...,1] >= 200) & (a[...,0] <= 110) & (a[...,2] <= 110))
        idx = ndi.distance_transform_edt(~valid, return_distances=False, return_indices=True)
        a[rest, :3] = a[idx[0][rest], idx[1][rest], :3]
    return a

def slots_of(a, slug):
    H, W = a.shape[:2]
    t = a[...,3] < 128
    lab, n = ndi.label(t); sz = ndi.sum(t, lab, range(1, n + 1))
    boxes = []
    for i in range(1, n + 1):
        if sz[i-1] > W * H * 0.01:
            ys, xs = np.where(lab == i)
            boxes.append([int(xs.min()), int(ys.min()), int(xs.max()-xs.min()+1), int(ys.max()-ys.min()+1)])
    L = sorted([b for b in boxes if b[0]+b[2]/2 <  W/2], key=lambda b: b[1])
    R = sorted([b for b in boxes if b[0]+b[2]/2 >= W/2], key=lambda b: b[1])
    if len(boxes) != 6 or len(L) != 3 or len(R) != 3:
        return None
    if slug == "love-letter": L[2][3] = 350   # ekor tembus di pita
    order = [b for i in range(3) for b in (L[i], R[i])]
    return [{"x":round(x/W,6),"y":round(y/H,6),"w":round(w/W,6),"h":round(h/H,6),"shape":"rect"} for x,y,w,h in order]

files = {p.stem.lower(): p for p in SRC.iterdir() if p.suffix.lower() == ".png"}
result = {}
for slug in SLUGS:
    p = files.get(slug)
    if not p: print(f"[LEWAT] {slug}: tidak ada di {SRC}"); continue
    im = Image.open(p).convert("RGBA"); a = np.array(im)
    if a[...,3].min() != 0: print(f"[SKIP ] {slug}: belum transparan"); continue
    if slug in CLEAN: a = clean(a)
    sl = slots_of(a, slug)
    if sl is None: print(f"[SKIP ] {slug}: jumlah lubang tidak 6 (3 kiri + 3 kanan)"); continue
    out = Image.fromarray(a)
    for n in (f"{slug}.png", f"{slug}-transparent.png", f"{slug}-thumbnail.png"):
        if (DST/n).exists(): shutil.copy2(DST/n, BAK/n)
    out.save(DST/f"{slug}.png"); out.save(DST/f"{slug}-transparent.png")
    th = out.copy(); th.thumbnail((THUMB_W, 10000), Image.LANCZOS); th.save(DST/f"{slug}-thumbnail.png", optimize=True)
    result[slug] = {"slots": sl}
    print(f"[OK   ] {slug}: {out.size[0]}x{out.size[1]}{'  (hijau dibersihkan)' if slug in CLEAN else ''}")

jp = Path("slots-semua-template.json").resolve(); jp.write_text(json.dumps(result))
php = ("$all=json_decode(file_get_contents('%s'),true);$apply=%s;"
       "foreach($all as $slug=>$c){$q=DB::table('templates')->where('frame_image','frames/'.$slug.'.png');"
       "$r=$q->first(); if(!$r){echo '[TIDAK ADA] '.$slug.PHP_EOL;continue;}"
       "echo ($apply?'[UPDATE] ':'[DRY] ').'id='.$r->id.' '.$r->name.' layout='.$r->layout_type.PHP_EOL;"
       "if($apply){$q->update(['layout_type'=>'2x3','slots'=>json_encode($c['slots']),'updated_at'=>now()]);}}"
       ) % (jp, "true" if APPLY else "false")
if SKIPDB:
    print("\n(skip db)")
else:
    print("\n--- Database (" + ("APPLY" if APPLY else "simulasi") + ") ---")
    subprocess.run(["php", "artisan", "tinker", "--execute", php])
    print("\nSelesai. Jalankan: php artisan optimize:clear")
    if not APPLY:
        print("Kalau daftar di atas sudah benar, ulangi dengan: python3 setup_frames.py --apply")
