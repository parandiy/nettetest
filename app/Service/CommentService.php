<?php declare(strict_types=1);

namespace App\Service;

use App\Repository\CommentRepository;

final class CommentService
{
    public function __construct(
        private readonly CommentRepository $commentRepository
    ) {}

    public function getComments(int $activityId): array
    {
        return $this->commentRepository->findByActivity($activityId);
    }
}