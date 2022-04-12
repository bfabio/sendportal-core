<?php

declare(strict_types=1);

namespace Sendportal\Base\Services\Messages;

use Exception;
use Illuminate\Support\Facades\Log;
use Sendportal\Base\Models\Campaign;
use Sendportal\Base\Models\EmailService;
use Sendportal\Base\Models\Message;
use Sendportal\Base\Models\Subscriber;
use Sendportal\Base\Services\Content\MergeContentService;
use Ramsey\Uuid\Uuid;

class DispatchConfirmationMessage
{
    /** @var ResolveEmailService */
    protected $resolveEmailService;

    /** @var RelayMessage */
    protected $relayMessage;

    /** @var MergeContentService */
    protected $mergeContent;

    public function __construct(
        MergeContentService $mergeContent,
        ResolveEmailService $resolveEmailService,
        RelayMessage $relayMessage
    ) {
        $this->resolveEmailService = $resolveEmailService;
        $this->relayMessage = $relayMessage;
        $this->mergeContent = $mergeContent;
    }

    /**
     * @throws Exception
     */
    public function handle(int $workspaceId, Subscriber $subscriber): ?string
    {
        $message = $this->createConfirmationMessage($workspaceId, $subscriber->email);
        $message->subscriber = $subscriber;

        $mergedContent = $this->getMergedContent($message);

        $emailService = $this->getEmailService($message);

        $trackingOptions = MessageTrackingOptions::fromMessage($message);

        return $this->dispatch($message, $emailService, $trackingOptions, $mergedContent);
    }

    /**
     * @throws Exception
     */
    protected function getMergedContent(Message $message): string
    {
        return $this->mergeContent->handle($message);
    }

    /**
     * @throws Exception
     */
    protected function dispatch(Message $message, EmailService $emailService, MessageTrackingOptions $trackingOptions, string $mergedContent): ?string
    {
        $messageOptions = (new MessageOptions)
            ->setTo($message->recipient_email)
            ->setFromEmail($message->from_email)
            ->setFromName($message->from_name)
            ->setSubject($message->subject)
            ->setTrackingOptions($trackingOptions);

        $messageId = $this->relayMessage->handle($mergedContent, $messageOptions, $emailService);

        Log::info('Message has been dispatched.', ['message_id' => $messageId]);

        return $messageId;
    }

    /**
     * @throws Exception
     */
    protected function getEmailService(Message $message): EmailService
    {
        return $this->resolveEmailService->handle($message);
    }

    protected function createConfirmationMessage(int $workspaceId, string $recipientEmail): Message
    {
        return new Message([
            'workspace_id' => $workspaceId,
            'recipient_email' => $recipientEmail,
            // XXX parametrize
            'subject' => 'Confirm registration to our mailing list',
            // XXX parametrize
            'from_name' => 'Developer & Designers Italia mailing list',
            // XXX parametrize
            'from_email' => 'no-reply@developers.italia.it',
            'hash' => 'confirmation-' . Uuid::uuid4()->toString()
        ]);
    }
}
