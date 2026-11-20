"""
Deteksi slot + buat versi frame transparan.

- Slot persegi (rect): pakai EST (bounding box estimasi) agar mencakup area slot
  lengkap (termasuk di balik teks/dekorasi yang akan muncul di atas foto via frame).
- Slot berbentuk (oval/hati/blob/polaroid): flood fill area transparan (alpha 0)
  di dalam bounding box, simpan mask bentuk.
- Semua koordinat disimpan sebagai RATIO terhadap ukuran canvas varian:
    kiri/kanan -> 600x1800, gabungan -> 1200x1800.
- Versi transparan (*-transparent.png): salinan file asli, dengan area slot
  (bbox diperlebar 2 px) di-set ke alpha 0 untuk menghilangkan halo tepi.
  Pembersihan pakai ImageDraw + channel alpha (cepat, tanpa loop piksel Python).
"""
import os, json, shutil
from PIL import Image, ImageFilter

SRC_DIR = "/home/romo/Downloads/drive-download-20261120T005519Z-1-001"
OUT_DIR = "/tmp/opencode/slot_output"
FRAME_DIR = "/home/romo/Project-Photobooth/public/storage/frames"
os.makedirs(OUT_DIR, exist_ok=True)
os.makedirs(FRAME_DIR, exist_ok=True)
W, H = 1200, 1800

FILES = [
    {"src": "IMG_7012.PNG", "name": "Denim",            "key": "denim",
     "left":  ["blob", "blob", "blob"],
     "right": ["blob", "blob", "blob"]},
    {"src": "IMG_7007.PNG", "name": "Lace & Brown",     "key": "lace-brown",
     "left":  ["rect", "rect", "rect"],
     "right": ["oval", "oval", "oval"]},
    {"src": "IMG_7011.PNG", "name": "Pink Plaid",       "key": "pink-plaid",
     "left":  ["polaroid", "polaroid", "polaroid"],
     "right": ["polaroid", "polaroid", "polaroid"]},
    {"src": "IMG_7008.PNG", "name": "Movie Ticket",     "key": "movie-ticket",
     "left":  ["rect", "rect", "rect"],
     "right": ["rect", "rect", "rect"]},
    {"src": "IMG_7009.PNG", "name": "Music Player",     "key": "music-player",
     "left":  ["rect", "rect", "rect"],
     "right": ["rect", "rect", "rect"]},
    {"src": "IMG_7005.PNG", "name": "Green Smile",      "key": "green-smile",
     "left":  ["rect", "rect", "rect"],
     "right": ["oval", "oval", "oval"]},
    {"src": "IMG_7004.PNG", "name": "Pinky Promise",    "key": "pinky-promise",
     "left":  ["rect", "rect", "rect"],
     "right": ["oval", "oval", "oval"]},
    {"src": "IMG_7006.PNG", "name": "Red Hearts",       "key": "red-hearts",
     "left":  ["heart", "heart", "heart"],
     "right": ["rect", "rect", "rect"]},
    {"src": "IMG_7003.PNG", "name": "Blue Growing",     "key": "blue-growing",
     "left":  ["rect", "rect", "rect"],
     "right": ["rect", "rect", "rect"]},
    {"src": "IMG_7010.PNG", "name": "Receipt",          "key": "receipt",
     "left":  ["rect", "rect", "rect"],
     "right": ["rect", "rect", "rect"]},
    {"src": "1.png", "name": "Template-1",              "key": "template-1",
     "left":  ["rect", "rect", "rect"],
     "right": ["rect", "rect", "rect"]},
    {"src": "5.png", "name": "Template-5",              "key": "template-5",
     "left":  ["rect", "rect", "rect"],
     "right": ["rect", "rect", "rect"]},
    {"src": "6.png", "name": "Template-6",              "key": "template-6",
     "left":  ["rect", "rect", "rect"],
     "right": ["rect", "rect", "rect"]},
    {"src": "7.png", "name": "Template-7",              "key": "template-7",
     "left":  ["rect", "rect", "rect"],
     "right": ["rect", "rect", "rect"]},
    {"src": "8.png", "name": "Template-8",              "key": "template-8",
     "left":  ["rect", "rect", "rect"],
     "right": ["rect", "rect", "rect"]},
]

