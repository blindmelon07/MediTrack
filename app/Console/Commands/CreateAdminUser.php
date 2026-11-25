<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('app:create-admin-user {--name= : Full name} {--email= : Email address used to log in} {--password= : Password (prompted for when omitted)}')]
#[Description('Create an administrator account, or promote an existing account to administrator')]
class CreateAdminUser extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->option('email') ?: text('Email address', required: true);
        $existingUser = User::firstWhere('email', $email);

        if ($existingUser !== null) {
            $existingUser->forceFill(['role' => Role::Admin, 'company_id' => null])->save();
            $this->components->info("{$existingUser->email} is now an administrator.");

            return self::SUCCESS;
        }

        $attributes = [
            'name' => $this->option('name') ?: text('Full name', required: true),
            'email' => $email,
            'password' => $this->option('password') ?: password('Password', required: true),
        ];

        $validator = Validator::make($attributes, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', Password::default()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = new User($attributes);
        $user->forceFill(['role' => Role::Admin, 'email_verified_at' => now()])->save();

        $this->components->info("Administrator {$user->email} created. Log in and add the other accounts under Users.");

        return self::SUCCESS;
    }
}
