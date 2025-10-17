<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version050200Date20250901120000 extends SimpleMigrationStep {

        /**
         * @param IOutput $output
         * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
         * @param array $options
         * @return null|ISchemaWrapper
         */
        public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
                /** @var ISchemaWrapper $schema */
                $schema = $schemaClosure();

                if (!$schema->hasTable('forms_v2_options')) {
                        return $schema;
                }

                $table = $schema->getTable('forms_v2_options');

                if (!$table->hasColumn('max_responses')) {
                        $table->addColumn('max_responses', Types::INTEGER, [
                                'notnull' => false,
                                'default' => null,
                        ]);
                }

                if (!$table->hasColumn('max_responses_message')) {
                        $table->addColumn('max_responses_message', Types::TEXT, [
                                'notnull' => false,
                                'default' => null,
                        ]);
                }

                return $schema;
        }
}
