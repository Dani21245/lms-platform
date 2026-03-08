<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'role' => $this->role->value,
            'avatar' => $this->avatar,
            'is_active' => $this->is_active,
            'phone_verified_at' => $this->phone_verified_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
