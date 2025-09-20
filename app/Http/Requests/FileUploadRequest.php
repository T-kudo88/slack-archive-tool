<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FileUploadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:50000', // 50MB max file size
                'mimes:jpg,jpeg,png,gif,bmp,webp,svg,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip,rar,7z,mp4,avi,mov,mp3,wav,ogg'
            ],
            'is_public' => 'boolean',
            'channel_id' => 'nullable|exists:slack_channels,id',
            'message_id' => 'nullable|exists:slack_messages,id',
            'description' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom validation error messages.
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Please select a file to upload.',
            'file.file' => 'The uploaded file is not valid.',
            'file.max' => 'The file size must not exceed 50MB.',
            'file.mimes' => 'The file type is not supported. Please upload a valid file format.',
            'channel_id.exists' => 'The selected channel does not exist.',
            'message_id.exists' => 'The selected message does not exist.',
            'description.max' => 'The description must not exceed 1000 characters.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert string 'true'/'false' to boolean
        if ($this->has('is_public')) {
            $this->merge([
                'is_public' => filter_var($this->input('is_public'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'file' => 'uploaded file',
            'is_public' => 'visibility setting',
            'channel_id' => 'channel',
            'message_id' => 'message',
        ];
    }
}