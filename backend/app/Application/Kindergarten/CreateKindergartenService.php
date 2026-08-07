<?php

namespace App\Application\Kindergarten;

use App\Domain\Kindergarten\Exceptions\OwnerEmailAlreadyExistsException;
use App\Domain\Kindergarten\KindergartenSlugGenerator;
use App\Domain\Shared\SecureToken;
use App\Domain\Staff\StaffRole;
use App\Models\Kindergarten;
use App\Models\KindergartenStaff;
use App\Models\StaffInvitation;
use App\Notifications\InitialPasswordSetupNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class CreateKindergartenService
{
    private const SETUP_TOKEN_EXPIRES_IN_DAYS = 7;

    public function __construct(
        private readonly KindergartenSlugGenerator $slugGenerator,
    ) {}

    public function handle(CreateKindergartenInput $input): CreateKindergartenResult
    {
        [$kindergarten, $owner, $invitation, $token] = DB::transaction(function () use ($input) {
            if (KindergartenStaff::where('email_normalized', $input->ownerEmail->normalized())->exists()) {
                throw new OwnerEmailAlreadyExistsException;
            }

            $kindergarten = Kindergarten::create([
                'name' => $input->name,
                'slug' => $this->slugGenerator->generateUniqueFrom($input->name),
            ]);

            $owner = KindergartenStaff::create([
                'kindergarten_id' => $kindergarten->id,
                'name' => $input->ownerName,
                'email' => $input->ownerEmail->value(),
                'email_normalized' => $input->ownerEmail->normalized(),
                'password_hash' => Hash::make(Str::random(60)),
                'role' => StaffRole::Owner,
                'invited_at' => now(),
            ]);

            $token = SecureToken::generate();

            $invitation = StaffInvitation::create([
                'kindergarten_id' => $kindergarten->id,
                'name' => $owner->name,
                'email' => $owner->email,
                'email_normalized' => $owner->email_normalized,
                'role' => StaffRole::Owner,
                'token_hash' => $token->hash(),
                'expires_at' => now()->addDays(self::SETUP_TOKEN_EXPIRES_IN_DAYS),
                'created_by_staff_id' => $owner->id,
            ]);

            return [$kindergarten, $owner, $invitation, $token];
        });

        $notificationQueued = false;

        if ($input->sendInvite) {
            $owner->notify(new InitialPasswordSetupNotification($kindergarten->name, $token->plainText()));
            $notificationQueued = true;
        }

        return new CreateKindergartenResult($kindergarten, $owner, $invitation, $token->plainText(), $notificationQueued);
    }
}
