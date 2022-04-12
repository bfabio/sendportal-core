<?php

declare(strict_types=1);

namespace Sendportal\Base\Http\Controllers\Api;

use Exception;
use Sendportal\Base\Http\Controllers\Controller;
use Sendportal\Base\Http\Requests\Api\SubscriberConfirmRequest;
use Sendportal\Base\Http\Requests\Api\SubscriberSubscribeRequest;
use Sendportal\Base\Repositories\Subscribers\SubscriberTenantRepositoryInterface;
use Sendportal\Base\Services\Messages\DispatchConfirmationMessage;
use Sendportal\Base\Services\Subscribers\ApiSubscriberService;

class SubscribeController extends Controller
{
    /** @var dispatchConfirmationMessage */
    protected $dispatchConfirmationMessage;

    /** @var SubscriberTenantRepositoryInterface */
    protected $subscribers;

    /** @var ApiSubscriberService */
    protected $apiService;

    public function __construct(
        SubscriberTenantRepositoryInterface $subscribers,
        ApiSubscriberService $apiService,
        DispatchConfirmationMessage $dispatchConfirmationMessage
    ) {
        $this->subscribers = $subscribers;
        $this->apiService = $apiService;
        $this->dispatchConfirmationMessage = $dispatchConfirmationMessage;
    }

    /**
     * @throws Exception
     */
    public function subscribe(SubscriberSubscribeRequest $request)
    {
        $now = now();

        $data = $request
            ->safe()
            ->merge(['created_at' => $now, 'unsubscribed_at' => $now])
            ->except(['workspace_id']);

        $workspaceId = (int) $request->input('workspace_id');

        $subscriber =  $this->subscribers->getOrCreate(
            $workspaceId,
            ['email' => $data['email']],
            $data
        );

        /* Resend the mail if the user is new or has requested to subscribe
         * more than 1 day before, to give them a chance to reconfirm
         * in case confirmation email was deleted or is lost. */
        if ($subscriber->wasRecentlyCreated || $subscriber->created_at->lt($now->subDays(1))) {
            $this->dispatchConfirmationMessage->handle($workspaceId, $subscriber);
        }

        return response(null, 200);
    }

    /**
     * @throws Exception
     */
    public function confirm(SubscriberConfirmRequest $request)
    {
        $data = $request->validated();
        $workspaceId = (int) $data['workspace_id'];

        $subscriber = $this->subscribers->findByMany(
            $workspaceId,
            ['hash' => $data['hash'], 'email' => $data['email']]
        );
        if ($subscriber) {
            $subscriber->unsubscribed_at = null;
            $subscriber->save();

            // XXX add event
            /* event(new SubscriberAddedEvent($subscriber)); */

            return response(null, 200);
        }

        return response(null, 400);
    }
}
