<?php

namespace App\Livewire\Forms;

use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Form;
use Spatie\Permission\Models\Role;

class UserForm extends Form
{
    public ?int $userId = null;

    public string $name = '';

    public string $username = '';

    public string $email = '';

    /** @var list<int|string> */
    public array $roleIds = [];

    public int|string|null $personId = null;

    public mixed $photo = null;

    public bool $removePhoto = false;

    public function setUser(User $user): void
    {
        $user->loadMissing(['roles:id', 'person:id,user_id']);

        $this->userId = $user->id;
        $this->name = $user->name;
        $this->username = $user->username;
        $this->email = $user->email;
        $this->roleIds = array_values(
            $user->roles->pluck('id')->map(fn (int $roleId): int => $roleId)->all(),
        );
        $this->personId = $user->person?->id;
    }

    public function normalize(): void
    {
        $this->name = Str::squish($this->name);
        $this->username = Str::lower(trim($this->username));
        $this->email = Str::lower(trim($this->email));
        $this->roleIds = array_values(
            collect($this->roleIds)
                ->map(fn (int|string $roleId): int => (int) $roleId)
                ->unique()
                ->all(),
        );
        $this->personId = filled($this->personId) ? (int) $this->personId : null;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'min:3',
                'max:30',
                'regex:/\A[a-z0-9._-]+\z/',
                Rule::unique(User::class)->ignore($this->userId),
            ],
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
                Rule::unique(User::class)->ignore($this->userId),
            ],
            'roleIds' => ['required', 'array', 'min:1'],
            'roleIds.*' => ['required', 'integer', 'distinct', Rule::exists(Role::class, 'id')],
            'personId' => [
                'nullable',
                'integer',
                Rule::exists(Person::class, 'id')->where(
                    fn ($query) => $query->where(
                        fn ($query) => $query
                            ->whereNull('user_id')
                            ->orWhere('user_id', $this->userId ?? 0),
                    ),
                ),
            ],
            'photo' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:2048',
            ],
            'removePhoto' => ['boolean'],
        ];
    }

    /**
     * @return array{name: string, username: string, email: string}
     */
    public function userData(): array
    {
        return [
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
        ];
    }
}
