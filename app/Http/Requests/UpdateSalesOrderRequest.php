<?php
namespace App\Http\Requests;
use App\Models\Sales\Sale;
use Illuminate\Foundation\Http\FormRequest;
class UpdateSalesOrderRequest extends FormRequest {
public function authorize(): bool { $sale=$this->route('salesOrder'); return (bool) $this->user()?->can('sales-order.update') && $sale instanceof Sale && $sale->status==='draft'; }
public function rules(): array { return (new StoreSalesOrderRequest())->rules(); }
}
