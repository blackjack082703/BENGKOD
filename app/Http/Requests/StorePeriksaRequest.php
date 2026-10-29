<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePeriksaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role === 'dokter';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id_daftar_poli' => ['required', 'integer', 'exists:daftar_poli,id'],
            'obat_json' => ['bail', 'required', 'string', 'json'],
            'obat_ids' => ['present', 'array'],
            'obat_ids.*' => ['required', 'integer', 'distinct', 'exists:obat,id'],
            'catatan' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $json = $this->input('obat_json');
        $ids = is_string($json) ? json_decode($json) : null;

        $this->merge([
            'obat_ids' => is_array($ids) && array_is_list($ids) ? $ids : null,
        ]);
    }

    public function messages(): array
    {
        return [
            'id_daftar_poli.required' => 'Pendaftaran pasien wajib dipilih.',
            'id_daftar_poli.integer' => 'Pendaftaran pasien tidak valid.',
            'id_daftar_poli.exists' => 'Pendaftaran pasien tidak ditemukan.',
            'obat_json.required' => 'Daftar obat wajib dikirim.',
            'obat_json.string' => 'Daftar obat harus berupa JSON yang valid.',
            'obat_json.json' => 'Daftar obat harus berupa JSON yang valid.',
            'obat_ids.array' => 'Daftar obat harus berupa daftar ID obat.',
            'obat_ids.*.required' => 'ID obat wajib diisi.',
            'obat_ids.*.integer' => 'ID obat tidak valid.',
            'obat_ids.*.distinct' => 'Obat yang sama tidak boleh dipilih lebih dari sekali.',
            'obat_ids.*.exists' => 'Obat yang dipilih tidak tersedia.',
            'catatan.string' => 'Catatan harus berupa teks.',
        ];
    }
}