EST = {

    "template-1":  {"left":  [(100,200,500,400),(100,600,500,400),(100,1000,500,400)],
                        "right": [(600,200,500,400),(600,600,500,400),(600,1000,500,400)]},
    "template-5":  {"left":  [(120,200,500,400),(120,600,500,400),(120,1000,500,400)],
                        "right": [(700,200,500,400),(700,600,500,400),(700,1000,500,400)]},
    "template-6":  {"left":  [(110,200,500,400),(110,600,500,400),(110,1000,500,400)],
                        "right": [(690,200,500,400),(690,600,500,400),(690,1000,500,400)]},
    "template-7":  {"left":  [(105,200,500,400),(105,600,500,400),(105,1000,500,400)],
                        "right": [(695,200,500,400),(695,600,500,400),(695,1000,500,400)]},
    "template-8":  {"left":  [(115,200,500,400),(115,600,500,400),(115,1000,500,400)],
                        "right": [(705,200,500,400),(705,600,500,400),(705,1000,500,400)]},

    "denim":           {"left":  [(85,230,430,320),(85,670,430,320),(85,1115,430,320)],
                        "right": [(685,230,430,320),(685,670,430,320),(685,1115,430,320)]},
    "lace-brown":      {"left":  [(70,180,460,410),(70,637,460,411),(70,1095,460,410)],
                        "right": [(643,190,509,400),(643,620,509,402),(643,1050,509,402)]},
    "pink-plaid":      {"left":  [(80,235,430,300),(80,637,430,298),(80,1040,430,310)],
                        "right": [(685,235,425,305),(685,637,425,298),(685,1035,425,315)]},
    "movie-ticket":    {"left":  [(60,230,481,339),(60,598,481,339),(60,967,481,339)],
                        "right": [(661,230,478,338),(661,599,478,338),(661,967,478,338)]},
    "music-player":    {"left":  [(88,120,424,392),(88,558,424,392),(88,997,424,392)],
                        "right": [(688,120,424,392),(688,558,424,392),(688,997,424,392)]},
    "green-smile":     {"left":  [(66,245,459,398),(66,707,459,400),(66,1170,459,398)],
                        "right": [(668,275,462,350),(668,725,462,350),(668,1175,462,350)]},
    "pinky-promise":   {"left":  [(56,215,488,415),(56,664,488,417),(56,1113,488,415)],
                        "right": [(671,215,481,416),(671,655,481,416),(671,1093,481,416)]},
    "red-hearts":      {"left":  [(62,300,468,355),(62,735,468,355),(62,1170,468,355)],
                        "right": [(671,223,457,418),(671,691,457,418),(671,1158,457,418)]},
    "blue-growing":    {"left":  [(62,177,487,398),(62,608,487,398),(62,1038,487,398)]},
}

def is_transparent(px, thr=30):
    return px[3] < thr

def flood_fill_transparent(img, x0, y0, box, thr=30):
    px = img.load()
    x1, y1, x2, y2 = box
    x1 = max(0, x1); y1 = max(0, y1); x2 = min(W - 1, x2); y2 = min(H - 1, y2)
    if not (x1 <= x0 <= x2 and y1 <= y0 <= y2):
        return set()
    if not is_transparent(px[x0, y0], thr):
        return set()
    visited = set()
    stack = [(x0, y0)]
    visited.add((x0, y0))
    while stack:
        x, y = stack.pop()
        for dx, dy in ((1,0),(-1,0),(0,1),(0,-1)):
            nx, ny = x + dx, y + dy
            if not (x1 <= nx <= x2 and y1 <= ny <= y2):
                continue
            if (nx, ny) in visited:
                continue
            if is_transparent(px[nx, ny], thr):
                visited.add((nx, ny))
                stack.append((nx, ny))
    return visited

def bbox_of(points):
    if not points:
        return None
    xs = [p[0] for p in points]; ys = [p[1] for p in points]
    return min(xs), min(ys), max(xs) - min(xs) + 1, max(ys) - min(ys) + 1

def make_mask(points, bbox):
    x, y, w, h = bbox
    mask = Image.new("L", (w, h), 0)
    mpx = mask.load()
    for (px_, py_) in points:
        if x <= px_ < x + w and y <= py_ < y + h:
            mpx[px_ - x, py_ - y] = 255
    return mask

