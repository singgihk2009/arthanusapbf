<?php

namespace App\Services\Regulatory;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BpomMovementReportQuery
{
    public const TYPE_INCOMING = 'incoming';
    public const TYPE_OUTGOING = 'outgoing';

    public function incoming(CarbonInterface|string $startDate, CarbonInterface|string $endDate): Collection
    {
        $vendorCity = $this->selectNullable('vendors', ['city', 'city_name', 'regency_name']);
        $vendorProvince = $this->selectNullable('vendors', ['province', 'province_name']);
        $vendorRegionId = $this->selectNullable('vendors', ['city_id', 'regency_id', 'id_kemenkes', 'region_id']);
        $irpSourceCode = Schema::hasColumn('item_regulatory_products', 'source_code') ? 'irp.source_code' : 'NULL';
        $rpSourceCode = Schema::hasColumn('regulatory_products', 'source_code') ? 'rp.source_code' : 'NULL';

        return DB::table('goods_receipt_lines as grl')
            ->join('goods_receipts as gr', 'gr.id', '=', 'grl.goods_receipt_id')
            ->leftJoin('vendors as v', 'v.id', '=', DB::raw('COALESCE(gr.vendor_id, gr.supplier_id)'))
            ->join('items as i', 'i.id', '=', 'grl.item_id')
            ->leftJoin('item_batches as b', 'b.id', '=', 'grl.batch_id')
            ->leftJoin('item_regulatory_products as irp', function ($join): void {
                $join->on('irp.item_id', '=', 'i.id')->where('irp.is_primary', true);
            })
            ->join('regulatory_products as rp', 'rp.id', '=', DB::raw('COALESCE(i.regulatory_product_id, irp.regulatory_product_id)'))
            ->join('regulatory_sources as rs', 'rs.id', '=', 'rp.source_id')
            ->leftJoin('vendor_invoice_lines as vil', 'vil.receipt_line_id', '=', 'grl.id')
            ->leftJoin('vendor_invoices as vi', 'vi.id', '=', 'vil.vendor_invoice_id')
            ->whereRaw('UPPER(rs.source_name) = ?', ['BPOM'])
            ->whereBetween(DB::raw('COALESCE(gr.receipt_date, gr.document_date)'), [$startDate, $endDate])
            ->where('gr.status', 'POSTED')
            ->selectRaw("ROW_NUMBER() OVER (ORDER BY COALESCE(gr.receipt_date, gr.document_date), gr.id, grl.id) as no")
            ->selectRaw("'PEMBELIAN' as jenis_transaksi")
            ->selectRaw('COALESCE(gr.receipt_date, gr.document_date) as tanggal_pemasukan')
            ->selectRaw("COALESCE({$irpSourceCode}, {$rpSourceCode}, rp.nie) as kode_obat_jadi")
            ->selectRaw('grl.qty_received as jumlah')
            ->selectRaw('b.batch_no as batch')
            ->selectRaw('COALESCE(grl.expired_date, b.expired_date) as tanggal_expired')
            ->selectRaw('vi.invoice_no_internal as nomor_faktur')
            ->selectRaw('v.name as sumber')
            ->selectRaw('COALESCE(gr.delivery_note_no, gr.notes) as keterangan')
            ->selectRaw("{$vendorRegionId} as id_kota_kab_sumber")
            ->selectRaw("{$vendorCity} as nama_kota_kab_sumber")
            ->selectRaw("{$vendorProvince} as nama_provinsi_sumber")
            ->selectRaw('gr.id as source_document_id, grl.id as source_line_id')
            ->get()
            ->map(fn ($row) => $this->appendValidation($row, self::TYPE_INCOMING));
    }

