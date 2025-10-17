<!--
  - SPDX-FileCopyrightText: 2020 John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<li class="question__item" @focusout="handleTabbing">
		<component
			:is="pseudoIcon"
			v-if="!isDropdown"
			class="question__item__pseudoInput" />
		<input
			ref="input"
			:aria-label="ariaLabel"
			:placeholder="placeholder"
			:value="answer.text"
			class="question__input"
			:class="{ 'question__input--shifted': !isDropdown }"
			:maxlength="maxOptionLength"
			type="text"
			dir="auto"
			@input="debounceOnInput"
			@keydown.delete="deleteEntry"
			@keydown.enter.prevent="focusNextInput"
			@compositionstart="onCompositionEnd"
			@compositionend="onCompositionEnd" />

		<!-- Actions for reordering and deleting the option  -->
                <div v-if="!answer.local" class="option__actions">
                        <NcActions
                                :id="optionDragMenuId"
                                :container="`#${optionDragMenuId}`"
                                :aria-label="t('forms', 'Move option actions')"
                                class="option__drag-handle"
				variant="tertiary-no-background">
				<template #icon>
					<IconDragIndicator :size="20" />
				</template>
				<NcActionButton
					ref="buttonOptionUp"
					:disabled="index === 0"
					@click="onMoveUp">
					<template #icon>
						<IconArrowUp :size="20" />
					</template>
					{{ t('forms', 'Move option up') }}
				</NcActionButton>
				<NcActionButton
					ref="buttonOptionDown"
					:disabled="index === maxIndex"
					@click="onMoveDown">
					<template #icon>
						<IconArrowDown :size="20" />
					</template>
                                        {{ t('forms', 'Move option down') }}
                                </NcActionButton>
                        </NcActions>
                        <NcButton
                                :aria-label="limitButtonLabel"
                                variant="tertiary"
                                @click="openLimitDialog">
                                <template #icon>
                                        <IconCounter :size="20" />
                                </template>
                        </NcButton>
                        <NcButton
                                :aria-label="t('forms', 'Delete answer')"
                                variant="tertiary"
                                @click="deleteEntry">
                                <template #icon>
					<IconDelete :size="20" />
				</template>
			</NcButton>
                </div>
                <div v-if="!answer.local && hasLimit" class="option__limit-info">
                        <NcBadge class="option__limit-info-badge" type="warning">
                                {{ limitUsageLabel }}
                        </NcBadge>
                        <span v-if="isLimitReached" class="option__limit-info-message">
                                {{ displayLimitMessage }}
                        </span>
                </div>

                <NcDialog
                        v-if="!answer.local"
                        :open.sync="isLimitDialogOpen"
                        :name="t('forms', 'Limit responses for option')"
                        close-on-click-outside
                        @update:open="onDialogToggle">
                        <form class="option__limit-dialog" @submit.prevent="saveLimit">
                                <NcTextField
                                        :value="limitValue"
                                        type="number"
                                        min="1"
                                        :label="t('forms', 'Maximum responses for this option')"
                                        :placeholder="t('forms', 'Unlimited')"
                                        :helper-text="t('forms', 'Leave empty to remove the limit')"
                                        @update:value="onLimitValueChange" />
                                <label class="option__limit-dialog-label" for="option-limit-message">
                                        {{ t('forms', 'Message shown when the limit is reached') }}
                                </label>
                                <textarea
                                        id="option-limit-message"
                                        v-model="limitMessage"
                                        class="option__limit-dialog-textarea"
                                        rows="3"
                                        :placeholder="t('forms', 'Requested response limit reached')" />
                                <div class="option__limit-dialog-actions">
                                        <NcButton type="button" variant="secondary" @click="closeLimitDialog">
                                                {{ t('forms', 'Cancel') }}
                                        </NcButton>
                                        <NcButton type="submit" variant="primary">
                                                {{ t('forms', 'Save') }}
                                        </NcButton>
                                </div>
                        </form>
                </NcDialog>
        </li>
</template>

