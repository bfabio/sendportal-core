<?php

namespace Sendportal\Base\Services\Messages;

use Exception;
use Sendportal\Base\Models\EmailService;
use Sendportal\Base\Models\Message;
use Sendportal\Base\Repositories\Campaigns\CampaignTenantRepositoryInterface;
use Sendportal\Base\Repositories\EmailServiceTenantRepository;
use Sendportal\Pro\Repositories\AutomationScheduleRepository;

class ResolveEmailService
{
    /** @var CampaignTenantRepositoryInterface */
    protected $campaignTenantRepository;

    /** @var EmailServiceTenantRepository */
    protected $emailServiceRepository;

    public function __construct(
        CampaignTenantRepositoryInterface $campaignTenantRepository,
        EmailServiceTenantRepository $emailServiceTenantRepository
    )
    {
        $this->campaignTenantRepository = $campaignTenantRepository;
        $this->emailServiceRepository = $emailServiceTenantRepository;
    }

    /**
     * @throws Exception
     */
    public function handle(Message $message): EmailService
    {
        if ($message->isAutomation()) {
            return $this->resolveAutomationEmailService($message);
        }

        if ($message->isCampaign()) {
            return $this->resolveCampaignEmailService($message);
        }

        if ($message->isConfirmation()) {
            return $this->resolveConfirmationEmailService($message);
        }

        throw new Exception('Unable to resolve email service for message id=' . $message->id);
    }

    /**
     * Resolve the email service for an automation
     *
     * @param Message $message
     * @return EmailService
     * @throws Exception
     */
    protected function resolveAutomationEmailService(Message $message): EmailService
    {
        if (!$automationSchedule = app(AutomationScheduleRepository::class)->find(
            $message->source_id,
            ['automation_step.automation.email_service.type']
        )) {
            throw new Exception('Unable to resolve automation schedule for message id=' . $message->id);
        }

        if (!$emailService = $automationSchedule->automation_step->automation->email_service) {
            throw new Exception('Unable to resolve email service for message id=' . $message->id);
        }

        return $emailService;
    }

    /**
     * Resolve the provider for a campaign
     *
     * @param Message $message
     * @return EmailService
     * @throws Exception
     */
    protected function resolveCampaignEmailService(Message $message): EmailService
    {
        if (! $campaign = $this->campaignTenantRepository->find($message->workspace_id, $message->source_id, ['email_service'])) {
            throw new Exception('Unable to resolve campaign for message id=' . $message->id);
        }

        if (! $emailService = $campaign->email_service) {
            throw new Exception('Unable to resolve email service for message id=' . $message->id);
        }

        return $emailService;
    }

    /**
     * Resolve the provider for a registration confirmation
     *
     * @param Message $message
     * @return EmailService
     * @throws Exception
     */
    protected function resolveConfirmationEmailService(Message $message): EmailService
    {
        // XXX handle failure better
        if (! $emailService = $this->emailServiceRepository->all($message->workspace_id)->first()) {
            throw new Exception('Unable to find email service for message id=' . $message->id);
        }

        return $emailService;
    }
}
