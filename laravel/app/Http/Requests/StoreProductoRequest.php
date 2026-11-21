<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// MEJORA 2: validación previa al guardado. Convierte los errores 500 por datos
// inválidos o SKU duplicado en respuestas 422 con un mensaje claro.
class StoreProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:30', 'unique:productos,sku'],
            'nombre' => ['required', 'string', 'max:120'],
            'categoria' => ['required', 'string', 'max:60'],
            'precio' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'existencia' => ['nullable', 'integer', 'min:0'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'sku.unique' => 'Ya existe un producto con ese SKU.',
            'precio.min' => 'El precio no puede ser negativo.',
        ];
    }
}
