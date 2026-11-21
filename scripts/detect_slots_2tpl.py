"""
Deteksi slot untuk 2 template: Pop-Art-Comic dan Movie-Ticket.

- Slot gelap (R,G,B <= 5): deteksi via flood fill area gelap.
- Semua koordinat disimpan sebagai RATIO 0-1 terhadap ukuran frame PENUH (W x H).
- Urutan slot: pisahkan kiri/kanan berdasarkan center x vs W/2,
  urutkan tiap kolom dari atas ke bawah,
  susun selang-seling: kiri1, kanan1, kiri2, kanan2, kiri3, kanan3.
- Assert: tepat 3 slot di tiap kolom.
"""
import os, json, shutil
from PIL import Image, ImageFilter
import numpy as np
from scipy import ndimage

SRC_DIR = "/home/romo/Project-Photobooth/public/source-frames"
OUT_DIR = "/tmp/opencode/slot_output_2tpl"
FRAME_DIR = "/home/romo/Project-Photobooth/public/storage/frames"
os.makedirs(OUT_DIR, exist_ok=True)
os.makedirs(FRAME_DIR, exist_ok=True)

FILES = [
    {"src": "Pop-Art-Comic.png", "name": "Pop-Art-Comic", "key": "pop-art-comic"},
    {"src": "Movie-Ticket.PNG",  "name": "Movie-Ticket",   "key": "movie-ticket"},
]

DARK_THRESH = 5  # R,G,B <= 5 dianggap gelap


def detect_slots(img):
    """Deteksi area gelap menggunakan flood fill.

    Returns list of slot dicts with pixel coords (unsorted, interleaved setelah diproses).
    Filter: minimal area 5000 px, minimal height 50 px agar hanya slot utama yang diseleksi.
    """
    arr = np.array(img.convert("RGB"))
    r, g, b = arr[:, :, 0], arr[:, :, 1], arr[:, :, 2]
    dark_mask = (r <= DARK_THRESH) & (g <= DARK_THRESH) & (b <= DARK_THRESH)

    # Label connected components
    labeled, n_comp = ndimage.label(dark_mask)

    components = []
    for i in range(1, n_comp + 1):
        ys, xs = np.where(labeled == i)
        if len(xs) < 500:  # skip noise, minimal slot area
            continue
        x_min, x_max = xs.min(), xs.max()
        y_min, y_max = ys.min(), ys.max()
        w = x_max - x_min + 1
        h = y_max - y_min + 1
        area = len(xs)
        cx = (x_min + x_max) / 2
        cy = (y_min + y_max) / 2
        # Filter: slot harus cukup luas dan tinggi (bukan kecil seperti teks/garis)
        if area < 5000 or h < 50:
            continue
        components.append({
            "x": int(x_min), "y": int(y_min),
            "w": int(w), "h": int(h),
            "cx": float(cx), "cy": float(cy),
            "area": int(area),
        })

    return components


def interleave_slots(components, W, H):
    """Pisahkan kiri/kanan berdasarkan center x vs W/2,
    urutkan tiap kolom dari atas ke bawah,
    susun selang-seling: kiri1, kanan1, kiri2, kanan2, kiri3, kanan3.

    Assert: tepat 3 slot per kolom.
    """
    mid_x = W / 2
    left = [c for c in components if c["cx"] < mid_x]
    right = [c for c in components if c["cx"] >= mid_x]

    left.sort(key=lambda c: c["cy"])
    right.sort(key=lambda c: c["cy"])

    assert len(left) == 3, f"Expected 3 left slots, got {len(left)}: {[(s['y'], s['h']) for s in left]}"
    assert len(right) == 3, f"Expected 3 right slots, got {len(right)}: {[(s['y'], s['h']) for s in right]}"

    interleaved = []
    for i in range(3):
        interleaved.append(left[i])
        interleaved.append(right[i])

    return interleaved


