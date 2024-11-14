<?php

declare(strict_types=1);

namespace App\Model;

/**
 * @phpstan-type SerializedJob array{
 *   id: non-empty-string,
 *   suite_id: non-empty-string,
 *   maximum_duration_in_seconds: positive-int,
 *   created_at: positive-int
 *  }
 */
interface JobInterface extends \JsonSerializable
{
    /**
     * @return non-empty-string
     */
    public function getId(): string;

    /**
     * @return non-empty-string
     */
    public function getUserId(): string;

    /**
     * @return non-empty-string
     */
    public function getSuiteId(): string;

    /**
     * @return positive-int
     */
    public function getMaximumDurationInSeconds(): int;

    /**
     * @return SerializedJob
     */
    public function toArray(): array;

    /**
     * @return SerializedJob
     */
    public function jsonSerialize(): array;
}
