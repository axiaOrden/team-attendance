<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isActive() === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Coordinates are captured by the browser only - there is no field for
     * entering them manually - and the optimized JPEG is always required.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'watermarked_photo' => ['required', 'file', 'image', 'mimes:jpeg,jpg', 'max:3072'],
            'device_information' => ['nullable', 'string', 'max:4000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'latitude.required' => 'Your GPS location could not be read. Location access is required to record attendance.',
            'longitude.required' => 'Your GPS location could not be read. Location access is required to record attendance.',
            'watermarked_photo.required' => 'An attendance photo is required.',
        ];
    }
}