<script>
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { generateOcsUrl } from '@nextcloud/router'
import debounce from 'debounce'
import PQueue from 'p-queue'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcBadge from '@nextcloud/vue/components/NcBadge'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import IconArrowDown from 'vue-material-design-icons/ArrowDown.vue'
import IconArrowUp from 'vue-material-design-icons/ArrowUp.vue'
import IconCheckboxBlankOutline from 'vue-material-design-icons/CheckboxBlankOutline.vue'
import IconRadioboxBlank from 'vue-material-design-icons/RadioboxBlank.vue'
import IconDelete from 'vue-material-design-icons/TrashCanOutline.vue'
import IconDragIndicator from '../Icons/IconDragIndicator.vue'
import IconCounter from 'vue-material-design-icons/Counter.vue'
import { INPUT_DEBOUNCE_MS } from '../../models/Constants.ts'
import logger from '../../utils/Logger.js'
import OcsResponse2Data from '../../utils/OcsResponse2Data.js'

export default {
	name: 'AnswerInput',

	components: {
		IconArrowDown,
                IconArrowUp,
                IconCheckboxBlankOutline,
                IconDelete,
                IconCounter,
                IconDragIndicator,
                IconRadioboxBlank,
                NcActions,
                NcActionButton,
                NcBadge,
                NcButton,
                NcDialog,
                NcTextField,
        },

	props: {
		answer: {
			type: Object,
			required: true,
		},

		index: {
			type: Number,
			required: true,
		},

		formId: {
			type: Number,
			required: true,
		},

		isUnique: {
			type: Boolean,
			required: true,
		},

		isDropdown: {
			type: Boolean,
			default: false,
		},

		maxIndex: {
			type: Number,
			required: true,
		},

		maxOptionLength: {
			type: Number,
			required: true,
		},
	},

	emits: [
		'tabbed-out',
		'create-answer',
		'update:answer',
		'focus-next',
		'delete',
		'move-down',
		'move-up',
	],

	data() {
                return {
                        queue: null,
                        debounceOnInput: null,
                        isIMEComposing: false,
                        isLimitDialogOpen: false,
                        limitValue: '',
                        limitMessage: '',
                }
        },

        computed: {
		ariaLabel() {
			if (this.answer.local) {
				return t('forms', 'Add a new answer option')
			}
			return t('forms', 'The text of option {index}', {
				index: this.index + 1,
			})
		},

		optionDragMenuId() {
			return `q${this.answer.questionId}o${this.answer.id}__drag_menu`
		},

                placeholder() {
                        if (this.answer.local) {
                                return t('forms', 'Add a new answer option')
                        }
                        return t('forms', 'Answer number {index}', { index: this.index + 1 })
                },

                pseudoIcon() {
                        return this.isUnique ? IconRadioboxBlank : IconCheckboxBlankOutline
                },

                hasLimit() {
                        return (this.answer?.maxResponses ?? null) !== null
                },

                isLimitReached() {
                        if (!this.answer?.maxResponses) {
                                return false
                        }
                        const responsesCount = this.answer.responsesCount ?? 0
                        return responsesCount >= this.answer.maxResponses
                },

                limitUsageLabel() {
                        if (!this.answer?.maxResponses) {
                                return ''
                        }
                        const responsesCount = this.answer.responsesCount ?? 0
                        return t('forms', '{used} of {max} responses used', {
                                used: responsesCount,
                                max: this.answer.maxResponses,
                        })
                },

                displayLimitMessage() {
                        if (this.answer?.maxResponsesMessage) {
                                return this.answer.maxResponsesMessage
                        }
                        return t('forms', 'Requested response limit reached')
                },

                limitButtonLabel() {
                        return this.answer?.maxResponses
                                ? t('forms', 'Edit response limit for this option')
                                : t('forms', 'Set response limit for this option')
                },
        },

        created() {
                this.queue = new PQueue({ concurrency: 1 })

		// As data instead of method, to have a separate debounce per AnswerInput
		this.debounceOnInput = debounce((event) => {
			return this.queue.add(() => this.onInput(event))
		}, INPUT_DEBOUNCE_MS)
	},

	methods: {
		handleTabbing() {
			this.$emit('tabbed-out')
		},

		/**
		 * Focus the input
		 */
		focus() {
			this.$refs.input?.focus()
		},

		/**
		 * Option changed, processing the data
		 *
		 * @param {InputEvent} event The input event that triggered adding a new entry
		 */
		async onInput({ target, isComposing }) {
			if (!isComposing && !this.isIMEComposing && target.value !== '') {
				// clone answer
				const answer = { ...this.answer }
				answer.text = this.$refs.input.value

				if (this.answer.local) {
					// Dispatched for creation. Marked as synced
					this.$set(this.answer, 'local', false)
					const newAnswer = await this.createAnswer(answer)

					// Forward changes, but use current answer.text to avoid erasing
					// any in-between changes while creating the answer
					newAnswer.text = this.$refs.input.value

					this.$emit('create-answer', this.index, newAnswer)
				} else {
					await this.updateAnswer(answer)

					// Forward changes, but use current answer.text to avoid erasing
					// any in-between changes while updating the answer
					answer.text = this.$refs.input.value
					this.$emit('update:answer', this.index, answer)
				}
			}
		},

		/**
		 * Request a new answer
		 */
		focusNextInput() {
			if (this.index <= this.maxIndex) {
				this.$emit('focus-next', this.index)
			}
		},

		/**
		 * Emit a delete request for this answer
		 * when pressing the delete key on an empty input
		 *
		 * @param {Event} e the event
		 */
		async deleteEntry(e) {
			if (this.answer.local) {
				return
			}

			if (e.type !== 'click' && this.$refs.input.value.length !== 0) {
				return
			}

			// Dismiss delete key action
			e.preventDefault()

			// do this in queue to prevent race conditions between PATCH and DELETE
			this.queue.add(() => {
				this.$emit('delete', this.answer.id)
				// Prevent any patch requests
				this.queue.pause()
				this.queue.clear()
			})
		},

		/**
		 * Create an unsynced answer to the server
		 *
		 * @param {object} answer the answer to sync
		 * @return {object} answer
		 */
		async createAnswer(answer) {
			try {
				const response = await axios.post(
					generateOcsUrl(
						'apps/forms/api/v3/forms/{id}/questions/{questionId}/options',
						{
							id: this.formId,
							questionId: answer.questionId,
						},
					),
					{
						optionTexts: [answer.text],
					},
				)
				logger.debug('Created answer', { answer })

				// Was synced once, this is now up to date with the server
				delete answer.local
				return OcsResponse2Data(response)[0]
			} catch (error) {
				logger.error('Error while saving answer', { answer, error })
				showError(t('forms', 'Error while saving the answer'))
			}

			return answer
		},

		/**
		 * Save to the server, only do it after 500ms
		 * of no change
		 *
		 * @param {object} answer the answer to sync
		 */
                async updateAnswer(answer) {
                        try {
                                await this.patchOptionData(answer.questionId, answer.id, {
                                        text: answer.text,
                                })
                                logger.debug('Updated answer', { answer })
                        } catch (error) {
                                logger.error('Error while saving answer', { answer, error })
                                showError(t('forms', 'Error while saving the answer'))
                        }
                },

                /**
                 * Reorder option but keep focus on the button
                 */
		onMoveDown() {
			this.$emit('move-down')
			this.focusButton(
				this.index < this.maxIndex - 1
					? 'buttonOptionDown'
					: 'buttonOptionUp',
			)
		},

		onMoveUp() {
			this.$emit('move-up')
			this.focusButton(this.index > 1 ? 'buttonOptionUp' : 'buttonOptionDown')
		},

		focusButton(refName) {
			this.$nextTick(() => this.$refs[refName].$el.focus())
		},

		/**
		 * Handle composition start event for IME inputs
		 */
		onCompositionStart() {
			this.isIMEComposing = true
		},

		/**
		 * Handle composition end event for IME inputs
		 *
		 * @param {CompositionEvent} event The input event that triggered adding a new entry
		 */
                onCompositionEnd({ target, isComposing }) {
                        this.isIMEComposing = false
                        if (!isComposing) {
                                this.onInput({ target, isComposing })
                        }
                },

                async patchOptionData(questionId, optionId, keyValuePairs) {
                        await axios.patch(
                                generateOcsUrl(
                                        'apps/forms/api/v3/forms/{id}/questions/{questionId}/options/{optionId}',
                                        {
                                                id: this.formId,
                                                questionId,
                                                optionId,
                                        },
                                ),
                                { keyValuePairs },
                        )
                },

                openLimitDialog() {
                        this.limitValue = this.answer?.maxResponses ? this.answer.maxResponses.toString() : ''
                        this.limitMessage = this.answer?.maxResponsesMessage ?? ''
                        this.isLimitDialogOpen = true
                },

                closeLimitDialog() {
                        this.isLimitDialogOpen = false
                },

                onDialogToggle(open) {
                        if (!open) {
                                this.closeLimitDialog()
                        }
                },

                onLimitValueChange(value) {
                        this.limitValue = value?.toString?.() ?? ''
                },

                async saveLimit() {
                        const trimmedLimit = this.limitValue?.toString().trim()
                        const hasLimitValue = trimmedLimit !== ''
                        let parsedLimit = null

                        if (hasLimitValue) {
                                parsedLimit = Number.parseInt(trimmedLimit, 10)
                                if (!Number.isFinite(parsedLimit) || parsedLimit <= 0) {
                                        showError(t('forms', 'Please enter a valid limit.'))
                                        return
                                }
                        }

                        const payload = {
                                maxResponses: parsedLimit,
                                maxResponsesMessage: this.limitMessage?.trim() || null,
                        }

                        const updatedAnswer = {
                                ...this.answer,
                                maxResponses: parsedLimit,
                                maxResponsesMessage: payload.maxResponsesMessage,
                        }

                        try {
                                await this.patchOptionData(this.answer.questionId, this.answer.id, payload)
                                this.$emit('update:answer', this.index, updatedAnswer)
                                this.isLimitDialogOpen = false
                        } catch (error) {
                                logger.error('Error while saving limit', { payload, error })
                                showError(t('forms', 'Error while saving the answer'))
                        }
                },
        },
}
</script>

