<?php
/**
 * Gaya cetak untuk dokumen surat (SO/PO). Ditempatkan sebagai <style> mandiri
 * agar halaman surat tidak bergantung pada app.css (dokumen harus bisa dicetak
 * berdiri sendiri). Tone warna MENGIKUTI token aplikasi — tanpa warna baru.
 */
?>
<style>
    * { box-sizing: border-box; }
    html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    body {
        margin: 0;
        background: #eceef1;
        color: #1f2937;
        font-family: "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        font-size: 14px;
        line-height: 1.5;
    }

    /* Bilah aksi — hanya tampil di layar, hilang saat dicetak. */
    .doc-toolbar {
        position: sticky;
        top: 0;
        z-index: 10;
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 24px;
        background: #ffffff;
        border-bottom: 1px solid #e2e5ea;
    }
    .doc-toolbar .tb-spacer { margin-left: auto; }
    .tb-btn {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 8px 14px;
        font: inherit;
        font-weight: 600;
        font-size: .85rem;
        color: #1f2937;
        background: #ffffff;
        border: 1px solid #e2e5ea;
        border-radius: 8px;
        text-decoration: none;
        cursor: pointer;
    }
    .tb-btn:hover { background: #f6f7f9; }
    .tb-btn--primary { background: #0d9488; border-color: #0d9488; color: #fff; }
    .tb-btn--primary:hover { filter: brightness(.95); }
    .tb-btn svg { width: 16px; height: 16px; }
    .doc-hint { color: #6b7280; font-size: .8rem; }

    /* Lembar A4. */
    .sheet {
        max-width: 820px;
        margin: 24px auto 48px;
        padding: 48px 56px;
        background: #ffffff;
        border: 1px solid #e2e5ea;
        border-radius: 8px;
        box-shadow: 0 10px 30px rgba(16, 24, 40, .08);
    }

    .doc-kop { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; }
    .doc-kop__brand { }
    .doc-kop__name { font-size: 1.5rem; font-weight: 700; letter-spacing: .06em; color: #1e3a5f; margin: 0; }
    .doc-kop__tag { margin: 2px 0 0; font-size: .78rem; letter-spacing: .12em; text-transform: uppercase; color: #0d9488; }
    .doc-kop__addr { margin: 8px 0 0; font-size: .8rem; color: #6b7280; line-height: 1.6; }

    .doc-meta { text-align: right; min-width: 240px; }
    .doc-meta__title { margin: 0; font-size: 1.05rem; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: #1e3a5f; }
    .doc-meta__no { margin: 6px 0 0; font-size: 1.15rem; font-weight: 700; font-family: "Cascadia Mono", Consolas, monospace; color: #0d9488; }
    .doc-meta__lines { margin: 10px 0 0; font-size: .82rem; color: #6b7280; }
    .doc-meta__lines span { display: block; }
    .doc-meta__lines b { color: #1f2937; font-weight: 600; }

    .doc-rule { height: 3px; background: #1e3a5f; border-radius: 2px; margin: 22px 0; }

    .doc-parties { display: flex; gap: 32px; }
    .doc-party { flex: 1 1 0; }
    .doc-party__label { margin: 0 0 4px; font-size: .72rem; letter-spacing: .1em; text-transform: uppercase; color: #6b7280; }
    .doc-party__name { margin: 0; font-weight: 700; color: #1f2937; }
    .doc-party__sub { margin: 2px 0 0; font-size: .85rem; color: #6b7280; }

    .doc-section-title { margin: 26px 0 10px; font-size: .8rem; letter-spacing: .1em; text-transform: uppercase; color: #1e3a5f; font-weight: 700; }

    table.doc-table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    .doc-table th, .doc-table td { padding: 8px 10px; border-bottom: 1px solid #e2e5ea; text-align: left; vertical-align: top; }
    .doc-table thead th { font-size: .72rem; letter-spacing: .06em; text-transform: uppercase; color: #6b7280; border-bottom: 2px solid #1e3a5f; }
    .doc-table .t-right { text-align: right; }
    .doc-table .t-center { text-align: center; }
    .doc-table .t-muted { color: #6b7280; }
    .doc-table tbody tr:last-child td { border-bottom: 1px solid #e2e5ea; }
    .doc-empty { padding: 14px 0; color: #6b7280; font-style: italic; }

    .doc-notes { margin-top: 18px; padding: 12px 14px; background: #f6f7f9; border: 1px solid #e2e5ea; border-radius: 8px; font-size: .85rem; }
    .doc-notes h4 { margin: 0 0 4px; font-size: .72rem; letter-spacing: .08em; text-transform: uppercase; color: #6b7280; }
    .doc-notes p { margin: 0; white-space: pre-line; }

    /* Ringkasan nilai rata kanan (surat penjualan). */
    .doc-totals { display: flex; justify-content: flex-end; margin-top: 16px; }
    .doc-totals table { border-collapse: collapse; min-width: 280px; }
    .doc-totals td { padding: 5px 10px; }
    .doc-totals .lbl { color: #6b7280; }
    .doc-totals .val { text-align: right; font-variant-numeric: tabular-nums; }
    .doc-totals tr.grand td { border-top: 2px solid #1e3a5f; font-weight: 700; color: #1e3a5f; font-size: 1.02rem; }

    .doc-sign { display: flex; justify-content: space-between; gap: 24px; margin-top: 40px; }
    .doc-sign__box { flex: 1 1 0; text-align: center; }
    .doc-sign__role { font-size: .72rem; letter-spacing: .08em; text-transform: uppercase; color: #6b7280; }
    .doc-sign__line { height: 64px; }
    .doc-sign__name { border-top: 1px solid #1f2937; padding-top: 6px; font-weight: 600; }

    .doc-foot { margin-top: 32px; padding-top: 12px; border-top: 1px solid #e2e5ea; font-size: .72rem; color: #6b7280; text-align: center; }

    @media print {
        body { background: #fff; }
        .doc-toolbar { display: none; }
        .sheet { max-width: none; margin: 0; padding: 0; border: none; border-radius: 0; box-shadow: none; }
        @page { size: A4; margin: 16mm; }
        .doc-table { page-break-inside: auto; }
        .doc-table tr { page-break-inside: avoid; }
        .doc-table thead { display: table-header-group; }
        .doc-sign { page-break-inside: avoid; }
    }

    @media (max-width: 640px) {
        .sheet { padding: 28px 20px; margin: 12px; }
        .doc-kop, .doc-parties, .doc-sign { flex-direction: column; }
        .doc-meta { text-align: left; }
    }
</style>
