<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Db;

use OCA\Forms\ResponseDefinitions;
use OCP\AppFramework\Db\Entity;

/**
 * @psalm-import-type FormsOption from ResponseDefinitions
 * @method int|float getQuestionId()
 * @method void setQuestionId(int|float $value)
 * @method string getText()
 * @method void setText(string $value)
 * @method int getOrder();
 * @method void setOrder(int $value)
 * @method int|null getMaxResponses()
 * @method void setMaxResponses(?int $value)
 * @method string|null getMaxResponsesMessage()
 * @method void setMaxResponsesMessage(?string $value)
 */
class Option extends Entity {

        // For 32bit PHP long integers, like IDs, are represented by floats
        protected int|float|null $questionId;
        protected ?string $text;
        protected ?int $order;
        protected ?int $maxResponses;
        protected ?string $maxResponsesMessage;

        /**
         * Option constructor.
         */
        public function __construct() {
                $this->questionId = null;
                $this->text = null;
                $this->order = null;
                $this->maxResponses = null;
                $this->maxResponsesMessage = null;
                $this->addType('questionId', 'integer');
                $this->addType('order', 'integer');
                $this->addType('text', 'string');
                $this->addType('maxResponses', 'integer');
                $this->addType('maxResponsesMessage', 'string');
        }

        /**
         * @return FormsOption
         */
        public function read(): array {
                return [
                        'id' => $this->getId(),
                        'questionId' => $this->getQuestionId(),
                        'order' => $this->getOrder(),
                        'text' => (string)$this->getText(),
                        'maxResponses' => $this->getMaxResponses(),
                        'maxResponsesMessage' => $this->getMaxResponsesMessage(),
                ];
        }
}
