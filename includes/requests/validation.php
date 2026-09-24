<?php
declare(strict_types=1);

function valid_request_date(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value && $value >= '1000-01-01';
}

function request_fields(array $input): array
{
    $fields = [];
    foreach (['title' => 200, 'purpose' => 10000, 'destination' => 200, 'country' => 100, 'start_date' => 10, 'end_date' => 10] as $key => $max) {
        $value = isset($input[$key]) && is_string($input[$key]) ? trim($input[$key]) : '';
        if (!mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > $max || str_contains($value, "\0")) {
            throw new DomainException('Invalid or overly long ' . request_label($key) . '.');
        }
        if (in_array($key, ['start_date', 'end_date'], true)) {
            if ($value !== '' && !valid_request_date($value)) {
                throw new DomainException('Enter a valid ' . request_label($key) . '.');
            }
            $fields[$key] = $value === '' ? null : $value;
        } else {
            $fields[$key] = $value;
        }
    }
    if ($fields['start_date'] && $fields['end_date'] && $fields['end_date'] < $fields['start_date']) {
        throw new DomainException('End date must be on or after start date.');
    }
    return $fields;
}

function request_remarks(string $remarks): string
{
    $remarks = trim($remarks);
    if (!mb_check_encoding($remarks, 'UTF-8') || mb_strlen($remarks) > 5000 || str_contains($remarks, "\0")) {
        throw new DomainException('Remarks must be valid text up to 5,000 characters.');
    }
    return $remarks;
}

function require_request_revision(array $request, int $revision): void
{
    if ((int) $request['revision'] !== $revision) {
        throw new DomainException('This request changed since you opened it. Refresh the page and try again.');
    }
}
