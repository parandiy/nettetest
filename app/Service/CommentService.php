<?php declare(strict_types=1);

namespace App\Service;

use App\DTO\CommentDto;
use App\Repository\CommentRepository;

final class CommentService
{
    public function __construct(
        private readonly CommentRepository $commentRepository,
    ) {}

    public function getComments(int $activityId): array
    {
        return $this->commentRepository->findByActivity($activityId);
    }

    /**
     * @throws \InvalidArgumentException if body is empty
     */
    public function addComment(int $activityId, int $operatorId, string $body): CommentDto
    {
        $body = trim($body);
        if ($body === '') {
            throw new \InvalidArgumentException('Comment body cannot be empty.');
        }

        return $this->commentRepository->add($activityId, $operatorId, $body);
    }
}
