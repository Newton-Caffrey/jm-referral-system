<?php

namespace JMReferral\Residents\Import;

use JMReferral\Residents\ExistingResident;
use JMReferral\Residents\ExistingResidentRegistrationService;
use JMReferral\Residents\ExistingResidentValidator;

/**
 * Phase 5F.1: two-step bulk import of existing residents.
 *
 * Step 1 (preview) reads the uploaded file on the server, checks every row and keeps the
 * checked rows for 30 minutes under a one-time token tied to the uploading user. The
 * uploaded file itself is never stored. Step 2 (confirm) registers every row in one
 * transaction, or none at all.
 */
class ResidentImportService
{
    private const TRANSIENT_PREFIX = 'jmrs_res_import_';
    private const TTL = 1800;

    public const ALLOWED_EXTENSIONS = ['csv', 'xlsx'];

    public function __construct(
        private SpreadsheetReader $reader,
        private ResidentImportParser $parser,
        private ExistingResidentValidator $validator,
        private ExistingResidentRegistrationService $registration
    ) {
    }

    /**
     * @param array<string, mixed> $file One entry from $_FILES.
     * @return array{ok: false, error: string}|array{
     *     ok: true,
     *     token: string,
     *     total: int,
     *     ready: int,
     *     rows: array<int, array{row: int, name: string, setting: string, location: string, funding: string, errors: array<int, string>, notes: array<int, string>}>,
     *     skipped_examples: int,
     *     ignored_columns: array<int, string>
     * }
     */
    public function preview(array $file, int $actor_id): array
    {
        $upload_error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if (UPLOAD_ERR_NO_FILE === $upload_error) {
            return ['ok' => false, 'error' => __('Choose the completed spreadsheet to upload.', 'jm-referral-system')];
        }
        if (UPLOAD_ERR_INI_SIZE === $upload_error || UPLOAD_ERR_FORM_SIZE === $upload_error) {
            return ['ok' => false, 'error' => $this->too_big_message()];
        }
        if (UPLOAD_ERR_OK !== $upload_error) {
            return ['ok' => false, 'error' => __('The file did not upload completely. Please try again.', 'jm-referral-system')];
        }

        $tmp  = (string) ($file['tmp_name'] ?? '');
        $name = sanitize_file_name((string) ($file['name'] ?? ''));
        $size = (int) ($file['size'] ?? 0);

        if ('' === $tmp || ! is_uploaded_file($tmp)) {
            return ['ok' => false, 'error' => __('The file did not upload completely. Please try again.', 'jm-referral-system')];
        }
        if ($size <= 0) {
            return ['ok' => false, 'error' => __('The file is empty.', 'jm-referral-system')];
        }
        if ($size > ExistingResident::MAX_IMPORT_BYTES) {
            return ['ok' => false, 'error' => $this->too_big_message()];
        }

        $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return ['ok' => false, 'error' => __('Upload the template as an Excel (.xlsx) or CSV file.', 'jm-referral-system')];
        }

        // Content must match the extension: .xlsx is a zip; a CSV must not be.
        $head = (string) file_get_contents($tmp, false, null, 0, 4);
        if ('xlsx' === $extension && "PK\x03\x04" !== $head) {
            return ['ok' => false, 'error' => __('That file is not a real Excel workbook. Save it as .xlsx or CSV and try again.', 'jm-referral-system')];
        }
        if ('csv' === $extension && "PK\x03\x04" === $head) {
            return ['ok' => false, 'error' => __('That file is an Excel workbook named .csv. Upload it with its .xlsx name.', 'jm-referral-system')];
        }

        $read = $this->reader->read($tmp, $name, ExistingResident::MAX_IMPORT_ROWS);
        if (empty($read['ok'])) {
            return ['ok' => false, 'error' => $this->read_error_message((string) ($read['error'] ?? ''))];
        }

        $parsed = $this->parser->parse($read['rows']);
        if (empty($parsed['ok'])) {
            return ['ok' => false, 'error' => (string) $parsed['error']];
        }

        if (count($parsed['rows']) > ExistingResident::MAX_IMPORT_ROWS) {
            return ['ok' => false, 'error' => $this->too_many_message()];
        }

        $checked = $this->validator->validate_batch($parsed['rows']);

        $rows  = [];
        $ready = 0;
        foreach ($parsed['rows'] as $number => $input) {
            // What could not be matched in the spreadsheet explains the problem better
            // than the follow-on "required" message, so it wins for the same field.
            $errors = array_merge($checked['errors'][$number] ?? [], $parsed['row_errors'][$number] ?? []);
            $messages = [];
            foreach ($errors as $field => $message) {
                $messages[] = $this->labelled((string) $field, (string) $message);
            }
            if ([] === $messages) {
                ++$ready;
            }

            $display = $parsed['display'][$number] ?? [];
            $rows[]  = [
                'row'      => (int) $number,
                'name'     => (string) ($display['name'] ?? ''),
                'setting'  => (string) ($display['setting'] ?? ''),
                'location' => (string) ($display['location'] ?? ''),
                'funding'  => (string) ($display['funding'] ?? ''),
                'errors'   => $messages,
                'notes'    => $parsed['row_notes'][$number] ?? [],
            ];
        }

