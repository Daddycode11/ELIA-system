<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/reports.php';
$user = requireAdmin();

$stamp = date('Ymd');
if (($_GET['view'] ?? '') === 'agreements') {
    $rows = report_agreements(report_agreement_filters($_GET));
    report_csv_send('elia-agreements-' . $stamp . '.csv',
        ['Reference', 'Title', 'Type', 'Institution', 'Country', 'Date signed', 'Effective from', 'Effective until', 'Status'],
        array_map(static fn(array $r): array => [$r['reference_no'], $r['title'], $r['agreement_type'], $r['partner_name'], $r['country'],
            $r['signed_date'], $r['start_date'], $r['end_date'], ucfirst($r['state'])], $rows));
}

$rows = report_requests(report_request_filters($_GET));
report_csv_send('elia-requests-' . $stamp . '.csv',
    ['Reference', 'Title', 'Client', 'Request type', 'Destination', 'Country', 'Travel start', 'Travel end', 'Status', 'Submitted', 'Approved', 'Completed'],
    array_map(static fn(array $r): array => [$r['reference_no'] ?: ('#' . $r['id']), $r['title'], $r['client'], $r['type_name'], $r['destination'], $r['country'],
        $r['start_date'], $r['end_date'], request_label($r['status']), $r['submitted_at'], $r['approved_at'], $r['completed_at']], $rows));
