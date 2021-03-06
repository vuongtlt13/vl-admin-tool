<?php

namespace Vuongdq\VLAdminTool\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Vuongdq\VLAdminTool\Models\Relation;

class CreateRelationRequest extends FormRequest
{

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'second_field_id' => 'required',
            'type' => 'required|string|max:255',
            'table_name' => 'nullable|string|max:255',
            'fk_1' => 'nullable|string|max:255',
            'fk_2' => 'nullable|string|max:255',
            'created_at' => 'nullable',
            'updated_at' => 'nullable'
        ];
    }
}
