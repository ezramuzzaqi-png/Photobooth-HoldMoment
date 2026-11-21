{{-- Mesin gambar strip (shared): dipakai halaman camera + halaman pilih desain.
 Syarat dari halaman pemanggil (di dalam IIFE script yang sama):
   - const state = { layout, filter, templateId, builtin, shots, visitor:{name,social}, ... }
   - const TEMPLATES = [...] dari database (id, name, layout_type, frame_url, transparent_url,
     background_url, slots[{x,y,w,h,shape}] sebagai rasio 0-1 terhadap gambar frame utuh)
   - const stripPreview = <canvas> (untuk drawStripPreview)
--}}
    // Cache lama (dibiarkan agar halaman lain yang merujuknya tidak error)
    const frameCache = {};
    const transparentFrameCache = {};
    const backgroundCache = {};
    const detectedCache = {};
    function invalidateDetected(id) {
        Object.keys(detectedCache).forEach(k => { if (k.startsWith(id + '_')) delete detectedCache[k]; });
    }

    // ===== TEMPLATE DATABASE: frame + slot (rasio) =====
    // Satu file frame = satu template. Foto i mengisi slot i (urutan array slots).
    // Urutan layer: frame asli (latar) -> foto di slot -> frame transparan paling atas.
    const dbLoaded = {}, dbPromises = {};

    function isDbTpl(t) {
        return !!(t && Array.isArray(t.slots) && t.slots.length > 0
            && t.slots[0] && typeof t.slots[0] === 'object' && t.frame_url);
    }

    function loadImg(src) {
        return new Promise(res => {
            if (!src) return res(null);
            const im = new Image();
            im.onload = () => res(im);
            im.onerror = () => { console.error('Gambar gagal dimuat (404?):', src); res(null); };
            im.src = src;
        });
    }

    function dbReady(t) {
        if (!isDbTpl(t)) return Promise.resolve();
        if (!dbPromises[t.id]) {
            dbPromises[t.id] = Promise.all([loadImg(t.frame_url), loadImg(t.transparent_url)])
                .then(([frame, trans]) => { dbLoaded[t.id] = { frame, trans }; });
        }
        return dbPromises[t.id];
    }

    function paintDbStrip(canvas, W, imgs, tpl) {
        const d = dbLoaded[tpl.id] || {};
        const ref = d.trans || d.frame;
        const ctx = canvas.getContext('2d');
        if (!ref) {
            // Frame belum/tidak bisa dimuat: tampilkan placeholder netral
            canvas.width = W;
            canvas.height = Math.round(W * 1.5);
            ctx.fillStyle = '#EDE9E1';
            ctx.fillRect(0, 0, W, canvas.height);
            return;
        }
        const H = Math.round(W * ref.naturalHeight / ref.naturalWidth);
        canvas.width = W;
        canvas.height = H;
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, W, H);
        if (d.frame) ctx.drawImage(d.frame, 0, 0, W, H); // latar (dekorasi)
        const bleed = Math.max(1, Math.round(2 * W / ref.naturalWidth));
        tpl.slots.forEach((s, i) => { // foto i -> slot i
            const im = imgs[i];
            if (!im) return;
            drawCover(ctx, im, s.x * W - bleed, s.y * H - bleed, s.w * W + bleed * 2, s.h * H + bleed * 2, 0);
        });
        if (d.trans) ctx.drawImage(d.trans, 0, 0, W, H); // frame transparan paling atas
        if (window.__debug) {
            tpl.slots.forEach((s, i) => {
                ctx.save();
                ctx.strokeStyle = '#ff0000';
                ctx.lineWidth = 2;
                ctx.strokeRect(s.x * W, s.y * H, s.w * W, s.h * H);
                ctx.fillStyle = '#ff0000';
                ctx.font = '16px Arial';
                ctx.fillText('S' + (i + 1), s.x * W + 4, s.y * H + 20);
                ctx.restore();
            });
        }
    }

    function selectedTemplate() {
        return TEMPLATES.find(t => String(t.id) === String(state.templateId)) || null;
    }

    const LAYOUTS = {
        '2x2': { cols: 2, rows: 2, total: 4 },
        '2x3': { cols: 2, rows: 3, total: 6 },
        '1x3': { cols: 1, rows: 3, total: 3 },
        'receipt': { cols: 2, rows: 2, total: 4 }, // 2 strip kembar, tiap strip 1 kolom × 2 foto
        'receipt_2x3': { cols: 2, rows: 3, total: 6 }, // 2 strip kembar, tiap strip 1 kolom × 3 foto
    };

    // Dipertahankan untuk kompatibilitas halaman lain; template database tidak memakainya.
    function slottedTemplate() { return null; }

    function requiredTotal() {
        const t = selectedTemplate();
        if (isDbTpl(t)) return t.slots.length;
        return LAYOUTS[state.layout] ? LAYOUTS[state.layout].total : 6;
    }

    // ===== Mesin gambar strip (dipakai preview & file simpanan) =====
    // Pola barcode deterministik agar preview dan hasil simpan identik
    const BARCODE_BARS = (() => {
        let seed = 20260915;
        const rnd = () => (seed = (seed * 1103515245 + 12345) & 0x7fffffff) / 0x7fffffff;
        const bars = [];
        for (let i = 0; i < 64; i++) bars.push(1 + Math.floor(rnd() * 4));
        return bars;
    })();

    function isReceipt() { return state.builtin === 'receipt'; }

    // Geometri strip untuk lebar W (s = skala, preview 600px / simpan 900px)
    function geomFor(W) {
        const s = W / 600;
        if (state.layout === 'receipt' || state.layout === 'receipt_2x3') {
            const rows = LAYOUTS[state.layout].rows;
            const pad = 16 * s, gutter = 24 * s, ip = 14 * s, gap = 12 * s;
            const colW = (W - pad * 2 - gutter) / 2;
            const cellW = colW - ip * 2;
            const cellH = cellW * 0.75;
            const headerH = 250 * s, footerH = 150 * s;
            const H = pad + headerH + cellH * rows + gap * (rows - 1) + footerH + pad;
            return { double: true, cols: 2, rows, s, pad, gutter, ip, gap, colW, cellW, cellH, headerH, footerH, radius: 3 * s, H };
        }
        const { cols, rows } = LAYOUTS[state.layout];
        const pad = 20 * s, gap = 12 * s;
        const cellW = (W - pad * 2 - gap * (cols - 1)) / cols;
        const cellH = cellW * 0.75;
        const headerH = 110 * s, footerH = 90 * s;
        const H = headerH + rows * cellH + (rows - 1) * gap + footerH + pad * 2;
        return { double: false, cols, rows, s, pad, gap, cellW, cellH, headerH, footerH, radius: 12 * s, H };
    }

    function dashedLine(ctx, x1, x2, y, s) {
        ctx.save();
        ctx.strokeStyle = '#1C1C1C';
        ctx.lineWidth = Math.max(1, 1.5 * s);
        ctx.setLineDash([6 * s, 4 * s]);
        ctx.beginPath();
        ctx.moveTo(x1, y);
        ctx.lineTo(x2, y);
        ctx.stroke();
        ctx.restore();
    }

    // Kecilkan font sampai teks muat di maxW (jamin tidak meluber keluar kolom)
    function fitFont(ctx, text, maxW, startPx, family, weight) {
        let px = startPx;
        const apply = (p) => { ctx.font = (weight ? weight + ' ' : '') + p + 'px ' + family; };
        apply(px);
        while (px > 6 && ctx.measureText(text).width > maxW) {
            px -= 1;
            apply(px);
        }
        return px;
    }

    // Kop satu kolom struk (dipakai tiap strip kembar kiri & kanan)
    function paintReceiptHead(ctx, x0, colW, topY, g) {
        const s = g.s, ip = g.ip, cx = x0 + colW / 2;
        const qtyX = x0 + ip + 4 * s;          // awal kolom QTY
        const itemX = x0 + ip + 82 * s;        // awal kolom ITEM
        const rightEdge = x0 + colW - ip - 4 * s;
        const qtyCX = qtyX + 34 * s;           // tengah kolom QTY
        ctx.fillStyle = '#111';
        ctx.textAlign = 'center';
        fitFont(ctx, '*****RECEIPT*****', colW - ip * 2 - 4 * s, 40 * s, '"Playfair Display", Georgia, serif', '800');
        ctx.fillText('*****RECEIPT*****', cx, topY + 46 * s);
        dashedLine(ctx, x0 + ip, x0 + colW - ip, topY + 62 * s, s);
        ctx.font = (22 * s) + 'px "Courier New", monospace';
        ctx.fillText('QTY', qtyCX, topY + 88 * s);
        ctx.textAlign = 'left';
        ctx.fillText('ITEM', itemX, topY + 88 * s);
        ctx.textAlign = 'center';
        dashedLine(ctx, x0 + ip, x0 + colW - ip, topY + 102 * s, s);
        const rows = [
            ['7', 'HOURS A DAY THINKING ABOUT YOU'],
            ['24', 'DAYS IN A WEEK'],
            ['365', 'TIRED ALL THE TIME'],
        ];
        rows.forEach((r, i) => {
            const y = topY + (132 + i * 34) * s;
            fitFont(ctx, r[1], rightEdge - itemX, 19 * s, '"Courier New", monospace', '');
            ctx.textAlign = 'center';
            ctx.fillText(r[0], qtyCX, y);
            ctx.textAlign = 'left';
            ctx.fillText(r[1], itemX, y);
        });
        ctx.textAlign = 'center';
    }

    // Latar + kop: cream HOLD' MOMENT atau kertas struk (tunggal/ganda)
    function paintChrome(ctx, W, g) {
        const s = g.s, pad = g.pad, cx = W / 2;
        ctx.fillStyle = g.double ? '#FBFAF6' : '#F7F5F0';
        ctx.fillRect(0, 0, W, g.H);
        if (g.double) {
            for (let k = 0; k < 2; k++) {
                paintReceiptHead(ctx, g.pad + k * (g.colW + g.gutter), g.colW, g.pad, g);
            }
            return;
        }
        ctx.fillStyle = '#1C1917';
        ctx.font = '800 ' + (34 * s) + 'px "Playfair Display", Georgia, serif';
        ctx.textAlign = 'center';
        ctx.fillText("HOLD' MOMENT", cx, 55 * s);
        ctx.fillStyle = '#BE4A1C';
        ctx.font = '600 ' + (14 * s) + 'px "Plus Jakarta Sans", sans-serif';
        ctx.fillText('• • •  PHOTOSTRIP  • • •', cx, 80 * s);
    }

    // Satu foto cover-fit ke sel.
    // Jika orientasi foto tidak cocok dengan lubang (mis. foto portrait di lubang landscape),
    // putar foto terlebih dahulu agar wajah tidak terpotong oleh cover-fit.
    function drawCover(ctx, im, x, y, w, h, r) {
        const cr = w / h;
        const imageIsLandscape = im.width >= im.height;
        const holeIsLandscape = w >= h;
        let src = im;
        let srcW = im.width, srcH = im.height;
        if (imageIsLandscape !== holeIsLandscape) {
            const rot = document.createElement('canvas');
            rot.width = im.height;
            rot.height = im.width;
            const rctx = rot.getContext('2d');
            if (!imageIsLandscape) {
                // Foto portrait → lubang landscape: putar 90° searah jarum jam
                rctx.translate(im.height, 0);
                rctx.rotate(Math.PI / 2);
            } else {
                // Foto landscape → lubang portrait: putar 90° berlawanan jarum jam
                rctx.rotate(-Math.PI / 2);
                rctx.translate(0, -im.width);
            }
            rctx.drawImage(im, 0, 0);
            src = rot;
            srcW = im.height;
            srcH = im.width;
        }
        let sw, sh, sx, sy;
        const nir = srcW / srcH;
        if (nir > cr) { sh = srcH; sw = sh * cr; sx = (srcW - sw) / 2; sy = 0; }
        else { sw = srcW; sh = sw / cr; sx = 0; sy = (srcH - sh) / 2; }
        ctx.save();
        ctx.beginPath();
        ctx.roundRect(x, y, w, h, r);
        ctx.clip();
        ctx.drawImage(src, sx, sy, sw, sh, x, y, w, h);
        ctx.restore();
    }

    // Sel foto — strip ganda: tiap strip 1 kolom × g.rows foto; slot kosong = kertas
    function paintPhotos(ctx, g, imgs) {
        if (g.double) {
            for (let k = 0; k < 2; k++) {
                for (let j = 0; j < g.rows; j++) {
                    const im = imgs[k * g.rows + j];
                    if (!im) continue;
                    const x = g.pad + k * (g.colW + g.gutter) + g.ip;
                    const y = g.pad + g.headerH + j * (g.cellH + g.gap);
                    drawCover(ctx, im, x, y, g.cellW, g.cellH, g.radius);
                }
            }
            return;
        }
        imgs.forEach((im, i) => {
            if (!im) return;
            const c = i % g.cols, r = Math.floor(i / g.cols);
            const x = g.pad + c * (g.cellW + g.gap);
            const y = g.headerH + r * (g.cellH + g.gap);
            drawCover(ctx, im, x, y, g.cellW, g.cellH, g.radius);
        });
    }

    function paintBarcode(ctx, cx, y, h, s, maxW) {
        const sum = BARCODE_BARS.reduce((a, b) => a + b, 0) + BARCODE_BARS.length - 1;
        let unit = 2.2 * s;
        if (maxW) unit = Math.min(unit, maxW / sum);
        const totalW = sum * unit;
        let x = cx - totalW / 2;
        ctx.fillStyle = '#111';
        BARCODE_BARS.forEach(w => {
            ctx.fillRect(x, y, w * unit, h);
            x += (w + 1) * unit;
        });
    }

    // Kaki strip: biodata pengunjung atau THANK YOU + barcode tiap strip kembar
    function paintFooter(ctx, W, g) {
        const s = g.s, cx = W / 2, H = g.H;
        ctx.textAlign = 'center';
        if (g.double) {
            for (let k = 0; k < 2; k++) {
                const ccx = g.pad + k * (g.colW + g.gutter) + g.colW / 2;
                const fy = g.pad + g.headerH + g.cellH * g.rows + g.gap * (g.rows - 1);
                ctx.fillStyle = '#111';
                fitFont(ctx, '*******THANK YOU!*******', g.colW - g.ip * 2 - 4 * s, 22 * s, '"Courier New", monospace', '');
                ctx.fillText('*******THANK YOU!*******', ccx, fy + 30 * s);
                paintBarcode(ctx, ccx, fy + 44 * s, 62 * s, s, g.colW - g.ip * 2);
                ctx.fillStyle = '#66604F';
                ctx.font = '500 ' + (14 * s) + 'px "Plus Jakarta Sans", sans-serif';
                ctx.fillText((state.visitor.name || 'Pengunjung') + ' • ' + (state.visitor.social || '@sosmed'), ccx, fy + 128 * s);
            }
            return;
        }
        ctx.fillStyle = '#1C1917';
        ctx.font = '700 ' + (20 * s) + 'px "Plus Jakarta Sans", sans-serif';
        ctx.fillText(state.visitor.name || 'Pengunjung', cx, H - 55 * s);
        ctx.fillStyle = '#66604F';
        ctx.font = '500 ' + (15 * s) + 'px "Plus Jakarta Sans", sans-serif';
        ctx.fillText((state.visitor.social || '@sosmed') + '  •  ' + new Date().toLocaleDateString('id-ID'), cx, H - 30 * s);
    }

    // Frame overlay untuk jalur non-database (cache lama). Template database
    // memakai paintDbStrip, jadi fungsi ini hanya berpengaruh bila cache terisi.
    function paintFrameOverlay(ctx, W, H) {
        const fr = state.templateId ? frameCache[state.templateId] : null;
        if (fr && fr.complete && fr.naturalWidth) {
            ctx.drawImage(fr, 0, 0, W, H);
        }
    }

    function paintStrip(canvas, W, imgs) {
        // Template database: frame utuh + slot rasio, jalur render sendiri.
        const dbTpl = selectedTemplate();
        if (isDbTpl(dbTpl) && state.builtin !== 'receipt') {
            paintDbStrip(canvas, W, imgs, dbTpl);
            return;
        }
        // Polos / Receipt (prosedural)
        const g = geomFor(W);
        canvas.width = W;
        canvas.height = g.H;
        const ctx = canvas.getContext('2d');
        const bg = state.templateId ? backgroundCache[state.templateId] : null;
        const hasBg = bg && bg.complete && bg.naturalWidth;
        if (hasBg) {
            ctx.drawImage(bg, 0, 0, W, g.H);
        } else {
            paintChrome(ctx, W, g);
        }
        paintPhotos(ctx, g, imgs);
        if (!hasBg) paintFooter(ctx, W, g);
        paintFrameOverlay(ctx, W, g.H);
    }

    const loadShot = (src) => new Promise((res) => {
        const im = new Image();
        im.onload = () => res(im);
        im.onerror = () => res(null);
        im.src = src;
    });

    function drawStripPreview() {
        const t = selectedTemplate();
        const tid = state.templateId;
        // Tunggu frame template + semua foto selesai dimuat sebelum menggambar
        Promise.all([dbReady(t), Promise.all(state.shots.map(loadShot))]).then(([, imgs]) => {
            if (state.templateId !== tid) return; // user sudah pindah desain
            paintStrip(stripPreview, 600, imgs);
        });
    }

    function fullResStrip() {
        // Resolusi asli frame (template database) atau 900px (Polos / Receipt).
        const t = selectedTemplate();
        return Promise.all([dbReady(t), Promise.all(state.shots.map(loadShot))]).then(([, imgs]) => {
            const cv = document.createElement('canvas');
            let W = 900;
            if (isDbTpl(t) && state.builtin !== 'receipt') {
                const d = dbLoaded[t.id] || {};
                const ref = d.trans || d.frame;
                if (ref) W = ref.naturalWidth;
            }
            paintStrip(cv, W, imgs);
            return cv.toDataURL('image/jpeg', 0.85);
        });
    }