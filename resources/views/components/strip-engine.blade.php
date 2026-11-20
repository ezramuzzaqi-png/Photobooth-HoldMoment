{{-- Mesin gambar strip (shared): dipakai halaman camera + halaman pilih desain.
 Syarat dari halaman pemanggil (di dalam IIFE script yang sama):
   - const state = { layout, filter, templateId, builtin, shots, visitor:{name,social}, ... }
   - const TEMPLATES = [...] (payload desain + slots + frame/background_url) -akan diisi dari config-
   - const stripPreview = <canvas> (untuk drawStripPreview)
--}}
    // Preload frame overlay & background agar preview langsung tampil saat desain dipilih
    const frameCache = {};
    const transparentFrameCache = {}; // versi transparan (slot = alpha 0), digambar paling atas
    const backgroundCache = {};
    const detectedCache = {}; // hasil ukur otomatis lubang per template (kunci: id_total)
    function invalidateDetected(id) {
        Object.keys(detectedCache).forEach(k => { if (k.startsWith(id + '_')) delete detectedCache[k]; });
    }

    // --- MEMUAT TEMPLATES DARI CONFIG FILE ---
    // TEMPLATS diisi dari template-config.js (satu file JSON/TS dengan definisi semua template)
    // Setiap template memiliki format:
    // { id, nama, kategori, frame, thumbnail, canvas: {width, height}, slots: [{x, y, w, h, shape?}] }
    // - Semua koordinat slot adalah rasio 0-1 terhadap ukuran canvas
    // - Setiap template memiliki 6 slot (3 kiri + 3 kanan) untuk duplikasi 3 foto ke 2 strip
    // - Bukan ada variants kiri/kanan/both di config; semua template punya 6 slot satuan
    // - Urutan: slot 1-3 = kiri, slot 4-6 = kanan (x relatif terhadap canvas 1200 total)
    // - shape: opsional, untuk slot bentuk (oval, hati, blob, polaroid)
    // - canvas: ukuran asli frame, TIDAK ADA hardcode di kode render
    //
    // Konversi dari format config ke internal yang diinginkan:
    // - kiri: slots[0..2] dengan x_ratio terhadap canvas kiri (0-600)
    // - kanan: slots[3..5] dengan x_ratio terhadap canvas kanan (600-1200),
    //   dimodifikasi dengan offset +600 untuk posisi piksel
    // - holes dan slots dalam render dikonversi dari rasio ke piksel saat perlu

    // Konversi slot ratio ke format internal per varian
    function convertConfigSlots(tpl) {
        if (!tpl || !tpl.slots || tpl.slots.length === 0) return null;
        const canvasW = tpl.canvas ? (tpl.canvas.width || 1200) : 1200;
        const canvasH = tpl.canvas ? (tpl.canvas.height || 1800) : 1800;
        const halfW = canvasW / 2; // 600 px: lebar strip kiri/kanan

        // Setiap template punya 6 slot: 3 kiri + 3 kanan
        // Kita konversi jadi varian kiri dan kanan
        const leftSlots = [];
        const rightSlots = [];

        for (let i = 0; i < 3; i++) {
            const s = tpl.slots[i]; // slot kiri ke-1, 2, 3
            if (!s) continue;

            // Konversi ratio ke piksel untuk strip kiri (canvas 600x1800)
            const xLeft = Math.round(s.x * halfW);
            const yLeft = Math.round(s.y * canvasH);
            const wLeft = Math.round(s.w * halfW);
            const hLeft = Math.round(s.h * canvasH);

            leftSlots.push({
                x: xLeft, y: yLeft, w: wLeft, h: hLeft,
                kind: s.shape || 'rect',
                slot: i + 1, side: 'left'
            });

            // Slot kanan: offset x +600, konversi ratio yang sama tapi terhadap kanan
            const xRight = Math.round(s.x * halfW + halfW);
            const yRight = Math.round(s.y * canvasH);
            const wRight = Math.round(s.w * halfW);
            const hRight = Math.round(s.h * canvasH);

            rightSlots.push({
                x: xRight, y: yRight, w: wRight, h: hRight,
                kind: s.shape || 'rect',
                slot: i + 3, side: 'right' // slot nomor 4,5,6
            });
        }

        if (leftSlots.length === 0 || rightSlots.length === 0) return null;

        return {
            fw: canvasW, fh: canvasH,
            holes: [], // tidak digunakan dalam sistem baru; gambar berdasarkan slot directly
            canvasW: canvasW, canvasH: canvasH,
            variant: 'gabungan',
            leftSlots: leftSlots,
            rightSlots: rightSlots,
            radius: tpl.slots.length > 0 ? (tpl.slots[0].shape ? 0 : 12) : 12,
            dupe: true, // selalu dupe dalam sistem baru (6 slot = 2 strip × 3 foto)
            dupeFactor: 2
        };
    }

    // --- AKHIR LOAD TEMPLATE DARI CONFIG ---

    function selectedTemplate() {
        return TEMPLATES.find(t => String(t.id) === String(state.templateId)) || null;
    }

    // Fungsi: dapatkan template dari config (baca dari state.cfgId)
    function selectedCfg() {
        if (state.cfgId) {
            return window.CFG_TEMPLATES.find(t => String(t.id) === String(state.cfgId)) || null;
        }
        return null;
    }

    // Mesin gambar strip menggunakan template dari config (layout 1x3 single strip)
    function paintCfgStrip(canvas, W, imgs, cfgTpl) {
        const k = W / cfgTpl.canvas.width;
        const ctx = canvas.getContext('2d');
        // Gambar frame sebagai background
        const frameImg = new Image();
        frameImg.src = cfgTpl.frame;
        frameImg.onload = () => {
            canvas.width = W;
            canvas.height = cfgTpl.canvas.height;
            ctx.drawImage(frameImg, 0, 0, cfgTpl.canvas.width, cfgTpl.canvas.height);
            // Gambar setiap slot sebagai area transparan dan tempat foto
            cfgTpl.slots.forEach((s, i) => {
                const x = s.x * k;
                const y = s.y * k;
                const w = s.w * k;
                const h = s.h * k;
                // Gambar shot ke slot area
                if (imgs && imgs[i]) {
                    ctx.drawImage(imgs[i], x, y, w, h);
                }
            });
            // Draw slot borders untuk visualisasi
            ctx.strokeStyle = 'rgba(255,0,0,0.5)';
            ctx.lineWidth = 1;
            cfgTpl.slots.forEach(s => {
                const X = s.x * k;
                const Y = s.y * k;
                const W2 = s.w * k;
                const H2 = s.h * k;
                ctx.strokeRect(X - 2, Y - 2, W2 + 4, H2 + 4);
            });
        };
        frameImg.onerror = () => paintStrip(canvas, W, imgs);
    }

    const LAYOUTS = {
        '2x2': { cols: 2, rows: 2, total: 4 },
        '2x3': { cols: 2, rows: 3, total: 6 },
        '1x3': { cols: 1, rows: 3, total: 3 }, // satu strip 3 foto (template 2-varian)
        'receipt': { cols: 2, rows: 2, total: 4 }, // 2 strip kembar, tiap strip 1 kolom × 2 foto
        'receipt_2x3': { cols: 2, rows: 3, total: 6 }, // 2 strip kembar, tiap strip 1 kolom × 3 foto
    };

    // --- SISTEM SLOT BARU DARI CONFIG ---
    // Menggunakan konversi slot dari config file
    function slottedTemplate() {
        const tpl = selectedTemplate();
        if (!tpl) return null;

        // Konversi slot dari format config ke format internal
        const converted = convertConfigSlots(tpl);
        if (!converted) return null;

        // Dalam sistem baru, total selalu 6 slot (3 kiri + 3 kanan)
        // untuk perilaku duplikasi 3 foto ke 2 strip identik
        const total = 6;

        // Holes tidak digunakan; kita gunakan leftSlots/rightSlots langsung
        // dalam paintStrip dan paintVariantDouble

        return {
            fw: converted.canvasW, fh: converted.canvasH,
            holes: [], // kosong, akan diganti oleh slot positions di paintStrip
            canvasW: converted.canvasW, canvasH: converted.canvasH,
            variant: converted.variant,
            leftSlots: converted.leftSlots,
            rightSlots: converted.rightSlots,
            radius: converted.radius,
            dupe: converted.dupe,
            dupeFactor: converted.dupeFactor
        };
    }

    function requiredTotal() {
        // Dalam sistem baru: selalu 6 slot (3 kiri + 3 kanan) untuk duplikasi 3 foto
        // 3 foto diambil, lalu diduplikasi ke 6 slot (2 strip × 3 foto)
        return 6;
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
        const slots = slottedTemplate();
        if (slots) {
            // Canvas mengikuti rasio asli canvas varian (tidak melar).
            // Frame transparan ada di atas, jadi foto di-bleed +2px agar tepi
            // lubang tertutup (hindari garis tipis di sekitar slot berbentuk).
            const k = W / slots.canvasW;
            const hasFrame = !!(selectedTemplate() && selectedTemplate().frame_url);
            const pad = (slots.pad != null) ? slots.pad : (hasFrame ? 2 : 0);
            // Gunakan leftSlots/rightSlots dari konversi config
            const holes = slots.leftSlots.concat(slots.rightSlots).map(s => ({
                x: (s.x - pad) * k,
                y: (s.y - pad) * k,
                w: (s.w + pad * 2) * k,
                h: (s.h + pad * 2) * k,
            }));
            const radius = (slots.radius != null ? slots.radius : 12) * k;
            return { slotted: true, s: k, holes, radius,
                     H: slots.canvasH * k,
                     canvasW: slots.canvasW, canvasH: slots.canvasH,
                     variant: slots.variant,
                     dupe: !!slots.dupe, dupeFactor: slots.dupeFactor || 1 };
        }
        // Fallback: layout grid baku
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
        const ir = im.width / im.height, cr = w / h;
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

    // Frame overlay (digambar paling atas). useTransparent=true pakai versi
    // transparan (slot = alpha 0) sehingga dekorasi tetap di atas foto.
    function paintFrameOverlay(ctx, W, H, useTransparent = false) {
        const tpl = state.templateId ? selectedTemplate() : null;
        let fr = state.templateId ? frameCache[state.templateId] : null;
        // Pakai versi transparan hanya jika tersedia; template lama yang file
        // frame-nya sudah transparan tetap memakai frameCache (fallback aman).
        if (useTransparent && tpl && tpl.transparent_url && transparentFrameCache[state.templateId]) {
            fr = transparentFrameCache[state.templateId];
        }
        if (fr && fr.complete && fr.naturalWidth) {
            ctx.drawImage(fr, 0, 0, W, H);
        }
    }

    // Render khusus template 2-varian untuk mode 'dupe' (duplikasi satu varian)
    // dan 'both' (gabungan asli kiri A + kanan B). Selalu menghasilkan 1200x1800.
    function paintVariantDouble(canvas, imgs, mode) {
        const tpl = selectedTemplate();
        const variant = state.templateVariant || 'gabungan';
        const W = 1200, H = 1800;
        canvas.width = W;
        canvas.height = H;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#1C1917';
        ctx.fillRect(0, 0, W, H);

        const useBoth = (mode === 'both' || variant === 'gabungan');
        const frOrig = tpl ? frameCache[tpl.id] : null;
        const frTrans = tpl ? transparentFrameCache[tpl.id] : null;
        const radius = ((tpl.slots && tpl.slots.radius) || 3);

        if (useBoth) {
            // Gabungan asli: frame penuh, 6 lubang (3 foto di-duplikasi ke kedua sisi)
            // Dalam sistem baru: gunakan slots dari konversi config
            const v = tpl ? convertConfigSlots(tpl) : null;
            if (!v) return;

            ctx.drawImage(frOrig, 0, 0, W, H);

            // Gambar 6 slot: 3 kiri + 3 kanan
            const shotIdx = [0, 1, 2, 0, 1, 2];
            // Urutan: slot 1-3 kiri, slot 4-6 kanan
            const allSlots = [...v.leftSlots, ...v.rightSlots];

            allSlots.forEach((s, i) => {
                const im = imgs[shotIdx[i]];
                if (!im) return;
                // Gambar ke posisi slot (sudah dalam koordinat piksel canvas 1200x1800)
                drawCover(ctx, im, s.x - 2, s.y - 2, s.w + 4, s.h + 4, radius);
            });

            if (window.__debug) {
                allSlots.forEach((s, i) => {
                    ctx.save(); ctx.strokeStyle = '#ff0000'; ctx.lineWidth = 2;
                    ctx.strokeRect(s.x, s.y, s.w, s.h);
                    ctx.font = '16px Arial'; ctx.fillStyle = '#ff0000';
                    ctx.fillText('S' + (shotIdx[i] + 1), s.x + 4, s.y + 20);
                    ctx.restore();
                });
            }
            if (frTrans && frTrans.complete && frTrans.naturalWidth) ctx.drawImage(frTrans, 0, 0, W, H);
            return;
        }

        // Dupe: duplikasi desain varian (kiri/kanan) ke dua sisi
        // Dalam sistem baru: gunakan slots dari konversi config
        const v = tpl ? convertConfigSlots(tpl) : null;
        if (!v) return;

        const sx = variant === 'kanan' ? 600 : 0;
        const drawFrameHalf = (img, dx) => {
            if (img && img.complete && img.naturalWidth) ctx.drawImage(img, sx, 0, 600, 1800, dx, 0, 600, 1800);
        };
        drawFrameHalf(frOrig, 0);
        drawFrameHalf(frOrig, 600);
        const shotIdx = [0, 1, 2, 0, 1, 2];
        [0, 600].forEach(dx => {
            v.leftSlots.forEach((s, j) => {
                const im = imgs[shotIdx[j]];
                if (!im) return;
                drawCover(ctx, im, dx + s.x - 2, s.y - 2, s.w + 4, s.h + 4, radius);
            });
            v.rightSlots.forEach((s, j) => {
                const im = imgs[shotIdx[j + 3]]; // slot 4,5,6 untuk kanan
                if (!im) return;
                drawCover(ctx, im, dx + s.x - 2, s.y - 2, s.w + 4, s.h + 4, radius);
            });
        });
        if (window.__debug) {
            [0, 600].forEach(dx => {
                v.leftSlots.forEach((s, j) => {
                    ctx.save(); ctx.strokeStyle = '#ff0000'; ctx.lineWidth = 2;
                    ctx.strokeRect(dx + s.x, s.y, s.w, s.h);
                    ctx.font = '16px Arial'; ctx.fillStyle = '#ff0000';
                    ctx.fillText('S' + (shotIdx[j] + 1), dx + s.x + 4, s.y + 20);
                    ctx.restore();
                });
                v.rightSlots.forEach((s, j) => {
                    ctx.save(); ctx.strokeStyle = '#ff0000'; ctx.lineWidth = 2;
                    ctx.strokeRect(dx + s.x, s.y, s.w, s.h);
                    ctx.font = '16px Arial'; ctx.fillStyle = '#ff0000';
                    ctx.fillText('S' + (shotIdx[j + 3] + 1), dx + s.x + 4, s.y + 20);
                    ctx.restore();
                });
            });
        }
        drawFrameHalf(frTrans, 0);
        drawFrameHalf(frTrans, 600);
    }

    function paintStrip(canvas, W, imgs) {
        const cfgTpl = selectedCfg();
        if (cfgTpl) { paintCfgStrip(canvas, W, imgs, cfgTpl); return; }
        const tpl = selectedTemplate();
        const isVariant = tpl && tpl.slots && state.builtin !== 'receipt';
        const mode = isVariant ? (state.printMode || 'single') : 'single';
        if (isVariant && (mode === 'dupe' || mode === 'both')) {
            paintVariantDouble(canvas, imgs, mode);
            return;
        }
        const g = geomFor(W);
        canvas.width = W;
        canvas.height = g.H;
        const ctx = canvas.getContext('2d');
        if (g.slotted) {
            // Background = frame asli (menunjukkan dekorasi di area non-slot).
            // Foto digambar di lubang slot. Frame transparan digambar PALING ATAS
            // (slot-nya alpha 0 → foto kelihatan; dekorasi/bintang/klip di atas foto).
            const bg = state.templateId ? frameCache[state.templateId] : null;
            if (bg && bg.complete && bg.naturalWidth) {
                ctx.drawImage(bg, 0, 0, W, g.H);
            } else {
                ctx.fillStyle = '#1C1917';
                ctx.fillRect(0, 0, W, g.H);
            }
            g.holes.forEach((hole, i) => {
                // Dupe template (mis. Express Yourself): 3 foto diduplikasi ke 6 slot.
                const sourceIdx = g.dupe ? Math.floor(i / g.dupeFactor) : i;
                const im = imgs[sourceIdx];
                if (!im || !hole) return;
                drawCover(ctx, im, hole.x, hole.y, hole.w, hole.h, g.radius);
            });
            // Debug: kotak merah tipis di bounding box tiap slot (?debug=1)
            if (window.__debug) {
                g.holes.forEach((hole, i) => {
                    ctx.save();
                    ctx.strokeStyle = '#ff0000';
                    ctx.lineWidth = Math.max(1, 2 * g.s);
                    ctx.strokeRect(hole.x, hole.y, hole.w, hole.h);
                    ctx.font = Math.max(12, 16 * g.s) + 'px Arial';
                    ctx.fillStyle = '#ff0000';
                    ctx.fillText('S' + (i + 1), hole.x + 4, hole.y + Math.max(14, 24 * g.s));
                    ctx.restore();
                });
            }
            // Frame transparan paling atas: dekorasi di atas foto, slot tetap lubang.
            paintFrameOverlay(ctx, W, g.H, true);
            return;
        }
        const bg = state.templateId ? backgroundCache[state.templateId] : null;
        const hasBg = bg && bg.complete && bg.naturalWidth;
        if (hasBg) {
            // Background upload admin — 100% persis seperti file (pixel sky, dll.) cover seluruh strip
            ctx.drawImage(bg, 0, 0, W, g.H);
        } else {
            paintChrome(ctx, W, g);
        }
        paintPhotos(ctx, g, imgs);
        if (!hasBg) paintFooter(ctx, W, g);
        // Jika pakai background, visitor name tidak digambar ulang agar 100% sesuai file upload
        // (data tetap tersimpan di DB dan tampil di halaman result/admin)
        paintFrameOverlay(ctx, W, g.H);
    }

    const loadShot = (src) => new Promise((res) => {
        const im = new Image();
        im.onload = () => res(im);
        im.onerror = () => res(null);
        im.src = src;
    });

    function drawStripPreview() {
        // Geometri dihitung ulang saat gambar selesai dimuat (layout bisa berubah)
        Promise.all(state.shots.map(loadShot)).then((imgs) => paintStrip(stripPreview, 600, imgs));
    }

    function fullResStrip() {
        // Resolusi asli: 600x1800 (satu strip) atau 1200x1800 (dua strip).
        return Promise.all(state.shots.map(loadShot)).then((imgs) => {
            const cv = document.createElement('canvas');
            const s = slottedTemplate();
            const W = s ? s.canvasW : 900;
            paintStrip(cv, W, imgs);
            return cv.toDataURL('image/jpeg', 0.85);
        });
    }