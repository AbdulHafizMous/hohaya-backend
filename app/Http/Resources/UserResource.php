<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'email'             => $this->email,
            'phone'             => $this->phone,
            'avatar'            => $this->avatar
                ? asset('storage/' . $this->avatar)
                : null,
            'adress'            => $this->adress,
            'preferences'       => $this->preferences,
            'is_verified'       => $this->is_verified,
            'is_suscribed'      => $this->is_suscribed,
            'subscription_end'  => $this->subscription_end,
            'email_verified_at' => $this->email_verified_at,
            'roles'             => $this->getRoleNames(),
            'created_at'        => $this->created_at,
        ];
    }
}