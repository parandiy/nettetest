<?php

declare(strict_types=1);

namespace App\Presentation\Api;

use App\DTO\Filter\ActivityFilter;
use App\DTO\Filter\CustomerFilter;
use App\Enum\ActivityType;
use App\Enum\CustomerSort;
use App\Enum\CustomerStatus;
use App\Enum\SortDirection;
use App\Service\ActivityService;
use App\Service\CommentService;
use App\Service\CustomerService;
use Nette;
use Nette\Application\Responses\JsonResponse;
use Nette\Application\UI\Presenter;

final class ApiPresenter extends Presenter
{
    public function __construct(
        private CustomerService $userService,
        private ActivityService $activityService,
        private CommentService $commentService,
    ) {
        parent::__construct();
    }

    protected function startup()
    {
        parent::startup();
        if (!$this->getUser()->isLoggedIn()) {
            $this->redirect('Auth:in');
        }
    }

    public function actionCustomers(
        ?string $q = null,
        ?string $is_active = null,
        ?int $page = 1,
        ?string $sort = null,
        ?string $dir = 'ASC',
    ): void {
        $filter = new CustomerFilter(
            q: $q,
            isActive: $is_active !== '' ? (boolean) $is_active : null,
            page: max(1, (int) $page),
            sort: CustomerSort::tryFrom($sort ?? '') ?? CustomerSort::NAME,
            dir: SortDirection::tryFrom(strtoupper($dir) ?? '') ?? SortDirection::ASC,
        );

        $this->sendResponse(
            new JsonResponse(
                $this->userService->getCustomers($filter)
            )
        );
    }

    public function actionActivities(
        int $id,
        ?string $q = null,
        ?string $type = null,
        ?int $page = 1
    ): void {
        $filter = new ActivityFilter(
            q: $q,
            activityType: ActivityType::tryFrom($type ?? '') ?? null,
            page: max(1, (int) $page),
            customerId: $id
        );

        $this->sendResponse(
            new JsonResponse(
                $this->activityService->getActivities($filter)
            )
        );
    }

    public function actionComments(int $activityId): void
    {
        $this->sendResponse(
            new JsonResponse(
                $this->commentService->getComments($activityId)
            )
        );
    }

    public function actionAddComment(int $activityId): void
    {
        if (!$this->getHttpRequest()->isMethod('POST')) {
            $this->error('Method not allowed', 405);
        }

        $text = trim((string) $this->getHttpRequest()->getPost('text'));

        if ($text === '') {
            $this->sendJson([
                'success' => false,
                'message' => 'Comment text is required',
            ]);
            return;
        }

        // The comment author is always the currently logged-in operator —
        // never trust an author/operator id coming from the client.
        $operatorId = (int) $this->getUser()->getId();

        try {
            $comment = $this->commentService->addComment($activityId, $operatorId, $text);
        } catch (\InvalidArgumentException $e) {
            $this->sendJson([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
            return;
        }

        $this->sendJson([
            'success' => true,
            'comment' => $comment,
        ]);
    }
}