def make_transparent(src_path, dst_path, slots_px, W, H):
    """Buat versi transparan: area slot (bbox diperlebar 2 px) di-set alpha 0."""
    img = Image.open(src_path).convert("RGBA")
    alpha = img.getchannel("A").copy()

    hole = Image.new("L", (W, H), 0)
    hp = hole.load()
    for s in slots_px:
        x0 = max(0, s["x"] - 2)
        y0 = max(0, s["y"] - 2)
        x1 = min(W - 1, s["x"] + s["w"] + 1)
        y1 = min(H - 1, s["y"] + s["h"] + 1)
        for yy in range(y0, y1 + 1):
            for xx in range(x0, x1 + 1):
                hp[xx, yy] = 255

    dilated = hole.filter(ImageFilter.MaxFilter(5))
    black = Image.new("L", (W, H), 0)
    alpha_new = Image.composite(black, alpha, dilated)
    img.putalpha(alpha_new)
    img.save(dst_path)
    print(f"  frame transparan -> {dst_path}")


def process_file(finfo):
    src = os.path.join(SRC_DIR, finfo["src"])
    img = Image.open(src).convert("RGBA")
    W, H = img.size
    print(f"\n=== {finfo['name']} ({finfo['src']}) size={W}x{H} ===")

    # Simpan file frame asli
    dst_orig = os.path.join(FRAME_DIR, f"{finfo['key']}.png")
    shutil.copyfile(src, dst_orig)
    print(f"  frame asli -> {dst_orig}")

    # Deteksi slot gelap
    components = detect_slots(img)
    print(f"  components detected: {len(components)}")
    for c in components:
        print(f"    x={c['x']} y={c['y']} w={c['w']} h={c['h']} cx={c['cx']:.0f} cy={c['cy']:.0f} area={c['area']}")

    # Interleave slots
    slots_px = interleave_slots(components, W, H)

    # Buat slot ratios 0-1 terhadap frame penuh
    slots_ratio = []
    for s in slots_px:
        slots_ratio.append({
            "x": round(s["x"] / W, 6),
            "y": round(s["y"] / H, 6),
            "w": round(s["w"] / W, 6),
            "h": round(s["h"] / H, 6),
            "shape": "rect",
        })

    print("  slot ratios (interleaved kiri1,kanan1,kiri2,kanan2,kiri3,kanan3):")
    for i, s in enumerate(slots_ratio):
        print(f"    s{i+1} {s['shape']}: x={s['x']} y={s['y']} w={s['w']} h={s['h']}")

    # Simpan config JSON
    out_path = os.path.join(OUT_DIR, f"{finfo['key']}-slots.json")
    with open(out_path, "w") as f:
        json.dump({
            "file": finfo["src"], "name": finfo["name"], "key": finfo["key"],
            "size": [W, H],
            "slots": slots_ratio,
        }, f, indent=2)
    print(f"  config -> {out_path}")

    # Buat frame transparan
    make_transparent(src, os.path.join(FRAME_DIR, f"{finfo['key']}-transparent.png"), slots_px, W, H)

    return slots_ratio


if __name__ == "__main__":
    all_results = {}
    for finfo in FILES:
        all_results[finfo["key"]] = process_file(finfo)

    print("\n===== RINGKASAN =====")
    for finfo in FILES:
        key = finfo["key"]
        r = all_results[key]
        print(f"\n--- {r['name']} ({key}) ---")
        print(f"  total slots: {len(r['slots'])}")
        for i, s in enumerate(r["slots"]):
            print(f"    s{i+1} {s['shape']}: x={s['x']} y={s['y']} w={s['w']} h={s['h']}")

    with open(os.path.join(OUT_DIR, "all-slots.json"), "w") as f:
        json.dump(all_results, f, indent=2)
    print("\nSelesai. Output:", OUT_DIR)
    print("Frame disimpan di:", FRAME_DIR)