        $token = '';
        if ($ready === count($rows)) {
            $token = wp_generate_password(32, false, false);
            set_transient(
                $this->transient_key($token, $actor_id),
                [
                    'user_id' => $actor_id,
                    'rows'    => $parsed['rows'],
                    'file'    => $name,
                ],
                self::TTL
            );
        }

        return [
            'ok'               => true,
            'token'            => $token,
            'total'            => count($rows),
            'ready'            => $ready,
            'rows'             => $rows,
            'skipped_examples' => (int) $parsed['skipped_examples'],
            'ignored_columns'  => $parsed['ignored_columns'],
        ];
    }

    /**
     * @return array{ok: true, created: array<int, array{row: int, id: int, referral_number: string, name: string}>, batch: string}
     *     |array{ok: false, error: string, message: string, row_errors: array<int, array<int, string>>}
     */
    public function confirm(string $token, int $actor_id): array
    {
        $token = preg_replace('/[^A-Za-z0-9]/', '', $token) ?? '';
        $key   = $this->transient_key($token, $actor_id);
        $saved = '' !== $token ? get_transient($key) : false;

        if (! is_array($saved) || (int) ($saved['user_id'] ?? 0) !== $actor_id || ! is_array($saved['rows'] ?? null)) {
            return [
                'ok'         => false,
                'error'      => 'expired',
                'message'    => __('This import has expired or was already used. Upload the spreadsheet again.', 'jm-referral-system'),
                'row_errors' => [],
            ];
        }

        $batch  = 'import-' . gmdate('Ymd-His') . '-' . strtolower(wp_generate_password(6, false, false));
        $result = $this->registration->register_many($saved['rows'], $actor_id, $batch);

        if (! empty($result['ok'])) {
            delete_transient($key);

            return ['ok' => true, 'created' => $result['created'], 'batch' => $batch];
        }

        $row_errors = [];
        foreach ((array) ($result['row_errors'] ?? []) as $number => $errors) {
            foreach ((array) $errors as $field => $message) {
                $row_errors[(int) $number][] = $this->labelled((string) $field, (string) $message);
            }
        }

        return [
            'ok'         => false,
            'error'      => (string) ($result['error'] ?? 'failed'),
            'message'    => (string) ($result['message'] ?? ''),
            'row_errors' => $row_errors,
        ];
    }

    public function discard(string $token, int $actor_id): void
    {
        $token = preg_replace('/[^A-Za-z0-9]/', '', $token) ?? '';
        if ('' !== $token) {
            delete_transient($this->transient_key($token, $actor_id));
        }
    }

    /**
     * Spreadsheet column heading for a validator field.
     */
    public function column_label(string $field): string
    {
        $defs = ResidentImportColumns::definitions();
        $map  = [
            'bedroom_id'         => __('Property / Bedroom', 'jm-referral-system'),
            'service_type_id'    => $defs['service']['heading'],
            'assigned_to'        => $defs['owner']['heading'],
            'local_authority_id' => $defs['funding_authority']['heading'],
            'funder_name'        => $defs['funding_authority']['heading'],
        ];

        if (isset($map[$field])) {
            return $map[$field];
        }

        return isset($defs[$field]) ? $defs[$field]['heading'] : ucfirst(str_replace('_', ' ', $field));
    }

    /**
     * Prefixes a message with its column, unless the message already names it.
     */
    private function labelled(string $field, string $message): string
    {
        $label = $this->column_label($field);

        return 0 === stripos($message, $label) ? $message : $label . ': ' . $message;
    }

    private function transient_key(string $token, int $actor_id): string
    {
        return self::TRANSIENT_PREFIX . md5($token . '|' . $actor_id);
    }

    private function read_error_message(string $code): string
    {
        return match ($code) {
            'too_many'    => $this->too_many_message(),
            'unsupported' => __('This server cannot read Excel files. Save the spreadsheet as CSV and upload that instead.', 'jm-referral-system'),
            'type'        => __('Upload the template as an Excel (.xlsx) or CSV file.', 'jm-referral-system'),
            default       => __('The spreadsheet could not be read. Save it again as .xlsx or CSV and try again.', 'jm-referral-system'),
        };
    }

    private function too_many_message(): string
    {
        return sprintf(
            /* translators: %d: maximum rows */
            __('A single import can register up to %d residents. Split the spreadsheet and import it in parts.', 'jm-referral-system'),
            ExistingResident::MAX_IMPORT_ROWS
        );
    }

    private function too_big_message(): string
    {
        return sprintf(
            /* translators: %s: size such as 1 MB */
            __('The file is larger than %s. Remove unused sheets or save it as CSV.', 'jm-referral-system'),
            size_format(ExistingResident::MAX_IMPORT_BYTES)
        );
    }
}