    public function outgoing(CarbonInterface|string $startDate, CarbonInterface|string $endDate): Collection
    {
        $customerRegionId = $this->selectNullable('customers', ['city_id', 'regency_id', 'id_kemenkes', 'region_id']);
        $irpSourceCode = Schema::hasColumn('item_regulatory_products', 'source_code') ? 'irp.source_code' : 'NULL';
        $rpSourceCode = Schema::hasColumn('regulatory_products', 'source_code') ? 'rp.source_code' : 'NULL';

        return DB::table('customer_invoice_lines as cil')
            ->join('customer_invoices as ci', 'ci.id', '=', 'cil.customer_invoice_id')
            ->leftJoin('shipment_lines as sl', 'sl.id', '=', 'cil.shipment_line_id')
            ->leftJoin('shipments as s', 's.id', '=', DB::raw('COALESCE(ci.shipment_id, sl.shipment_id)'))
            ->join('customers as c', 'c.id', '=', 'ci.customer_id')
            ->join('items as i', 'i.id', '=', 'cil.item_id')
            ->leftJoin('item_batches as b', 'b.id', '=', DB::raw('COALESCE(sl.batch_id, (SELECT sales_lines.batch_id FROM sales_lines WHERE sales_lines.id = cil.sale_line_id LIMIT 1))'))
            ->leftJoin('item_regulatory_products as irp', function ($join): void {
                $join->on('irp.item_id', '=', 'i.id')->where('irp.is_primary', true);
            })
            ->join('regulatory_products as rp', 'rp.id', '=', DB::raw('COALESCE(i.regulatory_product_id, irp.regulatory_product_id)'))
            ->join('regulatory_sources as rs', 'rs.id', '=', 'rp.source_id')
            ->whereRaw('UPPER(rs.source_name) = ?', ['BPOM'])
            ->whereBetween(DB::raw('COALESCE(s.shipment_date, ci.invoice_date)'), [$startDate, $endDate])
            ->whereIn('ci.status', ['posted', 'partially_paid', 'paid'])
            ->selectRaw("ROW_NUMBER() OVER (ORDER BY COALESCE(s.shipment_date, ci.invoice_date), ci.id, cil.id) as no")
            ->selectRaw("'PENJUALAN' as jenis_distribusi")
            ->selectRaw('COALESCE(s.shipment_date, ci.invoice_date) as tanggal_distribusi')
            ->selectRaw("COALESCE({$irpSourceCode}, {$rpSourceCode}, rp.nie) as kode_obat_jadi")
            ->selectRaw('COALESCE(sl.qty_shipped, cil.qty) as jumlah_obat_jadi')
            ->selectRaw('b.batch_no as batch_obat_jadi')
            ->selectRaw('b.expired_date as tanggal_expired')
            ->selectRaw('ci.number as nomor_faktur')
            ->selectRaw('c.customer_name as tujuan')
            ->selectRaw('c.address as alamat')
            ->selectRaw('ci.notes as keterangan_peruntukan')
            ->selectRaw("{$customerRegionId} as id_kota_kab_tujuan")
            ->selectRaw('c.city as nama_kota_kab_tujuan')
            ->selectRaw('c.province as provinsi_tujuan')
            ->selectRaw('ci.id as source_document_id, cil.id as source_line_id')
            ->get()
            ->map(fn ($row) => $this->appendValidation($row, self::TYPE_OUTGOING));
    }

    public function validationSummary(Collection $rows): array
    {
        $invalidRows = $rows->filter(fn ($row) => ! empty($row->validation_errors))->values();

        return [
            'total_rows' => $rows->count(),
            'valid_rows' => $rows->count() - $invalidRows->count(),
            'invalid_rows' => $invalidRows->count(),
            'errors' => $invalidRows->map(fn ($row) => [
                'no' => $row->no,
                'nomor_faktur' => $row->nomor_faktur ?? '-',
                'errors' => $row->validation_errors,
            ])->all(),
        ];
    }

    private function appendValidation(object $row, string $type): object
    {
        $required = $type === self::TYPE_INCOMING
            ? ['kode_obat_jadi', 'jumlah', 'batch', 'tanggal_expired', 'nomor_faktur', 'sumber', 'id_kota_kab_sumber', 'nama_kota_kab_sumber', 'nama_provinsi_sumber']
            : ['kode_obat_jadi', 'jumlah_obat_jadi', 'batch_obat_jadi', 'tanggal_expired', 'nomor_faktur', 'tujuan', 'alamat', 'id_kota_kab_tujuan', 'nama_kota_kab_tujuan', 'provinsi_tujuan'];

        $row->validation_errors = collect($required)
            ->filter(fn ($field) => blank($row->{$field} ?? null) || (str_starts_with($field, 'jumlah') && (float) $row->{$field} <= 0))
            ->map(fn ($field) => str_replace('_', ' ', $field).' wajib diisi')
            ->values()
            ->all();

        return $row;
    }

    private function selectNullable(string $table, array $candidates): string
    {
        foreach ($candidates as $column) {
            if (Schema::hasColumn($table, $column)) {
                return $table[0].'.'.$column;
            }
        }

        return 'NULL';
    }
}