<style lang="scss" scoped>
.question__item {
        position: relative;
        display: inline-flex;
        min-height: var(--default-clickable-area);
        width: 100%;

	&__pseudoInput {
		color: var(--color-primary-element);
		margin-inline-start: -2px;
		z-index: 1;
	}

	.option__actions {
		display: flex;
		position: absolute;
		gap: var(--default-grid-baseline);
		inset-inline-end: 12px;
		height: 100%;
	}

	.option__drag-handle,
	.drag-indicator-icon {
		color: var(--color-text-maxcontrast);
		cursor: grab;
		margin-block: auto;

		&:hover,
		&:focus,
		&:focus-within {
			color: var(--color-main-text);
		}

		&:active {
			cursor: grabbing;
		}

		> * {
			cursor: grab;
		}
	}

	.question__input {
		width: calc(100% - var(--default-clickable-area));
		position: relative;
		inset-inline-start: -12px;
		margin-inline-end: -12px !important;

		&--shifted {
			inset-inline-start: calc(-1 * var(--default-clickable-area));
			padding-inline-start: calc(
				var(--default-clickable-area) + var(--default-grid-baseline)
			) !important;
                }
        }
}

.option__limit-info {
        display: flex;
        align-items: center;
        gap: var(--default-grid-baseline);
        margin-inline-start: calc(var(--default-grid-baseline) * 2);
        margin-block-start: 4px;
}

.option__limit-info-badge {
        white-space: nowrap;
}

.option__limit-info-message {
        color: var(--color-error);
}

.option__limit-dialog {
        display: flex;
        flex-direction: column;
        gap: var(--default-grid-baseline);
        width: min(420px, 100%);
}

.option__limit-dialog-label {
        font-weight: 600;
}

.option__limit-dialog-textarea {
        width: 100%;
        border-radius: var(--border-radius-large);
        border: 1px solid var(--color-border);
        padding: calc(var(--default-grid-baseline) / 2);
        resize: vertical;
        font: inherit;
        color: inherit;
        background-color: var(--color-main-background);
}

.option__limit-dialog-textarea:focus {
        outline: 2px solid var(--color-primary-element);
}

.option__limit-dialog-actions {
        display: flex;
        justify-content: flex-end;
        gap: var(--default-grid-baseline);
}
</style>
