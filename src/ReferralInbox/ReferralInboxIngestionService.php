<?php

namespace JMReferral\ReferralInbox;

/**
 * Provider-neutral Referral Inbox ingestion gateway (Phase 5B.4).
 *
 * Future mailbox connectors construct {@see InboundMessage} and call {@see ingest()}.
 * Orchestrates {@see ReferralInboxService} only — no repository/identity access,
 * no Graph/Gmail, no detection, no Local Authority matching, no referral creation.
 *
 * attachment_count semantics (preserved from 5B.2):
 * source-declared count from InboundMessage::declared_attachment_count().
 * Metadata row count may temporarily differ (partial retrieval / reconciliation).
 * EXISTING Inbox rows are NOT updated to rewrite attachment_count.
 *
 * Attachment reconciliation on EXISTING:
 * missing provider_attachment_id metadata is added; existing metadata is not duplicated.
 *
 * Partial failure:
 * if the Inbox item is created/found but one or more attachments fail, returns PARTIAL
 * and does not delete the Inbox row. Retry by replaying the same message identity.
 */
class ReferralInboxIngestionService
{
    public function __construct(
        private ReferralInboxService $inbox_service
    ) {
    }

    /**
     * @return array{
     *   result: string,
     *   inbox_id?: int,
     *   inbox_result?: string,
     *   item?: array<string, mixed>|null,
     *   attachments_attempted?: int,
     *   attachments_created?: int,
     *   attachments_existing?: int,
     *   attachments_failed?: int,
     *   attachment_results?: array<int, array<string, mixed>>,
     *   errors?: array<string, string>
     * }
     */
    public function ingest(InboundMessage $message): array
    {
        $create = $this->inbox_service->create($message->to_create_input());
        $create_result = (string) ($create['result'] ?? '');

        if (ReferralInboxResult::VALIDATION_ERROR === $create_result) {
            return [
                'result'       => ReferralInboxIngestionResult::VALIDATION_ERROR,
                'inbox_result' => $create_result,
                'errors'       => is_array($create['errors'] ?? null) ? $create['errors'] : [],
            ];
        }

        if (ReferralInboxResult::PERSISTENCE_ERROR === $create_result) {
            return [
                'result'       => ReferralInboxIngestionResult::PERSISTENCE_ERROR,
                'inbox_result' => $create_result,
                'errors'       => is_array($create['errors'] ?? null) ? $create['errors'] : [
                    'general' => __('Unable to create Inbox item.', 'jm-referral-system'),
                ],
            ];
        }

        if (! in_array($create_result, [ReferralInboxResult::CREATED, ReferralInboxResult::EXISTING], true)) {
            return [
                'result'       => ReferralInboxIngestionResult::PERSISTENCE_ERROR,
                'inbox_result' => $create_result,
                'errors'       => [
                    'general' => __('Unexpected Inbox create result.', 'jm-referral-system'),
                ],
            ];
        }

        $item     = is_array($create['item'] ?? null) ? $create['item'] : null;
        $inbox_id = absint($item['id'] ?? 0);
        if ($inbox_id <= 0) {
            return [
                'result'       => ReferralInboxIngestionResult::PERSISTENCE_ERROR,
                'inbox_result' => $create_result,
                'errors'       => [
                    'general' => __('Inbox item was not returned after create.', 'jm-referral-system'),
                ],
            ];
        }

        $attachments = $message->attachments();
        $attempted   = count($attachments);
        $created     = 0;
        $existing    = 0;
        $failed      = 0;
        $attachment_results = [];
        $attachment_errors  = [];

        foreach ($attachments as $index => $attachment) {
            $att_result = $this->inbox_service->addAttachmentMetadata(
                $inbox_id,
                $attachment->to_service_input()
            );
            $code = (string) ($att_result['result'] ?? '');

            $row = [
                'index'                  => $index,
                'provider_attachment_id' => $attachment->provider_attachment_id(),
                'result'                 => $code,
            ];

            if (ReferralInboxResult::CREATED === $code) {
                ++$created;
                $row['attachment_id'] = absint($att_result['attachment']['id'] ?? 0);
            } elseif (ReferralInboxResult::EXISTING === $code) {
                ++$existing;
                $row['attachment_id'] = absint($att_result['attachment']['id'] ?? 0);
            } else {
                ++$failed;
                $safe_errors = [];
                if (is_array($att_result['errors'] ?? null)) {
                    foreach ($att_result['errors'] as $field => $msg) {
                        if (is_string($msg)) {
                            $safe_errors[(string) $field] = $msg;
                        }
                    }
                }
                $row['errors'] = $safe_errors;
                foreach ($safe_errors as $field => $msg) {
                    $attachment_errors['attachments.' . $index . '.' . $field] = $msg;
                }
                if ([] === $safe_errors) {
                    $attachment_errors['attachments.' . $index] = __('Attachment metadata could not be saved.', 'jm-referral-system');
                }
            }

            $attachment_results[] = $row;
        }

        // Refresh item for caller (status/detection unchanged by ingestion attachments).
        $fresh = $this->inbox_service->find($inbox_id);

        $outcome = ReferralInboxResult::CREATED === $create_result
            ? ReferralInboxIngestionResult::CREATED
            : ReferralInboxIngestionResult::EXISTING;

        if ($failed > 0) {
            $outcome = ReferralInboxIngestionResult::PARTIAL;
        }

        $response = [
            'result'                 => $outcome,
            'inbox_id'               => $inbox_id,
            'inbox_result'           => $create_result,
            'item'                   => $fresh,
            'attachments_attempted'  => $attempted,
            'attachments_created'    => $created,
            'attachments_existing'   => $existing,
            'attachments_failed'     => $failed,
            'attachment_results'     => $attachment_results,
        ];

        if ([] !== $attachment_errors) {
            $response['errors'] = $attachment_errors;
        }

        return $response;
    }
}
