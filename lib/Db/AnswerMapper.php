<?php

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<Answer>
 */
class AnswerMapper extends QBMapper {

	/**
	 * AnswerMapper constructor.
	 * @param IDBConnection $db
	 */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'forms_v2_answers', Answer::class);
	}

	/**
	 * @param int $submissionId
	 * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
	 * @return Answer[]
	 */
	public function findBySubmission(int $submissionId): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('submission_id', $qb->createNamedParameter($submissionId, IQueryBuilder::PARAM_INT))
			);

		return $this->findEntities($qb);
	}

	/**
	 * @param int $submissionId
	 */
        public function deleteBySubmission(int $submissionId): void {
                $qb = $this->db->getQueryBuilder();

                $qb->delete($this->getTableName())
                        ->where(
                                $qb->expr()->eq('submission_id', $qb->createNamedParameter($submissionId, IQueryBuilder::PARAM_INT))
                        );

                $qb->executeStatement();
        }

        /**
         * Count how often the provided option ids are used for the given question.
         *
         * @param int $questionId The question the options belong to
         * @param list<int> $optionIds The option ids to count answers for
         * @param int|null $excludeSubmissionId Optionally exclude answers that belong to this submission
         * @return array<int, int> A map of option id => usage count
         */
        public function countByOptionIds(int $questionId, array $optionIds, ?int $excludeSubmissionId = null): array {
                if ($optionIds === []) {
                        return [];
                }

                $qb = $this->db->getQueryBuilder();

                $qb->select('text')
                        ->addSelect('COUNT(*) AS answer_count')
                        ->from($this->getTableName())
                        ->where(
                                $qb->expr()->eq('question_id', $qb->createNamedParameter($questionId, IQueryBuilder::PARAM_INT)),
                        )
                        ->andWhere(
                                $qb->expr()->in('text', $qb->createNamedParameter(
                                        array_map(static fn (int $optionId) => (string)$optionId, $optionIds),
                                        IQueryBuilder::PARAM_STR_ARRAY,
                                )),
                        )
                        ->groupBy('text');

                if ($excludeSubmissionId !== null) {
                        $qb->andWhere(
                                $qb->expr()->neq('submission_id', $qb->createNamedParameter($excludeSubmissionId, IQueryBuilder::PARAM_INT)),
                        );
                }

                $results = $qb->executeQuery()->fetchAllAssociative();

                $counts = [];
                foreach ($results as $row) {
                        $optionId = (int)$row['text'];
                        $counts[$optionId] = (int)$row['answer_count'];
                }

                return $counts;
        }
}
