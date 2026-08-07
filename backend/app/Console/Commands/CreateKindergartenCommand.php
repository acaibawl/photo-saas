<?php

namespace App\Console\Commands;

use App\Application\Kindergarten\CreateKindergartenInput;
use App\Application\Kindergarten\CreateKindergartenService;
use App\Domain\Shared\EmailAddress;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateKindergartenCommand extends Command
{
    protected $signature = 'kindergarten:create
        {--name= : 園名}
        {--owner-name= : 初回ownerスタッフの氏名}
        {--owner-email= : 初回ownerスタッフのメールアドレス}
        {--send-invite : 初回パスワード設定案内を送信する}';

    protected $description = '園を開設し、初回ownerスタッフを作成する';

    public function handle(CreateKindergartenService $service): int
    {
        $validator = Validator::make($this->options(), [
            'name' => ['required', 'string', 'max:120'],
            'owner-name' => ['required', 'string', 'max:100'],
            'owner-email' => ['required', 'string', 'email', 'max:255'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        $validated = $validator->validated();

        try {
            $result = $service->handle(new CreateKindergartenInput(
                name: $validated['name'],
                ownerName: $validated['owner-name'],
                ownerEmail: EmailAddress::fromString($validated['owner-email']),
                sendInvite: (bool) $this->option('send-invite'),
            ));
        } catch (\DomainException|\InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info('園を開設しました。');

        $this->table(['項目', '値'], [
            ['kindergarten_id', $result->kindergarten->id],
            ['kindergarten_name', $result->kindergarten->name],
            ['owner_staff_id', $result->owner->id],
            ['owner_name', $result->owner->name],
            ['owner_email', $result->owner->email],
            ['setup_token', $result->rawSetupToken],
            ['setup_token_expires_at', $result->invitation->expires_at->toRfc3339String()],
            ['invite_mail_queued', $result->invitationNotificationQueued ? 'yes' : 'no'],
        ]);

        if (! $result->invitationNotificationQueued) {
            $this->components->warn('初回設定メールは送信されていません。setup_token を運営者から手動で案内してください。');
        }

        return self::SUCCESS;
    }
}
