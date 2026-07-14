<?php

namespace App\Services\Regulatory;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use ZipArchive;

class BpomMovementReportExcelExporter
{
    public function export(string $type, Collection $rows, string $path): void
    {
        $headers = $type === BpomMovementReportQuery::TYPE_INCOMING ? $this->incomingHeaders() : $this->outgoingHeaders();
        $data = [$headers];

        foreach ($rows as $row) {
            $data[] = $type === BpomMovementReportQuery::TYPE_INCOMING
                ? [
                    $row->no, $row->jenis_transaksi, $row->tanggal_pemasukan, $row->kode_obat_jadi,
                    $row->jumlah, $row->batch, $row->tanggal_expired, $row->nomor_faktur,
                    $row->sumber, $row->keterangan, $row->id_kota_kab_sumber,
                    $row->nama_kota_kab_sumber, $row->nama_provinsi_sumber,
                ]
                : [
                    $row->no, $row->jenis_distribusi, $row->tanggal_distribusi, $row->kode_obat_jadi,
                    $row->jumlah_obat_jadi, $row->batch_obat_jadi, $row->tanggal_expired, $row->nomor_faktur,
                    $row->tujuan, $row->alamat, $row->keterangan_peruntukan, $row->id_kota_kab_tujuan,
                    $row->nama_kota_kab_tujuan, $row->provinsi_tujuan,
                ];
        }

        $this->buildXlsx(
            $path,
            $data,
            $type === BpomMovementReportQuery::TYPE_INCOMING ? 'BARANG MASUK BPOM' : 'BARANG KELUAR BPOM',
            [0, 4],
            $type === BpomMovementReportQuery::TYPE_INCOMING ? [2, 6] : [2, 6],
        );
    }

    public function filename(string $type, string $startDate, string $endDate): string
    {
        $prefix = $type === BpomMovementReportQuery::TYPE_INCOMING ? 'LAPORAN_PENERIMAAN_BPOM' : 'LAPORAN_PENGELUARAN_BPOM';

        return "{$prefix}_{$startDate}_{$endDate}.xlsx";
    }

    private function incomingHeaders(): array
    {
        return ['NO', 'JENIS TRANSAKSI', 'TANGGAL PEMASUKAN', 'KODE OBAT JADI', 'JUMLAH', 'BATCH', 'TANGGAL EXPIRED', 'NOMOR FAKTUR', 'SUMBER', 'KETERANGAN', 'ID KOTA/KAB SUMBER', 'NAMA KOTA/KAB SUMBER', 'NAMA PROVINSI SUMBER'];
    }

    private function outgoingHeaders(): array
    {
        return ['NO', 'JENIS DISTRIBUSI', 'TANGGAL DISTRIBUSI', 'KODE OBAT JADI', 'JUMLAH OBAT JADI', 'BATCH OBAT JADI', 'TANGGAL EXPIRED', 'NOMOR FAKTUR', 'TUJUAN', 'ALAMAT', 'KETERANGAN/PERUNTUKAN', 'ID KOTA/KAB TUJUAN', 'NAMA KOTA/KAB TUJUAN', 'PROVINSI TUJUAN'];
    }

    private function buildXlsx(string $path, array $rows, string $sheetName, array $numberColumns = [], array $dateColumns = []): void
    {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) return;

        $sheetRows = '';
        foreach ($rows as $rowIndex => $row) {
            $cellXml = '';
            foreach ($row as $colIndex => $value) {
                $coordinate = $this->columnLabelFromIndex($colIndex).($rowIndex + 1);
                if ($rowIndex > 0 && in_array($colIndex, $dateColumns, true) && ($excelDate = $this->toExcelDateValue($value)) !== null) {
                    $cellXml .= "<c r=\"{$coordinate}\" s=\"1\"><v>{$excelDate}</v></c>";
                    continue;
                }
                if ($rowIndex > 0 && in_array($colIndex, $numberColumns, true) && is_numeric($value)) {
                    $cellXml .= "<c r=\"{$coordinate}\"><v>".(float) $value."</v></c>";
                    continue;
                }
                $escaped = htmlspecialchars((string) $value, ENT_XML1);
                $cellXml .= "<c r=\"{$coordinate}\" t=\"inlineStr\"><is><t>{$escaped}</t></is></c>";
            }
            $sheetRows .= '<row r="'.($rowIndex + 1).'">'.$cellXml.'</row>';
        }

        $safeSheetName = htmlspecialchars(substr($sheetName, 0, 31), ENT_XML1);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="'.$safeSheetName.'" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="1"><numFmt numFmtId="164" formatCode="yyyy-mm-dd"/></numFmts><fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts><fills count="1"><fill><patternFill patternType="none"/></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$sheetRows.'</sheetData></worksheet>');
        $zip->close();
    }

    private function toExcelDateValue(mixed $value): ?float
    {
        if ($value === null || $value === '') return null;
        try { $date = Carbon::parse((string) $value); } catch (\Throwable) { return null; }
        return ($date->getTimestamp() / 86400) + 25569;
    }

    private function columnLabelFromIndex(int $index): string
    {
        $label = ''; $position = $index + 1;
        while ($position > 0) { $modulo = ($position - 1) % 26; $label = chr(65 + $modulo).$label; $position = intdiv($position - 1, 26); }
        return $label;
    }
}
