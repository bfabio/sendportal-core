<?php

declare(strict_types=1);

namespace Sendportal\Base\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SubscriberConfirmRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'hash' => ['required'],
            'workspace_id' => ['required'],
            'tag_id' => ['nullable', 'integer'],
        ];
    }
}
