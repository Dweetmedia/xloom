<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRecordingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasGoogleDriveConnected();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'video' => [
                'required',
                'file',
                'mimes:webm,mp4,avi,mov',
                'max:512000', // 500MB max file size
            ],
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'visibility' => 'nullable|in:private,anyone_with_link,public',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'video.required' => 'Please select a video file to upload.',
            'video.file' => 'The uploaded item must be a valid file.',
            'video.mimes' => 'The video must be a file of type: webm, mp4, avi, mov.',
            'video.max' => 'The video file must not be larger than 500MB.',
        ];
    }
}