def process_file(finfo):
    src = os.path.join(SRC_DIR, finfo["src"])
    img = Image.open(src).convert("RGBA")
    print(f"\n=== {finfo['name']} ({finfo['src']}) ===")

    result = {
        "file": finfo["src"], "name": finfo["name"], "key": finfo["key"],
        "size": [W, H],
        "note": "slot = transparent area (alpha 0). ratios vs variant canvas (600x1800 for kiri/kanan, 1200x1800 for both).",
        "variants": {},
    }

    # Simpan file frame asli
    dst_orig = os.path.join(FRAME_DIR, f"{finfo['key']}.png")
    shutil.copyfile(src, dst_orig)
    print(f"  frame asli -> {dst_orig}")

    trans = img.copy()

    for side in ("left", "right"):
        x_shift = 0 if side == "left" else W // 2
        slots_out = []
        holes_out = []
        for i, kind in enumerate(finfo[side]):
            est = EST[finfo["key"]][side][i]
            est_full = (est[0], est[1], est[2], est[3])

            bb = None; points = set()
            if kind in ("rect",):
                bb = est_full
                cx = est_full[0] + est_full[2] // 2
                cy = est_full[1] + est_full[3] // 2
                ff = flood_fill_transparent(img, cx, cy, est_full)
                points = ff
                print(f"  [{side}] slot {i+1} ({kind}): pakai EST {est_full} (ff_n={len(ff)})")
            else:
                cx = est_full[0] + est_full[2] // 2
                cy = est_full[1] + est_full[3] // 2
                ex = max(0, est_full[0] - 6); ey = max(0, est_full[1] - 6)
                ex2 = min(W, est_full[0] + est_full[2] + 6); ey2 = min(H, est_full[1] + est_full[3] + 6)
                box = (ex, ey, ex2, ey2)
                points = flood_fill_transparent(img, cx, cy, box)
                if not points:
                    for (px_, py_) in ((cx, cy),
                                       (est_full[0]+est_full[2]//2, est_full[1]),
                                       (est_full[0]+est_full[2]//2, est_full[1]+est_full[3]-1),
                                       (est_full[0], est_full[1]+est_full[3]//2),
                                       (est_full[0]+est_full[2]-1, est_full[1]+est_full[3]//2)):
                        points = flood_fill_transparent(img, px_, py_, box)
                        if points:
                            break
                if points:
                    bb = bbox_of(points)
                print(f"  [{side}] slot {i+1} ({kind}): ff bbox={bb} (n={len(points)}) est={est_full}")

            if bb is None:
                bb = est_full
                points = set()
                print(f"  [{side}] slot {i+1} ({kind}): GAGAL, pakai EST {est_full}")

            # Slot untuk canvas varian (geser x untuk kanan)
            bb_shifted = (bb[0] - x_shift, bb[1], bb[2], bb[3])
            print(f"  [{side}] slot {i+1}: bbox_shifted={bb_shifted}")

            slots_out.append({
                "x": round(bb_shifted[0] / (W // 2), 6), "y": round(bb_shifted[1] / H, 6),
                "w": round(bb_shifted[2] / (W // 2), 6), "h": round(bb_shifted[3] / H, 6),
                "kind": kind, "side": side, "slot": i + 1,
            })
            holes_out.append([bb_shifted[0], bb_shifted[1], bb_shifted[2], bb_shifted[3]])


        result["variants"][side] = {
            "offset_x": x_shift, "canvas_w": W // 2, "canvas_h": H,
            "slots": slots_out, "holes_px": holes_out,
        }

    # Varian gabungan (keduanya): rasio vs canvas 1200 px
    both_slots = []
    both_holes = []
    for s in result["variants"]["left"]["slots"]:
        s2 = dict(s)
        s2["x"] = round(s["x"] / 2, 6)
        s2["w"] = round(s["w"] / 2, 6)
        both_slots.append(s2)
    for h in result["variants"]["left"]["holes_px"]:
        both_holes.append(list(h))
    for s in result["variants"]["right"]["slots"]:
        s2 = dict(s)
        s2["x"] = round(0.5 + s["x"] / 2, 6)
        s2["w"] = round(s["w"] / 2, 6)
        both_slots.append(s2)
    for h in result["variants"]["right"]["holes_px"]:
        both_holes.append([h[0] + 600, h[1], h[2], h[3]])

    result["variants"]["both"] = {
        "canvas_w": W, "canvas_h": H,
        "slots": both_slots, "holes_px": both_holes,
    }

    # Perluas area transparan asli 2 px (mengikuti bentuk) untuk hilangkan halo,
    # lalu terapkan ke alpha. Dekorasi yang menimpa slot tetap ada.
    alpha = trans.getchannel("A").copy()
    hole = alpha.point(lambda a: 255 if a < 30 else 0)
    dilated = hole.filter(ImageFilter.MaxFilter(5))
    black = Image.new("L", (W, H), 0)
    alpha_new = Image.composite(black, alpha, dilated)
    trans.putalpha(alpha_new)

    dst_trans = os.path.join(FRAME_DIR, f"{finfo['key']}-transparent.png")
    trans.save(dst_trans)
    print(f"  frame transparan -> {dst_trans}")

    out_path = os.path.join(OUT_DIR, f"{finfo['key']}-slots.json")
    with open(out_path, "w") as f:
        json.dump(result, f, indent=2)
    print(f"  config -> {out_path}")
    return result

if __name__ == "__main__":
    all_results = {}
    for finfo in FILES:
        all_results[finfo["key"]] = process_file(finfo)

    print("\n===== RINGKASAN RASIO SLOT (per varian) =====")
    for finfo in FILES:
        key = finfo["key"]; r = all_results[key]
        print(f"\n--- {r['name']} ({key}) ---")
        for side in ("left", "right", "both"):
            v = r["variants"][side]
            print(f"  {side}: {len(v['slots'])} slot")
            for s in v["slots"]:
                ar = round(s["w"] / s["h"], 3) if s["h"] else 0
                print(f"    s{s['slot']} {s['kind']}: x={s['x']} y={s['y']} w={s['w']} h={s['h']} ar={ar}")

    with open(os.path.join(OUT_DIR, "all-slots.json"), "w") as f:
        json.dump(all_results, f, indent=2)
    print("\nSelesai. Output:", OUT_DIR)
    print("Frame disimpan di:", FRAME_DIR)
