<?php

namespace App\Http\Controllers\Apps\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FacilityMovementReportController extends Controller
{
    public function __invoke(Request $request)
    {
        $q = DB::table('stock_ledgers')
            ->leftJoin('items', 'items.id', '=', 'stock_ledgers.item_id')
            ->leftJoin('item_batches', 'item_batches.id', '=', 'stock_ledgers.batch_id')
            ->leftJoin('warehouses', 'warehouses.id', '=', 'stock_ledgers.warehouse_id')
            ->leftJoin('facility_schemes', 'facility_schemes.id', '=', 'stock_ledgers.facility_scheme_id')
            ->when($request->date('date_from'), fn($qq,$v)=>$qq->whereDate('trx_datetime','>=',$v))
            ->when($request->date('date_to'), fn($qq,$v)=>$qq->whereDate('trx_datetime','<=',$v))
            ->when($request->integer('facility_scheme_id'), fn($qq,$v)=>$qq->where('stock_ledgers.facility_scheme_id',$v))
            ->when($request->integer('item_id'), fn($qq,$v)=>$qq->where('stock_ledgers.item_id',$v))
            ->when($request->integer('warehouse_id'), fn($qq,$v)=>$qq->where('stock_ledgers.warehouse_id',$v))
            ->when($request->filled('batch_no'), fn($qq)=>$qq->where('item_batches.batch_no','like','%'.$request->string('batch_no').'%'))
            ->selectRaw("DATE(stock_ledgers.trx_datetime) as date, CONCAT(stock_ledgers.trx_type,'-',stock_ledgers.trx_id) as document_no, stock_ledgers.trx_type as movement_type, items.name as item_name, item_batches.batch_no, facility_schemes.code as facility, warehouses.name as warehouse, CASE WHEN stock_ledgers.qty_base > 0 THEN stock_ledgers.qty_base ELSE 0 END as qty_in, CASE WHEN stock_ledgers.qty_base < 0 THEN ABS(stock_ledgers.qty_base) ELSE 0 END as qty_out, stock_ledgers.qty_base as balance, CONCAT(stock_ledgers.trx_type,'-',stock_ledgers.trx_id) as source_document, NULL as destination, NULL as remarks")
            ->orderBy('stock_ledgers.trx_datetime');

        return response()->json($q->paginate(100));
    }
}
