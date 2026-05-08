<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $base = [
            'name' => 'required|string|max:255',
            'selectedRoles' => 'required|array|min:1',
            'warehouse_ids' => 'nullable|array',
            'warehouse_ids.*' => 'integer|exists:warehouses,id',
            'default_warehouse_id' => 'nullable|integer|exists:warehouses,id',
        ];

        if ($this->isMethod('post')) {
            return $base + [
                'email' => 'required|email|unique:users',
                'password' => 'required|min:4|confirmed',
            ];
        }

        return $base + [
            'email' => 'required|email|unique:users,email,' . $this->user->id,
            'password' => 'nullable|min:4|confirmed',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $roles = collect($this->input('selectedRoles', []))->map(fn ($role) => strtolower((string) $role));
            $warehouseIds = collect($this->input('warehouse_ids', []))->filter()->map(fn ($id) => (int) $id)->unique()->values();
            $defaultWarehouseId = $this->input('default_warehouse_id') ? (int) $this->input('default_warehouse_id') : null;

            if ($roles->contains('stockkeeper') && $warehouseIds->isEmpty()) {
                $validator->errors()->add('warehouse_ids', 'Role Stockkeeper wajib memiliki minimal satu warehouse.');
            }

            if ($defaultWarehouseId && ! $warehouseIds->contains($defaultWarehouseId)) {
                $validator->errors()->add('default_warehouse_id', 'Default warehouse wajib salah satu assigned warehouse.');
            }
        });
    }
}
